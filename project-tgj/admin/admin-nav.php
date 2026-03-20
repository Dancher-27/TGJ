<?php $cur = basename($_SERVER['PHP_SELF']); ?>
<nav class="admin-nav">
    <a href="index.php" class="admin-brand">⚔ TGJ Admin</a>
    <div class="admin-nav-links">
        <a href="index.php"      class="<?= $cur==='index.php'      ?'active':'' ?>">Dashboard</a>
        <a href="characters.php" class="<?= $cur==='characters.php' ?'active':'' ?>">Characters</a>
        <a href="factions.php"   class="<?= $cur==='factions.php'   ?'active':'' ?>">Facties</a>
        <a href="timeline.php"   class="<?= $cur==='timeline.php'   ?'active':'' ?>">Tijdlijn</a>
        <a href="arcs.php"       class="<?= $cur==='arcs.php'       ?'active':'' ?>">Arcs</a>
        <a href="seasons.php"    class="<?= $cur==='seasons.php'    ?'active':'' ?>">Seizoenen</a>
        <a href="lore.php"       class="<?= $cur==='lore.php'       ?'active':'' ?>">Lore</a>
        <a href="gallery.php"    class="<?= $cur==='gallery.php'    ?'active':'' ?>">Gallery</a>
        <a href="../index.php" target="_blank" style="margin-left:auto;opacity:.6;">🌐 Site</a>
        <a href="logout.php" style="color:#f87171;">Uitloggen</a>
    </div>
</nav>
