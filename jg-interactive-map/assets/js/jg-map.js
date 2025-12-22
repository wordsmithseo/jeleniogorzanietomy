/**
 * JG Interactive Map - Frontend JavaScript
 * Version: 3.0.0
 */

(function($) {
  'use strict';

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

      // Clear localStorage cache (both old and new versions)
      try {
        localStorage.removeItem('jg_map_cache');
        localStorage.removeItem('jg_map_cache_version');
        localStorage.removeItem('jg_map_cache_v2');
        localStorage.removeItem('jg_map_cache_version_v2');
        // v3 will be used from now on, but clear old versions on page load
      } catch (e) {
        console.error('[JG MAP] Failed to clear localStorage:', e);
      }
    });
  }

  var loadingEl = document.getElementById('jg-map-loading');
  var errorEl = document.getElementById('jg-map-error');
  var errorMsg = document.getElementById('error-msg');
  var loadStartTime = Date.now(); // Track when loading started
  var minLoadingTime = 500; // Minimum time to show loader (ms)

  console.log('[JG MAP] Loader element:', loadingEl ? 'found' : 'NOT FOUND');
  console.log('[JG MAP] Loading started at', new Date(loadStartTime).toISOString());

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

  function showError(msg) {
    console.error('[JG MAP]', msg);
    if (loadingEl) loadingEl.style.display = 'none';
    if (errorEl) errorEl.style.display = 'block';
    if (errorMsg) errorMsg.textContent = msg;
  }

  function hideLoading() {
    var elapsed = Date.now() - loadStartTime;
    var remaining = minLoadingTime - elapsed;

    console.log('[JG MAP] hideLoading() called, elapsed:', elapsed + 'ms');

    if (remaining > 0) {
      console.log('[JG MAP] Delaying hide by', remaining + 'ms for better UX');
      setTimeout(function() {
        if (loadingEl) {
          loadingEl.style.display = 'none';
          console.log('[JG MAP] Loader hidden after delay');
        }
      }, remaining);
    } else {
      if (loadingEl) {
        loadingEl.style.display = 'none';
        console.log('[JG MAP] Loader hidden immediately');
      } else {
        console.log('[JG MAP] Loader element not found!');
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
      if (editProfileBtn) {
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
      }

      // Login button handler
      var loginBtn = document.getElementById('jg-login-btn');
      if (loginBtn) {
        loginBtn.addEventListener('click', function() {
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

          document.getElementById('submit-login-btn').addEventListener('click', function() {
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
          });
        });
      }

      // Register button handler
      var registerBtn = document.getElementById('jg-register-btn');
      if (registerBtn) {
        registerBtn.addEventListener('click', function() {
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

          document.getElementById('submit-register-btn').addEventListener('click', function() {
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
          });
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

        document.getElementById('submit-forgot-btn').addEventListener('click', function() {
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
      }

      function close(bg) {
        if (!bg) return;
        var c = qs('.jg-modal, .jg-lightbox', bg);
        if (c) {
          c.className = c.className.replace(/\bjg-modal--\w+/g, '');
          if (!c.classList.contains('jg-modal') && !c.classList.contains('jg-lightbox')) {
            c.className = 'jg-modal';
          }
        }
        bg.style.display = 'none';
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

      // Define bounds for Jelenia Góra region (stricter)
      var southWest = L.latLng(50.82, 15.62);
      var northEast = L.latLng(50.96, 15.82);
      var bounds = L.latLngBounds(southWest, northEast);

      // Detect mobile device
      var isMobile = window.innerWidth <= 768;

      var map = L.map(elMap, {
        zoomControl: true,
        scrollWheelZoom: !isMobile, // Disable scroll zoom on mobile
        dragging: !isMobile, // Disable dragging on mobile to allow page scroll
        minZoom: 12,
        maxZoom: 19,
        maxBounds: bounds,
        maxBoundsViscosity: 1.0,
        tap: isMobile, // Enable tap on mobile
        touchZoom: isMobile // Enable pinch zoom on mobile
      }).setView([lat, lng], zoom);

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
            console.log('[JG MAP] Cluster is now ready, pendingData:', pendingData ? pendingData.length : 0);

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
            if (pendingData && pendingData.length > 0) {
              console.log('[JG MAP] Processing pending data:', pendingData.length, 'points');
              setTimeout(function() { draw(pendingData); }, 300);
            }
          } catch (e) {
            console.error('[JG MAP] Błąd tworzenia clustera:', e);
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

      var lastSubmitTime = 0;
      var FLOOD_DELAY = 60000;
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
            showConfirm('Musisz być zalogowany. Przejść do logowania?').then(function(confirmed) {
              if (confirmed) {
                window.location.href = CFG.loginUrl;
              }
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
          if (lastSubmitTime > 0 && (now - lastSubmitTime) < FLOOD_DELAY) {
            var sec = Math.ceil((FLOOD_DELAY - (now - lastSubmitTime)) / 1000);
            showAlert('Poczekaj jeszcze ' + sec + ' sekund.');
            return;
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
                '<option value="">-- Wybierz kategorię --</option>' +
                '<optgroup label="Zgłoszenie usterek infrastruktury">' +
                '<option value="dziura_w_jezdni">🕳️ Dziura w jezdni</option>' +
                '<option value="uszkodzone_chodniki">🚶 Uszkodzone chodniki</option>' +
                '<option value="znaki_drogowe">🚸 Brakujące lub zniszczone znaki drogowe</option>' +
                '<option value="oswietlenie">💡 Awarie oświetlenia ulicznego</option>' +
                '</optgroup>' +
                '<optgroup label="Porządek i bezpieczeństwo">' +
                '<option value="dzikie_wysypisko">🗑️ Dzikie wysypisko śmieci</option>' +
                '<option value="przepelniony_kosz">♻️ Przepełniony kosz na śmieci</option>' +
                '<option value="graffiti">🎨 Graffiti</option>' +
                '<option value="sliski_chodnik">⚠️ Śliski chodnik</option>' +
                '</optgroup>' +
                '<optgroup label="Zieleń i estetyka miasta">' +
                '<option value="nasadzenie_drzew">🌳 Potrzeba nasadzenia drzew</option>' +
                '<option value="nieprzycięta_gałąź">🌿 Nieprzycięta gałąź zagrażająca niebezpieczeństwu</option>' +
                '</optgroup>' +
                '<optgroup label="Transport i komunikacja">' +
                '<option value="brak_przejscia">🚦 Brak przejścia dla pieszych</option>' +
                '<option value="przystanek_autobusowy">🚏 Potrzeba przystanku autobusowego</option>' +
                '<option value="organizacja_ruchu">🚗 Problem z organizacją ruchu</option>' +
                '<option value="korki">🚙 Powtarzające się korki</option>' +
                '</optgroup>' +
                '<optgroup label="Inicjatywy społeczne i rozwojowe">' +
                '<option value="mala_infrastruktura">🎪 Propozycja nowych obiektów małej infrastruktury</option>' +
                '</optgroup>' +
                '</select></label>' +
                '<label class="cols-2">Opis <textarea name="content" rows="4" maxlength="200" id="add-content-input" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px"></textarea><div id="add-content-counter" style="font-size:12px;color:#666;margin-top:4px;text-align:right">0 / 200 znaków</div></label>' +
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
                  var maxLength = 200;
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
                console.log('[JG MAP] Starting automatic reverse geocoding for:', lat, lng);

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
                  console.log('[JG MAP] Reverse geocoding response:', response);

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

                    console.log('[JG MAP] Address resolved:', fullAddress);
                    addressInput.value = fullAddress;
                    addressDisplay.innerHTML = '<strong>📍 Adres:</strong> ' + esc(fullAddress);
                  } else {
                    console.warn('[JG MAP] No address found in response');
                    addressDisplay.innerHTML = '<strong>📍 Adres:</strong> Nie znaleziono adresu dla tej lokalizacji';
                    addressInput.value = '';
                  }
                })
                .catch(function(err) {
                  console.error('[JG MAP] Reverse geocoding error:', err);
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
            console.log('[JG MAP DEBUG] ===== SUBMITTING FORM =====');
            console.log('[JG MAP DEBUG] Form action: jg_submit_point');
            var formDataObj = {};
            fd.forEach(function(value, key) {
              formDataObj[key] = value;
              console.log('[JG MAP DEBUG]   ' + key + ': ' + value);
            });
            console.log('[JG MAP DEBUG] FormData object:', formDataObj);
            console.log('[JG MAP DEBUG] =============================');

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

              lastSubmitTime = Date.now();

              msg.textContent = 'Wysłano do moderacji! Odświeżanie...';
              msg.style.color = '#15803d';
              form.reset();

              // Immediate refresh for better UX
              refreshAll().then(function() {
                msg.textContent = 'Wysłano do moderacji! Miejsce pojawi się po zaakceptowaniu.';
                setTimeout(function() {
                  close(modalAdd);
                }, 800);
              }).catch(function(err) {
                console.error('[JG MAP] Błąd odświeżania:', err);
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
        var hasReports = (CFG.isAdmin && p.reports_count > 0);

        // Pin sizes - much bigger for visibility! Sponsored even bigger
        var pinHeight = sponsored ? 90 : 72;
        var pinWidth = sponsored ? 60 : 48;

        // Anchor at the bottom tip of the pin (where it points to the location)
        var anchor = [pinWidth / 2, pinHeight];

        // Determine gradient colors and circle color based on type and state
        var gradientId = 'gradient-' + (p.id || Math.random());
        var gradientStart, gradientMid, gradientEnd;
        var circleColor; // Color for the inner circle

        if (isPending) {
          // Red gradient for pending
          gradientStart = '#dc2626';
          gradientMid = '#ef4444';
          gradientEnd = '#dc2626';
          circleColor = '#7f1d1d'; // Dark red
        } else if (isEdit) {
          // Purple gradient for edit
          gradientStart = '#9333ea';
          gradientMid = '#a855f7';
          gradientEnd = '#9333ea';
          circleColor = '#581c87'; // Dark purple
        } else if (sponsored) {
          // Gold gradient for sponsored
          gradientStart = '#f59e0b';
          gradientMid = '#fbbf24';
          gradientEnd = '#f59e0b';
          circleColor = null; // No circle, will use star emoji
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

        // Define gradient and shadow filter
        svgPin += '<defs>' +
          '<linearGradient id="' + gradientId + '" x1="0%" y1="0%" x2="100%" y2="100%">' +
          '<stop offset="0%" style="stop-color:' + gradientStart + ';stop-opacity:1" />' +
          '<stop offset="50%" style="stop-color:' + gradientMid + ';stop-opacity:1" />' +
          '<stop offset="100%" style="stop-color:' + gradientEnd + ';stop-opacity:1" />' +
          '</linearGradient>' +
          '<filter id="soft-shadow-' + gradientId + '" x="-50%" y="-50%" width="200%" height="200%">' +
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

        // Add inner circle (Google Maps style) - only for non-sponsored
        if (circleColor) {
          svgPin += '<circle cx="16" cy="13" r="5.5" fill="' + circleColor + '"/>';
        }

        svgPin += '</svg>';

        // Reports counter
        var reportsHtml = '';
        if (hasReports) {
          reportsHtml = '<span class="jg-reports-counter">' + p.reports_count + '</span>';
        }

        // Deletion request indicator
        var deletionHtml = '';
        if (CFG.isAdmin && p.is_deletion_requested) {
          deletionHtml = '<span class="jg-deletion-badge">✕</span>';
        }

        // Label for pin
        var labelClass = 'jg-marker-label';
        if (sponsored) labelClass += ' jg-marker-label--promo';
        if (isPending) labelClass += ' jg-marker-label--pending';
        if (isEdit) labelClass += ' jg-marker-label--edit';

        var suffix = '';
        if (isEdit) suffix = ' (edycja)';
        else if (isPending) suffix = ' (oczekuje)';

        var labelHtml = '<span class="' + labelClass + '">' + esc(p.title || 'Bez nazwy') + suffix + '</span>';

        // Category emoji mapping for reports
        var categoryEmojis = {
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

        // Star emoji for sponsored pins, category emoji for reports, or nothing for others
        var centerContent = '';

        if (sponsored) {
          var emojiFontSize = 28;
          var emojiStyle = 'position:absolute;' +
            'top:' + (pinHeight * 0.32) + 'px;' +
            'left:50%;' +
            'transform:translate(-50%,-50%);' +
            'font-size:' + emojiFontSize + 'px;' +
            'filter:drop-shadow(0 2px 3px rgba(0,0,0,0.4));' +
            'z-index:2;';
          centerContent = '<div class="jg-pin-emoji" style="' + emojiStyle + '">⭐</div>';
        } else if (p.type === 'zgloszenie' && p.category && categoryEmojis[p.category]) {
          // Show category emoji for reports with white background
          var emojiFontSize = 20;
          var emojiStyle = 'position:absolute;' +
            'top:' + (pinHeight * 0.32) + 'px;' +
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
            console.error('[JG MAP] JSON parse error:', e);
            console.error('[JG MAP] Raw response:', t);
          }
          if (!j || j.success === false) {
            var errMsg = (j && j.data && (j.data.message || j.data.error)) || 'Błąd';
            console.error('[JG MAP] API error:', errMsg, j);
            throw new Error(errMsg);
          }
          // DEBUG: Log API response for jg_points to see if addresses are included
          if (action === 'jg_points' && j.data) {
            console.log('[JG MAP] API Response for jg_points - first 3 points:', j.data.slice(0, 3));
            j.data.slice(0, 3).forEach(function(p, i) {
              console.log('[JG MAP] Point ' + (i+1) + ' - ID:', p.id, 'Address:', p.address);
            });
            // DEBUG: Log RAW JSON for zgłoszenia to check category field
            console.log('[JG MAP DEBUG] ===== RAW JSON RESPONSE CHECK =====');
            var zgloszenia = j.data.filter(function(p) { return p.type === 'zgloszenie'; });
            console.log('[JG MAP DEBUG] Found ' + zgloszenia.length + ' zgłoszenia in response');
            zgloszenia.slice(0, 3).forEach(function(p) {
              console.log('[JG MAP DEBUG] RAW Zgłoszenie #' + p.id + ':', JSON.stringify({
                id: p.id,
                title: p.title,
                type: p.type,
                category: p.category,
                has_category_key: ('category' in p),
                category_value: p.category,
                category_type: typeof p.category
              }, null, 2));
            });
            console.log('[JG MAP DEBUG] =====================================');
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
            bannerHtml += '<button id="jg-ban-banner-close" style="background:rgba(255,255,255,0.2);color:#fff;border:2px solid #fff;border-radius:6px;padding:8px 16px;cursor:pointer;font-weight:700;font-size:14px;white-space:nowrap;">Zamknij</button>';
            bannerHtml += '</div>';
            bannerHtml += '</div>';

            var existingBanner = document.getElementById('jg-ban-banner');
            if (existingBanner) {
              existingBanner.remove();
            }

            var bannerEl = document.createElement('div');
            bannerEl.innerHTML = bannerHtml;
            document.body.insertBefore(bannerEl.firstChild, document.body.firstChild);

            // Add close button handler
            var closeBtn = document.getElementById('jg-ban-banner-close');
            if (closeBtn) {
              closeBtn.addEventListener('click', function() {
                var banner = document.getElementById('jg-ban-banner');
                if (banner) {
                  banner.style.transition = 'all 0.3s ease';
                  banner.style.opacity = '0';
                  banner.style.transform = 'translateY(-100%)';
                  setTimeout(function() {
                    banner.remove();
                  }, 300);
                }
              });
            }

            // Store restrictions globally so we can check them before actions
            window.JG_USER_RESTRICTIONS = result;
          })
          .catch(function(e) {
            console.error('[JG MAP] Failed to check restrictions:', e);
          });
      }

      // Check if URL contains ?point_id= or #point-123 and open that point
      function checkDeepLink() {
        try {
          var pointId = null;

          // Check query parameter ?point_id=123
          var urlParams = new URLSearchParams(window.location.search);
          pointId = urlParams.get('point_id');

          // Check hash #point-123
          if (!pointId && window.location.hash) {
            var hashMatch = window.location.hash.match(/^#point-(\d+)$/);
            if (hashMatch) {
              pointId = hashMatch[1];
            }
          }

          if (pointId && ALL && ALL.length > 0) {
            console.log('[JG MAP] Deep link detected, point_id:', pointId);

            // Find the point with this ID
            var point = ALL.find(function(p) {
              return p.id.toString() === pointId.toString();
            });

            if (point) {
              console.log('[JG MAP] Found point:', point.title);

              // Wait for map to be ready, then zoom and show pulsing marker
              setTimeout(function() {
                // Zoom to point with maximum zoom level
                map.setView([point.lat, point.lng], 19, { animate: true });

                // Wait for zoom animation, then show pulsing marker
                setTimeout(function() {
                  // Add pulsing red circle around the point
                  // After animation completes (4 seconds), open modal
                  addPulsingMarker(point.lat, point.lng, function() {
                    console.log('[JG MAP] Pulsing animation complete, opening modal');

                    // Open modal after animation - use openDetails, not viewPoint!
                    openDetails(point);

                    // Clean URL (remove point_id parameter or hash) after modal opens
                    if (history.replaceState) {
                      var cleanUrl = window.location.origin + window.location.pathname;
                      history.replaceState(null, '', cleanUrl);
                    }
                  });
                }, 800); // Wait for zoom animation
              }, 1200); // Wait for cluster animation to complete
            } else {
              console.warn('[JG MAP] Point not found with id:', pointId);
            }
          }
        } catch (e) {
          console.error('[JG MAP] Deep link error:', e);
        }
      }

      // Add pulsing red circle marker for deep-linked points
      // Callback is called after animation completes (4 seconds)
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
        var maxPulses = 8; // 8 pulses over 4 seconds
        var pulseInterval = setInterval(function() {
          pulseCount++;

          // Toggle opacity for pulse effect
          if (pulseCount % 2 === 0) {
            pulsingCircle.setStyle({ fillOpacity: 0.6, weight: 4 });
          } else {
            pulsingCircle.setStyle({ fillOpacity: 0.2, weight: 2 });
          }

          // Remove after 5 seconds and call callback
          if (pulseCount >= maxPulses) {
            clearInterval(pulseInterval);
            setTimeout(function() {
              map.removeLayer(pulsingCircle);

              // Call callback after circle is removed
              if (callback && typeof callback === 'function') {
                callback();
              }
            }, 500);
          }
        }, 500); // Pulse every 500ms
      }

      var ALL = [];
      var lastModified = 0;
      // v5: Added reports_count field - invalidating cache to force reload
      var CACHE_KEY = 'jg_map_cache_v5';
      var CACHE_VERSION_KEY = 'jg_map_cache_version_v5';

      // Try to load from cache
      function loadFromCache() {
        try {
          var cached = localStorage.getItem(CACHE_KEY);
          var cachedVersion = localStorage.getItem(CACHE_VERSION_KEY);
          if (cached && cachedVersion) {
            var data = JSON.parse(cached);
            lastModified = parseInt(cachedVersion);
            return data;
          }
        } catch (e) {
          console.error('[JG MAP] Cache load error:', e);
        }
        return null;
      }

      // Save to cache
      function saveToCache(data, version) {
        try {
          localStorage.setItem(CACHE_KEY, JSON.stringify(data));
          localStorage.setItem(CACHE_VERSION_KEY, version.toString());
          lastModified = version;
        } catch (e) {
          console.error('[JG MAP] Cache save error:', e);
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

      // Helper function to refresh both map and notifications
      function refreshAll() {
        console.log('[JG MAP] refreshAll() called - refreshing map and notifications');

        // First refresh map data to get latest points
        return refreshData(true).then(function() {
          console.log('[JG MAP] Map data refreshed, now refreshing notifications');

          // Then refresh notifications if function exists (for admins/moderators)
          if (typeof window.jgRefreshNotifications === 'function') {
            return window.jgRefreshNotifications().then(function() {
              console.log('[JG MAP] Notifications refreshed successfully');
            }).catch(function(err) {
              console.error('[JG MAP] Failed to refresh notifications:', err);
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
              reports_count: +(r.reports_count || 0)
            };
          });

          // Always save to cache with current timestamp
          var cacheVersion = version || Date.now();
          saveToCache(ALL, cacheVersion);

          apply(true); // Skip fitBounds on refresh to preserve user's view

          // Check if URL contains jg_view_point parameter (from dashboard Gallery)
          var urlParams = new URLSearchParams(window.location.search);
          var viewPointId = urlParams.get('jg_view_point');
          if (viewPointId) {
            // Find point by ID
            var targetPoint = ALL.find(function(p) { return p.id === parseInt(viewPointId); });
            if (targetPoint) {
              // Zoom to point with maximum zoom
              map.setView([targetPoint.lat, targetPoint.lng], 18);
              // Wait a bit for markers to render, then open modal
              setTimeout(function() {
                openDetails(targetPoint);
              }, 500);
              // Remove parameter from URL to avoid reopening on refresh
              var newUrl = window.location.pathname + window.location.search.replace(/[?&]jg_view_point=\d+/, '').replace(/^\&/, '?');
              window.history.replaceState({}, '', newUrl);
            }
          }

          // Check if URL contains jg_view_reports parameter (from dashboard Reports)
          var viewReportsId = urlParams.get('jg_view_reports');
          if (viewReportsId && CFG.isAdmin) {
            // Find point by ID
            var targetPoint = ALL.find(function(p) { return p.id === parseInt(viewReportsId); });
            if (targetPoint) {
              // Zoom to max zoom level (19) to show point clearly
              map.setView([targetPoint.lat, targetPoint.lng], 19);
              // Wait a bit for markers to render, then open modal
              setTimeout(function() {
                openDetails(targetPoint);
              }, 500);
              // Remove parameter from URL to avoid reopening on refresh
              var newUrl = window.location.pathname + window.location.search.replace(/[?&]jg_view_reports=\d+/, '').replace(/^\&/, '?');
              window.history.replaceState({}, '', newUrl);
            }
          }

          return ALL;
        });
      }

      var isInitialLoad = true; // Track if this is the first load

      function draw(list, skipFitBounds) {
        console.log('[JG MAP] draw() called with', list ? list.length : 0, 'points, clusterReady:', clusterReady);

        if (!list || list.length === 0) {
          console.log('[JG MAP] No data to draw, showing map anyway');
          showMap();
          hideLoading();
          return;
        }

        // Wait for cluster to be ready (created in map.whenReady)
        if (!clusterReady || !cluster) {
          console.log('[JG MAP] Cluster not ready, storing', list.length, 'points in pendingData');
          pendingData = list;
          return;
        }

        console.log('[JG MAP] Cluster ready, drawing', list.length, 'markers');

        // Clear cluster layers
        try {
          cluster.clearLayers();
        } catch (e) {
          console.error('[JG MAP] Błąd czyszczenia clustera:', e);
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
              isEdit: p.has_pending_edit || false
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
            console.error('[JG MAP] Błąd dodawania markera:', e);
          }
        });

        // Add all markers at once to cluster (reduces animation flicker)
        if (clusterReady && cluster && newMarkers.length > 0) {
          cluster.addLayers(newMarkers);
          console.log('[JG MAP] Added', newMarkers.length, 'markers to cluster');
        } else if (newMarkers.length > 0) {
          // DON'T add markers directly to map - wait for cluster to be ready
          // This prevents duplicate markers (one on map, one in cluster)
          console.log('[JG MAP] Cluster not ready, not adding markers yet');
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
              console.error('[JG MAP] Błąd fitBounds:', e);
            }

            // Wait for cluster animation to complete before showing map
            setTimeout(function() {
              showMap();
              hideLoading();
              console.log('[JG MAP] Map shown after cluster animation');
            }, 600);
          }, 400);
        } else {
          // Wait for cluster animation to complete before showing map
          setTimeout(function() {
            showMap();
            hideLoading();
            console.log('[JG MAP] Map shown after cluster animation');
          }, 600);
        }
      }

      function chip(p) {
        var h = '';
        if (p.sponsored) {
          h += '<span class="jg-promo-tag">⭐ MIEJSCE SPONSOROWANE</span>';  // Changed class name and added star emoji
        }

        if (p.type === 'zgloszenie' && p.report_status) {
          var statusClass = 'jg-status-badge--' + p.report_status;
          h += '<span class="jg-status-badge ' + statusClass + '">' + esc(p.report_status_label || p.report_status) + '</span>';
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

      function openReportModal(p) {
        // Check if user is logged in
        if (!CFG.isLoggedIn) {
          showAlert('Musisz być zalogowany aby zgłosić miejsce');
          return;
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
                console.error('[JG MAP] Błąd aktualizacji markera:', err);
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
                '<label style="display:block;margin-bottom:8px">🌐 Strona internetowa <input type="text" name="website" id="edit-website-input" value="' + esc(p.website || '') + '" placeholder="np. jeleniagora.pl" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></label>' +
                '<label style="display:block;margin-bottom:16px">📞 Telefon <input type="text" name="phone" id="edit-phone-input" value="' + esc(p.phone || '') + '" placeholder="np. 123 456 789" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></label>' +
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
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>';
            }

            // Determine max length for description based on sponsored status
            var maxDescLength = isSponsored ? 1000 : 200;
            var currentDescLength = contentText.length;

            var formHtml = '<header><h3>Edytuj</h3><button class="jg-close" id="edt-close">&times;</button></header>' +
              '<form id="edit-form" class="jg-grid cols-2">' +
              '<input type="hidden" name="lat" id="edit-lat-input" value="' + p.lat + '">' +
              '<input type="hidden" name="lng" id="edit-lng-input" value="' + p.lng + '">' +
              limitsHtml +
              '<label>Tytuł* <input name="title" required value="' + esc(p.title || '') + '" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px"></label>' +
              '<label>Typ* <select name="type" id="edit-type-select" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' +
              '<option value="zgloszenie"' + (p.type === 'zgloszenie' ? ' selected' : '') + '>Zgłoszenie</option>' +
              '<option value="ciekawostka"' + (p.type === 'ciekawostka' ? ' selected' : '') + '>Ciekawostka</option>' +
              '<option value="miejsce"' + (p.type === 'miejsce' ? ' selected' : '') + '>Miejsce</option>' +
              '</select></label>' +
              '<label class="cols-2" id="edit-category-field" style="' + (p.type === 'zgloszenie' ? 'display:block' : 'display:none') + '"><span style="color:#dc2626">Kategoria zgłoszenia*</span> <select name="category" id="edit-category-select" ' + (p.type === 'zgloszenie' ? 'required' : '') + ' style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' +
              '<option value="">-- Wybierz kategorię --</option>' +
              '<optgroup label="Zgłoszenie usterek infrastruktury">' +
              '<option value="dziura_w_jezdni"' + (p.category === 'dziura_w_jezdni' ? ' selected' : '') + '>🕳️ Dziura w jezdni</option>' +
              '<option value="uszkodzone_chodniki"' + (p.category === 'uszkodzone_chodniki' ? ' selected' : '') + '>🚶 Uszkodzone chodniki</option>' +
              '<option value="znaki_drogowe"' + (p.category === 'znaki_drogowe' ? ' selected' : '') + '>🚸 Brakujące lub zniszczone znaki drogowe</option>' +
              '<option value="oswietlenie"' + (p.category === 'oswietlenie' ? ' selected' : '') + '>💡 Awarie oświetlenia ulicznego</option>' +
              '</optgroup>' +
              '<optgroup label="Utrzymanie porządku i estetyki">' +
              '<option value="dzikie_wysypisko"' + (p.category === 'dzikie_wysypisko' ? ' selected' : '') + '>🗑️ Dzikie wysypisko śmieci</option>' +
              '<option value="przepelniony_kosz"' + (p.category === 'przepelniony_kosz' ? ' selected' : '') + '>♻️ Przepełniony kosz na śmieci</option>' +
              '<option value="graffiti"' + (p.category === 'graffiti' ? ' selected' : '') + '>🎨 Graffiti</option>' +
              '<option value="sliski_chodnik"' + (p.category === 'sliski_chodnik' ? ' selected' : '') + '>⚠️ Śliski chodnik (lód/liście)</option>' +
              '</optgroup>' +
              '<optgroup label="Zieleń miejska">' +
              '<option value="nasadzenie_drzew"' + (p.category === 'nasadzenie_drzew' ? ' selected' : '') + '>🌳 Potrzeba nasadzenia drzew</option>' +
              '<option value="nieprzycięta_gałąź"' + (p.category === 'nieprzycięta_gałąź' ? ' selected' : '') + '>🌿 Nieprzycięta gałąź zagrażająca niebezpieczeństwu</option>' +
              '</optgroup>' +
              '<optgroup label="Transport i komunikacja">' +
              '<option value="brak_przejscia"' + (p.category === 'brak_przejscia' ? ' selected' : '') + '>🚦 Brak przejścia dla pieszych</option>' +
              '<option value="przystanek_autobusowy"' + (p.category === 'przystanek_autobusowy' ? ' selected' : '') + '>🚏 Potrzeba przystanku autobusowego</option>' +
              '<option value="organizacja_ruchu"' + (p.category === 'organizacja_ruchu' ? ' selected' : '') + '>🚗 Problem z organizacją ruchu</option>' +
              '<option value="korki"' + (p.category === 'korki' ? ' selected' : '') + '>🚙 Powtarzające się korki</option>' +
              '</optgroup>' +
              '<optgroup label="Inicjatywy obywatelskie">' +
              '<option value="mala_infrastruktura"' + (p.category === 'mala_infrastruktura' ? ' selected' : '') + '>🎪 Propozycja nowych obiektów małej infrastruktury (ławki, place zabaw, stojaki rowerowe)</option>' +
              '</optgroup>' +
              '</select></label>' +
              '<input type="hidden" name="address" id="edit-address-input" value="' + esc(p.address || '') + '">' +
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
            console.error('Error parsing promo date:', e);
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
          '<input type="radio" name="status" value="reported" ' + (currentStatus === 'reported' ? 'checked' : '') + ' style="width:20px;height:20px">' +
          '<div><strong>Zgłoszone do instytucji</strong></div>' +
          '</label>' +
          '<label style="display:flex;align-items:center;gap:8px;padding:12px;border:2px solid #e5e7eb;border-radius:8px;cursor:pointer">' +
          '<input type="radio" name="status" value="resolved" ' + (currentStatus === 'resolved' ? 'checked' : '') + ' style="width:20px;height:20px">' +
          '<div><strong>Rozwiązane</strong></div>' +
          '</label>' +
          '</div>' +
          '<div style="display:flex;gap:8px;justify-content:flex-end">' +
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

        saveBtn.onclick = function() {
          var selected = qs('input[name="status"]:checked', modalStatus);
          if (!selected) {
            msg.textContent = 'Wybierz status';
            msg.style.color = '#b91c1c';
            return;
          }

          var newStatus = selected.value;
          if (newStatus === currentStatus) {
            close(modalStatus);
            return;
          }

          msg.textContent = 'Zapisywanie...';
          saveBtn.disabled = true;

          adminChangeStatus({ post_id: p.id, new_status: newStatus })
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
        console.log('[JG MAP] Opening details for point:', p);
        console.log('[JG MAP] Point address:', p.address);

        var imgs = Array.isArray(p.images) ? p.images : [];

        // Check if user can delete images (admin/moderator or own place)
        // FIX: Convert currentUserId to number for comparison (wp_localize_script converts to string)
        var canDeleteImages = CFG.isAdmin || (+CFG.currentUserId > 0 && +CFG.currentUserId === +p.author_id);

        var gal = imgs.map(function(img, idx) {
          // Support both old format (string URL) and new format (object with thumb/full)
          var thumbUrl = typeof img === 'object' ? (img.thumb || img.full) : img;
          var fullUrl = typeof img === 'object' ? (img.full || img.thumb) : img;

          var deleteBtn = '';
          if (canDeleteImages) {
            deleteBtn = '<button class="jg-delete-image" data-point-id="' + p.id + '" data-image-index="' + idx + '" style="position:absolute;top:4px;right:4px;background:rgba(220,38,38,0.9);color:#fff;border:none;border-radius:4px;width:24px;height:24px;cursor:pointer;font-weight:700;display:flex;align-items:center;justify-content:center;z-index:10" title="Usuń zdjęcie">×</button>';
          }

          return '<div style="position:relative;width:120px;height:120px;display:inline-block;margin:4px;border-radius:12px;overflow:hidden;border:2px solid #e5e7eb;box-shadow:0 2px 4px rgba(0,0,0,0.1)">' +
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

          // Show CTA changes if present (for sponsored points)
          if (p.edit_info.prev_cta_enabled !== undefined && p.edit_info.new_cta_enabled !== undefined && p.edit_info.prev_cta_enabled !== p.edit_info.new_cta_enabled) {
            var prevCta = p.edit_info.prev_cta_enabled ? 'Włączone' : 'Wyłączone';
            var newCta = p.edit_info.new_cta_enabled ? 'Włączone' : 'Wyłączone';
            changes.push('<div><strong>🎯 CTA włączone:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + prevCta + '</span><br><span style="color:#16a34a">→ ' + newCta + '</span></div>');
          }

          // Show CTA type changes if present (for sponsored points)
          if (p.edit_info.prev_cta_type !== undefined && p.edit_info.new_cta_type !== undefined && p.edit_info.prev_cta_type !== p.edit_info.new_cta_type) {
            var ctaTypeLabels = { call: '📞 Zadzwoń teraz', website: '🌐 Wejdź na stronę' };
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
            editInfo = '<div style="background:#faf5ff;border:2px solid #9333ea;border-radius:8px;padding:12px;margin:16px 0"><div style="font-weight:700;margin-bottom:8px;color:#6b21a8">📝 Zmiany oczekujące (edytowano ' + esc(p.edit_info.edited_at) + '):</div>' + changes.join('<hr style="margin:12px 0;border:none;border-top:1px solid #e9d5ff">') + '</div>';
          }
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

        var reportsWarning = '';
        if (CFG.isAdmin && p.reports_count > 0) {
          reportsWarning = '<div class="jg-reports-warning">' +
            '<div class="jg-reports-warning-title">⚠️ Zgłoszeń: ' + p.reports_count + '</div>' +
            '<button class="jg-btn" id="btn-view-reports" style="margin-top:8px">Zobacz zgłoszenia</button>' +
            '</div>';
        }

        var adminBox = '';
        if (CFG.isAdmin) {
          var adminData = [];
          if (p.admin) {
            // Author name with 3-dot menu button
            adminData.push('<div style="display:flex;align-items:center;gap:8px"><div><strong>Autor:</strong> ' + esc(p.admin.author_name_real || '?') + '</div><button id="btn-user-actions" class="jg-btn jg-btn--ghost" style="padding:2px 8px;font-size:16px;line-height:1" title="Akcje użytkownika">⋮</button></div>');
            adminData.push('<div><strong>Email:</strong> ' + esc(p.admin.author_email || '?') + '</div>');
            if (p.admin.ip && p.admin.ip !== '(brak)' && p.admin.ip.trim() !== '') {
              adminData.push('<div><strong>IP:</strong> ' + esc(p.admin.ip) + '</div>');
            }
          }

          adminData.push('<div><strong>Status:</strong> ' + esc(p.status) + '</div>');

          // Show sponsored until date for admins
          if (p.sponsored && p.sponsored_until) {
            var sponsoredDate = new Date(p.sponsored_until);
            var dateStr = sponsoredDate.toLocaleDateString('pl-PL', { year: 'numeric', month: 'long', day: 'numeric' });
            adminData.push('<div style="color:#f59e0b;font-weight:700">⭐ Sponsorowane do: ' + dateStr + '</div>');
          } else if (p.sponsored) {
            adminData.push('<div style="color:#f59e0b;font-weight:700">⭐ Sponsorowane bezterminowo</div>');
          }

          var controls = '<div class="jg-admin-controls">';

          if (p.is_pending) {
            controls += '<button class="jg-btn" id="btn-approve-point" style="background:#15803d">✓ Akceptuj</button>';
            controls += '<button class="jg-btn" id="btn-reject-point" style="background:#b91c1c">✗ Odrzuć</button>';
          }

          if (p.is_edit && p.edit_info) {
            controls += '<button class="jg-btn" id="btn-approve-edit" style="background:#15803d">✓ Akceptuj edycję</button>';
            controls += '<button class="jg-btn" id="btn-reject-edit" style="background:#b91c1c">✗ Odrzuć edycję</button>';
          }

          if (p.is_deletion_requested && p.deletion_info) {
            controls += '<button class="jg-btn" id="btn-approve-deletion" style="background:#15803d">✓ Zatwierdź usunięcie</button>';
            controls += '<button class="jg-btn" id="btn-reject-deletion" style="background:#b91c1c">✗ Odrzuć usunięcie</button>';
          }

          controls += '<button class="jg-btn jg-btn--ghost" id="btn-toggle-sponsored">' + (p.sponsored ? 'Usuń sponsorowanie' : 'Sponsorowane') + '</button>';
          controls += '<button class="jg-btn jg-btn--ghost" id="btn-toggle-author">' + (p.author_hidden ? 'Ujawnij' : 'Ukryj') + ' autora</button>';
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
        var canEdit = (CFG.isAdmin || (+CFG.currentUserId > 0 && +CFG.currentUserId === +p.author_id));
        var myVote = p.my_vote || '';

        // Don't show voting for promo points
        var voteHtml = '';
        if (!p.sponsored) {
          voteHtml = '<div class="jg-vote"><button id="v-up" ' + (myVote === 'up' ? 'class="active"' : '') + '>⬆️</button><span class="cnt" id="v-cnt" style="' + colorForVotes(+p.votes || 0) + '">' + (p.votes || 0) + '</span><button id="v-down" ' + (myVote === 'down' ? 'class="active"' : '') + '>⬇️</button></div>';
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
        if (p.sponsored && (p.website || p.phone)) {
          var contactItems = [];
          if (p.website) {
            var websiteUrl = p.website.startsWith('http') ? p.website : 'https://' + p.website;
            contactItems.push('<div><strong>🌐 Strona:</strong> <a href="' + esc(websiteUrl) + '" target="_blank" rel="noopener" style="color:#2563eb;text-decoration:underline">' + esc(p.website) + '</a></div>');
          }
          if (p.phone) {
            contactItems.push('<div><strong>📞 Telefon:</strong> <a href="tel:' + esc(p.phone) + '" style="color:#2563eb;text-decoration:underline">' + esc(p.phone) + '</a></div>');
          }
          if (contactItems.length > 0) {
            contactInfo = '<div style="margin-top:10px;padding:12px;background:#fef3c7;border-radius:8px;border:2px solid #f59e0b">' + contactItems.join('') + '</div>';
          }
        }

        // CTA button for sponsored points - single beautiful button with gold gradient
        var ctaButton = '';
        if (p.sponsored && p.cta_enabled) {
          // Priority: website > phone (if both exist, show website)
          if (p.website) {
            var websiteUrl = p.website.startsWith('http') ? p.website : 'https://' + p.website;
            ctaButton = '<a href="' + esc(websiteUrl) + '" target="_blank" rel="noopener" class="jg-btn-cta-sponsored">🌟 Zobacz Więcej 🌟</a>';
          } else if (p.phone) {
            ctaButton = '<a href="tel:' + esc(p.phone) + '" class="jg-btn-cta-sponsored">📞 Zadzwoń Teraz 📞</a>';
          }
        }

        // Add deletion request button for authors (non-admins)
        var deletionBtn = '';
        if (canEdit && !CFG.isAdmin && !p.is_deletion_requested) {
          deletionBtn = '<button id="btn-request-deletion" class="jg-btn jg-btn--danger">Zgłoś usunięcie</button>';
        }

        // Address info - simple, below main content
        var addressInfo = '';
        if (p.address && p.address.trim()) {
          addressInfo = '<div style="margin:16px 0 8px 0;padding:0;font-size:13px;color:#6b7280"><span style="font-weight:500;color:#374151">📍</span> ' + esc(p.address) + '</div>';
        }

        // Category info for reports - prominent card
        var categoryInfo = '';

        // DEBUG: Log modal category data
        console.log('[JG MAP DEBUG] openDetails() - Category check:', {
          id: p.id,
          title: p.title,
          type: p.type,
          category: p.category,
          has_category: !!p.category,
          will_show_category: (p.type === 'zgloszenie' && p.category)
        });

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
          var categoryLabel = categoryLabels[p.category] || p.category;
          categoryInfo = '<div style="margin:12px 0;padding:14px 18px;background:linear-gradient(135deg,#fffbeb 0%,#fef3c7 100%);border-left:4px solid #f59e0b;border-radius:8px;box-shadow:0 2px 6px rgba(245,158,11,0.15)"><div style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:#92400e;margin-bottom:6px;font-weight:600">Kategoria zgłoszenia</div><div style="font-size:16px;color:#78350f;font-weight:600">' + categoryLabel + '</div></div>';
        }

        // Status badge - for header (right side)
        var statusBadge = '';
        if (p.type === 'zgloszenie' && p.report_status) {
          var statusColors = {
            'added': { bg: '#dbeafe', border: '#3b82f6', text: '#1e3a8a' },
            'in_progress': { bg: '#fef3c7', border: '#f59e0b', text: '#78350f' },
            'resolved': { bg: '#d1fae5', border: '#10b981', text: '#065f46' },
            'rejected': { bg: '#fee2e2', border: '#ef4444', text: '#991b1b' }
          };
          var colors = statusColors[p.report_status] || { bg: '#f3f4f6', border: '#6b7280', text: '#374151' };
          var statusLabel = p.report_status_label || p.report_status;
          statusBadge = '<div style="font-size:1rem;padding:6px 14px;background:' + colors.bg + ';border:1px solid ' + colors.border + ';border-radius:8px;color:' + colors.text + ';font-weight:600;white-space:nowrap">' + esc(statusLabel) + '</div>';
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
          var catLabel = categoryLabelsShort[p.category] || p.category;
          categoryBadgeHeader = '<div style="font-size:1rem;padding:6px 14px;background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;color:#78350f;font-weight:600;white-space:nowrap;display:flex;align-items:center;gap:8px"><span>' + emoji + '</span><span>' + catLabel + '</span></div>';
        }

        // Remove large category card from body since it's now in header
        categoryInfo = '';

        var html = '<header style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 20px;border-bottom:1px solid #e5e7eb"><div style="display:flex;align-items:center;gap:12px">' + typeBadge + categoryBadgeHeader + '</div><div style="display:flex;align-items:center;gap:12px">' + statusBadge + '<button class="jg-close" id="dlg-close" style="margin:0">&times;</button></div></header><div class="jg-grid" style="overflow:auto;padding:20px"><h3 class="jg-place-title" style="margin:0 0 16px 0;font-size:2.5rem;font-weight:400;line-height:1.2">' + esc(p.title || 'Szczegóły') + '</h3>' + dateInfo + (p.content ? ('<div class="jg-place-content">' + p.content + '</div>') : (p.excerpt ? ('<p class="jg-place-excerpt">' + esc(p.excerpt) + '</p>') : '')) + contactInfo + ctaButton + addressInfo + (gal ? ('<div class="jg-gallery" style="margin-top:10px">' + gal + '</div>') : '') + (who ? ('<div style="margin-top:10px">' + who + '</div>') : '') + (p.sponsored ? ('<div style="margin-bottom:10px">' + chip(p) + '</div>') : '') + verificationBadge + reportsWarning + editInfo + deletionInfo + adminNote + voteHtml + adminBox + '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px">' + (canEdit ? '<button id="btn-edit" class="jg-btn jg-btn--ghost">Edytuj</button>' : '') + deletionBtn + '<button id="btn-copy-link" class="jg-btn jg-btn--ghost">📎 Kopiuj link</button><button id="btn-report" class="jg-btn jg-btn--ghost">Zgłoś</button></div></div>';

        open(modalView, html, { addClass: (promoClass + typeClass).trim() });

        qs('#dlg-close', modalView).onclick = function() {
          close(modalView);
        };

        var g = qs('.jg-gallery', modalView);
        if (g) {
          g.querySelectorAll('img').forEach(function(img) {
            img.addEventListener('click', function() {
              var fullUrl = this.getAttribute('data-full') || this.src;
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

        // Copy link button handler
        var copyLinkBtn = qs('#btn-copy-link', modalView);
        if (copyLinkBtn) {
          copyLinkBtn.onclick = function() {
            // Generate SEO-friendly URL with point name
            var slug = (p.title || 'bez-nazwy')
              .toLowerCase()
              .replace(/ą/g, 'a').replace(/ć/g, 'c').replace(/ę/g, 'e')
              .replace(/ł/g, 'l').replace(/ń/g, 'n').replace(/ó/g, 'o')
              .replace(/ś/g, 's').replace(/ź/g, 'z').replace(/ż/g, 'z')
              .replace(/[^a-z0-9]+/g, '-')
              .replace(/^-+|-+$/g, '');
            var pointUrl = window.location.origin + '/miejsce/' + slug + '/';

            // Copy to clipboard
            if (navigator.clipboard && navigator.clipboard.writeText) {
              navigator.clipboard.writeText(pointUrl)
                .then(function() {
                  // Show success message
                  copyLinkBtn.textContent = '✓ Skopiowano!';
                  copyLinkBtn.style.background = '#15803d';
                  setTimeout(function() {
                    copyLinkBtn.textContent = '📎 Kopiuj link';
                    copyLinkBtn.style.background = '';
                  }, 2000);
                })
                .catch(function(err) {
                  console.error('[JG MAP] Failed to copy link:', err);
                  showAlert('Nie udało się skopiować linku');
                });
            } else {
              // Fallback for older browsers
              var tempInput = document.createElement('input');
              tempInput.value = pointUrl;
              document.body.appendChild(tempInput);
              tempInput.select();
              try {
                document.execCommand('copy');
                copyLinkBtn.textContent = '✓ Skopiowano!';
                copyLinkBtn.style.background = '#15803d';
                setTimeout(function() {
                  copyLinkBtn.textContent = '📎 Kopiuj link';
                  copyLinkBtn.style.background = '';
                }, 2000);
              } catch (err) {
                console.error('[JG MAP] Failed to copy link (fallback):', err);
                showAlert('Nie udało się skopiować linku');
              }
              document.body.removeChild(tempInput);
            }
          };
        }

        if (canEdit) {
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
            var authorName = this.textContent;

            if (CFG.isAdmin) {
              openUserActionsModal(authorId, authorName);
            } else {
              openAuthorModal(authorId, authorName);
            }
          });
        }

        if (CFG.isAdmin) {
          var btnViewReports = qs('#btn-view-reports', modalView);
          if (btnViewReports) {
            btnViewReports.onclick = function() {
              openReportsListModal(p);
            };
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
              var reason = prompt('Powód odrzucenia (zostanie wysłany do autora):');
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
              var reason = prompt('Powód odrzucenia edycji (zostanie wysłany do autora):');
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
              var reason = prompt('Powód odrzucenia zgłoszenia usunięcia (zostanie wysłany do autora):');
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
      }

      function apply(skipFitBounds) {
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

        // STEP 1: Get ALL sponsored points - they are ALWAYS visible (no filtering!)
        var sponsoredPoints = (ALL || []).filter(function(p) {
          // If "My Places" filter is active, check ownership even for sponsored
          if (myPlacesOnly) {
            return p.sponsored && (+CFG.currentUserId > 0 && +CFG.currentUserId === +p.author_id);
          }
          return p.sponsored;
        });

        // STEP 2: Filter non-sponsored points based on type filters only
        var nonSponsoredPoints = (ALL || []).filter(function(p) {
          // Skip sponsored points - they're already in sponsoredPoints array
          if (p.sponsored) return false;

          // Promo only mode - hide all non-sponsored
          if (promoOnly) return false;

          // My Places filter - show only user's own places
          if (myPlacesOnly) {
            if (!CFG.currentUserId || +CFG.currentUserId <= 0) {
              return false; // Not logged in
            }
            if (+CFG.currentUserId !== +p.author_id) {
              return false; // Not user's place
            }
          }

          // Type filters
          // If no filters are enabled, hide all non-sponsored points
          if (Object.keys(enabled).length === 0) {
            return false;
          }
          // Check if point type is in enabled filters
          return !!enabled[p.type];
        });

        // STEP 3: Combine sponsored + filtered non-sponsored points
        var list = sponsoredPoints.concat(nonSponsoredPoints);

        // Debug logging
        console.log('[JG MAP FILTER] Total points:', (ALL || []).length);
        console.log('[JG MAP FILTER] Sponsored (always visible):', sponsoredPoints.length);
        console.log('[JG MAP FILTER] Non-sponsored (filtered):', nonSponsoredPoints.length);
        console.log('[JG MAP FILTER] Final list:', list.length);
        console.log('[JG MAP FILTER] Enabled filters:', Object.keys(enabled).length > 0 ? Object.keys(enabled) : 'NONE');

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

          console.log('[JG SEARCH] Searching for:', query);

          // Search through ALL points
          var results = (ALL || []).filter(function(p) {
            var title = (p.title || '').toLowerCase();
            var content = (p.content || '').toLowerCase();
            var excerpt = (p.excerpt || '').toLowerCase();
            return title.indexOf(query) !== -1 ||
                   content.indexOf(query) !== -1 ||
                   excerpt.indexOf(query) !== -1;
          });

          console.log('[JG SEARCH] Found', results.length, 'results');

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
          console.log('[JG SEARCH] Zooming to:', point.title);

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
          console.error('[JG MAP] Filter container not found!');
        }
      }, 500);

      // Load from cache first for instant display, then check for updates
      var cachedData = loadFromCache();
      if (cachedData && cachedData.length > 0) {
        console.log('[JG MAP] Loaded ' + cachedData.length + ' points from cache');
        ALL = cachedData;
        apply(false); // Apply cached data immediately with fitBounds
        // Don't call hideLoading() here - let draw() handle it when cluster is ready

        // Check user restrictions
        checkUserRestrictions();

        // Check for deep-linked point
        checkDeepLink();

        // Then check for updates in background
        refreshData(false).catch(function(err) {
          console.error('[JG MAP] Background update failed:', err);
        });
      } else {
        // No cache, fetch fresh data
        console.log('[JG MAP] No cache, fetching fresh data');
        refreshData(true)
          .then(function() {
            checkUserRestrictions();
            checkDeepLink();
          })
          .catch(function(e) {
            showError('Nie udało się pobrać punktów: ' + (e.message || '?'));
          });
      }

      // Smart auto-refresh: Check for updates every 15 seconds, only fetch if needed
      var refreshInterval = setInterval(function() {

        refreshData(false).then(function() {
        }).catch(function(err) {
          console.error('[JG MAP] Auto-refresh error:', err);
        });
      }, 15000); // 15 seconds

      // Also check for updates when page becomes visible again
      document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
          refreshData(false);
        }
      });

    } catch (e) {
      showError('Błąd: ' + e.message);
    }
  }
})(jQuery);
