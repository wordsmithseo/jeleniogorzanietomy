/**
 * JG Interactive Map - Frontend JavaScript
 * Version: 3.0.0
 */

(function($) {
  'use strict';

  // Debug logging disabled for production
  function debugLog() {}
  function debugWarn() {}
  function debugError() {}

  // Helper function to generate category select options from config
  function generateCategoryOptions(selectedValue) {
    var categories = (window.JG_MAP_CFG && JG_MAP_CFG.reportCategories) || {};
    var reasons = (window.JG_MAP_CFG && JG_MAP_CFG.reportReasons) || {};
    var html = '<option value="">-- Wybierz kategorię --</option>';

    // Group reasons by category
    var grouped = {};
    for (var key in reasons) {
      if (reasons.hasOwnProperty(key)) {
        var reason = reasons[key];
        var group = reason.group || 'other';
        if (!grouped[group]) {
          grouped[group] = [];
        }
        grouped[group].push({
          key: key,
          label: reason.label,
          icon: reason.icon || '📌'
        });
      }
    }

    // Generate optgroups
    for (var catKey in categories) {
      if (categories.hasOwnProperty(catKey) && grouped[catKey]) {
        html += '<optgroup label="' + categories[catKey] + '">';
        for (var i = 0; i < grouped[catKey].length; i++) {
          var r = grouped[catKey][i];
          var selected = (selectedValue === r.key) ? ' selected' : '';
          html += '<option value="' + r.key + '"' + selected + '>' + r.icon + ' ' + r.label + '</option>';
        }
        html += '</optgroup>';
      }
    }

    // Add uncategorized reasons (if any)
    if (grouped[''] || grouped['other']) {
      var uncategorized = grouped[''] || grouped['other'] || [];
      if (uncategorized.length > 0) {
        html += '<optgroup label="Inne">';
        for (var j = 0; j < uncategorized.length; j++) {
          var u = uncategorized[j];
          var sel = (selectedValue === u.key) ? ' selected' : '';
          html += '<option value="' + u.key + '"' + sel + '>' + u.icon + ' ' + u.label + '</option>';
        }
        html += '</optgroup>';
      }
    }

    return html;
  }

  // Helper function to get category emoji map from config
  function getCategoryEmojis() {
    var reasons = (window.JG_MAP_CFG && JG_MAP_CFG.reportReasons) || {};
    var emojis = {};
    for (var key in reasons) {
      if (reasons.hasOwnProperty(key)) {
        emojis[key] = reasons[key].icon || '📌';
      }
    }
    return emojis;
  }

  // Helper function to get category label by key
  function getCategoryLabel(key) {
    var reasons = (window.JG_MAP_CFG && JG_MAP_CFG.reportReasons) || {};
    if (reasons[key]) {
      return (reasons[key].icon || '📌') + ' ' + reasons[key].label;
    }
    return key;
  }

  // Unregister Service Worker to fix caching issues
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
      navigator.serviceWorker.getRegistrations().then(function(registrations) {
        for (var registration of registrations) {
          registration.unregister().then(function() {
          });
        }
      });

      // Clear all caches
      if ('caches' in window) {
        caches.keys().then(function(names) {
          for (var name of names) {
            caches.delete(name);
          }
        });
      }

      // Clear localStorage cache (old versions without user_id check)
      try {
        localStorage.removeItem('jg_map_cache');
        localStorage.removeItem('jg_map_cache_version');
        localStorage.removeItem('jg_map_cache_v2');
        localStorage.removeItem('jg_map_cache_version_v2');
        // v5 had bug - didn't check user_id, causing admin data to show to guests
        localStorage.removeItem('jg_map_cache_v5');
        localStorage.removeItem('jg_map_cache_version_v5');
      } catch (e) {
        debugError('[JG MAP] Failed to clear localStorage:', e);
      }
    });
  }

  var loadingEl = document.getElementById('jg-map-loading');
  var errorEl = document.getElementById('jg-map-error');
  var errorMsg = document.getElementById('error-msg');
  var loadStartTime = Date.now(); // Track when loading started
  var minLoadingTime = 500; // Minimum time to show loader (ms)


  // ====================================
  // MESSAGE MODALS (Alert/Confirm replacements)
  // ====================================
  function showAlert(message) {
    return new Promise(function(resolve) {
      var modal = document.getElementById('jg-modal-alert');
      if (!modal) {
        // Fallback to native alert if modal not found
        alert(message);
        resolve();
        return;
      }

      var contentEl = modal.querySelector('.jg-modal-message-content');
      var buttonsEl = modal.querySelector('.jg-modal-message-buttons');

      contentEl.innerHTML = message;
      buttonsEl.innerHTML = '<button class="jg-btn" id="jg-alert-ok">OK</button>';

      modal.style.display = 'flex';

      var okBtn = document.getElementById('jg-alert-ok');
      okBtn.onclick = function() {
        modal.style.display = 'none';
        resolve();
      };

      // Close on background click
      modal.onclick = function(e) {
        if (e.target === modal) {
          modal.style.display = 'none';
          resolve();
        }
      };
    });
  }

  function showConfirm(message) {
    return new Promise(function(resolve) {
      var modal = document.getElementById('jg-modal-alert');
      if (!modal) {
        // Fallback to native confirm if modal not found
        resolve(confirm(message));
        return;
      }

      var contentEl = modal.querySelector('.jg-modal-message-content');
      var buttonsEl = modal.querySelector('.jg-modal-message-buttons');

      contentEl.innerHTML = message;
      buttonsEl.innerHTML = '<button class="jg-btn jg-btn--ghost" id="jg-confirm-no">Anuluj</button><button class="jg-btn" id="jg-confirm-yes">OK</button>';

      modal.style.display = 'flex';

      var yesBtn = document.getElementById('jg-confirm-yes');
      var noBtn = document.getElementById('jg-confirm-no');

      yesBtn.onclick = function() {
        modal.style.display = 'none';
        resolve(true);
      };

      noBtn.onclick = function() {
        modal.style.display = 'none';
        resolve(false);
      };

      // Close on background click = cancel
      modal.onclick = function(e) {
        if (e.target === modal) {
          modal.style.display = 'none';
          resolve(false);
        }
      };
    });
  }

  function showRejectReasonModal(message) {
    return new Promise(function(resolve) {
      var modal = document.getElementById('jg-modal-alert');
      if (!modal) {
        // Fallback to native prompt if modal not found
        resolve(prompt(message));
        return;
      }

      var contentEl = modal.querySelector('.jg-modal-message-content');
      var buttonsEl = modal.querySelector('.jg-modal-message-buttons');

      contentEl.innerHTML = '<div style="margin-bottom:12px;font-weight:600">' + message + '</div>' +
        '<textarea id="jg-reject-reason-textarea" style="width:100%;min-height:100px;padding:10px;border:2px solid #ddd;border-radius:6px;font-size:14px;font-family:inherit;resize:vertical" placeholder="Wpisz uzasadnienie odrzucenia (zostanie wysłane do autora)..."></textarea>';
      buttonsEl.innerHTML = '<button class="jg-btn jg-btn--ghost" id="jg-confirm-no">Anuluj</button><button class="jg-btn jg-btn--danger" id="jg-confirm-yes">Odrzuć</button>';

      modal.style.display = 'flex';

      var textarea = document.getElementById('jg-reject-reason-textarea');
      var yesBtn = document.getElementById('jg-confirm-yes');
      var noBtn = document.getElementById('jg-confirm-no');

      // Focus textarea after a brief delay
      setTimeout(function() {
        if (textarea) textarea.focus();
      }, 100);

      yesBtn.onclick = function() {
        var reason = textarea.value.trim();
        modal.style.display = 'none';
        resolve(reason || '');
      };

      noBtn.onclick = function() {
        modal.style.display = 'none';
        resolve(null);
      };

      // Close on background click = cancel
      modal.onclick = function(e) {
        if (e.target === modal) {
          modal.style.display = 'none';
          resolve(null);
        }
      };

      // Allow Enter with Ctrl/Cmd to submit
      textarea.onkeydown = function(e) {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
          yesBtn.onclick();
        }
      };
    });
  }

  function showError(msg) {
    debugError('[JG MAP]', msg);
    if (loadingEl) loadingEl.style.display = 'none';
    if (errorEl) errorEl.style.display = 'block';
    if (errorMsg) errorMsg.textContent = msg;
  }

  function hideLoading() {
    var elapsed = Date.now() - loadStartTime;
    var remaining = minLoadingTime - elapsed;


    if (remaining > 0) {
      setTimeout(function() {
        if (loadingEl) {
          loadingEl.style.display = 'none';
        }
      }, remaining);
    } else {
      if (loadingEl) {
        loadingEl.style.display = 'none';
      } else {
      }
    }
  }

  function wait(cb, maxAttempts) {
    var attempts = 0;
    var interval = setInterval(function() {
      attempts++;

      if (window.L && L.map && L.markerClusterGroup) {
        clearInterval(interval);
        // Don't hide loading here - let draw() handle it when map is ready with data
        cb();
        return;
      }

      if (attempts > maxAttempts) {
        clearInterval(interval);
        var missing = [];
        if (!window.L) missing.push('Leaflet');
        if (window.L && !L.map) missing.push('L.map');
        if (window.L && !L.markerClusterGroup) missing.push('L.markerClusterGroup');
        showError('Nie udało się załadować: ' + missing.join(', '));
      }
    }, 100);
  }

  wait(init, 100);

  function init() {
    try {
      var CFG = window.JG_MAP_CFG || {};
      if (!CFG.ajax || !CFG.nonce) {
        showError('Brak konfiguracji JG_MAP_CFG');
        return;
      }

      var elMap = document.getElementById('jg-map');
      var elFilters = document.getElementById('jg-map-filters');
      var modalAdd = document.getElementById('jg-map-modal-add');
      var modalView = document.getElementById('jg-map-modal-view');
      var modalReport = document.getElementById('jg-map-modal-report');
      var modalReportsList = document.getElementById('jg-map-modal-reports-list');
      var modalEdit = document.getElementById('jg-map-modal-edit');
      var modalAuthor = document.getElementById('jg-map-modal-author');
      var modalStatus = document.getElementById('jg-map-modal-status');
      var lightbox = document.getElementById('jg-map-lightbox');

      // Stats modal refresh interval
      var statsRefreshInterval = null;

      if (!elMap) {
        showError('Nie znaleziono #jg-map');
        return;
      }

      if ((elMap.offsetHeight || 0) < 50) elMap.style.minHeight = '520px';

      // ====================================
      // Custom Top Bar: Clock and Profile
      // ====================================
      var topBarDateTime = document.getElementById('jg-top-bar-datetime');
      var editProfileBtn = document.getElementById('jg-edit-profile-btn');

      // Update clock display
      function updateDateTime() {
        if (!topBarDateTime) return;

        var now = new Date();
        var days = ['Niedziela', 'Poniedziałek', 'Wtorek', 'Środa', 'Czwartek', 'Piątek', 'Sobota'];
        var dayName = days[now.getDay()];

        var day = String(now.getDate()).padStart(2, '0');
        var month = String(now.getMonth() + 1).padStart(2, '0');
        var year = now.getFullYear();

        var hours = String(now.getHours()).padStart(2, '0');
        var minutes = String(now.getMinutes()).padStart(2, '0');
        var seconds = String(now.getSeconds()).padStart(2, '0');

        topBarDateTime.textContent = dayName + ', ' + day + '.' + month + '.' + year + ' • ' + hours + ':' + minutes + ':' + seconds;
      }

      // Update clock every second
      if (topBarDateTime) {
        updateDateTime();
        setInterval(updateDateTime, 1000);
      }

      // Edit profile button handler
      if (editProfileBtn && !editProfileBtn.jgHandlerAttached) {
        editProfileBtn.addEventListener('click', function() {
          var html = '<div class="jg-modal-header" style="background:#8d2324;color:#fff;padding:20px 24px;border-radius:8px 8px 0 0">' +
            '<h2 style="margin:0;font-size:20px;font-weight:600">Edycja profilu</h2>' +
            '</div>' +
            '<div class="jg-modal-body" style="padding:24px">' +
            '<form id="jg-edit-profile-form">' +
            '<div class="jg-form-group" style="margin-bottom:20px">' +
            '<label style="display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px">Adres email</label>' +
            '<input type="email" id="profile-email" class="jg-input" required style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:14px;transition:border-color 0.2s" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#ddd\'">' +
            '</div>' +
            '<div class="jg-form-group" style="margin-bottom:20px">' +
            '<label style="display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px">Nowe hasło <span style="font-weight:400;color:#666;font-size:12px">(pozostaw puste, aby nie zmieniać)</span></label>' +
            '<input type="password" id="profile-password" class="jg-input" style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:14px;transition:border-color 0.2s" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#ddd\'">' +
            '</div>' +
            '<div class="jg-form-group" style="margin-bottom:20px">' +
            '<label style="display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px">Potwierdź hasło</label>' +
            '<input type="password" id="profile-password-confirm" class="jg-input" style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:14px;transition:border-color 0.2s" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#ddd\'">' +
            '</div>' +
            '</form>' +
            '</div>' +
            '<div class="jg-modal-footer" style="padding:16px 24px;background:#f9f9f9;border-top:1px solid #e5e5e5;display:flex;gap:12px;justify-content:flex-end;border-radius:0 0 8px 8px">' +
            '<button class="jg-btn jg-btn--secondary" onclick="document.getElementById(\'jg-map-modal-edit\').style.display=\'none\'" style="padding:10px 20px;background:#fff;color:#333;border:2px solid #ddd;border-radius:6px;font-weight:600;cursor:pointer;transition:all 0.2s" onmouseover="this.style.background=\'#f5f5f5\'" onmouseout="this.style.background=\'#fff\'">Anuluj</button>' +
            '<button class="jg-btn jg-btn--primary" id="save-profile-btn" style="padding:10px 24px;background:#8d2324;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;transition:all 0.2s" onmouseover="this.style.background=\'#a02829\'" onmouseout="this.style.background=\'#8d2324\'">Zapisz zmiany</button>' +
            '</div>';

          open(modalEdit, html);

          // Load current user data
          jQuery.ajax({
            url: CFG.ajax,
            type: 'POST',
            data: {
              action: 'jg_map_get_current_user',
              nonce: CFG.nonce
            },
            success: function(response) {
              if (response.success && response.data) {
                document.getElementById('profile-email').value = response.data.email || '';
              }
            }
          });

          // Save profile handler
          document.getElementById('save-profile-btn').addEventListener('click', function() {
            var email = document.getElementById('profile-email').value;
            var password = document.getElementById('profile-password').value;
            var passwordConfirm = document.getElementById('profile-password-confirm').value;

            if (!email) {
              showAlert('Proszę wypełnić adres email');
              return;
            }

            if (password && password !== passwordConfirm) {
              showAlert('Hasła nie pasują do siebie');
              return;
            }

            jQuery.ajax({
              url: CFG.ajax,
              type: 'POST',
              data: {
                action: 'jg_map_update_profile',
                nonce: CFG.nonce,
                email: email,
                password: password
              },
              success: function(response) {
                if (response.success) {
                  showAlert('Profil został zaktualizowany').then(function() {
                    close(modalEdit);
                    location.reload();
                  });
                } else {
                  showAlert(response.data || 'Wystąpił błąd podczas aktualizacji profilu');
                }
              },
              error: function() {
                showAlert('Wystąpił błąd podczas komunikacji z serwerem');
              }
            });
          });
        });
        editProfileBtn.jgHandlerAttached = true;
      }

      // Open login modal function - reusable
      function openLoginModal() {
        var modalEdit = document.getElementById('jg-map-modal-edit');
        var html = '<div class="jg-modal-header" style="background:#8d2324;color:#fff;padding:20px 24px;border-radius:8px 8px 0 0">' +
          '<h2 style="margin:0;font-size:20px;font-weight:600">Logowanie</h2>' +
          '</div>' +
          '<div class="jg-modal-body" style="padding:24px">' +
          '<form id="jg-login-form">' +
          '<!-- Honeypot field - hidden from users, visible to bots -->' +
          '<div style="position:absolute;left:-9999px;top:-9999px">' +
          '<label for="login-website">Website</label>' +
          '<input type="text" id="login-website" name="website" tabindex="-1" autocomplete="off">' +
          '</div>' +
          '<div class="jg-form-group" style="margin-bottom:20px">' +
          '<label style="display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px">Nazwa użytkownika lub email</label>' +
          '<input type="text" id="login-username" class="jg-input" required style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:14px;transition:border-color 0.2s" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#ddd\'">' +
          '</div>' +
          '<div class="jg-form-group" style="margin-bottom:20px">' +
          '<label style="display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px">Hasło</label>' +
          '<input type="password" id="login-password" class="jg-input" required style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:14px;transition:border-color 0.2s" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#ddd\'">' +
          '</div>' +
          '<div style="text-align:right;margin-bottom:20px">' +
          '<a href="#" id="forgot-password-link" style="color:#8d2324;font-size:13px;text-decoration:none;font-weight:600" onmouseover="this.style.textDecoration=\'underline\'" onmouseout="this.style.textDecoration=\'none\'">Zapomniałeś hasła?</a>' +
          '</div>' +
          '</form>' +
          '</div>' +
          '<div class="jg-modal-footer" style="padding:16px 24px;background:#f9f9f9;border-top:1px solid #e5e5e5;display:flex;gap:12px;justify-content:flex-end;border-radius:0 0 8px 8px">' +
          '<button class="jg-btn jg-btn--secondary" onclick="document.getElementById(\'jg-map-modal-edit\').style.display=\'none\'" style="padding:10px 20px;background:#fff;color:#333;border:2px solid #ddd;border-radius:6px;font-weight:600;cursor:pointer;transition:all 0.2s" onmouseover="this.style.background=\'#f5f5f5\'" onmouseout="this.style.background=\'#fff\'">Anuluj</button>' +
          '<button class="jg-btn jg-btn--primary" id="submit-login-btn" style="padding:10px 24px;background:#8d2324;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;transition:all 0.2s" onmouseover="this.style.background=\'#a02829\'" onmouseout="this.style.background=\'#8d2324\'">Zaloguj się</button>' +
          '</div>';

        open(modalEdit, html);

        // Forgot password link handler
        document.getElementById('forgot-password-link').addEventListener('click', function(e) {
          e.preventDefault();
          showForgotPasswordModal();
        });

        // Login submission handler
        function submitLogin() {
          var username = document.getElementById('login-username').value;
          var password = document.getElementById('login-password').value;
          var honeypot = document.getElementById('login-website').value;

          if (!username || !password) {
            showAlert('Proszę wypełnić wszystkie pola');
            return;
          }

          jQuery.ajax({
            url: CFG.ajax,
            type: 'POST',
            data: {
              action: 'jg_map_login',
              honeypot: honeypot,
              username: username,
              password: password
            },
            success: function(response) {
              if (response.success) {
                // No alert - just reload the page
                close(modalEdit);
                location.reload();
              } else {
                showAlert(response.data || 'Błąd logowania');
              }
            },
            error: function() {
              showAlert('Wystąpił błąd podczas logowania');
            }
          });
        }

        // Button click handler
        document.getElementById('submit-login-btn').addEventListener('click', submitLogin);

        // Enter key handler
        document.getElementById('jg-login-form').addEventListener('keypress', function(e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            submitLogin();
          }
        });
      }

      // Login button handler
      var loginBtn = document.getElementById('jg-login-btn');
      if (loginBtn && !loginBtn.jgHandlerAttached) {
        loginBtn.addEventListener('click', openLoginModal);
        loginBtn.jgHandlerAttached = true;
      }

      // Register button handler
      var registerBtn = document.getElementById('jg-register-btn');
      if (registerBtn && !registerBtn.jgHandlerAttached) {
        registerBtn.addEventListener('click', function() {
          // Check registration status on server (real-time check)
          jQuery.ajax({
            url: CFG.ajax,
            type: 'POST',
            data: {
              action: 'jg_check_registration_status'
            },
            success: function(response) {
              if (response.success && response.data) {
                if (!response.data.enabled) {
                  // Registration disabled - show alert
                  showAlert(response.data.message || 'Rejestracja jest obecnie wyłączona. Spróbuj ponownie później.');
                  return;
                }
                // Registration enabled - show form
                showRegistrationForm();
              } else {
                showAlert('Wystąpił błąd podczas sprawdzania dostępności rejestracji.');
              }
            },
            error: function() {
              showAlert('Wystąpił błąd połączenia. Spróbuj ponownie później.');
            }
          });
        });
        registerBtn.jgHandlerAttached = true;
      }

      // Registration form function
      function showRegistrationForm() {
          // Show registration form
          var html = '<div class="jg-modal-header" style="background:#8d2324;color:#fff;padding:20px 24px;border-radius:8px 8px 0 0">' +
            '<h2 style="margin:0;font-size:20px;font-weight:600">Rejestracja</h2>' +
            '</div>' +
            '<div class="jg-modal-body" style="padding:24px">' +
            '<form id="jg-register-form">' +
            '<!-- Honeypot field - hidden from users, visible to bots -->' +
            '<div style="position:absolute;left:-9999px;top:-9999px">' +
            '<label for="register-website">Website</label>' +
            '<input type="text" id="register-website" name="website" tabindex="-1" autocomplete="off">' +
            '</div>' +
            '<div class="jg-form-group" style="margin-bottom:20px">' +
            '<label style="display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px">Nazwa użytkownika</label>' +
            '<input type="text" id="register-username" class="jg-input" required style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:14px;transition:border-color 0.2s" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#ddd\'">' +
            '</div>' +
            '<div class="jg-form-group" style="margin-bottom:20px">' +
            '<label style="display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px">Adres email</label>' +
            '<input type="email" id="register-email" class="jg-input" required style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:14px;transition:border-color 0.2s" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#ddd\'">' +
            '</div>' +
            '<div class="jg-form-group" style="margin-bottom:20px">' +
            '<label style="display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px">Hasło</label>' +
            '<input type="password" id="register-password" class="jg-input" required style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:14px;transition:border-color 0.2s" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#ddd\'">' +
            '</div>' +
            '<div style="font-size:12px;color:#666;margin-top:8px">📧 Na podany adres email zostanie wysłany link aktywacyjny</div>' +
            '</form>' +
            '</div>' +
            '<div class="jg-modal-footer" style="padding:16px 24px;background:#f9f9f9;border-top:1px solid #e5e5e5;display:flex;gap:12px;justify-content:flex-end;border-radius:0 0 8px 8px">' +
            '<button class="jg-btn jg-btn--secondary" onclick="document.getElementById(\'jg-map-modal-edit\').style.display=\'none\'" style="padding:10px 20px;background:#fff;color:#333;border:2px solid #ddd;border-radius:6px;font-weight:600;cursor:pointer;transition:all 0.2s" onmouseover="this.style.background=\'#f5f5f5\'" onmouseout="this.style.background=\'#fff\'">Anuluj</button>' +
            '<button class="jg-btn jg-btn--primary" id="submit-register-btn" style="padding:10px 24px;background:#8d2324;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;transition:all 0.2s" onmouseover="this.style.background=\'#a02829\'" onmouseout="this.style.background=\'#8d2324\'">Zarejestruj się</button>' +
            '</div>';

          open(modalEdit, html);

          // Registration submission handler
          function submitRegistration() {
            var username = document.getElementById('register-username').value;
            var email = document.getElementById('register-email').value;
            var password = document.getElementById('register-password').value;
            var honeypot = document.getElementById('register-website').value;

            if (!username || !email || !password) {
              showAlert('Proszę wypełnić wszystkie pola');
              return;
            }

            jQuery.ajax({
              url: CFG.ajax,
              type: 'POST',
              data: {
                action: 'jg_map_register',
                honeypot: honeypot,
                username: username,
                email: email,
                password: password
              },
              success: function(response) {
                if (response.success) {
                  // Show beautiful success modal instead of alert
                  var successHtml = '<div class="jg-modal-header" style="background:#15803d;color:#fff;padding:20px 24px;border-radius:8px 8px 0 0">' +
                    '<h2 style="margin:0;font-size:20px;font-weight:600">✅ Rejestracja zakończona pomyślnie!</h2>' +
                    '</div>' +
                    '<div class="jg-modal-body" style="padding:24px;text-align:center">' +
                    '<div style="font-size:48px;margin:20px 0">📧</div>' +
                    '<p style="font-size:16px;line-height:1.6;color:#333;margin-bottom:20px">Na adres email <strong style="color:#8d2324">' + esc(email) + '</strong> wysłaliśmy wiadomość z linkiem aktywacyjnym.</p>' +
                    '<p style="font-size:14px;color:#666;margin-bottom:20px">Sprawdź swoją skrzynkę pocztową (również folder SPAM) i kliknij w link, aby dokończyć rejestrację.</p>' +
                    '<div style="background:#fef3c7;border:2px solid #f59e0b;border-radius:8px;padding:12px;margin-top:20px">' +
                    '<p style="font-size:13px;color:#92400e;margin:0">⏰ Link aktywacyjny jest ważny przez 48 godzin</p>' +
                    '</div>' +
                    '</div>' +
                    '<div class="jg-modal-footer" style="padding:16px 24px;background:#f9f9f9;border-top:1px solid #e5e5e5;display:flex;gap:12px;justify-content:center;border-radius:0 0 8px 8px">' +
                    '<button class="jg-btn jg-btn--primary" onclick="document.getElementById(\'jg-map-modal-edit\').style.display=\'none\';location.reload()" style="padding:10px 24px;background:#15803d;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer">OK, rozumiem</button>' +
                    '</div>';

                  open(modalEdit, successHtml);
                } else {
                  showAlert(response.data || 'Błąd rejestracji');
                }
              },
              error: function() {
                showAlert('Wystąpił błąd podczas rejestracji');
              }
            });
          }

          // Button click handler
          document.getElementById('submit-register-btn').addEventListener('click', submitRegistration);

          // Enter key handler
          document.getElementById('jg-register-form').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
              e.preventDefault();
              submitRegistration();
            }
          });
      }

      // Forgot password modal function
      function showForgotPasswordModal() {
        var modalEdit = document.getElementById('jg-map-modal-edit');
        var html = '<div class="jg-modal-header" style="background:#8d2324;color:#fff;padding:20px 24px;border-radius:8px 8px 0 0">' +
          '<h2 style="margin:0;font-size:20px;font-weight:600">🔑 Odzyskiwanie hasła</h2>' +
          '</div>' +
          '<div class="jg-modal-body" style="padding:24px">' +
          '<p style="font-size:14px;color:#666;margin-bottom:20px">Podaj swój adres email, a wyślemy Ci link do zresetowania hasła.</p>' +
          '<form id="jg-forgot-password-form">' +
          '<div class="jg-form-group" style="margin-bottom:20px">' +
          '<label style="display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px">Adres email</label>' +
          '<input type="email" id="forgot-email" class="jg-input" required style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:14px;transition:border-color 0.2s" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#ddd\'">' +
          '</div>' +
          '</form>' +
          '</div>' +
          '<div class="jg-modal-footer" style="padding:16px 24px;background:#f9f9f9;border-top:1px solid #e5e5e5;display:flex;gap:12px;justify-content:flex-end;border-radius:0 0 8px 8px">' +
          '<button class="jg-btn jg-btn--secondary" onclick="document.getElementById(\'jg-map-modal-edit\').style.display=\'none\'" style="padding:10px 20px;background:#fff;color:#333;border:2px solid #ddd;border-radius:6px;font-weight:600;cursor:pointer;transition:all 0.2s" onmouseover="this.style.background=\'#f5f5f5\'" onmouseout="this.style.background=\'#fff\'">Anuluj</button>' +
          '<button class="jg-btn jg-btn--primary" id="submit-forgot-btn" style="padding:10px 24px;background:#8d2324;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;transition:all 0.2s" onmouseover="this.style.background=\'#a02829\'" onmouseout="this.style.background=\'#8d2324\'">Wyślij link</button>' +
          '</div>';

        open(modalEdit, html);

        // Forgot password submission handler
        function submitForgotPassword() {
          var email = document.getElementById('forgot-email').value;

          if (!email) {
            showAlert('Proszę podać adres email');
            return;
          }

          // Validate email format
          var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          if (!emailRegex.test(email)) {
            showAlert('Proszę podać prawidłowy adres email');
            return;
          }

          jQuery.ajax({
            url: CFG.ajax,
            type: 'POST',
            data: {
              action: 'jg_map_forgot_password',
              email: email
            },
            success: function(response) {
              if (response.success) {
                // Show success modal
                var successHtml = '<div class="jg-modal-header" style="background:#15803d;color:#fff;padding:20px 24px;border-radius:8px 8px 0 0">' +
                  '<h2 style="margin:0;font-size:20px;font-weight:600">✅ Link wysłany!</h2>' +
                  '</div>' +
                  '<div class="jg-modal-body" style="padding:24px;text-align:center">' +
                  '<div style="font-size:48px;margin:20px 0">📧</div>' +
                  '<p style="font-size:16px;line-height:1.6;color:#333;margin-bottom:20px">Na adres <strong style="color:#8d2324">' + esc(email) + '</strong> wysłaliśmy link do resetowania hasła.</p>' +
                  '<p style="font-size:14px;color:#666;margin-bottom:20px">Sprawdź swoją skrzynkę pocztową (również folder SPAM) i kliknij w link, aby ustawić nowe hasło.</p>' +
                  '<div style="background:#fef3c7;border:2px solid #f59e0b;border-radius:8px;padding:12px;margin-top:20px">' +
                  '<p style="font-size:13px;color:#92400e;margin:0">⏰ Link jest ważny przez 24 godziny</p>' +
                  '</div>' +
                  '</div>' +
                  '<div class="jg-modal-footer" style="padding:16px 24px;background:#f9f9f9;border-top:1px solid #e5e5e5;display:flex;gap:12px;justify-center;border-radius:0 0 8px 8px">' +
                  '<button class="jg-btn jg-btn--primary" onclick="document.getElementById(\'jg-map-modal-edit\').style.display=\'none\'" style="padding:10px 24px;background:#15803d;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer">OK, rozumiem</button>' +
                  '</div>';

                open(modalEdit, successHtml);
              } else {
                showAlert(response.data || 'Nie znaleziono użytkownika z tym adresem email');
              }
            },
            error: function() {
              showAlert('Wystąpił błąd podczas wysyłania emaila');
            }
          });
        }

        // Button click handler
        document.getElementById('submit-forgot-btn').addEventListener('click', submitForgotPassword);

        // Enter key handler
        document.getElementById('jg-forgot-password-form').addEventListener('keypress', function(e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            submitForgotPassword();
          }
        });
      }

      // ====================================
      // End: Custom Top Bar
      // ====================================

      function qs(s, p) {
        return (p || document).querySelector(s);
      }

      function esc(s) {
        s = String(s || '');
        return s.replace(/[&<>"']/g, function(m) {
          return {"&":"&amp;", "<":"&lt;", ">":"&gt;", '"':"&quot;", "'":"&#39;"}[m];
        });
      }

      // Helper to format category slug to readable text
      // e.g. 'niepoprawnie_zaparkowane_auto' -> 'Niepoprawnie zaparkowane auto'
      function formatCategorySlug(slug) {
        if (!slug) return '';
        // Replace underscores with spaces and capitalize first letter
        var text = slug.replace(/_/g, ' ');
        return text.charAt(0).toUpperCase() + text.slice(1);
      }

      function open(bg, html, opts) {
        if (!bg) return;
        var c = qs('.jg-modal, .jg-lightbox', bg);
        if (!c) return;
        if (opts && opts.addClass) {
          var classes = opts.addClass.trim().split(/\s+/);
          classes.forEach(function(cls) {
            if (cls) c.classList.add(cls);
          });
        }
        c.innerHTML = html;
        bg.style.display = 'flex';

        // Update URL in browser address bar if opening point detail modal
        if (bg.id === 'jg-map-modal-view' && opts && opts.pointData) {
          var point = opts.pointData;

          if (point.slug && point.type) {
            // Determine URL path based on point type
            var typePath = 'miejsce'; // default
            if (point.type === 'ciekawostka') {
              typePath = 'ciekawostka';
            } else if (point.type === 'zgloszenie') {
              typePath = 'zgloszenie';
            }

            var newUrl = '/' + typePath + '/' + point.slug + '/';


            // Push state to browser history
            if (window.history && window.history.pushState) {
              window.history.pushState(
                { pointId: point.id, pointSlug: point.slug, pointType: point.type },
                point.title || '',
                newUrl
              );
            } else {
              debugError('[JG MAP] history.pushState not supported');
            }
          } else {
            debugWarn('[JG MAP] Point missing slug or type:', point);
          }
        }
      }

      function close(bg) {
        if (!bg) return;

        // Clear stats refresh interval when closing stats modal
        if (bg.id === 'jg-map-modal-report' && statsRefreshInterval) {
          clearInterval(statsRefreshInterval);
          statsRefreshInterval = null;
        }

        var c = qs('.jg-modal, .jg-lightbox', bg);
        if (c) {
          c.className = c.className.replace(/\bjg-modal--\w+/g, '');
          if (!c.classList.contains('jg-modal') && !c.classList.contains('jg-lightbox')) {
            c.className = 'jg-modal';
          }
        }
        bg.style.display = 'none';

        // Reset URL to homepage when closing point detail modal
        if (bg.id === 'jg-map-modal-view') {
          if (window.history && window.history.pushState) {
            // Check if current URL is a point URL (starts with /miejsce/, /ciekawostka/, or /zgloszenie/)
            var currentPath = window.location.pathname;
            if (currentPath.match(/^\/(miejsce|ciekawostka|zgloszenie)\//)) {
              window.history.pushState({}, '', '/');
            }
          }
        }
      }

      [modalAdd, modalView, modalReport, modalReportsList, modalEdit, modalAuthor, modalStatus, lightbox].forEach(function(bg) {
        if (!bg) return;
        bg.addEventListener('click', function(e) {
          if (e.target === bg) close(bg);
        });
      });

      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          [modalAdd, modalView, modalReport, modalReportsList, modalEdit, modalAuthor, modalStatus, lightbox].forEach(close);
        }
      });

      var lat = (CFG.defaults && typeof CFG.defaults.lat === 'number') ? CFG.defaults.lat : 50.904;
      var lng = (CFG.defaults && typeof CFG.defaults.lng === 'number') ? CFG.defaults.lng : 15.734;
      var zoom = (CFG.defaults && typeof CFG.defaults.zoom === 'number') ? CFG.defaults.zoom : 13;

      // Override from data attributes if present
      if (elMap.dataset.lat) lat = parseFloat(elMap.dataset.lat);
      if (elMap.dataset.lng) lng = parseFloat(elMap.dataset.lng);
      if (elMap.dataset.zoom) zoom = parseInt(elMap.dataset.zoom);

      // Define bounds for Jelenia Góra region (includes all districts like Jagniątków)
      var southWest = L.latLng(50.75, 15.58);
      var northEast = L.latLng(50.98, 15.85);
      var bounds = L.latLngBounds(southWest, northEast);

      // Detect mobile device
      var isMobile = window.innerWidth <= 768;

      var map = L.map(elMap, {
        zoomControl: true,
        scrollWheelZoom: !isMobile, // Disable scroll zoom on mobile
        dragging: !isMobile, // Start with dragging disabled on mobile
        minZoom: 12,
        maxZoom: 19,
        maxBounds: bounds,
        maxBoundsViscosity: 1.0,
        tap: isMobile, // Enable tap on mobile
        touchZoom: isMobile // Enable pinch zoom on mobile
      }).setView([lat, lng], zoom);

      // Two-finger dragging on mobile: one finger scrolls page, two fingers pan map
      if (isMobile) {
        var mapContainer = elMap;

        mapContainer.addEventListener('touchstart', function(e) {
          if (e.touches.length === 1) {
            // One finger - disable map dragging to allow page scroll
            map.dragging.disable();
          } else if (e.touches.length >= 2) {
            // Two or more fingers - enable map dragging
            map.dragging.enable();
          }
        }, { passive: true });

        mapContainer.addEventListener('touchend', function(e) {
          // When fingers are lifted, disable dragging
          if (e.touches.length < 2) {
            map.dragging.disable();
          }
        }, { passive: true });

        mapContainer.addEventListener('touchcancel', function(e) {
          // If touch is cancelled, disable dragging
          map.dragging.disable();
        }, { passive: true });
      }

      // Enforce bounds strictly - reset view if user tries to go outside
      map.on('drag', function() {
        map.panInsideBounds(bounds, { animate: false });
      });

      map.on('zoomend', function() {
        if (!bounds.contains(map.getCenter())) {
          map.panInsideBounds(bounds, { animate: true });
        }
      });

      var tileLayer = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
        crossOrigin: true
      });

      tileLayer.addTo(map);

      var cluster = null;
      var markers = [];
      var clusterReady = false;
      var pendingData = null;

      function showMap() {
        if (elMap) {
          elMap.style.opacity = '1';
          inv();
          // Extra invalidateSize for mobile devices with delay
          if (window.innerWidth <= 768) {
            setTimeout(function() {
              inv();
            }, 300);
            setTimeout(function() {
              inv();
            }, 600);
          }
        }
      }

      map.whenReady(function() {
        setTimeout(function() {
          try {
            // Single cluster with grid layout showing types
            // maxClusterRadius as function: breaks apart naturally when zooming in
            // At high zoom (17-18), small radius. At max zoom (19), larger to prevent breaking apart
            cluster = L.markerClusterGroup({
              showCoverageOnHover: false,
              maxClusterRadius: function(zoom) {
                // zoom < 17: Normal clusters (80px radius)
                // zoom 17-18: Special clusters (35px radius) - only very close places
                // zoom 19: Special clusters (50px radius) - larger to prevent breaking apart but not too large
                if (zoom >= 19) return 50;
                if (zoom >= 17) return 35;
                return 80;
              },
              spiderfyOnMaxZoom: false,
              zoomToBoundsOnClick: false,
              spiderfyDistanceMultiplier: 2,
              animate: true,
              animateAddingMarkers: true,
              disableClusteringAtZoom: 20, // Never disable clustering (max zoom is 19)
              iconCreateFunction: function(clusterGroup) {
                var childMarkers = clusterGroup.getAllChildMarkers();

                // Count by type and check for moderation needs
                var sponsored = 0;
                var places = 0;
                var curiosities = 0;
                var events = 0;
                var needsModeration = 0;

                childMarkers.forEach(function(marker) {
                  var opts = marker.options;
                  if (opts.isPromo) {
                    sponsored++;
                  } else if (opts.pointType === 'miejsce') {
                    places++;
                  } else if (opts.pointType === 'ciekawostka') {
                    curiosities++;
                  } else if (opts.pointType === 'zgloszenie') {
                    events++;
                  }

                  // Check if needs moderation
                  if (opts.pointStatus === 'pending' || opts.hasReports || opts.isDeletionRequested || opts.isEdit) {
                    needsModeration++;
                  }
                });

                // Build grid HTML
                var html = '<div class="jg-cluster-grid">';

                // Top row - sponsored (if any)
                if (sponsored > 0) {
                  html += '<div class="jg-cluster-grid-top">';
                  html += '<div class="jg-cluster-cell jg-cluster-cell--sponsored">';
                  html += '<span class="jg-cluster-icon">⭐</span>';
                  html += '<span class="jg-cluster-num">' + sponsored + '</span>';
                  html += '</div>';
                  html += '</div>';
                }

                // Bottom row - types (if any)
                var hasBottom = places > 0 || curiosities > 0 || events > 0;
                if (hasBottom) {
                  html += '<div class="jg-cluster-grid-bottom">';

                  if (places > 0) {
                    html += '<div class="jg-cluster-cell jg-cluster-cell--places">';
                    html += '<div class="jg-cluster-circle" style="width:10px;height:10px;border-radius:50%;background:#0a5a28;margin:0 auto 2px"></div>';
                    html += '<span class="jg-cluster-num">' + places + '</span>';
                    html += '</div>';
                  }

                  if (curiosities > 0) {
                    html += '<div class="jg-cluster-cell jg-cluster-cell--curiosities">';
                    html += '<div class="jg-cluster-circle" style="width:10px;height:10px;border-radius:50%;background:#1e3a8a;margin:0 auto 2px"></div>';
                    html += '<span class="jg-cluster-num">' + curiosities + '</span>';
                    html += '</div>';
                  }

                  if (events > 0) {
                    html += '<div class="jg-cluster-cell jg-cluster-cell--events">';
                    html += '<div class="jg-cluster-circle" style="width:10px;height:10px;border-radius:50%;background:#888;margin:0 auto 2px"></div>';
                    html += '<span class="jg-cluster-num">' + events + '</span>';
                    html += '</div>';
                  }

                  html += '</div>';
                }

                html += '</div>';

                // Add moderation alert badge if needed (but not when cluster has sponsored items - looks bad)
                if (needsModeration > 0 && sponsored === 0) {
                  html += '<div style="position:absolute;top:-6px;right:-6px;background:#dc2626;color:#fff;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;box-shadow:0 2px 6px rgba(0,0,0,0.3)">' + needsModeration + '</div>';
                }

                // Calculate icon size based on content
                var width = 80;
                var height = sponsored > 0 && hasBottom ? 90 : 50;

                return L.divIcon({
                  html: html,
                  className: 'jg-cluster-wrapper' + (needsModeration > 0 ? ' jg-cluster--needs-moderation' : ''),
                  iconSize: [width, height]
                });
              }
            });

            map.addLayer(cluster);

            // Add cluster click handler - spiderfy for normal clusters, list for special clusters
            cluster.on('clusterclick', function(e) {
              var currentZoom = map.getZoom();
              var childMarkers = e.layer.getAllChildMarkers();

              // For lower zoom levels (< 17), use spiderfy to spread out markers with lines
              // These are normal clusters (80px radius)
              if (currentZoom < 17) {
                e.layer.spiderfy();
                return;
              }

              // For high zoom (>= 17), show list of extremely close locations
              // These are special clusters (5px radius) - places practically on top of each other
              // Build list HTML
              var listHTML = '<div class="jg-modal-header" style="background:#8d2324;color:#fff;padding:20px 24px;border-radius:8px 8px 0 0">' +
                '<h2 style="margin:0;font-size:20px;font-weight:600">Miejsca w tej lokalizacji (' + childMarkers.length + ')</h2>' +
                '</div>' +
                '<div class="jg-modal-body" style="padding:0;max-height:500px;overflow-y:auto">' +
                '<div style="display:flex;flex-direction:column;gap:1px;background:#e5e5e5">';

              childMarkers.forEach(function(marker) {
                var opts = marker.options;
                var pointId = opts.pointId || 0;
                var title = opts.pointTitle || 'Bez nazwy';
                var type = opts.pointType || 'zgloszenie';
                var excerpt = opts.pointExcerpt || '';
                var isPromo = opts.isPromo || false;
                var status = opts.pointStatus || 'publish';
                var hasReports = opts.hasReports || false;
                var reportsCount = opts.reportsCount || 0;
                var isDeletionRequested = opts.isDeletionRequested || false;
                var isEdit = opts.isEdit || false;
                var isPending = status === 'pending';

                // Truncate excerpt if too long
                var maxExcerptLength = 80;
                if (excerpt.length > maxExcerptLength) {
                  excerpt = excerpt.substring(0, maxExcerptLength) + '...';
                }

                // Type icon - colored dot or star for sponsored
                var typeIcon = '';
                var dotColor = '#888'; // Default gray for zgloszenie

                if (isPromo) {
                  typeIcon = '<div style="font-size:20px">⭐</div>';
                } else {
                  // Use colored dots matching cluster icons
                  if (type === 'miejsce') {
                    dotColor = '#0a5a28'; // Green
                  } else if (type === 'ciekawostka') {
                    dotColor = '#1e3a8a'; // Blue
                  }
                  typeIcon = '<div style="width:20px;height:20px;border-radius:50%;background:' + dotColor + '"></div>';
                }

                // Moderation status badges
                var statusBadges = '';
                if (isPending) {
                  statusBadges += '<span style="display:inline-block;padding:2px 8px;background:#dc2626;color:#fff;border-radius:4px;font-size:11px;font-weight:600;margin-left:8px">Oczekuje</span>';
                }
                if (hasReports) {
                  statusBadges += '<span style="display:inline-block;padding:2px 8px;background:#f59e0b;color:#fff;border-radius:4px;font-size:11px;font-weight:600;margin-left:8px">🚨 ' + reportsCount + ' zgł.</span>';
                }
                if (isDeletionRequested) {
                  statusBadges += '<span style="display:inline-block;padding:2px 8px;background:#9333ea;color:#fff;border-radius:4px;font-size:11px;font-weight:600;margin-left:8px">✕ Prośba o usunięcie</span>';
                }
                if (isEdit) {
                  statusBadges += '<span style="display:inline-block;padding:2px 8px;background:#8b5cf6;color:#fff;border-radius:4px;font-size:11px;font-weight:600;margin-left:8px">✏️ Edycja</span>';
                }

                // Border color based on priority
                var borderColor = '#e5e5e5';
                if (isPending || hasReports || isDeletionRequested || isEdit) {
                  borderColor = '#dc2626'; // Red for items needing moderation
                } else if (isPromo) {
                  borderColor = '#fbbf24';
                } else if (type === 'miejsce') {
                  borderColor = '#8d2324';
                } else if (type === 'ciekawostka') {
                  borderColor = '#3b82f6';
                } else {
                  borderColor = '#ef4444';
                }

                listHTML += '<div class="jg-cluster-list-item" data-point-id="' + pointId + '" style="background:#fff;padding:16px 24px;cursor:pointer;transition:background 0.2s;border-left:4px solid ' + borderColor + '" onmouseover="this.style.background=\'#f9f9f9\'" onmouseout="this.style.background=\'#fff\'">' +
                  '<div style="display:flex;align-items:center;gap:12px">' +
                  '<div>' + typeIcon + '</div>' +
                  '<div style="flex:1">' +
                  '<div style="font-weight:600;font-size:16px;color:#333;margin-bottom:4px">' + title + statusBadges + '</div>' +
                  '<div style="font-size:13px;color:#666">' + excerpt + '</div>' +
                  '</div>' +
                  '<div style="color:#8d2324;font-size:20px">→</div>' +
                  '</div>' +
                  '</div>';
              });

              listHTML += '</div></div>' +
                '<div class="jg-modal-footer" style="padding:16px 24px;background:#f9f9f9;border-top:1px solid #e5e5e5;display:flex;justify-content:flex-end;border-radius:0 0 8px 8px">' +
                '<button class="jg-btn jg-btn--secondary" onclick="document.getElementById(\'jg-map-modal-view\').style.display=\'none\'" style="padding:10px 20px;background:#fff;color:#333;border:2px solid #ddd;border-radius:6px;font-weight:600;cursor:pointer;transition:all 0.2s" onmouseover="this.style.background=\'#f5f5f5\'" onmouseout="this.style.background=\'#fff\'">Zamknij</button>' +
                '</div>';

              open(modalView, listHTML);

              // Add click handlers to list items
              setTimeout(function() {
                var items = document.querySelectorAll('.jg-cluster-list-item');
                items.forEach(function(item) {
                  item.addEventListener('click', function() {
                    var pointId = parseInt(this.getAttribute('data-point-id'));
                    if (pointId) {
                      // Find the marker and trigger its click event
                      childMarkers.forEach(function(m) {
                        if (m.options.pointId === pointId) {
                          close(modalView);
                          setTimeout(function() {
                            m.fire('click');
                          }, 100);
                        }
                      });
                    }
                  });
                });
              }, 100);
            });

            clusterReady = true;

            // Clean up any old markers
            if (markers.length > 0) {
              markers.forEach(function(m) {
                try {
                  m.off();
                  if (map.hasLayer(m)) map.removeLayer(m);
                } catch (e) {}
              });
              markers = [];
            }

            // Process pending data if any (regardless of markers)
            // FIX: Check for null instead of length to handle empty arrays too
            if (pendingData !== null) {
              setTimeout(function() {
                draw(pendingData);
                pendingData = null; // Clear after processing
              }, 300);
            }
          } catch (e) {
            debugError('[JG MAP] Błąd tworzenia clustera:', e);
            clusterReady = false;
          }
        }, 800);
      });

      function inv() {
        try {
          map.invalidateSize(false);
        } catch (_) {}
      }

      setTimeout(inv, 80);
      setTimeout(inv, 300);
      setTimeout(inv, 900);
      window.addEventListener('resize', inv);

      var FLOOD_DELAY = 60000; // 60 seconds between submissions
      var REPORT_DELAY = 300000; // 5 minutes (300 seconds) between reports

      // Load last submit time from localStorage
      function getLastSubmitTime() {
        try {
          var stored = localStorage.getItem('jg_last_submit_time');
          return stored ? parseInt(stored) : 0;
        } catch (e) {
          return 0;
        }
      }

      function setLastSubmitTime(time) {
        try {
          localStorage.setItem('jg_last_submit_time', time.toString());
        } catch (e) {}
      }

      // Load last report time from localStorage
      function getLastReportTime() {
        try {
          var stored = localStorage.getItem('jg_last_report_time');
          return stored ? parseInt(stored) : 0;
        } catch (e) {
          return 0;
        }
      }

      function setLastReportTime(time) {
        try {
          localStorage.setItem('jg_last_report_time', time.toString());
        } catch (e) {}
      }

      var lastSubmitTime = getLastSubmitTime();
      var lastReportTime = getLastReportTime();
      var mapMoveDetected = false;
      var mapClickTimeout = null;
      var MIN_ZOOM_FOR_ADD = 17;

      map.on('movestart', function() {
        mapMoveDetected = true;
      });

      map.on('moveend', function() {
        setTimeout(function() {
          mapMoveDetected = false;
        }, 100);
      });

      map.on('click', function(e) {
        if (mapMoveDetected) return;

        if (map.getZoom() < MIN_ZOOM_FOR_ADD) {
          showAlert('Przybliż mapę maksymalnie (zoom ' + MIN_ZOOM_FOR_ADD + '+)!');
          return;
        }

        if (mapClickTimeout) clearTimeout(mapClickTimeout);

        mapClickTimeout = setTimeout(function() {
          if (!CFG.isLoggedIn) {
            showAlert('Musisz być zalogowany, aby dodać miejsce.').then(function() {
              openLoginModal();
            });
            return;
          }

          // Check if user is banned or has add_places restriction
          if (window.JG_USER_RESTRICTIONS) {
            if (window.JG_USER_RESTRICTIONS.is_banned) {
              showAlert('Nie możesz dodawać miejsc - Twoje konto jest zbanowane.');
              return;
            }
            if (window.JG_USER_RESTRICTIONS.restrictions && window.JG_USER_RESTRICTIONS.restrictions.indexOf('add_places') !== -1) {
              showAlert('Nie możesz dodawać miejsc - masz aktywną blokadę dodawania miejsc.');
              return;
            }
          }

          var now = Date.now();
          var remainingMs = FLOOD_DELAY - (now - lastSubmitTime);

          if (lastSubmitTime > 0 && remainingMs > 0) {
            var sec = Math.ceil(remainingMs / 1000);

            // For admins: show modal with countdown and bypass button
            if (CFG.isAdmin) {
              showConfirm(
                'Minęło dopiero ' + Math.floor((now - lastSubmitTime) / 1000) + ' sekund od ostatniego dodania miejsca.\n\n' +
                'Poczekaj jeszcze <strong id="jg-cooldown-timer">' + sec + '</strong> sekund lub dodaj pomimo limitu.',
                'Limit czasu',
                'Dodaj pomimo tego'
              ).then(function(confirmed) {
                if (confirmed) {
                  // Bypass: reset lastSubmitTime and continue
                  lastSubmitTime = 0;
                  setLastSubmitTime(0);
                  // Trigger click again to proceed
                  map.fire('click', e);
                }
              });

              // Start countdown timer
              var timerEl = null;
              var countdownInterval = setInterval(function() {
                timerEl = document.getElementById('jg-cooldown-timer');
                if (timerEl) {
                  var remaining = Math.ceil((FLOOD_DELAY - (Date.now() - lastSubmitTime)) / 1000);
                  if (remaining <= 0) {
                    clearInterval(countdownInterval);
                    timerEl.textContent = '0';
                  } else {
                    timerEl.textContent = remaining.toString();
                  }
                } else {
                  clearInterval(countdownInterval);
                }
              }, 1000);

              return;
            } else {
              // For regular users: just show alert
              showAlert('Poczekaj jeszcze ' + sec + ' sekund.');
              return;
            }
          }

          var lat = e.latlng.lat.toFixed(6);
          var lng = e.latlng.lng.toFixed(6);

          // Fetch daily limits first
          api('jg_get_daily_limits', {})
            .then(function(limits) {
              var limitsHtml = '';
              if (!limits.is_admin) {
                var photoRemaining = (limits.photo_limit_mb - limits.photo_used_mb).toFixed(2);
                limitsHtml = '<div class="cols-2" style="background:#f0f9ff;border:2px solid #3b82f6;border-radius:8px;padding:12px;margin-bottom:12px">' +
                  '<strong style="color:#1e40af">Pozostałe dzienne limity:</strong>' +
                  '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px">' +
                  '<div style="background:#fff;padding:8px;border-radius:4px;text-align:center">' +
                  '<div style="font-size:24px;font-weight:700;color:#3b82f6">' + limits.places_remaining + '</div>' +
                  '<div style="font-size:11px;color:#666">miejsc/ciekawostek</div>' +
                  '</div>' +
                  '<div style="background:#fff;padding:8px;border-radius:4px;text-align:center">' +
                  '<div style="font-size:24px;font-weight:700;color:#3b82f6">' + limits.reports_remaining + '</div>' +
                  '<div style="font-size:11px;color:#666">zgłoszeń</div>' +
                  '</div>' +
                  '</div>' +
                  '<div style="margin-top:8px;padding:8px;background:#fff;border-radius:4px;text-align:center">' +
                  '<div style="font-size:18px;font-weight:700;color:#8b5cf6">' + photoRemaining + ' MB / ' + limits.photo_limit_mb + ' MB</div>' +
                  '<div style="font-size:11px;color:#666">pozostały miesięczny limit zdjęć</div>' +
                  '</div>' +
                  '</div>';
              }

              var formHtml = '<header><h3>Dodaj nowe miejsce</h3><button class="jg-close" id="add-close">&times;</button></header>' +
                '<form id="add-form" class="jg-grid cols-2">' +
                '<input type="hidden" name="lat" id="add-lat-input" value="' + lat + '">' +
                '<input type="hidden" name="lng" id="add-lng-input" value="' + lng + '">' +
                '<input type="hidden" name="address" id="add-address-input" value="">' +
                limitsHtml +
                '<div class="cols-2" id="add-address-display" style="padding:8px 12px;background:#f3f4f6;border-left:3px solid #8d2324;border-radius:4px;font-size:13px;color:#374151;margin-bottom:8px"><strong>📍 Wczytywanie adresu...</strong></div>' +
                '<label>Tytuł* <input name="title" required placeholder="Nazwa miejsca" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px"></label>' +
                '<label>Typ* <select name="type" id="add-type-select" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' +
                '<option value="zgloszenie">Zgłoszenie</option>' +
                '<option value="ciekawostka">Ciekawostka</option>' +
                '<option value="miejsce">Miejsce</option>' +
                '</select></label>' +
                '<label class="cols-2" id="add-category-field" style="display:block"><span style="color:#dc2626">Kategoria zgłoszenia*</span> <select name="category" id="add-category-select" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' +
                generateCategoryOptions('') +
                '</select></label>' +
                '<label class="cols-2">Opis <textarea name="content" rows="4" maxlength="800" id="add-content-input" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px"></textarea><div id="add-content-counter" style="font-size:12px;color:#666;margin-top:4px;text-align:right">0 / 800 znaków</div></label>' +
                '<label class="cols-2"><input type="checkbox" name="public_name"> Pokaż moją nazwę użytkownika</label>' +
                '<label class="cols-2">Zdjęcia (max 6) <input type="file" name="images[]" multiple accept="image/*" id="add-images-input" style="width:100%;padding:8px"></label>' +
                '<div class="cols-2" id="add-images-preview" style="display:none;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:8px;margin-top:8px"></div>' +
                '<div class="cols-2" style="display:flex;gap:8px;justify-content:flex-end">' +
                '<button type="button" class="jg-btn jg-btn--ghost" id="add-cancel">Anuluj</button>' +
                '<button type="submit" class="jg-btn">Wyślij do moderacji</button>' +
                '</div>' +
                '<div id="add-msg" class="cols-2" style="font-size:12px;color:#555"></div>' +
                '</form>';

              open(modalAdd, formHtml);

              qs('#add-close', modalAdd).onclick = function() {
                close(modalAdd);
              };

              qs('#add-cancel', modalAdd).onclick = function() {
                close(modalAdd);
              };

              var form = qs('#add-form', modalAdd);
              var msg = qs('#add-msg', modalAdd);

              // Character counter for description
              var contentInput = qs('#add-content-input', modalAdd);
              var contentCounter = qs('#add-content-counter', modalAdd);
              if (contentInput && contentCounter) {
                contentInput.addEventListener('input', function() {
                  var length = this.value.length;
                  var maxLength = 800;
                  contentCounter.textContent = length + ' / ' + maxLength + ' znaków';
                  if (length > maxLength * 0.9) {
                    contentCounter.style.color = '#d97706';
                  } else {
                    contentCounter.style.color = '#666';
                  }
                });
              }

              // Image preview functionality
              var imagesInput = qs('#add-images-input', modalAdd);
              var imagesPreview = qs('#add-images-preview', modalAdd);

              if (imagesInput) {
                imagesInput.addEventListener('change', function(e) {
                  imagesPreview.innerHTML = '';
                  var files = e.target.files;

                  if (files.length > 6) {
                    msg.textContent = 'Uwaga: Możesz dodać maksymalnie 6 zdjęć. Pierwsze 6 zostanie użytych.';
                    msg.style.color = '#d97706';
                  } else if (msg.textContent.indexOf('maksymalnie 6') !== -1) {
                    msg.textContent = '';
                  }

                  if (files.length > 0) {
                    imagesPreview.style.display = 'grid';
                    var maxFiles = Math.min(files.length, 6);
                    for (var i = 0; i < maxFiles; i++) {
                      var file = files[i];
                      var reader = new FileReader();

                      reader.onload = (function(f) {
                        return function(e) {
                          var imgHtml = '<div style="position:relative;aspect-ratio:1;border-radius:8px;overflow:hidden;border:2px solid #e5e7eb">' +
                            '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover" alt="Podgląd">' +
                            '</div>';
                          imagesPreview.innerHTML += imgHtml;
                        };
                      })(file);

                      reader.readAsDataURL(file);
                    }
                  } else {
                    imagesPreview.style.display = 'none';
                  }
                });
              }

              // Toggle category field based on type selection
              var typeSelect = qs('#add-type-select', modalAdd);
              var categoryField = qs('#add-category-field', modalAdd);
              var categorySelect = qs('#add-category-select', modalAdd);

              if (typeSelect && categoryField && categorySelect) {
                // Function to toggle category field visibility
                function toggleCategoryField() {
                  if (typeSelect.value === 'zgloszenie') {
                    categoryField.style.display = 'block';
                    categorySelect.setAttribute('required', 'required');
                  } else {
                    categoryField.style.display = 'none';
                    categorySelect.removeAttribute('required');
                    categorySelect.value = '';
                  }
                }

                // Initial toggle on page load (default is zgloszenie)
                toggleCategoryField();

                // Listen for changes
                typeSelect.addEventListener('change', toggleCategoryField);
              }

              // AUTOMATIC REVERSE GEOCODING - display address automatically
              var addressInput = qs('#add-address-input', modalAdd);
              var addressDisplay = qs('#add-address-display', modalAdd);

              if (addressDisplay && addressInput) {

                var formData = new FormData();
                formData.append('action', 'jg_reverse_geocode');
                formData.append('lat', lat);
                formData.append('lng', lng);

                fetch(CFG.ajax, {
                  method: 'POST',
                  body: formData,
                  credentials: 'same-origin'
                })
                .then(function(r) { return r.json(); })
                .then(function(response) {

                  if (response.success && response.data && response.data.display_name) {
                    var data = response.data;
                    var addr = data.address || {};
                    var street = addr.road || '';
                    var houseNumber = addr.house_number || '';
                    var city = addr.city || addr.town || addr.village || 'Jelenia Góra';

                    var fullAddress = '';
                    if (street && houseNumber) {
                      fullAddress = street + ' ' + houseNumber + ', ' + city;
                    } else if (street) {
                      fullAddress = street + ', ' + city;
                    } else {
                      fullAddress = city;
                    }

                    addressInput.value = fullAddress;
                    addressDisplay.innerHTML = '<strong>📍 Adres:</strong> ' + esc(fullAddress);
                  } else {
                    debugWarn('[JG MAP] No address found in response');
                    addressDisplay.innerHTML = '<strong>📍 Adres:</strong> Nie znaleziono adresu dla tej lokalizacji';
                    addressInput.value = '';
                  }
                })
                .catch(function(err) {
                  debugError('[JG MAP] Reverse geocoding error:', err);
                  addressDisplay.innerHTML = '<strong>📍 Adres:</strong> Błąd pobierania adresu';
                  addressInput.value = '';
                });
              }

          form.onsubmit = function(e) {
            e.preventDefault();
            msg.textContent = 'Wysyłanie...';

            var fd = new FormData(form);
            fd.append('action', 'jg_submit_point');
            fd.append('_ajax_nonce', CFG.nonce);

            // DEBUG: Log FormData contents (more compatible approach)
            var formDataObj = {};
            fd.forEach(function(value, key) {
              formDataObj[key] = value;
            });

            fetch(CFG.ajax, {
              method: 'POST',
              body: fd,
              credentials: 'same-origin'
            })
            .then(function(r) {
              return r.text();
            })
            .then(function(t) {
              var j = null;
              try {
                j = JSON.parse(t);
              } catch (_) {}

              if (!j || j.success === false) {
                // Handle duplicate point error specially
                if (j && j.data && j.data.duplicate_point_id) {
                  var duplicatePointId = j.data.duplicate_point_id;
                  msg.innerHTML = (j.data.message || 'Błąd') + ' <br><button style="margin-top:8px;padding:6px 12px;background:#8d2324;color:#fff;border:none;border-radius:4px;cursor:pointer" onclick="' +
                    'document.getElementById(\'jg-map-modal-add\').style.display=\'none\';' +
                    'window.location.hash=\'#point-' + duplicatePointId + '\';' +
                    '">Zobacz istniejące zgłoszenie</button>';
                  msg.style.color = '#b91c1c';
                  return;
                }
                throw new Error((j && j.data && j.data.message) || 'Błąd');
              }

              var submitTime = Date.now();
              lastSubmitTime = submitTime;
              setLastSubmitTime(submitTime);

              msg.textContent = 'Wysłano do moderacji! Odświeżanie...';
              msg.style.color = '#15803d';
              form.reset();

              // Immediate refresh for better UX
              refreshAll().then(function() {
                msg.textContent = 'Wysłano do moderacji! Miejsce pojawi się po zaakceptowaniu.';

                // Show special info modal for reports
                if (j.data && j.data.show_report_info_modal && j.data.case_id) {
                  setTimeout(function() {
                    close(modalAdd);

                    var modalMessage = 'Twoje zgłoszenie zostało przyjęte i otrzymało unikalny numer sprawy: <strong>' + j.data.case_id + '</strong>.\n\n' +
                      'Teraz zostanie poddane weryfikacji przez nasz zespół. Po weryfikacji, jeśli zgłoszenie spełni nasze wytyczne, zostanie ono przekazane do właściwej instytucji (np. Straż Miejska, Urząd Miasta, administratorzy osiedli).\n\n' +
                      'Monitorujemy status każdego zgłoszenia i aktualizujemy jego statusy na mapie. Możesz śledzić postępy rozwiązywania problemu, wchodząc na mapę i klikając na pineskę Twojego zgłoszenia.\n\n' +
                      '<strong>Ważne:</strong> Portal nie daje gwarancji rozwiązania problemu, gdyż nie jest z definicji instytucją pośredniczącą, a jedynie organizacją, która stara się naświetlać istnienie nieprawidłowości w przestrzeni publicznej miasta Jelenia Góra oraz jej okolic.';

                    showAlert(modalMessage.replace(/\n\n/g, '<br><br>'));
                  }, 800);
                } else {
                  setTimeout(function() {
                    close(modalAdd);
                  }, 800);
                }
              }).catch(function(err) {
                debugError('[JG MAP] Błąd odświeżania:', err);
                setTimeout(function() {
                  close(modalAdd);
                }, 1000);
              });
            })
            .catch(function(err) {
              msg.textContent = err.message || 'Błąd';
              msg.style.color = '#b91c1c';
            });
          };
        })
        .catch(function(err) {
          showAlert('Błąd pobierania limitów: ' + (err.message || 'Nieznany błąd'));
        });
        }, 200);
      });

      function iconFor(p) {
        var sponsored = !!p.sponsored;
        var isPending = !!p.is_pending;
        var isEdit = !!p.is_edit;
        var isDeletionRequested = !!p.is_deletion_requested;
        // Show reports counter for admins OR for the place owner
        var isOwner = (CFG.currentUserId > 0 && p.author_id === CFG.currentUserId);
        var hasReports = ((CFG.isAdmin || isOwner) && p.reports_count > 0);
        // Check if current user has reported this point (but is not admin and not owner)
        var userHasReported = (!!p.user_has_reported && !CFG.isAdmin && !isOwner);

        // For place owner with pending place: show "pending" state, NOT reports counter
        // (even if there are reports, pending is more important for owner)
        var showPendingForOwner = (isOwner && isPending && !CFG.isAdmin);
        if (showPendingForOwner) {
          hasReports = false; // Don't show reports counter for owner's pending place
        }

        // Pin sizes in rem for better scaling - converted to px based on root font-size
        // Get root font size for rem to px conversion
        var rootFontSize = parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;

        // Define sizes in rem - sponsored pins are bigger
        var pinHeightRem = sponsored ? 5.2 : 3.5; // Sponsored: 5.2rem (bigger), Regular: 3.5rem
        var pinWidthRem = sponsored ? 3.5 : 2.5;  // Sponsored: 3.5rem (bigger), Regular: 2.5rem

        // Convert to pixels for actual rendering
        var pinHeight = pinHeightRem * rootFontSize;
        var pinWidth = pinWidthRem * rootFontSize;

        // Anchor at the bottom tip of the pin (where it points to the location)
        var anchor = [pinWidth / 2, pinHeight];

        // Determine gradient colors and circle color based on type and state
        // PRIORITY ORDER (highest to lowest):
        // 1. userHasReported (user zgłosił cudze miejsce) - yellow
        // 2. hasReports (miejsce ma zgłoszenia) - orange/red badge on existing color
        // 3. isEdit (pending edit) - purple
        // 4. isDeletionRequested (pending deletion) - orange
        // 5. isPending (owner's pending place) - red
        var gradientId = 'gradient-' + (p.id || Math.random());
        var gradientStart, gradientMid, gradientEnd;
        var circleColor; // Color for the inner circle

        if (userHasReported) {
          // Yellow gradient for user-reported (user zgłosił CUDZE miejsce do moderacji)
          gradientStart = '#ca8a04';
          gradientMid = '#eab308';
          gradientEnd = '#ca8a04';
          circleColor = '#713f12'; // Dark yellow/brown
        } else if (hasReports && CFG.isAdmin) {
          // Red/orange gradient for places with reports (ONLY for admins, owners see pending state instead)
          gradientStart = '#dc2626';
          gradientMid = '#ef4444';
          gradientEnd = '#dc2626';
          circleColor = '#7f1d1d'; // Dark red
        } else if (isEdit) {
          // Purple gradient for pending edit (higher priority than deletion)
          gradientStart = '#9333ea';
          gradientMid = '#a855f7';
          gradientEnd = '#9333ea';
          circleColor = '#581c87'; // Dark purple
        } else if (isDeletionRequested) {
          // Orange gradient for deletion requested
          gradientStart = '#ea580c';
          gradientMid = '#f97316';
          gradientEnd = '#ea580c';
          circleColor = '#7c2d12'; // Dark orange
        } else if (isPending) {
          // Red gradient for pending (owner's own pending place OR admin viewing pending)
          gradientStart = '#dc2626';
          gradientMid = '#ef4444';
          gradientEnd = '#dc2626';
          circleColor = '#7f1d1d'; // Dark red
        } else if (sponsored) {
          // Light matte gold for sponsored - brighter color
          gradientStart = '#f4d03f';
          gradientMid = '#f4d03f';
          gradientEnd = '#f4d03f';
          circleColor = '#fef3c7'; // Very light gold for circle if no image
        } else if (p.type === 'ciekawostka') {
          // Blue gradient for curiosities
          gradientStart = '#1e40af';
          gradientMid = '#3b82f6';
          gradientEnd = '#1e40af';
          circleColor = '#1e3a8a'; // Dark blue
        } else if (p.type === 'miejsce') {
          // Green gradient for places
          gradientStart = '#15803d';
          gradientMid = '#22c55e';
          gradientEnd = '#15803d';
          circleColor = '#0a5a28'; // Dark green
        } else {
          // Black gradient for reports
          gradientStart = '#000';
          gradientMid = '#1f1f1f';
          gradientEnd = '#000';
          // Don't show circle if report has category (will show emoji instead)
          circleColor = (p.category) ? null : '#888'; // Light gray (visible on black) only if no category
        }

        // Build SVG pin shape with gradients and soft shadow
        var svgPin = '<svg width="' + pinWidth + '" height="' + pinHeight + '" viewBox="0 0 32 40" xmlns="http://www.w3.org/2000/svg">';

        // Define gradient, pattern, and shadow filter
        svgPin += '<defs>' +
          '<linearGradient id="' + gradientId + '" x1="0%" y1="0%" x2="100%" y2="100%">' +
          '<stop offset="0%" style="stop-color:' + gradientStart + ';stop-opacity:1" />' +
          '<stop offset="50%" style="stop-color:' + gradientMid + ';stop-opacity:1" />' +
          '<stop offset="100%" style="stop-color:' + gradientEnd + ';stop-opacity:1" />' +
          '</linearGradient>';

        // Add fine diagonal stripe pattern for sponsored pins
        if (sponsored) {
          svgPin += '<pattern id="stripe-' + gradientId + '" patternUnits="userSpaceOnUse" width="4" height="4" patternTransform="rotate(45)">' +
            '<rect width="1.5" height="4" fill="rgba(0,0,0,0.05)"/>' +
            '</pattern>';
        }

        svgPin += '<filter id="soft-shadow-' + gradientId + '" x="-50%" y="-50%" width="200%" height="200%">' +
          '<feGaussianBlur in="SourceAlpha" stdDeviation="3"/>' +
          '<feOffset dx="0" dy="3" result="offsetblur"/>' +
          '<feComponentTransfer>' +
          '<feFuncA type="linear" slope="0.4"/>' +
          '</feComponentTransfer>' +
          '<feMerge>' +
          '<feMergeNode/>' +
          '<feMergeNode in="SourceGraphic"/>' +
          '</feMerge>' +
          '</filter>' +
          '</defs>';

        // Pin shape: rounded Google Maps style with smooth curves
        svgPin += '<path d="M16 0 C7.163 0 0 7.163 0 16 C0 19 1 22 4 26 L16 40 L28 26 C31 22 32 19 32 16 C32 7.163 24.837 0 16 0 Z" ' +
          'fill="url(#' + gradientId + ')" ' +
          'filter="url(#soft-shadow-' + gradientId + ')"/>';

        // Add diagonal stripe overlay for sponsored pins
        if (sponsored) {
          svgPin += '<path d="M16 0 C7.163 0 0 7.163 0 16 C0 19 1 22 4 26 L16 40 L28 26 C31 22 32 19 32 16 C32 7.163 24.837 0 16 0 Z" ' +
            'fill="url(#stripe-' + gradientId + ')" opacity="1"/>';
        }

        // Add inner circle (Google Maps style) - only for non-sponsored
        if (circleColor) {
          svgPin += '<circle cx="16" cy="16" r="5.5" fill="' + circleColor + '"/>';
        }

        svgPin += '</svg>';

        // Reports counter
        var reportsHtml = '';
        if (hasReports) {
          reportsHtml = '<span class="jg-reports-counter">' + p.reports_count + '</span>';
        }

        // Deletion request indicator
        var deletionHtml = '';
        if (p.is_deletion_requested) {
          deletionHtml = '<span class="jg-deletion-badge">✕</span>';
        }

        // Label for pin - apply states in priority order
        var labelClass = 'jg-marker-label';
        if (sponsored) labelClass += ' jg-marker-label--promo';
        if (userHasReported) labelClass += ' jg-marker-label--reported';
        else if (hasReports && CFG.isAdmin) labelClass += ' jg-marker-label--reported';
        else if (isEdit) labelClass += ' jg-marker-label--edit';
        else if (isDeletionRequested) labelClass += ' jg-marker-label--deletion';
        else if (isPending) labelClass += ' jg-marker-label--pending';

        // Suffix text - priority order (highest priority first)
        var suffix = '';
        if (userHasReported) {
          suffix = ' (zgłoszone do moderacji)';
        } else if (hasReports && CFG.isAdmin) {
          suffix = ' (' + p.reports_count + ' zgł.)';
        } else if (isEdit) {
          suffix = ' (edycja)';
        } else if (isDeletionRequested) {
          suffix = ' (do usunięcia)';
        } else if (isPending) {
          // For owner's pending place: special message
          if (showPendingForOwner) {
            suffix = ' (zgłoszone do moderacji)';
          } else {
            suffix = ' (oczekuje)';
          }
        }

        var labelHtml = '<span class="' + labelClass + '">' + esc(p.title || 'Bez nazwy') + suffix + '</span>';

        // Category emoji mapping for reports (dynamic from config)
        var categoryEmojis = getCategoryEmojis();

        // Image or light gold circle for sponsored pins, warning emoji for user-reported, category emoji for reports, or nothing for others
        var centerContent = '';

        if (sponsored) {
          var circleSize = 42; // Bigger for larger sponsored pins
          var featuredIndex = p.featured_image_index || 0;
          var featuredImage = (p.images && p.images.length > 0) ? p.images[featuredIndex] : null;
          var imageUrl = null;

          // Extract image URL from image object or string
          if (featuredImage) {
            if (typeof featuredImage === 'object') {
              imageUrl = featuredImage.thumb || featuredImage.full;
            } else {
              imageUrl = featuredImage;
            }
          }

          var circleStyle = 'position:absolute;' +
            'top:' + (pinHeight * 0.40) + 'px;' +
            'left:50%;' +
            'transform:translate(-50%,-50%);' +
            'width:' + circleSize + 'px;' +
            'height:' + circleSize + 'px;' +
            'border-radius:50%;' +
            'box-shadow:0 2px 4px rgba(0,0,0,0.3);' +
            'z-index:2;' +
            'overflow:hidden;';

          if (imageUrl) {
            // Show first image in circle
            centerContent = '<div style="' + circleStyle + 'background:#f0e68c;">' +
              '<img src="' + esc(imageUrl) + '" style="width:100%;height:100%;object-fit:cover;" alt="">' +
              '</div>';
          } else {
            // Show light gold circle if no image
            centerContent = '<div style="' + circleStyle + 'background:#f0e68c;"></div>';
          }
        } else if (userHasReported) {
          // Show warning emoji for user-reported places
          var emojiFontSize = 24;
          var emojiStyle = 'position:absolute;' +
            'top:' + (pinHeight * 0.40) + 'px;' +
            'left:50%;' +
            'transform:translate(-50%,-50%);' +
            'font-size:' + emojiFontSize + 'px;' +
            'background:white;' +
            'border-radius:50%;' +
            'width:32px;' +
            'height:32px;' +
            'display:flex;' +
            'align-items:center;' +
            'justify-content:center;' +
            'box-shadow:0 2px 4px rgba(0,0,0,0.3);' +
            'z-index:2;';
          centerContent = '<div class="jg-pin-emoji" style="' + emojiStyle + '">⚠️</div>';
        } else if (p.type === 'zgloszenie' && p.category && categoryEmojis[p.category]) {
          // Show category emoji for reports with white background
          var emojiFontSize = 20;
          var emojiStyle = 'position:absolute;' +
            'top:' + (pinHeight * 0.40) + 'px;' +
            'left:50%;' +
            'transform:translate(-50%,-50%);' +
            'font-size:' + emojiFontSize + 'px;' +
            'background:white;' +
            'border-radius:50%;' +
            'width:28px;' +
            'height:28px;' +
            'display:flex;' +
            'align-items:center;' +
            'justify-content:center;' +
            'box-shadow:0 2px 4px rgba(0,0,0,0.3);' +
            'z-index:2;';
          centerContent = '<div class="jg-pin-emoji" style="' + emojiStyle + '">' + categoryEmojis[p.category] + '</div>';
        }

        var iconHtml = '<div class="jg-pin-svg-wrapper" style="position:relative;width:' + pinWidth + 'px;height:' + pinHeight + 'px;">' +
          svgPin + centerContent + reportsHtml + deletionHtml + labelHtml +
          '</div>';

        var className = 'jg-pin-marker';
        if (sponsored) className += ' jg-pin-marker--promo';

        return L.divIcon({
          className: className,
          html: iconHtml,
          iconSize: [pinWidth, pinHeight],
          iconAnchor: anchor,
          popupAnchor: [0, -pinHeight + 5]
        });
      }

      function api(action, body) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('_ajax_nonce', CFG.nonce || '');
        if (body) {
          for (var k in body) {
            fd.append(k, body[k]);
          }
        }


        return fetch(CFG.ajax, {
          method: 'POST',
          body: fd,
          credentials: 'same-origin'
        })
        .then(function(r) {
          return r.text();
        })
        .then(function(t) {
          var j = null;
          try {
            j = JSON.parse(t);
          } catch (e) {
            debugError('[JG MAP] JSON parse error:', e);
            debugError('[JG MAP] Raw response:', t);
          }
          if (!j || j.success === false) {
            var errMsg = (j && j.data && (j.data.message || j.data.error)) || 'Błąd';
            debugError('[JG MAP] API error:', errMsg, j);
            throw new Error(errMsg);
          }
          // Process response data
          if (action === 'jg_points' && j.data) {
            // Data received successfully
          }
          return j.data;
        });
      }

      var fetchPoints = function() {
        return api('jg_points', {});
      };

      var fetchAuthorPoints = function(a) {
        return api('jg_author_points', { author_id: a });
      };

      var reportPoint = function(d) {
        return api('jg_report_point', d);
      };

      var getReports = function(id) {
        return api('jg_get_reports', { post_id: id });
      };

      var handleReports = function(d) {
        return api('jg_handle_reports', d);
      };

      var voteReq = function(d) {
        return api('jg_vote', d);
      };

      var updatePoint = function(d) {
        return api('jg_update_point', d);
      };

      var adminTogglePromo = function(d) {
        return api('jg_admin_toggle_promo', d);
      };

      var adminToggleAuthor = function(d) {
        return api('jg_admin_toggle_author', d);
      };

      var adminUpdateNote = function(d) {
        return api('jg_admin_update_note', d);
      };

      var adminChangeStatus = function(d) {
        return api('jg_admin_change_status', d);
      };

      var adminApprovePoint = function(d) {
        return api('jg_admin_approve_point', d);
      };

      var adminRejectPoint = function(d) {
        return api('jg_admin_reject_point', d);
      };

      var adminDeletePoint = function(d) {
        return api('jg_admin_delete_point', d);
      };

      // Check user restrictions and display banner
      function checkUserRestrictions() {
        if (!CFG.isLoggedIn) {
          return; // Don't check for guests
        }

        api('jg_get_my_restrictions', {})
          .then(function(result) {
            if (!result.is_banned && (!result.restrictions || result.restrictions.length === 0)) {
              return; // No restrictions, nothing to display
            }

            var bannerHtml = '<div id="jg-ban-banner" style="position:fixed;top:0;left:0;right:0;z-index:10000;background:#dc2626;color:#fff;padding:16px;box-shadow:0 4px 6px rgba(0,0,0,0.1);font-family:sans-serif;">';
            bannerHtml += '<div style="max-width:1200px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">';
            bannerHtml += '<div style="flex:1;min-width:300px;">';
            bannerHtml += '<div style="font-size:18px;font-weight:700;margin-bottom:8px;">⚠️ ';

            if (result.is_banned) {
              if (result.ban_status === 'permanent') {
                bannerHtml += 'Twoje konto zostało zbanowane permanentnie';
              } else if (result.ban_status === 'temporary' && result.ban_until) {
                var banDate = new Date(result.ban_until);
                var banDateStr = banDate.toLocaleDateString('pl-PL', {
                  year: 'numeric',
                  month: 'long',
                  day: 'numeric',
                  hour: '2-digit',
                  minute: '2-digit'
                });
                bannerHtml += 'Twoje konto zostało zbanowane do ' + banDateStr;
              } else {
                bannerHtml += 'Twoje konto zostało zbanowane';
              }
              bannerHtml += '</div>';
              bannerHtml += '<div style="font-size:14px;opacity:0.95;">W czasie banu nie możesz wykonywać żadnych akcji na mapie.</div>';
            } else if (result.restrictions && result.restrictions.length > 0) {
              bannerHtml += 'Twoje konto ma aktywne blokady</div>';
              bannerHtml += '<div style="font-size:14px;opacity:0.95;">Zablokowane akcje: ';

              var labels = {
                'voting': 'głosowanie',
                'add_places': 'dodawanie miejsc',
                'add_events': 'dodawanie wydarzeń',
                'add_trivia': 'dodawanie ciekawostek',
                'edit_places': 'edycja miejsc'
              };

              var restrictionLabels = result.restrictions.map(function(r) {
                return labels[r] || r;
              });

              bannerHtml += '<strong>' + restrictionLabels.join(', ') + '</strong>';
              bannerHtml += '</div>';
            }

            bannerHtml += '</div>';
            bannerHtml += '<a href="mailto:odwolania@jeleniogorzanietomy.pl?subject=Odwołanie od decyzji moderacyjnej" style="background:rgba(255,255,255,0.2);color:#fff;border:2px solid #fff;border-radius:6px;padding:8px 16px;cursor:pointer;font-weight:700;font-size:14px;white-space:nowrap;text-decoration:none;display:inline-block;">📧 Odwołaj się</a>';
            bannerHtml += '</div>';
            bannerHtml += '</div>';

            var existingBanner = document.getElementById('jg-ban-banner');
            if (existingBanner) {
              existingBanner.remove();
            }

            var bannerEl = document.createElement('div');
            bannerEl.innerHTML = bannerHtml;
            document.body.insertBefore(bannerEl.firstChild, document.body.firstChild);

            // Store restrictions globally so we can check them before actions
            window.JG_USER_RESTRICTIONS = result;
          })
          .catch(function(e) {
            debugError('[JG MAP] Failed to check restrictions:', e);
          });
      }

      // Check if URL contains ?point_id= or #point-123 and open that point
      function checkDeepLink() {
        try {
          var urlParams = new URLSearchParams(window.location.search);

          // Check for jg_view_reports parameter (from dashboard Reports) - HIGHEST PRIORITY
          var viewReportsId = urlParams.get('jg_view_reports');
          if (viewReportsId && CFG.isAdmin && ALL && ALL.length > 0) {
            var targetPoint = ALL.find(function(p) { return p.id === parseInt(viewReportsId); });
            if (targetPoint) {
              // Zoom to max zoom level (19) to show point clearly
              setTimeout(function() {
                map.setView([targetPoint.lat, targetPoint.lng], 19, { animate: true });
                // Wait for zoom, then open modal
                setTimeout(function() {
                  openDetails(targetPoint);
                  // Remove parameter from URL
                  var newUrl = window.location.pathname + window.location.search.replace(/[?&]jg_view_reports=\d+/, '').replace(/^\&/, '?');
                  if (newUrl.endsWith('?')) newUrl = newUrl.slice(0, -1);
                  window.history.replaceState({}, '', newUrl);
                }, 800);
              }, 100);
              return; // Exit early
            }
          }

          // Check for jg_view_point parameter (from dashboard Gallery)
          var viewPointId = urlParams.get('jg_view_point');
          if (viewPointId && ALL && ALL.length > 0) {
            var targetPoint = ALL.find(function(p) { return p.id === parseInt(viewPointId); });
            if (targetPoint) {

              // Map is already scrolled to by earlyScrollCheck()
              // Wait for map to be ready, then zoom to point with maximum zoom
              setTimeout(function() {
                map.setView([targetPoint.lat, targetPoint.lng], 18, { animate: true });
                // Wait for zoom, then open modal
                setTimeout(function() {
                  openDetails(targetPoint);
                  // Remove parameter from URL
                  var newUrl = window.location.pathname + window.location.search.replace(/[?&]jg_view_point=\d+/, '').replace(/^\&/, '?');
                  if (newUrl.endsWith('?')) newUrl = newUrl.slice(0, -1);
                  window.history.replaceState({}, '', newUrl);
                }, 800);
              }, 300);
              return; // Exit early
            }
          }

          var pointId = null;

          // Check query parameter ?point_id=123
          pointId = urlParams.get('point_id');

          // Check hash #point-123
          if (!pointId && window.location.hash) {
            var hashMatch = window.location.hash.match(/^#point-(\d+)$/);
            if (hashMatch) {
              pointId = hashMatch[1];
            }
          }

          if (pointId && ALL && ALL.length > 0) {

            // Find the point with this ID
            var point = ALL.find(function(p) {
              return p.id.toString() === pointId.toString();
            });

            if (point) {

              // STEP 1: Map is already scrolled to by earlyScrollCheck()
              // STEP 2: Wait for map to be ready, then zoom
              setTimeout(function() {
                // Zoom to point with maximum zoom level
                map.setView([point.lat, point.lng], 19, { animate: true });

                // STEP 3: Wait for zoom animation, then show pulsing marker
                setTimeout(function() {
                  // Add pulsing red circle around the point
                  addPulsingMarker(point.lat, point.lng, function() {

                    // STEP 4: Open modal immediately after pulsing animation
                    // Open modal after animation - use openDetails, not viewPoint!
                    openDetails(point);

                    // Clean URL (remove point_id parameter or hash) after modal opens
                    if (history.replaceState) {
                      var cleanUrl = window.location.origin + window.location.pathname;
                      history.replaceState(null, '', cleanUrl);
                    }
                  });
                }, 800); // Wait for zoom animation
              }, 300); // Map is already loaded and scrolled, just wait for initialization
            } else {
              debugWarn('[JG MAP] Point not found with id:', pointId);
            }
          }
        } catch (e) {
          debugError('[JG MAP] Deep link error:', e);
        }
      }

      // Add pulsing red circle marker for deep-linked points
      // Callback is called after animation completes (2 seconds)
      function addPulsingMarker(lat, lng, callback) {
        // Create pulsing red circle (small, just around the marker)
        var pulsingCircle = L.circle([lat, lng], {
          color: '#ef4444',
          fillColor: '#ef4444',
          fillOpacity: 0.3,
          radius: 12, // 12 meters - just slightly bigger than the marker
          weight: 3
        }).addTo(map);

        // Animate the circle (pulse effect)
        var pulseCount = 0;
        var maxPulses = 4; // 4 pulses over 2 seconds
        var pulseInterval = setInterval(function() {
          pulseCount++;

          // Toggle opacity for pulse effect
          if (pulseCount % 2 === 0) {
            pulsingCircle.setStyle({ fillOpacity: 0.6, weight: 4 });
          } else {
            pulsingCircle.setStyle({ fillOpacity: 0.2, weight: 2 });
          }

          // Remove after 2 seconds and call callback
          if (pulseCount >= maxPulses) {
            clearInterval(pulseInterval);
            setTimeout(function() {
              map.removeLayer(pulsingCircle);

              // Call callback after circle is removed
              if (callback && typeof callback === 'function') {
                callback();
              }
            }, 100);
          }
        }, 500); // Pulse every 500ms
      }

      var ALL = [];
      var dataLoaded = false; // Track if data has been loaded (even if empty)
      var lastModified = 0;
      // v6: Added user_id to cache to prevent showing admin data to guests
      var CACHE_KEY = 'jg_map_cache_v6';
      var CACHE_VERSION_KEY = 'jg_map_cache_version_v6';
      var CACHE_USER_KEY = 'jg_map_cache_user_v6';

      // Try to load from cache
      function loadFromCache() {
        try {
          var cached = localStorage.getItem(CACHE_KEY);
          var cachedVersion = localStorage.getItem(CACHE_VERSION_KEY);
          var cachedUserId = localStorage.getItem(CACHE_USER_KEY);

          // CRITICAL: Only use cache if user_id matches current session
          // This prevents showing admin/owner data to guests after logout
          var currentUserId = (+CFG.currentUserId || 0).toString();

          if (cached && cachedVersion && cachedUserId === currentUserId) {
            var parsedVersion = parseInt(cachedVersion);

            // FIX: Detect and clear old cache with millisecond timestamps
            // Timestamps in seconds should be < 10 billion (year ~2286)
            // Timestamps in milliseconds would be > 1 trillion
            if (parsedVersion > 10000000000) {
              // This is likely a millisecond timestamp from old code - clear it
              debugLog('[JG MAP] Clearing cache with invalid timestamp (ms instead of s)');
              localStorage.removeItem(CACHE_KEY);
              localStorage.removeItem(CACHE_VERSION_KEY);
              localStorage.removeItem(CACHE_USER_KEY);
              return null;
            }

            var data = JSON.parse(cached);
            lastModified = parsedVersion;
            return data;
          } else if (cachedUserId !== currentUserId) {
            // User changed - clear old cache
            localStorage.removeItem(CACHE_KEY);
            localStorage.removeItem(CACHE_VERSION_KEY);
            localStorage.removeItem(CACHE_USER_KEY);
          }
        } catch (e) {
          debugError('[JG MAP] Cache load error:', e);
        }
        return null;
      }

      // Save to cache
      function saveToCache(data, version) {
        try {
          var currentUserId = (+CFG.currentUserId || 0).toString();
          localStorage.setItem(CACHE_KEY, JSON.stringify(data));
          localStorage.setItem(CACHE_VERSION_KEY, version.toString());
          localStorage.setItem(CACHE_USER_KEY, currentUserId);
          lastModified = version;
        } catch (e) {
          debugError('[JG MAP] Cache save error:', e);
        }
      }

      // Check if updates are available
      function checkForUpdates() {
        return api('jg_check_updates', {}).then(function(data) {
          return {
            hasUpdates: data.last_modified > lastModified,
            lastModified: data.last_modified,
            pendingCount: data.pending_count || 0
          };
        });
      }

      function refreshData(force) {

        // If not forced, check for updates first
        if (!force) {
          return checkForUpdates().then(function(updateInfo) {
            if (!updateInfo.hasUpdates) {

              // Update pending count in title for moderators
              if (CFG.isAdmin && updateInfo.pendingCount > 0) {
                document.title = '(' + updateInfo.pendingCount + ') ' + document.title.replace(/^\(\d+\)\s*/, '');
              }

              return ALL;
            }

            return fetchAndProcessPoints(updateInfo.lastModified);
          });
        }

        return fetchAndProcessPoints();
      }

      // Export refreshData as global function for use by notification system
      window.refreshData = refreshData;

      // Function to remove markers by point IDs (for real-time deletion)
      function removeMarkersById(pointIds) {
        if (!cluster || !pointIds || pointIds.length === 0) return;


        var markersToRemove = [];
        var allMarkers = cluster.getLayers();

        for (var i = 0; i < allMarkers.length; i++) {
          var marker = allMarkers[i];
          if (marker.options && marker.options.pointId && pointIds.indexOf(marker.options.pointId) !== -1) {
            markersToRemove.push(marker);
          }
        }

        if (markersToRemove.length > 0) {
          cluster.removeLayers(markersToRemove);
        }
      }

      // Export removeMarkersById as global function for use by Heartbeat
      window.removeMarkersById = removeMarkersById;

      // Helper function to refresh both map and notifications
      function refreshAll() {

        // First refresh map data to get latest points
        return refreshData(true).then(function() {

          // Then refresh notifications if function exists (for admins/moderators)
          if (typeof window.jgRefreshNotifications === 'function') {
            return window.jgRefreshNotifications().then(function() {
            }).catch(function(err) {
              debugError('[JG MAP] Failed to refresh notifications:', err);
            });
          }

          return Promise.resolve();
        });
      }

      function fetchAndProcessPoints(version) {
        return fetchPoints().then(function(data) {

          ALL = (data || []).map(function(r) {
            return {
              id: r.id,
              title: r.title || '',
              slug: r.slug || '',
              excerpt: r.excerpt || '',
              content: r.content || '',
              lat: +r.lat,
              lng: +r.lng,
              address: r.address || '',
              type: r.type || 'zgloszenie',
              category: r.category || null,
              sponsored: !!r.sponsored,
              sponsored_until: r.sponsored_until || null,
              website: r.website || null,
              phone: r.phone || null,
              cta_enabled: !!r.cta_enabled,
              cta_type: r.cta_type || null,
              status: r.status || '',
              status_label: r.status_label || '',
              report_status: r.report_status || '',
              report_status_label: r.report_status_label || '',
              author_id: +(r.author_id || 0),
              author_name: (r.author_name || ''),
              author_hidden: !!r.author_hidden,
              images: (r.images || []),
              featured_image_index: +(r.featured_image_index || 0),
              votes: +(r.votes || 0),
              my_vote: (r.my_vote || ''),
              date: r.date || null,
              admin: r.admin || null,
              admin_note: r.admin_note || '',
              is_pending: !!r.is_pending,
              is_edit: !!r.is_edit,
              edit_info: r.edit_info || null,
              is_deletion_requested: !!r.is_deletion_requested,
              deletion_info: r.deletion_info || null,
              reports_count: +(r.reports_count || 0),
              user_has_reported: !!r.user_has_reported,
              reporter_info: r.reporter_info || null,
              case_id: r.case_id || null,
              resolved_delete_at: r.resolved_delete_at || null,
              resolved_summary: r.resolved_summary || null,
              rejected_reason: r.rejected_reason || null,
              rejected_delete_at: r.rejected_delete_at || null,
              stats: r.stats || null,  // FIX: Include stats from server (for admin/owner only)
              facebook_url: r.facebook_url || null,
              instagram_url: r.instagram_url || null,
              linkedin_url: r.linkedin_url || null,
              tiktok_url: r.tiktok_url || null,
              is_own_place: !!r.is_own_place,
              edit_locked: !!r.edit_locked
            };
          });

          // Always save to cache with current timestamp (in seconds to match server)
          var cacheVersion = version || Math.floor(Date.now() / 1000);
          saveToCache(ALL, cacheVersion);

          dataLoaded = true; // Mark data as loaded
          apply(true); // Skip fitBounds on refresh to preserve user's view

          // Deep link handling (jg_view_point, jg_view_reports, point_id, #point-123)
          // is now done in checkDeepLink() which is called after data is loaded
          // This ensures it works for both cached and fresh data

          return ALL;
        });
      }

      var isInitialLoad = true; // Track if this is the first load

      function draw(list, skipFitBounds) {

        // Wait for cluster to be ready (created in map.whenReady)
        if (!clusterReady || !cluster) {
          pendingData = list;

          // FIX: If list is empty, hide loader immediately (no need to wait for cluster)
          if (!list || list.length === 0) {
            hideLoading();
            showMap();
          }

          return;
        }

        // ALWAYS clear cluster first, even if list is empty
        try {
          cluster.clearLayers();
        } catch (e) {
        }

        // If empty list, show map and return
        if (!list || list.length === 0) {
          showMap();
          hideLoading();
          checkDeepLink();
          return;
        }

        // Clear any markers that were added directly to map (not in cluster)
        if (markers.length > 0) {
          markers.forEach(function(m) {
            try {
              m.off();
              if (map.hasLayer(m)) map.removeLayer(m);
            } catch (e) {}
          });
          markers = [];
        }

        var bounds = [];
        var validPoints = 0;

        list.forEach(function(p) {
          if (!p.lat || !p.lng) return;
          var lat = parseFloat(p.lat);
          var lng = parseFloat(p.lng);
          if (isNaN(lat) || isNaN(lng)) return;
          bounds.push([lat, lng]);
          validPoints++;
        });

        if (validPoints === 0) {
          showMap();
          return;
        }


        var newMarkers = [];

        list.forEach(function(p) {
          if (!p.lat || !p.lng) return;

          try {
            var lat = parseFloat(p.lat);
            var lng = parseFloat(p.lng);

            if (isNaN(lat) || isNaN(lng)) return;

            // Create marker with type info and moderation status
            var markerOptions = {
              icon: iconFor(p),
              isPromo: !!p.sponsored,
              pointType: p.type || 'unknown',
              pointId: p.id,
              pointTitle: p.title || 'Bez nazwy',
              pointExcerpt: p.excerpt || '',
              pointStatus: p.status || 'publish',
              hasReports: (p.reports_count || 0) > 0,
              reportsCount: p.reports_count || 0,
              isDeletionRequested: !!p.is_deletion_requested,
              isEdit: !!p.is_edit
            };

            var m = L.marker([lat, lng], markerOptions);

            (function(point) {
              m.on('click', function(e) {
                if (e && e.originalEvent) e.originalEvent.stopPropagation();
                L.DomEvent.stopPropagation(e);
                openDetails(point);
              });
            })(p);

            newMarkers.push(m);
          } catch (e) {
            debugError('[JG MAP] Błąd dodawania markera:', e);
          }
        });

        // Add all markers at once to cluster (reduces animation flicker)
        if (clusterReady && cluster && newMarkers.length > 0) {
          cluster.addLayers(newMarkers);
        } else if (newMarkers.length > 0) {
          // DON'T add markers directly to map - wait for cluster to be ready
          // This prevents duplicate markers (one on map, one in cluster)
        }


        // Only fit bounds on initial load, not on refresh
        if (!skipFitBounds && isInitialLoad && bounds.length > 0) {
          setTimeout(function() {
            try {
              var leafletBounds = L.latLngBounds(bounds);
              // Show more points unclustered - use zoom 15 max
              var maxZoom = 15;

              map.fitBounds(leafletBounds, {
                padding: [50, 50],
                maxZoom: maxZoom,
                animate: false
              });

              isInitialLoad = false;
            } catch (e) {
              debugError('[JG MAP] Błąd fitBounds:', e);
            }

            // Wait for cluster animation to complete before showing map
            setTimeout(function() {
              showMap();
              hideLoading();
              // Check for deep-linked point after map is fully ready
              checkDeepLink();
            }, 600);
          }, 400);
        } else {
          // Wait for cluster animation to complete before showing map
          setTimeout(function() {
            showMap();
            hideLoading();
            // Check for deep-linked point after map is fully ready
            checkDeepLink();
          }, 600);
        }
      }

      function chip(p) {
        var h = '';
        if (p.sponsored) {
          h += '<span class="jg-promo-tag">⭐ MIEJSCE SPONSOROWANE</span>';  // Changed class name and added star emoji
        }

        if (p.type === 'zgloszenie') {
          if (p.report_status) {
            var statusClass = 'jg-status-badge--' + p.report_status;
            h += '<span class="jg-status-badge ' + statusClass + '">' + esc(p.report_status_label || p.report_status) + '</span>';
          }

          // Display case ID if available
          if (p.case_id) {
            h += '<span class="jg-case-id-badge">' + esc(p.case_id) + '</span>';
          }
        }

        return h;
      }

      function colorForVotes(n) {
        if (n > 100) return 'color:#b58900;font-weight:800';
        if (n > 0) return 'color:#15803d;font-weight:700';
        if (n < 0) return 'color:#b91c1c;font-weight:700';
        return 'color:#111';
      }

      function openLightbox(src) {
        open(lightbox, '<button class="jg-lb-close" id="lb-close">Zamknij</button><img src="' + esc(src) + '" alt="">');
        var b = qs('#lb-close', lightbox);
        if (b) b.onclick = function() {
          close(lightbox);
        };
      }

      function openAuthorModal(authorId, name) {
        open(modalAuthor, '<header><h3>Miejsca: ' + esc(name || 'Autor') + '</h3><button class="jg-close" id="ath-close">&times;</button></header><div id="ath-list">Ładowanie...</div>');
        qs('#ath-close', modalAuthor).onclick = function() {
          close(modalAuthor);
        };

        fetchAuthorPoints(authorId).then(function(items) {
          var holder = qs('#ath-list', modalAuthor);
          if (!items || !items.length) {
            holder.innerHTML = '<p>Brak miejsc.</p>';
            return;
          }
          var html = '<ul style="margin:0;padding-left:18px">';
          items.forEach(function(it) {
            html += '<li><a href="#" data-id="' + it.id + '" class="jg-author-place">' + esc(it.title || ('Punkt #' + it.id)) + '</a></li>';
          });
          html += '</ul>';
          holder.innerHTML = html;
          holder.querySelectorAll('.jg-author-place').forEach(function(a) {
            a.addEventListener('click', function(ev) {
              ev.preventDefault();
              var id = +this.getAttribute('data-id');
              var p = ALL.find(function(x) {
                return +x.id === id;
              });
              if (p) {
                close(modalAuthor);
                openDetails(p);
              }
            });
          });
        }).catch(function() {
          qs('#ath-list', modalAuthor).innerHTML = '<p>Błąd.</p>';
        });
      }

      function openUserActionsModal(userId, userName) {
        var html = '<header><h3>Akcje wobec użytkownika: ' + esc(userName) + '</h3><button class="jg-close" id="user-actions-close">&times;</button></header>' +
          '<div class="jg-grid" style="padding:16px">' +
          '<div id="user-current-status" style="margin-bottom:16px;padding:12px;background:#f5f5f5;border-radius:8px">' +
          '<strong>Pobieranie informacji...</strong>' +
          '</div>' +
          '<div style="margin-bottom:16px">' +
          '<button class="jg-btn jg-btn--ghost" id="btn-view-user-places" style="width:100%">Zobacz miejsca użytkownika</button>' +
          '</div>' +
          '<div style="background:#fee;border:2px solid #dc2626;border-radius:8px;padding:12px;margin-bottom:16px">' +
          '<div style="font-weight:700;margin-bottom:12px;color:#dc2626">⚠️ Akcje moderacyjne</div>' +
          '<div style="display:grid;gap:8px">' +
          '<button class="jg-btn jg-btn--danger" id="btn-ban-permanent">Ban permanentny</button>' +
          '<button class="jg-btn jg-btn--danger" id="btn-ban-temporary">Ban czasowy</button>' +
          '<button class="jg-btn" id="btn-unban" style="display:none;background:#10b981;color:#fff">Usuń ban</button>' +
          '<button class="jg-btn jg-btn--ghost" id="btn-ban-voting">Blokada głosowania</button>' +
          '<button class="jg-btn jg-btn--ghost" id="btn-ban-add-places">Blokada dodawania miejsc</button>' +
          '<button class="jg-btn jg-btn--ghost" id="btn-ban-add-events">Blokada dodawania wydarzeń</button>' +
          '<button class="jg-btn jg-btn--ghost" id="btn-ban-add-trivia">Blokada dodawania ciekawostek</button>' +
          '<button class="jg-btn jg-btn--ghost" id="btn-ban-edit-places">Blokada edycji własnych miejsc</button>' +
          '<button class="jg-btn jg-btn--ghost" id="btn-ban-photo-upload">Blokada przesyłania zdjęć</button>' +
          '</div>' +
          '</div>' +
          '<div style="background:#f0f9ff;border:2px solid #3b82f6;border-radius:8px;padding:12px;margin-bottom:16px">' +
          '<div style="font-weight:700;margin-bottom:8px;color:#1e40af">📊 Limity dzienne (tymczasowe)</div>' +
          '<p style="font-size:11px;color:#666;margin:4px 0 12px 0">Reset o północy</p>' +
          '<div id="user-limits-display" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">' +
          '<div style="text-align:center;background:#fff;padding:8px;border-radius:6px">' +
          '<div style="font-size:24px;font-weight:700;color:#3b82f6" id="ulimit-places">-</div>' +
          '<div style="font-size:10px;color:#666">miejsc/ciekawostek</div>' +
          '</div>' +
          '<div style="text-align:center;background:#fff;padding:8px;border-radius:6px">' +
          '<div style="font-size:24px;font-weight:700;color:#3b82f6" id="ulimit-reports">-</div>' +
          '<div style="font-size:10px;color:#666">zgłoszeń</div>' +
          '</div>' +
          '</div>' +
          '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">' +
          '<input type="number" id="ulimit-places-input" min="0" max="999" value="5" placeholder="Miejsca" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:4px;font-size:12px">' +
          '<input type="number" id="ulimit-reports-input" min="0" max="999" value="5" placeholder="Zgłoszenia" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:4px;font-size:12px">' +
          '</div>' +
          '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">' +
          '<button class="jg-btn jg-btn--ghost" id="btn-reset-limits" style="font-size:11px;padding:6px">Reset (5/5)</button>' +
          '<button class="jg-btn" id="btn-set-limits" style="font-size:11px;padding:6px;background:#3b82f6;color:#fff">Ustaw</button>' +
          '</div>' +
          '</div>' +
          '<div style="background:#f8fafc;border:2px solid #8b5cf6;border-radius:8px;padding:12px;margin-bottom:16px">' +
          '<div style="font-weight:700;margin-bottom:8px;color:#6b21a8">📸 Miesięczny limit zdjęć</div>' +
          '<p style="font-size:11px;color:#666;margin:4px 0 12px 0">Reset 1-go każdego miesiąca</p>' +
          '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">' +
          '<div style="text-align:center;background:#fff;padding:8px;border-radius:6px">' +
          '<div style="font-size:24px;font-weight:700;color:#8b5cf6" id="uphoto-used">-</div>' +
          '<div style="font-size:10px;color:#666">wykorzystano (MB)</div>' +
          '</div>' +
          '<div style="text-align:center;background:#fff;padding:8px;border-radius:6px">' +
          '<div style="font-size:24px;font-weight:700;color:#3b82f6" id="uphoto-limit">-</div>' +
          '<div style="font-size:10px;color:#666">limit (MB)</div>' +
          '</div>' +
          '</div>' +
          '<div style="margin-bottom:8px">' +
          '<input type="number" id="uphoto-limit-input" min="1" max="10000" value="100" placeholder="Limit (MB)" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:4px;font-size:12px">' +
          '</div>' +
          '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">' +
          '<button class="jg-btn jg-btn--ghost" id="btn-reset-photo-limit" style="font-size:11px;padding:6px">Reset (100MB)</button>' +
          '<button class="jg-btn" id="btn-set-photo-limit" style="font-size:11px;padding:6px;background:#8b5cf6;color:#fff">Ustaw</button>' +
          '</div>' +
          '</div>' +
          '<div id="user-actions-msg" style="font-size:12px;margin-top:12px"></div>' +
          '</div>';

        open(modalAuthor, html);

        // Fetch user restrictions
        api('jg_get_user_restrictions', { user_id: userId })
          .then(function(result) {
            var statusDiv = qs('#user-current-status', modalAuthor);
            var statusHtml = '<strong>Aktualny status:</strong><br>';

            if (result.is_banned) {
              if (result.ban_status === 'permanent') {
                statusHtml += '<span style="color:#dc2626;font-weight:700">🚫 Ban permanentny</span>';
              } else if (result.ban_status === 'temporary') {
                var banDate = result.ban_until ? new Date(result.ban_until).toLocaleDateString('pl-PL') : '?';
                statusHtml += '<span style="color:#dc2626;font-weight:700">🚫 Ban czasowy do ' + banDate + '</span>';
              }
              // Show unban button
              var unbanBtn = qs('#btn-unban', modalAuthor);
              if (unbanBtn) unbanBtn.style.display = 'block';
            } else {
              statusHtml += '<span style="color:#10b981;font-weight:700">✓ Aktywny</span>';
            }

            if (result.restrictions && result.restrictions.length > 0) {
              var labels = {
                'voting': 'głosowanie',
                'add_places': 'dodawanie miejsc',
                'add_events': 'wydarzenia',
                'add_trivia': 'ciekawostki',
                'edit_places': 'edycja miejsc',
                'photo_upload': 'przesyłanie zdjęć'
              };
              statusHtml += '<br><strong>Aktywne blokady:</strong><br>';
              result.restrictions.forEach(function(r) {
                statusHtml += '<span style="background:#f59e0b;color:#fff;padding:2px 6px;border-radius:4px;font-size:11px;margin:2px;display:inline-block">⚠️ ' + (labels[r] || r) + '</span>';
              });
            }

            statusDiv.innerHTML = statusHtml;
          })
          .catch(function(err) {
            var statusDiv = qs('#user-current-status', modalAuthor);
            statusDiv.innerHTML = '<span style="color:#b91c1c">Błąd pobierania danych</span>';
          });

        // Fetch user daily limits
        api('jg_admin_get_user_limits', { user_id: userId })
          .then(function(result) {
            qs('#ulimit-places', modalAuthor).textContent = result.places_remaining;
            qs('#ulimit-reports', modalAuthor).textContent = result.reports_remaining;
            qs('#ulimit-places-input', modalAuthor).value = result.places_remaining;
            qs('#ulimit-reports-input', modalAuthor).value = result.reports_remaining;
          })
          .catch(function(err) {
            qs('#ulimit-places', modalAuthor).textContent = '?';
            qs('#ulimit-reports', modalAuthor).textContent = '?';
          });

        // Fetch monthly photo limit
        api('jg_admin_get_user_photo_limit', { user_id: userId })
          .then(function(result) {
            qs('#uphoto-used', modalAuthor).textContent = result.used_mb;
            qs('#uphoto-limit', modalAuthor).textContent = result.limit_mb;
            qs('#uphoto-limit-input', modalAuthor).value = result.limit_mb;
          })
          .catch(function(err) {
            qs('#uphoto-used', modalAuthor).textContent = '?';
            qs('#uphoto-limit', modalAuthor).textContent = '?';
          });

        qs('#user-actions-close', modalAuthor).onclick = function() {
          close(modalAuthor);
        };

        var msg = qs('#user-actions-msg', modalAuthor);

        qs('#btn-view-user-places', modalAuthor).onclick = function() {
          close(modalAuthor);
          openAuthorModal(userId, userName);
        };

        qs('#btn-ban-permanent', modalAuthor).onclick = function() {
          var self = this;
          showConfirm('Zbanować użytkownika ' + userName + ' permanentnie?').then(function(confirmed) {
            if (!confirmed) return;
            self.disabled = true;
            msg.textContent = 'Banowanie...';

            api('jg_admin_ban_user', { user_id: userId, ban_type: 'permanent' })
              .then(function(result) {
                msg.textContent = 'Użytkownik zbanowany permanentnie!';
                msg.style.color = '#15803d';
              })
              .catch(function(err) {
                msg.textContent = 'Błąd: ' + (err.message || '?');
                msg.style.color = '#b91c1c';
                self.disabled = false;
              });
          });
        };

        qs('#btn-ban-temporary', modalAuthor).onclick = function() {
          var days = prompt('Na ile dni zbanować użytkownika ' + userName + '?', '7');
          if (days === null) return;

          var daysNum = parseInt(days);
          if (isNaN(daysNum) || daysNum < 1) {
            showAlert('Podaj poprawną liczbę dni');
            return;
          }

          this.disabled = true;
          msg.textContent = 'Banowanie...';

          api('jg_admin_ban_user', { user_id: userId, ban_type: 'temporary', ban_days: daysNum })
            .then(function(result) {
              msg.textContent = 'Użytkownik zbanowany na ' + daysNum + ' dni!';
              msg.style.color = '#15803d';
            })
            .catch(function(err) {
              msg.textContent = 'Błąd: ' + (err.message || '?');
              msg.style.color = '#b91c1c';
              this.disabled = false;
            }.bind(this));
        };

        qs('#btn-unban', modalAuthor).onclick = function() {
          var self = this;
          showConfirm('Usunąć ban dla użytkownika ' + userName + '?').then(function(confirmed) {
            if (!confirmed) return;
            self.disabled = true;
            msg.textContent = 'Usuwanie banu...';

            api('jg_admin_unban_user', { user_id: userId })
              .then(function(result) {
                msg.textContent = 'Ban usunięty!';
                msg.style.color = '#15803d';
                self.style.display = 'none';
                // Refresh status
                api('jg_get_user_restrictions', { user_id: userId })
                  .then(function(result) {
                    var statusDiv = qs('#user-current-status', modalAuthor);
                    statusDiv.innerHTML = '<strong>Aktualny status:</strong><br><span style="color:#10b981;font-weight:700">✓ Aktywny</span>';
                  });
              })
              .catch(function(err) {
                msg.textContent = 'Błąd: ' + (err.message || '?');
                msg.style.color = '#b91c1c';
                self.disabled = false;
              });
          });
        };

        var banActions = {
          'btn-ban-voting': { type: 'voting', label: 'głosowania' },
          'btn-ban-add-places': { type: 'add_places', label: 'dodawania miejsc' },
          'btn-ban-add-events': { type: 'add_events', label: 'dodawania wydarzeń' },
          'btn-ban-add-trivia': { type: 'add_trivia', label: 'dodawania ciekawostek' },
          'btn-ban-edit-places': { type: 'edit_places', label: 'edycji własnych miejsc' },
          'btn-ban-photo-upload': { type: 'photo_upload', label: 'przesyłania zdjęć' }
        };

        for (var btnId in banActions) {
          (function(id, action) {
            var btn = qs('#' + id, modalAuthor);
            if (btn) {
              btn.onclick = function() {
                var self = this;
                showConfirm('Zablokować ' + action.label + ' dla użytkownika ' + userName + '?').then(function(confirmed) {
                  if (!confirmed) return;
                  self.disabled = true;
                  msg.textContent = 'Blokowanie...';

                  api('jg_admin_toggle_user_restriction', {
                    user_id: userId,
                    restriction_type: action.type
                  })
                    .then(function(result) {
                      msg.textContent = result.message || 'Zaktualizowano!';
                      msg.style.color = '#15803d';
                      btn.textContent = result.is_restricted ? 'Odblokuj ' + action.label : 'Blokuj ' + action.label;
                      self.disabled = false;
                    })
                    .catch(function(err) {
                      msg.textContent = 'Błąd: ' + (err.message || '?');
                      msg.style.color = '#b91c1c';
                      self.disabled = false;
                    });
                });
              };
            }
          })(btnId, banActions[btnId]);
        }

        // Set custom limits
        qs('#btn-set-limits', modalAuthor).onclick = function() {
          var placesLimit = parseInt(qs('#ulimit-places-input', modalAuthor).value);
          var reportsLimit = parseInt(qs('#ulimit-reports-input', modalAuthor).value);

          if (isNaN(placesLimit) || isNaN(reportsLimit) || placesLimit < 0 || reportsLimit < 0) {
            msg.textContent = 'Nieprawidłowe wartości limitów';
            msg.style.color = '#b91c1c';
            return;
          }

          this.disabled = true;
          msg.textContent = 'Ustawianie limitów...';

          api('jg_admin_set_user_limits', {
            user_id: userId,
            places_limit: placesLimit,
            reports_limit: reportsLimit
          })
            .then(function(result) {
              qs('#ulimit-places', modalAuthor).textContent = result.places_remaining;
              qs('#ulimit-reports', modalAuthor).textContent = result.reports_remaining;
              msg.textContent = 'Limity ustawione!';
              msg.style.color = '#15803d';
              this.disabled = false;
            }.bind(this))
            .catch(function(err) {
              msg.textContent = 'Błąd: ' + (err.message || '?');
              msg.style.color = '#b91c1c';
              this.disabled = false;
            }.bind(this));
        };

        // Reset limits to default
        qs('#btn-reset-limits', modalAuthor).onclick = function() {
          var self = this;
          showConfirm('Zresetować limity do domyślnych (5/5)?').then(function(confirmed) {
            if (!confirmed) return;

            self.disabled = true;
            msg.textContent = 'Resetowanie...';

            api('jg_admin_set_user_limits', {
              user_id: userId,
              places_limit: 5,
              reports_limit: 5
            })
              .then(function(result) {
                qs('#ulimit-places', modalAuthor).textContent = '5';
                qs('#ulimit-reports', modalAuthor).textContent = '5';
                qs('#ulimit-places-input', modalAuthor).value = 5;
                qs('#ulimit-reports-input', modalAuthor).value = 5;
                msg.textContent = 'Limity zresetowane!';
                msg.style.color = '#15803d';
                self.disabled = false;
              })
              .catch(function(err) {
                msg.textContent = 'Błąd: ' + (err.message || '?');
                msg.style.color = '#b91c1c';
                self.disabled = false;
              });
          });
        };

        // Set custom photo limit
        qs('#btn-set-photo-limit', modalAuthor).onclick = function() {
          var photoLimit = parseInt(qs('#uphoto-limit-input', modalAuthor).value);

          if (isNaN(photoLimit) || photoLimit < 1) {
            msg.textContent = 'Nieprawidłowa wartość limitu zdjęć (min. 1MB)';
            msg.style.color = '#b91c1c';
            return;
          }

          this.disabled = true;
          msg.textContent = 'Ustawianie limitu zdjęć...';

          api('jg_admin_set_user_photo_limit', {
            user_id: userId,
            limit_mb: photoLimit
          })
            .then(function(result) {
              qs('#uphoto-limit', modalAuthor).textContent = result.limit_mb;
              msg.textContent = 'Limit zdjęć ustawiony!';
              msg.style.color = '#15803d';
              this.disabled = false;
            }.bind(this))
            .catch(function(err) {
              msg.textContent = 'Błąd: ' + (err.message || '?');
              msg.style.color = '#b91c1c';
              this.disabled = false;
            }.bind(this));
        };

        // Reset photo limit to default
        qs('#btn-reset-photo-limit', modalAuthor).onclick = function() {
          var self = this;
          showConfirm('Zresetować miesięczny limit zdjęć do domyślnego (100MB)?').then(function(confirmed) {
            if (!confirmed) return;

            self.disabled = true;
            msg.textContent = 'Resetowanie limitu zdjęć...';

            api('jg_admin_reset_user_photo_limit', {
              user_id: userId
            })
              .then(function(result) {
                qs('#uphoto-used', modalAuthor).textContent = result.used_mb;
                qs('#uphoto-limit', modalAuthor).textContent = result.limit_mb;
                qs('#uphoto-limit-input', modalAuthor).value = result.limit_mb;
                msg.textContent = 'Limit zdjęć zresetowany do 100MB!';
                msg.style.color = '#15803d';
                self.disabled = false;
              })
              .catch(function(err) {
                msg.textContent = 'Błąd: ' + (err.message || '?');
                msg.style.color = '#b91c1c';
                self.disabled = false;
              });
          });
        };
      }

      /**
       * Track statistics for sponsored pins
       * Excludes tracking for point owner
       */
      function trackStat(pointId, actionType, extraData, pointOwnerId) {
        if (!pointId) return;

        // Don't track owner's own interactions
        if (CFG.currentUserId && pointOwnerId && parseInt(CFG.currentUserId) === parseInt(pointOwnerId)) {
          return;
        }

        var data = {
          action: 'jg_track_stat',
          point_id: pointId,
          action_type: actionType
        };

        if (extraData) {
          for (var key in extraData) {
            data[key] = extraData[key];
          }
        }

        fetch(CFG.ajax, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams(data)
        }).catch(function() {
          // Silent fail
        });
      }

      /**
       * Check if this is a unique visitor for a given point
       * Uses localStorage to track visited points (keeps last 1000)
       */
      function isUniqueVisitor(pointId) {
        try {
          // FIX: Ensure consistent type (convert to string for comparison)
          var pointIdStr = String(pointId);
          var visited = localStorage.getItem('jg_visited_points');
          var visitedPoints = visited ? JSON.parse(visited) : [];

          // Ensure all existing IDs are strings for consistent comparison
          visitedPoints = visitedPoints.map(function(id) { return String(id); });

          if (visitedPoints.indexOf(pointIdStr) === -1) {
            visitedPoints.push(pointIdStr);
            // Keep only last 1000 to prevent overflow
            if (visitedPoints.length > 1000) {
              visitedPoints = visitedPoints.slice(-1000);
            }
            localStorage.setItem('jg_visited_points', JSON.stringify(visitedPoints));
            return true; // First visit!
          }
          return false; // Already visited
        } catch (e) {
          return false;
        }
      }

      /**
       * Render stats modal content (for real-time updates)
       */
      /**
       * Animate number change with slot machine effect
       */
      function animateNumber(element, from, to, duration) {
        if (!element || from === to) return;

        duration = duration || 800;
        var startTime = Date.now();
        var range = to - from;

        function update() {
          var now = Date.now();
          var elapsed = now - startTime;
          var progress = Math.min(elapsed / duration, 1);

          // Easing function (ease-out)
          var eased = 1 - Math.pow(1 - progress, 3);
          var current = Math.round(from + (range * eased));

          element.textContent = current;

          if (progress < 1) {
            requestAnimationFrame(update);
          } else {
            element.textContent = to; // Ensure final value is exact
          }
        }

        requestAnimationFrame(update);
      }

      function renderStatsContent(p) {
        // Initialize stats object if not present (for new sponsored places)
        if (!p.stats) {
          p.stats = {
            views: 0,
            phone_clicks: 0,
            website_clicks: 0,
            social_clicks: {},
            cta_clicks: 0,
            gallery_clicks: {},
            first_viewed: null,
            last_viewed: null,
            unique_visitors: 0,
            avg_time_spent: 0
          };
        }

        var totalSocialClicks = 0;
        var socialBreakdown = [];
        if (p.stats.social_clicks) {
          var platforms = {
            facebook: { label: 'Facebook', color: '#1877f2', emoji: 'f' },
            instagram: { label: 'Instagram', color: '#e1306c', emoji: '📷' },
            linkedin: { label: 'LinkedIn', color: '#0077b5', emoji: 'in' },
            tiktok: { label: 'TikTok', color: '#000', emoji: '🎵' }
          };

          for (var platform in platforms) {
            var clicks = parseInt(p.stats.social_clicks[platform] || 0);
            if (clicks > 0) {
              totalSocialClicks += clicks;
              socialBreakdown.push('<div style="display:flex;align-items:center;justify-content:space-between;padding:8px;background:#f9fafb;border-radius:6px;margin-bottom:6px"><div style="display:flex;align-items:center;gap:10px"><div style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;background:' + platforms[platform].color + ';color:#fff;border-radius:50%;font-size:14px">' + platforms[platform].emoji + '</div><strong>' + platforms[platform].label + '</strong></div><div style="font-size:18px;font-weight:600;color:#374151">' + clicks + '</div></div>');
            }
          }
        }

        // Gallery clicks breakdown
        var galleryBreakdown = [];
        var totalGalleryClicks = 0;
        if (p.stats.gallery_clicks && p.images && p.images.length > 0) {
          // Collect all photos with clicks
          var photosWithClicks = [];
          for (var i = 0; i < p.images.length; i++) {
            var imgClicks = parseInt(p.stats.gallery_clicks[i] || 0);
            totalGalleryClicks += imgClicks;
            if (imgClicks > 0) {
              // FIX: Handle both old format (string URL) and new format (object with thumb/full)
              var imgData = p.images[i];
              var imgThumb = '';
              if (typeof imgData === 'object') {
                imgThumb = imgData.thumb || imgData.full || imgData.url || '';
              } else {
                imgThumb = imgData; // Old format - just a string URL
              }
              photosWithClicks.push({
                index: i + 1,
                thumb: imgThumb,
                clicks: imgClicks
              });
            }
          }

          // Sort by clicks descending (highest first)
          photosWithClicks.sort(function(a, b) {
            return b.clicks - a.clicks;
          });

          // Build HTML
          for (var j = 0; j < photosWithClicks.length; j++) {
            var photo = photosWithClicks[j];
            galleryBreakdown.push('<div style="display:flex;align-items:center;justify-content:space-between;padding:8px;background:#f9fafb;border-radius:6px;margin-bottom:6px"><div style="display:flex;align-items:center;gap:10px"><img src="' + esc(photo.thumb) + '" style="width:48px;height:48px;object-fit:cover;border-radius:6px" alt="Zdjęcie #' + photo.index + '"><span>Zdjęcie #' + photo.index + '</span></div><div style="font-size:18px;font-weight:600;color:#374151">' + photo.clicks + ' <span style="font-size:14px;font-weight:400;color:#6b7280">otwarć</span></div></div>');
          }
        }

        // Format dates
        var firstViewed = p.stats.first_viewed ? new Date(p.stats.first_viewed).toLocaleDateString('pl-PL', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'Brak danych';
        var lastViewed = p.stats.last_viewed ? new Date(p.stats.last_viewed).toLocaleDateString('pl-PL', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'Brak danych';

        // Format average time spent
        var avgTimeSpent = p.stats.avg_time_spent || 0;
        var timeFormatted = avgTimeSpent > 0 ? Math.floor(avgTimeSpent / 60) + ' min ' + (avgTimeSpent % 60) + ' sek' : '0 sek';

        var modalHtml = '<header><h3>📊 Statystyki pinezki</h3><button class="jg-close" id="stats-close">&times;</button></header>' +
          '<div style="padding:20px;max-height:70vh;overflow-y:auto">' +

          // Main metrics
          '<div style="margin-bottom:24px"><h4 style="margin:0 0 16px 0;color:#374151;font-size:16px;font-weight:600">Kluczowe wskaźniki</h4>' +
          '<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(140px, 1fr));gap:12px">' +
          '<div style="padding:16px;background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);border-radius:12px;box-shadow:0 4px 12px rgba(102,126,234,0.3);color:#fff"><div style="font-size:14px;opacity:0.9;margin-bottom:4px">👁️ Wyświetlenia</div><div style="font-size:32px;font-weight:700"><span data-stat="views">' + (p.stats.views || 0) + '</span></div></div>' +
          '<div id="unique-visitors-card" style="padding:16px;background:linear-gradient(135deg, #f093fb 0%, #f5576c 100%);border-radius:12px;box-shadow:0 4px 12px rgba(240,147,251,0.3);color:#fff;cursor:pointer;transition:transform 0.2s" onmouseover="this.style.transform=\'scale(1.05)\'" onmouseout="this.style.transform=\'scale(1)\'"><div style="font-size:14px;opacity:0.9;margin-bottom:4px">👥 Unikalni</div><div style="font-size:32px;font-weight:700"><span data-stat="unique_visitors">' + (p.stats.unique_visitors || 0) + '</span></div></div>' +
          '<div style="padding:16px;background:linear-gradient(135deg, #fa709a 0%, #fee140 100%);border-radius:12px;box-shadow:0 4px 12px rgba(250,112,154,0.3);color:#fff"><div style="font-size:14px;opacity:0.9;margin-bottom:4px">⏱️ Śr. czas</div><div style="font-size:20px;font-weight:700"><span data-stat="avg_time_spent">' + timeFormatted + '</span></div></div>' +
          '</div></div>' +

          // Interaction metrics
          '<div style="margin-bottom:24px"><h4 style="margin:0 0 16px 0;color:#374151;font-size:16px;font-weight:600">Interakcje użytkowników</h4>' +
          '<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(140px, 1fr));gap:12px">' +
          '<div style="padding:16px;background:linear-gradient(135deg, #f093fb 0%, #f5576c 100%);border-radius:12px;box-shadow:0 4px 12px rgba(240,147,251,0.3);color:#fff"><div style="font-size:14px;opacity:0.9;margin-bottom:4px">📞 Telefon</div><div style="font-size:32px;font-weight:700"><span data-stat="phone_clicks">' + (p.stats.phone_clicks || 0) + '</span></div></div>' +
          '<div style="padding:16px;background:linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);border-radius:12px;box-shadow:0 4px 12px rgba(79,172,254,0.3);color:#fff"><div style="font-size:14px;opacity:0.9;margin-bottom:4px">🌐 Strona WWW</div><div style="font-size:32px;font-weight:700"><span data-stat="website_clicks">' + (p.stats.website_clicks || 0) + '</span></div></div>' +
          '<div style="padding:16px;background:linear-gradient(135deg, #ffa751 0%, #ffe259 100%);border-radius:12px;box-shadow:0 4px 12px rgba(255,167,81,0.3);color:#fff"><div style="font-size:14px;opacity:0.9;margin-bottom:4px">🎯 CTA</div><div style="font-size:32px;font-weight:700"><span data-stat="cta_clicks">' + (p.stats.cta_clicks || 0) + '</span></div></div>' +
          '<div style="padding:16px;background:linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);border-radius:12px;box-shadow:0 4px 12px rgba(168,237,234,0.3);color:#333"><div style="font-size:14px;opacity:0.9;margin-bottom:4px">🖼️ Galeria</div><div style="font-size:32px;font-weight:700"><span data-stat="gallery_clicks">' + totalGalleryClicks + '</span></div></div>' +
          '</div></div>' +

          // Social media clicks - separate tiles for each platform
          (totalSocialClicks > 0 ? '<div style="margin-bottom:24px"><h4 style="margin:0 0 12px 0;color:#374151;font-size:16px;font-weight:600">Social media</h4><div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(140px, 1fr));gap:12px">' +
            '<div style="padding:16px;background:linear-gradient(135deg, #1877f2 0%, #0c65d8 100%);border-radius:12px;box-shadow:0 4px 12px rgba(24,119,242,0.3);color:#fff"><div style="font-size:14px;opacity:0.9;margin-bottom:4px;display:flex;align-items:center;gap:6px"><svg width="16" height="16" viewBox="0 0 24 24" fill="white"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg> Facebook</div><div style="font-size:32px;font-weight:700"><span data-stat="social_facebook">' + (p.stats.social_clicks.facebook || 0) + '</span></div></div>' +
            '<div style="padding:16px;background:linear-gradient(135deg, #e1306c 0%, #c13584 100%);border-radius:12px;box-shadow:0 4px 12px rgba(225,48,108,0.3);color:#fff"><div style="font-size:14px;opacity:0.9;margin-bottom:4px;display:flex;align-items:center;gap:6px"><svg width="16" height="16" viewBox="0 0 24 24" fill="white"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg> Instagram</div><div style="font-size:32px;font-weight:700"><span data-stat="social_instagram">' + (p.stats.social_clicks.instagram || 0) + '</span></div></div>' +
            '<div style="padding:16px;background:linear-gradient(135deg, #0077b5 0%, #005582 100%);border-radius:12px;box-shadow:0 4px 12px rgba(0,119,181,0.3);color:#fff"><div style="font-size:14px;opacity:0.9;margin-bottom:4px;display:flex;align-items:center;gap:6px"><svg width="16" height="16" viewBox="0 0 24 24" fill="white"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg> LinkedIn</div><div style="font-size:32px;font-weight:700"><span data-stat="social_linkedin">' + (p.stats.social_clicks.linkedin || 0) + '</span></div></div>' +
            '<div style="padding:16px;background:linear-gradient(135deg, #000 0%, #333 100%);border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.3);color:#fff"><div style="font-size:14px;opacity:0.9;margin-bottom:4px;display:flex;align-items:center;gap:6px"><svg width="16" height="16" viewBox="0 0 24 24" fill="white"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/></svg> TikTok</div><div style="font-size:32px;font-weight:700"><span data-stat="social_tiktok">' + (p.stats.social_clicks.tiktok || 0) + '</span></div></div>' +
          '</div></div>' : '') +

          // Gallery breakdown
          (galleryBreakdown.length > 0 ? '<div style="margin-bottom:24px"><h4 style="margin:0 0 12px 0;color:#374151;font-size:16px;font-weight:600">Najpopularniejsze zdjęcia</h4>' + galleryBreakdown.join('') + '</div>' : '') +

          // Timeline
          '<div style="margin-bottom:24px"><h4 style="margin:0 0 12px 0;color:#374151;font-size:16px;font-weight:600">Linia czasu</h4>' +
          '<div style="background:#f9fafb;border-radius:8px;padding:16px">' +
          '<div style="display:flex;justify-content:space-between;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #e5e7eb"><div><div style="font-size:12px;color:#6b7280;margin-bottom:4px">📅 Pierwsze wyświetlenie</div><div style="font-size:14px;font-weight:600;color:#374151">' + firstViewed + '</div></div></div>' +
          '<div style="display:flex;justify-content:space-between"><div><div style="font-size:12px;color:#6b7280;margin-bottom:4px">🕐 Ostatnie wyświetlenie</div><div style="font-size:14px;font-weight:600;color:#374151">' + lastViewed + '</div></div></div>' +
          '</div></div>' +

          '<div style="padding:12px;background:#eff6ff;border-left:4px solid #3b82f6;border-radius:6px"><div style="font-size:12px;color:#1e40af"><strong>💡 Wskazówka:</strong> Statystyki pokazują rzeczywiste interakcje użytkowników z Twoją pinezką. Wykorzystaj te dane aby zoptymalizować treść i zwiększyć zaangażowanie.<br><span id="stats-last-update" style="margin-top:4px;display:block;font-size:11px;opacity:0.7"></span></div></div>' +
          '</div>';

        return modalHtml;
      }

      /**
       * Open user profile modal
       */
      function openUserModal(userId) {
        api('jg_get_user_info', { user_id: userId }).then(function(user) {
          if (!user) {
            showAlert('Błąd pobierania informacji o użytkowniku');
            return;
          }
          var memberSince = user.member_since ? new Date(user.member_since).toLocaleDateString('pl-PL') : '-';
          var lastActivity = user.last_activity ? new Date(user.last_activity).toLocaleDateString('pl-PL') : 'Brak aktywności';

          var pointsHtml = '';
          if (user.points && user.points.length > 0) {
            pointsHtml = '<div style="max-height:300px;overflow-y:auto;margin-top:12px">';
            for (var i = 0; i < user.points.length; i++) {
              var point = user.points[i];
              var typeLabels = {
                'miejsce': '📍 Miejsce',
                'ciekawostka': '💡 Ciekawostka',
                'zgloszenie': '📢 Zgłoszenie'
              };
              var typeLabel = typeLabels[point.type] || point.type;
              var createdAt = point.created_at ? new Date(point.created_at).toLocaleDateString('pl-PL') : '-';

              pointsHtml += '<div style="padding:10px;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:8px">' +
                '<div style="font-weight:600;margin-bottom:4px">' + esc(point.title) + '</div>' +
                '<div style="font-size:12px;color:#6b7280">' +
                '<span style="margin-right:12px">' + typeLabel + '</span>' +
                '<span>Dodano: ' + createdAt + '</span>' +
                '</div>' +
                '</div>';
            }
            pointsHtml += '</div>';
          } else {
            pointsHtml = '<div style="padding:20px;text-align:center;color:#9ca3af">Brak dodanych miejsc</div>';
          }

          // Photo gallery
          var photosHtml = '';
          if (user.photos && user.photos.length > 0) {
            photosHtml = '<div>' +
              '<h4 style="margin:20px 0 12px 0;color:#374151">📷 Galeria zdjęć (' + user.photos.length + ')</h4>' +
              '<div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(120px, 1fr));gap:12px;max-height:400px;overflow-y:auto">';

            for (var j = 0; j < user.photos.length; j++) {
              var photo = user.photos[j];

              // Handle both object {url, thumbnail} and string formats
              var photoUrl = '';
              var thumbUrl = '';

              if (typeof photo === 'string') {
                photoUrl = photo;
                thumbUrl = photo;
              } else if (photo && typeof photo === 'object') {
                photoUrl = photo.url || photo.full || '';
                thumbUrl = photo.thumbnail || photo.thumb || photo.url || photo.full || '';
              }

              if (photoUrl && thumbUrl) {
                photosHtml += '<div class="user-photo-item" data-photo-url="' + esc(photoUrl) + '" style="position:relative;padding-bottom:100%;border-radius:8px;overflow:hidden;cursor:pointer;transition:transform 0.2s,box-shadow 0.2s">' +
                  '<img src="' + esc(thumbUrl) + '" alt="User photo" style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover">' +
                  '</div>';
              }
            }

            photosHtml += '</div></div>';
          }

          var modalHtml = '<header style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);padding:20px;border-radius:12px 12px 0 0">' +
            '<h3 style="margin:0;color:#fff;font-size:20px">👤 ' + esc(user.username) + '</h3>' +
            '<button class="jg-close" id="user-modal-close" style="color:#fff;opacity:0.9">&times;</button>' +
            '</header>' +
            '<div style="padding:20px">' +
            '<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin-bottom:20px">' +
            '<div style="padding:16px;background:#f9fafb;border-radius:8px">' +
            '<div style="font-size:12px;color:#6b7280;margin-bottom:4px">📅 Członek od</div>' +
            '<div style="font-weight:600">' + memberSince + '</div>' +
            '</div>' +
            '<div style="padding:16px;background:#f9fafb;border-radius:8px">' +
            '<div style="font-size:12px;color:#6b7280;margin-bottom:4px">⏱️ Ostatnia aktywność</div>' +
            '<div style="font-weight:600">' + lastActivity + '</div>' +
            '</div>' +
            '<div style="padding:16px;background:#f9fafb;border-radius:8px">' +
            '<div style="font-size:12px;color:#6b7280;margin-bottom:4px">📍 Dodane miejsca</div>' +
            '<div style="font-weight:600;font-size:24px">' + user.points_count + '</div>' +
            '</div>' +
            '</div>' +
            '<div>' +
            '<h4 style="margin:0 0 8px 0;color:#374151">Ostatnio dodane miejsca (max 10)</h4>' +
            pointsHtml +
            '</div>' +
            photosHtml +
            '</div>';

          open(modalReport, modalHtml);

          qs('#user-modal-close', modalReport).onclick = function() {
            close(modalReport);
          };

          // Add click handlers for photo gallery
          var photoItems = modalReport.querySelectorAll('.user-photo-item');
          for (var k = 0; k < photoItems.length; k++) {
            photoItems[k].onmouseover = function() {
              this.style.transform = 'scale(1.05)';
              this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
            };
            photoItems[k].onmouseout = function() {
              this.style.transform = 'scale(1)';
              this.style.boxShadow = 'none';
            };
            photoItems[k].onclick = function() {
              var photoUrl = this.getAttribute('data-photo-url');
              openLightbox(photoUrl);
            };
          }
        }).catch(function(err) {
          showAlert((err && err.message) || 'Błąd pobierania informacji o użytkowniku');
        });
      }

      /**
       * Open visitors list modal
       */
      function openVisitorsModal(p) {
        // Fetch visitors list
        api('jg_get_point_visitors', { point_id: p.id }).then(function(visitors) {
          if (!visitors) {
            showAlert('Błąd pobierania listy odwiedzających');
            return;
          }

          var visitorsHtml = '';

          if (visitors.length === 0) {
            visitorsHtml = '<div style="padding:40px;text-align:center;color:#9ca3af">Brak zarejestrowanych odwiedzin</div>';
          } else {
            visitorsHtml = '<div style="max-height:500px;overflow-y:auto">';
            for (var i = 0; i < visitors.length; i++) {
              var visitor = visitors[i];
              var lastVisited = visitor.last_visited ? new Date(visitor.last_visited).toLocaleDateString('pl-PL') : '-';
              var isAnonymous = visitor.is_anonymous || visitor.user_id === 0;
              var cursorStyle = isAnonymous ? 'default' : 'pointer';
              var opacityStyle = isAnonymous ? 'opacity:0.7;' : '';

              visitorsHtml += '<div style="padding:12px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;transition:background 0.2s;cursor:' + cursorStyle + ';' + opacityStyle + '" class="visitor-row" data-user-id="' + visitor.user_id + '" data-is-anonymous="' + isAnonymous + '">' +
                '<div style="flex:1">' +
                '<div style="font-weight:600;color:#111827;margin-bottom:4px">' + esc(visitor.username) + '</div>' +
                '<div style="font-size:12px;color:#6b7280">Ostatnia wizyta: ' + lastVisited + '</div>' +
                '</div>' +
                '<div style="padding:8px 16px;background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);border-radius:20px;color:#fff;font-weight:700">' +
                visitor.visit_count + (visitor.visit_count === 1 ? ' wizyta' : visitor.visit_count < 5 ? ' wizyty' : ' wizyt') +
                '</div>' +
                '</div>';
            }
            visitorsHtml += '</div>';
          }

          var modalHtml = '<header style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);padding:20px;border-radius:12px 12px 0 0">' +
            '<h3 style="margin:0;color:#fff;font-size:20px">👥 Unikalni odwiedzający</h3>' +
            '<button class="jg-close" id="visitors-close" style="color:#fff;opacity:0.9">&times;</button>' +
            '</header>' +
            '<div style="padding:20px">' + visitorsHtml + '</div>';

          open(modalReport, modalHtml);

          qs('#visitors-close', modalReport).onclick = function() {
            close(modalReport);
          };

          // Add click handlers to visitor rows (only for logged-in users)
          var visitorRows = modalReport.querySelectorAll('.visitor-row');
          for (var j = 0; j < visitorRows.length; j++) {
            (function(row) {
              var isAnonymous = row.getAttribute('data-is-anonymous') === 'true';

              if (!isAnonymous) {
                row.onmouseover = function() {
                  this.style.background = '#f3f4f6';
                };
                row.onmouseout = function() {
                  this.style.background = '';
                };
                row.onclick = function() {
                  var userId = parseInt(this.getAttribute('data-user-id'));
                  if (userId > 0) {
                    close(modalReport);
                    openUserModal(userId);
                  }
                };
              }
            })(visitorRows[j]);
          }
        }).catch(function(err) {
          showAlert((err && err.message) || 'Błąd pobierania listy odwiedzających');
        });
      }

      /**
       * Open stats modal with real-time updates
       */
      function openStatsModal(p) {
        var modalHtml = renderStatsContent(p);
        open(modalReport, modalHtml);

        qs('#stats-close', modalReport).onclick = function() {
          // Clear interval when modal is closed
          if (statsRefreshInterval) {
            clearInterval(statsRefreshInterval);
            statsRefreshInterval = null;
          }
          close(modalReport);
        };

        // Add click handler to unique visitors card
        var uniqueVisitorsCard = qs('#unique-visitors-card', modalReport);
        if (uniqueVisitorsCard) {
          uniqueVisitorsCard.onclick = function() {
            openVisitorsModal(p);
          };
        }

        // Start real-time updates - refresh every 3 seconds
        if (statsRefreshInterval) {
          clearInterval(statsRefreshInterval);
        }

        statsRefreshInterval = setInterval(function() {

          // Check if stats modal is still open (not visitors modal or user modal)
          var lastUpdateEl = qs('#stats-last-update', modalReport);
          if (!lastUpdateEl) {
            return;
          }

          // Update timestamp to show we're attempting refresh
          if (lastUpdateEl) {
            var now = new Date();
            lastUpdateEl.textContent = 'Próba odświeżenia: ' + now.toLocaleTimeString('pl-PL');
            lastUpdateEl.style.color = '#f59e0b'; // Orange while loading
          }

          // Fetch updated stats for this specific point
          api('jg_get_point_stats', { point_id: p.id }).then(function(updatedPoint) {
            if (!updatedPoint || !updatedPoint.stats) {
              return;
            }


            // Update ALL point data (stats, images, social media, phone, website, etc.)
            p.stats = updatedPoint.stats;
            p.images = updatedPoint.images || p.images;
            p.facebook_url = updatedPoint.facebook_url;
            p.instagram_url = updatedPoint.instagram_url;
            p.linkedin_url = updatedPoint.linkedin_url;
            p.tiktok_url = updatedPoint.tiktok_url;
            p.website = updatedPoint.website;
            p.phone = updatedPoint.phone;
            p.cta_enabled = updatedPoint.cta_enabled;
            p.cta_type = updatedPoint.cta_type;

            // Re-render modal content
            var updatedHtml = renderStatsContent(p);

            // Update only the content part (not the header)
            // First find the modal container, then the content div
            var modal = qs('.jg-modal', modalReport);
            if (!modal) {
              return;
            }
            var contentDiv = modal.querySelector('div:last-child');
            if (!contentDiv) {
              return;
            }

            // Before replacing, collect old values for animation
            var oldValues = {};
            var statsElements = contentDiv.querySelectorAll('[data-stat]');
            for (var i = 0; i < statsElements.length; i++) {
              var el = statsElements[i];
              var statName = el.getAttribute('data-stat');
              oldValues[statName] = parseInt(el.textContent) || 0;
            }

            // Extract content without header
            var tempDiv = document.createElement('div');
            tempDiv.innerHTML = updatedHtml;
            var newContent = tempDiv.querySelector('div:last-child');
            if (!newContent) {
              return;
            }

            // Replace content (handles structural changes like new social media fields)
            contentDiv.innerHTML = newContent.innerHTML;

            // Re-attach click handler to unique visitors card after content update
            var uniqueVisitorsCard = qs('#unique-visitors-card', modalReport);
            if (uniqueVisitorsCard) {
              uniqueVisitorsCard.onclick = function() {
                openVisitorsModal(p);
              };
            }

            // Animate ALL numbers with slot machine effect (even if value didn't change)
            var newStatsElements = contentDiv.querySelectorAll('[data-stat]');
            for (var j = 0; j < newStatsElements.length; j++) {
              var el = newStatsElements[j];
              var statName = el.getAttribute('data-stat');
              var newValue = parseInt(el.textContent) || 0;
              var oldValue = oldValues[statName] || 0;

              // Always animate, even if value is the same (for visual feedback)
              animateNumber(el, oldValue, newValue);
            }

            // Update last update time to show success
            var lastUpdateEl = qs('#stats-last-update', modalReport);
            if (lastUpdateEl) {
              var now = new Date();
              lastUpdateEl.textContent = 'Ostatnia aktualizacja: ' + now.toLocaleTimeString('pl-PL');
              lastUpdateEl.style.color = '#10b981'; // Green on success
            }
          }).catch(function(err) {
            // Update timestamp to show error
            var lastUpdateEl = qs('#stats-last-update', modalReport);
            if (lastUpdateEl) {
              var now = new Date();
              lastUpdateEl.textContent = 'Błąd odświeżenia: ' + now.toLocaleTimeString('pl-PL');
              lastUpdateEl.style.color = '#ef4444'; // Red on error
            }
          });
        }, 20000); // Update every 20 seconds
      }

      function openReportModal(p) {
        // Check if user is logged in
        if (!CFG.isLoggedIn) {
          showAlert('Musisz być zalogowany aby zgłosić miejsce');
          return;
        }

        // Check report cooldown (5 minutes between reports)
        var now = Date.now();
        var remainingMs = REPORT_DELAY - (now - lastReportTime);

        if (lastReportTime > 0 && remainingMs > 0) {
          var sec = Math.ceil(remainingMs / 1000);
          var minutes = Math.floor(sec / 60);
          var seconds = sec % 60;
          var timeStr = minutes > 0 ? minutes + ' min ' + seconds + ' sek' : seconds + ' sek';

          // For admins: show modal with countdown and bypass button
          if (CFG.isAdmin) {
            showConfirm(
              'Minęło dopiero ' + Math.floor((now - lastReportTime) / 1000) + ' sekund od ostatniego zgłoszenia.\n\n' +
              'Poczekaj jeszcze <strong id="jg-report-cooldown-timer">' + timeStr + '</strong> lub zgłoś pomimo limitu.',
              'Limit czasu zgłoszeń',
              'Zgłoś pomimo tego'
            ).then(function(confirmed) {
              if (confirmed) {
                // Bypass: reset lastReportTime and continue
                lastReportTime = 0;
                setLastReportTime(0);
                // Proceed to open modal
                openReportModal(p);
              }
            });

            // Start countdown timer
            var timerEl = null;
            var countdownInterval = setInterval(function() {
              timerEl = document.getElementById('jg-report-cooldown-timer');
              if (timerEl) {
                var remaining = Math.ceil((REPORT_DELAY - (Date.now() - lastReportTime)) / 1000);
                if (remaining <= 0) {
                  clearInterval(countdownInterval);
                  timerEl.textContent = '0 sek';
                } else {
                  var mins = Math.floor(remaining / 60);
                  var secs = remaining % 60;
                  timerEl.textContent = mins > 0 ? mins + ' min ' + secs + ' sek' : secs + ' sek';
                }
              } else {
                clearInterval(countdownInterval);
              }
            }, 1000);

            return;
          } else {
            // For regular users: just show alert
            showAlert('Poczekaj jeszcze ' + timeStr + ' przed kolejnym zgłoszeniem.');
            return;
          }
        }

        open(modalReport, '<header><h3>Zgłoś do moderacji</h3><button class="jg-close" id="rpt-close">&times;</button></header><form id="report-form" class="jg-grid"><textarea name="reason" rows="3" placeholder="Powód zgłoszenia*" required style="padding:8px;border:1px solid #ddd;border-radius:8px"></textarea><small style="color:#666">Powód zgłoszenia jest wymagany</small><div style="display:flex;gap:8px;justify-content:flex-end"><button class="jg-btn" type="submit">Zgłoś</button></div><div id="report-msg" style="font-size:12px;color:#555"></div></form>');
        qs('#rpt-close', modalReport).onclick = function() {
          close(modalReport);
        };

        var f = qs('#report-form', modalReport);
        var msg = qs('#report-msg', modalReport);

        f.onsubmit = function(e) {
          e.preventDefault();

          // Validate reason is not empty
          if (!f.reason.value || !f.reason.value.trim()) {
            msg.textContent = 'Powód zgłoszenia jest wymagany';
            msg.style.color = '#b91c1c';
            return;
          }

          msg.textContent = 'Wysyłanie...';
          msg.style.color = '#555';

          reportPoint({
            post_id: p.id,
            reason: f.reason.value.trim()
          })
          .then(function() {
            // Save report time to localStorage
            var reportTime = Date.now();
            lastReportTime = reportTime;
            setLastReportTime(reportTime);

            msg.textContent = 'Dziękujemy!';
            msg.style.color = '#15803d';
            f.reset();

            // Update marker appearance immediately if admin
            if (CFG.isAdmin && cluster && cluster.getLayers) {
              try {
                var allMarkers = cluster.getLayers();
                for (var i = 0; i < allMarkers.length; i++) {
                  var marker = allMarkers[i];
                  if (marker.options && marker.options.pointId === p.id) {
                    // Update point data
                    p.reports_count = (p.reports_count || 0) + 1;

                    // Update marker options
                    marker.options.hasReports = true;
                    marker.options.reportsCount = p.reports_count;

                    // Regenerate and update icon
                    var newIcon = iconFor(p);
                    marker.setIcon(newIcon);
                    break;
                  }
                }
              } catch (err) {
                debugError('[JG MAP] Błąd aktualizacji markera:', err);
              }
            }

            setTimeout(function() {
              close(modalReport);
            }, 900);
          })
          .catch(function(err) {
            msg.textContent = (err && err.message) || 'Błąd';
            msg.style.color = '#b91c1c';
          });
        };
      }

      function openReportsListModal(p) {
        open(modalReportsList, '<header><h3>Zgłoszenia</h3><button class="jg-close" id="rplist-close">&times;</button></header><div id="reports-content">Ładowanie...</div>');
        qs('#rplist-close', modalReportsList).onclick = function() {
          close(modalReportsList);
        };

        getReports(p.id).then(function(data) {
          var holder = qs('#reports-content', modalReportsList);
          if (!data.reports || data.reports.length === 0) {
            holder.innerHTML = '<p>Brak zgłoszeń.</p>';
            return;
          }

          var html = '<div class="jg-reports-warning">' +
            '<div class="jg-reports-warning-title">⚠️ Zgłoszeń: ' + data.count + '</div>';

          data.reports.forEach(function(r) {
            html += '<div class="jg-report-item">' +
              '<div class="jg-report-item-header">' +
              '<span class="jg-report-item-user">' + esc(r.user_name) + '</span>' +
              '<span class="jg-report-item-date">' + esc(r.date) + '</span>' +
              '</div>' +
              '<div class="jg-report-item-reason">' + esc(r.reason) + '</div>' +
              '</div>';
          });

          html += '</div>' +
            '<div style="margin-top:16px;background:#f8fafc;padding:12px;border-radius:8px">' +
            '<strong>Decyzja:</strong>' +
            '<div style="margin-top:12px">' +
            '<label style="display:block;margin-bottom:8px">Uzasadnienie (opcjonalne):<br>' +
            '<textarea id="admin-reason" rows="3" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></textarea>' +
            '</label>' +
            '<div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap">' +
            '<button class="jg-btn jg-btn--ghost" id="btn-keep">Pozostaw zgłoszenia</button>' +
            '<button class="jg-btn jg-btn--primary" id="btn-edit-place">Edytuj miejsce</button>' +
            '<button class="jg-btn jg-btn--danger" id="btn-remove">Usuń miejsce</button>' +
            '</div>' +
            '<div id="handle-msg" style="margin-top:8px;font-size:12px"></div>' +
            '</div>' +
            '</div>';

          holder.innerHTML = html;

          var reasonField = qs('#admin-reason', modalReportsList);
          var handleMsg = qs('#handle-msg', modalReportsList);

          qs('#btn-keep', modalReportsList).onclick = function() {
            var self = this;
            showConfirm('Pozostawić miejsce? Zgłoszenia zostaną usunięte.').then(function(confirmed) {
              if (!confirmed) return;

              self.disabled = true;
              handleMsg.textContent = 'Przetwarzanie...';

              handleReports({
                post_id: p.id,
                action_type: 'keep',
                reason: reasonField.value
              })
              .then(function(result) {
                close(modalReportsList);
                return refreshAll();
              })
              .then(function() {
              })
              .catch(function(err) {
                handleMsg.textContent = err.message || 'Błąd';
                handleMsg.style.color = '#b91c1c';
                self.disabled = false;
              });
            });
          };

          qs('#btn-edit-place', modalReportsList).onclick = function() {
            openEditModal(p, true); // Pass true to indicate editing from reports
          };

          qs('#btn-remove', modalReportsList).onclick = function() {
            var self = this;
            showConfirm('Usunąć miejsce?').then(function(confirmed) {
              if (!confirmed) return;

              self.disabled = true;
              handleMsg.textContent = 'Przetwarzanie...';

              handleReports({
                post_id: p.id,
                action_type: 'remove',
                reason: reasonField.value
              })
              .then(function(result) {
                close(modalReportsList);
                close(modalView);
                return refreshAll();
              })
              .then(function() {
              })
              .catch(function(err) {
                handleMsg.textContent = err.message || 'Błąd';
                handleMsg.style.color = '#b91c1c';
                self.disabled = false;
              });
            });
          };

        }).catch(function() {
          qs('#reports-content', modalReportsList).innerHTML = '<p style="color:#b91c1c">Błąd.</p>';
        });
      }

      function openEditModal(p, fromReports) {
        // Check if user is banned or has edit_places restriction (skip for admin editing from reports)
        if (!fromReports && window.JG_USER_RESTRICTIONS) {
          if (window.JG_USER_RESTRICTIONS.is_banned) {
            showAlert('Nie możesz edytować miejsc - Twoje konto jest zbanowane.');
            return;
          }
          if (window.JG_USER_RESTRICTIONS.restrictions && window.JG_USER_RESTRICTIONS.restrictions.indexOf('edit_places') !== -1) {
            showAlert('Nie możesz edytować miejsc - masz aktywną blokadę edycji miejsc.');
            return;
          }
        }

        // Fetch daily limits first
        api('jg_get_daily_limits', {})
          .then(function(limits) {
            var limitsHtml = '';
            if (!limits.is_admin) {
              var photoRemaining = (limits.photo_limit_mb - limits.photo_used_mb).toFixed(2);
              limitsHtml = '<div class="cols-2" style="background:#f0f9ff;border:2px solid #3b82f6;border-radius:8px;padding:12px;margin-bottom:12px">' +
                '<strong style="color:#1e40af">Pozostałe limity:</strong>' +
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px">' +
                '<div style="background:#fff;padding:8px;border-radius:4px;text-align:center">' +
                '<div style="font-size:24px;font-weight:700;color:#3b82f6">' + limits.places_remaining + '</div>' +
                '<div style="font-size:11px;color:#666">miejsc/ciekawostek</div>' +
                '</div>' +
                '<div style="background:#fff;padding:8px;border-radius:4px;text-align:center">' +
                '<div style="font-size:24px;font-weight:700;color:#3b82f6">' + limits.reports_remaining + '</div>' +
                '<div style="font-size:11px;color:#666">zgłoszeń</div>' +
                '</div>' +
                '</div>' +
                '<div style="margin-top:8px;padding:8px;background:#fff;border-radius:4px;text-align:center">' +
                '<div style="font-size:18px;font-weight:700;color:#8b5cf6">' + photoRemaining + ' MB / ' + limits.photo_limit_mb + ' MB</div>' +
                '<div style="font-size:11px;color:#666">pozostały miesięczny limit zdjęć</div>' +
                '</div>' +
                '</div>';
            }

            var contentText = p.content ? p.content.replace(/<\/?[^>]+(>|$)/g, "") : (p.excerpt || '');

            // Build existing images section
            var existingImagesHtml = '';
            if (p.images && p.images.length > 0) {
              existingImagesHtml = '<div class="cols-2" style="margin-bottom:16px"><label style="display:block;margin-bottom:8px;font-weight:600">Obecne zdjęcia:</label><div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px">';
              p.images.forEach(function(img, idx) {
                var thumbUrl = typeof img === 'object' ? (img.thumb || img.full) : img;
                existingImagesHtml += '<div style="position:relative;aspect-ratio:1;border-radius:8px;overflow:hidden;border:2px solid #e5e7eb"><img src="' + esc(thumbUrl) + '" style="width:100%;height:100%;object-fit:cover" alt="Zdjęcie ' + (idx + 1) + '"></div>';
              });
              existingImagesHtml += '</div><small style="display:block;color:#666;margin-top:8px">Zdjęcia nie mogą być usuwane podczas edycji. Nowe zdjęcia zostaną dodane do istniejących.</small></div>';
            }

            // Determine max images based on sponsored status
            var isSponsored = !!p.sponsored;
            var maxTotalImages = isSponsored ? 12 : 6;

            // Sponsored contact fields (only for sponsored points)
            var sponsoredContactHtml = '';
            if (isSponsored) {
              sponsoredContactHtml = '<div class="cols-2" style="background:#fef3c7;border:2px solid #f59e0b;border-radius:8px;padding:12px;margin:12px 0">' +
                '<strong style="display:block;margin-bottom:12px;color:#92400e">📋 Dane kontaktowe (punkt sponsorowany)</strong>' +
                '<label style="display:block;margin-bottom:8px;display:flex;align-items:center;gap:6px"><svg width="16" height="16" viewBox="0 0 24 24" fill="#92400e"><circle cx="12" cy="12" r="10" stroke="#92400e" stroke-width="2" fill="none"/><path d="M12 6v6l4 2" stroke="#92400e" stroke-width="2" fill="none"/></svg> Strona internetowa <input type="text" name="website" id="edit-website-input" value="' + esc(p.website || '') + '" placeholder="np. jeleniagora.pl" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></label>' +
                '<label style="display:block;margin-bottom:16px;display:flex;align-items:center;gap:6px"><svg width="16" height="16" viewBox="0 0 24 24" fill="#92400e"><path d="M20 10.999h2C22 5.869 18.127 2 12.99 2v2C17.052 4 20 6.943 20 10.999z"/><path d="M13 8c2.103 0 3 .897 3 3h2c0-3.225-1.775-5-5-5v2zm3.422 5.443a1.001 1.001 0 0 0-1.391.043l-2.393 2.461c-.576-.11-1.734-.471-2.926-1.66-1.192-1.193-1.553-2.354-1.66-2.926l2.459-2.394a1 1 0 0 0 .043-1.391L6.859 3.513a1 1 0 0 0-1.391-.087l-2.17 1.861a1 1 0 0 0-.29.649c-.015.25-.301 6.172 4.291 10.766C11.305 20.707 16.323 21 17.705 21c.202 0 .326-.006.359-.008a.992.992 0 0 0 .648-.291l1.86-2.171a.997.997 0 0 0-.086-1.391l-4.064-3.696z"/></svg> Telefon <input type="text" name="phone" id="edit-phone-input" value="' + esc(p.phone || '') + '" placeholder="np. 123 456 789" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></label>' +
                '<strong style="display:block;margin:16px 0 8px;color:#92400e">Media społecznościowe</strong>' +
                '<label style="display:block;margin-bottom:8px;display:flex;align-items:center;gap:6px"><svg width="16" height="16" viewBox="0 0 24 24" fill="#1877f2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg> Facebook <input type="text" name="facebook_url" id="edit-facebook-input" value="' + esc(p.facebook_url || '') + '" placeholder="np. facebook.com/twojstrona" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></label>' +
                '<label style="display:block;margin-bottom:8px;display:flex;align-items:center;gap:6px"><svg width="16" height="16" viewBox="0 0 24 24" fill="url(#instagram-gradient)"><defs><linearGradient id="instagram-gradient" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" style="stop-color:#f09433;stop-opacity:1" /><stop offset="25%" style="stop-color:#e6683c;stop-opacity:1" /><stop offset="50%" style="stop-color:#dc2743;stop-opacity:1" /><stop offset="75%" style="stop-color:#cc2366;stop-opacity:1" /><stop offset="100%" style="stop-color:#bc1888;stop-opacity:1" /></linearGradient></defs><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg> Instagram <input type="text" name="instagram_url" id="edit-instagram-input" value="' + esc(p.instagram_url || '') + '" placeholder="np. instagram.com/twojprofil" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></label>' +
                '<label style="display:block;margin-bottom:8px;display:flex;align-items:center;gap:6px"><svg width="16" height="16" viewBox="0 0 24 24" fill="#0077b5"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg> LinkedIn <input type="text" name="linkedin_url" id="edit-linkedin-input" value="' + esc(p.linkedin_url || '') + '" placeholder="np. linkedin.com/company/twojafirma" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></label>' +
                '<label style="display:block;margin-bottom:16px;display:flex;align-items:center;gap:6px"><svg width="16" height="16" viewBox="0 0 24 24" fill="#000"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/></svg> TikTok <input type="text" name="tiktok_url" id="edit-tiktok-input" value="' + esc(p.tiktok_url || '') + '" placeholder="np. tiktok.com/@twojprofil" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></label>' +
                '<div style="border-top:2px solid #f59e0b;padding-top:12px;margin-top:12px">' +
                '<label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;cursor:pointer">' +
                '<input type="checkbox" name="cta_enabled" id="edit-cta-enabled-checkbox" value="1" ' + (p.cta_enabled ? 'checked' : '') + ' style="width:20px;height:20px">' +
                '<strong style="color:#92400e">🎯 Włącz przycisk Call-to-Action (CTA)</strong>' +
                '</label>' +
                '<div id="edit-cta-type-selection" style="' + (p.cta_enabled ? '' : 'display:none;') + 'margin-left:28px">' +
                '<label style="display:block;margin-bottom:8px;color:#92400e"><strong>Typ przycisku:</strong></label>' +
                '<div style="display:flex;gap:8px;flex-direction:column">' +
                '<label style="display:flex;align-items:center;gap:8px;padding:8px;background:#fff;border:2px solid #d97706;border-radius:6px;cursor:pointer">' +
                '<input type="radio" name="cta_type" value="call" ' + (p.cta_type === 'call' ? 'checked' : '') + ' style="width:18px;height:18px">' +
                '<div><strong>📞 Zadzwoń teraz</strong></div>' +
                '</label>' +
                '<label style="display:flex;align-items:center;gap:8px;padding:8px;background:#fff;border:2px solid #d97706;border-radius:6px;cursor:pointer">' +
                '<input type="radio" name="cta_type" value="website" ' + (p.cta_type === 'website' ? 'checked' : '') + ' style="width:18px;height:18px">' +
                '<div><strong>🌐 Wejdź na naszą stronę</strong></div>' +
                '</label>' +
                '<label style="display:flex;align-items:center;gap:8px;padding:8px;background:#fff;border:2px solid #d97706;border-radius:6px;cursor:pointer">' +
                '<input type="radio" name="cta_type" value="facebook" ' + (p.cta_type === 'facebook' ? 'checked' : '') + ' style="width:18px;height:18px">' +
                '<div><strong>📘 Odwiedź nas na Facebooku</strong></div>' +
                '</label>' +
                '<label style="display:flex;align-items:center;gap:8px;padding:8px;background:#fff;border:2px solid #d97706;border-radius:6px;cursor:pointer">' +
                '<input type="radio" name="cta_type" value="instagram" ' + (p.cta_type === 'instagram' ? 'checked' : '') + ' style="width:18px;height:18px">' +
                '<div><strong>📷 Sprawdź nas na Instagramie</strong></div>' +
                '</label>' +
                '<label style="display:flex;align-items:center;gap:8px;padding:8px;background:#fff;border:2px solid #d97706;border-radius:6px;cursor:pointer">' +
                '<input type="radio" name="cta_type" value="linkedin" ' + (p.cta_type === 'linkedin' ? 'checked' : '') + ' style="width:18px;height:18px">' +
                '<div><strong>💼 Zobacz nas na LinkedIn</strong></div>' +
                '</label>' +
                '<label style="display:flex;align-items:center;gap:8px;padding:8px;background:#fff;border:2px solid #d97706;border-radius:6px;cursor:pointer">' +
                '<input type="radio" name="cta_type" value="tiktok" ' + (p.cta_type === 'tiktok' ? 'checked' : '') + ' style="width:18px;height:18px">' +
                '<div><strong>🎵 Obserwuj nas na TikToku</strong></div>' +
                '</label>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>';
            }

            // Determine max length for description based on sponsored status
            var maxDescLength = isSponsored ? 3000 : 800;
            var currentDescLength = contentText.length;

            // Check if user is editing someone else's place (requires approval)
            var isEditingOthersPlace = +CFG.currentUserId > 0 && +CFG.currentUserId !== +p.author_id;
            var approvalNoticeHtml = '';
            if (isEditingOthersPlace && !limits.is_admin) {
              approvalNoticeHtml = '<div class="cols-2" style="background:#fef3c7;border:2px solid #f59e0b;border-radius:8px;padding:12px;margin-bottom:12px">' +
                '<div style="display:flex;align-items:flex-start;gap:10px">' +
                '<span style="font-size:24px">ℹ️</span>' +
                '<div>' +
                '<strong style="color:#92400e;display:block;margin-bottom:4px">Edycja cudzego miejsca</strong>' +
                '<span style="color:#78350f;font-size:13px">Twoja propozycja zmian musi zostać zatwierdzona przez właściciela miejsca oraz moderatora przed publikacją.</span>' +
                '</div>' +
                '</div>' +
                '</div>';
            }

            var formHtml = '<header><h3>Edytuj</h3><button class="jg-close" id="edt-close">&times;</button></header>' +
              '<form id="edit-form" class="jg-grid cols-2">' +
              approvalNoticeHtml +
              limitsHtml +
              '<label>Tytuł* <input name="title" required value="' + esc(p.title || '') + '" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px"></label>' +
              '<label>Typ* <select name="type" id="edit-type-select" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' +
              '<option value="zgloszenie"' + (p.type === 'zgloszenie' ? ' selected' : '') + '>Zgłoszenie</option>' +
              '<option value="ciekawostka"' + (p.type === 'ciekawostka' ? ' selected' : '') + '>Ciekawostka</option>' +
              '<option value="miejsce"' + (p.type === 'miejsce' ? ' selected' : '') + '>Miejsce</option>' +
              '</select></label>' +
              '<label class="cols-2" id="edit-category-field" style="' + (p.type === 'zgloszenie' ? 'display:block' : 'display:none') + '"><span style="color:#dc2626">Kategoria zgłoszenia*</span> <select name="category" id="edit-category-select" ' + (p.type === 'zgloszenie' ? 'required' : '') + ' style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' +
              generateCategoryOptions(p.category || '') +
              '</select></label>' +
              '<div class="cols-2" style="position:relative">' +
              '<label style="display:block;margin-bottom:4px">Adres (korekta pozycji pinezki)</label>' +
              '<input type="text" name="address" id="edit-address-input" value="' + esc(p.address || '') + '" placeholder="Wpisz adres, aby skorygować pozycję..." style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px" autocomplete="off">' +
              '<input type="hidden" name="lat" id="edit-lat-input" value="' + p.lat + '">' +
              '<input type="hidden" name="lng" id="edit-lng-input" value="' + p.lng + '">' +
              '<input type="hidden" id="edit-original-lat" value="' + p.lat + '">' +
              '<input type="hidden" id="edit-original-lng" value="' + p.lng + '">' +
              '<input type="hidden" id="edit-original-address" value="' + esc(p.address || '') + '">' +
              '<div id="edit-address-suggestions" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #ddd;border-top:none;border-radius:0 0 8px 8px;max-height:200px;overflow-y:auto;z-index:1000;box-shadow:0 4px 6px rgba(0,0,0,0.1)"></div>' +
              '<small id="edit-address-hint" style="display:block;margin-top:4px;color:#666">Obecny adres. Wpisz nowy adres aby zmienić pozycję pinezki.</small>' +
              '</div>' +
              '<label class="cols-2">Opis <textarea name="content" rows="6" maxlength="' + maxDescLength + '" id="edit-content-input" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' + contentText + '</textarea><div id="edit-content-counter" style="font-size:12px;color:#666;margin-top:4px;text-align:right">' + currentDescLength + ' / ' + maxDescLength + ' znaków</div></label>' +
              sponsoredContactHtml +
              existingImagesHtml +
              '<label class="cols-2">Dodaj nowe zdjęcia (max ' + maxTotalImages + ' łącznie) <input type="file" name="images[]" multiple accept="image/*" id="edit-images-input" style="width:100%;padding:8px"></label>' +
              '<div class="cols-2" id="edit-images-preview" style="display:none;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:8px;margin-top:8px"></div>' +
              '<div class="cols-2" style="display:flex;gap:8px;justify-content:flex-end">' +
              '<button type="button" class="jg-btn jg-btn--ghost" id="edt-cancel">Anuluj</button>' +
              '<button type="submit" class="jg-btn">Zapisz</button>' +
              '</div>' +
              '<div id="edit-msg" class="cols-2" style="font-size:12px"></div>' +
              '</form>';

            open(modalEdit, formHtml);

        qs('#edt-close', modalEdit).onclick = function() {
          close(modalEdit);
        };

        qs('#edt-cancel', modalEdit).onclick = function() {
          close(modalEdit);
        };

        var form = qs('#edit-form', modalEdit);
        var msg = qs('#edit-msg', modalEdit);

        // Character counter for description in edit form
        var editContentInput = qs('#edit-content-input', modalEdit);
        var editContentCounter = qs('#edit-content-counter', modalEdit);
        if (editContentInput && editContentCounter) {
          editContentInput.addEventListener('input', function() {
            var length = this.value.length;
            var maxLength = parseInt(this.getAttribute('maxlength'));
            editContentCounter.textContent = length + ' / ' + maxLength + ' znaków';
            if (length > maxLength * 0.9) {
              editContentCounter.style.color = '#d97706';
            } else {
              editContentCounter.style.color = '#666';
            }
          });
        }

        // Image preview functionality for edit
        var imagesInput = qs('#edit-images-input', modalEdit);
        var imagesPreview = qs('#edit-images-preview', modalEdit);

        if (imagesInput) {
          imagesInput.addEventListener('change', function(e) {
            imagesPreview.innerHTML = '';
            var files = e.target.files;

            // Calculate max images based on existing count
            var existingCount = p.images ? p.images.length : 0;
            var maxNew = Math.max(0, maxTotalImages - existingCount);

            if (files.length > maxNew) {
              msg.textContent = 'Uwaga: Możesz dodać maksymalnie ' + maxNew + ' zdjęć (masz już ' + existingCount + '/' + maxTotalImages + '). Pierwsze ' + maxNew + ' zostanie użytych.';
              msg.style.color = '#d97706';
            } else if (msg.textContent.indexOf('Możesz dodać maksymalnie') !== -1) {
              msg.textContent = '';
            }

            if (files.length > 0) {
              imagesPreview.style.display = 'grid';
              var maxFiles = Math.min(files.length, maxNew);
              for (var i = 0; i < maxFiles; i++) {
                var file = files[i];
                var reader = new FileReader();

                reader.onload = (function(f) {
                  return function(e) {
                    var imgHtml = '<div style="position:relative;aspect-ratio:1;border-radius:8px;overflow:hidden;border:2px solid #e5e7eb">' +
                      '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover" alt="Podgląd">' +
                      '</div>';
                    imagesPreview.innerHTML += imgHtml;
                  };
                })(file);

                reader.readAsDataURL(file);
              }
            } else {
              imagesPreview.style.display = 'none';
            }
          });
        }

        // Toggle category field based on type selection in edit form
        var editTypeSelect = qs('#edit-type-select', modalEdit);
        var editCategoryField = qs('#edit-category-field', modalEdit);
        var editCategorySelect = qs('#edit-category-select', modalEdit);

        if (editTypeSelect && editCategoryField && editCategorySelect) {
          function toggleEditCategoryField() {
            if (editTypeSelect.value === 'zgloszenie') {
              editCategoryField.style.display = 'block';
              editCategorySelect.setAttribute('required', 'required');
            } else {
              editCategoryField.style.display = 'none';
              editCategorySelect.removeAttribute('required');
              editCategorySelect.value = ''; // Clear selection when hidden
            }
          }

          editTypeSelect.addEventListener('change', toggleEditCategoryField);
          // Call once to set initial state
          toggleEditCategoryField();
        }

        // CTA checkbox toggle for sponsored points
        if (isSponsored) {
          var ctaEnabledCheckbox = qs('#edit-cta-enabled-checkbox', modalEdit);
          var ctaTypeSelection = qs('#edit-cta-type-selection', modalEdit);

          if (ctaEnabledCheckbox && ctaTypeSelection) {
            ctaEnabledCheckbox.addEventListener('change', function() {
              ctaTypeSelection.style.display = ctaEnabledCheckbox.checked ? '' : 'none';
            });
          }
        }

        // Address autocomplete and geocoding for edit form
        var editAddressInput = qs('#edit-address-input', modalEdit);
        var editAddressSuggestions = qs('#edit-address-suggestions', modalEdit);
        var editAddressHint = qs('#edit-address-hint', modalEdit);
        var editLatInput = qs('#edit-lat-input', modalEdit);
        var editLngInput = qs('#edit-lng-input', modalEdit);
        var editOriginalLat = qs('#edit-original-lat', modalEdit);
        var editOriginalLng = qs('#edit-original-lng', modalEdit);
        var editOriginalAddress = qs('#edit-original-address', modalEdit);
        var editAddressTimeout = null;
        var editSelectedSuggestion = null;

        if (editAddressInput && editAddressSuggestions) {
          // Handle input for autocomplete
          editAddressInput.addEventListener('input', function() {
            var query = this.value.trim();
            editSelectedSuggestion = null;

            // Clear previous timeout
            if (editAddressTimeout) {
              clearTimeout(editAddressTimeout);
            }

            // Check if address has changed from original
            var originalAddress = editOriginalAddress ? editOriginalAddress.value : '';
            if (query === originalAddress) {
              // Reset to original coordinates
              editLatInput.value = editOriginalLat.value;
              editLngInput.value = editOriginalLng.value;
              editAddressHint.textContent = 'Obecny adres. Wpisz nowy adres aby zmienić pozycję pinezki.';
              editAddressHint.style.color = '#666';
              editAddressSuggestions.style.display = 'none';
              return;
            }

            if (query.length < 3) {
              editAddressSuggestions.style.display = 'none';
              return;
            }

            // Debounce search by 300ms
            editAddressTimeout = setTimeout(function() {
              searchEditAddressSuggestions(query);
            }, 300);
          });

          // Handle keyboard navigation
          editAddressInput.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown') {
              e.preventDefault();
              var items = editAddressSuggestions.querySelectorAll('.edit-suggestion-item');
              if (items.length > 0) {
                items[0].focus();
              }
            } else if (e.key === 'Escape') {
              editAddressSuggestions.style.display = 'none';
            } else if (e.key === 'Enter' && editSelectedSuggestion) {
              e.preventDefault();
              selectEditAddress(editSelectedSuggestion);
            }
          });

          // Close suggestions when clicking outside
          document.addEventListener('click', function(e) {
            if (!editAddressInput.contains(e.target) && !editAddressSuggestions.contains(e.target)) {
              editAddressSuggestions.style.display = 'none';
            }
          });

          // Search address suggestions function
          function searchEditAddressSuggestions(query) {
            api('jg_search_address', { query: query })
              .then(function(results) {
                editAddressSuggestions.innerHTML = '';

                if (results && results.length > 0) {
                  results.forEach(function(result) {
                    var item = document.createElement('div');
                    item.className = 'edit-suggestion-item';
                    item.setAttribute('tabindex', '0');
                    item.style.cssText = 'padding:10px 12px;cursor:pointer;font-size:13px;color:#374151;border-bottom:1px solid #f3f4f6;transition:background 0.2s';
                    item.textContent = result.display_name;

                    item.addEventListener('mouseenter', function() {
                      this.style.background = '#fef3c7';
                      editSelectedSuggestion = result;
                    });

                    item.addEventListener('mouseleave', function() {
                      this.style.background = '#fff';
                    });

                    item.addEventListener('click', function() {
                      selectEditAddress(result);
                    });

                    item.addEventListener('keydown', function(e) {
                      if (e.key === 'Enter') {
                        selectEditAddress(result);
                      } else if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        var next = this.nextElementSibling;
                        if (next && next.classList.contains('edit-suggestion-item')) {
                          next.focus();
                        }
                      } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        var prev = this.previousElementSibling;
                        if (prev && prev.classList.contains('edit-suggestion-item')) {
                          prev.focus();
                        } else {
                          editAddressInput.focus();
                        }
                      } else if (e.key === 'Escape') {
                        editAddressSuggestions.style.display = 'none';
                        editAddressInput.focus();
                      }
                    });

                    editAddressSuggestions.appendChild(item);
                  });

                  editAddressSuggestions.style.display = 'block';
                } else {
                  editAddressSuggestions.style.display = 'none';
                }
              })
              .catch(function(err) {
                console.error('[JG Edit] Address search error:', err);
                editAddressSuggestions.style.display = 'none';
              });
          }

          // Select address and update coordinates
          function selectEditAddress(result) {
            var lat = parseFloat(result.lat);
            var lng = parseFloat(result.lon);

            // Build clean address from components
            var addressParts = [];
            if (result.address) {
              if (result.address.road) {
                var road = result.address.road;
                if (result.address.house_number) {
                  road += ' ' + result.address.house_number;
                }
                addressParts.push(road);
              }
              if (result.address.city || result.address.town || result.address.village) {
                addressParts.push(result.address.city || result.address.town || result.address.village);
              }
            }
            var cleanAddress = addressParts.length > 0 ? addressParts.join(', ') : result.display_name;

            // Update form fields
            editAddressInput.value = cleanAddress;
            editLatInput.value = lat;
            editLngInput.value = lng;

            // Update hint to show coordinates changed
            editAddressHint.innerHTML = '<span style="color:#15803d;font-weight:500">&#10003; Nowa pozycja: ' + lat.toFixed(6) + ', ' + lng.toFixed(6) + '</span>';

            // Hide suggestions
            editAddressSuggestions.style.display = 'none';
            editSelectedSuggestion = null;
          }
        }

        form.onsubmit = function(e) {
          e.preventDefault();
          msg.textContent = 'Zapisywanie...';

          if (!form.title.value.trim()) {
            form.title.focus();
            msg.textContent = 'Podaj tytuł.';
            msg.style.color = '#b91c1c';
            return;
          }

          // Use FormData to support file uploads
          var fd = new FormData(form);
          // Use special endpoint when editing from reports modal
          fd.append('action', fromReports ? 'jg_admin_edit_and_resolve_reports' : 'jg_update_point');
          fd.append('_ajax_nonce', CFG.nonce);
          fd.append('post_id', p.id);

          fetch(CFG.ajax, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
          })
          .then(function(r) {
            return r.text();
          })
          .then(function(t) {
            var j = null;
            try {
              j = JSON.parse(t);
            } catch (_) {}

            if (!j || j.success === false) {
              throw new Error((j && j.data && j.data.message) || 'Błąd');
            }

            msg.textContent = 'Zaktualizowano.';
            msg.style.color = '#15803d';
            setTimeout(function() {
              close(modalEdit);
              if (fromReports) {
                close(modalReportsList);
              }
              refreshAll().then(function() {
                if (fromReports) {
                  showAlert('Miejsce edytowane i zgłoszenia zamknięte!');
                } else {
                  showAlert('Wysłano do moderacji. Zmiany będą widoczne po zaakceptowaniu.');
                }
              });
            }, 300);
          })
          .catch(function(err) {
            msg.textContent = err.message || 'Błąd';
            msg.style.color = '#b91c1c';
          });
        };
      })
      .catch(function(err) {
        showAlert('Błąd podczas ładowania limitów: ' + (err.message || 'Nieznany błąd'));
      });
      }

      function openDeletionRequestModal(p) {
        open(modalEdit, '<header><h3>Zgłoś usunięcie miejsca</h3><button class="jg-close" id="del-close">&times;</button></header><form id="deletion-form" class="jg-grid"><p>Czy na pewno chcesz zgłosić usunięcie tego miejsca? Administracja musi zatwierdzić Twoje zgłoszenie.</p><label>Powód (opcjonalnie) <textarea name="reason" rows="4" placeholder="Podaj powód usunięcia..." style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px"></textarea></label><div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px"><button type="button" class="jg-btn jg-btn--ghost" id="del-cancel">Anuluj</button><button type="submit" class="jg-btn jg-btn--danger">Zgłoś usunięcie</button></div><div id="deletion-msg" style="font-size:12px;margin-top:8px"></div></form>');

        qs('#del-close', modalEdit).onclick = function() {
          close(modalEdit);
        };

        qs('#del-cancel', modalEdit).onclick = function() {
          close(modalEdit);
        };

        var form = qs('#deletion-form', modalEdit);
        var msg = qs('#deletion-msg', modalEdit);

        form.onsubmit = function(e) {
          e.preventDefault();

          showConfirm('Czy na pewno chcesz zgłosić usunięcie tego miejsca?').then(function(confirmed) {
            if (!confirmed) {
              return;
            }

            msg.textContent = 'Wysyłanie zgłoszenia...';
            msg.style.color = '#666';

            api('jg_request_deletion', {
              post_id: p.id,
              reason: form.reason.value.trim()
            })
              .then(function() {
                msg.textContent = 'Zgłoszenie wysłane do moderacji!';
                msg.style.color = '#15803d';
                setTimeout(function() {
                  close(modalEdit);
                  close(modalView);
                  refreshAll();
                }, 1500);
              })
              .catch(function(err) {
                msg.textContent = (err && err.message) || 'Błąd';
                msg.style.color = '#b91c1c';
              });
          });
        };
      }

      function openPromoModal(p) {
        var currentPromoUntil = p.sponsored_until || '';
        var promoDateValue = '';

        if (currentPromoUntil && currentPromoUntil !== 'null') {
          try {
            var d = new Date(currentPromoUntil);
            // Format to YYYY-MM-DD for date input (time will be set to end of day)
            var year = d.getFullYear();
            var month = String(d.getMonth() + 1).padStart(2, '0');
            var day = String(d.getDate()).padStart(2, '0');
            promoDateValue = year + '-' + month + '-' + day;
          } catch (e) {
            debugError('Error parsing promo date:', e);
          }
        }

        var html = '<header><h3>Zarządzaj sponsorowaniem</h3><button class="jg-close" id="sponsored-modal-close">&times;</button></header>' +
          '<div class="jg-grid" style="padding:16px">' +
          '<p><strong>Miejsce:</strong> ' + esc(p.title) + '</p>' +
          '<div style="margin:16px 0">' +
          '<label style="display:block;margin-bottom:8px"><strong>Status sponsorowania:</strong></label>' +
          '<div style="display:flex;gap:12px;margin-bottom:16px">' +
          '<label style="display:flex;align-items:center;gap:8px;padding:12px;border:2px solid #e5e7eb;border-radius:8px;cursor:pointer;flex:1">' +
          '<input type="radio" name="sponsored_status" value="1" ' + (p.sponsored ? 'checked' : '') + ' style="width:20px;height:20px">' +
          '<div><strong>Sponsorowane</strong></div>' +
          '</label>' +
          '<label style="display:flex;align-items:center;gap:8px;padding:12px;border:2px solid #e5e7eb;border-radius:8px;cursor:pointer;flex:1">' +
          '<input type="radio" name="sponsored_status" value="0" ' + (!p.sponsored ? 'checked' : '') + ' style="width:20px;height:20px">' +
          '<div><strong>Bez sponsorowania</strong></div>' +
          '</label>' +
          '</div>' +
          '<label style="display:block;margin-bottom:8px"><strong>Data wygaśnięcia sponsorowania (opcjonalnie):</strong></label>' +
          '<input type="date" id="sponsored-until-input" value="' + promoDateValue + '" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-bottom:8px">' +
          '<small style="display:block;color:#666;margin-bottom:16px">Sponsorowanie wygasa o północy wybranego dnia. Pozostaw puste dla sponsorowania bezterminowego.</small>' +
          '<label style="display:block;margin-bottom:8px;margin-top:16px"><strong>🌐 Strona internetowa (opcjonalnie):</strong></label>' +
          '<input type="text" id="sponsored-website-input" value="' + esc(p.website || '') + '" placeholder="np. jeleniagora.pl" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-bottom:8px">' +
          '<label style="display:block;margin-bottom:8px"><strong>📞 Telefon (opcjonalnie):</strong></label>' +
          '<input type="text" id="sponsored-phone-input" value="' + esc(p.phone || '') + '" placeholder="np. 123 456 789" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-bottom:16px">' +
          '<div style="background:#e0f2fe;border:2px solid #0284c7;border-radius:8px;padding:12px;margin-top:16px">' +
          '<label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;cursor:pointer">' +
          '<input type="checkbox" id="cta-enabled-checkbox" ' + (p.cta_enabled ? 'checked' : '') + ' style="width:20px;height:20px">' +
          '<strong style="color:#075985">🎯 Włącz przycisk Call-to-Action (CTA)</strong>' +
          '</label>' +
          '<div id="cta-type-selection" style="' + (p.cta_enabled ? '' : 'display:none;') + 'margin-left:28px">' +
          '<label style="display:block;margin-bottom:8px;color:#075985"><strong>Typ przycisku:</strong></label>' +
          '<div style="display:flex;gap:8px;flex-direction:column">' +
          '<label style="display:flex;align-items:center;gap:8px;padding:8px;background:#fff;border:2px solid #cbd5e1;border-radius:6px;cursor:pointer">' +
          '<input type="radio" name="cta_type" value="call" ' + (p.cta_type === 'call' ? 'checked' : '') + ' style="width:18px;height:18px">' +
          '<div><strong>📞 Zadzwoń teraz</strong> <span style="color:#666;font-size:12px">(wymaga numeru telefonu)</span></div>' +
          '</label>' +
          '<label style="display:flex;align-items:center;gap:8px;padding:8px;background:#fff;border:2px solid #cbd5e1;border-radius:6px;cursor:pointer">' +
          '<input type="radio" name="cta_type" value="website" ' + (p.cta_type === 'website' ? 'checked' : '') + ' style="width:18px;height:18px">' +
          '<div><strong>🌐 Wejdź na naszą stronę</strong> <span style="color:#666;font-size:12px">(wymaga strony internetowej)</span></div>' +
          '</label>' +
          '<label style="display:flex;align-items:center;gap:8px;padding:8px;background:#fff;border:2px solid #cbd5e1;border-radius:6px;cursor:pointer">' +
          '<input type="radio" name="cta_type" value="facebook" ' + (p.cta_type === 'facebook' ? 'checked' : '') + ' style="width:18px;height:18px">' +
          '<div><strong>📘 Odwiedź nas na Facebooku</strong> <span style="color:#666;font-size:12px">(wymaga profilu Facebook)</span></div>' +
          '</label>' +
          '<label style="display:flex;align-items:center;gap:8px;padding:8px;background:#fff;border:2px solid #cbd5e1;border-radius:6px;cursor:pointer">' +
          '<input type="radio" name="cta_type" value="instagram" ' + (p.cta_type === 'instagram' ? 'checked' : '') + ' style="width:18px;height:18px">' +
          '<div><strong>📷 Sprawdź nas na Instagramie</strong> <span style="color:#666;font-size:12px">(wymaga profilu Instagram)</span></div>' +
          '</label>' +
          '<label style="display:flex;align-items:center;gap:8px;padding:8px;background:#fff;border:2px solid #cbd5e1;border-radius:6px;cursor:pointer">' +
          '<input type="radio" name="cta_type" value="linkedin" ' + (p.cta_type === 'linkedin' ? 'checked' : '') + ' style="width:18px;height:18px">' +
          '<div><strong>💼 Zobacz nas na LinkedIn</strong> <span style="color:#666;font-size:12px">(wymaga profilu LinkedIn)</span></div>' +
          '</label>' +
          '<label style="display:flex;align-items:center;gap:8px;padding:8px;background:#fff;border:2px solid #cbd5e1;border-radius:6px;cursor:pointer">' +
          '<input type="radio" name="cta_type" value="tiktok" ' + (p.cta_type === 'tiktok' ? 'checked' : '') + ' style="width:18px;height:18px">' +
          '<div><strong>🎵 Obserwuj nas na TikToku</strong> <span style="color:#666;font-size:12px">(wymaga profilu TikTok)</span></div>' +
          '</label>' +
          '</div>' +
          '</div>' +
          '</div>' +
          '</div>' +
          '<div style="display:flex;gap:8px;justify-content:flex-end">' +
          '<button type="button" class="jg-btn jg-btn--ghost" id="sponsored-modal-cancel">Anuluj</button>' +
          '<button type="button" class="jg-btn" id="sponsored-modal-save">Zapisz</button>' +
          '</div>' +
          '<div id="sponsored-modal-msg" style="margin-top:12px;font-size:12px"></div>' +
          '</div>';

        open(modalStatus, html);

        qs('#sponsored-modal-close', modalStatus).onclick = function() {
          close(modalStatus);
        };

        qs('#sponsored-modal-cancel', modalStatus).onclick = function() {
          close(modalStatus);
        };

        var msg = qs('#sponsored-modal-msg', modalStatus);
        var saveBtn = qs('#sponsored-modal-save', modalStatus);
        var dateInput = qs('#sponsored-until-input', modalStatus);
        var websiteInput = qs('#sponsored-website-input', modalStatus);
        var phoneInput = qs('#sponsored-phone-input', modalStatus);
        var ctaEnabledCheckbox = qs('#cta-enabled-checkbox', modalStatus);
        var ctaTypeSelection = qs('#cta-type-selection', modalStatus);

        // Toggle CTA type selection visibility based on checkbox
        if (ctaEnabledCheckbox) {
          ctaEnabledCheckbox.addEventListener('change', function() {
            ctaTypeSelection.style.display = ctaEnabledCheckbox.checked ? '' : 'none';
          });
        }

        saveBtn.onclick = function() {
          var selectedSponsored = qs('input[name="sponsored_status"]:checked', modalStatus);
          if (!selectedSponsored) {
            msg.textContent = 'Wybierz status sponsorowania';
            msg.style.color = '#b91c1c';
            return;
          }

          var isSponsored = selectedSponsored.value === '1';
          var sponsoredUntil = dateInput.value || '';
          var website = websiteInput.value.trim();
          var phone = phoneInput.value.trim();
          var ctaEnabled = ctaEnabledCheckbox.checked;
          var ctaType = null;

          // Get CTA type if enabled
          if (ctaEnabled) {
            var selectedCtaType = qs('input[name="cta_type"]:checked', modalStatus);
            if (selectedCtaType) {
              ctaType = selectedCtaType.value;
            }

            // Validate CTA requirements
            if (ctaType === 'call' && !phone) {
              msg.textContent = 'CTA "Zadzwoń teraz" wymaga numeru telefonu';
              msg.style.color = '#b91c1c';
              return;
            }
            if (ctaType === 'website' && !website) {
              msg.textContent = 'CTA "Wejdź na naszą stronę" wymaga strony internetowej';
              msg.style.color = '#b91c1c';
              return;
            }
            if (!ctaType) {
              msg.textContent = 'Wybierz typ przycisku CTA';
              msg.style.color = '#b91c1c';
              return;
            }
          }

          // If date is provided, add end of day time (23:59:59)
          if (sponsoredUntil) {
            sponsoredUntil = sponsoredUntil + ' 23:59:59';
          }

          msg.textContent = 'Zapisywanie...';
          msg.style.color = '#666';
          saveBtn.disabled = true;

          // Use new AJAX endpoint for updating sponsored with date
          api('jg_admin_update_sponsored', {
            post_id: p.id,
            is_sponsored: isSponsored ? '1' : '0',
            sponsored_until: sponsoredUntil,
            website: website,
            phone: phone,
            cta_enabled: ctaEnabled ? '1' : '0',
            cta_type: ctaType
          })
            .then(function(result) {
              msg.textContent = 'Zapisano! Odświeżanie...';
              msg.style.color = '#15803d';
              return refreshAll();
            })
            .then(function() {
              close(modalStatus);
              close(modalView);
              // Find and reopen the point to show updated state
              var updatedPoint = ALL.find(function(x) { return x.id === p.id; });
              if (updatedPoint) {
                setTimeout(function() {
                  openDetails(updatedPoint);
                }, 200);
              }
            })
            .catch(function(err) {
              msg.textContent = 'Błąd: ' + (err.message || '?');
              msg.style.color = '#b91c1c';
              saveBtn.disabled = false;
            });
        };
      }

      // Modal for changing point owner with user search
      function openChangeOwnerModal(p, parentModal) {
        var currentPage = 1;
        var searchTerm = '';
        var selectedUserId = null;

        function renderUserList(users, total, page, totalPages) {
          var html = '';
          if (users.length === 0) {
            html = '<div style="padding:20px;text-align:center;color:#6b7280">Brak wyników</div>';
          } else {
            html = '<div style="display:flex;flex-direction:column;gap:8px">';
            users.forEach(function(user) {
              var isSelected = selectedUserId === user.id;
              html += '<div class="user-item" data-user-id="' + user.id + '" style="padding:12px;border:2px solid ' + (isSelected ? '#2563eb' : '#e5e7eb') + ';border-radius:8px;cursor:pointer;background:' + (isSelected ? '#eff6ff' : '#fff') + ';transition:all 0.2s">' +
                '<div style="display:flex;justify-content:space-between;align-items:center">' +
                '<div>' +
                '<div style="font-weight:600;color:#1f2937">' + esc(user.display_name) + '</div>' +
                '<div style="font-size:12px;color:#6b7280">' + esc(user.email) + '</div>' +
                '</div>' +
                '<div style="font-size:11px;color:#9ca3af">ID: ' + user.id + '</div>' +
                '</div>' +
                '</div>';
            });
            html += '</div>';

            // Pagination
            if (totalPages > 1) {
              html += '<div style="display:flex;justify-content:center;align-items:center;gap:8px;margin-top:16px;padding-top:16px;border-top:1px solid #e5e7eb">';
              html += '<button id="prev-page" class="jg-btn jg-btn--ghost" ' + (page <= 1 ? 'disabled' : '') + ' style="padding:6px 12px">← Poprzednia</button>';
              html += '<span style="color:#6b7280">Strona ' + page + ' z ' + totalPages + ' (' + total + ' użytkowników)</span>';
              html += '<button id="next-page" class="jg-btn jg-btn--ghost" ' + (page >= totalPages ? 'disabled' : '') + ' style="padding:6px 12px">Następna →</button>';
              html += '</div>';
            }
          }
          return html;
        }

        function loadUsers(page, search) {
          var listContainer = qs('#user-list-container');
          if (listContainer) {
            listContainer.innerHTML = '<div style="padding:20px;text-align:center;color:#6b7280">Ładowanie...</div>';
          }

          api('jg_admin_search_users', { page: page, search: search })
            .then(function(result) {
              if (listContainer) {
                listContainer.innerHTML = renderUserList(result.users, result.total, result.page, result.total_pages);
                attachUserListHandlers();
              }
            })
            .catch(function(err) {
              if (listContainer) {
                listContainer.innerHTML = '<div style="padding:20px;text-align:center;color:#dc2626">Błąd: ' + (err.message || '?') + '</div>';
              }
            });
        }

        function attachUserListHandlers() {
          var modal = qs('#change-owner-modal');
          if (!modal) return;

          // User selection
          var userItems = modal.querySelectorAll('.user-item');
          userItems.forEach(function(item) {
            item.onclick = function() {
              var userId = parseInt(this.getAttribute('data-user-id'), 10);
              selectedUserId = userId;
              // Update visual selection
              userItems.forEach(function(u) {
                u.style.borderColor = '#e5e7eb';
                u.style.background = '#fff';
              });
              this.style.borderColor = '#2563eb';
              this.style.background = '#eff6ff';
              // Enable save button
              var saveBtn = qs('#save-owner-btn', modal);
              if (saveBtn) saveBtn.disabled = false;
            };
          });

          // Pagination
          var prevBtn = qs('#prev-page', modal);
          var nextBtn = qs('#next-page', modal);
          if (prevBtn) {
            prevBtn.onclick = function() {
              if (currentPage > 1) {
                currentPage--;
                loadUsers(currentPage, searchTerm);
              }
            };
          }
          if (nextBtn) {
            nextBtn.onclick = function() {
              currentPage++;
              loadUsers(currentPage, searchTerm);
            };
          }
        }

        var modalHtml = '<div id="change-owner-modal" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10001;display:flex;align-items:center;justify-content:center">' +
          '<div style="background:#fff;border-radius:12px;width:90%;max-width:500px;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 20px 40px rgba(0,0,0,0.3)">' +
          '<div style="padding:16px 20px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center">' +
          '<h3 style="margin:0;font-size:18px;color:#1f2937">👤 Zmień właściciela</h3>' +
          '<button id="close-owner-modal" style="background:none;border:none;font-size:24px;cursor:pointer;color:#6b7280;padding:0;line-height:1">&times;</button>' +
          '</div>' +
          '<div style="padding:16px 20px;border-bottom:1px solid #e5e7eb">' +
          '<input type="text" id="user-search-input" placeholder="Szukaj użytkownika (nazwa, email)..." style="width:100%;padding:10px 14px;border:2px solid #e5e7eb;border-radius:8px;font-size:14px">' +
          '</div>' +
          '<div id="user-list-container" style="flex:1;overflow-y:auto;padding:16px 20px;min-height:200px">' +
          '<div style="padding:20px;text-align:center;color:#6b7280">Ładowanie...</div>' +
          '</div>' +
          '<div style="padding:16px 20px;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:8px">' +
          '<button id="cancel-owner-btn" class="jg-btn jg-btn--ghost">Anuluj</button>' +
          '<button id="save-owner-btn" class="jg-btn jg-btn--primary" disabled>Zmień właściciela</button>' +
          '</div>' +
          '</div>' +
          '</div>';

        document.body.insertAdjacentHTML('beforeend', modalHtml);
        var modal = qs('#change-owner-modal');

        // Load initial users
        loadUsers(1, '');

        // Search handler with debounce
        var searchInput = qs('#user-search-input', modal);
        var searchTimeout = null;
        if (searchInput) {
          searchInput.oninput = function() {
            clearTimeout(searchTimeout);
            var value = this.value.trim();
            searchTimeout = setTimeout(function() {
              searchTerm = value;
              currentPage = 1;
              selectedUserId = null;
              var saveBtn = qs('#save-owner-btn', modal);
              if (saveBtn) saveBtn.disabled = true;
              loadUsers(1, value);
            }, 300);
          };
          searchInput.focus();
        }

        // Close handlers
        var closeBtn = qs('#close-owner-modal', modal);
        var cancelBtn = qs('#cancel-owner-btn', modal);
        function closeModal() {
          if (modal && modal.parentNode) {
            modal.parentNode.removeChild(modal);
          }
        }
        if (closeBtn) closeBtn.onclick = closeModal;
        if (cancelBtn) cancelBtn.onclick = closeModal;
        modal.onclick = function(e) {
          if (e.target === modal) closeModal();
        };

        // Save handler
        var saveBtn = qs('#save-owner-btn', modal);
        if (saveBtn) {
          saveBtn.onclick = function() {
            if (!selectedUserId) return;

            saveBtn.disabled = true;
            saveBtn.textContent = 'Zapisywanie...';

            api('jg_admin_change_owner', { point_id: p.id, new_owner_id: selectedUserId })
              .then(function(result) {
                showAlert(result.message || 'Właściciel zmieniony');
                closeModal();
                return refreshAll();
              })
              .then(function() {
                close(parentModal);
                var updatedPoint = ALL.find(function(x) { return x.id === p.id; });
                if (updatedPoint) {
                  setTimeout(function() {
                    openDetails(updatedPoint);
                  }, 200);
                }
              })
              .catch(function(err) {
                showAlert('Błąd: ' + (err.message || '?'));
                saveBtn.disabled = false;
                saveBtn.textContent = 'Zmień właściciela';
              });
          };
        }
      }

      function openSponsoredModal(p) {
        return openPromoModal(p); // Backward compatibility wrapper
      }

      function openStatusModal(p) {
        var currentStatus = p.report_status || 'added';
        var html = '<header><h3>Zmień status</h3><button class="jg-close" id="status-close">&times;</button></header>' +
          '<div class="jg-grid" style="padding:16px">' +
          '<p>Wybierz status:</p>' +
          '<div style="display:flex;flex-direction:column;gap:12px;margin:16px 0">' +
          '<label style="display:flex;align-items:center;gap:8px;padding:12px;border:2px solid #e5e7eb;border-radius:8px;cursor:pointer">' +
          '<input type="radio" name="status" value="added" ' + (currentStatus === 'added' ? 'checked' : '') + ' style="width:20px;height:20px">' +
          '<div><strong>Dodane</strong></div>' +
          '</label>' +
          '<label style="display:flex;align-items:center;gap:8px;padding:12px;border:2px solid #e5e7eb;border-radius:8px;cursor:pointer">' +
          '<input type="radio" name="status" value="needs_better_documentation" ' + (currentStatus === 'needs_better_documentation' ? 'checked' : '') + ' style="width:20px;height:20px">' +
          '<div><strong>Wymaga lepszego udokumentowania</strong></div>' +
          '</label>' +
          '<label style="display:flex;align-items:center;gap:8px;padding:12px;border:2px solid #e5e7eb;border-radius:8px;cursor:pointer">' +
          '<input type="radio" name="status" value="reported" ' + (currentStatus === 'reported' ? 'checked' : '') + ' style="width:20px;height:20px">' +
          '<div><strong>Zgłoszone do instytucji</strong></div>' +
          '</label>' +
          '<label style="display:flex;align-items:center;gap:8px;padding:12px;border:2px solid #e5e7eb;border-radius:8px;cursor:pointer">' +
          '<input type="radio" name="status" value="resolved" ' + (currentStatus === 'resolved' ? 'checked' : '') + ' style="width:20px;height:20px">' +
          '<div><strong>Rozwiązane</strong></div>' +
          '</label>' +
          '<label style="display:flex;align-items:center;gap:8px;padding:12px;border:2px solid #e5e7eb;border-radius:8px;cursor:pointer">' +
          '<input type="radio" name="status" value="rejected" ' + (currentStatus === 'rejected' ? 'checked' : '') + ' style="width:20px;height:20px">' +
          '<div><strong>Odrzucono</strong></div>' +
          '</label>' +
          '</div>' +
          '<div id="resolved-summary-box" style="display:none;margin-top:12px;padding:12px;background:#d1fae5;border:2px solid #10b981;border-radius:8px">' +
          '<label style="display:block;margin-bottom:8px;font-weight:700;color:#065f46">Podsumowanie rozwiązania (wymagane):</label>' +
          '<textarea id="resolved-summary" style="width:100%;min-height:80px;padding:8px;border:1px solid #10b981;border-radius:4px;resize:vertical" placeholder="Opisz jak zostało rozwiązane zgłoszenie..."></textarea>' +
          '</div>' +
          '<div id="rejection-reason-box" style="display:none;margin-top:12px;padding:12px;background:#fee2e2;border:2px solid #ef4444;border-radius:8px">' +
          '<label style="display:block;margin-bottom:8px;font-weight:700;color:#991b1b">Powód odrzucenia (wymagane):</label>' +
          '<textarea id="rejection-reason" style="width:100%;min-height:80px;padding:8px;border:1px solid #ef4444;border-radius:4px;resize:vertical" placeholder="Wyjaśnij dlaczego zgłoszenie zostało odrzucone..."></textarea>' +
          '</div>' +
          '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">' +
          '<button type="button" class="jg-btn jg-btn--ghost" id="status-cancel">Anuluj</button>' +
          '<button type="button" class="jg-btn" id="status-save">Zapisz</button>' +
          '</div>' +
          '<div id="status-msg" style="margin-top:12px;font-size:12px"></div>' +
          '</div>';

        open(modalStatus, html);

        qs('#status-close', modalStatus).onclick = function() {
          close(modalStatus);
        };

        qs('#status-cancel', modalStatus).onclick = function() {
          close(modalStatus);
        };

        var msg = qs('#status-msg', modalStatus);
        var saveBtn = qs('#status-save', modalStatus);
        var resolvedSummaryBox = qs('#resolved-summary-box', modalStatus);
        var resolvedSummaryInput = qs('#resolved-summary', modalStatus);
        var rejectionReasonBox = qs('#rejection-reason-box', modalStatus);
        var rejectionReasonInput = qs('#rejection-reason', modalStatus);

        // Show/hide summary/reason fields based on selected status
        var radioButtons = modalStatus.querySelectorAll('input[name="status"]');
        radioButtons.forEach(function(radio) {
          radio.onchange = function() {
            if (radio.value === 'resolved') {
              resolvedSummaryBox.style.display = 'block';
              rejectionReasonBox.style.display = 'none';
            } else if (radio.value === 'rejected') {
              resolvedSummaryBox.style.display = 'none';
              rejectionReasonBox.style.display = 'block';
            } else {
              resolvedSummaryBox.style.display = 'none';
              rejectionReasonBox.style.display = 'none';
            }
          };
        });

        // Show resolved summary box if already resolved
        if (currentStatus === 'resolved') {
          resolvedSummaryBox.style.display = 'block';
          if (p.resolved_summary) {
            resolvedSummaryInput.value = p.resolved_summary;
          }
        }

        // Show rejection reason box if already rejected
        if (currentStatus === 'rejected') {
          rejectionReasonBox.style.display = 'block';
          if (p.rejected_reason) {
            rejectionReasonInput.value = p.rejected_reason;
          }
        }

        saveBtn.onclick = function() {
          var selected = qs('input[name="status"]:checked', modalStatus);
          if (!selected) {
            msg.textContent = 'Wybierz status';
            msg.style.color = '#b91c1c';
            return;
          }

          var newStatus = selected.value;

          // Validation: resolved summary is required for "resolved" status
          if (newStatus === 'resolved') {
            var resolvedSummary = resolvedSummaryInput.value.trim();
            if (!resolvedSummary) {
              msg.textContent = 'Podsumowanie rozwiązania jest wymagane';
              msg.style.color = '#b91c1c';
              resolvedSummaryInput.focus();
              return;
            }
          }

          // Validation: rejection reason is required for "rejected" status
          if (newStatus === 'rejected') {
            var rejectionReason = rejectionReasonInput.value.trim();
            if (!rejectionReason) {
              msg.textContent = 'Powód odrzucenia jest wymagany';
              msg.style.color = '#b91c1c';
              rejectionReasonInput.focus();
              return;
            }
          }

          if (newStatus === currentStatus) {
            close(modalStatus);
            return;
          }

          msg.textContent = 'Zapisywanie...';
          saveBtn.disabled = true;

          var requestData = { post_id: p.id, new_status: newStatus };
          if (newStatus === 'resolved') {
            requestData.resolved_summary = resolvedSummaryInput.value.trim();
          }
          if (newStatus === 'rejected') {
            requestData.rejection_reason = rejectionReasonInput.value.trim();
          }

          adminChangeStatus(requestData)
            .then(function(result) {
              msg.textContent = 'Zapisano! Odświeżanie...';
              msg.style.color = '#15803d';
              return refreshAll();
            })
            .then(function() {
              close(modalStatus);
              close(modalView);
              var updatedPoint = ALL.find(function(x) { return x.id === p.id; });
              if (updatedPoint) {
                setTimeout(function() {
                  openDetails(updatedPoint);
                }, 200);
              }
            })
            .catch(function(err) {
              msg.textContent = 'Błąd: ' + (err.message || '?');
              msg.style.color = '#b91c1c';
              saveBtn.disabled = false;
            });
        };
      }

      function openDetails(p) {
        // CRITICAL: Validate point exists before opening modal
        // Prevents showing deleted points when user hasn't refreshed yet
        $.ajax({
          url: CFG.ajax,
          type: 'POST',
          data: {
            action: 'jg_check_point_exists',
            _ajax_nonce: CFG.nonce,
            point_id: p.id
          },
          success: function(response) {
            if (!response.success || !response.data.exists) {
              // Point has been deleted - show message instead of modal
              showMessage(
                'To miejsce zostało usunięte',
                'Miejsce "' + esc(p.title || 'Bez tytułu') + '" zostało usunięte przez moderatora i nie jest już dostępne.',
                [
                  { text: 'OK, rozumiem', className: 'jg-btn jg-btn--primary', callback: function() {
                    // Refresh map to remove deleted point - FORCE refresh to ensure marker is removed
                    refreshData(true);
                  }}
                ]
              );
              return;
            }

            // Point exists - proceed with opening modal
            openDetailsModalContent(p);
          },
          error: function() {
            // Network error - assume point exists (fail-safe)
            debugWarn('[JG MAP] Could not validate point existence - opening anyway');
            openDetailsModalContent(p);
          }
        });
      }

      function openDetailsModalContent(p) {
        var imgs = Array.isArray(p.images) ? p.images : [];

        // Check if user can delete images (admin/moderator or own place)
        // FIX: Convert currentUserId to number for comparison (wp_localize_script converts to string)
        var canDeleteImages = CFG.isAdmin || (+CFG.currentUserId > 0 && +CFG.currentUserId === +p.author_id);

        var gal = imgs.map(function(img, idx) {
          // Support both old format (string URL) and new format (object with thumb/full)
          var thumbUrl = typeof img === 'object' ? (img.thumb || img.full) : img;
          var fullUrl = typeof img === 'object' ? (img.full || img.thumb) : img;

          // Featured image indicator
          var isFeatured = idx === (p.featured_image_index || 0);
          var featuredStar = '';
          if (canDeleteImages && imgs.length > 1) {
            // Show star button for admin/author when there are multiple images
            var starColor = isFeatured ? '#fbbf24' : '#fff';
            var starOpacity = isFeatured ? '1' : '0.7';
            featuredStar = '<button class="jg-set-featured-image" data-point-id="' + p.id + '" data-image-index="' + idx + '" ' +
              'style="position:absolute;top:4px;left:4px;background:rgba(0,0,0,0.6);color:' + starColor + ';border:none;border-radius:4px;width:28px;height:28px;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center;z-index:10;opacity:' + starOpacity + ';transition:all 0.2s" ' +
              'title="' + (isFeatured ? 'Wyróżniony obraz' : 'Ustaw jako wyróżniony') + '">★</button>';
          }

          var deleteBtn = '';
          if (canDeleteImages) {
            deleteBtn = '<button class="jg-delete-image" data-point-id="' + p.id + '" data-image-index="' + idx + '" style="position:absolute;top:4px;right:4px;background:rgba(220,38,38,0.9);color:#fff;border:none;border-radius:4px;width:24px;height:24px;cursor:pointer;font-weight:700;display:flex;align-items:center;justify-content:center;z-index:10" title="Usuń zdjęcie">×</button>';
          }

          return '<div style="position:relative;width:120px;height:120px;display:inline-block;margin:4px;border-radius:12px;overflow:hidden;border:2px solid ' + (isFeatured ? '#fbbf24' : '#e5e7eb') + ';box-shadow:0 2px 4px rgba(0,0,0,0.1)">' +
                 featuredStar +
                 deleteBtn +
                 '<img src="' + esc(thumbUrl) + '" data-full="' + esc(fullUrl) + '" alt="" loading="lazy" style="cursor:pointer;width:100%;height:100%;object-fit:cover">' +
                 '</div>';
        }).join('');

        var dateInfo = (p.date && p.date.human) ? '<div class="jg-date-info">Dodano: ' + esc(p.date.human) + '</div>' : '';

        var who = '';
        if (p.author_name && p.author_name.trim() !== '') {
          who = '<div><strong>Autor:</strong> <a href="#" id="btn-author" data-id="' + esc(p.author_id) + '" style="color:#2563eb;text-decoration:underline;cursor:pointer">' + esc(p.author_name) + '</a></div>';
        } else if (p.author_hidden || p.author_id > 0) {
          who = '<div><strong>Autor:</strong> ukryty</div>';
        }

        var adminNote = '';
        if (p.admin_note && p.admin_note.trim()) {
          adminNote = '<div class="jg-admin-note"><div class="jg-admin-note-title">📢 Notatka administratora</div><div class="jg-admin-note-content">' + esc(p.admin_note) + '</div></div>';
        }

        var resolvedNotice = '';
        if (p.report_status === 'resolved' && p.resolved_summary) {
          resolvedNotice = '<div style="background:#d1fae5;border:2px solid #10b981;border-radius:8px;padding:12px;margin:12px 0"><div style="font-weight:700;color:#065f46;margin-bottom:6px">✅ Zgłoszenie rozwiązane</div><div style="color:#064e3b;margin-bottom:8px">' + esc(p.resolved_summary) + '</div><div style="font-size:0.875rem;color:#065f46">Za 7 dni pinezka zostanie automatycznie usunięta z mapy.</div></div>';
        }

        var rejectedNotice = '';
        if (p.report_status === 'rejected' && p.rejected_reason) {
          rejectedNotice = '<div style="background:#fecaca;border:2px solid #ef4444;border-radius:8px;padding:12px;margin:12px 0"><div style="font-weight:700;color:#991b1b;margin-bottom:6px">🚫 Zgłoszenie odrzucone</div><div style="color:#7f1d1d;margin-bottom:8px">' + esc(p.rejected_reason) + '</div><div style="font-size:0.875rem;color:#991b1b">Za 7 dni pinezka zostanie automatycznie usunięta z mapy.</div></div>';
        }

        var editInfo = '';
        if (CFG.isAdmin && p.is_edit && p.edit_info) {
          var changes = [];
          if (p.edit_info.prev_title !== p.edit_info.new_title) {
            changes.push('<div><strong>Tytuł:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + esc(p.edit_info.prev_title) + '</span><br><span style="color:#16a34a">→ ' + esc(p.edit_info.new_title) + '</span></div>');
          }
          if (p.edit_info.prev_type !== p.edit_info.new_type) {
            var typeLabels = { zgloszenie: 'Zgłoszenie', ciekawostka: 'Ciekawostka', miejsce: 'Miejsce' };
            changes.push('<div><strong>Typ:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + (typeLabels[p.edit_info.prev_type] || p.edit_info.prev_type) + '</span><br><span style="color:#16a34a">→ ' + (typeLabels[p.edit_info.new_type] || p.edit_info.new_type) + '</span></div>');
          }
          // Show category changes (for reports)
          if (p.edit_info.prev_category !== undefined && p.edit_info.new_category !== undefined && p.edit_info.prev_category !== p.edit_info.new_category) {
            var categoryLabels = {
              'dziura_w_jezdni': '🕳️ Dziura w jezdni',
              'uszkodzone_chodniki': '🚶 Uszkodzone chodniki',
              'znaki_drogowe': '🚸 Brakujące lub zniszczone znaki drogowe',
              'oswietlenie': '💡 Awarie oświetlenia ulicznego',
              'dzikie_wysypisko': '🗑️ Dzikie wysypisko śmieci',
              'przepelniony_kosz': '♻️ Przepełniony kosz na śmieci',
              'graffiti': '🎨 Graffiti',
              'sliski_chodnik': '⚠️ Śliski chodnik',
              'nasadzenie_drzew': '🌳 Potrzeba nasadzenia drzew',
              'nieprzycięta_gałąź': '🌿 Nieprzycięta gałąź',
              'brak_przejscia': '🚦 Brak przejścia dla pieszych',
              'przystanek_autobusowy': '🚏 Potrzeba przystanku autobusowego',
              'organizacja_ruchu': '🚗 Problem z organizacją ruchu',
              'korki': '🚙 Powtarzające się korki',
              'mala_infrastruktura': '🎪 Propozycja nowych obiektów małej infrastruktury'
            };
            var prevCategory = p.edit_info.prev_category ? (categoryLabels[p.edit_info.prev_category] || formatCategorySlug(p.edit_info.prev_category)) : '(brak)';
            var newCategory = p.edit_info.new_category ? (categoryLabels[p.edit_info.new_category] || formatCategorySlug(p.edit_info.new_category)) : '(brak)';
            changes.push('<div><strong>Kategoria zgłoszenia:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + prevCategory + '</span><br><span style="color:#16a34a">→ ' + newCategory + '</span></div>');
          }
          if (p.edit_info.prev_content !== p.edit_info.new_content) {
            var prevContentText = p.edit_info.prev_content.replace(/<\/?[^>]+(>|$)/g, '');
            var newContentText = p.edit_info.new_content.replace(/<\/?[^>]+(>|$)/g, '');
            changes.push('<div><strong>Opis:</strong><br>' +
              '<div style="max-height:150px;overflow-y:auto;padding:8px;background:#fee;border-radius:4px;margin-top:4px">' +
              '<strong style="color:#dc2626">Poprzedni:</strong><br>' +
              (prevContentText ? esc(prevContentText) : '<em>brak</em>') +
              '</div>' +
              '<div style="max-height:150px;overflow-y:auto;padding:8px;background:#d1fae5;border-radius:4px;margin-top:8px">' +
              '<strong style="color:#16a34a">Nowy:</strong><br>' +
              (newContentText ? esc(newContentText) : '<em>brak</em>') +
              '</div>' +
              '</div>');
          }

          // Show website changes if present (for sponsored points)
          if (p.edit_info.prev_website !== undefined && p.edit_info.new_website !== undefined && p.edit_info.prev_website !== p.edit_info.new_website) {
            changes.push('<div><strong>🌐 Strona internetowa:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + (p.edit_info.prev_website || '(brak)') + '</span><br><span style="color:#16a34a">→ ' + (p.edit_info.new_website || '(brak)') + '</span></div>');
          }

          // Show phone changes if present (for sponsored points)
          if (p.edit_info.prev_phone !== undefined && p.edit_info.new_phone !== undefined && p.edit_info.prev_phone !== p.edit_info.new_phone) {
            changes.push('<div><strong>📞 Telefon:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + (p.edit_info.prev_phone || '(brak)') + '</span><br><span style="color:#16a34a">→ ' + (p.edit_info.new_phone || '(brak)') + '</span></div>');
          }

          // Show Facebook changes if present (for sponsored points)
          if (p.edit_info.prev_facebook_url !== undefined && p.edit_info.new_facebook_url !== undefined && p.edit_info.prev_facebook_url !== p.edit_info.new_facebook_url) {
            changes.push('<div><strong>Facebook:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + (p.edit_info.prev_facebook_url || '(brak)') + '</span><br><span style="color:#16a34a">→ ' + (p.edit_info.new_facebook_url || '(brak)') + '</span></div>');
          }

          // Show Instagram changes if present (for sponsored points)
          if (p.edit_info.prev_instagram_url !== undefined && p.edit_info.new_instagram_url !== undefined && p.edit_info.prev_instagram_url !== p.edit_info.new_instagram_url) {
            changes.push('<div><strong>Instagram:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + (p.edit_info.prev_instagram_url || '(brak)') + '</span><br><span style="color:#16a34a">→ ' + (p.edit_info.new_instagram_url || '(brak)') + '</span></div>');
          }

          // Show LinkedIn changes if present (for sponsored points)
          if (p.edit_info.prev_linkedin_url !== undefined && p.edit_info.new_linkedin_url !== undefined && p.edit_info.prev_linkedin_url !== p.edit_info.new_linkedin_url) {
            changes.push('<div><strong>LinkedIn:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + (p.edit_info.prev_linkedin_url || '(brak)') + '</span><br><span style="color:#16a34a">→ ' + (p.edit_info.new_linkedin_url || '(brak)') + '</span></div>');
          }

          // Show TikTok changes if present (for sponsored points)
          if (p.edit_info.prev_tiktok_url !== undefined && p.edit_info.new_tiktok_url !== undefined && p.edit_info.prev_tiktok_url !== p.edit_info.new_tiktok_url) {
            changes.push('<div><strong>TikTok:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + (p.edit_info.prev_tiktok_url || '(brak)') + '</span><br><span style="color:#16a34a">→ ' + (p.edit_info.new_tiktok_url || '(brak)') + '</span></div>');
          }

          // Show CTA changes if present (for sponsored points)
          if (p.edit_info.prev_cta_enabled !== undefined && p.edit_info.new_cta_enabled !== undefined && p.edit_info.prev_cta_enabled !== p.edit_info.new_cta_enabled) {
            var prevCta = p.edit_info.prev_cta_enabled ? 'Włączone' : 'Wyłączone';
            var newCta = p.edit_info.new_cta_enabled ? 'Włączone' : 'Wyłączone';
            changes.push('<div><strong>🎯 CTA włączone:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + prevCta + '</span><br><span style="color:#16a34a">→ ' + newCta + '</span></div>');
          }

          // Show CTA type changes if present (for sponsored points)
          if (p.edit_info.prev_cta_type !== undefined && p.edit_info.new_cta_type !== undefined && p.edit_info.prev_cta_type !== p.edit_info.new_cta_type) {
            var ctaTypeLabels = {
              call: '📞 Zadzwoń teraz',
              website: '🌐 Wejdź na stronę',
              facebook: '📘 Odwiedź nas na Facebooku',
              instagram: '📷 Sprawdź nas na Instagramie',
              linkedin: '💼 Zobacz nas na LinkedIn',
              tiktok: '🎵 Obserwuj nas na TikToku'
            };
            var prevType = ctaTypeLabels[p.edit_info.prev_cta_type] || '(brak)';
            var newType = ctaTypeLabels[p.edit_info.new_cta_type] || '(brak)';
            changes.push('<div><strong>🎯 Typ CTA:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + prevType + '</span><br><span style="color:#16a34a">→ ' + newType + '</span></div>');
          }

          // Show new images if present
          if (p.edit_info.new_images && p.edit_info.new_images.length > 0) {
            var newImagesHtml = '<div><strong>Nowe zdjęcia (' + p.edit_info.new_images.length + '):</strong><br>' +
              '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:8px;margin-top:8px">';
            p.edit_info.new_images.forEach(function(img) {
              var thumbUrl = typeof img === 'object' ? (img.thumb || img.full) : img;
              newImagesHtml += '<div style="position:relative;aspect-ratio:1;border-radius:8px;overflow:hidden;border:2px solid #16a34a">' +
                '<img src="' + esc(thumbUrl) + '" style="width:100%;height:100%;object-fit:cover" alt="Nowe zdjęcie">' +
                '</div>';
            });
            newImagesHtml += '</div></div>';
            changes.push(newImagesHtml);
          }

          if (changes.length > 0) {
            // Build approval status info for external edits (multi-stage approval)
            var approvalStatusHtml = '';
            if (p.edit_info.is_external_edit && p.edit_info.requires_owner_approval) {
              var ownerStatusAdmin = '';
              var adminStatusAdmin = '';

              if (p.edit_info.owner_approval_status === 'approved') {
                ownerStatusAdmin = '<div style="display:flex;align-items:center;gap:6px"><span style="color:#16a34a;font-size:16px">✓</span><span>Właściciel <strong style="color:#16a34a">zaakceptował</strong></span></div>';
                adminStatusAdmin = '<div style="display:flex;align-items:center;gap:6px"><span style="color:#f59e0b;font-size:16px">⏳</span><span>Moderator <strong style="color:#f59e0b">oczekuje na Twoją decyzję</strong></span></div>';
              } else {
                ownerStatusAdmin = '<div style="display:flex;align-items:center;gap:6px"><span style="color:#f59e0b;font-size:16px">⏳</span><span>Właściciel <strong style="color:#f59e0b">jeszcze nie zaakceptował</strong></span></div>';
                adminStatusAdmin = '<div style="display:flex;align-items:center;gap:6px"><span style="color:#9ca3af;font-size:16px">○</span><span style="color:#9ca3af">Moderator (czeka na właściciela)</span></div>';
              }

              approvalStatusHtml = '<div style="background:#f3e8ff;padding:10px;border-radius:6px;margin-top:12px;border:1px solid #e9d5ff">' +
                '<div style="font-size:12px;color:#7c3aed;font-weight:600;margin-bottom:8px">📋 Edycja zewnętrzna - status akceptacji:</div>' +
                '<div style="display:flex;flex-direction:column;gap:6px;font-size:13px">' +
                ownerStatusAdmin +
                adminStatusAdmin +
                '</div>' +
                '</div>';
            }

            editInfo = '<div style="background:#faf5ff;border:2px solid #9333ea;border-radius:8px;padding:12px;margin:16px 0">' +
              '<div style="font-weight:700;margin-bottom:8px;color:#6b21a8">📝 Zmiany oczekujące od: <strong>' + esc(p.edit_info.editor_name || 'Nieznany użytkownik') + '</strong></div>' +
              '<div style="font-size:12px;color:#7c3aed;margin-bottom:8px">Edytowano ' + esc(p.edit_info.edited_at) + '</div>' +
              changes.join('<hr style="margin:12px 0;border:none;border-top:1px solid #e9d5ff">') +
              approvalStatusHtml +
              '</div>';
          }
        }

        // Show rejection reason to place owner (not admin)
        if (!CFG.isAdmin && p.is_own_place && p.is_edit && p.edit_info && p.edit_info.status === 'rejected' && p.edit_info.rejection_reason) {
          editInfo = '<div style="background:#fef2f2;border:2px solid #ef4444;border-radius:8px;padding:12px;margin:16px 0">' +
            '<div style="font-weight:700;margin-bottom:8px;color:#991b1b">❌ Twoja edycja została odrzucona (' + esc(p.edit_info.rejected_at) + ')</div>' +
            '<div style="background:#fff;padding:10px;border-radius:6px;border-left:4px solid #ef4444"><strong>Uzasadnienie moderatora:</strong><br>' + esc(p.edit_info.rejection_reason) + '</div>' +
            '</div>';
        }

        // Show pending edit status to the editor who submitted the edit (not owner, not admin)
        if (!CFG.isAdmin && !p.is_own_place && p.is_edit && p.edit_info && p.edit_info.is_my_edit) {
          var ownerStatus = '';
          var adminStatus = '';

          if (p.edit_info.requires_owner_approval) {
            if (p.edit_info.owner_approval_status === 'approved') {
              ownerStatus = '<div style="display:flex;align-items:center;gap:6px"><span style="color:#16a34a;font-size:18px">✓</span><span>Właściciel <strong style="color:#16a34a">zaakceptował</strong></span></div>';
              adminStatus = '<div style="display:flex;align-items:center;gap:6px"><span style="color:#f59e0b;font-size:18px">⏳</span><span>Moderator <strong style="color:#f59e0b">oczekuje</strong></span></div>';
            } else {
              ownerStatus = '<div style="display:flex;align-items:center;gap:6px"><span style="color:#f59e0b;font-size:18px">⏳</span><span>Właściciel <strong style="color:#f59e0b">oczekuje</strong></span></div>';
              adminStatus = '<div style="display:flex;align-items:center;gap:6px"><span style="color:#9ca3af;font-size:18px">○</span><span style="color:#9ca3af">Moderator (czeka na właściciela)</span></div>';
            }
          } else {
            adminStatus = '<div style="display:flex;align-items:center;gap:6px"><span style="color:#f59e0b;font-size:18px">⏳</span><span>Moderator <strong style="color:#f59e0b">oczekuje</strong></span></div>';
          }

          editInfo = '<div style="background:#faf5ff;border:2px solid #9333ea;border-radius:8px;padding:12px;margin:16px 0">' +
            '<div style="font-weight:700;margin-bottom:8px;color:#6b21a8">📝 Twoja propozycja zmian oczekuje na zatwierdzenie</div>' +
            '<div style="font-size:12px;color:#7c3aed;margin-bottom:12px">Zgłoszone ' + esc(p.edit_info.edited_at) + '</div>' +
            '<div style="background:#f3e8ff;padding:12px;border-radius:8px;display:flex;flex-direction:column;gap:8px">' +
            ownerStatus +
            adminStatus +
            '</div>' +
            '</div>';
        }

        // Show external edit info to place owner (when someone else edited their place)
        // Works for both regular owners and admin-owners
        var isOwnerViewingExternalEdit = p.is_own_place && p.is_edit && p.edit_info && p.edit_info.is_external_edit && p.edit_info.owner_approval_status === 'pending';
        if (isOwnerViewingExternalEdit) {
          var ownerChanges = [];
          if (p.edit_info.prev_title !== p.edit_info.new_title) {
            ownerChanges.push('<div><strong>Tytuł:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + esc(p.edit_info.prev_title) + '</span><br><span style="color:#16a34a">→ ' + esc(p.edit_info.new_title) + '</span></div>');
          }
          if (p.edit_info.prev_type !== p.edit_info.new_type) {
            var typeLabelsOwner = { zgloszenie: 'Zgłoszenie', ciekawostka: 'Ciekawostka', miejsce: 'Miejsce' };
            ownerChanges.push('<div><strong>Typ:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + (typeLabelsOwner[p.edit_info.prev_type] || p.edit_info.prev_type) + '</span><br><span style="color:#16a34a">→ ' + (typeLabelsOwner[p.edit_info.new_type] || p.edit_info.new_type) + '</span></div>');
          }
          if (p.edit_info.prev_content !== p.edit_info.new_content) {
            var prevContentOwner = p.edit_info.prev_content.replace(/<\/?[^>]+(>|$)/g, '');
            var newContentOwner = p.edit_info.new_content.replace(/<\/?[^>]+(>|$)/g, '');
            ownerChanges.push('<div><strong>Opis:</strong><br>' +
              '<div style="max-height:100px;overflow-y:auto;padding:8px;background:#fee;border-radius:4px;margin-top:4px">' +
              '<strong style="color:#dc2626">Poprzedni:</strong><br>' + (prevContentOwner ? esc(prevContentOwner) : '<em>brak</em>') + '</div>' +
              '<div style="max-height:100px;overflow-y:auto;padding:8px;background:#d1fae5;border-radius:4px;margin-top:8px">' +
              '<strong style="color:#16a34a">Nowy:</strong><br>' + (newContentOwner ? esc(newContentOwner) : '<em>brak</em>') + '</div>' +
              '</div>');
          }
          if (p.edit_info.new_images && p.edit_info.new_images.length > 0) {
            var ownerImagesHtml = '<div><strong>Nowe zdjęcia (' + p.edit_info.new_images.length + '):</strong><br>' +
              '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(80px,1fr));gap:8px;margin-top:8px">';
            p.edit_info.new_images.forEach(function(img) {
              var thumbUrl = typeof img === 'object' ? (img.thumb || img.full) : img;
              ownerImagesHtml += '<div style="position:relative;aspect-ratio:1;border-radius:8px;overflow:hidden;border:2px solid #16a34a">' +
                '<img src="' + esc(thumbUrl) + '" style="width:100%;height:100%;object-fit:cover" alt="Nowe zdjęcie"></div>';
            });
            ownerImagesHtml += '</div></div>';
            ownerChanges.push(ownerImagesHtml);
          }

          editInfo = '<div style="background:#faf5ff;border:2px solid #9333ea;border-radius:8px;padding:12px;margin:16px 0">' +
            '<div style="font-weight:700;margin-bottom:8px;color:#6b21a8">📝 Propozycja zmian od użytkownika <strong>' + esc(p.edit_info.editor_name) + '</strong></div>' +
            '<div style="font-size:12px;color:#7c3aed;margin-bottom:12px">Zgłoszone ' + esc(p.edit_info.edited_at) + '</div>' +
            (ownerChanges.length > 0 ? ownerChanges.join('<hr style="margin:12px 0;border:none;border-top:1px solid #e9d5ff">') : '<div style="color:#6b21a8">Brak zmian w podstawowych polach</div>') +
            '<div style="margin-top:16px;padding-top:12px;border-top:2px solid #e9d5ff;display:flex;gap:8px;flex-wrap:wrap">' +
            '<button class="jg-btn jg-btn--primary" id="btn-owner-approve-edit" data-history-id="' + p.edit_info.history_id + '">✓ Zatwierdź zmiany</button>' +
            '<button class="jg-btn jg-btn--danger" id="btn-owner-reject-edit" data-history-id="' + p.edit_info.history_id + '">✗ Odrzuć</button>' +
            '</div>' +
            '</div>';
        }

        // Show status to owner after they approved but admin hasn't yet
        // This includes any owner_approval_status that is NOT 'pending' (handles 'approved' and any other value)
        var isOwnerWaitingForAdmin = !CFG.isAdmin && p.is_own_place && p.is_edit && p.edit_info && p.edit_info.is_external_edit && p.edit_info.owner_approval_status && p.edit_info.owner_approval_status !== 'pending';
        if (isOwnerWaitingForAdmin) {
          editInfo = '<div style="background:#faf5ff;border:2px solid #9333ea;border-radius:8px;padding:12px;margin:16px 0">' +
            '<div style="font-weight:700;margin-bottom:8px;color:#6b21a8">📝 Propozycja zmian od użytkownika <strong>' + esc(p.edit_info.editor_name) + '</strong></div>' +
            '<div style="font-size:12px;color:#7c3aed;margin-bottom:12px">Zgłoszone ' + esc(p.edit_info.edited_at) + '</div>' +
            '<div style="background:#f3e8ff;padding:12px;border-radius:8px;display:flex;flex-direction:column;gap:8px">' +
            '<div style="display:flex;align-items:center;gap:6px"><span style="color:#16a34a;font-size:18px">✓</span><span>Ty (właściciel) <strong style="color:#16a34a">zaakceptowałeś</strong></span></div>' +
            '<div style="display:flex;align-items:center;gap:6px"><span style="color:#f59e0b;font-size:18px">⏳</span><span>Moderator <strong style="color:#f59e0b">jeszcze nie zatwierdził</strong></span></div>' +
            '</div>' +
            '<div style="font-size:12px;color:#7c3aed;margin-top:12px;font-style:italic">Zmiany zostaną wprowadzone po zatwierdzeniu przez moderatora.</div>' +
            '</div>';
        }

        // Deletion request info
        var deletionInfo = '';
        if (CFG.isAdmin && p.is_deletion_requested && p.deletion_info) {
          deletionInfo = '<div style="background:#fef2f2;border:2px solid #dc2626;border-radius:8px;padding:12px;margin:16px 0">' +
            '<div style="font-weight:700;margin-bottom:8px;color:#991b1b">🗑️ Zgłoszenie usunięcia (zgłoszono ' + esc(p.deletion_info.requested_at) + ')</div>';

          if (p.deletion_info.reason && p.deletion_info.reason.trim()) {
            deletionInfo += '<div><strong>Powód:</strong> ' + esc(p.deletion_info.reason) + '</div>';
          }

          deletionInfo += '</div>';
        }

        // Show deletion rejection reason to place owner (not admin)
        if (!CFG.isAdmin && p.is_own_place && p.deletion_info && p.deletion_info.status === 'rejected' && p.deletion_info.rejection_reason) {
          deletionInfo = '<div style="background:#fef2f2;border:2px solid #ef4444;border-radius:8px;padding:12px;margin:16px 0">' +
            '<div style="font-weight:700;margin-bottom:8px;color:#991b1b">❌ Twoje zgłoszenie usunięcia zostało odrzucone (' + esc(p.deletion_info.rejected_at) + ')</div>' +
            '<div style="background:#fff;padding:10px;border-radius:6px;border-left:4px solid #ef4444"><strong>Uzasadnienie moderatora:</strong><br>' + esc(p.deletion_info.rejection_reason) + '</div>' +
            '</div>';
        }

        var reportsWarning = '';
        if (CFG.isAdmin && p.reports_count > 0) {
          reportsWarning = '<div class="jg-reports-warning">' +
            '<div class="jg-reports-warning-title">⚠️ Zgłoszeń: ' + p.reports_count + '</div>' +
            '<button class="jg-btn" id="btn-view-reports" style="margin-top:8px">Zobacz zgłoszenia</button>' +
            '</div>';
        }

        // User report notice - displayed to user who reported this place
        var userReportNotice = '';
        if (p.user_has_reported && p.reporter_info && p.reporter_info.reported_at) {
          var reporterName = p.reporter_info.reporter_name || 'Ty';
          userReportNotice = '<div style="background:#fee2e2;border:2px solid #dc2626;border-radius:8px;padding:12px;margin:12px 0">' +
            '<div style="color:#991b1b;font-weight:700;margin-bottom:4px">⚠️ Miejsce zgłoszone do moderacji</div>' +
            '<div style="color:#7f1d1d;font-size:14px">Zgłoszone przez: <strong>' + esc(reporterName) + '</strong></div>' +
            '<div style="color:#7f1d1d;font-size:14px">Zgłoszono: ' + esc(p.reporter_info.reported_at) + '</div>' +
            '<div style="color:#7f1d1d;font-size:13px;margin-top:4px;opacity:0.9">Twoje zgłoszenie zostanie rozpatrzone przez moderatorów.</div>' +
            '</div>';
        }

        var adminBox = '';
        if (CFG.isAdmin) {
          var adminData = [];
          if (p.admin) {
            // Author name as clickable link with 3-dot menu button
            adminData.push('<div style="display:flex;align-items:center;gap:8px"><div><strong>Autor:</strong> <a href="#" id="btn-author-admin" data-user-id="' + esc(p.author_id) + '" style="color:#2563eb;text-decoration:underline;cursor:pointer">' + esc(p.admin.author_name_real || '?') + '</a></div><button id="btn-user-actions" class="jg-btn jg-btn--ghost" style="padding:2px 8px;font-size:16px;line-height:1" title="Akcje użytkownika">⋮</button></div>');
            adminData.push('<div><strong>Email:</strong> ' + esc(p.admin.author_email || '?') + '</div>');
            if (p.admin.ip && p.admin.ip !== '(brak)' && p.admin.ip.trim() !== '') {
              adminData.push('<div><strong>IP:</strong> ' + esc(p.admin.ip) + '</div>');
            }
          }

          adminData.push('<div><strong>Status:</strong> ' + esc(p.status) + '</div>');

          // ZAKOLEJKOWANE ZMIANY MODERACYJNE - pokazuje wszystkie pending changes
          var pendingChanges = [];
          var pendingCount = 0;

          // 1. Pending punkt (nowe miejsce czeka na akceptację)
          if (p.is_pending) {
            pendingCount++;
            pendingChanges.push(
              '<div style="background:#fef3c7;border-left:4px solid #f59e0b;padding:12px;border-radius:6px;margin-bottom:8px">' +
              '<div style="display:flex;justify-content:space-between;align-items:start;gap:12px">' +
              '<div style="flex:1">' +
              '<div style="font-weight:700;color:#92400e;margin-bottom:4px">➕ Nowe miejsce oczekuje</div>' +
              '<div style="font-size:13px;color:#78350f">Status: <strong>pending</strong> - miejsce musi być zaakceptowane przez moderatora</div>' +
              '</div>' +
              '<div style="display:flex;gap:6px;flex-shrink:0">' +
              '<button class="jg-btn" id="btn-approve-point" style="background:#15803d;padding:8px 12px;font-size:13px;white-space:nowrap">✓ Akceptuj</button>' +
              '<button class="jg-btn" id="btn-reject-point" style="background:#b91c1c;padding:8px 12px;font-size:13px;white-space:nowrap">✗ Odrzuć</button>' +
              '</div></div></div>'
            );
          }

          // 2. Pending edycja
          if (p.is_edit && p.edit_info) {
            pendingCount++;
            var editedAt = p.edit_info.edited_at || 'niedawno';
            pendingChanges.push(
              '<div style="background:#faf5ff;border-left:4px solid #9333ea;padding:12px;border-radius:6px;margin-bottom:8px">' +
              '<div style="display:flex;justify-content:space-between;align-items:start;gap:12px">' +
              '<div style="flex:1">' +
              '<div style="font-weight:700;color:#6b21a8;margin-bottom:4px">📝 Edycja oczekuje</div>' +
              '<div style="font-size:13px;color:#7e22ce">Edytowano: <strong>' + esc(editedAt) + '</strong></div>' +
              '</div>' +
              '<div style="display:flex;gap:6px;flex-shrink:0">' +
              '<button class="jg-btn" id="btn-approve-edit" style="background:#15803d;padding:8px 12px;font-size:13px;white-space:nowrap">✓ Akceptuj edycję</button>' +
              '<button class="jg-btn" id="btn-reject-edit" style="background:#b91c1c;padding:8px 12px;font-size:13px;white-space:nowrap">✗ Odrzuć edycję</button>' +
              '</div></div></div>'
            );
          }

          // 3. Pending usunięcie
          if (p.is_deletion_requested && p.deletion_info) {
            pendingCount++;
            var deletionReason = p.deletion_info.reason || '(brak powodu)';
            var requestedAt = p.deletion_info.requested_at || 'niedawno';
            pendingChanges.push(
              '<div style="background:#fee2e2;border-left:4px solid #dc2626;padding:12px;border-radius:6px;margin-bottom:8px">' +
              '<div style="display:flex;justify-content:space-between;align-items:start;gap:12px">' +
              '<div style="flex:1">' +
              '<div style="font-weight:700;color:#991b1b;margin-bottom:4px">🗑️ Prośba o usunięcie</div>' +
              '<div style="font-size:13px;color:#b91c1c">Zgłoszono: <strong>' + esc(requestedAt) + '</strong></div>' +
              '<div style="font-size:12px;color:#7f1d1d;margin-top:4px;font-style:italic">' + esc(deletionReason) + '</div>' +
              '</div>' +
              '<div style="display:flex;gap:6px;flex-shrink:0">' +
              '<button class="jg-btn" id="btn-approve-deletion" style="background:#15803d;padding:8px 12px;font-size:13px;white-space:nowrap">✓ Zatwierdź usunięcie</button>' +
              '<button class="jg-btn" id="btn-reject-deletion" style="background:#b91c1c;padding:8px 12px;font-size:13px;white-space:nowrap">✗ Odrzuć usunięcie</button>' +
              '</div></div></div>'
            );
          }

          // Buduj sekcję zmian moderacyjnych jeśli są jakieś zmiany
          var moderationQueue = '';
          if (pendingCount > 0) {
            var countBadge = pendingCount === 1
              ? '<span style="background:#dc2626;color:#fff;padding:4px 10px;border-radius:12px;font-size:13px;font-weight:700">1 zmiana oczekuje</span>'
              : '<span style="background:#dc2626;color:#fff;padding:4px 10px;border-radius:12px;font-size:13px;font-weight:700">' + pendingCount + ' zmiany oczekują</span>';

            moderationQueue = '<div style="background:#f9fafb;border:2px solid #dc2626;border-radius:8px;padding:16px;margin:16px 0">' +
              '<div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">' +
              '<div style="font-size:16px;font-weight:700;color:#1f2937">⚠️ Zakolejkowane zmiany moderacyjne</div>' +
              countBadge +
              '</div>' +
              '<div style="font-size:13px;color:#6b7280;margin-bottom:12px">Poniżej znajdują się wszystkie oczekujące zmiany dla tego miejsca. Po rozwiązaniu każdej zmiany zniknie ona z listy.</div>' +
              pendingChanges.join('') +
              '</div>';
          }

          // Show sponsored until date for admins
          if (p.sponsored && p.sponsored_until) {
            var sponsoredDate = new Date(p.sponsored_until);
            var dateStr = sponsoredDate.toLocaleDateString('pl-PL', { year: 'numeric', month: 'long', day: 'numeric' });
            adminData.push('<div style="color:#f59e0b;font-weight:700">⭐ Sponsorowane do: ' + dateStr + '</div>');
          } else if (p.sponsored) {
            adminData.push('<div style="color:#f59e0b;font-weight:700">⭐ Sponsorowane bezterminowo</div>');
          }

          // Dodaj sekcję zmian moderacyjnych do panelu admina
          if (moderationQueue) {
            adminData.push(moderationQueue);
          }

          // Kontrolki administracyjne (bez duplikatów pending buttons - są w moderationQueue)
          var controls = '<div class="jg-admin-controls">';

          controls += '<button class="jg-btn jg-btn--ghost" id="btn-toggle-sponsored">' + (p.sponsored ? 'Usuń sponsorowanie' : 'Sponsorowane') + '</button>';
          controls += '<button class="jg-btn jg-btn--ghost" id="btn-toggle-author">' + (p.author_hidden ? 'Ujawnij' : 'Ukryj') + ' autora</button>';
          controls += '<button class="jg-btn jg-btn--ghost" id="btn-toggle-edit-lock">' + (p.edit_locked ? '🔓 Odblokuj edycję' : '🔒 Zablokuj edycję') + '</button>';
          controls += '<button class="jg-btn jg-btn--ghost" id="btn-change-owner">👤 Zmień właściciela</button>';
          if (p.type === 'zgloszenie') {
            controls += '<button class="jg-btn jg-btn--ghost" id="btn-change-status">Zmień status</button>';
          }
          controls += '<button class="jg-btn jg-btn--ghost" id="btn-admin-note">' + (p.admin_note ? 'Edytuj' : 'Dodaj') + ' notatkę</button>';
          controls += '<button class="jg-btn jg-btn--danger" id="btn-delete-point">🗑️ Usuń miejsce</button>';
          controls += '</div>';

          adminBox = '<div class="jg-admin-panel"><div class="jg-admin-panel-title">Panel Administratora</div>' + adminData.join('') + controls + '</div>';
        }

        var promoClass = p.sponsored ? ' jg-modal--promo' : '';
        var typeClass = ' jg-modal--' + (p.type || 'zgloszenie');
        // FIX: Convert currentUserId to number for comparison (wp_localize_script converts to string)
        var isOwnPoint = +CFG.currentUserId > 0 && +CFG.currentUserId === +p.author_id;
        // Anyone logged in can edit non-sponsored places (edits to others' places require approval)
        // Sponsored places can only be edited by owner or admin
        // Edit-locked places can only be edited by admins
        var canEdit = CFG.isAdmin || (isOwnPoint && !p.edit_locked) || (+CFG.currentUserId > 0 && !p.sponsored && !p.edit_locked);
        var myVote = p.my_vote || '';

        // Don't show voting for promo points or own points
        var voteHtml = '';
        if (!p.sponsored && !isOwnPoint) {
          voteHtml = '<div class="jg-vote"><button id="v-up" ' + (myVote === 'up' ? 'class="active"' : '') + '>⬆️</button><span class="cnt" id="v-cnt" style="' + colorForVotes(+p.votes || 0) + '">' + (p.votes || 0) + '</span><button id="v-down" ' + (myVote === 'down' ? 'class="active"' : '') + '>⬇️</button></div>';
        } else if (!p.sponsored && isOwnPoint) {
          // Show compact vote count for own points (no voting buttons)
          voteHtml = '<div class="jg-vote jg-vote--own"><span class="jg-vote-own-icon">🗳️</span><span class="cnt" id="v-cnt" style="' + colorForVotes(+p.votes || 0) + '">' + (p.votes || 0) + '</span><span class="jg-vote-own-label">głosów</span></div>';
        }

        // Community verification badge (based on votes)
        var verificationBadge = '';
        if (p.votes && !p.sponsored) {
          if (+p.votes >= 50) {
            verificationBadge = '<div style="padding:10px;background:#d1fae5;border:2px solid #10b981;border-radius:8px;margin:10px 0;text-align:center"><strong style="color:#065f46">✅ Zweryfikowane pozytywnie przez społeczność Jeleniej Góry</strong><div style="font-size:12px;color:#047857;margin-top:4px">To zgłoszenie otrzymało ponad 50 pozytywnych głosów od społeczności</div></div>';
          } else if (+p.votes <= -50) {
            verificationBadge = '<div style="padding:10px;background:#fee2e2;border:2px solid #ef4444;border-radius:8px;margin:10px 0;text-align:center"><strong style="color:#991b1b">⚠️ Zweryfikowane negatywnie przez społeczność Jeleniej Góry</strong><div style="font-size:12px;color:#b91c1c;margin-top:4px">To zgłoszenie ma ponad 50 negatywnych głosów od społeczności</div></div>';
          }
        }

        // Contact info for sponsored points
        var contactInfo = '';
        if (p.sponsored && (p.website || p.phone || p.facebook_url || p.instagram_url || p.linkedin_url || p.tiktok_url)) {
          var contactItems = [];
          if (p.website) {
            var websiteUrl = p.website.startsWith('http') ? p.website : 'https://' + p.website;
            contactItems.push('<div><strong>🌐 Strona:</strong> <a href="' + esc(websiteUrl) + '" target="_blank" rel="noopener" style="color:#2563eb;text-decoration:underline">' + esc(p.website) + '</a></div>');
          }
          if (p.phone) {
            contactItems.push('<div><strong>📞 Telefon:</strong> <a href="tel:' + esc(p.phone) + '" style="color:#2563eb;text-decoration:underline">' + esc(p.phone) + '</a></div>');
          }

          // Social media icons with authentic SVG logos
          var socialIcons = [];
          if (p.facebook_url) {
            var fbUrl = p.facebook_url.startsWith('http') ? p.facebook_url : 'https://' + p.facebook_url;
            socialIcons.push('<a href="' + esc(fbUrl) + '" target="_blank" rel="noopener" data-social="facebook" data-point-id="' + p.id + '" class="jg-social-link" style="display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;background:#1877f2;color:#fff;border-radius:50%;text-decoration:none;transition:transform 0.2s,box-shadow 0.2s;box-shadow:0 2px 8px rgba(24,119,242,0.3)" title="Facebook" onmouseover="this.style.transform=\'scale(1.1)\';this.style.boxShadow=\'0 4px 12px rgba(24,119,242,0.5)\'" onmouseout="this.style.transform=\'scale(1)\';this.style.boxShadow=\'0 2px 8px rgba(24,119,242,0.3)\'"><svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>');
          }
          if (p.instagram_url) {
            var igUrl = p.instagram_url.startsWith('http') ? p.instagram_url : 'https://' + p.instagram_url;
            socialIcons.push('<a href="' + esc(igUrl) + '" target="_blank" rel="noopener" data-social="instagram" data-point-id="' + p.id + '" class="jg-social-link" style="display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;background:linear-gradient(45deg, #f09433 0%,#e6683c 25%,#dc2743 50%,#cc2366 75%,#bc1888 100%);color:#fff;border-radius:50%;text-decoration:none;transition:transform 0.2s,box-shadow 0.2s;box-shadow:0 2px 8px rgba(225,48,108,0.3)" title="Instagram" onmouseover="this.style.transform=\'scale(1.1)\';this.style.boxShadow=\'0 4px 12px rgba(225,48,108,0.5)\'" onmouseout="this.style.transform=\'scale(1)\';this.style.boxShadow=\'0 2px 8px rgba(225,48,108,0.3)\'"><svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg></a>');
          }
          if (p.linkedin_url) {
            var liUrl = p.linkedin_url.startsWith('http') ? p.linkedin_url : 'https://' + p.linkedin_url;
            socialIcons.push('<a href="' + esc(liUrl) + '" target="_blank" rel="noopener" data-social="linkedin" data-point-id="' + p.id + '" class="jg-social-link" style="display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;background:#0077b5;color:#fff;border-radius:50%;text-decoration:none;transition:transform 0.2s,box-shadow 0.2s;box-shadow:0 2px 8px rgba(0,119,181,0.3)" title="LinkedIn" onmouseover="this.style.transform=\'scale(1.1)\';this.style.boxShadow=\'0 4px 12px rgba(0,119,181,0.5)\'" onmouseout="this.style.transform=\'scale(1)\';this.style.boxShadow=\'0 2px 8px rgba(0,119,181,0.3)\'"><svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg></a>');
          }
          if (p.tiktok_url) {
            var ttUrl = p.tiktok_url.startsWith('http') ? p.tiktok_url : 'https://' + p.tiktok_url;
            socialIcons.push('<a href="' + esc(ttUrl) + '" target="_blank" rel="noopener" data-social="tiktok" data-point-id="' + p.id + '" class="jg-social-link" style="display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;background:#000;color:#fff;border-radius:50%;text-decoration:none;transition:transform 0.2s,box-shadow 0.2s;box-shadow:0 2px 8px rgba(0,0,0,0.3)" title="TikTok" onmouseover="this.style.transform=\'scale(1.1)\';this.style.boxShadow=\'0 4px 12px rgba(0,0,0,0.5)\'" onmouseout="this.style.transform=\'scale(1)\';this.style.boxShadow=\'0 2px 8px rgba(0,0,0,0.3)\'"><svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/></svg></a>');
          }

          if (socialIcons.length > 0) {
            contactItems.push('<div style="display:flex;gap:10px;margin-top:8px">' + socialIcons.join('') + '</div>');
          }

          if (contactItems.length > 0) {
            contactInfo = '<div style="margin-top:10px;padding:12px;background:#fef3c7;border-radius:8px;border:2px solid #f59e0b">' + contactItems.join('') + '</div>';
          }
        }

        // CTA button for sponsored points - single beautiful button with gold gradient
        var ctaButton = '';
        if (p.sponsored && p.cta_enabled && p.cta_type) {
          var ctaUrl = '';
          var ctaText = '';
          var ctaIcon = '';

          switch (p.cta_type) {
            case 'call':
              if (p.phone) {
                ctaUrl = 'tel:' + esc(p.phone);
                ctaText = 'Zadzwoń Teraz';
                ctaIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:6px"><path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56-.35-.12-.74-.03-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z"/></svg>';
              }
              break;
            case 'website':
              if (p.website) {
                ctaUrl = p.website.startsWith('http') ? p.website : 'https://' + p.website;
                ctaText = 'Zobacz Więcej';
                ctaIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:6px"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>';
              }
              break;
            case 'facebook':
              if (p.facebook_url) {
                ctaUrl = p.facebook_url.startsWith('http') ? p.facebook_url : 'https://' + p.facebook_url;
                ctaText = 'Odwiedź nas na Facebooku';
                ctaIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:6px"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>';
              }
              break;
            case 'instagram':
              if (p.instagram_url) {
                ctaUrl = p.instagram_url.startsWith('http') ? p.instagram_url : 'https://' + p.instagram_url;
                ctaText = 'Sprawdź nas na Instagramie';
                ctaIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:6px"><path d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m-.2 2A3.6 3.6 0 0 0 4 7.6v8.8C4 18.39 5.61 20 7.6 20h8.8a3.6 3.6 0 0 0 3.6-3.6V7.6C20 5.61 18.39 4 16.4 4H7.6m9.65 1.5a1.25 1.25 0 0 1 1.25 1.25A1.25 1.25 0 0 1 17.25 8 1.25 1.25 0 0 1 16 6.75a1.25 1.25 0 0 1 1.25-1.25M12 7a5 5 0 0 1 5 5 5 5 0 0 1-5 5 5 5 0 0 1-5-5 5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3z"/></svg>';
              }
              break;
            case 'linkedin':
              if (p.linkedin_url) {
                ctaUrl = p.linkedin_url.startsWith('http') ? p.linkedin_url : 'https://' + p.linkedin_url;
                ctaText = 'Zobacz nas na LinkedIn';
                ctaIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:6px"><path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z"/></svg>';
              }
              break;
            case 'tiktok':
              if (p.tiktok_url) {
                ctaUrl = p.tiktok_url.startsWith('http') ? p.tiktok_url : 'https://' + p.tiktok_url;
                ctaText = 'Obserwuj nas na TikToku';
                ctaIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:6px"><path d="M16.6 5.82s.51.5 0 0A4.278 4.278 0 0 1 15.54 3h-3.09v12.4a2.592 2.592 0 0 1-2.59 2.5c-1.42 0-2.6-1.16-2.6-2.6 0-1.72 1.66-3.01 3.37-2.48V9.66c-3.45-.46-6.47 2.22-6.47 5.64 0 3.33 2.76 5.7 5.69 5.7 3.14 0 5.69-2.55 5.69-5.7V9.01a7.35 7.35 0 0 0 4.3 1.38V7.3s-1.88.09-3.24-1.48z"/></svg>';
              }
              break;
          }

          if (ctaUrl) {
            var targetAttr = (p.cta_type === 'call') ? '' : ' target="_blank" rel="noopener"';
            ctaButton = '<a href="' + ctaUrl + '"' + targetAttr + ' class="jg-btn-cta-sponsored">' + ctaIcon + ' ' + ctaText + '</a>';
          }
        }

        // Add deletion request button for authors only (non-admins)
        var deletionBtn = '';
        if (isOwnPoint && !CFG.isAdmin && !p.is_deletion_requested) {
          deletionBtn = '<button id="btn-request-deletion" class="jg-btn jg-btn--danger">Zgłoś usunięcie</button>';
        }

        // Address info - simple, below main content
        var addressInfo = '';
        if (p.address && p.address.trim()) {
          addressInfo = '<div style="margin:16px 0 8px 0;padding:0;font-size:13px;color:#6b7280"><span style="font-weight:500;color:#374151">📍</span> ' + esc(p.address) + '</div>';
        }

        // Category info for reports - prominent card
        var categoryInfo = '';

        if (p.type === 'zgloszenie' && p.category) {
          var categoryLabels = {
            'dziura_w_jezdni': '🕳️ Dziura w jezdni',
            'uszkodzone_chodniki': '🚶 Uszkodzone chodniki',
            'znaki_drogowe': '🚸 Brakujące lub zniszczone znaki drogowe',
            'oswietlenie': '💡 Awarie oświetlenia ulicznego',
            'dzikie_wysypisko': '🗑️ Dzikie wysypisko śmieci',
            'przepelniony_kosz': '♻️ Przepełniony kosz na śmieci',
            'graffiti': '🎨 Graffiti',
            'sliski_chodnik': '⚠️ Śliski chodnik',
            'nasadzenie_drzew': '🌳 Potrzeba nasadzenia drzew',
            'nieprzycięta_gałąź': '🌿 Nieprzycięta gałąź zagrażająca niebezpieczeństwu',
            'brak_przejscia': '🚦 Brak przejścia dla pieszych',
            'przystanek_autobusowy': '🚏 Potrzeba przystanku autobusowego',
            'organizacja_ruchu': '🚗 Problem z organizacją ruchu',
            'korki': '🚙 Powtarzające się korki',
            'mala_infrastruktura': '🎪 Propozycja nowych obiektów małej infrastruktury'
          };
          var categoryLabel = categoryLabels[p.category] || formatCategorySlug(p.category);
          categoryInfo = '<div style="margin:12px 0;padding:14px 18px;background:linear-gradient(135deg,#fffbeb 0%,#fef3c7 100%);border-left:4px solid #f59e0b;border-radius:8px;box-shadow:0 2px 6px rgba(245,158,11,0.15)"><div style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:#92400e;margin-bottom:6px;font-weight:600">Kategoria zgłoszenia</div><div style="font-size:16px;color:#78350f;font-weight:600">' + categoryLabel + '</div></div>';
        }

        // Status badge - for header (right side)
        var statusBadge = '';
        if (p.type === 'zgloszenie' && p.report_status) {
          var statusColors = {
            'added': { bg: '#dbeafe', border: '#3b82f6', text: '#1e3a8a' },
            'needs_better_documentation': { bg: '#fef3c7', border: '#f59e0b', text: '#92400e' },
            'reported': { bg: '#dbeafe', border: '#3b82f6', text: '#1e40af' },
            'resolved': { bg: '#d1fae5', border: '#10b981', text: '#065f46' },
            'rejected': { bg: '#fee2e2', border: '#ef4444', text: '#991b1b' }
          };
          var colors = statusColors[p.report_status] || { bg: '#f3f4f6', border: '#6b7280', text: '#374151' };
          var statusLabel = p.report_status_label || p.report_status;

          // Add deletion date for resolved reports
          var deletionDateText = '';
          if (p.report_status === 'resolved' && p.resolved_delete_at) {
            try {
              var deleteDate = new Date(p.resolved_delete_at);
              var months = ['stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca', 'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia'];
              var formattedDate = deleteDate.getDate() + ' ' + months[deleteDate.getMonth()] + ' ' + deleteDate.getFullYear();
              deletionDateText = '<div style="font-size:0.85rem;margin-top:4px;opacity:0.8">Usunięcie: ' + formattedDate + '</div>';
            } catch(e) {
              // Ignore date parsing errors
            }
          }
          // Add deletion date for rejected reports
          if (p.report_status === 'rejected' && p.rejected_delete_at) {
            try {
              var deleteDate = new Date(p.rejected_delete_at);
              var months = ['stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca', 'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia'];
              var formattedDate = deleteDate.getDate() + ' ' + months[deleteDate.getMonth()] + ' ' + deleteDate.getFullYear();
              deletionDateText = '<div style="font-size:0.85rem;margin-top:4px;opacity:0.8">Usunięcie: ' + formattedDate + '</div>';
            } catch(e) {
              // Ignore date parsing errors
            }
          }

          statusBadge = '<div style="font-size:1rem;padding:6px 14px;background:' + colors.bg + ';border:1px solid ' + colors.border + ';border-radius:8px;color:' + colors.text + ';font-weight:600;white-space:nowrap">' + esc(statusLabel) + deletionDateText + '</div>';
        }

        // Type badge for header (left side)
        var typeBadge = '';
        var typeLabels = {
          'zgloszenie': 'Zgłoszenie',
          'ciekawostka': 'Ciekawostka',
          'miejsce': 'Miejsce'
        };
        var typeColors = {
          'zgloszenie': { bg: '#fef2f2', border: '#dc2626', text: '#7f1d1d' },
          'ciekawostka': { bg: '#eff6ff', border: '#3b82f6', text: '#1e40af' },
          'miejsce': { bg: '#f0fdf4', border: '#10b981', text: '#065f46' }
        };
        var tColors = typeColors[p.type] || { bg: '#f3f4f6', border: '#6b7280', text: '#374151' };
        typeBadge = '<div style="font-size:1rem;padding:6px 14px;background:' + tColors.bg + ';border:1px solid ' + tColors.border + ';border-radius:8px;color:' + tColors.text + ';font-weight:600;white-space:nowrap">' + (typeLabels[p.type] || p.type) + '</div>';

        // Category badge for header (next to type, only for zgloszenie)
        var categoryBadgeHeader = '';
        if (p.type === 'zgloszenie' && p.category) {
          var categoryEmoji = {
            'dziura_w_jezdni': '🕳️',
            'uszkodzone_chodniki': '🚶',
            'znaki_drogowe': '🚸',
            'oswietlenie': '💡',
            'dzikie_wysypisko': '🗑️',
            'przepelniony_kosz': '♻️',
            'graffiti': '🎨',
            'sliski_chodnik': '⚠️',
            'nasadzenie_drzew': '🌳',
            'nieprzycięta_gałąź': '🌿',
            'brak_przejscia': '🚦',
            'przystanek_autobusowy': '🚏',
            'organizacja_ruchu': '🚗',
            'korki': '🚙',
            'mala_infrastruktura': '🎪'
          };
          var categoryLabelsShort = {
            'dziura_w_jezdni': 'Dziura w jezdni',
            'uszkodzone_chodniki': 'Uszkodzone chodniki',
            'znaki_drogowe': 'Znaki drogowe',
            'oswietlenie': 'Oświetlenie',
            'dzikie_wysypisko': 'Dzikie wysypisko',
            'przepelniony_kosz': 'Przepełniony kosz',
            'graffiti': 'Graffiti',
            'sliski_chodnik': 'Śliski chodnik',
            'nasadzenie_drzew': 'Nasadzenie drzew',
            'nieprzycięta_gałąź': 'Nieprzycięta gałąź',
            'brak_przejscia': 'Brak przejścia',
            'przystanek_autobusowy': 'Przystanek',
            'organizacja_ruchu': 'Organizacja ruchu',
            'korki': 'Korki',
            'mala_infrastruktura': 'Mała infrastruktura'
          };
          var emoji = categoryEmoji[p.category] || '📌';
          var catLabel = categoryLabelsShort[p.category] || formatCategorySlug(p.category);
          categoryBadgeHeader = '<div style="font-size:1rem;padding:6px 14px;background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;color:#78350f;font-weight:600;white-space:nowrap;display:flex;align-items:center;gap:8px"><span>' + emoji + '</span><span>' + catLabel + '</span></div>';
        }

        // Remove large category card from body since it's now in header
        categoryInfo = '';

        // Sponsored badge for header (first position on the left)
        var sponsoredBadgeHeader = '';
        if (p.sponsored) {
          sponsoredBadgeHeader = '<span class="jg-promo-tag">⭐ MIEJSCE SPONSOROWANE</span>';
        }

        // Stats button for sponsored places (owner + admins only)
        var statsBtn = '';
        if (p.sponsored && (isOwnPoint || CFG.isAdmin)) {
          statsBtn = '<button id="btn-stats" class="jg-btn jg-btn--ghost">📊 Statystyki</button>';
        }

        // Case ID badge (for reports)
        var caseIdBadge = '';
        if (p.type === 'zgloszenie' && p.case_id) {
          caseIdBadge = '<span class="jg-case-id-badge">' + esc(p.case_id) + '</span>';
        }

        // Lock icon for edit-locked places
        var lockIcon = p.edit_locked ? '<span title="Edycja zablokowana" style="margin-left:8px;color:#dc2626;font-size:0.7em;vertical-align:middle">🔒</span>' : '';

        var html = '<header style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 20px;border-bottom:1px solid #e5e7eb"><div style="display:flex;align-items:center;gap:12px">' + sponsoredBadgeHeader + typeBadge + categoryBadgeHeader + '</div><div style="display:flex;align-items:center;gap:12px">' + statusBadge + caseIdBadge + '<button class="jg-close" id="dlg-close" style="margin:0">&times;</button></div></header><div class="jg-grid" style="overflow:auto;padding:20px"><h3 class="jg-place-title" style="margin:0 0 16px 0;font-size:2.5rem;font-weight:400;line-height:1.2">' + esc(p.title || 'Szczegóły') + lockIcon + '</h3>' + dateInfo + (p.content ? ('<div class="jg-place-content">' + p.content + '</div>') : (p.excerpt ? ('<p class="jg-place-excerpt">' + esc(p.excerpt) + '</p>') : '')) + contactInfo + ctaButton + addressInfo + (gal ? ('<div class="jg-gallery" style="margin-top:10px">' + gal + '</div>') : '') + (who ? ('<div style="margin-top:10px">' + who + '</div>') : '') + verificationBadge + reportsWarning + userReportNotice + editInfo + deletionInfo + adminNote + resolvedNotice + rejectedNotice + voteHtml + adminBox + '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px">' + statsBtn + (canEdit ? '<button id="btn-edit" class="jg-btn jg-btn--ghost">Edytuj</button>' : '') + deletionBtn + '<button id="btn-report" class="jg-btn jg-btn--ghost">Zgłoś</button></div></div>';

        open(modalView, html, { addClass: (promoClass + typeClass).trim(), pointData: p });

        // Track view for sponsored pins with unique visitor detection
        var viewStartTime = Date.now();
        if (p.sponsored) {
          var isUnique = isUniqueVisitor(p.id);
          trackStat(p.id, 'view', { is_unique: isUnique }, p.author_id);
        }

        // Close button handler - track time spent
        qs('#dlg-close', modalView).onclick = function() {
          // Track time spent before closing (for sponsored pins)
          if (p.sponsored) {
            var timeSpent = Math.round((Date.now() - viewStartTime) / 1000); // seconds
            if (timeSpent > 0 && timeSpent < 3600) { // Max 1 hour to filter out abandoned tabs
              trackStat(p.id, 'time_spent', { time_spent: timeSpent }, p.author_id);
            }
          }
          close(modalView);
        };

        // Close on backdrop click - also track time spent
        modalView.onclick = function(e) {
          if (e.target === modalView) {
            // Track time spent before closing (for sponsored pins)
            if (p.sponsored) {
              var timeSpent = Math.round((Date.now() - viewStartTime) / 1000);
              if (timeSpent > 0 && timeSpent < 3600) {
                trackStat(p.id, 'time_spent', { time_spent: timeSpent }, p.author_id);
              }
            }
            close(modalView);
          }
        };

        var g = qs('.jg-gallery', modalView);
        if (g) {
          g.querySelectorAll('img').forEach(function(img, idx) {
            img.addEventListener('click', function() {
              var fullUrl = this.getAttribute('data-full') || this.src;

              // Track gallery click for sponsored pins
              if (p.sponsored) {
                trackStat(p.id, 'gallery_click', { image_index: idx }, p.author_id);
              }

              openLightbox(fullUrl);
            });
          });

          // Add delete image handlers
          g.querySelectorAll('.jg-delete-image').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
              e.stopPropagation();
              var pointId = this.getAttribute('data-point-id');
              var imageIndex = this.getAttribute('data-image-index');

              showConfirm('Czy na pewno chcesz usunąć to zdjęcie?').then(function(confirmed) {
                if (!confirmed) {
                  return;
                }

                btn.disabled = true;
                btn.textContent = '...';

                api('jg_delete_image', { point_id: pointId, image_index: imageIndex })
                  .then(function(result) {
                    // Update local point data with new featured index
                    var point = ALL.find(function(x) { return x.id === +pointId; });
                    if (point && result.new_featured_index !== undefined) {
                      point.featured_image_index = result.new_featured_index;
                    }
                    close(modalView);
                    refreshAll().then(function() {
                      showAlert('Zdjęcie zostało usunięte');
                    });
                  })
                  .catch(function(err) {
                    btn.disabled = false;
                    btn.textContent = '×';
                    showAlert('Błąd: ' + (err.message || 'Nie udało się usunąć zdjęcia'));
                  });
              });
            });
          });
        }

        // Setup featured image handlers (for admin/author)
        if (canDeleteImages && imgs.length > 1) {
          var featuredBtns = modalView.querySelectorAll('.jg-set-featured-image');
          featuredBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
              e.stopPropagation();
              var pointId = +this.getAttribute('data-point-id');
              var imageIndex = +this.getAttribute('data-image-index');

              btn.disabled = true;
              btn.style.opacity = '0.5';

              api('jg_set_featured_image', { point_id: pointId, image_index: imageIndex })
                .then(function(result) {
                  // Update local data
                  var point = ALL.find(function(x) { return x.id === pointId; });
                  if (point) {
                    point.featured_image_index = imageIndex;
                  }

                  // Refresh modal to show new featured image
                  close(modalView);
                  refreshAll().then(function() {
                    var updatedPoint = ALL.find(function(x) { return x.id === pointId; });
                    if (updatedPoint) {
                      openDetails(updatedPoint);
                    }
                  });
                })
                .catch(function(err) {
                  btn.disabled = false;
                  btn.style.opacity = '1';
                  showAlert('Błąd: ' + (err.message || 'Nie udało się ustawić wyróżnionego obrazu'));
                });
            });
          });
        }

        // Track clicks on contact links for sponsored pins
        if (p.sponsored) {
          // Track phone clicks
          var phoneLinks = modalView.querySelectorAll('a[href^="tel:"]');
          phoneLinks.forEach(function(link) {
            link.addEventListener('click', function() {
              trackStat(p.id, 'phone_click', null, p.author_id);
            });
          });

          // Track website clicks (excluding social media)
          var websiteLinks = modalView.querySelectorAll('a[href^="http"]:not(.jg-social-link):not(.jg-btn-cta-sponsored)');
          websiteLinks.forEach(function(link) {
            link.addEventListener('click', function() {
              trackStat(p.id, 'website_click', null, p.author_id);
            });
          });

          // Track social media clicks
          var socialLinks = modalView.querySelectorAll('.jg-social-link');
          socialLinks.forEach(function(link) {
            link.addEventListener('click', function() {
              var platform = this.getAttribute('data-social');
              if (platform) {
                trackStat(p.id, 'social_click', { platform: platform }, p.author_id);
              }
            });
          });

          // Track CTA button clicks
          var ctaBtn = modalView.querySelector('.jg-btn-cta-sponsored');
          if (ctaBtn) {
            ctaBtn.addEventListener('click', function() {
              trackStat(p.id, 'cta_click', null, p.author_id);
            });
          }
        }

        // Setup voting handlers only if not promo
        if (!p.sponsored) {
          var cnt = qs('#v-cnt', modalView);
          var up = qs('#v-up', modalView);
          var down = qs('#v-down', modalView);

          if (cnt && up && down) {
            function refresh(n, my) {
              cnt.textContent = n;
              cnt.setAttribute('style', colorForVotes(+n || 0));
              up.classList.toggle('active', my === 'up');
              down.classList.toggle('active', my === 'down');
            }

            function doVote(dir) {
              if (!CFG.isLoggedIn) {
                showAlert('Zaloguj się.');
                return;
              }

              // Check if user is banned or has voting restriction
              if (window.JG_USER_RESTRICTIONS) {
                if (window.JG_USER_RESTRICTIONS.is_banned) {
                  showAlert('Nie możesz głosować - Twoje konto jest zbanowane.');
                  return;
                }
                if (window.JG_USER_RESTRICTIONS.restrictions && window.JG_USER_RESTRICTIONS.restrictions.indexOf('voting') !== -1) {
                  showAlert('Nie możesz głosować - masz aktywną blokadę głosowania.');
                  return;
                }
              }

              up.disabled = down.disabled = true;
              voteReq({ post_id: p.id, dir: dir })
                .then(function(d) {
                  p.votes = +d.votes || 0;
                  p.my_vote = d.my_vote || '';
                  refresh(p.votes, p.my_vote);
                })
                .catch(function(e) {
                  showAlert((e && e.message) || 'Błąd');
                })
                .finally(function() {
                  up.disabled = down.disabled = false;
                });
            }

            up.onclick = function() {
              doVote('up');
            };

            down.onclick = function() {
              doVote('down');
            };
          }
        }

        qs('#btn-report', modalView).onclick = function() {
          openReportModal(p);
        };

        if (canEdit) {
          // Stats button handler
          var statsBtn = qs('#btn-stats', modalView);
          if (statsBtn) statsBtn.onclick = function() {
            openStatsModal(p);
          };

          var editBtn = qs('#btn-edit', modalView);
          if (editBtn) editBtn.onclick = function() {
            openEditModal(p);
          };

          // Add handler for deletion request button
          var deletionBtn = qs('#btn-request-deletion', modalView);
          if (deletionBtn) deletionBtn.onclick = function() {
            openDeletionRequestModal(p);
          };
        }

        var ba = qs('#btn-author', modalView);
        if (ba) {
          ba.addEventListener('click', function(ev) {
            ev.preventDefault();
            var authorId = +this.getAttribute('data-id');

            // Open user profile modal for everyone
            openUserModal(authorId);
          });
        }

        if (CFG.isAdmin) {
          var btnViewReports = qs('#btn-view-reports', modalView);
          if (btnViewReports) {
            btnViewReports.onclick = function() {
              openReportsListModal(p);
            };
          }

          // Handler for author link in admin panel
          var btnAuthorAdmin = qs('#btn-author-admin', modalView);
          if (btnAuthorAdmin) {
            btnAuthorAdmin.addEventListener('click', function(ev) {
              ev.preventDefault();
              var userId = +this.getAttribute('data-user-id');
              openUserModal(userId);
            });
          }

          // User actions menu button
          var btnUserActions = qs('#btn-user-actions', modalView);
          if (btnUserActions && p.admin) {
            btnUserActions.onclick = function() {
              openUserActionsModal(p.author_id, p.admin.author_name_real || 'Użytkownik');
            };
          }

          var btnAuthor = qs('#btn-toggle-author', modalView);
          var btnStatus = qs('#btn-change-status', modalView);
          var btnNote = qs('#btn-admin-note', modalView);
          var btnApprove = qs('#btn-approve-point', modalView);
          var btnReject = qs('#btn-reject-point', modalView);
          var btnApproveEdit = qs('#btn-approve-edit', modalView);
          var btnRejectEdit = qs('#btn-reject-edit', modalView);
          var btnDelete = qs('#btn-delete-point', modalView);

          if (btnApprove) {
            btnApprove.onclick = function() {
              showConfirm('Zaakceptować?').then(function(confirmed) {
                if (!confirmed) return;
                btnApprove.disabled = true;
                btnApprove.textContent = 'Akceptowanie...';

                adminApprovePoint({ post_id: p.id })
                  .then(function() {
                    close(modalView);
                    return refreshAll();
                  })
                  .then(function() {
                    showAlert('Zaakceptowano i opublikowano!');
                  })
                  .catch(function(err) {
                    showAlert('Błąd: ' + (err.message || '?'));
                    btnApprove.disabled = false;
                    btnApprove.textContent = '✓ Akceptuj';
                  });
              });
            };
          }

          if (btnReject) {
            btnReject.onclick = function() {
              showRejectReasonModal('Powód odrzucenia miejsca')
                .then(function(reason) {
                  if (reason === null) return;

                  btnReject.disabled = true;
                  btnReject.textContent = 'Odrzucanie...';

                  adminRejectPoint({ post_id: p.id, reason: reason })
                    .then(function() {
                      close(modalView);
                      return refreshAll();
                    })
                    .then(function() {
                      showAlert('Odrzucono i przeniesiono do kosza.');
                    })
                    .catch(function(err) {
                      showAlert('Błąd: ' + (err.message || '?'));
                      btnReject.disabled = false;
                      btnReject.textContent = '✗ Odrzuć';
                    });
                });
            };
          }

          var btnSponsored = qs('#btn-toggle-sponsored', modalView);
          if (btnSponsored) {
            btnSponsored.onclick = function() {
              openSponsoredModal(p);
            };
          }

          if (btnAuthor) {
            btnAuthor.onclick = function() {
              showConfirm((p.author_hidden ? 'Ujawnić' : 'Ukryć') + ' autora?').then(function(confirmed) {
                if (!confirmed) return;
                btnAuthor.disabled = true;
                btnAuthor.textContent = 'Zapisywanie...';

                adminToggleAuthor({ post_id: p.id })
                  .then(function(result) {
                    return refreshAll();
                  })
                  .then(function() {
                    close(modalView);
                    var updatedPoint = ALL.find(function(x) { return x.id === p.id; });
                    if (updatedPoint) {
                      setTimeout(function() {
                        openDetails(updatedPoint);
                      }, 200);
                    }
                  })
                  .catch(function(err) {
                    showAlert('Błąd: ' + (err.message || '?'));
                    btnAuthor.disabled = false;
                    btnAuthor.textContent = p.author_hidden ? 'Ujawnij autora' : 'Ukryj autora';
                  });
              });
            };
          }

          if (btnStatus) {
            btnStatus.onclick = function() {
              openStatusModal(p);
            };
          }

          if (btnNote) {
            btnNote.onclick = function() {
              var currentNote = p.admin_note || '';
              var newNote = prompt('Notatka administratora (pozostaw puste aby usunąć):', currentNote);
              if (newNote === null) return;

              btnNote.disabled = true;
              btnNote.textContent = 'Zapisywanie...';

              adminUpdateNote({ post_id: p.id, note: newNote })
                .then(function(result) {
                  return refreshAll();
                })
                .then(function() {
                  close(modalView);
                  var updatedPoint = ALL.find(function(x) { return x.id === p.id; });
                  if (updatedPoint) {
                    setTimeout(function() {
                      openDetails(updatedPoint);
                    }, 200);
                  }
                })
                .catch(function(err) {
                  showAlert('Błąd: ' + (err.message || '?'));
                  btnNote.disabled = false;
                  btnNote.textContent = p.admin_note ? 'Edytuj notatkę' : 'Dodaj notatkę';
                });
            };
          }

          // Edit lock toggle handler
          var btnEditLock = qs('#btn-toggle-edit-lock', modalView);
          if (btnEditLock) {
            btnEditLock.onclick = function() {
              var action = p.edit_locked ? 'odblokować' : 'zablokować';
              showConfirm('Czy na pewno chcesz ' + action + ' edycję tego miejsca?').then(function(confirmed) {
                if (!confirmed) return;

                btnEditLock.disabled = true;
                btnEditLock.textContent = 'Zapisywanie...';

                api('jg_admin_toggle_edit_lock', { point_id: p.id })
                  .then(function(result) {
                    return refreshAll();
                  })
                  .then(function() {
                    close(modalView);
                    var updatedPoint = ALL.find(function(x) { return x.id === p.id; });
                    if (updatedPoint) {
                      setTimeout(function() {
                        openDetails(updatedPoint);
                      }, 200);
                    }
                  })
                  .catch(function(err) {
                    showAlert('Błąd: ' + (err.message || '?'));
                    btnEditLock.disabled = false;
                    btnEditLock.textContent = p.edit_locked ? '🔓 Odblokuj edycję' : '🔒 Zablokuj edycję';
                  });
              });
            };
          }

          // Change owner handler - opens modal with user search
          var btnChangeOwner = qs('#btn-change-owner', modalView);
          if (btnChangeOwner) {
            btnChangeOwner.onclick = function() {
              openChangeOwnerModal(p, modalView);
            };
          }

          if (btnApproveEdit) {
            btnApproveEdit.onclick = function() {
              showConfirm('Zaakceptować edycję?').then(function(confirmed) {
                if (!confirmed) return;

                btnApproveEdit.disabled = true;
                btnApproveEdit.textContent = 'Akceptowanie...';

                api('jg_admin_approve_edit', { history_id: p.edit_info.history_id })
                  .then(function(result) {
                    return refreshAll();
                  })
                  .then(function() {
                    close(modalView);
                    var updatedPoint = ALL.find(function(x) { return x.id === p.id; });
                    if (updatedPoint) {
                      setTimeout(function() {
                        openDetails(updatedPoint);
                      }, 200);
                    }
                  })
                  .catch(function(err) {
                    showAlert('Błąd: ' + (err.message || '?'));
                    btnApproveEdit.disabled = false;
                    btnApproveEdit.textContent = '✓ Akceptuj edycję';
                  });
              });
            };
          }

          if (btnRejectEdit) {
            btnRejectEdit.onclick = function() {
              showRejectReasonModal('Powód odrzucenia edycji')
                .then(function(reason) {
                  if (reason === null) return;

                  btnRejectEdit.disabled = true;
                  btnRejectEdit.textContent = 'Odrzucanie...';

                  api('jg_admin_reject_edit', { history_id: p.edit_info.history_id, reason: reason })
                    .then(function(result) {
                      return refreshAll();
                    })
                    .then(function() {
                      close(modalView);
                      var updatedPoint = ALL.find(function(x) { return x.id === p.id; });
                      if (updatedPoint) {
                        setTimeout(function() {
                          openDetails(updatedPoint);
                        }, 200);
                      }
                    })
                    .catch(function(err) {
                      showAlert('Błąd: ' + (err.message || '?'));
                      btnRejectEdit.disabled = false;
                      btnRejectEdit.textContent = '✗ Odrzuć edycję';
                    });
                });
            };
          }

          // Deletion request handlers
          var btnApproveDeletion = qs('#btn-approve-deletion', modalView);
          var btnRejectDeletion = qs('#btn-reject-deletion', modalView);

          if (btnApproveDeletion) {
            btnApproveDeletion.onclick = function() {
              showConfirm('Zatwierdzić usunięcie miejsca? Miejsca nie będzie można przywrócić!').then(function(confirmed) {
                if (!confirmed) return;

                btnApproveDeletion.disabled = true;
                btnApproveDeletion.textContent = 'Usuwanie...';

                api('jg_admin_approve_deletion', { history_id: p.deletion_info.history_id })
                  .then(function(result) {
                    return refreshAll();
                  })
                  .then(function() {
                    close(modalView);
                    showAlert('Miejsce zostało usunięte');
                  })
                  .catch(function(err) {
                    showAlert('Błąd: ' + (err.message || '?'));
                    btnApproveDeletion.disabled = false;
                    btnApproveDeletion.textContent = '✓ Zatwierdź usunięcie';
                  });
              });
            };
          }

          if (btnRejectDeletion) {
            btnRejectDeletion.onclick = function() {
              showRejectReasonModal('Powód odrzucenia zgłoszenia usunięcia')
                .then(function(reason) {
                  if (reason === null) return;

                  btnRejectDeletion.disabled = true;
                  btnRejectDeletion.textContent = 'Odrzucanie...';

                  api('jg_admin_reject_deletion', { history_id: p.deletion_info.history_id, reason: reason })
                    .then(function(result) {
                      return refreshAll();
                    })
                    .then(function() {
                      close(modalView);
                      var updatedPoint = ALL.find(function(x) { return x.id === p.id; });
                      if (updatedPoint) {
                        setTimeout(function() {
                          openDetails(updatedPoint);
                        }, 200);
                      }
                    })
                    .catch(function(err) {
                      showAlert('Błąd: ' + (err.message || '?'));
                      btnRejectDeletion.disabled = false;
                      btnRejectDeletion.textContent = '✗ Odrzuć usunięcie';
                    });
                });
            };
          }

          if (btnDelete) {
            btnDelete.onclick = function() {
              showConfirm('NA PEWNO usunąć to miejsce? Tej operacji nie można cofnąć!').then(function(confirmed) {
                if (!confirmed) return;

                btnDelete.disabled = true;
                btnDelete.textContent = 'Usuwanie...';

                adminDeletePoint({ post_id: p.id })
                  .then(function() {
                    close(modalView);
                    return refreshAll();
                  })
                  .then(function() {
                    showAlert('Miejsce usunięte trwale!');
                  })
                  .catch(function(err) {
                    showAlert('Błąd: ' + (err.message || '?'));
                    btnDelete.disabled = false;
                    btnDelete.textContent = '🗑️ Usuń miejsce';
                  });
              });
            };
          }
        }

        // Owner approval handlers (for external edits to owner's place)
        var btnOwnerApproveEdit = qs('#btn-owner-approve-edit', modalView);
        var btnOwnerRejectEdit = qs('#btn-owner-reject-edit', modalView);

        if (btnOwnerApproveEdit) {
          btnOwnerApproveEdit.onclick = function() {
            var historyId = this.getAttribute('data-history-id');
            showConfirm('Zatwierdzić proponowane zmiany?').then(function(confirmed) {
              if (!confirmed) return;

              btnOwnerApproveEdit.disabled = true;
              btnOwnerApproveEdit.textContent = 'Zatwierdzanie...';

              var successMessage = 'Edycja zatwierdzona';

              api('jg_owner_approve_edit', { history_id: historyId })
                .then(function(result) {
                  if (result && result.message) {
                    successMessage = result.message;
                  }
                  return refreshAll();
                })
                .then(function() {
                  close(modalView);
                  showAlert(successMessage);
                  var updatedPoint = ALL.find(function(x) { return x.id === p.id; });
                  if (updatedPoint) {
                    setTimeout(function() {
                      openDetails(updatedPoint);
                    }, 200);
                  }
                })
                .catch(function(err) {
                  showAlert('Błąd: ' + (err.message || '?'));
                  btnOwnerApproveEdit.disabled = false;
                  btnOwnerApproveEdit.textContent = '✓ Zatwierdź zmiany';
                });
            });
          };
        }

        if (btnOwnerRejectEdit) {
          btnOwnerRejectEdit.onclick = function() {
            var historyId = this.getAttribute('data-history-id');
            showRejectReasonModal('Powód odrzucenia proponowanych zmian')
              .then(function(reason) {
                if (reason === null) return;

                btnOwnerRejectEdit.disabled = true;
                btnOwnerRejectEdit.textContent = 'Odrzucanie...';

                api('jg_owner_reject_edit', { history_id: historyId, reason: reason })
                  .then(function(result) {
                    return refreshAll();
                  })
                  .then(function() {
                    close(modalView);
                    showAlert('Propozycja zmian została odrzucona');
                    var updatedPoint = ALL.find(function(x) { return x.id === p.id; });
                    if (updatedPoint) {
                      setTimeout(function() {
                        openDetails(updatedPoint);
                      }, 200);
                    }
                  })
                  .catch(function(err) {
                    showAlert('Błąd: ' + (err.message || '?'));
                    btnOwnerRejectEdit.disabled = false;
                    btnOwnerRejectEdit.textContent = '✗ Odrzuć';
                  });
              });
          };
        }
      }

      function apply(skipFitBounds) {

        // CRITICAL FIX: Don't apply filters if data not yet loaded
        // Allow empty arrays if data was actually loaded (could be no points in DB)
        if (!dataLoaded) {
          return;
        }

        var enabled = {};
        var promoOnly = false;
        var myPlacesOnly = false;

        if (elFilters) {
          elFilters.querySelectorAll('input[data-type]').forEach(function(cb) {
            if (cb.checked) enabled[cb.getAttribute('data-type')] = true;
          });
          var pr = elFilters.querySelector('input[data-promo]');
          promoOnly = !!(pr && pr.checked);
          var myPlaces = elFilters.querySelector('input[data-my-places]');
          myPlacesOnly = !!(myPlaces && myPlaces.checked);
        }


        // Filter logic:
        // 1. "Tylko sponsorowane" checkbox -> show ONLY sponsored
        // 2. "Moje miejsca" checkbox -> show only user's places + ALL sponsored
        // 3. Type filters (miejsca/ciekawostki/zgłoszenia) -> filter by type but sponsored are ALWAYS visible
        // 4. No filters enabled -> show ALL points

        var list = (ALL || []).filter(function(p) {
          var isSponsored = !!p.sponsored;
          var isUserPlace = (+CFG.currentUserId > 0 && +CFG.currentUserId === +p.author_id);

          // CHECKBOX: "Tylko sponsorowane" - show ONLY sponsored
          if (promoOnly) {
            return isSponsored;
          }

          // CHECKBOX: "Moje miejsca" - show only user's places + ALL sponsored
          if (myPlacesOnly) {
            if (!CFG.currentUserId || +CFG.currentUserId <= 0) {
              return isSponsored; // Not logged in - show only sponsored
            }
            return isSponsored || isUserPlace; // Show sponsored OR user's places
          }

          // TYPE FILTERS: Sponsored are ALWAYS visible, others filtered by type
          if (isSponsored) {
            return true; // Sponsored always visible (unless promoOnly/myPlacesOnly handled above)
          }

          // For non-sponsored: check type filters
          // If NO type filters enabled -> hide all non-sponsored (only sponsored show)
          if (Object.keys(enabled).length === 0) {
            return false; // No type filters enabled = hide non-sponsored points
          }

          // If type filters enabled -> check if this point's type is enabled
          return !!enabled[p.type];
        });

        // Debug logging
        var sponsoredCount = list.filter(function(p) { return p.sponsored; }).length;
        var nonSponsoredCount = list.length - sponsoredCount;

        pendingData = list;
        draw(list, skipFitBounds);
      }

      // ====================================
      // NEW SEARCH FUNCTIONALITY with Side Panel
      // ====================================
      setTimeout(function() {
        var searchInput = document.getElementById('jg-search-input');
        var searchBtn = document.getElementById('jg-search-btn');
        var searchPanel = document.getElementById('jg-search-panel');
        var searchResults = document.getElementById('jg-search-results');
        var searchCount = document.getElementById('jg-search-panel-count');
        var searchCloseBtn = document.getElementById('jg-search-close-btn');

        // Perform search and show results in side panel
        function performSearch() {
          var query = searchInput.value.toLowerCase().trim();

          if (!query) {
            closeSearchPanel();
            return;
          }


          // Search through ALL points
          var results = (ALL || []).filter(function(p) {
            var title = (p.title || '').toLowerCase();
            var content = (p.content || '').toLowerCase();
            var excerpt = (p.excerpt || '').toLowerCase();
            return title.indexOf(query) !== -1 ||
                   content.indexOf(query) !== -1 ||
                   excerpt.indexOf(query) !== -1;
          });


          // Update panel count
          searchCount.textContent = results.length + (results.length === 1 ? ' wynik' : ' wyników');

          // Build results HTML
          if (results.length === 0) {
            searchResults.innerHTML = '<div style="padding:40px 20px;text-align:center;color:#6b7280">' +
              '<div style="font-size:48px;margin-bottom:12px">🔍</div>' +
              '<div style="font-size:16px;font-weight:600;margin-bottom:8px">Brak wyników</div>' +
              '<div style="font-size:14px">Spróbuj wyszukać czegość innego</div>' +
              '</div>';
          } else {
            var html = '';
            results.forEach(function(point) {
              var iconClass = 'jg-search-result-icon--' + (point.sponsored ? 'sponsored' : point.type);

              // Use colored dots instead of emoji
              var icon = '';
              if (point.sponsored) {
                icon = '<div style="font-size:20px">⭐</div>';
              } else {
                var dotColor = '#888';
                if (point.type === 'miejsce') {
                  dotColor = '#0a5a28'; // Green
                } else if (point.type === 'ciekawostka') {
                  dotColor = '#1e3a8a'; // Blue
                }
                icon = '<div style="width:20px;height:20px;border-radius:50%;background:' + dotColor + '"></div>';
              }

              var excerpt = point.excerpt || '';
              if (excerpt.length > 100) {
                excerpt = excerpt.substring(0, 100) + '...';
              }

              html += '<div class="jg-search-result-item" data-point-id="' + point.id + '">' +
                '<div class="jg-search-result-icon ' + iconClass + '">' + icon + '</div>' +
                '<div class="jg-search-result-content">' +
                '<div class="jg-search-result-title">' + esc(point.title || 'Bez nazwy') + '</div>' +
                (excerpt ? '<div class="jg-search-result-excerpt">' + esc(excerpt) + '</div>' : '') +
                '</div>' +
                '</div>';
            });
            searchResults.innerHTML = html;

            // Add click handlers to results
            setTimeout(function() {
              var items = document.querySelectorAll('.jg-search-result-item');
              items.forEach(function(item) {
                item.addEventListener('click', function() {
                  var pointId = parseInt(this.getAttribute('data-point-id'));
                  var point = results.find(function(p) { return p.id === pointId; });
                  if (point) {
                    zoomToSearchResult(point);
                  }
                });
              });
            }, 50);
          }

          // Open panel
          searchPanel.classList.add('active');
        }

        // Zoom to search result with fast pulsing circle
        function zoomToSearchResult(point) {

          // Zoom to point
          map.setView([point.lat, point.lng], 19, { animate: true });

          // On mobile: close panel and scroll to map
          if (window.innerWidth <= 768) {
            setTimeout(function() {
              closeSearchPanel();
              // Scroll to map smoothly
              var mapEl = document.getElementById('jg-map');
              if (mapEl) {
                mapEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
              }
            }, 300); // Small delay to show selection
          }

          // Wait for zoom, then show FAST pulsing circle
          setTimeout(function() {
            addFastPulsingMarker(point.lat, point.lng);
          }, 600);
        }

        // Add fast pulsing red circle (1.5s total, faster pulses)
        function addFastPulsingMarker(lat, lng) {
          var pulsingCircle = L.circle([lat, lng], {
            color: '#ef4444',
            fillColor: '#ef4444',
            fillOpacity: 0.3,
            radius: 12,
            weight: 3
          }).addTo(map);

          var pulseCount = 0;
          var maxPulses = 6; // 6 fast pulses over 1.5s
          var pulseInterval = setInterval(function() {
            pulseCount++;

            // Toggle opacity for pulse effect (faster)
            if (pulseCount % 2 === 0) {
              pulsingCircle.setStyle({ fillOpacity: 0.3, opacity: 1 });
            } else {
              pulsingCircle.setStyle({ fillOpacity: 0.1, opacity: 0.4 });
            }

            // Remove after 1.5 seconds
            if (pulseCount >= maxPulses) {
              clearInterval(pulseInterval);
              setTimeout(function() {
                map.removeLayer(pulsingCircle);
              }, 250);
            }
          }, 250); // Fast pulse every 250ms
        }

        // Close search panel
        function closeSearchPanel() {
          searchPanel.classList.remove('active');
          searchInput.value = '';
          searchResults.innerHTML = '';
        }

        // Event listeners
        if (searchBtn) {
          searchBtn.addEventListener('click', performSearch);
        }

        if (searchInput) {
          searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
              e.preventDefault();
              performSearch();
            }
          });
        }

        if (searchCloseBtn) {
          searchCloseBtn.addEventListener('click', closeSearchPanel);
        }
      }, 500);

      // Setup filter listeners - wait for DOM
      setTimeout(function() {
        if (elFilters) {
          var allCheckboxes = elFilters.querySelectorAll('input[type="checkbox"]');
          allCheckboxes.forEach(function(cb) {
            cb.addEventListener('change', function() {
              apply(true); // Skip fitBounds on filter change
            });
          });
        } else {
          debugError('[JG MAP] Filter container not found!');
        }
      }, 500);

      // EARLY CHECK: If there's a deep link, scroll to map FIRST before loading anything
      (function earlyScrollCheck() {
        try {
          var urlParams = new URLSearchParams(window.location.search);
          var hasDeepLink = false;

          // Check for any deep link parameters
          if (urlParams.get('jg_view_reports') ||
              urlParams.get('jg_view_point') ||
              urlParams.get('point_id') ||
              window.location.hash.match(/^#point-(\d+)$/)) {
            hasDeepLink = true;
          }

          if (hasDeepLink) {
            var mapSection = document.getElementById('mapa-start');
            if (!mapSection) {
              mapSection = document.getElementById('jg-map-wrap');
            }
            if (mapSection) {
              var targetPosition = mapSection.getBoundingClientRect().top + window.pageYOffset - 80;
              window.scrollTo({ top: targetPosition, behavior: 'smooth' });
            }
          }
        } catch (e) {
          debugError('[JG MAP] Early scroll check error:', e);
        }
      })();

      // Load from cache first for instant display, then check for updates
      var cachedData = loadFromCache();
      if (cachedData && cachedData.length > 0) {
        ALL = cachedData;
        dataLoaded = true; // Mark data as loaded from cache
        apply(false); // Apply cached data immediately with fitBounds
        // Don't call hideLoading() here - let draw() handle it when cluster is ready

        // Check user restrictions
        checkUserRestrictions();

        // Deep-linked point will be checked by draw() after map is fully ready

        // Always fetch fresh data on page load (not just check for updates)
        // This ensures users always see current pin state, regardless of cache
        refreshData(true).catch(function(err) {
          debugError('[JG MAP] Background refresh failed:', err);
        });
      } else {
        // No cache, fetch fresh data
        refreshData(true)
          .then(function() {
            checkUserRestrictions();
            // Deep-linked point will be checked by draw() after map is fully ready
          })
          .catch(function(e) {
            showError('Nie udało się pobrać punktów: ' + (e.message || '?'));
          });
      }

      // ========================================================================
      // REAL-TIME SYNCHRONIZATION via WordPress Heartbeat API
      // ========================================================================

      var lastSyncCheck = Math.floor(Date.now() / 1000);
      var syncOnline = false;
      var syncStatusIndicator = null;
      var syncCompletedTimeout = null;

      // Create sync status indicator
      function createSyncStatusIndicator() {
        var indicator = $('<div>')
          .attr('id', 'jg-sync-status')
          .attr('title', 'Status synchronizacji')
          .css({
            padding: '6px 10px',
            borderRadius: '6px',
            fontSize: '11px',
            fontWeight: '600',
            display: 'flex',
            alignItems: 'center',
            gap: '4px',
            transition: 'all 0.3s ease',
            boxShadow: '0 2px 8px rgba(0,0,0,0.08)',
            background: 'linear-gradient(135deg, #fef3c7 0%, #fde68a 100%)',
            color: '#92400e',
            border: '1px solid #fbbf24',
            whiteSpace: 'nowrap',
            flexShrink: 0,
            cursor: 'help',
            order: 10
          });

        // Add to filters bar
        $('#jg-map-filters').append(indicator);
        return indicator;
      }

      function updateSyncStatus(state) {
        if (!syncStatusIndicator) {
          syncStatusIndicator = createSyncStatusIndicator();
        }

        // Clear any pending "completed" timeout
        if (syncCompletedTimeout) {
          clearTimeout(syncCompletedTimeout);
          syncCompletedTimeout = null;
        }

        var dot = '<span style="width: 8px; height: 8px; border-radius: 50%; background: #92400e; display: inline-block;"></span>';
        var text = '';
        var tooltipText = 'Status synchronizacji';

        if (state === 'online') {
          syncOnline = true;
          dot = '<span style="width: 8px; height: 8px; border-radius: 50%; background: #16a34a; display: inline-block; animation: pulse-dot 2s infinite;"></span>';
          text = '<span style="font-size:14px;">⟳</span>';
          tooltipText = 'Synchronizacja: Online';
        } else if (state === 'syncing') {
          dot = '<span style="width: 8px; height: 8px; border-radius: 50%; background: #f59e0b; display: inline-block; animation: pulse-dot 1s infinite;"></span>';
          text = '<span style="font-size:14px; animation: spin-icon 1s linear infinite;">⟳</span>';
          tooltipText = 'Synchronizacja w trakcie...';
        } else if (state === 'completed') {
          dot = '<span style="width: 8px; height: 8px; border-radius: 50%; background: #16a34a; display: inline-block;"></span>';
          text = '<span style="font-size:14px;">✓</span>';
          tooltipText = 'Synchronizacja ukończona';

          // Return to "online" after 3 seconds
          syncCompletedTimeout = setTimeout(function() {
            updateSyncStatus('online');
          }, 3000);
        } else {
          // offline or error
          syncOnline = false;
          dot = '<span style="width: 8px; height: 8px; border-radius: 50%; background: #dc2626; display: inline-block;"></span>';
          text = '<span style="font-size:14px;">⚠</span>';
          tooltipText = 'Synchronizacja: ' + (state || 'Offline');
        }

        syncStatusIndicator.html(dot + text).attr('title', tooltipText);
      }

      // Add CSS animations
      if (!$('#jg-sync-animations').length) {
        $('<style id="jg-sync-animations">')
          .text('@keyframes pulse-dot { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.6; transform: scale(0.85); } } @keyframes spin-icon { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }')
          .appendTo('head');
      }

      // WordPress Heartbeat for REAL-TIME synchronization
      // Wrap in document.ready to ensure wp.heartbeat is fully initialized
      $(document).ready(function() {
        if (typeof wp !== 'undefined' && wp.heartbeat) {

          // Set heartbeat interval to 15 seconds (optimal for real-time updates)
          wp.heartbeat.interval(15);

          // Send sync check request on every heartbeat tick
          $(document).on('heartbeat-send.jgMapSync', function(e, data) {
            data.jg_map_check = true;
            data.jg_map_last_check = lastSyncCheck;
          });

          // Process heartbeat response - REAL-TIME sync events
          $(document).on('heartbeat-tick.jgMapSync', function(e, data) {
            if (!data.jg_map_sync) {
              return;
            }

            var syncData = data.jg_map_sync;

            // Update last check timestamp
            lastSyncCheck = syncData.server_time || Math.floor(Date.now() / 1000);

            // CRITICAL: Detect session change (login/logout)
            // If user_id changed, we need to refresh data because visibility rules differ
            var serverUserId = syncData.current_user_id || 0;
            var clientUserId = +CFG.currentUserId || 0;

            if (serverUserId !== clientUserId) {
              // Session changed! Update CFG and force full refresh
              CFG.currentUserId = serverUserId;
              CFG.isLoggedIn = serverUserId > 0;
              CFG.isAdmin = !!syncData.is_admin;

              // Force refresh to get correct data for new session
              updateSyncStatus('syncing');
              refreshData(true).then(function() {
                updateSyncStatus('completed');
              }).catch(function() {
                updateSyncStatus('online');
              });
              return; // Skip normal sync logic, we already refreshed
            }

            // Check if there are new/updated points
            if (syncData.new_points > 0 || (syncData.sync_events && syncData.sync_events.length > 0)) {

              // Show "syncing" status
              updateSyncStatus('syncing');

              // INSTANT refresh - FORCE full refresh to ensure deleted points are removed
              refreshData(true).then(function() {

                // Show "completed" status
                updateSyncStatus('completed');
              }).catch(function(err) {
                updateSyncStatus('online'); // Return to online on error
              });
            } else {
              // No changes, just update to online
              updateSyncStatus('online');
            }

            // Update pending counts for admins
            if (CFG.isAdmin && syncData.pending_counts) {
              // Notification system will handle this via jg-notifications.js
            }
          });

          // Handle connection errors
          $(document).on('heartbeat-error.jgMapSync', function() {
            // For guests (not logged in), show "Online" instead of error
            // Sync is not critical for guests, they just view published points
            if (!CFG.isLoggedIn) {
              updateSyncStatus('online');
            } else {
              updateSyncStatus('Błąd połączenia');
            }
          });

          // Track if first heartbeat response was received
          var firstHeartbeatReceived = false;

          // Mark first heartbeat as received when we get a response
          $(document).on('heartbeat-tick.jgMapSync-first', function(e, data) {
            if (data.jg_map_sync) {
              firstHeartbeatReceived = true;
              $(document).off('heartbeat-tick.jgMapSync-first');
            }
          });

          // Initial status
          updateSyncStatus('Łączenie...');

          // CRITICAL: Trigger IMMEDIATE first heartbeat tick (don't wait 15 seconds!)
          // This ensures sync check happens instantly on page load
          setTimeout(function() {
            if (typeof wp !== 'undefined' && wp.heartbeat) {
              wp.heartbeat.connectNow();
            }
          }, 100); // Small delay to ensure everything is initialized

          // Fallback: If no heartbeat response after 5 seconds, show "Online" for guests
          // This handles cases where heartbeat might not work for unauthenticated users
          setTimeout(function() {
            if (!firstHeartbeatReceived && !CFG.isLoggedIn) {
              updateSyncStatus('online');
            }
          }, 5000);

        } else {
          // No heartbeat available - for guests show "Online", for logged-in users show message
          if (!CFG.isLoggedIn) {
            updateSyncStatus('online');
          } else {
            updateSyncStatus('Brak Heartbeat API');
          }

          // Fallback: Polling every 10 seconds if heartbeat not available - FORCE refresh
          setInterval(function() {
            refreshData(true).catch(function() {});
          }, 10000);
        }

        // Check for updates when page becomes visible
        document.addEventListener('visibilitychange', function() {
          if (!document.hidden) {
            // FORCE refresh when returning to tab to ensure we have latest data
            refreshData(true);
          }
        });
      }); // End of $(document).ready()

      // ========================================================================
      // FLOATING ACTION BUTTON (FAB) - Quick Add Place
      // ========================================================================

      var fabExpanded = false;
      var fabContainer = null;
      var fabToggling = false;

      function createFAB() {
        // Ensure map container has position relative for absolute positioning
        if ($(elMap).css('position') === 'static') {
          $(elMap).css('position', 'relative');
        }

        // Main container
        fabContainer = $('<div>')
          .attr('id', 'jg-fab-container')
          .css({
            position: 'absolute',
            bottom: '30px',
            right: '30px',
            zIndex: 9998,
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'flex-end',
            gap: '12px'
          })
          .on('click', function(e) {
            // Prevent map click event
            e.stopPropagation();
            e.preventDefault();
          })
          .on('wheel', function(e) {
            // Prevent zoom on scroll wheel over FAB
            e.stopPropagation();
            e.preventDefault();
          })
          .on('dblclick', function(e) {
            // Prevent double-click zoom
            e.stopPropagation();
            e.preventDefault();
          })
          .on('mousedown', function(e) {
            // Prevent any map interaction
            e.stopPropagation();
          });

        // Menu items container (hidden by default)
        var menuContainer = $('<div>')
          .attr('id', 'jg-fab-menu')
          .css({
            display: 'none',
            flexDirection: 'column',
            alignItems: 'flex-end',
            gap: '10px',
            marginBottom: '8px'
          });

        // Menu item: Add by address
        var addressOption = $('<div>')
          .addClass('jg-fab-menu-item')
          .css({
            display: 'flex',
            alignItems: 'center',
            gap: '12px',
            cursor: 'pointer',
            opacity: 0,
            transform: 'translateY(10px)',
            transition: 'all 0.3s ease'
          })
          .html(
            '<span style="background: #fff; padding: 10px 16px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); font-size: 14px; font-weight: 600; color: #1f2937; white-space: nowrap;">📍 Po adresie</span>' +
            '<div style="width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.2);"><span style="color: #fff; font-size: 20px;">📍</span></div>'
          )
          .on('click', function(e) {
            e.stopPropagation();
            showAddressInput();
          });

        // Menu item: Add by coordinates
        var coordsOption = $('<div>')
          .addClass('jg-fab-menu-item')
          .css({
            display: 'flex',
            alignItems: 'center',
            gap: '12px',
            cursor: 'pointer',
            opacity: 0,
            transform: 'translateY(10px)',
            transition: 'all 0.3s ease'
          })
          .html(
            '<span style="background: #fff; padding: 10px 16px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); font-size: 14px; font-weight: 600; color: #1f2937; white-space: nowrap;">🎯 Po koordynatach</span>' +
            '<div style="width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.2);"><span style="color: #fff; font-size: 20px;">🎯</span></div>'
          )
          .on('click', function(e) {
            e.stopPropagation();
            showCoordsInput();
          });

        menuContainer.append(addressOption, coordsOption);

        // Main FAB button
        var fabButton = $('<button>')
          .attr('id', 'jg-fab-button')
          .css({
            width: '60px',
            height: '60px',
            borderRadius: '50%',
            background: 'linear-gradient(135deg, #dc2626 0%, #b91c1c 100%)',
            border: 'none',
            cursor: 'pointer',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            boxShadow: '0 4px 12px rgba(220, 38, 38, 0.4)',
            transition: 'all 0.3s ease',
            outline: 'none'
          })
          .html('<span style="color: #fff; font-size: 28px; font-weight: 300; transition: transform 0.3s ease;">+</span>')
          .on('mouseenter', function() {
            $(this).css({
              transform: 'scale(1.1)',
              boxShadow: '0 6px 16px rgba(220, 38, 38, 0.5)'
            });
          })
          .on('mouseleave', function() {
            $(this).css({
              transform: 'scale(1)',
              boxShadow: '0 4px 12px rgba(220, 38, 38, 0.4)'
            });
          })
          .on('click', function(e) {
            e.stopPropagation();
            toggleFAB();
          });

        fabContainer.append(menuContainer, fabButton);
        $(elMap).append(fabContainer);
      }

      function toggleFAB() {
        // Prevent rapid toggling that could cause zoom
        if (fabToggling) {
          return;
        }

        fabToggling = true;
        fabExpanded = !fabExpanded;
        var menu = $('#jg-fab-menu');
        var plusIcon = $('#jg-fab-button span');

        if (fabExpanded) {
          // Expand
          menu.css('display', 'flex');
          setTimeout(function() {
            menu.find('.jg-fab-menu-item').each(function(i) {
              var item = $(this);
              setTimeout(function() {
                item.css({
                  opacity: 1,
                  transform: 'translateY(0)'
                });
              }, i * 50);
            });
          }, 10);
          plusIcon.css('transform', 'rotate(45deg)');

          // Allow next toggle after animation
          setTimeout(function() {
            fabToggling = false;
          }, 200);
        } else {
          // Collapse
          menu.find('.jg-fab-menu-item').css({
            opacity: 0,
            transform: 'translateY(10px)'
          });
          setTimeout(function() {
            menu.css('display', 'none');
            fabToggling = false;
          }, 300);
          plusIcon.css('transform', 'rotate(0deg)');
        }
      }

      function showAddressInput() {
        // Close FAB menu
        toggleFAB();

        // Create input overlay
        var overlay = $('<div>')
          .attr('id', 'jg-fab-input-overlay')
          .css({
            position: 'fixed',
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
            background: 'rgba(0, 0, 0, 0.5)',
            zIndex: 10000,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center'
          })
          .on('click', function(e) {
            if (e.target === this) {
              $(this).remove();
            }
          });

        var inputWrapper = $('<div>')
          .css({
            background: '#fff',
            borderRadius: '12px',
            padding: '24px',
            boxShadow: '0 8px 24px rgba(0,0,0,0.2)',
            minWidth: '400px',
            maxWidth: '90%',
            position: 'relative'
          });

        var title = $('<h3>')
          .css({
            margin: '0 0 16px 0',
            fontSize: '18px',
            fontWeight: '700',
            color: '#1f2937'
          })
          .text('📍 Dodaj miejsce po adresie');

        var inputContainer = $('<div>')
          .css({
            position: 'relative'
          });

        var input = $('<input>')
          .attr({
            type: 'text',
            placeholder: 'np. ul. 1 Maja 14, Jelenia Góra',
            autocomplete: 'off'
          })
          .css({
            width: '100%',
            padding: '12px',
            fontSize: '14px',
            border: '2px solid #e5e7eb',
            borderRadius: '8px',
            outline: 'none',
            fontFamily: 'inherit',
            boxSizing: 'border-box'
          })
          .on('focus', function() {
            $(this).css('border-color', '#dc2626');
          })
          .on('blur', function(e) {
            setTimeout(function() {
              $(e.target).css('border-color', '#e5e7eb');
              suggestionsList.hide();
            }, 200);
          });

        // Suggestions dropdown
        var suggestionsList = $('<div>')
          .css({
            position: 'absolute',
            top: '100%',
            left: 0,
            right: 0,
            background: '#fff',
            border: '2px solid #dc2626',
            borderTop: 'none',
            borderRadius: '0 0 8px 8px',
            maxHeight: '200px',
            overflowY: 'auto',
            zIndex: 10001,
            display: 'none',
            boxShadow: '0 4px 12px rgba(0,0,0,0.15)'
          });

        var searchTimeout = null;
        var selectedSuggestion = null;

        input.on('input', function() {
          var query = $(this).val().trim();
          debugLog('[JG FAB] Input changed, query length:', query.length, 'value:', query);

          if (searchTimeout) clearTimeout(searchTimeout);

          if (query.length < 3) {
            debugLog('[JG FAB] Query too short, hiding suggestions');
            suggestionsList.hide().empty();
            return;
          }

          debugLog('[JG FAB] Scheduling search in 300ms');
          // Debounce search by 300ms
          searchTimeout = setTimeout(function() {
            searchAddressSuggestions(query, suggestionsList);
          }, 300);
        });

        input.on('keydown', function(e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedSuggestion) {
              // Use selected suggestion
              var lat = selectedSuggestion.lat;
              var lng = selectedSuggestion.lon;
              goToLocationAndOpenModal(lat, lng);
              overlay.remove();
            } else {
              // Geocode what user typed
              var address = $(this).val().trim();
              if (address) {
                geocodeAddress(address);
                overlay.remove();
              }
            }
          } else if (e.key === 'Escape') {
            overlay.remove();
          } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            var items = suggestionsList.find('.suggestion-item');
            if (items.length > 0) {
              items.first().focus();
            }
          }
        });

        var hint = $('<p>')
          .css({
            margin: '8px 0 0 0',
            fontSize: '12px',
            color: '#6b7280'
          })
          .text('Zacznij pisać aby zobaczyć podpowiedzi, lub naciśnij Enter');

        inputContainer.append(input, suggestionsList);
        inputWrapper.append(title, inputContainer, hint);
        overlay.append(inputWrapper);
        $('body').append(overlay);

        // Search suggestions function
        function searchAddressSuggestions(query, container) {
          debugLog('[JG FAB] Searching for:', query);

          // Use backend proxy endpoint to avoid CSP issues
          $.ajax({
            url: CFG.ajax,
            type: 'POST',
            data: {
              action: 'jg_search_address',
              _ajax_nonce: CFG.nonce,
              query: query
            },
            success: function(response) {
              debugLog('[JG FAB] Got response:', response);
              container.empty();

              if (response.success && response.data && response.data.length > 0) {
                var results = response.data;
                debugLog('[JG FAB] Processing', results.length, 'results');

                results.forEach(function(result) {
                  var displayName = result.display_name;

                  var item = $('<div>')
                    .addClass('suggestion-item')
                    .attr('tabindex', '0')
                    .css({
                      padding: '10px 12px',
                      cursor: 'pointer',
                      fontSize: '13px',
                      color: '#374151',
                      borderBottom: '1px solid #f3f4f6',
                      transition: 'background 0.2s'
                    })
                    .text(displayName)
                    .on('mouseenter', function() {
                      $(this).css('background', '#fef3c7');
                      selectedSuggestion = result;
                    })
                    .on('mouseleave', function() {
                      $(this).css('background', '#fff');
                    })
                    .on('click', function() {
                      var lat = parseFloat(result.lat);
                      var lng = parseFloat(result.lon);
                      goToLocationAndOpenModal(lat, lng);
                      overlay.remove();
                    })
                    .on('keydown', function(e) {
                      if (e.key === 'Enter') {
                        $(this).click();
                      } else if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        var next = $(this).next('.suggestion-item');
                        if (next.length > 0) {
                          next.focus();
                        }
                      } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        var prev = $(this).prev('.suggestion-item');
                        if (prev.length > 0) {
                          prev.focus();
                        } else {
                          input.focus();
                        }
                      }
                    });

                  container.append(item);
                });

                container.show();
              } else {
                debugLog('[JG FAB] No results found or empty response');
                container.hide();
              }
            },
            error: function(xhr, status, error) {
              debugError('[JG FAB] AJAX Error:', status, error);
              if (xhr.responseJSON) {
                debugError('[JG FAB] Error response:', xhr.responseJSON);
              }
              container.hide();
            }
          });
        }

        // Focus input after a short delay
        setTimeout(function() {
          input.focus();
        }, 100);
      }

      function showCoordsInput() {
        // Close FAB menu
        toggleFAB();

        // Create input overlay
        var overlay = $('<div>')
          .attr('id', 'jg-fab-input-overlay')
          .css({
            position: 'fixed',
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
            background: 'rgba(0, 0, 0, 0.5)',
            zIndex: 10000,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center'
          })
          .on('click', function(e) {
            if (e.target === this) {
              $(this).remove();
            }
          });

        var inputBox = $('<div>')
          .css({
            background: '#fff',
            borderRadius: '12px',
            padding: '24px',
            boxShadow: '0 8px 24px rgba(0,0,0,0.2)',
            minWidth: '400px',
            maxWidth: '90%'
          });

        var title = $('<h3>')
          .css({
            margin: '0 0 16px 0',
            fontSize: '18px',
            fontWeight: '700',
            color: '#1f2937'
          })
          .text('🎯 Dodaj miejsce po koordynatach');

        var input = $('<input>')
          .attr({
            type: 'text',
            placeholder: 'np. 50.9029, 15.7277 lub 50.9029, 15.7277'
          })
          .css({
            width: '100%',
            padding: '12px',
            fontSize: '14px',
            border: '2px solid #e5e7eb',
            borderRadius: '8px',
            outline: 'none',
            fontFamily: 'inherit',
            boxSizing: 'border-box'
          })
          .on('focus', function() {
            $(this).css('border-color', '#dc2626');
          })
          .on('blur', function() {
            $(this).css('border-color', '#e5e7eb');
          })
          .on('keydown', function(e) {
            if (e.key === 'Enter') {
              var coords = $(this).val().trim();
              if (coords) {
                parseAndGoToCoords(coords);
                overlay.remove();
              }
            } else if (e.key === 'Escape') {
              overlay.remove();
            }
          });

        var hint = $('<p>')
          .css({
            margin: '8px 0 0 0',
            fontSize: '12px',
            color: '#6b7280'
          })
          .text('Wpisz współrzędne (szerokość, długość) i naciśnij Enter');

        inputBox.append(title, input, hint);
        overlay.append(inputBox);
        $('body').append(overlay);

        // Focus input after a short delay
        setTimeout(function() {
          input.focus();
        }, 100);
      }

      function geocodeAddress(address) {
        // Use Nominatim for geocoding (free, no API key needed)
        // Add context of Jelenia Góra if not already in query
        var searchQuery = address;
        if (address.toLowerCase().indexOf('jelenia') === -1 && address.toLowerCase().indexOf('góra') === -1) {
          searchQuery = address + ', Jelenia Góra, Poland';
        }

        var url = 'https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(searchQuery) + '&limit=1';

        $.ajax({
          url: url,
          type: 'GET',
          success: function(results) {
            if (results && results.length > 0) {
              var lat = parseFloat(results[0].lat);
              var lng = parseFloat(results[0].lon);
              goToLocationAndOpenModal(lat, lng);
            } else {
              showMessage('Nie znaleziono adresu "' + address + '". Spróbuj ponownie z pełniejszym adresem.', 'error');
            }
          },
          error: function() {
            showMessage('Błąd podczas wyszukiwania adresu. Spróbuj ponownie.', 'error');
          }
        });
      }

      function parseAndGoToCoords(coordsStr) {
        // Parse coordinates from string (supports various formats)
        // Examples: "50.9029, 15.7277" or "50.9029 15.7277" or "50.9029,15.7277"
        var parts = coordsStr.replace(/\s+/g, ' ').replace(/,/g, ' ').split(' ').filter(function(p) { return p; });

        if (parts.length !== 2) {
          showMessage('Nieprawidłowy format współrzędnych. Użyj formatu: szerokość, długość', 'error');
          return;
        }

        var lat = parseFloat(parts[0]);
        var lng = parseFloat(parts[1]);

        if (isNaN(lat) || isNaN(lng)) {
          showMessage('Nieprawidłowe współrzędne. Użyj liczb dziesiętnych.', 'error');
          return;
        }

        // Validate coordinates are in reasonable range
        if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
          showMessage('Współrzędne poza zakresem. Szerokość: -90 do 90, Długość: -180 do 180', 'error');
          return;
        }

        goToLocationAndOpenModal(lat, lng);
      }

      function goToLocationAndOpenModal(lat, lng) {
        // Fly to location with maximum zoom (19)
        map.flyTo([lat, lng], 19, {
          duration: 1.5
        });

        // Wait for animation to complete, then open add modal
        setTimeout(function() {
          debugLog('[JG FAB] Opening add modal for:', lat, lng);
          openAddPlaceModal(lat, lng);
        }, 1600);
      }

      // Helper function to open add place modal at specific coordinates
      function openAddPlaceModal(lat, lng) {
        // Check if user is logged in
        if (!CFG.isLoggedIn) {
          showAlert('Musisz być zalogowany, aby dodać miejsce.').then(function() {
            openLoginModal();
          });
          return;
        }

        // Check if user is banned or has add_places restriction
        if (window.JG_USER_RESTRICTIONS) {
          if (window.JG_USER_RESTRICTIONS.is_banned) {
            showAlert('Nie możesz dodawać miejsc - Twoje konto jest zbanowane.');
            return;
          }
          if (window.JG_USER_RESTRICTIONS.restrictions && window.JG_USER_RESTRICTIONS.restrictions.indexOf('add_places') !== -1) {
            showAlert('Nie możesz dodawać miejsc - masz aktywną blokadę dodawania miejsc.');
            return;
          }
        }

        // Check flood protection
        var now = Date.now();
        var remainingMs = FLOOD_DELAY - (now - lastSubmitTime);

        if (lastSubmitTime > 0 && remainingMs > 0) {
          var sec = Math.ceil(remainingMs / 1000);

          // For admins: show modal with countdown and bypass button
          if (CFG.isAdmin) {
            showConfirm(
              'Minęło dopiero ' + Math.floor((now - lastSubmitTime) / 1000) + ' sekund od ostatniego dodania miejsca.\n\n' +
              'Poczekaj jeszcze <strong id="jg-cooldown-timer-fab">' + sec + '</strong> sekund lub dodaj pomimo limitu.',
              'Limit czasu',
              'Dodaj pomimo tego'
            ).then(function(confirmed) {
              if (confirmed) {
                // Bypass: reset lastSubmitTime and continue
                lastSubmitTime = 0;
                setLastSubmitTime(0);
                // Proceed to open modal
                openAddPlaceModal(lat, lng);
              }
            });

            // Start countdown timer
            var timerEl = null;
            var countdownInterval = setInterval(function() {
              timerEl = document.getElementById('jg-cooldown-timer-fab');
              if (timerEl) {
                var remaining = Math.ceil((FLOOD_DELAY - (Date.now() - lastSubmitTime)) / 1000);
                if (remaining <= 0) {
                  clearInterval(countdownInterval);
                  timerEl.textContent = '0';
                } else {
                  timerEl.textContent = remaining.toString();
                }
              } else {
                clearInterval(countdownInterval);
              }
            }, 1000);

            return;
          } else {
            // For regular users: just show alert
            showAlert('Poczekaj jeszcze ' + sec + ' sekund.');
            return;
          }
        }

        var latFixed = parseFloat(lat).toFixed(6);
        var lngFixed = parseFloat(lng).toFixed(6);

        debugLog('[JG FAB] Fetching daily limits...');

        // Fetch daily limits and open modal
        api('jg_get_daily_limits', {})
          .then(function(limits) {
            debugLog('[JG FAB] Got limits, building modal...');

            var limitsHtml = '';
            if (!limits.is_admin) {
              var photoRemaining = (limits.photo_limit_mb - limits.photo_used_mb).toFixed(2);
              limitsHtml = '<div class="cols-2" style="background:#f0f9ff;border:2px solid #3b82f6;border-radius:8px;padding:12px;margin-bottom:12px">' +
                '<strong style="color:#1e40af">Pozostałe dzienne limity:</strong>' +
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px">' +
                '<div style="background:#fff;padding:8px;border-radius:4px;text-align:center">' +
                '<div style="font-size:24px;font-weight:700;color:#3b82f6">' + limits.places_remaining + '</div>' +
                '<div style="font-size:11px;color:#666">miejsc/ciekawostek</div>' +
                '</div>' +
                '<div style="background:#fff;padding:8px;border-radius:4px;text-align:center">' +
                '<div style="font-size:24px;font-weight:700;color:#3b82f6">' + limits.reports_remaining + '</div>' +
                '<div style="font-size:11px;color:#666">zgłoszeń</div>' +
                '</div>' +
                '</div>' +
                '<div style="margin-top:8px;padding:8px;background:#fff;border-radius:4px;text-align:center">' +
                '<div style="font-size:18px;font-weight:700;color:#8b5cf6">' + photoRemaining + ' MB / ' + limits.photo_limit_mb + ' MB</div>' +
                '<div style="font-size:11px;color:#666">pozostały miesięczny limit zdjęć</div>' +
                '</div>' +
                '</div>';
            }

            var formHtml = '<header><h3>Dodaj nowe miejsce</h3><button class="jg-close" id="add-close">&times;</button></header>' +
              '<form id="add-form" class="jg-grid cols-2">' +
              '<input type="hidden" name="lat" id="add-lat-input" value="' + latFixed + '">' +
              '<input type="hidden" name="lng" id="add-lng-input" value="' + lngFixed + '">' +
              '<input type="hidden" name="address" id="add-address-input" value="">' +
              limitsHtml +
              '<div class="cols-2" id="add-address-display" style="padding:8px 12px;background:#f3f4f6;border-left:3px solid #8d2324;border-radius:4px;font-size:13px;color:#374151;margin-bottom:8px"><strong>📍 Wczytywanie adresu...</strong></div>' +
              '<label>Tytuł* <input name="title" required placeholder="Nazwa miejsca" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px"></label>' +
              '<label>Typ* <select name="type" id="add-type-select" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' +
              '<option value="zgloszenie">Zgłoszenie</option>' +
              '<option value="ciekawostka">Ciekawostka</option>' +
              '<option value="miejsce">Miejsce</option>' +
              '</select></label>' +
              '<label class="cols-2" id="add-category-field" style="display:block"><span style="color:#dc2626">Kategoria zgłoszenia*</span> <select name="category" id="add-category-select" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' +
              generateCategoryOptions('') +
              '</select></label>' +
              '<label class="cols-2">Opis* (max 800 znaków)<textarea name="content" id="add-content-input" required maxlength="800" placeholder="Opisz miejsce..." style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;resize:vertical;min-height:80px"></textarea><div id="add-content-counter" style="font-size:11px;color:#666;margin-top:4px">0 / 800 znaków</div></label>' +
              '<label class="cols-2">Zdjęcia (opcjonalne, max 6)<input type="file" name="images" id="add-images-input" accept="image/*" multiple style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px"></label>' +
              '<div id="add-images-preview" class="cols-2" style="display:none;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:8px;margin-top:8px"></div>' +
              '<div class="cols-2" style="display:flex;gap:12px;justify-content:flex-end;margin-top:16px">' +
              '<button type="button" class="jg-btn jg-btn--ghost" id="add-cancel">Anuluj</button>' +
              '<button type="submit" class="jg-btn">Wyślij do moderacji</button>' +
              '</div>' +
              '<div id="add-msg" class="cols-2" style="font-size:12px;color:#555"></div>' +
              '</form>';

            debugLog('[JG FAB] Opening modal...');
            open(modalAdd, formHtml);

            // Setup modal handlers (same as in map click handler)
            qs('#add-close', modalAdd).onclick = function() {
              close(modalAdd);
            };

            qs('#add-cancel', modalAdd).onclick = function() {
              close(modalAdd);
            };

            // Perform reverse geocoding to populate address
            var addressInput = qs('#add-address-input', modalAdd);
            var addressDisplay = qs('#add-address-display', modalAdd);

            if (addressDisplay && addressInput) {
              var formData = new FormData();
              formData.append('action', 'jg_reverse_geocode');
              formData.append('lat', latFixed);
              formData.append('lng', lngFixed);

              fetch(CFG.ajax, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
              })
              .then(function(r) { return r.json(); })
              .then(function(response) {
                if (response.success && response.data && response.data.display_name) {
                  var data = response.data;
                  var addr = data.address || {};
                  var street = addr.road || '';
                  var houseNumber = addr.house_number || '';
                  var city = addr.city || addr.town || addr.village || 'Jelenia Góra';

                  var fullAddress = '';
                  if (street && houseNumber) {
                    fullAddress = street + ' ' + houseNumber + ', ' + city;
                  } else if (street) {
                    fullAddress = street + ', ' + city;
                  } else {
                    fullAddress = city;
                  }

                  addressDisplay.innerHTML = '<strong>📍 ' + fullAddress + '</strong>';
                  addressInput.value = fullAddress;
                } else {
                  addressDisplay.innerHTML = '<strong>📍 Nie udało się odczytać adresu. Współrzędne: ' + latFixed + ', ' + lngFixed + '</strong>';
                  addressInput.value = latFixed + ', ' + lngFixed;
                }
              })
              .catch(function(err) {
                debugError('[JG FAB] Reverse geocoding error:', err);
                addressDisplay.innerHTML = '<strong>📍 Błąd pobierania adresu. Współrzędne: ' + latFixed + ', ' + lngFixed + '</strong>';
                addressInput.value = latFixed + ', ' + lngFixed;
              });
            }

            // Setup form handlers
            var form = qs('#add-form', modalAdd);
            var msg = qs('#add-msg', modalAdd);

            // Character counter for description
            var contentInput = qs('#add-content-input', modalAdd);
            var contentCounter = qs('#add-content-counter', modalAdd);
            if (contentInput && contentCounter) {
              contentInput.addEventListener('input', function() {
                var length = this.value.length;
                var maxLength = 800;
                contentCounter.textContent = length + ' / ' + maxLength + ' znaków';
                if (length > maxLength * 0.9) {
                  contentCounter.style.color = '#d97706';
                } else {
                  contentCounter.style.color = '#666';
                }
              });
            }

            // Image preview functionality
            var imagesInput = qs('#add-images-input', modalAdd);
            var imagesPreview = qs('#add-images-preview', modalAdd);

            if (imagesInput) {
              imagesInput.addEventListener('change', function(e) {
                imagesPreview.innerHTML = '';
                var files = e.target.files;

                if (files.length > 6) {
                  msg.textContent = 'Uwaga: Możesz dodać maksymalnie 6 zdjęć. Pierwsze 6 zostanie użytych.';
                  msg.style.color = '#d97706';
                } else if (msg.textContent.indexOf('maksymalnie 6') !== -1) {
                  msg.textContent = '';
                }

                if (files.length > 0) {
                  imagesPreview.style.display = 'grid';
                  var maxFiles = Math.min(files.length, 6);
                  for (var i = 0; i < maxFiles; i++) {
                    var file = files[i];
                    var reader = new FileReader();

                    reader.onload = (function(f) {
                      return function(e) {
                        var imgHtml = '<div style="position:relative;aspect-ratio:1;border-radius:8px;overflow:hidden;border:2px solid #e5e7eb">' +
                          '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover" alt="Podgląd">' +
                          '</div>';
                        imagesPreview.innerHTML += imgHtml;
                      };
                    })(file);

                    reader.readAsDataURL(file);
                  }
                } else {
                  imagesPreview.style.display = 'none';
                }
              });
            }

            // Toggle category field based on type selection
            var typeSelect = qs('#add-type-select', modalAdd);
            var categoryField = qs('#add-category-field', modalAdd);
            var categorySelect = qs('#add-category-select', modalAdd);

            if (typeSelect && categoryField && categorySelect) {
              // Function to toggle category field visibility
              function toggleCategoryField() {
                if (typeSelect.value === 'zgloszenie') {
                  categoryField.style.display = 'block';
                  categorySelect.setAttribute('required', 'required');
                } else {
                  categoryField.style.display = 'none';
                  categorySelect.removeAttribute('required');
                  categorySelect.value = ''; // Clear selection when hidden
                }
              }

              // Initial toggle on page load (default is zgloszenie)
              toggleCategoryField();

              // Listen for changes
              typeSelect.addEventListener('change', toggleCategoryField);
            }

            // Form submission handler
            form.onsubmit = function(e) {
              e.preventDefault();
              msg.textContent = 'Wysyłanie...';

              var fd = new FormData(form);
              fd.append('action', 'jg_submit_point');
              fd.append('_ajax_nonce', CFG.nonce);

              fetch(CFG.ajax, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
              })
              .then(function(r) {
                return r.text();
              })
              .then(function(t) {
                var j = null;
                try {
                  j = JSON.parse(t);
                } catch (_) {}

                if (!j || j.success === false) {
                  // Handle duplicate point error specially
                  if (j && j.data && j.data.duplicate_point_id) {
                    var duplicatePointId = j.data.duplicate_point_id;
                    msg.innerHTML = (j.data.message || 'Błąd') + ' <br><button style="margin-top:8px;padding:6px 12px;background:#8d2324;color:#fff;border:none;border-radius:4px;cursor:pointer" onclick="' +
                      'document.getElementById(\'jg-map-modal-add\').style.display=\'none\';' +
                      'window.location.hash=\'#point-' + duplicatePointId + '\';' +
                      '">Zobacz istniejące zgłoszenie</button>';
                    msg.style.color = '#b91c1c';
                    return;
                  }
                  throw new Error((j && j.data && j.data.message) || 'Błąd');
                }

                var submitTime = Date.now();
              lastSubmitTime = submitTime;
              setLastSubmitTime(submitTime);

                msg.textContent = 'Wysłano do moderacji! Odświeżanie...';
                msg.style.color = '#15803d';
                form.reset();

                // Immediate refresh for better UX
                refreshAll().then(function() {
                  msg.textContent = 'Wysłano do moderacji! Miejsce pojawi się po zaakceptowaniu.';

                  // Show special info modal for reports
                  if (j.data && j.data.show_report_info_modal && j.data.case_id) {
                    setTimeout(function() {
                      close(modalAdd);

                      var modalMessage = 'Twoje zgłoszenie zostało przyjęte i otrzymało unikalny numer sprawy: <strong>' + j.data.case_id + '</strong>.\n\n' +
                        'Teraz zostanie poddane weryfikacji przez nasz zespół. Po weryfikacji, jeśli zgłoszenie spełni nasze wytyczne, zostanie ono przekazane do właściwej instytucji (np. Straż Miejska, Urząd Miasta, administratorzy osiedli).\n\n' +
                        'Monitorujemy status każdego zgłoszenia i aktualizujemy jego statusy na mapie. Możesz śledzić postępy rozwiązywania problemu, wchodząc na mapę i klikając na pineskę Twojego zgłoszenia.\n\n' +
                        '<strong>Ważne:</strong> Portal nie daje gwarancji rozwiązania problemu, gdyż nie jest z definicji instytucją pośredniczącą, a jedynie organizacją, która stara się naświetlać istnienie nieprawidłowości w przestrzeni publicznej miasta Jelenia Góra oraz jej okolic.';

                      showAlert(modalMessage.replace(/\n\n/g, '<br><br>'));
                    }, 800);
                  } else {
                    setTimeout(function() {
                      close(modalAdd);
                    }, 800);
                  }
                }).catch(function(err) {
                  debugError('[JG FAB] Błąd odświeżania:', err);
                  setTimeout(function() {
                    close(modalAdd);
                  }, 1000);
                });
              })
              .catch(function(err) {
                msg.textContent = err.message || 'Błąd';
                msg.style.color = '#b91c1c';
              });
            };
          })
          .catch(function(err) {
            debugError('[JG FAB] Error fetching limits:', err);
            showMessage('Błąd podczas pobierania limitów. Spróbuj ponownie.', 'error');
          });
      }

      // Create FAB on init
      createFAB();

      // Export map and openDetails as global functions for use by sidebar widget
      window.jgMap = map;
      window.openDetails = openDetails;

    } catch (e) {
      showError('Błąd: ' + e.message);
    }
  }
})(jQuery);
