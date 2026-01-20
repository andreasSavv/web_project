<?php
session_start();
include("db_connect.php");
include("connected.php");

// μόνο student
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$student = Student_Connected($connection);
if (!$student) die("Δεν βρέθηκαν στοιχεία φοιτητή.");

$studentAm = (int)($student['student_am'] ?? 0);
if ($studentAm <= 0) die("Λείπει το AM.");

// Φέρνουμε την “τρέχουσα” διπλωματική του (όπως διορθώσαμε πριν)
$sqlDiplo = "
  SELECT *
  FROM diplo
  WHERE diplo_student = ?
  ORDER BY
    FIELD(diplo_status, 'under review', 'under_review', 'active', 'finished', 'pending', 'cancelled'),
    diplo_id DESC
  LIMIT 1
";
$stmt = $connection->prepare($sqlDiplo);
$stmt->bind_param("i", $studentAm);
$stmt->execute();
$diplo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$diplo) die("Δεν βρέθηκε διπλωματική.");

$diploId = (int)$diplo['diplo_id'];
$status  = $diplo['diplo_status'] ?? '';
$title   = $diplo['diplo_title'] ?? '';
$finalGrade = $diplo['diplo_grade'] ?? null;

// --- Κανόνας: πρακτικό εμφανίζεται αφού υπάρχουν βαθμοί (ή όταν έχει τελικό βαθμό)
$hasFinal = ($finalGrade !== null && $finalGrade !== '');

// Φέρνουμε τριμελή (user_ids)
$sqlTri = "SELECT trimelous_professor1, trimelous_professor2, trimelous_professor3
           FROM trimelous
           WHERE diplo_id = ?
           LIMIT 1";
$stmtT = $connection->prepare($sqlTri);
$stmtT->bind_param("i", $diploId);
$stmtT->execute();
$tri = $stmtT->get_result()->fetch_assoc();
$stmtT->close();

$p1 = (int)($tri['trimelous_professor1'] ?? 0);
$p2 = (int)($tri['trimelous_professor2'] ?? 0);
$p3 = (int)($tri['trimelous_professor3'] ?? 0);

$triIds = array_values(array_filter([$p1,$p2,$p3], fn($x)=>$x>0));

// Φέρνουμε ονόματα καθηγητών + κριτήρια από grade_criteria
$grades = [];
if (!empty($triIds)) {
    $in = implode(",", array_fill(0, count($triIds), "?"));
    $types = str_repeat("i", count($triIds));

    // names map
    $sqlNames = "SELECT professor_user_id, professor_name, professor_surname
                 FROM professor
                 WHERE professor_user_id IN ($in)";
    $stN = $connection->prepare($sqlNames);
    $stN->bind_param($types, ...$triIds);
    $stN->execute();
    $rn = $stN->get_result();
    $nameMap = [];
    while ($r = $rn->fetch_assoc()) {
        $id = (int)$r['professor_user_id'];
        $nameMap[$id] = trim(($r['professor_surname'] ?? '') . " " . ($r['professor_name'] ?? ''));
    }
    $stN->close();

    // criteria
    $sqlCrit = "SELECT diplo_id, professor_user_id, quality_goals, time_interval, text_quality, Presentation
                FROM grade_criteria
                WHERE diplo_id = ?
                ORDER BY professor_user_id";
    $stC = $connection->prepare($sqlCrit);
    $stC->bind_param("i", $diploId);
    $stC->execute();
    $rc = $stC->get_result();
    while ($r = $rc->fetch_assoc()) {
        $pid = (int)$r['professor_user_id'];
        $r['prof_name'] = $nameMap[$pid] ?? ("Καθηγητής #" . $pid);
        $grades[] = $r;
    }
    $stC->close();
}

$hasAnyCriteria = (count($grades) > 0);

// ----------------- Νημερτής link save -----------------
$nimertisMsg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_nimertis'])) {
    $url = trim($_POST['nimertis_link'] ?? '');

    if ($url === '') {
        $nimertisMsg = "Βάλε ένα link.";
    } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
        $nimertisMsg = "Το link δεν είναι έγκυρο.";
    } else {
        $stmtU = $connection->prepare("UPDATE diplo SET nimertis_link = ? WHERE diplo_id = ? AND diplo_student = ?");
        $stmtU->bind_param("sii", $url, $diploId, $studentAm);
        $stmtU->execute();
        $stmtU->close();
        header("Location: student_minutes.php");
        exit;
    }
}

