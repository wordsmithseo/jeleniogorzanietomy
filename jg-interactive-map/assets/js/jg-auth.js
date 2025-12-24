/**
 * JG Auth - Global authentication handlers
 * Loads on ALL pages to handle login/register buttons everywhere
 */
(function($) {
  'use strict';

  // Helper functions
  function esc(s) {
    s = String(s || '');
    return s.replace(/[&<>"']/g, function(m) {
      return {"&":"&amp;", "<":"&lt;", ">":"&gt;", '"':"&quot;", "'":"&#39;"}[m];
    });
  }

  function showAlert(message) {
    return new Promise(function(resolve) {
      var modal = document.getElementById('jg-modal-alert');
      if (!modal) {
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

      modal.onclick = function(e) {
        if (e.target === modal) {
          modal.style.display = 'none';
          resolve();
        }
      };
    });
  }

  function open(modalEl, html) {
    if (!modalEl) return;
    var innerModal = modalEl.querySelector('.jg-modal');
    if (!innerModal) return;
    innerModal.innerHTML = html;
    modalEl.style.display = 'flex';
  }

  function close(modalEl) {
    if (!modalEl) return;
    modalEl.style.display = 'none';
  }

  function openLoginModal() {
    var CFG = window.JG_AUTH_CFG || {};
    var modalEdit = document.getElementById('jg-map-modal-edit');
    if (!modalEdit) {
      alert('Brak modala. Odśwież stronę.');
      return;
    }

    var html = '<div class="jg-modal-header" style="background:#8d2324;color:#fff;padding:20px 24px;border-radius:8px 8px 0 0">' +
      '<h2 style="margin:0;font-size:20px;font-weight:600">Logowanie</h2>' +
      '</div>' +
      '<div class="jg-modal-body" style="padding:24px">' +
      '<form id="jg-login-form">' +
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

    // Forgot password link
    document.getElementById('forgot-password-link').addEventListener('click', function(e) {
      e.preventDefault();
      showForgotPasswordModal();
    });

    // Login submission
    function submitLogin() {
      var username = document.getElementById('login-username').value;
      var password = document.getElementById('login-password').value;
      var honeypot = document.getElementById('login-website').value;

      if (!username || !password) {
        showAlert('Proszę wypełnić wszystkie pola');
        return;
      }

      $.ajax({
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

    document.getElementById('submit-login-btn').addEventListener('click', submitLogin);
    document.getElementById('jg-login-form').addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        submitLogin();
      }
    });
  }

  function showForgotPasswordModal() {
    var CFG = window.JG_AUTH_CFG || {};
    var modalEdit = document.getElementById('jg-map-modal-edit');
    if (!modalEdit) return;

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

    function submitForgotPassword() {
      var email = document.getElementById('forgot-email').value;

      if (!email) {
        showAlert('Proszę podać adres email');
        return;
      }

      var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        showAlert('Proszę podać prawidłowy adres email');
        return;
      }

      $.ajax({
        url: CFG.ajax,
        type: 'POST',
        data: {
          action: 'jg_map_forgot_password',
          email: email
        },
        success: function(response) {
          if (response.success) {
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
              '<div class="jg-modal-footer" style="padding:16px 24px;background:#f9f9f9;border-top:1px solid #e5e5e5;display:flex;gap:12px;justify-content:center;border-radius:0 0 8px 8px">' +
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

    document.getElementById('submit-forgot-btn').addEventListener('click', submitForgotPassword);
    document.getElementById('jg-forgot-password-form').addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        submitForgotPassword();
      }
    });
  }

  function openRegisterModal() {
    var CFG = window.JG_AUTH_CFG || {};
    var modalEdit = document.getElementById('jg-map-modal-edit');
    if (!modalEdit) {
      alert('Brak modala. Odśwież stronę.');
      return;
    }

    // Check if registration is enabled
    if (CFG.registrationEnabled === false) {
      var disabledHtml = '<div class="jg-modal-header" style="background:#d97706;color:#fff;padding:20px 24px;border-radius:8px 8px 0 0">' +
        '<h2 style="margin:0;font-size:20px;font-weight:600">⚠️ Rejestracja wyłączona</h2>' +
        '</div>' +
        '<div class="jg-modal-body" style="padding:24px;text-align:center">' +
        '<p style="font-size:16px;line-height:1.6;color:#333;margin:20px 0">' + esc(CFG.registrationDisabledMessage || 'Rejestracja jest obecnie wyłączona. Spróbuj ponownie później.') + '</p>' +
        '</div>' +
        '<div class="jg-modal-footer" style="padding:16px 24px;background:#f9f9f9;border-top:1px solid #e5e5e5;display:flex;gap:12px;justify-content:center;border-radius:0 0 8px 8px">' +
        '<button class="jg-btn jg-btn--primary" onclick="document.getElementById(\'jg-map-modal-edit\').style.display=\'none\'" style="padding:10px 24px;background:#d97706;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer">OK, rozumiem</button>' +
        '</div>';
      open(modalEdit, disabledHtml);
      return;
    }

    var html = '<div class="jg-modal-header" style="background:#8d2324;color:#fff;padding:20px 24px;border-radius:8px 8px 0 0">' +
      '<h2 style="margin:0;font-size:20px;font-weight:600">Rejestracja</h2>' +
      '</div>' +
      '<div class="jg-modal-body" style="padding:24px">' +
      '<form id="jg-register-form">' +
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

    function submitRegistration() {
      var username = document.getElementById('register-username').value;
      var email = document.getElementById('register-email').value;
      var password = document.getElementById('register-password').value;
      var honeypot = document.getElementById('register-website').value;

      if (!username || !email || !password) {
        showAlert('Proszę wypełnić wszystkie pola');
        return;
      }

      $.ajax({
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

    document.getElementById('submit-register-btn').addEventListener('click', submitRegistration);
    document.getElementById('jg-register-form').addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        submitRegistration();
      }
    });
  }

  function openEditProfileModal() {
    var CFG = window.JG_AUTH_CFG || {};
    var modalEdit = document.getElementById('jg-map-modal-edit');
    if (!modalEdit) {
      alert('Brak modala. Odśwież stronę.');
      return;
    }

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
    $.ajax({
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

    // Save profile
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

      $.ajax({
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
  }

  // Initialize buttons when DOM is ready
  function initAuthButtons() {
    var loginBtn = document.getElementById('jg-login-btn');
    var registerBtn = document.getElementById('jg-register-btn');
    var editProfileBtn = document.getElementById('jg-edit-profile-btn');

    // Only attach if jg-map.js hasn't already handled them
    if (loginBtn && !loginBtn.jgHandlerAttached) {
      loginBtn.addEventListener('click', openLoginModal);
      loginBtn.jgHandlerAttached = true;
    }

    if (registerBtn && !registerBtn.jgHandlerAttached) {
      registerBtn.addEventListener('click', openRegisterModal);
      registerBtn.jgHandlerAttached = true;
    }

    if (editProfileBtn && !editProfileBtn.jgHandlerAttached) {
      editProfileBtn.addEventListener('click', openEditProfileModal);
      editProfileBtn.jgHandlerAttached = true;
    }
  }

  // Run on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAuthButtons);
  } else {
    initAuthButtons();
  }

})(jQuery);
