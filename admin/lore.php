<?php
session_start();
if (!isset($_SESSION['tgj_admin'])) { header('Location: login.php'); exit(); }
require_once '../includes/connection.php';
$msg = '';

if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $row = $conn->query("SELECT image FROM lore WHERE id=$did")->fetch_assoc();
    if ($row && $row['image']) @unlink(__DIR__ . '/../uploads/lore/' . $row['image']);
    $conn->query("DELETE FROM lore WHERE id=$did");
    $msg = 'ok:Entry verwijderd.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lore'])) {
    $eid  = (int)($_POST['id'] ?? 0);
    $tit  = trim($_POST['title'] ?? '');
    $cat  = trim($_POST['category'] ?? 'Algemeen');
    $cont = trim($_POST['content'] ?? '');
    $ord  = (int)($_POST['sort_order'] ?? 0);

    $imgName = '';
    if (!empty($_FILES['image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $imgName = uniqid('lore_') . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/../uploads/lore/' . $imgName);
        }
    }

    if ($eid) {
        $old = $conn->query("SELECT image FROM lore WHERE id=$eid")->fetch_assoc();
        if ($imgName && $old['image']) @unlink(__DIR__ . '/../uploads/lore/' . $old['image']);
        $useImg = $imgName ?: ($old['image'] ?? '');
        $stmt = $conn->prepare("UPDATE lore SET title=?,category=?,content=?,image=?,sort_order=? WHERE id=?");
        $stmt->bind_param("ssssii",$tit,$cat,$cont,$useImg,$ord,$eid);
        $stmt->execute();
        $msg = 'ok:Entry bijgewerkt.';
    } else {
        $stmt = $conn->prepare("INSERT INTO lore (title,category,content,image,sort_order) VALUES (?,?,?,?,?)");
        $stmt->bind_param("ssssi",$tit,$cat,$cont,$imgName,$ord);
        $stmt->execute();
        $msg = 'ok:Entry toegevoegd.';
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $edit = $conn->query("SELECT * FROM lore WHERE id=".(int)$_GET['edit'])->fetch_assoc();
}
$entries = $conn->query("SELECT * FROM lore ORDER BY sort_order ASC, id ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8"><title>Lore — Admin TGJ</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="page admin-body">
<?php include 'admin-nav.php'; ?>
<div class="admin-container">
    <div class="admin-topbar">
        <h1>🌍 Lore</h1>
        <a href="?add=1" class="btn btn-gold btn-sm">+ Entry toevoegen</a>
    </div>

    <?php if ($msg): [$t,$m] = explode(':', $msg, 2); ?>
    <div class="flash flash-<?= $t === 'ok' ? 'ok' : 'err' ?>"><?= htmlspecialchars($m) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['add']) || $edit): ?>
    <div class="admin-form-card">
        <h2><?= $edit ? 'Entry bewerken' : 'Nieuwe entry' ?></h2>
        <form method="POST" enctype="multipart/form-data">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
            <div class="form-grid">
                <div class="form-group form-full">
                    <label>Titel *</label>
                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($edit['title'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Categorie (bijv. Magie, Wereld, Fractie)</label>
                    <input type="text" name="category" class="form-control" value="<?= htmlspecialchars($edit['category'] ?? 'Algemeen') ?>">
                </div>
                <div class="form-group">
                    <label>Volgorde</label>
                    <input type="number" name="sort_order" class="form-control" value="<?= $edit['sort_order'] ?? 0 ?>">
                </div>
                <div class="form-group">
                    <label>Afbeelding</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <?php if (!empty($edit['image'])): ?>
                    <img src="../uploads/lore/<?= htmlspecialchars($edit['image']) ?>" style="height:60px;margin-top:8px;border-radius:6px;">
                    <?php endif; ?>
                </div>
                <div class="form-group form-full">
                    <label>Inhoud</label>
                    <textarea name="content" class="form-control" rows="8"><?= htmlspecialchars($edit['content'] ?? '') ?></textarea>
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:8px;">
                <button type="submit" name="save_lore" class="btn btn-gold">Opslaan</button>
                <a href="lore.php" class="btn btn-outline">Annuleren</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div style="background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:hidden;">
        <table class="admin-table">
            <thead><tr><th>Titel</th><th>Categorie</th><th>Volgorde</th><th>Acties</th></tr></thead>
            <tbody>
            <?php foreach ($entries as $e): ?>
            <tr>
                <td><strong><?= htmlspecialchars($e['title']) ?></strong></td>
                <td><?= htmlspecialchars($e['category']) ?></td>
                <td><?= $e['sort_order'] ?></td>
                <td class="tbl-actions">
                    <a href="?edit=<?= $e['id'] ?>" class="btn-edit">Bewerken</a>
                    <a href="?delete=<?= $e['id'] ?>" class="btn-del" onclick="return confirm('Verwijderen?')">Verwijderen</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($entries)): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:32px;">Geen entries</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
