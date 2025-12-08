<?php
session_start();
include("db_connect.php");
include("connected.php");

// Έλεγχος ότι είναι καθηγητής
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

// Στοιχεία καθηγητή
$user = Professor_Connected($connection);
$prof_id = $user['professor_id'];
$name = $user['professor_name'];

// ------------------ ΔΗΜΙΟΥΡΓΙΑ ΝΕΟΥ ΘΕΜΑΤΟΣ --------------------
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = mysqli_real_escape_string($connection, $_POST['title']);
    $desc = mysqli_real_escape_string($connection, $_POST['desc']);
    $status = "under assignment"; // Πάντα υπό ανάθεση

    // Upload PDF
    $pdf_name = "";
    if (!empty($_FILES['pdf']['name'])) {
        $pdf_name = time() . "_" . basename($_FILES["pdf"]["name"]);
        $target = "uploads/" . $pdf_name;

        move_uploaded_file($_FILES["pdf"]["tmp_name"], $target);
    }

    $query = "INSERT INTO diplo (diplo_title, diplo_desc, diplo_pdf, diplo_status, diplo_professor)
              VALUES ('$title', '$desc', '$pdf_name', 'pending', '$prof_id')";

    if ($connection->query($query)) {
        $message = "Το θέμα προστέθηκε επιτυχώς!";
    } else {
        $message = "Σφάλμα: " . $connection->error;
    }
}

// ------------------ ΠΡΟΒΟΛΗ ΘΕΜΑΤΩΝ --------------------
$topics = [];
$sql = "SELECT * FROM diplo WHERE diplo_professor = '$prof_id' AND diplo_status = 'pending'";
$res = $connection->query($sql);

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $topics[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Θέματα Προς Ανάθεση</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand">Η Πλατφόρμα</a>
    <a href="professor_page.php" class="btn btn-success ms-2">Αρχική</a>

    <div class="ms-auto">
        <a href="logout.php" class="btn btn-danger">Αποσύνδεση</a>
    </div>
  </div>
</nav>

<div class="container-fluid">
<div class="row">

<!-- SIDEBAR -->
<?php include "sidebar.php"; ?>

<main class="col-md-8 col-lg-9 ms-sm-auto px-4">

    <h2 class="mt-4 text-center fw-bold">Θέματα Προς Ανάθεση</h2>

    <!-- Μήνυμα επιτυχίας/αποτυχίας -->
    <?php if ($message): ?>
        <div class="alert alert-info mt-3 text-center"><?= $message ?></div>
    <?php endif; ?>

    <!-- ΦΟΡΜΑ ΔΗΜΙΟΥΡΓΙΑΣ -->
    <div class="card mt-4 shadow-sm">
        <div class="card-header fw-bold">Δημιουργία Νέου Θέματος</div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">

                <label class="form-label">Τίτλος</label>
                <input type="text" name="title" class="form-control" required>

                <label class="form-label mt-3">Περιγραφή</label>
                <textarea name="desc" class="form-control" required></textarea>

                <label class="form-label mt-3">Ανέβασμα PDF (προαιρετικό)</label>
                <input type="file" name="pdf" class="form-control">

                <button type="submit" class="btn btn-primary mt-3 w-100">Καταχώρηση</button>
            </form>
        </div>
    </div>

    <!-- ΛΙΣΤΑ ΘΕΜΑΤΩΝ -->
    <h3 class="mt-5">Τα Θέματά μου</h3>

    <?php if (count($topics) === 0): ?>
        <p class="text-muted">Δεν έχετε δημιουργήσει θέματα προς ανάθεση.</p>
    <?php else: ?>
        <div class="table-responsive mt-3">
            <table class="table table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Τίτλος</th>
                        <th>Περιγραφή</th>
                        <th>PDF</th>
                        <th>Ενέργειες</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($topics as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t['diplo_title']) ?></td>
                        <td><?= htmlspecialchars($t['diplo_desc']) ?></td>
                        <td>
                            <?php if ($t['diplo_pdf']): ?>
                                <a href="uploads/<?= $t['diplo_pdf'] ?>" target="_blank">Προβολή</a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="edit_topic.php?id=<?= $t['diplo_id'] ?>" class="btn btn-warning btn-sm">Επεξεργασία</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>

            </table>
        </div>
    <?php endif; ?>

</main>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
