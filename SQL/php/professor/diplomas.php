<?php
session_start();
include("db_connect.php");
include("connected.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


// Έλεγχος αν είναι καθηγητής
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

$user = Professor_Connected($connection);
$profUserId = (int)($user['professor_user_id'] ?? 0);
if ($profUserId <= 0) {
    die("Δεν βρέθηκαν στοιχεία καθηγητή.");
}

// ------------------ Φιλτράρισμα ------------------
$status_filter = $_GET['status'] ?? '';
$role_filter   = $_GET['role'] ?? '';

// ------------------ Query διπλωματικών (Supervisor + Member) ------------------
$query = "
SELECT
  d.diplo_id,
  d.diplo_title,
  d.diplo_desc,
  d.diplo_status,
  d.diplo_grade,
  d.diplo_pdf,
  d.diplo_student,

  s.student_name,
  s.student_surname,

  CASE
    WHEN psup.professor_user_id = $profUserId THEN 'supervisor'
    ELSE 'member'
  END AS role_in_thesis,

  t.trimelous_professor1,
  t.trimelous_professor2,
  t.trimelous_professor3,

  p1.professor_name AS p1_name, p1.professor_surname AS p1_surname,
  p2.professor_name AS p2_name, p2.professor_surname AS p2_surname,
  p3.professor_name AS p3_name, p3.professor_surname AS p3_surname

FROM diplo d
LEFT JOIN student s ON s.student_am = d.diplo_student
LEFT JOIN trimelous t ON t.diplo_id = d.diplo_id

-- επιβλέπων: diplo_professor -> professor_id
LEFT JOIN professor psup ON psup.professor_user_id = d.diplo_professor



-- ονόματα τριμελούς: professor_user_id -> professor table
LEFT JOIN professor p1 ON p1.professor_user_id = t.trimelous_professor1
LEFT JOIN professor p2 ON p2.professor_user_id = t.trimelous_professor2
LEFT JOIN professor p3 ON p3.professor_user_id = t.trimelous_professor3

WHERE
   (psup.professor_user_id = $profUserId)
OR (t.trimelous_professor1 = $profUserId
 OR t.trimelous_professor2 = $profUserId
 OR t.trimelous_professor3 = $profUserId)
";

// φίλτρο κατάστασης
if ($status_filter !== '') {
    $safeStatus = $connection->real_escape_string($status_filter);
    $query .= " AND d.diplo_status = '$safeStatus'";
}

// φίλτρο ρόλου
if ($role_filter === 'supervisor') {
    $query .= " AND psup.professor_user_id = $profUserId";
} elseif ($role_filter === 'member') {
    $query .= " AND (t.trimelous_professor1 = $profUserId
                 OR t.trimelous_professor2 = $profUserId
                 OR t.trimelous_professor3 = $profUserId)";
}

$query .= " ORDER BY d.diplo_id DESC";

// Εκτέλεση
$result = $connection->query($query);
if (!$result) {
    die("SQL ERROR: " . $connection->error . "<pre>$query</pre>");
}

$diplomas = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $diplomas[] = $row;
    }
}

