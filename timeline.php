<?php require_once 'includes/connection.php'; ?>
<?php
$events = $conn->query("
    SELECT te.*, a.name AS arc_name, a.color AS arc_color
    FROM timeline_events te
    LEFT JOIN arcs a ON a.id = te.arc_id
    ORDER BY te.sort_order ASC, te.event_year ASC, te.id ASC
")->fetch_all(MYSQLI_ASSOC);

$arcs = $conn->query("SELECT * FROM arcs ORDER BY sort_order ASC")->fetch_all(MYSQLI_ASSOC);

$catLabels = ['battle'=>'Battle','discovery'=>'Ontdekking','death'=>'Dood','birth'=>'Geboorte','political'=>'Politiek','romance'=>'Romance','mystery'=>'Mysterie','flashback'=>'Flashback','other'=>'Anders'];
$catIcons  = ['battle'=>'⚔️','discovery'=>'🔍','death'=>'💀','birth'=>'🌱','political'=>'👑','romance'=>'❤️','mystery'=>'🌀','flashback'=>'⏪','other'=>'📌'];
$catColors = [
    'battle'    => '#ef4444',
    'discovery' => '#22c55e',
    'death'     => '#6b7280',
    'birth'     => '#60a5fa',
    'political' => '#7c3aed',
    'romance'   => '#ec4899',
    'mystery'   => '#f59e0b',
    'flashback' => '#8b5cf6',
    'other'     => '#94a3b8',
];

// Group events into rows of 4
$perRow = 4;
$rows   = array_chunk($events, $perRow);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tijdlijn — The Greatest Journey</title>
    <?php include 'includes/fonts.php'; ?>
    <link rel="stylesheet" href="styles.css">
    <style>
    /* ── CONTROLS ── */
    .tl-controls {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 48px;
        align-items: center;
        padding: 20px;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 16px;
        box-shadow: var(--card-shadow);
    }
    .tl-sep { width: 1px; height: 28px; background: var(--border); margin: 0 4px; }
    .spoiler-toggle {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: .78rem;
        color: var(--muted);
        cursor: pointer;
        user-select: none;
    }
    .spoiler-toggle input { accent-color: var(--primary); }

    /* ── SNAKE TIMELINE ── */
    .snake-wrap {
        padding: 20px 0 80px;
    }

    /* Each row of events */
    .snake-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        position: relative;
        padding-top: 0;
    }

    /* Horizontal line through the dots */
    .snake-row::before {
        content: '';
        position: absolute;
        top: 28px; /* center of dot */
        left: calc(100% / 8);       /* center of first column */
        right: calc(100% / 8);      /* center of last column */
        height: 3px;
        background: var(--border);
        z-index: 0;
    }

    /* RTL row: items reversed visually */
    .snake-row.rtl {
        direction: rtl;
    }
    .snake-row.rtl .snake-card {
        direction: ltr;
    }

    /* Single event item */
    .snake-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0;
        position: relative;
    }

    /* The numbered dot */
    .snake-dot {
        width: 56px; height: 56px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.3rem; font-weight: 900;
        color: #fff;
        position: relative;
        z-index: 2;
        flex-shrink: 0;
        box-shadow: 0 4px 14px rgba(0,0,0,.15);
        transition: transform .2s, box-shadow .2s;
        cursor: pointer;
        border: 4px solid #fff;
    }
    .snake-dot:hover { transform: scale(1.12); box-shadow: 0 6px 20px rgba(0,0,0,.2); }

    /* Vertical connector from dot down to card */
    .snake-connector {
        width: 3px;
        height: 32px;
        background: var(--border);
        flex-shrink: 0;
    }

    /* The event card */
    .snake-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 14px 16px;
        box-shadow: var(--card-shadow);
        width: calc(100% - 24px);
        transition: box-shadow .2s, transform .2s;
        cursor: pointer;
        margin-bottom: 8px;
    }
    .snake-card:hover {
        box-shadow: var(--card-hover-shadow);
        transform: translateY(-2px);
    }

    .sc-label {
        font-size: .62rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        margin-bottom: 4px;
    }
    .sc-title {
        font-size: .88rem;
        font-weight: 800;
        color: var(--text);
        margin-bottom: 6px;
        line-height: 1.3;
    }
    .sc-desc {
        font-size: .75rem;
        color: var(--muted);
        line-height: 1.5;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .sc-tags {
        display: flex;
        gap: 4px;
        margin-top: 8px;
        flex-wrap: wrap;
    }
    .sc-tag {
        font-size: .6rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 100px;
        border: 1px solid;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    /* Turn connectors between rows */
    .snake-turn {
        height: 56px;
        position: relative;
    }
    .snake-turn::after {
        content: '';
        position: absolute;
        top: 0;
        width: 3px;
        height: 100%;
        background: var(--border);
    }
    .snake-turn.right::after { right: calc(100% / 8 - 1.5px); }
    .snake-turn.left::after  { left:  calc(100% / 8 - 1.5px); }

    /* Critical event styling */
    .snake-item.critical .snake-dot {
        width: 64px; height: 64px;
        font-size: 1.5rem;
        box-shadow: 0 0 0 4px rgba(245,158,11,.3), 0 4px 20px rgba(245,158,11,.25);
        border-color: #fcd34d;
    }
    .snake-item.critical .snake-card {
        border-color: #fcd34d;
        background: linear-gradient(135deg, #fffbeb, #fff);
    }

    /* Spoiler */
    .snake-item.spoiler-item .snake-card { filter: blur(6px); pointer-events: none; }

    /* ── FLAT GRID (filtered view) ── */
    .flat-grid {
        display: none;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
        padding: 0 0 80px;
    }
    .flat-grid.visible { display: grid; }
    .flat-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 20px;
        box-shadow: var(--card-shadow);
        transition: box-shadow .2s, transform .2s;
    }
    .flat-card:hover { box-shadow: var(--card-hover-shadow); transform: translateY(-2px); }

    /* ── MODAL ── */
    .tl-modal {
        position: fixed; inset: 0;
        background: rgba(30,27,75,.6);
        backdrop-filter: blur(6px);
        z-index: 9000;
        display: flex; align-items: center; justify-content: center;
        padding: 20px;
        opacity: 0; pointer-events: none;
        transition: opacity .25s;
    }
    .tl-modal.open { opacity: 1; pointer-events: auto; }
    .tl-modal-box {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 32px;
        max-width: 560px;
        width: 100%;
        box-shadow: 0 20px 60px rgba(0,0,0,.2);
        position: relative;
        transform: translateY(20px);
        transition: transform .25s;
    }
    .tl-modal.open .tl-modal-box { transform: translateY(0); }
    .tl-modal-close {
        position: absolute; top: 16px; right: 16px;
        background: var(--bg); border: 1px solid var(--border);
        border-radius: 8px; width: 32px; height: 32px;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; font-size: 1rem; color: var(--muted);
    }
    .tl-modal-close:hover { color: var(--text); }
    .tl-modal-label { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; margin-bottom: 6px; }
    .tl-modal-title { font-size: 1.4rem; font-weight: 900; color: var(--text); margin-bottom: 12px; }
    .tl-modal-desc { font-size: .9rem; color: var(--muted); line-height: 1.75; }
    .tl-modal-img { width: 100%; border-radius: 10px; object-fit: cover; max-height: 200px; margin-bottom: 16px; }

    @media(max-width: 700px){
        .snake-row { grid-template-columns: repeat(2, 1fr); }
        .snake-row::before { left: calc(100% / 4); right: calc(100% / 4); }
        .snake-turn.right::after { right: calc(100% / 4 - 1.5px); }
        .snake-turn.left::after  { left:  calc(100% / 4 - 1.5px); }
    }
    @media (max-width: 480px) {
        .snake-row { grid-template-columns: repeat(1, 1fr); }
        .snake-row.rtl { direction: ltr; }
        .snake-row::before { display: none; }
        .snake-turn.right::after, .snake-turn.left::after { display: none; }
        .tl-modal-box { padding: 20px 16px; border-radius: 14px; }
        .tl-modal-title { font-size: 1.2rem; }
        /* Filter bar: horizontal scroll */
        .tl-controls { overflow-x: auto; flex-wrap: nowrap; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
        .tl-controls::-webkit-scrollbar { display: none; }
        .filter-btn { white-space: nowrap; }
    }
    </style>
</head>
<body class="page">
<?php include 'includes/navbar.php'; ?>

<div class="container">
    <div class="section-header">
        <h1 class="section-title">Tijdlijn</h1>
        <p class="section-sub">Alle events in chronologische volgorde — klik op een event voor meer info</p>
    </div>

    <!-- Controls -->
    <div class="tl-controls">
        <button class="filter-btn active" data-filter="all">Alle</button>
        <?php foreach ($catLabels as $key => $lbl): ?>
        <button class="filter-btn" data-filter="cat:<?= $key ?>"
            style="--fc:<?= $catColors[$key] ?>">
            <?= $catIcons[$key] ?> <?= $lbl ?>
        </button>
        <?php endforeach; ?>
        <?php if (!empty($arcs)): ?>
        <div class="tl-sep"></div>
        <?php foreach ($arcs as $arc): ?>
        <button class="filter-btn" data-filter="arc:<?= $arc['id'] ?>"
            style="color:<?= htmlspecialchars($arc['color']) ?>;border-color:<?= htmlspecialchars($arc['color']) ?>40">
            <?= htmlspecialchars($arc['name']) ?>
        </button>
        <?php endforeach; ?>
        <?php endif; ?>
        <label class="spoiler-toggle" style="margin-left:auto;">
            <input type="checkbox" id="showSpoilers"> Spoilers tonen
        </label>
    </div>

    <?php if (empty($events)): ?>
    <div class="empty-state">
        <span class="empty-icon">📜</span>
        <div class="empty-title">Nog geen events</div>
        <div class="empty-desc">Voeg timeline events toe via het admin panel.</div>
    </div>
    <?php else: ?>

    <!-- SNAKE TIMELINE (shown when filter = all) -->
    <div class="snake-wrap" id="snakeWrap">
    <?php
    $globalIdx = 0;
    foreach ($rows as $rowIdx => $row):
        $isRtl = ($rowIdx % 2 === 1);
        $displayRow = $row; // keep chronological order, CSS direction:rtl handles visual flip
        $turnSide = $isRtl ? 'left' : 'right';
    ?>
        <div class="snake-row <?= $isRtl ? 'rtl' : 'ltr' ?>">
        <?php foreach ($displayRow as $ev):
            $globalIdx++;
            $color = $catColors[$ev['category']] ?? '#7c3aed';
            $img = !empty($ev['image']) ? 'uploads/timeline/' . $ev['image'] : '';
            $hasImg = $img && file_exists(__DIR__ . '/' . $img);
            $isCritical = $ev['importance'] === 'critical';
            $isSpoiler  = $ev['is_spoiler'];
        ?>
        <div class="snake-item <?= $isCritical ? 'critical' : '' ?> <?= $isSpoiler ? 'spoiler-item' : '' ?>"
             data-cat="<?= $ev['category'] ?>"
             data-arc="<?= $ev['arc_id'] ?>"
             data-spoiler="<?= $isSpoiler ? '1' : '0' ?>"
             onclick="openModal(<?= htmlspecialchars(json_encode([
                 'title'   => $ev['title'],
                 'desc'    => $ev['description'],
                 'label'   => trim(($ev['episode_number'] ?? null ? 'Afl. '.($ev['episode_number'] ?? '') : '') . ($ev['event_label'] ? ' · '.$ev['event_label'] : '') . ($ev['event_year'] ? ' Jaar '.$ev['event_year'] : ''), ' · '),
                 'arc'     => $ev['arc_name'] ?? '',
                 'arcColor'=> $ev['arc_color'] ?? '',
                 'cat'     => $ev['category'],
                 'icon'    => $catIcons[$ev['category']],
                 'catLabel'=> $catLabels[$ev['category']],
                 'color'   => $color,
                 'img'     => $hasImg ? $img : '',
                 'critical'=> $isCritical,
                 'spoiler' => (bool)$isSpoiler,
             ]), ENT_QUOTES) ?>)">

            <div class="snake-dot" style="background:<?= $color ?>;">
                <?= $globalIdx ?>
            </div>
            <div class="snake-connector"></div>
            <div class="snake-card">
                <div class="sc-label" style="color:<?= $color ?>">
                    <?php $epNum = $ev['episode_number'] ?? null; ?>
                    <?php if ($epNum): ?>Afl. <?= $epNum ?><?php endif; ?>
                    <?php if ($ev['event_label']): ?><?= $epNum ? ' · ' : '' ?><?= htmlspecialchars($ev['event_label']) ?><?php endif; ?>
                    <?php if ($ev['arc_name']): ?> · <span style="color:<?= htmlspecialchars($ev['arc_color']) ?>"><?= htmlspecialchars($ev['arc_name']) ?></span><?php endif; ?>
                </div>
                <div class="sc-title"><?= htmlspecialchars($ev['title']) ?></div>
                <?php if ($ev['description']): ?>
                <div class="sc-desc"><?= htmlspecialchars($ev['description']) ?></div>
                <?php endif; ?>
                <div class="sc-tags">
                    <span class="sc-tag cat-<?= $ev['category'] ?>"><?= $catIcons[$ev['category']] ?> <?= $catLabels[$ev['category']] ?></span>
                    <?php if ($isCritical): ?><span class="sc-tag" style="color:#f59e0b;border-color:#fcd34d;background:#fffbeb;">⭐ Kritiek</span><?php endif; ?>
                    <?php if ($isSpoiler): ?><span class="sc-tag" style="color:#ec4899;border-color:#fbcfe8;background:#fdf2f8;">⚠️ Spoiler</span><?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>

        <?php if ($rowIdx < count($rows) - 1): ?>
        <div class="snake-turn <?= $turnSide ?>"></div>
        <?php endif; ?>

    <?php endforeach; ?>
    </div>

    <!-- FLAT GRID (shown when filtered) -->
    <div class="flat-grid" id="flatGrid">
    <?php foreach ($events as $ev):
        $color = $catColors[$ev['category']] ?? '#7c3aed';
        $img = !empty($ev['image']) ? 'uploads/timeline/' . $ev['image'] : '';
        $hasImg = $img && file_exists(__DIR__ . '/' . $img);
    ?>
    <div class="flat-card"
         data-cat="<?= $ev['category'] ?>"
         data-arc="<?= $ev['arc_id'] ?>"
         data-spoiler="<?= $ev['is_spoiler'] ? '1' : '0' ?>"
         onclick="openModal(<?= htmlspecialchars(json_encode([
             'title'   => $ev['title'],
             'desc'    => $ev['description'],
             'label'   => trim((($ev['episode_number'] ?? null) ? 'Afl. '.($ev['episode_number']) : '') . ($ev['event_label'] ? ' · '.$ev['event_label'] : '') . ($ev['event_year'] ? ' Jaar '.$ev['event_year'] : ''), ' · '),
             'arc'     => $ev['arc_name'] ?? '',
             'arcColor'=> $ev['arc_color'] ?? '',
             'cat'     => $ev['category'],
             'icon'    => $catIcons[$ev['category']],
             'catLabel'=> $catLabels[$ev['category']],
             'color'   => $color,
             'img'     => $hasImg ? $img : '',
             'critical'=> $ev['importance'] === 'critical',
             'spoiler' => (bool)$ev['is_spoiler'],
         ]), ENT_QUOTES) ?>)">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
            <div style="width:40px;height:40px;border-radius:50%;background:<?= $color ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.1rem;flex-shrink:0;"><?= $catIcons[$ev['category']] ?></div>
            <div>
                <?php if ($ev['event_label'] || $ev['event_year']): ?>
                <div style="font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:<?= $color ?>;margin-bottom:2px;">
                    <?= htmlspecialchars($ev['event_label'] ?: 'Jaar '.$ev['event_year']) ?>
                </div>
                <?php endif; ?>
                <div style="font-size:.92rem;font-weight:800;color:var(--text);"><?= htmlspecialchars($ev['title']) ?></div>
            </div>
        </div>
        <?php if ($ev['description']): ?>
        <div style="font-size:.8rem;color:var(--muted);line-height:1.6;"><?= htmlspecialchars(substr($ev['description'], 0, 120)) ?><?= strlen($ev['description']) > 120 ? '…' : '' ?></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<!-- MODAL -->
