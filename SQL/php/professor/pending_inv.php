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
$prof_id = $user['professor_id'];
$message = "";

// ------------------------- Ενέργεια Αποδοχής ----------------------------------
if (isset($_POST['accept'])) {

    $invite_id = $_POST['invite_id'];

    $sql = "UPDATE committee_invitations 
            SET status='accepted' 
            WHERE invitation_id='$invite_id' 
            AND professor_id='$prof_id'";

    if ($connection->query($sql)) {
        $message = "Η πρόσκληση έγινε αποδεκτή.";
    } else {
        $message = "Σφάλμα: " . $connection->error;
    }
}

// ------------------------- Ενέργεια Απόρριψης ----------------------------------
if (isset($_POST['reject'])) {

    $invite_id = $_POST['invite_id'];

    $sql = "UPDATE committee_invitations 
            SET status='rejected' 
            WHERE invitation_id='$invite_id' 
            AND professor_id='$prof_id'";

    if ($connection->query($sql)) {
        $message = "Η πρόσκληση απορρίφθηκε.";
    } else {
        $message = "Σφάλμα: " . $connection->error;
    }
}

// ------------------------- Φόρτωση Ενεργών Προσκλήσεων -------------------------
$sql = "SELECT i.invitation_id, i.status, 
               d.diplo_title, d.diplo_id,
               p.professor_name AS sender_name
        FROM committee_invitations i
        JOIN diplo d ON d.diplo_id = i.diplo_id
        JOIN professor p ON p.professor_id = i.sender_professor_id
        WHERE i.professor_id='$prof_id'
        AND i.status='pending'";

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

    <h2 class="fw-bold text-center">Προσκλήσεις Συμμετοχής σε Τριμελείς</h2>

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
                            <th>Θέμα</th>
                            <th>Αποστολέας</th>
                            <th>Κατάσταση</th>
                            <th>Ενέργειες</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($invitations as $inv): ?>
                        <tr>
                            <td><?= htmlspecialchars($inv['diplo_title']) ?></td>
                            <td><?= htmlspecialchars($inv['sender_name']) ?></td>
                            <td>
                                <span class="badge bg-warning text-dark">
                                    Σε αναμονή
                                </span>
                            </td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="invite_id" value="<?= $inv['invitation_id'] ?>">
                                    <button name="accept" class="btn btn-success btn-sm">
                                        Αποδοχή
                                    </button>
                                </form>

                                <form method="POST" class="d-inline ms-1">
                                    <input type="hidden" name="invite_id" value="<?= $inv['invitation_id'] ?>">
                                    <button name="reject" class="btn btn-danger btn-sm">
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
