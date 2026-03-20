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
    <a href="character.php?id=<?= $c['id'] ?>"
       class="char-card"
       data-role="<?= $c['role'] ?>"
       data-faction="faction-<?= $c['faction_id'] ?>">
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
        document.querySelectorAll('.char-card').forEach(card => {
            if (f === 'all') { card.style.display = ''; return; }
            const matchRole    = card.dataset.role === f;
            const matchFaction = card.dataset.faction === f;
            card.style.display = (matchRole || matchFaction) ? '' : 'none';
        });
    });
});
</script>
</body>
</html>