<div class="tl-modal" id="tlModal" onclick="if(event.target===this)closeModal()">
    <div class="tl-modal-box">
        <button class="tl-modal-close" onclick="closeModal()">✕</button>
        <div id="mlImg"></div>
        <div class="tl-modal-label" id="mlLabel"></div>
        <div class="tl-modal-title" id="mlTitle"></div>
        <div class="tl-modal-desc" id="mlDesc"></div>
        <div style="display:flex;gap:6px;margin-top:14px;flex-wrap:wrap;" id="mlTags"></div>
    </div>
</div>

<footer class="tgj-footer"><strong>The Greatest Journey</strong></footer>

<script>
// ── MODAL ──
function openModal(d){
    if(d.spoiler && !document.getElementById('showSpoilers').checked) return;
    document.getElementById('mlImg').innerHTML  = d.img ? `<img src="${d.img}" class="tl-modal-img">` : '';
    document.getElementById('mlLabel').style.color = d.color;
    document.getElementById('mlLabel').textContent = [d.label, d.arc ? `• ${d.arc}` : ''].filter(Boolean).join(' ');
    document.getElementById('mlTitle').textContent = d.title;
    document.getElementById('mlDesc').textContent  = d.desc || 'Geen beschrijving.';

    let tags = `<span class="te-tag cat-${d.cat}" style="font-size:.7rem;padding:3px 10px;border-radius:100px;border:1px solid;">${d.icon} ${d.catLabel}</span>`;
    if(d.critical) tags += `<span style="font-size:.7rem;padding:3px 10px;border-radius:100px;background:#fffbeb;color:#f59e0b;border:1px solid #fcd34d;">⭐ Kritiek</span>`;
    if(d.spoiler)  tags += `<span style="font-size:.7rem;padding:3px 10px;border-radius:100px;background:#fdf2f8;color:#ec4899;border:1px solid #fbcfe8;">⚠️ Spoiler</span>`;
    document.getElementById('mlTags').innerHTML = tags;

    document.getElementById('tlModal').classList.add('open');
}
function closeModal(){ document.getElementById('tlModal').classList.remove('open'); }
document.addEventListener('keydown', e => { if(e.key==='Escape') closeModal(); });

