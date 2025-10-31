<?php

function Professor_Connected($connection){
    if(isset($_SESSION['user_id']))
    {
        $id = $_SESSION['user_id'];
        $query ="select * from professor where professor_user_id = '$id'";
        $result = mysqli_query($connection, $query);
        if ($result && mysqli_num_rows($result) === 1)
        {
            $user = mysqli_fetch_assoc($result); //Fetch the result
            return $user;
        }
    }
    else
    {
        //Not already login -> goto login.php
        header("Location: login.php");
        exit;
    }
} 

function Secretary_Connected($connection){
    if(isset($_SESSION['user_id']))
    {
        $id = $_SESSION['user_id'];
        $query ="select * from secretary where secretary_user_id = '$id'";
        $result = mysqli_query($connection, $query);
        if ($result && mysqli_num_rows($result) === 1)
        {
            $user = mysqli_fetch_assoc($result); //Fetch the result
            return $user;
        }
    }
    else
    {
        //Not already login -> goto login.php
        header("Location: login.php");
        exit;
    }
} 

function Student_Connected($connection){
    if(isset($_SESSION['user_id']))
    {
        $id = $_SESSION['user_id'];
        $query ="select * from student where student_user_id = '$id'";
        $result = mysqli_query($connection, $query);
        if ($result && mysqli_num_rows($result) === 1)
        {
            $user = mysqli_fetch_assoc($result); //Fetch the result
            return $user;
        }
    }
    else
    {
        //Not already login -> goto login.php
        header("Location: login.php");
        exit;
    }
} 
?>