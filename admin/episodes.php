<?php
session_start();
if (!isset($_SESSION['tgj_admin'])) { header('Location: login.php'); exit(); }
require_once '../includes/connection.php';
$msg = '';

// Auto-migration
$conn->query("ALTER TABLE episodes ADD COLUMN IF NOT EXISTS content LONGTEXT AFTER description");
$conn->query("ALTER TABLE episodes ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0 AFTER release_date");
$conn->query("ALTER TABLE episodes ADD COLUMN IF NOT EXISTS status ENUM('draft','published','upcoming') NOT NULL DEFAULT 'published' AFTER sort_order");
$conn->query("ALTER TABLE episodes ADD COLUMN IF NOT EXISTS reading_time INT DEFAULT 0 AFTER status");
$conn->query("CREATE TABLE IF NOT EXISTS episode_covers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    episode_id INT NOT NULL,
    image VARCHAR(255) NOT NULL,
    position ENUM('start','end') DEFAULT 'start',
    sort_order INT DEFAULT 0,
    FOREIGN KEY (episode_id) REFERENCES episodes(id) ON DELETE CASCADE
)");

// ── COVER UPLOAD (AJAX) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_cover'])) {
    header('Content-Type: application/json');
    $epId = (int)($_POST['ep_id'] ?? 0);
    $pos  = in_array($_POST['position'] ?? '', ['start','end']) ? $_POST['position'] : 'start';
    if (!$epId || empty($_FILES['cover']['name'])) { echo json_encode(['ok'=>false]); exit(); }
    $ext = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) { echo json_encode(['ok'=>false,'error'=>'Ongeldig type']); exit(); }
    $dir = __DIR__ . '/../uploads/covers/ep_' . $epId;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fname = uniqid('cover_') . '.' . $ext;
    move_uploaded_file($_FILES['cover']['tmp_name'], $dir . '/' . $fname);
    $maxOrd = (int)($conn->query("SELECT MAX(sort_order) FROM episode_covers WHERE episode_id=$epId")->fetch_row()[0] ?? 0);
    $nextOrd = $maxOrd + 1;
    $stmt = $conn->prepare("INSERT INTO episode_covers (episode_id,image,position,sort_order) VALUES (?,?,?,?)");
    $stmt->bind_param("issi", $epId, $fname, $pos, $nextOrd);
    $stmt->execute();
    $newId = $conn->insert_id;
    echo json_encode(['ok'=>true,'id'=>$newId,'file'=>$fname,'ep_id'=>$epId,'position'=>$pos]);
    exit();
}

// ── COVER DELETE (AJAX) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_cover'])) {
    header('Content-Type: application/json');
    $covId = (int)($_POST['cover_id'] ?? 0);
    $row   = $conn->query("SELECT * FROM episode_covers WHERE id=$covId")->fetch_assoc();
    if ($row) {
        @unlink(__DIR__ . '/../uploads/covers/ep_' . $row['episode_id'] . '/' . $row['image']);
        $conn->query("DELETE FROM episode_covers WHERE id=$covId");
    }
    echo json_encode(['ok'=>true]);
    exit();
}

// DELETE
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $row = $conn->query("SELECT season_id FROM episodes WHERE id=$did")->fetch_assoc();
    $conn->query("DELETE FROM episodes WHERE id=$did");
    $back = $row ? '?season=' . $row['season_id'] . '&msg=ok:Aflevering+verwijderd.' : '?msg=ok:Aflevering+verwijderd.';
    header('Location: episodes.php' . $back); exit();
}

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_episode'])) {
    $eid     = (int)($_POST['id'] ?? 0);
    $sid     = (int)$_POST['season_id'];
    $num     = (int)$_POST['number'];
    $title   = trim($_POST['title'] ?? '');
    $desc    = trim($_POST['description'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $rel     = !empty($_POST['release_date']) ? $_POST['release_date'] : null;
    $ord     = (int)($_POST['sort_order'] ?? 0);
    $status  = in_array($_POST['status'] ?? '', ['draft','published','upcoming']) ? $_POST['status'] : 'published';

    // Auto-calculate reading time (200 wpm average)
    $wordCount   = $content ? str_word_count(strip_tags($content)) : 0;
    $readingTime = $wordCount > 0 ? max(1, (int)ceil($wordCount / 200)) : 0;

    if ($eid) {
        $stmt = $conn->prepare("UPDATE episodes SET season_id=?,number=?,title=?,description=?,content=?,release_date=?,sort_order=?,status=?,reading_time=? WHERE id=?");
        $stmt->bind_param("iissssisii", $sid, $num, $title, $desc, $content, $rel, $ord, $status, $readingTime, $eid);
        $stmt->execute();
        $redir = "episodes.php?season=$sid&msg=ok:Aflevering+bijgewerkt.";
    } else {
        $stmt = $conn->prepare("INSERT INTO episodes (season_id,number,title,description,content,release_date,sort_order,status,reading_time) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("iissssisi", $sid, $num, $title, $desc, $content, $rel, $ord, $status, $readingTime);
        $stmt->execute();
        $redir = "episodes.php?season=$sid&msg=ok:Aflevering+toegevoegd.";
    }
    header("Location: $redir"); exit();
}

// UPLOAD SCENE IMAGE (AJAX — returns JSON, per episode folder)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_scene'])) {
    header('Content-Type: application/json');
    $epId = (int)($_POST['episode_id'] ?? 0);
    if (!$epId) { echo json_encode(['ok' => false, 'error' => 'Geen aflevering ID.']); exit(); }
    $dir = __DIR__ . '/../uploads/scenes/ep_' . $epId;
    @mkdir($dir, 0755, true);
    if (!empty($_FILES['scene_img']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['scene_img']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
            $fname = 'scene_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            move_uploaded_file($_FILES['scene_img']['tmp_name'], $dir . '/' . $fname);
            echo json_encode(['ok' => true, 'file' => $fname, 'ep_id' => $epId]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Alleen jpg, png, webp of gif toegestaan.']);
        }
    } else {
        echo json_encode(['ok' => false, 'error' => 'Geen bestand geselecteerd.']);
    }
    exit();
}

// DELETE SCENE IMAGE (AJAX — returns JSON)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_scene'])) {
    header('Content-Type: application/json');
    $epId  = (int)($_POST['episode_id'] ?? 0);
    $fname = basename($_POST['filename'] ?? '');
    if ($epId && $fname && preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $fname)) {
        $path = __DIR__ . '/../uploads/scenes/ep_' . $epId . '/' . $fname;
        if (file_exists($path)) { unlink($path); echo json_encode(['ok' => true]); }
        else { echo json_encode(['ok' => false, 'error' => 'Bestand niet gevonden.']); }
    } else {
        echo json_encode(['ok' => false, 'error' => 'Ongeldige aanvraag.']);
    }
    exit();
}

