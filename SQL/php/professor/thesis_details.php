<?php
session_start();
include("db_connect.php");
include("connected.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// only professor
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

$prof = Professor_Connected($connection);
$profUserId = (int)($prof['professor_user_id'] ?? $prof['professor_id'] ?? 0);
if ($profUserId <= 0) die("Δεν βρέθηκαν στοιχεία καθηγητή.");

$diploId = (int)($_GET['diplo_id'] ?? 0);
if ($diploId <= 0) die("Λάθος diplo_id.");

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function profFull($n, $s) {
  $n = trim((string)$n); $s = trim((string)$s);
  $f = trim($s . " " . $n);
  return $f !== "" ? $f : "-";
}
function status_gr($s) {
  $s = (string)$s;
  if ($s === 'pending') return 'Υπό Ανάθεση';
  if ($s === 'active') return 'Ενεργή';
  if ($s === 'finished') return 'Περατωμένη';
  if ($s === 'cancelled' || $s === 'cancel') return 'Ακυρωμένη';
  if ($s === 'under review' || $s === 'under_review') return 'Υπό Εξέταση';
  return $s ?: '-';
}
function is_under_review_status($s){
  $s = strtolower(trim((string)$s));
  return ($s === 'under_review' || $s === 'under review');
}

// ------------------ load thesis (diplo + student + trimelous + professors) ------------------
$sql = "
SELECT
  d.*,
  s.student_am, s.student_name, s.student_surname,
  t.trimelous_professor1, t.trimelous_professor2, t.trimelous_professor3,
  p1.professor_name AS p1_name, p1.professor_surname AS p1_surname,
  p2.professor_name AS p2_name, p2.professor_surname AS p2_surname,
  p3.professor_name AS p3_name, p3.professor_surname AS p3_surname
FROM diplo d
LEFT JOIN student s ON s.student_am = d.diplo_student
LEFT JOIN trimelous t ON t.diplo_id = d.diplo_id
LEFT JOIN professor p1 ON p1.professor_user_id = t.trimelous_professor1
LEFT JOIN professor p2 ON p2.professor_user_id = t.trimelous_professor2
LEFT JOIN professor p3 ON p3.professor_user_id = t.trimelous_professor3
WHERE d.diplo_id = ?
LIMIT 1
";
$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $diploId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) die("Δεν βρέθηκε διπλωματική.");

// access: supervisor or committee member
$isSupervisor = ((int)($row['diplo_professor'] ?? 0) === $profUserId);
$isMember = (
  (int)($row['trimelous_professor1'] ?? 0) === $profUserId ||
  (int)($row['trimelous_professor2'] ?? 0) === $profUserId ||
  (int)($row['trimelous_professor3'] ?? 0) === $profUserId
);
if (!$isSupervisor && !$isMember) die("Δεν έχετε δικαίωμα προβολής αυτής της διπλωματικής.");

// grading flag (THE IMPORTANT ONE)
$gradingEnabled = ((int)($row['grading_enabled'] ?? 0) === 1);

// ------------------ message ------------------
$message = (string)($_GET['msg'] ?? "");

