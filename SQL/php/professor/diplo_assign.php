<?php
session_start();
include("db_connect.php");
include("connected.php");

// Debug (σβήστο μετά αν θες)
error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Έλεγχος ρόλου
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

$user = Professor_Connected($connection);
$prof_id = (int)($user['professor_user_id'] ?? 0);

if ($prof_id <= 0) {
    die("Δεν βρέθηκαν στοιχεία καθηγητή.");
}

$message  = "";
$students = [];
$diplomas = [];

// ------------------ Φόρτωση διαθέσιμων θεμάτων (χωρίς φοιτητή) ------------------
$sql = "SELECT * FROM diplo
        WHERE diplo_professor = ?
          AND diplo_student IS NULL
          AND diplo_status = 'pending'
        ORDER BY diplo_id DESC";

$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $prof_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $diplomas[] = $row;
}
$stmt->close();

// ------------------ Αναζήτηση φοιτητή ------------------
if (isset($_GET['search']) && $_GET['search'] !== '') {
    $term = trim($_GET['search']);

    $like = "%" . $term . "%";
    $q = "SELECT * FROM student
          WHERE student_am LIKE ?
             OR student_name LIKE ?
             OR student_surname LIKE ?
          ORDER BY student_surname, student_name
          LIMIT 50";

    $stmtS = $connection->prepare($q);
    $stmtS->bind_param("sss", $like, $like, $like);
    $stmtS->execute();
    $resS = $stmtS->get_result();
    while ($row = $resS->fetch_assoc()) {
        $students[] = $row;
    }
    $stmtS->close();
}

// ------------------ Ανάθεση θέματος ------------------
if (isset($_POST['assign'])) {

    $diplo_id   = (int)($_POST['diplo_id'] ?? 0);
    $student_am = (int)($_POST['student_am'] ?? 0);

    if ($diplo_id <= 0 || $student_am <= 0) {
        $message = "Λάθος στοιχεία ανάθεσης.";
    } else {

        // ✅ Έλεγχος: ο φοιτητής να μην έχει ήδη άλλη διπλωματική (εκτός cancelled/cancel)
        $sqlCheck = "SELECT COUNT(*) AS cnt
                     FROM diplo
                     WHERE diplo_student = ?
                       AND diplo_status NOT IN ('cancelled','cancel')
                     ";
        $stmtCk = $connection->prepare($sqlCheck);
        $stmtCk->bind_param("i", $student_am);
        $stmtCk->execute();
        $cnt = (int)($stmtCk->get_result()->fetch_assoc()['cnt'] ?? 0);
        $stmtCk->close();

        if ($cnt > 0) {
            $message = "⚠ Ο φοιτητής έχει ήδη αναλάβει άλλη διπλωματική και δεν μπορεί να πάρει δεύτερη.";
        } else {

            $connection->begin_transaction();
            try {
                // 1) Assign + pending
                $sqlU = "UPDATE diplo
                         SET diplo_student = ?,
                             diplo_status  = 'pending'
                         WHERE diplo_id = ?
                           AND diplo_professor = ?";
                $stmtU = $connection->prepare($sqlU);
                $stmtU->bind_param("iii", $student_am, $diplo_id, $prof_id);
                $stmtU->execute();

                if ($stmtU->affected_rows <= 0) {
                    $stmtU->close();
                    throw new Exception("Δεν έγινε ανάθεση. Ίσως το θέμα δεν ανήκει στον καθηγητή ή δεν είναι διαθέσιμο.");
                }
                $stmtU->close();

                // 2) Timeline: pending (μόνο αν δεν υπάρχει ήδη)
                $sqlExists = "SELECT 1
                              FROM diplo_date
                              WHERE diplo_id = ? AND diplo_status = 'pending'
                              LIMIT 1";
                $stmtE = $connection->prepare($sqlExists);
                $stmtE->bind_param("i", $diplo_id);
                $stmtE->execute();
                $exists = $stmtE->get_result()->fetch_assoc();
                $stmtE->close();

                if (!$exists) {
                    $sqlI = "INSERT INTO diplo_date (diplo_id, diplo_date, diplo_status)
                             VALUES (?, NOW(), 'pending')";
                    $stmtI = $connection->prepare($sqlI);
                    $stmtI->bind_param("i", $diplo_id);
                    $stmtI->execute();
                    $stmtI->close();
                }

                $connection->commit();
                $message = "✅ Η ανάθεση ολοκληρώθηκε και καταγράφηκε στο χρονολόγιο (pending).";

            } catch (Exception $e) {
                $connection->rollback();
                $message = "❌ Σφάλμα ανάθεσης: " . $e->getMessage();
            }
        }
    }
}

