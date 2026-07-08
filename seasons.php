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

// Per season: arcs, events, characters, episodes
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

    // Episodes for this season — only published/upcoming
    $episodes = [];
    $epResult = $conn->query("
        SELECT id, number, title, description, release_date, reading_time, status
        FROM episodes WHERE season_id = $sid AND status != 'draft'
        ORDER BY sort_order ASC, number ASC
    ");
    if ($epResult) $episodes = $epResult->fetch_all(MYSQLI_ASSOC);

    $seasonData[$sid] = compact('arcs','byArc','chars','episodes');
}

// Fetch episode ratings for all episodes across all seasons
$epRatings = [];
$allEpIds = [];
foreach ($seasonData as $sd) {
    foreach ($sd['episodes'] as $ep) { $allEpIds[] = (int)$ep['id']; }
}
if (!empty($allEpIds)) {
    $ic = implode(',', $allEpIds);
    $tbl = $conn->query("SHOW TABLES LIKE 'episode_ratings'");
    if ($tbl && $tbl->num_rows > 0) {
        $rRows = $conn->query("SELECT episode_id, ROUND(AVG(rating),1) AS avg, COUNT(*) AS cnt FROM episode_ratings WHERE episode_id IN ($ic) GROUP BY episode_id");
        if ($rRows) while ($rr = $rRows->fetch_assoc()) {
            $epRatings[(int)$rr['episode_id']] = ['avg' => (float)$rr['avg'], 'count' => (int)$rr['cnt']];
        }
    }
}

