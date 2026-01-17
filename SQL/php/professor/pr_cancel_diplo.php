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

// Λίστα: active διπλωματικές του επιβλέποντα
$list = [];
$res = $connection->query("
    SELECT diplo_id, diplo_title, diplo_student, diplo_status, assigned_at
    FROM diplo
    WHERE diplo_professor = '$prof_id'
      AND diplo_status = 'active'
    ORDER BY diplo_id DESC
");
if ($res) while ($r = $res->fetch_assoc()) $list[] = $r;

// Ακύρωση ενεργής διπλωματικής μετά από 2 έτη
if (isset($_POST['cancel_active'])) {

    $diplo_id = (int)$_POST['diplo_id'];
    $gs_number = trim($_POST['gs_number'] ?? "");
    $gs_year   = trim($_POST['gs_year'] ?? "");

    // basic validation
    if ($gs_number === "" || !ctype_digit($gs_number) || (int)$gs_number <= 0) {
        $message = "❌ Συμπλήρωσε σωστό αριθμό Γενικής Συνέλευσης.";
    } elseif ($gs_year === "" || !ctype_digit($gs_year) || (int)$gs_year < 1900 || (int)$gs_year > 2100) {
        $message = "❌ Συμπλήρωσε σωστό έτος Γενικής Συνέλευσης.";
    } else {

        // Έλεγχος: είναι δική του, active, και έχουν περάσει 2 έτη από assigned_at
        $stmt = $connection->prepare("
            SELECT diplo_id, assigned_at
            FROM diplo
            WHERE diplo_id = ?
              AND diplo_professor = ?
              AND diplo_status = 'active'
            LIMIT 1
        ");
        $stmt->bind_param("is", $diplo_id, $prof_id);
        $stmt->execute();
        $dip = $stmt->get_result()->fetch_assoc();

        if (!$dip) {
            $message = "❌ Δεν επιτρέπεται ακύρωση (δεν είναι active ή δεν είστε ο επιβλέπων).";
        } elseif (empty($dip['assigned_at'])) {
            $message = "❌ Δεν υπάρχει ημερομηνία οριστικής ανάθεσης (assigned_at). Δεν μπορεί να ελεγχθεί το 2ετές όριο.";
        } else {

            // Έλεγχος 2 ετών
            $assigned_at = strtotime($dip['assigned_at']);
            $two_years_ago = strtotime("-2 years");

            if ($assigned_at > $two_years_ago) {
                $message = "⚠ Δεν έχουν περάσει 2 έτη από την οριστική ανάθεση. Δεν επιτρέπεται ακύρωση.";
            } else {

                // Transaction: ενημέρωση diplo + διαγραφή προσκλήσεων
                $connection->begin_transaction();

                try {
                    // 1) Ακύρωση (λόγος: από Διδάσκοντα)
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
                    $upd->bind_param("iiis", $gsn, $gsy, $diplo_id, $prof_id);

                    if (!$upd->execute()) {
                        throw new Exception("Σφάλμα ενημέρωσης diplo: " . $connection->error);
                    }

                    // 2) Διαγραφή προσκλήσεων τριμελούς
                    $del = $connection->prepare("
                        DELETE FROM trimelous_invite
                        WHERE diplo_id = ?
                    ");
                    $del->bind_param("i", $diplo_id);

                    if (!$del->execute()) {
                        throw new Exception("Σφάλμα διαγραφής προσκλήσεων: " . $connection->error);
                    }

                    $connection->commit();
                    $message = "✅ Η διπλωματική ακυρώθηκε (λόγος: από Διδάσκοντα) και καταχωρήθηκαν τα στοιχεία ΓΣ.";

                } catch (Exception $e) {
                    $connection->rollback();
                    $message = "❌ Αποτυχία: " . $e->getMessage();
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
  <title>5) Ακύρωση Ενεργής Διπλωματικής</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-dark bg-dark p-2">
  <span class="navbar-brand ms-3">5) Ακύρωση Ενεργής Διπλωματικής (μετά από 2 έτη)</span>
  <a href="professor_page.php" class="btn btn-success ms-auto me-2">Αρχική</a>
  <a href="logout.php" class="btn btn-danger me-3">Αποσύνδεση</a>
</nav>

<div class="container mt-4">

  <?php if ($message): ?>
    <div class="alert alert-info text-center"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-header fw-bold">Ενεργές Διπλωματικές (status=active)</div>
    <div class="card-body table-responsive">

      <table class="table table-bordered table-hover align-middle">
        <thead class="table-dark">
          <tr>
            <th>ID</th>
            <th>Τίτλος</th>
            <th>Φοιτητής</th>
            <th>assigned_at</th>
            <th>GS Αριθμός</th>
            <th>GS Έτος</th>
            <th>Ενέργεια</th>
          </tr>
        </thead>
        <tbody>

        <?php if (empty($list)): ?>
          <tr><td colspan="7" class="text-center text-muted">Δεν υπάρχουν ενεργές διπλωματικές.</td></tr>
        <?php else: ?>
          <?php foreach ($list as $d): ?>
            <tr>
              <td><?= (int)$d['diplo_id'] ?></td>
              <td><?= htmlspecialchars($d['diplo_title']) ?></td>
              <td><?= htmlspecialchars($d['diplo_student'] ?? '-') ?></td>
              <td><?= htmlspecialchars($d['assigned_at'] ?? '—') ?></td>

              <form method="POST">
                <td style="min-width:140px;">
                  <input type="text" name="gs_number" class="form-control" placeholder="π.χ. 12" required>
                </td>
                <td style="min-width:140px;">
                  <input type="text" name="gs_year" class="form-control" placeholder="π.χ. 2024" required>
                </td>
                <td style="min-width:170px;">
                  <input type="hidden" name="diplo_id" value="<?= (int)$d['diplo_id'] ?>">
                  <button type="submit" name="cancel_active" class="btn btn-danger btn-sm w-100"
                          onclick="return confirm('Σίγουρα; Επιτρέπεται μόνο αν έχουν περάσει 2 έτη από assigned_at. Θα διαγραφούν και οι προσκλήσεις τριμελούς.')">
                    Ακύρωση
                  </button>
                </td>
              </form>

            </tr>
          <?php endforeach; ?>
        <?php endif; ?>

        </tbody>
      </table>

      <div class="alert alert-secondary mt-3">
        <strong>Κανόνας:</strong> Η ακύρωση επιτρέπεται μόνο αν έχουν περάσει 2 έτη από <code>assigned_at</code> (οριστική ανάθεση).
      </div>

    </div>
  </div>

</div>
</body>
</html>