// ------------------ Εξαγωγή (εξάγει ό,τι βλέπεις μετά τα φίλτρα) ------------------
if (isset($_GET['export'])) {
    $format = $_GET['export'];

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="diplomas.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Τίτλος', 'Φοιτητής', 'Ρόλος', 'Κατάσταση', 'Βαθμός', 'PDF', 'Τριμελής']);

        foreach ($diplomas as $d) {
            $stud = "-";
            if (!empty($d['diplo_student'])) {
                $stud = $d['diplo_student'] . " - " . ($d['student_surname'] ?? '') . " " . ($d['student_name'] ?? '');
                $stud = trim($stud);
            }

            $roleTxt = ($d['role_in_thesis'] === 'supervisor') ? 'Επιβλέπων' : 'Μέλος τριμελούς';

            $p1 = trim(($d['p1_surname'] ?? '') . " " . ($d['p1_name'] ?? ''));
            $p2 = trim(($d['p2_surname'] ?? '') . " " . ($d['p2_name'] ?? ''));
            $p3 = trim(($d['p3_surname'] ?? '') . " " . ($d['p3_name'] ?? ''));

            $committeeParts = [];
            if ($p1 !== '') $committeeParts[] = "Επιβλέπων: $p1";
            if ($p2 !== '') $committeeParts[] = "Μέλος: $p2";
            if ($p3 !== '') $committeeParts[] = "Μέλος: $p3";
            $committee = !empty($committeeParts) ? implode(" | ", $committeeParts) : "-";

            fputcsv($output, [
                $d['diplo_id'],
                $d['diplo_title'],
                $stud,
                $roleTxt,
                $d['diplo_status'],
                $d['diplo_grade'] ?? '-',
                $d['diplo_pdf'] ?? '-',
                $committee
            ]);
        }

        fclose($output);
        exit;
    }

    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($diplomas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Λίστα Διπλωματικών Εργασιών</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container mt-4">
    <h2 class="text-center">Λίστα Διπλωματικών Εργασιών</h2>

    <!-- Φίλτρα -->
    <form method="GET" class="row g-3 my-3">
        <div class="col-md-4">
            <select name="status" class="form-select">
                <option value="">Όλες οι καταστάσεις</option>
                <option value="pending"   <?= $status_filter=='pending'?'selected':'' ?>>Υπό Ανάθεση</option>
                <option value="active"    <?= $status_filter=='active'?'selected':'' ?>>Ενεργή</option>
                <option value="finished"  <?= $status_filter=='finished'?'selected':'' ?>>Περατωμένη</option>
                <option value="cancelled" <?= $status_filter=='cancelled'?'selected':'' ?>>Ακυρωμένη</option>
            </select>
        </div>

        <div class="col-md-4">
            <select name="role" class="form-select">
                <option value="">Όλοι οι ρόλοι</option>
                <option value="supervisor" <?= $role_filter=='supervisor'?'selected':'' ?>>Επιβλέπων</option>
                <option value="member"     <?= $role_filter=='member'?'selected':'' ?>>Μέλος Τριμελούς</option>
            </select>
        </div>

        <div class="col-md-4 d-flex">
            <button class="btn btn-primary me-2" type="submit">Φιλτράρισμα</button>

            <!-- export κρατάει φίλτρα -->
            <a class="btn btn-success me-2"
               href="?status=<?= urlencode($status_filter) ?>&role=<?= urlencode($role_filter) ?>&export=csv">
               Εξαγωγή CSV
            </a>

            <a class="btn btn-info"
               href="?status=<?= urlencode($status_filter) ?>&role=<?= urlencode($role_filter) ?>&export=json">
               Εξαγωγή JSON
            </a>
        </div>
    </form>

    <!-- Πίνακας -->
    <table class="table table-striped table-bordered">
        <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Τίτλος</th>
            <th>Φοιτητής</th>
            <th>Ρόλος μου</th>
            <th>Τριμελής</th>
            <th>Κατάσταση</th>
            <th>Βαθμός</th>
            <th>PDF</th>
            <th>Ενέργειες</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($diplomas)): ?>
            <tr><td colspan="9" class="text-center">Δεν υπάρχουν διπλωματικές για εμφάνιση</td></tr>
        <?php else: ?>
            <?php foreach ($diplomas as $d): ?>
                <?php
                $stud = "-";
                if (!empty($d['diplo_student'])) {
                    $stud = $d['diplo_student'] . " - " . ($d['student_surname'] ?? '') . " " . ($d['student_name'] ?? '');
                    $stud = trim($stud);
                }

                $roleTxt = ($d['role_in_thesis'] === 'supervisor') ? 'Επιβλέπων' : 'Μέλος τριμελούς';

                $p1 = trim(($d['p1_surname'] ?? '') . " " . ($d['p1_name'] ?? ''));
                $p2 = trim(($d['p2_surname'] ?? '') . " " . ($d['p2_name'] ?? ''));
                $p3 = trim(($d['p3_surname'] ?? '') . " " . ($d['p3_name'] ?? ''));

                $committeeParts = [];
                if ($p1 !== '') $committeeParts[] = "Επιβλέπων: $p1";
                if ($p2 !== '') $committeeParts[] = "Μέλος: $p2";
                if ($p3 !== '') $committeeParts[] = "Μέλος: $p3";
                $committee = !empty($committeeParts) ? implode(" | ", $committeeParts) : "-";
                ?>
                <tr>
                    <td><?= (int)$d['diplo_id'] ?></td>
                    <td><?= htmlspecialchars($d['diplo_title'] ?? '') ?></td>
                    <td><?= htmlspecialchars($stud) ?></td>
                    <td><?= htmlspecialchars($roleTxt) ?></td>
                    <td><?= htmlspecialchars($committee) ?></td>
                    <td><?= htmlspecialchars($d['diplo_status'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($d['diplo_grade'] ?? '-') ?></td>
                    <td>
                        <?php if (!empty($d['diplo_pdf'])): ?>
                            <a href="<?= htmlspecialchars($d['diplo_pdf']) ?>" target="_blank">PDF</a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="thesis_details.php?diplo_id=<?= (int)$d['diplo_id'] ?>"
                           class="btn btn-sm btn-primary">Λεπτομέρειες</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
