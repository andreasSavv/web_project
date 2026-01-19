<?php
session_start();
include("db_connect.php");
include("connected.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Μόνο professor
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

$user = Professor_Connected($connection);
$profUserId = (int)($user['professor_user_id'] ?? $user['professor_id'] ?? 0);
if ($profUserId <= 0) die("Δεν βρέθηκε professor id.");

$diplo_id = (int)($_GET['diplo_id'] ?? 0);
if ($diplo_id <= 0) die("Μη έγκυρο diplo_id.");

// Φέρνουμε διπλωματική + ελέγχουμε συμμετοχή καθηγητή (επιβλέπων ή τριμελής)
$sql = "
SELECT
  d.diplo_id,
  d.diplo_title,
  d.diplo_status,
  d.diplo_student,

  s.student_name,
  s.student_surname,

  psup.professor_user_id AS supervisor_user_id,

  t.trimelous_professor1,
  t.trimelous_professor2,
  t.trimelous_professor3,

  dr.draft_diplo_pdf,
  dr.draft_links

FROM diplo d
LEFT JOIN student s ON s.student_am = d.diplo_student
LEFT JOIN professor psup ON psup.professor_user_id = d.diplo_professor
LEFT JOIN trimelous t ON t.diplo_id = d.diplo_id
LEFT JOIN draft dr ON dr.diplo_id = d.diplo_id

WHERE d.diplo_id = ?
LIMIT 1
";

$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $diplo_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) die("Δεν βρέθηκε διπλωματική.");

// Έλεγχος πρόσβασης: επιβλέπων ή μέλος τριμελούς
$isSupervisor = ((int)($row['supervisor_user_id'] ?? 0) === $profUserId);
$isMember = (
    ((int)($row['trimelous_professor1'] ?? 0) === $profUserId) ||
    ((int)($row['trimelous_professor2'] ?? 0) === $profUserId) ||
    ((int)($row['trimelous_professor3'] ?? 0) === $profUserId)
);

if (!$isSupervisor && !$isMember) {
    die("❌ Δεν έχετε πρόσβαση στο πρόχειρο αυτής της διπλωματικής.");
}

// (Προαιρετικό) επιτρέπουμε προβολή draft μόνο όταν είναι under review
$status = (string)($row['diplo_status'] ?? '');
if ($status !== 'under review' && $status !== 'under_review') {
    // αν θες να επιτρέπεται και σε finished, απλά αφαίρεσέ το
    die("⚠ Το πρόχειρο εμφανίζεται μόνο όταν η διπλωματική είναι σε 'Υπό Εξέταση'.");
}

// Links σε array
function links_to_array($text) {
    $lines = preg_split("/\r\n|\n|\r/", (string)$text);
    $out = [];
    foreach ($lines as $l) {
        $l = trim($l);
        if ($l !== "") $out[] = $l;
    }
    return $out;
}

$linksArr = links_to_array($row['draft_links'] ?? '');
$stud = "-";
if (!empty($row['diplo_student'])) {
    $stud = $row['diplo_student'] . " - " . trim(($row['student_surname'] ?? '') . " " . ($row['student_name'] ?? ''));
    $stud = trim($stud);
}

$roleBadge = $isSupervisor ? "Επιβλέπων" : "Μέλος τριμελούς";
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <title>Πρόχειρο Διπλωματικής</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark p-2">
  <span class="navbar-brand ms-3">Πρόχειρο Διπλωματικής</span>
  <div class="ms-auto d-flex gap-2 me-3">
    <a href="diplomas.php" class="btn btn-secondary">Πίσω στη λίστα</a>
    <a href="professor_page.php" class="btn btn-success">Αρχική</a>
    <a href="logout.php" class="btn btn-danger">Αποσύνδεση</a>
  </div>
</nav>

<div class="container mt-4" style="max-width: 950px;">

  <div class="card shadow-sm">
    <div class="card-header fw-bold">
      <?= htmlspecialchars($row['diplo_title'] ?? '') ?>
      <span class="badge bg-primary ms-2"><?= htmlspecialchars($roleBadge) ?></span>
      <span class="badge bg-dark ms-2"><?= htmlspecialchars($row['diplo_status'] ?? '-') ?></span>
    </div>
    <div class="card-body">
      <p><strong>Φοιτητής:</strong> <?= htmlspecialchars($stud) ?></p>

      <hr>

      <h5 class="fw-bold">1) Πρόχειρο κείμενο (PDF)</h5>
      <?php if (!empty($row['draft_diplo_pdf'])): ?>
        <p>
          <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars($row['draft_diplo_pdf']) ?>" target="_blank">
            Άνοιγμα PDF
          </a>
        </p>
      <?php else: ?>
        <div class="alert alert-warning py-2">⚠ Δεν έχει ανέβει ακόμα πρόχειρο PDF.</div>
      <?php endif; ?>

      <hr>

      <h5 class="fw-bold">2) Links προς υλικό</h5>
      <?php if (empty($linksArr)): ?>
        <p class="text-muted">Δεν υπάρχουν links.</p>
      <?php else: ?>
        <ul class="list-group">
          <?php foreach ($linksArr as $url): ?>
            <li class="list-group-item">
              <a href="<?= htmlspecialchars($url) ?>" target="_blank"><?= htmlspecialchars($url) ?></a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

    </div>
  </div>

</div>
</body>
</html>
