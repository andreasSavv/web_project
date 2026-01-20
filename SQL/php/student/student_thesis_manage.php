<?php
session_start();
include("db_connect.php");
include("connected.php");

// Debug (σβήστο μετά)
error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 1) Μόνο φοιτητής
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

// 2) Στοιχεία φοιτητή
$student = Student_Connected($connection);
if (!$student) die("Δεν βρέθηκαν στοιχεία φοιτητή.");

$studentAm = $student['student_am'] ?? null;
if (!$studentAm) die("Λείπει το AM του φοιτητή.");

// 3) Διπλωματική φοιτητή
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

// ------------------------------------- edwwwwwwww pintnw -----------------------------------------

// ===================== FINAL GRADE + REPO LINK =====================
$finalGrade = null;

// Πιάσε τελικό βαθμό από trimelis_grades
$stmtFG = $connection->prepare("
    SELECT trimelis_final_grade
    FROM trimelis_grades
    WHERE diplo_id = ?
    LIMIT 1
");
$stmtFG->bind_param("i", $diploId);
$stmtFG->execute();
$grRow = $stmtFG->get_result()->fetch_assoc();
$stmtFG->close();

if ($grRow && $grRow['trimelis_final_grade'] !== null) {
    $finalGrade = (float)$grRow['trimelis_final_grade'];
}

$repoLink = $diplo['diplo_repo_link'] ?? '';

// Καταχώρηση / ενημέρωση link Νημερτή (μόνο αν υπάρχουν βαθμοί)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_repo_link'])) {
    if ($finalGrade === null) {
        $error_message = "Δεν μπορείτε να καταχωρήσετε σύνδεσμο Νημερτή πριν ολοκληρωθεί η βαθμολόγηση.";
    } else {
        $newLink = trim($_POST['repo_link'] ?? '');

        if ($newLink === '') {
            $error_message = "Βάλε ένα link.";
        } elseif (!filter_var($newLink, FILTER_VALIDATE_URL)) {
            $error_message = "Το link δεν είναι έγκυρο.";
        } else {
            $stmtUp = $connection->prepare("UPDATE diplo SET diplo_repo_link = ? WHERE diplo_id = ?");
            $stmtUp->bind_param("si", $newLink, $diploId);
            $stmtUp->execute();
            $stmtUp->close();

            header("Location: student_thesis_manage.php?ok=repo");
            exit;
        }
    }
}

if (isset($_GET['ok']) && $_GET['ok'] === 'repo') {
    $success_message = "✅ Ο σύνδεσμος Νημερτή αποθηκεύτηκε.";
}


$stmt->close();

if (!$diplo) die("Δεν σας έχει ανατεθεί διπλωματική εργασία.");

$diploId     = (int)$diplo['diplo_id'];
$diploStatus = $diplo['diplo_status'] ?? '';
$supervisorUserId = (int)($diplo['diplo_professor'] ?? 0); // επιβλέπων (professor_user_id)

$success_message = "";
$error_message   = "";
$info_message    = "";

$invites = [];
$availableProfessors = [];

// Under review flag (δέχεται και τα δύο ονόματα)
$isUnderReview = ($diploStatus === 'under review' || $diploStatus === 'under_review');

// 4) info αν δεν είμαστε pending
if ($diploStatus !== 'pending') {
    $info_message = "Η διπλωματική σας δεν είναι πλέον στη φάση 'Υπό ανάθεση'. "
                  . "Τρέχουσα κατάσταση: " . htmlspecialchars($diploStatus) . ".";
}

