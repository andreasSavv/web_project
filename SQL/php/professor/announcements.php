<?php
// --- Announcement generator (μέσα στο thesis_details) ---
$announcementText = "";

function presentation_complete_row($r) {
    if (!$r) return false;
    if (empty($r['presentation_date']) || empty($r['presentation_time']) || empty($r['presentation_way'])) return false;
    if ($r['presentation_way'] === 'online') return !empty(trim($r['presentation_link'] ?? ''));
    if ($r['presentation_way'] === 'in person') return !empty(trim($r['presentation_room'] ?? ''));
    return false;
}

$presentation = null;
$stP = $connection->prepare("
  SELECT presentation_date, presentation_time, presentation_way, presentation_room, presentation_link
  FROM presentation
  WHERE diplo_id = ?
  LIMIT 1
");
$stP->bind_param("i", $diploId);
$stP->execute();
$presentation = $stP->get_result()->fetch_assoc();
$stP->close();

$canAnnounce = ($isSupervisor && ($row['diplo_status'] ?? '') === 'under_review' && presentation_complete_row($presentation));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_announcement'])) {
    if (!$canAnnounce) {
        $message = "⚠ Η ανακοίνωση παράγεται μόνο όταν είστε επιβλέπων, η ΔΕ είναι Υπό Εξέταση και τα στοιχεία παρουσίασης είναι πλήρη.";
    } else {

        $studName = trim(($row['student_surname'] ?? '') . " " . ($row['student_name'] ?? ''));
        if ($studName === "") $studName = "AM: " . ($row['diplo_student'] ?? '-');

        $dateStr = date("d/m/Y", strtotime($presentation['presentation_date']));
        $timeStr = substr((string)$presentation['presentation_time'], 0, 5);

        $wayStr = ($presentation['presentation_way'] === 'online') ? "Διαδικτυακά" : "Δια ζώσης";
        $placeStr = ($presentation['presentation_way'] === 'online')
            ? ("Σύνδεσμος παρακολούθησης: " . ($presentation['presentation_link'] ?? '—'))
            : ("Αίθουσα: " . ($presentation['presentation_room'] ?? '—'));

        // draft (αν υπάρχει)
        $draftPdf = trim($draft['draft_diplo_pdf'] ?? '');
        $draftLinks = trim($draft['draft_links'] ?? '');

        $material = "";
        if ($draftPdf !== "" || $draftLinks !== "") {
            $material .= "\n\nΣχετικό υλικό:";
            if ($draftPdf !== "") $material .= "\n- Πρόχειρο κείμενο (PDF): " . $draftPdf;
            if ($draftLinks !== "") $material .= "\n- Σύνδεσμοι:\n" . $draftLinks;
        }

        $announcementText =
"ΑΝΑΚΟΙΝΩΣΗ ΠΑΡΟΥΣΙΑΣΗΣ ΔΙΠΛΩΜΑΤΙΚΗΣ ΕΡΓΑΣΙΑΣ

Τίτλος Διπλωματικής: " . ($row['diplo_title'] ?? '') . "
Φοιτητής/τρια: " . $studName . "

Η παρουσίαση/εξέταση της διπλωματικής εργασίας θα πραγματοποιηθεί:
Ημερομηνία: " . $dateStr . "
Ώρα: " . $timeStr . "
Τρόπος: " . $wayStr . "
" . $placeStr . "

Η παρουσίαση είναι ανοικτή για παρακολούθηση." . $material;

        $message = "✅ Η ανακοίνωση δημιουργήθηκε.";
    }
}
?>
