<?php
session_start();
include("db_connect.php");
include("connected.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Μόνο professor
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "professor") {
    header("Location: login.php");
    exit;
}

$prof = Professor_Connected($connection);
$profUserId = (int)($prof['professor_user_id'] ?? $prof['professor_id'] ?? 0);
if ($profUserId <= 0) die("Δεν βρέθηκαν στοιχεία καθηγητή.");

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$message = "";

// enable grading
if ($_SERVER['REQUEST_METHOD'] === "POST" && isset($_POST['enable_grading'])) {
    $diplo_id = (int)($_POST['diplo_id'] ?? 0);

    // μόνο επιβλέπων + μόνο under_review
    $chk = $connection->prepare("
        SELECT diplo_id
        FROM diplo
        WHERE diplo_id = ?
          AND diplo_professor = ?
          AND diplo_status IN ('under review','under_review')
        LIMIT 1
    ");
    $chk->bind_param("ii", $diplo_id, $profUserId);
    $chk->execute();
    $ok = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$ok) {
        $message = "❌ Δεν επιτρέπεται (πρέπει να είστε επιβλέπων και η ΔΕ να είναι 'Υπό Εξέταση').";
        header("Location: grade_enable.php?msg=" . urlencode($message));
        exit;
    }

    // ✅ FIX: γράφουμε ΚΑΙ diplo.grading_enabled=1 ΚΑΙ trimelis_grades row
    $connection->begin_transaction();
    try {
        $upd = $connection->prepare("
            UPDATE diplo
            SET grading_enabled = 1
            WHERE diplo_id = ? AND diplo_professor = ?
        ");
        $upd->bind_param("ii", $diplo_id, $profUserId);
        $upd->execute();
        $upd->close();

        // ensure row exists in trimelis_grades (προτείνεται UNIQUE στο diplo_id)
        $ins = $connection->prepare("
            INSERT INTO trimelis_grades (diplo_id)
            VALUES (?)
            ON DUPLICATE KEY UPDATE diplo_id = diplo_id
        ");
        $ins->bind_param("i", $diplo_id);
        $ins->execute();
        $ins->close();

        $connection->commit();

        // πάμε στο thesis_details
        header("Location: thesis_details.php?diplo_id=" . $diplo_id . "&msg=" . urlencode("✅ Η βαθμολόγηση ενεργοποιήθηκε."));
        exit;

    } catch (Exception $e) {
        $connection->rollback();
        $message = "❌ Σφάλμα: " . $e->getMessage();
        header("Location: grade_enable.php?msg=" . urlencode($message));
        exit;
    }
}

if (isset($_GET['msg'])) $message = (string)$_GET['msg'];

// list under review supervisor
$list = [];
$stmt = $connection->prepare("
    SELECT
      d.diplo_id,
      d.diplo_title,
      d.diplo_student,
      d.diplo_status,
      d.grading_enabled,
      s.student_name,
      s.student_surname
    FROM diplo d
    LEFT JOIN student s ON s.student_am = d.diplo_student
    WHERE d.diplo_professor = ?
      AND d.diplo_status IN ('under review','under_review')
    ORDER BY d.diplo_id DESC
");
$stmt->bind_param("i", $profUserId);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $list[] = $r;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <title>Ενεργοποίηση Βαθμολόγησης</title>
  <style>
    body { font-family: Arial, sans-serif; background:#eef6ff; margin:0; padding:0; }
    .container { max-width:1100px; margin:40px auto; background:#fff; padding:20px 30px; border-radius:10px; box-shadow:0 0 10px rgba(0,0,0,0.1); }
    h1 { margin:0 0 6px 0; }
    .subtitle { color:#555; font-size:0.95rem; margin-bottom:10px; }
    .top-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; gap:12px; flex-wrap:wrap; }
    .actions { display:flex; gap:10px; align-items:center; }
    .btn { text-decoration:none; padding:8px 12px; border-radius:6px; font-size:0.9rem; display:inline-block; border:none; cursor:pointer; color:#fff; }
    .btn-dark { background:#212529; }
    .btn-dark:hover { background:#111; }
    .btn-danger { background:#dc3545; }
    .btn-danger:hover { background:#b52a37; }
    .btn-primary { background:#0d6efd; }
    .btn-primary:hover { background:#0b5ed7; }
    .btn-success { background:#198754; }
    .btn-success:hover { background:#157347; }
    .alert { padding:10px 12px; border-radius:6px; margin-bottom:15px; background:#e8f2ff; border:1px solid #b6d4fe; color:#084298; }
    table { width:100%; border-collapse:collapse; margin-top:10px; }
    th, td { border:1px solid #dde7f5; padding:10px; text-align:left; vertical-align:middle; }
    th { background:#007bff; color:#fff; }
    tr:nth-child(even) { background:#ffffff; }
    tr:nth-child(odd) { background:#f8fbff; }
    .badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:0.8rem; font-weight:bold; }
    .bg-success { background:#198754; color:#fff; }
    .bg-warning { background:#ffc107; color:#111; }
    .bg-secondary { background:#6c757d; color:#fff; }
  </style>
</head>
<body>
<div class="container">

  <div class="top-bar">
    <div>
      <h1>✅ Ενεργοποίηση βαθμολόγησης</h1>
      <div class="subtitle">Διπλωματικές σας σε κατάσταση <strong>Υπό Εξέταση</strong>.</div>
    </div>
    <div class="actions">
      <a class="btn btn-dark" href="professor_page.php">Αρχική</a>
      <a class="btn btn-danger" href="logout.php">Αποσύνδεση</a>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert"><?= h($message) ?></div>
  <?php endif; ?>

  <?php if (empty($list)): ?>
    <div class="subtitle">Δεν υπάρχουν διπλωματικές σε κατάσταση Υπό Εξέταση.</div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Τίτλος</th>
          <th>Φοιτητής</th>
          <th>Status</th>
          <th>Enabled</th>
          <th>Ενέργεια</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($list as $d): ?>
        <?php
          $stud = "-";
          if (!empty($d['diplo_student'])) {
            $stud = $d['diplo_student'] . " - " . trim(($d['student_surname'] ?? '') . " " . ($d['student_name'] ?? ''));
            $stud = trim($stud);
          }
          $enabled = ((int)($d['grading_enabled'] ?? 0) === 1);
        ?>
        <tr>
          <td><?= (int)$d['diplo_id'] ?></td>
          <td><?= h($d['diplo_title'] ?? '') ?></td>
          <td><?= h($stud) ?></td>
          <td><span class="badge bg-secondary"><?= h($d['diplo_status'] ?? '-') ?></span></td>
          <td>
            <?php if ($enabled): ?>
              <span class="badge bg-success">Ναι</span>
            <?php else: ?>
              <span class="badge bg-warning">Όχι</span>
            <?php endif; ?>
          </td>
          <td style="min-width:260px;">
            <?php if ($enabled): ?>
              <a class="btn btn-primary" href="thesis_details.php?diplo_id=<?= (int)$d['diplo_id'] ?>">Άνοιγμα λεπτομερειών</a>
            <?php else: ?>
              <form method="POST" onsubmit="return confirm('Ενεργοποίηση δυνατότητας βαθμολόγησης;');" style="display:inline;">
                <input type="hidden" name="diplo_id" value="<?= (int)$d['diplo_id'] ?>">
                <button type="submit" name="enable_grading" class="btn btn-success">Ενεργοποίηση</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

</div>
</body>
</html>
