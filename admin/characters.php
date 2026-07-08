<?php
session_start();
if (!isset($_SESSION['tgj_admin'])) { header('Location: login.php'); exit(); }
require_once '../includes/connection.php';

$factions   = $conn->query("SELECT * FROM factions ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$nexusTypes = $conn->query("SELECT * FROM nexus_types ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC) ?: [];
$msg = '';

// Auto-migration: character_images table
$conn->query("CREATE TABLE IF NOT EXISTS character_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    character_id INT NOT NULL,
    image VARCHAR(255) NOT NULL,
    caption VARCHAR(255) DEFAULT '',
    sort_order INT DEFAULT 0,
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
)");

// ── DELETE extra image ──
if (isset($_GET['del_img'])) {
    $imgId = (int)$_GET['del_img'];
    $cid   = (int)($_GET['cid'] ?? 0);
    $row   = $conn->query("SELECT image FROM character_images WHERE id=$imgId")->fetch_assoc();
    if ($row) {
        @unlink(__DIR__ . '/../uploads/characters/' . $row['image']);
        $conn->query("DELETE FROM character_images WHERE id=$imgId");
    }
    header("Location: ?edit=$cid&msg=ok:Foto+verwijderd."); exit();
}

// ── SAVE extra image ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_extra_img'])) {
    $cid     = (int)$_POST['char_id'];
    $caption = trim($_POST['caption'] ?? '');
    $ord     = (int)($_POST['sort_order'] ?? 0);
    if (!empty($_FILES['extra_image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['extra_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $fname = uniqid('char_ex_') . '.' . $ext;
            move_uploaded_file($_FILES['extra_image']['tmp_name'], __DIR__ . '/../uploads/characters/' . $fname);
            $stmt = $conn->prepare("INSERT INTO character_images (character_id, image, caption, sort_order) VALUES (?,?,?,?)");
            $stmt->bind_param("issi", $cid, $fname, $caption, $ord);
            $stmt->execute();
        }
    }
    header("Location: ?edit=$cid&msg=ok:Foto+toegevoegd."); exit();
}

// ── UPDATE caption/order ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_img'])) {
    $imgId   = (int)$_POST['img_id'];
    $cid     = (int)$_POST['char_id'];
    $caption = trim($_POST['caption'] ?? '');
    $ord     = (int)($_POST['sort_order'] ?? 0);
    $stmt = $conn->prepare("UPDATE character_images SET caption=?, sort_order=? WHERE id=?");
    $stmt->bind_param("sii", $caption, $ord, $imgId);
    $stmt->execute();
    header("Location: ?edit=$cid&msg=ok:Foto+bijgewerkt."); exit();
}

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
        $charId = $eid;
        $msg = 'ok:Character bijgewerkt.';
    } else {
        $stmt = $conn->prepare("INSERT INTO characters (name,alias,role,faction_id,age,status,description,backstory,abilities,image,is_spoiler,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $facVal = $_POST['faction_id'] ? (int)$_POST['faction_id'] : null;
        $stmt->bind_param("ssssssssssii",$name,$alias,$role,$facVal,$age,$stat,$desc,$back,$abil,$imgName,$spoi,$ord);
        $stmt->execute();
        $charId = $conn->insert_id;
        $msg = 'ok:Character toegevoegd.';
    }

    // Save nexus types
    $conn->query("DELETE FROM character_nexus_types WHERE character_id=$charId");
    $selectedTypes = array_map('intval', $_POST['nexus_types'] ?? []);
    if ($selectedTypes) {
        $ntStmt = $conn->prepare("INSERT IGNORE INTO character_nexus_types (character_id,nexus_type_id) VALUES (?,?)");
        foreach ($selectedTypes as $tid) {
            $ntStmt->bind_param("ii", $charId, $tid);
            $ntStmt->execute();
        }
    }
}

// Flash from redirect
if (isset($_GET['msg']) && !$msg) $msg = urldecode($_GET['msg']);

$edit = null;
if (isset($_GET['edit'])) {
    $edit = $conn->query("SELECT * FROM characters WHERE id=".(int)$_GET['edit'])->fetch_assoc();
}

