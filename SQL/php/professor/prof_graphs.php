<?php
session_start();
include("db_connect.php");
include("connected.php");

// Debug (βγάλ’ τα μετά αν θες)
error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

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

/*
  Βοηθητική SQL ιδέα για χρόνο περάτωσης:
  start_date = MIN(diplo_date) ή MIN(pending) αν υπάρχει
  end_date   = MAX(diplo_date όπου status='finished')
  days = TIMESTAMPDIFF(DAY, start_date, end_date)
*/

// ---------------------------- (A) Επιβλέπων ----------------------------

// Συνολικό πλήθος διπλωματικών ως επιβλέπων
$sqlCountSup = "SELECT COUNT(*) AS c
                FROM diplo
                WHERE diplo_professor = ?";
$stmt = $connection->prepare($sqlCountSup);
$stmt->bind_param("i", $profUserId);
$stmt->execute();
$countSupervisor = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

// Μέσος βαθμός ως επιβλέπων (μόνο όπου υπάρχει grade)
$sqlAvgGradeSup = "SELECT AVG(diplo_grade) AS avg_grade
                   FROM diplo
                   WHERE diplo_professor = ?
                     AND diplo_grade IS NOT NULL";
$stmt = $connection->prepare($sqlAvgGradeSup);
$stmt->bind_param("i", $profUserId);
$stmt->execute();
$avgGradeSupervisor = (float)($stmt->get_result()->fetch_assoc()['avg_grade'] ?? 0);
$stmt->close();

// Μέσος χρόνος περάτωσης ως επιβλέπων (μόνο finished)
$sqlAvgTimeSup = "
SELECT AVG(x.days_to_finish) AS avg_days
FROM (
    SELECT
      d.diplo_id,
      TIMESTAMPDIFF(
        DAY,
        COALESCE(MIN(CASE WHEN dd.diplo_status='pending' THEN dd.diplo_date END), MIN(dd.diplo_date)),
        MAX(CASE WHEN dd.diplo_status='finished' THEN dd.diplo_date END)
      ) AS days_to_finish
    FROM diplo d
    JOIN diplo_date dd ON dd.diplo_id = d.diplo_id
    WHERE d.diplo_professor = ?
    GROUP BY d.diplo_id
    HAVING MAX(CASE WHEN dd.diplo_status='finished' THEN dd.diplo_date END) IS NOT NULL
) x";
$stmt = $connection->prepare($sqlAvgTimeSup);
$stmt->bind_param("i", $profUserId);
$stmt->execute();
$avgDaysSupervisor = (float)($stmt->get_result()->fetch_assoc()['avg_days'] ?? 0);
$stmt->close();


// ---------------------------- (B) Μέλος τριμελούς ----------------------------
// Μέλος = σε trimelous_professor1/2/3 αλλά όχι επιβλέπων (για να μην διπλομετράει)

$sqlCountMem = "SELECT COUNT(DISTINCT d.diplo_id) AS c
                FROM diplo d
                JOIN trimelous t ON t.diplo_id = d.diplo_id
                WHERE (t.trimelous_professor1 = ? OR t.trimelous_professor2 = ? OR t.trimelous_professor3 = ?)
                  AND d.diplo_professor <> ?";
$stmt = $connection->prepare($sqlCountMem);
$stmt->bind_param("iiii", $profUserId, $profUserId, $profUserId, $profUserId);
$stmt->execute();
$countMember = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

// Μέσος βαθμός ως μέλος τριμελούς
$sqlAvgGradeMem = "SELECT AVG(d.diplo_grade) AS avg_grade
                   FROM diplo d
                   JOIN trimelous t ON t.diplo_id = d.diplo_id
                   WHERE (t.trimelous_professor1 = ? OR t.trimelous_professor2 = ? OR t.trimelous_professor3 = ?)
                     AND d.diplo_professor <> ?
                     AND d.diplo_grade IS NOT NULL";
$stmt = $connection->prepare($sqlAvgGradeMem);
$stmt->bind_param("iiii", $profUserId, $profUserId, $profUserId, $profUserId);
$stmt->execute();
$avgGradeMember = (float)($stmt->get_result()->fetch_assoc()['avg_grade'] ?? 0);
$stmt->close();

