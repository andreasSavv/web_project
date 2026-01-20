<?php
session_start();
include("db_connect.php");
include("connected.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Μόνο γραμματεία (όπως στο secretary_page σου)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'secretary') {
    header("Location: login.php");
    exit;
}

$diploId = isset($_GET['diplo_id']) ? (int)$_GET['diplo_id'] : 0;
if ($diploId <= 0) die("Λάθος diplo_id.");

// ---------- Φέρνουμε διπλωματική + student + trimelous + ονόματα τριμελούς ----------
$sql = "
SELECT
  d.diplo_id,
  d.diplo_title,
  d.diplo_desc,
  d.diplo_pdf,
  d.diplo_status,
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
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) die("Δεν βρέθηκε διπλωματική.");

// Γραμματεία βλέπει μόνο active + under review
$status = (string)($row['diplo_status'] ?? '');
if (!in_array($status, ['active','under review','under_review'], true)) {
    die("Η Γραμματεία βλέπει μόνο ΔΕ σε κατάσταση 'active' ή 'under review'.");
}

function profFull($n, $s) {
    $n = trim((string)$n);
    $s = trim((string)$s);
    $full = trim($s . " " . $n);
    return $full !== "" ? $full : "-";
}

// ---------- Timeline αλλαγών κατάστασης ----------
$timeline = [];
$sqlTL = "SELECT diplo_date, diplo_status
          FROM diplo_date
          WHERE diplo_id = ?
          ORDER BY diplo_date ASC";
$stmtTL = $connection->prepare($sqlTL);
$stmtTL->bind_param("i", $diploId);
$stmtTL->execute();
$resTL = $stmtTL->get_result();
while ($r = $resTL->fetch_assoc()) $timeline[] = $r;
$stmtTL->close();

// ---------- Επίσημη ανάθεση = πρώτη ημερομηνία που έγινε active ----------
$assignedAt = null;
foreach ($timeline as $t) {
    if (($t['diplo_status'] ?? '') === 'active') {
        $assignedAt = $t['diplo_date'];
        break;
    }
}

$elapsedText = "Δεν υπάρχει επίσημη ανάθεση ακόμα.";
if (!empty($assignedAt)) {
    $assigned = new DateTime($assignedAt);
    $now = new DateTime();
    $diff = $assigned->diff($now);

    $parts = [];
    if ($diff->y) $parts[] = $diff->y . " έτη";
    if ($diff->m) $parts[] = $diff->m . " μήνες";
    if ($diff->d) $parts[] = $diff->d . " ημέρες";
    if (!$diff->y && !$diff->m && !$diff->d) $parts[] = "0 ημέρες";

    $elapsedText = implode(", ", $parts);
}

$studentFull = "-";
if (!empty($row['student_am'])) {
    $studentFull = ($row['student_surname'] ?? '') . " " . ($row['student_name'] ?? '') . " (ΑΜ: " . ($row['student_am'] ?? '') . ")";
    $studentFull = trim($studentFull);
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <title>Λεπτομέρειες Διπλωματικής (Γραμματεία)</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark p-2">
  <span class="navbar-brand ms-3">Λεπτομέρειες ΔΕ (Γραμματεία)</span>
  <div class="ms-auto me-3 d-flex gap-2">
    <a href="secretary_view_diplo.php" class="btn btn-secondary">Πίσω</a>
    <a href="secretary_page.php" class="btn btn-success">Αρχική</a>
    <a href="logout.php" class="btn btn-danger">Αποσύνδεση</a>
  </div>
</nav>

<div class="container mt-4" style="max-width: 1000px;">
  <div class="card shadow-sm">
    <div class="card-header fw-bold">
      Διπλωματική #<?= (int)$row['diplo_id'] ?> — <?= htmlspecialchars($row['diplo_title'] ?? '') ?>
    </div>

    <div class="card-body">
      <p><strong>Κατάσταση:</strong> <?= htmlspecialchars($row['diplo_status'] ?? '-') ?></p>
      <p><strong>Φοιτητής:</strong> <?= htmlspecialchars($studentFull) ?></p>

      <p><strong>Χρόνος από επίσημη ανάθεση:</strong>
        <?php if (!empty($assignedAt)): ?>
          <?= htmlspecialchars($elapsedText) ?>
          <span class="text-muted">(από <?= htmlspecialchars($assignedAt) ?>)</span>
        <?php else: ?>
          <span class="text-muted"><?= htmlspecialchars($elapsedText) ?></span>
        <?php endif; ?>
      </p>

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

      <h5 class="fw-bold">Χρονολόγιο ενεργειών (αλλαγές κατάστασης)</h5>
      <?php if (empty($timeline)): ?>
        <p class="text-muted">Δεν υπάρχουν καταχωρημένες αλλαγές κατάστασης.</p>
      <?php else: ?>
        <table class="table table-sm table-bordered mt-2">
          <thead class="table-light">
            <tr>
              <th style="width: 240px;">Ημερομηνία</th>
              <th>Κατάσταση</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($timeline as $t): ?>
            <tr>
              <td><?= htmlspecialchars($t['diplo_date'] ?? '-') ?></td>
              <td><?= htmlspecialchars($t['diplo_status'] ?? '-') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

    </div>
  </div>
</div>

</body>
</html>
