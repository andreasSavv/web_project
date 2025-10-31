<?php
session_start();
include("db_connect.php");
include("connected.php");

$sec = Secretary_Connected($connection);
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Secretary Page</title></head>
<body>
<h2>Καλωσήρθες, Γραμματέα <?php echo htmlspecialchars($sec['secretary_name'] ?? ''); ?></h2>
<a href="logout.php">Αποσύνδεση</a>
</body>
</html>
