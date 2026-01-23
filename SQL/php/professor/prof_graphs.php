<?php
session_start();
include("db_connect.php");
include("connected.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Μόνο καθηγητής
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

$prof = Professor_Connected($connection);
$profUserId = (int)($prof['professor_user_id'] ?? $prof['professor_id'] ?? 0);
if ($profUserId <= 0) die("Δεν βρέθηκαν στοιχεία καθηγητή.");

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function n0($v){ return (is_numeric($v) ? (float)$v : 0.0); } // null -> 0

// -----------------------------------------------------------
// 1) COUNTS
// -----------------------------------------------------------

// Σύνολο ως επιβλέπων
$stmt = $connection->prepare("SELECT COUNT(*) c FROM diplo WHERE diplo_professor=?");
$stmt->bind_param("i", $profUserId);
$stmt->execute();
$countSupervisor = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

// Σύνολο ως μέλος τριμελούς (distinct diplo_id) ΚΑΙ όχι επιβλέπων
$stmt = $connection->prepare("
    SELECT COUNT(DISTINCT d.diplo_id) c
    FROM diplo d
    JOIN trimelous t ON t.diplo_id = d.diplo_id
    WHERE (t.trimelous_professor1=? OR t.trimelous_professor2=? OR t.trimelous_professor3=?)
      AND d.diplo_professor <> ?
");
$stmt->bind_param("iiii", $profUserId, $profUserId, $profUserId, $profUserId);
$stmt->execute();
$countMember = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();


// -----------------------------------------------------------
// 2) AVG GRADES
// -----------------------------------------------------------

// Μέσος βαθμός επιβλέποντα
$stmt = $connection->prepare("
    SELECT AVG(diplo_grade) a
    FROM diplo
    WHERE diplo_professor=? AND diplo_grade IS NOT NULL
");
$stmt->bind_param("i", $profUserId);
$stmt->execute();
$avgGradeSupervisor = (float)($stmt->get_result()->fetch_assoc()['a'] ?? 0);
$stmt->close();
$avgGradeSupervisor = round($avgGradeSupervisor, 2);

// Μέσος βαθμός ως μέλος τριμελούς
$stmt = $connection->prepare("
    SELECT AVG(d.diplo_grade) a
    FROM diplo d
    JOIN trimelous t ON t.diplo_id = d.diplo_id
    WHERE (t.trimelous_professor1=? OR t.trimelous_professor2=? OR t.trimelous_professor3=?)
      AND d.diplo_professor <> ?
      AND d.diplo_grade IS NOT NULL
");
$stmt->bind_param("iiii", $profUserId, $profUserId, $profUserId, $profUserId);
$stmt->execute();
$avgGradeMember = (float)($stmt->get_result()->fetch_assoc()['a'] ?? 0);
$stmt->close();
$avgGradeMember = round($avgGradeMember, 2);


// -----------------------------------------------------------
// 3) AVG COMPLETION TIME (days)
//    from first active to first finished, based on diplo_date
// -----------------------------------------------------------

$avgDaysSupervisor = 0.0;
$avgDaysMember = 0.0;
$timeNoteSupervisor = "";
$timeNoteMember = "";

// helper query blocks (πιο “σωστό” από MIN/MAX ανά status)
try {
    // Επιβλέπων
    $stmt = $connection->prepare("
        SELECT AVG(x.days) a, COUNT(*) cnt
        FROM (
            SELECT
                TIMESTAMPDIFF(
                    DAY,
                    (SELECT MIN(dd1.diplo_date)
                     FROM diplo_date dd1
                     WHERE dd1.diplo_id = d.diplo_id AND dd1.diplo_status='active'
                    ),
                    (SELECT MIN(dd2.diplo_date)
                     FROM diplo_date dd2
                     WHERE dd2.diplo_id = d.diplo_id AND dd2.diplo_status='finished'
                    )
                ) AS days
            FROM diplo d
            WHERE d.diplo_professor = ?
              AND EXISTS (SELECT 1 FROM diplo_date a WHERE a.diplo_id=d.diplo_id AND a.diplo_status='active')
              AND EXISTS (SELECT 1 FROM diplo_date f WHERE f.diplo_id=d.diplo_id AND f.diplo_status='finished')
        ) x
        WHERE x.days IS NOT NULL AND x.days >= 0
    ");
    $stmt->bind_param("i", $profUserId);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $avgDaysSupervisor = n0($r['a'] ?? 0);
    $cntSupFinished = (int)($r['cnt'] ?? 0);
    $avgDaysSupervisor = round($avgDaysSupervisor, 1);

    if ($cntSupFinished === 0) {
        $timeNoteSupervisor = "Δεν υπάρχουν περατωμένες διπλωματικές με καταγεγραμμένα active/finished στο diplo_date.";
        $avgDaysSupervisor = 0.0;
    }

    // Μέλος τριμελούς
    $stmt = $connection->prepare("
        SELECT AVG(x.days) a, COUNT(*) cnt
        FROM (
            SELECT
                TIMESTAMPDIFF(
                    DAY,
                    (SELECT MIN(dd1.diplo_date)
                     FROM diplo_date dd1
                     WHERE dd1.diplo_id = d.diplo_id AND dd1.diplo_status='active'
                    ),
                    (SELECT MIN(dd2.diplo_date)
                     FROM diplo_date dd2
                     WHERE dd2.diplo_id = d.diplo_id AND dd2.diplo_status='finished'
                    )
                ) AS days
            FROM diplo d
            JOIN trimelous t ON t.diplo_id = d.diplo_id
            WHERE (t.trimelous_professor1=? OR t.trimelous_professor2=? OR t.trimelous_professor3=?)
              AND d.diplo_professor <> ?
              AND EXISTS (SELECT 1 FROM diplo_date a WHERE a.diplo_id=d.diplo_id AND a.diplo_status='active')
              AND EXISTS (SELECT 1 FROM diplo_date f WHERE f.diplo_id=d.diplo_id AND f.diplo_status='finished')
        ) x
        WHERE x.days IS NOT NULL AND x.days >= 0
    ");
    $stmt->bind_param("iiii", $profUserId, $profUserId, $profUserId, $profUserId);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $avgDaysMember = n0($r['a'] ?? 0);
    $cntMemFinished = (int)($r['cnt'] ?? 0);
    $avgDaysMember = round($avgDaysMember, 1);

    if ($cntMemFinished === 0) {
        $timeNoteMember = "Δεν υπάρχουν περατωμένες διπλωματικές (ως μέλος) με καταγεγραμμένα active/finished στο diplo_date.";
        $avgDaysMember = 0.0;
    }

} catch (Exception $e) {
    // π.χ. δεν υπάρχει diplo_date
    $avgDaysSupervisor = 0.0;
    $avgDaysMember = 0.0;
    $timeNoteSupervisor = "Δεν είναι διαθέσιμος ο υπολογισμός χρόνου (πιθανόν λείπει ο πίνακας diplo_date).";
    $timeNoteMember = "Δεν είναι διαθέσιμος ο υπολογισμός χρόνου (πιθανόν λείπει ο πίνακας diplo_date).";
}

?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>📊 Στατιστικά Διδάσκοντα</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<style>
body { font-family: Arial, sans-serif; background:#eef6ff; margin:0; }
.container { max-width:1100px; margin:40px auto; background:#fff;
             padding:20px 30px; border-radius:10px;
             box-shadow:0 0 10px rgba(0,0,0,.1); }

.top-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px; }
.subtitle { color:#555; font-size:.95rem; }
.btn { padding:8px 12px; border-radius:6px; text-decoration:none; color:#fff; font-size:.9rem; display:inline-block; }
.home { background:#198754; } .home:hover{background:#157347;}
.logout { background:#dc3545; } .logout:hover{background:#b52a37;}

.grid { display:grid; grid-template-columns:1fr 1fr; gap:15px; }
@media(max-width:900px){ .grid { grid-template-columns:1fr; } }

.card { background:#f8fbff; border:1px solid #dde7f5;
        border-radius:8px; padding:15px 20px; margin-bottom:20px; }

.big { font-size:2rem; font-weight:bold; }
.center { text-align:center; }

.note { margin-top:10px; background:#fff3cd; border:1px solid #ffecb5; color:#664d03;
        padding:10px 12px; border-radius:8px; font-size:.92rem; }
.small { color:#666; font-size:.92rem; }
</style>
</head>

<body>
<div class="container">

  <div class="top-bar">
    <div>
      <h1>📊 Στατιστικά Διδάσκοντα</h1>
      <div class="subtitle">Μέσος χρόνος περάτωσης, μέσος βαθμός, και πλήθος διπλωματικών</div>
    </div>
    <div>
      <a class="btn home" href="professor_page.php">Αρχική</a>
      <a class="btn logout" href="logout.php">Αποσύνδεση</a>
    </div>
  </div>

  <div class="grid">
    <div class="card center">
      <div>Συνολικό πλήθος (Επιβλέπων)</div>
      <div class="big"><?= (int)$countSupervisor ?></div>
      <div class="small">Όλες οι διπλωματικές με diplo_professor = εσάς</div>
    </div>

    <div class="card center">
      <div>Συνολικό πλήθος (Μέλος τριμελούς)</div>
      <div class="big"><?= (int)$countMember ?></div>
      <div class="small">Distinct διπλωματικές που είστε σε trimelous</div>
    </div>
  </div>

  <div class="card">
    <h3>⏱ Μέσος χρόνος περάτωσης (ημέρες)</h3>
    <canvas id="timeChart"></canvas>
    <p class="subtitle">Επιβλέπων: <strong><?= h($avgDaysSupervisor) ?></strong> • Μέλος: <strong><?= h($avgDaysMember) ?></strong></p>

    <?php if ($timeNoteSupervisor): ?>
      <div class="note"><strong>Επιβλέπων:</strong> <?= h($timeNoteSupervisor) ?></div>
    <?php endif; ?>
    <?php if ($timeNoteMember): ?>
      <div class="note"><strong>Μέλος:</strong> <?= h($timeNoteMember) ?></div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>🎓 Μέσος βαθμός</h3>
    <canvas id="gradeChart"></canvas>
    <p class="subtitle">Επιβλέπων: <strong><?= h($avgGradeSupervisor) ?></strong> • Μέλος: <strong><?= h($avgGradeMember) ?></strong></p>
  </div>

</div>

<script>
const labels = ["Επιβλέπων", "Μέλος τριμελούς"];

new Chart(document.getElementById("timeChart"), {
  type: "bar",
  data: {
    labels,
    datasets: [{
      label: "Ημέρες",
      data: [<?= (float)$avgDaysSupervisor ?>, <?= (float)$avgDaysMember ?>]
    }]
  },
  options: {
    responsive: true,
    scales: { y: { beginAtZero: true } }
  }
});

new Chart(document.getElementById("gradeChart"), {
  type: "bar",
  data: {
    labels,
    datasets: [{
      label: "Βαθμός",
      data: [<?= (float)$avgGradeSupervisor ?>, <?= (float)$avgGradeMember ?>]
    }]
  },
  options: {
    responsive: true,
    scales: { y: { beginAtZero: true, suggestedMax: 10 } }
  }
});
</script>

</body>
</html>
