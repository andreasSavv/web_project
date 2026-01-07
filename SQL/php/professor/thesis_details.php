<?php
session_start();
include("db_connect.php");
include("connected.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Μόνο καθηγητής
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

$prof = Professor_Connected($connection);
$profUserId = (int)($prof['professor_user_id'] ?? 0);
if ($profUserId <= 0) {
    die("Δεν βρέθηκαν στοιχεία καθηγητή.");
}

$diploId = isset($_GET['diplo_id']) ? (int)$_GET['diplo_id'] : 0;
if ($diploId <= 0) {
    die("Λάθος diplo_id.");
}

/*
  Φέρνουμε:
  - diplo
  - student (με βάση diplo_student = student_am)
  - trimelous (prof1/2/3 = professor_user_id)
  - ονόματα καθηγητών τριμελούς
  Και ΕΛΕΓΧΟΥΜΕ ότι ο καθηγητής είναι είτε:
    - επιβλέπων (μέσω diplo_professor -> professor.professor_id -> professor_user_id)
    - ή μέλος τριμελούς (trimelous_professor1/2/3)
*/
$sql = "
SELECT
  d.diplo_id,
  d.diplo_title,
  d.diplo_desc,
  d.diplo_pdf,
  d.diplo_status,
  d.diplo_grade,
  d.diplo_student,

  s.student_am,
  s.student_name,
  s.student_surname,

  t.trimelous_professor1,
  t.trimelous_professor2,
  t.trimelous_professor3,

  p1.professor_name  AS p1_name, p1.professor_surname AS p1_surname,
  p2.professor_name  AS p2_name, p2.professor_surname AS p2_surname,
  p3.professor_name  AS p3_name, p3.professor_surname AS p3_surname

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
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    die("Δεν βρέθηκε διπλωματική.");
}

// ✅ Έλεγχος δικαιώματος: supervisor ή μέλος τριμελούς
// Supervisor: diplo_professor δείχνει professor_id, άρα το βρίσκουμε με έξτρα query:
$sqlSup = "
SELECT COUNT(*) AS c
FROM diplo d
JOIN professor p ON d.diplo_professor = p.professor_user_id
WHERE d.diplo_id = ?
  AND p.professor_user_id = ?
";
$stmtSup = $connection->prepare($sqlSup);
$stmtSup->bind_param("ii", $diploId, $profUserId);
$stmtSup->execute();
$resSup = $stmtSup->get_result();
$isSupervisor = ((int)($resSup->fetch_assoc()['c'] ?? 0) > 0);
$stmtSup->close();

$isMember = (
    (int)($row['trimelous_professor1'] ?? 0) === $profUserId ||
    (int)($row['trimelous_professor2'] ?? 0) === $profUserId ||
    (int)($row['trimelous_professor3'] ?? 0) === $profUserId
);

if (!$isSupervisor && !$isMember) {
    die("Δεν έχετε δικαίωμα προβολής αυτής της διπλωματικής.");
}

function profFull($n, $s) {
    $n = trim((string)$n);
    $s = trim((string)$s);
    $full = trim($s . " " . $n);
    return $full !== "" ? $full : "-";
}

$studentFull = "-";
if (!empty($row['student_am'])) {
    $studentFull = $row['student_surname'] . " " . $row['student_name'] . " (ΑΜ: " . $row['student_am'] . ")";
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <title>Λεπτομέρειες Διπλωματικής</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container mt-4" style="max-width: 900px;">
  <a class="btn btn-secondary mb-3" href="diplomas.php">⟵ Πίσω στη λίστα</a>

  <div class="card shadow-sm">
    <div class="card-header fw-bold">
      Διπλωματική #<?= (int)$row['diplo_id'] ?> — <?= htmlspecialchars($row['diplo_title'] ?? '') ?>
    </div>
    <div class="card-body">
      <p><strong>Κατάσταση:</strong> <?= htmlspecialchars($row['diplo_status'] ?? '-') ?></p>
      <p><strong>Φοιτητής:</strong> <?= htmlspecialchars($studentFull) ?></p>

      <hr>

      <p><strong>Περιγραφή:</strong><br>
        <?= nl2br(htmlspecialchars($row['diplo_desc'] ?? '')) ?>
      </p>

      <p><strong>PDF:</strong>
        <?php if (!empty($row['diplo_pdf'])): ?>
          <a href="<?= htmlspecialchars($row['diplo_pdf']) ?>" target="_blank">Άνοιγμα PDF</a>
        <?php else: ?>
          <span class="text-muted">-</span>
        <?php endif; ?>
      </p>

      <hr>

      <h5 class="fw-bold">Τριμελής</h5>
      <ul>
        <li>Professor 1 (Επιβλέπων): <?= htmlspecialchars(profFull($row['p1_name'], $row['p1_surname'])) ?></li>
        <li>Professor 2: <?= htmlspecialchars(profFull($row['p2_name'], $row['p2_surname'])) ?></li>
        <li>Professor 3: <?= htmlspecialchars(profFull($row['p3_name'], $row['p3_surname'])) ?></li>
      </ul>

      <hr>

      <p><strong>Τελικός βαθμός:</strong> <?= htmlspecialchars($row['diplo_grade'] ?? '-') ?></p>

      <!-- placeholders για να το επεκτείνουμε μετά -->
      <p class="text-muted mb-0">
        Timeline αλλαγών κατάστασης / Νημερτής / Πρακτικό: θα τα δέσουμε μόλις αποφασίσουμε πού είναι στη ΒΔ σου.
      </p>

    </div>
  </div>
</div>

</body>
</html>
