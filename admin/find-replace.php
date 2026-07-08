<?php
session_start();
if (!isset($_SESSION['tgj_admin'])) { header('Location: login.php'); exit(); }
require_once '../includes/connection.php';

$results  = null;
$applied  = false;
$applyLog = [];

// ── PREVIEW ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview'])) {
    $find    = $_POST['find']    ?? '';
    $replace = $_POST['replace'] ?? '';
    $fields  = $_POST['fields']  ?? ['content'];
    $caseSensitive = isset($_POST['case_sensitive']);

    if ($find !== '') {
        $results = [];
        $episodes = $conn->query("
            SELECT e.id, e.number, e.title, e.content, e.description, s.number AS sn, s.title AS st
            FROM episodes e JOIN seasons s ON s.id = e.season_id
            ORDER BY s.number ASC, e.number ASC
        ")->fetch_all(MYSQLI_ASSOC);

        foreach ($episodes as $ep) {
            $hits = [];
            foreach ($fields as $field) {
                if (!isset($ep[$field])) continue;
                $text  = $ep[$field] ?? '';
                $count = $caseSensitive
                    ? substr_count($text, $find)
                    : substr_count(mb_strtolower($text), mb_strtolower($find));
                if ($count > 0) {
                    $hits[$field] = $count;
                }
            }
            if ($hits) {
                // Build a short context snippet from content
                $snippet = '';
                if (isset($ep['content']) && in_array('content', $fields)) {
                    $pos = $caseSensitive
                        ? strpos($ep['content'], $find)
                        : stripos($ep['content'], $find);
                    if ($pos !== false) {
                        $start   = max(0, $pos - 60);
                        $raw     = substr($ep['content'], $start, 160);
                        $snippet = ($start > 0 ? '…' : '') . $raw . '…';
                        // Highlight the found term in snippet
                        $snippet = htmlspecialchars($snippet);
                        $pattern = '/' . preg_quote(htmlspecialchars($find), '/') . '/';
                        if (!$caseSensitive) $pattern .= 'i';
                        $snippet = preg_replace($pattern,
                            '<mark style="background:#7c3aed33;color:#a78bfa;border-radius:3px;padding:0 2px;">$0</mark>',
                            $snippet);
                    }
                }
                $results[] = [
                    'ep'      => $ep,
                    'hits'    => $hits,
                    'snippet' => $snippet,
                ];
            }
        }
    }
}

// ── APPLY ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply'])) {
    $find    = $_POST['find']    ?? '';
    $replace = $_POST['replace'] ?? '';
    $fields  = $_POST['fields']  ?? ['content'];
    $caseSensitive = isset($_POST['case_sensitive']);
    $epIds   = array_map('intval', $_POST['ep_ids'] ?? []);

    if ($find !== '' && !empty($epIds)) {
        foreach ($epIds as $epId) {
            $ep = $conn->query("SELECT * FROM episodes WHERE id=$epId")->fetch_assoc();
            if (!$ep) continue;

            $updates = [];
            $params  = [];
            $types   = '';
            foreach ($fields as $field) {
                if (!isset($ep[$field])) continue;
                $original = $ep[$field] ?? '';
                $new = $caseSensitive
                    ? str_replace($find, $replace, $original)
                    : str_ireplace($find, $replace, $original);
                if ($new !== $original) {
                    $updates[] = "$field = ?";
                    $params[]  = $new;
                    $types    .= 's';
                }
            }

            if ($updates) {
                $params[] = $epId;
                $types   .= 'i';
                $stmt = $conn->prepare("UPDATE episodes SET " . implode(', ', $updates) . " WHERE id=?");
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $applyLog[] = 'Afl. ' . $ep['number'] . ': ' . htmlspecialchars($ep['title']);
            }
        }
        $applied = true;
    }
}

