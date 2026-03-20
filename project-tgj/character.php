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

$img = !empty($char['image']) ? 'uploads/characters/' . $char['image'] : '';
$hasImg = $img && file_exists(__DIR__ . '/' . $img);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($char['name']) ?> — The Greatest Journey</title>
    <?php include 'includes/fonts.php'; ?>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="page">
<?php include 'includes/navbar.php'; ?>


<div class="container" style="position:relative;z-index:1;">
    <div style="padding-top:40px;">
        <a href="characters.php" style="color:var(--muted);text-decoration:none;font-size:.82rem;">← Terug naar characters</a>
    </div>

    <div class="char-hero">
        <!-- Portrait -->
        <div class="char-portrait-wrap">
            <?php if ($hasImg): ?>
                <img src="<?= htmlspecialchars($img) ?>" class="char-portrait" alt="<?= htmlspecialchars($char['name']) ?>">
            <?php else: ?>
                <div class="char-portrait-ph">⚔</div>
            <?php endif; ?>

            <?php if ($char['faction_name']): ?>
            <div style="position:absolute;bottom:-16px;left:0;right:0;text-align:center;">
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

            <div class="char-detail-badges">
                <span class="detail-badge <?= $roleColors[$char['role']] ?? 'db-gray' ?>"><?= $roleLabels[$char['role']] ?? $char['role'] ?></span>
                <span class="detail-badge <?= $statusColors[$char['status']] ?? 'db-gray' ?>"><?= $statusLabels[$char['status']] ?></span>
                <?php if ($char['age']): ?>
                <span class="detail-badge db-gray">Leeftijd: <?= htmlspecialchars($char['age']) ?></span>
                <?php endif; ?>
            </div>

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
</script>
</body>
</html>