// ── FILTER ──
const snakeWrap = document.getElementById('snakeWrap');
const flatGrid  = document.getElementById('flatGrid');
let showSpoilers = false;

document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function(){
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const f = this.dataset.filter;

        if(f === 'all'){
            snakeWrap.style.display = '';
            flatGrid.classList.remove('visible');
            applySpoilerSnake();
            return;
        }

        // Show flat grid filtered
        snakeWrap.style.display = 'none';
        flatGrid.classList.add('visible');
        const [type, val] = f.split(':');
        document.querySelectorAll('.flat-card').forEach(c => {
            const match = type === 'cat' ? c.dataset.cat === val : c.dataset.arc === val;
            const spoilerOk = c.dataset.spoiler === '0' || showSpoilers;
            c.style.display = (match && spoilerOk) ? '' : 'none';
        });
    });
});

// ── SPOILER ──
function applySpoilerSnake(){
    document.querySelectorAll('.snake-item').forEach(item => {
        if(item.dataset.spoiler === '1'){
            item.style.display = showSpoilers ? '' : 'none';
        }
    });
}
const spoilerToggle = document.getElementById('showSpoilers');
spoilerToggle.addEventListener('change', function(){
    showSpoilers = this.checked;
    applySpoilerSnake();
    // also update flat grid if visible
    document.querySelectorAll('.flat-card[data-spoiler="1"]').forEach(c => {
        if(!c.style.display || c.style.display !== 'none') return; // already hidden by filter
        c.style.display = showSpoilers ? '' : 'none';
    });
});
applySpoilerSnake();
</script>
</body>
</html>