// Extra images for this character (if editing)
$extraImages = [];
$editNexusTypes = [];
if ($edit) {
    $extraImages = $conn->query("
        SELECT * FROM character_images WHERE character_id={$edit['id']}
        ORDER BY sort_order ASC, id ASC
    ")->fetch_all(MYSQLI_ASSOC);
    $ntRows = $conn->query("SELECT nexus_type_id FROM character_nexus_types WHERE character_id={$edit['id']}")->fetch_all(MYSQLI_ASSOC);
    $editNexusTypes = array_column($ntRows, 'nexus_type_id');
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

                <?php if (!empty($nexusTypes)): ?>
                <div class="form-group form-full">
                    <label>Nexus Types</label>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:4px;">
                        <?php foreach ($nexusTypes as $nt): ?>
                        <label style="display:flex;align-items:center;gap:6px;background:var(--surface);border:1.5px solid var(--border);border-radius:100px;padding:5px 14px;cursor:pointer;font-size:.78rem;font-weight:700;transition:all .15s;"
                               id="nt-lbl-<?= $nt['id'] ?>">
                            <input type="checkbox" name="nexus_types[]" value="<?= $nt['id'] ?>"
                                   <?= in_array($nt['id'], $editNexusTypes) ? 'checked' : '' ?>
                                   onchange="ntToggle(<?= $nt['id'] ?>, this.checked)"
                                   style="display:none;">
                            <span><?= htmlspecialchars($nt['emoji']) ?> <?= htmlspecialchars($nt['name']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:10px;margin-top:8px;">
                <button type="submit" name="save_char" class="btn btn-gold">Opslaan</button>
                <a href="characters.php" class="btn btn-outline">Annuleren</a>
            </div>
        </form>
        <script>
        // Highlight selected nexus type chips
        <?php foreach ($nexusTypes as $nt): ?>
        ntToggle(<?= $nt['id'] ?>, <?= in_array($nt['id'], $editNexusTypes) ? 'true' : 'false' ?>);
        <?php endforeach; ?>
        function ntToggle(id, checked) {
            const lbl = document.getElementById('nt-lbl-' + id);
            if (!lbl) return;
            lbl.style.background      = checked ? 'rgba(124,58,237,.18)' : '';
            lbl.style.borderColor     = checked ? '#7c3aed' : '';
            lbl.style.color           = checked ? '#a78bfa' : '';
        }
        document.querySelectorAll('input[name="nexus_types[]"]').forEach(cb => {
            cb.addEventListener('change', () => ntToggle(cb.value, cb.checked));
        });
        </script>
    </div>
    <?php endif; ?>

    <!-- EXTRA IMAGES (only when editing) -->
    <?php if ($edit): ?>
    <div class="admin-form-card" style="margin-bottom:24px;">
        <h2 style="margin-bottom:20px;">🖼 Extra foto's — <?= htmlspecialchars($edit['name']) ?></h2>

        <!-- Existing images -->
        <?php if ($extraImages): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-bottom:24px;">
            <?php foreach ($extraImages as $xi): ?>
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;overflow:hidden;">
                <img src="../uploads/characters/<?= htmlspecialchars($xi['image']) ?>"
                     style="width:100%;aspect-ratio:3/4;object-fit:cover;display:block;">
                <div style="padding:10px 12px;">
                    <form method="POST" style="display:flex;flex-direction:column;gap:6px;">
                        <input type="hidden" name="img_id"  value="<?= $xi['id'] ?>">
                        <input type="hidden" name="char_id" value="<?= $edit['id'] ?>">
                        <input type="text" name="caption" class="form-control"
                            placeholder="Bijschrift (bijv. Jong, Arc 2…)"
                            value="<?= htmlspecialchars($xi['caption']) ?>"
                            style="font-size:.75rem;padding:5px 8px;">
                        <div style="display:flex;gap:6px;align-items:center;">
                            <input type="number" name="sort_order" class="form-control"
                                value="<?= $xi['sort_order'] ?>"
                                style="width:56px;font-size:.75rem;padding:5px 8px;" title="Volgorde">
                            <button type="submit" name="update_img" class="btn btn-outline" style="flex:1;font-size:.72rem;padding:5px 0;">Opslaan</button>
                        </div>
                    </form>
                    <a href="?del_img=<?= $xi['id'] ?>&cid=<?= $edit['id'] ?>"
                       class="btn-del" style="display:block;text-align:center;margin-top:6px;font-size:.72rem;"
                       onclick="return confirm('Foto verwijderen?')">🗑 Verwijderen</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="color:var(--muted);font-size:.85rem;margin-bottom:20px;">Nog geen extra foto's voor dit character.</p>
        <?php endif; ?>

        <!-- Upload new -->
        <div style="border-top:1px solid var(--border);padding-top:20px;">
            <div style="font-size:.8rem;font-weight:700;color:var(--muted);margin-bottom:12px;text-transform:uppercase;letter-spacing:.06em;">Nieuwe foto toevoegen</div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="char_id" value="<?= $edit['id'] ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Afbeelding *</label>
                        <input type="file" name="extra_image" class="form-control" accept="image/*" required>
                    </div>
                    <div class="form-group">
                        <label>Bijschrift <span style="color:var(--muted);font-weight:400;">(bijv. "Jong", "Arc 2", "Backstory")</span></label>
                        <input type="text" name="caption" class="form-control" placeholder="Optioneel">
                    </div>
                    <div class="form-group">
                        <label>Volgorde</label>
                        <input type="number" name="sort_order" class="form-control" value="<?= count($extraImages) ?>">
                    </div>
                </div>
                <button type="submit" name="save_extra_img" class="btn btn-gold">+ Foto toevoegen</button>
            </form>
        </div>
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
