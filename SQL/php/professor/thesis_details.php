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

$prof = Professor_Connected($connection);
$profUserId = (int)($prof['professor_user_id'] ?? 0);
if ($profUserId <= 0) {
    die("Δεν βρέθηκαν στοιχεία καθηγητή.");
}

$diploId = isset($_GET['diplo_id']) ? (int)$_GET['diplo_id'] : 0;
if ($diploId <= 0) {
    die("Λάθος diplo_id.");
}

// ---------- Φέρνουμε διπλωματική + student + trimelous + ονόματα τριμελούς ----------
$sql = "
SELECT
  d.diplo_id,
  d.diplo_title,
  d.diplo_desc,
  d.diplo_pdf,
  d.diplo_status,
  d.diplo_grade,
  d.diplo_student,
  d.diplo_professor,

  s.student_am,
  s.student_name,
  s.student_surname,

  t.trimelous_professor1,
  t.trimelous_professor2,
  t.trimelous_professor3,

  p1.professor_name  AS p1_name, p1.professor_surname AS p1_surname,
  p2.professor_name  AS p2_name, p2.professor_surname AS p2_surname,
  p3.professor_name  AS p3_name, p3.professor_surname AS p3_surname

FROM diplo d
LEFT JOIN student s ON s.student_am = d.diplo_student
LEFT JOIN trimelous t ON t.diplo_id = d.diplo_id

LEFT JOIN professor p1 ON p1.professor_user_id = t.trimelous_professor1
LEFT JOIN professor p2 ON p2.professor_user_id = t.trimelous_professor2
LEFT JOIN professor p3 ON p3.professor_user_id = t.trimelous_professor3

WHERE d.diplo_id = ?
LIMIT 1
";
$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $diploId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    die("Δεν βρέθηκε διπλωματική.");
}

// ---------- Έλεγχος πρόσβασης: supervisor ή μέλος τριμελούς ----------
$isSupervisor = ((int)$row['diplo_professor'] === $profUserId);

$isMember = (
    (int)($row['trimelous_professor1'] ?? 0) === $profUserId ||
    (int)($row['trimelous_professor2'] ?? 0) === $profUserId ||
    (int)($row['trimelous_professor3'] ?? 0) === $profUserId
);

if (!$isSupervisor && !$isMember) {
    die("Δεν έχετε δικαίωμα προβολής αυτής της διπλωματικής.");
}

function profFull($n, $s) {
    $n = trim((string)$n);
    $s = trim((string)$s);
    $full = trim($s . " " . $n);
    return $full !== "" ? $full : "-";
}

// -------- Timeline αλλαγών κατάστασης (diplo_date) --------
$timeline = [];
$sqlTL = "SELECT diplo_date, diplo_status
          FROM diplo_date
          WHERE diplo_id = ?
          ORDER BY diplo_date ASC";
$stmtTL = $connection->prepare($sqlTL);
$stmtTL->bind_param("i", $diploId);
$stmtTL->execute();
$resTL = $stmtTL->get_result();
while ($r = $resTL->fetch_assoc()) {
    $timeline[] = $r;
}
$stmtTL->close();


// -------------------- Pending actions (invites + cancel assignment) --------------------
$pendingInvites = [];

// Αν ο επιβλέπων ακυρώσει ανάθεση
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_assignment'])) {

    // Μόνο επιβλέπων και μόνο αν είναι pending
    if (!$isSupervisor || ($row['diplo_status'] ?? '') !== 'pending') {
        die("Δεν έχετε δικαίωμα για αυτή την ενέργεια.");
    }

    $connection->begin_transaction();
    try {
        // 1) Αναιρούμε τον φοιτητή από τη διπλωματική
        $stmt1 = $connection->prepare("UPDATE diplo SET diplo_student = NULL, diplo_status = 'pending' WHERE diplo_id = ?");
        $stmt1->bind_param("i", $diploId);
        $stmt1->execute();
        $stmt1->close();

        // 2) Διαγράφουμε όλες τις προσκλήσεις τριμελούς για αυτή τη διπλωματική
        $stmt2 = $connection->prepare("DELETE FROM trimelous_invite WHERE diplo_id = ?");
        $stmt2->bind_param("i", $diploId);
        $stmt2->execute();
        $stmt2->close();

        // 3) Διαγράφουμε την τριμελή (αν υπάρχει γραμμή)
        $stmt3 = $connection->prepare("DELETE FROM trimelous WHERE diplo_id = ?");
        $stmt3->bind_param("i", $diploId);
        $stmt3->execute();
        $stmt3->close();

        $connection->commit();

        header("Location: thesis_details.php?diplo_id=" . $diploId);
        exit;

    } catch (Exception $e) {
        $connection->rollback();
        die("Σφάλμα ακύρωσης: " . $e->getMessage());
    }
}

