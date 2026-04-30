<?php
/**
 * Trait: Reports & Report Reasons pages
 */

if (!defined('ABSPATH')) {
    exit;
}

trait JG_Map_Admin_Reports {

    public function render_reports_page() {
        global $wpdb;
        $reports_table = JG_Map_Database::get_reports_table();
        $points_table = JG_Map_Database::get_points_table();

        $reports = $wpdb->get_results(
            "SELECT r.*, p.title as point_title, p.status as point_status, COUNT(r2.id) as report_count
            FROM $reports_table r
            LEFT JOIN $points_table p ON r.point_id = p.id
            LEFT JOIN $reports_table r2 ON r.point_id = r2.point_id AND r2.status = 'pending'
            WHERE r.status = 'pending' AND p.status = 'publish'
            GROUP BY r.point_id
            ORDER BY report_count DESC, r.created_at DESC",
            ARRAY_A
        );

        ?>
        <div class="wrap">
            <h1>Zgłoszenia miejsc</h1>

            <?php if (!empty($reports)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Miejsce</th>
                        <th>Liczba zgłoszeń</th>
                        <th>Ostatnie zgłoszenie</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td><strong><?php echo esc_html($report['point_title']); ?></strong></td>
                            <td><span style="background:#dc2626;color:#fff;padding:4px 8px;border-radius:4px"><?php echo $report['report_count']; ?></span></td>
                            <td><?php echo human_time_diff(strtotime($report['created_at'] . ' UTC'), time()); ?> temu</td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg('jg_view_reports', $report['point_id'], $this->get_map_page_url())); ?>" class="button">Zobacz szczegóły</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p>Brak zgłoszeń! 🎉</p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_report_reasons_page() {
        // Get current data
        $categories = JG_Map_Ajax_Handlers::get_category_groups();
        $reasons = JG_Map_Ajax_Handlers::get_report_categories();

        // Extended emoji list for picker - organized by category
        $common_emojis = array(
            // Infrastructure & Roads
            '📌', '🕳️', '🛣️', '🚶', '🚸', '💡', '🔧', '⚠️', '🔩', '🪛', '🔨', '⛏️', '🪚', '🧱', '🏗️',
            // Waste & Environment
            '🗑️', '♻️', '🧹', '🧺', '🪣', '🚮', '☢️', '☣️', '🌫️', '💨',
            // Art & Vandalism
            '🎨', '🖌️', '🖼️', '✏️', '🔏',
            // Nature & Greenery
            '🌳', '🌲', '🌴', '🌿', '🍀', '🍃', '🍂', '🍁', '🌾', '🌻', '🌺', '🌸', '🌷', '🌹', '💐', '🏞️', '🌱', '🪴', '🪻', '🪷',
            // Transport
            '🚦', '🚥', '🚏', '🚌', '🚎', '🚐', '🚗', '🚙', '🚕', '🚖', '🛻', '🚚', '🚛', '🚜', '🏎️', '🏍️', '🛵', '🚲', '🛴', '🚋', '🚃', '🚈', '🚇', '🚊', '🚝', '🚆', '🚂', '✈️', '🛫', '🛬', '🛩️', '🚁', '🚀', '🛶', '⛵', '🚤', '🛥️', '⛴️', '🚢',
            // Buildings & Places
            '🏢', '🏠', '🏡', '🏘️', '🏚️', '🏭', '🏬', '🏣', '🏤', '🏥', '🏦', '🏨', '🏩', '🏪', '🏫', '🏛️', '⛪', '🕌', '🕍', '🛕', '⛩️', '🏰', '🏯', '🗼', '🗽', '⛲', '🎡', '🎢', '🎠', '🎪',
            // Urban furniture
            '🪑', '🛋️', '🪞', '🚪', '🛗', '🪜', '🧳',
            // Water & Weather
            '💧', '💦', '🌊', '🌧️', '⛈️', '🌩️', '❄️', '☃️', '⛄', '🌨️', '🌪️', '🌈', '☀️', '🌤️', '⛅', '🌥️', '☁️', '🌦️',
            // Safety & Warning
            '🔴', '🟠', '🟡', '🟢', '🔵', '🟣', '⚫', '⚪', '🟤', '❗', '❓', '‼️', '⁉️', '🚨', '🔔', '🔕', '📢', '📣', '🆘', '🛑', '⛔', '🚫', '🚷', '🚳', '🚯', '🚱', '🚭',
            // Animals
            '🐕', '🐈', '🐦', '🐤', '🐧', '🦆', '🦅', '🦉', '🐝', '🦋', '🐛', '🐜', '🐞', '🦗', '🕷️', '🐀', '🐁', '🐿️', '🦔', '🦇',
            // Sport & Recreation
            '⚽', '🏀', '🏈', '⚾', '🎾', '🏐', '🏉', '🎱', '🏓', '🏸', '🥅', '⛳', '🏒', '🥊', '🎣', '🤿', '🎿', '⛷️', '🏂', '🛷', '⛸️', '🏋️', '🤸', '🧘', '🏃', '🚴',
            // Other useful
            '✨', '⭐', '🌟', '💫', '🔥', '💥', '🎵', '🎶', '🔊', '🔇', '📱', '💻', '🖥️', '⌨️', '🖱️', '🖨️', '📷', '📹', '📺', '📻', '🔦', '💰', '💳', '📦', '📫', '📮', '🗳️', '📋', '📝', '✅', '❎', '➕', '➖', '➗', '✖️', '💯', '🔢', '🔤', '🔠', '🔣', 'ℹ️', '🆕', '🆓', '🆙', '🆗', '🆒', '🆖', '📍', '🏁', '🎯', '💎', '🔑', '🗝️', '🔓', '🔒'
        );
        ?>
        <div class="wrap">
            <?php $this->render_page_header('Zarządzanie powodami zgłoszeń'); ?>

            <style>
                .jg-report-editor { display: grid; grid-template-columns: 1fr 2fr; gap: 30px; margin-top: 20px; }
                .jg-report-editor .card { background: #fff; padding: 18px 20px; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 1px 4px rgba(0,0,0,.06); margin-bottom: 20px; }
                .jg-report-editor h2 { margin-top: 0; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb; margin-bottom: 16px; font-size: 15px; font-weight: 700; color: #111827; }
                .jg-category-list, .jg-reason-list { list-style: none; padding: 0; margin: 0; }
                .jg-category-item, .jg-reason-item {
                    display: flex; align-items: center; gap: 10px; padding: 12px;
                    border: 1px solid #ddd; border-radius: 6px; margin-bottom: 8px;
                    background: #fafafa; transition: all 0.2s;
                }
                .jg-category-item:hover, .jg-reason-item:hover { background: #f0f0f0; border-color: #999; }
                .jg-category-item.active { background: #e3f2fd; border-color: #2196f3; }
                .jg-category-item .cat-name { flex: 1; font-weight: 500; cursor: pointer; }
                .jg-category-item .cat-count { background: #e0e0e0; padding: 2px 8px; border-radius: 10px; font-size: calc(12 * var(--jg)); }
                .jg-reason-item .reason-icon { font-size: calc(20 * var(--jg)); width: 30px; text-align: center; }
                .jg-reason-item .reason-label { flex: 1; }
                .jg-reason-item .reason-category { font-size: calc(11 * var(--jg)); color: #666; background: #eee; padding: 2px 6px; border-radius: 4px; }
                .jg-action-btn { background: none; border: none; cursor: pointer; padding: 4px 8px; border-radius: 4px; transition: background 0.2s; }
                .jg-action-btn:hover { background: #e0e0e0; }
                .jg-action-btn.delete:hover { background: #ffebee; color: #c62828; }
                .jg-add-form { display: none; padding: 15px; background: #f5f5f5; border-radius: 6px; margin-top: 15px; }
                .jg-add-form.visible { display: block; }
                .jg-add-form label { display: block; margin-bottom: 5px; font-weight: 500; }
                .jg-add-form input[type="text"], .jg-add-form select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px; }
                .jg-emoji-picker { display: flex; flex-wrap: wrap; gap: 4px; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px; max-height: 200px; overflow-y: auto; }
                .jg-emoji-btn { padding: 4px 6px; border: 1px solid transparent; border-radius: 4px; cursor: pointer; font-size: calc(16 * var(--jg)); background: none; transition: all 0.2s; line-height: 1; }
                .jg-emoji-btn:hover { background: #e3f2fd; }
                .jg-emoji-btn.selected { background: #2196f3; border-color: #1976d2; }
                .jg-icon-preview { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
                .jg-icon-preview .preview { font-size: calc(32 * var(--jg)); width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; background: #fff; border: 2px solid #ddd; border-radius: 8px; }
                .jg-manual-emoji { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
                .jg-manual-emoji input { width: 60px; font-size: calc(24 * var(--jg)); text-align: center; padding: 4px; border: 1px solid #ddd; border-radius: 4px; }
                .jg-manual-emoji .hint { font-size: calc(11 * var(--jg)); color: #666; }
                .jg-manual-emoji input.invalid { border-color: #c62828; }
                .jg-icon-mode { display: flex; gap: 10px; margin-bottom: 10px; }
                .jg-icon-mode label { display: flex; align-items: center; gap: 5px; cursor: pointer; }
                .jg-btn-row { display: flex; gap: 10px; margin-top: 15px; }
                .jg-edit-inline { display: none; padding: 10px; background: #fff3e0; border-radius: 4px; margin-top: 8px; }
                .jg-edit-inline.visible { display: block; }
                .jg-edit-inline input { padding: 6px; border: 1px solid #ddd; border-radius: 4px; }
                .jg-filter-bar { margin-bottom: 15px; }
                .jg-filter-bar select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
                @media (max-width: 1200px) { .jg-report-editor { grid-template-columns: 1fr; } }
            </style>

            <div class="jg-report-editor">
                <!-- Categories Column -->
                <div class="card">
                    <h2>Kategorie zgłoszeń</h2>
                    <p class="description">Kategorie grupują powody zgłoszeń. Kliknij kategorię aby zobaczyć przypisane powody.</p>

                    <ul class="jg-category-list" id="jg-category-list">
                        <?php foreach ($categories as $key => $label):
                            $count = 0;
                            foreach ($reasons as $reason) {
                                if (isset($reason['group']) && $reason['group'] === $key) $count++;
                            }
                        ?>
                        <li class="jg-category-item" data-key="<?php echo esc_attr($key); ?>">
                            <span class="cat-name" onclick="jgFilterByCategory('<?php echo esc_js($key); ?>')"><?php echo esc_html($label); ?></span>
                            <code class="cat-slug"><?php echo esc_html($key); ?></code>
                            <span class="cat-count"><?php echo $count; ?></span>
                            <button class="jg-action-btn" onclick="jgEditCategory('<?php echo esc_js($key); ?>', '<?php echo esc_js($label); ?>')" title="Edytuj">✏️</button>
                            <button class="jg-action-btn delete" onclick="jgDeleteCategory('<?php echo esc_js($key); ?>')" title="Usuń">🗑️</button>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <button class="button" onclick="jgToggleAddCategory()">+ Dodaj kategorię</button>

                    <div class="jg-add-form" id="jg-add-category-form">
                        <label for="new-cat-key">Klucz kategorii (bez spacji, małe litery)</label>
                        <input type="text" id="new-cat-key" placeholder="np. environment">

                        <label for="new-cat-label">Nazwa wyświetlana</label>
                        <input type="text" id="new-cat-label" placeholder="np. Środowisko naturalne">

                        <div class="jg-btn-row">
                            <button class="button button-primary" onclick="jgSaveCategory()">Zapisz</button>
                            <button class="button" onclick="jgToggleAddCategory()">Anuluj</button>
                        </div>
                    </div>

                    <!-- Edit category inline -->
                    <div class="jg-edit-inline" id="jg-edit-category-form">
                        <label>Edytuj nazwę kategorii</label>
                        <input type="hidden" id="edit-cat-key">
                        <input type="text" id="edit-cat-label" style="width: calc(100% - 20px);">
                        <div class="jg-btn-row">
                            <button class="button button-primary" onclick="jgUpdateCategory()">Zapisz</button>
                            <button class="button" onclick="jgCancelEditCategory()">Anuluj</button>
                        </div>
                    </div>
                </div>

                <!-- Reasons Column -->
                <div class="card">
                    <h2>Powody zgłoszeń</h2>
                    <p class="description">Lista wszystkich powodów zgłoszeń. Możesz filtrować według kategorii.</p>

                    <div class="jg-filter-bar">
                        <select id="jg-reason-filter" onchange="jgFilterReasons()">
                            <option value="">Wszystkie kategorie</option>
                            <?php foreach ($categories as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <ul class="jg-reason-list" id="jg-reason-list">
                        <?php foreach ($reasons as $key => $reason): ?>
                        <li class="jg-reason-item" data-key="<?php echo esc_attr($key); ?>" data-group="<?php echo esc_attr($reason['group'] ?? ''); ?>">
                            <span class="reason-icon"><?php echo esc_html($reason['icon'] ?? '📌'); ?></span>
                            <span class="reason-label"><?php echo esc_html($reason['label']); ?></span>
                            <span class="reason-category"><?php echo esc_html($categories[$reason['group']] ?? $reason['group'] ?? 'Brak'); ?></span>
                            <button class="jg-action-btn" onclick="jgEditReason('<?php echo esc_js($key); ?>')" title="Edytuj">✏️</button>
                            <button class="jg-action-btn delete" onclick="jgDeleteReason('<?php echo esc_js($key); ?>')" title="Usuń">🗑️</button>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <button class="button" onclick="jgToggleAddReason()">+ Dodaj powód</button>

                    <div class="jg-add-form" id="jg-add-reason-form">
                        <label for="new-reason-key">Klucz powodu (bez spacji, małe litery)</label>
                        <input type="text" id="new-reason-key" placeholder="np. zanieczyszczenie_powietrza" oninput="jgGenerateKey(this)">

                        <label for="new-reason-label">Nazwa wyświetlana</label>
                        <input type="text" id="new-reason-label" placeholder="np. Zanieczyszczenie powietrza" oninput="jgSuggestIcon()">

                        <label for="new-reason-group">Kategoria</label>
                        <select id="new-reason-group">
                            <?php foreach ($categories as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label>Ikona</label>
                        <div class="jg-icon-mode">
                            <label><input type="radio" name="icon-mode" value="auto" checked onchange="jgToggleIconMode()"> Automatyczna</label>
                            <label><input type="radio" name="icon-mode" value="manual" onchange="jgToggleIconMode()"> Ręczna</label>
                        </div>

                        <div class="jg-icon-preview">
                            <div class="preview" id="icon-preview">📌</div>
                            <span id="icon-hint">Ikona zostanie dobrana automatycznie na podstawie nazwy</span>
                        </div>

                        <div class="jg-emoji-picker" id="emoji-picker" style="display: none;">
                            <?php foreach ($common_emojis as $emoji): ?>
                            <button type="button" class="jg-emoji-btn" onclick="jgSelectEmoji('<?php echo $emoji; ?>')"><?php echo $emoji; ?></button>
                            <?php endforeach; ?>
                        </div>
                        <div class="jg-manual-emoji" id="manual-emoji-input" style="display: none;">
                            <input type="text" id="new-reason-icon-manual" maxlength="4" placeholder="📌" oninput="jgManualEmojiInput(this)">
                            <span class="hint">Wklej emoji lub wpisz bezpośrednio</span>
                        </div>
                        <input type="hidden" id="new-reason-icon" value="">

                        <div class="jg-btn-row">
                            <button class="button button-primary" onclick="jgSaveReason()">Zapisz</button>
                            <button class="button" onclick="jgToggleAddReason()">Anuluj</button>
                        </div>
                    </div>

                    <!-- Edit reason modal -->
                    <div class="jg-add-form" id="jg-edit-reason-form">
                        <h3 style="margin-top:0">Edytuj powód zgłoszenia</h3>
                        <input type="hidden" id="edit-reason-key">

                        <label for="edit-reason-label">Nazwa wyświetlana</label>
                        <input type="text" id="edit-reason-label" oninput="jgSuggestIconEdit()">

                        <label for="edit-reason-group">Kategoria</label>
                        <select id="edit-reason-group">
                            <?php foreach ($categories as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label>Ikona</label>
                        <div class="jg-icon-mode">
                            <label><input type="radio" name="edit-icon-mode" value="auto" onchange="jgToggleIconModeEdit()"> Automatyczna</label>
                            <label><input type="radio" name="edit-icon-mode" value="manual" onchange="jgToggleIconModeEdit()"> Ręczna</label>
                        </div>

                        <div class="jg-icon-preview">
                            <div class="preview" id="edit-icon-preview">📌</div>
                        </div>

                        <div class="jg-emoji-picker" id="edit-emoji-picker" style="display: none;">
                            <?php foreach ($common_emojis as $emoji): ?>
                            <button type="button" class="jg-emoji-btn" onclick="jgSelectEmojiEdit('<?php echo $emoji; ?>')"><?php echo $emoji; ?></button>
                            <?php endforeach; ?>
                        </div>
                        <div class="jg-manual-emoji" id="edit-manual-emoji-input" style="display: none;">
                            <input type="text" id="edit-reason-icon-manual" maxlength="4" placeholder="📌" oninput="jgManualEmojiInputEdit(this)">
                            <span class="hint">Wklej emoji lub wpisz bezpośrednio</span>
                        </div>
                        <input type="hidden" id="edit-reason-icon" value="">

                        <div class="jg-btn-row">
                            <button class="button button-primary" onclick="jgUpdateReason()">Zapisz zmiany</button>
                            <button class="button" onclick="jgCancelEditReason()">Anuluj</button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            (function() {
                const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
                const nonce = '<?php echo wp_create_nonce('jg_map_report_reasons_nonce'); ?>';

                // Store current data
                let categories = <?php echo json_encode($categories); ?>;
                let reasons = <?php echo json_encode($reasons); ?>;

                // Emoji validation function
                function jgIsValidEmoji(str) {
                    str = str.trim();
                    if (!str) return false;
                    if (/[a-zA-Z0-9ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/.test(str)) return false;
                    return /\p{Extended_Pictographic}/u.test(str);
                }

                function jgGetFirstReportEmoji() {
                    const btn = document.querySelector('#emoji-picker .jg-emoji-btn');
                    return btn ? btn.textContent.trim() : '📌';
                }

                // Category functions
                window.jgToggleAddCategory = function() {
                    const form = document.getElementById('jg-add-category-form');
                    form.classList.toggle('visible');
                    document.getElementById('jg-edit-category-form').classList.remove('visible');
                };

                window.jgSaveCategory = function() {
                    const key = document.getElementById('new-cat-key').value.trim().toLowerCase().replace(/\s+/g, '_');
                    const label = document.getElementById('new-cat-label').value.trim();

                    if (!key || !label) {
                        alert('Wypełnij wszystkie pola');
                        return;
                    }

                    if (categories[key]) {
                        alert('Kategoria o tym kluczu już istnieje');
                        return;
                    }

                    // Save via AJAX
                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'jg_save_report_category',
                            nonce: nonce,
                            key: key,
                            label: label
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.data || 'Błąd zapisu');
                        }
                    });
                };

                window.jgEditCategory = function(key, label) {
                    document.getElementById('jg-add-category-form').classList.remove('visible');
                    const form = document.getElementById('jg-edit-category-form');
                    form.classList.add('visible');
                    document.getElementById('edit-cat-key').value = key;
                    document.getElementById('edit-cat-label').value = label;
                };

                window.jgCancelEditCategory = function() {
                    document.getElementById('jg-edit-category-form').classList.remove('visible');
                };

                window.jgUpdateCategory = function() {
                    const key = document.getElementById('edit-cat-key').value;
                    const label = document.getElementById('edit-cat-label').value.trim();

                    if (!label) {
                        alert('Nazwa nie może być pusta');
                        return;
                    }

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'jg_update_report_category',
                            nonce: nonce,
                            key: key,
                            label: label
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.data || 'Błąd zapisu');
                        }
                    });
                };

                window.jgDeleteCategory = function(key) {
                    // Check if category has reasons
                    let count = 0;
                    for (const k in reasons) {
                        if (reasons[k].group === key) count++;
                    }

                    if (count > 0) {
                        if (!confirm(`Ta kategoria zawiera ${count} powód(ów). Czy na pewno chcesz ją usunąć? Powody zostaną odłączone od kategorii.`)) {
                            return;
                        }
                    } else {
                        if (!confirm('Czy na pewno chcesz usunąć tę kategorię?')) {
                            return;
                        }
                    }

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'jg_delete_report_category',
                            nonce: nonce,
                            key: key
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.data || 'Błąd usuwania');
                        }
                    });
                };

                window.jgFilterByCategory = function(key) {
                    document.querySelectorAll('.jg-category-item').forEach(el => {
                        el.classList.toggle('active', el.dataset.key === key);
                    });
                    document.getElementById('jg-reason-filter').value = key;
                    jgFilterReasons();
                };

                // Reason functions
                window.jgFilterReasons = function() {
                    const filter = document.getElementById('jg-reason-filter').value;
                    document.querySelectorAll('.jg-reason-item').forEach(el => {
                        if (!filter || el.dataset.group === filter) {
                            el.style.display = '';
                        } else {
                            el.style.display = 'none';
                        }
                    });

                    // Update category highlight
                    document.querySelectorAll('.jg-category-item').forEach(el => {
                        el.classList.toggle('active', el.dataset.key === filter);
                    });
                };

                window.jgToggleAddReason = function() {
                    const form = document.getElementById('jg-add-reason-form');
                    form.classList.toggle('visible');
                    document.getElementById('jg-edit-reason-form').classList.remove('visible');
                };

                window.jgGenerateKey = function(input) {
                    // Auto-generate key from label if user types in label field
                };

                window.jgSuggestIcon = function() {
                    const mode = document.querySelector('input[name="icon-mode"]:checked').value;
                    if (mode !== 'auto') return;

                    const label = document.getElementById('new-reason-label').value;
                    if (!label) {
                        document.getElementById('icon-preview').textContent = '📌';
                        return;
                    }

                    // Call AJAX to get suggested icon
                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'jg_suggest_reason_icon',
                            nonce: nonce,
                            label: label
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('icon-preview').textContent = data.data.icon;
                            document.getElementById('new-reason-icon').value = '';
                        }
                    });
                };

                window.jgToggleIconMode = function() {
                    const mode = document.querySelector('input[name="icon-mode"]:checked').value;
                    const picker = document.getElementById('emoji-picker');
                    const manualInput = document.getElementById('manual-emoji-input');
                    const hint = document.getElementById('icon-hint');

                    if (mode === 'manual') {
                        picker.style.display = 'flex';
                        manualInput.style.display = 'flex';
                        hint.textContent = 'Wybierz ikonę z listy lub wklej własną poniżej';
                    } else {
                        picker.style.display = 'none';
                        manualInput.style.display = 'none';
                        hint.textContent = 'Ikona zostanie dobrana automatycznie na podstawie nazwy';
                        jgSuggestIcon();
                    }
                };

                window.jgManualEmojiInput = function(input) {
                    const emoji = input.value.trim();
                    if (emoji) {
                        if (jgIsValidEmoji(emoji)) {
                            input.classList.remove('invalid');
                            document.getElementById('icon-preview').textContent = emoji;
                            document.getElementById('new-reason-icon').value = emoji;
                        } else {
                            input.classList.add('invalid');
                        }
                        document.querySelectorAll('#emoji-picker .jg-emoji-btn').forEach(btn => {
                            btn.classList.remove('selected');
                        });
                    } else {
                        input.classList.remove('invalid');
                    }
                };

                window.jgSelectEmoji = function(emoji) {
                    document.getElementById('icon-preview').textContent = emoji;
                    document.getElementById('new-reason-icon').value = emoji;
                    document.getElementById('new-reason-icon-manual').value = emoji;
                    document.getElementById('new-reason-icon-manual').classList.remove('invalid');

                    document.querySelectorAll('#emoji-picker .jg-emoji-btn').forEach(btn => {
                        btn.classList.toggle('selected', btn.textContent === emoji);
                    });
                };

                window.jgSaveReason = function() {
                    const key = document.getElementById('new-reason-key').value.trim().toLowerCase().replace(/\s+/g, '_');
                    const label = document.getElementById('new-reason-label').value.trim();
                    const group = document.getElementById('new-reason-group').value;
                    const mode = document.querySelector('input[name="icon-mode"]:checked').value;
                    let icon = document.getElementById('new-reason-icon').value;

                    if (mode === 'auto') {
                        icon = document.getElementById('icon-preview').textContent;
                    }

                    if (!key || !label) {
                        alert('Wypełnij klucz i nazwę');
                        return;
                    }

                    if (reasons[key]) {
                        alert('Powód o tym kluczu już istnieje');
                        return;
                    }

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'jg_save_report_reason',
                            nonce: nonce,
                            key: key,
                            label: label,
                            group: group,
                            icon: icon || '📌'
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.data || 'Błąd zapisu');
                        }
                    });
                };

                window.jgEditReason = function(key) {
                    const reason = reasons[key];
                    if (!reason) return;

                    document.getElementById('jg-add-reason-form').classList.remove('visible');
                    const form = document.getElementById('jg-edit-reason-form');
                    form.classList.add('visible');

                    document.getElementById('edit-reason-key').value = key;
                    document.getElementById('edit-reason-label').value = reason.label;
                    document.getElementById('edit-reason-group').value = reason.group || '';
                    document.getElementById('edit-icon-preview').textContent = reason.icon || '📌';
                    document.getElementById('edit-reason-icon').value = reason.icon || '';
                    document.getElementById('edit-reason-icon-manual').value = reason.icon || '';

                    // Default to manual mode when editing since we have an existing icon
                    document.querySelector('input[name="edit-icon-mode"][value="manual"]').checked = true;
                    document.getElementById('edit-emoji-picker').style.display = 'flex';
                    document.getElementById('edit-manual-emoji-input').style.display = 'flex';

                    // Highlight current emoji
                    document.querySelectorAll('#edit-emoji-picker .jg-emoji-btn').forEach(btn => {
                        btn.classList.toggle('selected', btn.textContent === (reason.icon || '📌'));
                    });

                    // Scroll to form
                    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                };

                window.jgCancelEditReason = function() {
                    document.getElementById('jg-edit-reason-form').classList.remove('visible');
                };

                window.jgSuggestIconEdit = function() {
                    const mode = document.querySelector('input[name="edit-icon-mode"]:checked').value;
                    if (mode !== 'auto') return;

                    const label = document.getElementById('edit-reason-label').value;
                    if (!label) {
                        document.getElementById('edit-icon-preview').textContent = '📌';
                        return;
                    }

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'jg_suggest_reason_icon',
                            nonce: nonce,
                            label: label
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('edit-icon-preview').textContent = data.data.icon;
                            document.getElementById('edit-reason-icon').value = '';
                        }
                    });
                };

                window.jgToggleIconModeEdit = function() {
                    const mode = document.querySelector('input[name="edit-icon-mode"]:checked').value;
                    const picker = document.getElementById('edit-emoji-picker');
                    const manualInput = document.getElementById('edit-manual-emoji-input');

                    if (mode === 'manual') {
                        picker.style.display = 'flex';
                        manualInput.style.display = 'flex';
                    } else {
                        picker.style.display = 'none';
                        manualInput.style.display = 'none';
                        jgSuggestIconEdit();
                    }
                };

                window.jgManualEmojiInputEdit = function(input) {
                    const emoji = input.value.trim();
                    if (emoji) {
                        document.getElementById('edit-icon-preview').textContent = emoji;
                        document.getElementById('edit-reason-icon').value = emoji;
                        // Deselect all picker buttons
                        document.querySelectorAll('#edit-emoji-picker .jg-emoji-btn').forEach(btn => {
                            btn.classList.remove('selected');
                        });
                    }
                };

                window.jgSelectEmojiEdit = function(emoji) {
                    document.getElementById('edit-icon-preview').textContent = emoji;
                    document.getElementById('edit-reason-icon').value = emoji;
                    document.getElementById('edit-reason-icon-manual').value = emoji;

                    document.querySelectorAll('#edit-emoji-picker .jg-emoji-btn').forEach(btn => {
                        btn.classList.toggle('selected', btn.textContent === emoji);
                    });
                };

                window.jgUpdateReason = function() {
                    const key = document.getElementById('edit-reason-key').value;
                    const label = document.getElementById('edit-reason-label').value.trim();
                    const group = document.getElementById('edit-reason-group').value;
                    const mode = document.querySelector('input[name="edit-icon-mode"]:checked').value;
                    let icon = document.getElementById('edit-reason-icon').value;

                    if (mode === 'auto') {
                        icon = document.getElementById('edit-icon-preview').textContent;
                    }

                    if (!label) {
                        alert('Nazwa nie może być pusta');
                        return;
                    }

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'jg_update_report_reason',
                            nonce: nonce,
                            key: key,
                            label: label,
                            group: group,
                            icon: icon || '📌'
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.data || 'Błąd zapisu');
                        }
                    });
                };

                window.jgDeleteReason = function(key) {
                    if (!confirm('Czy na pewno chcesz usunąć ten powód zgłoszenia?')) {
                        return;
                    }

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'jg_delete_report_reason',
                            nonce: nonce,
                            key: key
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.data || 'Błąd usuwania');
                        }
                    });
                };
            })();
            </script>
        </div>
        <?php $this->render_filter_reset_card(); ?>
        <?php
    }
}
