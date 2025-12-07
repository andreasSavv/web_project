<?php
session_start();
include("db_connect.php");
include("connected.php");

// Έλεγχος ρόλου
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

$user = Professor_Connected($connection);
$professor_id = $user['professor_id'];

$id = $_GET['id'];

// Πληροφορίες διπλωματικής
$stmt = $connection->prepare("SELECT * FROM diplo WHERE diplo_id=?");
$stmt->execute([$id]);
$d = $stmt->fetch(PDO::FETCH_ASSOC);

// Timeline
$timeline_stmt = $connection->prepare("SELECT * FROM diplo_timeline WHERE diplo_id=? ORDER BY action_date ASC");
$timeline_stmt->execute([$id]);
$timeline = $timeline_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>Προβολή Διπλωματικής</title>
<style>
    body { font-family: Arial; background: #f4f4f4; margin: 40px; }
    .container { background: white; padding: 20px; border-radius: 10px; max-width: 900px; margin: auto; box-shadow: 0 0 10px rgba(0,0,0,0.2); }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 8px; border: 1px solid #ccc; }
    th { background: #ddd; }
</style>
</head>
<body>

<div class="container">

<h2>📘 <?= htmlspecialchars($d['diplo_title']) ?></h2>

<h3>Βασικές Πληροφορίες</h3>
<ul>
    <li><strong>Φοιτητής:</strong> <?= $d['diplo_student'] ?: "—" ?></li>
    <li><strong>Επιβλέπων:</strong> <?= $d['diplo_professor'] ?></li>
    <li><strong>Τριμελής:</strong> <?= $d['diplo_trimelis'] ?></li>
    <li><strong>Κατάσταση:</strong> <?= $d['diplo_status'] ?></li>

    <?php if ($d['diplo_status'] === "finished"): ?>
        <li><strong>Τελικός Βαθμός:</strong> <?= $d['diplo_grade'] ?></li>
        <li><strong>Τελικό Κείμενο:</strong> 
            <a href="<?= $d['nimertis_link'] ?>" target="_blank">Nimertis</a>
        </li>
        <li><strong>Πρακτικό Αξιολόγησης:</strong>
            <a href="uploads/praktiko_<?= $d['diplo_id'] ?>.pdf" target="_blank">Προβολή</a>
        </li>
    <?php endif; ?>
</ul>

<h3>📜 Χρονολόγιο Ενεργειών</h3>

<table>
<tr>
    <th>Ημερομηνία</th>
    <th>Ενέργεια</th>
</tr>

<?php foreach ($timeline as $t): ?>
<tr>
    <td><?= $t['action_date'] ?></td>
    <td><?= htmlspecialchars($t['action']) ?></td>
</tr>
<?php endforeach; ?>

</table>

<br>
<a href="diplomas.php">⬅ Επιστροφή</a>

</div>

</body>
</html>
