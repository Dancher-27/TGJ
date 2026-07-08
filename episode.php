<?php
require_once 'includes/connection.php';

// Auto-migration
$conn->query("ALTER TABLE episodes ADD COLUMN IF NOT EXISTS content LONGTEXT AFTER description");
$conn->query("ALTER TABLE episodes ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0 AFTER release_date");
$conn->query("ALTER TABLE episodes ADD COLUMN IF NOT EXISTS status ENUM('draft','published','upcoming') NOT NULL DEFAULT 'published' AFTER sort_order");
$conn->query("ALTER TABLE episodes ADD COLUMN IF NOT EXISTS reading_time INT DEFAULT 0 AFTER status");
$conn->query("CREATE TABLE IF NOT EXISTS episode_covers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    episode_id INT NOT NULL,
    image VARCHAR(255) NOT NULL,
    position ENUM('start','end') NOT NULL DEFAULT 'start',
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (episode_id) REFERENCES episodes(id) ON DELETE CASCADE
)");

// Handle rating submission (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rate') {
    header('Content-Type: application/json');
    $conn->query("CREATE TABLE IF NOT EXISTS episode_ratings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        episode_id INT NOT NULL,
        rating TINYINT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (episode_id)
    )");
    $epId   = (int)($_POST['ep_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    if ($epId && $rating >= 1 && $rating <= 10) {
        $stmt = $conn->prepare("INSERT INTO episode_ratings (episode_id, rating) VALUES (?,?)");
        $stmt->bind_param("ii", $epId, $rating);
        $stmt->execute();
        $row = $conn->query("SELECT ROUND(AVG(rating),1) AS avg, COUNT(*) AS cnt FROM episode_ratings WHERE episode_id=$epId")->fetch_assoc();
        echo json_encode(['ok' => true, 'avg' => (float)$row['avg'], 'count' => (int)$row['cnt']]);
    } else {
        echo json_encode(['ok' => false]);
    }
    exit();
}

