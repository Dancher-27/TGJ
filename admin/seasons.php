<?php
session_start();
if (!isset($_SESSION['tgj_admin'])) { header('Location: login.php'); exit(); }
require_once '../includes/connection.php';
$msg = '';

if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $row = $conn->query("SELECT cover_image FROM seasons WHERE id=$did")->fetch_assoc();
    if ($row && $row['cover_image']) @unlink(__DIR__ . '/../uploads/seasons/' . $row['cover_image']);
    $conn->query("DELETE FROM seasons WHERE id=$did");
    $msg = 'ok:Seizoen verwijderd.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_season'])) {
    $eid    = (int)($_POST['id'] ?? 0);
    $num    = (int)$_POST['number'];
    $title  = trim($_POST['title'] ?? '');
    $sub    = trim($_POST['subtitle'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $eps    = (int)($_POST['episode_count'] ?? 0);
    $stat   = $_POST['status'] ?? 'upcoming';
    $rel    = $_POST['release_date'] ?: null;
    $end    = $_POST['end_date'] ?: null;
    $ord    = (int)($_POST['sort_order'] ?? 0);

    $imgName = '';
    if (!empty($_FILES['cover']['name'])) {
        $ext = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $imgName = uniqid('season_') . '.' . $ext;
            move_uploaded_file($_FILES['cover']['tmp_name'], __DIR__ . '/../uploads/seasons/' . $imgName);
        }
    }

    if ($eid) {
        $old = $conn->query("SELECT cover_image FROM seasons WHERE id=$eid")->fetch_assoc();
        if ($imgName && $old['cover_image']) @unlink(__DIR__ . '/../uploads/seasons/' . $old['cover_image']);
        $useImg = $imgName ?: ($old['cover_image'] ?? '');
        $stmt = $conn->prepare("UPDATE seasons SET number=?,title=?,subtitle=?,description=?,cover_image=?,episode_count=?,status=?,release_date=?,end_date=?,sort_order=? WHERE id=?");
        $stmt->bind_param("issssiissii",$num,$title,$sub,$desc,$useImg,$eps,$stat,$rel,$end,$ord,$eid);
        $stmt->execute();
        $msg = 'ok:Seizoen bijgewerkt.';
    } else {
        $stmt = $conn->prepare("INSERT INTO seasons (number,title,subtitle,description,cover_image,episode_count,status,release_date,end_date,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("issssiissi",$num,$title,$sub,$desc,$imgName,$eps,$stat,$rel,$end,$ord);
        $stmt->execute();
        $msg = 'ok:Seizoen toegevoegd.';
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $edit = $conn->query("SELECT * FROM seasons WHERE id=".(int)$_GET['edit'])->fetch_assoc();
}

$seasons = $conn->query("SELECT * FROM seasons ORDER BY sort_order ASC, number ASC")->fetch_all(MYSQLI_ASSOC);
$statusLabels = ['released'=>'Uitgebracht','ongoing'=>'Bezig','upcoming'=>'Binnenkort'];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8"><title>Seizoenen — Admin TGJ</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="page admin-body">
<?php include 'admin-nav.php'; ?>
<div class="admin-container">
    <div class="admin-topbar">
        <h1>📺 Seizoenen</h1>
        <a href="?add=1" class="btn btn-gold btn-sm">+ Seizoen toevoegen</a>
    </div>

    <?php if ($msg): [$t,$m] = explode(':', $msg, 2); ?>
    <div class="flash flash-<?= $t === 'ok' ? 'ok' : 'err' ?>"><?= htmlspecialchars($m) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['add']) || $edit): ?>
    <div class="admin-form-card">
        <h2><?= $edit ? 'Seizoen bewerken' : 'Nieuw seizoen' ?></h2>
        <form method="POST" enctype="multipart/form-data">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
            <div class="form-grid">
                <div class="form-group">
                    <label>Seizoen nummer *</label>
                    <input type="number" name="number" class="form-control" value="<?= $edit['number'] ?? '' ?>" required>
                </div>
                <div class="form-group">
                    <label>Titel *</label>
                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($edit['title'] ?? '') ?>" required>
                </div>
                <div class="form-group form-full">
                    <label>Subtitel</label>
                    <input type="text" name="subtitle" class="form-control" value="<?= htmlspecialchars($edit['subtitle'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <?php foreach ($statusLabels as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($edit['status'] ?? 'upcoming') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Aantal afleveringen</label>
                    <input type="number" name="episode_count" class="form-control" value="<?= $edit['episode_count'] ?? 0 ?>">
                </div>
                <div class="form-group">
                    <label>Releasedatum</label>
                    <input type="date" name="release_date" class="form-control" value="<?= $edit['release_date'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>Einddatum</label>
                    <input type="date" name="end_date" class="form-control" value="<?= $edit['end_date'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>Volgorde</label>
                    <input type="number" name="sort_order" class="form-control" value="<?= $edit['sort_order'] ?? 0 ?>">
                </div>
                <div class="form-group">
                    <label>Cover afbeelding</label>
                    <input type="file" name="cover" class="form-control" accept="image/*">
                    <?php if (!empty($edit['cover_image'])): ?>
                    <img src="../uploads/seasons/<?= htmlspecialchars($edit['cover_image']) ?>" style="height:60px;margin-top:8px;border-radius:6px;">
                    <?php endif; ?>
                </div>
                <div class="form-group form-full">
                    <label>Beschrijving</label>
                    <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($edit['description'] ?? '') ?></textarea>
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:8px;">
                <button type="submit" name="save_season" class="btn btn-gold">Opslaan</button>
                <a href="seasons.php" class="btn btn-outline">Annuleren</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div style="background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:hidden;">
        <table class="admin-table">
            <thead><tr><th>#</th><th>Titel</th><th>Status</th><th>Afl.</th><th>Releasedatum</th><th>Acties</th></tr></thead>
            <tbody>
            <?php foreach ($seasons as $s): ?>
            <tr>
                <td><strong><?= $s['number'] ?></strong></td>
                <td><strong><?= htmlspecialchars($s['title']) ?></strong><?php if ($s['subtitle']): ?><br><span style="font-size:.72rem;color:var(--gold);"><?= htmlspecialchars($s['subtitle']) ?></span><?php endif; ?></td>
                <td><span class="season-status-badge status-<?= $s['status'] ?>" style="position:static;font-size:.68rem;"><?= $statusLabels[$s['status']] ?? $s['status'] ?></span></td>
                <td><?= $s['episode_count'] ?: '—' ?></td>
                <td><?= $s['release_date'] ? date('d M Y', strtotime($s['release_date'])) : '—' ?></td>
                <td class="tbl-actions">
                    <a href="?edit=<?= $s['id'] ?>" class="btn-edit">Bewerken</a>
                    <a href="?delete=<?= $s['id'] ?>" class="btn-del" onclick="return confirm('Verwijderen?')">Verwijderen</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($seasons)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:32px;">Geen seizoenen</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