// Αν είναι pending, φέρνουμε invites για προβολή
if (($row['diplo_status'] ?? '') === 'pending') {
    $sqlInv = "
        SELECT
            ti.diplo_id,
            ti.diplo_student_am,
            ti.professor_user_id,
            ti.trimelous_date,
            ti.invite_status,
            ti.invite_accept_date,
            ti.invite_deny_date,
            p.professor_name,
            p.professor_surname
        FROM trimelous_invite ti
        JOIN professor p ON p.professor_user_id = ti.professor_user_id
        WHERE ti.diplo_id = ?
        ORDER BY ti.trimelous_date ASC
    ";
    $stmtInv = $connection->prepare($sqlInv);
    $stmtInv->bind_param("i", $diploId);
    $stmtInv->execute();
    $resInv = $stmtInv->get_result();
    while ($r = $resInv->fetch_assoc()) {
        $pendingInvites[] = $r;
    }
    $stmtInv->close();
}

$studentFull = "-";
if (!empty($row['student_am'])) {
    $studentFull = $row['student_surname'] . " " . $row['student_name'] . " (ΑΜ: " . $row['student_am'] . ")";
}

// ---------- Supervisor action: set to "under review" ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_under_review'])) {

    if (!$isSupervisor) {
        die("Δεν έχετε δικαίωμα για αυτή την ενέργεια.");
    }

    if (($row['diplo_status'] ?? '') !== 'active') {
        die("Η ενέργεια επιτρέπεται μόνο όταν η διπλωματική είναι σε κατάσταση 'active'.");
    }

    $connection->begin_transaction();
    try {
        // Update status
        $stmt1 = $connection->prepare("UPDATE diplo SET diplo_status = 'under_review' WHERE diplo_id = ?");
        $stmt1->bind_param("i", $diploId);
        $stmt1->execute();
        $stmt1->close();

        // Add to timeline
        $stmt2 = $connection->prepare("INSERT INTO diplo_date (diplo_id, diplo_date, diplo_status) VALUES (?, NOW(), 'under_review')");
        $stmt2->bind_param("i", $diploId);
        $stmt2->execute();
        $stmt2->close();

        $connection->commit();

        header("Location: thesis_details.php?diplo_id=" . $diploId);
        exit;

    } catch (Exception $e) {
        $connection->rollback();
        die("Σφάλμα αλλαγής κατάστασης: " . $e->getMessage());
    }
}
?>






<!----------------------------------- ΗΤΜΛ -------------- -->
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <title>Λεπτομέρειες Διπλωματικής</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>


<div class="container mt-4" style="max-width: 1000px;">
  <a class="btn btn-secondary mb-3" href="diplomas.php">⟵ Πίσω στη λίστα</a>

  <div class="card shadow-sm">
    <div class="card-header fw-bold">
      Διπλωματική #<?= (int)$row['diplo_id'] ?> — <?= htmlspecialchars($row['diplo_title'] ?? '') ?>
    </div>

    <div class="card-body">
      <p><strong>Κατάσταση:</strong> <?= htmlspecialchars($row['diplo_status'] ?? '-') ?></p>
      <p><strong>Ρόλος μου:</strong> <?= $isSupervisor ? 'Επιβλέπων' : 'Μέλος τριμελούς' ?></p>
      <p><strong>Φοιτητής:</strong> <?= htmlspecialchars($studentFull) ?></p>

      <hr>

      <p><strong>Περιγραφή:</strong><br>
        <?= nl2br(htmlspecialchars($row['diplo_desc'] ?? '')) ?>
      </p>

      <p><strong>PDF:</strong>
        <?php if (!empty($row['diplo_pdf'])): ?>
          <a href="<?= htmlspecialchars($row['diplo_pdf']) ?>" target="_blank">Άνοιγμα PDF</a>
        <?php else: ?>
          <span class="text-muted">-</span>
        <?php endif; ?>
      </p>

      <hr>

      <h5 class="fw-bold">Τριμελής</h5>
      <ul>
        <li>Professor 1 (Επιβλέπων): <?= htmlspecialchars(profFull($row['p1_name'], $row['p1_surname'])) ?></li>
        <li>Professor 2: <?= htmlspecialchars(profFull($row['p2_name'], $row['p2_surname'])) ?></li>
        <li>Professor 3: <?= htmlspecialchars(profFull($row['p3_name'], $row['p3_surname'])) ?></li>
      </ul>
     
      <hr>
      <h6 class="fw-bold">Σημειώσεις</h6>
      <p>Εδώ μπορείτε να προσθέσετε σημειώσεις για την Διπλωματική Εργασία<br>
      <a class="btn btn-outline-primary" href="prof_show_notes.php?diplo_id=<?= (int)$diploId ?>">
        Σημειώσεις
        </a>
        </p>
        
    <hr>
    <?php if ($isSupervisor && ($row['diplo_status'] ?? '') === 'active'): ?>
    <div class="alert alert-info mt-3">
        <div class="fw-bold mb-2">Ενέργειες επιβλέποντα</div>
        <p class="mb-2">
            Όταν είστε έτοιμοι, μπορείτε να αλλάξετε την κατάσταση σε <strong>Υπό Εξέταση</strong>
            ώστε ο φοιτητής να προχωρήσει στις ενέργειες της εξέτασης.
        </p>
        <form method="POST" onsubmit="return confirm('Θέλετε σίγουρα να αλλάξετε την κατάσταση σε Υπό Εξέταση;');">
            <button type="submit" name="set_under_review" class="btn btn-warning">
                Μετάβαση σε «Υπό Εξέταση»
            </button>
        </form>
    </div>
