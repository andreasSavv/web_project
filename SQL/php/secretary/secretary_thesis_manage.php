<?php
session_start();
include("db_connect.php");
include("connected.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 1) Μόνο γραμματεία
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'secretary') {
    header("Location: login.php");
    exit;
}

// 2) Στοιχεία γραματειας
$secretary = Secretary_Connected($connection);
$secUserId = (int)($secretary['secretary_user_id'] ?? 0);

// ------------------ ΜΗΝΥΜΑΤΑ ------------------
$message = "";

// ------------------ SAVE GS ASSIGN AP ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_gs_assign'])) {
    $diploId = (int)($_POST['diplo_id'] ?? 0);
    $ap      = trim($_POST['gs_assign_ap'] ?? '');
    $year    = (int)($_POST['gs_assign_year'] ?? 0);

    if ($diploId <= 0 || $ap === '' || $year <= 0) {
        $message = "⚠ Συμπλήρωσε σωστά diplo_id, ΑΠ και έτος ΓΣ.";
    } else {

        // Επιτρέπεται μόνο αν η διπλωματική είναι active
        $chk = $connection->prepare("SELECT diplo_status FROM diplo WHERE diplo_id = ? LIMIT 1");
        $chk->bind_param("i", $diploId);
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (!$row) {
            $message = "❌ Δεν βρέθηκε διπλωματική.";
        } elseif (($row['diplo_status'] ?? '') !== 'active') {
            $message = "❌ Η καταχώρηση ΑΠ επιτρέπεται μόνο όταν η διπλωματική είναι active.";
        } else {
            // upsert στο diplo_gs
            $stmt = $connection->prepare("
                INSERT INTO diplo_gs (diplo_id, gs_assign_ap, gs_assign_year)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                  gs_assign_ap = VALUES(gs_assign_ap),
                  gs_assign_year = VALUES(gs_assign_year)
            ");
            $stmt->bind_param("isi", $diploId, $ap, $year);
            $stmt->execute();
            $stmt->close();

            $message = "✅ Καταχωρήθηκε ο ΑΠ/έτος ΓΣ ανάθεσης.";
        }
    }
}