// ====================== PENDING FLOW ======================
if ($diploStatus === 'pending') {

    // 5α) Αποστολή νέας πρόσκλησης
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['professor_user_id'])) {
        $profUserId = (int)$_POST['professor_user_id'];

        if ($profUserId <= 0) {
            $error_message = "Πρέπει να επιλέξετε Διδάσκοντα.";
        } elseif ($supervisorUserId > 0 && $profUserId === $supervisorUserId) {
            $error_message = "Δεν μπορείτε να προσκαλέσετε τον επιβλέποντα ως μέλος της τριμελούς.";
        } else {

            // έλεγχος duplicate invite
            $sqlCheck = "SELECT COUNT(*) AS cnt
                         FROM trimelous_invite
                         WHERE diplo_id = ? AND professor_user_id = ?";
            $stmtCheck = $connection->prepare($sqlCheck);
            $stmtCheck->bind_param("ii", $diploId, $profUserId);
            $stmtCheck->execute();
            $cnt = (int)($stmtCheck->get_result()->fetch_assoc()['cnt'] ?? 0);
            $stmtCheck->close();

            if ($cnt > 0) {
                $error_message = "Έχετε ήδη στείλει πρόσκληση σε αυτόν τον Διδάσκοντα.";
            } else {
                // insert invite
                $sqlIns = "INSERT INTO trimelous_invite
                           (diplo_id, diplo_student_am, professor_user_id, trimelous_date, invite_status)
                           VALUES (?, ?, ?, NOW(), 'pending')";
                $stmtIns = $connection->prepare($sqlIns);
                $stmtIns->bind_param("iii", $diploId, $studentAm, $profUserId);
                $stmtIns->execute();
                $stmtIns->close();

                header("Location: student_thesis_manage.php");
                exit;
            }
        }
    }

    // 5β) Φέρνουμε invites
    $sqlInv = "SELECT ti.*, p.professor_name, p.professor_surname
               FROM trimelous_invite ti
               JOIN professor p ON ti.professor_user_id = p.professor_user_id
               WHERE ti.diplo_id = ?
               ORDER BY ti.trimelous_date ASC";
    $stmtInv = $connection->prepare($sqlInv);
    $stmtInv->bind_param("i", $diploId);
    $stmtInv->execute();
    $resInv = $stmtInv->get_result();
    while ($r = $resInv->fetch_assoc()) $invites[] = $r;
    $stmtInv->close();

    // 5γ) accepted
    $accepted = array_values(array_filter($invites, fn($r) => ($r['invite_status'] ?? '') === 'accept'));
    $acceptedCount = count($accepted);

    // 5δ) Αν >=2 accept -> active + trimelous update
    if ($acceptedCount >= 2) {
        usort($accepted, fn($a,$b) => strcmp($a['trimelous_date'], $b['trimelous_date']));

        // παίρνουμε 2 πρώτους accepted (εκτός επιβλέποντα)
        $acceptedFiltered = array_values(array_filter($accepted, fn($r) => (int)$r['professor_user_id'] !== $supervisorUserId));
        if (count($acceptedFiltered) >= 2) {
            $prof2 = (int)$acceptedFiltered[0]['professor_user_id'];
            $prof3 = (int)$acceptedFiltered[1]['professor_user_id'];

            $connection->begin_transaction();
            try {
                // upsert trimelous
                $sqlHasTri = "SELECT COUNT(*) AS c FROM trimelous WHERE diplo_id = ?";
                $stmtHas = $connection->prepare($sqlHasTri);
                $stmtHas->bind_param("i", $diploId);
                $stmtHas->execute();
                $has = (int)($stmtHas->get_result()->fetch_assoc()['c'] ?? 0);
                $stmtHas->close();

                if ($has === 0) {
                    $sqlInsTri = "INSERT INTO trimelous
                                  (diplo_id, trimelous_professor1, trimelous_professor2, trimelous_professor3)
                                  VALUES (?, ?, ?, ?)";
                    $stmtInsTri = $connection->prepare($sqlInsTri);
                    $stmtInsTri->bind_param("iiii", $diploId, $supervisorUserId, $prof2, $prof3);
                    $stmtInsTri->execute();
                    $stmtInsTri->close();
                } else {
                    $sqlUpdTri = "UPDATE trimelous
                                  SET trimelous_professor1 = COALESCE(NULLIF(trimelous_professor1, 0), ?),
                                      trimelous_professor2 = ?,
                                      trimelous_professor3 = ?
                                  WHERE diplo_id = ?";
                    $stmtUpdTri = $connection->prepare($sqlUpdTri);
                    $stmtUpdTri->bind_param("iiii", $supervisorUserId, $prof2, $prof3, $diploId);
                    $stmtUpdTri->execute();
                    $stmtUpdTri->close();
                }

                // diplo -> active
                $sqlUpdDiplo = "UPDATE diplo SET diplo_status = 'active' WHERE diplo_id = ?";
                $stmtUpdD = $connection->prepare($sqlUpdDiplo);
                $stmtUpdD->bind_param("i", $diploId);
                $stmtUpdD->execute();
                $stmtUpdD->close();

                // cancel remaining pending invites
                $sqlCancel = "UPDATE trimelous_invite
                              SET invite_status = 'cancelled'
                              WHERE diplo_id = ? AND invite_status = 'pending'";
                $stmtCancel = $connection->prepare($sqlCancel);
                $stmtCancel->bind_param("i", $diploId);
                $stmtCancel->execute();
                $stmtCancel->close();

                $connection->commit();

                header("Location: student_thesis_manage.php");
                exit;

            } catch (Exception $e) {
                $connection->rollback();
                $error_message = "Σφάλμα ενημέρωσης τριμελούς/κατάστασης: " . $e->getMessage();
            }
        } else {
            $error_message = "Δεν υπάρχουν 2 αποδοχές από διαφορετικούς καθηγητές (εκτός επιβλέποντα).";
        }
    }

    // 5ε) διαθέσιμοι καθηγητές (exclude already invited + exclude supervisor)
    $sqlProf = "SELECT professor_user_id, professor_name, professor_surname
                FROM professor
                WHERE professor_user_id NOT IN (
                    SELECT professor_user_id
                    FROM trimelous_invite
                    WHERE diplo_id = ?
                )
                AND professor_user_id <> ?
                ORDER BY professor_surname, professor_name";
    $stmtProf = $connection->prepare($sqlProf);
    $stmtProf->bind_param("ii", $diploId, $supervisorUserId);
    $stmtProf->execute();
    $resProf = $stmtProf->get_result();
    while ($r = $resProf->fetch_assoc()) $availableProfessors[] = $r;
    $stmtProf->close();
}
// ====================== END PENDING FLOW ======================


