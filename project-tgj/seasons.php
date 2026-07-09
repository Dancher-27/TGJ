<?php require_once 'includes/connection.php'; ?>
<?php
$seasons      = $conn->query("SELECT * FROM seasons ORDER BY sort_order ASC, number ASC")->fetch_all(MYSQLI_ASSOC);
$statusLabels = ['released'=>'Uitgebracht','ongoing'=>'Bezig','upcoming'=>'Binnenkort'];
$catLabels    = ['battle'=>'Battle','discovery'=>'Ontdekking','death'=>'Dood','birth'=>'Geboorte',
                 'political'=>'Politiek','romance'=>'Romance','mystery'=>'Mysterie',
                 'flashback'=>'Flashback','other'=>'Anders'];
$catIcons     = ['battle'=>'⚔️','discovery'=>'🔍','death'=>'💀','birth'=>'🌱',
                 'political'=>'👑','romance'=>'❤️','mystery'=>'🌀','flashback'=>'⏪','other'=>'📌'];
$catColors    = ['battle'=>'#ef4444','discovery'=>'#22c55e','death'=>'#6b7280',
                 'birth'=>'#60a5fa','political'=>'#7c3aed','romance'=>'#ec4899',
                 'mystery'=>'#f59e0b','flashback'=>'#8b5cf6','other'=>'#94a3b8'];