$nimertisLink = $diplo['nimertis_link'] ?? '';
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <title>Πρακτικό Εξέτασης</title>
  <style>
    body { font-family: Arial; background:#f6f6f6; margin:0; padding:0; }
    .wrap { max-width: 1000px; margin: 30px auto; background:#fff; padding: 20px 25px; border-radius: 10px; }
    table { width:100%; border-collapse: collapse; margin-top:10px; }
    th,td { border:1px solid #ddd; padding:8px; text-align:left; }
    th { background:#f0f0f0; }
    .muted { color:#777; }
    .badge { display:inline-block; padding:3px 8px; border-radius: 6px; background:#eef; }
    .ok { color: green; }
    .err { color: red; }
  </style>
</head>
<body>
<div class="wrap">
  <a href="student_page.php">← Πίσω</a>
  <h2>Πρακτικό Εξέτασης (HTML)</h2>

  <p><strong>Διπλωματική:</strong> #<?= (int)$diploId ?> — <?= htmlspecialchars($title) ?></p>
  <p><strong>Κατάσταση:</strong> <span class="badge"><?= htmlspecialchars($status) ?></span></p>

  <?php if (!$hasAnyCriteria && !$hasFinal): ?>
    <p class="muted">
      Δεν υπάρχουν ακόμη καταχωρημένοι βαθμοί. Το πρακτικό θα είναι διαθέσιμο αφού βαθμολογήσει η τριμελής
      (ή μόλις υπάρχει τελικός βαθμός).
    </p>
  <?php else: ?>

    <h3>Αναλυτικοί βαθμοί (κριτήρια)</h3>

    <?php if (!$hasAnyCriteria): ?>
      <p class="muted">Δεν έχουν καταχωρηθεί ακόμη αναλυτικά κριτήρια στο grade_criteria.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Καθηγητής</th>
            <th>Ποιότητα στόχων</th>
            <th>Χρονική διάρκεια</th>
            <th>Ποιότητα κειμένου</th>
            <th>Παρουσίαση</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($grades as $g): ?>
          <tr>
            <td><?= htmlspecialchars($g['prof_name']) ?></td>
            <td><?= htmlspecialchars($g['quality_goals']) ?></td>
            <td><?= htmlspecialchars($g['time_interval']) ?></td>
            <td><?= htmlspecialchars($g['text_quality']) ?></td>
            <td><?= htmlspecialchars($g['Presentation']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <h3 style="margin-top:20px;">Τελικός βαθμός</h3>
    <p>
      <?php
        echo ($hasFinal) ? "<strong class='ok'>".htmlspecialchars($finalGrade)."</strong>" : "<span class='muted'>Δεν υπάρχει ακόμη τελικός βαθμός.</span>";
      ?>
    </p>

    <hr>

    <h3>Σύνδεσμος Νημερτής (τελικό κείμενο)</h3>

    <?php if ($nimertisMsg): ?>
      <p class="<?= strpos($nimertisMsg, 'έγκυρο') !== false ? 'err' : 'err' ?>"><?= htmlspecialchars($nimertisMsg) ?></p>
    <?php endif; ?>

    <?php if (!empty($nimertisLink)): ?>
      <p>Τρέχον link: <a href="<?= htmlspecialchars($nimertisLink) ?>" target="_blank"><?= htmlspecialchars($nimertisLink) ?></a></p>
    <?php else: ?>
      <p class="muted">Δεν έχει καταχωρηθεί link ακόμα.</p>
    <?php endif; ?>

    <form method="POST">
      <input type="text" name="nimertis_link" value="<?= htmlspecialchars($nimertisLink) ?>" placeholder="https://..." style="width:80%;" required>
      <button type="submit" name="save_nimertis">Αποθήκευση</button>
    </form>

  <?php endif; ?>
</div>
</body>
</html>
