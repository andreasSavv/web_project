<?php
session_start();
include("db_connect.php");
include("connected.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Μόνο καθηγητής
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

$prof = Professor_Connected($connection);
$profUserId = (int)($prof['professor_user_id'] ?? 0);
if ($profUserId <= 0) {
    die("Δεν βρέθηκαν στοιχεία καθηγητή.");
}

$diploId = isset($_GET['diplo_id']) ? (int)$_GET['diplo_id'] : 0;
if ($diploId <= 0) {
    die("Λείπει ή είναι λάθος το diplo_id.");
}

// Έλεγχος ότι ο καθηγητής έχει σχέση με τη διπλωματική (supervisor ή member)
$sqlAuth = "
SELECT
  d.diplo_id,
  d.diplo_title,
  d.diplo_status,
  d.diplo_professor,
  t.trimelous_professor1,
  t.trimelous_professor2,
  t.trimelous_professor3
FROM diplo d
LEFT JOIN trimelous t ON t.diplo_id = d.diplo_id
WHERE d.diplo_id = ?
LIMIT 1
";
$stmt = $connection->prepare($sqlAuth);
$stmt->bind_param("i", $diploId);
$stmt->execute();
$diplo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$diplo) {
    die("Η διπλωματική δεν βρέθηκε.");
}

$isSupervisor = ((int)($diplo['diplo_professor'] ?? 0) === $profUserId);
$isMember = (
    (int)($diplo['trimelous_professor1'] ?? 0) === $profUserId ||
    (int)($diplo['trimelous_professor2'] ?? 0) === $profUserId ||
    (int)($diplo['trimelous_professor3'] ?? 0) === $profUserId
);

if (!$isSupervisor && !$isMember) {
    die("Δεν έχετε δικαίωμα πρόσβασης σε αυτή τη διπλωματική.");
}

// Insert νέας σημείωσης
$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note_text'])) {
    $noteText = trim($_POST['note_text']);

    if ($noteText === "") {
        $error = "Η σημείωση δεν μπορεί να είναι κενή.";
    } elseif (mb_strlen($noteText, 'UTF-8') > 300) {
        $error = "Η σημείωση πρέπει να είναι μέχρι 300 χαρακτήρες.";
    } else {
        // Εισαγωγή στον πίνακα professor_notes
        $sqlIns = "INSERT INTO professor_notes (diplo_id, professor_user_id, notes)
                   VALUES (?, ?, ?)";
        $stmtIns = $connection->prepare($sqlIns);
        $stmtIns->bind_param("iis", $diploId, $profUserId, $noteText);
        $stmtIns->execute();
        $stmtIns->close();

        header("Location: prof_show_notes.php?diplo_id=" . $diploId . "&ok=1");
        exit;
    }
}

if (isset($_GET['ok'])) {
    $success = "✅ Η σημείωση αποθηκεύτηκε.";
}

// Φόρτωση σημειώσεων (μόνο του ίδιου καθηγητή)
$notes = [];
$sqlNotes = "SELECT notes
             FROM professor_notes
             WHERE diplo_id = ? AND professor_user_id = ?";
$stmtN = $connection->prepare($sqlNotes);
$stmtN->bind_param("ii", $diploId, $profUserId);
$stmtN->execute();
$resN = $stmtN->get_result();
while ($r = $resN->fetch_assoc()) {
    $notes[] = $r['notes'];
}
$stmtN->close();

$roleTxt = $isSupervisor ? "Επιβλέπων" : "Μέλος τριμελούς";
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Σημειώσεις Διδάσκοντα</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-dark bg-dark p-2">
    <span class="navbar-brand ms-3">Σημειώσεις Διδάσκοντα</span>
    <a href="thesis_details.php?diplo_id=<?= $diploId ?>" class="btn btn-success ms-auto me-2">Λεπτομέρειες</a>
    <a href="diplomas.php" class="btn btn-primary me-2">Λίστα</a>
    <a href="logout.php" class="btn btn-danger me-3">Αποσύνδεση</a>
</nav>

<div class="container mt-4" style="max-width: 900px;">

    <div class="card shadow-sm">
        <div class="card-body">
            <h4 class="fw-bold mb-1">Διπλωματική #<?= (int)$diploId ?></h4>
            <div class="text-muted">
                <div><strong>Θέμα:</strong> <?= htmlspecialchars($diplo['diplo_title'] ?? '') ?></div>
                <div><strong>Κατάσταση:</strong> <?= htmlspecialchars($diplo['diplo_status'] ?? '-') ?></div>
                <div><strong>Ρόλος μου:</strong> <?= htmlspecialchars($roleTxt) ?></div>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success mt-3"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger mt-3"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mt-3">
        <div class="card-header fw-bold">Νέα σημείωση (έως 300 χαρακτήρες)</div>
        <div class="card-body">
            <form method="POST">
                <textarea name="note_text" class="form-control" rows="3" maxlength="300" required></textarea>
                <div class="d-flex justify-content-between mt-2">
                    <small class="text-muted">Οι σημειώσεις είναι ορατές μόνο σε εσάς.</small>
                    <button class="btn btn-primary" type="submit">Αποθήκευση</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mt-3">
        <div class="card-header fw-bold">Οι σημειώσεις μου</div>
        <div class="card-body">

            <?php if (empty($notes)): ?>
                <p class="text-muted mb-0">Δεν έχετε καταχωρήσει σημειώσεις ακόμα.</p>
            <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($notes as $txt): ?>
                        <li class="list-group-item"><?= nl2br(htmlspecialchars($txt)) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

        </div>
    </div>

</div>

</body>
</html>
