<?php
session_start();
if (!isset($_SESSION['tgj_admin'])) { header('Location: login.php'); exit(); }
require_once '../includes/connection.php';

$chars   = $conn->query("SELECT COUNT(*) FROM characters")->fetch_row()[0];
$seasons = $conn->query("SELECT COUNT(*) FROM seasons")->fetch_row()[0];
$events  = $conn->query("SELECT COUNT(*) FROM timeline_events")->fetch_row()[0];
$lore    = $conn->query("SELECT COUNT(*) FROM lore")->fetch_row()[0];
$gallery = $conn->query("SELECT COUNT(*) FROM gallery")->fetch_row()[0];

// Writing stats
$totalWords   = 0;
$totalEps     = 0;
$avgReadTime  = 0;
$longestEps   = [];
$perSeason    = [];

$epRows = $conn->query("
    SELECT e.id, e.title, e.content, e.reading_time, e.number,
           s.title AS stitle, s.number AS snum, s.id AS sid
    FROM episodes e JOIN seasons s ON s.id = e.season_id
    WHERE e.status = 'published' AND e.content IS NOT NULL AND e.content != ''
    ORDER BY s.number ASC, e.sort_order ASC, e.number ASC
")->fetch_all(MYSQLI_ASSOC);

foreach ($epRows as $er) {
    $wc = str_word_count(strip_tags($er['content']));
    $totalWords += $wc;
    $totalEps++;
    $avgReadTime += (int)$er['reading_time'];
    $sid = $er['sid'];
    if (!isset($perSeason[$sid])) $perSeason[$sid] = ['title'=>$er['stitle'],'num'=>$er['snum'],'words'=>0,'eps'=>0];
    $perSeason[$sid]['words'] += $wc;
    $perSeason[$sid]['eps']++;
    $longestEps[] = ['title'=>$er['title'],'num'=>$er['number'],'words'=>$wc,'stitle'=>$er['stitle'],'snum'=>$er['snum'],'id'=>$er['id']];
}
usort($longestEps, fn($a,$b) => $b['words'] <=> $a['words']);
$longestEps   = array_slice($longestEps, 0, 5);
$avgReadTime  = $totalEps > 0 ? round($avgReadTime / $totalEps) : 0;

// Comments count
$commentCount = 0;
try { $commentCount = $conn->query("SELECT COUNT(*) FROM episode_comments")->fetch_row()[0] ?? 0; } catch(Exception $e) {}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Admin — TGJ</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="page admin-body">
<?php include 'admin-nav.php'; ?>
<div class="admin-container">
    <h1 class="section-title">Dashboard</h1>
    <p class="section-sub">The Greatest Journey — Beheer</p>

    <div class="admin-stats">
        <div class="admin-stat-card">
            <div class="asc-icon">👥</div>
            <div class="asc-val"><?= $chars ?></div>
            <div class="asc-lbl">Characters</div>
            <a href="characters.php" class="asc-link">Beheren →</a>
        </div>
        <div class="admin-stat-card">
            <div class="asc-icon">📺</div>
            <div class="asc-val"><?= $seasons ?></div>
            <div class="asc-lbl">Seizoenen</div>
            <a href="seasons.php" class="asc-link">Beheren →</a>
        </div>
        <div class="admin-stat-card">
            <div class="asc-icon">📜</div>
            <div class="asc-val"><?= $events ?></div>
            <div class="asc-lbl">Timeline events</div>
            <a href="timeline.php" class="asc-link">Beheren →</a>
        </div>
        <div class="admin-stat-card">
            <div class="asc-icon">🌍</div>
            <div class="asc-val"><?= $lore ?></div>
            <div class="asc-lbl">Lore entries</div>
            <a href="lore.php" class="asc-link">Beheren →</a>
        </div>
        <div class="admin-stat-card">
            <div class="asc-icon">🎨</div>
            <div class="asc-val"><?= $gallery ?></div>
            <div class="asc-lbl">Gallery items</div>
            <a href="gallery.php" class="asc-link">Beheren →</a>
        </div>
    </div>

    <div class="gold-divider"></div>

    <!-- Writing Stats -->
    <h2 style="font-size:1rem;font-weight:800;margin-bottom:16px;">✍️ Schrijfstatistieken</h2>
    <div class="admin-stats" style="margin-bottom:28px;">
        <div class="admin-stat-card">
            <div class="asc-icon">📝</div>
            <div class="asc-val"><?= number_format($totalWords, 0, ',', '.') ?></div>
            <div class="asc-lbl">Totaal woorden</div>
        </div>
        <div class="admin-stat-card">
            <div class="asc-icon">📺</div>
            <div class="asc-val"><?= $totalEps ?></div>
            <div class="asc-lbl">Gepubliceerde afl.</div>
        </div>
        <div class="admin-stat-card">
            <div class="asc-icon">⏱</div>
            <div class="asc-val"><?= $avgReadTime ?> min</div>
            <div class="asc-lbl">Gem. leestijd</div>
        </div>
        <div class="admin-stat-card">
            <div class="asc-icon">💬</div>
            <div class="asc-val"><?= $commentCount ?></div>
            <div class="asc-lbl">Reacties</div>
        </div>
    </div>

    <?php if (!empty($perSeason)): ?>
    <h3 style="font-size:.8rem;font-weight:800;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;margin-bottom:12px;">Woorden per seizoen</h3>
    <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:28px;">
    <?php foreach ($perSeason as $ps): ?>
        <?php $pct = $totalWords > 0 ? round(($ps['words']/$totalWords)*100) : 0; ?>
        <div style="background:var(--card);border:1px solid var(--border);border-radius:10px;padding:12px 16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <span style="font-weight:700;font-size:.85rem;">S<?= $ps['num'] ?> — <?= htmlspecialchars($ps['title']) ?></span>
                <span style="font-size:.75rem;color:var(--muted);"><?= number_format($ps['words'],0,',','.') ?> woorden · <?= $ps['eps'] ?> afl.</span>
            </div>
            <div style="height:6px;background:var(--border);border-radius:100px;overflow:hidden;">
                <div style="width:<?= $pct ?>%;height:100%;background:linear-gradient(90deg,var(--primary),#a78bfa);border-radius:100px;"></div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($longestEps)): ?>
    <h3 style="font-size:.8rem;font-weight:800;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;margin-bottom:12px;">Langste afleveringen</h3>
    <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:28px;">
    <?php foreach ($longestEps as $i => $le): ?>
        <div style="display:flex;align-items:center;gap:14px;padding:10px 16px;<?= $i > 0 ? 'border-top:1px solid var(--border);' : '' ?>">
            <span style="font-size:.75rem;font-weight:800;color:var(--muted);width:18px;text-align:center;"><?= $i+1 ?></span>
            <div style="flex:1;min-width:0;">
                <div style="font-weight:700;font-size:.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($le['title']) ?></div>
                <div style="font-size:.7rem;color:var(--muted);">S<?= $le['snum'] ?> · Afl. <?= $le['num'] ?></div>
            </div>
            <span style="font-size:.75rem;font-weight:700;color:var(--primary);"><?= number_format($le['words'],0,',','.') ?> w</span>
            <a href="episodes.php?edit=<?= $le['id'] ?>" class="btn-edit" style="font-size:.7rem;">Bewerken</a>
        </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <a href="../index.php" class="btn btn-outline btn-sm" target="_blank">🌐 Bekijk site →</a>
        <a href="logout.php" class="btn btn-sm" style="background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3);">🚪 Uitloggen</a>
    </div>
</div>
</body>
</html>
