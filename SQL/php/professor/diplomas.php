<?php
session_start();
include("db_connect.php");
include("connected.php");

// Έλεγχος ρόλου
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

// Δεδομένα καθηγητή (ID)
$user = Professor_Connected($connection);
$professor_id = $user['professor_id']; 
$name = $user['professor_name'];

// -------------------------
// ===== ΦΙΛΤΡΑ =====
// -------------------------
$status_filter = $_GET['status'] ?? '';
$role_filter   = $_GET['role']   ?? '';

// -------------------------
// ===== Query για διπλωματικές =====
// -------------------------
$sql = "
SELECT * FROM diplo
WHERE (
    diplo_professor = ?
    OR FIND_IN_SET(?, diplo_trimelis)
)
";

// Φιλτράρισμα κατάστασης
if ($status_filter !== "") {
    $sql .= " AND diplo_status = ? ";
}

// Φιλτράρισμα ρόλου
if ($role_filter === "supervisor") {
    $sql .= " AND diplo_professor = ? ";
}
if ($role_filter === "trimelis") {
    $sql .= " AND FIND_IN_SET(?, diplo_trimelis) ";
}

$sql .= " ORDER BY diplo_id DESC";

$stmt = $connection->prepare($sql);

// Δέσμευση τιμών δυναμικά
$params = [$professor_id, $professor_id];

if ($status_filter !== "") $params[] = $status_filter;
if ($role_filter === "supervisor") $params[] = $professor_id;
if ($role_filter === "trimelis") $params[] = $professor_id;

$stmt->execute($params);
$diplomas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -------------------------
// ===== Εξαγωγή CSV =====
// -------------------------
if (isset($_GET['export']) && $_GET['export'] === "csv") {
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=diplomas.csv");

    $out = fopen("php://output", "w");

    if (!empty($diplomas)) {
        fputcsv($out, array_keys($diplomas[0])); // headers
        foreach ($diplomas as $row) fputcsv($out, $row);
    }
    exit;
}

// -------------------------
// ===== Εξαγωγή JSON =====
// -------------------------
if (isset($_GET['export']) && $_GET['export'] === "json") {
    header("Content-Type: application/json");
    header("Content-Disposition: attachment; filename=diplomas.json");
    echo json_encode($diplomas, JSON_PRETTY_PRINT);
    exit;
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>Διπλωματικές Εργασίες</title>
<style>
    body { font-family: Arial; background: #f4f4f4; margin: 40px; }
    .container { background: white; padding: 20px; border-radius: 10px; max-width: 1000px; margin: auto; box-shadow: 0 0 10px rgba(0,0,0,0.2); }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 8px; border: 1px solid #ccc; }
    th { background: #ddd; }
    a { color: #007bff; text-decoration: none; }
</style>
</head>

<body>
<div class="container">

<h2>📘 Οι Διπλωματικές Μου</h2>
<p>Καθηγητής: <strong><?= htmlspecialchars($name) ?></strong></p>

<!-- ΦΙΛΤΡΑ -->
<form method="GET">
    <label>Κατάσταση:</label>
    <select name="status">
        <option value="">Όλες</option>
        <option value="active" <?= $status_filter=="active"?"selected":"" ?>>Active</option>
        <option value="under assignment" <?= $status_filter=="under assignment"?"selected":"" ?>>Under Assignment</option>
        <option value="finished" <?= $status_filter=="finished"?"selected":"" ?>>Finished</option>
        <option value="cancelled" <?= $status_filter=="cancelled"?"selected":"" ?>>Cancelled</option>
    </select>

    <label>Ρόλος:</label>
    <select name="role">
        <option value="">Όλοι</option>
        <option value="supervisor" <?= $role_filter=="supervisor"?"selected":"" ?>>Επιβλέπων</option>
        <option value="trimelis" <?= $role_filter=="trimelis"?"selected":"" ?>>Τριμελής</option>
    </select>

    <button type="submit">Φιλτράρισμα</button>
</form>

<br>

<a href="diplomas.php?export=csv">📄 Εξαγωγή CSV</a> |
<a href="diplomas.php?export=json">📄 Εξαγωγή JSON</a>

<hr>

<table>
<tr>
    <th>ID</th>
    <th>Τίτλος</th>
    <th>Φοιτητής</th>
    <th>Κατάσταση</th>
    <th>Ρόλος</th>
    <th>Ενέργειες</th>
</tr>

<?php foreach ($diplomas as $d): ?>
<tr>
    <td><?= $d['diplo_id'] ?></td>
    <td><?= htmlspecialchars($d['diplo_title']) ?></td>
    <td><?= $d['diplo_student'] ?: "-" ?></td>
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

<br>
<a href="professor_page.php">⬅ Επιστροφή</a>

</div>
</body>
</html>
