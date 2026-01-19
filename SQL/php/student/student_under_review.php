<?php
session_start();
include("db_connect.php");
include("connected.php");

// Debug (σβήστο μετά)
error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Μόνο student
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$student = Student_Connected($connection);
if (!$student) die("Δεν βρέθηκαν στοιχεία φοιτητή.");

$studentAm = (int)($student['student_am'] ?? 0);
if ($studentAm <= 0) die("Λείπει το AM του φοιτητή.");

// Βρίσκουμε διπλωματική
$stmt = $connection->prepare("SELECT * FROM diplo WHERE diplo_student = ? LIMIT 1");
$stmt->bind_param("i", $studentAm);
$stmt->execute();
$diplo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$diplo) die("Δεν σας έχει ανατεθεί διπλωματική εργασία.");

$diploId = (int)$diplo['diplo_id'];
$status  = (string)($diplo['diplo_status'] ?? '');

// Επιτρέπεται μόνο under review (πιάσε και under_review για σιγουριά)
if ($status !== 'under review' && $status !== 'under_review') {
    die("Η σελίδα αυτή είναι διαθέσιμη μόνο όταν η διπλωματική είναι σε κατάσταση 'Υπό εξέταση'.");
}

$success = "";
$error   = "";

// ---------- Φέρνουμε (ή δημιουργούμε) εγγραφή στον draft ----------
$draftRow = null;
$stmtD = $connection->prepare("SELECT diplo_id, draft_diplo_pdf, draft_links FROM draft WHERE diplo_id = ? LIMIT 1");
$stmtD->bind_param("i", $diploId);
$stmtD->execute();
$draftRow = $stmtD->get_result()->fetch_assoc();
$stmtD->close();

if (!$draftRow) {
    // δημιουργούμε κενή εγγραφή
    $stmtIns = $connection->prepare("INSERT INTO draft (diplo_id, draft_diplo_pdf, draft_links) VALUES (?, NULL, NULL)");
    $stmtIns->bind_param("i", $diploId);
    $stmtIns->execute();
    $stmtIns->close();

    $draftRow = ['diplo_id' => $diploId, 'draft_diplo_pdf' => null, 'draft_links' => null];
}

$currentPdf   = $draftRow['draft_diplo_pdf'] ?? '';
$currentLinks = $draftRow['draft_links'] ?? '';

// βοηθητική: κάνουμε array links (1 ανά γραμμή)
function links_to_array($text) {
    $lines = preg_split("/\r\n|\n|\r/", (string)$text);
    $out = [];
    foreach ($lines as $l) {
        $l = trim($l);
        if ($l !== "") $out[] = $l;
    }
    return $out;
}
function array_to_links($arr) {
    return implode("\n", $arr);
}

$linksArr = links_to_array($currentLinks);

// ---------- Upload draft PDF ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_pdf'])) {

    if (!isset($_FILES['draft_pdf']) || $_FILES['draft_pdf']['error'] !== UPLOAD_ERR_OK) {
        $error = "Σφάλμα στο ανέβασμα αρχείου.";
    } else {
        $tmp  = $_FILES['draft_pdf']['tmp_name'];
        $name = $_FILES['draft_pdf']['name'];

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            $error = "Επιτρέπεται μόνο PDF.";
        } else {
            $maxBytes = 10 * 1024 * 1024; // 10MB
            if ($_FILES['draft_pdf']['size'] > $maxBytes) {
                $error = "Το αρχείο είναι πολύ μεγάλο (max 10MB).";
            } else {
                // Φάκελος
                $dirRel = "uploads/drafts";
                $dirAbs = __DIR__ . "/" . $dirRel;

                if (!is_dir($dirAbs)) {
                    $error = "Λείπει ο φάκελος $dirRel. Δημιούργησέ τον μέσα στο htdocs.";
                } else {
                    $safeName = "diplo_" . $diploId . "_draft_" . time() . ".pdf";
                    $targetRel = $dirRel . "/" . $safeName;
                    $targetAbs = $dirAbs . "/" . $safeName;

                    if (move_uploaded_file($tmp, $targetAbs)) {
                        // update στη ΒΔ
                        $stmtUp = $connection->prepare("UPDATE draft SET draft_diplo_pdf = ? WHERE diplo_id = ?");
                        $stmtUp->bind_param("si", $targetRel, $diploId);
                        $stmtUp->execute();
                        $stmtUp->close();

                        $currentPdf = $targetRel;
                        $success = "✅ Το πρόχειρο PDF ανέβηκε.";
                    } else {
                        $error = "Αποτυχία αποθήκευσης αρχείου στον server.";
                    }
                }
            }
        }
    }
}

