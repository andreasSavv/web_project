<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();


include 'db_connect.php';
include("connected.php");   // Χρειάζεται για να πάρουμε τα στοιχεία του χρήστη

// Έλεγχος αν ο χρήστης είναι συνδεδεμένος
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'];
$role = $_SESSION['role']; // Εμφανίζεται ο ρόλος

if ($_SESSION['role'] === 'Student') {
    $user = Student_Connected($connection);
     $name = $user['student_name'];
}
else if ($_SESSION['role'] === 'Professor') {
    $user = Professor_Connected($connection);
    $name = $user['professor_name'];
}
else if ($_SESSION['role'] === 'Secretary') {
    $user = Secretary_Connected($connection);
    $name = $user['secretary_name'];
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Home Page</title>
    <style>
        body { font-family: Arial; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        a.button { display: inline-block; padding: 8px 15px; margin-top: 10px; background: #007BFF; color: #fff; text-decoration: none; border-radius: 4px; }
        a.button:hover { background: #0056b3; }
    </style>
</head>
<body>
<div class="container">
    <h1>Καλωσήρθες, <?php echo htmlspecialchars($name); ?>!</h1>
    <p>Role: <?php echo htmlspecialchars($role); ?></p>

    <h3>Μενού:</h3>
    <ul>
        <li><a href="profile.php">Προφίλ</a></li>
        <li><a href="diplomas.php">Λίστα Διπλωματικών</a></li>
        <li><a href="add_diploma.php">Προσθήκη Διπλωματικής</a></li>
        <li><a href="logout.php" class="button">Αποσύνδεση</a></li>
    </ul>
</div>
</body>
</html>
