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
    <title>Professor Page</title>
    <style>
        body { font-family: Arial; background: #f4f4f4; margin: 40px; }
        .container { background: white; padding: 20px; border-radius: 10px; max-width: 800px; margin: auto; box-shadow: 0 0 10px rgba(0,0,0,0.2); }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="container">
    <h2>Καλωσήρθες, Καθηγητά <?php echo htmlspecialchars($name); ?>!</h2>
    <p>Είσαι συνδεδεμένος επιτυχώς στο σύστημα.</p>
    <ul>
        <li><a href="add_diploma.php">1)Δημιουργία Διπλωματικής Εργασίας</a></li>
        <li><a href="diplo_assign.php">2)Ανάθεση Διπλωματικής Εργασίας</a></li>
        <li><a href="pending_inv.php">3)Προβολή προσκλήσεων σε τριμελή</a></li>
        <li><a href="diplomas.php">4)Διπλωματικές Εργασίες</a></li>
        <li><a href="pr_cancel_diplo.php">5)Ακύρωση Ενεργής Διπλωματικής Εργασίας</a></li>
        <li><a href="cancel_pending_diplo.php">6)Ακύρωση "Υπο Ανάθεση" Διπλωματικής Εργασίας</a></li>
        <li><a href="diplo_status_under_rev.php">7)Αλλαγή Κατάστασης ΔΕ σε Περατωμένη</a></li>
        <li><a href="prof_notes.php">8)Σημειώσεις Διπλωματικής Εργασίας</a></li>
        <li><a href="grade_enable.php">9)Ενεργοποίηση Βαθμού</a></li>
        <li><a href="diplo_grade.php">10)Καταχώρηση Βαθμού</a></li>
        <li><a href="prof_graphs.php">11)Γραφικές Παραστάσεις Καθηγητή</a></li>
        <li><a href="prof_show_invite.php">12)Προβολή Καθηγητών που εχουν προσκληθεί ως μέλος τριμελούς</a></li>
        <li><a href="prof_show_notes.php">14)Σημειώσεις Διπλωματικών Εργασιών</a></li>
        <li><a href="prof_show_grades.php">15)Βαθμοί ΔΕ ως μέλος τριμελούς</a></li>
        <li><a href="prof_show_st_notes.php">16)Προβολή πρόχειρου κειμένου φοιτητή ως μέλος τριμελούς</a></li>
        <li><a href="announcements.php">17)Δημιουργία ανακοίνωσης</a></li>
        <li><a href="logout.php">Αποσύνδεση</a></li>
        
    </ul>
</div>
</body>
</html>
