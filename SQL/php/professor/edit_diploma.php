<?php
session_start();
include("db_connect.php");
include("connected.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) die("Μη έγκυρο θέμα.");

$sql = "SELECT * FROM diplo WHERE diplo_id = $id";
$result = mysqli_query($connection, $sql);
$diploma = mysqli_fetch_assoc($result);

if (!$diploma) die("Το θέμα δεν βρέθηκε.");

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST['title']);
    $desc  = trim($_POST['description']);

    $pdf_name = $diploma['diplo_pdf'];

    if (!empty($_FILES['pdf']['name'])) {
        $pdf_name = time() . "_" . basename($_FILES['pdf']['name']);
        move_uploaded_file($_FILES['pdf']['tmp_name'], "uploads/$pdf_name");
    }

    $stmt = $connection->prepare("
        UPDATE diplo 
        SET diplo_title = ?, diplo_desc = ?, diplo_pdf = ?
        WHERE diplo_id = ?
    ");
    $stmt->bind_param("sssi", $title, $desc, $pdf_name, $id);
    $stmt->execute();
    $stmt->close();

    $message = "✅ Η ενημέρωση ολοκληρώθηκε επιτυχώς!";
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>Επεξεργασία Θέματος</title>

<style>
body { font-family: Arial, sans-serif; background: #eef6ff; margin: 0; padding: 0; }
.container { max-width: 900px; margin: 40px auto; background: #fff; padding: 20px 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
h1, h2 { margin-top: 0; }
.subtitle { color: #555; font-size: 0.95rem; margin-bottom: 10px; }
.top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.btn { text-decoration: none; padding: 8px 12px; border-radius: 6px; font-size: 0.9rem; display:inline-block; }
.home-btn { background: #198754; color: #fff; }
.home-btn:hover { background: #157347; }
.back-btn { background: #6c757d; color: #fff; }
.back-btn:hover { background: #5c636a; }
.logout-btn { background: #dc3545; color: #fff; }
.logout-btn:hover { background: #b52a37; }

.card { padding: 15px 20px; border-radius: 8px; background: #f8fbff; border: 1px solid #dde7f5; }
.label { font-weight: bold; margin-bottom: 5px; display:block; }
.input, textarea { width: 100%; padding: 10px; border: 1px solid #cfe0f4; border-radius: 6px; }
.input:focus, textarea:focus { border-color: #0d6efd; outline: none; box-shadow: 0 0 0 2px rgba(13,110,253,0.15); }
.btn-primary { background: #0d6efd; color:#fff; border:none; cursor:pointer; }
.btn-primary:hover { background:#0b5ed7; }

.alert { padding:10px 12px; border-radius:6px; background:#e8f2ff; border:1px solid #b6d4fe; color:#084298; margin-bottom:15px; }
.pdf-box { margin-top: 6px; font-size: 0.9rem; }
</style>
</head>

<body>

<div class="container">

    <!-- Top bar -->
    <div class="top-bar">
        <div>
            <h1>✏️ Επεξεργασία Θέματος</h1>
            <div class="subtitle">Τροποποίηση στοιχείων διπλωματικής</div>
        </div>
        <div>
            <a class="btn home-btn" href="professor_page.php">Αρχική</a>
            <a class="btn back-btn" href="add_diploma.php">Επιστροφή</a>
            <a class="btn logout-btn" href="logout.php">Αποσύνδεση</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" enctype="multipart/form-data">

            <div class="label">Τίτλος</div>
            <input class="input" type="text" name="title"
                   value="<?= htmlspecialchars($diploma['diplo_title']) ?>" required>

            <div class="label" style="margin-top:12px;">Περιγραφή</div>
            <textarea class="input" name="description" rows="4" required><?= htmlspecialchars($diploma['diplo_desc']) ?></textarea>

            <div class="label" style="margin-top:12px;">PDF</div>

            <?php if (!empty($diploma['diplo_pdf'])): ?>
                <div class="pdf-box">
                    Τρέχον αρχείο:
                    <a href="uploads/<?= htmlspecialchars($diploma['diplo_pdf']) ?>" target="_blank">
                        Προβολή PDF
                    </a>
                </div>
            <?php else: ?>
                <div class="pdf-box text-muted">Δεν υπάρχει ανεβασμένο PDF</div>
            <?php endif; ?>

            <input class="input" type="file" name="pdf" accept="application/pdf" style="margin-top:6px;">

            <button class="btn btn-primary" style="width:100%; margin-top:16px;">
                Αποθήκευση αλλαγών
            </button>

        </form>
    </div>

</div>

</body>
</html>