// Per season: arcs, events, characters
$seasonData = [];
foreach ($seasons as $s) {
    $sid = (int)$s['id'];

    // Arcs for this season
    $arcs = $conn->query("
        SELECT * FROM arcs WHERE season_id = $sid ORDER BY sort_order ASC, id ASC
    ")->fetch_all(MYSQLI_ASSOC);

    // Timeline events via arcs
    $byArc = [];
    if ($arcs) {
        $arcIds = implode(',', array_map(fn($a)=>(int)$a['id'], $arcs));
        $evRows = $conn->query("
            SELECT te.*, a.name AS arc_name, a.color AS arc_color, a.id AS the_arc_id
            FROM timeline_events te
            LEFT JOIN arcs a ON a.id = te.arc_id
            WHERE te.arc_id IN ($arcIds)
            ORDER BY a.sort_order ASC, te.sort_order ASC, te.id ASC
        ")->fetch_all(MYSQLI_ASSOC);
        foreach ($evRows as $ev) {
            $byArc[$ev['the_arc_id']][] = $ev;
        }
    }

    // Characters in this season
    $chars = $conn->query("
        SELECT c.id, c.name, c.role, c.image
        FROM character_seasons cs
        JOIN characters c ON c.id = cs.character_id
        WHERE cs.season_id = $sid
        ORDER BY FIELD(c.role,'protagonist','anti-hero','antagonist','villain','supporting'), c.name ASC
    ")->fetch_all(MYSQLI_ASSOC);

    $seasonData[$sid] = compact('arcs','byArc','chars');
}

// Upcoming countdown
$upcoming = null;
foreach ($seasons as $s) {
    if ($s['status'] === 'upcoming' && !empty($s['release_date'])) { $upcoming = $s; break; }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seizoenen — The Greatest Journey</title>
    <?php include 'includes/fonts.php'; ?>
    <link rel="stylesheet" href="styles.css">
    <style>
    /* ── SEASON TABS ── */
    .sn-tabs {
        display: flex; gap: 4px; flex-wrap: wrap;
        border-bottom: 2px solid var(--border);
        margin-bottom: 0;
    }
    .sn-tab {
        padding: 11px 24px;
        border: none; background: transparent;
        font-family: var(--font-display);
        font-size: .82rem; font-weight: 700; letter-spacing: .03em;
        color: var(--muted); border-radius: 10px 10px 0 0;
        cursor: pointer; transition: all .15s;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
    }
    .sn-tab:hover  { color: var(--text); background: var(--surface); }
    .sn-tab.active { color: var(--primary); border-bottom-color: var(--primary); background: var(--card); }

    .sn-panel        { display: none; padding-top: 32px; }
    .sn-panel.active { display: block; }

    /* ── HERO BANNER ── */
    .sn-hero {
        background: linear-gradient(135deg, var(--navbar-bg) 0%, #312e81 100%);
        border-radius: 20px;
        padding: 36px 40px;
        display: flex; gap: 32px; align-items: flex-start;
        position: relative; overflow: hidden;
        margin-bottom: 36px;
    }
    .sn-hero::before {
        content: '';
        position: absolute; inset: 0;
        background: radial-gradient(ellipse at 80% 50%, rgba(124,58,237,.3) 0%, transparent 65%);
    }
    .sn-cover {
        flex-shrink: 0; width: 150px; height: 190px;
        border-radius: 14px; object-fit: cover;
        border: 3px solid var(--primary);
        box-shadow: 0 8px 32px rgba(124,58,237,.4);
        position: relative; z-index: 1;
    }
    .sn-cover-ph {
        flex-shrink: 0; width: 150px; height: 190px;
        border-radius: 14px; background: rgba(255,255,255,.06);
        border: 3px solid rgba(255,255,255,.12);
        display: flex; align-items: center; justify-content: center;
        font-size: 3rem; position: relative; z-index: 1;
    }
    .sn-info { flex: 1; position: relative; z-index: 1; }
    .sn-eyebrow {
        font-size: .68rem; font-weight: 800; letter-spacing: .15em;
        text-transform: uppercase; color: #a78bfa; margin-bottom: 6px;
    }
    .sn-title {
        font-family: var(--font-display); font-size: 2rem; font-weight: 700;
        color: #fff; line-height: 1.1; margin-bottom: 8px;
    }
    .sn-sub  { font-size: .95rem; color: rgba(255,255,255,.55); font-style: italic; margin-bottom: 16px; }
    .sn-badges { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
    .sn-badge  { font-size: .7rem; font-weight: 700; letter-spacing: .04em; padding: 4px 14px; border-radius: 100px; }
    .sn-desc   { font-size: .9rem; color: rgba(255,255,255,.72); line-height: 1.75; max-width: 680px; }
    .sn-meta   { display: flex; gap: 24px; flex-wrap: wrap; margin-top: 18px; }
    .sn-meta-item { display: flex; flex-direction: column; gap: 2px; }
    .sn-meta-lbl  { font-size: .58rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: rgba(255,255,255,.35); }
    .sn-meta-val  { font-size: .85rem; font-weight: 700; color: rgba(255,255,255,.85); }

    /* ── SECTION HEADER ── */
    .sn-sec-ttl {
        font-size: .6rem; font-weight: 700; font-family: var(--font-display);
        letter-spacing: .18em; text-transform: uppercase; color: var(--primary);
        margin-bottom: 16px;
        display: flex; align-items: center; gap: 8px;
    }
    .sn-sec-ttl::after { content: ''; flex: 1; height: 1px; background: var(--border); }
    .sn-section { margin-bottom: 36px; }

    /* ── TOGGLE BUTTONS ── */
    .sn-toggle-row { display: flex; gap: 8px; margin-bottom: 14px; }

    /* ── ARC BLOCK ── */
    .arc-block { margin-bottom: 12px; border-radius: 14px; border: 1px solid var(--border); overflow: hidden; }
    .arc-hd {
        display: flex; align-items: center; gap: 12px;
        padding: 13px 18px; background: var(--surface);
        cursor: pointer; user-select: none; transition: background .15s;
    }
    .arc-hd:hover { background: var(--primary-light); }
    .arc-dot  { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
    .arc-name { font-family: var(--font-display); font-size: .88rem; font-weight: 700; color: var(--text); flex: 1; }
    .arc-pill {
        font-size: .68rem; font-weight: 700;
        background: var(--primary-light); color: var(--primary);
        padding: 2px 10px; border-radius: 100px; border: 1px solid var(--border);
    }
    .arc-chev { font-size: .8rem; color: var(--muted); transition: transform .2s; }
    .arc-block.open .arc-chev { transform: rotate(180deg); }

    .arc-body { display: none; background: var(--card); }
    .arc-block.open .arc-body { display: block; }

    /* ── EVENT ROW ── */
    .ev-row {
        display: grid; grid-template-columns: 40px 1fr auto;
        gap: 0 14px; align-items: start;
        padding: 10px 18px; border-bottom: 1px solid var(--border);
        cursor: pointer; transition: background .12s;
    }
    .ev-row:last-child { border-bottom: none; }
    .ev-row:hover { background: var(--surface); }
    .ev-num {
        width: 34px; height: 34px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: .67rem; font-weight: 800; color: #fff; flex-shrink: 0; margin-top: 2px;
    }
    .ev-body { min-width: 0; }
    .ev-title { font-size: .85rem; font-weight: 700; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ev-tags  { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 3px; }
    .ev-tag   { font-size: .62rem; font-weight: 700; padding: 2px 8px; border-radius: 100px; border: 1px solid; }
    .ev-ep    { font-size: .65rem; color: var(--muted); font-weight: 600; align-self: center; white-space: nowrap; }

    /* ── CHARACTERS ── */
    .sn-chars { display: flex; flex-wrap: wrap; gap: 10px; }
    .sn-char {
        display: flex; align-items: center; gap: 10px;
        background: var(--surface); border: 1px solid var(--border);
        border-radius: 12px; padding: 8px 14px;
        text-decoration: none; color: var(--text); transition: all .15s;
    }
    .sn-char:hover { background: var(--primary-light); border-color: var(--primary); transform: translateY(-1px); }
    .sn-char-av {
        width: 34px; height: 34px; border-radius: 8px; overflow: hidden;
        background: var(--primary-light); display: flex; align-items: center;
        justify-content: center; font-weight: 800; font-size: .8rem;
        color: var(--primary); flex-shrink: 0;
    }
    .sn-char-av img { width: 100%; height: 100%; object-fit: cover; }
    .sn-char-name { font-size: .82rem; font-weight: 700; }

    .sn-empty-arc { padding: 24px 18px; text-align: center; color: var(--muted); font-size: .85rem; }

    @media (max-width: 768px) {
        .sn-hero { flex-direction: column; gap: 20px; padding: 24px; }
        .sn-title { font-size: 1.5rem; }
    }
    </style>
</head>
<body class="page">
<?php include 'includes/navbar.php'; ?>

<div class="container" style="padding-bottom: 60px;">
    <div class="section-header">
        <h1 class="section-title">Seizoenen</h1>
        <p class="section-sub">Verhaallijnen, events en characters per seizoen</p>
    </div>

    <!-- Countdown -->
    <?php if ($upcoming): ?>
    <div class="countdown-strip" style="margin-bottom:32px;">
        <div>
            <div class="cd-label">Volgende release</div>
            <div class="cd-title">Seizoen <?= $upcoming['number'] ?> — <?= htmlspecialchars($upcoming['title']) ?></div>
        </div>
        <div class="cd-boxes" id="countdown" data-date="<?= htmlspecialchars($upcoming['release_date']) ?>">
            <div class="cd-box"><span class="cd-val" id="cd-d">--</span><span class="cd-unit">dagen</span></div>
            <div class="cd-box"><span class="cd-val" id="cd-h">--</span><span class="cd-unit">uren</span></div>
            <div class="cd-box"><span class="cd-val" id="cd-m">--</span><span class="cd-unit">min</span></div>
            <div class="cd-box"><span class="cd-val" id="cd-s">--</span><span class="cd-unit">sec</span></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($seasons)): ?>
    <div class="empty-state">
        <span class="empty-icon">📺</span>
        <div class="empty-title">Nog geen seizoenen</div>
        <div class="empty-desc">Voeg seizoenen toe via het admin panel.</div>
    </div>
    <?php else: ?>

    <!-- Season tabs -->
    <div class="sn-tabs">
        <?php foreach ($seasons as $i => $s): ?>
        <button class="sn-tab <?= $i===0?'active':'' ?>" data-sn="<?= $s['id'] ?>">
            S<?= $s['number'] ?> — <?= htmlspecialchars($s['title']) ?>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- Season panels -->
    <?php foreach ($seasons as $i => $s):
        $sid  = (int)$s['id'];
        $data = $seasonData[$sid];
        $img  = !empty($s['cover_image']) ? 'uploads/seasons/'.$s['cover_image'] : '';
        $hasImg = $img && file_exists(__DIR__.'/'.$img);
        $stColor = ['released'=>'#22c55e','ongoing'=>'#f59e0b','upcoming'=>'#7c3aed'][$s['status']] ?? '#6b7280';
        $totalEv = array_sum(array_map('count', $data['byArc']));
    ?>
    <div class="sn-panel <?= $i===0?'active':'' ?>" id="sn-<?= $sid ?>">

        <!-- Hero -->
        <div class="sn-hero">
            <?php if ($hasImg): ?>
                <img src="<?= htmlspecialchars($img) ?>" class="sn-cover" alt="">
            <?php else: ?>
                <div class="sn-cover-ph">📺</div>
            <?php endif; ?>

            <div class="sn-info">
                <div class="sn-eyebrow">Seizoen <?= $s['number'] ?></div>
                <div class="sn-title"><?= htmlspecialchars($s['title']) ?></div>
                <?php if ($s['subtitle']): ?>
                <div class="sn-sub"><?= htmlspecialchars($s['subtitle']) ?></div>
                <?php endif; ?>

                <div class="sn-badges">
                    <span class="sn-badge" style="background:<?= $stColor ?>22;color:<?= $stColor ?>;border:1.5px solid <?= $stColor ?>44;">
                        <?= $statusLabels[$s['status']] ?? $s['status'] ?>
                    </span>
                    <?php if (count($data['arcs'])): ?>
                    <span class="sn-badge" style="background:var(--primary-light);color:var(--primary);border:1.5px solid var(--border);">
                        <?= count($data['arcs']) ?> arcs
                    </span>
                    <?php endif; ?>
                    <?php if ($totalEv): ?>
                    <span class="sn-badge" style="background:var(--surface);color:var(--muted);border:1.5px solid var(--border);">
                        <?= $totalEv ?> events
                    </span>
                    <?php endif; ?>
                </div>

                <?php if ($s['description']): ?>
                <div class="sn-desc"><?= nl2br(htmlspecialchars($s['description'])) ?></div>
                <?php endif; ?>

                <div class="sn-meta">
                    <?php if ($s['episode_count']): ?>
                    <div class="sn-meta-item">
                        <span class="sn-meta-lbl">Afleveringen</span>
                        <span class="sn-meta-val">📺 <?= $s['episode_count'] ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($s['release_date']): ?>
                    <div class="sn-meta-item">
                        <span class="sn-meta-lbl">Start</span>
                        <span class="sn-meta-val">📅 <?= date('d M Y', strtotime($s['release_date'])) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($s['end_date'] && $s['status']==='released'): ?>
                    <div class="sn-meta-item">
                        <span class="sn-meta-lbl">Einde</span>
                        <span class="sn-meta-val">🏁 <?= date('d M Y', strtotime($s['end_date'])) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($data['chars'])): ?>
                    <div class="sn-meta-item">
                        <span class="sn-meta-lbl">Characters</span>
                        <span class="sn-meta-val">👤 <?= count($data['chars']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Timeline events per arc -->
        <?php if (!empty($data['arcs'])): ?>
        <div class="sn-section">
            <div class="sn-sec-ttl">Tijdlijn Events</div>
            <div class="sn-toggle-row">
                <button class="filter-btn" onclick="snToggleAll(<?= $sid ?>,true)">Alles uitklappen</button>
                <button class="filter-btn" onclick="snToggleAll(<?= $sid ?>,false)">Alles inklappen</button>
            </div>

            <?php
            $evCounter = 0;
            foreach ($data['arcs'] as $arc):
                $aEvs  = $data['byArc'][$arc['id']] ?? [];
                $aColor = $arc['color'] ?: '#7c3aed';
                if (empty($aEvs)) continue;
            ?>
            <div class="arc-block open" id="arc-<?= $sid ?>-<?= $arc['id'] ?>">
                <div class="arc-hd" onclick="snToggleArc(this.parentElement)">
                    <div class="arc-dot" style="background:<?= htmlspecialchars($aColor) ?>"></div>
                    <div class="arc-name"><?= htmlspecialchars($arc['name']) ?></div>
                    <div class="arc-pill"><?= count($aEvs) ?> events</div>
                    <div class="arc-chev">▼</div>
                </div>
                <div class="arc-body">
                    <?php foreach ($aEvs as $ei => $ev):
                        $evCounter++;
                        $cat      = $ev['category'] ?? 'other';
                        $cc       = $catColors[$cat] ?? '#94a3b8';
                        $epNum    = $ev['episode_number'] ?? '';
                        $evYear   = $ev['event_year'] ?? '';
                    ?>
                    <div class="ev-row">
                        <div class="ev-num" style="background:<?= htmlspecialchars($aColor) ?>;"><?= $evCounter ?></div>
                        <div class="ev-body">
                            <div class="ev-title"><?= htmlspecialchars($ev['title']) ?></div>
                            <div class="ev-tags">
                                <span class="ev-tag" style="color:<?= $cc ?>;border-color:<?= $cc ?>44;background:<?= $cc ?>15;">
                                    <?= $catIcons[$cat] ?? '' ?> <?= $catLabels[$cat] ?? $cat ?>
                                </span>
                                <?php if (!empty($ev['is_spoiler'])): ?>
                                <span class="ev-tag" style="color:#f59e0b;border-color:#f59e0b44;background:#f59e0b15;">⚠️ Spoiler</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($epNum): ?>
                        <div class="ev-ep">Afl. <?= htmlspecialchars((string)$epNum) ?></div>
                        <?php elseif ($evYear): ?>
                        <div class="ev-ep">Jaar <?= htmlspecialchars((string)$evYear) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php elseif (empty($data['byArc'])): ?>
        <div class="card" style="padding:40px;text-align:center;color:var(--muted);margin-bottom:36px;">
            <div style="font-size:2rem;margin-bottom:10px;">📋</div>
            <div style="font-weight:700;margin-bottom:6px;">Nog geen timeline events</div>
            <div style="font-size:.85rem;">Maak arcs aan voor dit seizoen via het admin panel en koppel de timeline events daaraan.</div>
        </div>
        <?php endif; ?>

        <!-- Characters -->
        <?php if (!empty($data['chars'])): ?>
        <div class="sn-section">
            <div class="sn-sec-ttl">Characters in dit seizoen</div>
            <div class="sn-chars">
                <?php foreach ($data['chars'] as $c):
                    $ci    = !empty($c['image']) ? 'uploads/characters/'.$c['image'] : '';
                    $chImg = $ci && file_exists(__DIR__.'/'.$ci);
                ?>
                <a href="character.php?id=<?= $c['id'] ?>" class="sn-char">
                    <div class="sn-char-av">
                        <?php if ($chImg): ?>
                            <img src="<?= htmlspecialchars($ci) ?>" alt="">
                        <?php else: ?>
                            <?= strtoupper(substr($c['name'],0,1)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="sn-char-name"><?= htmlspecialchars($c['name']) ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /sn-panel -->
    <?php endforeach; ?>

    <?php endif; ?>
</div>

<footer class="tgj-footer"><strong>The Greatest Journey</strong></footer>

<script>
// Season tabs
document.querySelectorAll('.sn-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.sn-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.sn-panel').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('sn-' + this.dataset.sn)?.classList.add('active');
    });
});

// Arc collapse
function snToggleArc(block) { block.classList.toggle('open'); }
function snToggleAll(sid, open) {
    document.querySelectorAll('[id^="arc-' + sid + '-"]').forEach(b => b.classList.toggle('open', open));
}

// Countdown
const cdEl = document.getElementById('countdown');
if (cdEl) {
    const target = new Date(cdEl.dataset.date + 'T00:00:00').getTime();
    function tick() {
        const diff = target - Date.now();
        if (diff <= 0) { cdEl.innerHTML = '<span style="color:var(--gold);font-weight:700;">🎉 Nu beschikbaar!</span>'; return; }
        document.getElementById('cd-d').textContent = String(Math.floor(diff/86400000)).padStart(2,'0');
        document.getElementById('cd-h').textContent = String(Math.floor((diff%86400000)/3600000)).padStart(2,'0');
        document.getElementById('cd-m').textContent = String(Math.floor((diff%3600000)/60000)).padStart(2,'0');
        document.getElementById('cd-s').textContent = String(Math.floor((diff%60000)/1000)).padStart(2,'0');
    }
    tick(); setInterval(tick, 1000);
}
</script>
</body>
</html>
