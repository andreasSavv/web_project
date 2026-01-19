<?php
session_start();
include("db_connect.php");
include("connected.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Μόνο professor
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "professor") {
    header("Location: login.php");
    exit;
}

$user = Professor_Connected($connection);
$profUserId = (int)($user['professor_user_id'] ?? $user['professor_id'] ?? 0);
if ($profUserId <= 0) die("Δεν βρέθηκαν στοιχεία καθηγητή.");

$message = "";

// ------------------ Ενεργοποίηση βαθμολόγησης ------------------
if ($_SERVER['REQUEST_METHOD'] === "POST" && isset($_POST['enable_grading'])) {
    $diplo_id = (int)($_POST['diplo_id'] ?? 0);

    // μόνο επιβλέπων + μόνο under review
    $chk = $connection->prepare("
        SELECT diplo_id
        FROM diplo
        WHERE diplo_id = ?
          AND diplo_professor = ?
          AND diplo_status IN ('under review','under_review')
        LIMIT 1
    ");
    $chk->bind_param("ii", $diplo_id, $profUserId);
    $chk->execute();
    $ok = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$ok) {
        $message = "❌ Δεν επιτρέπεται (πρέπει να είστε επιβλέπων και η ΔΕ να είναι 'Υπό Εξέταση').";
    } else {

        // enable flag
        $upd = $connection->prepare("
            UPDATE diplo
            SET grading_enabled = 1
            WHERE diplo_id = ? AND diplo_professor = ?
        ");
        $upd->bind_param("ii", $diplo_id, $profUserId);
        $upd->execute();
        $upd->close();

        // ensure row exists in trimelis_grades (ιδανικά UNIQUE στο diplo_id)
        $ins = $connection->prepare("
            INSERT INTO trimelis_grades (diplo_id)
            VALUES (?)
            ON DUPLICATE KEY UPDATE diplo_id = diplo_id
        ");
        $ins->bind_param("i", $diplo_id);
        $ins->execute();
        $ins->close();

        $message = "✅ Η βαθμολόγηση ενεργοποιήθηκε για τη ΔΕ (ID: $diplo_id).";
    }

    header("Location: grade_enable.php?msg=" . urlencode($message));
    exit;
}

if (isset($_GET['msg'])) $message = $_GET['msg'];

// ------------------ Λίστα ΔΕ επιβλέποντα σε under review ------------------
$list = [];
$stmt = $connection->prepare("
    SELECT
      d.diplo_id,
      d.diplo_title,
      d.diplo_student,
      d.diplo_status,
      d.grading_enabled,
      s.student_name,
      s.student_surname
    FROM diplo d
    LEFT JOIN student s ON s.student_am = d.diplo_student
    WHERE d.diplo_professor = ?
      AND d.diplo_status IN ('under review','under_review')
    ORDER BY d.diplo_id DESC
");
$stmt->bind_param("i", $profUserId);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $list[] = $r;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>9) Ενεργοποίηση Βαθμού</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <button class="btn btn-outline-light me-2 d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle sidebar">
      ☰ Μενού
    </button>

    <span class="navbar-brand">Η Πλατφόρμα</span>
    <a href="professor_page.php" class="btn btn-success ms-2">Αρχική</a>

    <div class="ms-auto">
      <a href="logout.php" class="btn btn-danger">Αποσύνδεση</a>
    </div>
  </div>
</nav>

<div class="container-fluid">
  <div class="row">
    

    <main class="col-md-8 col-lg-9 ms-sm-auto px-3 px-md-4 pt-4">

      <div class="card shadow-lg p-4">
        <h2 class="mb-3 text-center text-primary">
          9) Ενεργοποίηση Δυνατότητας Καταχώρησης Βαθμού (ως Επιβλέπων)
        </h2>
        <p class="text-center text-muted mb-4">
          Εμφανίζονται οι διπλωματικές σας σε κατάσταση <strong>Υπό Εξέταση</strong>.
        </p>

        <?php if (!empty($message)): ?>
          <div class="alert alert-info text-center"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (empty($list)): ?>
          <div class="alert alert-warning text-center">
            Δεν υπάρχουν διπλωματικές σε κατάσταση <strong>Υπό Εξέταση</strong>.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
              <thead class="table-dark">
                <tr>
                  <th>ID</th>
                  <th>Τίτλος</th>
                  <th>Φοιτητής</th>
                  <th>Status</th>
                  <th>Grading Enabled</th>
                  <th>Ενέργεια</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($list as $d): ?>
                <?php
                  $studTxt = "-";
                  if (!empty($d['diplo_student'])) {
                      $studTxt = $d['diplo_student'] . " - " . trim(($d['student_surname'] ?? '') . " " . ($d['student_name'] ?? ''));
                      $studTxt = trim($studTxt);
                  }
                  $enabled = ((int)$d['grading_enabled'] === 1);
                ?>
                <tr>
                  <td><?= (int)$d['diplo_id'] ?></td>
                  <td><?= htmlspecialchars($d['diplo_title'] ?? '') ?></td>
                  <td><?= htmlspecialchars($studTxt) ?></td>
                  <td><span class="badge bg-secondary"><?= htmlspecialchars($d['diplo_status'] ?? '-') ?></span></td>
                  <td>
                    <?php if ($enabled): ?>
                      <span class="badge bg-success">Ναι</span>
                    <?php else: ?>
                      <span class="badge bg-warning text-dark">Όχι</span>
                    <?php endif; ?>
                  </td>
                  <td style="min-width:220px;">
                    <?php if ($enabled): ?>
                      <a class="btn btn-primary btn-sm w-100"
                         href="diplo_grade.php?diplo_id=<?= (int)$d['diplo_id'] ?>">
                         Μετάβαση στη βαθμολόγηση
                      </a>
                    <?php else: ?>
                      <form method="POST" onsubmit="return confirm('Ενεργοποίηση δυνατότητας βαθμολόγησης;');">
                        <input type="hidden" name="diplo_id" value="<?= (int)$d['diplo_id'] ?>">
                        <button type="submit" name="enable_grading" class="btn btn-success btn-sm w-100">
                          Ενεργοποίηση βαθμού
                        </button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

      </div>

    </main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
