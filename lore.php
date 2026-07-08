<?php require_once 'includes/connection.php'; ?>
<?php
$entries    = $conn->query("SELECT * FROM lore ORDER BY sort_order ASC, id ASC")->fetch_all(MYSQLI_ASSOC);
$categories = array_unique(array_column($entries, 'category'));
sort($categories);

// ── NEXUS TYPES — Auto-migration ──
$conn->query("CREATE TABLE IF NOT EXISTS nexus_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    emoji VARCHAR(20) DEFAULT '',
    slug VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    traits TEXT,
    color VARCHAR(20) DEFAULT '#7c3aed',
    sort_order INT DEFAULT 0
)");
$conn->query("CREATE TABLE IF NOT EXISTS character_nexus_types (
    character_id INT NOT NULL,
    nexus_type_id INT NOT NULL,
    PRIMARY KEY (character_id, nexus_type_id),
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (nexus_type_id) REFERENCES nexus_types(id) ON DELETE CASCADE
)");

// Seed 10 types on first run
if ((int)$conn->query("SELECT COUNT(*) FROM nexus_types")->fetch_row()[0] === 0) {
    $seedTypes = [
        [1,  'energy',     '⚡',  'Energy-Type',
         'Energy-Type Nexus manifests through energetic phenomena, energy conversion, elemental output, and energy-state manipulation. Abilities take active forms such as plasma, lightning, heat, beams, and radiation.',
         '["High offensive output","Energy saturation","Environmental destruction","Continuous Nexus consumption"]', '#f97316'],
        [2,  'force',      '⚔️',  'Force-Type',
         'Force-Type Nexus affects momentum, kinetic force, acceleration, impact, and physical motion. These abilities are often brutal in close combat and rely heavily on movement efficiency.',
         '["Extreme physical combat pressure","Momentum manipulation","Heavy-impact fighting styles","Explosive acceleration"]', '#ef4444'],
        [3,  'spatial',    '🌌',  'Spatial-Type',
         'Spatial-Type Nexus manipulates space, positioning, distance, dimensional relation, and spatial movement. These users excel at battlefield control and unpredictable mobility.',
         '["Unpredictable movement","Difficult-to-counter mobility","High concentration requirements"]', '#8b5cf6'],
        [4,  'perception', '👁️',  'Perception-Type',
         'Perception-Type Nexus enhances awareness, prediction, reaction speed, sensory processing, and information gathering. These users dominate through superior combat awareness.',
         '["Elite reaction speed","Efficient movement","Tactical combat styles"]', '#3b82f6'],
        [5,  'mental',     '🧠',  'Mental-Type',
         'Mental-Type Nexus directly affects thoughts, emotions, instinct, fear, and consciousness. These abilities interfere with the internal mind rather than external reality.',
         '["Psychological warfare","Indirect combat methods","Difficult-to-detect attacks"]', '#d946ef'],
        [6,  'concept',    '⚖️',  'Concept-Type',
         'Concept-Type Nexus interacts with abstract laws and conceptual phenomena. Rather than manipulating physical reality, these users distort the rules governing events themselves.',
         '["Extremely unpredictable","Difficult to classify","High strategic potential"]', '#eab308'],
        [7,  'veil',       '🕶️',  'Veil-Type',
         'Veil-Type Nexus suppresses presence, visibility, detection, and awareness of existence. Unlike illusion-based powers, Veil-Type abilities conceal reality rather than distort it.',
         '["Assassination specialization","High stealth capability","Difficult tracking"]', '#64748b'],
        [8,  'manifest',   '⚒️',  'Manifest-Type',
         'Manifest-Type Nexus materializes Nexus energy into constructs, weapons, entities, and physical manifestations. These abilities create something tangible from Nexus itself.',
         '["Versatile combat styles","Weapon specialization","Adaptive fighting methods"]', '#22c55e'],
        [9,  'arcane',     '🔮',  'Arcane-Type',
         'Arcane-Type Nexus manifests through supernatural techniques, rituals, invocations, and magical structures. These users shape Nexus through unnatural methods rather than instinctive projection.',
         '["Highly versatile techniques","Complex activation methods","Unpredictable applications"]', '#14b8a6'],
        [10, 'catalyst',   '👑',  'Catalyst-Type',
         'Catalyst-Type is the rarest Nexus classification. A Catalyst-Type user naturally possesses four or more complete Nexus Types simultaneously, manifesting across multiple layers of reality at once.',
         '["Multiple advanced Nexus expressions","Extreme adaptability","Rapid evolution potential","Unclassifiable combat behavior"]', '#f59e0b'],
    ];
    $stmt = $conn->prepare("INSERT INTO nexus_types (sort_order,slug,emoji,name,description,traits,color) VALUES (?,?,?,?,?,?,?)");
    foreach ($seedTypes as $t) {
        $stmt->bind_param("issssss", $t[0],$t[1],$t[2],$t[3],$t[4],$t[5],$t[6]);
        $stmt->execute();
    }

    // Assign existing characters their types
    $assignments = [
        'Kimmie'          => ['energy','spatial'],
        'Nezzie Silvanie' => ['energy'],
        'Ruin Kade'       => ['force'],
        'Nyssa'           => ['perception','spatial'],
        'Park Tae-hyun'   => ['spatial','manifest'],
        'Kiro Matsuda'    => ['mental'],
        'Chance Calder'   => ['concept'],
        'Kaito Hoshigami' => ['concept'],
        'Shaf Sno'        => ['manifest'],
        'Caleb'           => ['arcane'],
        'Cassian Riven'   => ['catalyst','perception','veil','mental','energy'],
    ];
    foreach ($assignments as $charName => $slugs) {
        $safe = $conn->real_escape_string($charName);
        $charRow = $conn->query("SELECT id FROM characters WHERE name='$safe'")->fetch_assoc();
        if (!$charRow) continue;
        $cid = (int)$charRow['id'];
        foreach ($slugs as $slug) {
            $safeSlug = $conn->real_escape_string($slug);
            $typeRow  = $conn->query("SELECT id FROM nexus_types WHERE slug='$safeSlug'")->fetch_assoc();
            if (!$typeRow) continue;
            $tid = (int)$typeRow['id'];
            $conn->query("INSERT IGNORE INTO character_nexus_types (character_id,nexus_type_id) VALUES ($cid,$tid)");
        }
    }
}