// Handle comment submission (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'comment') {
    header('Content-Type: application/json');
    $conn->query("CREATE TABLE IF NOT EXISTS episode_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        episode_id INT NOT NULL,
        name VARCHAR(80) NOT NULL,
        body TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $epId = (int)($_POST['ep_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $body = trim($_POST['body'] ?? '');
    if ($epId && strlen($name) >= 2 && strlen($body) >= 3) {
        $name = mb_substr($name, 0, 80);
        $body = mb_substr($body, 0, 2000);
        $stmt = $conn->prepare("INSERT INTO episode_comments (episode_id, name, body) VALUES (?,?,?)");
        $stmt->bind_param("iss", $epId, $name, $body);
        $stmt->execute();
        $cid = $conn->insert_id;
        $row = $conn->query("SELECT * FROM episode_comments WHERE id=$cid")->fetch_assoc();
        echo json_encode(['ok' => true, 'comment' => [
            'id'         => (int)$row['id'],
            'name'       => htmlspecialchars($row['name']),
            'body'       => nl2br(htmlspecialchars($row['body'])),
            'created_at' => date('d M Y H:i', strtotime($row['created_at'])),
        ]]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Vul je naam (min. 2 tekens) en een reactie (min. 3 tekens) in.']);
    }
    exit();
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: seasons.php'); exit(); }

$ep = $conn->query("
    SELECT e.*, s.title AS season_title, s.number AS season_number, s.id AS season_id
    FROM episodes e JOIN seasons s ON s.id = e.season_id
    WHERE e.id = $id AND e.status != 'draft'
")->fetch_assoc();

if (!$ep) { header('Location: seasons.php'); exit(); }

// All published/upcoming episodes in season for navigation
$allEps = $conn->query("
    SELECT id, number, title, description, release_date, sort_order, content, status, reading_time
    FROM episodes
    WHERE season_id = {$ep['season_id']} AND status != 'draft'
    ORDER BY sort_order ASC, number ASC
")->fetch_all(MYSQLI_ASSOC);

$currentIdx = 0;
foreach ($allEps as $i => $e) {
    if ((int)$e['id'] === $id) { $currentIdx = $i; break; }
}
$total = count($allEps);

// Fetch covers + ratings for all episodes in this season
$epCovers  = [];
$epRatings = [];
$conn->query("CREATE TABLE IF NOT EXISTS episode_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    episode_id INT NOT NULL,
    rating TINYINT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (episode_id)
)");
if (!empty($allEps)) {
    $inClause = implode(',', array_map('intval', array_column($allEps, 'id')));
    $coverRows = $conn->query("SELECT * FROM episode_covers WHERE episode_id IN ($inClause) ORDER BY sort_order ASC")->fetch_all(MYSQLI_ASSOC);
    foreach ($coverRows as $cr) {
        $eid = (int)$cr['episode_id'];
        $epCovers[$eid][$cr['position']][] = $cr;
    }
    $ratingRows = $conn->query("SELECT episode_id, ROUND(AVG(rating),1) AS avg, COUNT(*) AS cnt FROM episode_ratings WHERE episode_id IN ($inClause) GROUP BY episode_id")->fetch_all(MYSQLI_ASSOC);
    foreach ($ratingRows as $rr) {
        $epRatings[(int)$rr['episode_id']] = ['avg' => (float)$rr['avg'], 'count' => (int)$rr['cnt']];
    }
}

// Normalize raw content so AI-formatted episodes render identically to hand-formatted ones
function normalizeContent(string $raw): string {
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);
    $lines = explode("\n", $raw);
    $out = [];
    foreach ($lines as $line) {
        $line = rtrim($line);
        // (# headings are handled by the parser with their own level — do not convert here)
        // Strip blockquote markers: "> text" → "text"
        $line = preg_replace('/^>\s*/', '', $line);
        // Remove horizontal rules (---, ___, ***)
        if (preg_match('/^[-_*]{3,}\s*$/', $line)) { $line = ''; }
        // Strip markdown bold/italic from speaker names: **Name:** or *Name:* → Name:
        $line = preg_replace('/^\*\*([^*\n:]+(?:\s*\([^)]*\))?)\*\*:\s*/', '$1: ', $line);
        $line = preg_replace('/^\*([^*\n:]+(?:\s*\([^)]*\))?)\*:\s*/', '$1: ', $line);
        $out[] = $line;
    }
    // Ensure every content line is its own paragraph block (blank line between consecutive lines).
    // Exception: a speaker-label line (e.g. "Kimmie:" alone) stays attached to the dialogue below it.
    $speakerPat = '/^' . EP_NAME_PAT . '(?:\s*\([^)]*\))?\s*:\s*$/u';
    $spaced = [];
    $n = count($out);
    for ($i = 0; $i < $n; $i++) {
        $spaced[] = $out[$i];
        $curr = $out[$i];
        $next = $out[$i + 1] ?? null;
        if ($curr !== '' && $next !== null && $next !== '') {
            if (!preg_match($speakerPat, $curr)) {
                $spaced[] = '';
            }
        }
    }
    // Collapse 3+ consecutive blank lines to 1 blank line
    return preg_replace('/\n{3,}/', "\n\n", implode("\n", $spaced));
}

// Parse content into chapters + paragraphs
// Lines starting with ## are chapter headings
function parseEpisodeContent(string $raw): array {
    $raw = normalizeContent($raw);
    $chapters = [];
    $currentChapter = ['id' => '', 'title' => '', 'paragraphs' => []];
    $chapterIndex   = 0;

    $lines = explode("\n", $raw);
    $buffer = [];

    $flush = function() use (&$buffer, &$currentChapter) {
        $block = trim(implode("\n", $buffer));
        if ($block !== '') {
            // Split block by double newlines into paragraphs
            $paras = preg_split('/\n{2,}/', $block);
            foreach ($paras as $p) {
                $p = trim($p);
                if ($p !== '') $currentChapter['paragraphs'][] = $p;
            }
        }
        $buffer = [];
    };

    foreach ($lines as $line) {
        if (preg_match('/^(#{1,2})\s*(.+)$/', $line, $m)) {
            $flush();
            if ($chapterIndex > 0 || !empty($currentChapter['paragraphs'])) {
                $chapters[] = $currentChapter;
            }
            $chapterIndex++;
            $slug = 'ch-' . $chapterIndex;
            $currentChapter = ['id' => $slug, 'title' => trim($m[2]), 'level' => strlen($m[1]), 'paragraphs' => []];
        } else {
            $buffer[] = $line;
        }
    }
    $flush();
    $chapters[] = $currentChapter;

    // Keep chapters that have a title (even if empty paragraphs) OR have content
    return array_values(array_filter($chapters, fn($c) => $c['title'] !== '' || !empty($c['paragraphs'])));
}

// Render a single paragraph, highlighting speaker lines (e.g. "Nyssa:", "Nezzie (grinning):")
// Shared speaker regex pieces
// Name: uppercase start, letters/spaces/hyphens/apostrophes, max ~35 chars before colon
define('EP_NAME_PAT', '[A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞŸ][A-Za-zÀ-ÿ\'\-]{0,20}(?:\s[A-Za-zÀ-ÿ\'\-]{1,20}){0,2}');

// Apply inline formatting: **bold** or *bold* → <strong class="ep-bold">
function applyInline(string $html): string {
    // Double asterisks first (so **x** doesn't get eaten by single-asterisk rule)
    $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong class="ep-bold">$1</strong>', $html);
    // Single asterisks
    $html = preg_replace('/\*([^*\n]+)\*/', '<strong class="ep-bold">$1</strong>', $html);
    return $html;
}

function renderLine(string $line): string {
    $t = trim($line);

    // Format A — "Name (action):" alone on line
    if (preg_match('/^(' . EP_NAME_PAT . ')((?:\s*\([^)]*\))?)\s*:\s*$/u', $t, $m)) {
        $name   = htmlspecialchars(trim($m[1]));
        $action = htmlspecialchars(trim($m[2]));
        return '<span class="ep-speaker">' . $name . '</span>'
             . ($action ? '<span class="ep-speaker-action"> ' . $action . '</span>' : '')
             . ':';
    }

    // Format B — "Name: dialogue" or "Name: (action) dialogue" inline
    if (preg_match('/^(' . EP_NAME_PAT . '):\s*((?:\([^)]*\)\s*)?)(.+)/u', $t, $m)) {
        $name   = htmlspecialchars(trim($m[1]));
        $action = htmlspecialchars(rtrim($m[2]));
        $rest   = applyInline(htmlspecialchars($m[3]));
        return '<span class="ep-speaker">' . $name . ':</span>'
             . ($action ? ' <span class="ep-speaker-action">' . $action . '</span>' : '')
             . ' ' . $rest;
    }

    return applyInline(htmlspecialchars($line));
}

function renderParagraph(string $para, bool &$isFirst): string {
    $lines   = explode("\n", $para);
    $result  = '';
    $pending = [];   // lines accumulating into a <p>

    $flushPending = function() use (&$pending, &$isFirst, &$result) {
        if (empty($pending)) return;
        $rendered = array_map('renderLine', $pending);
        $cls = $isFirst ? ' class="ep-first-para"' : '';
        $result .= '<p' . $cls . '>' . implode('<br>', $rendered) . '</p>';
        $isFirst = false;
        $pending = [];
    };

    foreach ($lines as $line) {
        $scene = tryRenderSceneMarker($line);
        if ($scene !== null) {
            $flushPending();
            $result .= $scene;
        } else {
            $pending[] = $line;
        }
    }
    $flushPending();
    return $result;
}

// Detect and render [scene: filename.jpg] or [scene: filename.jpg | Caption] markers
function tryRenderSceneMarker(string $para): ?string {
    if (!preg_match('/^\[scene:\s*([^\|\]]+?)(?:\|([^\]]*))?\]\s*$/i', trim($para), $m)) {
        return null;
    }
    $filename = trim($m[1]);
    $caption  = isset($m[2]) ? trim($m[2]) : '';
    $src = 'uploads/scenes/' . htmlspecialchars($filename);
    $alt = $caption ? htmlspecialchars($caption) : 'Scene afbeelding';
    $html  = '<div class="ep-scene-wrap">';
    $html .= '<div class="ep-scene-frame">';
    $html .= '<img class="ep-scene-img" src="' . $src . '" alt="' . $alt . '" loading="lazy">';
    $html .= '<div class="ep-scene-vignette"></div>';
    $html .= '</div>';
    if ($caption) {
        $html .= '<p class="ep-scene-caption">' . htmlspecialchars($caption) . '</p>';
    }
    $html .= '</div>';
    return $html;
}

$chapters = parseEpisodeContent($ep['content'] ?? '');
$hasChapters = count(array_filter($chapters, fn($c) => $c['title'] !== '' && ($c['level'] ?? 2) === 2)) > 0;

// Load comments for this episode
$conn->query("CREATE TABLE IF NOT EXISTS episode_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    episode_id INT NOT NULL,
    name VARCHAR(80) NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$comments = $conn->query("SELECT * FROM episode_comments WHERE episode_id=$id ORDER BY created_at ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($ep['title']) ?> — The Greatest Journey</title>
    <?php include 'includes/fonts.php'; ?>
    <link rel="stylesheet" href="styles.css">
    <style>
    /* ════════════════════════════════════════════
       EPISODE READER
    ════════════════════════════════════════════ */
    .ep-reader-page {
        background: #0f0e17;
        min-height: 100vh;
        color: #e8e4f0;
    }

    /* ── TOP BAR ── */
    .ep-topbar {
        position: fixed; top: 0; left: 0; right: 0; z-index: 200;
        height: 56px;
        background: rgba(15,14,23,.94);
        backdrop-filter: blur(14px);
        border-bottom: 1px solid rgba(124,58,237,.2);
        display: flex; align-items: center;
        padding: 0 20px; gap: 0;
    }
    .ep-topbar-back {
        display: flex; align-items: center; gap: 6px;
        color: rgba(255,255,255,.5); text-decoration: none;
        font-size: .78rem; font-weight: 600;
        padding: 6px 16px 6px 6px;
        border-right: 1px solid rgba(255,255,255,.08);
        margin-right: 16px; flex-shrink: 0;
        transition: color .15s;
    }
    .ep-topbar-back:hover { color: #fff; }
    .ep-topbar-crumb {
        flex: 1; display: flex; align-items: center; gap: 7px;
        font-size: .74rem; color: rgba(255,255,255,.35);
        overflow: hidden; white-space: nowrap;
    }
    .ep-topbar-crumb strong { color: rgba(255,255,255,.7); }
    .ep-topbar-right {
        display: flex; align-items: center; gap: 14px; flex-shrink: 0; margin-left: 12px;
    }
    .ep-toc-toggle {
        background: rgba(124,58,237,.18); border: 1px solid rgba(124,58,237,.35);
        color: #a78bfa; font-size: .72rem; font-weight: 700;
        padding: 5px 12px; border-radius: 8px; cursor: pointer;
        transition: all .15s; font-family: inherit;
        display: flex; align-items: center; gap: 6px;
    }
    .ep-toc-toggle:hover { background: rgba(124,58,237,.32); color: #fff; }
    .ep-toc-toggle.hidden { display: none; }
    .ep-dots-wrap { display: flex; gap: 3px; }
    .ep-dot {
        width: 6px; height: 6px; border-radius: 50%;
        background: rgba(255,255,255,.15); cursor: pointer; transition: all .15s;
    }
    .ep-dot.active { background: #7c3aed; transform: scale(1.4); }
    .ep-dot:hover  { background: rgba(255,255,255,.45); }
    .ep-counter    { font-size: .7rem; color: rgba(255,255,255,.35); font-weight: 600; }

    /* ── PROGRESS BAR ── */
    .ep-progress-bar {
        position: fixed; top: 56px; left: 0; right: 0; z-index: 199;
        height: 3px; background: rgba(255,255,255,.05);
    }
    .ep-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #7c3aed, #a78bfa);
        width: 0%; transition: width .08s linear;
    }

    /* ── LAYOUT: TOC + CONTENT ── */
    .ep-layout {
        display: grid;
        grid-template-columns: 260px 1fr;
        min-height: 100vh;
        padding-top: 59px; /* topbar + progress */
    }
    .ep-layout.no-toc { grid-template-columns: 1fr; }

    /* ── TOC SIDEBAR ── */
    .ep-toc {
        position: sticky; top: 59px;
        height: calc(100vh - 59px);
        overflow-y: auto;
        padding: 32px 0 100px;
        border-right: 1px solid rgba(255,255,255,.06);
        background: rgba(15,14,23,.6);
    }
    .ep-toc::-webkit-scrollbar { width: 3px; }
    .ep-toc::-webkit-scrollbar-thumb { background: rgba(124,58,237,.4); border-radius: 2px; }
    .ep-toc-label {
        font-size: .6rem; font-weight: 800; letter-spacing: .16em;
        text-transform: uppercase; color: rgba(255,255,255,.25);
        padding: 0 20px; margin-bottom: 14px;
    }
    .ep-toc-item {
        display: block; padding: 7px 20px;
        font-size: .78rem; color: rgba(255,255,255,.4);
        text-decoration: none; cursor: pointer;
        border-left: 2px solid transparent;
        transition: all .15s; line-height: 1.4;
    }
    .ep-toc-item:hover  { color: rgba(255,255,255,.8); background: rgba(124,58,237,.08); }
    .ep-toc-item.active { color: #a78bfa; border-left-color: #7c3aed; background: rgba(124,58,237,.12); font-weight: 600; }
    .ep-toc-sep { height: 1px; background: rgba(255,255,255,.05); margin: 10px 20px; }

    /* Mobile TOC overlay */
    .ep-toc-overlay {
        display: none;
        position: fixed; inset: 0; z-index: 190;
        background: rgba(0,0,0,.7);
        backdrop-filter: blur(4px);
    }
    .ep-toc-overlay.open { display: block; }
    .ep-toc-mobile {
        position: fixed; top: 59px; left: 0; bottom: 0; width: 280px; z-index: 191;
        background: #1a1828;
        border-right: 1px solid rgba(124,58,237,.25);
        overflow-y: auto; padding: 24px 0 80px;
        transform: translateX(-100%); transition: transform .3s ease;
    }
    .ep-toc-overlay.open .ep-toc-mobile { transform: translateX(0); }

    /* ── STAGE / VIEWPORT ── */
    .ep-stage { padding-bottom: 100px; overflow: hidden; }
    .ep-viewport {
        will-change: transform, opacity;
        transition: transform .38s cubic-bezier(.4,0,.2,1), opacity .35s ease;
    }
    .ep-viewport.slide-out-left  { transform: translateX(-70px); opacity: 0; pointer-events: none; }
    .ep-viewport.slide-out-right { transform: translateX(70px);  opacity: 0; pointer-events: none; }
    .ep-viewport.slide-in-left   { transform: translateX(70px);  opacity: 0; transition: none; }
    .ep-viewport.slide-in-right  { transform: translateX(-70px); opacity: 0; transition: none; }
    .ep-viewport.slide-active    { transform: translateX(0);     opacity: 1; }

    /* ── EPISODE HEADER ── */
    .ep-header { max-width: 720px; margin: 0 auto; padding: 52px 40px 32px; }
    .ep-season-pill {
        display: inline-flex; align-items: center; gap: 6px;
        background: rgba(124,58,237,.18); border: 1px solid rgba(124,58,237,.3);
        color: #a78bfa; font-size: .66rem; font-weight: 800;
        letter-spacing: .1em; text-transform: uppercase;
        padding: 5px 14px; border-radius: 100px; margin-bottom: 18px;
    }
    .ep-num-label {
        font-size: .7rem; font-weight: 800; letter-spacing: .2em;
        color: rgba(255,255,255,.28); text-transform: uppercase; margin-bottom: 8px;
    }
    .ep-main-title {
        font-family: var(--font-display);
        font-size: clamp(1.8rem, 5vw, 2.8rem);
        font-weight: 700; color: #fff; line-height: 1.1; margin-bottom: 18px;
    }
    .ep-teaser {
        font-size: .98rem; color: rgba(255,255,255,.5);
        line-height: 1.85; font-style: italic;
        border-left: 3px solid rgba(124,58,237,.45);
        padding-left: 16px; margin-bottom: 6px;
    }
    .ep-divider { border: none; border-top: 1px solid rgba(255,255,255,.07); margin: 28px 0; }
    .ep-meta-row {
        display: flex; gap: 20px; flex-wrap: wrap;
        font-size: .71rem; color: rgba(255,255,255,.3); font-weight: 600;
    }
    .ep-meta-row span { display: flex; align-items: center; gap: 5px; }

    /* ── CHAPTER HEADING ── */
    .ep-chapter-heading {
        font-family: var(--font-display);
        font-size: 1.25rem; font-weight: 700;
        color: #a78bfa; margin: 2.4em 0 1em;
        padding-bottom: .6em;
        border-bottom: 1px solid rgba(124,58,237,.25);
        scroll-margin-top: 80px;
    }
    .ep-chapter-heading::before {
        content: '§'; margin-right: .5em;
        color: rgba(124,58,237,.4); font-size: .85em;
    }

    /* ── SPEAKER LINES ── */
    .ep-speaker {
        font-family: var(--font-display);
        font-weight: 700;
        font-size: .92em;
        color: #a78bfa;
        letter-spacing: .03em;
    }
    .ep-speaker-action {
        font-style: italic;
        font-size: .82em;
        color: rgba(167,139,250,.55);
        font-family: var(--font-body);
        font-weight: 400;
    }
    .ep-bold {
        font-weight: 800;
        color: #fff;
        text-shadow: 0 0 18px rgba(167,139,250,.35);
    }

    /* ── CONTENT BODY ── */
    .ep-content {
        max-width: 720px; margin: 0 auto;
        padding: 0 40px 60px;
        font-size: 1.05rem; line-height: 1.95;
        color: rgba(232,228,240,.85);
    }
    .ep-content p { margin-bottom: 1.5em; }
    /* Drop cap only on very first paragraph (no chapter heading before it) */
    .ep-content > p:first-child::first-letter {
        font-family: var(--font-display);
        font-size: 3.2em; font-weight: 900;
        color: #a78bfa; float: left;
        line-height: .8; margin: .08em .12em 0 0;
        text-shadow: 0 0 28px rgba(167,139,250,.55);
    }
    .ep-no-content {
        max-width: 720px; margin: 0 auto; padding: 60px 40px;
        text-align: center; color: rgba(255,255,255,.25);
    }
    .ep-no-content .icon { font-size: 3rem; margin-bottom: 14px; }

    /* ── READ COMPLETE BANNER ── */
    .ep-read-banner {
        max-width: 720px; margin: 0 auto 20px;
        padding: 0 40px;
        display: none;
    }
    .ep-read-banner.show { display: block; }
    .ep-read-inner {
        background: linear-gradient(135deg, rgba(34,197,94,.12), rgba(124,58,237,.12));
        border: 1px solid rgba(34,197,94,.25);
        border-radius: 14px; padding: 16px 22px;
        display: flex; align-items: center; gap: 14px;
        font-size: .85rem; color: rgba(255,255,255,.7);
    }
    .ep-read-icon { font-size: 1.5rem; flex-shrink: 0; }
    .ep-read-text strong { color: #4ade80; display: block; font-size: .9rem; margin-bottom: 2px; }

    /* ── BOTTOM NAV ── */
    .ep-nav {
        position: fixed; bottom: 0; left: 0; right: 0; z-index: 200;
        height: 70px;
        background: rgba(15,14,23,.96);
        backdrop-filter: blur(16px);
        border-top: 1px solid rgba(124,58,237,.18);
        display: flex; align-items: center;
        padding: 0 20px; gap: 12px;
    }
    .ep-nav-btn {
        display: flex; align-items: center; gap: 8px;
        background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.09);
        border-radius: 11px; padding: 9px 16px;
        color: rgba(255,255,255,.65); font-size: .78rem; font-weight: 700;
        cursor: pointer; text-decoration: none;
        transition: all .18s; font-family: inherit;
        min-width: 110px;
    }
    .ep-nav-btn:hover:not(:disabled):not(.ep-nav-disabled) {
        background: rgba(124,58,237,.2); border-color: rgba(124,58,237,.45);
        color: #fff; transform: translateY(-1px);
    }
    .ep-nav-btn:disabled, .ep-nav-disabled {
        opacity: .22; cursor: not-allowed; pointer-events: none;
    }
    .ep-nav-btn.next { margin-left: auto; background: rgba(124,58,237,.14); border-color: rgba(124,58,237,.3); color: #a78bfa; }
    .ep-nav-btn.next:hover:not(:disabled) { background: rgba(124,58,237,.32); border-color: #7c3aed; color: #fff; }
    .ep-nav-center { flex: 1; text-align: center; }
    .ep-nav-center-title { font-size: .72rem; font-weight: 700; color: rgba(255,255,255,.65); }
    .ep-nav-center-pos   { font-size: .63rem; color: rgba(255,255,255,.28); font-weight: 600; margin-top: 2px; }
    .ep-nav-label { font-size: .57rem; text-transform: uppercase; letter-spacing: .06em; color: rgba(255,255,255,.28); }
    .ep-nav-name  { font-size: .75rem; max-width: 110px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* ── SCENE ILLUSTRATION (Light Novel style) ── */
    @keyframes ep-scene-in {
        from { opacity: 0; transform: translateY(22px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .ep-scene-wrap {
        margin: 3em 0;
        padding: 0;
        opacity: 0;
        animation: ep-scene-in .75s ease forwards;
    }
    /* Stagger if multiple scenes: each one reveals slightly after the one before */
    .ep-scene-wrap:nth-of-type(1) { animation-delay: .1s; }
    .ep-scene-wrap:nth-of-type(2) { animation-delay: .2s; }
    .ep-scene-wrap:nth-of-type(3) { animation-delay: .3s; }
    .ep-scene-frame {
        position: relative;
        border: 1px solid rgba(124,58,237,.35);
        border-radius: 3px;
        overflow: hidden;
        box-shadow: 0 14px 52px rgba(0,0,0,.7), 0 0 0 1px rgba(124,58,237,.08);
    }
    /* Corner ornaments */
    .ep-scene-frame::before, .ep-scene-frame::after {
        content: '';
        position: absolute;
        width: 22px; height: 22px;
        border-style: solid;
        border-color: rgba(167,139,250,.55);
        z-index: 3; pointer-events: none;
    }
    .ep-scene-frame::before { top: 10px; left: 10px; border-width: 2px 0 0 2px; }
    .ep-scene-frame::after  { bottom: 10px; right: 10px; border-width: 0 2px 2px 0; }
    .ep-scene-img {
        display: block; width: 100%; height: auto;
        background: #080710;
    }
    .ep-scene-vignette {
        position: absolute; inset: 0; z-index: 1; pointer-events: none;
        background: radial-gradient(ellipse at center, transparent 55%, rgba(15,14,23,.6) 100%);
    }
    .ep-scene-caption {
        text-align: center; font-style: italic;
        font-size: .77rem; color: rgba(167,139,250,.52);
        padding: 10px 0 0; letter-spacing: .04em; line-height: 1.6;
    }

    /* ── READER SETTINGS PANEL ── */
    .ep-settings-btn {
        background: rgba(124,58,237,.18); border: 1px solid rgba(124,58,237,.3);
        color: #a78bfa; font-size: .8rem; padding: 5px 10px;
        border-radius: 8px; cursor: pointer; font-family: inherit;
        transition: all .15s; display: flex; align-items: center; gap: 5px;
    }
    .ep-settings-btn:hover { background: rgba(124,58,237,.32); color: #fff; }
    .ep-settings-panel {
        position: fixed; top: 59px; right: 0; z-index: 190;
        width: 270px; background: #1a1828;
        border-left: 1px solid rgba(124,58,237,.25);
        border-bottom: 1px solid rgba(124,58,237,.25);
        border-radius: 0 0 0 14px;
        padding: 18px 20px; box-shadow: -6px 6px 32px rgba(0,0,0,.5);
        transform: translateX(100%); transition: transform .3s ease;
    }
    .ep-settings-panel.open { transform: translateX(0); }
    .ep-settings-label { font-size: .58rem; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: rgba(255,255,255,.3); margin-bottom: 10px; }
    .ep-settings-row { margin-bottom: 16px; }
    .ep-settings-opts { display: flex; gap: 6px; flex-wrap: wrap; }
    .ep-settings-opt {
        background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.1);
        color: rgba(255,255,255,.55); font-size: .72rem; font-weight: 600;
        padding: 5px 12px; border-radius: 8px; cursor: pointer;
        transition: all .15s; font-family: inherit;
    }
    .ep-settings-opt:hover { background: rgba(124,58,237,.2); color: #fff; }
    .ep-settings-opt.active { background: rgba(124,58,237,.35); border-color: #7c3aed; color: #fff; }
    /* Theme overrides */
    .ep-theme-sepia  { background: #1c1510 !important; --ep-text: rgba(235,218,188,.85); }
    .ep-theme-sepia .ep-content { color: rgba(235,218,188,.85) !important; }
    .ep-theme-sepia .ep-main-title { color: #e8d5b0 !important; }
    .ep-theme-light  { background: #f8f7f0 !important; }
    .ep-theme-light .ep-content { color: #2d2a22 !important; }
    .ep-theme-light .ep-main-title { color: #1a1814 !important; }
    .ep-theme-light .ep-topbar, .ep-theme-light .ep-nav { background: rgba(245,243,235,.96) !important; }
    .ep-theme-light .ep-topbar-back, .ep-theme-light .ep-topbar-crumb, .ep-theme-light .ep-counter { color: #555 !important; }
    .ep-theme-light .ep-chapter-heading { color: #7c3aed !important; }
    .ep-theme-night { background: #07060f !important; }
    .ep-theme-night .ep-content { color: rgba(180,170,210,.6) !important; }
    .ep-theme-night .ep-main-title { color: rgba(190,180,220,.75) !important; }
    .ep-theme-night .ep-topbar { background: rgba(4,4,10,.98) !important; }
    .ep-theme-night .ep-nav { background: rgba(4,4,10,.96) !important; }
    .ep-theme-night .ep-chapter-heading { color: #7c5fd4 !important; }
    .ep-theme-night .ep-teaser { color: rgba(160,150,195,.5) !important; }

    /* ── RELATED EPISODES ── */
    .ep-related {
        max-width: 720px; margin: 0 auto;
        padding: 0 40px 48px;
    }
    .ep-related:empty { display: none; }
    .ep-rel-section { margin-bottom: 22px; }
    .ep-rel-label {
        font-size: .6rem; font-weight: 800; letter-spacing: .14em;
        text-transform: uppercase; color: rgba(255,255,255,.25);
        margin-bottom: 10px;
    }
    .ep-rel-row {
        display: flex; gap: 10px;
        overflow-x: auto; padding-bottom: 6px;
        scrollbar-width: none;
    }
    .ep-rel-row::-webkit-scrollbar { display: none; }
    .ep-rel-card {
        flex-shrink: 0; width: 160px;
        background: var(--card); border: 1px solid var(--border);
        border-radius: 12px; padding: 13px 15px;
        text-align: left; cursor: pointer;
        transition: all .15s; font-family: inherit;
        color: var(--text);
    }
    .ep-rel-card:hover { background: rgba(124,58,237,.12); border-color: var(--primary); transform: translateY(-2px); }
    .ep-rel-num {
        font-size: .6rem; font-weight: 800; letter-spacing: .1em;
        text-transform: uppercase; color: var(--primary); margin-bottom: 5px;
    }
    .ep-rel-title {
        font-size: .8rem; font-weight: 700; line-height: 1.3;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .ep-rel-rating {
        font-size: .68rem; font-weight: 800; margin-top: 7px;
        color: rgba(255,255,255,.35);
    }
    @media (max-width: 600px) {
        .ep-related { padding: 0 16px 40px; }
        .ep-rel-card { width: 138px; }
    }

    /* ── SCROLL RESTORE TOAST ── */
    .ep-restore-toast {
        position: fixed; bottom: 82px; left: 50%; transform: translateX(-50%);
        background: rgba(124,58,237,.92); color: #fff;
        border-radius: 100px; padding: 10px 22px;
        font-size: .8rem; font-weight: 700; z-index: 300;
        box-shadow: 0 4px 20px rgba(0,0,0,.4);
        cursor: pointer; backdrop-filter: blur(8px);
        animation: toast-in .3s ease;
        display: none;
    }
    @keyframes toast-in { from { opacity:0; transform:translateX(-50%) translateY(10px); } to { opacity:1; transform:translateX(-50%) translateY(0); } }
    .ep-restore-toast.show { display: block; }

    /* ── RATING ── */
    .ep-rating-section {
        max-width: 720px; margin: 0 auto 8px;
        padding: 20px 40px 24px;
        border-top: 1px solid rgba(255,255,255,.06);
    }
    .ep-rating-label {
        font-size: .6rem; font-weight: 800; letter-spacing: .14em;
        text-transform: uppercase; color: rgba(255,255,255,.25);
        margin-bottom: 12px;
    }
    .ep-nums { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 10px; }
    .ep-num-btn {
        width: 38px; height: 38px; border-radius: 8px;
        border: 1.5px solid rgba(255,255,255,.12);
        background: rgba(255,255,255,.05);
        color: rgba(255,255,255,.3);
        font-size: .82rem; font-weight: 800;
        cursor: default; user-select: none;
        transition: all .12s; line-height: 1;
        display: flex; align-items: center; justify-content: center;
    }
    .ep-nums.interactive .ep-num-btn { cursor: pointer; }
    .ep-nums.interactive .ep-num-btn:hover { transform: scale(1.12); }
    .ep-num-btn.lit {
        border-color: var(--c, rgba(255,255,255,.4));
        background: color-mix(in srgb, var(--c, #fff) 18%, transparent);
        color: var(--c, #fff);
    }
    .ep-num-btn.mine {
        border-color: var(--c, rgba(255,255,255,.4));
        background: var(--c, #fff);
        color: #111; font-size: .85rem;
    }
    .ep-rating-avg {
        font-size: .78rem; color: rgba(255,255,255,.35); line-height: 1.6;
    }
    .ep-rating-thanks {
        font-size: .78rem; color: #4ade80; margin-top: 4px;
        display: none;
    }
    .ep-rating-thanks.show { display: block; }

    /* ── COMMENTS ── */
    .ep-comments {
        max-width: 720px; margin: 0 auto 20px;
        padding: 0 40px 40px;
    }
    .ep-comments-title {
        font-family: var(--font-display); font-size: .9rem; font-weight: 700;
        color: rgba(255,255,255,.5); letter-spacing: .06em; text-transform: uppercase;
        border-bottom: 1px solid rgba(255,255,255,.07); padding-bottom: 12px;
        margin-bottom: 20px;
    }
    .ep-comment {
        display: flex; gap: 12px; margin-bottom: 18px;
    }
    .ep-comment-av {
        width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
        background: linear-gradient(135deg, #7c3aed, #a78bfa);
        display: flex; align-items: center; justify-content: center;
        font-size: .85rem; font-weight: 800; color: #fff;
    }
    .ep-comment-body { flex: 1; }
    .ep-comment-meta { display: flex; align-items: baseline; gap: 8px; margin-bottom: 4px; }
    .ep-comment-name { font-size: .82rem; font-weight: 700; color: #fff; }
    .ep-comment-date { font-size: .65rem; color: rgba(255,255,255,.3); }
    .ep-comment-text { font-size: .88rem; color: rgba(232,228,240,.7); line-height: 1.7; }
    .ep-comment-form { margin-top: 24px; }
    .ep-comment-form input, .ep-comment-form textarea {
        width: 100%; background: rgba(255,255,255,.05);
        border: 1px solid rgba(255,255,255,.1); border-radius: 10px;
        color: #fff; font-family: inherit; font-size: .88rem;
        padding: 10px 14px; outline: none; transition: border-color .15s;
        margin-bottom: 10px;
    }
    .ep-comment-form input:focus, .ep-comment-form textarea:focus { border-color: rgba(124,58,237,.6); }
    .ep-comment-form input::placeholder, .ep-comment-form textarea::placeholder { color: rgba(255,255,255,.25); }
    .ep-comment-form textarea { height: 90px; resize: vertical; }
    .ep-comment-submit {
        background: linear-gradient(135deg, #7c3aed, #6d28d9);
        color: #fff; border: none; border-radius: 10px;
        font-family: inherit; font-weight: 700; font-size: .82rem;
        padding: 10px 22px; cursor: pointer; transition: opacity .15s;
    }
    .ep-comment-submit:hover { opacity: .85; }
    .ep-comment-err { font-size: .75rem; color: #f87171; margin-top: 6px; }
    .ep-comment-ok  { font-size: .75rem; color: #4ade80; margin-top: 6px; }
    .ep-no-comments { font-size: .82rem; color: rgba(255,255,255,.25); font-style: italic; margin-bottom: 18px; }

    /* ── COVER PAGES (manga-style, full-width) ── */
    .ep-covers-wrap {
        display: flex;
        flex-direction: column;
        align-items: center;
        line-height: 0;
    }
    .ep-covers-wrap.layout-side { flex-direction: row; align-items: stretch; }
    .ep-covers-wrap.ep-covers-end { margin-top: 48px; }
    .ep-cover-img {
        display: block;
        width: auto;
        max-width: 100%;
        max-height: 100vh;
        height: auto;
    }
    .ep-covers-wrap.layout-side .ep-cover-img { flex: 1; min-width: 0; width: 100%; object-fit: contain; }

    @media (max-width: 900px) {
        .ep-layout { grid-template-columns: 1fr !important; }
        .ep-toc { display: none; }
    }
    @media (max-width: 900px) {
        /* Topbar right: hide less-important controls on tablet to prevent overflow */
        .ep-topbar { padding: 0 14px; gap: 0; }
    }
    @media (max-width: 600px) {
        .ep-header, .ep-content, .ep-read-banner, .ep-rating-section, .ep-comments {
            padding-left: 16px; padding-right: 16px;
        }
        .ep-main-title { font-size: 1.45rem; }
        .ep-main-desc  { font-size: .88rem; }
        .ep-topbar-crumb { display: none; }
        .ep-topbar-right { gap: 8px; }
        .ep-toc-toggle   { font-size: .66rem; padding: 5px 9px; gap: 4px; }
        .ep-nav-btn .ep-nav-name { display: none; }
        .ep-nav-btn { min-width: unset; padding: 9px 12px; }
        .ep-scene-wrap { padding: 0; }
        .ep-settings-panel { width: 100%; border-radius: 0; }
        /* Rating: shrink number buttons to fit 10 in a line */
        .ep-nums { gap: 4px; }
        .ep-num-btn { width: 30px; height: 30px; font-size: .75rem; border-radius: 7px; }
        /* Comments form */
        .ep-cmt-form { padding: 16px; }
    }
    @media (max-width: 380px) {
        .ep-header, .ep-content, .ep-read-banner, .ep-rating-section, .ep-comments {
            padding-left: 12px; padding-right: 12px;
        }
        .ep-num-btn { width: 27px; height: 27px; font-size: .7rem; gap: 3px; }
    }

    /* ── QUOTE / BOOKMARK TOOLTIP ── */
    .ep-quote-tip {
        position: fixed; z-index: 500;
        background: #1a1828;
        border: 1px solid rgba(124,58,237,.5);
        border-radius: 10px; padding: 8px 14px;
        display: none; flex-direction: column; gap: 0;
        box-shadow: 0 8px 28px rgba(0,0,0,.55);
        transform: translateX(-50%);
        pointer-events: auto;
    }
    .ep-quote-tip.show { display: flex; }
    .ep-qt-row { display: flex; align-items: center; gap: 8px; white-space: nowrap; }
    .ep-qt-sep { color: rgba(255,255,255,.2); font-size: .65rem; user-select: none; }
    .ep-quote-save-btn, .ep-bm-open-btn {
        border: none; font-size: .72rem; font-weight: 700; padding: 5px 11px;
        border-radius: 6px; cursor: pointer; font-family: inherit;
        transition: background .12s; white-space: nowrap;
    }
    .ep-quote-save-btn { background: var(--primary); color: #fff; }
    .ep-quote-save-btn:hover { background: #6d28d9; }
    .ep-bm-open-btn { background: rgba(245,158,11,.18); color: #f59e0b; }
    .ep-bm-open-btn:hover { background: rgba(245,158,11,.3); }
    .ep-qt-confirm { font-size: .75rem; color: #4ade80; font-weight: 700; display: none; padding: 3px 0; }
    .ep-qt-confirm.show { display: block; }
    .ep-bm-form { display: none; flex-direction: column; gap: 8px; margin-top: 8px; }
    .ep-bm-form.show { display: flex; }
    .ep-bm-preview {
        font-size: .7rem; font-style: italic; color: rgba(255,255,255,.45);
        border-left: 2px solid #f59e0b; padding-left: 8px;
        overflow: hidden; display: -webkit-box;
        -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical;
    }
    .ep-bm-textarea {
        background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.15);
        border-radius: 8px; color: #fff; font-family: inherit; font-size: .78rem;
        padding: 8px 10px; resize: none; outline: none;
        min-height: 56px; width: 224px; box-sizing: border-box;
    }
    .ep-bm-textarea:focus { border-color: rgba(245,158,11,.5); }
    .ep-bm-textarea::placeholder { color: rgba(255,255,255,.3); }
    .ep-bm-row { display: flex; gap: 6px; justify-content: flex-end; }
    .ep-bm-cancel {
        background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12);
        color: rgba(255,255,255,.55); border-radius: 6px;
        font-size: .7rem; font-weight: 700; padding: 5px 10px;
        cursor: pointer; font-family: inherit;
    }
    .ep-bm-cancel:hover { color: #fff; background: rgba(255,255,255,.15); }
    .ep-bm-save {
        background: #f59e0b; border: none; color: #111;
        border-radius: 6px; font-size: .7rem; font-weight: 800;
        padding: 5px 12px; cursor: pointer; font-family: inherit;
    }
    .ep-bm-save:hover { background: #fbbf24; }

    /* ── READING TIMER ── */
    .ep-timer-badge {
        position: fixed; bottom: 22px; left: 50%;
        transform: translateX(-50%) translateY(8px);
        background: rgba(18,16,40,.88);
        border: 1px solid rgba(124,58,237,.35);
        color: rgba(255,255,255,.7);
        border-radius: 100px; padding: 6px 18px;
        font-size: .73rem; font-weight: 700;
        backdrop-filter: blur(10px);
        box-shadow: 0 4px 20px rgba(0,0,0,.4);
        opacity: 0; transition: opacity .4s, transform .4s;
        pointer-events: none; z-index: 200; white-space: nowrap;
    }
    .ep-timer-badge.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    .ep-read-time-chip {
        display: none; margin-top: 10px;
        font-size: .72rem; font-weight: 700; color: var(--primary);
        background: rgba(124,58,237,.1); border: 1px solid rgba(124,58,237,.2);
        border-radius: 100px; padding: 3px 14px; width: fit-content;
    }
    </style>
</head>
<body class="ep-reader-page">

<!-- TOP BAR -->
<div class="ep-topbar">
    <a href="seasons.php" class="ep-topbar-back">← Terug</a>
    <div class="ep-topbar-crumb">
        <strong>S<?= $ep['season_number'] ?></strong>
        <span style="opacity:.25;">/</span>
        <?= htmlspecialchars($ep['season_title']) ?>
        <span style="opacity:.25;">/</span>
        <strong>Afl. <?= $ep['number'] ?></strong>
    </div>
    <div class="ep-topbar-right">
        <?php if ($hasChapters): ?>
        <button class="ep-toc-toggle" id="toc-toggle-btn" onclick="toggleMobileToc()">
            ☰ Inhoud
        </button>
        <?php endif; ?>
        <div class="ep-dots-wrap" id="ep-dots">
            <?php foreach ($allEps as $i => $e): ?>
            <div class="ep-dot <?= $i === $currentIdx ? 'active' : '' ?>"
                 title="Afl. <?= $e['number'] ?>: <?= htmlspecialchars($e['title']) ?>"
                 onclick="goToIndex(<?= $i ?>)"></div>
            <?php endforeach; ?>
        </div>
        <span class="ep-counter" id="ep-counter"><?= $currentIdx+1 ?>/<?= $total ?></span>
        <button id="cover-layout-btn" class="ep-settings-btn" onclick="toggleCoverLayout()" title="Covers naast elkaar" style="display:none;">⊞</button>
        <button id="ep-fav-btn" onclick="toggleEpFav()" title="Toevoegen aan favorieten"
                style="background:rgba(220,38,38,.12);border:1px solid rgba(220,38,38,.3);color:#f87171;font-size:.78rem;font-weight:700;padding:5px 11px;border-radius:8px;cursor:pointer;display:flex;align-items:center;gap:5px;transition:all .15s;font-family:inherit;">🤍</button>
        <button class="ep-settings-btn" onclick="toggleSettings()" title="Leescomfort">⚙</button>
    </div>
</div>

<!-- SETTINGS PANEL -->
<div class="ep-settings-panel" id="ep-settings-panel">
    <div class="ep-settings-row">
        <div class="ep-settings-label">Lettergrootte</div>
        <div class="ep-settings-opts">
            <button class="ep-settings-opt" data-pref="size" data-val="sm" onclick="setPref('size','sm')">A</button>
            <button class="ep-settings-opt active" data-pref="size" data-val="md" onclick="setPref('size','md')">A</button>
            <button class="ep-settings-opt" data-pref="size" data-val="lg" onclick="setPref('size','lg')" style="font-size:.9rem;">A</button>
            <button class="ep-settings-opt" data-pref="size" data-val="xl" onclick="setPref('size','xl')" style="font-size:1rem;">A</button>
        </div>
    </div>
    <div class="ep-settings-row">
        <div class="ep-settings-label">Regelafstand</div>
        <div class="ep-settings-opts">
            <button class="ep-settings-opt" data-pref="spacing" data-val="tight" onclick="setPref('spacing','tight')">Compact</button>
            <button class="ep-settings-opt active" data-pref="spacing" data-val="normal" onclick="setPref('spacing','normal')">Normaal</button>
            <button class="ep-settings-opt" data-pref="spacing" data-val="loose" onclick="setPref('spacing','loose')">Ruim</button>
        </div>
    </div>
    <div class="ep-settings-row">
        <div class="ep-settings-label">Achtergrond</div>
        <div class="ep-settings-opts">
            <button class="ep-settings-opt active" data-pref="theme" data-val="dark"  onclick="setPref('theme','dark')">🌑 Donker</button>
            <button class="ep-settings-opt" data-pref="theme" data-val="night" onclick="setPref('theme','night')">🌙 Nacht</button>
            <button class="ep-settings-opt" data-pref="theme" data-val="sepia" onclick="setPref('theme','sepia')">📜 Sepia</button>
            <button class="ep-settings-opt" data-pref="theme" data-val="light" onclick="setPref('theme','light')">☀️ Licht</button>
        </div>
    </div>
</div>

<!-- SCROLL RESTORE TOAST -->
<div class="ep-restore-toast" id="ep-restore-toast" onclick="restoreScroll()"></div>

<!-- READING PROGRESS -->
<div class="ep-progress-bar"><div class="ep-progress-fill" id="read-progress"></div></div>

<!-- MOBILE TOC OVERLAY -->
<?php if ($hasChapters): ?>
<div class="ep-toc-overlay" id="toc-overlay" onclick="closeMobileToc()">
    <nav class="ep-toc-mobile" onclick="event.stopPropagation()" id="mobile-toc">
        <div class="ep-toc-label" style="padding:0 20px 14px;">Inhoudsopgave</div>
        <?php foreach (array_values($chapters) as $ci => $ch): if (!$ch['title'] || ($ch['level'] ?? 2) !== 2) continue; ?>
        <a class="ep-toc-item" onclick="scrollToChapter('<?= $ch['id'] ?>');closeMobileToc()">
            <?= htmlspecialchars($ch['title']) ?>
        </a>
        <?php endforeach; ?>
    </nav>
</div>
<?php endif; ?>

<!-- LAYOUT -->
<div class="ep-layout <?= !$hasChapters ? 'no-toc' : '' ?>" id="ep-layout">

    <?php if ($hasChapters): ?>
    <!-- DESKTOP TOC SIDEBAR -->
    <nav class="ep-toc" id="desktop-toc">
        <div class="ep-toc-label">Inhoudsopgave</div>
        <?php foreach (array_values($chapters) as $ci => $ch): if (!$ch['title'] || ($ch['level'] ?? 2) !== 2) continue; ?>
        <a class="ep-toc-item <?= $ci === 0 ? 'active' : '' ?>"
           id="toc-<?= $ch['id'] ?>"
           onclick="scrollToChapter('<?= $ch['id'] ?>')">
            <?= htmlspecialchars($ch['title']) ?>
        </a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <!-- MAIN STAGE -->
    <div class="ep-stage">
        <div class="ep-viewport slide-active" id="ep-viewport">

            <div class="ep-header">
                <div class="ep-season-pill">
                    ⚔ Seizoen <?= $ep['season_number'] ?> — <?= htmlspecialchars($ep['season_title']) ?>
                </div>
                <div class="ep-num-label">Aflevering <?= $ep['number'] ?></div>
                <h1 class="ep-main-title" id="ep-title"><?= htmlspecialchars($ep['title']) ?></h1>
                <?php if (!empty($ep['description'])): ?>
                <div class="ep-teaser" id="ep-teaser"><?= nl2br(htmlspecialchars($ep['description'])) ?></div>
                <?php endif; ?>
                <hr class="ep-divider">
                <div class="ep-meta-row">
                    <?php if ($ep['release_date']): ?>
                    <span>📅 <?= date('d F Y', strtotime($ep['release_date'])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($ep['reading_time'])): ?>
                    <span>⏱ ~<?= $ep['reading_time'] ?> min lezen</span>
                    <?php endif; ?>
                    <?php if (!empty($ep['content'])): ?>
                    <span>📖 ~<?= number_format(str_word_count($ep['content'])) ?> woorden</span>
                    <?php endif; ?>
                </div>
                <div id="ep-read-time-chip" class="ep-read-time-chip"></div>
            </div>

            <?php $startCovers = $epCovers[$id]['start'] ?? []; ?>
            <div class="ep-covers-wrap ep-covers-start" id="ep-covers-start"<?= empty($startCovers) ? ' style="display:none;"' : '' ?>>
                <?php foreach ($startCovers as $cv): ?>
                <img class="ep-cover-img" src="uploads/covers/ep_<?= $cv['episode_id'] ?>/<?= htmlspecialchars($cv['image']) ?>" alt="Cover" loading="lazy">
                <?php endforeach; ?>
            </div>

            <div class="ep-content" id="ep-body">
                <?php if (!empty($ep['content']) && !empty($chapters)):
                    $isFirst = true;
                    foreach (array_values($chapters) as $ci => $ch):
                ?>
                    <?php if ($ch['title']): ?>
                    <h2 class="ep-chapter-heading" id="<?= $ch['id'] ?>"><?= htmlspecialchars($ch['title']) ?></h2>
                    <?php endif; ?>
                    <?php foreach ($ch['paragraphs'] as $para): ?>
                    <?= renderParagraph($para, $isFirst) ?>
                    <?php endforeach; ?>
                <?php endforeach;
                else: ?>
                <div class="ep-no-content">
                    <div class="icon">📜</div>
                    <h3 style="font-family:var(--font-display);color:rgba(255,255,255,.4);margin-bottom:8px;">Inhoud binnenkort beschikbaar</h3>
                    <p>De tekst van deze aflevering wordt nog toegevoegd.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Read complete banner (shown after scrolling to bottom) -->
            <div class="ep-read-banner" id="read-banner">
                <div class="ep-read-inner">
                    <div class="ep-read-icon">✅</div>
                    <div class="ep-read-text">
                        <strong>Aflevering gelezen!</strong>
                        Je voortgang is opgeslagen. Ga verder met de volgende aflevering.
                    </div>
                </div>
            </div>

            <!-- RATING -->
            <div class="ep-rating-section" id="ep-rating-section">
                <div class="ep-rating-label">Beoordeel deze aflevering</div>
                <div class="ep-nums" id="ep-nums">
                    <button class="ep-num-btn" data-n="1">1</button>
                    <button class="ep-num-btn" data-n="2">2</button>
                    <button class="ep-num-btn" data-n="3">3</button>
                    <button class="ep-num-btn" data-n="4">4</button>
                    <button class="ep-num-btn" data-n="5">5</button>
                    <button class="ep-num-btn" data-n="6">6</button>
                    <button class="ep-num-btn" data-n="7">7</button>
                    <button class="ep-num-btn" data-n="8">8</button>
                    <button class="ep-num-btn" data-n="9">9</button>
                    <button class="ep-num-btn" data-n="10">10</button>
                </div>
                <div class="ep-rating-avg" id="ep-rating-avg"></div>
                <div class="ep-rating-thanks" id="ep-rating-thanks">✓ Bedankt voor je beoordeling!</div>
            </div>

            <?php $endCovers = $epCovers[$id]['end'] ?? []; ?>
            <div class="ep-covers-wrap ep-covers-end" id="ep-covers-end"<?= empty($endCovers) ? ' style="display:none;"' : '' ?>>
                <?php foreach ($endCovers as $cv): ?>
                <img class="ep-cover-img" src="uploads/covers/ep_<?= $cv['episode_id'] ?>/<?= htmlspecialchars($cv['image']) ?>" alt="Cover" loading="lazy">
                <?php endforeach; ?>
            </div>

        </div><!-- /ep-viewport -->

        <!-- COMMENTS -->
        <div class="ep-comments" id="ep-comments-section">
            <div class="ep-comments-title" id="ep-comments-title">
                💬 Reacties (<span id="ep-comment-count"><?= count($comments) ?></span>)
            </div>
            <div id="ep-comment-list">
            <?php if (empty($comments)): ?>
            <div class="ep-no-comments" id="ep-no-comments">Nog geen reacties. Wees de eerste!</div>
            <?php else: foreach ($comments as $c): ?>
            <div class="ep-comment">
                <div class="ep-comment-av"><?= strtoupper(mb_substr($c['name'],0,1)) ?></div>
                <div class="ep-comment-body">
                    <div class="ep-comment-meta">
                        <span class="ep-comment-name"><?= htmlspecialchars($c['name']) ?></span>
                        <span class="ep-comment-date"><?= date('d M Y H:i', strtotime($c['created_at'])) ?></span>
                    </div>
                    <div class="ep-comment-text"><?= nl2br(htmlspecialchars($c['body'])) ?></div>
                </div>
            </div>
            <?php endforeach; endif; ?>
            </div>
            <div class="ep-comment-form" id="ep-comment-form">
                <input type="text" id="ep-cmt-name" placeholder="Jouw naam…" maxlength="80">
                <textarea id="ep-cmt-body" placeholder="Jouw reactie…" maxlength="2000"></textarea>
                <button class="ep-comment-submit" onclick="submitComment()">Reactie plaatsen</button>
                <div id="ep-cmt-msg"></div>
            </div>
        </div>

        <!-- RELATED EPISODES -->
        <div id="ep-related" class="ep-related"></div>

    </div><!-- /ep-stage -->
</div><!-- /ep-layout -->

<!-- BOTTOM NAV -->
<nav class="ep-nav">
    <?php $prev = $currentIdx > 0 ? $allEps[$currentIdx-1] : null; ?>
    <?php $next = $currentIdx < $total-1 ? $allEps[$currentIdx+1] : null; ?>

    <button class="ep-nav-btn <?= !$prev ? 'ep-nav-disabled' : '' ?>"
            id="btn-prev" onclick="navigate(-1)" <?= !$prev ? 'disabled' : '' ?>>
        <span>←</span>
        <div>
            <div class="ep-nav-label">Vorige</div>
            <div class="ep-nav-name"><?= $prev ? 'Afl.'.$prev['number'].': '.htmlspecialchars($prev['title']) : '—' ?></div>
        </div>
    </button>

    <div class="ep-nav-center">
        <div class="ep-nav-center-title" id="nav-title"><?= htmlspecialchars($ep['title']) ?></div>
        <div class="ep-nav-center-pos" id="nav-pos">Afl. <?= $ep['number'] ?> · <?= $currentIdx+1 ?>/<?= $total ?></div>
    </div>

    <button class="ep-nav-btn next <?= !$next ? 'ep-nav-disabled' : '' ?>"
            id="btn-next" onclick="navigate(1)" <?= !$next ? 'disabled' : '' ?>>
        <div style="text-align:right;">
            <div class="ep-nav-label">Volgende</div>
            <div class="ep-nav-name"><?= $next ? 'Afl.'.$next['number'].': '.htmlspecialchars($next['title']) : '—' ?></div>
        </div>
        <span>→</span>
    </button>
</nav>

<!-- EPISODE DATA (client-side navigation) -->
<script>
const ALL_EPISODES = <?= json_encode(array_map(function($e) use ($epCovers, $epRatings) {
    $eid = (int)$e['id'];
    $mkSrc = fn($c) => 'uploads/covers/ep_' . $c['episode_id'] . '/' . $c['image'];
    return [
        'id'           => $eid,
        'number'       => (int)$e['number'],
        'title'        => $e['title'],
        'description'  => $e['description'] ?? '',
        'content'      => $e['content'] ?? '',
        'release_date' => $e['release_date'] ?? '',
        'reading_time' => (int)($e['reading_time'] ?? 0),
        'status'       => $e['status'] ?? 'published',
        'covers_start' => array_map($mkSrc, $epCovers[$eid]['start'] ?? []),
        'covers_end'   => array_map($mkSrc, $epCovers[$eid]['end']   ?? []),
        'avg_rating'   => $epRatings[$eid]['avg']   ?? 0,
        'rating_count' => $epRatings[$eid]['count'] ?? 0,
    ];
}, $allEps), JSON_UNESCAPED_UNICODE) ?>;

const SEASON_NUM   = <?= (int)$ep['season_number'] ?>;
const SEASON_TITLE = <?= json_encode($ep['season_title'], JSON_UNESCAPED_UNICODE) ?>;
let currentIdx = <?= $currentIdx ?>;
let isAnimating = false;
let readBannerShown = false;

// ── DOM refs ──
const viewport   = document.getElementById('ep-viewport');
const titleEl    = document.getElementById('ep-title');
const teaserEl   = document.getElementById('ep-teaser');
const bodyEl     = document.getElementById('ep-body');
const navTitle   = document.getElementById('nav-title');
const navPos     = document.getElementById('nav-pos');
const btnPrev    = document.getElementById('btn-prev');
const btnNext    = document.getElementById('btn-next');
const counterEl  = document.getElementById('ep-counter');
const allDots    = document.querySelectorAll('.ep-dot');
const progressFill = document.getElementById('read-progress');

// ── Reading progress & mark-as-read ──
const STORAGE_KEY = 'tgj_read_ep';

function getReadEpisodes() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); } catch { return []; }
}
function markAsRead(epId) {
    const arr = getReadEpisodes();
    if (!arr.includes(epId)) {
        arr.push(epId);
        localStorage.setItem(STORAGE_KEY, JSON.stringify(arr));
        try {
            const log = JSON.parse(localStorage.getItem('tgj_read_log') || '[]');
            log.push({ epId, date: new Date().toISOString().slice(0, 10) });
            if (log.length > 1000) log.splice(0, log.length - 1000);
            localStorage.setItem('tgj_read_log', JSON.stringify(log));
        } catch {}
    }
}
function isRead(epId) { return getReadEpisodes().includes(epId); }

// Track scroll progress
window.addEventListener('scroll', () => {
    const scrollTop  = window.scrollY;
    const docHeight  = document.documentElement.scrollHeight - window.innerHeight;
    const pct = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
    progressFill.style.width = pct + '%';

    // Mark as read when 85%+ scrolled
    if (pct >= 85 && !readBannerShown) {
        readBannerShown = true;
        const epId = ALL_EPISODES[currentIdx].id;
        markAsRead(epId);
        const banner = document.getElementById('read-banner');
        if (banner) banner.classList.add('show');
    }

    // Highlight active TOC item based on scroll
    updateActiveTocItem();
}, { passive: true });

// ── TOC active item ──
function updateActiveTocItem() {
    const headings = document.querySelectorAll('.ep-chapter-heading');
    if (!headings.length) return;
    let active = headings[0].id;
    const scrollY = window.scrollY + 100;
    headings.forEach(h => { if (h.offsetTop <= scrollY) active = h.id; });

    document.querySelectorAll('.ep-toc-item').forEach(item => {
        // Match by onclick content
        const isActive = item.getAttribute('onclick')?.includes(`'${active}'`);
        item.classList.toggle('active', !!isActive);
    });
}

function scrollToChapter(id) {
    const el = document.getElementById(id);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ── Mobile TOC ──
function toggleMobileToc() {
    document.getElementById('toc-overlay').classList.toggle('open');
}
function closeMobileToc() {
    document.getElementById('toc-overlay')?.classList.remove('open');
}

// Shared name pattern: starts uppercase, letters/hyphens/apostrophes, 1–3 words
const EP_NAME = String.raw`[A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞŸ][A-Za-zÀ-ÿ'\-]{0,20}(?:\s[A-Za-zÀ-ÿ'\-]{1,20}){0,2}`;

// ── Content normalizer (mirrors PHP normalizeContent) ──
const _SPEAKER_A_NORM = new RegExp(
    String.raw`^${EP_NAME}(?:\s*\([^)]*\))?\s*:\s*$`, 'u'
);
function normalizeContent(raw) {
    if (!raw) return raw;
    const lines = raw.split('\n').map(line => {
        line = line.replace(/\s+$/, '');
        // (# headings handled by parser with level — do not convert here)
        line = line.replace(/^>\s*/, '');
        if (/^[-_*]{3,}\s*$/.test(line)) return '';
        line = line.replace(/^\*\*([^*\n:]+(?:\s*\([^)]*\))?)\*\*:\s*/, '$1: ');
        line = line.replace(/^\*([^*\n:]+(?:\s*\([^)]*\))?)\*:\s*/, '$1: ');
        return line;
    });
    // Ensure every content line is its own paragraph; keep speaker label attached to dialogue
    const spaced = [];
    for (let i = 0; i < lines.length; i++) {
        spaced.push(lines[i]);
        const curr = lines[i];
        const next = lines[i + 1];
        if (curr !== '' && next !== undefined && next !== '') {
            if (!_SPEAKER_A_NORM.test(curr.trim())) spaced.push('');
        }
    }
    return spaced.join('\n').replace(/\n{3,}/g, '\n\n');
}

// ── Chapter parser (JS mirror of PHP) ──
function parseChapters(raw) {
    if (!raw || !raw.trim()) return [];
    raw = normalizeContent(raw);
    const lines = raw.split('\n');
    const chapters = [];
    let cur = { id: '', title: '', paragraphs: [] };
    let idx = 0;
    let buffer = [];

    function flush() {
        const block = buffer.join('\n').trim();
        if (block) {
            block.split(/\n{2,}/).forEach(p => {
                p = p.trim();
                if (p) cur.paragraphs.push(p);
            });
        }
        buffer = [];
    }

    for (const line of lines) {
        const m = line.match(/^(#{1,2})\s*(.+)$/);
        if (m) {
            flush();
            if (idx > 0 || cur.paragraphs.length) chapters.push(cur);
            idx++;
            cur = { id: 'ch-' + idx, title: m[2].trim(), level: m[1].length, paragraphs: [] };
        } else {
            buffer.push(line);
        }
    }
    flush();
    chapters.push(cur);
    return chapters.filter(c => c.title || c.paragraphs.length);
}

// Scene image marker: [scene: filename.jpg] or [scene: filename.jpg | Caption]
const RE_SCENE = /^\[scene:\s*([^|\]]+?)(?:\|([^\]]*))?\]\s*$/i;

function renderSceneMarker(para) {
    const m = para.trim().match(RE_SCENE);
    if (!m) return null;
    const filename = m[1].trim();
    const caption  = m[2] ? m[2].trim() : '';
    const src = `uploads/scenes/${escHtml(filename)}`;
    const alt = caption ? escHtml(caption) : 'Scene afbeelding';
    return `<div class="ep-scene-wrap">
        <div class="ep-scene-frame">
            <img class="ep-scene-img" src="${src}" alt="${alt}" loading="lazy">
            <div class="ep-scene-vignette"></div>
        </div>${caption ? `\n        <p class="ep-scene-caption">${escHtml(caption)}</p>` : ''}
    </div>`;
}


// Format A: "Name (action):" alone on line
const RE_SPEAKER_A = new RegExp(String.raw`^(${EP_NAME})((?:\s*\([^)]*\))?)\s*:\s*$`, 'u');
// Format B: "Name: dialogue" or "Name: (action) dialogue" inline
const RE_SPEAKER_B = new RegExp(String.raw`^(${EP_NAME}):\s*((?:\([^)]*\)\s*)?)(.+)`, 'u');

// Apply inline formatting: **bold** or *bold*
function applyInline(html) {
    // Double asterisks first
    html = html.replace(/\*\*(.+?)\*\*/gs, '<strong class="ep-bold">$1</strong>');
    // Single asterisks
    html = html.replace(/\*([^*\n]+)\*/g, '<strong class="ep-bold">$1</strong>');
    return html;
}

function renderLine(line) {
    const t = line.trim();

    // Format A
    let m = t.match(RE_SPEAKER_A);
    if (m) {
        const name   = escHtml(m[1].trim());
        const action = escHtml(m[2].trim());
        return `<span class="ep-speaker">${name}</span>`
             + (action ? ` <span class="ep-speaker-action">${action}</span>` : '')
             + ':';
    }

    // Format B
    m = t.match(RE_SPEAKER_B);
    if (m) {
        const name   = escHtml(m[1].trim());
        const action = escHtml(m[2].trimEnd());
        const rest   = applyInline(escHtml(m[3]));
        return `<span class="ep-speaker">${name}:</span>`
             + (action ? ` <span class="ep-speaker-action">${action}</span>` : '')
             + ' ' + rest;
    }

    return applyInline(escHtml(line));
}

function renderChapters(chapters) {
    if (!chapters.length) {
        return `<div class="ep-no-content">
            <div class="icon">📜</div>
            <h3 style="font-family:var(--font-display);color:rgba(255,255,255,.4);margin-bottom:8px;">Inhoud binnenkort beschikbaar</h3>
            <p>De tekst van deze aflevering wordt nog toegevoegd.</p>
        </div>`;
    }
    let html = '';
    let isFirst = true;
    chapters.forEach(ch => {
        if (ch.title) {
            html += `<h2 class="ep-chapter-heading" id="${ch.id}">${escHtml(ch.title)}</h2>`;
        }
        ch.paragraphs.forEach(p => {
            // Scan line by line so scene markers work even without blank lines around them
            const lines = p.split('\n');
            let pending = [];

            const flushPending = () => {
                if (!pending.length) return;
                const cls = isFirst ? ' class="ep-first-para"' : '';
                html += `<p${cls}>${pending.map(renderLine).join('<br>')}</p>`;
                isFirst = false;
                pending = [];
            };

            for (const line of lines) {
                const scene = renderSceneMarker(line);
                if (scene) {
                    flushPending();
                    html += scene;
                } else {
                    pending.push(line);
                }
            }
            flushPending();
        });
    });
    return html;
}

function buildTocLinks(chapters) {
    return chapters
        .filter(c => c.title && (c.level || 2) === 2)
        .map((c, i) =>
            `<a class="ep-toc-item${i===0?' active':''}" id="toc-${c.id}" onclick="scrollToChapter('${c.id}')">${escHtml(c.title)}</a>`
        ).join('');
}

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Cover pages ──
const COVER_LAYOUT_KEY = 'tgj_cover_layout';

function getCoverLayout() {
    return localStorage.getItem(COVER_LAYOUT_KEY) || 'column';
}

function applyCoverLayout(layout) {
    document.querySelectorAll('.ep-covers-wrap').forEach(el => {
        el.classList.toggle('layout-side', layout === 'row');
    });
    const btn = document.getElementById('cover-layout-btn');
    if (btn) {
        btn.textContent = layout === 'row' ? '☷' : '⊞';
        btn.title       = layout === 'row' ? 'Covers onder elkaar' : 'Covers naast elkaar';
    }
}

function toggleCoverLayout() {
    const next = getCoverLayout() === 'column' ? 'row' : 'column';
    localStorage.setItem(COVER_LAYOUT_KEY, next);
    applyCoverLayout(next);
}

function renderCovers(ep) {
    const startEl = document.getElementById('ep-covers-start');
    const endEl   = document.getElementById('ep-covers-end');
    const mkImg   = src => `<img class="ep-cover-img" src="${src}" alt="Cover" loading="lazy">`;
    const totalCovers = (ep.covers_start?.length || 0) + (ep.covers_end?.length || 0);

    if (startEl) {
        const imgs = ep.covers_start || [];
        startEl.innerHTML = imgs.map(mkImg).join('');
        startEl.style.display = imgs.length ? '' : 'none';
    }
    if (endEl) {
        const imgs = ep.covers_end || [];
        endEl.innerHTML = imgs.map(mkImg).join('');
        endEl.style.display = imgs.length ? '' : 'none';
    }

    // Show layout toggle only when there are multiple covers in a single section
    const layoutBtn = document.getElementById('cover-layout-btn');
    if (layoutBtn) {
        const hasMulti = (ep.covers_start?.length > 1) || (ep.covers_end?.length > 1);
        layoutBtn.style.display = hasMulti ? '' : 'none';
    }

    applyCoverLayout(getCoverLayout());
}

// ── Navigation ──
function navigate(dir) {
    const newIdx = currentIdx + dir;
    if (newIdx < 0 || newIdx >= ALL_EPISODES.length || isAnimating) return;
    goToIndex(newIdx, dir);
}

function goToIndex(newIdx, dir) {
    if (newIdx === currentIdx || isAnimating) return;
    if (dir === undefined) dir = newIdx > currentIdx ? 1 : -1;
    isAnimating = true;

    // Slide out
    viewport.classList.remove('slide-active');
    viewport.classList.add(dir > 0 ? 'slide-out-left' : 'slide-out-right');

    setTimeout(() => {
        currentIdx = newIdx;
        const ep = ALL_EPISODES[newIdx];

        // Update title / teaser
        titleEl.textContent = ep.title;
        const t = document.getElementById('ep-teaser');
        if (ep.description) {
            if (t) t.innerHTML = ep.description.replace(/\n/g, '<br>');
        } else if (t) {
            t.innerHTML = '';
        }

        // Update meta row
        const metaRow = document.querySelector('.ep-meta-row');
        if (metaRow) {
            let m = '';
            if (ep.release_date) m += `<span>📅 ${ep.release_date}</span>`;
            if (ep.reading_time) m += `<span>⏱ ~${ep.reading_time} min lezen</span>`;
            metaRow.innerHTML = m;
        }

        // Render cover pages
        renderCovers(ep);

        // Parse and render chapters
        const chapters = parseChapters(ep.content);
        bodyEl.innerHTML = renderChapters(chapters);

        // Rebuild TOC (only level-2 scene headings drive the TOC)
        const hasChapters = chapters.filter(c => c.title && (c.level || 2) === 2).length > 0;
        const desktopToc = document.getElementById('desktop-toc');
        const mobileToc  = document.getElementById('mobile-toc');
        const tocBtn     = document.getElementById('toc-toggle-btn');
        const layout     = document.getElementById('ep-layout');

        if (hasChapters) {
            const tocLinks = buildTocLinks(chapters);
            if (desktopToc) { desktopToc.innerHTML = '<div class="ep-toc-label">Inhoudsopgave</div>' + tocLinks; desktopToc.style.display=''; }
            if (mobileToc)  { mobileToc.innerHTML  = '<div class="ep-toc-label" style="padding:0 20px 14px;">Inhoudsopgave</div>' + tocLinks; }
            if (tocBtn)     tocBtn.style.display = '';
            if (layout)     layout.classList.remove('no-toc');
        } else {
            if (desktopToc) desktopToc.style.display = 'none';
            if (tocBtn)     tocBtn.style.display = 'none';
            if (layout)     layout.classList.add('no-toc');
        }

        // Update nav bar
        navTitle.textContent = ep.title;
        navPos.textContent   = `Afl. ${ep.number} · ${currentIdx+1}/${ALL_EPISODES.length}`;
        counterEl.textContent = `${currentIdx+1}/${ALL_EPISODES.length}`;
        allDots.forEach((d, i) => d.classList.toggle('active', i === currentIdx));

        // Nav buttons
        const prevEp = ALL_EPISODES[currentIdx - 1];
        const nextEp = ALL_EPISODES[currentIdx + 1];
        updateNavBtn(btnPrev, prevEp, 'Vorige');
        updateNavBtn(btnNext, nextEp, 'Volgende');

        // Mark-as-read banner reset
        readBannerShown = isRead(ep.id);
        const banner = document.getElementById('read-banner');
        if (banner) banner.classList.toggle('show', readBannerShown);

        // URL + title
        history.pushState({ epId: ep.id, idx: currentIdx }, '', `?id=${ep.id}`);
        document.title = `${ep.title} — The Greatest Journey`;

        // Update fav button and rating for the new episode
        refreshEpFavBtn();
        refreshRating();
        timerSwitch(ep.id);
        renderRelated();

        // Scroll top
        window.scrollTo({ top: 0, behavior: 'instant' });
        checkScrollRestore();
        progressFill.style.width = '0%';

        // Slide in
        viewport.classList.remove('slide-out-left', 'slide-out-right');
        viewport.classList.add(dir > 0 ? 'slide-in-left' : 'slide-in-right');
        void viewport.offsetHeight; // force reflow
        viewport.classList.remove('slide-in-left', 'slide-in-right');
        viewport.classList.add('slide-active');

        setTimeout(() => { isAnimating = false; }, 420);
    }, 340);
}

function updateNavBtn(btn, ep, label) {
    const nameEl = btn.querySelector('.ep-nav-name');
    if (ep) {
        btn.disabled = false; btn.classList.remove('ep-nav-disabled');
        if (nameEl) nameEl.textContent = `Afl.${ep.number}: ${ep.title}`;
    } else {
        btn.disabled = true; btn.classList.add('ep-nav-disabled');
        if (nameEl) nameEl.textContent = '—';
    }
}

// ── Init: mark if already read ──
(function() {
    const epId = ALL_EPISODES[currentIdx].id;
    if (isRead(epId)) {
        readBannerShown = true;
        const banner = document.getElementById('read-banner');
        if (banner) banner.classList.add('show');
    }
})();

// ── Keyboard ──
document.addEventListener('keydown', e => {
    if (e.key === 'ArrowRight' || e.key === 'ArrowDown') navigate(1);
    if (e.key === 'ArrowLeft'  || e.key === 'ArrowUp')   navigate(-1);
});

// ── Browser back/forward ──
window.addEventListener('popstate', e => {
    if (e.state && e.state.idx !== undefined) {
        goToIndex(e.state.idx, e.state.idx > currentIdx ? 1 : -1);
    }
});

// ══════════════════════════════════════════════
// FEATURE 1 — READER SETTINGS
// ══════════════════════════════════════════════
const PREFS_KEY = 'tgj_reader_prefs';
let _settingsOpen = false;

function loadPrefs() {
    try { return JSON.parse(localStorage.getItem(PREFS_KEY) || '{}'); } catch { return {}; }
}
function savePrefs(p) { localStorage.setItem(PREFS_KEY, JSON.stringify(p)); }

function applyPrefs(p) {
    const content = document.getElementById('ep-body');
    const body    = document.querySelector('.ep-reader-page');
    if (!content || !body) return;
    const sizeMap    = { sm: '.9rem', md: '1.05rem', lg: '1.18rem', xl: '1.32rem' };
    const spacingMap = { tight: '1.6', normal: '1.95', loose: '2.4' };
    content.style.fontSize   = sizeMap[p.size || 'md']    || '1.05rem';
    content.style.lineHeight = spacingMap[p.spacing || 'normal'] || '1.95';
    body.classList.remove('ep-theme-sepia', 'ep-theme-light', 'ep-theme-night');
    if (p.theme === 'sepia') body.classList.add('ep-theme-sepia');
    if (p.theme === 'light') body.classList.add('ep-theme-light');
    if (p.theme === 'night') body.classList.add('ep-theme-night');
    // Sync button states
    document.querySelectorAll('.ep-settings-opt').forEach(btn => {
        const pref = btn.dataset.pref;
        const val  = btn.dataset.val;
        btn.classList.toggle('active', p[pref] === val || (!p[pref] && btn.classList.contains('active') && false));
        if (pref && val) btn.classList.toggle('active', (p[pref] || (pref==='size'?'md':pref==='spacing'?'normal':'dark')) === val);
    });
}

function setPref(key, val) {
    const p = loadPrefs();
    p[key] = val;
    savePrefs(p);
    applyPrefs(p);
}

function toggleSettings() {
    _settingsOpen = !_settingsOpen;
    document.getElementById('ep-settings-panel').classList.toggle('open', _settingsOpen);
}

document.addEventListener('click', e => {
    const panel = document.getElementById('ep-settings-panel');
    const btn   = e.target.closest('.ep-settings-btn');
    if (!btn && panel && !panel.contains(e.target) && _settingsOpen) {
        _settingsOpen = false;
        panel.classList.remove('open');
    }
});

// Apply saved prefs on load
applyPrefs(loadPrefs());

// Apply cover layout on initial load
(function() {
    const ep = ALL_EPISODES[currentIdx];
    const hasMulti = (ep.covers_start?.length > 1) || (ep.covers_end?.length > 1);
    const layoutBtn = document.getElementById('cover-layout-btn');
    if (layoutBtn && hasMulti) layoutBtn.style.display = '';
    applyCoverLayout(getCoverLayout());
})();

// ══════════════════════════════════════════════
// FEATURE 2 — SCROLL POSITION SAVING
// ══════════════════════════════════════════════
function scrollKey(epId) { return 'tgj_scroll_' + epId; }

function saveScrollPos() {
    const epId = ALL_EPISODES[currentIdx].id;
    const pct  = window.scrollY / Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
    if (pct > 0.02 && pct < 0.98) {
        // Detect the last chapter heading that scrolled above the viewport
        let chId = '', chTitle = '';
        document.querySelectorAll('#ep-body .ep-chapter-heading[id]').forEach(h => {
            if (h.getBoundingClientRect().top < 100) {
                chId    = h.id;
                chTitle = h.textContent.replace(/^§\s*/, '').trim();
            }
        });
        localStorage.setItem(scrollKey(epId), JSON.stringify({ pct: pct.toFixed(4), chId, chTitle }));
    } else if (pct >= 0.98) {
        localStorage.removeItem(scrollKey(epId));
    }
    // Save last opened episode for "continue reading" on homepage
    localStorage.setItem('tgj_last_ep', JSON.stringify({
        id: epId,
        number: ALL_EPISODES[currentIdx].number,
        title: ALL_EPISODES[currentIdx].title,
        seasonNum: SEASON_NUM,
        seasonTitle: SEASON_TITLE,
    }));
}

let _scrollSaveTimer = null;
window.addEventListener('scroll', () => {
    clearTimeout(_scrollSaveTimer);
    _scrollSaveTimer = setTimeout(saveScrollPos, 400);
}, { passive: true });

let _savedScrollPos = null, _savedScrollChId = '';
function checkScrollRestore() {
    const epId = ALL_EPISODES[currentIdx].id;
    const raw  = localStorage.getItem(scrollKey(epId));
    _savedScrollPos = null; _savedScrollChId = '';
    if (!raw) return;
    let data;
    try { data = JSON.parse(raw); } catch { data = { pct: parseFloat(raw) }; }
    _savedScrollPos  = parseFloat(data.pct);
    _savedScrollChId = data.chId || '';
    const pct   = Math.round(_savedScrollPos * 100);
    const label = data.chTitle
        ? `↩ Verdergaan bij "${data.chTitle}"`
        : `↩ Verdergaan vanaf ${pct}%`;
    const toast = document.getElementById('ep-restore-toast');
    if (toast && pct > 3) {
        toast.textContent = label;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 5000);
    }
}

function restoreScroll() {
    if (_savedScrollPos == null) return;
    const chEl = _savedScrollChId ? document.getElementById(_savedScrollChId) : null;
    if (chEl) {
        const top = chEl.getBoundingClientRect().top + window.scrollY - 80;
        window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
    } else {
        const target = _savedScrollPos * (document.documentElement.scrollHeight - window.innerHeight);
        window.scrollTo({ top: target, behavior: 'smooth' });
    }
    document.getElementById('ep-restore-toast')?.classList.remove('show');
}

checkScrollRestore();

// ══════════════════════════════════════════════
// FAVORITES
// ══════════════════════════════════════════════
const FAV_KEY_EPS = 'tgj_fav_eps';
function getFavEps() { try { return JSON.parse(localStorage.getItem(FAV_KEY_EPS)||'[]'); } catch { return []; } }
function toggleEpFav() {
    const epId = ALL_EPISODES[currentIdx].id;
    let favs = getFavEps();
    const idx = favs.indexOf(epId);
    if (idx === -1) { favs.push(epId); } else { favs.splice(idx, 1); }
    localStorage.setItem(FAV_KEY_EPS, JSON.stringify(favs));
    updateEpFavBtn(favs.includes(epId));
}
function updateEpFavBtn(active) {
    const btn = document.getElementById('ep-fav-btn');
    if (!btn) return;
    btn.textContent = active ? '❤' : '🤍';
    btn.style.background = active ? 'rgba(220,38,38,.3)' : 'rgba(220,38,38,.12)';
    btn.style.borderColor = active ? 'rgba(220,38,38,.7)' : 'rgba(220,38,38,.3)';
    btn.title = active ? 'Verwijder uit favorieten' : 'Toevoegen aan favorieten';
}
// Update the fav button whenever the episode changes
function refreshEpFavBtn() {
    const epId = ALL_EPISODES[currentIdx].id;
    updateEpFavBtn(getFavEps().includes(epId));
}
// Called on initial load
refreshEpFavBtn();

// ══════════════════════════════════════════════
// FEATURE 3 — RATING (1–10)
// ══════════════════════════════════════════════
const RATED_KEY = 'tgj_rated_eps';
function getRatedEps() { try { return JSON.parse(localStorage.getItem(RATED_KEY) || '{}'); } catch { return {}; } }

const numsEl       = document.getElementById('ep-nums');
const ratingAvg    = document.getElementById('ep-rating-avg');
const ratingThanks = document.getElementById('ep-rating-thanks');

const RATING_COLORS = {
    1:'#ef4444', 2:'#ef4444', 3:'#ef4444',
    4:'#f97316', 5:'#f97316',
    6:'#eab308',
    7:'#10b981', 8:'#10b981',
    9:'#22c55e', 10:'#a855f7'
};

function ratingColor(v) {
    if (v >= 9.5) return '#a855f7';
    if (v >= 8.5) return '#22c55e';
    if (v >= 7.0) return '#10b981';
    if (v >= 5.5) return '#eab308';
    if (v >= 4.0) return '#f97316';
    return '#ef4444';
}

// Set --c CSS variable per button once (buttons stay in DOM across navigation)
if (numsEl) {
    numsEl.querySelectorAll('.ep-num-btn').forEach(btn => {
        btn.style.setProperty('--c', RATING_COLORS[parseInt(btn.dataset.n)]);
    });
    numsEl.querySelectorAll('.ep-num-btn').forEach(btn => {
        const n = parseInt(btn.dataset.n);
        btn.addEventListener('mouseenter', () => {
            if (!numsEl.classList.contains('interactive')) return;
            numsEl.querySelectorAll('.ep-num-btn').forEach(b => {
                b.classList.remove('mine');
                b.classList.toggle('lit', parseInt(b.dataset.n) <= n);
            });
        });
        btn.addEventListener('mouseleave', () => {
            if (!numsEl.classList.contains('interactive')) return;
            renderNums(getRatedEps()[ALL_EPISODES[currentIdx].id] || 0);
        });
        btn.addEventListener('click', () => submitRating(n));
    });
}

function renderNums(avg, myVote) {
    if (!numsEl) return;
    const filled = Math.round(avg);
    numsEl.querySelectorAll('.ep-num-btn').forEach(b => {
        const n = parseInt(b.dataset.n);
        b.classList.remove('lit', 'mine');
        if (myVote && n === myVote) b.classList.add('mine');
        else if (!myVote && n <= filled) b.classList.add('lit');
    });
}

function renderRatingInfo(ep) {
    if (!ep) return;
    const myVote  = getRatedEps()[ep.id] || 0;
    const hasRated = !!myVote;

    numsEl?.classList.toggle('interactive', !hasRated);
    renderNums(ep.avg_rating || 0, myVote);

    if (ratingAvg) {
        if (ep.rating_count) {
            const avg = ep.avg_rating;
            const col = ratingColor(avg);
            ratingAvg.innerHTML = `<strong style="color:${col};font-size:1.05rem;">${avg}</strong><span style="color:rgba(255,255,255,.2)">/10</span>&ensp;<span style="font-size:.72rem;">(${ep.rating_count} ${ep.rating_count === 1 ? 'beoordeling' : 'beoordelingen'})</span>`;
        } else {
            ratingAvg.innerHTML = hasRated ? '' : '<span style="opacity:.4;font-size:.72rem;">Nog geen beoordelingen — wees de eerste!</span>';
        }
    }
    if (ratingThanks) ratingThanks.classList.toggle('show', hasRated);
}

async function submitRating(n) {
    const ep = ALL_EPISODES[currentIdx];
    if (getRatedEps()[ep.id]) return;
    numsEl?.classList.remove('interactive');
    renderNums(0, n);
    if (ratingThanks) ratingThanks.classList.add('show');
    const fd = new FormData();
    fd.append('action', 'rate');
    fd.append('ep_id', ep.id);
    fd.append('rating', n);
    try {
        const res  = await fetch('episode.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            const rated = getRatedEps();
            rated[ep.id] = n;
            localStorage.setItem(RATED_KEY, JSON.stringify(rated));
            ep.avg_rating   = data.avg;
            ep.rating_count = data.count;
            renderRatingInfo(ep);
        }
    } catch {}
}

// Called on load and after each navigation
function refreshRating() { renderRatingInfo(ALL_EPISODES[currentIdx]); }
refreshRating();

// ══════════════════════════════════════════════
// FEATURE 4 — COMMENTS
// ══════════════════════════════════════════════
async function submitComment() {
    const name = document.getElementById('ep-cmt-name')?.value.trim();
    const body = document.getElementById('ep-cmt-body')?.value.trim();
    const msg  = document.getElementById('ep-cmt-msg');
    const epId = ALL_EPISODES[currentIdx].id;

    msg.className = '';
    if (!name || name.length < 2 || !body || body.length < 3) {
        msg.className = 'ep-comment-err';
        msg.textContent = 'Vul je naam en een reactie in.';
        return;
    }
    const fd = new FormData();
    fd.append('action', 'comment');
    fd.append('ep_id', epId);
    fd.append('name', name);
    fd.append('body', body);
    try {
        const res  = await fetch('episode.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            const c = data.comment;
            const noMsg = document.getElementById('ep-no-comments');
            if (noMsg) noMsg.remove();
            const list = document.getElementById('ep-comment-list');
            const el = document.createElement('div');
            el.className = 'ep-comment';
            el.innerHTML = `
                <div class="ep-comment-av">${c.name[0].toUpperCase()}</div>
                <div class="ep-comment-body">
                    <div class="ep-comment-meta">
                        <span class="ep-comment-name">${c.name}</span>
                        <span class="ep-comment-date">${c.created_at}</span>
                    </div>
                    <div class="ep-comment-text">${c.body}</div>
                </div>`;
            list.appendChild(el);
            document.getElementById('ep-cmt-name').value = '';
            document.getElementById('ep-cmt-body').value = '';
            const cnt = document.getElementById('ep-comment-count');
            if (cnt) cnt.textContent = parseInt(cnt.textContent || 0) + 1;
            msg.className = 'ep-comment-ok';
            msg.textContent = 'Reactie geplaatst!';
            setTimeout(() => { msg.textContent = ''; }, 3000);
        } else {
            msg.className = 'ep-comment-err';
            msg.textContent = data.error || 'Fout bij plaatsen.';
        }
    } catch {
        msg.className = 'ep-comment-err';
        msg.textContent = 'Netwerkfout. Probeer opnieuw.';
    }
}

// ══════════════════════════════════════════════
// FEATURE 5 — RELATED EPISODES
// ══════════════════════════════════════════════
function renderRelated() {
    const rel = document.getElementById('ep-related');
    if (!rel) return;
    const ep  = ALL_EPISODES[currentIdx];
    const idx = currentIdx;

    // Nearby episodes in the same season (±3, exclude self)
    const nearby = [];
    for (let i = Math.max(0, idx - 3); i <= Math.min(ALL_EPISODES.length - 1, idx + 3); i++) {
        if (i !== idx) nearby.push({ i, e: ALL_EPISODES[i] });
    }

    // Similarly rated (avg_rating within ±1.5, exclude self, must have rating)
    const myR = ep.avg_rating || 0;
    const similar = myR >= 1
        ? ALL_EPISODES
            .map((e, i) => ({ i, e }))
            .filter(({ i, e }) => i !== idx && e.avg_rating >= 1 && Math.abs(e.avg_rating - myR) <= 1.5)
            .sort((a, b) => Math.abs(a.e.avg_rating - myR) - Math.abs(b.e.avg_rating - myR))
            .slice(0, 4)
        : [];

    if (!nearby.length && !similar.length) { rel.innerHTML = ''; return; }

    function card({ i, e }) {
        const col = e.avg_rating >= 1 ? epcRatingColorFallback(e.avg_rating) : '';
        return `<button class="ep-rel-card" onclick="goToIndex(${i})">
            <div class="ep-rel-num">Afl. ${e.number}</div>
            <div class="ep-rel-title">${escH(e.title)}</div>
            ${e.avg_rating >= 1 ? `<div class="ep-rel-rating" style="color:${col}">★ ${e.avg_rating.toFixed(1)}</div>` : ''}
        </button>`;
    }
    function epcRatingColorFallback(v) {
        if (v >= 9.5) return '#a855f7';
        if (v >= 8.5) return '#22c55e';
        if (v >= 7.0) return '#10b981';
        if (v >= 5.5) return '#eab308';
        if (v >= 4.0) return '#f97316';
        return '#ef4444';
    }

    let html = '';
    if (nearby.length) {
        html += `<div class="ep-rel-section">
            <div class="ep-rel-label">Meer in dit seizoen</div>
            <div class="ep-rel-row">${nearby.map(card).join('')}</div>
        </div>`;
    }
    if (similar.length) {
        html += `<div class="ep-rel-section">
            <div class="ep-rel-label">Vergelijkbaar beoordeeld</div>
            <div class="ep-rel-row">${similar.map(card).join('')}</div>
        </div>`;
    }
    rel.innerHTML = html;
}
renderRelated();

// ══════════════════════════════════════════════
// FEATURE 6 — QUOTE SAVING + BOOKMARKS
// ══════════════════════════════════════════════
const QUOTES_KEY = 'tgj_quotes';
const BM_KEY     = 'tgj_bookmarks';
function getQuotes()    { try { return JSON.parse(localStorage.getItem(QUOTES_KEY) || '[]'); } catch { return []; } }
function getBookmarks() { try { return JSON.parse(localStorage.getItem(BM_KEY)     || '[]'); } catch { return []; } }

const _qtip = document.getElementById('ep-quote-tip');
const _qok  = document.getElementById('ep-quote-tip-ok');
let _qtext  = '';

function _positionTip(rect) {
    const cx = rect.left + rect.width / 2;
    const ty = rect.top + window.scrollY - 60;
    _qtip.style.left = Math.max(100, Math.min(cx, window.innerWidth - 100)) + 'px';
    _qtip.style.top  = (ty < 60 ? rect.bottom + window.scrollY + 8 : ty) + 'px';
}

function _tryShowTip() {
    const sel  = window.getSelection();
    const text = sel?.toString().trim();
    if (!text || text.length < 8 || text.length > 600) { _hideTip(); return; }
    let range;
    try { range = sel.getRangeAt(0); } catch { _hideTip(); return; }
    const content = document.querySelector('.ep-content');
    if (!content?.contains(range.commonAncestorContainer)) { _hideTip(); return; }
    _qtext = text;
    _positionTip(range.getBoundingClientRect());
    document.getElementById('ep-qt-row')?.style.removeProperty('display');
    _qok?.classList.remove('show');
    document.getElementById('ep-bm-tip-ok')?.classList.remove('show');
    closeBookmarkForm();
    _qtip.classList.add('show');
}

function _hideTip() {
    _qtip.classList.remove('show');
    _qtext = '';
    document.getElementById('ep-qt-row')?.style.removeProperty('display');
    _qok?.classList.remove('show');
    document.getElementById('ep-bm-tip-ok')?.classList.remove('show');
    closeBookmarkForm();
}

document.addEventListener('mouseup',  () => setTimeout(_tryShowTip, 20));
document.addEventListener('touchend', () => setTimeout(_tryShowTip, 60));
document.addEventListener('mousedown', e => { if (_qtip && !_qtip.contains(e.target)) _hideTip(); });

function saveSelectedQuote() {
    if (!_qtext) return;
    const ep = ALL_EPISODES[currentIdx];
    const quotes = getQuotes();
    quotes.unshift({
        id:          Date.now(),
        text:        _qtext,
        epId:        ep.id,
        epTitle:     ep.title,
        epNumber:    ep.number,
        seasonNum:   SEASON_NUM,
        seasonTitle: SEASON_TITLE,
        date:        new Date().toISOString().slice(0, 10),
    });
    if (quotes.length > 200) quotes.length = 200;
    localStorage.setItem(QUOTES_KEY, JSON.stringify(quotes));
    document.getElementById('ep-qt-row')?.style.setProperty('display', 'none');
    _qok?.classList.add('show');
    setTimeout(_hideTip, 1400);
    window.getSelection()?.removeAllRanges();
}

function openBookmarkForm() {
    if (!_qtext) return;
    const form    = document.getElementById('ep-bm-form');
    const preview = document.getElementById('ep-bm-preview');
    if (!form) return;
    if (preview) preview.textContent = '"' + (_qtext.length > 90 ? _qtext.slice(0, 90) + '…' : _qtext) + '"';
    form.classList.add('show');
    setTimeout(() => document.getElementById('ep-bm-note')?.focus(), 50);
}

function closeBookmarkForm() {
    document.getElementById('ep-bm-form')?.classList.remove('show');
    const n = document.getElementById('ep-bm-note');
    if (n) n.value = '';
}

function saveBookmark() {
    if (!_qtext) return;
    const ep   = ALL_EPISODES[currentIdx];
    const note = document.getElementById('ep-bm-note')?.value.trim() || '';
    const bms  = getBookmarks();
    bms.unshift({
        id:          Date.now(),
        text:        _qtext,
        note:        note,
        epId:        ep.id,
        epTitle:     ep.title,
        epNumber:    ep.number,
        seasonNum:   SEASON_NUM,
        seasonTitle: SEASON_TITLE,
        date:        new Date().toISOString().slice(0, 10),
    });
    if (bms.length > 300) bms.length = 300;
    localStorage.setItem(BM_KEY, JSON.stringify(bms));
    closeBookmarkForm();
    const ok = document.getElementById('ep-bm-tip-ok');
    ok?.classList.add('show');
    setTimeout(_hideTip, 1400);
    window.getSelection()?.removeAllRanges();
}

// ══════════════════════════════════════════════
// FEATURE 6 — READING TIMER
// ══════════════════════════════════════════════
const READ_TIMES_KEY = 'tgj_read_times';
function getReadTimes() { try { return JSON.parse(localStorage.getItem(READ_TIMES_KEY) || '{}'); } catch { return {}; } }

let _timerEpId    = null;
let _timerStart   = null;   // Date.now() when timing (null = paused)
let _timerPrev    = 0;      // seconds from all previous sessions for this episode
let _timerSession = 0;      // seconds in current session (excluding live tick)

function _timerReset(epId) {
    _timerEpId    = epId;
    _timerPrev    = getReadTimes()[epId] || 0;
    _timerSession = 0;
    _timerStart   = document.hidden ? null : Date.now();
}

function timerSwitch(epId) {
    // Flush current episode
    if (_timerStart !== null) { _timerSession += (Date.now() - _timerStart) / 1000; _timerStart = null; }
    if (_timerEpId !== null && _timerSession > 0) {
        const t = getReadTimes(); t[_timerEpId] = _timerPrev + Math.round(_timerSession);
        localStorage.setItem(READ_TIMES_KEY, JSON.stringify(t));
    }
    _timerReset(epId);
    _updateReadChip();
}

function _timerPause() {
    if (_timerStart !== null) { _timerSession += (Date.now() - _timerStart) / 1000; _timerStart = null; }
    _timerFlush();
}

function _timerResume() {
    if (_timerStart === null && _timerEpId !== null) _timerStart = Date.now();
}

function _timerFlush() {
    if (_timerEpId === null) return;
    const secs = _timerSession + (_timerStart ? (Date.now() - _timerStart) / 1000 : 0);
    const t = getReadTimes(); t[_timerEpId] = _timerPrev + Math.round(secs);
    localStorage.setItem(READ_TIMES_KEY, JSON.stringify(t));
}

function _timerSessionSecs() {
    return _timerSession + (_timerStart !== null ? (Date.now() - _timerStart) / 1000 : 0);
}

function formatReadTime(secs) {
    secs = Math.floor(secs);
    if (secs < 60) return secs + ' sec';
    const m = Math.floor(secs / 60), s = secs % 60;
    return s > 0 ? `${m} min ${s} sec` : `${m} min`;
}

function _updateReadChip() {
    const chip = document.getElementById('ep-read-time-chip');
    if (!chip) return;
    if (_timerPrev >= 30) {
        chip.textContent = '📚 Eerder gelezen in ' + formatReadTime(_timerPrev);
        chip.style.display = '';
    } else {
        chip.style.display = 'none';
    }
}

// Live badge — updates every second
setInterval(() => {
    const badge = document.getElementById('ep-timer-badge');
    if (!badge) return;
    const secs = _timerSessionSecs();
    if (secs < 4) { badge.classList.remove('show'); return; }
    badge.textContent = '⏱ ' + formatReadTime(secs);
    badge.classList.add('show');
}, 1000);

document.addEventListener('visibilitychange', () => { document.hidden ? _timerPause() : _timerResume(); });
window.addEventListener('pagehide', _timerFlush);
window.addEventListener('beforeunload', _timerFlush);

// Init on page load
_timerReset(ALL_EPISODES[currentIdx].id);
_updateReadChip();
</script>

<div class="ep-timer-badge" id="ep-timer-badge"></div>
<div class="ep-quote-tip" id="ep-quote-tip">
    <div class="ep-qt-row" id="ep-qt-row">
        <span style="font-size:.82rem;">💬</span>
        <button class="ep-quote-save-btn" id="ep-quote-save-btn" onclick="saveSelectedQuote()">Quote</button>
        <span class="ep-qt-sep">|</span>
        <span style="font-size:.82rem;">📌</span>
        <button class="ep-bm-open-btn" onclick="openBookmarkForm()">Bookmark</button>
    </div>
    <span class="ep-qt-confirm" id="ep-quote-tip-ok">✓ Quote opgeslagen!</span>
    <span class="ep-qt-confirm" id="ep-bm-tip-ok">📌 Bookmark opgeslagen!</span>
    <div class="ep-bm-form" id="ep-bm-form">
        <div class="ep-bm-preview" id="ep-bm-preview"></div>
        <textarea class="ep-bm-textarea" id="ep-bm-note" placeholder="Jouw notitie (optioneel)…" rows="3"></textarea>
        <div class="ep-bm-row">
            <button class="ep-bm-cancel" onclick="closeBookmarkForm()">Annuleer</button>
            <button class="ep-bm-save" onclick="saveBookmark()">📌 Opslaan</button>
        </div>
    </div>
</div>
</body>
</html>
