<?php
session_start();
include("db_connect.php");
include("connected.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

$user = Professor_Connected($connection);
$prof_id = $user['professor_user_id'] ?? $user['professor_id'] ?? null;
if (!$prof_id) die("Δεν βρέθηκε professor id.");

$message = "";

// Φέρνουμε ΜΟΝΟ τις pending του επιβλέποντα (που έχουν/δεν έχουν φοιτητή)
$list = [];
$res = $connection->query("
    SELECT diplo_id, diplo_title, diplo_student, diplo_status
    FROM diplo
    WHERE diplo_professor = '$prof_id'
      AND diplo_status = 'pending'
    ORDER BY diplo_id DESC
");
if ($res) while ($r = $res->fetch_assoc()) $list[] = $r;

// Ακύρωση pending
if (isset($_POST['cancel_pending'])) {
    $diplo_id = (int)$_POST['diplo_id'];

    // Έλεγχος ότι είναι δική του και pending
    $chk = $connection->query("
        SELECT diplo_id
        FROM diplo
        WHERE diplo_id = '$diplo_id'
          AND diplo_professor = '$prof_id'
          AND diplo_status = 'pending'
        LIMIT 1
    ");

    if (!$chk || $chk->num_rows === 0) {
        $message = "❌ Δεν επιτρέπεται ακύρωση (δεν είναι pending ή δεν είστε ο επιβλέπων).";
    } else {

        $connection->begin_transaction();
        try {
            // 1) Ακύρωση διπλωματικής
            $ok1 = $connection->query("
                UPDATE diplo
                SET diplo_status = 'cancelled',
                    diplo_student = NULL,
                    diplo_trimelis = NULL
                WHERE diplo_id = '$diplo_id'
                  AND diplo_professor = '$prof_id'
            ");
            if (!$ok1) throw new Exception($connection->error);

            // 2) Διαγραφή προσκλήσεων τριμελούς
            $ok2 = $connection->query("
                DELETE FROM trimelous_invite
                WHERE diplo_id = '$diplo_id'
            ");
            if (!$ok2) throw new Exception($connection->error);

            $connection->commit();
            $message = "✅ Η pending διπλωματική ακυρώθηκε και οι προσκλήσεις τριμελούς διαγράφηκαν.";

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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-dark bg-dark p-2">
  <span class="navbar-brand ms-3">6) Ακύρωση "Υπό Ανάθεση" (pending)</span>
  <a href="professor_page.php" class="btn btn-success ms-auto me-2">Αρχική</a>
  <a href="logout.php" class="btn btn-danger me-3">Αποσύνδεση</a>
</nav>

<div class="container mt-4">

  <?php if ($message): ?>
    <div class="alert alert-info text-center"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-header fw-bold">Pending Διπλωματικές (status = pending)</div>
    <div class="card-body table-responsive">
      <table class="table table-bordered table-hover align-middle">
        <thead class="table-dark">
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
          <tr><td colspan="5" class="text-center text-muted">Δεν υπάρχουν pending διπλωματικές.</td></tr>
        <?php else: ?>
          <?php foreach ($list as $d): ?>
            <tr>
              <td><?= (int)$d['diplo_id'] ?></td>
              <td><?= htmlspecialchars($d['diplo_title']) ?></td>
              <td><?= htmlspecialchars($d['diplo_student'] ?? '-') ?></td>
              <td><span class="badge bg-warning text-dark">pending</span></td>
              <td>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="diplo_id" value="<?= (int)$d['diplo_id'] ?>">
                  <button type="submit" name="cancel_pending" class="btn btn-danger btn-sm"
                          onclick="return confirm('Σίγουρα θέλεις να ακυρώσεις την PENDING διπλωματική; Θα διαγραφούν προσκλήσεις τριμελούς.')">
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

</div>
</body>
</html>
