<?php
require_once 'includes/connection.php';
require_once 'includes/helpers.php';
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: characters.php'); exit(); }

$stmt = $conn->prepare("
    SELECT c.*, f.name AS faction_name, f.color AS faction_color, f.description AS faction_desc
    FROM characters c
    LEFT JOIN factions f ON f.id = c.faction_id
    WHERE c.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$char = $stmt->get_result()->fetch_assoc();
if (!$char) { header('Location: characters.php'); exit(); }

// Relations
$rels = $conn->prepare("
    SELECT cr.relation, cr.description AS rel_desc, c.id, c.name, c.image, c.role
    FROM character_relations cr
    JOIN characters c ON c.id = cr.related_id
    WHERE cr.character_id = ?
");
$rels->bind_param("i", $id);
$rels->execute();
$relations = $rels->get_result()->fetch_all(MYSQLI_ASSOC);

// Seasons appeared in
$seas = $conn->prepare("
    SELECT s.id, s.number, s.title FROM character_seasons cs
    JOIN seasons s ON s.id = cs.season_id
    WHERE cs.character_id = ?
    ORDER BY s.number
");
$seas->bind_param("i", $id);
$seas->execute();
$appeared = $seas->get_result()->fetch_all(MYSQLI_ASSOC);

$roleLabels = ['protagonist'=>'Protagonist','antagonist'=>'Antagonist','supporting'=>'Bijrol','villain'=>'Schurk','anti-hero'=>'Anti-held'];
$statusLabels = ['alive'=>'Levend','deceased'=>'Overleden','unknown'=>'Onbekend'];
$statusColors = ['alive'=>'db-green','deceased'=>'db-red','unknown'=>'db-gray'];
$roleColors   = ['protagonist'=>'db-gold','antagonist'=>'db-red','villain'=>'db-red','anti-hero'=>'db-purple','supporting'=>'db-gray'];
$relLabels = ['friend'=>'Vriend','enemy'=>'Vijand','rival'=>'Rivaal','family'=>'Familie','mentor'=>'Mentor','student'=>'Student','love'=>'Liefde'];

// Episodes this character appears in (name/alias mentioned in content or title)
$charName  = $conn->real_escape_string($char['name']);
$charAlias = $conn->real_escape_string($char['alias'] ?? '');
$epSearch  = "SELECT e.id, e.number, e.title, e.description, s.number AS snum, s.title AS stitle
              FROM episodes e JOIN seasons s ON s.id = e.season_id
              WHERE e.status = 'published' AND (
                  e.title LIKE '%$charName%'
                  OR e.content LIKE '%$charName%'"
           . ($charAlias ? " OR e.title LIKE '%$charAlias%' OR e.content LIKE '%$charAlias%'" : "")
           . ") ORDER BY s.number ASC, e.sort_order ASC, e.number ASC LIMIT 20";
$appearsInEps = $conn->query($epSearch)->fetch_all(MYSQLI_ASSOC);

// Auto-migration
$conn->query("CREATE TABLE IF NOT EXISTS character_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    character_id INT NOT NULL,
    image VARCHAR(255) NOT NULL,
    caption VARCHAR(255) DEFAULT '',
    sort_order INT DEFAULT 0,
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
)");

$img = !empty($char['image']) ? 'uploads/characters/' . $char['image'] : '';
$hasImg = $img && file_exists(__DIR__ . '/' . $img);

// Nexus types for this character
$charNexusTypes = [];
$ntResult = $conn->query("
    SELECT nt.name, nt.emoji, nt.color, nt.slug
    FROM character_nexus_types cnt
    JOIN nexus_types nt ON nt.id = cnt.nexus_type_id
    WHERE cnt.character_id = $id
    ORDER BY nt.sort_order
");
if ($ntResult) $charNexusTypes = $ntResult->fetch_all(MYSQLI_ASSOC);

// Extra images
$extraImgs = $conn->query("
    SELECT * FROM character_images WHERE character_id = $id ORDER BY sort_order ASC, id ASC
")->fetch_all(MYSQLI_ASSOC);

// Build full gallery: main image first, then extras
$gallery = [];
if ($hasImg) {
    $gallery[] = ['src' => $img, 'caption' => 'Huidig'];
}
foreach ($extraImgs as $xi) {
    $xsrc = 'uploads/characters/' . $xi['image'];
    if (file_exists(__DIR__ . '/' . $xsrc)) {
        $gallery[] = ['src' => $xsrc, 'caption' => $xi['caption']];
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($char['name']) ?> — The Greatest Journey</title>
    <?php include 'includes/fonts.php'; ?>
    <link rel="stylesheet" href="styles.css">
    <style>
    /* ── PHOTO GALLERY STRIP ── */
    .char-img-counter {
        position: absolute; top: 10px; right: 10px;
        background: rgba(0,0,0,.55); color: #fff;
        font-size: .65rem; font-weight: 700;
        padding: 3px 9px; border-radius: 100px;
        backdrop-filter: blur(4px);
    }
    .char-img-caption {
        text-align: center;
        font-size: .72rem; font-weight: 700;
        color: var(--muted);
        min-height: 1.2em;
        margin: 8px 0 10px;
        letter-spacing: .03em;
        text-transform: uppercase;
    }
    .char-thumb-strip {
        display: flex; gap: 6px; flex-wrap: wrap;
        justify-content: center;
        margin-bottom: 14px;
    }
    .char-thumb {
        width: 54px; height: 68px;
        border-radius: 8px; overflow: hidden;
        border: 2px solid transparent;
        cursor: pointer; padding: 0;
        background: var(--surface);
        transition: all .18s;
        flex-direction: column;
        position: relative;
    }
    .char-thumb img {
        width: 100%; height: 100%;
        object-fit: cover; display: block;
        transition: transform .2s;
    }
    .char-thumb:hover { border-color: var(--primary); transform: translateY(-2px); }
    .char-thumb:hover img { transform: scale(1.06); }
    .char-thumb.active { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
    .char-thumb span {
        display: none; /* caption shown in main caption area, not on thumb */
    }

    /* Fade animation on main portrait */
    #char-main-img {
        transition: opacity .22s ease;
    }
    #char-main-img.fading { opacity: 0; }

    @media (max-width: 480px) {
        .char-detail-name { font-size: 1.55rem; }
        .char-detail-alias { font-size: .88rem; }
        .char-desc-block p { font-size: .85rem; }
        .relations-grid { gap: 8px; }
        .relation-chip { padding: 7px 12px; font-size: .78rem; }
    }
    </style>
</head>
<body class="page">
<?php include 'includes/navbar.php'; ?>


<div class="container" style="position:relative;z-index:1;">
    <div style="padding-top:40px;">
        <a href="characters.php" style="color:var(--muted);text-decoration:none;font-size:.82rem;">← Terug naar characters</a>
    </div>

    <div class="char-hero">
        <!-- Portrait + gallery -->
        <div class="char-portrait-wrap">

            <!-- Main image -->
            <div style="position:relative;">
                <?php if (!empty($gallery)): ?>
                    <img src="<?= htmlspecialchars($gallery[0]['src']) ?>"
                         class="char-portrait" id="char-main-img"
                         alt="<?= htmlspecialchars($char['name']) ?>">
                    <?php if (count($gallery) > 1): ?>
                    <div class="char-img-counter" id="char-img-counter">1 / <?= count($gallery) ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="char-portrait-ph">⚔</div>
                <?php endif; ?>
            </div>

            <!-- Caption -->
            <?php if (count($gallery) > 1): ?>
            <div class="char-img-caption" id="char-img-caption">
                <?= htmlspecialchars($gallery[0]['caption'] ?: '') ?>
            </div>
            <?php endif; ?>

            <!-- Thumbnail strip -->
            <?php if (count($gallery) > 1): ?>
            <div class="char-thumb-strip">
                <?php foreach ($gallery as $gi => $gitem): ?>
                <button class="char-thumb <?= $gi === 0 ? 'active' : '' ?>"
                        onclick="swapPhoto(<?= $gi ?>)"
                        title="<?= htmlspecialchars($gitem['caption'] ?: 'Foto '.($gi+1)) ?>">
                    <img src="<?= htmlspecialchars($gitem['src']) ?>" alt="">
                    <?php if ($gitem['caption']): ?>
                    <span><?= htmlspecialchars($gitem['caption']) ?></span>
                    <?php endif; ?>
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Faction badge -->
            <?php if ($char['faction_name']): ?>
            <div style="text-align:center;margin-top:<?= count($gallery) > 1 ? '10px' : '0' ?>;">
                <div style="display:inline-block;background:var(--card);border:1px solid var(--border);border-radius:100px;padding:6px 18px;font-size:.75rem;font-weight:700;color:<?= htmlspecialchars($char['faction_color']) ?>;">
                    <?= htmlspecialchars($char['faction_name']) ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Info -->
        <div style="padding-top:20px;">
            <h1 class="char-detail-name"><?= htmlspecialchars($char['name']) ?></h1>
            <?php if ($char['alias']): ?>
            <div class="char-detail-alias">"<?= htmlspecialchars($char['alias']) ?>"</div>
            <?php endif; ?>

            <div class="char-detail-badges" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <span class="detail-badge <?= $roleColors[$char['role']] ?? 'db-gray' ?>"><?= $roleLabels[$char['role']] ?? $char['role'] ?></span>
                <span class="detail-badge <?= $statusColors[$char['status']] ?? 'db-gray' ?>"><?= $statusLabels[$char['status']] ?></span>
                <?php if ($char['age']): ?>
                <span class="detail-badge db-gray">Leeftijd: <?= htmlspecialchars($char['age']) ?></span>
                <?php endif; ?>
                <button id="char-fav-btn" type="button" onclick="toggleCharFav()"
                        style="background:rgba(220,38,38,.1);border:1.5px solid rgba(220,38,38,.3);color:#f87171;border-radius:100px;padding:5px 14px;font-size:.78rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:5px;transition:all .15s;font-family:inherit;"
                        title="Toevoegen aan favorieten">🤍 Favoriet</button>
            </div>

            <?php if (!empty($charNexusTypes)): ?>
            <div style="display:flex;flex-wrap:wrap;gap:7px;margin-top:12px;">
                <?php foreach ($charNexusTypes as $nt): ?>
                <a href="lore.php#types"
                   style="display:inline-flex;align-items:center;gap:5px;background:<?= htmlspecialchars($nt['color']) ?>15;border:1.5px solid <?= htmlspecialchars($nt['color']) ?>44;color:<?= htmlspecialchars($nt['color']) ?>;border-radius:100px;padding:4px 13px;font-size:.72rem;font-weight:800;text-decoration:none;letter-spacing:.04em;transition:all .15s;"
                   onmouseover="this.style.background='<?= htmlspecialchars($nt['color']) ?>28'"
                   onmouseout="this.style.background='<?= htmlspecialchars($nt['color']) ?>15'">
                    <?= htmlspecialchars($nt['emoji']) ?> <?= htmlspecialchars($nt['name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($char['description']): ?>
            <div class="char-desc-block">
                <h4>Over dit character</h4>
                <div class="db-parsed-desc"><?= renderDescription($char['description']) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($char['abilities']): ?>
            <div class="char-desc-block">
                <h4>Krachten & Abilities</h4>
                <div class="db-parsed-desc"><?= renderDescription($char['abilities']) ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($appeared)): ?>
            <div class="char-desc-block">
                <h4>Verschijnt in</h4>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <?php foreach ($appeared as $s): ?>
                    <a href="seasons.php" class="detail-badge db-purple" style="text-decoration:none;">
                        S<?= $s['number'] ?> — <?= htmlspecialchars($s['title']) ?>
                    </a>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($relations)): ?>
            <div class="char-desc-block">
                <h4>Relaties</h4>
                <div class="relations-grid">
                <?php foreach ($relations as $r):
                    $rImg = !empty($r['image']) ? 'uploads/characters/' . $r['image'] : '';
                    $rHasImg = $rImg && file_exists(__DIR__ . '/' . $rImg);
                ?>
                <a href="character.php?id=<?= $r['id'] ?>" class="relation-chip">
                    <div class="relation-av">
                        <?php if ($rHasImg): ?>
                            <img src="<?= htmlspecialchars($rImg) ?>">
                        <?php else: ?>
                            <?= strtoupper(substr($r['name'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:.82rem;"><?= htmlspecialchars($r['name']) ?></div>
                        <div style="font-size:.7rem;color:var(--gold);"><?= $relLabels[$r['relation']] ?? $r['relation'] ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Appears in episodes -->
    <?php if (!empty($appearsInEps)): ?>
    <div style="padding:0 0 36px;">
        <div class="gold-divider"></div>
        <h2 style="font-size:1.1rem;font-weight:900;margin-bottom:16px;">📖 Verschijnt in afleveringen</h2>
        <div style="display:flex;flex-direction:column;gap:8px;">
        <?php foreach ($appearsInEps as $ae): ?>
        <a href="episode.php?id=<?= $ae['id'] ?>" style="display:grid;grid-template-columns:44px 1fr;gap:12px;align-items:center;background:var(--card);border:1px solid var(--border);border-radius:12px;padding:10px 16px;text-decoration:none;color:var(--text);transition:all .15s;" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
            <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,var(--primary),#6d28d9);display:flex;align-items:center;justify-content:center;flex-direction:column;flex-shrink:0;">
                <span style="font-size:.48rem;font-weight:800;color:rgba(255,255,255,.65);text-transform:uppercase;">Afl.</span>
                <span style="font-size:1rem;font-weight:900;color:#fff;font-family:var(--font-display);line-height:1;"><?= $ae['number'] ?></span>
            </div>
            <div style="min-width:0;">
                <div style="font-weight:700;font-size:.88rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($ae['title']) ?></div>
                <div style="font-size:.7rem;color:var(--muted);">S<?= $ae['snum'] ?> — <?= htmlspecialchars($ae['stitle']) ?></div>
            </div>
        </a>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Backstory -->
    <?php if ($char['backstory']): ?>
    <div style="padding:0 0 80px;">
        <div class="gold-divider"></div>
        <h2 style="font-size:1.4rem;font-weight:900;margin-bottom:20px;">Backstory</h2>
        <?php if ($char['is_spoiler']): ?>
        <div class="spoiler-blur" onclick="this.classList.remove('spoiler-blur')" title="Klik om spoiler te onthullen">
        <?php endif; ?>
            <div class="db-parsed-desc" style="max-width:800px;"><?= renderDescription($char['backstory']) ?></div>
        <?php if ($char['is_spoiler']): ?>
        </div>
        <p style="font-size:.75rem;color:var(--muted);margin-top:8px;">⚠️ Spoiler — klik om te lezen</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<footer class="tgj-footer"><strong>The Greatest Journey</strong></footer>
<script>
document.querySelectorAll('.dp-stat-fill').forEach(b => { void b.offsetWidth; b.classList.add('animated'); });

// ── Favorites ──
const _CHAR_ID = <?= (int)$char['id'] ?>;
const FAV_KEY_CHARS = 'tgj_fav_chars';
function getFavChars() { try { return JSON.parse(localStorage.getItem(FAV_KEY_CHARS)||'[]'); } catch { return []; } }
function toggleCharFav() {
    let favs = getFavChars();
    const idx = favs.indexOf(_CHAR_ID);
    if (idx === -1) { favs.push(_CHAR_ID); } else { favs.splice(idx, 1); }
    localStorage.setItem(FAV_KEY_CHARS, JSON.stringify(favs));
    updateFavBtn(favs.includes(_CHAR_ID));
}
function updateFavBtn(active) {
    const btn = document.getElementById('char-fav-btn');
    if (!btn) return;
    btn.innerHTML = (active ? '❤' : '🤍') + ' Favoriet';
    btn.style.background = active ? 'rgba(220,38,38,.25)' : 'rgba(220,38,38,.1)';
    btn.style.borderColor = active ? 'rgba(220,38,38,.7)' : 'rgba(220,38,38,.3)';
    btn.title = active ? 'Verwijder uit favorieten' : 'Toevoegen aan favorieten';
}
updateFavBtn(getFavChars().includes(_CHAR_ID));

// ── Photo gallery swap ──
const GALLERY = <?= json_encode(array_map(fn($g) => [
    'src'     => $g['src'],
    'caption' => $g['caption'] ?? '',
], $gallery), JSON_UNESCAPED_UNICODE) ?>;

function swapPhoto(idx) {
    if (!GALLERY[idx]) return;
    const mainImg    = document.getElementById('char-main-img');
    const captionEl  = document.getElementById('char-img-caption');
    const counterEl  = document.getElementById('char-img-counter');
    const thumbs     = document.querySelectorAll('.char-thumb');

    if (!mainImg) return;

    // Fade out
    mainImg.classList.add('fading');

    setTimeout(() => {
        mainImg.src = GALLERY[idx].src;
        mainImg.onload = () => mainImg.classList.remove('fading');
        // If cached, onload may not fire
        if (mainImg.complete) mainImg.classList.remove('fading');

        if (captionEl) captionEl.textContent = GALLERY[idx].caption || '';
        if (counterEl) counterEl.textContent = (idx + 1) + ' / ' + GALLERY.length;

        thumbs.forEach((t, i) => t.classList.toggle('active', i === idx));
    }, 200);
}
</script>
</body>
</html>
