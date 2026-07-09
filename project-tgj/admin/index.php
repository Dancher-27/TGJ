<?php
session_start();
if (!isset($_SESSION['tgj_admin'])) { header('Location: login.php'); exit(); }
require_once '../includes/connection.php';

$chars   = $conn->query("SELECT COUNT(*) FROM characters")->fetch_row()[0];
$seasons = $conn->query("SELECT COUNT(*) FROM seasons")->fetch_row()[0];
$events  = $conn->query("SELECT COUNT(*) FROM timeline_events")->fetch_row()[0];
$lore    = $conn->query("SELECT COUNT(*) FROM lore")->fetch_row()[0];
$gallery = $conn->query("SELECT COUNT(*) FROM gallery")->fetch_row()[0];
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

    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <a href="../index.php" class="btn btn-outline btn-sm" target="_blank">🌐 Bekijk site →</a>
        <a href="logout.php" class="btn btn-sm" style="background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3);">🚪 Uitloggen</a>
    </div>
</div>
</body>
</html>
