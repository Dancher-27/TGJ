<?php
session_start();
if (!isset($_SESSION['tgj_admin'])) { header('Location: login.php'); exit(); }
require_once '../includes/connection.php';
$arcs = $conn->query("SELECT * FROM arcs ORDER BY sort_order ASC")->fetch_all(MYSQLI_ASSOC);
$msg = '';

if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $row = $conn->query("SELECT image FROM timeline_events WHERE id=$did")->fetch_assoc();
    if ($row && $row['image']) @unlink(__DIR__ . '/../uploads/timeline/' . $row['image']);
    $conn->query("DELETE FROM timeline_events WHERE id=$did");
    $msg = 'ok:Event verwijderd.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_event'])) {
    $eid   = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $year  = $_POST['event_year'] !== '' ? (int)$_POST['event_year'] : null;
    $label = trim($_POST['event_label'] ?? '');
    $cat   = $_POST['category'] ?? 'other';
    $arcId = $_POST['arc_id'] ? (int)$_POST['arc_id'] : null;
    $epNum = $_POST['episode_number'] !== '' ? (int)$_POST['episode_number'] : null;
    $imp   = $_POST['importance'] ?? 'major';
    $spoi  = isset($_POST['is_spoiler']) ? 1 : 0;
    $ord   = (int)($_POST['sort_order'] ?? 0);

    $imgName = '';
    if (!empty($_FILES['image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $imgName = uniqid('ev_') . '.' . $ext;
            @mkdir(__DIR__ . '/../uploads/timeline', 0755, true);
            move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/../uploads/timeline/' . $imgName);
        }
    }

    if ($eid) {
        $old = $conn->query("SELECT image FROM timeline_events WHERE id=$eid")->fetch_assoc();
        if ($imgName && $old['image']) @unlink(__DIR__ . '/../uploads/timeline/' . $old['image']);
        $useImg = $imgName ?: ($old['image'] ?? '');
        $stmt = $conn->prepare("UPDATE timeline_events SET title=?,description=?,event_year=?,event_label=?,category=?,arc_id=?,episode_number=?,image=?,importance=?,is_spoiler=?,sort_order=? WHERE id=?");
        $stmt->bind_param("ssissiissiii",$title,$desc,$year,$label,$cat,$arcId,$epNum,$useImg,$imp,$spoi,$ord,$eid);
        $stmt->execute();
        $msg = 'ok:Event bijgewerkt.';
    } else {
        $stmt = $conn->prepare("INSERT INTO timeline_events (title,description,event_year,event_label,category,arc_id,episode_number,image,importance,is_spoiler,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("ssissiissii",$title,$desc,$year,$label,$cat,$arcId,$epNum,$imgName,$imp,$spoi,$ord);
        $stmt->execute();
        $msg = 'ok:Event toegevoegd.';
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $edit = $conn->query("SELECT * FROM timeline_events WHERE id=".(int)$_GET['edit'])->fetch_assoc();
}

$events = $conn->query("
    SELECT te.*, a.name AS arc_name FROM timeline_events te
    LEFT JOIN arcs a ON a.id=te.arc_id
    ORDER BY te.sort_order ASC, te.event_year ASC
")->fetch_all(MYSQLI_ASSOC);

$catLabels = ['battle'=>'Battle','discovery'=>'Ontdekking','death'=>'Dood','birth'=>'Geboorte','political'=>'Politiek','romance'=>'Romance','mystery'=>'Mysterie','flashback'=>'Flashback','other'=>'Anders'];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8"><title>Tijdlijn — Admin TGJ</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="page admin-body">
<?php include 'admin-nav.php'; ?>
<div class="admin-container">
    <div class="admin-topbar">
        <h1>📜 Tijdlijn</h1>
        <a href="?add=1" class="btn btn-gold btn-sm">+ Event toevoegen</a>
    </div>

    <?php if ($msg): [$t,$m] = explode(':', $msg, 2); ?>
    <div class="flash flash-<?= $t === 'ok' ? 'ok' : 'err' ?>"><?= htmlspecialchars($m) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['add']) || $edit): ?>
    <div class="admin-form-card">
        <h2><?= $edit ? 'Event bewerken' : 'Nieuw event' ?></h2>
        <form method="POST" enctype="multipart/form-data">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
            <div class="form-grid">
                <div class="form-group form-full">
                    <label>Titel *</label>
                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($edit['title'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Jaar (in-universe)</label>
                    <input type="number" name="event_year" class="form-control" value="<?= $edit['event_year'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>Label (bijv. "Jaar 0" of "Voor de Oorlog")</label>
                    <input type="text" name="event_label" class="form-control" value="<?= htmlspecialchars($edit['event_label'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Categorie</label>
                    <select name="category" class="form-control">
                        <?php foreach ($catLabels as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($edit['category'] ?? 'other') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Arc</label>
                    <select name="arc_id" class="form-control">
                        <option value="">Geen arc</option>
                        <?php foreach ($arcs as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= ($edit['arc_id'] ?? '') == $a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Aflevering #</label>
                    <input type="number" name="episode_number" class="form-control" placeholder="bijv. 3" value="<?= $edit['episode_number'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>Belang</label>
                    <select name="importance" class="form-control">
                        <option value="minor"    <?= ($edit['importance'] ?? '') === 'minor'    ? 'selected' : '' ?>>Klein</option>
                        <option value="major"    <?= ($edit['importance'] ?? 'major') === 'major' ? 'selected' : '' ?>>Belangrijk</option>
                        <option value="critical" <?= ($edit['importance'] ?? '') === 'critical' ? 'selected' : '' ?>>Kritiek</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Volgorde</label>
                    <input type="number" name="sort_order" class="form-control" value="<?= $edit['sort_order'] ?? 0 ?>">
                </div>
                <div class="form-group">
                    <label>Afbeelding</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
                <div class="form-group form-full">
                    <label>Beschrijving</label>
                    <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($edit['description'] ?? '') ?></textarea>
                </div>
                <div class="form-group" style="display:flex;align-items:center;gap:10px;">
                    <input type="checkbox" name="is_spoiler" id="spoi" <?= !empty($edit['is_spoiler']) ? 'checked' : '' ?>>
                    <label for="spoi" style="font-size:.85rem;text-transform:none;letter-spacing:0;">Spoiler</label>
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:8px;">
                <button type="submit" name="save_event" class="btn btn-gold">Opslaan</button>
                <a href="timeline.php" class="btn btn-outline">Annuleren</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div style="background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:hidden;">
        <table class="admin-table">
            <thead><tr><th>Titel</th><th>Label/Jaar</th><th>Afl.</th><th>Categorie</th><th>Arc</th><th>Belang</th><th>Volgorde</th><th>Acties</th></tr></thead>
            <tbody>
            <?php foreach ($events as $e): ?>
            <tr>
                <td><strong><?= htmlspecialchars($e['title']) ?></strong></td>
                <td><?= htmlspecialchars($e['event_label'] ?: ($e['event_year'] ? 'Jaar '.$e['event_year'] : '—')) ?></td>
                <td><?= ($e['episode_number'] ?? null) ? 'Afl. '.$e['episode_number'] : '—' ?></td>
                <td><?= $catLabels[$e['category']] ?? $e['category'] ?></td>
                <td><?= htmlspecialchars($e['arc_name'] ?? '—') ?></td>
                <td><?= $e['importance'] ?></td>
                <td><?= $e['sort_order'] ?></td>
                <td class="tbl-actions">
                    <a href="?edit=<?= $e['id'] ?>" class="btn-edit">Bewerken</a>
                    <a href="?delete=<?= $e['id'] ?>" class="btn-del" onclick="return confirm('Verwijderen?')">Verwijderen</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($events)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:32px;">Geen events</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
