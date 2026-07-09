<?php
session_start();
if (!isset($_SESSION['tgj_admin'])) { header('Location: login.php'); exit(); }
require_once '../includes/connection.php';

$factions = $conn->query("SELECT * FROM factions ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$msg = '';

// DELETE
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $row = $conn->query("SELECT image FROM characters WHERE id=$did")->fetch_assoc();
    if ($row && $row['image']) @unlink(__DIR__ . '/../uploads/characters/' . $row['image']);
    $conn->query("DELETE FROM characters WHERE id=$did");
    $msg = 'ok:Character verwijderd.';
}

// ADD / EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_char'])) {
    $eid  = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $alias= trim($_POST['alias'] ?? '');
    $role = $_POST['role'] ?? 'supporting';
    $fac  = $_POST['faction_id'] ? (int)$_POST['faction_id'] : 'NULL';
    $age  = trim($_POST['age'] ?? '');
    $stat = $_POST['status'] ?? 'alive';
    $desc = trim($_POST['description'] ?? '');
    $back = trim($_POST['backstory'] ?? '');
    $abil = trim($_POST['abilities'] ?? '');
    $spoi = isset($_POST['is_spoiler']) ? 1 : 0;
    $ord  = (int)($_POST['sort_order'] ?? 0);

    // Image upload
    $imgName = '';
    if (!empty($_FILES['image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $imgName = uniqid('char_') . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/../uploads/characters/' . $imgName);
        }
    }

    if ($eid) {
        // Update
        $old = $conn->query("SELECT image FROM characters WHERE id=$eid")->fetch_assoc();
        if ($imgName && $old['image']) @unlink(__DIR__ . '/../uploads/characters/' . $old['image']);
        $useImg = $imgName ?: ($old['image'] ?? '');
        $stmt = $conn->prepare("UPDATE characters SET name=?,alias=?,role=?,faction_id=?,age=?,status=?,description=?,backstory=?,abilities=?,image=?,is_spoiler=?,sort_order=? WHERE id=?");
        $facVal = $_POST['faction_id'] ? (int)$_POST['faction_id'] : null;
        $stmt->bind_param("ssssssssssiis",$name,$alias,$role,$facVal,$age,$stat,$desc,$back,$abil,$useImg,$spoi,$ord,$eid);
        $stmt->execute();
        $msg = 'ok:Character bijgewerkt.';
    } else {
        $stmt = $conn->prepare("INSERT INTO characters (name,alias,role,faction_id,age,status,description,backstory,abilities,image,is_spoiler,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $facVal = $_POST['faction_id'] ? (int)$_POST['faction_id'] : null;
        $stmt->bind_param("ssssssssssii",$name,$alias,$role,$facVal,$age,$stat,$desc,$back,$abil,$imgName,$spoi,$ord);
        $stmt->execute();
        $msg = 'ok:Character toegevoegd.';
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $edit = $conn->query("SELECT * FROM characters WHERE id=".(int)$_GET['edit'])->fetch_assoc();
}

$characters = $conn->query("
    SELECT c.*, f.name AS faction_name FROM characters c
    LEFT JOIN factions f ON f.id=c.faction_id
    ORDER BY c.sort_order ASC, c.name ASC
")->fetch_all(MYSQLI_ASSOC);

$roleLabels = ['protagonist'=>'Protagonist','antagonist'=>'Antagonist','supporting'=>'Bijrol','villain'=>'Schurk','anti-hero'=>'Anti-held'];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8"><title>Characters — Admin TGJ</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="page admin-body">
<?php include 'admin-nav.php'; ?>
<div class="admin-container">
    <div class="admin-topbar">
        <h1>👥 Characters</h1>
        <a href="?add=1" class="btn btn-gold btn-sm">+ Toevoegen</a>
    </div>

    <?php if ($msg): [$t,$m] = explode(':', $msg, 2); ?>
    <div class="flash flash-<?= $t === 'ok' ? 'ok' : 'err' ?>"><?= htmlspecialchars($m) ?></div>
    <?php endif; ?>

    <!-- FORM -->
    <?php if (isset($_GET['add']) || $edit): ?>
    <div class="admin-form-card">
        <h2><?= $edit ? 'Character bewerken' : 'Nieuw character' ?></h2>
        <form method="POST" enctype="multipart/form-data">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
            <div class="form-grid">
                <div class="form-group">
                    <label>Naam *</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Alias / Bijnaam</label>
                    <input type="text" name="alias" class="form-control" value="<?= htmlspecialchars($edit['alias'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Rol</label>
                    <select name="role" class="form-control">
                        <?php foreach ($roleLabels as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($edit['role'] ?? 'supporting') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fractie</label>
                    <select name="faction_id" class="form-control">
                        <option value="">Geen</option>
                        <?php foreach ($factions as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= ($edit['faction_id'] ?? '') == $f['id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Leeftijd</label>
                    <input type="text" name="age" class="form-control" value="<?= htmlspecialchars($edit['age'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="alive"    <?= ($edit['status'] ?? '') === 'alive'    ? 'selected' : '' ?>>Levend</option>
                        <option value="deceased" <?= ($edit['status'] ?? '') === 'deceased' ? 'selected' : '' ?>>Overleden</option>
                        <option value="unknown"  <?= ($edit['status'] ?? '') === 'unknown'  ? 'selected' : '' ?>>Onbekend</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Volgorde</label>
                    <input type="number" name="sort_order" class="form-control" value="<?= $edit['sort_order'] ?? 0 ?>">
                </div>
                <div class="form-group">
                    <label>Afbeelding</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <?php if (!empty($edit['image'])): ?>
                    <img src="../uploads/characters/<?= htmlspecialchars($edit['image']) ?>" style="height:60px;margin-top:8px;border-radius:6px;">
                    <?php endif; ?>
                </div>
                <div class="form-group form-full">
                    <label>Beschrijving</label>
                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($edit['description'] ?? '') ?></textarea>
                </div>
                <div class="form-group form-full">
                    <label>Krachten & Abilities</label>
                    <textarea name="abilities" class="form-control" rows="3"><?= htmlspecialchars($edit['abilities'] ?? '') ?></textarea>
                </div>
                <div class="form-group form-full">
                    <label>Backstory</label>
                    <textarea name="backstory" class="form-control" rows="5"><?= htmlspecialchars($edit['backstory'] ?? '') ?></textarea>
                </div>
                <div class="form-group" style="display:flex;align-items:center;gap:10px;">
                    <input type="checkbox" name="is_spoiler" id="spoi" <?= !empty($edit['is_spoiler']) ? 'checked' : '' ?>>
                    <label for="spoi" style="font-size:.85rem;text-transform:none;letter-spacing:0;">Bevat spoilers</label>
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:8px;">
                <button type="submit" name="save_char" class="btn btn-gold">Opslaan</button>
                <a href="characters.php" class="btn btn-outline">Annuleren</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- TABLE -->
    <div style="background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:hidden;">
        <table class="admin-table">
            <thead><tr>
                <th></th><th>Naam</th><th>Rol</th><th>Fractie</th><th>Status</th><th>Volgorde</th><th>Acties</th>
            </tr></thead>
            <tbody>
            <?php foreach ($characters as $c):
                $img = !empty($c['image']) ? '../uploads/characters/'.$c['image'] : '';
                $hasImg = $img && file_exists(__DIR__ . '/' . $img);
            ?>
            <tr>
                <td><?php if ($hasImg): ?><img src="<?= htmlspecialchars($img) ?>" class="admin-thumb"><?php else: ?><div class="admin-thumb-ph">⚔</div><?php endif; ?></td>
                <td><strong><?= htmlspecialchars($c['name']) ?></strong><?php if ($c['alias']): ?><br><span style="font-size:.72rem;color:var(--muted);">"<?= htmlspecialchars($c['alias']) ?>"</span><?php endif; ?></td>
                <td><span class="admin-badge role-<?= $c['role'] ?>"><?= $roleLabels[$c['role']] ?? $c['role'] ?></span></td>
                <td><?= htmlspecialchars($c['faction_name'] ?? '—') ?></td>
                <td><?= $c['status'] ?></td>
                <td><?= $c['sort_order'] ?></td>
                <td class="tbl-actions">
                    <a href="?edit=<?= $c['id'] ?>" class="btn-edit">Bewerken</a>
                    <a href="?delete=<?= $c['id'] ?>" class="btn-del" onclick="return confirm('Weet je het zeker?')">Verwijderen</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($characters)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:32px;">Geen characters</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