// ------------------ CANCEL ASSIGNMENT ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_diplo'])) {
    $diploId = (int)($_POST['diplo_id'] ?? 0);
    $cNum    = (int)($_POST['gs_cancel_num'] ?? 0);
    $cYear   = (int)($_POST['gs_cancel_year'] ?? 0);
    $reason  = trim($_POST['gs_cancel_reason'] ?? '');

    if ($diploId <= 0 || $cNum <= 0 || $cYear <= 0 || $reason === '') {
        $message = "⚠ Συμπλήρωσε σωστά: αριθμό/έτος ΓΣ ακύρωσης και λόγο ακύρωσης.";
    } else {

        // Επιτρέπεται μόνο αν η διπλωματική είναι active
        $chk = $connection->prepare("SELECT diplo_status FROM diplo WHERE diplo_id = ? LIMIT 1");
        $chk->bind_param("i", $diploId);
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (!$row) {
            $message = "❌ Δεν βρέθηκε διπλωματική.";
        } elseif (($row['diplo_status'] ?? '') !== 'active') {
            $message = "❌ Ακύρωση επιτρέπεται μόνο όταν η διπλωματική είναι active.";
        } else {

            $connection->begin_transaction();
            try {
                // 1) Αλλαγή status σε cancelled (ή cancel αν έτσι το έχεις)
                $upd = $connection->prepare("UPDATE diplo SET diplo_status = 'cancelled' WHERE diplo_id = ?");
                $upd->bind_param("i", $diploId);
                $upd->execute();
                $upd->close();

                // 2) Timeline diplo_date
                $insTL = $connection->prepare("
                    INSERT INTO diplo_date (diplo_id, diplo_date, diplo_status)
                    VALUES (?, NOW(), 'cancelled')
                ");
                $insTL->bind_param("i", $diploId);
                $insTL->execute();
                $insTL->close();

                // 3) Καταχώρηση GS ακύρωσης + reason
                $stmt = $connection->prepare("
                    INSERT INTO diplo_gs (diplo_id, gs_cancel_num, gs_cancel_year, gs_cancel_reason, gs_cancel_date)
                    VALUES (?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                      gs_cancel_num = VALUES(gs_cancel_num),
                      gs_cancel_year = VALUES(gs_cancel_year),
                      gs_cancel_reason = VALUES(gs_cancel_reason),
                      gs_cancel_date = VALUES(gs_cancel_date)
                ");
                $stmt->bind_param("iiis", $diploId, $cNum, $cYear, $reason);
                $stmt->execute();
                $stmt->close();

                // 4) (ΠΡΟΑΙΡΕΤΙΚΟ αλλά σύμφωνο με όσα έκανες πριν):
                // διαγραφή προσκλήσεων/τριμελούς για να "καθαρίσει" η ΔΕ
                $delInv = $connection->prepare("DELETE FROM trimelous_invite WHERE diplo_id = ?");
                $delInv->bind_param("i", $diploId);
                $delInv->execute();
                $delInv->close();

                $delTri = $connection->prepare("DELETE FROM trimelous WHERE diplo_id = ?");
                $delTri->bind_param("i", $diploId);
                $delTri->execute();
                $delTri->close();

                $connection->commit();
                $message = "✅ Η ανάθεση ακυρώθηκε και καταχωρήθηκε η απόφαση ΓΣ + λόγος ακύρωσης.";

            } catch (Exception $e) {
                $connection->rollback();
                $message = "❌ Σφάλμα ακύρωσης: " . $e->getMessage();
            }
        }
    }
}

// ------------------ LIST ACTIVE DIPLOS ------------------
// active + δείχνουμε και τα gs στοιχεία αν υπάρχουν
$list = [];
$sqlList = "
SELECT
  d.diplo_id,
  d.diplo_title,
  d.diplo_student,
  d.diplo_status,
  gs.gs_assign_ap,
  gs.gs_assign_year,
  gs.gs_cancel_num,
  gs.gs_cancel_year,
  gs.gs_cancel_reason
FROM diplo d
LEFT JOIN diplo_gs gs ON gs.diplo_id = d.diplo_id
WHERE d.diplo_status = 'active'
ORDER BY d.diplo_id DESC
";
$res = $connection->query($sqlList);
while ($r = $res->fetch_assoc()) $list[] = $r;

$listUR = [];

$sqlUR = "
SELECT
  d.diplo_id,
  d.diplo_title,
  d.diplo_student,
  d.diplo_status,
  d.diplo_grade,
  d.nimertis_link
FROM diplo d
WHERE d.diplo_status IN ('under review','under_review')
ORDER BY d.diplo_id DESC
";
$resUR = $connection->query($sqlUR);
while ($r = $resUR->fetch_assoc()) $listUR[] = $r;



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_finished'])) {

    $diploId = (int)($_POST['diplo_id'] ?? 0);
    if ($diploId <= 0) {
        $message = "⚠ Μη έγκυρο diplo_id.";
        header("Location: secretary_thesis_manage.php?msg=" . urlencode($message));
        exit;
    }

    // Παίρνουμε grade + nimertis_link από τον diplo (ΟΧΙ από diplo_library)
    $chk = $connection->prepare("
        SELECT diplo_status, diplo_grade, nimertis_link
        FROM diplo
        WHERE diplo_id = ?
        LIMIT 1
    ");
    $chk->bind_param("i", $diploId);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$row) {
        $message = "❌ Δεν βρέθηκε διπλωματική.";
        header("Location: secretary_thesis_manage.php?msg=" . urlencode($message));
        exit;
    }

    $status = $row['diplo_status'] ?? '';
    $grade  = $row['diplo_grade'];
    $nimertis = trim((string)($row['nimertis_link'] ?? ''));

    if (!in_array($status, ['under review','under_review'], true)) {
        $message = "❌ Επιτρέπεται μόνο όταν η ΔΕ είναι Υπό Εξέταση.";
        header("Location: secretary_thesis_manage.php?msg=" . urlencode($message));
        exit;
    }

    if ($grade === null || $grade === '') {
        $message = "⚠ Δεν μπορεί να περατωθεί: λείπει βαθμός.";
        header("Location: secretary_thesis_manage.php?msg=" . urlencode($message));
        exit;
    }

    if ($nimertis === '') {
        $message = "⚠ Δεν μπορεί να περατωθεί: λείπει σύνδεσμος Νημερτή.";
        header("Location: secretary_thesis_manage.php?msg=" . urlencode($message));
        exit;
    }

    $connection->begin_transaction();
    try {
        // 1) finished
        $upd = $connection->prepare("UPDATE diplo SET diplo_status = 'finished' WHERE diplo_id = ?");
        $upd->bind_param("i", $diploId);
        $upd->execute();
        $upd->close();

        // 2) timeline
        $ins = $connection->prepare("
            INSERT INTO diplo_date (diplo_id, diplo_date, diplo_status)
            VALUES (?, NOW(), 'finished')
        ");
        $ins->bind_param("i", $diploId);
        $ins->execute();
        $ins->close();

        $connection->commit();
        $message = "✅ Η διπλωματική περατώθηκε επιτυχώς.";

    } catch (Exception $e) {
        $connection->rollback();
        $message = "❌ Σφάλμα περάτωσης: " . $e->getMessage();
    }

    header("Location: secretary_thesis_manage.php?msg=" . urlencode($message));
    exit;
}


?>



<!------------------ HTML ------------------>

<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <title>Γραμματεία - Διαχείριση ΔΕ</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark p-2">
  <span class="navbar-brand ms-3">Γραμματεία - Διαχείριση ΔΕ (Active)</span>
  <div class="ms-auto d-flex gap-2 me-3">
    <a href="secretary_page.php" class="btn btn-success">Αρχική</a>
    <a href="logout.php" class="btn btn-danger">Αποσύνδεση</a>
  </div>
</nav>

<div class="container mt-4">

  <?php if (!empty($message)): ?>
    <div class="alert alert-info text-center"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-header fw-bold">
      Ενεργές Διπλωματικές (Active)
    </div>
    <div class="card-body">

      <?php if (empty($list)): ?>
        <div class="alert alert-warning text-center m-0">
          Δεν υπάρχουν ενεργές διπλωματικές.
        </div>
      <?php else: ?>

        <div class="table-responsive">
          <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
              <tr>
                <th>ID</th>
                <th>Θέμα</th>
                <th>Φοιτητής (AM)</th>
                <th>Κατάσταση</th>
                <th style="min-width:300px;">ΑΠ ΓΣ Ανάθεσης</th>
                <th style="min-width:360px;">Ακύρωση Ανάθεσης (ΓΣ + λόγος)</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($list as $d): ?>
              <tr>
                <td><?= (int)$d['diplo_id'] ?></td>
                <td><?= htmlspecialchars($d['diplo_title'] ?? '') ?></td>
                <td><?= htmlspecialchars($d['diplo_student'] ?? '-') ?></td>
                <td><span class="badge bg-success">active</span></td>

                <!-- GS Assign -->
                <td>
                  <form method="POST" class="row g-2">
                    <input type="hidden" name="diplo_id" value="<?= (int)$d['diplo_id'] ?>">
                    <div class="col-12">
                      <input class="form-control" type="text" name="gs_assign_ap"
                             placeholder="ΑΠ (π.χ. 123)"
                             value="<?= htmlspecialchars($d['gs_assign_ap'] ?? '') ?>" required>
                    </div>
                    <div class="col-12">
                      <input class="form-control" type="number" name="gs_assign_year"
                             placeholder="Έτος (π.χ. 2026)"
                             value="<?= htmlspecialchars($d['gs_assign_year'] ?? '') ?>" required>
                    </div>
                    <div class="col-12">
                      <button class="btn btn-primary btn-sm w-100" name="save_gs_assign">
                        Αποθήκευση ΑΠ/Έτους
                      </button>
                    </div>
                  </form>
                </td>

                <!-- Cancel -->
                <td>
                  <form method="POST" class="row g-2" onsubmit="return confirm('Σίγουρα ακύρωση ανάθεσης; Θα γίνει cancelled.');">
                    <input type="hidden" name="diplo_id" value="<?= (int)$d['diplo_id'] ?>">

                    <div class="col-6">
                      <input class="form-control" type="number" name="gs_cancel_num"
                             placeholder="Αριθμός ΓΣ"
                             value="" required>
                    </div>
                    <div class="col-6">
                      <input class="form-control" type="number" name="gs_cancel_year"
                             placeholder="Έτος ΓΣ"
                             value="" required>
                    </div>

                    <div class="col-12">
                      <input class="form-control" type="text" name="gs_cancel_reason"
                             placeholder="Λόγος (π.χ. κατόπιν αίτησης φοιτητή)"
                             maxlength="255" required>
                    </div>

                    <div class="col-12">
                      <button class="btn btn-danger btn-sm w-100" name="cancel_diplo">
                        Ακύρωση ανάθεσης
                      </button>
                    </div>
                  </form>
                </td>

              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      <?php endif; ?>

      <h3 class="mt-4">Διπλωματικές Υπό Εξέταση</h3>

<?php if (empty($listUR)): ?>
  <div class="alert alert-warning">Δεν υπάρχουν διπλωματικές Υπό Εξέταση.</div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-bordered table-striped align-middle">
      <thead class="table-dark">
        <tr>
          <th>ID</th>
          <th>Θέμα</th>
          <th>Φοιτητής</th>
          <th>Βαθμός</th>
          <th>Νημερτής link</th>
          <th>Ενέργεια</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($listUR as $d): ?>
        <?php
          $hasGrade = ($d['diplo_grade'] !== null && $d['diplo_grade'] !== '');
          $hasLink  = (trim((string)($d['nimertis_link'] ?? '')) !== '');
          $canFinish = $hasGrade && $hasLink;
        ?>
        <tr>
          <td><?= (int)$d['diplo_id'] ?></td>
          <td><?= htmlspecialchars($d['diplo_title'] ?? '') ?></td>
          <td><?= htmlspecialchars($d['diplo_student'] ?? '-') ?></td>
          <td><?= htmlspecialchars($d['diplo_grade'] ?? '-') ?></td>
          <td>
            <?php if ($hasLink): ?>
              <a href="<?= htmlspecialchars($d['nimertis_link']) ?>" target="_blank">Άνοιγμα</a>
            <?php else: ?>
              <span class="text-muted">-</span>
            <?php endif; ?>
          </td>
          <td style="min-width:220px;">
            <?php if ($canFinish): ?>
              <form method="POST" onsubmit="return confirm('Να γίνει περάτωση της διπλωματικής;');">
                <input type="hidden" name="diplo_id" value="<?= (int)$d['diplo_id'] ?>">
                <button type="submit" name="mark_finished" class="btn btn-success btn-sm w-100">
                  Περάτωση (Finished)
                </button>
              </form>
            <?php else: ?>
              <button class="btn btn-secondary btn-sm w-100" disabled>
                Δεν γίνεται περάτωση
              </button>
              <div class="small text-muted mt-1">
                <?php if (!$hasGrade) echo "• Λείπει βαθμός<br>"; ?>
                <?php if (!$hasLink)  echo "• Λείπει Νημερτής link"; ?>
              </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>


    </div>
  </div>

</div>
</body>
</html>