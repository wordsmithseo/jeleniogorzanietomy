<?php
/**
 * Trait JG_Map_Admin_Gamification
 * Edytor XP, osiągnięć i wyzwań.
 */
trait JG_Map_Admin_Gamification {

    /**
     * Render XP Editor page
     */
    public function render_xp_editor_page() {
        $nonce = wp_create_nonce('jg_map_admin_nonce');
        ?>
        <div class="wrap">
            <?php $this->render_page_header('Edytor doświadczenia (XP)'); ?>
            <p style="margin-top:0;color:#6b7280">Konfiguruj za jakie akcje użytkownicy otrzymują doświadczenie (XP) i ile punktów przyznawać.</p>
            <p><strong>Formuła poziomów:</strong> Poziom N wymaga N&sup2; &times; 100 XP (np. poziom 2 = 400 XP, poziom 5 = 2500 XP, poziom 10 = 10000 XP)</p>

            <div id="jg-xp-editor" style="max-width:800px;margin-top:20px">
                <div class="jg-admin-table-wrap"><div class="jg-table-scroll">
                <table class="jg-admin-table" id="jg-xp-table">
                    <thead>
                        <tr>
                            <th style="width:240px">Akcja</th>
                            <th>Opis (opcjonalny)</th>
                            <th style="width:100px">XP</th>
                            <th style="width:80px">Aktywna</th>
                        </tr>
                    </thead>
                    <tbody id="jg-xp-tbody"></tbody>
                </table>
                </div></div>
                <p style="margin-top:12px">
                    <button class="button button-primary" id="jg-xp-save">Zapisz zmiany</button>
                    <span id="jg-xp-status" style="margin-left:12px;color:#059669;font-weight:600;display:none">Zapisano!</span>
                </p>
            </div>

            <script>
            (function() {
                var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
                var nonce = '<?php echo $nonce; ?>';
                var tbody = document.getElementById('jg-xp-tbody');

                var availableActions = [
                    { key: 'submit_point', name: 'Dodanie punktu', defaultXp: 50 },
                    { key: 'point_approved', name: 'Zatwierdzenie punktu przez admina', defaultXp: 30 },
                    { key: 'receive_upvote', name: 'Otrzymanie głosu w górę', defaultXp: 5 },
                    { key: 'vote_on_point', name: 'Oddanie głosu na punkt', defaultXp: 2 },
                    { key: 'add_photo', name: 'Dodanie zdjęcia do punktu', defaultXp: 10 },
                    { key: 'edit_point', name: 'Edycja punktu', defaultXp: 15 },
                    { key: 'daily_login', name: 'Dzienny login', defaultXp: 5 },
                    { key: 'report_point', name: 'Zgłoszenie punktu', defaultXp: 10 }
                ];

                function renderRow(action, savedData) {
                    var tr = document.createElement('tr');
                    var isActive = savedData !== null;
                    var xpVal = isActive ? (savedData.xp || 0) : action.defaultXp;
                    var labelVal = isActive && savedData.label ? savedData.label : '';
                    tr.setAttribute('data-key', action.key);
                    tr.innerHTML = '<td><strong>' + esc(action.key) + '</strong><br><span style="color:#6b7280;font-size:calc(12 * var(--jg))">' + esc(action.name) + '</span></td>' +
                        '<td><input type="text" value="' + esc(labelVal) + '" class="xp-label regular-text" style="width:100%" placeholder="' + esc(action.name) + '"></td>' +
                        '<td><input type="number" value="' + xpVal + '" class="xp-amount" style="width:80px" min="0"></td>' +
                        '<td style="text-align:center"><input type="checkbox" class="xp-active"' + (isActive ? ' checked' : '') + '></td>';
                    tbody.appendChild(tr);
                }

                function esc(s) {
                    var d = document.createElement('div');
                    d.textContent = s || '';
                    return d.innerHTML.replace(/"/g, '&quot;');
                }

                // Load saved sources, then render all available actions
                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'jg_admin_get_xp_sources', _ajax_nonce: nonce })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var saved = {};
                    if (data.success && Array.isArray(data.data)) {
                        data.data.forEach(function(s) { saved[s.key] = s; });
                    }
                    availableActions.forEach(function(action) {
                        renderRow(action, saved[action.key] || null);
                    });
                });

