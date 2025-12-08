<?php
session_start();
include("db_connect.php");
include("connected.php");

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
    <title>Πίνακας Ελέγχου Διδάσκοντα</title>

    

    <style>
        body {
            background: #eef2f7;
            font-family: Arial;
            padding: 30px;
        }
        .menu-card {
            border-radius: 12px;
            transition: transform .2s;
        }
        .menu-card:hover {
            transform: scale(1.03);
        }
        h2 span {
            color: #007bff;
        }
    </style>
</head>

<body>

<div class="container">

    <h2 class="mb-4">Καλωσήρθες, <span><?php echo htmlspecialchars($name); ?></span>!</h2>
    <p class="lead">Είσαι συνδεδεμένος ως Διδάσκων. Επίλεξε μια ενότητα:</p>

    <div class="row g-4">

        <!-- -------------------------------------- -->
        <!-- 1) Προβολή & Δημιουργία Θεμάτων -->
        <!-- -------------------------------------- -->
        <div class="col-md-6">
            <div class="card menu-card p-3 shadow-sm">
                <h4>1) Διαχείριση Θεμάτων Προς Ανάθεση</h4>
                <ul>
                    <li><a href="add_diploma.php">Προσθήκη νέου θέματος</a></li>
                    <li><a href="diplomas.php">Λίστα θεμάτων προς ανάθεση</a></li>
                </ul>
            </div>
        </div>

        <!-- -------------------------------------- -->
        <!-- 2) Ανάθεση Θέματος σε Φοιτητή -->
        <!-- -------------------------------------- -->
        <div class="col-md-6">
            <div class="card menu-card p-3 shadow-sm">
                <h4>2) Ανάθεση Θέματος</h4>
                <ul>
                    <li><a href="assign_student.php">Ανάθεση θέματος σε φοιτητή</a></li>
                    <li><a href="my_assigned_diplomas.php">Προσωρινές αναθέσεις που έχω κάνει</a></li>
                </ul>
            </div>
        </div>

        <!-- -------------------------------------- -->
        <!-- 3) Προβολή Λίστας Διπλωματικών -->
        <!-- -------------------------------------- -->
        <div class="col-md-6">
            <div class="card menu-card p-3 shadow-sm">
                <h4>3) Διπλωματικές Εργασίες</h4>
                <ul>
                    <li><a href="all_diplomas.php">Λίστα όλων των διπλωματικών μου</a></li>
                    <li><a href="export_diplomas.php?type=csv">Εξαγωγή CSV</a></li>
                    <li><a href="export_diplomas.php?type=json">Εξαγωγή JSON</a></li>
                </ul>
            </div>
        </div>

        <!-- -------------------------------------- -->
        <!-- 4) Προσκλήσεις για Τριμελείς -->
        <!-- -------------------------------------- -->
        <div class="col-md-6">
            <div class="card menu-card p-3 shadow-sm">
                <h4>4) Προσκλήσεις Συμμετοχής σε Τριμελείς</h4>
                <ul>
                    <li><a href="committee_invitations.php">Εισερχόμενες προσκλήσεις</a></li>
                </ul>
            </div>
        </div>

        <!-- -------------------------------------- -->
        <!-- 5) Προβολή Στατιστικών -->
        <!-- -------------------------------------- -->
        <div class="col-md-6">
            <div class="card menu-card p-3 shadow-sm">
                <h4>5) Στατιστικά</h4>
                <ul>
                    <li><a href="stats_completion_time.php">Μέσος χρόνος περάτωσης</a></li>
                    <li><a href="stats_grades.php">Μέσοι βαθμοί</a></li>
                    <li><a href="stats_totals.php">Συνολικός αριθμός διπλωματικών</a></li>
                </ul>
            </div>
        </div>

        <!-- -------------------------------------- -->
        <!-- 6) Διαχείριση Διπλωματικών -->
        <!-- -------------------------------------- -->
        <div class="col-md-6">
            <div class="card menu-card p-3 shadow-sm">
                <h4>6) Διαχείριση Διπλωματικών Εργασιών</h4>
                <ul>
                    <li><a href="manage_pending.php">Υπό Ανάθεση</a></li>
                    <li><a href="manage_active.php">Ενεργές</a></li>
                    <li><a href="manage_under_review.php">Υπό Εξέταση</a></li>
                    <li><a href="manage_finished.php">Περατωμένες</a></li>
                </ul>
            </div>
        </div>

    </div>

    <div class="mt-4">
        <a href="logout.php" class="btn btn-danger">Αποσύνδεση</a>
    </div>

</div>

</body>
</html>
