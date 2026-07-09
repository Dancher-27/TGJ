<?php
session_start();
if (!isset($_SESSION['tgj_admin'])) { header('Location: login.php'); exit(); }
require_once '../includes/connection.php';
$msg = '';

if (isset($_GET['delete'])) {
    $conn->query("DELETE FROM factions WHERE id=".(int)$_GET['delete']);
    $msg = 'ok:Fractie verwijderd.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_faction'])) {
    $eid  = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $col  = $_POST['color'] ?? '#7c3aed';
    $good = isset($_POST['is_good']) ? 1 : 0;

    if ($eid) {
        $stmt = $conn->prepare("UPDATE factions SET name=?,description=?,color=?,is_good=? WHERE id=?");
        $stmt->bind_param("sssii",$name,$desc,$col,$good,$eid);
        $stmt->execute();
        $msg = 'ok:Fractie bijgewerkt.';
    } else {
        $stmt = $conn->prepare("INSERT INTO factions (name,description,color,is_good) VALUES (?,?,?,?)");
        $stmt->bind_param("sssi",$name,$desc,$col,$good);
        $stmt->execute();
        $msg = 'ok:Fractie toegevoegd.';
    }
}
$edit = null;
if (isset($_GET['edit'])) $edit = $conn->query("SELECT * FROM factions WHERE id=".(int)$_GET['edit'])->fetch_assoc();
$factions = $conn->query("SELECT * FROM factions ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="nl">
<head><meta charset="UTF-8"><title>Facties — Admin TGJ</title>
<link rel="stylesheet" href="../styles.css"><link rel="stylesheet" href="admin.css"></head>
<body class="page admin-body">
<?php include 'admin-nav.php'; ?>
<div class="admin-container">
    <div class="admin-topbar"><h1>⚔ Facties</h1><a href="?add=1" class="btn btn-gold btn-sm">+ Fractie toevoegen</a></div>
    <?php if ($msg): [$t,$m] = explode(':', $msg, 2); ?><div class="flash flash-<?= $t==='ok'?'ok':'err' ?>"><?= htmlspecialchars($m) ?></div><?php endif; ?>

    <?php if (isset($_GET['add']) || $edit): ?>
    <div class="admin-form-card">
        <h2><?= $edit ? 'Fractie bewerken' : 'Nieuwe fractie' ?></h2>
        <form method="POST">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
            <div class="form-grid">
                <div class="form-group"><label>Naam *</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" required></div>
                <div class="form-group"><label>Kleur</label><input type="color" name="color" class="form-control" value="<?= $edit['color'] ?? '#7c3aed' ?>" style="height:42px;padding:4px;"></div>
                <div class="form-group form-full"><label>Beschrijving</label><textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($edit['description'] ?? '') ?></textarea></div>
                <div class="form-group" style="display:flex;align-items:center;gap:10px;"><input type="checkbox" name="is_good" id="isg" <?= !empty($edit['is_good']) ? 'checked' : '' ?>><label for="isg" style="font-size:.85rem;text-transform:none;letter-spacing:0;">Goede fractie</label></div>
            </div>
            <div style="display:flex;gap:10px;margin-top:8px;">
                <button type="submit" name="save_faction" class="btn btn-gold">Opslaan</button>
                <a href="factions.php" class="btn btn-outline">Annuleren</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div style="background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:hidden;">
        <table class="admin-table">
            <thead><tr><th>Naam</th><th>Kleur</th><th>Type</th><th>Acties</th></tr></thead>
            <tbody>
            <?php foreach ($factions as $f): ?>
            <tr>
                <td><strong><?= htmlspecialchars($f['name']) ?></strong></td>
                <td><div style="width:28px;height:28px;border-radius:6px;background:<?= htmlspecialchars($f['color']) ?>;border:1px solid var(--border);"></div></td>
                <td><?= $f['is_good'] ? '✅ Goed' : '💀 Slecht' ?></td>
                <td class="tbl-actions">
                    <a href="?edit=<?= $f['id'] ?>" class="btn-edit">Bewerken</a>
                    <a href="?delete=<?= $f['id'] ?>" class="btn-del" onclick="return confirm('Verwijderen?')">Verwijderen</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($factions)): ?><tr><td colspan="4" style="text-align:center;color:var(--muted);padding:32px;">Geen facties</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
