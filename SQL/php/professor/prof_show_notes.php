<?php
// --- Notes (μέσα στο thesis_details) ---
$notesMsg = "";
$notesErr = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_note'])) {
    $noteText = trim($_POST['note_text'] ?? "");

    if ($noteText === "") {
        $notesErr = "❌ Η σημείωση δεν μπορεί να είναι κενή.";
    } elseif (mb_strlen($noteText, 'UTF-8') > 300) {
        $notesErr = "❌ Η σημείωση πρέπει να είναι μέχρι 300 χαρακτήρες.";
    } else {
        $ins = $connection->prepare("
          INSERT INTO professor_notes (diplo_id, professor_user_id, notes)
          VALUES (?, ?, ?)
        ");
        $ins->bind_param("iis", $diploId, $profUserId, $noteText);
        $ins->execute();
        $ins->close();

        header("Location: thesis_details.php?diplo_id=".$diploId."&msg=".urlencode("✅ Η σημείωση αποθηκεύτηκε."));
        exit;
    }
}

// φόρτωση σημειώσεων ΜΟΝΟ του ίδιου καθηγητή
$myNotes = [];
$stN = $connection->prepare("
  SELECT notes
  FROM professor_notes
  WHERE diplo_id = ? AND professor_user_id = ?
");
$stN->bind_param("ii", $diploId, $profUserId);
$stN->execute();
$resN = $stN->get_result();
while ($r = $resN->fetch_assoc()) $myNotes[] = $r['notes'];
$stN->close();
?>
