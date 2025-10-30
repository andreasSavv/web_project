<?php   
session_start();

include("db_connect.php");
include("connected.php");

if($_SERVER['REQUEST_METHOD'] == "POST")
    {
        $username = $_POST['username']; 
        $password = $_POST['password']; 

        $query = "SELECT * from user_category where user_username = ?";
        $stmt = $connection->prepare($query);
        if (!$stmt){
            die("Failed :" . $connection->error);
        }
        $stmt->bind_param("s" , $username);
        $stmt->execute();
        $result=$stmt->get_result();

        if($result == $result->num_rows === 1)
        {      $user = mysqli_fetch_assoc($result);
            //elexos an dothike sosto password
            if ($user['user_pass'] === $password)
            {   //Thetoume ta stixia sta session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role'] = $user['user_category'];
                //Elegxoume to role gia na kseroume se pio main page na katefthinthoume.
                if ($_SESSION['role'] === "professor")
                {
                    header("Location: professor_page.php");
                    exit;
                }
                elseif ($_SESSION['role'] === "student")
                {
                    header("Location: student_page.php");
                    exit;
                }
                elseif ($_SESSION['role'] === "secretary")
                {
                    header("Location: secretary_page.php");
                    exit;
                }
            }
            else{$error_message = "Λάθος Κωδικός Πρόσβασης! Προσπαθήστε ξανά.";

            }
        }
        else
        {
            $error_message = "Ο λογαριασμός δεν υπάρχει! Προσπαθήστε ξανά.";
        }
        

        
    }
?>


