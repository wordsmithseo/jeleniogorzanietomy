<?php
/**
 * Trait JG_Map_Admin_Categories
 * Zarządzanie kategoriami miejsc i ciekawostek.
 */
trait JG_Map_Admin_Categories {

    public function render_place_categories_page() {
        // Get current data
        $categories = JG_Map_Ajax_Handlers::get_place_categories();

        // Extended emoji list for picker - organized by category
        $common_emojis = array(
            // Food & Dining
            '🍽️', '🍴', '🥄', '🍕', '🍔', '🌭', '🥪', '🌮', '🌯', '🥙', '🧆', '🥚', '🍳', '🥘', '🍲', '🥣', '🥗', '🍿', '🧈', '🧂', '🥫',
            '🍱', '🍘', '🍙', '🍚', '🍛', '🍜', '🍝', '🍠', '🍢', '🍣', '🍤', '🍥', '🥮', '🍡', '🥟', '🥠', '🥡',
            '🍦', '🍧', '🍨', '🍩', '🍪', '🎂', '🍰', '🧁', '🥧', '🍫', '🍬', '🍭', '🍮', '🍯',
            '🍼', '🥛', '☕', '🍵', '🧃', '🥤', '🧋', '🍶', '🍺', '🍻', '🥂', '🍷', '🥃', '🍸', '🍹', '🍾',
            // Buildings & Places
            '🏛️', '🏢', '🏠', '🏡', '🏘️', '🏚️', '🏭', '🏬', '🏣', '🏤', '🏥', '🏦', '🏨', '🏩', '🏪', '🏫', '⛪', '🕌', '🕍', '🛕', '⛩️', '🏰', '🏯', '🗼', '🗽', '⛲', '🎡', '🎢', '🎠', '🎪',
            // Nature & Parks
            '🌲', '🌳', '🌴', '🌿', '🍀', '🍃', '🍂', '🍁', '🌾', '🌻', '🌺', '🌸', '🌷', '🌹', '💐', '🏞️', '🌱', '🪴', '🪻', '🪷', '🏕️', '⛺', '🏖️', '🏜️', '🏔️', '⛰️', '🌄', '🌅',
            // Sports & Recreation
            '⚽', '🏀', '🏈', '⚾', '🎾', '🏐', '🏉', '🎱', '🏓', '🏸', '🥅', '⛳', '🏒', '🥊', '🎣', '🤿', '🎿', '⛷️', '🏂', '🛷', '⛸️', '🏋️', '🤸', '🧘', '🏃', '🚴', '🏊', '🎮', '🎳', '🧗',
            // Culture & Entertainment
            '🎭', '🎨', '🖼️', '🎬', '📽️', '🎤', '🎧', '🎼', '🎹', '🥁', '🎷', '🎺', '🎸', '🪕', '🎻', '🎪', '🎟️', '🏟️', '📚', '📖', '📕', '📗', '📘', '📙',
            // History & Heritage
            '🏰', '🏯', '⛪', '🕌', '🏛️', '🗿', '🏺', '⚱️', '🗽', '🗼', '⚔️', '🛡️', '👑', '📜', '🗺️',
            // Services & Commerce
            '🏢', '🏪', '🏬', '🏦', '🏨', '🏥', '💈', '🛒', '🛍️', '💇', '💆', '🧖', '🛁', '🚿', '✂️', '💊', '💉', '🏧',
            // Transport
            '🚗', '🚌', '🚎', '🚐', '🚕', '🚖', '🛻', '🚚', '🚛', '🚜', '🏎️', '🏍️', '🛵', '🚲', '🛴', '🚋', '🚃', '🚈', '🚇', '🚊', '🚝', '🚆', '🚂', '✈️', '🛫', '🛬', '🛩️', '🚁', '🚀', '🛶', '⛵', '🚤', '🛥️', '⛴️', '🚢', '🅿️',
            // Other useful
            '✨', '⭐', '🌟', '💫', '🔥', '💎', '🔑', '🗝️', '📍', '🎯', '❤️', '💙', '💚', '💛', '🧡', '💜', '🖤', '🤍', '🤎', 'ℹ️', '🆕', '🆓', '🆙', '🆗', '🆒'
        );
        ?>
        <div class="wrap">
            <?php $this->render_page_header('Zarządzanie kategoriami miejsc'); ?>

            <style>
                .jg-category-editor { max-width: 800px; margin-top: 20px; }
                .jg-category-editor .card { background: #fff; padding: 18px 20px; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 1px 4px rgba(0,0,0,.06); margin-bottom: 20px; }
                .jg-category-editor h2 { margin-top: 0; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb; margin-bottom: 16px; font-size: 15px; font-weight: 700; color: #111827; }
                .jg-category-list { list-style: none; padding: 0; margin: 0; }
                .jg-category-item {
                    display: flex; align-items: center; gap: 10px; padding: 12px;
                    border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 8px;
                    background: #f8fafc; transition: all 0.15s;
                }
                .jg-category-item:hover { background: #f0f7ff; border-color: #93c5fd; }
                .jg-category-item .cat-icon { font-size: calc(20 * var(--jg)); width: 30px; text-align: center; }
                .jg-category-item .cat-name { flex: 1; font-weight: 500; }
                .jg-action-btn { background: none; border: none; cursor: pointer; padding: 4px 8px; border-radius: 4px; transition: background 0.2s; }
                .jg-action-btn:hover { background: #e0e0e0; }
                .jg-action-btn.delete:hover { background: #ffebee; color: #c62828; }
                .jg-add-form { display: none; padding: 15px; background: #f5f5f5; border-radius: 6px; margin-top: 15px; }
                .jg-add-form.visible { display: block; }
                .jg-add-form label { display: block; margin-bottom: 5px; font-weight: 500; }
                .jg-add-form input[type="text"] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px; }
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
                .jg-btn-row { display: flex; gap: 10px; margin-top: 15px; }
                .jg-edit-inline { display: none; padding: 15px; background: #fff3e0; border-radius: 6px; margin-top: 15px; }
                .jg-edit-inline.visible { display: block; }
                .jg-edit-inline input { padding: 6px; border: 1px solid #ddd; border-radius: 4px; }
            </style>

            <div class="jg-category-editor">
                <div class="card">
                    <h2>Kategorie miejsc</h2>
                    <p class="description">Kategorie pomagają użytkownikom filtrować i organizować miejsca na mapie.</p>

                    <ul class="jg-category-list" id="jg-place-category-list">
                        <?php foreach ($categories as $key => $category): ?>
                        <li class="jg-category-item" data-key="<?php echo esc_attr($key); ?>">
                            <span class="cat-icon"><?php echo esc_html($category['icon'] ?? '📍'); ?></span>
                            <span class="cat-name"><?php echo esc_html($category['label']); ?></span>
                            <code class="cat-slug"><?php echo esc_html($key); ?></code>
                            <?php if (!empty($category['has_menu'])): ?>
                            <span title="Posiada menu" style="font-size:14px;opacity:0.7">🍽️</span>
                            <?php endif; ?>
                            <?php if (!empty($category['serves_cuisine'])): ?>
                            <span title="Miejsce serwujące jedzenie" style="font-size:14px;opacity:0.7">🥗</span>
                            <?php endif; ?>
                            <?php if (!empty($category['has_price_range'])): ?>
                            <span title="Zakres cenowy" style="font-size:14px;opacity:0.7">💰</span>
                            <?php endif; ?>
                            <?php if (!empty($category['show_promo'])): ?>
                            <span title="Ramka promocyjna" style="font-size:14px;opacity:0.7">💼</span>
                            <?php endif; ?>
                            <?php if (!empty($category['offerings_label'])): ?>
                            <span title="Lista ofert: <?php echo esc_attr($category['offerings_label']); ?>" style="font-size:14px;opacity:0.7">📋</span>
                            <?php endif; ?>
                            <button class="jg-action-btn" onclick="jgEditPlaceCategory('<?php echo esc_js($key); ?>', '<?php echo esc_js($category['label']); ?>', '<?php echo esc_js($category['icon'] ?? '📍'); ?>', <?php echo !empty($category['has_menu']) ? 'true' : 'false'; ?>, <?php echo !empty($category['serves_cuisine']) ? 'true' : 'false'; ?>, <?php echo !empty($category['has_price_range']) ? 'true' : 'false'; ?>, <?php echo !empty($category['show_promo']) ? 'true' : 'false'; ?>, '<?php echo esc_js($category['offerings_label'] ?? ''); ?>')" title="Edytuj">✏️</button>
                            <button class="jg-action-btn delete" onclick="jgDeletePlaceCategory('<?php echo esc_js($key); ?>')" title="Usuń">🗑️</button>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <button class="button" onclick="jgToggleAddPlaceCategory()">+ Dodaj kategorię</button>

                    <div class="jg-add-form" id="jg-add-place-category-form">
                        <label for="new-place-cat-key">Klucz kategorii (bez spacji, małe litery)</label>
                        <input type="text" id="new-place-cat-key" placeholder="np. gastronomia">

                        <label for="new-place-cat-label">Nazwa wyświetlana</label>
                        <input type="text" id="new-place-cat-label" placeholder="np. Gastronomia">

                        <label>Ikona</label>
                        <div class="jg-icon-preview">
                            <div class="preview" id="place-icon-preview">📍</div>
                            <span>Wybierz ikonę z listy lub wklej własne emoji</span>
                        </div>

                        <div class="jg-manual-emoji">
                            <input type="text" id="new-place-cat-icon-manual" maxlength="4" placeholder="📍" oninput="jgManualPlaceEmojiInput(this)">
                            <span class="hint">Wklej własne emoji</span>
                        </div>

                        <div class="jg-emoji-picker" id="place-emoji-picker">
                            <?php foreach ($common_emojis as $emoji): ?>
                            <button type="button" class="jg-emoji-btn" onclick="jgSelectPlaceEmoji('<?php echo $emoji; ?>')"><?php echo $emoji; ?></button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="new-place-cat-icon" value="📍">

                        <label style="display:flex;align-items:center;gap:8px;margin-top:10px;cursor:pointer">
                            <input type="checkbox" id="new-place-cat-has-menu" value="1">
                            🍽️ Kategoria posiada menu (włącz opcję dodawania menu dla miejsc)
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;margin-top:8px;cursor:pointer">
                            <input type="checkbox" id="new-place-cat-serves-cuisine" value="1">
                            🥗 Miejsce serwujące jedzenie (dodaje pole rodzaju kuchni i servesCuisine do schematu)
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;margin-top:8px;cursor:pointer">
                            <input type="checkbox" id="new-place-cat-has-price-range" value="1">
                            💰 Zakres cenowy (dodaje pole zakresu cenowego i priceRange do schematu)
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;margin-top:8px;cursor:pointer">
                            <input type="checkbox" id="new-place-cat-show-promo" value="1">
                            💼 Wyświetlaj ramkę promocyjną „Jesteś właścicielem?" (mapa i strona pineski)
                        </label>
                        <label style="display:block;margin-top:10px;font-weight:500">📋 Etykieta listy ofert (zostaw puste, aby wyłączyć)</label>
                        <input type="text" id="new-place-cat-offerings-label" placeholder='np. "Usługi" lub "Produkty"' maxlength="50" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;margin-top:4px">
                        <p class="description" style="margin:4px 0 0">Pojawi się jako przycisk obok Menu w oknie pineski. Właściciel może dodać listę pozycji z cenami.</p>

                        <div class="jg-btn-row">
                            <button class="button button-primary" onclick="jgSavePlaceCategory()">Zapisz</button>
                            <button class="button" onclick="jgToggleAddPlaceCategory()">Anuluj</button>
                        </div>
                    </div>

                    <!-- Edit category inline -->
                    <div class="jg-edit-inline" id="jg-edit-place-category-form">
                        <label>Edytuj kategorię</label>
                        <input type="hidden" id="edit-place-cat-key">

                        <label for="edit-place-cat-label" style="margin-top:10px">Nazwa</label>
                        <input type="text" id="edit-place-cat-label" style="width: 100%; margin-bottom: 10px;">

                        <label>Ikona</label>
                        <div class="jg-icon-preview">
                            <div class="preview" id="edit-place-icon-preview">📍</div>
                            <span>Wybierz ikonę z listy lub wklej własne emoji</span>
                        </div>

                        <div class="jg-manual-emoji">
                            <input type="text" id="edit-place-cat-icon-manual" maxlength="4" placeholder="📍" oninput="jgManualPlaceEmojiInputEdit(this)">
                            <span class="hint">Wklej własne emoji</span>
                        </div>

                        <div class="jg-emoji-picker" id="edit-place-emoji-picker">
                            <?php foreach ($common_emojis as $emoji): ?>
                            <button type="button" class="jg-emoji-btn" onclick="jgSelectPlaceEmojiEdit('<?php echo $emoji; ?>')"><?php echo $emoji; ?></button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="edit-place-cat-icon" value="">

                        <label style="display:flex;align-items:center;gap:8px;margin-top:10px;cursor:pointer">
                            <input type="checkbox" id="edit-place-cat-has-menu" value="1">
                            🍽️ Kategoria posiada menu
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;margin-top:8px;cursor:pointer">
                            <input type="checkbox" id="edit-place-cat-serves-cuisine" value="1">
                            🥗 Miejsce serwujące jedzenie
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;margin-top:8px;cursor:pointer">
                            <input type="checkbox" id="edit-place-cat-has-price-range" value="1">
                            💰 Zakres cenowy
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;margin-top:8px;cursor:pointer">
                            <input type="checkbox" id="edit-place-cat-show-promo" value="1">
                            💼 Wyświetlaj ramkę promocyjną „Jesteś właścicielem?"
                        </label>
                        <label style="display:block;margin-top:10px;font-weight:500">📋 Etykieta listy ofert (zostaw puste, aby wyłączyć)</label>
                        <input type="text" id="edit-place-cat-offerings-label" placeholder='np. "Usługi" lub "Produkty"' maxlength="50" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;margin-top:4px">
                        <p class="description" style="margin:4px 0 0">Pojawi się jako przycisk obok Menu w oknie pineski.</p>

                        <div class="jg-btn-row">
                            <button class="button button-primary" onclick="jgUpdatePlaceCategory()">Zapisz zmiany</button>
                            <button class="button" onclick="jgCancelEditPlaceCategory()">Anuluj</button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            (function() {
                const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
                const nonce = '<?php echo wp_create_nonce('jg_map_place_categories_nonce'); ?>';

                // Store current data
                let categories = <?php echo json_encode($categories); ?>;

                // Emoji validation function
                function jgIsValidEmoji(str) {
                    str = str.trim();
                    if (!str) return false;
                    if (/[a-zA-Z0-9ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/.test(str)) return false;
                    return /\p{Extended_Pictographic}/u.test(str);
                }

                function jgGetFirstPlaceEmoji() {
                    const btn = document.querySelector('#place-emoji-picker .jg-emoji-btn');
                    return btn ? btn.textContent.trim() : '📍';
                }

                // Toggle add form
                window.jgToggleAddPlaceCategory = function() {
                    const form = document.getElementById('jg-add-place-category-form');
                    form.classList.toggle('visible');
                    document.getElementById('jg-edit-place-category-form').classList.remove('visible');
                };

                // Manual emoji input for new category
                window.jgManualPlaceEmojiInput = function(input) {
                    const emoji = input.value.trim();
                    if (emoji) {
                        if (jgIsValidEmoji(emoji)) {
                            input.classList.remove('invalid');
                            document.getElementById('place-icon-preview').textContent = emoji;
                            document.getElementById('new-place-cat-icon').value = emoji;
                        } else {
                            input.classList.add('invalid');
                        }
                        document.querySelectorAll('#place-emoji-picker .jg-emoji-btn').forEach(btn => {
                            btn.classList.remove('selected');
                        });
                    } else {
                        input.classList.remove('invalid');
                    }
                };

                // Select emoji for new category
                window.jgSelectPlaceEmoji = function(emoji) {
                    document.getElementById('place-icon-preview').textContent = emoji;
                    document.getElementById('new-place-cat-icon').value = emoji;
                    document.getElementById('new-place-cat-icon-manual').value = emoji;
                    document.getElementById('new-place-cat-icon-manual').classList.remove('invalid');
                    document.querySelectorAll('#place-emoji-picker .jg-emoji-btn').forEach(btn => {
                        btn.classList.toggle('selected', btn.textContent === emoji);
                    });
                };

                // Save new category
                window.jgSavePlaceCategory = function() {
                    const key = document.getElementById('new-place-cat-key').value.trim().toLowerCase().replace(/\s+/g, '_');
                    const label = document.getElementById('new-place-cat-label').value.trim();
                    let icon = document.getElementById('new-place-cat-icon').value || '📍';
                    const hasMenu = document.getElementById('new-place-cat-has-menu').checked ? '1' : '0';
                    const servesCuisine = document.getElementById('new-place-cat-serves-cuisine').checked ? '1' : '0';
                    const hasPriceRange = document.getElementById('new-place-cat-has-price-range').checked ? '1' : '0';
                    const showPromo = document.getElementById('new-place-cat-show-promo').checked ? '1' : '0';
                    const offeringsLabel = document.getElementById('new-place-cat-offerings-label').value.trim();
                    if (!jgIsValidEmoji(icon)) {
                        icon = jgGetFirstPlaceEmoji();
                    }

                    if (!key || !label) {
                        alert('Wypełnij wszystkie pola');
                        return;
                    }

                    if (categories[key]) {
                        alert('Kategoria o tym kluczu już istnieje');
                        return;
                    }

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'jg_save_place_category',
                            nonce: nonce,
                            key: key,
                            label: label,
                            icon: icon,
                            has_menu: hasMenu,
                            serves_cuisine: servesCuisine,
                            has_price_range: hasPriceRange,
                            show_promo: showPromo,
                            offerings_label: offeringsLabel
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

                // Edit category
                window.jgEditPlaceCategory = function(key, label, icon, hasMenu, servesCuisine, hasPriceRange, showPromo, offeringsLabel) {
                    document.getElementById('jg-add-place-category-form').classList.remove('visible');
                    const form = document.getElementById('jg-edit-place-category-form');
                    form.classList.add('visible');
                    document.getElementById('edit-place-cat-key').value = key;
                    document.getElementById('edit-place-cat-label').value = label;
                    document.getElementById('edit-place-cat-icon').value = icon;
                    document.getElementById('edit-place-icon-preview').textContent = icon;
                    document.getElementById('edit-place-cat-icon-manual').value = icon;
                    document.getElementById('edit-place-cat-icon-manual').classList.remove('invalid');
                    document.getElementById('edit-place-cat-has-menu').checked = !!hasMenu;
                    document.getElementById('edit-place-cat-serves-cuisine').checked = !!servesCuisine;
                    document.getElementById('edit-place-cat-has-price-range').checked = !!hasPriceRange;
                    document.getElementById('edit-place-cat-show-promo').checked = !!showPromo;
                    document.getElementById('edit-place-cat-offerings-label').value = offeringsLabel || '';

                    // Highlight current emoji
                    document.querySelectorAll('#edit-place-emoji-picker .jg-emoji-btn').forEach(btn => {
                        btn.classList.toggle('selected', btn.textContent === icon);
                    });

                    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                };

                // Cancel edit
                window.jgCancelEditPlaceCategory = function() {
                    document.getElementById('jg-edit-place-category-form').classList.remove('visible');
                };

                // Manual emoji input for edit
                window.jgManualPlaceEmojiInputEdit = function(input) {
                    const emoji = input.value.trim();
                    if (emoji) {
                        if (jgIsValidEmoji(emoji)) {
                            input.classList.remove('invalid');
                            document.getElementById('edit-place-icon-preview').textContent = emoji;
                            document.getElementById('edit-place-cat-icon').value = emoji;
                        } else {
                            input.classList.add('invalid');
                        }
                        document.querySelectorAll('#edit-place-emoji-picker .jg-emoji-btn').forEach(btn => {
                            btn.classList.remove('selected');
                        });
                    } else {
                        input.classList.remove('invalid');
                    }
                };

                // Select emoji for edit
                window.jgSelectPlaceEmojiEdit = function(emoji) {
                    document.getElementById('edit-place-icon-preview').textContent = emoji;
                    document.getElementById('edit-place-cat-icon').value = emoji;
                    document.getElementById('edit-place-cat-icon-manual').value = emoji;
                    document.getElementById('edit-place-cat-icon-manual').classList.remove('invalid');
                    document.querySelectorAll('#edit-place-emoji-picker .jg-emoji-btn').forEach(btn => {
                        btn.classList.toggle('selected', btn.textContent === emoji);
                    });
                };

                // Update category
                window.jgUpdatePlaceCategory = function() {
                    const key = document.getElementById('edit-place-cat-key').value;
                    const label = document.getElementById('edit-place-cat-label').value.trim();
                    let icon = document.getElementById('edit-place-cat-icon').value || '📍';
                    const hasMenu = document.getElementById('edit-place-cat-has-menu').checked ? '1' : '0';
                    const servesCuisine = document.getElementById('edit-place-cat-serves-cuisine').checked ? '1' : '0';
                    const hasPriceRange = document.getElementById('edit-place-cat-has-price-range').checked ? '1' : '0';
                    const showPromo = document.getElementById('edit-place-cat-show-promo').checked ? '1' : '0';
                    const offeringsLabel = document.getElementById('edit-place-cat-offerings-label').value.trim();
                    if (!jgIsValidEmoji(icon)) {
                        icon = jgGetFirstPlaceEmoji();
                    }

                    if (!label) {
                        alert('Nazwa nie może być pusta');
                        return;
                    }

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'jg_update_place_category',
                            nonce: nonce,
                            key: key,
                            label: label,
                            icon: icon,
                            has_menu: hasMenu,
                            serves_cuisine: servesCuisine,
                            has_price_range: hasPriceRange,
                            show_promo: showPromo,
                            offerings_label: offeringsLabel
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

                // Delete category
                window.jgDeletePlaceCategory = function(key) {
                    if (!confirm('Czy na pewno chcesz usunąć tę kategorię?')) {
                        return;
                    }

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'jg_delete_place_category',
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

    public function render_curiosity_categories_page() {
        // Get current data
        $categories = JG_Map_Ajax_Handlers::get_curiosity_categories();

        // Extended emoji list for picker - organized by category
        $common_emojis = array(
            // History & Heritage
            '📜', '📖', '📚', '🏰', '🏯', '⛪', '🕌', '🏛️', '🗿', '🏺', '⚱️', '🗽', '🗼', '⚔️', '🛡️', '👑', '🗺️', '🧭', '📯', '🎺',
            // Nature & Wildlife
            '🦋', '🐦', '🦅', '🦉', '🐝', '🐛', '🐜', '🐞', '🦗', '🕷️', '🐀', '🐁', '🐿️', '🦔', '🦇', '🐺', '🦊', '🦝', '🐻', '🐨', '🐼', '🦁', '🐯', '🐸', '🦎', '🐍', '🐢', '🦕', '🦖',
            '🌲', '🌳', '🌴', '🌿', '🍀', '🍃', '🍂', '🍁', '🌾', '🌻', '🌺', '🌸', '🌷', '🌹', '💐', '🏞️', '🌱', '🪴', '🪻', '🪷', '🍄', '🪨', '💎', '🌋', '⛰️', '🏔️',
            // Architecture
            '🏰', '🏯', '🗼', '🏛️', '⛪', '🕌', '🕍', '🛕', '⛩️', '🏚️', '🏗️', '🧱', '🪵', '🪟', '🚪', '🏠', '🏡', '🏢', '🏬', '🏭', '🌉', '🗿',
            // Stories & Legends
            '📖', '📕', '📗', '📘', '📙', '📓', '📔', '📒', '📃', '📜', '📰', '🗞️', '✒️', '🖋️', '🖊️', '📝', '💭', '💬', '🗯️', '👻', '🧙', '🧚', '🧛', '🧜', '🧝', '🧞', '🧟', '🐉', '🐲', '🦄', '🔮', '🪄', '✨',
            // Mystery & Discovery
            '🔍', '🔎', '🧩', '🗝️', '🔑', '🗃️', '🗄️', '📦', '🎁', '💡', '🔦', '🕯️', '🪔', '⚗️', '🔬', '🔭', '📡', '🧲', '⚙️', '🛠️',
            // Culture & Art
            '🎭', '🎨', '🖼️', '🎬', '📽️', '🎤', '🎧', '🎼', '🎹', '🥁', '🎷', '🎺', '🎸', '🪕', '🎻', '🎪', '🎟️',
            // Water & Geography
            '💧', '💦', '🌊', '🏝️', '🏖️', '⛵', '🚣', '🌅', '🌄', '🏕️', '⛺', '🌈', '☀️', '🌙', '⭐', '🌟', '💫',
            // Other useful
            '❤️', '💙', '💚', '💛', '🧡', '💜', '🖤', '🤍', '🤎', '❓', '❗', '💯', '🎯', '📍', 'ℹ️', '🆕', '🏆', '🥇', '🎖️', '🏅'
        );
        ?>
        <div class="wrap">
            <?php $this->render_page_header('Zarządzanie kategoriami ciekawostek'); ?>

            <style>
                .jg-category-editor { max-width: 800px; margin-top: 20px; }
                .jg-category-editor .card { background: #fff; padding: 18px 20px; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 1px 4px rgba(0,0,0,.06); margin-bottom: 20px; }
                .jg-category-editor h2 { margin-top: 0; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb; margin-bottom: 16px; font-size: 15px; font-weight: 700; color: #111827; }
                .jg-category-list { list-style: none; padding: 0; margin: 0; }
                .jg-category-item {
                    display: flex; align-items: center; gap: 10px; padding: 12px;
                    border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 8px;
                    background: #f8fafc; transition: all 0.15s;
                }
                .jg-category-item:hover { background: #f0f7ff; border-color: #93c5fd; }
                .jg-category-item .cat-icon { font-size: calc(20 * var(--jg)); width: 30px; text-align: center; }
                .jg-category-item .cat-name { flex: 1; font-weight: 500; }
                .jg-action-btn { background: none; border: none; cursor: pointer; padding: 4px 8px; border-radius: 4px; transition: background 0.2s; }
                .jg-action-btn:hover { background: #e0e0e0; }
                .jg-action-btn.delete:hover { background: #ffebee; color: #c62828; }
                .jg-add-form { display: none; padding: 15px; background: #f5f5f5; border-radius: 6px; margin-top: 15px; }
                .jg-add-form.visible { display: block; }
                .jg-add-form label { display: block; margin-bottom: 5px; font-weight: 500; }
                .jg-add-form input[type="text"] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px; }
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
                .jg-btn-row { display: flex; gap: 10px; margin-top: 15px; }
                .jg-edit-inline { display: none; padding: 15px; background: #fff3e0; border-radius: 6px; margin-top: 15px; }
                .jg-edit-inline.visible { display: block; }
                .jg-edit-inline input { padding: 6px; border: 1px solid #ddd; border-radius: 4px; }
            </style>

            <div class="jg-category-editor">
                <div class="card">
                    <h2>Kategorie ciekawostek</h2>
                    <p class="description">Kategorie pomagają użytkownikom filtrować i organizować ciekawostki na mapie.</p>

                    <ul class="jg-category-list" id="jg-curiosity-category-list">
                        <?php foreach ($categories as $key => $category): ?>
                        <li class="jg-category-item" data-key="<?php echo esc_attr($key); ?>">
                            <span class="cat-icon"><?php echo esc_html($category['icon'] ?? '📖'); ?></span>
                            <span class="cat-name"><?php echo esc_html($category['label']); ?></span>
                            <code class="cat-slug"><?php echo esc_html($key); ?></code>
                            <button class="jg-action-btn" onclick="jgEditCuriosityCategory('<?php echo esc_js($key); ?>', '<?php echo esc_js($category['label']); ?>', '<?php echo esc_js($category['icon'] ?? '📖'); ?>')" title="Edytuj">✏️</button>
                            <button class="jg-action-btn delete" onclick="jgDeleteCuriosityCategory('<?php echo esc_js($key); ?>')" title="Usuń">🗑️</button>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <button class="button" onclick="jgToggleAddCuriosityCategory()">+ Dodaj kategorię</button>

                    <div class="jg-add-form" id="jg-add-curiosity-category-form">
                        <label for="new-curiosity-cat-key">Klucz kategorii (bez spacji, małe litery)</label>
                        <input type="text" id="new-curiosity-cat-key" placeholder="np. historyczne">

                        <label for="new-curiosity-cat-label">Nazwa wyświetlana</label>
                        <input type="text" id="new-curiosity-cat-label" placeholder="np. Historyczne">

                        <label>Ikona</label>
                        <div class="jg-icon-preview">
                            <div class="preview" id="curiosity-icon-preview">📖</div>
                            <span>Wybierz ikonę z listy lub wklej własne emoji</span>
                        </div>

                        <div class="jg-manual-emoji">
                            <input type="text" id="new-curiosity-cat-icon-manual" maxlength="4" placeholder="📖" oninput="jgManualCuriosityEmojiInput(this)">
                            <span class="hint">Wklej własne emoji</span>
                        </div>

                        <div class="jg-emoji-picker" id="curiosity-emoji-picker">
                            <?php foreach ($common_emojis as $emoji): ?>
                            <button type="button" class="jg-emoji-btn" onclick="jgSelectCuriosityEmoji('<?php echo $emoji; ?>')"><?php echo $emoji; ?></button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="new-curiosity-cat-icon" value="📖">

                        <div class="jg-btn-row">
                            <button class="button button-primary" onclick="jgSaveCuriosityCategory()">Zapisz</button>
                            <button class="button" onclick="jgToggleAddCuriosityCategory()">Anuluj</button>
                        </div>
                    </div>

                    <!-- Edit category inline -->
                    <div class="jg-edit-inline" id="jg-edit-curiosity-category-form">
                        <label>Edytuj kategorię</label>
                        <input type="hidden" id="edit-curiosity-cat-key">

                        <label for="edit-curiosity-cat-label" style="margin-top:10px">Nazwa</label>
                        <input type="text" id="edit-curiosity-cat-label" style="width: 100%; margin-bottom: 10px;">

                        <label>Ikona</label>
                        <div class="jg-icon-preview">
                            <div class="preview" id="edit-curiosity-icon-preview">📖</div>
                            <span>Wybierz ikonę z listy lub wklej własne emoji</span>
                        </div>

                        <div class="jg-manual-emoji">
                            <input type="text" id="edit-curiosity-cat-icon-manual" maxlength="4" placeholder="📖" oninput="jgManualCuriosityEmojiInputEdit(this)">
                            <span class="hint">Wklej własne emoji</span>
                        </div>

                        <div class="jg-emoji-picker" id="edit-curiosity-emoji-picker">
                            <?php foreach ($common_emojis as $emoji): ?>
                            <button type="button" class="jg-emoji-btn" onclick="jgSelectCuriosityEmojiEdit('<?php echo $emoji; ?>')"><?php echo $emoji; ?></button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="edit-curiosity-cat-icon" value="">

                        <div class="jg-btn-row">
                            <button class="button button-primary" onclick="jgUpdateCuriosityCategory()">Zapisz zmiany</button>
                            <button class="button" onclick="jgCancelEditCuriosityCategory()">Anuluj</button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            (function() {
                const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
                const nonce = '<?php echo wp_create_nonce('jg_map_curiosity_categories_nonce'); ?>';

                // Store current data
                let categories = <?php echo json_encode($categories); ?>;

                // Emoji validation function
                function jgIsValidEmoji(str) {
                    str = str.trim();
                    if (!str) return false;
                    if (/[a-zA-Z0-9ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/.test(str)) return false;
                    return /\p{Extended_Pictographic}/u.test(str);
                }

                function jgGetFirstCuriosityEmoji() {
                    const btn = document.querySelector('#curiosity-emoji-picker .jg-emoji-btn');
                    return btn ? btn.textContent.trim() : '📖';
                }

                // Toggle add form
                window.jgToggleAddCuriosityCategory = function() {
                    const form = document.getElementById('jg-add-curiosity-category-form');
                    form.classList.toggle('visible');
                    document.getElementById('jg-edit-curiosity-category-form').classList.remove('visible');
                };

                // Manual emoji input for new category
                window.jgManualCuriosityEmojiInput = function(input) {
                    const emoji = input.value.trim();
                    if (emoji) {
                        if (jgIsValidEmoji(emoji)) {
                            input.classList.remove('invalid');
                            document.getElementById('curiosity-icon-preview').textContent = emoji;
                            document.getElementById('new-curiosity-cat-icon').value = emoji;
                        } else {
                            input.classList.add('invalid');
                        }
                        document.querySelectorAll('#curiosity-emoji-picker .jg-emoji-btn').forEach(btn => {
                            btn.classList.remove('selected');
                        });
                    } else {
                        input.classList.remove('invalid');
                    }
                };

                // Select emoji for new category
                window.jgSelectCuriosityEmoji = function(emoji) {
                    document.getElementById('curiosity-icon-preview').textContent = emoji;
                    document.getElementById('new-curiosity-cat-icon').value = emoji;
                    document.getElementById('new-curiosity-cat-icon-manual').value = emoji;
                    document.getElementById('new-curiosity-cat-icon-manual').classList.remove('invalid');
                    document.querySelectorAll('#curiosity-emoji-picker .jg-emoji-btn').forEach(btn => {
                        btn.classList.toggle('selected', btn.textContent === emoji);
                    });
                };

                // Save new category
                window.jgSaveCuriosityCategory = function() {
                    const key = document.getElementById('new-curiosity-cat-key').value.trim().toLowerCase().replace(/\s+/g, '_');
                    const label = document.getElementById('new-curiosity-cat-label').value.trim();
                    let icon = document.getElementById('new-curiosity-cat-icon').value || '📖';
                    if (!jgIsValidEmoji(icon)) {
                        icon = jgGetFirstCuriosityEmoji();
                    }

                    if (!key || !label) {
                        alert('Wypełnij wszystkie pola');
                        return;
                    }

                    if (categories[key]) {
                        alert('Kategoria o tym kluczu już istnieje');
                        return;
                    }

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'jg_save_curiosity_category',
                            nonce: nonce,
                            key: key,
                            label: label,
                            icon: icon
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

                // Edit category
                window.jgEditCuriosityCategory = function(key, label, icon) {
                    document.getElementById('jg-add-curiosity-category-form').classList.remove('visible');
                    const form = document.getElementById('jg-edit-curiosity-category-form');
                    form.classList.add('visible');
                    document.getElementById('edit-curiosity-cat-key').value = key;
                    document.getElementById('edit-curiosity-cat-label').value = label;
                    document.getElementById('edit-curiosity-cat-icon').value = icon;
                    document.getElementById('edit-curiosity-icon-preview').textContent = icon;
                    document.getElementById('edit-curiosity-cat-icon-manual').value = icon;
                    document.getElementById('edit-curiosity-cat-icon-manual').classList.remove('invalid');

                    // Highlight current emoji
                    document.querySelectorAll('#edit-curiosity-emoji-picker .jg-emoji-btn').forEach(btn => {
                        btn.classList.toggle('selected', btn.textContent === icon);
                    });

                    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                };

                // Cancel edit
                window.jgCancelEditCuriosityCategory = function() {
                    document.getElementById('jg-edit-curiosity-category-form').classList.remove('visible');
                };

                // Manual emoji input for edit
                window.jgManualCuriosityEmojiInputEdit = function(input) {
                    const emoji = input.value.trim();
                    if (emoji) {
                        if (jgIsValidEmoji(emoji)) {
                            input.classList.remove('invalid');
                            document.getElementById('edit-curiosity-icon-preview').textContent = emoji;
                            document.getElementById('edit-curiosity-cat-icon').value = emoji;
                        } else {
                            input.classList.add('invalid');
                        }
                        document.querySelectorAll('#edit-curiosity-emoji-picker .jg-emoji-btn').forEach(btn => {
                            btn.classList.remove('selected');
                        });
                    } else {
                        input.classList.remove('invalid');
                    }
                };

                // Select emoji for edit
                window.jgSelectCuriosityEmojiEdit = function(emoji) {
                    document.getElementById('edit-curiosity-icon-preview').textContent = emoji;
                    document.getElementById('edit-curiosity-cat-icon').value = emoji;
                    document.getElementById('edit-curiosity-cat-icon-manual').value = emoji;
                    document.getElementById('edit-curiosity-cat-icon-manual').classList.remove('invalid');
                    document.querySelectorAll('#edit-curiosity-emoji-picker .jg-emoji-btn').forEach(btn => {
                        btn.classList.toggle('selected', btn.textContent === emoji);
                    });
                };

                // Update category
                window.jgUpdateCuriosityCategory = function() {
                    const key = document.getElementById('edit-curiosity-cat-key').value;
                    const label = document.getElementById('edit-curiosity-cat-label').value.trim();
                    let icon = document.getElementById('edit-curiosity-cat-icon').value || '📖';
                    if (!jgIsValidEmoji(icon)) {
                        icon = jgGetFirstCuriosityEmoji();
                    }

                    if (!label) {
                        alert('Nazwa nie może być pusta');
                        return;
                    }

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'jg_update_curiosity_category',
                            nonce: nonce,
                            key: key,
                            label: label,
                            icon: icon
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

                // Delete category
                window.jgDeleteCuriosityCategory = function(key) {
                    if (!confirm('Czy na pewno chcesz usunąć tę kategorię?')) {
                        return;
                    }

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'jg_delete_curiosity_category',
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
