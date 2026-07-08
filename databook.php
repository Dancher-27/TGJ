<?php
require_once 'includes/connection.php';

$characters = $conn->query("
    SELECT c.*, f.name AS faction_name, f.color AS faction_color
    FROM characters c
    LEFT JOIN factions f ON f.id = c.faction_id
    ORDER BY FIELD(c.role,'protagonist','anti-hero','antagonist','villain','supporting'), c.sort_order ASC, c.name ASC
")->fetch_all(MYSQLI_ASSOC);

// Load relations per character
$allRelations = [];
$relResult = $conn->query("
    SELECT cr.character_id, cr.relation, cr.description AS rel_desc, c.id, c.name, c.image, c.role
    FROM character_relations cr
    JOIN characters c ON c.id = cr.related_id
");
if ($relResult) {
    foreach ($relResult->fetch_all(MYSQLI_ASSOC) as $r) {
        $allRelations[$r['character_id']][] = $r;
    }
}

// Load seasons per character
$allSeasons = [];
$seasResult = $conn->query("
    SELECT cs.character_id, s.number, s.title
    FROM character_seasons cs
    JOIN seasons s ON s.id = cs.season_id
    ORDER BY s.number
");
if ($seasResult) {
    foreach ($seasResult->fetch_all(MYSQLI_ASSOC) as $s) {
        $allSeasons[$s['character_id']][] = $s;
    }
}

$roleLabels   = ['protagonist'=>'Protagonist','antagonist'=>'Antagonist','supporting'=>'Bijrol','villain'=>'Schurk','anti-hero'=>'Anti-held'];
$statusLabels = ['alive'=>'Levend','deceased'=>'Overleden','unknown'=>'Onbekend'];
$relLabels    = ['friend'=>'Vriend','enemy'=>'Vijand','rival'=>'Rivaal','family'=>'Familie','mentor'=>'Mentor','student'=>'Student','love'=>'Liefde'];
$roleColors   = ['protagonist'=>'#f59e0b','antagonist'=>'#ef4444','villain'=>'#ef4444','anti-hero'=>'#7c3aed','supporting'=>'#6b7280'];
$statusColors = ['alive'=>'#22c55e','deceased'=>'#ef4444','unknown'=>'#6b7280'];

require_once 'includes/helpers.php';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Databook — The Greatest Journey</title>
    <?php include 'includes/fonts.php'; ?>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* ── DATABOOK LAYOUT ── */
        .databook-wrap {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 0;
            min-height: calc(100vh - var(--nav-h) - 80px);
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        /* ── SIDEBAR ── */
        .db-sidebar {
            background: var(--navbar-bg);
            border-right: 1px solid rgba(255,255,255,.08);
            display: flex;
            flex-direction: column;
        }
        .db-sidebar-header {
            padding: 24px 20px 16px;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }
        .db-sidebar-title {
            font-size: .7rem;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--primary-mid);
            margin-bottom: 12px;
        }
        .db-search {
            width: 100%;
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 10px;
            padding: 8px 12px;
            color: #fff;
            font-size: .8rem;
            outline: none;
        }
        .db-search::placeholder { color: rgba(255,255,255,.35); }
        .db-search:focus { border-color: var(--primary-mid); }

        .db-char-list {
            flex: 1;
            overflow-y: auto;
            padding: 8px;
        }
        .db-char-list::-webkit-scrollbar { width: 4px; }
        .db-char-list::-webkit-scrollbar-track { background: transparent; }
        .db-char-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,.15); border-radius: 2px; }

        .db-tab {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 10px 12px;
            border-radius: 12px;
            cursor: pointer;
            transition: background .15s;
            border: none;
            width: 100%;
            text-align: left;
            color: rgba(255,255,255,.65);
            background: transparent;
            margin-bottom: 2px;
        }
        .db-tab:hover { background: rgba(255,255,255,.07); color: #fff; }
        .db-tab.active {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 4px 16px rgba(124,58,237,.4);
        }
        .db-tab-av {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            overflow: hidden;
            flex-shrink: 0;
            background: rgba(255,255,255,.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 700;
        }
        .db-tab-av img { width: 100%; height: 100%; object-fit: cover; }
        .db-tab-info { flex: 1; min-width: 0; }
        .db-tab-name {
            font-size: .75rem;
            font-weight: 600;
            font-family: var(--font-display);
            letter-spacing: .02em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .db-tab-role {
            font-size: .67rem;
            opacity: .6;
            margin-top: 1px;
        }
        .db-tab.active .db-tab-role { opacity: .8; }

        .db-role-sep {
            font-size: .6rem;
            font-weight: 800;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: rgba(255,255,255,.25);
            padding: 10px 12px 4px;
        }

        /* ── MAIN PANEL ── */
        .db-panel {
            padding: 0;
            overflow-y: auto;
            position: relative;
        }
        .db-entry { display: none; }
        .db-entry.active { display: block; }

        .db-entry-header {
            background: linear-gradient(135deg, var(--navbar-bg) 0%, var(--navbar-bg2) 100%);
            padding: 36px 40px 32px;
            display: flex;
            gap: 32px;
            align-items: flex-start;
            position: relative;
            overflow: hidden;
        }
        .db-entry-header::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 80% 50%, rgba(124,58,237,.25) 0%, transparent 60%);
        }
        .db-portrait-wrap {
            flex-shrink: 0;
            position: relative;
            z-index: 1;
        }
        .db-portrait {
            width: 140px;
            height: 180px;
            border-radius: 16px;
            object-fit: cover;
            border: 3px solid var(--primary);
            box-shadow: 0 8px 32px rgba(124,58,237,.4);
            display: block;
        }
        .db-portrait-ph {
            width: 140px;
            height: 180px;
            border-radius: 16px;
            background: rgba(255,255,255,.05);
            border: 3px solid rgba(255,255,255,.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
        }
        .db-header-info {
            flex: 1;
            position: relative;
            z-index: 1;
        }
        .db-char-name {
            font-size: 2rem;
            font-weight: 700;
            font-family: var(--font-display);
            letter-spacing: .03em;
            color: #fff;
            line-height: 1.15;
            margin-bottom: 4px;
        }
        .db-char-alias {
            font-size: .9rem;
            color: var(--gold-light);
            font-style: italic;
            margin-bottom: 16px;
        }
        .db-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .db-badge {
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .05em;
            padding: 4px 12px;
            border-radius: 100px;
            border: 1.5px solid;
        }
        .db-faction-tag {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 100px;
            padding: 5px 14px;
            font-size: .75rem;
            font-weight: 600;
            color: rgba(255,255,255,.8);
        }
        .db-faction-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .db-quick-stats {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .db-stat-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .db-stat-label {
            font-size: .6rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: rgba(255,255,255,.4);
        }
        .db-stat-val {
            font-size: .85rem;
            font-weight: 700;
            color: rgba(255,255,255,.9);
        }

        /* ── BODY CONTENT ── */
        .db-body {
            padding: 36px 40px;
        }
        .db-section {
            margin-bottom: 36px;
        }
        .db-section-title {
            font-size: .6rem;
            font-weight: 700;
            font-family: var(--font-display);
            letter-spacing: .18em;
            text-transform: uppercase;
            color: var(--primary);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .db-section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }
        .db-text {
            font-size: .9rem;
            color: var(--text);
            line-height: 1.8;
        }
        .db-abilities-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .db-ability-tag {
            background: var(--primary-light);
            color: var(--primary);
            font-size: .75rem;
            font-weight: 700;
            padding: 5px 14px;
            border-radius: 100px;
            border: 1px solid var(--border);
        }
        .db-spoiler-wrap {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
        }
        .db-spoiler-blur {
            filter: blur(6px);
            user-select: none;
            pointer-events: none;
        }
        .db-spoiler-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            z-index: 2;
        }
        .db-spoiler-overlay span {
            font-size: .8rem;
            font-weight: 700;
            color: var(--primary);
            background: var(--card);
            padding: 8px 20px;
            border-radius: 100px;
            border: 1.5px solid var(--primary);
            box-shadow: 0 4px 16px rgba(124,58,237,.2);
        }
        .db-spoiler-wrap.revealed .db-spoiler-blur { filter: none; user-select: auto; pointer-events: auto; }
        .db-spoiler-wrap.revealed .db-spoiler-overlay { display: none; }

        /* Relations */
        .db-relations {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .db-rel-chip {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 9px 14px;
            text-decoration: none;
            color: var(--text);
            transition: all .15s;
            min-width: 160px;
        }
        .db-rel-chip:hover {
            background: var(--primary-light);
            border-color: var(--primary);
            transform: translateY(-1px);
        }
        .db-rel-av {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            overflow: hidden;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: .85rem;
            color: var(--primary);
            flex-shrink: 0;
        }
        .db-rel-av img { width: 100%; height: 100%; object-fit: cover; }
        .db-rel-name { font-size: .8rem; font-weight: 700; }
        .db-rel-type { font-size: .68rem; color: var(--gold); font-weight: 600; }

        /* Seasons pills */
        .db-season-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .db-season-pill {
            background: var(--primary-light);
            color: var(--primary);
            font-size: .75rem;
            font-weight: 700;
            padding: 6px 16px;
            border-radius: 100px;
            border: 1px solid var(--border);
        }

        /* Empty state */
        .db-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            min-height: 400px;
            gap: 12px;
            color: var(--muted);
        }
        .db-empty-icon { font-size: 3rem; }
        .db-empty-text { font-size: .9rem; font-weight: 600; }

        /* No characters */
        .db-no-chars {
            padding: 60px 40px;
            text-align: center;
            color: var(--muted);
        }

        /* ── dp-* parser styles live in styles.css ── */

        /* Responsive */
        @media (max-width: 768px) {
            .databook-wrap {
                grid-template-columns: 1fr;
                grid-template-rows: auto 1fr;
            }
            .db-sidebar {
                border-right: none;
                border-bottom: 1px solid rgba(255,255,255,.08);
            }
            .db-char-list {
                max-height: 200px;
            }
            .db-entry-header {
                flex-direction: column;
                gap: 20px;
                padding: 24px;
            }
            .db-body { padding: 24px; }
            .db-char-name { font-size: 1.5rem; }
        }
        @media (max-width: 480px) {
            .db-entry-header { padding: 18px 16px; gap: 14px; }
            .db-body { padding: 16px; }
            .db-char-name { font-size: 1.3rem; }
            .db-char-portrait { width: 90px; height: 120px; border-radius: 12px; }
        }
    </style>
</head>
<body class="page">
<?php include 'includes/navbar.php'; ?>

<div class="container" style="padding-bottom: 60px;">
    <div class="section-header">
        <h1 class="section-title">Databook</h1>
        <p class="section-sub">Alles over elk character — statistieken, abilities en meer</p>
    </div>

    <?php if (empty($characters)): ?>
    <div class="empty-state">
        <span class="empty-icon">📖</span>
        <div class="empty-title">Nog geen characters</div>
        <div class="empty-desc">Voeg characters toe via het admin panel.</div>
    </div>
    <?php else: ?>

    <div class="databook-wrap">
        <!-- SIDEBAR -->
        <aside class="db-sidebar">
            <div class="db-sidebar-header">
                <div class="db-sidebar-title">📖 Characters</div>
                <input type="text" class="db-search" id="dbSearch" placeholder="Zoeken…">
            </div>
            <div class="db-char-list" id="dbCharList">
                <?php
                $grouped = [];
                foreach ($characters as $c) {
                    $grouped[$c['role']][] = $c;
                }
                $roleOrder = ['protagonist','anti-hero','antagonist','villain','supporting'];
                foreach ($roleOrder as $role):
                    if (empty($grouped[$role])) continue;
                ?>
                <div class="db-role-sep"><?= $roleLabels[$role] ?? $role ?></div>
                <?php foreach ($grouped[$role] as $c):
                    $img = !empty($c['image']) ? 'uploads/characters/' . $c['image'] : '';
                    $hasImg = $img && file_exists(__DIR__ . '/' . $img);
                ?>
                <button class="db-tab" data-id="<?= $c['id'] ?>" data-name="<?= htmlspecialchars(strtolower($c['name'])) ?>">
                    <div class="db-tab-av">
                        <?php if ($hasImg): ?>
                            <img src="<?= htmlspecialchars($img) ?>" alt="">
                        <?php else: ?>
                            <?= strtoupper(substr($c['name'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="db-tab-info">
                        <div class="db-tab-name"><?= htmlspecialchars($c['name']) ?></div>
                        <div class="db-tab-role"><?= $roleLabels[$c['role']] ?? $c['role'] ?></div>
                    </div>
                </button>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </aside>

        <!-- MAIN PANEL -->
        <main class="db-panel" id="dbPanel">

            <!-- Default state -->
            <div class="db-empty" id="dbDefault">
                <div class="db-empty-icon">📖</div>
                <div class="db-empty-text">Kies een character links</div>
            </div>

            <!-- Character entries -->
            <?php foreach ($characters as $c):
                $img    = !empty($c['image']) ? 'uploads/characters/' . $c['image'] : '';
                $hasImg = $img && file_exists(__DIR__ . '/' . $img);
                $rels   = $allRelations[$c['id']] ?? [];
                $seas   = $allSeasons[$c['id']] ?? [];
                $roleColor   = $roleColors[$c['role']] ?? '#6b7280';
                $statusColor = $statusColors[$c['status']] ?? '#6b7280';
            ?>
            <div class="db-entry" id="db-<?= $c['id'] ?>">

                <!-- Header -->
                <div class="db-entry-header">
                    <div class="db-portrait-wrap">
                        <?php if ($hasImg): ?>
                            <img src="<?= htmlspecialchars($img) ?>" class="db-portrait" alt="<?= htmlspecialchars($c['name']) ?>">
                        <?php else: ?>
                            <div class="db-portrait-ph">⚔</div>
                        <?php endif; ?>
                    </div>
                    <div class="db-header-info">
                        <div class="db-char-name"><?= htmlspecialchars($c['name']) ?></div>
                        <?php if ($c['alias']): ?>
                        <div class="db-char-alias">"<?= htmlspecialchars($c['alias']) ?>"</div>
                        <?php endif; ?>

                        <div class="db-badges">
                            <span class="db-badge" style="color:<?= $roleColor ?>;border-color:<?= $roleColor ?>40;background:<?= $roleColor ?>15;">
                                <?= $roleLabels[$c['role']] ?? $c['role'] ?>
                            </span>
                            <span class="db-badge" style="color:<?= $statusColor ?>;border-color:<?= $statusColor ?>40;background:<?= $statusColor ?>15;">
                                <?= $statusLabels[$c['status']] ?? $c['status'] ?>
                            </span>
                        </div>

                        <?php if ($c['faction_name']): ?>
                        <div class="db-faction-tag" style="margin-bottom:14px;">
                            <div class="db-faction-dot" style="background:<?= htmlspecialchars($c['faction_color'] ?? '#7c3aed') ?>"></div>
                            <?= htmlspecialchars($c['faction_name']) ?>
                        </div>
                        <?php endif; ?>

                        <div class="db-quick-stats">
                            <?php if ($c['age']): ?>
                            <div class="db-stat-item">
                                <span class="db-stat-label">Leeftijd</span>
                                <span class="db-stat-val"><?= htmlspecialchars($c['age']) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="db-stat-item">
                                <span class="db-stat-label">Status</span>
                                <span class="db-stat-val" style="color:<?= $statusColor ?>"><?= $statusLabels[$c['status']] ?? $c['status'] ?></span>
                            </div>
                            <?php if (!empty($seas)): ?>
                            <div class="db-stat-item">
                                <span class="db-stat-label">Seizoenen</span>
                                <span class="db-stat-val"><?= count($seas) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($rels)): ?>
                            <div class="db-stat-item">
                                <span class="db-stat-label">Relaties</span>
                                <span class="db-stat-val"><?= count($rels) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Body -->
                <div class="db-body">

                    <?php if ($c['description']): ?>
                    <div class="db-section">
                        <div class="db-section-title">Over dit character</div>
                        <div class="db-parsed-desc"><?= renderDescription($c['description']) ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($c['abilities']): ?>
                    <div class="db-section">
                        <div class="db-section-title">Krachten &amp; Abilities</div>
                        <div class="db-parsed-desc"><?= renderDescription($c['abilities']) ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($seas)): ?>
                    <div class="db-section">
                        <div class="db-section-title">Verschijnt in</div>
                        <div class="db-season-pills">
                            <?php foreach ($seas as $s): ?>
                            <span class="db-season-pill">S<?= $s['number'] ?> — <?= htmlspecialchars($s['title']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($rels)): ?>
                    <div class="db-section">
                        <div class="db-section-title">Relaties</div>
                        <div class="db-relations">
                            <?php foreach ($rels as $r):
                                $rImg = !empty($r['image']) ? 'uploads/characters/' . $r['image'] : '';
                                $rHasImg = $rImg && file_exists(__DIR__ . '/' . $rImg);
                            ?>
                            <a href="character.php?id=<?= $r['id'] ?>" class="db-rel-chip">
                                <div class="db-rel-av">
                                    <?php if ($rHasImg): ?>
                                        <img src="<?= htmlspecialchars($rImg) ?>" alt="">
                                    <?php else: ?>
                                        <?= strtoupper(substr($r['name'], 0, 1)) ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="db-rel-name"><?= htmlspecialchars($r['name']) ?></div>
                                    <div class="db-rel-type"><?= $relLabels[$r['relation']] ?? $r['relation'] ?></div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($c['backstory']): ?>
                    <div class="db-section">
                        <div class="db-section-title">Backstory</div>
                        <?php if ($c['is_spoiler']): ?>
                        <div class="db-spoiler-wrap" onclick="revealSpoiler(this)">
                            <div class="db-spoiler-blur">
                                <p class="db-text"><?= nl2br(htmlspecialchars($c['backstory'])) ?></p>
                            </div>
                            <div class="db-spoiler-overlay">
                                <span>⚠️ Spoiler — klik om te onthullen</span>
                            </div>
                        </div>
                        <?php else: ?>
                        <p class="db-text"><?= nl2br(htmlspecialchars($c['backstory'])) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!$c['description'] && !$c['abilities'] && !$c['backstory']): ?>
                    <div style="text-align:center;padding:60px 0;color:var(--muted);">
                        <div style="font-size:2rem;margin-bottom:12px;">📝</div>
                        <div style="font-size:.9rem;font-weight:600;">Nog geen informatie toegevoegd</div>
                        <div style="font-size:.8rem;margin-top:4px;">Bewerk dit character in het admin panel.</div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
            <?php endforeach; ?>

        </main>
    </div>

    <?php endif; ?>
</div>

<footer class="tgj-footer"><strong>The Greatest Journey</strong></footer>

<script>
// Tab switching
const tabs  = document.querySelectorAll('.db-tab');
const panel = document.getElementById('dbPanel');

function animateBars(entry) {
    entry.querySelectorAll('.dp-stat-fill').forEach(bar => {
        bar.classList.remove('animated');
        // Force reflow so transition fires
        void bar.offsetWidth;
        bar.classList.add('animated');
    });
}

function openTab(id) {
    document.querySelectorAll('.db-entry').forEach(e => e.classList.remove('active'));
    document.querySelectorAll('.db-tab').forEach(t => t.classList.remove('active'));
    const entry = document.getElementById('db-' + id);
    if (entry) { entry.classList.add('active'); animateBars(entry); }
    document.querySelectorAll('.db-tab[data-id="'+id+'"]').forEach(t => t.classList.add('active'));
    const def = document.getElementById('dbDefault');
    if (def) def.style.display = entry ? 'none' : '';
    if (panel) panel.scrollTop = 0;
}

tabs.forEach(tab => {
    tab.addEventListener('click', () => openTab(tab.dataset.id));
});

// Auto-open first character
if (tabs.length) openTab(tabs[0].dataset.id);

// Search
document.getElementById('dbSearch')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.db-tab').forEach(tab => {
        const name = tab.dataset.name || '';
        tab.style.display = name.includes(q) ? '' : 'none';
    });
    // Show/hide separators
    document.querySelectorAll('.db-role-sep').forEach(sep => {
        let next = sep.nextElementSibling;
        let anyVisible = false;
        while (next && !next.classList.contains('db-role-sep')) {
            if (next.style.display !== 'none') anyVisible = true;
            next = next.nextElementSibling;
        }
        sep.style.display = anyVisible ? '' : 'none';
    });
});

// Spoiler reveal
function revealSpoiler(el) {
    el.classList.add('revealed');
}
</script>
</body>
</html>
