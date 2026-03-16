/**
 * JG Map - Real-time Notifications System
 * This file handles the notification counter updates in the custom top bar
 */

(function($) {
    'use strict';

    // Global function to refresh notifications immediately
    window.jgRefreshNotifications = function() {
        return $.ajax({
            url: jgNotificationsConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'jg_get_notification_counts',
                _ajax_nonce: jgNotificationsConfig.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateNotifications(response.data);
                }
            }
        });
    };

    // SVG icons for mobile notification buttons (keyed by data-type)
    var mupIcons = {
        points:    '<path d="M12 5v14M5 12h14"/>',
        edits:     '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
        reports:   '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        deletions: '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>'
    };

    var mupUrls = {
        points:    jgNotificationsConfig.pointsUrl    || jgNotificationsConfig.moderationUrl,
        edits:     jgNotificationsConfig.editsUrl     || jgNotificationsConfig.moderationUrl,
        reports:   jgNotificationsConfig.reportsUrl,
        deletions: jgNotificationsConfig.deletionsUrl
    };

    var mupTitles = {
        points:    'Nowe miejsca',
        edits:     'Edycje',
        reports:   'Zgłoszenia',
        deletions: 'Usunięcia'
    };

    function updateNotifications(counts) {
        var container = $('#jg-top-bar-notifications');
        if (!container.length) {
            return;
        }

        var notifications = [];

        if (counts.points > 0) {
            notifications.push({
                icon: '➕',
                label: 'Nowe miejsca',
                type: 'nowe miejsca',
                count: counts.points,
                url: jgNotificationsConfig.moderationUrl
            });
        }

        if (counts.edits > 0) {
            notifications.push({
                icon: '📝',
                label: 'Edycje',
                type: 'edycje',
                count: counts.edits,
                url: jgNotificationsConfig.moderationUrl
            });
        }

        if (counts.reports > 0) {
            notifications.push({
                icon: '🚨',
                label: 'Zgłoszenia',
                type: 'zgłoszenia',
                count: counts.reports,
                url: jgNotificationsConfig.reportsUrl
            });
        }

        if (counts.deletions > 0) {
            notifications.push({
                icon: '🗑️',
                label: 'Usunięcia',
                type: 'usunięcia',
                count: counts.deletions,
                url: jgNotificationsConfig.deletionsUrl
            });
        }

        // Rebuild desktop top bar notifications HTML
        var html = '';
        notifications.forEach(function(notif) {
            html += '<a href="' + notif.url + '" class="jg-top-bar-btn jg-top-bar-notif" data-type="' + notif.type + '">';
            html += '<span>' + notif.icon + ' ' + notif.label + '</span>';
            html += '<span class="jg-notif-badge">' + notif.count + '</span>';
            html += '</a>';
        });

        container.html(html);

        // Add/remove empty class for proper spacing
        if (notifications.length === 0) {
            container.addClass('jg-notifications-empty');
        } else {
            container.removeClass('jg-notifications-empty');
        }

        // ── Update mobile user panel notifications row ──────────────────────────
        var mupContainer = $('#jg-mup-notifications');
        if (!mupContainer.length) return;

        var countMap = {
            points:    counts.points    || 0,
            edits:     counts.edits     || 0,
            reports:   counts.reports   || 0,
            deletions: counts.deletions || 0
        };

        var mupHtml = '';
        $.each(countMap, function(type, count) {
            if (count > 0) {
                var url   = mupUrls[type]   || '#';
                var title = mupTitles[type] || type;
                var icon  = mupIcons[type]  || '';
                mupHtml += '<a href="' + url + '" class="jg-mup-notif-btn" data-type="' + type + '" title="' + title + ' (' + count + ')">';
                mupHtml += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + icon + '</svg>';
                mupHtml += '<span class="jg-mup-notif-badge">' + count + '</span>';
                mupHtml += '</a>';
            }
        });

        mupContainer.html(mupHtml);
        if (mupHtml === '') {
            mupContainer.addClass('jg-mup-notifications--empty');
        } else {
            mupContainer.removeClass('jg-mup-notifications--empty');
        }
    }

    // Heartbeat for periodic updates
    if (typeof wp !== 'undefined' && wp.heartbeat) {
        wp.heartbeat.interval(15);

        // Send request for notification updates
        $(document).on('heartbeat-send', function(e, data) {
            data.jg_map_check_notifications = true;
        });

        // Process heartbeat response
        $(document).on('heartbeat-tick', function(e, data) {
            if (!data.jg_map_notifications) return;
            updateNotifications(data.jg_map_notifications);
        });
    }

    // Initial load - refresh after 1 second
    setTimeout(function() {
        window.jgRefreshNotifications();
    }, 1000);

    // Refresh every 10 seconds as backup
    setInterval(function() {
        window.jgRefreshNotifications();
    }, 10000);

})(jQuery);
