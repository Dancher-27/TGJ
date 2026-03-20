<?php $current = basename($_SERVER['PHP_SELF']); ?>
<nav class="tgj-nav">
    <a href="/Portofolio-opdrachten/project-tgj/index.php" class="tgj-brand">
        <span class="brand-emblem">⚔</span>
        <span class="brand-text">The Greatest Journey</span>
    </a>
    <div class="tgj-nav-links">
        <a href="/Portofolio-opdrachten/project-tgj/index.php"      class="<?= $current==='index.php'           ?'active':'' ?>">Home</a>
        <a href="/Portofolio-opdrachten/project-tgj/timeline.php"   class="<?= $current==='timeline.php'        ?'active':'' ?>">Tijdlijn</a>
        <a href="/Portofolio-opdrachten/project-tgj/seasons.php"    class="<?= $current==='seasons.php'         ?'active':'' ?>">Seizoenen</a>
        <a href="/Portofolio-opdrachten/project-tgj/characters.php" class="<?= in_array($current,['characters.php','character.php'])?'active':'' ?>">Characters</a>
        <a href="/Portofolio-opdrachten/project-tgj/lore.php"       class="<?= $current==='lore.php'            ?'active':'' ?>">Lore</a>
        <a href="/Portofolio-opdrachten/project-tgj/gallery.php"    class="<?= $current==='gallery.php'         ?'active':'' ?>">Gallery</a>
        <a href="/Portofolio-opdrachten/project-tgj/databook.php"  class="<?= $current==='databook.php'        ?'active':'' ?>">Databook</a>
    </div>
    <button class="nav-burger" id="navBurger" onclick="toggleMobileNav()">☰</button>
</nav>
<div class="tgj-mobile-menu" id="mobileMenu">
    <a href="/Portofolio-opdrachten/project-tgj/index.php">Home</a>
    <a href="/Portofolio-opdrachten/project-tgj/timeline.php">Tijdlijn</a>
    <a href="/Portofolio-opdrachten/project-tgj/seasons.php">Seizoenen</a>
    <a href="/Portofolio-opdrachten/project-tgj/characters.php">Characters</a>
    <a href="/Portofolio-opdrachten/project-tgj/lore.php">Lore</a>
    <a href="/Portofolio-opdrachten/project-tgj/gallery.php">Gallery</a>
    <a href="/Portofolio-opdrachten/project-tgj/databook.php">Databook</a>
</div>
<script>
function toggleMobileNav(){
    document.getElementById('mobileMenu').classList.toggle('open');
}
</script>
