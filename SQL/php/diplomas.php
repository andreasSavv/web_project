<?php
require 'db_connect.php';

$professor_id = 5; // παράδειγμα από login

// --- ΦΙΛΤΡΑ ---
$statusFilter = $_GET['status'] ?? '';
$roleFilter   = $_GET['role'] ?? '';

// --- Βασικό query ---
$query = "
SELECT * FROM diplo 
WHERE 
(
    diplo_professor = :pid 
    OR FIND_IN_SET(:pid, diplo_trimelis)
)
";

// --- Προσθήκη φίλτρου κατάστασης ---
if ($statusFilter !== '') {
    $query .= " AND diplo_status = :status ";
}

// --- Φίλτρο ρόλου ---
if ($roleFilter === 'supervisor') {
    $query .= " AND diplo_professor = :pid ";
}

if ($roleFilter === 'trimelis') {
    $query .= " AND FIND_IN_SET(:pid, diplo_trimelis) ";
}

$query .= " ORDER BY diplo_id DESC";

$stmt = $pdo->prepare($query);

// Δέσμευση τιμών
$stmt->bindValue(':pid', $professor_id);

if ($statusFilter !== '') {
    $stmt->bindValue(':status', $statusFilter);
}

$stmt->execute();
$diplomas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Εξαγωγή CSV ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="diplomas.csv"');

    $output = fopen("php://output", "w");
    fputcsv($output, array_keys($diplomas[0])); // headers

    foreach ($diplomas as $d) {
        fputcsv($output, $d);
    }
    exit;
}

// --- Εξαγωγή JSON ---
if (isset($_GET['export']) && $_GET['export'] === 'json') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="diplomas.json"');
    echo json_encode($diplomas, JSON_PRETTY_PRINT);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Λίστα Διπλωματικών</title>
</head>
<body>

<h2>📘 Λίστα Διπλωματικών</h2>

<form method="GET">
    <label>Κατάσταση:</label>
    <select name="status">
        <option value="">-- Όλες --</option>
        <option value="active" <?= $statusFilter=='active'?'selected':'' ?>>Active</option>
        <option value="under assignment" <?= $statusFilter=='under assignment'?'selected':'' ?>>Under Assignment</option>
        <option value="finished" <?= $statusFilter=='finished'?'selected':'' ?>>Finished</option>
        <option value="cancelled" <?= $statusFilter=='cancelled'?'selected':'' ?>>Cancelled</option>
    </select>

    <label>Ρόλος:</label>
    <select name="role">
        <option value="">-- Όλοι --</option>
        <option value="supervisor" <?= $roleFilter=='supervisor'?'selected':'' ?>>Επιβλέπων</option>
        <option value="trimelis" <?= $roleFilter=='trimelis'?'selected':'' ?>>Τριμελής</option>
    </select>

    <button type="submit">Φιλτράρισμα</button>
</form>

<br>

<a href="diplomas_list.php?export=csv">📄 Εξαγωγή CSV</a> |
<a href="diplomas_list.php?export=json">📄 Εξαγωγή JSON</a>

<hr>

<table border="1" cellpadding="5">
<tr>
    <th>ID</th>
    <th>Τίτλος</th>
    <th>Φοιτητής</th>
    <th>Κατάσταση</th>
    <th>Ρόλος μου</th>
    <th>Ενέργειες</th>
</tr>

<?php foreach ($diplomas as $d): ?>
<tr>
    <td><?= $d['diplo_id'] ?></td>
    <td><?= htmlspecialchars($d['diplo_title']) ?></td>
    <td><?= $d['diplo_student'] ?: '-' ?></td>
    <td><?= $d['diplo_status'] ?></td>

    <td>
        <?php 
            if ($d['diplo_professor'] == $professor_id) echo "Επιβλέπων";
            else echo "Τριμελής";
        ?>
    </td>

    <td>
        <a href="view_diploma.php?id=<?= $d['diplo_id'] ?>">Προβολή</a>
    </td>
</tr>
<?php endforeach; ?>
</table>

</body>
</html>
