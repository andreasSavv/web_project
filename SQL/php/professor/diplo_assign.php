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
$prof_id = $user['professor_user_id'];
$message = "";
$students = [];
$diplomas = [];


// ------------------ Φόρτωση διαθέσιμων θεμάτων (χωρίς φοιτητή) ------------------
$sql = "SELECT * FROM diplo 
        WHERE diplo_professor = '$prof_id'
        AND diplo_student IS NULL 
        AND diplo_status = 'pending'";

$result = $connection->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $diplomas[] = $row;
    }
}

// ------------------ Αναζήτηση φοιτητή ------------------
if (isset($_GET['search'])) {
    $term = mysqli_real_escape_string($connection, $_GET['search']);

    $q = "SELECT * FROM student 
          WHERE student_am LIKE '%$term%' 
          OR student_name LIKE '%$term%' 
          OR student_surname LIKE '%$term%'";

    $res = $connection->query($q);

    while ($row = $res->fetch_assoc()) {
        $students[] = $row;
    }
}

// ------------------ Ανάθεση θέματος ------------------
if (isset($_POST['assign'])) {
    $diplo_id = $_POST['diplo_id'];
    $student_id = $_POST['student_am'];
    
    $check = $connection->query("
        SELECT diplo_id 
        FROM diplo 
        WHERE diplo_student = '$student_id'
        AND diplo_id != '$diplo_id'
    ");

    if ($check->num_rows > 0) {
        $message = "⚠ Ο φοιτητής έχει ήδη αναλάβει άλλη διπλωματική και δεν μπορεί να πάρει δεύτερη.";
    }else{
    $update = "UPDATE diplo SET 
                diplo_student = '$student_id',
                diplo_status = 'pending'
               WHERE diplo_id = '$diplo_id'";
               

    if ($connection->query($update)) {
        $message = "Το θέμα ανατέθηκε προσωρινά στον φοιτητή!";
    } else {
        $message = "Σφάλμα: " . $connection->error;
    }
    }
}

// ------------------ Ανάκληση ανάθεσης ------------------
if (isset($_POST['cancel_assignment'])) {
    $diplo_id = $_POST['diplo_id'];

    $undo = "UPDATE diplo SET diplo_student = NULL WHERE diplo_id = '$diplo_id'";

    if ($connection->query($undo)) {
        $message = "Η ανάθεση αναιρέθηκε επιτυχώς.";
    } else {
        $message = "Σφάλμα: " . $connection->error;
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

<!-- NAVBAR -->
<nav class="navbar navbar-dark bg-dark p-2">
    <a class="navbar-brand ms-3">Η Πλατφόρμα</a>
    <a href="professor_page.php" class="btn btn-success ms-auto me-2">Αρχική</a>
    <a href="logout.php" class="btn btn-danger me-3">Αποσύνδεση</a>
</nav>

<div class="container-fluid">
<div class="row">

<?php include "sidebar.php"; ?>

<main class="col-md-8 col-lg-9 mt-4">

    <h2 class="text-center fw-bold">Ανάθεση Θέματος σε Φοιτητή</h2>

    <?php if ($message): ?>
        <div class="alert alert-info mt-3 text-center"><?= $message ?></div>
    <?php endif; ?>

    <!-- ΔΙΑΘΕΣΙΜΑ ΘΕΜΑΤΑ -->
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
                            <td><?= htmlspecialchars($d['diplo_title']) ?></td>
                            <td><?= htmlspecialchars($d['diplo_desc']) ?></td>
                            <td>
                                <a href="?select_diplo=<?= $d['diplo_id'] ?>" 
                                   class="btn btn-primary btn-sm">
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
        $selected_diplo = $_GET['select_diplo'];
    ?>

    <!-- ΦΟΡΜΑ ΑΝΑΖΗΤΗΣΗΣ ΦΟΙΤΗΤΗ -->
    <div class="card mt-5 shadow-sm">
        <div class="card-header fw-bold">Αναζήτηση Φοιτητή</div>

        <div class="card-body">
            <form method="GET">
                <input type="hidden" name="select_diplo" value="<?= $selected_diplo ?>">
                <input type="text" class="form-control" name="search" placeholder="ΑΜ, όνομα ή επώνυμο">
                <button class="btn btn-primary w-100 mt-2">Αναζήτηση</button>
            </form>
        </div>
    </div>

    <!-- ΑΠΟΤΕΛΕΣΜΑΤΑ ΑΝΑΖΗΤΗΣΗΣ -->
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
                            <td><?= $s['student_am'] ?></td>
                            <td><?= $s['student_name'] . " " . $s['student_surname'] ?></td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="diplo_id" value="<?= $selected_diplo ?>">
                                    <input type="hidden" name="student_am" value="<?= $s['student_am'] ?>">
                                    <button class="btn btn-success btn-sm" name="assign">
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
    <?php endif; ?>

    <?php endif; ?>

</main>
</div>
</div>

</body>
</html>