function epScoreColor(float $v): string {
    if ($v >= 9.5) return '#a855f7';
    if ($v >= 8.5) return '#22c55e';
    if ($v >= 7.0) return '#10b981';
    if ($v >= 5.5) return '#eab308';
    if ($v >= 4.0) return '#f97316';
    return '#ef4444';
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

    /* ── EPISODES LIST ── */
    .ep-list { display: flex; flex-direction: column; gap: 10px; }
    .ep-card {
        display: grid;
        grid-template-columns: 56px 1fr auto;
        gap: 0 18px; align-items: center;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 14px 20px;
        text-decoration: none; color: var(--text);
        transition: all .18s;
        position: relative; overflow: hidden;
    }
    .ep-card::before {
        content: ''; position: absolute; left: 0; top: 0; bottom: 0;
        width: 4px; background: var(--primary);
        opacity: 0; transition: opacity .18s;
        border-radius: 4px 0 0 4px;
    }
    .ep-card:hover { background: var(--primary-light); border-color: var(--primary); transform: translateX(4px); box-shadow: var(--card-hover-shadow); }
    .ep-card:hover::before { opacity: 1; }
    .ep-num-badge {
        width: 48px; height: 48px; border-radius: 12px;
        background: linear-gradient(135deg, var(--primary) 0%, #6d28d9 100%);
        display: flex; align-items: center; justify-content: center;
        flex-direction: column; flex-shrink: 0;
    }
    .ep-num-label { font-size: .5rem; font-weight: 800; letter-spacing: .08em; color: rgba(255,255,255,.65); text-transform: uppercase; }
    .ep-num-val   { font-size: 1.1rem; font-weight: 900; color: #fff; font-family: var(--font-display); line-height: 1; }
    .ep-info { min-width: 0; }
    .ep-title { font-size: .9rem; font-weight: 700; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ep-desc  { font-size: .77rem; color: var(--muted); margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ep-meta  { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
    .ep-date  { font-size: .68rem; color: var(--muted); font-weight: 600; white-space: nowrap; }
    .ep-read-btn {
        font-size: .68rem; font-weight: 700; padding: 4px 12px;
        background: var(--primary); color: #fff;
        border-radius: 100px; white-space: nowrap;
        opacity: 0; transition: opacity .18s;
    }
    .ep-card:hover .ep-read-btn { opacity: 1; }
    .ep-locked {
        font-size: .68rem; font-weight: 700; padding: 4px 12px;
        background: var(--surface); color: var(--muted);
        border: 1px solid var(--border); border-radius: 100px; white-space: nowrap;
    }
    .ep-card-locked { opacity: .6; cursor: default; pointer-events: none; }
    .ep-badges { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 4px; }
    .ep-time-badge {
        font-size: .63rem; font-weight: 700; padding: 2px 8px;
        background: var(--primary-light); color: var(--primary);
        border: 1px solid var(--border); border-radius: 100px;
    }
    .ep-read-check {
        position: absolute; top: 10px; right: 10px;
        width: 22px; height: 22px; border-radius: 50%;
        background: #22c55e; color: #fff;
        font-size: .72rem; font-weight: 900;
        display: flex !important; align-items: center; justify-content: center;
        box-shadow: 0 2px 8px rgba(34,197,94,.4);
    }
    .ep-card { position: relative; }
    .ep-card.is-read { border-color: rgba(34,197,94,.3); }
    .ep-card.is-read .ep-num-badge { background: linear-gradient(135deg, #16a34a 0%, #22c55e 100%); }
    .ep-season-progress {
        font-size: .65rem; font-weight: 700;
        color: var(--primary); background: var(--primary-light);
        border: 1px solid var(--border); border-radius: 100px;
        padding: 2px 10px; margin-left: 8px;
    }
    .sn-progress-bar-wrap {
        height: 5px; background: var(--border); border-radius: 100px;
        margin-bottom: 16px; overflow: hidden;
    }
    .sn-progress-bar-fill {
        height: 100%; border-radius: 100px; width: 0%;
        background: linear-gradient(90deg, var(--primary), #a78bfa);
        transition: width .6s cubic-bezier(.4,0,.2,1);
    }

    /* ── EPISODE FAVORITE BUTTON ── */
    .ep-fav-btn {
        background: none; border: none; font-size: 1rem;
        cursor: pointer; padding: 4px 8px; line-height: 1;
        opacity: .5; transition: opacity .15s, transform .15s;
        flex-shrink: 0; align-self: center;
    }
    .ep-fav-btn:hover { opacity: 1; transform: scale(1.2); }
    .ep-fav-btn.fav-active { opacity: 1; }

    /* ── EPISODE CARD RATING ── */
    .ep-crating-badge {
        font-size: .72rem; font-weight: 900; letter-spacing: .02em;
        padding: 2px 9px; border-radius: 7px; height: 22px;
        border: 1.5px solid rgba(255,255,255,.12);
        background: rgba(255,255,255,.05);
        color: rgba(255,255,255,.3);
        cursor: pointer; transition: all .15s; user-select: none;
        display: inline-flex; align-items: center; white-space: nowrap;
    }
    .ep-crating-badge:hover { border-color: rgba(255,255,255,.3); color: rgba(255,255,255,.65); }
    .ep-crating-badge.has-score { font-size: .78rem; }
    .ep-crate-nums {
        gap: 4px; flex-wrap: wrap; margin-top: 6px; padding: 4px 0 2px;
    }
    .ep-crate-num {
        width: 30px; height: 30px; border-radius: 6px;
        border: 1.5px solid rgba(255,255,255,.12);
        background: rgba(255,255,255,.05);
        color: rgba(255,255,255,.35);
        font-size: .75rem; font-weight: 800;
        cursor: pointer; transition: all .12s;
        display: flex; align-items: center; justify-content: center;
    }
    .ep-crate-num:hover { transform: scale(1.12); }
    .ep-crate-num.lit {
        border-color: var(--c); background: color-mix(in srgb, var(--c) 18%, transparent); color: var(--c);
    }
    .ep-crate-num.mine {
        border-color: var(--c); background: var(--c); color: #111;
    }

    @media (max-width: 768px) {
        .sn-hero { flex-direction: column; gap: 20px; padding: 24px; }
        .sn-title { font-size: 1.5rem; }
        /* Always show read button — no hover on touch */
        .ep-read-btn { opacity: 1 !important; }
        .ep-card:hover { transform: none; }
    }
    @media (max-width: 600px) {
        /* Episode card: 2-column grid, meta wraps below info */
        .ep-card { grid-template-columns: 44px 1fr; gap: 0 14px; padding: 12px 14px; }
        .ep-num-badge { grid-row: span 2; width: 38px; height: 38px; border-radius: 10px; }
        .ep-num-val   { font-size: .95rem; }
        .ep-info  { grid-column: 2; }
        .ep-meta  {
            grid-column: 2;
            flex-direction: row; flex-wrap: wrap;
            align-items: center; justify-content: flex-start;
            gap: 6px; padding-top: 4px;
        }
        .ep-read-btn  { font-size: .63rem; padding: 3px 10px; opacity: 1 !important; }
        .ep-date      { font-size: .62rem; }
        .ep-fav-btn   { font-size: .85rem; padding: 2px 6px; }
        .ep-time-badge { font-size: .6rem; padding: 2px 7px; }
        .ep-crating-badge { font-size: .65rem; padding: 2px 7px; height: 20px; }
        .ep-crate-num  { width: 27px; height: 27px; font-size: .7rem; }
        .ep-crate-nums { gap: 3px; }
        /* Season hero condensed */
        .sn-hero { padding: 18px; gap: 14px; }
        .sn-cover, .sn-cover-ph { width: 110px; height: 140px; }
        .sn-title { font-size: 1.3rem; }
        .sn-desc  { font-size: .82rem; }
        .sn-meta  { gap: 16px; margin-top: 12px; }
    }

    /* ── LEESDOELEN WIDGET ── */
    .sn-goal-widget {
        background: var(--card); border: 1px solid var(--border);
        border-radius: 16px; padding: 18px 24px;
        margin-bottom: 28px;
        display: flex; align-items: center; gap: 20px; flex-wrap: wrap;
    }
    .sn-goal-left { flex: 1; min-width: 200px; }
    .sn-goal-heading {
        font-size: .6rem; font-weight: 800; letter-spacing: .14em;
        text-transform: uppercase; color: var(--primary); margin-bottom: 8px;
    }
    .sn-goal-text { font-size: .88rem; font-weight: 700; color: var(--text); margin-bottom: 10px; }
    .sn-goal-bar-wrap {
        background: var(--border); border-radius: 100px; height: 6px; overflow: hidden;
        display: none;
    }
    .sn-goal-bar {
        height: 100%; border-radius: 100px;
        background: linear-gradient(90deg, var(--primary), #a78bfa);
        width: 0; transition: width .6s cubic-bezier(.4,0,.2,1);
    }
    .sn-goal-right { flex-shrink: 0; }
    .sn-goal-presets { display: flex; gap: 6px; }
    .sn-goal-preset {
        background: var(--surface); border: 1px solid var(--border);
        color: var(--muted); font-size: .72rem; font-weight: 700;
        padding: 5px 13px; border-radius: 8px; cursor: pointer;
        transition: all .15s; font-family: inherit;
    }
    .sn-goal-preset:hover  { background: var(--primary-light); border-color: var(--primary); color: var(--primary); }
    .sn-goal-preset.active { background: var(--primary); border-color: var(--primary); color: #fff; }
    .sn-goal-clear {
        background: none; border: none; cursor: pointer;
        font-size: .68rem; color: var(--muted); font-family: inherit;
        padding: 4px 0; margin-top: 6px; display: none;
        transition: color .15s;
    }
    .sn-goal-clear:hover { color: #ef4444; }
    @media (max-width: 600px) {
        .sn-goal-widget { padding: 14px 16px; gap: 14px; }
    }

    /* ── SEASON SUMMARY ── */
    .sn-summary {
        margin: 32px 0 0;
        background: rgba(124,58,237,.07);
        border: 1px solid rgba(124,58,237,.2);
        border-radius: 16px;
        padding: 20px 24px;
    }
    .sn-summary-title {
        font-size: .72rem; font-weight: 800; letter-spacing: .1em;
        text-transform: uppercase; color: var(--primary);
        margin-bottom: 16px;
    }
    .sn-sum-stats { display: flex; gap: 14px; flex-wrap: wrap; }
    .sn-sum-stat {
        flex: 1; min-width: 100px;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 12px 16px;
    }
    .sn-sum-val {
        display: block;
        font-size: 1.6rem; font-weight: 900; line-height: 1;
        font-family: var(--font-display); color: var(--text);
        margin-bottom: 4px;
    }
    .sn-sum-lbl {
        font-size: .66rem; font-weight: 600;
        color: var(--muted); text-transform: uppercase; letter-spacing: .05em;
    }
    .sn-sum-bar-wrap {
        margin-top: 8px; background: rgba(255,255,255,.07);
        border-radius: 100px; height: 4px;
    }
    .sn-sum-bar-fill {
        height: 100%; border-radius: 100px;
        background: var(--primary); width: 0;
        transition: width .7s cubic-bezier(.4,0,.2,1);
    }
    @media (max-width: 600px) {
        .sn-summary { padding: 16px; }
        .sn-sum-stats { gap: 10px; }
        .sn-sum-stat  { min-width: 88px; padding: 10px 12px; }
        .sn-sum-val   { font-size: 1.25rem; }
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

    <!-- Reading goal widget -->
    <div class="sn-goal-widget">
        <div class="sn-goal-left">
            <div class="sn-goal-heading">🎯 Leesdoel deze week</div>
            <div class="sn-goal-text" id="sn-goal-text"></div>
            <div class="sn-goal-bar-wrap" id="sn-goal-bar-wrap"><div class="sn-goal-bar" id="sn-goal-bar"></div></div>
        </div>
        <div class="sn-goal-right">
            <div class="sn-goal-heading" id="sn-goal-right-label" style="margin-bottom:8px;">Stel een doel in</div>
            <div class="sn-goal-presets">
                <button class="sn-goal-preset" data-val="1"  onclick="setGoal(1)">1</button>
                <button class="sn-goal-preset" data-val="3"  onclick="setGoal(3)">3</button>
                <button class="sn-goal-preset" data-val="5"  onclick="setGoal(5)">5</button>
                <button class="sn-goal-preset" data-val="10" onclick="setGoal(10)">10</button>
            </div>
            <button class="sn-goal-clear" id="sn-goal-clear" onclick="clearGoal()">✕ Geen doel</button>
        </div>
    </div>

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

        <!-- Episodes -->
        <?php if (!empty($data['episodes'])): ?>
        <div class="sn-section">
            <div class="sn-sec-ttl">
                Afleveringen
                <span class="ep-season-progress" id="ep-progress-<?= $sid ?>"></span>
            </div>
            <div class="sn-progress-bar-wrap" id="sn-bar-wrap-<?= $sid ?>" style="display:none;">
                <div class="sn-progress-bar-fill" id="sn-bar-<?= $sid ?>"></div>
            </div>
            <div class="ep-list">
                <?php foreach ($data['episodes'] as $ep):
                    $epStatus  = $ep['status'] ?? 'published';
                    $isLocked  = $epStatus === 'upcoming';
                    $isReadable = $epStatus === 'published';
                    $relDate   = $ep['release_date'] ? date('d M Y', strtotime($ep['release_date'])) : null;
                ?>
                <?php if ($isReadable): ?>
                <a href="episode.php?id=<?= $ep['id'] ?>" class="ep-card" data-ep-id="<?= $ep['id'] ?>">
                <?php else: ?>
                <div class="ep-card ep-card-locked" data-ep-id="<?= $ep['id'] ?>">
                <?php endif; ?>
                    <!-- Read checkmark (shown via JS) -->
                    <div class="ep-read-check" id="ep-check-<?= $ep['id'] ?>" style="display:none;">✓</div>

                    <div class="ep-num-badge">
                        <span class="ep-num-label">Afl.</span>
                        <span class="ep-num-val"><?= $ep['number'] ?></span>
                    </div>
                    <div class="ep-info">
                        <div class="ep-title"><?= htmlspecialchars($ep['title']) ?></div>
                        <?php if (!empty($ep['description'])): ?>
                        <div class="ep-desc"><?= htmlspecialchars($ep['description']) ?></div>
                        <?php endif; ?>
                        <div class="ep-badges">
                            <?php if (!empty($ep['reading_time'])): ?>
                            <span class="ep-time-badge">⏱ ~<?= $ep['reading_time'] ?> min</span>
                            <?php endif; ?>
                            <?php
                            $eid = (int)$ep['id'];
                            $eRat = $epRatings[$eid] ?? null;
                            $eHas = $eRat && $eRat['count'] > 0;
                            $eBadgeStyle = '';
                            if ($eHas) {
                                $eCol = epScoreColor($eRat['avg']);
                                $eBadgeStyle = "background:{$eCol}22;color:{$eCol};border-color:{$eCol}55;";
                            }
                            if ($isReadable):
                            ?>
                            <button class="ep-crating-badge<?= $eHas ? ' has-score' : '' ?>"
                                    id="epcb-<?= $eid ?>"
                                    data-ep-id="<?= $eid ?>"
                                    data-avg="<?= $eHas ? $eRat['avg'] : 0 ?>"
                                    style="<?= $eBadgeStyle ?>"
                                    onclick="event.stopPropagation();event.preventDefault();epcToggle(<?= $eid ?>)"
                                    type="button"
                                    title="<?= $eHas ? $eRat['count'].' '.($eRat['count']===1?'beoordeling':'beoordelingen') : 'Beoordeel' ?>">
                                <?= $eHas ? number_format($eRat['avg'],1) : '✦ Beoordeel' ?>
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php if ($isReadable): ?>
                        <div class="ep-crate-nums" id="epcn-<?= $eid ?>" style="display:none;">
                            <?php for($ni=1;$ni<=10;$ni++): ?>
                            <button class="ep-crate-num" data-n="<?= $ni ?>" type="button"
                                    onclick="event.stopPropagation();event.preventDefault();epcSubmit(<?= $eid ?>,<?= $ni ?>)"><?= $ni ?></button>
                            <?php endfor; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="ep-meta">
                        <?php if ($relDate): ?>
                        <span class="ep-date">📅 <?= $relDate ?></span>
                        <?php endif; ?>
                        <?php if ($isReadable): ?>
                        <span class="ep-read-btn">Lezen →</span>
                        <?php else: ?>
                        <span class="ep-locked">🔒 Binnenkort</span>
                        <?php endif; ?>
                        <?php if ($isReadable): ?>
                        <button class="ep-fav-btn" type="button"
                                data-ep-id="<?= $ep['id'] ?>"
                                onclick="event.stopPropagation();event.preventDefault();toggleEpFav(<?= $ep['id'] ?>,this)"
                                title="Toevoegen aan favorieten">🤍</button>
                        <?php endif; ?>
                    </div>
                <?php if ($isReadable): ?>
                </a>
                <?php else: ?>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

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

        <!-- Episode data + summary placeholder (rendered by JS from localStorage) -->
        <script type="application/json" id="sneps-<?= $sid ?>" data-snum="<?= (int)$s['number'] ?>"><?= json_encode(
            array_map(fn($e) => ['id' => (int)$e['id'], 'rt' => (int)($e['reading_time'] ?? 0)], $data['episodes']),
            JSON_UNESCAPED_UNICODE
        ) ?></script>
        <div class="sn-summary" id="sn-sum-<?= $sid ?>" style="display:none;"></div>

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

// ── Read progress from localStorage ──
(function() {
    let readArr = [];
    try { readArr = JSON.parse(localStorage.getItem('tgj_read_ep') || '[]'); } catch {}

    document.querySelectorAll('.ep-card[data-ep-id]').forEach(card => {
        const epId = parseInt(card.dataset.epId, 10);
        if (readArr.includes(epId)) {
            card.classList.add('is-read');
            const check = document.getElementById('ep-check-' + epId);
            if (check) check.style.display = 'flex';
        }
    });

    // Show season progress per season panel
    document.querySelectorAll('[id^="ep-progress-"]').forEach(el => {
        const sid = el.id.replace('ep-progress-', '');
        const panel = document.getElementById('sn-' + sid);
        if (!panel) return;
        const cards = panel.querySelectorAll('.ep-card[data-ep-id]');
        const total = cards.length;
        if (!total) return;
        let done = 0;
        cards.forEach(c => { if (readArr.includes(parseInt(c.dataset.epId, 10))) done++; });
        if (done > 0) {
            el.textContent = done + '/' + total + ' gelezen';
            const bar = document.getElementById('sn-bar-' + sid);
            const barWrap = document.getElementById('sn-bar-wrap-' + sid);
            if (bar && barWrap) {
                barWrap.style.display = 'block';
                setTimeout(() => { bar.style.width = Math.round((done / total) * 100) + '%'; }, 100);
            }
        }
    });
})();

// ── Episode Favorites ──
const FAV_KEY_EPS = 'tgj_fav_eps';
function getFavEps() { try { return JSON.parse(localStorage.getItem(FAV_KEY_EPS)||'[]'); } catch { return []; } }
function toggleEpFav(id, btn) {
    let favs = getFavEps();
    const idx = favs.indexOf(id);
    if (idx === -1) { favs.push(id); } else { favs.splice(idx, 1); }
    localStorage.setItem(FAV_KEY_EPS, JSON.stringify(favs));
    updateEpFavBtn(btn, favs.includes(id));
}
function updateEpFavBtn(btn, active) {
    btn.textContent = active ? '❤' : '🤍';
    btn.classList.toggle('fav-active', active);
    btn.title = active ? 'Verwijder uit favorieten' : 'Toevoegen aan favorieten';
}
// Init heart buttons on load
document.querySelectorAll('.ep-fav-btn').forEach(btn => {
    const id = parseInt(btn.dataset.epId);
    updateEpFavBtn(btn, getFavEps().includes(id));
});

// ── Episode card ratings ──
const EPC_COLORS = {
    1:'#ef4444',2:'#ef4444',3:'#ef4444',
    4:'#f97316',5:'#f97316',
    6:'#eab308',
    7:'#10b981',8:'#10b981',
    9:'#22c55e',10:'#a855f7'
};
function epcRatingColor(v) {
    if (v >= 9.5) return '#a855f7';
    if (v >= 8.5) return '#22c55e';
    if (v >= 7.0) return '#10b981';
    if (v >= 5.5) return '#eab308';
    if (v >= 4.0) return '#f97316';
    return '#ef4444';
}
function epcGetRated() { try { return JSON.parse(localStorage.getItem('tgj_rated_eps')||'{}'); } catch { return {}; } }

function epcToggle(epId) {
    const row = document.getElementById('epcn-' + epId);
    if (!row) return;
    row.style.display = (row.style.display === 'none' || !row.style.display) ? 'flex' : 'none';
}

async function epcSubmit(epId, n) {
    const row = document.getElementById('epcn-' + epId);
    if (row) row.style.display = 'none';
    const fd = new FormData();
    fd.append('action','rate'); fd.append('ep_id',epId); fd.append('rating',n);
    try {
        const res  = await fetch('episode.php', { method: 'POST', body: fd });
        const data = await res.json();
        epcUpdateBadge(epId, data.ok ? data.avg : n, data.ok ? data.count : 1, n);
    } catch { epcUpdateBadge(epId, n, 1, n); }
    const rated = epcGetRated(); rated[epId] = n;
    localStorage.setItem('tgj_rated_eps', JSON.stringify(rated));
    const numRow = document.getElementById('epcn-' + epId);
    if (numRow) numRow.querySelectorAll('.ep-crate-num').forEach(b => {
        b.classList.toggle('mine', parseInt(b.dataset.n) === n);
        b.classList.remove('lit');
    });
}

function epcUpdateBadge(epId, avg, cnt, myVote) {
    const badge = document.getElementById('epcb-' + epId);
    if (!badge) return;
    const col = epcRatingColor(avg);
    badge.style.background = col + '22';
    badge.style.color = col;
    badge.style.borderColor = col + '55';
    badge.classList.add('has-score');
    badge.textContent = parseFloat(avg).toFixed(1);
    badge.title = cnt + (cnt === 1 ? ' beoordeling' : ' beoordelingen');
}

// Init: set --c on num buttons, restore voted state from localStorage
(function() {
    const rated = epcGetRated();
    document.querySelectorAll('.ep-crate-num').forEach(btn => {
        btn.style.setProperty('--c', EPC_COLORS[parseInt(btn.dataset.n)]);
    });
    Object.entries(rated).forEach(([epId, myVote]) => {
        const numRow = document.getElementById('epcn-' + epId);
        if (numRow) numRow.querySelectorAll('.ep-crate-num').forEach(b => {
            b.classList.toggle('mine', parseInt(b.dataset.n) === myVote);
        });
    });
})();

// ── Leesdoelen ──
(function() {
    const GOAL_KEY = 'tgj_goal';
    const LOG_KEY  = 'tgj_read_log';

    function loadGoal() {
        try { return Math.max(1, parseInt(JSON.parse(localStorage.getItem(GOAL_KEY) || '{"weekly":5}').weekly) || 5); }
        catch { return 5; }
    }

    function getWeeklyCount() {
        let log = [];
        try { log = JSON.parse(localStorage.getItem(LOG_KEY) || '[]'); } catch {}
        const today    = new Date();
        const weekStart = new Date(today);
        const dow      = today.getDay();
        weekStart.setDate(today.getDate() - (dow === 0 ? 6 : dow - 1));
        weekStart.setHours(0, 0, 0, 0);
        const wStr = weekStart.toISOString().slice(0, 10);
        return log.filter(e => e.date >= wStr).length;
    }

    function renderGoalWidget() {
        const hasGoal = localStorage.getItem(GOAL_KEY) !== null;
        const textEl  = document.getElementById('sn-goal-text');
        const barWrap = document.getElementById('sn-goal-bar-wrap');
        const barEl   = document.getElementById('sn-goal-bar');
        const labelEl = document.getElementById('sn-goal-right-label');

        const clearBtn = document.getElementById('sn-goal-clear');
        if (!hasGoal) {
            if (textEl)   textEl.textContent = 'Kies hoeveel afleveringen je wil lezen deze week.';
            if (barWrap)  barWrap.style.display = 'none';
            if (labelEl)  labelEl.textContent = 'Stel een doel in';
            if (clearBtn) clearBtn.style.display = 'none';
            document.querySelectorAll('.sn-goal-preset').forEach(b => b.classList.remove('active'));
            return;
        }

        const goal = loadGoal();
        const done = getWeeklyCount();
        const left = Math.max(0, goal - done);
        const pct  = Math.min(100, Math.round(done / goal * 100));

        if (textEl) {
            if (done >= goal) {
                textEl.innerHTML = `<span style="color:#22c55e;font-weight:800;">🎉 Doel bereikt!</span> ${done} van ${goal} afleveringen gelezen`;
            } else {
                textEl.textContent = `${done} van ${goal} afleveringen gelezen — nog ${left} te gaan`;
            }
        }
        if (barWrap)  barWrap.style.display = '';
        if (barEl)    setTimeout(() => { barEl.style.width = pct + '%'; }, 120);
        if (labelEl)  labelEl.textContent = 'Aanpassen';
        if (clearBtn) clearBtn.style.display = '';
        document.querySelectorAll('.sn-goal-preset').forEach(btn => {
            btn.classList.toggle('active', parseInt(btn.dataset.val) === goal);
        });
    }

    window.setGoal = function(n) {
        localStorage.setItem(GOAL_KEY, JSON.stringify({ weekly: n }));
        renderGoalWidget();
    };

    window.clearGoal = function() {
        localStorage.removeItem(GOAL_KEY);
        renderGoalWidget();
    };

    renderGoalWidget();
})();

// ── Season Summary ──
(function() {
    let readArr = [], rated = {}, quotes = [];
    try { readArr = JSON.parse(localStorage.getItem('tgj_read_ep') || '[]'); } catch {}
    try { rated   = JSON.parse(localStorage.getItem('tgj_rated_eps') || '{}'); } catch {}
    try { quotes  = JSON.parse(localStorage.getItem('tgj_quotes') || '[]'); } catch {}

    document.querySelectorAll('[id^="sneps-"]').forEach(el => {
        const sid  = el.id.replace('sneps-', '');
        const snum = parseInt(el.dataset.snum, 10);
        let eps;
        try { eps = JSON.parse(el.textContent); } catch { return; }
        const wrap = document.getElementById('sn-sum-' + sid);
        if (!wrap || !eps.length) return;

        const total    = eps.length;
        const readEps  = eps.filter(e => readArr.includes(e.id));
        const done     = readEps.length;
        const mins     = readEps.reduce((s, e) => s + e.rt, 0);
        const myRatings = eps.map(e => rated[e.id]).filter(Boolean);
        const avgR     = myRatings.length
            ? (myRatings.reduce((a, b) => a + b, 0) / myRatings.length).toFixed(1)
            : null;
        const sqCount  = quotes.filter(q => q.seasonNum === snum).length;

        if (!done && !sqCount) return;

        const pct   = Math.round(done / total * 100);
        const rcol  = avgR ? epcRatingColor(parseFloat(avgR)) : null;

        wrap.style.display = '';
        wrap.innerHTML = `
        <div class="sn-summary-title">📊 Jouw seizoen ${snum} overzicht</div>
        <div class="sn-sum-stats">
            <div class="sn-sum-stat">
                <span class="sn-sum-val">${done}<span style="font-size:.5em;opacity:.45;font-weight:700">/${total}</span></span>
                <span class="sn-sum-lbl">Afleveringen gelezen</span>
                <div class="sn-sum-bar-wrap"><div class="sn-sum-bar-fill" id="sbar-${sid}"></div></div>
            </div>
            ${mins ? `<div class="sn-sum-stat">
                <span class="sn-sum-val">${mins}</span>
                <span class="sn-sum-lbl">Minuten gelezen</span>
            </div>` : ''}
            ${avgR ? `<div class="sn-sum-stat">
                <span class="sn-sum-val" style="color:${rcol}">${avgR}</span>
                <span class="sn-sum-lbl">Gem. jouw rating</span>
            </div>` : ''}
            ${sqCount ? `<div class="sn-sum-stat">
                <span class="sn-sum-val">💬 ${sqCount}</span>
                <span class="sn-sum-lbl">Opgeslagen quotes</span>
            </div>` : ''}
        </div>`;

        setTimeout(() => {
            const b = document.getElementById('sbar-' + sid);
            if (b) b.style.width = pct + '%';
        }, 100);
    });
})();

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
