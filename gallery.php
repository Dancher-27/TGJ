<?php require_once 'includes/connection.php'; ?>
<?php
$items = $conn->query("SELECT * FROM gallery ORDER BY uploaded_at DESC")->fetch_all(MYSQLI_ASSOC);
$categories = array_unique(array_column($items, 'category'));
sort($categories);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery — The Greatest Journey</title>
    <?php include 'includes/fonts.php'; ?>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="page">
<?php include 'includes/navbar.php'; ?>

<div class="container">
    <div class="section-header">
        <h1 class="section-title">Gallery</h1>
        <p class="section-sub">Artwork, covers en sketches van The Greatest Journey</p>
    </div>

    <div class="filter-bar">
        <button class="filter-btn active" data-cat="all">Alle</button>
        <?php foreach ($categories as $cat): ?>
        <button class="filter-btn" data-cat="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></button>
        <?php endforeach; ?>
    </div>

    <?php if (empty($items)): ?>
    <div class="empty-state">
        <span class="empty-icon">🎨</span>
        <div class="empty-title">Nog geen afbeeldingen</div>
        <div class="empty-desc">Voeg artwork toe via het admin panel.</div>
    </div>
    <?php else: ?>
    <div class="gallery-grid" id="galleryGrid">
    <?php foreach ($items as $item):
        $src = 'uploads/gallery/' . $item['image'];
    ?>
    <div class="gallery-item" data-cat="<?= htmlspecialchars($item['category']) ?>" onclick="openLightbox('<?= htmlspecialchars($src) ?>','<?= htmlspecialchars(addslashes($item['title'])) ?>')">
        <img src="<?= htmlspecialchars($src) ?>" alt="<?= htmlspecialchars($item['title']) ?>" loading="lazy">
        <div class="gallery-overlay">
            <div class="gallery-overlay-text"><?= htmlspecialchars($item['title']) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <button class="lightbox-close" onclick="closeLightbox()">✕</button>
    <img id="lightboxImg" src="" alt="">
</div>

<footer class="tgj-footer"><strong>The Greatest Journey</strong></footer>

<script>
document.querySelectorAll('.filter-btn[data-cat]').forEach(btn => {
    btn.addEventListener('click', function(){
        document.querySelectorAll('.filter-btn[data-cat]').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const cat = this.dataset.cat;
        document.querySelectorAll('.gallery-item').forEach(item => {
            item.style.display = (cat === 'all' || item.dataset.cat === cat) ? '' : 'none';
        });
    });
});

function openLightbox(src, title){
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').classList.add('open');
    event.stopPropagation();
}
function closeLightbox(){
    document.getElementById('lightbox').classList.remove('open');
    document.getElementById('lightboxImg').src = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
</script>
</body>
</html>
