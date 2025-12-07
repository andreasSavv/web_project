<?php
session_start();
include("db_connect.php");
include("connected.php");
include("add_diploma.php");
// Έλεγχος αν είναι professor
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

// Παίρνουμε τα στοιχεία του καθηγητή
$user = Professor_Connected($connection);
$name = $user['professor_name'] ?? "Καθηγητής";
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Professor Page</title>
    <style>
        body { font-family: Arial; background: #f4f4f4; margin: 40px; }
        .container { background: white; padding: 20px; border-radius: 10px; max-width: 800px; margin: auto; box-shadow: 0 0 10px rgba(0,0,0,0.2); }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="container">
    <h2>Καλωσήρθες, Καθηγητά <?php echo htmlspecialchars($name); ?>!</h2>
    <p>Είσαι συνδεδεμένος επιτυχώς στο σύστημα.</p>
    <ul>
        <li><a href="diplomas.php">Διπλωματικές Εργασίες</a></li>
        <li><a href="add_diploma.php">Προσθήκη Νέας Διπλωματικής</a></li>
        <li><a href="logout.php">Αποσύνδεση</a></li>
    </ul>
</div>
</body>
</html>
