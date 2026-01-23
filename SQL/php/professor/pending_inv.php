<?php
session_start();
include("db_connect.php");
include("connected.php");

// Έλεγχος ρόλου
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

$user = Professor_Connected($connection);
$prof_user_id = (int)($user['professor_user_id'] ?? 0);
$message = "";

// ------------------------- Αποδοχή Πρόσκλησης -------------------------
if (isset($_POST['accept'])) {
    $diplo_id = (int)$_POST['diplo_id'];

    $stmt = $connection->prepare("
        UPDATE trimelous_invite
        SET invite_status='accept', invite_accept_date=NOW()
        WHERE diplo_id=? AND professor_user_id=?
    ");
    $stmt->bind_param("ii", $diplo_id, $prof_user_id);
    $stmt->execute();
    $message = "✅ Η πρόσκληση έγινε αποδεκτή.";
    $stmt->close();
}

// ------------------------- Απόρριψη Πρόσκλησης -------------------------
if (isset($_POST['deny'])) {
    $diplo_id = (int)$_POST['diplo_id'];

    $stmt = $connection->prepare("
        UPDATE trimelous_invite
        SET invite_status='deny', invite_deny_date=NOW()
        WHERE diplo_id=? AND professor_user_id=?
    ");
    $stmt->bind_param("ii", $diplo_id, $prof_user_id);
    $stmt->execute();
    $message = "❌ Η πρόσκληση απορρίφθηκε.";
    $stmt->close();
}

// ------------------------- Φόρτωση προσκλήσεων -------------------------
$stmt = $connection->prepare("
    SELECT t.*, d.diplo_title
    FROM trimelous_invite t
    JOIN diplo d ON d.diplo_id = t.diplo_id
    WHERE t.professor_user_id=?
      AND t.invite_status='pending'
    ORDER BY t.trimelous_date ASC
");
$stmt->bind_param("i", $prof_user_id);
$stmt->execute();
$res = $stmt->get_result();

$invitations = [];
while ($row = $res->fetch_assoc()) $invitations[] = $row;
$stmt->close();
?>

<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>Προσκλήσεις Τριμελούς</title>

<style>
body { font-family: Arial, sans-serif; background:#eef6ff; margin:0; }
.container { max-width:1100px; margin:40px auto; background:#fff;
             padding:20px 30px; border-radius:10px;
             box-shadow:0 0 10px rgba(0,0,0,.1); }

.top-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
.subtitle { color:#555; font-size:.95rem; }

.btn { padding:8px 12px; border-radius:6px; text-decoration:none;
       color:#fff; font-size:.9rem; border:none; cursor:pointer; }
.home { background:#198754; }
.logout { background:#dc3545; }
.accept { background:#198754; }
.deny { background:#dc3545; }

.card { background:#f8fbff; border:1px solid #dde7f5;
        border-radius:8px; padding:15px 20px; margin-bottom:20px; }

table { width:100%; border-collapse:collapse; }
th, td { border:1px solid #dde7f5; padding:10px; text-align:left; }
th { background:#0d6efd; color:#fff; }
tr:nth-child(even){background:#ffffff;}
tr:nth-child(odd){background:#f8fbff;}

.alert { padding:10px 12px; border-radius:6px; margin-bottom:15px; }
.alert-info { background:#e8f2ff; border:1px solid #b6d4fe; color:#084298; }

.actions { display:flex; gap:8px; }
.center { text-align:center; }
</style>
</head>

<body>
<div class="container">

    <div class="top-bar">
        <div>
            <h1>📩 Προσκλήσεις Τριμελούς</h1>
            <div class="subtitle">Συμμετοχή σε τριμελείς επιτροπές</div>
        </div>
        <div class="actions">
            <a class="btn home" href="professor_page.php">Αρχική</a>
            <a class="btn logout" href="logout.php">Αποσύνδεση</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="card">
        <?php if (!$invitations): ?>
            <p class="subtitle center">Δεν υπάρχουν ενεργές προσκλήσεις.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Θέμα Διπλωματικής</th>
                        <th>ΑΜ Φοιτητή</th>
                        <th>Ημ/νία Πρόσκλησης</th>
                        <th class="center">Ενέργειες</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($invitations as $inv): ?>
                    <tr>
                        <td><?= htmlspecialchars($inv['diplo_title']) ?></td>
                        <td><?= htmlspecialchars($inv['diplo_student_am']) ?></td>
                        <td><?= htmlspecialchars($inv['trimelous_date']) ?></td>
                        <td class="center">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="diplo_id" value="<?= (int)$inv['diplo_id'] ?>">
                                <button class="btn accept" name="accept">Αποδοχή</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="diplo_id" value="<?= (int)$inv['diplo_id'] ?>">
                                <button class="btn deny" name="deny">Απόρριψη</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