$savedFind    = htmlspecialchars($_POST['find']    ?? '');
$savedReplace = htmlspecialchars($_POST['replace'] ?? '');
$savedFields  = $_POST['fields'] ?? ['content'];
$savedCase    = isset($_POST['case_sensitive']);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Zoek & Vervang — Admin TGJ</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="admin.css">
    <style>
    .fr-card {
        background: var(--card); border: 1px solid var(--border);
        border-radius: 16px; padding: 28px 32px; margin-bottom: 24px;
    }
    .fr-row {
        display: grid; grid-template-columns: 1fr auto 1fr; gap: 16px;
        align-items: end; margin-bottom: 20px;
    }
    .fr-arrow {
        font-size: 1.4rem; color: var(--primary); padding-bottom: 8px;
        text-align: center; font-weight: 900;
    }
    .fr-options { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px; }
    .fr-check { display: flex; align-items: center; gap: 8px; font-size: .83rem; font-weight: 600; cursor: pointer; }
    .fr-check input { accent-color: var(--primary); width: 16px; height: 16px; cursor: pointer; }

    .fr-results-header {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 16px; flex-wrap: wrap; gap: 10px;
    }
    .fr-results-title { font-family: var(--font-display); font-size: 1rem; font-weight: 700; }
    .fr-count {
        font-size: .75rem; font-weight: 700; padding: 4px 12px;
        border-radius: 100px;
    }
    .fr-count.has-results { background: var(--primary-light); color: var(--primary); border: 1px solid var(--border); }
    .fr-count.no-results  { background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; }

    .fr-ep-row {
        border: 1px solid var(--border); border-radius: 12px;
        padding: 14px 18px; margin-bottom: 10px;
        background: var(--surface);
    }
    .fr-ep-top { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 6px; }
    .fr-ep-label { font-weight: 700; font-size: .88rem; }
    .fr-ep-season { font-size: .72rem; color: var(--muted); font-weight: 600; }
    .fr-hit-pills { display: flex; gap: 6px; flex-wrap: wrap; margin-left: auto; }
    .fr-hit-pill {
        font-size: .65rem; font-weight: 700; padding: 3px 10px;
        border-radius: 100px; background: var(--primary-light);
        color: var(--primary); border: 1px solid var(--border);
    }
    .fr-snippet {
        font-size: .78rem; color: var(--muted); font-family: monospace;
        background: #f8f7ff; border-radius: 6px; padding: 8px 12px;
        white-space: pre-wrap; word-break: break-word; margin-top: 6px;
        border: 1px solid var(--border);
    }
    .fr-select-row { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }

    .fr-apply-bar {
        background: linear-gradient(135deg, #1e1b4b, #312e81);
        border-radius: 14px; padding: 18px 24px;
        display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
        border: 1px solid rgba(124,58,237,.3);
    }
    .fr-apply-info { flex: 1; color: rgba(255,255,255,.8); font-size: .85rem; }
    .fr-apply-info strong { color: #fff; }

    .fr-success {
        background: #f0fdf4; border: 1px solid #bbf7d0;
        border-radius: 14px; padding: 20px 24px; margin-bottom: 24px;
    }
    .fr-success h3 { color: #16a34a; font-size: .95rem; margin-bottom: 10px; }
    .fr-success ul { margin: 0; padding-left: 18px; font-size: .82rem; color: #166534; }

    .fr-warning {
        background: #fffbeb; border: 1px solid #fde68a;
        border-radius: 10px; padding: 12px 16px;
        font-size: .8rem; color: #92400e; margin-bottom: 16px;
        display: flex; gap: 8px; align-items: flex-start;
    }
    </style>
</head>
<body class="page admin-body">
<?php include 'admin-nav.php'; ?>
<div class="admin-container">
    <div class="admin-topbar">
        <h1>🔍 Zoek &amp; Vervang — Afleveringen</h1>
    </div>

    <?php if ($applied): ?>
    <div class="fr-success">
        <h3>✅ Succesvol toegepast!</h3>
        <?php if ($applyLog): ?>
        <ul><?php foreach ($applyLog as $l): ?><li><?= $l ?></li><?php endforeach; ?></ul>
        <?php else: ?>
        <p style="font-size:.82rem;color:#166534;">Geen afleveringen gewijzigd (geen matches gevonden).</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="fr-card">
        <form method="POST" id="fr-form">

            <!-- Find → Replace row -->
            <div class="fr-row">
                <div class="form-group" style="margin:0;">
                    <label>Zoeken naar</label>
                    <input type="text" name="find" class="form-control"
                        value="<?= $savedFind ?>" placeholder="bijv. Danshu" required autofocus>
                </div>
                <div class="fr-arrow">→</div>
                <div class="form-group" style="margin:0;">
                    <label>Vervangen door</label>
                    <input type="text" name="replace" class="form-control"
                        value="<?= $savedReplace ?>" placeholder="bijv. Nezzie">
                </div>
            </div>

            <!-- Options -->
            <div class="fr-options">
                <span style="font-size:.78rem;font-weight:700;color:var(--muted);align-self:center;">Zoek in:</span>
                <?php
                $fieldOptions = [
                    'content'     => '📖 Inhoud',
                    'title'       => '🏷 Titel',
                    'description' => '📝 Beschrijving',
                ];
                foreach ($fieldOptions as $fk => $fl): ?>
                <label class="fr-check">
                    <input type="checkbox" name="fields[]" value="<?= $fk ?>"
                        <?= in_array($fk, $savedFields) ? 'checked' : '' ?>>
                    <?= $fl ?>
                </label>
                <?php endforeach; ?>
                <label class="fr-check" style="margin-left:auto;">
                    <input type="checkbox" name="case_sensitive" <?= $savedCase ? 'checked' : '' ?>>
                    Hoofdlettergevoelig
                </label>
            </div>

            <div class="fr-warning">
                ⚠️ <span>Dit wijzigt rechtstreeks de database. Controleer de preview goed voordat je op <strong>Toepassen</strong> klikt.</span>
            </div>

            <button type="submit" name="preview" class="btn btn-gold">🔎 Preview bekijken</button>
        </form>
    </div>

    <?php if ($results !== null): ?>
    <div class="fr-card">
        <div class="fr-results-header">
            <div class="fr-results-title">Preview resultaten</div>
            <span class="fr-count <?= count($results) ? 'has-results' : 'no-results' ?>">
                <?= count($results) ?> aflevering<?= count($results) !== 1 ? 'en' : '' ?> gevonden
            </span>
        </div>

        <?php if (empty($results)): ?>
        <div style="text-align:center;padding:32px;color:var(--muted);">
            <div style="font-size:2rem;margin-bottom:10px;">🔍</div>
            <div style="font-weight:700;">Geen resultaten</div>
            <div style="font-size:.85rem;margin-top:4px;">"<?= $savedFind ?>" komt niet voor in de geselecteerde afleveringen.</div>
        </div>

        <?php else: ?>
        <!-- Select all/none -->
        <div class="fr-select-row">
            <label class="fr-check">
                <input type="checkbox" id="select-all" checked onchange="toggleAll(this)">
                Alles selecteren
            </label>
            <span style="font-size:.75rem;color:var(--muted);">
                Totaal: <?= array_sum(array_map(fn($r) => array_sum($r['hits']), $results)) ?> voorkomens
            </span>
        </div>

        <form method="POST" id="apply-form">
            <input type="hidden" name="find"    value="<?= $savedFind ?>">
            <input type="hidden" name="replace" value="<?= $savedReplace ?>">
            <?php if ($savedCase): ?>
            <input type="hidden" name="case_sensitive" value="1">
            <?php endif; ?>
            <?php foreach ($savedFields as $f): ?>
            <input type="hidden" name="fields[]" value="<?= htmlspecialchars($f) ?>">
            <?php endforeach; ?>

            <?php foreach ($results as $r):
                $ep = $r['ep'];
                $totalHits = array_sum($r['hits']);
            ?>
            <div class="fr-ep-row">
                <div class="fr-ep-top">
                    <label class="fr-check">
                        <input type="checkbox" class="ep-check" name="ep_ids[]" value="<?= $ep['id'] ?>" checked>
                    </label>
                    <span class="fr-ep-label">
                        Afl. <?= $ep['number'] ?>: <?= htmlspecialchars($ep['title']) ?>
                    </span>
                    <span class="fr-ep-season">S<?= $ep['sn'] ?> — <?= htmlspecialchars($ep['st']) ?></span>
                    <div class="fr-hit-pills">
                        <?php foreach ($r['hits'] as $field => $count): ?>
                        <span class="fr-hit-pill"><?= $fieldOptions[$field] ?? $field ?>: <?= $count ?>×</span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if ($r['snippet']): ?>
                <div class="fr-snippet"><?= $r['snippet'] ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <div class="fr-apply-bar" style="margin-top:20px;">
                <div class="fr-apply-info">
                    Vervang <strong>"<?= $savedFind ?>"</strong> door <strong>"<?= $savedReplace ?>"</strong>
                    in de geselecteerde afleveringen.
                </div>
                <button type="submit" name="apply" class="btn btn-gold"
                    onclick="return confirm('Zeker weten? Dit kan niet ongedaan worden gemaakt.')">
                    ✅ Toepassen
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleAll(master) {
    document.querySelectorAll('.ep-check').forEach(cb => cb.checked = master.checked);
}
document.addEventListener('change', e => {
    if (e.target.classList.contains('ep-check')) {
        const all   = document.querySelectorAll('.ep-check');
        const checked = document.querySelectorAll('.ep-check:checked');
        const master = document.getElementById('select-all');
        if (master) master.checked = all.length === checked.length;
    }
});
</script>
</body>
</html>
