<?php
session_start();
include("db_connect.php");
include("connected.php");

// Έλεγχος αν είναι καθηγητής
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

$user = Professor_Connected($connection);
$prof_id = $user['professor_user_id'] ?? null;

// ------------------ Φιλτράρισμα ------------------
$status_filter = $_GET['status'] ?? '';
$role_filter   = $_GET['role'] ?? '';

// Δημιουργία query για τις διπλωματικές
$query = "SELECT * FROM diplo WHERE diplo_professor = '$prof_id'";

// Αν έχει επιλεγεί κατάσταση
if ($status_filter) {
    $query .= " AND diplo_status = '" . $connection->real_escape_string($status_filter) . "'";
}

// Αν έχει επιλεγεί ρόλος
if ($role_filter) {
    if ($role_filter === 'member') {
        $query .= " AND FIND_IN_SET('$prof_id', diplo_trimelis)";
    } elseif ($role_filter === 'supervisor') {
        $query .= " AND diplo_professor = '$prof_id'";
    }
}

$result = $connection->query($query);
$diplomas = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $diplomas[] = $row;
    }
}

// ------------------ Εξαγωγή ------------------
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="diplomas.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Τίτλος', 'Περιγραφή', 'Φοιτητής', 'Κατάσταση', 'Βαθμός', 'PDF']);
        foreach ($diplomas as $d) {
            fputcsv($output, [$d['diplo_id'], $d['diplo_title'], $d['diplo_desc'], $d['diplo_student'], $d['diplo_status'], $d['diplo_grade'], $d['diplo_pdf']]);
        }
        fclose($output);
        exit;
    } elseif ($format === 'json') {
        header('Content-Type: application/json');
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
                <option value="pending" <?= $status_filter=='pending'?'selected':'' ?>>Υπό Ανάθεση</option>
                <option value="active" <?= $status_filter=='active'?'selected':'' ?>>Ενεργή</option>
                <option value="finished" <?= $status_filter=='finished'?'selected':'' ?>>Περατωμένη</option>
                <option value="cancelled" <?= $status_filter=='cancelled'?'selected':'' ?>>Ακυρωμένη</option>
            </select>
        </div>
        <div class="col-md-4">
            <select name="role" class="form-select">
                <option value="">Όλοι οι ρόλοι</option>
                <option value="supervisor" <?= $role_filter=='supervisor'?'selected':'' ?>>Επιβλέπων</option>
                <option value="member" <?= $role_filter=='member'?'selected':'' ?>>Μέλος Τριμελούς</option>
            </select>
        </div>
        <div class="col-md-4 d-flex">
            <button class="btn btn-primary me-2" type="submit">Φιλτράρισμα</button>
            <a class="btn btn-success me-2" href="?export=csv">Εξαγωγή CSV</a>
            <a class="btn btn-info" href="?export=json">Εξαγωγή JSON</a>
        </div>
    </form>

    <!-- Πίνακας -->
    <table class="table table-striped table-bordered">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Τίτλος</th>
                <th>Περιγραφή</th>
                <th>Φοιτητής</th>
                <th>Τριμελής</th>
                <th>Κατάσταση</th>
                <th>Βαθμός</th>
                <th>PDF</th>
                <th>Ενέργειες</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($diplomas)): ?>
                <tr><td colspan="9" class="text-center">Δεν υπάρχουν διπλωματικές για εμφάνιση</td></tr>
            <?php else: ?>
                <?php foreach($diplomas as $d): ?>
                    <tr>
                        <td><?= $d['diplo_id'] ?></td>
                        <td><?= htmlspecialchars($d['diplo_title']) ?></td>
                        <td><?= htmlspecialchars($d['diplo_desc']) ?></td>
                        <td><?= $d['diplo_student'] ?? '-' ?></td>
                        <td><?= htmlspecialchars($d['diplo_trimelis']) ?></td>
                        <td><?= $d['diplo_status'] ?></td>
                        <td><?= $d['diplo_grade'] ?? '-' ?></td>
                        <td>
                            <?php if($d['diplo_pdf']): ?>
                                <a href="<?= $d['diplo_pdf'] ?>" target="_blank">PDF</a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="view_diploma.php?diplo_id=<?= $d['diplo_id'] ?>" class="btn btn-sm btn-primary">Λεπτομέρειες</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
