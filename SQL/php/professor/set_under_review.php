<?php
session_start();
include("db_connect.php");
include("connected.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Έλεγχος ρόλου
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

$user = Professor_Connected($connection);
$profUserId = (int)($user['professor_user_id'] ?? 0);
if ($profUserId <= 0) {
    die("Δεν βρέθηκαν στοιχεία καθηγητή.");
}

$diplo_id = (int)($_GET['diplo_id'] ?? 0);
if ($diplo_id <= 0) die("Μη έγκυρο diplo_id.");

// για επιστροφή στη λίστα με φίλτρα
$returnStatus = $_GET['status'] ?? '';
$returnRole   = $_GET['role'] ?? '';

// ------------------ Φέρνουμε τα στοιχεία της διπλωματικής + ρόλο καθηγητή ------------------
$sql = "
SELECT
  d.*,
  s.student_name,
  s.student_surname,

  psup.professor_user_id AS supervisor_user_id,

  t.trimelous_professor1,
  t.trimelous_professor2,
  t.trimelous_professor3,

  pSup.professor_name AS sup_name, pSup.professor_surname AS sup_surname,
  p1.professor_name AS p1_name, p1.professor_surname AS p1_surname,
  p2.professor_name AS p2_name, p2.professor_surname AS p2_surname,
  p3.professor_name AS p3_name, p3.professor_surname AS p3_surname

FROM diplo d
LEFT JOIN student s ON s.student_am = d.diplo_student
LEFT JOIN trimelous t ON t.diplo_id = d.diplo_id

LEFT JOIN professor psup ON psup.professor_user_id = d.diplo_professor
LEFT JOIN professor pSup ON pSup.professor_user_id = d.diplo_professor

LEFT JOIN professor p1 ON p1.professor_user_id = t.trimelous_professor1
LEFT JOIN professor p2 ON p2.professor_user_id = t.trimelous_professor2
LEFT JOIN professor p3 ON p3.professor_user_id = t.trimelous_professor3

WHERE d.diplo_id = ?
LIMIT 1
";

$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $diplo_id);
$stmt->execute();
$thesis = $stmt->get_result()->fetch_assoc();

if (!$thesis) {
    die("Δεν βρέθηκε διπλωματική.");
}

// Έλεγχος πρόσβασης: supervisor ή μέλος τριμελούς
$isSupervisor = ((int)($thesis['supervisor_user_id'] ?? 0) === $profUserId);
$isMember = (
    ((int)($thesis['trimelous_professor1'] ?? 0) === $profUserId) ||
    ((int)($thesis['trimelous_professor2'] ?? 0) === $profUserId) ||
    ((int)($thesis['trimelous_professor3'] ?? 0) === $profUserId)
);

if (!$isSupervisor && !$isMember) {
    die("Δεν έχετε πρόσβαση σε αυτή τη διπλωματική.");
}

