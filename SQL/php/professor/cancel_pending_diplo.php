<?php
session_start();
include("db_connect.php");
include("connected.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

$user = Professor_Connected($connection);
$prof_id = (int)($user['professor_user_id'] ?? $user['professor_id'] ?? 0);
if ($prof_id <= 0) die("Δεν βρέθηκε professor id.");

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$message = "";

// Λίστα pending (του επιβλέποντα)
$list = [];
$stmt = $connection->prepare("
    SELECT diplo_id, diplo_title, diplo_student, diplo_status
    FROM diplo
    WHERE diplo_professor = ?
      AND diplo_status = 'pending'
    ORDER BY diplo_id DESC
");
$stmt->bind_param("i", $prof_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $list[] = $r;
$stmt->close();

// Ακύρωση pending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_pending'])) {
    $diplo_id = (int)($_POST['diplo_id'] ?? 0);

    // Έλεγχος ότι είναι δική του και pending
    $chk = $connection->prepare("
        SELECT diplo_id
        FROM diplo
        WHERE diplo_id = ?
          AND diplo_professor = ?
          AND diplo_status = 'pending'
        LIMIT 1
    ");
    $chk->bind_param("ii", $diplo_id, $prof_id);
    $chk->execute();
    $ok = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$ok) {
        $message = "❌ Δεν επιτρέπεται ακύρωση (δεν είναι pending ή δεν είστε ο επιβλέπων).";
    } else {
        $connection->begin_transaction();
        try {
            // 1) Ακύρωση διπλωματικής
            $upd = $connection->prepare("
                UPDATE diplo
                SET diplo_status = 'cancelled',
                    diplo_student = NULL,
                    diplo_trimelis = NULL
                WHERE diplo_id = ?
                  AND diplo_professor = ?
                  AND diplo_status = 'pending'
            ");
            $upd->bind_param("ii", $diplo_id, $prof_id);
            $upd->execute();
            $upd->close();

            // 2) Διαγραφή προσκλήσεων τριμελούς
            $del = $connection->prepare("DELETE FROM trimelous_invite WHERE diplo_id = ?");
            $del->bind_param("i", $diplo_id);
            $del->execute();
            $del->close();

            $connection->commit();
            $message = "✅ Η pending διπλωματική ακυρώθηκε επιτυχώς και οι προσκλήσεις διαγράφηκαν.";

        } catch (Exception $e) {
            $connection->rollback();
            $message = "❌ Σφάλμα: " . $e->getMessage();
        }
    }

    header("Location: cancel_pending_diplo.php?msg=" . urlencode($message));
    exit;
}

if (isset($_GET['msg'])) $message = $_GET['msg'];
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>Ακύρωση Pending Διπλωματικής</title>
<style>
  body { font-family: Arial, sans-serif; background:#eef6ff; margin:0; padding:0; }
  .container { max-width:1100px; margin:40px auto; background:#fff; padding:20px 30px; border-radius:10px; box-shadow:0 0 10px rgba(0,0,0,0.1); }
  .top-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; gap:12px; flex-wrap:wrap; }
  .subtitle { color:#555; font-size:.95rem; }
  .btn { padding:8px 12px; border-radius:6px; text-decoration:none; color:#fff; font-size:.9rem; border:none; cursor:pointer; display:inline-block; }
  .home { background:#198754; } .home:hover{ background:#157347; }
  .logout { background:#dc3545; } .logout:hover{ background:#b52a37; }
  .danger { background:#dc3545; } .danger:hover{ background:#b52a37; }
  .card { background:#f8fbff; border:1px solid #dde7f5; border-radius:8px; padding:15px 20px; }
  .alert { padding:10px 12px; border-radius:6px; margin-bottom:15px; background:#e8f2ff; border:1px solid #b6d4fe; color:#084298; text-align:center; }
  table { width:100%; border-collapse:collapse; margin-top:10px; }
  th, td { border:1px solid #dde7f5; padding:10px; text-align:left; }
  th { background:#007bff; color:#fff; }
  tr:nth-child(even){ background:#fff; } tr:nth-child(odd){ background:#f8fbff; }
  .badge { padding:4px 8px; border-radius:6px; font-size:.8rem; background:#ffc107; }
  .center { text-align:center; }
</style>
</head>
<body>
<div class="container">

  <div class="top-bar">
    <div>
      <h1>❌ Ακύρωση Pending Διπλωματικής</h1>
      <div class="subtitle">Διαχείριση διπλωματικών σε κατάσταση «Υπό Ανάθεση»</div>
    </div>
    <div>
      <a class="btn home" href="professor_page.php">Αρχική</a>
      <a class="btn logout" href="logout.php">Αποσύνδεση</a>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert"><?= h($message) ?></div>
  <?php endif; ?>

  <div class="card">
    <h3>Pending Διπλωματικές</h3>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Τίτλος</th>
          <th>Φοιτητής</th>
          <th>Κατάσταση</th>
          <th>Ενέργεια</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($list)): ?>
        <tr><td colspan="5" class="center">Δεν υπάρχουν pending διπλωματικές.</td></tr>
      <?php else: ?>
        <?php foreach ($list as $d): ?>
          <tr>
            <td><?= (int)$d['diplo_id'] ?></td>
            <td><?= h($d['diplo_title']) ?></td>
            <td><?= h($d['diplo_student'] ?? '-') ?></td>
            <td><span class="badge">pending</span></td>
            <td>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="diplo_id" value="<?= (int)$d['diplo_id'] ?>">
                <button class="btn danger" name="cancel_pending"
                  onclick="return confirm('Σίγουρα ακύρωση; Θα διαγραφούν προσκλήσεις τριμελούς.')">
                  Ακύρωση
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>
</body>
</html>