// Μέσος χρόνος περάτωσης ως μέλος (μόνο finished)
$sqlAvgTimeMem = "
SELECT AVG(x.days_to_finish) AS avg_days
FROM (
    SELECT
      d.diplo_id,
      TIMESTAMPDIFF(
        DAY,
        COALESCE(MIN(CASE WHEN dd.diplo_status='pending' THEN dd.diplo_date END), MIN(dd.diplo_date)),
        MAX(CASE WHEN dd.diplo_status='finished' THEN dd.diplo_date END)
      ) AS days_to_finish
    FROM diplo d
    JOIN trimelous t ON t.diplo_id = d.diplo_id
    JOIN diplo_date dd ON dd.diplo_id = d.diplo_id
    WHERE (t.trimelous_professor1 = ? OR t.trimelous_professor2 = ? OR t.trimelous_professor3 = ?)
      AND d.diplo_professor <> ?
    GROUP BY d.diplo_id
    HAVING MAX(CASE WHEN dd.diplo_status='finished' THEN dd.diplo_date END) IS NOT NULL
) x";
$stmt = $connection->prepare($sqlAvgTimeMem);
$stmt->bind_param("iiii", $profUserId, $profUserId, $profUserId, $profUserId);
$stmt->execute();
$avgDaysMember = (float)($stmt->get_result()->fetch_assoc()['avg_days'] ?? 0);
$stmt->close();


// Μορφοποίηση για εμφάνιση
$avgDaysSupervisorDisp = round($avgDaysSupervisor, 1);
$avgDaysMemberDisp     = round($avgDaysMember, 1);

$avgGradeSupervisorDisp = round($avgGradeSupervisor, 2);
$avgGradeMemberDisp     = round($avgGradeMember, 2);
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <title>Στατιστικά Διδάσκοντα</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>

<nav class="navbar navbar-dark bg-dark p-2">
  <span class="navbar-brand ms-3">Στατιστικά Διδάσκοντα</span>
  <a href="professor_page.php" class="btn btn-success ms-auto me-2">Αρχική</a>
  <a href="logout.php" class="btn btn-danger me-3">Αποσύνδεση</a>
</nav>

<div class="container mt-4" style="max-width: 1100px;">

  <div class="row g-3 mb-3">
    <div class="col-md-6">
      <div class="card p-3 shadow-sm">
        <h6 class="fw-bold mb-1">Σύνολο διπλωματικών (Επιβλέπων)</h6>
        <div class="display-6"><?= (int)$countSupervisor ?></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card p-3 shadow-sm">
        <h6 class="fw-bold mb-1">Σύνολο διπλωματικών (Μέλος τριμελούς)</h6>
        <div class="display-6"><?= (int)$countMember ?></div>
      </div>
    </div>
  </div>

  <div class="card p-3 shadow-sm mb-4">
    <h5 class="fw-bold mb-3">i) Μέσος χρόνος περάτωσης (ημέρες) — μόνο για Περατωμένες</h5>
    <canvas id="avgTimeChart" height="110"></canvas>
    <div class="text-muted mt-2">
      Επιβλέπων: <?= $avgDaysSupervisorDisp ?> ημέρες • Μέλος τριμελούς: <?= $avgDaysMemberDisp ?> ημέρες
    </div>
  </div>

  <div class="card p-3 shadow-sm mb-4">
    <h5 class="fw-bold mb-3">ii) Μέσος βαθμός — μόνο όπου υπάρχει βαθμός</h5>
    <canvas id="avgGradeChart" height="110"></canvas>
    <div class="text-muted mt-2">
      Επιβλέπων: <?= $avgGradeSupervisorDisp ?> • Μέλος τριμελούς: <?= $avgGradeMemberDisp ?>
    </div>
  </div>

  <div class="card p-3 shadow-sm mb-4">
    <h5 class="fw-bold mb-3">iii) Συνολικό πλήθος διπλωματικών</h5>
    <canvas id="countChart" height="110"></canvas>
  </div>

</div>

<script>
const labels = ["Επιβλέπων", "Μέλος τριμελούς"];

new Chart(document.getElementById('avgTimeChart'), {
  type: 'bar',
  data: {
    labels,
    datasets: [{
      label: 'Μέσος χρόνος περάτωσης (ημέρες)',
      data: [<?= json_encode($avgDaysSupervisorDisp) ?>, <?= json_encode($avgDaysMemberDisp) ?>]
    }]
  },
  options: {
    responsive: true,
    scales: { y: { beginAtZero: true } }
  }
});

new Chart(document.getElementById('avgGradeChart'), {
  type: 'bar',
  data: {
    labels,
    datasets: [{
      label: 'Μέσος βαθμός',
      data: [<?= json_encode($avgGradeSupervisorDisp) ?>, <?= json_encode($avgGradeMemberDisp) ?>]
    }]
  },
  options: {
    responsive: true,
    scales: { y: { beginAtZero: true } }
  }
});

new Chart(document.getElementById('countChart'), {
  type: 'bar',
  data: {
    labels,
    datasets: [{
      label: 'Πλήθος διπλωματικών',
      data: [<?= json_encode($countSupervisor) ?>, <?= json_encode($countMember) ?>]
    }]
  },
  options: {
    responsive: true,
    scales: { y: { beginAtZero: true } }
  }
});
</script>

</body>
</html>
