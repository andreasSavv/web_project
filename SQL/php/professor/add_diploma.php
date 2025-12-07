<?php
session_start();
include("db_connect.php");
include("connected.php");

// Έλεγχος role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

$user = Professor_Connected($connection);
$prof_id = $user['professor_id'];

$message = "";

// Αν υποβλήθηκε η φόρμα
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = mysqli_real_escape_string($connection, $_POST['title']);
    $desc = mysqli_real_escape_string($connection, $_POST['description']);
    $status = "under assignment";

    // Διαχείριση PDF αρχείου
    $pdf_name = "";
    if (!empty($_FILES['pdf']['name'])) {
        $pdf_name = time() . "_" . basename($_FILES['pdf']['name']);
        $target = "uploads/" . $pdf_name;

        if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $target)) {
            $message = "Σφάλμα κατά το ανέβασμα του PDF.";
        }
    }

    // Εισαγωγή στη βάση
    $sql = "INSERT INTO diplo (diplo_title, diplo_desc, diplo_pdf, diplo_status, diplo_professor)
            VALUES ('$title', '$desc', '$pdf_name', '$status', '$prof_id')";

    if (mysqli_query($connection, $sql)) {
        $message = "Η διπλωματική προστέθηκε επιτυχώς!";
    } else {
        $message = "Σφάλμα: " . mysqli_error($connection);
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Προσθήκη Νέας Διπλωματικής</title>
    <style>
        body { font-family: Arial; background: #f4f4f4; padding: 30px; }
        .form-box { background:white; padding:20px; border-radius:10px; max-width:600px; margin:auto; }
        input, textarea { width:100%; padding:8px; margin-bottom:10px; }
        button { padding:10px 20px; background:#007bff; color:white; border:none; cursor:pointer; }
    </style>
</head>
<body>

<div class="form-box">
    <h2>Προσθήκη Νέου Θέματος Διπλωματικής</h2>
    
    <?php if ($message) echo "<p><strong>$message</strong></p>"; ?>

    <form method="POST" enctype="multipart/form-data">
        <label>Τίτλος:</label>
        <input type="text" name="title" required>

        <label>Περιγραφή:</label>
        <textarea name="description" rows="4" required></textarea>

        <label>Αρχείο PDF (προαιρετικό):</label>
        <input type="file" name="pdf" accept="application/pdf">

        <button type="submit">Καταχώρηση</button>
    </form>

    <p><a href="professor_page.php">⮨ Επιστροφή</a></p>
</div>

</body>
</html>
