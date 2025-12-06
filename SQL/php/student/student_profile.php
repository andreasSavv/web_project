<?php
session_start();
include("db_connect.php");
include("connected.php");

// 1. Έλεγχος ότι είναι συνδεδεμένος φοιτητής
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

// 2. Παίρνουμε τα στοιχεία του φοιτητή από τη βάση
$student = Student_Connected($connection);
if (!$student) {
    die("Δεν βρέθηκαν στοιχεία φοιτητή.");
}

// Μηνύματα feedback
$success_message = "";
$error_message   = "";

// 3. Αν υποβλήθηκε η φόρμα (POST), ενημερώνουμε τη βάση
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Παίρνουμε τιμές από τη φόρμα
    $street     = trim($_POST['street'] ?? '');
    $streetnum  = trim($_POST['streetnum'] ?? '');
    $city       = trim($_POST['city'] ?? '');
    $postcode   = trim($_POST['postcode'] ?? '');
    $tel        = trim($_POST['tel'] ?? '');

    // Απλός έλεγχος – προσαρμόζεις όσο αυστηρά θες
    if ($street === "" || $city === "" || $postcode === "") {
        $error_message = "Η διεύθυνση, η πόλη και ο ταχυδρομικός κώδικας είναι υποχρεωτικά πεδία.";
    } else {
        $sql = "UPDATE student 
                SET student_street = ?, 
                    student_streetnum = ?, 
                    student_city = ?, 
                    student_postcode = ?, 
                    student_tel = ?
                WHERE student_user_id = ?";

        $stmt = $connection->prepare($sql);
        if (!$stmt) {
            $error_message = "Σφάλμα προετοιμασίας query: " . $connection->error;
        } else {
            $userId = (int)$_SESSION['user_id'];
            $streetnumInt = ($streetnum !== '') ? (int)$streetnum : 0;
            $postcodeInt  = ($postcode !== '') ? (int)$postcode : 0;
            $telInt       = ($tel !== '') ? (int)$tel : 0;

            // s i s i i i  => street (string), streetnum (int), city (string), postcode (int), tel (int), userId (int)
            $stmt->bind_param("sisiii", $street, $streetnumInt, $city, $postcodeInt, $telInt, $userId);

            if ($stmt->execute()) {
                $success_message = "Τα στοιχεία σας ενημερώθηκαν με επιτυχία.";

                // Ενημερώνουμε και το τοπικό array $student για να φανούν οι αλλαγές αμέσως
                $student['student_street']     = $street;
                $student['student_streetnum']  = $streetnumInt;
                $student['student_city']       = $city;
                $student['student_postcode']   = $postcodeInt;
                $student['student_tel']        = $telInt;
            } else {
                $error_message = "Προέκυψε σφάλμα κατά την ενημέρωση: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Τιμές για τη φόρμα από τη ΒΔ
$street_value    = $student['student_street']    ?? '';
$streetnum_value = $student['student_streetnum'] ?? '';
$city_value      = $student['student_city']      ?? '';
$postcode_value  = $student['student_postcode']  ?? '';
$tel_value       = $student['student_tel']       ?? '';
$email_value     = $student['student_email']     ?? ''; // μόνο εμφάνιση
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Επεξεργασία Προφίλ Φοιτητή</title>
    <style>
        body { font-family: Arial, sans-serif; background: #eef6ff; margin: 0; padding: 0; }
        .container { max-width: 700px; margin: 40px auto; background: #fff; padding: 20px 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        input[type=text] { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; }
        .inline-group { display: flex; gap: 10px; }
        .inline-group > div { flex: 1; }
        .buttons { margin-top: 15px; }
        .buttons input[type=submit] { padding: 8px 16px; }
        .message-success { color: green; margin-top: 10px; }
        .message-error { color: red; margin-top: 10px; }
        .back-link { text-decoration: none; color: #007bff; }
        .back-link:hover { text-decoration: underline; }
        .readonly { background: #f5f5f5; }
        small { color: #666; }
    </style>
</head>
<body>
<div class="container">
    <a class="back-link" href="student_page.php">&larr; Πίσω στην αρχική φοιτητή</a>
    <h1>Επεξεργασία Προφίλ</h1>
    <p>Εδώ μπορείτε να ενημερώσετε τα στοιχεία επικοινωνίας σας. Το email ορίζεται από τη Γραμματεία και δεν μπορεί να αλλάξει.</p>

    <?php if ($success_message): ?>
        <div class="message-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="message-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <form method="post" action="">
        <label for="street">Οδός:</label>
        <input type="text" id="street" name="street" required value="<?php echo htmlspecialchars($street_value); ?>">

        <div class="inline-group">
            <div>
                <label for="streetnum">Αριθμός:</label>
                <input type="text" id="streetnum" name="streetnum" value="<?php echo htmlspecialchars($streetnum_value); ?>">
            </div>
            <div>
                <label for="city">Πόλη:</label>
                <input type="text" id="city" name="city" required value="<?php echo htmlspecialchars($city_value); ?>">
            </div>
            <div>
                <label for="postcode">Ταχυδρομικός κώδικας:</label>
                <input type="text" id="postcode" name="postcode" required value="<?php echo htmlspecialchars($postcode_value); ?>">
            </div>
        </div>

        <label for="tel">Τηλέφωνο επικοινωνίας (κινητό ή σταθερό):</label>
        <input type="text" id="tel" name="tel" value="<?php echo htmlspecialchars($tel_value); ?>">

        <label for="email">Email επικοινωνίας (μόνο για ανάγνωση):</label>
        <input type="text" id="email" value="<?php echo htmlspecialchars($email_value); ?>" class="readonly" disabled>
        <small>Για αλλαγή email επικοινωνήστε με τη Γραμματεία.</small>

        <div class="buttons">
            <input type="submit" value="Αποθήκευση αλλαγών">
        </div>
    </form>
</div>
</body>
</html>
