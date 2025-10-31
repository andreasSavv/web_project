<?php
$servername = "sql105.infinityfree.com"; // host
$username = "if0_40237844";              // username
$password = "wb09fgxqRyoi1ra";                   // password
$dbname = "if0_40237844_web";           // database name

$connection = new mysqli($servername, $username, $password, $dbname);
$connection->set_charset("utf8"); //For greek


if ($connection->connect_error) {
    die("Connection error: " . $connection->connect_error);
}
?>
