<?php
/**
 * The site's line-icon set: one 24 px grid, 1.7 px strokes, round joins. Each glyph is a small part
 * drawn the way the rest of the drafting layer is (a plate, a pin, a gauge), in the current text
 * colour, with at most one stroke in the birch-gold accent (`.ic-a`) marking the part that moves or
 * is pressed. They replace the Unicode characters the /more cards and the 404 page used as icons,
 * which rendered from whichever fallback font the visitor's system happened to pick.
 *
 * Usage: <?= ui_icon('archive') ?>   (decorative: aria-hidden; the card's heading names the link)
 * Styling lives in assets/css/blueprint.css (.ui-icon, .ic-a, .ic-dot).
 */
if (!function_exists('ui_icon_paths')) {
    function ui_icon_paths(): array
    {
        return [
            // Project archive: a two-drawer cabinet; the pulls are the accent
            'archive' => '<rect x="3.5" y="4.5" width="17" height="15" rx="2.2"/><path d="M3.5 12h17"/><path class="ic-a" d="M10 8.2h4M10 15.8h4"/>',
            // Journal: a bound notebook with a ruled page
            'journal' => '<path d="M6.5 3.5h9.8a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H6.5z"/><path d="M9.4 3.5v17"/><path d="M12.4 8h3.4M12.4 11.2h3.4"/><path class="ic-a" d="M12.4 14.4h2"/>',
            // YunoBot: a chip on its pins
            'bot' => '<rect x="6.5" y="6.5" width="11" height="11" rx="2.2"/><path d="M9.5 3.5v3M14.5 3.5v3M9.5 17.5v3M14.5 17.5v3M3.5 9.5h3M3.5 14.5h3M17.5 9.5h3M17.5 14.5h3"/><circle class="ic-a" cx="12" cy="12" r="1.9"/>',
            // About & CV: an ID plate
            'profile' => '<rect x="3.5" y="5.5" width="17" height="13" rx="2.4"/><circle cx="9" cy="11" r="2.1"/><path d="M5.8 16c.6-2 2-2.9 3.2-2.9s2.6.9 3.2 2.9"/><path d="M14.2 10h3.6"/><path class="ic-a" d="M14.2 13.4h2.4"/>',
            // Claim Splitter: one node branching into two
            'split' => '<circle class="ic-a" cx="5.8" cy="12" r="2"/><circle cx="18.2" cy="6.6" r="2"/><circle cx="18.2" cy="17.4" r="2"/><path d="M7.8 12h3c2.4 0 2.6-5.4 5.4-5.4M10.8 12c2.4 0 2.6 5.4 5.4 5.4"/>',
            // Chessko: a pawn on its base
            'pawn' => '<circle cx="12" cy="7.4" r="2.6"/><path d="M9.6 11.6h4.8"/><path d="M10.3 11.6c0 3-1.4 4.2-2.7 6.1h8.8c-1.3-1.9-2.7-3.1-2.7-6.1"/><path class="ic-a" d="M6.2 20h11.6"/>',
            // Jello Shop: a mug with steam
            'mug' => '<path d="M4.5 10h11v4.6a4.9 4.9 0 0 1-4.9 4.9H9.4a4.9 4.9 0 0 1-4.9-4.9z"/><path d="M15.5 11.4h1.3a2.6 2.6 0 0 1 0 5.2h-1.3"/><path class="ic-a" d="M8.6 3.4c-1.2 1.1 1.2 2.1 0 3.5M12 3.4c-1.2 1.1 1.2 2.1 0 3.5"/><path d="M3.5 21.6h13"/>',
            // Post-Code: angle brackets with the slash in red
            'code' => '<path d="M8.6 7.6 4 12l4.6 4.4M15.4 7.6 20 12l-4.6 4.4"/><path class="ic-a" d="M13.4 5.6 10.6 18.4"/>',
            // Science Corner: three orbits round a nucleus
            'atom' => '<ellipse cx="12" cy="12" rx="9" ry="3.6"/><ellipse cx="12" cy="12" rx="9" ry="3.6" transform="rotate(60 12 12)"/><ellipse cx="12" cy="12" rx="9" ry="3.6" transform="rotate(120 12 12)"/><circle class="ic-a" cx="12" cy="12" r="1.5"/>',
            // Music: level bars on a baseline
            'levels' => '<path d="M5.5 19.5v-6M10 19.5V6.5M14.5 19.5v-9M19 19.5v-4"/><path class="ic-a" d="M10 19.5V6.5"/><path d="M3.5 19.5h17"/>',
            // Comedy: a speech bubble with a grin
            'bubble' => '<path d="M5 5.5h14a1.8 1.8 0 0 1 1.8 1.8v8a1.8 1.8 0 0 1-1.8 1.8h-6.2l-4.4 3.5v-3.5H5a1.8 1.8 0 0 1-1.8-1.8v-8A1.8 1.8 0 0 1 5 5.5z"/><path class="ic-a" d="M8.8 11.4c.8 1.8 2 2.6 3.2 2.6s2.4-.8 3.2-2.6"/><circle class="ic-dot" cx="9.4" cy="9" r=".8"/><circle class="ic-dot" cx="14.6" cy="9" r=".8"/>',
            // Videos: a frame with the play head
            'play' => '<rect x="3" y="5.5" width="18" height="13" rx="2.6"/><path class="ic-a" d="M10.2 9.2v5.6l4.8-2.8z"/>',
            // Random Memories: a cassette
            'tape' => '<rect x="3" y="6" width="18" height="12.5" rx="2.4"/><circle cx="8.6" cy="11.6" r="1.9"/><circle class="ic-a" cx="15.4" cy="11.6" r="1.9"/><path d="M8 18.5l1.3-3h5.4l1.3 3"/>',
            // Downloads: an arrow into a tray
            'download' => '<path d="M12 4v10"/><path class="ic-a" d="M7.8 10.4 12 14.6l4.2-4.2"/><path d="M4.5 15.5v3a1.5 1.5 0 0 0 1.5 1.5h12a1.5 1.5 0 0 0 1.5-1.5v-3"/>',
            // 3D pages: a cube
            'cube' => '<path d="M12 3.2 19.6 7.5v9L12 20.8 4.4 16.5v-9z"/><path d="M4.4 7.5 12 11.8l7.6-4.3"/><path class="ic-a" d="M12 11.8v9"/>',
            // The Book: a hardback
            'book' => '<path d="M6.5 3.5h11A1.5 1.5 0 0 1 19 5v12H8a1.5 1.5 0 0 0-1.5 1.5z"/><path d="M6.5 18.5A1.5 1.5 0 0 0 8 20h11"/><path class="ic-a" d="M10.5 8h5"/>',
            // Home: a roof over a door
            'home' => '<path d="M4 11.2 12 4.6l8 6.6"/><path d="M6 10v9.5h12V10"/><path class="ic-a" d="M10.2 19.5v-5h3.6v5"/>',
            // Everything else: a 2x2 module grid
            'grid' => '<rect x="4" y="4" width="6.5" height="6.5" rx="1.4"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1.4"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1.4"/><rect class="ic-a" x="13.5" y="13.5" width="6.5" height="6.5" rx="1.4"/>',
        ];
    }
}

if (!function_exists('ui_icon')) {
    function ui_icon(string $name, string $class = ''): string
    {
        $icons = ui_icon_paths();
        if (!isset($icons[$name])) {
            return '';
        }
        $class = trim('ui-icon ' . preg_replace('/[^a-z0-9 _-]/i', '', $class));
        return '<svg class="' . $class . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $icons[$name] . '</svg>';
    }
}
