<?php require_once 'includes/connection.php'; ?>
<?php
$factions = $conn->query("SELECT * FROM factions ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$characters = $conn->query("
    SELECT c.*, f.name AS faction_name, f.color AS faction_color
    FROM characters c
    LEFT JOIN factions f ON f.id = c.faction_id
    ORDER BY FIELD(c.role,'protagonist','anti-hero','antagonist','villain','supporting'), c.sort_order ASC, c.name ASC
")->fetch_all(MYSQLI_ASSOC);

$roleLabels = ['protagonist'=>'Protagonist','antagonist'=>'Antagonist','supporting'=>'Bijrol','villain'=>'Schurk','anti-hero'=>'Anti-held'];
$statusLabels = ['alive'=>'Levend','deceased'=>'Overleden','unknown'=>'Onbekend'];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Characters — The Greatest Journey</title>
    <?php include 'includes/fonts.php'; ?>
    <link rel="stylesheet" href="styles.css">
    <style>
    .char-card-wrap { position: relative; }
    .char-fav-btn {
        position: absolute; top: 7px; right: 7px; z-index: 2;
        background: rgba(0,0,0,.55); border: none;
        border-radius: 50%; width: 30px; height: 30px;
        font-size: .9rem; line-height: 1; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        backdrop-filter: blur(4px);
        transition: transform .15s, background .15s;
    }
    .char-fav-btn:hover { transform: scale(1.2); background: rgba(0,0,0,.75); }
    .char-fav-btn.fav-active { background: rgba(220,38,38,.75); }
    </style>
</head>
<body class="page">
<?php include 'includes/navbar.php'; ?>

<div class="container">
    <div class="section-header">
        <h1 class="section-title">Characters</h1>
        <p class="section-sub">De mensen en wezens die The Greatest Journey bevolken</p>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
        <button class="filter-btn active" data-filter="all">Alle</button>
        <button class="filter-btn" data-filter="protagonist">Protagonist</button>
        <button class="filter-btn" data-filter="antagonist">Antagonist</button>
        <button class="filter-btn" data-filter="villain">Schurk</button>
        <button class="filter-btn" data-filter="anti-hero">Anti-held</button>
        <button class="filter-btn" data-filter="supporting">Bijrol</button>
        <?php foreach ($factions as $f): ?>
        <button class="filter-btn" data-filter="faction-<?= $f['id'] ?>" style="border-color:<?= htmlspecialchars($f['color']) ?>20;color:<?= htmlspecialchars($f['color']) ?>">
            <?= htmlspecialchars($f['name']) ?>
        </button>
        <?php endforeach; ?>
    </div>

    <?php if (empty($characters)): ?>
    <div class="empty-state">
        <span class="empty-icon">👥</span>
        <div class="empty-title">Nog geen characters</div>
        <div class="empty-desc">Voeg characters toe via het admin panel.</div>
    </div>
    <?php else: ?>
    <div class="char-grid" id="charGrid">
    <?php foreach ($characters as $c):
        $img = !empty($c['image']) ? 'uploads/characters/' . $c['image'] : '';
        $hasImg = $img && file_exists(__DIR__ . '/' . $img);
    ?>
    <div class="char-card-wrap"
         data-role="<?= $c['role'] ?>"
         data-faction="faction-<?= $c['faction_id'] ?>">
        <a href="character.php?id=<?= $c['id'] ?>" class="char-card">
            <div class="char-cover">
                <?php if ($hasImg): ?>
                    <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($c['name']) ?>" loading="lazy">
                <?php else: ?>
                    <div class="char-cover-ph">⚔</div>
                <?php endif; ?>
                <div class="char-role-badge role-<?= $c['role'] ?>"><?= $roleLabels[$c['role']] ?? $c['role'] ?></div>
                <div class="char-status-dot status-<?= $c['status'] ?>" title="<?= $statusLabels[$c['status']] ?>"></div>
            </div>
            <div class="char-info">
                <div class="char-name"><?= htmlspecialchars($c['name']) ?></div>
                <?php if ($c['alias']): ?>
                <div class="char-alias">"<?= htmlspecialchars($c['alias']) ?>"</div>
                <?php endif; ?>
                <?php if ($c['faction_name']): ?>
                <div class="char-faction">
                    <div class="faction-dot" style="background:<?= htmlspecialchars($c['faction_color'] ?? '#7c3aed') ?>"></div>
                    <?= htmlspecialchars($c['faction_name']) ?>
                </div>
                <?php endif; ?>
            </div>
        </a>
        <button class="char-fav-btn" type="button"
                data-char-id="<?= $c['id'] ?>"
                onclick="toggleCharFav(<?= $c['id'] ?>, this)"
                title="Toevoegen aan favorieten">🤍</button>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<footer class="tgj-footer"><strong>The Greatest Journey</strong></footer>

<script>
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function(){
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const f = this.dataset.filter;
        document.querySelectorAll('.char-card-wrap').forEach(card => {
            if (f === 'all') { card.style.display = ''; return; }
            const matchRole    = card.dataset.role === f;
            const matchFaction = card.dataset.faction === f;
            card.style.display = (matchRole || matchFaction) ? '' : 'none';
        });
    });
});

// ── Favorites ──
const FAV_KEY_CHARS = 'tgj_fav_chars';
function getFavChars() { try { return JSON.parse(localStorage.getItem(FAV_KEY_CHARS)||'[]'); } catch { return []; } }
function toggleCharFav(id, btn) {
    let favs = getFavChars();
    const idx = favs.indexOf(id);
    if (idx === -1) { favs.push(id); } else { favs.splice(idx, 1); }
    localStorage.setItem(FAV_KEY_CHARS, JSON.stringify(favs));
    updateCharBtn(btn, favs.includes(id));
}
function updateCharBtn(btn, active) {
    btn.textContent = active ? '❤' : '🤍';
    btn.classList.toggle('fav-active', active);
    btn.title = active ? 'Verwijder uit favorieten' : 'Toevoegen aan favorieten';
}
// Init all heart buttons on load
document.querySelectorAll('.char-fav-btn').forEach(btn => {
    const id = parseInt(btn.dataset.charId);
    updateCharBtn(btn, getFavChars().includes(id));
});
</script>
</body>
</html>
