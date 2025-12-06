<?php
session_start();
include("db_connect.php");
include("connected.php");

// Login check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

// check if user is student (στο login βάζουμε strtolower, άρα 'student')
if ($_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

// get student info from database
$student = Student_Connected($connection);

// Προσπαθούμε να φτιάξουμε ένα όνομα για εμφάνιση
$displayName = "Φοιτητής";
if ($student) {
    // Προσάρμοσε αυτά τα ονόματα στηλών ανάλογα με τη ΒΔ σου
    $first = $student['student_name']      ?? '';
    $last  = $student['student_surname']   ?? '';
    
    if (trim($first . $last) !== '') {
        $displayName = trim($first . ' ' . $last);
    }
}

// Μπορεί να θες και το username από το session
$username = $_SESSION['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Φοιτητής - Αρχική</title>
    <style>
        body { font-family: Arial, sans-serif; background: #eef6ff; margin: 0; padding: 0; }
        .container { max-width: 900px; margin: 40px auto; background: #fff; padding: 20px 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1, h2, h3 { margin-top: 0; }
        .subtitle { color: #555; font-size: 0.9rem; margin-bottom: 15px; }
        ul.menu { list-style: none; padding: 0; }
        ul.menu li { margin: 8px 0; }
        ul.menu a { text-decoration: none; color: #007bff; }
        ul.menu a:hover { text-decoration: underline; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .logout-btn { text-decoration: none; padding: 6px 12px; background: #dc3545; color: #fff; border-radius: 4px; font-size: 0.9rem; }
        .logout-btn:hover { background: #b52a37; }
        .card { padding: 15px 20px; border-radius: 8px; background: #f8fbff; border: 1px solid #dde7f5; margin-bottom: 20px; }
        .label { font-weight: bold; color: #333; }
        .value { color: #444; }
    </style>
</head>
<body>
<div class="container">
    <div class="top-bar">
        <div>
            <h1>Καλωσήρθες, <?php echo htmlspecialchars($displayName); ?>!</h1>
            <?php if ($username): ?>
                <div class="subtitle">Username: <?php echo htmlspecialchars($username); ?></div>
            <?php endif; ?>
        </div>
        <div>
            <a class="logout-btn" href="logout.php">Αποσύνδεση</a>
        </div>
    </div>

    <div class="card">
        <h3>Μενού</h3>
        <ul class="menu">
            <li><a href="student_view_topic.php">📚 Προβολή θέματος διπλωματικής</a></li>
            <li><a href="student_profile.php">👤 Επεξεργασία προφίλ</a></li>
            <li><a href="student_thesis_manage.php">🛠 Διαχείριση διπλωματικής</a></li>
        </ul>
    </div>

    <div class="card">
        <h3>Πληροφορίες λογαριασμού</h3>
        <p><span class="label">Ρόλος:</span> <span class="value">Φοιτητής</span></p>
        <?php if ($student): ?>
            <?php if (!empty($student['student_am'])): ?>
                <p><span class="label">Αριθμός Μητρώου:</span> <span class="value"><?php echo htmlspecialchars($student['student_am']); ?></span></p>
            <?php endif; ?>
            <?php if (!empty($student['student_email'])): ?>
                <p><span class="label">Email:</span> <span class="value"><?php echo htmlspecialchars($student['student_email']); ?></span></p>
            <?php endif; ?>
        <?php else: ?>
            <p>Δεν βρέθηκαν επιπλέον στοιχεία φοιτητή στη βάση.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
