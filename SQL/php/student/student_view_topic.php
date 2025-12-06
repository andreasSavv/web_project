<?php
session_start();
include("db_connect.php");
include("connected.php");

// 1. Έλεγχος αν είναι συνδεδεμένος και αν είναι φοιτητής
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

// 2. Παίρνουμε στοιχεία φοιτητή
$student = Student_Connected($connection);
if (!$student) {
    die("Δεν βρέθηκαν στοιχεία φοιτητή.");
}

// 🔴 ΠΡΟΣΑΡΜΟΣΕ αυτό ανάλογα με τη ΒΔ σου:
// Υποθέτουμε ότι στον πίνακα student υπάρχει στήλη student_id
$studentId = $student['student_am'] ?? null;
if (!$studentId) {
    die("Δεν είναι σωστά ρυθμισμένη η σύνδεση φοιτητή με διπλωματική (λείπει student_id).");
}

// 3. Φέρνουμε τη διπλωματική του φοιτητή
// Υποθέτουμε ότι στον πίνακα diplo υπάρχει στήλη diplo_student που κρατάει το student_id
$sql = "SELECT * FROM diplo WHERE diplo_student = ? LIMIT 1";
$stmt = $connection->prepare($sql);
if (!$stmt) {
    die("Σφάλμα στη βάση: " . $connection->error);
}
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();

$diplo = $result->fetch_assoc();

$assignDate = null;
$assignDateText = null;
$timePassedText = "Δεν έχει οριστεί ημερομηνία ανάθεσης.";

if ($diplo) {
    // Παίρνουμε το diplo_id της συγκεκριμένης διπλωματικής
    $diploId = $diplo['diplo_id'];

    // Βρίσκουμε την ΠΡΩΤΗ (πιο παλιά) ημερομηνία από τον πίνακα diplo_date
    // (αν το όνομα του πίνακα είναι άλλο, άλλαξέ το εδώ)
    $sqlDate = "SELECT MIN(diplo_date) AS assign_date 
                FROM diplo_date 
                WHERE diplo_id = ?";
    $stmtDate = $connection->prepare($sqlDate);
    if ($stmtDate) {
        $stmtDate->bind_param("i", $diploId);
        $stmtDate->execute();
        $resDate = $stmtDate->get_result();
        if ($rowDate = $resDate->fetch_assoc()) {
            $assignDate = $rowDate['assign_date']; // π.χ. "2025-01-14 00:00:00"
        }
    }

    // Αν βρέθηκε ημερομηνία ανάθεσης → υπολογίζουμε πόσος χρόνος πέρασε
    if (!empty($assignDate)) {
        try {
            $start = new DateTime($assignDate);
            $now   = new DateTime();
            $diff  = $start->diff($now);

            // κρατάμε μορφοποιημένη ημερομηνία για εμφάνιση
            $assignDateText = $start->format('d/m/Y');

            $parts = [];
            if ($diff->y > 0) $parts[] = $diff->y . " έτος" . ($diff->y > 1 ? "η" : "");
            if ($diff->m > 0) $parts[] = $diff->m . " μήνας" . ($diff->m > 1 ? "ες" : "");
            if ($diff->d > 0) $parts[] = $diff->d . " ημέρα" . ($diff->d > 1 ? "ες" : "");
            if (empty($parts)) $parts[] = "0 ημέρες";

            $timePassedText = implode(", ", $parts);
        } catch (Exception $e) {
            $timePassedText = "Σφάλμα στον υπολογισμό ημερομηνίας.";
        }
    }
}


