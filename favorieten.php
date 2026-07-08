<?php
require_once 'includes/connection.php';

// Fetch all characters and episodes at once — JS will filter by saved IDs
$allChars = $conn->query("
    SELECT c.id, c.name, c.alias, c.role, c.image, f.name AS faction_name, f.color AS faction_color
    FROM characters c LEFT JOIN factions f ON f.id = c.faction_id
    ORDER BY c.name ASC
")->fetch_all(MYSQLI_ASSOC);

$allEps = $conn->query("
    SELECT e.id, e.number, e.title, e.description, e.reading_time,
           s.number AS snum, s.title AS stitle, s.id AS sid
    FROM episodes e JOIN seasons s ON s.id = e.season_id
    WHERE e.status = 'published'
    ORDER BY s.number ASC, e.sort_order ASC, e.number ASC
")->fetch_all(MYSQLI_ASSOC);

$roleLabels = ['protagonist'=>'Protagonist','antagonist'=>'Antagonist','supporting'=>'Bijrol','villain'=>'Schurk','anti-hero'=>'Anti-held'];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mijn Favorieten — The Greatest Journey</title>
    <?php include 'includes/fonts.php'; ?>
    <link rel="stylesheet" href="styles.css">
    <style>
    .fav-page { padding: 40px 0 100px; }

    /* tabs */
    .fav-tabs {
        display: flex; gap: 4px;
        border-bottom: 2px solid var(--border);
        margin-bottom: 32px;
    }
    .fav-tab {
        padding: 10px 22px; border: none; background: transparent;
        font-family: var(--font-display); font-size: .82rem; font-weight: 700;
        color: var(--muted); border-radius: 10px 10px 0 0; cursor: pointer;
        transition: all .15s; border-bottom: 3px solid transparent; margin-bottom: -2px;
    }
    .fav-tab:hover  { color: var(--text); background: var(--surface); }
    .fav-tab.active { color: var(--primary); border-bottom-color: var(--primary); background: var(--card); }
    .fav-tab .fav-count {
        display: inline-flex; align-items: center; justify-content: center;
        background: var(--primary); color: #fff;
        font-size: .6rem; font-weight: 800;
        min-width: 18px; height: 18px; border-radius: 100px;
        padding: 0 5px; margin-left: 6px;
    }

    .fav-panel        { display: none; }
    .fav-panel.active { display: block; }

    /* empty state */
    .fav-empty {
        text-align: center; padding: 80px 0; color: var(--muted);
    }
    .fav-empty-icon { font-size: 3rem; margin-bottom: 14px; }
    .fav-empty-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 8px; color: var(--text); }
    .fav-empty-sub { font-size: .88rem; }

    /* character grid — reuse char-card from styles.css */
    .fav-chars-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 18px;
    }

    /* episode list */
    .fav-ep-list { display: flex; flex-direction: column; gap: 10px; }
    .fav-ep-card {
        display: grid; grid-template-columns: 52px 1fr auto;
        gap: 0 16px; align-items: center;
        background: var(--card); border: 1px solid var(--border);
        border-radius: 14px; padding: 14px 18px;
        text-decoration: none; color: var(--text);
        transition: all .18s; position: relative;
    }
    .fav-ep-card:hover { background: var(--primary-light); border-color: var(--primary); transform: translateX(4px); }
    .fav-ep-num {
        width: 44px; height: 44px; border-radius: 10px;
        background: linear-gradient(135deg, var(--primary), #6d28d9);
        display: flex; align-items: center; justify-content: center;
        flex-direction: column; flex-shrink: 0;
    }
    .fav-ep-num span:first-child { font-size: .48rem; font-weight: 800; color: rgba(255,255,255,.65); text-transform: uppercase; }
    .fav-ep-num span:last-child  { font-size: 1rem; font-weight: 900; color: #fff; font-family: var(--font-display); }
    .fav-ep-info { min-width: 0; }
    .fav-ep-title { font-size: .9rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .fav-ep-sub   { font-size: .72rem; color: var(--muted); margin-top: 3px; }
    .fav-ep-time  { font-size: .68rem; font-weight: 700; color: var(--primary); background: var(--primary-light); border: 1px solid var(--border); border-radius: 100px; padding: 2px 10px; white-space: nowrap; flex-shrink: 0; }

    /* unfav button on each card */
    .fav-remove-btn {
        position: absolute; top: 8px; right: 8px;
        background: none; border: none; font-size: 1rem;
        cursor: pointer; opacity: .4; transition: opacity .15s;
        line-height: 1; padding: 4px;
    }
    .fav-remove-btn:hover { opacity: 1; }

    /* Quote cards */
    .fav-quote-list { display: flex; flex-direction: column; gap: 12px; }
    .fav-quote-card {
        background: var(--card); border: 1px solid var(--border);
        border-radius: 14px; padding: 18px 20px 14px; position: relative;
        transition: border-color .15s;
    }
    .fav-quote-card:hover { border-color: var(--primary); }
    .fav-quote-text {
        font-size: .9rem; line-height: 1.8; color: var(--text);
        font-style: italic; margin-bottom: 10px;
        padding-left: 14px; border-left: 3px solid var(--primary);
        padding-right: 24px;
    }
    .fav-quote-meta {
        display: flex; gap: 8px; flex-wrap: wrap; align-items: center;
        font-size: .7rem; color: var(--muted);
    }
    .fav-quote-ep {
        background: var(--primary-light); color: var(--primary);
        border: 1px solid rgba(124,58,237,.25); border-radius: 100px;
        padding: 2px 10px; font-weight: 700; font-size: .68rem;
        text-decoration: none;
    }
    .fav-quote-ep:hover { background: rgba(124,58,237,.25); }
    .fav-quote-copy {
        background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1);
        color: rgba(255,255,255,.5); border-radius: 6px;
        font-size: .65rem; font-weight: 700; padding: 2px 8px; cursor: pointer;
        transition: all .12s; margin-left: auto;
    }
    .fav-quote-copy:hover { background: rgba(255,255,255,.12); color: #fff; }

    /* Bookmark cards */
    .fav-bm-list { display: flex; flex-direction: column; gap: 12px; }
    .fav-bm-card {
        background: var(--card); border: 1px solid var(--border);
        border-radius: 14px; padding: 18px 20px 14px; position: relative;
        transition: border-color .15s;
    }
    .fav-bm-card:hover { border-color: rgba(245,158,11,.4); }
    .fav-bm-text {
        font-size: .88rem; line-height: 1.75; color: rgba(255,255,255,.65);
        font-style: italic; margin-bottom: 8px;
        padding-left: 14px; border-left: 3px solid #f59e0b;
        padding-right: 24px;
    }
    .fav-bm-note {
        font-size: .82rem; color: var(--text); line-height: 1.5;
        margin-bottom: 10px; padding: 8px 12px;
        background: rgba(255,255,255,.04); border-radius: 8px;
        border-left: 2px solid rgba(255,255,255,.1);
    }
    .fav-bm-meta {
        display: flex; gap: 8px; flex-wrap: wrap; align-items: center;
        font-size: .7rem; color: var(--muted);
    }
    .fav-bm-ep {
        background: rgba(245,158,11,.1); color: #f59e0b;
        border: 1px solid rgba(245,158,11,.25); border-radius: 100px;
        padding: 2px 10px; font-weight: 700; font-size: .68rem;
        text-decoration: none;
    }
    .fav-bm-ep:hover { background: rgba(245,158,11,.2); }
    </style>
</head>
<body class="page">
<?php include 'includes/navbar.php'; ?>

<div class="container fav-page">
    <div class="section-header">
        <h1 class="section-title">❤️ Mijn Favorieten</h1>
        <p class="section-sub">Characters en afleveringen die je hebt geliked</p>
    </div>

    <div class="fav-tabs">
        <button class="fav-tab active" data-tab="chars">
            👤 Characters <span class="fav-count" id="count-chars">0</span>
        </button>
        <button class="fav-tab" data-tab="eps">
            📖 Afleveringen <span class="fav-count" id="count-eps">0</span>
        </button>
        <button class="fav-tab" data-tab="quotes">
            💬 Quotes <span class="fav-count" id="count-quotes">0</span>
        </button>
        <button class="fav-tab" data-tab="bookmarks">
            📌 Bookmarks <span class="fav-count" id="count-bookmarks">0</span>
        </button>
    </div>

    <!-- CHARACTERS PANEL -->
    <div class="fav-panel active" id="panel-chars">
        <div class="fav-empty" id="empty-chars">
            <div class="fav-empty-icon">💔</div>
            <div class="fav-empty-title">Nog geen favoriete characters</div>
            <div class="fav-empty-sub">Klik op het ❤ hartje op een characterkaart om hem toe te voegen.</div>
        </div>
        <div class="fav-chars-grid" id="grid-chars"></div>
    </div>

    <!-- EPISODES PANEL -->
    <div class="fav-panel" id="panel-eps">
        <div class="fav-empty" id="empty-eps">
            <div class="fav-empty-icon">📭</div>
            <div class="fav-empty-title">Nog geen favoriete afleveringen</div>
            <div class="fav-empty-sub">Klik op het ❤ hartje in de reader of op de seizoenspagina.</div>
        </div>
        <div class="fav-ep-list" id="list-eps"></div>
    </div>

    <!-- QUOTES PANEL -->
    <div class="fav-panel" id="panel-quotes">
        <div class="fav-empty" id="empty-quotes">
            <div class="fav-empty-icon">💬</div>
            <div class="fav-empty-title">Nog geen opgeslagen quotes</div>
            <div class="fav-empty-sub">Selecteer tekst in de reader en klik op "Quote opslaan".</div>
        </div>
        <div class="fav-quote-list" id="list-quotes"></div>
    </div>

    <!-- BOOKMARKS PANEL -->
    <div class="fav-panel" id="panel-bookmarks">
        <div class="fav-empty" id="empty-bookmarks">
            <div class="fav-empty-icon">📌</div>
            <div class="fav-empty-title">Nog geen bookmarks</div>
            <div class="fav-empty-sub">Selecteer tekst in de reader en klik op "Bookmark" om een passage op te slaan met notitie.</div>
        </div>
        <div class="fav-bm-list" id="list-bookmarks"></div>
    </div>
</div>

<footer class="tgj-footer"><strong>The Greatest Journey</strong></footer>

<!-- All data embedded for JS to filter -->
<script>
const ALL_CHARS = <?= json_encode(array_map(fn($c) => [
    'id'           => (int)$c['id'],
    'name'         => $c['name'],
    'alias'        => $c['alias'] ?? '',
    'role'         => $c['role'],
    'image'        => $c['image'] ? 'uploads/characters/' . $c['image'] : '',
    'faction_name' => $c['faction_name'] ?? '',
    'faction_color'=> $c['faction_color'] ?? '#7c3aed',
], $allChars), JSON_UNESCAPED_UNICODE) ?>;

const ALL_EPS = <?= json_encode(array_map(fn($e) => [
    'id'          => (int)$e['id'],
    'number'      => (int)$e['number'],
    'title'       => $e['title'],
    'description' => $e['description'] ?? '',
    'reading_time'=> (int)($e['reading_time'] ?? 0),
    'snum'        => (int)$e['snum'],
    'stitle'      => $e['stitle'],
], $allEps), JSON_UNESCAPED_UNICODE) ?>;

const ROLE_LABELS = <?= json_encode($roleLabels) ?>;

const FAV_KEY_CHARS = 'tgj_fav_chars';
const FAV_KEY_EPS   = 'tgj_fav_eps';

function getFavs(key) {
    try { return JSON.parse(localStorage.getItem(key) || '[]'); } catch { return []; }
}
function removeFav(key, id) {
    const arr = getFavs(key).filter(x => x !== id);
    localStorage.setItem(key, JSON.stringify(arr));
}

function escH(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function renderChars() {
    const ids   = getFavs(FAV_KEY_CHARS);
    const chars = ALL_CHARS.filter(c => ids.includes(c.id));
    const grid  = document.getElementById('grid-chars');
    const empty = document.getElementById('empty-chars');
    document.getElementById('count-chars').textContent = chars.length;

    if (!chars.length) { grid.innerHTML = ''; empty.style.display = ''; return; }
    empty.style.display = 'none';

    grid.innerHTML = chars.map(c => {
        const imgHtml = c.image
            ? `<img src="${escH(c.image)}" alt="${escH(c.name)}" loading="lazy">`
            : `<div class="char-cover-ph">⚔</div>`;
        return `
        <div style="position:relative;">
            <a href="character.php?id=${c.id}" class="char-card" style="display:flex;flex-direction:column;">
                <div class="char-cover">
                    ${imgHtml}
                    <div class="char-role-badge role-${escH(c.role)}">${escH(ROLE_LABELS[c.role] || c.role)}</div>
                </div>
                <div class="char-info">
                    <div class="char-name">${escH(c.name)}</div>
                    ${c.alias ? `<div class="char-alias">"${escH(c.alias)}"</div>` : ''}
                    ${c.faction_name ? `<div class="char-faction"><div class="faction-dot" style="background:${escH(c.faction_color)}"></div>${escH(c.faction_name)}</div>` : ''}
                </div>
            </a>
            <button class="fav-remove-btn" title="Verwijder uit favorieten" onclick="removeChar(${c.id})">💔</button>
        </div>`;
    }).join('');
}

function removeChar(id) {
    removeFav(FAV_KEY_CHARS, id);
    renderChars();
}

function renderEps() {
    const ids = getFavs(FAV_KEY_EPS);
    const eps = ALL_EPS.filter(e => ids.includes(e.id));
    const list  = document.getElementById('list-eps');
    const empty = document.getElementById('empty-eps');
    document.getElementById('count-eps').textContent = eps.length;

    if (!eps.length) { list.innerHTML = ''; empty.style.display = ''; return; }
    empty.style.display = 'none';

    list.innerHTML = eps.map(e => `
        <a href="episode.php?id=${e.id}" class="fav-ep-card">
            <div class="fav-ep-num">
                <span>Afl.</span>
                <span>${e.number}</span>
            </div>
            <div class="fav-ep-info">
                <div class="fav-ep-title">${escH(e.title)}</div>
                <div class="fav-ep-sub">S${e.snum} — ${escH(e.stitle)}</div>
            </div>
            ${e.reading_time ? `<span class="fav-ep-time">⏱ ~${e.reading_time} min</span>` : ''}
            <button class="fav-remove-btn" title="Verwijder uit favorieten"
                    onclick="event.preventDefault();removeEp(${e.id})">💔</button>
        </a>`
    ).join('');
}

function removeEp(id) {
    removeFav(FAV_KEY_EPS, id);
    renderEps();
}

const QUOTES_KEY = 'tgj_quotes';
function getQuotes() { try { return JSON.parse(localStorage.getItem(QUOTES_KEY) || '[]'); } catch { return []; } }

function renderQuotes() {
    const quotes = getQuotes();
    const list   = document.getElementById('list-quotes');
    const empty  = document.getElementById('empty-quotes');
    document.getElementById('count-quotes').textContent = quotes.length;

    if (!quotes.length) { list.innerHTML = ''; empty.style.display = ''; return; }
    empty.style.display = 'none';

    list.innerHTML = quotes.map(q => `
        <div class="fav-quote-card">
            <div class="fav-quote-text">${escH(q.text)}</div>
            <div class="fav-quote-meta">
                <a class="fav-quote-ep" href="episode.php?id=${q.epId}">
                    S${q.seasonNum} · Afl. ${q.epNumber} — ${escH(q.epTitle)}
                </a>
                <span>${q.date || ''}</span>
                <button class="fav-quote-copy" onclick="copyQuote(${q.id})">📋 Kopieer</button>
            </div>
            <button class="fav-remove-btn" title="Verwijder quote" onclick="removeQuote(${q.id})">✕</button>
        </div>`
    ).join('');
}

function removeQuote(id) {
    const arr = getQuotes().filter(q => q.id !== id);
    localStorage.setItem(QUOTES_KEY, JSON.stringify(arr));
    renderQuotes();
}

function copyQuote(id) {
    const q = getQuotes().find(x => x.id === id);
    if (!q) return;
    const fallback = () => {
        const ta = document.createElement('textarea');
        ta.value = q.text; document.body.appendChild(ta);
        ta.select(); document.execCommand('copy'); ta.remove();
    };
    if (navigator.clipboard) {
        navigator.clipboard.writeText(q.text).catch(fallback);
    } else { fallback(); }
}

// Tabs
document.querySelectorAll('.fav-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.fav-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.fav-panel').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('panel-' + this.dataset.tab).classList.add('active');
    });
});

