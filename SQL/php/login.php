<?php   
session_start();

include("db_connect.php");
include("connected.php");

$error_message = "";

if($_SERVER['REQUEST_METHOD'] == "POST") {
        $username = $_POST['username']; 
        $password = $_POST['password']; 
    
        //elegxos username
        $query = "SELECT * from user where user_username = ?";
        $stmt = $connection->prepare($query);
        if (!$stmt){
            die("Failed :" . $connection->error);
        }
        $stmt->bind_param("s" , $username);
        $stmt->execute();
        $result=$stmt->get_result();

        if($result && $result->num_rows === 1){
            $user = $result->fetch_assoc();

            //elexos an dothike sosto password
            if ($user['user_pass'] === $password){
               //Thetoume ta stixia sta session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['user_username'];
                $_SESSION['role'] = $user['user_category'];
                //Oloi oi users redirect sto index.php

            // Analoga me to category header stin analogi home page
            if ($_SESSION['role'] === "professor") {
                 header("Location: professor_page.php");
                exit;
            } else {
                header("Location: index.php");
                exit;
}
            elseif ($_SESSION['role'] === "student") {
                header("Location: student_page.php");
                exit;
            } 
            elseif ($_SESSION['role'] === "secretary") {
                header("Location: secretary_page.php");
                exit;
            } 
            else {
                header("Location: index.php"); // default
                exit;
            }

        } else {
            $error_message = "Λάθος κωδικός! Δοκίμασε ξανά.";
        }
    } else {
        $error_message = "Δεν βρέθηκε λογαριασμός! Δοκίμασε ξανά.";
    }
}
?>


<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <style>
        body { font-family: Arial; background: #f5f5f5; }
        .login-container { max-width: 400px; margin: 100px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        input[type=text], input[type=password] { width: 100%; padding: 8px; margin: 5px 0; }
        input[type=submit] { padding: 8px 15px; background: #007BFF; color: #fff; border: none; cursor: pointer; }
        .error { color: red; }
    </style>
</head>
<body>
<div class="login-container">
    <h2>Σύνδεση</h2>
    <?php if($error_message != "") { echo "<p class='error'>$error_message</p>"; } ?>
    <form method="POST" action="">
        <label>Username:</label>
        <input type="text" name="username" required>
        <label>Password:</label>
        <input type="password" name="password" required>
        <input type="submit" value="Σύνδεση">
    </form>
</div>
</body>
</html>