// Insert any new types added after initial seed (INSERT IGNORE checks slug uniqueness)
$extraTypes = [
    [11, 'restoration', '🌿', 'Restoration-Type',
     'Restoration-Type Nexus is centered around healing, regeneration, recovery, and stabilization. These abilities repair physical damage, internal Nexus damage, exhaustion, and corrupted energy. Some users can accelerate biological evolution and stabilize unstable Nexus systems. Warning: reversed, these abilities become horrifying — forced regeneration, uncontrolled cell restoration, and restorative overload.',
     '["Physical regeneration","Nexus flow stabilization","Accelerated biological recovery","Can be weaponized in reverse"]', '#4ade80'],
    [12, 'elemental',   '🌑', 'Elemental-Type',
     'Elemental-Type Nexus ties the user to a specific natural or unnatural element — fire, ice, shadow, lightning, poison, smoke, ash, blood, crystal, or storm. Elemental users naturally generate and manipulate their chosen element as an extension of themselves.',
     '["Element generation & manipulation","Element acts as extension of the body","Wide domain variety","High versatility based on element"]', '#0ea5e9'],
];
$extStmt = $conn->prepare("INSERT IGNORE INTO nexus_types (sort_order,slug,emoji,name,description,traits,color) VALUES (?,?,?,?,?,?,?)");
foreach ($extraTypes as $t) {
    $extStmt->bind_param("issssss", $t[0],$t[1],$t[2],$t[3],$t[4],$t[5],$t[6]);
    $extStmt->execute();
}

