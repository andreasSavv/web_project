<?php
session_start();
include("db_connect.php");
include("connected.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 1) Μόνο φοιτητής
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

// 2) Στοιχεία φοιτητή
$student = Student_Connected($connection);
if (!$student) die("Δεν βρέθηκαν στοιχεία φοιτητή.");

$studentAm = $student['student_am'] ?? null;
if (!$studentAm) die("Λείπει το AM του φοιτητή.");

// 3) Φέρνουμε τη διπλωματική του φοιτητή
$sql = "
  SELECT *
  FROM diplo
  WHERE diplo_student = ?
  ORDER BY
    FIELD(diplo_status, 'under review', 'under_review', 'active', 'finished', 'pending', 'cancelled'),
    diplo_id DESC
  LIMIT 1
";
$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $studentAm);
$stmt->execute();
$diplo = $stmt->get_result()->fetch_assoc();
$stmt->close();

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Η Διπλωματική Μου</title>
    <style>
        body { font-family: Arial, sans-serif; background: #eef6ff; margin: 0; padding: 0; }
        .container { max-width: 900px; margin: 40px auto; background: #fff; padding: 20px 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .back-link { text-decoration: none; color: #007bff; }
        .back-link:hover { text-decoration: underline; }
        .field-label { font-weight: bold; margin-top: 12px; }
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 6px; background: #e0ebff; font-size: 0.85rem; }
        ul { margin-top: 6px; }
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
    $diploId   = (int)$diplo['diplo_id'];
    $title     = $diplo['diplo_title']  ?? '';
    $desc      = $diplo['diplo_desc']   ?? '';
    $pdfFile   = $diplo['diplo_pdf']    ?? '';
    $status    = $diplo['diplo_status'] ?? '';

    // ----------------- Τριμελής -----------------
    // Παίρνουμε επιβλέποντα από diplo, και μέλη από trimelous
    $supervisorUserId = (int)($diplo['diplo_professor'] ?? 0);

    $sqlTri = "
        SELECT
            t.trimelous_professor2,
            t.trimelous_professor3,
            ps.professor_name  AS sup_name,
            ps.professor_surname AS sup_surname,
            p2.professor_name  AS p2_name, p2.professor_surname AS p2_surname,
            p3.professor_name  AS p3_name, p3.professor_surname AS p3_surname
        FROM diplo d
        LEFT JOIN trimelous t ON t.diplo_id = d.diplo_id
        LEFT JOIN professor ps ON ps.professor_user_id = d.diplo_professor
        LEFT JOIN professor p2 ON p2.professor_user_id = t.trimelous_professor2
        LEFT JOIN professor p3 ON p3.professor_user_id = t.trimelous_professor3
        WHERE d.diplo_id = ?
        LIMIT 1
    ";
    $stmtTri = $connection->prepare($sqlTri);
    $stmtTri->bind_param("i", $diploId);
    $stmtTri->execute();
    $tri = $stmtTri->get_result()->fetch_assoc();
    $stmtTri->close();

    $committee = [];
    if (!empty($tri['sup_surname']) || !empty($tri['sup_name'])) {
        $committee[] = "Επιβλέπων: " . trim(($tri['sup_surname'] ?? '') . " " . ($tri['sup_name'] ?? ''));
    }
    if (!empty($tri['p2_surname']) || !empty($tri['p2_name'])) {
        $committee[] = "Μέλος 2: " . trim(($tri['p2_surname'] ?? '') . " " . ($tri['p2_name'] ?? ''));
    }
    if (!empty($tri['p3_surname']) || !empty($tri['p3_name'])) {
        $committee[] = "Μέλος 3: " . trim(($tri['p3_surname'] ?? '') . " " . ($tri['p3_name'] ?? ''));
    }

    // ----------------- Ημερομηνία επίσημης ανάθεσης -----------------
    // Παίρνουμε την ΠΡΩΤΗ φορά που έγινε pending στο diplo_date
    $assignDate = null;
    $sqlAssign = "SELECT diplo_date
                  FROM diplo_date
                  WHERE diplo_id = ? AND diplo_status = 'pending'
                  ORDER BY diplo_date ASC
                  LIMIT 1";
    $stmtA = $connection->prepare($sqlAssign);
    $stmtA->bind_param("i", $diploId);
    $stmtA->execute();
    $rowA = $stmtA->get_result()->fetch_assoc();
    $stmtA->close();

    if ($rowA && !empty($rowA['diplo_date'])) {
        $assignDate = $rowA['diplo_date'];
    }

    $assignText = "Δεν έχει οριστεί ημερομηνία ανάθεσης.";
    if ($assignDate) {
        try {
            $start = new DateTime($assignDate);
            $now = new DateTime();
            $diff = $start->diff($now);

            $parts = [];
            if ($diff->y > 0) $parts[] = $diff->y . " έτη";
            if ($diff->m > 0) $parts[] = $diff->m . " μήνες";
            if ($diff->d > 0) $parts[] = $diff->d . " ημέρες";
            if (empty($parts)) $parts[] = "0 ημέρες";

            $assignText = "Ημερομηνία ανάθεσης: " . $start->format("d/m/Y") . " — έχουν περάσει " . implode(", ", $parts);
        } catch (Exception $e) {
            $assignText = "Σφάλμα στον υπολογισμό ημερομηνίας.";
        }
    }
?>

    <div class="field-label">Θέμα:</div>
    <div><?php echo htmlspecialchars($title); ?></div>

    <div class="field-label">Περιγραφή:</div>
    <div><?php echo nl2br(htmlspecialchars($desc)); ?></div>

    <div class="field-label">Συνημμένο αρχείο περιγραφής:</div>
    <div>
        <?php if (!empty($pdfFile)): ?>
            <a href="<?php echo htmlspecialchars($pdfFile); ?>" target="_blank">Άνοιγμα PDF</a>
        <?php else: ?>
            Δεν έχει ανέβει αρχείο.
        <?php endif; ?>
    </div>

    <div class="field-label">Τρέχουσα κατάσταση:</div>
    <div><span class="status-badge"><?php echo htmlspecialchars($status); ?></span></div>

    <div class="field-label">Μέλη τριμελούς επιτροπής:</div>
    <div>
        <?php if (count($committee) === 0): ?>
            Δεν έχουν οριστεί ακόμα.
        <?php else: ?>
            <ul>
                <?php foreach ($committee as $c): ?>
                    <li><?php echo htmlspecialchars($c); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="field-label">Χρόνος από την επίσημη ανάθεση:</div>
    <div><?php echo htmlspecialchars($assignText); ?></div>

<?php endif; ?>

</div>
</body>
</html>
