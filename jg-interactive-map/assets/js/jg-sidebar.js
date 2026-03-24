/**
 * JG Map Sidebar - List of pins with filtering, sorting and lazy loading
 */
(function($) {
    'use strict';

    // Check if sidebar exists on page
    if (!document.getElementById('jg-map-sidebar')) {
        return;
    }

    var PAGE_SIZE = 10;

    let sidebarPoints = [];
    let currentFilters = {
        types: ['miejsce', 'ciekawostka', 'zgloszenie'],
        myPlaces: false,
        sortBy: 'date_desc',
        placeCategories: [],
        curiosityCategories: []
    };

    // Fingerprint of current data for change detection
    let currentDataFingerprint = null;
    // Current sponsored pin ID to maintain consistency
    let currentSponsoredId = null;

    // LocalStorage cache keys (v2: added opening_hours to fingerprint)
    var SIDEBAR_CACHE_KEY      = 'jg_sidebar_cache_v2';
    var SIDEBAR_CACHE_META_KEY = 'jg_sidebar_cache_meta_v2';

    function getSidebarUserId() {
        return (window.JG_MAP_CFG && JG_MAP_CFG.currentUserId)
            ? JG_MAP_CFG.currentUserId.toString()
            : '0';
    }

    function loadSidebarFromCache() {
        try {
            var cached = localStorage.getItem(SIDEBAR_CACHE_KEY);
            var meta   = JSON.parse(localStorage.getItem(SIDEBAR_CACHE_META_KEY) || '{}');
            if (cached && meta.userId === getSidebarUserId()) {
                return JSON.parse(cached); // { points, stats }
            }
        } catch (e) {}
        return null;
    }

    function saveSidebarToCache(points, stats) {
        try {
            localStorage.setItem(SIDEBAR_CACHE_KEY, JSON.stringify({ points: points, stats: stats }));
            localStorage.setItem(SIDEBAR_CACHE_META_KEY, JSON.stringify({ userId: getSidebarUserId() }));
        } catch (e) {}
    }

    // Lazy loading state
    let allRegularPoints = [];
    let renderedRegularCount = 0;
    let isAppendingMore = false;

    /**
     * Initialize sidebar
     */
    function init() {
        // Generate category filter checkboxes
        initCategoryFilters();

        // Setup event listeners
        setupEventListeners();

        // Setup fixed tooltip for info-badges (avoids overflow:hidden clipping)
        setupBadgeTooltip();

        // Load from cache for instant display, then refresh in background
        var cached = loadSidebarFromCache();
        if (cached && cached.points && cached.points.length > 0) {
            sidebarPoints = cached.points;
            currentDataFingerprint = generateFingerprint(cached.points, cached.stats);
            updateStats(cached.stats || {});
            renderPoints(sidebarPoints);
            // Signal coordinator: sidebar ready (from cache)
            if (window._jgLoad) window._jgLoad.setSidebar();
            // Background refresh to pick up any changes (silent = no flicker)
            loadPoints(true);
        } else {
            loadPoints();
        }
    }

    /**
     * Creates a single fixed <div> tooltip in <body> and hooks mouseenter/leave
     * on every .jg-info-badge via event delegation.
     * Uses getBoundingClientRect() + viewport clamping so the tooltip is never
     * clipped by an overflow:hidden ancestor.
     */
    function setupBadgeTooltip() {
        // Singleton tooltip element
        var $tip = $('#jg-badge-tooltip');
        if ($tip.length === 0) {
            $tip = $('<div id="jg-badge-tooltip"></div>').appendTo('body');
        }

        var hideTimer = null;

        $('#jg-map-sidebar').on('mouseenter', '.jg-info-badge', function() {
            clearTimeout(hideTimer);

            var label = $(this).attr('data-jg-tip') || '';
            if (!label) return;

            // Make element visible for measurement but keep opacity:0 (from CSS)
            $tip
                .text(label)
                .removeClass('jg-badge-tooltip--above jg-badge-tooltip--below jg-badge-tooltip--visible')
                .css('display', 'block');

            var rect    = this.getBoundingClientRect();
            var tipW    = $tip.outerWidth(true);
            var tipH    = $tip.outerHeight(true);
            var margin  = 8; // px gap from viewport edges
            var gap     = 7; // px gap between badge and tooltip

            // Prefer above; fall back to below if not enough room
            var placeAbove = (rect.top - tipH - gap) >= margin;
            var top;
            if (placeAbove) {
                top = rect.top - tipH - gap;
                $tip.addClass('jg-badge-tooltip--above');
            } else {
                top = rect.bottom + gap;
                $tip.addClass('jg-badge-tooltip--below');
            }

            // Horizontal: centre over badge, then clamp to viewport
            var idealLeft   = rect.left + rect.width / 2 - tipW / 2;
            var clampedLeft = Math.max(margin, Math.min(idealLeft, window.innerWidth - tipW - margin));

            // Arrow points at badge centre regardless of clamping
            var arrowLeft = (rect.left + rect.width / 2) - clampedLeft;
            arrowLeft = Math.max(8, Math.min(arrowLeft, tipW - 8));

            $tip.css({
                top:  top + 'px',
                left: clampedLeft + 'px',
                '--arrow-left': arrowLeft + 'px'
            });

            // Force reflow so CSS transition fires opacity:0 → 1
            void $tip[0].offsetHeight;
            $tip.addClass('jg-badge-tooltip--visible');
        });

        $('#jg-map-sidebar').on('mouseleave', '.jg-info-badge', function() {
            hideTimer = setTimeout(function() {
                $tip
                    .removeClass('jg-badge-tooltip--visible jg-badge-tooltip--above jg-badge-tooltip--below')
                    .css('display', 'none');
            }, 80);
        });
    }

    /**
     * Initialize category filter checkboxes from config
     */
    function initCategoryFilters() {
        const placeCategories = (window.JG_MAP_CFG && JG_MAP_CFG.placeCategories) || {};
        const curiosityCategories = (window.JG_MAP_CFG && JG_MAP_CFG.curiosityCategories) || {};

        // Generate place category filters (sorted alphabetically)
        const $placeContainer = $('[data-category-type="miejsce"]');
        if ($placeContainer.length && Object.keys(placeCategories).length > 0) {
            const sortedPlace = Object.keys(placeCategories)
                .filter(function(key) { return placeCategories.hasOwnProperty(key); })
                .map(function(key) { return { key: key, label: placeCategories[key].label, icon: placeCategories[key].icon }; })
                .sort(function(a, b) { return a.label.localeCompare(b.label, 'pl'); });
            let html = '';
            for (let i = 0; i < sortedPlace.length; i++) {
                const cat = sortedPlace[i];
                html += '<label class="jg-sidebar-filter-label"><input type="checkbox" data-sidebar-place-category="' + cat.key + '" checked><span class="jg-sidebar-filter-icon">' + (cat.icon || '📍') + '</span><span class="jg-sidebar-filter-text">' + cat.label + '</span></label>';
            }
            $placeContainer.html(html);
            $('#jg-sidebar-place-categories').show();
        }

        // Generate curiosity category filters (sorted alphabetically)
        const $curiosityContainer = $('[data-category-type="ciekawostka"]');
        if ($curiosityContainer.length && Object.keys(curiosityCategories).length > 0) {
            const sortedCuriosity = Object.keys(curiosityCategories)
                .filter(function(key) { return curiosityCategories.hasOwnProperty(key); })
                .map(function(key) { return { key: key, label: curiosityCategories[key].label, icon: curiosityCategories[key].icon }; })
                .sort(function(a, b) { return a.label.localeCompare(b.label, 'pl'); });
            let html = '';
            for (let i = 0; i < sortedCuriosity.length; i++) {
                const cat = sortedCuriosity[i];
                html += '<label class="jg-sidebar-filter-label"><input type="checkbox" data-sidebar-curiosity-category="' + cat.key + '" checked><span class="jg-sidebar-filter-icon">' + (cat.icon || '💡') + '</span><span class="jg-sidebar-filter-text">' + cat.label + '</span></label>';
            }
            $curiosityContainer.html(html);
            $('#jg-sidebar-curiosity-categories').show();
        }
    }

    /**
     * Setup event listeners for filters and sorting
     */
    function setupEventListeners() {
        // Type filters
        $('[data-sidebar-type]').on('change', function() {
            updateTypeFilters();
            loadPoints();
        });

        // My places filter
        $('[data-sidebar-my-places]').on('change', function() {
            currentFilters.myPlaces = $(this).is(':checked');
            loadPoints();
        });

        // Place category filters
        $(document).on('change', '[data-sidebar-place-category]', function() {
            updatePlaceCategoryFilters();
            loadPoints();
        });

        // Curiosity category filters
        $(document).on('change', '[data-sidebar-curiosity-category]', function() {
            updateCuriosityCategoryFilters();
            loadPoints();
        });

        // Sort dropdown
        $('#jg-sidebar-sort-select').on('change', function() {
            currentFilters.sortBy = $(this).val();
            loadPoints();
        });

        // Collapsible sections - Multiple approaches to ensure it works

        // Method 1: Event delegation on parent
        $('#jg-map-sidebar').on('click', '.jg-sidebar-collapsible-header', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleCollapsible($(this));
        });

        // Method 2: Direct binding (backup)
        $(document).on('click', '.jg-sidebar-collapsible-header', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleCollapsible($(this));
        });

        // Method 3: Direct on ready
        $('.jg-sidebar-collapsible-header').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleCollapsible($(this));
        });

        // Lazy loading: detect scroll-to-bottom on the list container
        $('#jg-sidebar-list').on('scroll.lazyload', function() {
            var el = this;
            if (el.scrollTop + el.clientHeight >= el.scrollHeight - 100) {
                appendNextBatch();
            }
        });
    }

    /**
     * Toggle collapsible section
     */
    function toggleCollapsible($header) {
        const $content = $header.next('.jg-sidebar-collapsible-content');
        const $icon = $header.find('.jg-sidebar-toggle-icon');

        if ($content.length === 0) {
            return;
        }

        // Check current state before toggle
        const isCurrentlyVisible = $content.is(':visible');

        // Toggle content visibility with callback
        $content.slideToggle(200, function() {
            // Update icon after animation completes
            if (isCurrentlyVisible) {
                $icon.text('▼');
            } else {
                $icon.text('▲');
            }
        });
    }

    /**
     * Update type filters based on checkboxes
     */
    function updateTypeFilters() {
        const types = [];
        $('[data-sidebar-type]:checked').each(function() {
            types.push($(this).attr('data-sidebar-type'));
        });
        currentFilters.types = types;
    }

    /**
     * Update place category filters based on checkboxes
     */
    function updatePlaceCategoryFilters() {
        const categories = [];
        $('[data-sidebar-place-category]:checked').each(function() {
            categories.push($(this).attr('data-sidebar-place-category'));
        });
        currentFilters.placeCategories = categories;
    }

    /**
     * Update curiosity category filters based on checkboxes
     */
    function updateCuriosityCategoryFilters() {
        const categories = [];
        $('[data-sidebar-curiosity-category]:checked').each(function() {
            categories.push($(this).attr('data-sidebar-curiosity-category'));
        });
        currentFilters.curiosityCategories = categories;
    }

    /**
     * Generate fingerprint of points data for change detection
     * Returns a string that uniquely identifies the current state
     */
    function generateFingerprint(points, stats) {
        if (!points || points.length === 0) {
            return 'empty';
        }

        // Build fingerprint from all visible/editable fields so any change triggers re-render
        const pointsData = points.map(p => `${p.id}:${p.title}:${p.slug}:${p.type}:${p.votes_count}:${p.is_promo ? 1 : 0}:${p.featured_image || ''}:${p.lat}:${p.lng}:${p.has_description ? 1 : 0}:${p.has_tags ? 1 : 0}:${p.category || ''}:${p.images_count || 0}:${p.has_internal_links ? 1 : 0}:${p.has_external_links ? 1 : 0}:${p.has_incomplete_sections ? 1 : 0}:${p.opening_hours || ''}`).join(',');
        const statsData = stats ? `|${stats.total}:${stats.miejsce}:${stats.ciekawostka}:${stats.zgloszenie}` : '';
        return pointsData + statsData;
    }

    /**
     * Load points from server
     * @param {boolean} silent - If true, skip loading indicator (for sync refreshes)
     */
    function loadPoints(silent) {
        // Show loading only if not silent
        if (!silent) {
            $('#jg-sidebar-loading').show();
            $('#jg-sidebar-list').hide();
        }

        $.ajax({
            url: JG_MAP_CFG.ajax,
            type: 'POST',
            data: {
                action: 'jg_get_sidebar_points',
                _ajax_nonce: JG_MAP_CFG.nonce,
                type_filters: currentFilters.types,
                my_places: currentFilters.myPlaces,
                sort_by: currentFilters.sortBy,
                place_categories: currentFilters.placeCategories,
                curiosity_categories: currentFilters.curiosityCategories
            },
            success: function(response) {
                if (response.success && response.data) {
                    const newPoints = response.data.points;
                    const newStats = response.data.stats;
                    const newFingerprint = generateFingerprint(newPoints, newStats);

                    // Only re-render if data actually changed
                    if (newFingerprint !== currentDataFingerprint) {
                        currentDataFingerprint = newFingerprint;
                        sidebarPoints = newPoints;
                        updateStats(newStats);
                        renderPoints(sidebarPoints);
                        // Save fresh data to cache (only default filter state)
                        if (!currentFilters.myPlaces &&
                            currentFilters.types.length === 3 &&
                            currentFilters.placeCategories.length === 0 &&
                            currentFilters.curiosityCategories.length === 0) {
                            saveSidebarToCache(newPoints, newStats);
                        }
                    }
                    // If silent and no changes, do nothing (no flicker)
                } else if (!silent) {
                    showError('Nie udało się załadować listy pinezek');
                }

                if (!silent) {
                    $('#jg-sidebar-loading').hide();
                    $('#jg-sidebar-list').show();
                    // Signal coordinator: sidebar ready
                    if (window._jgLoad) window._jgLoad.setSidebar();
                }
            },
            error: function(xhr, status, error) {
                if (!silent) {
                    showError('Błąd połączenia z serwerem');
                    $('#jg-sidebar-loading').hide();
                    $('#jg-sidebar-list').show();
                    // Signal coordinator even on error so map loader doesn't hang
                    if (window._jgLoad) window._jgLoad.setSidebar();
                }
            }
        });
    }

    /**
     * Update statistics display
     */
    function updateStats(stats) {
        $('#jg-sidebar-stat-total').text(stats.total || 0);
        $('#jg-sidebar-stat-miejsce').text(stats.miejsce || 0);
        $('#jg-sidebar-stat-ciekawostka').text(stats.ciekawostka || 0);
        $('#jg-sidebar-stat-zgloszenie').text(stats.zgloszenie || 0);
    }

    /**
     * Render points list - always shows sponsored pin immediately,
     * then renders first PAGE_SIZE regular pins with lazy loading for the rest.
     */
    function renderPoints(points) {
        const $list = $('#jg-sidebar-list');
        $list.empty();

        // Reset lazy loading state
        allRegularPoints = [];
        renderedRegularCount = 0;
        isAppendingMore = false;

        if (!points || points.length === 0) {
            $list.html('<div class="jg-sidebar-empty">Brak pinezek spełniających kryteria</div>');
            currentSponsoredId = null;
            return;
        }

        // Separate sponsored and regular pins
        const sponsoredPoints = points.filter(p => p.is_promo);
        allRegularPoints = points.filter(p => !p.is_promo);

        // Add ONE sponsored pin in "Polecamy" section - always rendered immediately
        if (sponsoredPoints.length > 0) {
            // Try to keep the same sponsored pin if it's still available
            let selectedSponsored = null;
            if (currentSponsoredId !== null) {
                selectedSponsored = sponsoredPoints.find(p => p.id === currentSponsoredId);
            }

            // If not found or first load, pick a random one
            if (!selectedSponsored) {
                const randomIndex = Math.floor(Math.random() * sponsoredPoints.length);
                selectedSponsored = sponsoredPoints[randomIndex];
                currentSponsoredId = selectedSponsored.id;
            }

            $list.append('<div class="jg-sidebar-section-title">Polecamy:</div>');
            const $item = createPointItem(selectedSponsored);
            $list.append($item);
        } else {
            currentSponsoredId = null;
        }

        // Add regular pins section with lazy loading
        if (allRegularPoints.length > 0) {
            $list.append('<div class="jg-sidebar-section-title">Pinezki na mapie</div>');
            // Render first batch
            appendNextBatch();
        }

        // Update timestamps immediately after render (fixes stale "1 sekunda temu" from cache)
        refreshTimestamps();
    }

    /**
     * Append next PAGE_SIZE regular points to the list.
     * Called on initial render and on scroll-to-bottom.
     */
    function appendNextBatch() {
        if (isAppendingMore || renderedRegularCount >= allRegularPoints.length) {
            return;
        }

        isAppendingMore = true;

        const $list = $('#jg-sidebar-list');
        const batch = allRegularPoints.slice(renderedRegularCount, renderedRegularCount + PAGE_SIZE);

        batch.forEach(function(point) {
            $list.append(createPointItem(point));
        });

        renderedRegularCount += batch.length;
        isAppendingMore = false;
    }

    /**
     * Create HTML for single point item
     */
    function createPointItem(point) {
        const typeLabels = {
            'miejsce': 'Miejsce',
            'ciekawostka': 'Ciekawostka',
            'zgloszenie': 'Zgłoszenie'
        };

        const typeIcons = {
            'miejsce': '📍',
            'ciekawostka': '💡',
            'zgloszenie': '⚠️'
        };

        const $item = $('<div>')
            .addClass('jg-sidebar-item')
            .addClass('jg-sidebar-item--' + point.type)
            .attr('data-point-id', point.id)
            .attr('data-lat', point.lat)
            .attr('data-lng', point.lng);

        // Add sponsored badge
        if (point.is_promo) {
            $item.addClass('jg-sidebar-item--sponsored');
        }

        // Image (if available), star icon for sponsored, or "no photo" placeholder
        let imageHtml = '';
        if (point.featured_image) {
            imageHtml = `<div class="jg-sidebar-item__image" style="position:relative;width:80px;height:80px;flex-shrink:0;overflow:hidden;border-radius:8px;background:#f3f4f6">
                <img src="${point.featured_image}" alt="${escapeHtml(point.title)}" loading="lazy" style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;object-position:center" />
            </div>`;
        } else if (point.is_promo) {
            // Show gold star for sponsored places without image
            imageHtml = `<div class="jg-sidebar-item__image jg-sidebar-item__image--star">
                <span class="jg-sidebar-star-icon">⭐</span>
            </div>`;
        } else {
            // Show crossed-camera icon for places without image
            imageHtml = `<div class="jg-sidebar-item__image" style="position:relative;width:80px;height:80px;flex-shrink:0;border-radius:8px;background:#f3f4f6;display:flex;align-items:center;justify-content:center">
                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-label="Brak zdjęcia">
                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                    <circle cx="12" cy="13" r="4"/>
                </svg>
            </div>`;
        }

        // Badge (sponsored or type)
        let badgeHtml = '';
        if (point.is_promo) {
            badgeHtml = '<span class="jg-sidebar-item__badge jg-sidebar-item__badge--sponsored">⭐ Sponsorowane</span>';
        } else {
            const typeIcon = typeIcons[point.type] || '📍';
            const typeLabel = typeLabels[point.type] || point.type;
            badgeHtml = `<span class="jg-sidebar-item__badge jg-sidebar-item__badge--${point.type}">${typeIcon} ${typeLabel}</span>`;
        }

        // Votes display (only for non-sponsored items)
        let votesHtml = '';
        if (!point.is_promo) {
            let votesClass = 'jg-sidebar-item__votes--neutral';
            if (point.votes_count > 0) {
                votesClass = 'jg-sidebar-item__votes--positive';
            } else if (point.votes_count < 0) {
                votesClass = 'jg-sidebar-item__votes--negative';
            }

            votesHtml = `<div class="jg-sidebar-item__votes ${votesClass}">
                ${point.votes_count > 0 ? '+' : ''}${point.votes_count} głosów
            </div>`;
        }

        // Build today's opening hours line for sidebar (miejsce only)
        var todayHoursHtml = '';
        if (point.opening_hours && (point.type === 'miejsce' || point.type === 'ciekawostka')) {
            var sbDayKeys = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
            var sbTodayKey = sbDayKeys[(new Date().getDay() + 6) % 7];
            var sbParsed = {};
            point.opening_hours.trim().split('\n').forEach(function(line) {
                var m = line.trim().match(/^(Mo|Tu|We|Th|Fr|Sa|Su)\s+(\d{2}:\d{2})-(\d{2}:\d{2})$/);
                if (m) sbParsed[m[1]] = { open: m[2], close: m[3] };
            });
            var sbToday = sbParsed[sbTodayKey] || null;
            if (Object.keys(sbParsed).length > 0) {
                if (sbToday) {
                    var sbNow = new Date();
                    var sbNowMins = sbNow.getHours() * 60 + sbNow.getMinutes();
                    var sbOpenMins = parseInt(sbToday.open.split(':')[0]) * 60 + parseInt(sbToday.open.split(':')[1]);
                    var sbCloseMins = parseInt(sbToday.close.split(':')[0]) * 60 + parseInt(sbToday.close.split(':')[1]);
                    var sbIsOpen = sbNowMins >= sbOpenMins && sbNowMins < sbCloseMins;
                    if (!sbIsOpen) {
                        var sbDayLabels = { Mo: 'Pon', Tu: 'Wt', We: 'Śr', Th: 'Czw', Fr: 'Pt', Sa: 'Sob', Su: 'Niedz' };
                        var sbNextOpen = '';
                        var sbTodayIdx = sbDayKeys.indexOf(sbTodayKey);
                        if (sbNowMins < sbOpenMins) {
                            sbNextOpen = 'Otwiera o ' + sbToday.open;
                        } else {
                            for (var sbDi = 1; sbDi <= 7; sbDi++) {
                                var sbNextKey = sbDayKeys[(sbTodayIdx + sbDi) % 7];
                                if (sbParsed[sbNextKey]) {
                                    var sbNextLabel = sbDi === 1 ? 'Jutro' : (sbDayLabels[sbNextKey] || sbNextKey);
                                    sbNextOpen = sbNextLabel + ' o ' + sbParsed[sbNextKey].open;
                                    break;
                                }
                            }
                        }
                        todayHoursHtml = `<div class="jg-sidebar-item__hours jg-sidebar-item__hours--closed">🕐 Zamknięte${sbNextOpen ? ' · ' + escapeHtml(sbNextOpen) : ''}</div>`;
                    } else {
                        var sbMinsLeft = sbCloseMins - sbNowMins;
                        var sbWarning = (sbMinsLeft > 0 && sbMinsLeft < 60)
                            ? `<br><span class="jg-sidebar-item__hours-warning">⚠️ Zamknięcie za ${sbMinsLeft} min</span>`
                            : '';
                        todayHoursHtml = `<div class="jg-sidebar-item__hours">🕐 ${escapeHtml(sbToday.open)} – ${escapeHtml(sbToday.close)}${sbWarning}</div>`;
                    }
                } else {
                    todayHoursHtml = `<div class="jg-sidebar-item__hours jg-sidebar-item__hours--closed">🕐 Nieczynne</div>`;
                }
            }
        }

        // Build item HTML
        var infoBadgesHtml = buildInfoBadges(point);
        $item.html(`
            ${imageHtml}
            <div class="jg-sidebar-item__content">
                <div class="jg-sidebar-item__header">
                    ${badgeHtml}
                    <h4 class="jg-sidebar-item__title">${escapeHtml(point.title)}</h4>
                    ${todayHoursHtml}
                </div>
                <div class="jg-sidebar-item__footer">
                    ${votesHtml}
                    <div class="jg-sidebar-item__date" data-jg-timestamp="${escapeHtml(point.date.raw)}">${escapeHtml(point.date.human)}</div>
                </div>
                ${infoBadgesHtml}
            </div>
        `);

        // Click handler - zoom map to pin using existing mechanism
        $item.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            handlePointClick(point);
        });

        return $item;
    }

    /**
     * Build small info-badge strip for a point.
     * Shows icon-only badges; tooltip text is set via data-jg-tip attribute
     * and revealed via CSS :hover – fully independent of Elementor styles.
     */
    function buildInfoBadges(point) {
        var badges = [];

        if (point.has_description) {
            badges.push({ icon: '📝', tip: 'Ma opis' });
        }
        if (point.has_incomplete_sections) {
            badges.push({ icon: '⚠️', tip: 'Zawiera sekcje do uzupełnienia' });
        }
        if (point.has_tags) {
            badges.push({ icon: '🏷️', tip: 'Ma tagi' });
        }
        if (point.category) {
            var catLabel = resolveCategoryLabel(point.type, point.category);
            badges.push({ icon: '🗂️', tip: 'Kategoria: ' + catLabel });
        }
        if (point.images_count > 0) {
            var n = point.images_count;
            var photoLabel = n === 1 ? '1 zdjęcie' : (n < 5 ? n + ' zdjęcia' : n + ' zdjęć');
            badges.push({ icon: '📷', tip: photoLabel });
        }

        // Admin/moderator-only badges
        var adminBadges = [];
        if (window.JG_MAP_CFG && JG_MAP_CFG.isAdmin) {
            if (point.has_internal_links) {
                adminBadges.push({ icon: '🔗', tip: 'Linki do pinesek' });
            }
            if (point.has_external_links) {
                adminBadges.push({ icon: '🌐', tip: 'Linki zewnętrzne' });
            }
        }

        if (badges.length === 0 && adminBadges.length === 0) {
            return '';
        }

        var html = '<div class="jg-sidebar-item__info-badges">';
        for (var i = 0; i < badges.length; i++) {
            html += '<span class="jg-info-badge" data-jg-tip="' + escapeHtml(badges[i].tip) + '">' + badges[i].icon + '</span>';
        }
        for (var j = 0; j < adminBadges.length; j++) {
            var cls = adminBadges[j].cls || 'jg-info-badge--admin';
            html += '<span class="jg-info-badge ' + cls + '" data-jg-tip="' + escapeHtml(adminBadges[j].tip) + '">' + adminBadges[j].icon + '</span>';
        }
        html += '</div>';
        return html;
    }

    /**
     * Resolve human-readable category label from JG_MAP_CFG config.
     */
    function resolveCategoryLabel(type, categoryKey) {
        var cfg = window.JG_MAP_CFG || {};
        var map = type === 'ciekawostka' ? (cfg.curiosityCategories || {}) : (cfg.placeCategories || {});
        if (map[categoryKey] && map[categoryKey].label) {
            return map[categoryKey].label;
        }
        return categoryKey;
    }

    /**
     * Handle click on point item - zoom the map to the pin location, then open modal.
     * Uses window.jgZoomToPoint (exported from jg-map.js) which replicates
     * the existing search result zoom mechanic (setView + pulsing circle).
     */
    function handlePointClick(point) {
        // First check if point still exists (protection against clicking deleted points before sync)
        $.ajax({
            url: JG_MAP_CFG.ajax,
            type: 'POST',
            data: {
                action: 'jg_check_point_exists',
                _ajax_nonce: JG_MAP_CFG.nonce,
                point_id: point.id
            },
            success: function(response) {
                if (!response.success || !response.data || !response.data.exists) {
                    // Point has been deleted - show alert and refresh sidebar
                    showDeletedPointAlert();
                    setTimeout(function() {
                        loadPoints();
                    }, 1500);
                    return;
                }

                // Point exists - zoom map to its location then open modal
                zoomToPin(point.lat, point.lng, function() {
                    if (typeof window.jgOpenPointById === 'function') {
                        window.jgOpenPointById(point.id);
                    } else if (typeof window.openDetails === 'function') {
                        window.openDetails(point);
                    }
                });
            },
            error: function(xhr, status, error) {
                // On network error, still try to zoom (fallback)
                zoomToPin(point.lat, point.lng, function() {
                    if (typeof window.jgOpenPointById === 'function') {
                        window.jgOpenPointById(point.id);
                    } else if (typeof window.openDetails === 'function') {
                        window.openDetails(point);
                    }
                });
            }
        });
    }

    /**
     * Zoom the map to a pin location using the exported map mechanism.
     * Optional callback is fired after the animation completes.
     */
    function zoomToPin(lat, lng, callback) {
        if (typeof window.jgZoomToPoint === 'function') {
            window.jgZoomToPoint(lat, lng, callback);
        } else if (window.jgMap) {
            // Fallback: use Leaflet map directly if export not yet ready
            window.jgMap.setView([lat, lng], 19, { animate: true });
            if (typeof callback === 'function') setTimeout(callback, 800);
        }
    }

    /**
     * Show alert when clicked point was deleted
     */
    function showDeletedPointAlert() {
        // Try to use map's showAlert function if available
        if (typeof window.showAlert === 'function') {
            window.showAlert('Miejsce usunięte, niebawem zniknie z tej listy.');
        } else {
            // Fallback to browser alert
            alert('Miejsce usunięte, niebawem zniknie z tej listy.');
        }
    }

    /**
     * Show error message
     */
    function showError(message) {
        const $list = $('#jg-sidebar-list');
        $list.html(`<div class="jg-sidebar-error">${escapeHtml(message)}</div>`);
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    /**
     * Polish plural helper: n=1 → one, 2-4 (not 12-14) → few, else → many
     */
    function pluralPl(n, one, few, many) {
        if (n === 1) return one;
        var mod10 = n % 10;
        var mod100 = n % 100;
        if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return few;
        return many;
    }

    /**
     * Convert a UTC datetime string (from date.raw) to a Polish relative time string.
     * Mirrors WordPress human_time_diff() output format.
     */
    function humanTimeDiffPl(dateStr) {
        if (!dateStr) return '';
        // date.raw is "YYYY-MM-DD HH:MM:SS" in UTC
        var date = new Date(dateStr.replace(' ', 'T') + 'Z');
        if (isNaN(date.getTime())) return '';
        var diff = Math.floor((Date.now() - date.getTime()) / 1000);
        if (diff < 0) diff = 0;

        if (diff < 60) {
            return diff + ' ' + pluralPl(diff, 'sekunda', 'sekundy', 'sekund') + ' temu';
        }
        if (diff < 3600) {
            var mins = Math.floor(diff / 60);
            return mins + ' ' + pluralPl(mins, 'minuta', 'minuty', 'minut') + ' temu';
        }
        if (diff < 86400) {
            var hours = Math.floor(diff / 3600);
            return hours + ' ' + pluralPl(hours, 'godzina', 'godziny', 'godzin') + ' temu';
        }
        if (diff < 2592000) {
            var days = Math.floor(diff / 86400);
            return days + ' ' + pluralPl(days, 'dzień', 'dni', 'dni') + ' temu';
        }
        if (diff < 31536000) {
            var months = Math.floor(diff / 2592000);
            return months + ' ' + pluralPl(months, 'miesiąc', 'miesiące', 'miesięcy') + ' temu';
        }
        var years = Math.floor(diff / 31536000);
        return years + ' ' + pluralPl(years, 'rok', 'lata', 'lat') + ' temu';
    }

    /**
     * Update all visible timestamp labels in the sidebar.
     * Called once after render and then every 60 seconds.
     */
    function refreshTimestamps() {
        $('#jg-sidebar-list [data-jg-timestamp]').each(function() {
            var raw = $(this).attr('data-jg-timestamp');
            var label = humanTimeDiffPl(raw);
            if (label) {
                $(this).text(label);
            }
        });
    }

    /**
     * Setup interval to keep relative timestamps current.
     */
    function setupTimestampRefresh() {
        // Immediate pass so cached items show correct time right away
        refreshTimestamps();
        setInterval(refreshTimestamps, 60000);
    }

    /**
     * Setup synchronization with WordPress Heartbeat API
     * This integrates with the main synchronization manager
     * to automatically refresh sidebar when changes are detected
     */
    function setupSync() {
        // Check if WordPress Heartbeat API is available
        if (typeof wp === 'undefined' || !wp.heartbeat) {
            return;
        }

        let lastSyncCheck = Math.floor(Date.now() / 1000);
        let refreshPending = false;
        let refreshTimeout = null;

        // Listen to heartbeat responses
        $(document).on('heartbeat-tick.jgSidebarSync', function(e, data) {
            // Check if we have sync data from the sync manager
            if (!data.jg_map_sync) {
                return;
            }

            const syncData = data.jg_map_sync;

            // Update last check timestamp
            lastSyncCheck = syncData.server_time || Math.floor(Date.now() / 1000);

            // Check if there are changes that affect sidebar
            const hasChanges = syncData.new_points > 0 ||
                              (syncData.sync_events && syncData.sync_events.length > 0);

            if (hasChanges) {
                scheduleRefresh();
            }
        });

        /**
         * Schedule a refresh with debouncing to prevent excessive updates
         * If multiple changes come in rapid succession, we only refresh once
         * Uses silent mode to avoid flickering - only re-renders if data changed
         */
        function scheduleRefresh() {
            // If refresh is already pending, just extend the timeout
            if (refreshPending) {
                clearTimeout(refreshTimeout);
            }

            refreshPending = true;

            // Wait 500ms before refreshing to batch multiple rapid changes
            // Use silent=true to avoid flickering - only re-renders if data changed
            refreshTimeout = setTimeout(function() {
                loadPoints(true);
                refreshPending = false;
            }, 500);
        }
    }

    // Initialize on DOM ready
    $(document).ready(function() {
        init();
        setupSync();
        setupTimestampRefresh();
    });

    // Expose refresh function for external use
    window.jgSidebarRefresh = loadPoints;

})(jQuery);
