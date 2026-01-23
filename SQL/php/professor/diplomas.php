<?php
session_start();
include("db_connect.php");
include("connected.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit;
}

$user = Professor_Connected($connection);
$profId = (int)($user['professor_user_id'] ?? $user['professor_id'] ?? 0);
if ($profId <= 0) die("Δεν βρέθηκαν στοιχεία καθηγητή.");

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// -------------------- diplo_date sync helper --------------------
function diplo_date_sync_current_status(mysqli $connection, int $diploId): void {
    // Αν δεν υπάρχει ο πίνακας diplo_date ή κάτι πάει στραβά, μην ρίξεις error στη σελίδα.
    try {
        // 1) current status από diplo
        $st = $connection->prepare("SELECT diplo_status FROM diplo WHERE diplo_id=? LIMIT 1");
        $st->bind_param("i", $diploId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$row) return;
        $curStatus = (string)($row['diplo_status'] ?? '');
        if ($curStatus === '') return;

        // 2) τελευταίο status στο diplo_date
        $lt = $connection->prepare("
            SELECT diplo_status
            FROM diplo_date
            WHERE diplo_id=?
            ORDER BY diplo_date DESC
            LIMIT 1
        ");
        $lt->bind_param("i", $diploId);
        $lt->execute();
        $last = $lt->get_result()->fetch_assoc();
        $lt->close();

        $lastStatus = $last ? (string)($last['diplo_status'] ?? '') : '';

        // 3) αν λείπει ή διαφέρει -> γράψε νέα γραμμή
        if ($lastStatus !== $curStatus) {
            $ins = $connection->prepare("
                INSERT INTO diplo_date (diplo_id, diplo_date, diplo_status)
                VALUES (?, NOW(), ?)
            ");
            $ins->bind_param("is", $diploId, $curStatus);
            $ins->execute();
            $ins->close();
        }
    } catch (Exception $e) {
        // σιωπηρά αγνόησε για να μη “σπάσει” η σελίδα
        return;
    }
}

// -------------------- Filters & Pagination --------------------
$status = trim($_GET['status'] ?? "");          // pending / active / under_review / finished / cancelled
$role   = trim($_GET['role'] ?? "");            // supervisor / member / ""
$q      = trim($_GET['q'] ?? "");               // search in title
$page   = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;

$perPage = 20;
$offset  = ($page - 1) * $perPage;

// -------------------- WHERE building (tight!) --------------------
$where = [];
$params = [];
$types  = "";

// βασικό: καθηγητής είναι είτε επιβλέπων είτε μέλος (EXISTS για trimelous)
$baseCondition = "(d.diplo_professor = ? OR EXISTS(
    SELECT 1 FROM trimelous t
    WHERE t.diplo_id = d.diplo_id
      AND (t.trimelous_professor1 = ? OR t.trimelous_professor2 = ? OR t.trimelous_professor3 = ?)
))";

$where[] = $baseCondition;
$params[] = $profId; $params[] = $profId; $params[] = $profId; $params[] = $profId;
$types .= "iiii";

if ($status !== "") {
    $where[] = "d.diplo_status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($role === "supervisor") {
    $where[] = "d.diplo_professor = ?";
    $params[] = $profId;
    $types .= "i";
} elseif ($role === "member") {
    $where[] = "d.diplo_professor <> ?";
    $params[] = $profId;
    $types .= "i";
}

if ($q !== "") {
    $where[] = "d.diplo_title LIKE ?";
    $params[] = "%".$q."%";
    $types .= "s";
}

$whereSql = "WHERE " . implode(" AND ", $where);

// -------------------- Count for pagination --------------------
$sqlCount = "
SELECT COUNT(*) AS c
FROM diplo d
$whereSql
";
$stmt = $connection->prepare($sqlCount);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$total = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;

// -------------------- Data query --------------------
$sql = "
SELECT
  d.diplo_id,
  d.diplo_title,
  d.diplo_status,
  d.diplo_student,
  d.diplo_professor,
  s.student_name,
  s.student_surname,
  CASE WHEN d.diplo_professor = ? THEN 'supervisor' ELSE 'member' END AS role_in_thesis
FROM diplo d
LEFT JOIN student s ON s.student_am = d.diplo_student
$whereSql
ORDER BY d.diplo_id DESC
LIMIT $perPage OFFSET $offset
";

// για το CASE WHEN (θέλει ένα extra profId μπροστά)
$params2 = $params;
$types2  = "i" . $types;
array_unshift($params2, $profId);

$stmt = $connection->prepare($sql);
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$res = $stmt->get_result();

$diplomas = [];
while ($r = $res->fetch_assoc()) $diplomas[] = $r;
$stmt->close();

// ✅ Sync diplo_date για τα αποτελέσματα της σελίδας (max 20 γραμμές -> safe)
foreach ($diplomas as $d) {
    diplo_date_sync_current_status($connection, (int)$d['diplo_id']);
}

