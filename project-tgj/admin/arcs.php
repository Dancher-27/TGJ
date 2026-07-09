<?php
session_start();
if (!isset($_SESSION['tgj_admin'])) { header('Location: login.php'); exit(); }
require_once '../includes/connection.php';
$msg = '';
$seasons = $conn->query("SELECT id, number, title FROM seasons ORDER BY number")->fetch_all(MYSQLI_ASSOC);

if (isset($_GET['delete'])) { $conn->query("DELETE FROM arcs WHERE id=".(int)$_GET['delete']); $msg = 'ok:Arc verwijderd.'; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_arc'])) {
    $eid  = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $col  = $_POST['color'] ?? '#d4af37';
    $sid  = $_POST['season_id'] ? (int)$_POST['season_id'] : null;
    $ord  = (int)($_POST['sort_order'] ?? 0);
    if ($eid) {
        $stmt = $conn->prepare("UPDATE arcs SET name=?,description=?,color=?,season_id=?,sort_order=? WHERE id=?");
        $stmt->bind_param("ssssii",$name,$desc,$col,$sid,$ord,$eid); $stmt->execute(); $msg = 'ok:Arc bijgewerkt.';
    } else {
        $stmt = $conn->prepare("INSERT INTO arcs (name,description,color,season_id,sort_order) VALUES (?,?,?,?,?)");
        $stmt->bind_param("sssii",$name,$desc,$col,$sid,$ord); $stmt->execute(); $msg = 'ok:Arc toegevoegd.';
    }
}
$edit = null;
if (isset($_GET['edit'])) $edit = $conn->query("SELECT * FROM arcs WHERE id=".(int)$_GET['edit'])->fetch_assoc();
$arcs = $conn->query("SELECT a.*, s.title AS season_title FROM arcs a LEFT JOIN seasons s ON s.id=a.season_id ORDER BY a.sort_order ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="nl">
<head><meta charset="UTF-8"><title>Arcs — Admin TGJ</title>
<link rel="stylesheet" href="../styles.css"><link rel="stylesheet" href="admin.css"></head>
<body class="page admin-body">
<?php include 'admin-nav.php'; ?>
<div class="admin-container">
    <div class="admin-topbar"><h1>🌀 Arcs</h1><a href="?add=1" class="btn btn-gold btn-sm">+ Arc toevoegen</a></div>
    <?php if ($msg): [$t,$m] = explode(':', $msg, 2); ?><div class="flash flash-<?= $t==='ok'?'ok':'err' ?>"><?= htmlspecialchars($m) ?></div><?php endif; ?>

    <?php if (isset($_GET['add']) || $edit): ?>
    <div class="admin-form-card">
        <h2><?= $edit ? 'Arc bewerken' : 'Nieuwe arc' ?></h2>
        <form method="POST">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
            <div class="form-grid">
                <div class="form-group"><label>Naam *</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" required></div>
                <div class="form-group"><label>Kleur</label><input type="color" name="color" class="form-control" value="<?= $edit['color'] ?? '#d4af37' ?>" style="height:42px;padding:4px;"></div>
                <div class="form-group"><label>Seizoen</label>
                    <select name="season_id" class="form-control"><option value="">Geen seizoen</option>
                    <?php foreach ($seasons as $s): ?><option value="<?= $s['id'] ?>" <?= ($edit['season_id'] ?? '') == $s['id'] ? 'selected' : '' ?>>S<?= $s['number'] ?> — <?= htmlspecialchars($s['title']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Volgorde</label><input type="number" name="sort_order" class="form-control" value="<?= $edit['sort_order'] ?? 0 ?>"></div>
                <div class="form-group form-full"><label>Beschrijving</label><textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($edit['description'] ?? '') ?></textarea></div>
            </div>
            <div style="display:flex;gap:10px;margin-top:8px;">
                <button type="submit" name="save_arc" class="btn btn-gold">Opslaan</button>
                <a href="arcs.php" class="btn btn-outline">Annuleren</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div style="background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:hidden;">
        <table class="admin-table">
            <thead><tr><th>Naam</th><th>Kleur</th><th>Seizoen</th><th>Volgorde</th><th>Acties</th></tr></thead>
            <tbody>
            <?php foreach ($arcs as $a): ?>
            <tr>
                <td><strong style="color:<?= htmlspecialchars($a['color']) ?>"><?= htmlspecialchars($a['name']) ?></strong></td>
                <td><div style="width:24px;height:24px;border-radius:4px;background:<?= htmlspecialchars($a['color']) ?>;"></div></td>
                <td><?= htmlspecialchars($a['season_title'] ?? '—') ?></td>
                <td><?= $a['sort_order'] ?></td>
                <td class="tbl-actions">
                    <a href="?edit=<?= $a['id'] ?>" class="btn-edit">Bewerken</a>
                    <a href="?delete=<?= $a['id'] ?>" class="btn-del" onclick="return confirm('Verwijderen?')">Verwijderen</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($arcs)): ?><tr><td colspan="5" style="text-align:center;color:var(--muted);padding:32px;">Geen arcs</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
