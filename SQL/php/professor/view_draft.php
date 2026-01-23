<?php
// --- Draft (μέσα στο thesis_details) ---
$draft = null;
$stD = $connection->prepare("
  SELECT draft_diplo_pdf, draft_links
  FROM draft
  WHERE diplo_id = ?
  LIMIT 1
");
$stD->bind_param("i", $diploId);
$stD->execute();
$draft = $stD->get_result()->fetch_assoc();
$stD->close();
?>