const BM_KEY = 'tgj_bookmarks';
function getBookmarks() { try { return JSON.parse(localStorage.getItem(BM_KEY) || '[]'); } catch { return []; } }

function renderBookmarks() {
    const bms   = getBookmarks();
    const list  = document.getElementById('list-bookmarks');
    const empty = document.getElementById('empty-bookmarks');
    document.getElementById('count-bookmarks').textContent = bms.length;

    if (!bms.length) { list.innerHTML = ''; empty.style.display = ''; return; }
    empty.style.display = 'none';

    list.innerHTML = bms.map(b => `
        <div class="fav-bm-card">
            <div class="fav-bm-text">${escH(b.text.length > 220 ? b.text.slice(0, 220) + '…' : b.text)}</div>
            ${b.note ? `<div class="fav-bm-note">📝 ${escH(b.note)}</div>` : ''}
            <div class="fav-bm-meta">
                <a class="fav-bm-ep" href="episode.php?id=${b.epId}">
                    S${b.seasonNum} · Afl. ${b.epNumber} — ${escH(b.epTitle)}
                </a>
                <span>${b.date || ''}</span>
            </div>
            <button class="fav-remove-btn" title="Verwijder bookmark" onclick="removeBookmark(${b.id})">✕</button>
        </div>`
    ).join('');
}

function removeBookmark(id) {
    const arr = getBookmarks().filter(b => b.id !== id);
    localStorage.setItem(BM_KEY, JSON.stringify(arr));
    renderBookmarks();
}

renderChars();
renderEps();
renderQuotes();
renderBookmarks();
</script>
</body>
</html>