// Fetch nexus types
$ntRows = $conn->query("SELECT * FROM nexus_types ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);

// Fetch character assignments (with image)
$cntRows = $conn->query("
    SELECT cnt.nexus_type_id, c.id AS cid, c.name, c.image
    FROM character_nexus_types cnt
    JOIN characters c ON c.id = cnt.character_id
    ORDER BY c.name
")->fetch_all(MYSQLI_ASSOC);
$typeChars = [];
foreach ($cntRows as $r) { $typeChars[$r['nexus_type_id']][] = $r; }
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lore — The Greatest Journey</title>
    <?php include 'includes/fonts.php'; ?>
    <link rel="stylesheet" href="styles.css">
    <style>
    .lore-page { padding: 40px 0 100px; }

    /* ── FILTER ── */
    .lore-filter {
        display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 36px;
    }
    .lore-filter-btn {
        padding: 7px 18px; border-radius: 100px;
        border: 1.5px solid var(--border);
        background: var(--card); color: var(--muted);
        font-size: .75rem; font-weight: 700; letter-spacing: .04em;
        cursor: pointer; transition: all .15s;
    }
    .lore-filter-btn:hover  { border-color: var(--primary); color: var(--primary); }
    .lore-filter-btn.active { background: var(--primary); border-color: var(--primary); color: #fff; }

    /* ── CARD GRID ── */
    .lore-strip {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 18px;
        align-items: start;
    }

    .lore-card {
        border-radius: 16px;
        border: 1.5px solid var(--border);
        background: var(--card);
        display: flex; flex-direction: column;
        cursor: pointer;
        transition: box-shadow .25s, border-color .25s, transform .25s;
        overflow: hidden;
    }
    .lore-card:hover:not(.active) {
        border-color: rgba(124,58,237,.4);
        box-shadow: 0 8px 32px rgba(124,58,237,.1);
        transform: translateY(-2px);
    }
    .lore-card.active {
        border-color: var(--primary);
        box-shadow: 0 12px 40px rgba(124,58,237,.22);
        transform: translateY(-3px);
        cursor: default;
    }

    /* Optional banner image at top */
    .lore-card-banner {
        width: 100%; height: 140px;
        object-fit: cover; display: block;
        border-bottom: 1px solid var(--border);
    }

    /* Card body */
    .lore-card-content {
        padding: 20px 22px 22px;
        display: flex; flex-direction: column; gap: 10px;
    }

    /* Icon + category row */
    .lore-card-top {
        display: flex; align-items: center; gap: 8px;
    }
    .lore-card-icon {
        width: 28px; height: 28px; border-radius: 7px;
        background: var(--primary-light);
        display: flex; align-items: center; justify-content: center;
        font-size: .82rem; flex-shrink: 0;
    }
    .lore-card-cat {
        font-size: .6rem; font-weight: 800; letter-spacing: .12em;
        text-transform: uppercase; color: var(--primary);
    }

    /* Title */
    .lore-card-title {
        font-family: var(--font-display);
        font-size: 1.05rem; font-weight: 700;
        color: var(--text); line-height: 1.3;
    }

    /* Teaser — always visible, 4 lines */
    .lore-card-teaser {
        font-size: .83rem; color: var(--muted);
        line-height: 1.65;
        display: -webkit-box;
        -webkit-line-clamp: 4;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Divider */
    .lore-card-divider {
        height: 1px; background: var(--border);
        transform: scaleX(0); transform-origin: left;
        transition: transform .4s .1s;
    }
    .lore-card.active .lore-card-divider { transform: scaleX(1); }

    /* Full content — slides in when active */
    .lore-card-full {
        font-size: .83rem; color: var(--text);
        line-height: 1.8; white-space: pre-wrap;
        max-height: 0; overflow: hidden; opacity: 0;
        transition: max-height .55s .1s ease, opacity .4s .15s;
    }
    .lore-card.active .lore-card-full {
        max-height: 1000px; opacity: 1;
    }

    /* "Read more" hint */
    .lore-card-hint {
        font-size: .65rem; font-weight: 700; letter-spacing: .08em;
        text-transform: uppercase; color: var(--primary);
        opacity: .6; transition: opacity .2s;
        display: flex; align-items: center; gap: 4px;
    }
    .lore-card:hover .lore-card-hint { opacity: 1; }
    .lore-card.active .lore-card-hint { display: none; }

    /* Empty */
    .lore-empty { text-align: center; padding: 80px 0; color: var(--muted); }

    @media (max-width: 600px) {
        .lore-strip { grid-template-columns: 1fr; }
    }

    /* ── LORE PAGE TABS ── */
    .lore-page-tabs {
        display: flex; gap: 4px;
        border-bottom: 2px solid var(--border);
        margin-bottom: 32px;
    }
    .lore-page-tab {
        padding: 10px 22px; border: none; background: transparent;
        font-family: var(--font-display); font-size: .82rem; font-weight: 700;
        color: var(--muted); border-radius: 10px 10px 0 0; cursor: pointer;
        transition: all .15s; border-bottom: 3px solid transparent; margin-bottom: -2px;
    }
    .lore-page-tab:hover  { color: var(--text); background: var(--surface); }
    .lore-page-tab.active { color: var(--primary); border-bottom-color: var(--primary); background: var(--card); }
    .lore-tab-panel        { display: none; }
    .lore-tab-panel.active { display: block; }

    /* ── NEXUS TYPE CARDS ── */
    .nt-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 20px;
    }
    .nt-card {
        border-radius: 18px;
        border: 1.5px solid var(--border);
        background: var(--card);
        overflow: hidden;
        transition: box-shadow .25s, transform .2s;
    }
    .nt-card:hover { transform: translateY(-3px); box-shadow: 0 12px 40px rgba(0,0,0,.25); }
    .nt-card-header {
        display: flex; align-items: center; gap: 14px;
        padding: 18px 22px 16px;
        border-bottom: 1px solid var(--border);
    }
    .nt-emoji-wrap {
        width: 48px; height: 48px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem; flex-shrink: 0;
    }
    .nt-num {
        font-size: .58rem; font-weight: 800; letter-spacing: .14em;
        text-transform: uppercase; margin-bottom: 2px;
    }
    .nt-name {
        font-family: var(--font-display); font-size: 1.05rem; font-weight: 800;
        letter-spacing: .03em;
    }
    .nt-body { padding: 16px 22px 20px; display: flex; flex-direction: column; gap: 14px; }
    .nt-desc { font-size: .82rem; color: var(--muted); line-height: 1.7; }
    .nt-section-label {
        font-size: .58rem; font-weight: 800; letter-spacing: .14em;
        text-transform: uppercase; color: var(--muted); margin-bottom: 7px;
    }
    .nt-traits { display: flex; flex-wrap: wrap; gap: 6px; }
    .nt-trait {
        font-size: .68rem; font-weight: 700;
        padding: 3px 10px; border-radius: 100px;
        border: 1px solid var(--border);
        background: var(--surface); color: var(--muted);
    }
    .nt-users { display: flex; flex-wrap: wrap; gap: 8px; }
    .nt-user-chip {
        display: flex; align-items: center; gap: 7px;
        background: var(--surface); border: 1px solid var(--border);
        border-radius: 100px; padding: 4px 12px 4px 4px;
        text-decoration: none; color: var(--text);
        font-size: .72rem; font-weight: 700;
        transition: all .15s;
    }
    .nt-user-chip:hover { border-color: var(--primary); background: var(--primary-light); }
    .nt-user-av {
        width: 26px; height: 26px; border-radius: 50%; overflow: hidden;
        background: var(--border); flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-size: .72rem; font-weight: 800; color: var(--muted);
    }
    .nt-user-av img { width: 100%; height: 100%; object-fit: cover; }
    .nt-catalyst-badge {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: .65rem; font-weight: 800; letter-spacing: .1em;
        text-transform: uppercase; color: #f59e0b;
        background: rgba(245,158,11,.1); border: 1px solid rgba(245,158,11,.3);
        border-radius: 100px; padding: 4px 12px; margin-bottom: 4px;
    }

    @media (max-width: 600px) {
        .nt-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
        /* Tabs scroll horizontally */
        .lore-page-tabs { overflow-x: auto; flex-wrap: nowrap; -webkit-overflow-scrolling: touch; scrollbar-width: none; padding-bottom: 2px; }
        .lore-page-tabs::-webkit-scrollbar { display: none; }
        .lore-page-tab { white-space: nowrap; }
        .lore-filter { gap: 6px; }
        .lore-filter-btn { padding: 6px 14px; font-size: .72rem; }
    }
    </style>
</head>
<body class="page">
<?php include 'includes/navbar.php'; ?>

<div class="container lore-page">
    <div class="section-header">
        <h1 class="section-title">Lore &amp; Wereld</h1>
        <p class="section-sub">De wereld, magie, facties en geschiedenis achter het verhaal</p>
    </div>

    <!-- Page tabs -->
    <div class="lore-page-tabs">
        <button class="lore-page-tab active" data-lore-tab="lore">📖 Lore & Wereld</button>
        <button class="lore-page-tab" data-lore-tab="types">📘 Ability Types</button>
    </div>

    <!-- ══ PANEL: LORE ══ -->
    <div class="lore-tab-panel active" id="lore-panel-lore">
        <?php if (empty($entries)): ?>
        <div class="lore-empty">
            <div style="font-size:3rem;margin-bottom:14px;">🌍</div>
            <div style="font-weight:700;">Nog geen lore entries</div>
            <div style="font-size:.85rem;margin-top:6px;">Voeg lore toe via het admin panel.</div>
        </div>
        <?php else: ?>

        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
            <?php if (count($categories) > 1): ?>
            <div class="lore-filter" style="margin-bottom:0;">
                <button class="lore-filter-btn active" data-cat="all">Alle</button>
                <?php foreach ($categories as $cat): ?>
                <button class="lore-filter-btn" data-cat="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></button>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div></div>
            <?php endif; ?>

            <?php if (count($entries) > 1): ?>
            <button id="random-lore-btn" onclick="openRandomLore()"
                    style="display:inline-flex;align-items:center;gap:8px;background:var(--primary);color:#fff;border:none;border-radius:12px;padding:10px 20px;font-family:inherit;font-size:.82rem;font-weight:700;cursor:pointer;transition:all .18s;box-shadow:0 4px 16px rgba(124,58,237,.35);"
                    onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                🎲 Willekeurig artikel
            </button>
            <?php endif; ?>
        </div>

        <div class="lore-strip" id="loreStrip">
        <?php foreach ($entries as $idx => $e):
            $img    = !empty($e['image']) ? 'uploads/lore/' . $e['image'] : '';
            $hasImg = $img && file_exists(__DIR__ . '/' . $img);
            $icon   = match(strtolower($e['category'] ?? '')) {
                'magie', 'magic', 'nexus'   => '✨',
                'locatie', 'location'       => '🗺',
                'fractie', 'faction'        => '⚔',
                'geschiedenis', 'history'   => '📜',
                'wezen', 'creature'         => '🐉',
                'technologie', 'technology' => '⚙',
                'religie', 'religion'       => '🌙',
                default                     => '📖',
            };
        ?>
        <div class="lore-card"
             data-cat="<?= htmlspecialchars($e['category']) ?>"
             onclick="activateLore(this)">
            <?php if ($hasImg): ?>
            <img src="<?= htmlspecialchars($img) ?>" class="lore-card-banner" alt="">
            <?php endif; ?>
            <div class="lore-card-content">
                <div class="lore-card-top">
                    <div class="lore-card-icon"><?= $icon ?></div>
                    <div class="lore-card-cat"><?= htmlspecialchars($e['category']) ?></div>
                </div>
                <div class="lore-card-title"><?= htmlspecialchars($e['title']) ?></div>
                <div class="lore-card-teaser"><?= htmlspecialchars($e['content']) ?></div>
                <div class="lore-card-hint">▼ Lees meer</div>
                <div class="lore-card-divider"></div>
                <div class="lore-card-full"><?= htmlspecialchars($e['content']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>

        <?php endif; ?>
    </div>

    <!-- ══ PANEL: ABILITY TYPES ══ -->
    <div class="lore-tab-panel" id="lore-panel-types">
        <div style="margin-bottom:28px;">
            <p style="font-size:.88rem;color:var(--muted);line-height:1.75;max-width:700px;">
                Het Nexus Classificatiesysteem categoriseert ontwakte abilities op basis van de primaire aard van de Nexus-manifestatie.
                Een Nexus Type definieert <em>niet</em> de exacte ability, maar het domein waartoe die ability van nature behoort.
                <br><br>
                <span class="nt-catalyst-badge">👑 Catalyst-Type</span>
                Zeldzame individuen die van nature vier of meer Nexus Types bezitten, worden geclassificeerd als <strong>Catalyst-Type</strong>.
            </p>
        </div>

        <?php if (empty($ntRows)): ?>
        <div class="lore-empty"><div style="font-size:2rem;">📘</div><div>Geen types gevonden.</div></div>
        <?php else: ?>
        <div class="nt-grid">
        <?php foreach ($ntRows as $nt):
            $color  = htmlspecialchars($nt['color']);
            $traits = json_decode($nt['traits'] ?? '[]', true) ?: [];
            $chars  = $typeChars[$nt['id']] ?? [];
        ?>
        <div class="nt-card" style="border-top: 3px solid <?= $color ?>;">
            <div class="nt-card-header">
                <div class="nt-emoji-wrap" style="background:<?= $color ?>18;border:1.5px solid <?= $color ?>33;">
                    <?= htmlspecialchars($nt['emoji']) ?>
                </div>
                <div>
                    <div class="nt-num" style="color:<?= $color ?>;"><?= $nt['sort_order'] ?>. NEXUS CLASSIFICATIE</div>
                    <div class="nt-name" style="color:<?= $color ?>;"><?= htmlspecialchars($nt['name']) ?></div>
                </div>
            </div>
            <div class="nt-body">
                <div class="nt-desc"><?= htmlspecialchars($nt['description']) ?></div>

                <?php if (!empty($traits)): ?>
                <div>
                    <div class="nt-section-label">Kenmerken</div>
                    <div class="nt-traits">
                        <?php foreach ($traits as $trait): ?>
                        <span class="nt-trait" style="border-color:<?= $color ?>33;color:<?= $color ?>;"><?= htmlspecialchars($trait) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($chars)): ?>
                <div>
                    <div class="nt-section-label">Bekende gebruikers</div>
                    <div class="nt-users">
                        <?php foreach ($chars as $cu):
                            $cuImg = !empty($cu['image']) ? 'uploads/characters/'.$cu['image'] : '';
                            $cuHasImg = $cuImg && file_exists(__DIR__.'/'.$cuImg);
                        ?>
                        <a href="character.php?id=<?= $cu['cid'] ?>" class="nt-user-chip">
                            <div class="nt-user-av">
                                <?php if ($cuHasImg): ?>
                                <img src="<?= htmlspecialchars($cuImg) ?>" alt="">
                                <?php else: ?>
                                <?= strtoupper(substr($cu['name'],0,1)) ?>
                                <?php endif; ?>
                            </div>
                            <?= htmlspecialchars($cu['name']) ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div style="font-size:.75rem;color:var(--muted);font-style:italic;">Geen bekende gebruikers.</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<footer class="tgj-footer"><strong>The Greatest Journey</strong></footer>
<script>
// ── Page tabs ──
document.querySelectorAll('.lore-page-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.lore-page-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.lore-tab-panel').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('lore-panel-' + this.dataset.loreTab).classList.add('active');
    });
});

