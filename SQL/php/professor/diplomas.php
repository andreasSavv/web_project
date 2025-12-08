<?php
session_start();

    include("database_connection.php");
    include("functions.php");

    $user = login_session_professor($connection);

if (isset($_SESSION['role']) && ($_SESSION['role'] === "professor")){
    }
else {
    // If the user is not logged in as a professor, redirect to the login page
    header("Location: login.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Αρχική Σελίδα Καθηγητή </title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .sidebar { min-height: 100vh; }
    @media (max-width: 767.98px){
      main { padding-top: 1rem; }
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <button class="btn btn-outline-light me-2 d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle sidebar">
      ☰ Μενού
    </button>

    <a class="navbar-brand">Η Πλατφόρμα</a>
    <a href="professor_page.php" class="btn btn-success ms-2">Αρχική</a>

            <div class="ms-auto">
            <a href="logout.php" class="btn btn-danger">Αποσύνδεση</a>
        </div>
  </div>
</nav>

<div class="container-fluid">
  <div class="row">
    <?php
include "sidebar.php";
    ?>


<main class="col-md-8 col-lg-9 ms-sm-auto px-3 px-md-4 pt-4">
  <div id="Create_Diplomatiki" class="card shadow-lg p-4">
    <h2 class="mb-4 text-center text-primary">
      Προβολή Λίστας Διπλωματικών Εργασιών
    </h2>

    <!-- Σταθερά κουμπιά κάτω από τον τίτλο -->
    <div class="mb-4 text-center">
      <button id="allthesis" class="btn btn-primary m-2">Όλες οι Διπλωματικές</button>
      <button id="trimelis" class="btn btn-secondary m-2">Διπλωματικές που είστε μέλος τριμελούς</button>
      <button id="epimelitis" class="btn btn-info m-2">Διπλωματικές που είστε επιμελητής</button>
      <button id="xronologio" class="btn btn-warning m-2">Χρονολόγιο ενεργειών</button>
      <button id="meloi_trimeloi" class="btn btn-success m-2">Μέλη τριμελούς</button>
      <newline></newline>
      <label for="statusselect" class="ms-3">Κατάσταση Διπλωματικής:</label>
      <select id="statusselect" class="form-select d-inline-block w-auto ms-2">
        <option value="" disabled selected>Select status</option>  
        <option value="pending">pending</option>
        <option value="active">active</option>
        <option value="ready">ready</option>
        <option value="cancel">cancel</option>
        <option value="under_review">under_review</option>
      </select>

    </div>

    <!-- Εμφάνιση δεδομένων -->
    <div id="data_display"></div>
  </div>
</main>



        <script src="../JavaScript/show_diplo_professor.js" defer></script>
</body>



</html>
