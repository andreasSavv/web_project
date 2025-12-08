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
$prof_user_id = $user['professor_user_id'];
$message = "";

// ------------------------- Αποδοχή Πρόσκλησης -----------------------------------
if (isset($_POST['accept'])) {
    $diplo_id = $_POST['diplo_id'];

    $sql = "UPDATE trimelous_invite
            SET invite_status = 'accept'
            WHERE diplo_id = '$diplo_id'
            AND professor_user_id = '$prof_user_id'";

    if ($connection->query($sql)) {
        $message = "Η πρόσκληση έγινε αποδεκτή.";
    } else {
        $message = "Σφάλμα: " . $connection->error;
    }
}

// ------------------------- Απόρριψη Πρόσκλησης -----------------------------------
if (isset($_POST['deny'])) {
    $diplo_id = $_POST['diplo_id'];

    $sql = "UPDATE trimelous_invite
            SET invite_status = 'deny'
            WHERE diplo_id = '$diplo_id'
            AND professor_user_id = '$prof_user_id'";

    if ($connection->query($sql)) {
        $message = "Η πρόσκληση απορρίφθηκε.";
    } else {
        $message = "Σφάλμα: " . $connection->error;
    }
}

// ------------------------- Φόρτωση ενεργών προσκλήσεων ----------------------------
$sql = "SELECT t.*, d.diplo_title
        FROM trimelous_invite t
        JOIN diplo d ON d.diplo_id = t.diplo_id
        WHERE t.professor_user_id = '$prof_user_id'
        AND t.invite_status = 'pending'";

$result = $connection->query($sql);

$invitations = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $invitations[] = $row;
    }
}
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
        <div class="alert alert-info text-center mt-3"><?= $message ?></div>
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
                                    <input type="hidden" name="diplo_id" value="<?= $inv['diplo_id'] ?>">
                                    <button name="accept" class="btn btn-success btn-sm">
                                        Αποδοχή
                                    </button>
                                </form>

                                <form method="POST" class="d-inline ms-1">
                                    <input type="hidden" name="diplo_id" value="<?= $inv['diplo_id'] ?>">
                                    <button name="deny" class="btn btn-danger btn-sm">
                                        Απόρριψη
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

</div>

</body>
</html>
