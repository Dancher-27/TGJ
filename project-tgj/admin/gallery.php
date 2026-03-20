<?php
session_start();
if (!isset($_SESSION['tgj_admin'])) { header('Location: login.php'); exit(); }
require_once '../includes/connection.php';
$msg = '';

if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $row = $conn->query("SELECT image FROM gallery WHERE id=$did")->fetch_assoc();
    if ($row && $row['image']) @unlink(__DIR__ . '/../uploads/gallery/' . $row['image']);
    $conn->query("DELETE FROM gallery WHERE id=$did");
    $msg = 'ok:Afbeelding verwijderd.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_gallery'])) {
    $tit  = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $cat  = trim($_POST['category'] ?? 'Art');

    if (!empty($_FILES['image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $imgName = uniqid('gal_') . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/../uploads/gallery/' . $imgName);
            $stmt = $conn->prepare("INSERT INTO gallery (title,description,image,category) VALUES (?,?,?,?)");
            $stmt->bind_param("ssss",$tit,$desc,$imgName,$cat);
            $stmt->execute();
            $msg = 'ok:Afbeelding toegevoegd.';
        } else {
            $msg = 'err:Ongeldig bestandstype.';
        }
    } else {
        $msg = 'err:Geen afbeelding geselecteerd.';
    }
}

$items = $conn->query("SELECT * FROM gallery ORDER BY uploaded_at DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8"><title>Gallery — Admin TGJ</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="page admin-body">
<?php include 'admin-nav.php'; ?>
<div class="admin-container">
    <div class="admin-topbar">
        <h1>🎨 Gallery</h1>
    </div>

    <?php if ($msg): [$t,$m] = explode(':', $msg, 2); ?>
    <div class="flash flash-<?= $t === 'ok' ? 'ok' : 'err' ?>"><?= htmlspecialchars($m) ?></div>
    <?php endif; ?>

    <div class="admin-form-card">
        <h2>Afbeelding uploaden</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group">
                    <label>Titel *</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Categorie</label>
                    <input type="text" name="category" class="form-control" value="Art" placeholder="Art, Cover, Sketch...">
                </div>
                <div class="form-group">
                    <label>Afbeelding *</label>
                    <input type="file" name="image" class="form-control" accept="image/*" required>
                </div>
                <div class="form-group">
                    <label>Beschrijving</label>
                    <input type="text" name="description" class="form-control">
                </div>
            </div>
            <button type="submit" name="save_gallery" class="btn btn-gold">Uploaden</button>
        </form>
    </div>

    <!-- Grid -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;">
    <?php foreach ($items as $item): ?>
    <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;">
        <img src="../uploads/gallery/<?= htmlspecialchars($item['image']) ?>" style="width:100%;aspect-ratio:1;object-fit:cover;" alt="">
        <div style="padding:10px 12px;">
            <div style="font-weight:700;font-size:.82rem;margin-bottom:2px;"><?= htmlspecialchars($item['title']) ?></div>
            <div style="font-size:.7rem;color:var(--muted);margin-bottom:8px;"><?= htmlspecialchars($item['category']) ?></div>
            <a href="?delete=<?= $item['id'] ?>" class="btn-del" style="font-size:.72rem;" onclick="return confirm('Verwijderen?')">Verwijderen</a>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($items)): ?>
    <div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--muted);">Geen afbeeldingen</div>
    <?php endif; ?>
    </div>
</div>
</body>
</html>