                document.getElementById('jg-xp-save').onclick = function() {
                    var rows = tbody.querySelectorAll('tr');
                    var sources = [];
                    rows.forEach(function(tr) {
                        if (!tr.querySelector('.xp-active').checked) return;
                        var key = tr.getAttribute('data-key');
                        var action = availableActions.find(function(a) { return a.key === key; });
                        sources.push({
                            key: key,
                            label: tr.querySelector('.xp-label').value || (action ? action.name : key),
                            xp: parseInt(tr.querySelector('.xp-amount').value) || 0
                        });
                    });

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'jg_admin_save_xp_sources',
                            _ajax_nonce: nonce,
                            sources: JSON.stringify(sources)
                        })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var status = document.getElementById('jg-xp-status');
                        if (data.success) {
                            status.textContent = 'Zapisano!';
                            status.style.color = '#059669';
                        } else {
                            status.textContent = 'Błąd: ' + (data.data || 'nieznany');
                            status.style.color = '#dc2626';
                        }
                        status.style.display = 'inline';
                        setTimeout(function() { status.style.display = 'none'; }, 3000);
                    });
                };
            })();
            </script>
        </div>
        <?php
    }

    /**
     * Render Achievements Editor page
     */
    public function render_achievements_editor_page() {
        $nonce = wp_create_nonce('jg_map_admin_nonce');
        ?>
        <div class="wrap">
            <?php $this->render_page_header('Edytor osiągnięć'); ?>
            <p style="margin-top:0;color:#6b7280">Konfiguruj osiągnięcia dostępne dla użytkowników. Rzadkość determinuje kolor poświaty wokół osiągnięcia.</p>

            <div class="jg-rarity-badges">
                <span class="jg-rarity-badge" style="background:#f3f4f6;border:2px solid #d1d5db">
                    <span class="jg-rarity-dot" style="background:#d1d5db;box-shadow:0 0 6px #d1d5db"></span> Zwykłe (common)
                </span>
                <span class="jg-rarity-badge" style="background:#ecfdf5;border:2px solid #10b981">
                    <span class="jg-rarity-dot" style="background:#10b981;box-shadow:0 0 6px #10b981"></span> Niepospolite (uncommon)
                </span>
                <span class="jg-rarity-badge" style="background:#eff6ff;border:2px solid #3b82f6">
                    <span class="jg-rarity-dot" style="background:#3b82f6;box-shadow:0 0 6px #3b82f6"></span> Rzadkie (rare)
                </span>
                <span class="jg-rarity-badge" style="background:#faf5ff;border:2px solid #8b5cf6">
                    <span class="jg-rarity-dot" style="background:#8b5cf6;box-shadow:0 0 6px #8b5cf6"></span> Epickie (epic)
                </span>
                <span class="jg-rarity-badge" style="background:#fffbeb;border:2px solid #f59e0b">
                    <span class="jg-rarity-dot" style="background:#f59e0b;box-shadow:0 0 6px #f59e0b"></span> Legendarne (legendary)
                </span>
            </div>

            <div id="jg-ach-editor" style="max-width:1100px;margin-top:12px">
                <div class="jg-admin-table-wrap"><div class="jg-table-scroll">
                <table class="jg-admin-table" id="jg-ach-table">
                    <thead>
                        <tr>
                            <th style="width:40px">ID</th>
                            <th style="width:120px">Slug</th>
                            <th style="width:160px">Nazwa</th>
                            <th>Opis</th>
                            <th style="width:50px">Ikona</th>
                            <th style="width:120px">Rzadkość</th>
                            <th style="width:140px">Warunek</th>
                            <th style="width:70px">Wartość</th>
                            <th style="width:60px">Kolejn.</th>
                            <th style="width:80px">Akcje</th>
                        </tr>
                    </thead>
                    <tbody id="jg-ach-tbody"></tbody>
                </table>
                </div></div>
                <p style="margin-top:12px">
                    <button class="button" id="jg-ach-add-row">+ Dodaj osiągnięcie</button>
                    <button class="button button-primary" id="jg-ach-save" style="margin-left:8px">Zapisz zmiany</button>
                    <span id="jg-ach-status" style="margin-left:12px;color:#059669;font-weight:600;display:none">Zapisano!</span>
                </p>
            </div>

            <script>
            (function() {
                var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
                var nonce = '<?php echo $nonce; ?>';
                var tbody = document.getElementById('jg-ach-tbody');

                var rarityOptions = '<option value="common">Zwykłe (biała)</option><option value="uncommon">Niepospolite (zielona)</option><option value="rare">Rzadkie (niebieska)</option><option value="epic">Epickie (fioletowa)</option><option value="legendary">Legendarne (złota)</option>';
                var conditionOptions = '<option value="points_count">Liczba punktów</option><option value="votes_count">Liczba głosów</option><option value="photos_count">Liczba zdjęć</option><option value="level">Poziom</option><option value="all_types">Wszystkie typy</option><option value="received_upvotes">Otrzymane upvote\'y</option>';

                function esc(s) {
                    var d = document.createElement('div');
                    d.textContent = s || '';
                    return d.innerHTML.replace(/"/g, '&quot;');
                }

                function renderRow(ach) {
                    var tr = document.createElement('tr');
                    tr.dataset.id = ach.id || '';
                    tr.innerHTML =
                        '<td>' + (ach.id || '<em>nowe</em>') + '<input type="hidden" class="ach-id" value="' + (ach.id || '') + '"></td>' +
                        '<td><input type="text" value="' + esc(ach.slug) + '" class="ach-slug" style="width:100%"></td>' +
                        '<td><input type="text" value="' + esc(ach.name) + '" class="ach-name" style="width:100%"></td>' +
                        '<td><input type="text" value="' + esc(ach.description) + '" class="ach-desc" style="width:100%"></td>' +
                        '<td><input type="text" value="' + esc(ach.icon) + '" class="ach-icon" style="width:40px;text-align:center;font-size:calc(18 * var(--jg))"></td>' +
                        '<td><select class="ach-rarity">' + rarityOptions + '</select></td>' +
                        '<td><select class="ach-condition">' + conditionOptions + '</select></td>' +
                        '<td><input type="number" value="' + (ach.condition_value || 1) + '" class="ach-value" style="width:60px" min="1"></td>' +
                        '<td><input type="number" value="' + (ach.sort_order || 0) + '" class="ach-sort" style="width:50px"></td>' +
                        '<td><button class="button ach-remove" style="color:#dc2626">Usuń</button></td>';

                    // Set select values
                    if (ach.rarity) tr.querySelector('.ach-rarity').value = ach.rarity;
                    if (ach.condition_type) tr.querySelector('.ach-condition').value = ach.condition_type;

                    tr.querySelector('.ach-remove').onclick = function() {
                        var id = tr.dataset.id;
                        if (id) {
                            if (!confirm('Usunąć osiągnięcie?')) return;
                            fetch(ajaxUrl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({ action: 'jg_admin_delete_achievement', _ajax_nonce: nonce, achievement_id: id })
                            }).then(function() { tr.remove(); });
                        } else {
                            tr.remove();
                        }
                    };

                    tbody.appendChild(tr);
                }

                // Load
                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'jg_admin_get_achievements', _ajax_nonce: nonce })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && Array.isArray(data.data)) {
                        data.data.forEach(renderRow);
                    }
                });

                document.getElementById('jg-ach-add-row').onclick = function() {
                    renderRow({ id: '', slug: '', name: '', description: '', icon: '🏆', rarity: 'common', condition_type: 'points_count', condition_value: 1, sort_order: 0 });
                };

                document.getElementById('jg-ach-save').onclick = function() {
                    var rows = tbody.querySelectorAll('tr');
                    var achievements = [];
                    rows.forEach(function(tr) {
                        achievements.push({
                            id: tr.querySelector('.ach-id').value || '',
                            slug: tr.querySelector('.ach-slug').value,
                            name: tr.querySelector('.ach-name').value,
                            description: tr.querySelector('.ach-desc').value,
                            icon: tr.querySelector('.ach-icon').value,
                            rarity: tr.querySelector('.ach-rarity').value,
                            condition_type: tr.querySelector('.ach-condition').value,
                            condition_value: parseInt(tr.querySelector('.ach-value').value) || 1,
                            sort_order: parseInt(tr.querySelector('.ach-sort').value) || 0
                        });
                    });

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'jg_admin_save_achievements',
                            _ajax_nonce: nonce,
                            achievements: JSON.stringify(achievements)
                        })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var status = document.getElementById('jg-ach-status');
                        if (data.success) {
                            status.textContent = 'Zapisano!';
                            status.style.color = '#059669';
                            // Reload to get proper IDs
                            setTimeout(function() { location.reload(); }, 1000);
                        } else {
                            status.textContent = 'Błąd: ' + (data.data || 'nieznany');
                            status.style.color = '#dc2626';
                        }
                        status.style.display = 'inline';
                    });
                };
            })();
            </script>
        </div>
        <?php
    }

    /**
     * Render Challenges editor page
     */
    public function render_challenges_page() {
        $nonce      = wp_create_nonce('jg_map_admin_nonce');
        $conditions = JG_Map_Challenges::get_condition_types();
        ?>
        <div class="wrap">
            <?php $this->render_page_header('Wyzwania społecznościowe'); ?>

            <style>
            .jg-ch-page { max-width: 1200px; }
            .jg-ch-info { background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:14px 18px; margin-bottom:24px; font-size:13px; color:#1e40af; line-height:1.6; }
            .jg-ch-info strong { display:block; margin-bottom:4px; font-size:14px; }

            .jg-ch-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; box-shadow:0 1px 4px rgba(0,0,0,.06); margin-bottom:20px; overflow:hidden; }
            .jg-ch-card-head { padding:14px 20px; background:#f8fafc; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
            .jg-ch-card-title { font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#374151; margin:0; }
            .jg-ch-card-body { padding:20px; }

            .jg-ch-list { display:flex; flex-direction:column; gap:16px; }

            .jg-ch-row { border:1px solid #e5e7eb; border-radius:10px; overflow:hidden; }
            .jg-ch-row-head { background:#f9fafb; padding:10px 16px; display:flex; align-items:center; gap:10px; border-bottom:1px solid #e5e7eb; }
            .jg-ch-row-id { font-size:11px; color:#9ca3af; min-width:50px; }
            .jg-ch-row-status { margin-left:auto; display:flex; align-items:center; gap:8px; }
            .jg-ch-row-body { padding:16px; display:grid; grid-template-columns:1fr 1fr; gap:14px 20px; }
            .jg-ch-row-body .jg-ch-full { grid-column:1/-1; }

            .jg-ch-field { display:flex; flex-direction:column; gap:5px; }
            .jg-ch-field label { font-size:12px; font-weight:600; color:#374151; }
            .jg-ch-field input[type=text],
            .jg-ch-field input[type=number],
            .jg-ch-field input[type=datetime-local],
            .jg-ch-field select,
            .jg-ch-field textarea {
                padding:8px 10px; border:1px solid #d1d5db; border-radius:6px;
                font-size:13px; width:100%; box-sizing:border-box;
                background:#fff; color:#111827; transition:border-color .15s;
                font-family:inherit;
            }
            .jg-ch-field input:focus,
            .jg-ch-field select:focus,
            .jg-ch-field textarea:focus { outline:none; border-color:#8d2324; box-shadow:0 0 0 2px rgba(141,35,36,.1); }
            .jg-ch-field textarea { resize:vertical; min-height:60px; }
            .jg-ch-field .jg-ch-hint { font-size:11px; color:#6b7280; margin-top:2px; }
            .jg-ch-field .jg-ch-error { font-size:11px; color:#dc2626; display:none; }

            .jg-ch-row-foot { padding:10px 16px; background:#f9fafb; border-top:1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between; gap:12px; }
            .jg-ch-row-foot .jg-ch-row-save { background:#8d2324; color:#fff; border:none; padding:8px 18px; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer; }
            .jg-ch-row-foot .jg-ch-row-delete { background:#fff; color:#dc2626; border:1px solid #dc2626; padding:7px 14px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; }
            .jg-ch-row-foot .jg-ch-row-msg { font-size:12px; font-weight:600; display:none; }

            .jg-ch-add-btn { background:#8d2324; color:#fff; border:none; padding:10px 20px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px; }
            .jg-ch-add-btn:hover { background:#a02829; }

            .jg-ch-toggle-wrap { display:flex; align-items:center; gap:8px; }
            .jg-ch-toggle { position:relative; width:40px; height:22px; cursor:pointer; flex-shrink:0; }
            .jg-ch-toggle input { opacity:0; width:0; height:0; position:absolute; }
            .jg-ch-toggle-slider { position:absolute; inset:0; background:#d1d5db; border-radius:11px; transition:.2s; }
            .jg-ch-toggle input:checked + .jg-ch-toggle-slider { background:#059669; }
            .jg-ch-toggle-slider:before { content:''; position:absolute; left:3px; top:3px; width:16px; height:16px; border-radius:50%; background:#fff; transition:.2s; }
            .jg-ch-toggle input:checked + .jg-ch-toggle-slider:before { transform:translateX(18px); }

            @media(max-width:700px) {
                .jg-ch-row-body { grid-template-columns:1fr; }
                .jg-ch-row-body .jg-ch-full { grid-column:1; }
            }
            </style>

            <div class="jg-ch-page">
                <div class="jg-ch-info">
                    <strong>Jak działają wyzwania?</strong>
                    Wyzwanie jest widoczne na mapie dla wszystkich użytkowników — na desktopie jako widget na mapie, na telefonie między przyciskami. Postęp jest liczony automatycznie na podstawie aktywności w portalu w wybranym przedziale czasowym. Tylko jedno wyzwanie może być aktywne jednocześnie (pierwsze aktywne w bieżącym czasie).
                </div>

                <div class="jg-ch-card">
                    <div class="jg-ch-card-head">
                        <p class="jg-ch-card-title">🏆 Lista wyzwań</p>
                        <button class="jg-ch-add-btn" id="jg-ch-add">+ Dodaj nowe wyzwanie</button>
                    </div>
                    <div class="jg-ch-card-body">
                        <div class="jg-ch-list" id="jg-ch-list">
                            <p id="jg-ch-loading" style="color:#9ca3af;font-size:13px">Ładowanie...</p>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            (function() {
                var ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
                var nonce   = '<?php echo esc_js($nonce); ?>';
                var list    = document.getElementById('jg-ch-list');

                var conditionTypes = <?php
                    $ct = array();
                    foreach ($conditions as $k => $v) {
                        $ct[] = array('key' => $k, 'label' => $v['label'], 'needs_cat' => (bool)$v['needs_cat'], 'group' => $v['group'] ?? '');
                    }
                    echo json_encode($ct);
                ?>;

                function esc(s) {
                    var d = document.createElement('div');
                    d.textContent = s || '';
                    return d.innerHTML.replace(/"/g, '&quot;');
                }

                function toDatetimeLocal(dt) {
                    if (!dt) return '';
                    return dt.replace(' ', 'T').substring(0, 16);
                }

                function buildConditionOptions(selected) {
                    var html = '';
                    var curGroup = null;
                    conditionTypes.forEach(function(ct) {
                        if (ct.group && ct.group !== curGroup) {
                            if (curGroup !== null) html += '</optgroup>';
                            html += '<optgroup label="' + ct.group + '">';
                            curGroup = ct.group;
                        }
                        html += '<option value="' + ct.key + '"' + (ct.key === selected ? ' selected' : '') + '>' + ct.label + '</option>';
                    });
                    if (curGroup !== null) html += '</optgroup>';
                    return html;
                }

                function needsCat(ctKey) {
                    for (var i = 0; i < conditionTypes.length; i++) {
                        if (conditionTypes[i].key === ctKey) return conditionTypes[i].needs_cat;
                    }
                    return false;
                }

                function buildRow(ch) {
                    var isNew = !ch.id;
                    var div = document.createElement('div');
                    div.className = 'jg-ch-row';
                    div.dataset.id = ch.id || '';

                    var now  = new Date();
                    var week = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);
                    var fmt  = function(d) { return d.toISOString().substring(0, 16); };

                    var catVal = ch.category || ch.cat || '';
                    var ctVal  = ch.condition_type || ch.point_type || 'any_point';
                    var showCat = needsCat(ctVal) ? '' : 'display:none';

                    div.innerHTML =
                        '<div class="jg-ch-row-head">' +
                            '<span class="jg-ch-row-id">' + (ch.id ? '#' + ch.id : '<em>nowe</em>') + '</span>' +
                            '<strong style="font-size:13px;color:#111827;flex:1">' + esc(ch.title || '—') + '</strong>' +
                            '<div class="jg-ch-row-status">' +
                                '<label class="jg-ch-toggle" title="Aktywne">' +
                                    '<input type="checkbox" class="ch-active"' + (ch.is_active == 1 ? ' checked' : '') + '>' +
                                    '<span class="jg-ch-toggle-slider"></span>' +
                                '</label>' +
                                '<span style="font-size:12px;color:#6b7280">Aktywne</span>' +
                            '</div>' +
                        '</div>' +

                        '<div class="jg-ch-row-body">' +
                            '<div class="jg-ch-field jg-ch-full">' +
                                '<label>Tytuł wyzwania <span style="color:#dc2626">*</span></label>' +
                                '<input type="text" class="ch-title" value="' + esc(ch.title) + '" placeholder="np. Odkryj restauracje Jeleniej Góry!" maxlength="255">' +
                                '<span class="jg-ch-error" id="err-title-' + (ch.id||'new') + '">Tytuł jest wymagany</span>' +
                            '</div>' +

                            '<div class="jg-ch-field jg-ch-full">' +
                                '<label>Opis (opcjonalny)</label>' +
                                '<textarea class="ch-desc" placeholder="Dodatkowe wyjaśnienie czego dotyczy wyzwanie…" maxlength="500">' + esc(ch.description) + '</textarea>' +
                            '</div>' +

                            '<div class="jg-ch-field jg-ch-full">' +
                                '<label>Warunek — co trzeba zrobić <span style="color:#dc2626">*</span></label>' +
                                '<select class="ch-condition-type">' + buildConditionOptions(ctVal) + '</select>' +
                                '<span class="jg-ch-hint">Postęp wyzwania jest liczony automatycznie na podstawie wybranego warunku w przedziale czasowym wyzwania.</span>' +
                            '</div>' +

                            '<div class="jg-ch-field jg-ch-full jg-ch-cat-wrap" style="' + showCat + '">' +
                                '<label>Konkretna kategoria (slug)</label>' +
                                '<input type="text" class="ch-category" value="' + esc(catVal) + '" placeholder="np. restauracja, historyczne (slug z ustawień kategorii)">' +
                                '<span class="jg-ch-hint">Wpisz slug kategorii z edytora kategorii. Pozostaw puste by liczyć wszystkie kategorie wybranego warunku.</span>' +
                            '</div>' +

                            '<div class="jg-ch-field">' +
                                '<label>Cel (liczba akcji) <span style="color:#dc2626">*</span></label>' +
                                '<input type="number" class="ch-target" value="' + (ch.target_count || 10) + '" min="1" max="9999" style="max-width:120px">' +
                                '<span class="jg-ch-hint">Ile akcji trzeba wykonać, żeby ukończyć wyzwanie</span>' +
                                '<span class="jg-ch-error" id="err-target-' + (ch.id||'new') + '">Cel musi być liczbą ≥ 1</span>' +
                            '</div>' +

                            '<div class="jg-ch-field">' +
                                '<label>Nagroda XP za ukończenie</label>' +
                                '<input type="number" class="ch-xp" value="' + (ch.xp_reward || 0) + '" min="0" max="99999" style="max-width:120px">' +
                                '<span class="jg-ch-hint">0 = brak nagrody XP (wyzwanie jest nadal widoczne)</span>' +
                            '</div>' +

                            '<div class="jg-ch-field jg-ch-full" style="border-top:1px solid #e5e7eb;padding-top:14px;margin-top:2px">' +
                                '<label style="font-size:13px;font-weight:700;color:#374151">🏅 Unikalne osiągnięcie za ukończenie (opcjonalne)</label>' +
                                '<span class="jg-ch-hint" style="display:block;margin-top:3px">Użytkownicy, którzy ukończą wyzwanie, otrzymają poniższe osiągnięcie. Zostaw nazwę pustą, by nie przyznawać osiągnięcia.</span>' +
                            '</div>' +

                            '<div class="jg-ch-field">' +
                                '<label>Nazwa osiągnięcia</label>' +
                                '<input type="text" class="ch-ach-name" value="' + esc(ch.ach_name || '') + '" placeholder="np. Odkrywca Jeleniej Góry" maxlength="255">' +
                            '</div>' +

                            '<div class="jg-ch-field">' +
                                '<label>Ikona (emoji) i rzadkość</label>' +
                                '<div style="display:flex;gap:8px;align-items:center">' +
                                    '<input type="text" class="ch-ach-icon" value="' + esc(ch.ach_icon || '🏆') + '" placeholder="🏆" style="max-width:64px;font-size:20px;text-align:center">' +
                                    '<select class="ch-ach-rarity" style="flex:1">' +
                                        '<option value="common"'    + ((!ch.ach_rarity||ch.ach_rarity==='common')?' selected':'')    + '>Zwykłe</option>' +
                                        '<option value="uncommon"'  + (ch.ach_rarity==='uncommon'?' selected':'')  + '>Niepospolite</option>' +
                                        '<option value="rare"'      + (ch.ach_rarity==='rare'?' selected':'')      + '>Rzadkie</option>' +
                                        '<option value="epic"'      + (ch.ach_rarity==='epic'?' selected':'')      + '>Epickie</option>' +
                                        '<option value="legendary"' + (ch.ach_rarity==='legendary'?' selected':'') + '>Legendarne</option>' +
                                    '</select>' +
                                '</div>' +
                            '</div>' +

                            '<div class="jg-ch-field jg-ch-full">' +
                                '<label>Opis osiągnięcia</label>' +
                                '<textarea class="ch-ach-desc" placeholder="np. Odkryłeś restauracje i kawiarnie Jeleniej Góry!" maxlength="500" style="min-height:48px">' + esc(ch.ach_desc || '') + '</textarea>' +
                            '</div>' +

                            '<div class="jg-ch-field">' +
                                '<label>Data i godzina startu <span style="color:#dc2626">*</span></label>' +
                                '<input type="datetime-local" class="ch-start" value="' + toDatetimeLocal(ch.start_date || fmt(now).replace(\'T\',\' \')) + '">' +
                                '<span class="jg-ch-error" id="err-start-' + (ch.id||'new') + '">Data startu jest wymagana</span>' +
                            '</div>' +

                            '<div class="jg-ch-field">' +
                                '<label>Data i godzina zakończenia <span style="color:#dc2626">*</span></label>' +
                                '<input type="datetime-local" class="ch-end" value="' + toDatetimeLocal(ch.end_date || fmt(week).replace(\'T\',\' \')) + '">' +
                                '<span class="jg-ch-error" id="err-end-' + (ch.id||'new') + '">Data zakończenia musi być po starcie</span>' +
                            '</div>' +

                            '<input type="hidden" class="ch-id" value="' + (ch.id || '') + '">' +
                        '</div>' +

                        '<div class="jg-ch-row-foot">' +
                            '<button class="jg-ch-row-delete">🗑 Usuń wyzwanie</button>' +
                            '<div style="display:flex;align-items:center;gap:12px">' +
                                '<span class="jg-ch-row-msg"></span>' +
                                '<button class="jg-ch-row-save">💾 Zapisz</button>' +
                            '</div>' +
                        '</div>';

                    // Show/hide category field on condition type change
                    var ctSel  = div.querySelector('.ch-condition-type');
                    var catWrap = div.querySelector('.jg-ch-cat-wrap');
                    ctSel.addEventListener('change', function() {
                        catWrap.style.display = needsCat(ctSel.value) ? '' : 'none';
                    });

                    // Update header title on input
                    var titleInput = div.querySelector('.ch-title');
                    var headTitle  = div.querySelector('.jg-ch-row-head strong');
                    titleInput.addEventListener('input', function() {
                        headTitle.textContent = titleInput.value || '—';
                    });

                    // Validate and save row
                    div.querySelector('.jg-ch-row-save').addEventListener('click', function() {
                        var title   = titleInput.value.trim();
                        var target  = parseInt(div.querySelector('.ch-target').value, 10);
                        var startV  = div.querySelector('.ch-start').value;
                        var endV    = div.querySelector('.ch-end').value;
                        var msg     = div.querySelector('.jg-ch-row-msg');
                        var valid   = true;

                        if (!title) {
                            valid = false;
                            msg.textContent = '⚠ Tytuł jest wymagany.';
                            msg.style.color = '#dc2626';
                            msg.style.display = 'inline';
                        }
                        if (!target || target < 1) {
                            valid = false;
                            msg.textContent = '⚠ Cel musi być liczbą ≥ 1.';
                            msg.style.color = '#dc2626';
                            msg.style.display = 'inline';
                        }
                        if (!startV || !endV) {
                            valid = false;
                            msg.textContent = '⚠ Daty startu i zakończenia są wymagane.';
                            msg.style.color = '#dc2626';
                            msg.style.display = 'inline';
                        }
                        if (startV && endV && startV >= endV) {
                            valid = false;
                            msg.textContent = '⚠ Data zakończenia musi być późniejsza niż start.';
                            msg.style.color = '#dc2626';
                            msg.style.display = 'inline';
                        }
                        if (!valid) return;

                        msg.style.display = 'none';
                        var btn = div.querySelector('.jg-ch-row-save');
                        btn.disabled = true;
                        btn.textContent = 'Zapisywanie…';

                        var data = {
                            action:         'jg_admin_save_challenge',
                            _ajax_nonce:    nonce,
                            id:             div.querySelector('.ch-id').value,
                            title:          title,
                            description:    div.querySelector('.ch-desc').value,
                            condition_type: div.querySelector('.ch-condition-type').value,
                            category:       div.querySelector('.ch-category').value,
                            target_count:   target,
                            xp_reward:      parseInt(div.querySelector('.ch-xp').value, 10) || 0,
                            start_date:     startV.replace('T', ' ') + ':00',
                            end_date:       endV.replace('T', ' ')   + ':00',
                            is_active:      div.querySelector('.ch-active').checked ? 1 : 0,
                            ach_name:       div.querySelector('.ch-ach-name').value.trim(),
                            ach_desc:       div.querySelector('.ch-ach-desc').value.trim(),
                            ach_icon:       div.querySelector('.ch-ach-icon').value.trim() || '🏆',
                            ach_rarity:     div.querySelector('.ch-ach-rarity').value
                        };

                        fetch(ajaxUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams(data)
                        })
                        .then(function(r) { return r.json(); })
                        .then(function(resp) {
                            btn.disabled = false;
                            btn.textContent = '💾 Zapisz';
                            if (resp.success) {
                                msg.textContent = '✓ Zapisano!';
                                msg.style.color = '#059669';
                                msg.style.display = 'inline';
                                if (!div.dataset.id && resp.data && resp.data.id) {
                                    div.dataset.id = resp.data.id;
                                    div.querySelector('.ch-id').value = resp.data.id;
                                    div.querySelector('.jg-ch-row-id').textContent = '#' + resp.data.id;
                                }
                                setTimeout(function() { msg.style.display = 'none'; }, 3000);
                            } else {
                                msg.textContent = '✗ ' + (resp.data || 'Błąd zapisu');
                                msg.style.color = '#dc2626';
                                msg.style.display = 'inline';
                            }
                        })
                        .catch(function() {
                            btn.disabled = false;
                            btn.textContent = '💾 Zapisz';
                            msg.textContent = '✗ Błąd połączenia';
                            msg.style.color = '#dc2626';
                            msg.style.display = 'inline';
                        });
                    });

                    // Delete row
                    div.querySelector('.jg-ch-row-delete').addEventListener('click', function() {
                        var id = div.dataset.id;
                        if (id) {
                            if (!confirm('Usunąć to wyzwanie trwale?')) return;
                            fetch(ajaxUrl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({ action: 'jg_admin_delete_challenge', _ajax_nonce: nonce, challenge_id: id })
                            }).then(function() { div.remove(); });
                        } else {
                            div.remove();
                        }
                    });

                    return div;
                }

                // Load existing challenges
                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'jg_admin_get_challenges', _ajax_nonce: nonce })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    document.getElementById('jg-ch-loading').remove();
                    if (data.success && Array.isArray(data.data) && data.data.length > 0) {
                        data.data.forEach(function(ch) { list.appendChild(buildRow(ch)); });
                    } else {
                        var empty = document.createElement('p');
                        empty.style.cssText = 'color:#9ca3af;font-size:13px;margin:0';
                        empty.textContent = 'Brak wyzwań. Kliknij „+ Dodaj nowe wyzwanie" by utworzyć pierwsze.';
                        list.appendChild(empty);
                    }
                });

                document.getElementById('jg-ch-add').addEventListener('click', function() {
                    var emptyMsg = list.querySelector('p');
                    if (emptyMsg) emptyMsg.remove();
                    var row = buildRow({ id:'', title:'', description:'', condition_type:'any_point', category:'', target_count:10, xp_reward:50, start_date:'', end_date:'', is_active:1 });
                    list.prepend(row);
                    row.querySelector('.ch-title').focus();
                });
            })();
            </script>
        </div>
        <?php
    }
}
