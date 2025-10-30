<?php
// Δηλώνουμε UTF-8 ώστε να εμφανίζονται σωστά ελληνικά
header('Content-Type: text/html; charset=utf-8');
// Εμφάνιση σφαλμάτων (μόνο για δοκιμή)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Σύνδεση με τη βάση
include 'db_connect.php';

// Έλεγχος σύνδεσης
if ($conn->connect_error) {
    die("❌ Database connection failed: " . $conn->connect_error);
} else {
    echo "<h3>✅ Database connection successful!</h3>";
}

// Παράδειγμα query (άλλαξε το όνομα του πίνακα ανάλογα με τη βάση σου)
$sql = "SELECT * FROM student"; 
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "<h4>📋 Δείγμα δεδομένων από τη βάση:</h4>";
    echo "<table border='1' cellpadding='6' style='border-collapse: collapse;'>";
    echo "<tr>";

    // Εμφάνιση των ονομάτων των στηλών
    $fields = $result->fetch_fields();
    foreach ($fields as $field) {
        echo "<th>" . htmlspecialchars($field->name) . "</th>";
    }
    echo "</tr>";

    // Εμφάνιση των γραμμών
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value) . "</td>";
        }
        echo "</tr>";
    }

    echo "</table>";
} else {
    echo "<p>Δεν βρέθηκαν δεδομένα ή πίνακας!</p>";
}

$conn->close();
?>
