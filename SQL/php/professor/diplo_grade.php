<?php
session_start();
include("db_connect.php");
include("connected.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ------------------ Μόνο professor ------------------
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

$user = Professor_Connected($connection);
$profUserId = (int)($user['professor_user_id'] ?? $user['professor_id'] ?? 0);
if ($profUserId <= 0) die("Δεν βρέθηκε professor id.");

$diplo_id = (int)($_GET['diplo_id'] ?? 0);
if ($diplo_id <= 0) die("Μη έγκυρο diplo_id.");

$message = "";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ------------------ Helpers ------------------
function clamp_score($v) {
    $v = str_replace(",", ".", (string)$v);
    if (!is_numeric($v)) return null;
    $f = (float)$v;
    if ($f < 0) $f = 0;
    if ($f > 10) $f = 10;
    return $f;
}
function safe_prof_name($r) {
    return trim(($r['professor_surname'] ?? '') . " " . ($r['professor_name'] ?? ''));
}

// ------------------ Φέρνουμε ΔΕ + trimelous + επιβλέπων ------------------
$stmt = $connection->prepare("
    SELECT
      d.diplo_id,
      d.diplo_title,
      d.diplo_status,
      d.grading_enabled,
      d.diplo_professor,

      t.trimelous_professor1,
      t.trimelous_professor2,
      t.trimelous_professor3

    FROM diplo d
    LEFT JOIN trimelous t ON t.diplo_id = d.diplo_id
    WHERE d.diplo_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $diplo_id);
$stmt->execute();
$diplo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$diplo) die("Δεν βρέθηκε διπλωματική εργασία.");

// ------------------ Έλεγχος συμμετοχής καθηγητή ------------------
$isSupervisor = ((int)($diplo['diplo_professor'] ?? 0) === $profUserId);

$p1 = (int)($diplo['trimelous_professor1'] ?? 0);
$p2 = (int)($diplo['trimelous_professor2'] ?? 0);
$p3 = (int)($diplo['trimelous_professor3'] ?? 0);

$isMember = ($profUserId === $p1 || $profUserId === $p2 || $profUserId === $p3);

if (!$isSupervisor && !$isMember) {
    die("❌ Δεν έχετε δικαίωμα πρόσβασης σε αυτή τη βαθμολόγηση.");
}

// ✅ grading enabled (σωστό)
$gradingEnabled = ((int)($diplo['grading_enabled'] ?? 0) === 1);

if (!$gradingEnabled) {
    // fallback: αν υπάρχει trimelis_grades row
    $chkG = $connection->prepare("SELECT diplo_id FROM trimelis_grades WHERE diplo_id=? LIMIT 1");
    $chkG->bind_param("i", $diplo_id);
    $chkG->execute();
    $gradingEnabled = (bool)$chkG->get_result()->fetch_assoc();
    $chkG->close();
}

