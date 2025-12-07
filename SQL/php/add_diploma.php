<?php
require 'db_connect.php';


if (isset($_POST['submit'])) {

    $title = $_POST['title'];
    $summary = $_POST['summary'];
    $pdfPath = null;

    // PDF upload
    if (!empty($_FILES['pdf']['name'])) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir);

        $fileName = time() . "_" . basename($_FILES["pdf"]["name"]);
        $targetFile = $targetDir . $fileName;

        if (move_uploaded_file($_FILES["pdf"]["tmp_name"], $targetFile)) {
            $pdfPath = $targetFile;
        }
    }

    // Εισαγωγή στη βάση
    $stmt = $pdo->prepare("INSERT INTO thesis_topics (title, summary, pdf_path) VALUES (?, ?, ?)");
    $stmt->execute([$title, $summary, $pdfPath]);

    echo "<p style='color:green;'>Το θέμα καταχωρήθηκε επιτυχώς!</p>";
}

$topics = $pdo->query("SELECT * FROM thesis_topics ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>Θέματα Διπλωματικών</title>
</head>
<body>

<h2>➤ Δημιουργία Νέου Θέματος</h2>

<form action="" method="post" enctype="multipart/form-data">
    <label>Τίτλος:</label><br>
    <input type="text" name="title" required><br><br>

    <label>Σύνοψη:</label><br>
    <textarea name="summary" rows="4" cols="50"></textarea><br><br>

    <label>Ανέβασμα PDF:</label><br>
    <input type="file" name="pdf" accept="application/pdf"><br><br>

    <input type="submit" name="submit" value="Καταχώρηση">
</form>

<hr>

<h2>➤ Λίστα Θεμάτων</h2>

<table border="1" cellpadding="5">
    <tr>
        <th>ID</th>
        <th>Τίτλος</th>
        <th>Σύνοψη</th>
        <th>PDF</th>
        <th>Επεξεργασία</th>
    </tr>

    <?php foreach ($topics as $topic): ?>
    <tr>
        <td><?= $topic['id'] ?></td>
        <td><?= htmlspecialchars($topic['title']) ?></td>
        <td><?= nl2br(htmlspecialchars($topic['summary'])) ?></td>
        <td>
            <?php if ($topic['pdf_path']): ?>
                <a href="<?= $topic['pdf_path'] ?>" target="_blank">Προβολή PDF</a>
            <?php else: ?>
                -
            <?php endif; ?>
        </td>
        <td>
            <a href="edit.php?id=<?= $topic['id'] ?>">Επεξεργασία</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

</body>
</html>