// ------------------ timeline ------------------
$timeline = [];
$stmtTL = $connection->prepare("
  SELECT diplo_date, diplo_status
  FROM diplo_date
  WHERE diplo_id = ?
  ORDER BY diplo_date ASC
");
$stmtTL->bind_param("i", $diploId);
$stmtTL->execute();
$resTL = $stmtTL->get_result();
while ($t = $resTL->fetch_assoc()) $timeline[] = $t;
$stmtTL->close();

// ------------------ official assignment date = first ACTIVE in diplo_date ------------------
$assignedAt = null;
try {
    $stmtA = $connection->prepare("
      SELECT MIN(diplo_date) AS dt
      FROM diplo_date
      WHERE diplo_id = ? AND diplo_status='active'
    ");
    $stmtA->bind_param("i", $diploId);
    $stmtA->execute();
    $assignedAt = ($stmtA->get_result()->fetch_assoc()['dt'] ?? null);
    $stmtA->close();
} catch (Exception $e) {
    $assignedAt = null;
}

$timeSinceTxt = "—";
if (!empty($assignedAt)) {
  $days = (int)floor((time() - strtotime($assignedAt)) / 86400);
  if ($days < 0) $days = 0;
  $years = (int)floor($days / 365);
  $rem = $days % 365;
  $months = (int)floor($rem / 30);
  $dleft = $rem % 30;
  $parts = [];
  if ($years > 0) $parts[] = $years . " έτη";
  if ($months > 0) $parts[] = $months . " μήνες";
  $parts[] = $dleft . " ημέρες";
  $timeSinceTxt = implode(", ", $parts) . " (από " . date("d/m/Y", strtotime($assignedAt)) . ")";
}

// show student
$studentFull = "-";
if (!empty($row['student_am'])) {
  $studentFull = trim(($row['student_surname'] ?? '') . " " . ($row['student_name'] ?? '') . " (ΑΜ: " . $row['student_am'] . ")");
}

// pending invites list
$pendingInvites = [];
if (($row['diplo_status'] ?? '') === 'pending') {
  $stmtInv = $connection->prepare("
    SELECT ti.*, p.professor_name, p.professor_surname
    FROM trimelous_invite ti
    JOIN professor p ON p.professor_user_id = ti.professor_user_id
    WHERE ti.diplo_id = ?
    ORDER BY ti.trimelous_date ASC
  ");
  $stmtInv->bind_param("i", $diploId);
  $stmtInv->execute();
  $resInv = $stmtInv->get_result();
  while ($x = $resInv->fetch_assoc()) $pendingInvites[] = $x;
  $stmtInv->close();
}

// ------------------ ACTIONS inside details ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

  $stR = $connection->prepare("SELECT diplo_status, diplo_professor, diplo_student FROM diplo WHERE diplo_id=? LIMIT 1");
  $stR->bind_param("i", $diploId);
  $stR->execute();
  $cur = $stR->get_result()->fetch_assoc();
  $stR->close();
  if (!$cur) die("Δεν βρέθηκε διπλωματική (POST).");

  $curStatus = (string)($cur['diplo_status'] ?? '');
  $curSupervisor = ((int)($cur['diplo_professor'] ?? 0) === $profUserId);
  $curStudent = $cur['diplo_student'] ?? null;

  $act = (string)$_POST['action'];

  // ✅ ENABLE GRADING (only supervisor + under_review)
  if ($act === 'enable_grading') {
    if (!$curSupervisor) die("Δεν επιτρέπεται (μόνο επιβλέπων).");
    if (!is_under_review_status($curStatus)) die("Επιτρέπεται μόνο όταν είναι Υπό Εξέταση.");

    $connection->begin_transaction();
    try {
      // 1) set flag in diplo (IMPORTANT for diplo_grade.php)
      $upd = $connection->prepare("
        UPDATE diplo
        SET grading_enabled = 1
        WHERE diplo_id = ? AND diplo_professor = ?
      ");
      $upd->bind_param("ii", $diploId, $profUserId);
      $upd->execute();
      $upd->close();

      // 2) ensure row exists in trimelis_grades (optional but good)
      $ins = $connection->prepare("
        INSERT INTO trimelis_grades (diplo_id)
        VALUES (?)
        ON DUPLICATE KEY UPDATE diplo_id = diplo_id
      ");
      $ins->bind_param("i", $diploId);
      $ins->execute();
      $ins->close();

      $connection->commit();
      header("Location: thesis_details.php?diplo_id=".$diploId."&msg=".urlencode("✅ Η βαθμολόγηση ενεργοποιήθηκε."));
      exit;
    } catch (Exception $e) {
      $connection->rollback();
      die("Σφάλμα ενεργοποίησης βαθμολόγησης: ".$e->getMessage());
    }
  }

  // cancel assignment (only supervisor + pending + has student)
  if ($act === 'cancel_assignment') {
    if (!$curSupervisor || $curStatus !== 'pending' || empty($curStudent)) die("Δεν επιτρέπεται.");

    $connection->begin_transaction();
    try {
      $st1 = $connection->prepare("UPDATE diplo SET diplo_student=NULL, diplo_status='pending' WHERE diplo_id=? AND diplo_professor=? AND diplo_status='pending'");
      $st1->bind_param("ii", $diploId, $profUserId);
      $st1->execute();
      $st1->close();

      $st2 = $connection->prepare("DELETE FROM trimelous_invite WHERE diplo_id=?");
      $st2->bind_param("i", $diploId);
      $st2->execute();
      $st2->close();

      $st3 = $connection->prepare("DELETE FROM trimelous WHERE diplo_id=?");
      $st3->bind_param("i", $diploId);
      $st3->execute();
      $st3->close();

      $connection->commit();
      header("Location: thesis_details.php?diplo_id=".$diploId."&msg=".urlencode("✅ Ακυρώθηκε η ανάθεση. Διαγράφηκαν προσκλήσεις/τριμελής."));
      exit;
    } catch (Exception $e) {
      $connection->rollback();
      die("Σφάλμα ακύρωσης: ".$e->getMessage());
    }
  }

  // set under review (only supervisor + active)
  if ($act === 'set_under_review') {
    if (!$curSupervisor) die("Δεν έχετε δικαίωμα.");
    if ($curStatus !== 'active') die("Επιτρέπεται μόνο όταν είναι active.");

    $connection->begin_transaction();
    try {
      $u1 = $connection->prepare("UPDATE diplo SET diplo_status='under_review' WHERE diplo_id=?");
      $u1->bind_param("i", $diploId);
      $u1->execute();
      $u1->close();

      $u2 = $connection->prepare("INSERT INTO diplo_date (diplo_id, diplo_date, diplo_status) VALUES (?, NOW(), 'under_review')");
      $u2->bind_param("i", $diploId);
      $u2->execute();
      $u2->close();

      $connection->commit();
      header("Location: thesis_details.php?diplo_id=".$diploId."&msg=".urlencode("✅ Η διπλωματική πέρασε σε Υπό Εξέταση."));
      exit;
    } catch (Exception $e) {
      $connection->rollback();
      die("Σφάλμα αλλαγής: " . $e->getMessage());
    }
  }

  // cancel pending thesis (only supervisor + pending)
  if ($act === 'cancel_pending_inside_details') {
    if (!$curSupervisor || $curStatus !== 'pending') die("Δεν επιτρέπεται.");

    $connection->begin_transaction();
    try {
      $upd = $connection->prepare("
        UPDATE diplo
        SET diplo_status='cancelled',
            diplo_student=NULL,
            diplo_trimelis=NULL
        WHERE diplo_id=?
          AND diplo_professor=?
          AND diplo_status='pending'
      ");
      $upd->bind_param("ii", $diploId, $profUserId);
      $upd->execute();
      $upd->close();

      $del = $connection->prepare("DELETE FROM trimelous_invite WHERE diplo_id=?");
      $del->bind_param("i", $diploId);
      $del->execute();
      $del->close();

      $connection->commit();
      header("Location: thesis_details.php?diplo_id=".$diploId."&msg=".urlencode("✅ Η pending διπλωματική ακυρώθηκε."));
      exit;
    } catch (Exception $e) {
      $connection->rollback();
      die("Σφάλμα: ".$e->getMessage());
    }
  }

  // cancel active thesis after 2 years (only supervisor + active) — uses assignedAt from diplo_date(active)
  if ($act === 'cancel_active_inside_details') {
    if (!$curSupervisor || $curStatus !== 'active') die("Δεν επιτρέπεται.");

    $gs_number = trim($_POST['gs_number'] ?? '');
    $gs_year   = trim($_POST['gs_year'] ?? '');

    if ($gs_number === "" || !ctype_digit($gs_number) || (int)$gs_number <= 0) {
      header("Location: thesis_details.php?diplo_id=".$diploId."&msg=".urlencode("❌ Λάθος αριθμός ΓΣ."));
      exit;
    }
    if ($gs_year === "" || !ctype_digit($gs_year) || (int)$gs_year < 1900 || (int)$gs_year > 2100) {
      header("Location: thesis_details.php?diplo_id=".$diploId."&msg=".urlencode("❌ Λάθος έτος ΓΣ."));
      exit;
    }

    // find first active date from diplo_date
    $firstActive = null;
    $stFA = $connection->prepare("SELECT MIN(diplo_date) AS dt FROM diplo_date WHERE diplo_id=? AND diplo_status='active'");
    $stFA->bind_param("i", $diploId);
    $stFA->execute();
    $firstActive = ($stFA->get_result()->fetch_assoc()['dt'] ?? null);
    $stFA->close();

    if (empty($firstActive)) {
      header("Location: thesis_details.php?diplo_id=".$diploId."&msg=".urlencode("❌ Δεν υπάρχει ημερομηνία ανάθεσης (active) στο diplo_date."));
      exit;
    }

    $assigned_at = strtotime($firstActive);
    $two_years_ago = strtotime("-2 years");
    if ($assigned_at > $two_years_ago) {
      header("Location: thesis_details.php?diplo_id=".$diploId."&msg=".urlencode("⚠ Δεν έχουν περάσει 2 έτη από την οριστική ανάθεση (active)."));
      exit;
    }

    $connection->begin_transaction();
    try {
      $upd = $connection->prepare("
        UPDATE diplo
        SET diplo_status='cancelled',
            cancel_reason='από Διδάσκοντα',
            cancel_gs_number=?,
            cancel_gs_year=?,
            cancelled_at=NOW(),
            diplo_student=NULL,
            diplo_trimelis=NULL
        WHERE diplo_id=? AND diplo_professor=? AND diplo_status='active'
      ");
      $gsn = (int)$gs_number;
      $gsy = (int)$gs_year;
      $upd->bind_param("iiii", $gsn, $gsy, $diploId, $profUserId);
      $upd->execute();
      $upd->close();

      $del = $connection->prepare("DELETE FROM trimelous_invite WHERE diplo_id=?");
      $del->bind_param("i", $diploId);
      $del->execute();
      $del->close();

      $connection->commit();
      header("Location: thesis_details.php?diplo_id=".$diploId."&msg=".urlencode("✅ Η ενεργή διπλωματική ακυρώθηκε."));
      exit;

    } catch (Exception $e) {
      $connection->rollback();
      die("Σφάλμα: ".$e->getMessage());
    }
  }
}

// paths
$diploPdf = trim((string)($row['diplo_pdf'] ?? ''));
$diploPdfHref = "-";
if ($diploPdf !== '') {
  $diploPdfHref = (strpos($diploPdf, '/') !== false) ? $diploPdf : ("uploads/" . $diploPdf);
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <title>Λεπτομέρειες Διπλωματικής</title>
  <style>
    body { font-family: Arial, sans-serif; background:#eef6ff; margin:0; padding:0; }
    .container { max-width:1100px; margin:40px auto; background:#fff; padding:20px 30px; border-radius:10px; box-shadow:0 0 10px rgba(0,0,0,0.1); }
    h1,h2,h3 { margin-top:0; }
    .subtitle { color:#555; font-size:0.95rem; margin-bottom:10px; }
    .top-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; gap:12px; flex-wrap:wrap; }
    .actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .btn { text-decoration:none; padding:8px 12px; border-radius:6px; font-size:0.9rem; display:inline-block; border:none; cursor:pointer; color:#fff; }
    .back-btn { background:#0d6efd; } .back-btn:hover{ background:#0b5ed7; }
    .logout-btn { background:#dc3545; } .logout-btn:hover{ background:#b52a37; }
    .btn-primary { background:#0d6efd; color:#fff; } .btn-primary:hover{ background:#0b5ed7; }
    .btn-danger { background:#dc3545; color:#fff; } .btn-danger:hover{ background:#b52a37; }
    .btn-warning { background:#ffc107; color:#111; } .btn-warning:hover{ background:#e0a800; }
    .card { padding:15px 20px; border-radius:8px; background:#f8fbff; border:1px solid #dde7f5; margin-bottom:20px; }
    .alert { padding:10px 12px; border-radius:6px; margin:12px 0; }
    .alert-info { background:#e8f2ff; border:1px solid #b6d4fe; color:#084298; }
    .alert-warn { background:#fff3cd; border:1px solid #ffecb5; color:#664d03; }
    table { width:100%; border-collapse:collapse; margin-top:10px; }
    th, td { border:1px solid #dde7f5; padding:10px; text-align:left; vertical-align:middle; }
    th { background:#007bff; color:#fff; }
    tr:nth-child(even) { background:#ffffff; }
    tr:nth-child(odd) { background:#f8fbff; }
    .badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:0.8rem; font-weight:bold; background:#6c757d; color:#fff; }
    .input { width:100%; padding:10px; border:1px solid #cfe0f4; border-radius:6px; box-sizing:border-box; }
    .input:focus { outline:none; border-color:#0d6efd; box-shadow:0 0 0 2px rgba(13,110,253,0.15); }
    hr { border:none; border-top:1px solid #dde7f5; margin:16px 0; }
  </style>
</head>
<body>
<div class="container">

  <div class="top-bar">
    <div>
      <h1>📌 Λεπτομέρειες διπλωματικής #<?= (int)$row['diplo_id'] ?></h1>
      <div class="subtitle"><?= h($row['diplo_title'] ?? '') ?></div>
    </div>
    <div class="actions">
      <a class="btn back-btn" href="diplomas.php">← Πίσω στη Λίστα</a>
      <a class="btn logout-btn" href="logout.php">Αποσύνδεση</a>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert alert-info" style="text-align:center;"><?= h($message) ?></div>
  <?php endif; ?>

  <div class="card">
    <p><strong>Κατάσταση:</strong> <span class="badge"><?= h(status_gr($row['diplo_status'] ?? '')) ?></span></p>
    <p><strong>Ρόλος μου:</strong> <?= $isSupervisor ? 'Επιβλέπων' : 'Μέλος τριμελούς' ?></p>
    <p><strong>Φοιτητής:</strong> <?= h($studentFull) ?></p>
    <p><strong>Χρόνος από επίσημη ανάθεση:</strong> <?= h($timeSinceTxt) ?></p>

    <hr>

    <p><strong>Περιγραφή:</strong><br><?= nl2br(h($row['diplo_desc'] ?? '')) ?></p>

    <p><strong>Τελικό PDF:</strong>
      <?php if ($diploPdfHref !== "-"): ?>
        <a class="btn btn-primary" href="<?= h($diploPdfHref) ?>" target="_blank">Άνοιγμα PDF</a>
      <?php else: ?>
        <span class="subtitle">Δεν υπάρχει.</span>
      <?php endif; ?>
      <a class="btn btn-primary" href="view_diploma.php?diplo_id=<?= (int)$diploId ?>">Προβολή σελίδας PDF</a>
    </p>

    <p><strong>Πρόχειρο (draft):</strong>
      <a class="btn btn-primary" href="view_draft.php?diplo_id=<?= (int)$diploId ?>">Προβολή draft</a>
    </p>

    <hr>

    <h3>Τριμελής</h3>
    <ul>
      <li>Professor 1 (Επιβλέπων): <?= h(profFull($row['p1_name'] ?? '', $row['p1_surname'] ?? '')) ?></li>
      <li>Professor 2: <?= h(profFull($row['p2_name'] ?? '', $row['p2_surname'] ?? '')) ?></li>
      <li>Professor 3: <?= h(profFull($row['p3_name'] ?? '', $row['p3_surname'] ?? '')) ?></li>
    </ul>

    <hr>

    <p><strong>Σημειώσεις:</strong>
      <a class="btn btn-primary" href="prof_show_notes.php?diplo_id=<?= (int)$diploId ?>">Άνοιγμα σημειώσεων</a>
    </p>

    <!-- ✅ UNDER REVIEW: GRADING SECTION -->
    <?php if (is_under_review_status($row['diplo_status'] ?? '')): ?>
      <hr>
      <h3>✅ Βαθμολόγηση</h3>

      <?php if ($isSupervisor): ?>
        <?php if (!$gradingEnabled): ?>
          <div class="alert alert-warn">
            Η βαθμολόγηση δεν είναι ενεργή. Ως επιβλέπων μπορείτε να την ενεργοποιήσετε.
          </div>

          <form method="POST" onsubmit="return confirm('Ενεργοποίηση δυνατότητας βαθμολόγησης;');">
            <input type="hidden" name="action" value="enable_grading">
            <button type="submit" class="btn btn-warning">Ενεργοποίηση βαθμού</button>
          </form>
        <?php else: ?>
          <div class="alert alert-info">✅ Η βαθμολόγηση είναι ενεργή.</div>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($gradingEnabled): ?>
        <div style="margin-top:10px;">
          <a class="btn btn-primary" href="diplo_grade.php?diplo_id=<?= (int)$diploId ?>">
            Καταχώρηση / Προβολή βαθμών
          </a>
        </div>
      <?php else: ?>
        <div class="subtitle" style="margin-top:10px;">
          Η καταχώρηση βαθμών θα εμφανιστεί μόλις ενεργοποιηθεί από τον επιβλέποντα.
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($isSupervisor && ($row['diplo_status'] ?? '') === 'active'): ?>
      <div class="alert alert-info">
        <strong>Ενέργεια επιβλέποντα:</strong> Μπορείτε να αλλάξετε την κατάσταση σε <strong>Υπό Εξέταση</strong>.
        <form method="POST" style="margin-top:10px;" onsubmit="return confirm('Να γίνει μετάβαση σε Υπό Εξέταση;');">
          <input type="hidden" name="action" value="set_under_review">
          <button type="submit" class="btn btn-warning">Μετάβαση σε «Υπό Εξέταση»</button>
        </form>
      </div>

      <hr>
      <h3>❌ Ακύρωση Ενεργής (μετά από 2 έτη)</h3>

      <?php if (empty($assignedAt)): ?>
        <div class="alert alert-warn">Δεν υπάρχει ημερομηνία ανάθεσης (active) στο <code>diplo_date</code>.</div>
      <?php else: ?>
        <form method="POST" onsubmit="return confirm('Σίγουρα; Επιτρέπεται μόνο μετά από 2 έτη από την οριστική ανάθεση (active).');" style="max-width:520px;">
          <input type="hidden" name="action" value="cancel_active_inside_details">

          <div style="margin-bottom:10px;">
            <label><strong>Αρ. ΓΣ</strong></label>
            <input class="input" type="text" name="gs_number" placeholder="π.χ. 12" required>
          </div>

          <div style="margin-bottom:10px;">
            <label><strong>Έτος ΓΣ</strong></label>
            <input class="input" type="text" name="gs_year" placeholder="π.χ. 2024" required>
          </div>

          <button class="btn btn-danger" type="submit">Ακύρωση Ενεργής</button>
        </form>

        <div class="subtitle" style="margin-top:8px;">
          Ανάθεση (active) από: <strong><?= h(date("d/m/Y", strtotime($assignedAt))) ?></strong>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (($row['diplo_status'] ?? '') === 'pending'): ?>
      <hr>
      <h3>Υπό Ανάθεση — Προσκλήσεις τριμελούς</h3>

      <?php if (empty($pendingInvites)): ?>
        <div class="subtitle">Δεν υπάρχουν προσκλήσεις.</div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Διδάσκων</th>
              <th>Κατάσταση</th>
              <th>Ημ/νία Πρόσκλησης</th>
              <th>Ημ/νία Αποδοχής</th>
              <th>Ημ/νία Απόρριψης</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($pendingInvites as $inv): ?>
            <tr>
              <td><?= h(trim(($inv['professor_surname'] ?? '') . " " . ($inv['professor_name'] ?? ''))) ?></td>
              <td><?= h($inv['invite_status'] ?? '-') ?></td>
              <td><?= h($inv['trimelous_date'] ?? '-') ?></td>
              <td><?= h($inv['invite_accept_date'] ?? '-') ?></td>
              <td><?= h($inv['invite_deny_date'] ?? '-') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <?php if ($isSupervisor && !empty($row['diplo_student'])): ?>
        <div class="alert alert-warn" style="margin-top:12px;">
          <strong>Ακύρωση ανάθεσης:</strong> Θα διαγραφούν προσκλήσεις και τριμελής (μένει pending).
          <form method="POST" style="margin-top:10px;" onsubmit="return confirm('Σίγουρα ακύρωση ανάθεσης;');">
            <input type="hidden" name="action" value="cancel_assignment">
            <button type="submit" class="btn btn-danger">Ακύρωση ανάθεσης</button>
          </form>
        </div>
      <?php endif; ?>

      <?php if ($isSupervisor): ?>
        <hr>
        <h3>❌ Ακύρωση Pending (οριστική ακύρωση θέματος)</h3>
        <div class="alert alert-warn">
          Θα γίνει <strong>cancelled</strong> και θα διαγραφούν οι προσκλήσεις τριμελούς.
        </div>
        <form method="POST" onsubmit="return confirm('Σίγουρα ακύρωση της pending διπλωματικής;');">
          <input type="hidden" name="action" value="cancel_pending_inside_details">
          <button class="btn btn-danger" type="submit">Ακύρωση Pending</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>

    <hr>

    <h3>Χρονολόγιο (αλλαγές κατάστασης)</h3>
    <?php if (empty($timeline)): ?>
      <div class="subtitle">Δεν υπάρχουν καταχωρημένες αλλαγές.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th style="width:240px;">Ημερομηνία</th>
            <th>Κατάσταση</th>
          </tr>
        </thead>
        <tbody>
        <?php $last = count($timeline)-1; ?>
        <?php foreach ($timeline as $i => $t): ?>
          <tr>
            <td><?= h($t['diplo_date'] ?? '') ?></td>
            <td>
              <?= h(status_gr($t['diplo_status'] ?? '')) ?>
              <?php if ($i === $last): ?> <span class="badge">τρέχουσα</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <hr>
    <p><strong>Τελικός βαθμός:</strong> <?= h($row['diplo_grade'] ?? '-') ?></p>
  </div>

</div>
</body>
</html>