// ===================== UNDER REVIEW: Draft + Links + Presentation =====================
$draftRow = null;
$currentPdf = "";
$linksArr = [];
$presentationRow = null;
$presentationLocked = false;

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

if ($isUnderReview) {

    // ---------- Draft row ----------
    $stmtD = $connection->prepare("SELECT diplo_id, draft_diplo_pdf, draft_links FROM draft WHERE diplo_id = ? LIMIT 1");
    $stmtD->bind_param("i", $diploId);
    $stmtD->execute();
    $draftRow = $stmtD->get_result()->fetch_assoc();
    $stmtD->close();

    if (!$draftRow) {
        $stmtIns = $connection->prepare("INSERT INTO draft (diplo_id, draft_diplo_pdf, draft_links) VALUES (?, NULL, NULL)");
        $stmtIns->bind_param("i", $diploId);
        $stmtIns->execute();
        $stmtIns->close();
        $draftRow = ['diplo_id' => $diploId, 'draft_diplo_pdf' => null, 'draft_links' => null];
    }

    $currentPdf = $draftRow['draft_diplo_pdf'] ?? '';
    $linksArr   = links_to_array($draftRow['draft_links'] ?? '');

    // ---------- Upload draft PDF ----------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_draft_pdf'])) {

        if (!isset($_FILES['draft_pdf']) || $_FILES['draft_pdf']['error'] !== UPLOAD_ERR_OK) {
            $error_message = "Σφάλμα στο ανέβασμα αρχείου.";
        } else {
            $tmp  = $_FILES['draft_pdf']['tmp_name'];
            $name = $_FILES['draft_pdf']['name'];

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                $error_message = "Επιτρέπεται μόνο PDF.";
            } else {
                $maxBytes = 10 * 1024 * 1024; // 10MB
                if ($_FILES['draft_pdf']['size'] > $maxBytes) {
                    $error_message = "Το αρχείο είναι πολύ μεγάλο (max 10MB).";
                } else {
                    $dirRel = "uploads/drafts";
                    $dirAbs = __DIR__ . "/" . $dirRel;

                    if (!is_dir($dirAbs)) {
                        $error_message = "Λείπει ο φάκελος $dirRel. Δημιούργησέ τον μέσα στο htdocs.";
                    } else {
                        $safeName  = "diplo_" . $diploId . "_draft_" . time() . ".pdf";
                        $targetRel = $dirRel . "/" . $safeName;
                        $targetAbs = $dirAbs . "/" . $safeName;

                        if (move_uploaded_file($tmp, $targetAbs)) {
                            $stmtUp = $connection->prepare("UPDATE draft SET draft_diplo_pdf = ? WHERE diplo_id = ?");
                            $stmtUp->bind_param("si", $targetRel, $diploId);
                            $stmtUp->execute();
                            $stmtUp->close();

                            header("Location: student_thesis_manage.php");
                            exit;
                        } else {
                            $error_message = "Αποτυχία αποθήκευσης αρχείου στον server.";
                        }
                    }
                }
            }
        }
    }

    // ---------- Add link ----------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_draft_link'])) {
        $url = trim($_POST['new_link'] ?? "");

        if ($url === "") {
            $error_message = "Βάλε ένα link.";
        } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
            $error_message = "Το link δεν είναι έγκυρο.";
        } else {
            if (!in_array($url, $linksArr, true)) {
                $linksArr[] = $url;
            }

            $newText = array_to_links($linksArr);
            $stmtUp = $connection->prepare("UPDATE draft SET draft_links = ? WHERE diplo_id = ?");
            $stmtUp->bind_param("si", $newText, $diploId);
            $stmtUp->execute();
            $stmtUp->close();

            header("Location: student_thesis_manage.php");
            exit;
        }
    }

    // ---------- Delete link ----------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_draft_link'])) {
        $idx = (int)($_POST['idx'] ?? -1);

        if ($idx >= 0 && $idx < count($linksArr)) {
            array_splice($linksArr, $idx, 1);

            $newText = array_to_links($linksArr);
            $stmtUp = $connection->prepare("UPDATE draft SET draft_links = ? WHERE diplo_id = ?");
            $stmtUp->bind_param("si", $newText, $diploId);
            $stmtUp->execute();
            $stmtUp->close();

            header("Location: student_thesis_manage.php");
            exit;
        }
    }

    // ---------- Presentation row ----------
    $stmtP = $connection->prepare("SELECT * FROM presentation WHERE diplo_id = ? LIMIT 1");
    $stmtP->bind_param("i", $diploId);
    $stmtP->execute();
    $presentationRow = $stmtP->get_result()->fetch_assoc();
    $stmtP->close();

    if (!$presentationRow) {
        $stmtPI = $connection->prepare("INSERT INTO presentation (diplo_id) VALUES (?)");
        $stmtPI->bind_param("i", $diploId);
        $stmtPI->execute();
        $stmtPI->close();

        $presentationRow = [
            'diplo_id' => $diploId,
            'presentation_date' => null,
            'presentation_time' => null,
            'presentation_way'  => null,
            'presentation_room' => null,
            'presentation_link' => null
        ];
    }

    // Save presentation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_presentation'])) {

        $p_date = trim($_POST['presentation_date'] ?? "");
        $p_time = trim($_POST['presentation_time'] ?? "");
        $p_way  = trim($_POST['presentation_way'] ?? ""); // 'in person' / 'online'
        $p_room = trim($_POST['presentation_room'] ?? "");
        $p_link = trim($_POST['presentation_link'] ?? "");

        if ($p_date === "" || $p_time === "" || ($p_way !== "in person" && $p_way !== "online")) {
            $error_message = "Συμπλήρωσε ημερομηνία, ώρα και τρόπο εξέτασης.";
        } else {
            if ($p_way === "in person") {
                if ($p_room === "") {
                    $error_message = "Για δια ζώσης εξέταση πρέπει να συμπληρώσεις αίθουσα.";
                } else {
                    $p_link = ""; // καθαρίζουμε link
                }
            } else { // online
                if ($p_link === "" || !filter_var($p_link, FILTER_VALIDATE_URL)) {
                    $error_message = "Για διαδικτυακή εξέταση πρέπει να συμπληρώσεις έγκυρο σύνδεσμο.";
                } else {
                    $p_room = ""; // καθαρίζουμε αίθουσα
                }
            }
        }

        if ($error_message === "") {
            $sqlPU = "UPDATE presentation
                      SET presentation_date = ?,
                          presentation_time = ?,
                          presentation_way  = ?,
                          presentation_room = ?,
                          presentation_link = ?
                      WHERE diplo_id = ?";
            $stmtPU = $connection->prepare($sqlPU);
            $stmtPU->bind_param("sssssi", $p_date, $p_time, $p_way, $p_room, $p_link, $diploId);
            $stmtPU->execute();
            $stmtPU->close();

            header("Location: student_thesis_manage.php");
            exit;
        }
    }

    // Re-fetch presentation
    $stmtP2 = $connection->prepare("SELECT * FROM presentation WHERE diplo_id = ? LIMIT 1");
    $stmtP2->bind_param("i", $diploId);
    $stmtP2->execute();
    $presentationRow = $stmtP2->get_result()->fetch_assoc();
    $stmtP2->close();

    $presentationLocked = (
        !empty($presentationRow['presentation_date']) &&
        !empty($presentationRow['presentation_time']) &&
        !empty($presentationRow['presentation_way'])
    );
}
// =================== END UNDER REVIEW: Draft + Links + Presentation ===================

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Διαχείριση Διπλωματικής</title>
    <style>
        body { font-family: Arial, sans-serif; background: #eef6ff; margin: 0; padding: 0; }
        .container { max-width: 1000px; margin: 40px auto; background: #fff; padding: 20px 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; }
        .back-link { text-decoration: none; color: #007bff; }
        .back-link:hover { text-decoration: underline; }
        .message-success { color: green; margin-top: 10px; }
        .message-error { color: red; margin-top: 10px; }
        .info { margin-top: 10px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
<div class="container">
    <a class="back-link" href="student_page.php">&larr; Πίσω στην αρχική φοιτητή</a>
    <h1>Διαχείριση Διπλωματικής Εργασίας</h1>

    <p>Τίτλος: <strong><?php echo htmlspecialchars($diplo['diplo_title'] ?? ''); ?></strong></p>
    <p>Τρέχουσα κατάσταση: <strong><?php echo htmlspecialchars($diploStatus); ?></strong></p>

    <?php if ($info_message): ?>
        <div class="info"><?php echo $info_message; ?></div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="message-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="message-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <?php if ($diploStatus === 'pending'): ?>

        <h2>Υπό ανάθεση – Διαχείριση τριμελούς επιτροπής</h2>

        <h3>Προσκλήσεις</h3>
        <?php if (empty($invites)): ?>
            <p>Δεν έχετε στείλει ακόμη προσκλήσεις.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Διδάσκων</th>
                    <th>Κατάσταση</th>
                    <th>Ημ/νία Πρόσκλησης</th>
                </tr>
                <?php foreach ($invites as $inv): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(($inv['professor_surname'] ?? '') . " " . ($inv['professor_name'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars($inv['invite_status'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($inv['trimelous_date'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <h3>Νέα πρόσκληση</h3>
        <?php if (empty($availableProfessors)): ?>
            <p>Δεν υπάρχουν διαθέσιμοι επιπλέον Διδάσκοντες για πρόσκληση.</p>
        <?php else: ?>
            <form method="post" action="">
                <label for="professor_user_id">Επιλέξτε Διδάσκοντα:</label>
                <select name="professor_user_id" id="professor_user_id" required>
                    <option value="">-- Επιλέξτε --</option>
                    <?php foreach ($availableProfessors as $p): ?>
                        <option value="<?php echo (int)$p['professor_user_id']; ?>">
                            <?php echo htmlspecialchars(($p['professor_surname'] ?? '') . " " . ($p['professor_name'] ?? '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Αποστολή πρόσκλησης</button>
            </form>
        <?php endif; ?>

    <?php else: ?>

        <?php if ($isUnderReview): ?>

            <hr>
            <h2>Υπό εξέταση – Πρόχειρο κείμενο & Υλικό</h2>

            <h4>1) Πρόχειρο κείμενο (PDF)</h4>
            <?php if (!empty($currentPdf)): ?>
                <p>Τρέχον draft: <a href="<?php echo htmlspecialchars($currentPdf); ?>" target="_blank">Άνοιγμα PDF</a></p>
            <?php else: ?>
                <p class="text-muted">Δεν έχει ανέβει ακόμη draft.</p>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" style="margin-top:10px;">
                <input type="file" name="draft_pdf" accept="application/pdf" required>
                <button type="submit" name="upload_draft_pdf">Ανέβασμα PDF</button>
            </form>

            <hr>

            <h4>2) Links υλικού (Drive / YouTube κλπ)</h4>
            <form method="POST" style="margin-top:10px;">
                <input type="text" name="new_link" placeholder="https://..." required style="width:70%;">
                <button type="submit" name="add_draft_link">Προσθήκη link</button>
            </form>

            <div style="margin-top:15px;">
                <?php if (empty($linksArr)): ?>
                    <p class="text-muted">Δεν υπάρχουν links.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($linksArr as $i => $url): ?>
                            <li style="margin-bottom:6px;">
                                <a href="<?php echo htmlspecialchars($url); ?>" target="_blank"><?php echo htmlspecialchars($url); ?></a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Διαγραφή link;');">
                                    <input type="hidden" name="idx" value="<?php echo (int)$i; ?>">
                                    <button type="submit" name="delete_draft_link">Διαγραφή</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <hr>
            <h2>3) Καταχώρηση ημερομηνίας & τρόπου εξέτασης</h2>

            <?php if ($presentationLocked): ?>
                <div style="background:#e6ffed;border:1px solid #2ecc71;padding:15px;border-radius:8px;margin-bottom:20px;">
                    <strong>✅ Η εξέταση έχει οριστεί!</strong><br><br>
                    <ul style="margin:0; padding-left:18px;">
                        <li><strong>Ημερομηνία:</strong>
                            <?php echo date("d/m/Y", strtotime($presentationRow['presentation_date'])); ?>
                        </li>
                        <li><strong>Ώρα:</strong>
                            <?php echo substr((string)$presentationRow['presentation_time'], 0, 5); ?>
                        </li>
                        <li><strong>Τρόπος:</strong>
                            <?php echo ($presentationRow['presentation_way'] === 'online') ? 'Διαδικτυακά' : 'Δια ζώσης'; ?>
                        </li>
                        <?php if (($presentationRow['presentation_way'] ?? '') === 'online'): ?>
                            <li><strong>Σύνδεσμος:</strong>
                                <a href="<?php echo htmlspecialchars($presentationRow['presentation_link'] ?? ''); ?>" target="_blank">Άνοιγμα</a>
                            </li>
                        <?php else: ?>
                            <li><strong>Αίθουσα:</strong>
                                <?php echo htmlspecialchars($presentationRow['presentation_room'] ?? ''); ?>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <p style="margin-top:10px; font-size:0.9em; color:#555;">
                        Η πληροφορία αυτή είναι ορατή σε όλα τα μέλη της τριμελούς.
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($finalGrade !== null): ?>
    <hr>
    <h2>Περάτωση / Πρακτικό / Νημερτής</h2>

    <p><strong>Τελικός βαθμός τριμελούς:</strong> <?php echo htmlspecialchars(number_format($finalGrade, 2)); ?></p>

    <p>
        <a href="student_exam_report.php?diplo_id=<?php echo (int)$diploId; ?>" target="_blank">
            📄 Προβολή Πρακτικού Εξέτασης (HTML)
        </a>
    </p>

    <h4>Σύνδεσμος Νημερτή (τελικό κείμενο)</h4>

    <?php if (!empty($repoLink)): ?>
        <p>Τρέχον link: <a href="<?php echo htmlspecialchars($repoLink); ?>" target="_blank"><?php echo htmlspecialchars($repoLink); ?></a></p>
    <?php else: ?>
        <p class="text-muted">Δεν έχει καταχωρηθεί ακόμα link Νημερτή.</p>
    <?php endif; ?>

    <form method="POST" style="margin-top:10px;">
        <input type="text" name="repo_link" placeholder="https://..." value="<?php echo htmlspecialchars($repoLink); ?>" required style="width:70%;">
        <button type="submit" name="save_repo_link">Αποθήκευση link Νημερτή</button>
    </form>

<?php else: ?>
    <hr>
    <h2>Περάτωση / Πρακτικό / Νημερτής</h2>
    <p class="text-muted">
        Το πρακτικό και ο σύνδεσμος Νημερτή θα εμφανιστούν αφού καταχωρηθούν οι βαθμοί από όλα τα μέλη της τριμελούς.
    </p>
<?php endif; ?>


            <form method="POST" style="margin-top:10px;">
                <label>Ημερομηνία:</label><br>
                <input type="date" name="presentation_date"
                       value="<?php echo htmlspecialchars($presentationRow['presentation_date'] ?? ''); ?>"
                       required><br><br>

                <label>Ώρα:</label><br>
                <input type="time" name="presentation_time"
                       value="<?php echo htmlspecialchars($presentationRow['presentation_time'] ?? ''); ?>"
                       required><br><br>

                <label>Τρόπος εξέτασης:</label><br>
                <select name="presentation_way" id="presentation_way" required>
                    <option value="">-- Επιλογή --</option>
                    <option value="in person" <?php echo (($presentationRow['presentation_way'] ?? '') === 'in person') ? 'selected' : ''; ?>>
                        Δια ζώσης
                    </option>
                    <option value="online" <?php echo (($presentationRow['presentation_way'] ?? '') === 'online') ? 'selected' : ''; ?>>
                        Διαδικτυακά
                    </option>
                </select>
                <br><br>

                <div id="onsite_fields" style="display:none;">
                    <label>Αίθουσα εξέτασης:</label><br>
                    <input type="text" name="presentation_room"
                           value="<?php echo htmlspecialchars($presentationRow['presentation_room'] ?? ''); ?>"
                           placeholder="π.χ. Αίθουσα Β3"><br><br>
                </div>

                <div id="online_fields" style="display:none;">
                    <label>Σύνδεσμος παρακολούθησης:</label><br>
                    <input type="text" name="presentation_link"
                           value="<?php echo htmlspecialchars($presentationRow['presentation_link'] ?? ''); ?>"
                           placeholder="https://..."><br><br>
                </div>

                <button type="submit" name="save_presentation">Αποθήκευση</button>
            </form>

            <script>
            (function(){
                function toggleFields() {
                    var way = document.getElementById('presentation_way').value;
                    document.getElementById('onsite_fields').style.display = (way === 'in person') ? 'block' : 'none';
                    document.getElementById('online_fields').style.display = (way === 'online') ? 'block' : 'none';
                }
                document.getElementById('presentation_way').addEventListener('change', toggleFields);
                toggleFields();
            })();
            </script>

        <?php else: ?>
            <p>Δεν υπάρχουν ενέργειες για αυτή την κατάσταση εδώ (προς το παρόν).</p>
        <?php endif; ?>

    <?php endif; ?>

</div>
</body>
</html>