?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Η διπλωματική μου</title>
    <style>
        body { font-family: Arial, sans-serif; background: #eef6ff; margin: 0; padding: 0; }
        .container { max-width: 900px; margin: 40px auto; background: #fff; padding: 20px 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1, h2, h3 { margin-top: 0; }
        .subtitle { color: #555; font-size: 0.9rem; margin-bottom: 15px; }
        .field-label { font-weight: bold; color: #333; }
        .field-value { margin-bottom: 10px; color: #444; }
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 6px; background: #e0ebff; font-size: 0.85rem; }
        .back-link { text-decoration: none; color: #007bff; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="container">
    <a class="back-link" href="student_page.php">&larr; Πίσω στην αρχική φοιτητή</a>
    <h1>Η Διπλωματική Μου</h1>

<?php if (!$diplo): ?>
    <p>Δεν σας έχει ανατεθεί ακόμη κάποια διπλωματική εργασία.</p>
<?php else: ?>

    <?php
    // Βγάζουμε τα πεδία από τη ΒΔ (ΠΡΟΣΑΡΜΟΣΕ τα ονόματα αν χρειάζεται)
    $title       = $diplo['diplo_title']       ?? '';
    $desc        = $diplo['diplo_desc']        ?? '';
    $pdfFile     = $diplo['diplo_pdf']         ?? '';
    $status      = $diplo['diplo_status']      ?? '';
    $committee   = $diplo['diplo_trimelis']    ?? ''; // π.χ. ονόματα χωρισμένα με κόμμα
    $assignDate  = $diplo['diplo_assign_date'] ?? null; // 🔴 ΠΡΟΣΟΧΗ: στήλη ημερομηνίας ανάθεσης

    // Υπολογισμός χρόνου από ανάθεση
    $timePassedText = "Δεν έχει οριστεί ημερομηνία ανάθεσης.";
    if (!empty($assignDate)) {
        try {
            $start = new DateTime($assignDate);
            $now   = new DateTime();
            $diff  = $start->diff($now);

            // φτιάχνουμε ένα απλό κείμενο π.χ. "1 έτος, 2 μήνες και 5 ημέρες"
            $parts = [];
            if ($diff->y > 0) $parts[] = $diff->y . " έτος" . ($diff->y > 1 ? "η" : "");
            if ($diff->m > 0) $parts[] = $diff->m . " μήνας" . ($diff->m > 1 ? "ες" : "");
            if ($diff->d > 0) $parts[] = $diff->d . " ημέρα" . ($diff->d > 1 ? "ες" : "");
            if (empty($parts)) $parts[] = "0 ημέρες";

            $timePassedText = implode(", ", $parts);
        } catch (Exception $e) {
            $timePassedText = "Σφάλμα στον υπολογισμό ημερομηνίας.";
        }
    }
    ?>

    <div class="field">
        <div class="field-label">Θέμα:</div>
        <div class="field-value"><?php echo htmlspecialchars($title); ?></div>
    </div>

    <div class="field">
        <div class="field-label">Περιγραφή:</div>
        <div class="field-value"><?php echo nl2br(htmlspecialchars($desc)); ?></div>
    </div>

    <div class="field">
        <div class="field-label">Συνημμένο αρχείο περιγραφής:</div>
        <div class="field-value">
            <?php if (!empty($pdfFile)): ?>
                <a href="<?php echo htmlspecialchars($pdfFile); ?>" target="_blank">Άνοιγμα PDF</a>
            <?php else: ?>
                Δεν έχει ανέβει αρχείο.
            <?php endif; ?>
        </div>
    </div>

    <div class="field">
        <div class="field-label">Τρέχουσα κατάσταση:</div>
        <div class="field-value">
            <span class="status-badge">
                <?php echo htmlspecialchars($status); ?>
            </span>
        </div>
    </div>

    <div class="field">
        <div class="field-label">Μέλη τριμελούς επιτροπής:</div>
        <div class="field-value">
            <?php echo !empty($committee) ? htmlspecialchars($committee) : "Δεν έχουν οριστεί ακόμα."; ?>
        </div>
    </div>

    <div class="field">
    <div class="field-label">Χρόνος από την επίσημη ανάθεση:</div>
    <div class="field-value">
        <?php if ($assignDateText): ?>
            Ημερομηνία ανάθεσης: 
            <strong><?php echo htmlspecialchars($assignDateText); ?></strong>
            — 
            έχουν περάσει 
            <strong><?php echo htmlspecialchars($timePassedText); ?></strong>.
        <?php else: ?>
            Δεν έχει οριστεί ημερομηνία ανάθεσης.
        <?php endif; ?>
    </div>
</div>


<?php endif; ?>
</div>
</body>
</html>
