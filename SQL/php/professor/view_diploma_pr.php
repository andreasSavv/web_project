<?php
session_start();
include("db_connect.php");
include("connected.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

$user = Professor_Connected($connection);
$prof_id = $user['professor_id'];

$sql = "SELECT * FROM diplo WHERE diplo_professor = $prof_id AND diplo_status = 'under assignment'";
$result = mysqli_query($connection, $sql);
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Τα Θέματά Μου</title>
    <style>
        body { font-family: Arial; background:#f4f4f4; padding:30px; }
        table { width:100%; border-collapse:collapse; background:white; }
        table th, table td { padding:10px; border-bottom:1px solid #ccc; }
        a { color:#007bff; }
    </style>
</head>
<body>

<h2>Θέματα Προς Ανάθεση</h2>

<table>
    <tr>
        <th>Τίτλος</th>
        <th>Περιγραφή</th>
        <th>PDF</th>
        <th>Ενέργειες</th>
    </tr>

    <?php while ($row = mysqli_fetch_assoc($result)) { ?>
        <tr>
            <td><?= htmlspecialchars($row['diplo_title']); ?></td>
            <td><?= htmlspecialchars($row['diplo_desc']); ?></td>
            <td>
                <?php if ($row['diplo_pdf']) { ?>
                    <a href="uploads/<?= $row['diplo_pdf']; ?>" target="_blank">Προβολή</a>
                <?php } else echo "-"; ?>
            </td>
            <td>
                <a href="edit_diploma.php?id=<?= $row['diplo_id']; ?>">Επεξεργασία</a>
            </td>
        </tr>
    <?php } ?>

</table>

<p><a href="professor_page.php">⮨ Επιστροφή</a></p>

</body>
</html>
