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

$user = Professor_Connected($connection);
$prof_id = (int)($user['professor_user_id'] ?? $user['professor_id'] ?? 0);
if ($prof_id <= 0) die("Δεν βρέθηκαν στοιχεία καθηγητή.");

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$message = "";

// ---------- Δημιουργία νέου θέματος ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST['title'] ?? '');
    $desc  = trim($_POST['desc'] ?? '');

    if ($title === '' || $desc === '') {
        $message = "❌ Συμπλήρωσε τίτλο και περιγραφή.";
    } else {

        // Αν δεν ανέβει αρχείο, βάλε κενό string (για να μη σκάει το NOT NULL)
        $pdf_name = "";

        if (!empty($_FILES['pdf']['name']) && is_uploaded_file($_FILES['pdf']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['pdf']['name'], PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                $message = "❌ Επιτρέπεται μόνο PDF.";
            } else {
                $safeName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($_FILES['pdf']['name']));
                $pdf_name = time() . "_" . $safeName;

                if (!move_uploaded_file($_FILES['pdf']['tmp_name'], "uploads/" . $pdf_name)) {
                    $message = "❌ Αποτυχία αποθήκευσης PDF.";
                }
            }
        }

        if ($message === "") {
            $connection->begin_transaction();
            try {
                // 1) insert στο diplo
                $stmt = $connection->prepare("
                    INSERT INTO diplo (diplo_title, diplo_desc, diplo_pdf, diplo_status, diplo_professor)
                    VALUES (?, ?, ?, 'pending', ?)
                ");
                $stmt->bind_param("sssi", $title, $desc, $pdf_name, $prof_id);
                $stmt->execute();
                $newDiploId = (int)$connection->insert_id;
                $stmt->close();

                // 2) insert στο diplo_date (pending)
                $stmt2 = $connection->prepare("
                    INSERT INTO diplo_date (diplo_id, diplo_date, diplo_status)
                    VALUES (?, NOW(), 'pending')
                ");
                $stmt2->bind_param("i", $newDiploId);
                $stmt2->execute();
                $stmt2->close();

                $connection->commit();

                header("Location: add_diploma.php?ok=1");
                exit;

            } catch (Exception $e) {
                $connection->rollback();
                $message = "❌ Σφάλμα: " . $e->getMessage();
            }
        }
    }
}

if (isset($_GET['ok'])) {
    $message = "✅ Το θέμα καταχωρήθηκε επιτυχώς!";
}

// ---------- Θέματα καθηγητή ----------
$topics = [];
$stmt = $connection->prepare("
    SELECT diplo_id, diplo_title, diplo_desc, diplo_pdf
    FROM diplo
    WHERE diplo_professor = ? AND diplo_status = 'pending'
    ORDER BY diplo_id DESC
");
$stmt->bind_param("i", $prof_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $topics[] = $row;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>Θέματα προς Ανάθεση</title>

<style>
body { font-family: Arial, sans-serif; background: #eef6ff; margin: 0; padding: 0; }
.container { max-width: 1100px; margin: 40px auto; background: #fff; padding: 20px 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
h1, h2, h3 { margin-top: 0; }
.subtitle { color: #555; font-size: 0.95rem; margin-bottom: 10px; }
.top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap:12px; flex-wrap:wrap; }
.btn { text-decoration: none; padding: 8px 12px; border-radius: 6px; font-size: 0.9rem; display:inline-block; color:#fff; }
.home-btn { background: #198754; }
.home-btn:hover { background: #157347; }
.logout-btn { background: #dc3545; }
.logout-btn:hover { background: #b52a37; }
.card { padding: 15px 20px; border-radius: 8px; background: #f8fbff; border: 1px solid #dde7f5; margin-bottom: 20px; }
.label { font-weight: bold; margin-bottom: 4px; display:block; }
.input, textarea { width: 100%; padding: 10px; border: 1px solid #cfe0f4; border-radius: 6px; box-sizing:border-box; }
.input:focus, textarea:focus { border-color: #0d6efd; outline: none; box-shadow: 0 0 0 2px rgba(13,110,253,0.15); }
.btn-primary { background: #0d6efd; border:none; cursor:pointer; color:#fff; padding:10px 12px; border-radius:6px; }
.btn-primary:hover { background:#0b5ed7; }
.btn-warning { background:#ffc107; color:#000; padding:6px 10px; border-radius:6px; text-decoration:none; }
table { width:100%; border-collapse: collapse; margin-top: 10px; }
th, td { border:1px solid #dde7f5; padding:10px; vertical-align:top; }
th { background:#007bff; color:#fff; }
tr:nth-child(even) { background:#fff; }
tr:nth-child(odd) { background:#f8fbff; }
.alert { padding:10px 12px; border-radius:6px; background:#e8f2ff; border:1px solid #b6d4fe; color:#084298; margin-bottom:15px; text-align:center; }
</style>
</head>

<body>
<div class="container">

    <div class="top-bar">
        <div>
            <h1>📌 Θέματα προς Ανάθεση</h1>
            <div class="subtitle">Δημιουργία και διαχείριση θεμάτων διπλωματικής</div>
        </div>
        <div style="display:flex; gap:10px;">
            <a class="btn home-btn" href="professor_page.php">Αρχική</a>
            <a class="btn logout-btn" href="logout.php">Αποσύνδεση</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert"><?= h($message) ?></div>
    <?php endif; ?>

    <!-- Δημιουργία -->
    <div class="card">
        <h3>Δημιουργία Νέου Θέματος</h3>
        <form method="POST" enctype="multipart/form-data">
            <div class="label">Τίτλος</div>
            <input class="input" type="text" name="title" required>

            <div class="label" style="margin-top:12px;">Περιγραφή</div>
            <textarea class="input" name="desc" rows="4" required></textarea>

            <div class="label" style="margin-top:12px;">PDF (προαιρετικό)</div>
            <input class="input" type="file" name="pdf" accept="application/pdf">

            <button class="btn-primary" style="width:100%; margin-top:15px;">Καταχώρηση</button>
        </form>
    </div>

    <!-- Λίστα -->
    <div class="card">
        <h3>Τα Θέματά μου (pending)</h3>

        <?php if (empty($topics)): ?>
            <div class="subtitle">Δεν υπάρχουν θέματα προς ανάθεση.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Τίτλος</th>
                        <th>Περιγραφή</th>
                        <th>PDF</th>
                        <th>Ενέργεια</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($topics as $t): ?>
                    <tr>
                        <td><?= h($t['diplo_title']) ?></td>
                        <td><?= h($t['diplo_desc']) ?></td>
                        <td>
                            <?php if (!empty($t['diplo_pdf'])): ?>
                                <a href="<?= h('uploads/'.$t['diplo_pdf']) ?>" target="_blank">Προβολή</a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="btn-warning" href="edit_diploma.php?id=<?= (int)$t['diplo_id'] ?>">Επεξεργασία</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
