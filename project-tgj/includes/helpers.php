<?php
// ── Smart databook description parser ────────────────────────────────────────
// Handles: "Key: Value" (same line), "Key:\nValue" (next line),
//          "• Stat: 85\nDescription", section headers, bullet lists.
// Multibyte-safe: uses substr(...,0,3) for the 3-byte UTF-8 bullet '•'.

function renderDescription(string $text): string {

    $BULLET = "\xe2\x80\xa2"; // UTF-8 bytes of •  (U+2022)

    // Strip a leading bullet and spaces, return [isBullet, content]
    $stripBullet = function(string $line) use ($BULLET): array {
        if (substr($line, 0, 3) === $BULLET) {
            return [true, ltrim(substr($line, 3))];
        }
        if (substr($line, 0, 2) === '- ') {
            return [true, substr($line, 2)];
        }
        return [false, $line];
    };

    // True if line is "structured" (bullet, header, or stat) — not a plain value
    $isStructured = function(string $t) use ($BULLET): bool {
        if ($t === '') return false;
        if (substr($t, 0, 3) === $BULLET) return true;
        if (substr($t, 0, 2) === '- ')    return true;
        // Key:  or  Key: number
        if (preg_match('/^[A-Za-z][A-Za-z\s&\/\-]+:\s*(\d+)?\s*$/', $t)) return true;
        return false;
    };

    // Normalise line endings, split, rtrim each line
    $lines = array_map('rtrim', explode("\n",
        str_replace(["\r\n", "\r"], "\n", $text)
    ));
    $n    = count($lines);
    $html = '';
    $i    = 0;

    // Returns [index, trimmedText] of next non-empty line from $from, or null
    $nextNonEmpty = function(int $from) use ($lines, $n): ?array {
        for ($j = $from; $j < $n; $j++) {
            $t = trim($lines[$j]);
            if ($t !== '') return [$j, $t];
        }
        return null;
    };

    while ($i < $n) {
        $line = trim($lines[$i]);

        if ($line === '') { $i++; continue; }

        [$isBullet, $content] = $stripBullet($line);

        // ── STAT LINE: "Strength: 85" or "• Strength: 85" ─────────────────────
        if (preg_match('/^([A-Za-z][A-Za-z\s&\/\-]+):\s*(\d{1,3})\s*$/', $content, $m)) {
            $label = trim($m[1]);
            $val   = (int) $m[2];
            $desc  = '';

            // Look ahead (skip empty lines) for a plain-text description
            $la = $nextNonEmpty($i + 1);
            if ($la !== null && !$isStructured($la[1])) {
                $desc = $la[1];
                $i    = $la[0]; // advance past description line
            }

            $pct   = min(100, max(0, $val));
            $color = $pct >= 90 ? '#7c3aed'
                   : ($pct >= 75 ? '#6d28d9'
                   : ($pct >= 55 ? '#f59e0b' : '#6b7280'));

            $html .= '<div class="dp-stat">';
            $html .= '<div class="dp-stat-head">'
                   . '<span class="dp-stat-name">' . htmlspecialchars($label) . '</span>'
                   . '<span class="dp-stat-num">'  . $val  . '</span>'
                   . '</div>';
            $html .= '<div class="dp-stat-track"><div class="dp-stat-fill"'
                   . ' style="--bar-w:' . $pct . '%;--bar-c:' . $color . ';"></div></div>';
            if ($desc !== '') {
                $html .= '<div class="dp-stat-desc">' . htmlspecialchars($desc) . '</div>';
            }
            $html .= '</div>';
            $i++; continue;
        }

        // ── "Key:" ONLY — value may be on the next non-empty line ──────────────
        if (preg_match('/^([A-Za-z][A-Za-z\s&\/\-]+):\s*$/', $content, $m)) {
            $label = trim($m[1]);
            $la    = $nextNonEmpty($i + 1);

            // If next line is plain text (not structured), pair them as key:value
            if ($la !== null && !$isStructured($la[1])) {
                $html .= '<div class="dp-kv">'
                       . '<span class="dp-key">' . htmlspecialchars($label)  . '</span>'
                       . '<span class="dp-val">' . htmlspecialchars($la[1]) . '</span>'
                       . '</div>';
                $i = $la[0] + 1; continue;
            }

            // Otherwise: section header
            $html .= '<div class="dp-section-head">' . htmlspecialchars($label) . '</div>';
            $i++; continue;
        }

        // ── "Key: Value" on same line ──────────────────────────────────────────
        if (preg_match('/^([A-Za-z][A-Za-z\s\/\-]+):\s*(.+)$/', $content, $m)) {
            $cls  = $isBullet ? 'dp-bullet dp-kv' : 'dp-kv';
            $html .= '<div class="' . $cls . '">'
                   . '<span class="dp-key">' . htmlspecialchars(trim($m[1])) . '</span>'
                   . '<span class="dp-val">' . htmlspecialchars(trim($m[2])) . '</span>'
                   . '</div>';
            $i++; continue;
        }

        // ── Plain bullet "• Some note" ─────────────────────────────────────────
        if ($isBullet) {
            $html .= '<div class="dp-bullet">' . htmlspecialchars($content) . '</div>';
            $i++; continue;
        }

        // ── Plain text ─────────────────────────────────────────────────────────
        $html .= '<p class="dp-plain">' . htmlspecialchars($line) . '</p>';
        $i++;
    }

    return $html;
}
