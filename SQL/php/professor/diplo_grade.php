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

$gradingEnabled = ((int)($diplo['grading_enabled'] ?? 0) === 1);

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

// ------------------ Αποθήκευση κριτηρίων (INSERT/UPDATE) ------------------
if (isset($_POST['save_grade'])) {

    if (!$gradingEnabled) {
        $message = "❌ Η βαθμολόγηση δεν έχει ενεργοποιηθεί από τον επιβλέποντα (επιλογή 9).";
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

            // 3) Γράψιμο στον πίνακα trimelis_grades μόνο αν είναι μέλος τριμελούς
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

                    // (προαιρετικά) γράφουμε και στο diplo
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

// Καθηγητές τριμελούς (ονόματα)
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
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <title>10) Καταχώρηση Βαθμού</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark p-2">
  <span class="navbar-brand ms-3">10) Βαθμολόγηση Διπλωματικής (κριτήρια)</span>
  <div class="ms-auto d-flex gap-2 me-3">
    <a href="professor_page.php" class="btn btn-success">Αρχική</a>
    <a href="logout.php" class="btn btn-danger">Αποσύνδεση</a>
  </div>
</nav>

<div class="container mt-4" style="max-width: 1100px;">

  <?php if ($message): ?>
    <div class="alert alert-info text-center"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm mb-4">
    <div class="card-header fw-bold">
      <?= htmlspecialchars($diplo['diplo_title'] ?? '') ?>
      <?php if ($isSupervisor): ?>
        <span class="badge bg-primary ms-2">Επιβλέπων</span>
      <?php else: ?>
        <span class="badge bg-secondary ms-2">Μέλος τριμελούς</span>
      <?php endif; ?>
      <?php if ($gradingEnabled): ?>
        <span class="badge bg-success ms-2">Grading Enabled</span>
      <?php else: ?>
        <span class="badge bg-warning text-dark ms-2">Grading Disabled</span>
      <?php endif; ?>
    </div>

    <div class="card-body">

      <?php if (!$gradingEnabled): ?>
        <div class="alert alert-warning">
          ⚠ Η καταχώρηση βαθμών δεν είναι ενεργή ακόμα. Ο επιβλέπων πρέπει να την ενεργοποιήσει από το (9).
        </div>
      <?php endif; ?>

      <h5 class="fw-bold">Ο δικός μου βαθμός (κριτήρια 0-10)</h5>

      <form method="POST" class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Ποιότητα στόχων</label>
          <input type="number" step="0.01" min="0" max="10" name="quality_goals"
                 value="<?= htmlspecialchars($my['quality_goals'] ?? '') ?>"
                 class="form-control" <?= $gradingEnabled ? "" : "disabled" ?> required>
        </div>

        <div class="col-md-3">
          <label class="form-label">Χρονική διάρκεια</label>
          <input type="number" step="0.01" min="0" max="10" name="time_interval"
                 value="<?= htmlspecialchars($my['time_interval'] ?? '') ?>"
                 class="form-control" <?= $gradingEnabled ? "" : "disabled" ?> required>
        </div>

        <div class="col-md-3">
          <label class="form-label">Ποιότητα κειμένου</label>
          <input type="number" step="0.01" min="0" max="10" name="text_quality"
                 value="<?= htmlspecialchars($my['text_quality'] ?? '') ?>"
                 class="form-control" <?= $gradingEnabled ? "" : "disabled" ?> required>
        </div>

        <div class="col-md-3">
          <label class="form-label">Παρουσίαση</label>
          <input type="number" step="0.01" min="0" max="10" name="Presentation"
                 value="<?= htmlspecialchars($my['Presentation'] ?? '') ?>"
                 class="form-control" <?= $gradingEnabled ? "" : "disabled" ?> required>
        </div>

        <div class="col-12">
          <button class="btn btn-primary w-100" name="save_grade"
                  <?= $gradingEnabled ? "" : "disabled" ?>>
            Αποθήκευση Βαθμού
          </button>
        </div>
      </form>

      <hr>

      <h5 class="fw-bold">Βαθμοί αναλυτικά (κριτήρια) από όσους έχουν καταχωρήσει</h5>

      <?php if (empty($allCriteria)): ?>
        <p class="text-muted">Δεν έχει καταχωρηθεί κανένας βαθμός ακόμα.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
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
                <td><?= htmlspecialchars(safe_prof_name($r)) ?></td>
                <td><?= htmlspecialchars($r['quality_goals']) ?></td>
                <td><?= htmlspecialchars($r['time_interval']) ?></td>
                <td><?= htmlspecialchars($r['text_quality']) ?></td>
                <td><?= htmlspecialchars($r['Presentation']) ?></td>
                <td><strong><?= number_format($avg, 2) ?></strong></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <hr>

      <h5 class="fw-bold">Συνοπτικοί βαθμοί τριμελούς (trimelis_grades)</h5>

      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead class="table-dark">
            <tr>
              <th>Μέλος</th>
              <th>Βαθμός</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><?= htmlspecialchars($triNames['p1']) ?></td>
              <td><?= htmlspecialchars($trimGrades['trimelis_professor1_grade'] ?? '-') ?></td>
            </tr>
            <tr>
              <td><?= htmlspecialchars($triNames['p2']) ?></td>
              <td><?= htmlspecialchars($trimGrades['trimelis_professor2_grade'] ?? '-') ?></td>
            </tr>
            <tr>
              <td><?= htmlspecialchars($triNames['p3']) ?></td>
              <td><?= htmlspecialchars($trimGrades['trimelis_professor3_grade'] ?? '-') ?></td>
            </tr>
            <tr>
              <td class="fw-bold">Τελικός Βαθμός (Μ.Ο.)</td>
              <td class="fw-bold"><?= htmlspecialchars($trimGrades['trimelis_final_grade'] ?? '-') ?></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="alert alert-secondary">
        <strong>Σημείωση:</strong> Ο τελικός βαθμός υπολογίζεται αυτόματα όταν υπάρχουν και οι 3 βαθμοί.
      </div>

    </div>
  </div>

</div>

</body>
</html>
