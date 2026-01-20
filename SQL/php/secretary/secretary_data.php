<?php
session_start();
include("db_connect.php");
include("connected.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Μόνο γραμματεία
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'secretary') {
    header("Location: login.php");
    exit;
}

$message = "";
$students = [];
$selected = null;

// ----------- Αναζήτηση φοιτητή -----------
if (isset($_GET['search']) && trim($_GET['search']) !== '') {
    $term = trim($_GET['search']);
    $like = "%{$term}%";

    $stmt = $connection->prepare("
        SELECT student_am, student_name, student_surname, student_middlename,
               student_street, student_streetnum, student_city, student_postcode,
               student_email, student_tel, student_user_id
        FROM student
        WHERE CAST(student_am AS CHAR) LIKE ?
           OR student_name LIKE ?
           OR student_surname LIKE ?
           OR student_middlename LIKE ?
        ORDER BY student_surname, student_name
        LIMIT 50
    ");
    $stmt->bind_param("ssss", $like, $like, $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $students[] = $row;
    $stmt->close();
}

// ----------- Επιλογή φοιτητή -----------
if (isset($_GET['student_am'])) {
    $am = (int)$_GET['student_am'];
    if ($am > 0) {
        $stmt = $connection->prepare("
            SELECT student_am, student_name, student_surname, student_middlename,
                   student_street, student_streetnum, student_city, student_postcode,
                   student_email, student_tel, student_user_id
            FROM student
            WHERE student_am = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $am);
        $stmt->execute();
        $selected = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// ----------- Update στοιχείων -----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {

    $am = (int)($_POST['student_am'] ?? 0);
    if ($am <= 0) {
        $message = "❌ Μη έγκυρο ΑΜ.";
    } else {
        $street     = trim($_POST['student_street'] ?? "");
        $streetnum  = trim($_POST['student_streetnum'] ?? "");
        $city       = trim($_POST['student_city'] ?? "");
        $postcode   = trim($_POST['student_postcode'] ?? "");
        $email      = trim($_POST['student_email'] ?? "");
        $tel        = trim($_POST['student_tel'] ?? "");

        if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "❌ Το email δεν είναι έγκυρο.";
        } else {
            $stmt = $connection->prepare("
                UPDATE student
                SET student_street = ?,
                    student_streetnum = ?,
                    student_city = ?,
                    student_postcode = ?,
                    student_email = ?,
                    student_tel = ?
                WHERE student_am = ?
            ");
            $stmt->bind_param("ssssssi", $street, $streetnum, $city, $postcode, $email, $tel, $am);
            $stmt->execute();
            $stmt->close();

            $message = "✅ Τα στοιχεία επικοινωνίας ενημερώθηκαν!";

            // reload selected
            $stmt = $connection->prepare("
                SELECT student_am, student_name, student_surname, student_middlename,
                       student_street, student_streetnum, student_city, student_postcode,
                       student_email, student_tel, student_user_id
                FROM student
                WHERE student_am = ?
                LIMIT 1
            ");
            $stmt->bind_param("i", $am);
            $stmt->execute();
            $selected = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Γραμματεία - Εισαγωγή Δεδομένων</title>
    <style>
        body { font-family: Arial, sans-serif; background: #eef6ff; margin: 0; padding: 0; }
        .container { max-width: 1100px; margin: 40px auto; background: #fff; padding: 20px 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1, h2, h3 { margin-top: 0; }
        .subtitle { color: #555; font-size: 0.95rem; margin-bottom: 10px; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn { text-decoration: none; padding: 8px 12px; border-radius: 6px; font-size: 0.9rem; display:inline-block; }
        .home-btn { background: #198754; color: #fff; }
        .home-btn:hover { background: #157347; }
        .logout-btn { background: #dc3545; color: #fff; }
        .logout-btn:hover { background: #b52a37; }
        .card { padding: 15px 20px; border-radius: 8px; background: #f8fbff; border: 1px solid #dde7f5; margin-bottom: 20px; }
        .label { font-weight: bold; color: #333; }
        .input { width: 100%; padding: 10px; border: 1px solid #cfe0f4; border-radius: 6px; outline: none; }
        .input:focus { border-color: #0d6efd; box-shadow: 0 0 0 2px rgba(13,110,253,0.15); }
        .btn-primary { background: #0d6efd; color: #fff; border: none; cursor: pointer; }
        .btn-primary:hover { background: #0b5ed7; }
        .btn-success { background: #198754; color: #fff; border: none; cursor: pointer; }
        .btn-success:hover { background: #157347; }
        .btn-link { background: #0d6efd; color:#fff; text-decoration:none; padding:6px 10px; border-radius:6px; }
        .btn-link:hover { background:#0b5ed7; }
        .grid { display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
        .grid3 { display:grid; grid-template-columns: 2fr 1fr; gap:12px; }
        @media (max-width: 800px) {
            .grid, .grid3 { grid-template-columns: 1fr; }
        }
        table { width:100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #dde7f5; padding: 10px; text-align: left; }
        th { background: #007bff; color:#fff; }
        tr:nth-child(even) { background:#ffffff; }
        tr:nth-child(odd) { background:#f8fbff; }
        .alert { padding:10px 12px; border-radius:6px; margin-bottom: 15px; }
        .alert-info { background:#e8f2ff; border:1px solid #b6d4fe; color:#084298; }
        .alert-warn { background:#fff3cd; border:1px solid #ffecb5; color:#664d03; }
        .actions { display:flex; gap:10px; align-items:center; }
    </style>
</head>
<body>

<div class="container">
    <div class="top-bar">
        <div>
            <h1>👤 Εισαγωγή δεδομένων</h1>
            <div class="subtitle">Επεξεργασία στοιχείων επικοινωνίας φοιτητή</div>
        </div>
        <div class="actions">
            <a class="btn home-btn" href="secretary_page.php">Αρχική</a>
            <a class="btn logout-btn" href="logout.php">Αποσύνδεση</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Αναζήτηση -->
    <div class="card">
        <h3>Αναζήτηση Φοιτητή</h3>
        <form method="GET" class="grid3">
            <div>
                <input class="input" type="text" name="search" placeholder="ΑΜ ή Όνομα ή Επώνυμο ή Πατρώνυμο"
                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>
            <div>
                <button class="btn btn-primary" style="width:100%; padding:10px;">Αναζήτηση</button>
            </div>
        </form>
    </div>

    <!-- Αποτελέσματα -->
    <?php if (!empty($students)): ?>
        <div class="card">
            <h3>Αποτελέσματα</h3>
            <table>
                <thead>
                    <tr>
                        <th>ΑΜ</th>
                        <th>Ονοματεπώνυμο</th>
                        <th>Email</th>
                        <th>Τηλέφωνο</th>
                        <th>Ενέργεια</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $st): ?>
                    <tr>
                        <td><?= (int)$st['student_am'] ?></td>
                        <td><?= htmlspecialchars(($st['student_surname'] ?? '') . " " . ($st['student_name'] ?? '') . " " . ($st['student_middlename'] ?? '')) ?></td>
                        <td><?= htmlspecialchars($st['student_email'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($st['student_tel'] ?? '-') ?></td>
                        <td>
                            <a class="btn-link" href="secretary_data.php?student_am=<?= (int)$st['student_am'] ?>">Επεξεργασία</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif (isset($_GET['search'])): ?>
        <div class="alert alert-warn">Δεν βρέθηκαν φοιτητές.</div>
    <?php endif; ?>

    <!-- Φόρμα επεξεργασίας -->
    <?php if ($selected): ?>
        <div class="card">
            <h3>
                Επεξεργασία Προφίλ —
                <?= htmlspecialchars(($selected['student_surname'] ?? '') . " " . ($selected['student_name'] ?? '') . " " . ($selected['student_middlename'] ?? '')) ?>
                (ΑΜ: <?= (int)$selected['student_am'] ?>)
            </h3>

            <form method="POST">
                <input type="hidden" name="student_am" value="<?= (int)$selected['student_am'] ?>">

                <div class="grid">
                    <div>
                        <div class="label">Οδός</div>
                        <input class="input" type="text" name="student_street" value="<?= htmlspecialchars($selected['student_street'] ?? '') ?>">
                    </div>
                    <div>
                        <div class="label">Αριθμός</div>
                        <input class="input" type="text" name="student_streetnum" value="<?= htmlspecialchars($selected['student_streetnum'] ?? '') ?>">
                    </div>
                </div>

                <div class="grid" style="margin-top:12px;">
                    <div>
                        <div class="label">Πόλη</div>
                        <input class="input" type="text" name="student_city" value="<?= htmlspecialchars($selected['student_city'] ?? '') ?>">
                    </div>
                    <div>
                        <div class="label">Τ.Κ.</div>
                        <input class="input" type="text" name="student_postcode" value="<?= htmlspecialchars($selected['student_postcode'] ?? '') ?>">
                    </div>
                </div>

                <div style="margin-top:12px;">
                    <div class="label">Email επικοινωνίας</div>
                    <input class="input" type="email" name="student_email" value="<?= htmlspecialchars($selected['student_email'] ?? '') ?>">
                </div>

                <div style="margin-top:12px;">
                    <div class="label">Τηλέφωνο επικοινωνίας (κινητό/σταθερό)</div>
                    <input class="input" type="text" name="student_tel" value="<?= htmlspecialchars($selected['student_tel'] ?? '') ?>">
                </div>

                <div style="margin-top:16px;">
                    <button class="btn btn-success" type="submit" name="save_profile" style="width:100%; padding:10px;">
                        Αποθήκευση
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

</div>
</body>
</html>
