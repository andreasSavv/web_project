<?php
session_start();
include("db_connect.php");
include("connected.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Login check (ίδιο με secretary_page)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

// Role check (ίδιο με secretary_page)
if ($_SESSION['role'] !== 'secretary') {
    header("Location: login.php");
    exit;
}

$diplomas = [];
$stmt = $connection->prepare("
    SELECT diplo_id, diplo_title, diplo_desc, diplo_status
    FROM diplo
    WHERE diplo_status IN ('active','under review','under_review')
    ORDER BY diplo_id DESC
");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $diplomas[] = $row;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <title>📚 Προβολή Διπλωματικών</title>
  <style>
    body { font-family: Arial, sans-serif; background: #eef6ff; margin: 0; padding: 0; }
    .container { max-width: 1100px; margin: 40px auto; background: #fff; padding: 20px 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
    .top-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .btn { text-decoration:none; padding:8px 12px; border-radius:6px; font-size:0.9rem; display:inline-block; }
    .btn-home { background:#198754; color:#fff; }
    .btn-home:hover { background:#157347; }
    .btn-logout { background:#dc3545; color:#fff; }
    .btn-logout:hover { background:#b52a37; }
    table { width:100%; border-collapse:collapse; margin-top:15px; }
    th, td { border:1px solid #dde7f5; padding:10px; text-align:left; }
    th { background:#0d6efd; color:#fff; }
    tr:nth-child(even) { background:#f8fbff; }
    .badge { padding:4px 8px; border-radius:10px; font-size:0.85rem; color:#fff; }
    .b-active { background:#198754; }
    .b-review { background:#fd7e14; }
    .details { background:#0d6efd; color:#fff; padding:6px 10px; border-radius:6px; text-decoration:none; }
    .details:hover { background:#0b5ed7; }
  </style>
</head>
<body>
<div class="container">

  <div class="top-bar">
    <h2>📚 Προβολή Διπλωματικών (Ενεργή / Υπό Εξέταση)</h2>
    <div>
      <a class="btn btn-home" href="secretary_page.php">Αρχική</a>
      <a class="btn btn-logout" href="logout.php">Αποσύνδεση</a>
    </div>
  </div>

  <?php if (empty($diplomas)): ?>
    <p>Δεν υπάρχουν διπλωματικές σε κατάσταση Ενεργή ή Υπό Εξέταση.</p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Θέμα</th>
          <th>Περιγραφή</th>
          <th>Κατάσταση</th>
          <th>Ενέργεια</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($diplomas as $d): ?>
          <?php
            $status = $d['diplo_status'] ?? '';
            $badgeClass = ($status === 'active') ? 'b-active' : 'b-review';
          ?>
          <tr>
            <td><?= (int)$d['diplo_id'] ?></td>
            <td><?= htmlspecialchars($d['diplo_title'] ?? '') ?></td>
            <td><?= htmlspecialchars($d['diplo_desc'] ?? '') ?></td>
            <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span></td>
            <td>
              <a class="details" href="secretary_diplo_details.php?diplo_id=<?= (int)$d['diplo_id'] ?>">
                Λεπτομέρειες
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

</div>
</body>
</html>
