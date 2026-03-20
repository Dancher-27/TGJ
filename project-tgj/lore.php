<?php require_once 'includes/connection.php'; ?>
<?php
$entries  = $conn->query("SELECT * FROM lore ORDER BY sort_order ASC, id ASC")->fetch_all(MYSQLI_ASSOC);
$categories = array_unique(array_column($entries, 'category'));
sort($categories);
$activecat = $_GET['cat'] ?? 'all';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lore — The Greatest Journey</title>
    <?php include 'includes/fonts.php'; ?>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="page">
<?php include 'includes/navbar.php'; ?>

<div class="container">
    <div class="section-header">
        <h1 class="section-title">Lore & Wereld</h1>
        <p class="section-sub">De wereld, magie, facties en geschiedenis achter het verhaal</p>
    </div>

    <?php if (empty($entries)): ?>
    <div class="empty-state" style="padding:80px 0;">
        <span class="empty-icon">🌍</span>
        <div class="empty-title">Nog geen lore entries</div>
        <div class="empty-desc">Voeg lore toe via het admin panel.</div>
    </div>
    <?php else: ?>
    <div class="lore-grid">
        <!-- Sidebar -->
        <div class="lore-sidebar">
            <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:var(--muted);margin-bottom:12px;">Categorieën</div>
            <ul class="lore-category-list">
                <li><a href="#" class="<?= $activecat==='all'?'active':'' ?>" data-cat="all">Alle</a></li>
                <?php foreach ($categories as $cat): ?>
                <li><a href="#" class="<?= $activecat===$cat?'active':'' ?>" data-cat="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Entries -->
        <div class="lore-entries" id="loreEntries">
        <?php foreach ($entries as $e):
            $img = !empty($e['image']) ? 'uploads/lore/' . $e['image'] : '';
            $hasImg = $img && file_exists(__DIR__ . '/' . $img);
        ?>
        <div class="lore-entry" data-cat="<?= htmlspecialchars($e['category']) ?>">
            <?php if ($hasImg): ?>
            <img src="<?= htmlspecialchars($img) ?>" class="lore-entry-img" alt="">
            <?php endif; ?>
            <div class="lore-entry-body">
                <div class="lore-entry-cat"><?= htmlspecialchars($e['category']) ?></div>
                <h2 class="lore-entry-title"><?= htmlspecialchars($e['title']) ?></h2>
                <div class="lore-entry-content"><?= nl2br(htmlspecialchars($e['content'])) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<footer class="tgj-footer"><strong>The Greatest Journey</strong></footer>

<script>
document.querySelectorAll('.lore-category-list a').forEach(a => {
    a.addEventListener('click', function(e){
        e.preventDefault();
        document.querySelectorAll('.lore-category-list a').forEach(x => x.classList.remove('active'));
        this.classList.add('active');
        const cat = this.dataset.cat;
        document.querySelectorAll('.lore-entry').forEach(entry => {
            entry.style.display = (cat === 'all' || entry.dataset.cat === cat) ? '' : 'none';
        });
    });
});
</script>
</body>
</html>
