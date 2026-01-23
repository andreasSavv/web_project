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

// Λίστα ενεργών διπλωματικών του επιβλέποντα
$list = [];
$stmt = $connection->prepare("
    SELECT diplo_id, diplo_title, diplo_student, diplo_status, assigned_at
    FROM diplo
    WHERE diplo_professor = ?
      AND diplo_status = 'active'
    ORDER BY diplo_id DESC
");
$stmt->bind_param("i", $prof_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $list[] = $r;
$stmt->close();

// Ακύρωση ενεργής
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_active'])) {

    $diplo_id  = (int)($_POST['diplo_id'] ?? 0);
    $gs_number = trim($_POST['gs_number'] ?? "");
    $gs_year   = trim($_POST['gs_year'] ?? "");

    if ($gs_number === "" || !ctype_digit($gs_number) || (int)$gs_number <= 0) {
        $message = "❌ Συμπλήρωσε σωστό αριθμό Γενικής Συνέλευσης.";
    } elseif ($gs_year === "" || !ctype_digit($gs_year) || (int)$gs_year < 1900 || (int)$gs_year > 2100) {
        $message = "❌ Συμπλήρωσε σωστό έτος Γενικής Συνέλευσης.";
    } else {

        $stmt = $connection->prepare("
            SELECT diplo_id, assigned_at
            FROM diplo
            WHERE diplo_id = ?
              AND diplo_professor = ?
              AND diplo_status = 'active'
            LIMIT 1
        ");
        $stmt->bind_param("ii", $diplo_id, $prof_id);
        $stmt->execute();
        $dip = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$dip) {
            $message = "❌ Δεν επιτρέπεται ακύρωση (δεν είναι active ή δεν είστε ο επιβλέπων).";
        } elseif (empty($dip['assigned_at'])) {
            $message = "❌ Δεν υπάρχει ημερομηνία οριστικής ανάθεσης (assigned_at).";
        } else {
            $assigned_at = strtotime($dip['assigned_at']);
            $two_years_ago = strtotime("-2 years");

            if ($assigned_at > $two_years_ago) {
                $message = "⚠ Δεν έχουν περάσει 2 έτη από την οριστική ανάθεση. Δεν επιτρέπεται ακύρωση.";
            } else {

                $connection->begin_transaction();
                try {
                    // 1) Ακύρωση στο diplo
                    $upd = $connection->prepare("
                        UPDATE diplo
                        SET diplo_status = 'cancelled',
                            cancel_reason = 'από Διδάσκοντα',
                            cancel_gs_number = ?,
                            cancel_gs_year = ?,
                            cancelled_at = NOW(),
                            diplo_student = NULL,
                            diplo_trimelis = NULL
                        WHERE diplo_id = ?
                          AND diplo_professor = ?
                          AND diplo_status = 'active'
                    ");
                    $gsn = (int)$gs_number;
                    $gsy = (int)$gs_year;
                    $upd->bind_param("iiii", $gsn, $gsy, $diplo_id, $prof_id);
                    $upd->execute();
                    $upd->close();

                    // 2) Διαγραφή προσκλήσεων τριμελούς
                    $del = $connection->prepare("DELETE FROM trimelous_invite WHERE diplo_id = ?");
                    $del->bind_param("i", $diplo_id);
                    $del->execute();
                    $del->close();

                    $connection->commit();
                    $message = "✅ Η διπλωματική ακυρώθηκε και καταχωρήθηκαν τα στοιχεία ΓΣ.";

                } catch (Exception $e) {
                    $connection->rollback();
                    $message = "❌ Σφάλμα: " . $e->getMessage();
                }
            }
        }
    }

    header("Location: pr_cancel_diplo.php?msg=" . urlencode($message));
    exit;
}

if (isset($_GET['msg'])) $message = $_GET['msg'];
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>Ακύρωση Ενεργής Διπλωματικής</title>
<style>
  body { font-family: Arial, sans-serif; background:#eef6ff; margin:0; padding:0; }
  .container { max-width:1200px; margin:40px auto; background:#fff; padding:20px 30px; border-radius:10px; box-shadow:0 0 10px rgba(0,0,0,0.1); }
  .top-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; gap:12px; flex-wrap:wrap; }
  .subtitle { color:#555; font-size:.95rem; }
  .btn { padding:8px 12px; border-radius:6px; text-decoration:none; color:#fff; font-size:.9rem; border:none; cursor:pointer; display:inline-block; }
  .home { background:#198754; } .home:hover{ background:#157347; }
  .logout { background:#dc3545; } .logout:hover{ background:#b52a37; }
  .danger { background:#dc3545; } .danger:hover{ background:#b52a37; }
  .card { background:#f8fbff; border:1px solid #dde7f5; border-radius:8px; padding:15px 20px; }
  .alert { padding:10px 12px; border-radius:6px; margin-bottom:15px; background:#e8f2ff; border:1px solid #b6d4fe; color:#084298; text-align:center; }
  .alert-warn { background:#fff3cd; border:1px solid #ffecb5; color:#664d03; text-align:left; }
  table { width:100%; border-collapse:collapse; margin-top:10px; }
  th, td { border:1px solid #dde7f5; padding:10px; vertical-align:middle; }
  th { background:#007bff; color:#fff; }
  tr:nth-child(even){ background:#fff; } tr:nth-child(odd){ background:#f8fbff; }
  input { width:100%; padding:8px; border:1px solid #cfe0f4; border-radius:6px; box-sizing:border-box; }
  input:focus { outline:none; border-color:#0d6efd; box-shadow:0 0 0 2px rgba(13,110,253,0.15); }
  .center { text-align:center; }
</style>
</head>
<body>
<div class="container">

  <div class="top-bar">
    <div>
      <h1>❌ Ακύρωση Ενεργής Διπλωματικής</h1>
      <div class="subtitle">Επιτρέπεται μόνο μετά από 2 έτη από την οριστική ανάθεση</div>
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
    <h3>Ενεργές Διπλωματικές</h3>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Τίτλος</th>
          <th>Φοιτητής</th>
          <th>assigned_at</th>
          <th>Αρ. ΓΣ</th>
          <th>Έτος ΓΣ</th>
          <th>Ενέργεια</th>
        </tr>
      </thead>
      <tbody>

      <?php if (empty($list)): ?>
        <tr><td colspan="7" class="center">Δεν υπάρχουν ενεργές διπλωματικές.</td></tr>
      <?php else: ?>
        <?php foreach ($list as $d): ?>
          <tr>
            <td><?= (int)$d['diplo_id'] ?></td>
            <td><?= h($d['diplo_title']) ?></td>
            <td><?= h($d['diplo_student'] ?? '-') ?></td>
            <td><?= h($d['assigned_at'] ?? '—') ?></td>

            <form method="POST">
              <td><input name="gs_number" placeholder="π.χ. 12" required></td>
              <td><input name="gs_year" placeholder="π.χ. 2024" required></td>
              <td>
                <input type="hidden" name="diplo_id" value="<?= (int)$d['diplo_id'] ?>">
                <button class="btn danger" name="cancel_active"
                        onclick="return confirm('Σίγουρα; Επιτρέπεται μόνο μετά από 2 έτη. Θα διαγραφούν και οι προσκλήσεις τριμελούς.');">
                  Ακύρωση
                </button>
              </td>
            </form>

          </tr>
        <?php endforeach; ?>
      <?php endif; ?>

      </tbody>
    </table>

    <div class="alert alert-warn" style="margin-top:15px;">
      <strong>Κανόνας:</strong> Η ακύρωση επιτρέπεται μόνο αν έχουν περάσει 2 έτη από <code>assigned_at</code>.
    </div>
  </div>

</div>
</body>
</html>