// ---------- Προσθήκη link (append) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_link'])) {
    $url = trim($_POST['new_link'] ?? "");

    if ($url === "") {
        $error = "Βάλε ένα link.";
    } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
        $error = "Το link δεν είναι έγκυρο.";
    } else {
        // αποφυγή duplicate
        if (!in_array($url, $linksArr, true)) {
            $linksArr[] = $url;
        }

        $newText = array_to_links($linksArr);

        $stmtUp = $connection->prepare("UPDATE draft SET draft_links = ? WHERE diplo_id = ?");
        $stmtUp->bind_param("si", $newText, $diploId);
        $stmtUp->execute();
        $stmtUp->close();

        $success = "✅ Το link προστέθηκε.";
    }
}

// ---------- Διαγραφή link (by index) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_link'])) {
    $idx = (int)($_POST['idx'] ?? -1);

    if ($idx >= 0 && $idx < count($linksArr)) {
        array_splice($linksArr, $idx, 1);
        $newText = array_to_links($linksArr);

        $stmtUp = $connection->prepare("UPDATE draft SET draft_links = ? WHERE diplo_id = ?");
        $stmtUp->bind_param("si", $newText, $diploId);
        $stmtUp->execute();
        $stmtUp->close();

        $success = "✅ Το link διαγράφηκε.";
    }
}

?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <title>Υπό Εξέταση — Πρόχειρο</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-4" style="max-width: 900px;">
  <a class="btn btn-secondary mb-3" href="student_page.php">⟵ Πίσω</a>

  <div class="card shadow-sm">
    <div class="card-header fw-bold">Υπό εξέταση — Πρόχειρο κείμενο & Υλικό</div>
    <div class="card-body">

      <p><strong>Διπλωματική:</strong> <?= htmlspecialchars($diplo['diplo_title'] ?? '') ?></p>
      <p><strong>Κατάσταση:</strong> <?= htmlspecialchars($status) ?></p>

      <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <hr>

      <h5 class="fw-bold">1) Ανέβασμα πρόχειρου κειμένου (PDF)</h5>

      <?php if (!empty($currentPdf)): ?>
        <p>Τρέχον draft: <a href="<?= htmlspecialchars($currentPdf) ?>" target="_blank">Άνοιγμα PDF</a></p>
      <?php else: ?>
        <p class="text-muted">Δεν έχει ανέβει ακόμη draft.</p>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data" class="mt-2">
        <input type="file" name="draft_pdf" accept="application/pdf" class="form-control" required>
        <button type="submit" name="upload_pdf" class="btn btn-primary mt-2">Ανέβασμα PDF</button>
      </form>

      <hr>

      <h5 class="fw-bold">2) Links προς υλικό (Drive, YouTube κλπ)</h5>

      <form method="POST" class="row g-2 align-items-end">
        <div class="col-md-10">
          <label class="form-label">Νέο link</label>
          <input type="text" name="new_link" class="form-control" placeholder="https://..." required>
        </div>
        <div class="col-md-2">
          <button type="submit" name="add_link" class="btn btn-success w-100">Προσθήκη</button>
        </div>
      </form>

      <div class="mt-3">
        <?php if (empty($linksArr)): ?>
          <p class="text-muted">Δεν υπάρχουν links.</p>
        <?php else: ?>
          <ul class="list-group">
            <?php foreach ($linksArr as $i => $url): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <a href="<?= htmlspecialchars($url) ?>" target="_blank"><?= htmlspecialchars($url) ?></a>
                <form method="POST" onsubmit="return confirm('Διαγραφή link;');" style="margin:0;">
                  <input type="hidden" name="idx" value="<?= (int)$i ?>">
                  <button type="submit" name="delete_link" class="btn btn-outline-danger btn-sm">Διαγραφή</button>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

</body>
</html>
