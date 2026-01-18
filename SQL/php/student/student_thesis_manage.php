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
$sqlDiplo = "SELECT * FROM diplo WHERE diplo_student = ? LIMIT 1";
$stmt = $connection->prepare($sqlDiplo);
$stmt->bind_param("i", $studentAm);
$stmt->execute();
$diplo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$diplo) die("Δεν σας έχει ανατεθεί διπλωματική εργασία.");

$diploId     = (int)$diplo['diplo_id'];
$diploStatus = $diplo['diplo_status'] ?? '';
$supervisorUserId = (int)($diplo['diplo_professor'] ?? 0); // επιβλέπων

$success_message = "";
$error_message   = "";
$info_message    = "";

$invites = [];
$availableProfessors = [];

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
        $prof2 = (int)$accepted[0]['professor_user_id'];
        $prof3 = (int)$accepted[1]['professor_user_id'];

        // προστασία: αν κάπως επιλέχθηκε ο επιβλέπων μέσα στα accept (δεν θα έπρεπε), τον πετάμε
        if ($supervisorUserId > 0) {
            $acceptedFiltered = array_values(array_filter($accepted, fn($r) => (int)$r['professor_user_id'] !== $supervisorUserId));
            if (count($acceptedFiltered) >= 2) {
                $prof2 = (int)$acceptedFiltered[0]['professor_user_id'];
                $prof3 = (int)$acceptedFiltered[1]['professor_user_id'];
            }
        }

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

        <h2>Η διπλωματική δεν βρίσκεται στη φάση «Υπό ανάθεση».</h2>
        <p>Σε αυτή τη φάση μπορείτε μόνο να δείτε τις υπάρχουσες προσκλήσεις.</p>

        <?php
        $sqlInvView = "SELECT ti.*, p.professor_name, p.professor_surname
                       FROM trimelous_invite ti
                       JOIN professor p ON ti.professor_user_id = p.professor_user_id
                       WHERE ti.diplo_id = ?
                       ORDER BY ti.trimelous_date ASC";
        $stmtInvV = $connection->prepare($sqlInvView);
        $stmtInvV->bind_param("i", $diploId);
        $stmtInvV->execute();
        $resInvV = $stmtInvV->get_result();
        if ($resInvV->num_rows > 0): ?>
            <table>
                <tr>
                    <th>Διδάσκων</th>
                    <th>Κατάσταση</th>
                    <th>Ημ/νία Πρόσκλησης</th>
                </tr>
                <?php while ($inv = $resInvV->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(($inv['professor_surname'] ?? '') . " " . ($inv['professor_name'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars($inv['invite_status'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($inv['trimelous_date'] ?? ''); ?></td>
                    </tr>
                <?php endwhile; ?>
            </table>
        <?php else: ?>
            <p>Δεν υπάρχουν καταχωρημένες προσκλήσεις.</p>
        <?php endif;
        $stmtInvV->close();
        ?>

    <?php endif; ?>
</div>
</body>
</html>
