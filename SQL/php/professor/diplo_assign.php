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
$prof_id = (int)($user['professor_user_id'] ?? $user['professor_id'] ?? 0);
if ($prof_id <= 0) die("Δεν βρέθηκαν στοιχεία καθηγητή.");

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$message  = "";
$students = [];

// -------------------- Φέρνουμε ΟΛΑ τα pending του καθηγητή --------------------
$diplomas_all = [];
$stmt = $connection->prepare("
    SELECT diplo_id, diplo_title, diplo_desc, diplo_student, diplo_status
    FROM diplo
    WHERE diplo_professor = ?
      AND diplo_status = 'pending'
    ORDER BY diplo_id DESC
");
$stmt->bind_param("i", $prof_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $diplomas_all[] = $r;
$stmt->close();

// Χωρίζουμε σε “ελεύθερα” και “ανατεθειμένα”
$free_diplomas = [];
$assigned_pending = [];
foreach ($diplomas_all as $d) {
    $st = $d['diplo_student'];

    // ελεύθερο αν είναι NULL ή 0 ή '' (πολλά db το κρατάνε έτσι)
    $isFree = ($st === null || $st === '' || (string)$st === '0' || (int)$st === 0);

    if ($isFree) $free_diplomas[] = $d;
    else $assigned_pending[] = $d;
}

// -------------------- Αναζήτηση φοιτητή --------------------
$select_diplo = (int)($_GET['select_diplo'] ?? 0);

if (!empty($_GET['search']) && $select_diplo > 0) {
    $like = "%" . trim($_GET['search']) . "%";
    $stmt = $connection->prepare("
        SELECT student_am, student_name, student_surname
        FROM student
        WHERE student_am LIKE ?
           OR student_name LIKE ?
           OR student_surname LIKE ?
        ORDER BY student_surname, student_name
        LIMIT 50
    ");
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $students[] = $r;
    $stmt->close();
}

// --------- Ανάθεση ---------
if (isset($_POST['assign'])) {
    $diplo_id   = (int)($_POST['diplo_id'] ?? 0);
    $student_am = (int)($_POST['student_am'] ?? 0);

    if ($diplo_id <= 0 || $student_am <= 0) {
        $message = "❌ Λάθος στοιχεία.";
    } else {

        // 1) Έλεγχος ότι το θέμα είναι δικό μου, pending και ελεύθερο (χωρίς φοιτητή)
        $chkDip = $connection->prepare("
            SELECT diplo_id
            FROM diplo
            WHERE diplo_id = ?
              AND diplo_professor = ?
              AND diplo_status = 'pending'
              AND diplo_student IS NULL
            LIMIT 1
        ");
        $chkDip->bind_param("ii", $diplo_id, $prof_id);
        $chkDip->execute();
        $okDip = $chkDip->get_result()->fetch_assoc();
        $chkDip->close();

        if (!$okDip) {
            $message = "❌ Δεν επιτρέπεται ανάθεση (δεν είναι διαθέσιμο pending θέμα ή δεν είναι δικό σας).";
        } else {

            // 2) Έλεγχος ότι ο φοιτητής δεν έχει άλλη διπλωματική (εκτός cancelled/cancel)
            $chk = $connection->prepare("
                SELECT COUNT(*) cnt
                FROM diplo
                WHERE diplo_student = ?
                  AND diplo_status NOT IN ('cancelled','cancel')
            ");
            $chk->bind_param("i", $student_am);
            $chk->execute();
            $cnt = (int)($chk->get_result()->fetch_assoc()['cnt'] ?? 0);
            $chk->close();

            if ($cnt > 0) {
                $message = "⚠ Ο φοιτητής έχει ήδη άλλη διπλωματική.";
            } else {

                $connection->begin_transaction();
                try {
                    // 3) Ανάθεση στον πίνακα diplo
                    $upd = $connection->prepare("
                        UPDATE diplo
                        SET diplo_student = ?
                        WHERE diplo_id = ?
                          AND diplo_professor = ?
                          AND diplo_status = 'pending'
                          AND diplo_student IS NULL
                    ");
                    $upd->bind_param("iii", $student_am, $diplo_id, $prof_id);
                    $upd->execute();

                    if ($upd->affected_rows !== 1) {
                        $upd->close();
                        throw new Exception("Απέτυχε η ανάθεση (ίσως ανατέθηκε ήδη από άλλο session).");
                    }
                    $upd->close();

                    // 4) Καταγραφή στο diplo_date (status = pending)
                    $ins = $connection->prepare("
                        INSERT INTO diplo_date (diplo_id, diplo_date, diplo_status)
                        VALUES (?, NOW(), 'pending')
                    ");
                    $ins->bind_param("i", $diplo_id);
                    $ins->execute();
                    $ins->close();

                    $connection->commit();
                    $message = "✅ Η ανάθεση ολοκληρώθηκε επιτυχώς.";

                } catch (Exception $e) {
                    $connection->rollback();
                    $message = "❌ Σφάλμα ανάθεσης: " . $e->getMessage();
                }
            }
        }
    }
}


if (isset($_GET['msg']) && $message === "") $message = $_GET['msg'];
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>Ανάθεση Θέματος</title>

<style>
body { font-family: Arial, sans-serif; background:#eef6ff; margin:0; }
.container { max-width:1100px; margin:40px auto; background:#fff; padding:20px 30px;
             border-radius:10px; box-shadow:0 0 10px rgba(0,0,0,.1); }

.top-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px; }
.subtitle { color:#555; font-size:.95rem; }

.btn { padding:8px 12px; border-radius:6px; text-decoration:none; color:#fff; font-size:.9rem; display:inline-block; }
.home { background:#198754; }
.home:hover { background:#157347; }
.logout { background:#dc3545; }
.logout:hover { background:#b52a37; }
.btn-primary { background:#0d6efd; border:none; cursor:pointer; }
.btn-success { background:#198754; border:none; cursor:pointer; }
.btn-primary:hover { background:#0b5ed7; }
.btn-success:hover { background:#157347; }

.card { background:#f8fbff; border:1px solid #dde7f5; border-radius:8px;
        padding:15px 20px; margin-bottom:20px; }

.input { width:100%; padding:10px; border:1px solid #cfe0f4; border-radius:6px; }
.input:focus { border-color:#0d6efd; outline:none; box-shadow:0 0 0 2px rgba(13,110,253,.15); }

table { width:100%; border-collapse:collapse; margin-top:10px; }
th, td { border:1px solid #dde7f5; padding:10px; vertical-align:top; }
th { background:#0d6efd; color:#fff; }
tr:nth-child(even){background:#ffffff;}
tr:nth-child(odd){background:#f8fbff;}

.alert { padding:10px; border-radius:6px; margin-bottom:15px;
         background:#e8f2ff; border:1px solid #b6d4fe; color:#084298; }

.badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:.85rem; font-weight:bold; }
.badge-free { background:#d1e7dd; color:#0f5132; border:1px solid #badbcc; }
.badge-assigned { background:#fff3cd; color:#664d03; border:1px solid #ffecb5; }

.center { text-align:center; }
small.muted { color:#666; }
</style>
</head>

<body>
<div class="container">

    <div class="top-bar">
        <div>
            <h1>📌 Ανάθεση Θέματος</h1>
            <div class="subtitle">Ανάθεση θέματος σε φοιτητή (pending)</div>
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
        <h3>Ελεύθερα Θέματα (pending + χωρίς φοιτητή)</h3>

        <?php if (empty($free_diplomas)): ?>
            <p class="subtitle">Δεν υπάρχουν διαθέσιμα θέματα.</p>
            <?php if (!empty($assigned_pending)): ?>
              <small class="muted">Υπάρχουν pending θέματα που έχουν ήδη φοιτητή, δείτε πιο κάτω.</small>
            <?php endif; ?>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Τίτλος</th>
                        <th>Περιγραφή</th>
                        <th style="width:160px;">Κατάσταση</th>
                        <th style="width:160px;">Ενέργεια</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($free_diplomas as $d): ?>
                    <tr>
                        <td><?= h($d['diplo_title']) ?></td>
                        <td><?= h($d['diplo_desc']) ?></td>
                        <td><span class="badge badge-free">Ελεύθερο</span></td>
                        <td class="center">
                            <a class="btn btn-primary" href="diplo_assign.php?select_diplo=<?= (int)$d['diplo_id'] ?>">
                               Επιλογή
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php if (!empty($assigned_pending)): ?>
      <div class="card">
        <h3>Pending που έχουν ήδη φοιτητή (για έλεγχο)</h3>
        <table>
          <thead>
            <tr>
              <th style="width:90px;">ID</th>
              <th>Τίτλος</th>
              <th style="width:220px;">Φοιτητής (ΑΜ)</th>
              <th style="width:160px;">Κατάσταση</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($assigned_pending as $d): ?>
            <tr>
              <td><?= (int)$d['diplo_id'] ?></td>
              <td><?= h($d['diplo_title']) ?></td>
              <td><?= h($d['diplo_student']) ?></td>
              <td><span class="badge badge-assigned">Pending με φοιτητή</span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if ($select_diplo > 0): ?>
        <div class="card">
            <h3>Αναζήτηση Φοιτητή για Ανάθεση (diplo_id: <?= (int)$select_diplo ?>)</h3>
            <form method="GET">
                <input type="hidden" name="select_diplo" value="<?= (int)$select_diplo ?>">
                <input class="input" type="text" name="search"
                       placeholder="ΑΜ, όνομα ή επώνυμο"
                       value="<?= h($_GET['search'] ?? '') ?>">
                <button class="btn btn-primary" style="width:100%; margin-top:10px;">
                    Αναζήτηση
                </button>
            </form>
        </div>

        <?php if ($students): ?>
            <div class="card">
                <h3>Αποτελέσματα</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ΑΜ</th>
                            <th>Ονοματεπώνυμο</th>
                            <th style="width:160px;">Ενέργεια</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students as $s): ?>
                        <tr>
                            <td><?= (int)$s['student_am'] ?></td>
                            <td><?= h(($s['student_surname'] ?? '')." ".($s['student_name'] ?? '')) ?></td>
                            <td class="center">
                                <form method="POST" onsubmit="return confirm('Ανάθεση θέματος στον φοιτητή;');">
                                    <input type="hidden" name="diplo_id" value="<?= (int)$select_diplo ?>">
                                    <input type="hidden" name="student_am" value="<?= (int)$s['student_am'] ?>">
                                    <button class="btn btn-success" name="assign">Ανάθεση</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (isset($_GET['search'])): ?>
            <div class="alert">Δεν βρέθηκαν φοιτητές.</div>
        <?php endif; ?>
    <?php endif; ?>

</div>
</body>
</html>