// ------------------ Αλλαγή σε 'under review' (μόνο αν supervisor + active) ------------------
$message = "";
if (isset($_POST['set_under_review'])) {

    if (!$isSupervisor) {
        $message = "❌ Μόνο ο επιβλέπων μπορεί να αλλάξει κατάσταση.";
    } elseif (($thesis['diplo_status'] ?? '') !== 'active') {
        $message = "❌ Επιτρέπεται μόνο όταν η διπλωματική είναι active.";
    } else {

        $upd = $connection->prepare("
            UPDATE diplo
            SET diplo_status = 'under review'
            WHERE diplo_id = ?
              AND diplo_professor = ?
              AND diplo_status = 'active'
        ");
        // diplo_professor είναι professor_user_id στο δικό σου query usage
        $upd->bind_param("ii", $diplo_id, $profUserId);

        if ($upd->execute() && $upd->affected_rows > 0) {
            // redirect πίσω στις λεπτομέρειες (για refresh)
            header("Location: thesis_details.php?diplo_id=$diplo_id&status=" . urlencode($returnStatus) . "&role=" . urlencode($returnRole) . "&msg=" . urlencode("✅ Έγινε μετάβαση σε 'Υπό Εξέταση'"));
            exit;
        } else {
            $message = "❌ Δεν έγινε αλλαγή (ίσως άλλαξε ήδη ή δεν είστε επιβλέπων).";
        }
    }
}

if (isset($_GET['msg'])) $message = $_GET['msg'];

// ---------- UI helpers ----------
$stud = "-";
if (!empty($thesis['diplo_student'])) {
    $stud = $thesis['diplo_student'] . " - " . trim(($thesis['student_surname'] ?? '') . " " . ($thesis['student_name'] ?? ''));
    $stud = trim($stud);
}

$sup = trim(($thesis['sup_surname'] ?? '') . " " . ($thesis['sup_name'] ?? ''));

$p1 = trim(($thesis['p1_surname'] ?? '') . " " . ($thesis['p1_name'] ?? ''));
$p2 = trim(($thesis['p2_surname'] ?? '') . " " . ($thesis['p2_name'] ?? ''));
$p3 = trim(($thesis['p3_surname'] ?? '') . " " . ($thesis['p3_name'] ?? ''));

$committeeParts = [];
if ($sup !== '') $committeeParts[] = "Επιβλέπων: $sup";
if ($p1 !== '') $committeeParts[] = "Μέλος: $p1";
if ($p2 !== '') $committeeParts[] = "Μέλος: $p2";
if ($p3 !== '') $committeeParts[] = "Μέλος: $p3";
$committee = !empty($committeeParts) ? implode(" | ", $committeeParts) : "-";
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <title>Λεπτομέρειες Διπλωματικής</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-dark bg-dark p-2">
  <span class="navbar-brand ms-3">Λεπτομέρειες Διπλωματικής</span>
  <a href="diplomas.php?status=<?= urlencode($returnStatus) ?>&role=<?= urlencode($returnRole) ?>"
     class="btn btn-secondary ms-auto me-2">Πίσω στη λίστα</a>
  <a href="logout.php" class="btn btn-danger me-3">Αποσύνδεση</a>
</nav>

<div class="container mt-4">

  <?php if ($message): ?>
    <div class="alert alert-info text-center"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-header fw-bold">
      <?= htmlspecialchars($thesis['diplo_title'] ?? '') ?>
      <span class="badge bg-dark ms-2"><?= htmlspecialchars($thesis['diplo_status'] ?? '-') ?></span>
      <?php if ($isSupervisor): ?>
        <span class="badge bg-primary ms-2">Επιβλέπων</span>
      <?php else: ?>
        <span class="badge bg-secondary ms-2">Μέλος τριμελούς</span>
      <?php endif; ?>
    </div>

    <div class="card-body">
      <p><strong>Περιγραφή:</strong><br><?= nl2br(htmlspecialchars($thesis['diplo_desc'] ?? '')) ?></p>
      <p><strong>Φοιτητής:</strong> <?= htmlspecialchars($stud) ?></p>
      <p><strong>Τριμελής:</strong> <?= htmlspecialchars($committee) ?></p>

      <p><strong>PDF:</strong>
        <?php if (!empty($thesis['diplo_pdf'])): ?>
          <a href="<?= htmlspecialchars($thesis['diplo_pdf']) ?>" target="_blank">Άνοιγμα PDF</a>
        <?php else: ?>
          -
        <?php endif; ?>
      </p>

      <?php if (($thesis['diplo_status'] ?? '') === 'finished'): ?>
        <p><strong>Τελικός Βαθμός:</strong> <?= htmlspecialchars($thesis['diplo_grade'] ?? '-') ?></p>
        <p><strong>Nimertis link:</strong>
          <?php if (!empty($thesis['nimertis_link'])): ?>
            <a href="<?= htmlspecialchars($thesis['nimertis_link']) ?>" target="_blank">Άνοιγμα</a>
          <?php else: ?>
            -
          <?php endif; ?>
        </p>
      <?php endif; ?>

      <!-- ✅ ΕΝΕΡΓΕΙΑ: Μόνο επιβλέπων + active -->
      <?php if ($isSupervisor && ($thesis['diplo_status'] ?? '') === 'active'): ?>
        <hr>
        <form method="POST">
          <button type="submit" name="set_under_review" class="btn btn-warning w-100"
                  onclick="return confirm('Να αλλάξει η κατάσταση σε Υπό Εξέταση (under review);')">
            Μετάβαση σε "Υπό Εξέταση"
          </button>
        </form>
      <?php endif; ?>

    </div>
  </div>

</div>
</body>
</html>
