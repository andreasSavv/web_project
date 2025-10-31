<?php
session_start();
include("db_connect.php");
include("connected.php");

$stud = Student_Connected($connection);
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Student Page</title></head>
<body>
<h2>Καλωσήρθες, Φοιτητή <?php echo htmlspecialchars($stud['student_name'] ?? ''); ?></h2>
<a href="logout.php">Αποσύνδεση</a>
</body>
</html>
