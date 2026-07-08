<?php
/**
 * Global AJAX search endpoint
 * Returns JSON: { results: [ {type, id, title, sub, url, icon} ] }
 */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once 'includes/connection.php';

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit();
}

// Sanitise for LIKE (escape % and _)
$safe = $conn->real_escape_string($q);
$like = "%$safe%";

$results = [];
$limit   = 5; // max per category

// ── Characters ──
$stmt = $conn->prepare("SELECT id, name, alias, role, image FROM characters WHERE name LIKE ? OR alias LIKE ? LIMIT ?");
$stmt->bind_param("ssi", $like, $like, $limit);
$stmt->execute();
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $results[] = [
        'type'  => 'character',
        'id'    => (int)$r['id'],
        'title' => $r['name'],
        'sub'   => $r['alias'] ? '"'.$r['alias'].'"' : ucfirst($r['role']),
        'url'   => 'character.php?id=' . $r['id'],
        'icon'  => '👤',
        'image' => $r['image'] ? 'uploads/characters/' . $r['image'] : null,
    ];
}

// ── Episodes (published only) ──
$stmt = $conn->prepare("
    SELECT e.id, e.number, e.title, e.description, s.number AS sn, s.title AS st
    FROM episodes e JOIN seasons s ON s.id = e.season_id
    WHERE e.status = 'published' AND (e.title LIKE ? OR e.description LIKE ?)
    LIMIT ?
");
$stmt->bind_param("ssi", $like, $like, $limit);
$stmt->execute();
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $results[] = [
        'type'  => 'episode',
        'id'    => (int)$r['id'],
        'title' => 'Afl. ' . $r['number'] . ': ' . $r['title'],
        'sub'   => 'S' . $r['sn'] . ' — ' . $r['st'],
        'url'   => 'episode.php?id=' . $r['id'],
        'icon'  => '📖',
        'image' => null,
    ];
}

// ── Lore ──
$stmt = $conn->prepare("SELECT id, title, category, content FROM lore WHERE title LIKE ? OR content LIKE ? LIMIT ?");
$stmt->bind_param("ssi", $like, $like, $limit);
$stmt->execute();
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $results[] = [
        'type'  => 'lore',
        'id'    => (int)$r['id'],
        'title' => $r['title'],
        'sub'   => ucfirst($r['category'] ?? 'Lore'),
        'url'   => 'lore.php#lore-' . $r['id'],
        'icon'  => '📜',
        'image' => null,
    ];
}

// ── Timeline events ──
$stmt = $conn->prepare("SELECT id, title, category, event_year FROM timeline_events WHERE title LIKE ? LIMIT ?");
$stmt->bind_param("si", $like, $limit);
$stmt->execute();
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $results[] = [
        'type'  => 'event',
        'id'    => (int)$r['id'],
        'title' => $r['title'],
        'sub'   => ucfirst($r['category'] ?? '') . ($r['event_year'] ? ' · Jaar ' . $r['event_year'] : ''),
        'url'   => 'timeline.php',
        'icon'  => '⚔️',
        'image' => null,
    ];
}

// ── Seasons ──
$stmt = $conn->prepare("SELECT id, number, title, subtitle FROM seasons WHERE title LIKE ? OR subtitle LIKE ? LIMIT ?");
$stmt->bind_param("ssi", $like, $like, $limit);
$stmt->execute();
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $results[] = [
        'type'  => 'season',
        'id'    => (int)$r['id'],
        'title' => 'Seizoen ' . $r['number'] . ': ' . $r['title'],
        'sub'   => $r['subtitle'] ?: 'Seizoen',
        'url'   => 'seasons.php#sn-' . $r['id'],
        'icon'  => '📺',
        'image' => null,
    ];
}

echo json_encode(['results' => $results, 'query' => $q], JSON_UNESCAPED_UNICODE);
