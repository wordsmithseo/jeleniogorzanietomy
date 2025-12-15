/**
 * JG Map - Real-time Notifications System
 * This file handles the notification counter updates in the custom top bar
 */

(function($) {
    'use strict';

    console.log('[JG MAP TOPBAR] Real-time notifications initialized');

    // Global function to refresh notifications immediately
    window.jgRefreshNotifications = function() {
        console.log('[JG MAP TOPBAR] Manual refresh requested');
        return $.ajax({
            url: jgNotificationsConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'jg_get_notification_counts',
                _ajax_nonce: jgNotificationsConfig.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('[JG MAP TOPBAR] Notification data received:', response.data);
                    updateNotifications(response.data);
                } else {
                    console.error('[JG MAP TOPBAR] Invalid response:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('[JG MAP TOPBAR] Failed to refresh notifications:', error);
            }
        });
    };

    function updateNotifications(counts) {
        console.log('[JG MAP TOPBAR] Updating notifications:', counts);

        var container = $('#jg-top-bar-notifications');
        if (!container.length) {
            console.warn('[JG MAP TOPBAR] Container not found');
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

        // Rebuild notifications HTML
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