// helpers
function status_gr($s) {
  $s = (string)$s;
  if ($s === 'pending') return 'Υπό Ανάθεση';
  if ($s === 'active') return 'Ενεργή';
  if ($s === 'finished') return 'Περατωμένη';
  if ($s === 'cancelled' || $s === 'cancel') return 'Ακυρωμένη';
  if ($s === 'under review' || $s === 'under_review') return 'Υπό Εξέταση';
  return $s ?: '-';
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>Λίστα Διπλωματικών</title>
<style>
body { font-family: Arial, sans-serif; background:#eef6ff; margin:0; }
.container { max-width:1100px; margin:40px auto; background:#fff; padding:20px 30px;
             border-radius:10px; box-shadow:0 0 10px rgba(0,0,0,.1); }
.top-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px; }
.subtitle { color:#555; font-size:.95rem; }
.btn { padding:8px 12px; border-radius:6px; text-decoration:none; color:#fff; font-size:.9rem; display:inline-block; }
.home { background:#198754; } .home:hover{background:#157347;}
.logout { background:#dc3545; } .logout:hover{background:#b52a37;}
.card { background:#f8fbff; border:1px solid #dde7f5; border-radius:8px; padding:15px 20px; margin-bottom:20px; }
.input, select { width:100%; padding:10px; border:1px solid #cfe0f4; border-radius:6px; }
.input:focus, select:focus { border-color:#0d6efd; outline:none; box-shadow:0 0 0 2px rgba(13,110,253,.15); }
.grid { display:grid; grid-template-columns: 1fr 1fr 1fr; gap:12px; }
@media(max-width:900px){ .grid{ grid-template-columns:1fr; } }
table{ width:100%; border-collapse:collapse; margin-top:10px; }
th,td{ border:1px solid #dde7f5; padding:10px; vertical-align:top; }
th{ background:#0d6efd; color:#fff; }
tr:nth-child(even){background:#fff;}
tr:nth-child(odd){background:#f8fbff;}
.badge{ display:inline-block; padding:4px 10px; border-radius:999px; font-size:.85rem; font-weight:bold; background:#6c757d; color:#fff; }
.pager{ display:flex; gap:8px; align-items:center; justify-content:center; margin-top:15px; flex-wrap:wrap; }
.pager a{ color:#0d6efd; text-decoration:none; padding:6px 10px; border:1px solid #cfe0f4; border-radius:6px; background:#fff; }
.pager .cur{ padding:6px 10px; border-radius:6px; background:#0d6efd; color:#fff; border:1px solid #0d6efd; }
.small{ color:#666; font-size:.92rem; }
</style>
</head>
<body>
<div class="container">

  <div class="top-bar">
    <div>
      <h1>📚 Λίστα Διπλωματικών</h1>
      <div class="subtitle">Επιβλέπων ή μέλος τριμελούς</div>
    </div>
    <div>
      <a class="btn home" href="professor_page.php">Αρχική</a>
      <a class="btn logout" href="logout.php">Αποσύνδεση</a>
    </div>
  </div>

  <div class="card">
    <form method="GET" class="grid">
      <div>
        <div class="small"><strong>Κατάσταση</strong></div>
        <select name="status">
          <option value="">Όλες</option>
          <option value="pending" <?= $status==='pending'?'selected':'' ?>>Υπό Ανάθεση</option>
          <option value="active" <?= $status==='active'?'selected':'' ?>>Ενεργή</option>
          <option value="under_review" <?= $status==='under_review'?'selected':'' ?>>Υπό Εξέταση</option>
          <option value="finished" <?= $status==='finished'?'selected':'' ?>>Περατωμένη</option>
          <option value="cancelled" <?= $status==='cancelled'?'selected':'' ?>>Ακυρωμένη</option>
        </select>
      </div>

      <div>
        <div class="small"><strong>Ρόλος</strong></div>
        <select name="role">
          <option value="">Όλοι</option>
          <option value="supervisor" <?= $role==='supervisor'?'selected':'' ?>>Επιβλέπων</option>
          <option value="member" <?= $role==='member'?'selected':'' ?>>Μέλος τριμελούς</option>
        </select>
      </div>

      <div>
        <div class="small"><strong>Αναζήτηση τίτλου</strong></div>
        <input class="input" type="text" name="q" value="<?= h($q) ?>" placeholder="π.χ. NLP">
      </div>

      <div style="grid-column:1/-1;">
        <button class="btn home" type="submit" style="border:none; cursor:pointer;">Φιλτράρισμα</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="small">Αποτελέσματα: <strong><?= (int)$total ?></strong></div>

    <?php if (empty($diplomas)): ?>
      <p class="subtitle">Δεν υπάρχουν διπλωματικές.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th style="width:90px;">ID</th>
            <th>Τίτλος</th>
            <th style="width:220px;">Φοιτητής</th>
            <th style="width:140px;">Ρόλος</th>
            <th style="width:170px;">Κατάσταση</th>
            <th style="width:160px;">Ενέργεια</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($diplomas as $d): ?>
          <?php
            $stud = "-";
            if (!empty($d['diplo_student'])) {
              $stud = $d['diplo_student']." - ".trim(($d['student_surname'] ?? '')." ".($d['student_name'] ?? ''));
            }
            $roleTxt = ($d['role_in_thesis']==='supervisor') ? "Επιβλέπων" : "Μέλος";
          ?>
          <tr>
            <td><?= (int)$d['diplo_id'] ?></td>
            <td><?= h($d['diplo_title']) ?></td>
            <td><?= h($stud) ?></td>
            <td><?= h($roleTxt) ?></td>
            <td><span class="badge"><?= h(status_gr($d['diplo_status'])) ?></span></td>
            <td>
              <a class="btn home" href="thesis_details.php?diplo_id=<?= (int)$d['diplo_id'] ?>">Λεπτομέρειες</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ($totalPages > 1): ?>
        <div class="pager">
          <?php
            $base = $_GET;
            for ($p=1; $p<=$totalPages; $p++) {
              $base['page'] = $p;
              $href = "diplomas.php?".http_build_query($base);
              if ($p === $page) echo '<span class="cur">'.$p.'</span>';
              else echo '<a href="'.h($href).'">'.$p.'</a>';
            }
          ?>
        </div>
      <?php endif; ?>

    <?php endif; ?>
  </div>

</div>
</body>
</html>
