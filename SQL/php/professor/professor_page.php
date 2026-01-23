<?php
session_start();
include("db_connect.php");
include("connected.php");

// Login check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

// Μόνο καθηγητής
if ($_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

// Στοιχεία καθηγητή
$professor = Professor_Connected($connection);

// Όνομα εμφάνισης
$displayName = "Καθηγητής";
if ($professor) {
    $first = $professor['professor_name'] ?? '';
    $last  = $professor['professor_surname'] ?? '';
    if (trim($first . $last) !== '') {
        $displayName = trim($first . ' ' . $last);
    }
}

// username από session (αν υπάρχει)
$username = $_SESSION['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Καθηγητής - Αρχική</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #eef6ff;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 900px;
            margin: 40px auto;
            background: #fff;
            padding: 20px 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1, h2, h3 { margin-top: 0; }
        .subtitle {
            color: #555;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        ul.menu {
            list-style: none;
            padding: 0;
        }
        ul.menu li {
            margin: 10px 0;
        }
        ul.menu a {
            text-decoration: none;
            color: #007bff;
            font-size: 1rem;
        }
        ul.menu a:hover {
            text-decoration: underline;
        }
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .logout-btn {
            text-decoration: none;
            padding: 6px 12px;
            background: #dc3545;
            color: #fff;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        .logout-btn:hover {
            background: #b52a37;
        }
        .card {
            padding: 15px 20px;
            border-radius: 8px;
            background: #f8fbff;
            border: 1px solid #dde7f5;
            margin-bottom: 20px;
        }
        .label {
            font-weight: bold;
            color: #333;
        }
        .value {
            color: #444;
        }
    </style>
</head>
<body>

<div class="container">

    <!-- Top bar -->
    <div class="top-bar">
        <div>
            <h1>Καλωσήρθες, <?php echo htmlspecialchars($displayName); ?>!</h1>
            <?php if ($username): ?>
                <div class="subtitle">
                    Username: <?php echo htmlspecialchars($username); ?>
                </div>
            <?php endif; ?>
        </div>
        <div>
            <a class="logout-btn" href="logout.php">Αποσύνδεση</a>
        </div>
    </div>

    <!-- Menu -->
    <div class="card">
        <h3>Μενού</h3>
        <ul class="menu">
            <li>
                <a href="add_diploma.php">
                    📌 1) Προβολή & Δημιουργία θεμάτων προς ανάθεση
                </a>
            </li>

            <li>
                <a href="diplo_assign.php">
                    👤 2) Αρχική ανάθεση θέματος σε φοιτητή
                </a>
            </li>

            <li>
                <a href="diplomas.php">
                    📚 3) Προβολή λίστας διπλωματικών
                </a>
            </li>

            <li>
                <a href="pending_inv.php">
                    ✉️ 4) Προβολή προσκλήσεων συμμετοχής σε τριμελή
                </a>
            </li>

            <li>
                <a href="prof_graphs.php">
                    📊 5) Προβολή στατιστικών
                </a>
            </li>

        </ul>
    </div>

    <!-- Account info -->
    <div class="card">
        <h3>Πληροφορίες λογαριασμού</h3>
        <p>
            <span class="label">Ρόλος:</span>
            <span class="value">Καθηγητής</span>
        </p>

        <?php if ($professor): ?>
            <?php if (!empty($professor['professor_user_id'])): ?>
                <p>
                    <span class="label">ID:</span>
                    <span class="value">
                        <?php echo htmlspecialchars($professor['professor_user_id']); ?>
                    </span>
                </p>
            <?php endif; ?>

            <?php if (!empty($professor['professor_email'])): ?>
                <p>
                    <span class="label">Email:</span>
                    <span class="value">
                        <?php echo htmlspecialchars($professor['professor_email']); ?>
                    </span>
                </p>
            <?php endif; ?>
        <?php else: ?>
            <p>Δεν βρέθηκαν επιπλέον στοιχεία καθηγητή.</p>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
