/**
 * JG Map Sidebar - List of pins with filtering and sorting
 */
(function($) {
    'use strict';

    // Check if sidebar exists on page
    if (!document.getElementById('jg-map-sidebar')) {
        return;
    }

    let sidebarPoints = [];
    let currentFilters = {
        types: ['miejsce', 'ciekawostka', 'zgloszenie'],
        myPlaces: false,
        sortBy: 'date_desc'
    };

    /**
     * Initialize sidebar
     */
    function init() {
        // Setup event listeners
        setupEventListeners();

        // Load initial data
        loadPoints();
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
     * Load points from server
     */
    function loadPoints() {
        // Show loading
        $('#jg-sidebar-loading').show();
        $('#jg-sidebar-list').hide();

        $.ajax({
            url: JG_MAP_CFG.ajax,
            type: 'POST',
            data: {
                action: 'jg_get_sidebar_points',
                _ajax_nonce: JG_MAP_CFG.nonce,
                type_filters: currentFilters.types,
                my_places: currentFilters.myPlaces,
                sort_by: currentFilters.sortBy
            },
            success: function(response) {
                if (response.success && response.data) {
                    sidebarPoints = response.data.points;
                    updateStats(response.data.stats);
                    renderPoints(sidebarPoints);
                } else {
                    showError('Nie udało się załadować listy pinezek');
                }

                $('#jg-sidebar-loading').hide();
                $('#jg-sidebar-list').show();
            },
            error: function(xhr, status, error) {
                showError('Błąd połączenia z serwerem');
                $('#jg-sidebar-loading').hide();
                $('#jg-sidebar-list').show();
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
     * Render points list
     */
    function renderPoints(points) {
        const $list = $('#jg-sidebar-list');
        $list.empty();

        if (!points || points.length === 0) {
            $list.html('<div class="jg-sidebar-empty">Brak pinezek spełniających kryteria</div>');
            return;
        }

        // Separate sponsored and regular pins
        const sponsoredPoints = points.filter(p => p.is_promo);
        const regularPoints = points.filter(p => !p.is_promo);

        // Add ONE random sponsored pin in "Polecamy" section
        if (sponsoredPoints.length > 0) {
            // Pick one random sponsored place
            const randomIndex = Math.floor(Math.random() * sponsoredPoints.length);
            const randomSponsored = sponsoredPoints[randomIndex];

            $list.append('<div class="jg-sidebar-section-title">Polecamy:</div>');
            const $item = createPointItem(randomSponsored);
            $list.append($item);
        }

        // Add regular pins section if there are regular pins
        if (regularPoints.length > 0) {
            $list.append('<div class="jg-sidebar-section-title">Pinezki na mapie</div>');
            regularPoints.forEach(function(point) {
                const $item = createPointItem(point);
                $list.append($item);
            });
        }
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

        // Image (if available) or star icon for sponsored without image
        let imageHtml = '';
        if (point.featured_image) {
            imageHtml = `<div class="jg-sidebar-item__image">
                <img src="${point.featured_image}" alt="${point.title}" />
            </div>`;
        } else if (point.is_promo) {
            // Show gold star for sponsored places without image
            imageHtml = `<div class="jg-sidebar-item__image jg-sidebar-item__image--star">
                <span class="jg-sidebar-star-icon">⭐</span>
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

        // Build item HTML
        $item.html(`
            ${imageHtml}
            <div class="jg-sidebar-item__content">
                <div class="jg-sidebar-item__header">
                    ${badgeHtml}
                    <h4 class="jg-sidebar-item__title">${escapeHtml(point.title)}</h4>
                </div>
                <div class="jg-sidebar-item__footer">
                    ${votesHtml}
                    <div class="jg-sidebar-item__date">${formatDate(point.created_at)}</div>
                </div>
            </div>
        `);

        // Click handler - trigger map zoom and modal
        $item.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            handlePointClick(point);
        });

        return $item;
    }

    /**
     * Handle click on point item - navigate to point URL
     * This uses the existing SEO routing which will automatically:
     * - Zoom the map to the point
     * - Open the details modal
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
                    // Refresh sidebar to remove deleted point
                    setTimeout(function() {
                        loadPoints();
                    }, 1500);
                    return;
                }

                // Point exists - navigate to it
                const url = `/${point.type}/${point.slug}`;
                window.location.href = url;
            },
            error: function(xhr, status, error) {
                // On error, still try to navigate (fallback)
                const url = `/${point.type}/${point.slug}`;
                window.location.href = url;
            }
        });
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
     * Format date for display
     */
    function formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffTime = Math.abs(now - date);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays === 0) {
            return 'Dzisiaj';
        } else if (diffDays === 1) {
            return 'Wczoraj';
        } else if (diffDays < 7) {
            return diffDays + ' dni temu';
        } else if (diffDays < 30) {
            const weeks = Math.floor(diffDays / 7);
            return weeks === 1 ? '1 tydzień temu' : weeks + ' tygodnie temu';
        } else if (diffDays < 365) {
            const months = Math.floor(diffDays / 30);
            return months === 1 ? '1 miesiąc temu' : months + ' miesięcy temu';
        } else {
            const years = Math.floor(diffDays / 365);
            return years === 1 ? '1 rok temu' : years + ' lat temu';
        }
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
         */
        function scheduleRefresh() {
            // If refresh is already pending, just extend the timeout
            if (refreshPending) {
                clearTimeout(refreshTimeout);
            }

            refreshPending = true;

            // Wait 500ms before refreshing to batch multiple rapid changes
            refreshTimeout = setTimeout(function() {
                loadPoints();
                refreshPending = false;
            }, 500);
        }
    }

    // Initialize on DOM ready
    $(document).ready(function() {
        init();
        setupSync();
    });

    // Expose refresh function for external use
    window.jgSidebarRefresh = loadPoints;

})(jQuery);
