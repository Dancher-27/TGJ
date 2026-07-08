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
        <a href="/Portofolio-opdrachten/project-tgj/favorieten.php" class="<?= $current==='favorieten.php'     ?'active':'' ?>" id="nav-fav-link">❤ Favorieten</a>
    </div>

    <!-- Global search -->
    <div class="tgj-search-wrap" id="tgjSearchWrap">
        <button class="tgj-search-btn" id="tgjSearchBtn" onclick="tgjToggleSearch()" aria-label="Zoeken">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
        </button>
        <div class="tgj-search-box" id="tgjSearchBox">
            <input type="text" class="tgj-search-input" id="tgjSearchInput"
                placeholder="Zoek characters, afleveringen, lore…"
                autocomplete="off" spellcheck="false">
            <button class="tgj-search-close" onclick="tgjCloseSearch()">✕</button>
        </div>
        <div class="tgj-search-results" id="tgjSearchResults"></div>
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
    <a href="/Portofolio-opdrachten/project-tgj/favorieten.php">❤ Favorieten</a>
</div>

<style>
/* ── SEARCH WRAP ── */
.tgj-search-wrap {
    position: relative; flex-shrink: 0;
    display: flex; align-items: center;
}
@media (min-width: 769px) {
    .tgj-search-wrap { margin-left: 8px; }
}
@media (max-width: 768px) {
    .tgj-search-wrap { margin-left: auto; }
}
.tgj-search-btn {
    background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12);
    border-radius: 8px; width: 34px; height: 34px;
    display: flex; align-items: center; justify-content: center;
    color: rgba(255,255,255,.7); cursor: pointer;
    transition: all .15s; flex-shrink: 0;
}
.tgj-search-btn:hover { background: rgba(255,255,255,.16); color: #fff; }

.tgj-search-box {
    position: absolute; right: 0; top: 50%; transform: translateY(-50%);
    display: flex; align-items: center; gap: 6px;
    background: #1e1b4b; border: 1.5px solid rgba(124,58,237,.5);
    border-radius: 12px; padding: 0 8px 0 14px;
    width: 0; overflow: hidden; opacity: 0;
    transition: width .25s ease, opacity .2s ease;
    pointer-events: none; z-index: 300;
}
.tgj-search-box.open {
    width: min(320px, calc(100vw - 80px)); opacity: 1; pointer-events: all;
}
.tgj-search-input {
    flex: 1; background: transparent; border: none; outline: none;
    color: #fff; font-size: .85rem; font-family: inherit;
    padding: 9px 0; min-width: 0;
}
.tgj-search-input::placeholder { color: rgba(255,255,255,.35); }
.tgj-search-close {
    background: none; border: none; color: rgba(255,255,255,.4);
    cursor: pointer; font-size: .9rem; padding: 4px 6px;
    transition: color .15s; flex-shrink: 0;
}
.tgj-search-close:hover { color: #fff; }

/* ── RESULTS DROPDOWN ── */
.tgj-search-results {
    position: absolute; right: 0; top: calc(100% + 10px); z-index: 400;
    background: #1a1740; border: 1px solid rgba(124,58,237,.35);
    border-radius: 14px; width: 340px;
    box-shadow: 0 16px 48px rgba(0,0,0,.5);
    overflow: hidden; display: none;
}
.tgj-search-results.open { display: block; }
.sr-header {
    padding: 9px 14px 6px;
    font-size: .6rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase;
    color: rgba(255,255,255,.3);
}
.sr-item {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 14px; text-decoration: none;
    transition: background .12s; cursor: pointer;
}
.sr-item:hover { background: rgba(124,58,237,.18); }
.sr-icon {
    width: 32px; height: 32px; border-radius: 8px;
    background: rgba(124,58,237,.15);
    display: flex; align-items: center; justify-content: center;
    font-size: .9rem; flex-shrink: 0; overflow: hidden;
}
.sr-icon img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
.sr-body { flex: 1; min-width: 0; }
.sr-title { font-size: .82rem; font-weight: 700; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sr-sub   { font-size: .68rem; color: rgba(255,255,255,.4); margin-top: 1px; }
.sr-type  { font-size: .6rem; font-weight: 700; padding: 2px 7px; border-radius: 100px; flex-shrink: 0; }
.sr-empty, .sr-loading {
    padding: 20px 16px; text-align: center;
    font-size: .82rem; color: rgba(255,255,255,.35);
}
.sr-sep { height: 1px; background: rgba(255,255,255,.06); margin: 2px 0; }

@media (max-width: 768px) {
    .tgj-search-results { width: calc(100vw - 28px); max-width: 100vw; right: 0; left: auto; }
    .tgj-search-box.open { width: min(300px, calc(100vw - 90px)); }
}
</style>

<script>
function toggleMobileNav(){
    document.getElementById('mobileMenu').classList.toggle('open');
}

// ── SEARCH ──
let _tgjSearchTimer = null;
let _tgjSearchOpen  = false;

function tgjToggleSearch() {
    _tgjSearchOpen = !_tgjSearchOpen;
    const box = document.getElementById('tgjSearchBox');
    const inp = document.getElementById('tgjSearchInput');
    box.classList.toggle('open', _tgjSearchOpen);
    if (_tgjSearchOpen) { setTimeout(() => inp.focus(), 220); }
    else { tgjCloseSearch(); }
}

function tgjCloseSearch() {
    _tgjSearchOpen = false;
    document.getElementById('tgjSearchBox').classList.remove('open');
    document.getElementById('tgjSearchResults').classList.remove('open');
    document.getElementById('tgjSearchInput').value = '';
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') tgjCloseSearch();
    // Ctrl+K / Cmd+K to open search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); tgjToggleSearch(); }
});

document.addEventListener('click', e => {
    const wrap = document.getElementById('tgjSearchWrap');
    if (wrap && !wrap.contains(e.target)) tgjCloseSearch();
});

const typeColors = {
    character: '#7c3aed', episode: '#2563eb',
    lore: '#d97706', event: '#dc2626', season: '#0891b2'
};
const typeLabels = {
    character: 'Character', episode: 'Aflevering',
    lore: 'Lore', event: 'Event', season: 'Seizoen'
};

document.getElementById('tgjSearchInput')?.addEventListener('input', function() {
    clearTimeout(_tgjSearchTimer);
    const q = this.value.trim();
    const res = document.getElementById('tgjSearchResults');

    if (q.length < 2) { res.classList.remove('open'); return; }

    res.classList.add('open');
    res.innerHTML = '<div class="sr-loading">⏳ Zoeken…</div>';

    _tgjSearchTimer = setTimeout(async () => {
        try {
            const base = document.querySelector('base')?.href || '/Portofolio-opdrachten/project-tgj/';
            const r = await fetch(`/Portofolio-opdrachten/project-tgj/search.php?q=${encodeURIComponent(q)}`);
            const data = await r.json();

            if (!data.results.length) {
                res.innerHTML = `<div class="sr-empty">Geen resultaten voor "<strong style="color:#fff">${escH(q)}</strong>"</div>`;
                return;
            }

            // Group by type
            const groups = {};
            data.results.forEach(item => {
                if (!groups[item.type]) groups[item.type] = [];
                groups[item.type].push(item);
            });

            let html = '';
            for (const [type, items] of Object.entries(groups)) {
                html += `<div class="sr-header">${typeLabels[type] || type}</div>`;
                items.forEach(item => {
                    const color = typeColors[type] || '#6b7280';
                    const iconContent = item.image
                        ? `<img src="/Portofolio-opdrachten/project-tgj/${escH(item.image)}" alt="">`
                        : item.icon;
                    html += `
                    <a class="sr-item" href="/Portofolio-opdrachten/project-tgj/${escH(item.url)}" onclick="tgjCloseSearch()">
                        <div class="sr-icon">${iconContent}</div>
                        <div class="sr-body">
                            <div class="sr-title">${escH(item.title)}</div>
                            ${item.sub ? `<div class="sr-sub">${escH(item.sub)}</div>` : ''}
                        </div>
                        <span class="sr-type" style="background:${color}22;color:${color};border:1px solid ${color}44;">${typeLabels[type]}</span>
                    </a>`;
                });
                html += '<div class="sr-sep"></div>';
            }

            res.innerHTML = html;
        } catch(e) {
            res.innerHTML = '<div class="sr-empty">Fout bij zoeken. Probeer opnieuw.</div>';
        }
    }, 280);
});

function escH(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