<?php endif; ?>



      <!-- =================== Υπό Ανάθεση actions =================== -->
      <?php if (($row['diplo_status'] ?? '') === 'pending'): ?>
        <hr>
        <h5 class="fw-bold">Υπό Ανάθεση — Προσκλήσεις τριμελούς</h5>

        <?php if (empty($pendingInvites)): ?>
            <p class="text-muted">Δεν υπάρχουν προσκλήσεις για αυτή τη διπλωματική.</p>
        <?php else: ?>
            <table class="table table-sm table-bordered mt-2">
                <thead class="table-light">
                    <tr>
                        <th>Διδάσκων</th>
                        <th>Κατάσταση</th>
                        <th>Ημ/νία Πρόσκλησης</th>
                        <th>Ημ/νία Αποδοχής</th>
                        <th>Ημ/νία Απόρριψης</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pendingInvites as $inv): ?>
                    <tr>
                        <td><?= htmlspecialchars(($inv['professor_surname'] ?? '') . " " . ($inv['professor_name'] ?? '')) ?></td>
                        <td><?= htmlspecialchars($inv['invite_status'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($inv['trimelous_date'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($inv['invite_accept_date'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($inv['invite_deny_date'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($isSupervisor && !empty($row['diplo_student'])): ?>
            <div class="alert alert-warning mt-3">
                <div class="fw-bold mb-2">Ενέργεια επιβλέποντα</div>
                <p class="mb-2">
                    Μπορείτε να ακυρώσετε την ανάθεση θέματος στον φοιτητή.
                    Θα διαγραφούν όλες οι προσκλήσεις και η τριμελής.
                </p>

                <form method="POST" onsubmit="return confirm('Σίγουρα θέλετε να ακυρώσετε την ανάθεση; Θα διαγραφούν οι προσκλήσεις.');">
                    <button type="submit" name="cancel_assignment" class="btn btn-danger">
                        Ακύρωση ανάθεσης
                    </button>
                </form>
            </div>
        <?php endif; ?>
      <?php endif; ?>
      <!-- =========================================================== -->

      <hr>

      <h5 class="fw-bold">Χρονολόγιο ενεργειών (αλλαγές κατάστασης)</h5>
      <?php if (empty($timeline)): ?>
          <p class="text-muted">Δεν υπάρχουν καταχωρημένες αλλαγές κατάστασης.</p>
      <?php else: ?>
          <?php $lastIndex = count($timeline) - 1; ?>
          <table class="table table-sm table-bordered mt-2">
              <thead class="table-light">
                  <tr>
                      <th style="width: 220px;">Ημερομηνία</th>
                      <th>Κατάσταση</th>
                  </tr>
              </thead>
              <tbody>
              <?php foreach ($timeline as $i => $t): ?>
                  <tr>
                      <td><?= htmlspecialchars($t['diplo_date']) ?></td>
                      <td>
                          <?= htmlspecialchars($t['diplo_status']) ?>
                          <?php if ($i === $lastIndex): ?>
                              <span class="badge bg-success ms-2">τρέχουσα</span>
                          <?php endif; ?>
                      </td>
                  </tr>
              <?php endforeach; ?>
              </tbody>
          </table>
      <?php endif; ?>

      <hr>

      <p><strong>Τελικός βαθμός:</strong> <?= htmlspecialchars($row['diplo_grade'] ?? '-') ?></p>

    </div>
  </div>
</div>

</body>
</html>
