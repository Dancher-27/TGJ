<?php require_once 'includes/connection.php'; ?>
<?php
$charCount    = $conn->query("SELECT COUNT(*) FROM characters")->fetch_row()[0] ?? 0;
$seasonCount  = $conn->query("SELECT COUNT(*) FROM seasons")->fetch_row()[0] ?? 0;
$eventCount   = $conn->query("SELECT COUNT(*) FROM timeline_events")->fetch_row()[0] ?? 0;
$loreCount    = $conn->query("SELECT COUNT(*) FROM lore")->fetch_row()[0] ?? 0;

// Latest season
$latestSeason = $conn->query("SELECT * FROM seasons ORDER BY sort_order DESC LIMIT 1")->fetch_assoc();

// Upcoming (first upcoming season)
$upcoming = $conn->query("SELECT * FROM seasons WHERE status='upcoming' ORDER BY sort_order ASC LIMIT 1")->fetch_assoc();

// Featured characters (protagonists first)
$featuredChars = $conn->query("
    SELECT * FROM characters
    ORDER BY FIELD(role,'protagonist','anti-hero','antagonist','villain','supporting'), sort_order ASC
    LIMIT 6
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Greatest Journey</title>
    <?php include 'includes/fonts.php'; ?>
    <link rel="stylesheet" href="styles.css">
    <style>
    .particle { position: absolute; border-radius: 50%; animation: float linear infinite; opacity: 0; }
    @keyframes float {
        0%   { transform: translateY(100vh) rotate(0deg); opacity: 0; }
        10%  { opacity: .6; }
        90%  { opacity: .4; }
        100% { transform: translateY(-20px) rotate(720deg); opacity: 0; }
    }
    .featured-chars {
        padding: 80px 0;
        position: relative;
    }
    .featured-header {
        display: flex; align-items: flex-end; justify-content: space-between;
        margin-bottom: 36px;
    }
    .featured-chars-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 20px;
    }
    .current-season-strip {
        background: linear-gradient(135deg, var(--primary-light) 0%, #f5f3ff 100%);
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 36px 40px;
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 32px;
        align-items: center;
        margin: 60px 0;
    }
    @media(max-width:700px){ .current-season-strip { grid-template-columns: 1fr; } }
    .css-eyebrow { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .15em; color: var(--primary); margin-bottom: 8px; }
    .css-title { font-size: 1.6rem; font-weight: 900; margin-bottom: 8px; color: var(--text); }
    .css-desc { font-size: .88rem; color: var(--muted); line-height: 1.7; max-width: 500px; }
    </style>
</head>
<body class="page">
<?php include 'includes/navbar.php'; ?>

<!-- HERO -->
<section class="hero">
    <div class="hero-bg"></div>
    <canvas class="hero-particles" id="particleCanvas"></canvas>
    <div class="hero-content">
        <div class="hero-badge">⚔ Een origineel verhaal</div>
        <h1 class="hero-title">
            <span class="line1">The</span><br>
            <span class="line2">Greatest Journey</span>
        </h1>
        <p class="hero-desc">
            Een episch verhaal van kracht, verraad en bestemming.
            Volg de tijdlijn, ontdek de characters en duik in de wereld.
        </p>
        <div class="hero-actions">
            <a href="timeline.php" class="btn btn-gold">⚔ Bekijk Tijdlijn</a>
            <a href="characters.php" class="btn btn-outline">👥 Characters</a>
            <a href="seasons.php" class="btn btn-purple">📺 Seizoenen</a>
        </div>
        <div id="continue-reading-wrap" style="display:none;margin-top:18px;">
            <a id="continue-reading-btn" href="#"
               style="display:inline-flex;align-items:center;gap:10px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);border-radius:14px;padding:12px 22px;color:#fff;text-decoration:none;font-size:.85rem;font-weight:700;backdrop-filter:blur(8px);transition:all .2s;">
                <span style="font-size:1.1rem;">📖</span>
                <div>
                    <div style="font-size:.63rem;letter-spacing:.1em;text-transform:uppercase;opacity:.6;margin-bottom:1px;">Verderlezen</div>
                    <div id="continue-reading-label">—</div>
                </div>
                <span style="opacity:.5;margin-left:4px;">→</span>
            </a>
        </div>

        <div class="hero-stats">
            <div class="hero-stat">
                <span class="hs-val"><?= $charCount ?></span>
                <span class="hs-lbl">Characters</span>
            </div>
            <div class="hero-stat">
                <span class="hs-val"><?= $seasonCount ?></span>
                <span class="hs-lbl">Seizoenen</span>
            </div>
            <div class="hero-stat">
                <span class="hs-val"><?= $eventCount ?></span>
                <span class="hs-lbl">Events</span>
            </div>
            <div class="hero-stat">
                <span class="hs-val"><?= $loreCount ?></span>
                <span class="hs-lbl">Lore entries</span>
            </div>
        </div>
    </div>
</section>

<div class="container">

    <!-- CURRENT SEASON STRIP -->
    <?php if ($latestSeason): ?>
    <div class="current-season-strip">
        <div>
            <div class="css-eyebrow">
                <?= $latestSeason['status'] === 'ongoing' ? '🔴 Nu bezig' : ($latestSeason['status'] === 'released' ? '✅ Uitgebracht' : '🔜 Binnenkort') ?>
            </div>
            <div class="css-title"><?= htmlspecialchars($latestSeason['title']) ?></div>
            <?php if ($latestSeason['subtitle']): ?>
            <div style="color:var(--gold);font-size:.85rem;margin-bottom:10px;font-weight:600;"><?= htmlspecialchars($latestSeason['subtitle']) ?></div>
            <?php endif; ?>
            <div class="css-desc"><?= htmlspecialchars(substr($latestSeason['description'] ?? '', 0, 180)) ?><?= strlen($latestSeason['description'] ?? '') > 180 ? '…' : '' ?></div>
        </div>
        <a href="seasons.php" class="btn btn-gold">Bekijk seizoenen →</a>
    </div>
    <?php endif; ?>

    <!-- FEATURED CHARACTERS -->
    <?php if (!empty($featuredChars)): ?>
    <div class="featured-chars">
        <div class="featured-header">
            <div>
                <div class="section-title">Characters</div>
                <div class="section-sub">De mensen (en meer) die dit verhaal vormen</div>
            </div>
            <a href="characters.php" class="btn btn-outline btn-sm">Alle characters →</a>
        </div>
        <div class="featured-chars-grid">
        <?php foreach ($featuredChars as $c):
            $img = !empty($c['image']) ? 'uploads/characters/' . $c['image'] : '';
            $hasImg = $img && file_exists(__DIR__ . '/' . $img);
            $roleLabels = ['protagonist'=>'Protagonist','antagonist'=>'Antagonist','supporting'=>'Bijrol','villain'=>'Schurk','anti-hero'=>'Anti-held'];
        ?>
        <a href="character.php?id=<?= $c['id'] ?>" class="char-card">
            <div class="char-cover">
                <?php if ($hasImg): ?>
                    <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($c['name']) ?>">
                <?php else: ?>
                    <div class="char-cover-ph">⚔</div>
                <?php endif; ?>
                <div class="char-role-badge role-<?= $c['role'] ?>"><?= $roleLabels[$c['role']] ?? $c['role'] ?></div>
                <div class="char-status-dot status-<?= $c['status'] ?>"></div>
            </div>
            <div class="char-info">
                <div class="char-name"><?= htmlspecialchars($c['name']) ?></div>
                <?php if ($c['alias']): ?>
                <div class="char-alias">"<?= htmlspecialchars($c['alias']) ?>"</div>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="gold-divider"></div>

    <!-- SECTION CARDS -->
    <div style="padding-bottom:80px;">
        <div class="section-title">Verken de wereld</div>
        <div class="section-sub">Alles wat je moet weten over The Greatest Journey</div>
        <div class="home-sections">
            <a href="timeline.php" class="home-section-card">
                <span class="hsc-icon">📜</span>
                <div class="hsc-title">Tijdlijn</div>
                <div class="hsc-desc">Alle events, battles en keerpunten van het verhaal op een interactieve tijdlijn.</div>
                <span class="hsc-arrow">Bekijk tijdlijn →</span>
            </a>
            <a href="seasons.php" class="home-section-card">
                <span class="hsc-icon">📺</span>
                <div class="hsc-title">Seizoenen</div>
                <div class="hsc-desc">Overzicht van alle seizoenen — wat uit is, wat bezig is en wat nog komt.</div>
                <span class="hsc-arrow">Bekijk seizoenen →</span>
            </a>
            <a href="characters.php" class="home-section-card">
                <span class="hsc-icon">👥</span>
                <div class="hsc-title">Characters</div>
                <div class="hsc-desc">Ontmoet alle characters, hun backstory, krachten en relaties.</div>
                <span class="hsc-arrow">Bekijk characters →</span>
            </a>
            <a href="lore.php" class="home-section-card">
                <span class="hsc-icon">🌍</span>
                <div class="hsc-title">Lore & Wereld</div>
                <div class="hsc-desc">De wereld, facties, magie en geschiedenis achter het verhaal.</div>
                <span class="hsc-arrow">Bekijk lore →</span>
            </a>
            <a href="gallery.php" class="home-section-card">
                <span class="hsc-icon">🎨</span>
                <div class="hsc-title">Gallery</div>
                <div class="hsc-desc">Artwork, covers en sketches van The Greatest Journey.</div>
                <span class="hsc-arrow">Bekijk gallery →</span>
            </a>
        </div>
    </div>

</div>

<footer class="tgj-footer">
    <strong>The Greatest Journey</strong> — Een origineel verhaal
</footer>

<script>
// ── Feature 5: Continue Reading ──
(function() {
    try {
        const last = JSON.parse(localStorage.getItem('tgj_last_ep') || 'null');
        if (!last || !last.id) return;
        const wrap  = document.getElementById('continue-reading-wrap');
        const btn   = document.getElementById('continue-reading-btn');
        const label = document.getElementById('continue-reading-label');
        if (!wrap || !btn || !label) return;
        label.textContent = `S${last.seasonNum} · Afl. ${last.number} — ${last.title}`;
        btn.href = `/Portofolio-opdrachten/project-tgj/episode.php?id=${last.id}`;
        wrap.style.display = 'block';
    } catch {}
})();

// Particle canvas
const canvas = document.getElementById('particleCanvas');
const ctx    = canvas.getContext('2d');
let particles = [];

function resize(){ canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
resize();
window.addEventListener('resize', resize);

function Particle(){
    this.x = Math.random() * canvas.width;
    this.y = canvas.height + 10;
    this.r = Math.random() * 2 + 0.5;
    this.speed = Math.random() * 0.6 + 0.2;
    this.drift = (Math.random() - .5) * 0.5;
    this.opacity = 0;
    this.maxOp = Math.random() * 0.5 + 0.1;
    this.color = Math.random() > .5 ? '212,175,55' : '124,58,237';
    this.life = 0;
    this.maxLife = canvas.height / this.speed;
}

function animate(){
    ctx.clearRect(0,0,canvas.width,canvas.height);
    if (particles.length < 60) particles.push(new Particle());
    particles = particles.filter(p => p.life < p.maxLife);
    particles.forEach(p => {
        p.life++;
        p.y -= p.speed;
        p.x += p.drift;
        const prog = p.life / p.maxLife;
        p.opacity = prog < .1 ? prog * 10 * p.maxOp : prog > .9 ? (1-prog)*10*p.maxOp : p.maxOp;
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r, 0, Math.PI*2);
        ctx.fillStyle = `rgba(${p.color},${p.opacity})`;
        ctx.fill();
    });
    requestAnimationFrame(animate);
}
animate();
</script>
</body>
</html>
