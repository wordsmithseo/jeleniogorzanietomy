/**
 * JG Map Session Monitor
 * Monitors user session and logs out users when:
 * 1. Maintenance mode is enabled (for regular users)
 * 2. Test user permissions are revoked during maintenance
 */
(function($) {
  'use strict';

  // Only run if user is logged in
  if (!window.JG_SESSION_CFG || !JG_SESSION_CFG.isLoggedIn) {
    return;
  }

  var checkInterval = 10000; // Check every 10 seconds
  var modalShown = false;
  var previousUserData = null; // Track previous user state for change detection

  /**
   * Show modal before logging out
   */
  function showLogoutModal(message, requiresConfirmation, callback) {
    if (modalShown) return;
    modalShown = true;

    // Create modal
    var modal = document.createElement('div');
    modal.id = 'jg-session-logout-modal';
    modal.style.cssText = 'display:flex;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:999999;align-items:center;justify-content:center;';

    var modalContent = document.createElement('div');
    modalContent.style.cssText = 'background:#fff;padding:30px;border-radius:12px;max-width:500px;width:90%;box-shadow:0 10px 40px rgba(0,0,0,0.3);text-align:center;';

    // Different content based on whether confirmation is required
    if (requiresConfirmation) {
      // Show modal with OK button for deleted account
      modalContent.innerHTML =
        '<div style="font-size:64px;margin-bottom:20px">ℹ️</div>' +
        '<h2 style="margin:0 0 15px;font-size:24px;color:#333">Powiadomienie systemowe</h2>' +
        '<p style="font-size:16px;line-height:1.6;color:#666;margin:0 0 25px">' + escapeHtml(message) + '</p>' +
        '<button id="jg-logout-confirm-btn" style="padding:12px 32px;background:#8d2324;color:#fff;border:none;border-radius:6px;font-size:16px;font-weight:600;cursor:pointer;transition:background 0.2s">OK</button>';

      modal.appendChild(modalContent);
      document.body.appendChild(modal);

      // Handle OK button click
      document.getElementById('jg-logout-confirm-btn').addEventListener('click', function() {
        if (callback) callback();
      });

      // Handle Enter key
      document.addEventListener('keypress', function onEnter(e) {
        if (e.key === 'Enter') {
          document.removeEventListener('keypress', onEnter);
          if (callback) callback();
        }
      });
    } else {
      // Auto logout after 3 seconds for maintenance mode
      modalContent.innerHTML =
        '<div style="font-size:64px;margin-bottom:20px">⚠️</div>' +
        '<h2 style="margin:0 0 15px;font-size:24px;color:#333">Powiadomienie systemowe</h2>' +
        '<p style="font-size:16px;line-height:1.6;color:#666;margin:0 0 25px">' + escapeHtml(message) + '</p>' +
        '<p style="font-size:14px;color:#999;margin:0">Za chwilę zostaniesz automatycznie wylogowany...</p>';

      modal.appendChild(modalContent);
      document.body.appendChild(modal);

      // Auto logout after 3 seconds
      setTimeout(function() {
        if (callback) callback();
      }, 3000);
    }
  }

  /**
   * Show info modal without logout (for role/premium changes)
   */
  function showInfoModal(message, callback) {
    // Create modal
    var modal = document.createElement('div');
    modal.id = 'jg-session-info-modal';
    modal.style.cssText = 'display:flex;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:999999;align-items:center;justify-content:center;';

    var modalContent = document.createElement('div');
    modalContent.style.cssText = 'background:#fff;padding:30px;border-radius:12px;max-width:500px;width:90%;box-shadow:0 10px 40px rgba(0,0,0,0.3);text-align:center;';

    modalContent.innerHTML =
      '<div style="font-size:64px;margin-bottom:20px">ℹ️</div>' +
      '<h2 style="margin:0 0 15px;font-size:24px;color:#333">Powiadomienie systemowe</h2>' +
      '<p style="font-size:16px;line-height:1.6;color:#666;margin:0 0 25px">' + escapeHtml(message) + '</p>' +
      '<button id="jg-info-confirm-btn" style="padding:12px 32px;background:#8d2324;color:#fff;border:none;border-radius:6px;font-size:16px;font-weight:600;cursor:pointer;transition:background 0.2s">OK</button>';

    modal.appendChild(modalContent);
    document.body.appendChild(modal);

    // Handle OK button click
    document.getElementById('jg-info-confirm-btn').addEventListener('click', function() {
      modal.remove();
      if (callback) callback();
    });

    // Handle Enter key
    document.addEventListener('keypress', function onEnter(e) {
      if (e.key === 'Enter') {
        document.removeEventListener('keypress', onEnter);
        modal.remove();
        if (callback) callback();
      }
    });
  }

  /**
   * Escape HTML to prevent XSS
   */
  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  /**
   * Logout user via AJAX and reload page
   */
  function logoutUser() {
    $.ajax({
      url: JG_SESSION_CFG.ajax,
      type: 'POST',
      data: {
        action: 'jg_logout_user'
      },
      complete: function() {
        // Reload page to show logged out state / maintenance page
        window.location.reload();
      }
    });
  }

  /**
   * Check user session status
   */
  function checkSessionStatus() {
    $.ajax({
      url: JG_SESSION_CFG.ajax,
      type: 'POST',
      data: {
        action: 'jg_check_user_session_status'
      },
      success: function(response) {
        if (response.success && response.data) {
          var data = response.data;

          // User should be logged out
          if (data.should_logout) {
            var message = data.message || 'Sesja wygasła. Zapraszamy później.';
            var requiresConfirmation = data.requires_confirmation || false;

            // Show modal and then logout
            showLogoutModal(message, requiresConfirmation, function() {
              logoutUser();
            });
            return;
          }

          // Check for role/permission changes
          if (data.user_data && previousUserData) {
            var current = data.user_data;
            var previous = previousUserData;

            // Check for moderator role changes
            if (!previous.is_moderator && current.is_moderator) {
              showInfoModal('Otrzymałeś uprawnienia moderatora! Odśwież stronę aby zobaczyć nowe funkcje.', function() {
                window.location.reload();
              });
              return;
            }

            if (previous.is_moderator && !current.is_moderator) {
              showInfoModal('Twoje uprawnienia moderatora zostały cofnięte. Strona zostanie odświeżona.', function() {
                window.location.reload();
              });
              return;
            }

            // Check for admin role changes
            if (!previous.is_admin && current.is_admin) {
              showInfoModal('Otrzymałeś uprawnienia administratora! Odśwież stronę aby zobaczyć nowe funkcje.', function() {
                window.location.reload();
              });
              return;
            }

            if (previous.is_admin && !current.is_admin) {
              showInfoModal('Twoje uprawnienia administratora zostały cofnięte. Strona zostanie odświeżona.', function() {
                window.location.reload();
              });
              return;
            }

            // Check for premium status changes (sponsored places)
            if (previous.sponsored_count === 0 && current.sponsored_count > 0) {
              showInfoModal('Gratulacje! Otrzymałeś status użytkownika premium. Jedno lub więcej Twoich miejsc jest teraz sponsorowane.', function() {
                window.location.reload();
              });
              return;
            }

            if (previous.sponsored_count > 0 && current.sponsored_count === 0) {
              showInfoModal('Twój status użytkownika premium wygasł. Żadne z Twoich miejsc nie jest już sponsorowane.', function() {
                window.location.reload();
              });
              return;
            }
          }

          // Store current user data for next check
          if (data.user_data) {
            previousUserData = data.user_data;
          }
        }
      },
      error: function() {
        // Silent fail - don't interrupt user experience
        console.warn('[JG Session Monitor] Failed to check session status');
      }
    });
  }

  /**
   * Start monitoring
   */
  function startMonitoring() {
    // Initial check after 5 seconds
    setTimeout(checkSessionStatus, 5000);

    // Then check every 10 seconds
    setInterval(checkSessionStatus, checkInterval);

    console.log('[JG Session Monitor] Started monitoring user session');
  }

  // Start when DOM is ready
  $(document).ready(function() {
    startMonitoring();
  });

})(jQuery);
