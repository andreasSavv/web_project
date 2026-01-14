<?php
session_start();
include("db_connect.php");
include("connected.php");

// Έλεγχος ρόλου
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

// Στοιχεία καθηγητή
$user = Professor_Connected($connection);
$prof_user_id = (int)($user['professor_user_id'] ?? 0);
$message = "";

// ------------------------- Αποδοχή Πρόσκλησης -----------------------------------
if (isset($_POST['accept'])) {
    $diplo_id = (int)$_POST['diplo_id'];

    $sql = "UPDATE trimelous_invite
            SET invite_status = 'accept',
                invite_accept_date = NOW()
            WHERE diplo_id = ?
              AND professor_user_id = ?";

    $stmt = $connection->prepare($sql);
    $stmt->bind_param("ii", $diplo_id, $prof_user_id);

    if ($stmt->execute()) {
        $message = "Η πρόσκληση έγινε αποδεκτή.";
    } else {
        $message = "Σφάλμα: " . $stmt->error;
    }
    $stmt->close();
}

// ------------------------- Απόρριψη Πρόσκλησης -----------------------------------
if (isset($_POST['deny'])) {
    $diplo_id = (int)$_POST['diplo_id'];

    $sql = "UPDATE trimelous_invite
            SET invite_status = 'deny',
                invite_deny_date = NOW()
            WHERE diplo_id = ?
              AND professor_user_id = ?";

    $stmt = $connection->prepare($sql);
    $stmt->bind_param("ii", $diplo_id, $prof_user_id);

    if ($stmt->execute()) {
        $message = "Η πρόσκληση απορρίφθηκε.";
    } else {
        $message = "Σφάλμα: " . $stmt->error;
    }
    $stmt->close();
}

// ------------------------- Φόρτωση ενεργών προσκλήσεων ----------------------------
$sql = "SELECT t.*, d.diplo_title
        FROM trimelous_invite t
        JOIN diplo d ON d.diplo_id = t.diplo_id
        WHERE t.professor_user_id = ?
          AND t.invite_status = 'pending'
        ORDER BY t.trimelous_date ASC";

$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $prof_user_id);
$stmt->execute();
$result = $stmt->get_result();

$invitations = [];
while ($row = $result->fetch_assoc()) {
    $invitations[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Προσκλήσεις Τριμελούς</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

<nav class="navbar navbar-dark bg-dark p-2">
    <a class="navbar-brand ms-3">Προσκλήσεις Τριμελούς</a>
    <a href="professor_page.php" class="btn btn-success ms-auto me-2">Αρχική</a>
    <a href="logout.php" class="btn btn-danger me-3">Αποσύνδεση</a>
</nav>

<div class="container mt-4">

    <h2 class="fw-bold text-center">Προσκλήσεις Συμμετοχής σε Τριμελείς Επιτροπές</h2>

    <?php if ($message): ?>
        <div class="alert alert-info text-center mt-3"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mt-4">
        <div class="card-header fw-bold">Ενεργές Προσκλήσεις</div>
        <div class="card-body">

            <?php if (empty($invitations)): ?>
                <p class="text-muted text-center">
                    Δεν υπάρχουν ενεργές προσκλήσεις αυτή τη στιγμή.
                </p>
            <?php else: ?>
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Θέμα Διπλωματικής</th>
                            <th>Αριθμός Μητρώου Φοιτητή</th>
                            <th>Ημερομηνία Πρόσκλησης</th>
                            <th>Ενέργειες</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($invitations as $inv): ?>
                        <tr>
                            <td><?= htmlspecialchars($inv['diplo_title']) ?></td>
                            <td><?= htmlspecialchars($inv['diplo_student_am']) ?></td>
                            <td><?= htmlspecialchars($inv['trimelous_date']) ?></td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="diplo_id" value="<?= (int)$inv['diplo_id'] ?>">
                                    <button name="accept" class="btn btn-success btn-sm">Αποδοχή</button>
                                </form>

                                <form method="POST" class="d-inline ms-1">
                                    <input type="hidden" name="diplo_id" value="<?= (int)$inv['diplo_id'] ?>">
                                    <button name="deny" class="btn btn-danger btn-sm">Απόρριψη</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>

                </table>
            <?php endif; ?>
        </div>
    </div>

</div>

</body>
</html>