function activateLore(card) {
    const isAlreadyOpen = card.classList.contains('active');
    document.querySelectorAll('#loreStrip .lore-card.active').forEach(c => c.classList.remove('active'));
    if (!isAlreadyOpen) card.classList.add('active');
}

// ── Willekeurig lore artikel ──
function openRandomLore() {
    const visibleCards = [...document.querySelectorAll('.lore-card')]
        .filter(c => c.style.display !== 'none');
    if (!visibleCards.length) return;

    // Close currently open card
    document.querySelectorAll('.lore-card.active').forEach(c => c.classList.remove('active'));

    // Pick a random one (different from last if possible)
    let pick;
    if (visibleCards.length === 1) {
        pick = visibleCards[0];
    } else {
        do { pick = visibleCards[Math.floor(Math.random() * visibleCards.length)]; }
        while (pick === openRandomLore._last && visibleCards.length > 1);
    }
    openRandomLore._last = pick;

    // Open it and scroll into view
    pick.classList.add('active');
    setTimeout(() => pick.scrollIntoView({ behavior: 'smooth', block: 'center' }), 60);

    // Spin the button briefly
    const btn = document.getElementById('random-lore-btn');
    if (btn) {
        btn.style.transform = 'rotate(20deg) scale(.95)';
        setTimeout(() => { btn.style.transform = ''; }, 250);
    }
}
openRandomLore._last = null;

// Category filter
document.querySelectorAll('.lore-filter-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.lore-filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const cat = this.dataset.cat;
        const cards = [...document.querySelectorAll('.lore-card')];
        let firstVisible = null;
        cards.forEach(card => {
            const show = cat === 'all' || card.dataset.cat === cat;
            card.style.display = show ? '' : 'none';
            card.classList.remove('active');
            if (show && !firstVisible) firstVisible = card;
        });
        if (firstVisible) firstVisible.classList.add('active');
    });
});
</script>
</body>
</html>