// ------------------ Φέρνουμε τα δικά μου κριτήρια (αν υπάρχουν) ------------------
$my = null;
$stmtMy = $connection->prepare("
    SELECT quality_goals, time_interval, text_quality, Presentation
    FROM grade_criteria
    WHERE diplo_id = ? AND professor_user_id = ?
    LIMIT 1
");
$stmtMy->bind_param("ii", $diplo_id, $profUserId);
$stmtMy->execute();
$my = $stmtMy->get_result()->fetch_assoc();
$stmtMy->close();

// ------------------ Αποθήκευση κριτηρίων ------------------
if (isset($_POST['save_grade'])) {

    if (!$gradingEnabled) {
        $message = "❌ Η βαθμολόγηση δεν έχει ενεργοποιηθεί από τον επιβλέποντα (μέσα από τις λεπτομέρειες της διπλωματικής).";
    } else {

        $qg  = clamp_score($_POST['quality_goals'] ?? null);
        $ti  = clamp_score($_POST['time_interval'] ?? null);
        $tq  = clamp_score($_POST['text_quality'] ?? null);
        $pre = clamp_score($_POST['Presentation'] ?? null);

        if ($qg === null || $ti === null || $tq === null || $pre === null) {
            $message = "⚠ Συμπλήρωσε όλους τους βαθμούς (0-10).";
        } else {

            // 1) αποθήκευση κριτηρίων
            $stmtUp = $connection->prepare("
                INSERT INTO grade_criteria (diplo_id, professor_user_id, quality_goals, time_interval, text_quality, Presentation)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                  quality_goals = VALUES(quality_goals),
                  time_interval = VALUES(time_interval),
                  text_quality  = VALUES(text_quality),
                  Presentation  = VALUES(Presentation)
            ");
            $stmtUp->bind_param("iidddd", $diplo_id, $profUserId, $qg, $ti, $tq, $pre);
            $stmtUp->execute();
            $stmtUp->close();

            // 2) Υπολογισμός τελικού βαθμού καθηγητή (μέσος όρος 4 κριτηρίων)
            $prof_avg = round(($qg + $ti + $tq + $pre) / 4.0, 2);

            // 3) Γράψιμο στον πίνακα trimelis_grades ΜΟΝΟ αν είναι μέλος τριμελούς
            $col = null;
            if ($profUserId === $p1) $col = 'trimelis_professor1_grade';
            elseif ($profUserId === $p2) $col = 'trimelis_professor2_grade';
            elseif ($profUserId === $p3) $col = 'trimelis_professor3_grade';

            if ($col !== null) {
                // ensure row exists
                $ins = $connection->prepare("
                    INSERT INTO trimelis_grades (diplo_id)
                    VALUES (?)
                    ON DUPLICATE KEY UPDATE diplo_id = diplo_id
                ");
                $ins->bind_param("i", $diplo_id);
                $ins->execute();
                $ins->close();

                // update member grade
                $sqlUpd = "UPDATE trimelis_grades SET $col = ? WHERE diplo_id = ?";
                $upd = $connection->prepare($sqlUpd);
                $upd->bind_param("di", $prof_avg, $diplo_id);
                $upd->execute();
                $upd->close();

                // 4) Αν υπάρχουν και οι 3 -> υπολόγισε τελικό
                $g = $connection->prepare("
                    SELECT trimelis_professor1_grade, trimelis_professor2_grade, trimelis_professor3_grade
                    FROM trimelis_grades
                    WHERE diplo_id = ?
                    LIMIT 1
                ");
                $g->bind_param("i", $diplo_id);
                $g->execute();
                $gr = $g->get_result()->fetch_assoc();
                $g->close();

                $g1 = ($gr['trimelis_professor1_grade'] !== null) ? (float)$gr['trimelis_professor1_grade'] : null;
                $g2 = ($gr['trimelis_professor2_grade'] !== null) ? (float)$gr['trimelis_professor2_grade'] : null;
                $g3 = ($gr['trimelis_professor3_grade'] !== null) ? (float)$gr['trimelis_professor3_grade'] : null;

                if ($g1 !== null && $g2 !== null && $g3 !== null) {
                    $final = round(($g1 + $g2 + $g3) / 3.0, 2);

                    $uf = $connection->prepare("
                        UPDATE trimelis_grades
                        SET trimelis_final_grade = ?
                        WHERE diplo_id = ?
                    ");
                    $uf->bind_param("di", $final, $diplo_id);
                    $uf->execute();
                    $uf->close();

                    // γράφουμε και στο diplo
                    $ud = $connection->prepare("
                        UPDATE diplo
                        SET diplo_grade = ?
                        WHERE diplo_id = ?
                    ");
                    $ud->bind_param("di", $final, $diplo_id);
                    $ud->execute();
                    $ud->close();
                }
            }

            header("Location: diplo_grade.php?diplo_id=$diplo_id&msg=" . urlencode("✅ Ο βαθμός αποθηκεύτηκε."));
            exit;
        }
    }
}

if (isset($_GET['msg'])) $message = $_GET['msg'];

// ------------------ Φέρνουμε όλους τους βαθμούς ανά κριτήριο ------------------
$allCriteria = [];
$stmtAll = $connection->prepare("
    SELECT
      gc.professor_user_id,
      p.professor_name,
      p.professor_surname,
      gc.quality_goals,
      gc.time_interval,
      gc.text_quality,
      gc.Presentation
    FROM grade_criteria gc
    LEFT JOIN professor p ON p.professor_user_id = gc.professor_user_id
    WHERE gc.diplo_id = ?
    ORDER BY p.professor_surname, p.professor_name
");
$stmtAll->bind_param("i", $diplo_id);
$stmtAll->execute();
$resAll = $stmtAll->get_result();
while ($r = $resAll->fetch_assoc()) $allCriteria[] = $r;
$stmtAll->close();

// ------------------ Φέρνουμε συνοπτικούς βαθμούς τριμελούς ------------------
$trimGrades = null;
$tg = $connection->prepare("
  SELECT trimelis_professor1_grade, trimelis_professor2_grade, trimelis_professor3_grade, trimelis_final_grade
  FROM trimelis_grades
  WHERE diplo_id = ?
  LIMIT 1
");
$tg->bind_param("i", $diplo_id);
$tg->execute();
$trimGrades = $tg->get_result()->fetch_assoc();
$tg->close();

$triNames = ['p1' => '-', 'p2' => '-', 'p3' => '-'];
$ids = array_values(array_filter([$p1, $p2, $p3], fn($x)=>$x>0));
if (!empty($ids)) {
    $in = implode(",", array_fill(0, count($ids), "?"));
    $types = str_repeat("i", count($ids));

    $sqlNames = "SELECT professor_user_id, professor_name, professor_surname FROM professor WHERE professor_user_id IN ($in)";
    $stN = $connection->prepare($sqlNames);
    $stN->bind_param($types, ...$ids);
    $stN->execute();
    $rn = $stN->get_result();
    $map = [];
    while ($r = $rn->fetch_assoc()) {
        $map[(int)$r['professor_user_id']] = safe_prof_name($r);
    }
    $stN->close();

    $triNames['p1'] = $map[$p1] ?? '-';
    $triNames['p2'] = $map[$p2] ?? '-';
    $triNames['p3'] = $map[$p3] ?? '-';
}

function avg_criteria_row($r){
    $a = [(float)$r['quality_goals'], (float)$r['time_interval'], (float)$r['text_quality'], (float)$r['Presentation']];
    return round(array_sum($a) / count($a), 2);
}

$roleBadge = $isSupervisor ? "Επιβλέπων" : "Μέλος τριμελούς";
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <title>Βαθμολόγηση Διπλωματικής</title>
  <style>
    body { font-family: Arial, sans-serif; background:#eef6ff; margin:0; padding:0; }
    .container { max-width:1100px; margin:40px auto; background:#fff; padding:20px 30px; border-radius:10px; box-shadow:0 0 10px rgba(0,0,0,0.1); }
    .top-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; gap:12px; flex-wrap:wrap; }
    .actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .btn { text-decoration:none; padding:8px 12px; border-radius:6px; font-size:0.9rem; display:inline-block; border:none; cursor:pointer; }
    .btn-success { background:#198754; color:#fff; }
    .btn-success:hover { background:#157347; }
    .btn-danger { background:#dc3545; color:#fff; }
    .btn-danger:hover { background:#b52a37; }
    .btn-primary { background:#0d6efd; color:#fff; }
    .btn-primary:hover { background:#0b5ed7; }
    .card { padding:15px 20px; border-radius:8px; background:#f8fbff; border:1px solid #dde7f5; margin-bottom:20px; }
    .alert { padding:10px 12px; border-radius:6px; margin:12px 0; }
    .alert-info { background:#e8f2ff; border:1px solid #b6d4fe; color:#084298; }
    .alert-warn { background:#fff3cd; border:1px solid #ffecb5; color:#664d03; }
    .badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:0.8rem; font-weight:bold; }
    .badge-blue { background:#0d6efd; color:#fff; }
    .badge-green { background:#198754; color:#fff; }
    .badge-yellow { background:#ffc107; color:#111; }
    .input { width:100%; padding:10px; border:1px solid #cfe0f4; border-radius:6px; outline:none; }
    .input:focus { border-color:#0d6efd; box-shadow:0 0 0 2px rgba(13,110,253,0.15); }
    table { width:100%; border-collapse:collapse; margin-top:10px; }
    th, td { border:1px solid #dde7f5; padding:10px; text-align:left; vertical-align:middle; }
    th { background:#007bff; color:#fff; }
    tr:nth-child(even) { background:#ffffff; }
    tr:nth-child(odd) { background:#f8fbff; }
    .grid { display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
    @media (max-width: 850px){ .grid { grid-template-columns:1fr; } }
    hr { border:none; border-top:1px solid #dde7f5; margin:15px 0; }
  </style>
</head>
<body>
<div class="container">

  <div class="top-bar">
    <div>
      <h2 style="margin:0;"><?= h($diplo['diplo_title'] ?? '') ?></h2>
      <div style="margin-top:6px;">
        <span class="badge badge-blue"><?= h($roleBadge) ?></span>
        <?php if ($gradingEnabled): ?>
          <span class="badge badge-green">Grading Enabled</span>
        <?php else: ?>
          <span class="badge badge-yellow">Grading Disabled</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="actions">
      <a class="btn btn-success" href="thesis_details.php?diplo_id=<?= (int)$diplo_id ?>#grading">← Πίσω</a>
      <a class="btn btn-danger" href="logout.php">Αποσύνδεση</a>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert alert-info"><?= h($message) ?></div>
  <?php endif; ?>

  <?php if (!$gradingEnabled): ?>
    <div class="alert alert-warn">
      ⚠ Η καταχώρηση βαθμών δεν είναι ενεργή ακόμα. Ο επιβλέπων πρέπει να την ενεργοποιήσει από τις <strong>Λεπτομέρειες</strong>.
    </div>
  <?php endif; ?>

  <div class="card">
    <h3 style="margin-top:0;">Ο δικός μου βαθμός (κριτήρια 0-10)</h3>

    <form method="POST">
      <div class="grid">
        <div>
          <div><strong>Ποιότητα στόχων</strong></div>
          <input class="input" type="number" step="0.01" min="0" max="10" name="quality_goals"
                 value="<?= h($my['quality_goals'] ?? '') ?>" <?= $gradingEnabled ? "" : "disabled" ?> required>
        </div>
        <div>
          <div><strong>Χρονική διάρκεια</strong></div>
          <input class="input" type="number" step="0.01" min="0" max="10" name="time_interval"
                 value="<?= h($my['time_interval'] ?? '') ?>" <?= $gradingEnabled ? "" : "disabled" ?> required>
        </div>
        <div>
          <div><strong>Ποιότητα κειμένου</strong></div>
          <input class="input" type="number" step="0.01" min="0" max="10" name="text_quality"
                 value="<?= h($my['text_quality'] ?? '') ?>" <?= $gradingEnabled ? "" : "disabled" ?> required>
        </div>
        <div>
          <div><strong>Παρουσίαση</strong></div>
          <input class="input" type="number" step="0.01" min="0" max="10" name="Presentation"
                 value="<?= h($my['Presentation'] ?? '') ?>" <?= $gradingEnabled ? "" : "disabled" ?> required>
        </div>
      </div>

      <div style="margin-top:12px;">
        <button class="btn btn-primary" style="width:100%;" name="save_grade" <?= $gradingEnabled ? "" : "disabled" ?>>
          Αποθήκευση Βαθμού
        </button>
      </div>
    </form>
  </div>

  <div class="card">
    <h3 style="margin-top:0;">Βαθμοί αναλυτικά (κριτήρια)</h3>

    <?php if (empty($allCriteria)): ?>
      <div class="subtitle">Δεν έχει καταχωρηθεί κανένας βαθμός ακόμα.</div>
    <?php else: ?>
      <div style="overflow:auto;">
        <table>
          <thead>
            <tr>
              <th>Καθηγητής</th>
              <th>Ποιότητα στόχων</th>
              <th>Χρονική διάρκεια</th>
              <th>Ποιότητα κειμένου</th>
              <th>Παρουσίαση</th>
              <th>Μ.Ο.</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($allCriteria as $r): ?>
            <?php $avg = avg_criteria_row($r); ?>
            <tr>
              <td><?= h(safe_prof_name($r)) ?></td>
              <td><?= h($r['quality_goals']) ?></td>
              <td><?= h($r['time_interval']) ?></td>
              <td><?= h($r['text_quality']) ?></td>
              <td><?= h($r['Presentation']) ?></td>
              <td><strong><?= number_format($avg, 2) ?></strong></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3 style="margin-top:0;">Συνοπτικοί βαθμοί τριμελούς</h3>

    <div style="overflow:auto;">
      <table>
        <thead>
          <tr>
            <th>Μέλος</th>
            <th>Βαθμός</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><?= h($triNames['p1']) ?></td>
            <td><?= h($trimGrades['trimelis_professor1_grade'] ?? '-') ?></td>
          </tr>
          <tr>
            <td><?= h($triNames['p2']) ?></td>
            <td><?= h($trimGrades['trimelis_professor2_grade'] ?? '-') ?></td>
          </tr>
          <tr>
            <td><?= h($triNames['p3']) ?></td>
            <td><?= h($trimGrades['trimelis_professor3_grade'] ?? '-') ?></td>
          </tr>
          <tr>
            <td><strong>Τελικός Βαθμός (Μ.Ο.)</strong></td>
            <td><strong><?= h($trimGrades['trimelis_final_grade'] ?? '-') ?></strong></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="alert alert-info" style="margin-bottom:0;">
      <strong>Σημείωση:</strong> Ο τελικός βαθμός υπολογίζεται αυτόματα όταν υπάρχουν και οι 3 βαθμοί.
    </div>
  </div>

</div>
</body>
</html>