if (isset($_GET['msg'])) $msg = urldecode($_GET['msg']);

$edit = null;
$editCovers = [];
if (isset($_GET['edit'])) {
    $edit = $conn->query("SELECT * FROM episodes WHERE id=".(int)$_GET['edit'])->fetch_assoc();
    if ($edit) {
        $editCovers = $conn->query("SELECT * FROM episode_covers WHERE episode_id={$edit['id']} ORDER BY position ASC, sort_order ASC, id ASC")->fetch_all(MYSQLI_ASSOC);
    }
}

$filterSeason = (int)($_GET['season'] ?? ($edit['season_id'] ?? 0));
$seasons = $conn->query("SELECT * FROM seasons ORDER BY sort_order ASC, number ASC")->fetch_all(MYSQLI_ASSOC);

if ($filterSeason) {
    $episodes = $conn->query("
        SELECT e.*, s.title AS season_title, s.number AS season_number
        FROM episodes e JOIN seasons s ON s.id=e.season_id
        WHERE e.season_id=$filterSeason
        ORDER BY e.sort_order ASC, e.number ASC
    ")->fetch_all(MYSQLI_ASSOC);
} else {
    $episodes = $conn->query("
        SELECT e.*, s.title AS season_title, s.number AS season_number
        FROM episodes e JOIN seasons s ON s.id=e.season_id
        ORDER BY s.sort_order ASC, s.number ASC, e.sort_order ASC, e.number ASC
    ")->fetch_all(MYSQLI_ASSOC);
}

$formSeasonId = $edit['season_id'] ?? $filterSeason ?: ($seasons[0]['id'] ?? 0);

$statusLabels = ['draft' => '✏️ Concept', 'published' => '✅ Gepubliceerd', 'upcoming' => '🔒 Binnenkort'];
$statusColors = ['draft' => '#f59e0b', 'published' => '#22c55e', 'upcoming' => '#7c3aed'];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Afleveringen — Admin TGJ</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="admin.css">
    <style>
    .ep-content-hint { font-size: .75rem; color: var(--muted); margin-top: 4px; line-height: 1.5; }
    .char-count      { font-size: .72rem; color: var(--muted); text-align: right; margin-top: 2px; }
    .ep-preview      { font-size: .78rem; color: var(--muted); font-style: italic; max-width: 400px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    /* Autosave indicator */
    .autosave-bar {
        display: flex; align-items: center; gap: 8px;
        font-size: .72rem; color: var(--muted);
        margin-top: 6px; min-height: 20px;
    }
    .autosave-dot {
        width: 7px; height: 7px; border-radius: 50%;
        background: var(--muted); flex-shrink: 0;
        transition: background .3s;
    }
    .autosave-bar.saved   .autosave-dot { background: #22c55e; }
    .autosave-bar.unsaved .autosave-dot { background: #f59e0b; }
    .autosave-bar.restored .autosave-dot { background: #7c3aed; }

    /* Preview pane */
    .ep-preview-pane {
        background: #0f0e17; border: 1px solid rgba(124,58,237,.3);
        border-radius: 12px; padding: 24px 28px;
        font-size: 1rem; line-height: 1.9; color: rgba(232,228,240,.85);
        max-height: 500px; overflow-y: auto;
        font-family: 'Inter', system-ui, sans-serif;
        display: none;
    }
    .ep-preview-pane.open { display: block; }
    .ep-preview-pane p { margin-bottom: 1.4em; }
    .ep-preview-pane h2 { font-family: 'Cinzel',serif; color: #a78bfa; font-size: 1.1rem; margin: 1.8em 0 .8em; border-bottom: 1px solid rgba(124,58,237,.25); padding-bottom: .4em; }
    .ep-preview-pane .ep-speaker { color: #a78bfa; font-weight: 700; }
    .ep-preview-pane .ep-speaker-action { color: rgba(167,139,250,.6); font-style: italic; }
    .ep-preview-pane strong { color: #fff; }
    .preview-toggle-btn { margin-left: auto; }
    .hint-box {
        background: var(--primary-light); border: 1px solid var(--border);
        border-radius: 10px; padding: 12px 16px;
        font-size: .78rem; color: var(--text); line-height: 1.7;
    }
    .hint-box strong { color: var(--primary); }
    .hint-box code {
        background: rgba(124,58,237,.12); padding: 1px 6px;
        border-radius: 4px; font-family: monospace; font-size: .82em;
    }
    .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 5px; }
    .reading-time-badge {
        display: inline-flex; align-items: center; gap: 4px;
        background: var(--primary-light); color: var(--primary);
        font-size: .68rem; font-weight: 700; padding: 3px 10px;
        border-radius: 100px; border: 1px solid var(--border);
    }
    /* Scene panel inside the form */
    .scene-panel {
        border: 1px solid var(--border); border-radius: 12px;
        padding: 16px 18px; background: var(--surface);
        margin-top: 4px;
    }
    .scene-panel-title {
        font-size: .78rem; font-weight: 800; color: var(--primary);
        letter-spacing: .06em; text-transform: uppercase; margin-bottom: 12px;
    }
    .scene-upload-row {
        display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 16px;
    }
    .scene-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px;
    }
    .scene-card {
        border: 1px solid var(--border); border-radius: 8px;
        overflow: hidden; background: var(--card); cursor: pointer;
        transition: border-color .15s, box-shadow .15s;
    }
    .scene-card:hover { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(124,58,237,.15); }
    .scene-card img { width: 100%; aspect-ratio: 3/2; object-fit: cover; background: #1a1828; display: block; }
    .scene-card-label { font-size: .6rem; color: var(--muted); padding: 5px 7px; word-break: break-all; line-height: 1.3; }
    .scene-card-btn { display: block; width: 100%; font-size: .65rem; padding: 5px; border: none; border-top: 1px solid var(--border); background: var(--primary-light); color: var(--primary); font-weight: 700; cursor: pointer; font-family: inherit; transition: background .12s; }
    .scene-card-btn:hover { background: var(--primary); color: #fff; }
    </style>
</head>
<body class="page admin-body">
<?php include 'admin-nav.php'; ?>
<div class="admin-container">
    <div class="admin-topbar">
        <h1>📺 Afleveringen</h1>
        <a href="?add=1<?= $filterSeason ? "&season=$filterSeason" : '' ?>" class="btn btn-gold btn-sm">+ Aflevering toevoegen</a>
    </div>

    <?php if ($msg): [$t,$m] = explode(':', $msg, 2); ?>
    <div class="flash flash-<?= $t === 'ok' ? 'ok' : 'err' ?>"><?= htmlspecialchars($m) ?></div>
    <?php endif; ?>

    <!-- Season filter -->
    <div style="margin-bottom:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <span style="font-size:.8rem;font-weight:700;color:var(--muted);">Filter:</span>
        <a href="episodes.php" class="filter-btn <?= !$filterSeason?'active':'' ?>">Alle seizoenen</a>
        <?php foreach ($seasons as $s): ?>
        <a href="?season=<?= $s['id'] ?>" class="filter-btn <?= $filterSeason===$s['id']?'active':'' ?>">
            S<?= $s['number'] ?> — <?= htmlspecialchars($s['title']) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (isset($_GET['add']) || $edit): ?>
    <div class="admin-form-card">
        <h2><?= $edit ? 'Aflevering bewerken' : 'Nieuwe aflevering' ?></h2>
        <form method="POST" enctype="multipart/form-data">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
            <div class="form-grid">

                <div class="form-group">
                    <label>Seizoen *</label>
                    <select name="season_id" class="form-control" required>
                        <?php foreach ($seasons as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= (int)$formSeasonId === (int)$s['id'] ? 'selected' : '' ?>>
                            S<?= $s['number'] ?> — <?= htmlspecialchars($s['title']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Aflevering nummer *</label>
                    <input type="number" name="number" class="form-control" min="1"
                        value="<?= $edit['number'] ?? '' ?>" required>
                </div>

                <div class="form-group form-full">
                    <label>Titel *</label>
                    <input type="text" name="title" class="form-control"
                        value="<?= htmlspecialchars($edit['title'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <?php foreach ($statusLabels as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($edit['status'] ?? 'published') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div style="font-size:.72rem;color:var(--muted);margin-top:4px;">
                        <strong>Concept</strong> = alleen jij ziet het · <strong>Binnenkort</strong> = zichtbaar maar vergrendeld · <strong>Gepubliceerd</strong> = leesbaar
                    </div>
                </div>

                <div class="form-group">
                    <label>Releasedatum</label>
                    <input type="date" name="release_date" class="form-control"
                        value="<?= $edit['release_date'] ?? '' ?>">
                </div>

                <div class="form-group form-full">
                    <label>Korte beschrijving <span style="color:var(--muted);font-weight:400;">(samenvatting / teaser)</span></label>
                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($edit['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group form-full">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                        <label style="margin-bottom:0;">Inhoud (verhaal / tekst)</label>
                        <button type="button" class="btn btn-outline btn-sm preview-toggle-btn" onclick="togglePreview()">👁 Preview</button>
                        <button type="button" class="btn btn-outline btn-sm" id="copy-ep-btn" onclick="copyEpisode()" title="Kopieer volledige aflevering naar klembord">📋 Kopiëren</button>
                    </div>
                    <textarea name="content" id="ep-content" class="form-control" rows="22"
                        placeholder="Schrijf de volledige inhoud van de aflevering hier..."
                        oninput="updateStats(this);updatePreview()"><?= htmlspecialchars($edit['content'] ?? '') ?></textarea>
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                        <div class="char-count" id="char-count" style="margin-top:0;">0 tekens</div>
                        <div class="autosave-bar" id="autosave-bar">
                            <span class="autosave-dot"></span>
                            <span id="autosave-msg">Niet opgeslagen</span>
                        </div>
                    </div>
                    <div class="ep-preview-pane" id="ep-preview-pane"></div>
                </div>

                <!-- COVER PAGES -->
                <?php $editId = (int)($edit['id'] ?? 0); ?>
                <div class="form-group form-full">
                    <div class="scene-panel">
                        <div class="scene-panel-title">📖 Cover Pagina's</div>
                        <?php if (!$editId): ?>
                        <p style="font-size:.78rem;color:var(--muted);font-style:italic;">Sla de aflevering eerst op voordat je covers kunt uploaden.</p>
                        <?php else: ?>
                        <p style="font-size:.76rem;color:var(--muted);margin-bottom:14px;line-height:1.6;">
                            Covers worden als volledige pagina vóór of ná de tekst getoond — zoals een manga chaptercover. Meerdere covers verschijnen in de volgorde van upload.
                        </p>
                        <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px;">
                            <div>
                                <label style="font-size:.72rem;font-weight:700;color:var(--muted);display:block;margin-bottom:5px;">Afbeelding</label>
                                <input type="file" id="cover-file-input" accept="image/jpeg,image/png,image/webp,image/gif" class="form-control" style="max-width:260px;font-size:.8rem;">
                            </div>
                            <div>
                                <label style="font-size:.72rem;font-weight:700;color:var(--muted);display:block;margin-bottom:5px;">Positie</label>
                                <select id="cover-position" class="form-control" style="width:120px;">
                                    <option value="start">Begin</option>
                                    <option value="end">Eind</option>
                                </select>
                            </div>
                            <button type="button" id="cover-upload-btn" class="btn btn-gold" style="white-space:nowrap;">Uploaden</button>
                            <span id="cover-upload-msg" style="font-size:.75rem;display:none;align-self:center;"></span>
                        </div>
                        <div id="cover-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;">
                            <?php if (empty($editCovers)): ?>
                            <p id="cover-no-msg" style="font-size:.75rem;color:var(--muted);font-style:italic;grid-column:1/-1;">Nog geen covers voor deze aflevering.</p>
                            <?php else: foreach ($editCovers as $cov):
                                $covSrc = '../uploads/covers/ep_' . $editId . '/' . $cov['image'];
                            ?>
                            <div id="cover-card-<?= $cov['id'] ?>" style="background:var(--surface);border:1px solid var(--border);border-radius:10px;overflow:hidden;">
                                <img src="<?= htmlspecialchars($covSrc) ?>" style="width:100%;aspect-ratio:2/3;object-fit:cover;display:block;" alt="">
                                <div style="padding:7px 10px;">
                                    <span style="font-size:.68rem;font-weight:700;background:rgba(124,58,237,.15);color:var(--primary);border-radius:4px;padding:2px 8px;">
                                        <?= $cov['position'] === 'start' ? 'Begin' : 'Eind' ?>
                                    </span>
                                </div>
                                <button type="button" onclick="deleteCover(<?= $cov['id'] ?>, this.closest('[id^=cover-card-]'))"
                                        style="background:#fee2e2;color:#dc2626;border:none;border-top:1px solid var(--border);width:100%;padding:6px;font-size:.72rem;cursor:pointer;">✕ Verwijderen</button>
                            </div>
                            <?php endforeach; endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- SCENE IMAGES — only shown when editing an existing episode -->
                <div class="form-group form-full">
                    <div class="scene-panel">
                        <div class="scene-panel-title">🖼 Scène Afbeeldingen</div>
                        <?php if (!$editId): ?>
                        <p style="font-size:.78rem;color:var(--muted);font-style:italic;">
                            Sla de aflevering eerst op voordat je afbeeldingen kunt uploaden.
                        </p>
                        <?php else: ?>
                        <p style="font-size:.76rem;color:var(--muted);margin-bottom:12px;line-height:1.6;">
                            Upload een afbeelding → klik op het tekstveld onder de foto → alles selecteren met <kbd>Ctrl+A</kbd> → kopiëren met <kbd>Ctrl+C</kbd> → plakken in de tekst op de gewenste plek met <kbd>Ctrl+V</kbd>.
                        </p>
                        <!-- Upload — NOTE: this is a plain div, NOT a <form>, because we are already inside the episode <form> -->
                        <div class="scene-upload-row">
                            <div>
                                <label style="font-size:.72rem;font-weight:700;color:var(--muted);display:block;margin-bottom:5px;">Afbeelding toevoegen</label>
                                <input type="file" id="scene-file-input" accept="image/jpeg,image/png,image/webp,image/gif" class="form-control" style="max-width:280px;font-size:.8rem;">
                            </div>
                            <button type="button" id="scene-upload-btn" class="btn btn-gold" style="white-space:nowrap;">Uploaden</button>
                            <span id="scene-upload-msg" style="font-size:.75rem;display:none;align-self:center;"></span>
                        </div>
                        <!-- Existing scene images for THIS episode -->
                        <?php
                        $epSceneDir   = __DIR__ . '/../uploads/scenes/ep_' . $editId;
                        $epSceneFiles = is_dir($epSceneDir)
                            ? array_values(array_filter(scandir($epSceneDir), fn($f) => !in_array($f, ['.','..']) && preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $f)))
                            : [];
                        ?>
                        <div class="scene-grid" id="scene-grid">
                            <?php if (empty($epSceneFiles)): ?>
                            <p id="scene-no-msg" style="font-size:.75rem;color:var(--muted);font-style:italic;grid-column:1/-1;">Nog geen afbeeldingen voor deze aflevering.</p>
                            <?php else: foreach ($epSceneFiles as $f):
                                $relPath = 'ep_' . $editId . '/' . $f; ?>
                            <div class="scene-card" id="scene-card-<?= htmlspecialchars($f) ?>">
                                <img src="../uploads/scenes/<?= htmlspecialchars($relPath) ?>" alt="" style="cursor:default;">
                                <div style="padding:6px 6px 3px;">
                                    <div style="font-size:.6rem;color:var(--muted);margin-bottom:3px;">Selecteer → Ctrl+C → plak in tekst</div>
                                    <input type="text" readonly
                                           value="<?= htmlspecialchars('[scene: ' . $relPath . ']') ?>"
                                           onclick="this.select()"
                                           style="width:100%;font-size:.62rem;font-family:monospace;background:rgba(124,58,237,.08);border:1px solid var(--border);border-radius:4px;padding:4px 6px;color:var(--primary);cursor:text;">
                                </div>
                                <button type="button" class="scene-card-btn" style="background:#fee2e2;color:#dc2626;border-top:1px solid var(--border);width:100%;" onclick="deleteScene(<?= htmlspecialchars(json_encode($f)) ?>, this)" title="Verwijderen">✕ Verwijderen</button>
                            </div>
                            <?php endforeach; endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group form-full">
                    <div class="hint-box">
                        <strong>Hoofdstukken toevoegen:</strong> gebruik <code>##Hoofdstuknaam</code> of <code>## Hoofdstuknaam</code> op een eigen regel om een hoofdstuk te starten.
                        Dit verschijnt als klikbaar inhoudsopgave in de reader.<br>
                        <strong>Alinea's:</strong> laat één lege regel tussen alinea's.<br>
                        <strong>Leestijd</strong> wordt automatisch berekend bij opslaan (±200 woorden/minuut).
                    </div>
                </div>

                <div class="form-group">
                    <label>Volgorde</label>
                    <input type="number" name="sort_order" class="form-control"
                        value="<?= $edit['sort_order'] ?? 0 ?>">
                </div>

                <?php if (!empty($edit['reading_time'])): ?>
                <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:4px;">
                    <div>
                        <label>Huidige leestijd</label>
                        <div style="margin-top:6px;"><span class="reading-time-badge">⏱ ~<?= $edit['reading_time'] ?> min lezen</span></div>
                        <div style="font-size:.7rem;color:var(--muted);margin-top:4px;">Wordt herberekend bij opslaan</div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
            <div style="display:flex;gap:10px;margin-top:8px;">
                <button type="submit" name="save_episode" class="btn btn-gold">Opslaan</button>
                <a href="episodes.php<?= $filterSeason ? "?season=$filterSeason" : '' ?>" class="btn btn-outline">Annuleren</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div style="background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:hidden;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width:50px;">#</th>
                    <th>Titel</th>
                    <th>Status</th>
                    <th>Seizoen</th>
                    <th>Datum</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($episodes)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:32px;">
                <?= $filterSeason ? 'Geen afleveringen in dit seizoen.' : 'Nog geen afleveringen.' ?>
            </td></tr>
            <?php endif; ?>
            <?php foreach ($episodes as $ep):
                $epStatus = $ep['status'] ?? 'published';
                $statusColor = $statusColors[$epStatus] ?? '#6b7280';
            ?>
            <tr>
                <td><strong><?= $ep['number'] ?></strong></td>
                <td>
                    <strong><?= htmlspecialchars($ep['title']) ?></strong>
                    <?php if (!empty($ep['description'])): ?>
                    <div class="ep-preview"><?= htmlspecialchars($ep['description']) ?></div>
                    <?php endif; ?>
                    <div style="display:flex;gap:8px;margin-top:4px;flex-wrap:wrap;">
                        <?php if (!empty($ep['content'])): ?>
                        <span style="font-size:.68rem;color:var(--primary);">📖 <?= number_format(str_word_count($ep['content'])) ?> woorden</span>
                        <?php endif; ?>
                        <?php if (!empty($ep['reading_time'])): ?>
                        <span class="reading-time-badge">⏱ ~<?= $ep['reading_time'] ?> min</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <span style="font-size:.72rem;font-weight:700;color:<?= $statusColor ?>;">
                        <span class="status-dot" style="background:<?= $statusColor ?>;"></span>
                        <?= $statusLabels[$epStatus] ?? $epStatus ?>
                    </span>
                </td>
                <td>S<?= $ep['season_number'] ?> — <?= htmlspecialchars($ep['season_title']) ?></td>
                <td><?= $ep['release_date'] ? date('d M Y', strtotime($ep['release_date'])) : '—' ?></td>
                <td class="tbl-actions">
                    <?php if ($epStatus === 'published'): ?>
                    <a href="../episode.php?id=<?= $ep['id'] ?>" target="_blank" class="btn-edit" style="background:var(--primary-light);color:var(--primary);">Bekijken</a>
                    <?php endif; ?>
                    <a href="?edit=<?= $ep['id'] ?><?= $filterSeason ? "&season=$filterSeason" : '' ?>" class="btn-edit">Bewerken</a>
                    <a href="?delete=<?= $ep['id'] ?><?= $filterSeason ? "&season=$filterSeason" : '' ?>"
                       class="btn-del" onclick="return confirm('Aflevering verwijderen?')">Verwijderen</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const EPISODE_ID = <?= (int)($edit['id'] ?? 0) ?>;

// ── Copy marker to clipboard ──
function copyMarker(marker, el) {
    navigator.clipboard.writeText(marker).then(() => {
        // Find the button inside the card to show feedback
        const card = el.closest('.scene-card') || el.parentElement;
        const btn  = card ? card.querySelector('.scene-card-btn') : el;
        const orig = btn ? btn.textContent : '';
        if (btn) {
            btn.textContent = '✓ Gekopieerd! Plak met Ctrl+V';
            btn.style.background = '#dcfce7';
            btn.style.color = '#16a34a';
            setTimeout(() => {
                btn.textContent = orig;
                btn.style.background = '';
                btn.style.color = '';
            }, 2200);
        }
    }).catch(() => {
        // Fallback: select the marker text in a temp input
        const tmp = document.createElement('input');
        tmp.value = marker;
        document.body.appendChild(tmp);
        tmp.select();
        document.execCommand('copy');
        document.body.removeChild(tmp);
    });
}

// ── Add uploaded card to grid ──
function addSceneCard(filename, epId) {
    const grid = document.getElementById('scene-grid');
    if (!grid) return;
    const noMsg = document.getElementById('scene-no-msg');
    if (noMsg) noMsg.remove();
    const relPath = 'ep_' + epId + '/' + filename;
    const card = document.createElement('div');
    card.className = 'scene-card';
    card.id = 'scene-card-' + filename;
    const marker = '[scene: ' + relPath + ']';
    card.innerHTML =
        '<img src="../uploads/scenes/' + relPath + '" alt="" style="cursor:default;">' +
        '<div style="padding:6px 6px 3px;">' +
            '<div style="font-size:.6rem;color:var(--muted);margin-bottom:3px;">Selecteer → Ctrl+C → plak in tekst</div>' +
            '<input type="text" readonly value="' + marker.replace(/"/g, '&quot;') + '" onclick="this.select()" style="width:100%;font-size:.62rem;font-family:monospace;background:rgba(124,58,237,.08);border:1px solid var(--border);border-radius:4px;padding:4px 6px;color:var(--primary);cursor:text;">' +
        '</div>' +
        '<button type="button" class="scene-card-btn" style="background:#fee2e2;color:#dc2626;border-top:1px solid var(--border);width:100%;" onclick="deleteScene(' + JSON.stringify(filename) + ',this)">✕ Verwijderen</button>';
    grid.insertBefore(card, grid.firstChild);
}

// ── AJAX upload (triggered by button, NOT by form submit) ──
const uploadBtn = document.getElementById('scene-upload-btn');
if (uploadBtn) {
    uploadBtn.addEventListener('click', async () => {
        const fileInput = document.getElementById('scene-file-input');
        const msgEl     = document.getElementById('scene-upload-msg');
        if (!fileInput || !fileInput.files.length) {
            showSceneMsg(msgEl, 'Kies eerst een afbeelding.', 'var(--danger)'); return;
        }
        const fd = new FormData();
        fd.append('upload_scene', '1');
        fd.append('episode_id', EPISODE_ID);
        fd.append('scene_img', fileInput.files[0]);
        uploadBtn.disabled = true;
        uploadBtn.textContent = 'Bezig...';
        msgEl.style.display = 'none';
        try {
            const res  = await fetch('episodes.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) {
                addSceneCard(data.file, data.ep_id);
                fileInput.value = '';
                showSceneMsg(msgEl, '✓ Geüpload!', 'var(--primary)');
            } else {
                showSceneMsg(msgEl, data.error || 'Upload mislukt.', 'var(--danger)');
            }
        } catch {
            showSceneMsg(msgEl, 'Upload mislukt.', 'var(--danger)');
        }
        uploadBtn.disabled = false;
        uploadBtn.textContent = 'Uploaden';
    });
}

// ── AJAX delete ──
async function deleteScene(filename, btn) {
    if (!confirm('Afbeelding verwijderen?')) return;
    const fd = new FormData();
    fd.append('delete_scene', '1');
    fd.append('episode_id', EPISODE_ID);
    fd.append('filename', filename);
    try {
        const res  = await fetch('episodes.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            const card = document.getElementById('scene-card-' + filename);
            if (card) card.remove();
            const grid = document.getElementById('scene-grid');
            if (grid && !grid.querySelector('.scene-card')) {
                grid.innerHTML = '<p id="scene-no-msg" style="font-size:.75rem;color:var(--muted);font-style:italic;grid-column:1/-1;">Nog geen afbeeldingen voor deze aflevering.</p>';
            }
        } else {
            alert(data.error || 'Verwijderen mislukt.');
        }
    } catch {
        alert('Verwijderen mislukt.');
    }
}

// ── COVER UPLOAD ──
const coverUploadBtn = document.getElementById('cover-upload-btn');
if (coverUploadBtn) {
    coverUploadBtn.addEventListener('click', async () => {
        const fileInput = document.getElementById('cover-file-input');
        const pos       = document.getElementById('cover-position').value;
        const msg       = document.getElementById('cover-upload-msg');
        msg.style.display = 'inline';
        if (!fileInput.files.length) { msg.textContent = 'Kies eerst een bestand.'; msg.style.color = '#ef4444'; return; }
        const fd = new FormData();
        fd.append('upload_cover', '1');
        fd.append('ep_id', EPISODE_ID);
        fd.append('position', pos);
        fd.append('cover', fileInput.files[0]);
        coverUploadBtn.textContent = 'Uploaden…';
        try {
            const r    = await fetch('episodes.php', { method: 'POST', body: fd });
            const data = await r.json();
            if (data.ok) {
                addCoverCard(data);
                fileInput.value = '';
                msg.textContent = '✓ Cover geüpload!';
                msg.style.color = '#22c55e';
            } else {
                msg.textContent = data.error || 'Fout bij uploaden.';
                msg.style.color = '#ef4444';
            }
        } catch { msg.textContent = 'Netwerkfout.'; msg.style.color = '#ef4444'; }
        coverUploadBtn.textContent = 'Uploaden';
        setTimeout(() => { msg.textContent = ''; msg.style.display = 'none'; }, 3000);
    });
}

function addCoverCard(data) {
    const noMsg = document.getElementById('cover-no-msg');
    if (noMsg) noMsg.remove();
    const grid     = document.getElementById('cover-grid');
    const posLabel = data.position === 'start' ? 'Begin' : 'Eind';
    const div      = document.createElement('div');
    div.id         = 'cover-card-' + data.id;
    div.style.cssText = 'background:var(--surface);border:1px solid var(--border);border-radius:10px;overflow:hidden;';
    div.innerHTML = `
        <img src="../uploads/covers/ep_${data.ep_id}/${data.file}" style="width:100%;aspect-ratio:2/3;object-fit:cover;display:block;" alt="">
        <div style="padding:7px 10px;">
            <span style="font-size:.68rem;font-weight:700;background:rgba(124,58,237,.15);color:var(--primary);border-radius:4px;padding:2px 8px;">${posLabel}</span>
        </div>
        <button type="button" onclick="deleteCover(${data.id}, this.closest('[id^=cover-card-]'))"
                style="background:#fee2e2;color:#dc2626;border:none;border-top:1px solid var(--border);width:100%;padding:6px;font-size:.72rem;cursor:pointer;">✕ Verwijderen</button>`;
    grid.appendChild(div);
}

async function deleteCover(id, card) {
    if (!confirm('Cover verwijderen?')) return;
    const fd = new FormData();
    fd.append('delete_cover', '1');
    fd.append('cover_id', id);
    try {
        const r    = await fetch('episodes.php', { method: 'POST', body: fd });
        const data = await r.json();
        if (data.ok) {
            if (card) card.remove();
            const grid = document.getElementById('cover-grid');
            if (grid && !grid.querySelector('[id^=cover-card-]')) {
                grid.innerHTML = '<p id="cover-no-msg" style="font-size:.75rem;color:var(--muted);font-style:italic;grid-column:1/-1;">Nog geen covers voor deze aflevering.</p>';
            }
        }
    } catch { alert('Verwijderen mislukt.'); }
}

function showSceneMsg(el, text, color) {
    el.textContent = text;
    el.style.color = color;
    el.style.display = 'inline';
    setTimeout(() => { el.style.display = 'none'; }, 3000);
}

function copyEpisode() {
    const ta  = document.getElementById('ep-content');
    const btn = document.getElementById('copy-ep-btn');
    if (!ta || !ta.value.trim()) return;
    ta.select();
    ta.setSelectionRange(0, ta.value.length);
    document.execCommand('copy');
    window.getSelection()?.removeAllRanges();
    ta.blur();
    btn.textContent = '✓ Gekopieerd!';
    btn.style.color = '#22c55e';
    btn.style.borderColor = '#22c55e';
    setTimeout(() => {
        btn.textContent = '📋 Kopiëren';
        btn.style.color = '';
        btn.style.borderColor = '';
    }, 2000);
}

function updateStats(el) {
    const text = el.value;
    const chars = text.length;
    const words = text.trim() ? text.trim().split(/\s+/).length : 0;
    const readMin = words > 0 ? Math.ceil(words / 200) : 0;
    document.getElementById('char-count').textContent =
        chars.toLocaleString('nl') + ' tekens · ' +
        words.toLocaleString('nl') + ' woorden · ⏱ ~' + readMin + ' min lezen';
}
const ta = document.getElementById('ep-content');
if (ta && ta.value) { updateStats(ta); }

// ── Feature 9: Live Preview ──
let _previewOpen = false;

function togglePreview() {
    _previewOpen = !_previewOpen;
    const pane = document.getElementById('ep-preview-pane');
    const ta   = document.getElementById('ep-content');
    if (!pane) return;
    pane.classList.toggle('open', _previewOpen);
    if (_previewOpen) updatePreview();
}

const EP_NAME_RE = String.raw`[A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞŸ][A-Za-zÀ-ÿ'\-]{0,20}(?:\s[A-Za-zÀ-ÿ'\-]{1,20}){0,2}`;
const _PREVIEW_SPEAKER_A = new RegExp(String.raw`^${EP_NAME_RE}(?:\s*\([^)]*\))?\s*:\s*$`, 'u');

function normalizeContent(raw) {
    if (!raw) return raw;
    const lines = raw.split('\n').map(line => {
        line = line.replace(/\s+$/, '');
        line = line.replace(/^#(?!#)\s*(.+)$/, '## $1');
        line = line.replace(/^>\s*/, '');
        if (/^[-_*]{3,}\s*$/.test(line)) return '';
        line = line.replace(/^\*\*([^*\n:]+(?:\s*\([^)]*\))?)\*\*:\s*/, '$1: ');
        line = line.replace(/^\*([^*\n:]+(?:\s*\([^)]*\))?)\*:\s*/, '$1: ');
        return line;
    });
    const spaced = [];
    for (let i = 0; i < lines.length; i++) {
        spaced.push(lines[i]);
        const curr = lines[i];
        const next = lines[i + 1];
        if (curr !== '' && next !== undefined && next !== '') {
            if (!_PREVIEW_SPEAKER_A.test(curr.trim())) spaced.push('');
        }
    }
    return spaced.join('\n').replace(/\n{3,}/g, '\n\n');
}
const RE_SPK_A   = new RegExp(String.raw`^(${EP_NAME_RE})((?:\s*\([^)]*\))?)\s*:\s*$`, 'u');
const RE_SPK_B   = new RegExp(String.raw`^(${EP_NAME_RE}):\s*((?:\([^)]*\)\s*)?)(.+)`, 'u');

function previewEscHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function previewInline(html) {
    html = html.replace(/\*\*(.+?)\*\*/gs, '<strong>$1</strong>');
    html = html.replace(/\*([^*\n]+)\*/g, '<strong>$1</strong>');
    return html;
}
function previewLine(line) {
    const t = line.trim();
    let m = t.match(RE_SPK_A);
    if (m) return `<span class="ep-speaker">${previewEscHtml(m[1].trim())}</span>${m[2] ? ` <span class="ep-speaker-action">${previewEscHtml(m[2].trim())}</span>` : ''}:`;
    m = t.match(RE_SPK_B);
    if (m) return `<span class="ep-speaker">${previewEscHtml(m[1].trim())}:</span>${m[2] ? ` <span class="ep-speaker-action">${previewEscHtml(m[2].trimEnd())}</span>` : ''} ${previewInline(previewEscHtml(m[3]))}`;
    return previewInline(previewEscHtml(line));
}

function updatePreview() {
    if (!_previewOpen) return;
    const ta   = document.getElementById('ep-content');
    const pane = document.getElementById('ep-preview-pane');
    if (!ta || !pane) return;
    const raw   = normalizeContent(ta.value);
    const lines = raw.split('\n');
    let html = '';
    let buffer = [];

    const flush = () => {
        const block = buffer.join('\n').trim();
        if (block) {
            block.split(/\n{2,}/).forEach(para => {
                para = para.trim();
                if (!para) return;
                if (/^\[scene:/i.test(para)) {
                    html += `<div style="background:rgba(124,58,237,.1);border:1px dashed rgba(124,58,237,.4);border-radius:8px;padding:10px;text-align:center;color:#a78bfa;font-size:.8rem;margin:1em 0;">🖼 ${previewEscHtml(para)}</div>`;
                    return;
                }
                const plines = para.split('\n').map(previewLine).join('<br>');
                html += `<p>${plines}</p>`;
            });
        }
        buffer = [];
    };

    for (const line of lines) {
        if (/^##\s*(.+)$/.test(line)) {
            flush();
            const title = line.replace(/^##\s*/, '');
            html += `<h2>§ ${previewEscHtml(title)}</h2>`;
        } else {
            buffer.push(line);
        }
    }
    flush();
    pane.innerHTML = html || '<em style="color:rgba(255,255,255,.3);">Nog niets om te previewen…</em>';
}

// ── Autosave ──
const AUTOSAVE_KEY = 'tgj_autosave_ep_' + EPISODE_ID;
const autosaveBar  = document.getElementById('autosave-bar');
const autosaveMsg  = document.getElementById('autosave-msg');
let _autosaveTimer = null;
let _lastSavedVal  = null;

function setAutosaveStatus(state, msg) {
    if (!autosaveBar || !autosaveMsg) return;
    autosaveBar.className = 'autosave-bar ' + state;
    autosaveMsg.textContent = msg;
}

function doAutosave() {
    const ta = document.getElementById('ep-content');
    if (!ta) return;
    const val = ta.value;
    if (val === _lastSavedVal) return; // nothing changed
    try {
        localStorage.setItem(AUTOSAVE_KEY, JSON.stringify({
            content: val,
            savedAt: new Date().toISOString(),
        }));
        _lastSavedVal = val;
        const now = new Date();
        const t   = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0') + ':' + now.getSeconds().toString().padStart(2,'0');
        setAutosaveStatus('saved', `Automatisch opgeslagen om ${t}`);
    } catch {
        setAutosaveStatus('unsaved', 'Opslaan mislukt (localStorage vol?)');
    }
}

// Mark unsaved on every keystroke, autosave after 30s of inactivity OR every 30s
if (ta) {
    // Check for a saved draft on load
    try {
        const draft = JSON.parse(localStorage.getItem(AUTOSAVE_KEY) || 'null');
        if (draft && draft.content && draft.content !== ta.value) {
            const savedAt = new Date(draft.savedAt);
            const fmt = savedAt.toLocaleString('nl-NL', { day:'numeric', month:'short', hour:'2-digit', minute:'2-digit' });
            setAutosaveStatus('restored',
                `Niet-opgeslagen concept gevonden van ${fmt} — `
            );
            // Add restore button
            const restoreBtn = document.createElement('button');
            restoreBtn.type = 'button';
            restoreBtn.textContent = 'Herstellen';
            restoreBtn.style.cssText = 'font-size:.7rem;font-weight:700;color:var(--primary);background:none;border:none;cursor:pointer;text-decoration:underline;padding:0;';
            restoreBtn.onclick = () => {
                ta.value = draft.content;
                updateStats(ta);
                updatePreview();
                _lastSavedVal = draft.content;
                setAutosaveStatus('saved', 'Concept hersteld ✓');
                restoreBtn.remove();
            };
            autosaveMsg.after(restoreBtn);
        } else {
            setAutosaveStatus('saved', 'Geen wijzigingen');
        }
    } catch {}

    ta.addEventListener('input', () => {
        setAutosaveStatus('unsaved', 'Niet opgeslagen…');
        clearTimeout(_autosaveTimer);
        _autosaveTimer = setTimeout(doAutosave, 30000); // save 30s after last keystroke
    });

    // Also save on fixed interval (every 30s regardless)
    setInterval(doAutosave, 30000);

    // Save before page unload
    window.addEventListener('beforeunload', (e) => {
        const ta2 = document.getElementById('ep-content');
        if (!ta2 || ta2.value === _lastSavedVal) return;
        doAutosave();
    });

    // Clear autosave when form is submitted (successfully saved to server)
    document.querySelector('form[method="POST"]')?.addEventListener('submit', () => {
        try { localStorage.removeItem(AUTOSAVE_KEY); } catch {}
    });
}
</script>
</body>
</html>
