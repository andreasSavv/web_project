<?php
session_start();
include("db_connect.php");
include("connected.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Μόνο professor
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

$user = Professor_Connected($connection);
$profUserId = (int)($user['professor_user_id'] ?? $user['professor_id'] ?? 0);
if ($profUserId <= 0) die("Δεν βρέθηκε professor id.");

$message = "";
$announcementText = "";

// Helper: έλεγχος πληρότητας παρουσίασης
function presentation_complete($row) {
    if (!$row) return false;

    if (empty($row['presentation_date']) || empty($row['presentation_time']) || empty($row['presentation_way'])) {
        return false;
    }

    if ($row['presentation_way'] === 'online') {
        return !empty(trim($row['presentation_link'] ?? ''));
    }
    if ($row['presentation_way'] === 'in person') {
        return !empty(trim($row['presentation_room'] ?? ''));
    }
    return false;
}

// ------------------ Φόρτωση διπλωματικών του επιβλέποντα σε under review ------------------
$list = [];
$stmt = $connection->prepare("
    SELECT
      d.diplo_id,
      d.diplo_title,
      d.diplo_student,
      d.diplo_status,
      s.student_name,
      s.student_surname,

      pr.presentation_date,
      pr.presentation_time,
      pr.presentation_way,
      pr.presentation_room,
      pr.presentation_link,

      dr.draft_diplo_pdf,
      dr.draft_links

    FROM diplo d
    LEFT JOIN student s ON s.student_am = d.diplo_student
    LEFT JOIN presentation pr ON pr.diplo_id = d.diplo_id
    LEFT JOIN draft dr ON dr.diplo_id = d.diplo_id

    WHERE d.diplo_professor = ?
      AND d.diplo_status IN ('under review','under_review')
    ORDER BY d.diplo_id DESC
");
$stmt->bind_param("i", $profUserId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $list[] = $row;

// ------------------ Παραγωγή ανακοίνωσης ------------------
if (isset($_POST['generate'])) {
    $diplo_id = (int)$_POST['diplo_id'];

    $st = $connection->prepare("
        SELECT
          d.diplo_id,
          d.diplo_title,
          d.diplo_student,
          d.diplo_status,
          s.student_name,
          s.student_surname,

          pr.presentation_date,
          pr.presentation_time,
          pr.presentation_way,
          pr.presentation_room,
          pr.presentation_link,

          dr.draft_diplo_pdf,
          dr.draft_links

        FROM diplo d
        LEFT JOIN student s ON s.student_am = d.diplo_student
        LEFT JOIN presentation pr ON pr.diplo_id = d.diplo_id
        LEFT JOIN draft dr ON dr.diplo_id = d.diplo_id

        WHERE d.diplo_id = ?
          AND d.diplo_professor = ?
          AND d.diplo_status IN ('under review','under_review')
        LIMIT 1
    ");
    $st->bind_param("ii", $diplo_id, $profUserId);
    $st->execute();
    $d = $st->get_result()->fetch_assoc();

    if (!$d) {
        $message = "❌ Δεν βρέθηκε διπλωματική ή δεν είστε επιβλέπων.";
    } elseif (!presentation_complete($d)) {
        $message = "⚠ Η επιλογή είναι ενεργή μόνο όταν ο φοιτητής έχει συμπληρώσει πλήρως τα στοιχεία παρουσίασης.";
    } else {

        $studName = trim(($d['student_surname'] ?? '') . " " . ($d['student_name'] ?? ''));
        $studPart = $studName !== '' ? $studName : ("AM: " . ($d['diplo_student'] ?? '-'));

        $dateStr = date("d/m/Y", strtotime($d['presentation_date']));
        $timeStr = substr((string)$d['presentation_time'], 0, 5);

        $wayStr = ($d['presentation_way'] === 'online') ? "Διαδικτυακά" : "Δια ζώσης";

        $placeStr = "";
        if ($d['presentation_way'] === 'online') {
            $placeStr = "Σύνδεσμος παρακολούθησης: " . ($d['presentation_link'] ?? '—');
        } else {
            $placeStr = "Αίθουσα: " . ($d['presentation_room'] ?? '—');
        }

        // Προαιρετικά: υλικό από draft
        $draftPdf = trim($d['draft_diplo_pdf'] ?? '');
        $draftLinks = trim($d['draft_links'] ?? '');

        $materialBlock = "";
        if ($draftPdf !== "" || $draftLinks !== "") {
            $materialBlock .= "\n\nΣχετικό υλικό:";
            if ($draftPdf !== "") {
                $materialBlock .= "\n- Πρόχειρο κείμενο (PDF): " . $draftPdf;
            }
            if ($draftLinks !== "") {
                $materialBlock .= "\n- Σύνδεσμοι:\n" . $draftLinks;
            }
        }

        $announcementText =
"ΑΝΑΚΟΙΝΩΣΗ ΠΑΡΟΥΣΙΑΣΗΣ ΔΙΠΛΩΜΑΤΙΚΗΣ ΕΡΓΑΣΙΑΣ

Τίτλος Διπλωματικής: " . ($d['diplo_title'] ?? '') . "
Φοιτητής/τρια: " . $studPart . "

Η παρουσίαση/εξέταση της διπλωματικής εργασίας θα πραγματοποιηθεί:
Ημερομηνία: " . $dateStr . "
Ώρα: " . $timeStr . "
Τρόπος: " . $wayStr . "
" . $placeStr . "

Η παρουσίαση είναι ανοικτή για παρακολούθηση." . $materialBlock;

        $message = "✅ Η ανακοίνωση δημιουργήθηκε.";
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <title>16) Δημιουργία ανακοίνωσης</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-dark bg-dark p-2">
  <span class="navbar-brand ms-3">16) Δημιουργία ανακοίνωσης</span>
  <a href="professor_page.php" class="btn btn-success ms-auto me-2">Αρχική</a>
  <a href="logout.php" class="btn btn-danger me-3">Αποσύνδεση</a>
</nav>

<div class="container mt-4">

  <?php if ($message): ?>
    <div class="alert alert-info text-center"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-header fw-bold">Διπλωματικές "Υπό Εξέταση" — Επιβλέπων</div>
    <div class="card-body table-responsive">

      <?php if (empty($list)): ?>
        <p class="text-muted text-center">Δεν υπάρχουν διπλωματικές σε κατάσταση "Υπό Εξέταση".</p>
      <?php else: ?>
        <table class="table table-bordered table-hover align-middle">
          <thead class="table-dark">
            <tr>
              <th>ID</th>
              <th>Θέμα</th>
              <th>Φοιτητής</th>
              <th>Παρουσίαση (φοιτητής)</th>
              <th>Υλικό draft (προαιρετικό)</th>
              <th>Ενέργεια</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($list as $d): ?>
            <?php
              $stud = "-";
              if (!empty($d['diplo_student'])) {
                  $stud = $d['diplo_student'] . " - " . trim(($d['student_surname'] ?? '') . " " . ($d['student_name'] ?? ''));
                  $stud = trim($stud);
              }
              $complete = presentation_complete($d);

              $draftOk = (!empty(trim($d['draft_diplo_pdf'] ?? '')) || !empty(trim($d['draft_links'] ?? '')));
            ?>
            <tr>
              <td><?= (int)$d['diplo_id'] ?></td>
              <td><?= htmlspecialchars($d['diplo_title'] ?? '') ?></td>
              <td><?= htmlspecialchars($stud) ?></td>

              <td>
                <?php if ($complete): ?>
                  <span class="badge bg-success">Πλήρη</span>
                  <div class="small text-muted">
                    <?= htmlspecialchars($d['presentation_way'] ?? '') ?> |
                    <?= htmlspecialchars($d['presentation_date'] ?? '') ?>
                    <?= htmlspecialchars($d['presentation_time'] ?? '') ?>
                  </div>
                <?php else: ?>
                  <span class="badge bg-warning text-dark">Ελλιπή / Δεν υπάρχει</span>
                <?php endif; ?>
              </td>

              <td>
                <?php if ($draftOk): ?>
                  <span class="badge bg-secondary">Υπάρχει</span>
                <?php else: ?>
                  <span class="badge bg-light text-dark">Δεν υπάρχει</span>
                <?php endif; ?>
              </td>

              <td style="min-width:220px;">
                <form method="POST">
                  <input type="hidden" name="diplo_id" value="<?= (int)$d['diplo_id'] ?>">
                  <button type="submit" name="generate" class="btn btn-primary btn-sm w-100"
                          <?= $complete ? "" : "disabled" ?>>
                    Παραγωγή ανακοίνωσης
                  </button>
                </form>
              </td>

            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

    </div>
  </div>

  <?php if (!empty($announcementText)): ?>
    <div class="card shadow-sm mt-4">
      <div class="card-header fw-bold">Κείμενο ανακοίνωσης</div>
      <div class="card-body">
        <textarea id="annText" class="form-control" rows="12"><?= htmlspecialchars($announcementText) ?></textarea>
        <button class="btn btn-success mt-2" onclick="copyAnn()">Αντιγραφή</button>
      </div>
    </div>

    <script>
      function copyAnn(){
        const ta = document.getElementById('annText');
        ta.select();
        ta.setSelectionRange(0, 999999);
        document.execCommand('copy');
        alert('Αντιγράφηκε!');
      }
    </script>
  <?php endif; ?>

</div>
</body>
</html>
