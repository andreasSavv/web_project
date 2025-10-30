<?php
$servername = "sql105.infinityfree.com"; // host
$username = "if0_40237844";              // username
$password = "wb09fgxqRyoi1ra";                   // password
$dbname = "if0_40237844_web";           // database name

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8"); //For greek


if ($conn->connect_error) {
    die("Connection error: " . $conn->connect_error);
}
?>