// ------------------ Ανάκληση ανάθεσης (προαιρετικό) ------------------
if (isset($_POST['cancel_assignment'])) {
    $diplo_id = (int)($_POST['diplo_id'] ?? 0);

    if ($diplo_id <= 0) {
        $message = "Λάθος diplo_id.";
    } else {
        $undo = "UPDATE diplo
                 SET diplo_student = NULL
                 WHERE diplo_id = ?
                   AND diplo_professor = ?";
        $stmtUndo = $connection->prepare($undo);
        $stmtUndo->bind_param("ii", $diplo_id, $prof_id);
        $stmtUndo->execute();
        $stmtUndo->close();

        $message = "Η ανάθεση αναιρέθηκε επιτυχώς.";
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Ανάθεση Θέματος</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

<nav class="navbar navbar-dark bg-dark p-2">
    <a class="navbar-brand ms-3">Η Πλατφόρμα</a>
    <a href="professor_page.php" class="btn btn-success ms-auto me-2">Αρχική</a>
    <a href="logout.php" class="btn btn-danger me-3">Αποσύνδεση</a>
</nav>

<div class="container-fluid">
<div class="row">



<main class="col-md-8 col-lg-9 mt-4">

    <h2 class="text-center fw-bold">Ανάθεση Θέματος σε Φοιτητή</h2>

    <?php if ($message): ?>
        <div class="alert alert-info mt-3 text-center"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="card mt-4 shadow-sm">
        <div class="card-header fw-bold">Ελεύθερα Θέματα</div>
        <div class="card-body">

            <?php if (empty($diplomas)): ?>
                <p class="text-muted">Δεν υπάρχουν διαθέσιμα θέματα προς ανάθεση.</p>
            <?php else: ?>
                <table class="table table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Τίτλος</th>
                            <th>Περιγραφή</th>
                            <th>Ενέργειες</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($diplomas as $d): ?>
                        <tr>
                            <td><?= htmlspecialchars($d['diplo_title'] ?? '') ?></td>
                            <td><?= htmlspecialchars($d['diplo_desc'] ?? '') ?></td>
                            <td>
                                <a href="?select_diplo=<?= (int)$d['diplo_id'] ?>" class="btn btn-primary btn-sm">
                                    Επιλογή για Ανάθεση
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        </div>
    </div>

    <?php if (isset($_GET['select_diplo'])):
        $selected_diplo = (int)$_GET['select_diplo'];
    ?>

    <div class="card mt-5 shadow-sm">
        <div class="card-header fw-bold">Αναζήτηση Φοιτητή</div>
        <div class="card-body">
            <form method="GET">
                <input type="hidden" name="select_diplo" value="<?= $selected_diplo ?>">
                <input type="text" class="form-control" name="search" placeholder="ΑΜ, όνομα ή επώνυμο" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                <button class="btn btn-primary w-100 mt-2" type="submit">Αναζήτηση</button>
            </form>
        </div>
    </div>

    <?php if (!empty($students)): ?>
        <div class="card mt-3 shadow-sm">
            <div class="card-header fw-bold">Αποτελέσματα</div>
            <div class="card-body">

                <table class="table table-striped table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>ΑΜ</th>
                            <th>Ονοματεπώνυμο</th>
                            <th>Ενέργεια</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['student_am'] ?? '') ?></td>
                            <td><?= htmlspecialchars(($s['student_name'] ?? '') . " " . ($s['student_surname'] ?? '')) ?></td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="diplo_id" value="<?= $selected_diplo ?>">
                                    <input type="hidden" name="student_am" value="<?= (int)($s['student_am'] ?? 0) ?>">
                                    <button class="btn btn-success btn-sm" name="assign" type="submit">
                                        Ανάθεση
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

            </div>
        </div>
    <?php elseif (isset($_GET['search'])): ?>
        <div class="alert alert-warning mt-3">Δεν βρέθηκαν αποτελέσματα.</div>
    <?php endif; ?>

    <?php endif; ?>

</main>
</div>
</div>

</body>
</html>
