<?php
session_start();
include("db_connect.php");
include("connected.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

$id = intval($_GET['id']);
$sql = "SELECT * FROM diplo WHERE diplo_id = $id";
$result = mysqli_query($connection, $sql);
$diploma = mysqli_fetch_assoc($result);

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = mysqli_real_escape_string($connection, $_POST['title']);
    $desc = mysqli_real_escape_string($connection, $_POST['description']);

    $pdf_name = $diploma['diplo_pdf'];

    if (!empty($_FILES['pdf']['name'])) {
        $pdf_name = time() . "_" . basename($_FILES['pdf']['name']);
        move_uploaded_file($_FILES['pdf']['tmp_name'], "uploads/$pdf_name");
    }

    $update = "UPDATE diplo 
               SET diplo_title='$title', diplo_desc='$desc', diplo_pdf='$pdf_name'
               WHERE diplo_id=$id";

    if (mysqli_query($connection, $update)) {
        $message = "Επιτυχής ενημέρωση!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Επεξεργασία Θέματος</title>
</head>
<body>

<h2>Επεξεργασία Θέματος</h2>

<?php if ($message) echo "<p><strong>$message</strong></p>"; ?>

<form method="POST" enctype="multipart/form-data">
    <label>Τίτλος:</label>
    <input type="text" name="title" value="<?= $diploma['diplo_title']; ?>" required><br>

    <label>Περιγραφή:</label>
    <textarea name="description" required><?= $diploma['diplo_desc']; ?></textarea><br>

    <label>PDF:</label>
    <?php if ($diploma['diplo_pdf']) { ?>
        <p>Τρέχον PDF: <a href="uploads/<?= $diploma['diplo_pdf']; ?>" target="_blank">Προβολή</a></p>
    <?php } ?>
    <input type="file" name="pdf" accept="application/pdf">

    <button type="submit">Αποθήκευση</button>
</form>

<p><a href="diplomas.php">⮨ Επιστροφή</a></p>

</body>
</html>
