/**
 * JG Auth - Global authentication handlers
 * Loads on ALL pages to handle login/register buttons everywhere
 */
(function($) {
  'use strict';

  // Ensure modal containers exist (create if missing)
  function ensureModalsExist() {
    // Check if edit modal exists
    var modalEdit = document.getElementById('jg-map-modal-edit');
    if (!modalEdit) {
      // Create edit modal
      modalEdit = document.createElement('div');
      modalEdit.id = 'jg-map-modal-edit';
      modalEdit.className = 'jg-modal-bg';
      modalEdit.style.cssText = 'display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:999999;align-items:center;justify-content:center;';
      modalEdit.innerHTML = '<div class="jg-modal" style="background:#fff;border-radius:8px;max-width:500px;width:90%;max-height:90vh;overflow-y:auto;position:relative;"></div>';
      document.body.appendChild(modalEdit);

      // Close on background click
      modalEdit.addEventListener('click', function(e) {
        if (e.target === modalEdit) {
          modalEdit.style.display = 'none';
        }
      });
    }

    // Check if alert modal exists
    var modalAlert = document.getElementById('jg-modal-alert');
    if (!modalAlert) {
      // Create alert modal
      modalAlert = document.createElement('div');
      modalAlert.id = 'jg-modal-alert';
      modalAlert.className = 'jg-modal-message-bg';
      modalAlert.style.cssText = 'display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000000;align-items:center;justify-content:center;';
      modalAlert.innerHTML = '<div class="jg-modal-message" style="background:#fff;border-radius:8px;max-width:400px;width:90%;padding:24px;"><div class="jg-modal-message-content" style="margin-bottom:20px;font-size:16px;line-height:1.5;"></div><div class="jg-modal-message-buttons" style="display:flex;gap:10px;justify-content:flex-end;"></div></div>';
      document.body.appendChild(modalAlert);

      // Close on background click
      modalAlert.addEventListener('click', function(e) {
        if (e.target === modalAlert) {
          modalAlert.style.display = 'none';
        }
      });
    }
  }

  // Helper functions
  function esc(s) {
    s = String(s || '');
    return s.replace(/[&<>"']/g, function(m) {
      return {"&":"&amp;", "<":"&lt;", ">":"&gt;", '"':"&quot;", "'":"&#39;"}[m];
    });
  }

  function showAlert(message) {
    ensureModalsExist();
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
      buttonsEl.innerHTML = '<button class="jg-btn" id="jg-alert-ok" style="padding:8px 16px;background:#8d2324;color:#fff;border:none;border-radius:4px;cursor:pointer;font-weight:600;">OK</button>';

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

  function showPendingActivationModal(username, email) {
    ensureModalsExist();
    var CFG = window.JG_AUTH_CFG || {};
    var modal = document.getElementById('jg-modal-alert');
    if (!modal) {
      alert('Twoje konto nie zostało jeszcze aktywowane. Sprawdź swoją skrzynkę email i kliknij w link aktywacyjny.');
      return;
    }

    var contentEl = modal.querySelector('.jg-modal-message-content');
    var buttonsEl = modal.querySelector('.jg-modal-message-buttons');

    contentEl.innerHTML =
      '<div style="font-size:64px;margin-bottom:20px">📧</div>' +
      '<h2 style="margin:0 0 16px 0;font-size:24px;font-weight:600">Konto oczekuje na aktywację</h2>' +
      '<p style="margin:0 0 16px 0;color:#666">Twoje konto nie zostało jeszcze aktywowane. Sprawdź swoją skrzynkę email i kliknij w link aktywacyjny.</p>' +
      '<p style="margin:0;color:#666">Nie otrzymałeś emaila? Możesz wysłać link ponownie.</p>';

    buttonsEl.innerHTML =
      '<button class="jg-btn" id="jg-resend-activation" style="padding:8px 16px;background:#8d2324;color:#fff;border:none;border-radius:4px;cursor:pointer;font-weight:600;margin-right:8px;">Wyślij ponownie</button>' +
      '<button class="jg-btn" id="jg-activation-cancel" style="padding:8px 16px;background:#f3f4f6;color:#374151;border:none;border-radius:4px;cursor:pointer;font-weight:600;">Zamknij</button>';

    modal.style.display = 'flex';

    var resendBtn = document.getElementById('jg-resend-activation');
    var cancelBtn = document.getElementById('jg-activation-cancel');

    resendBtn.onclick = function() {
      resendBtn.disabled = true;
      resendBtn.textContent = 'Wysyłanie...';

      $.ajax({
        url: CFG.ajax,
        type: 'POST',
        data: {
          action: 'jg_map_resend_activation',
          username: username,
          email: email
        },
        success: function(response) {
          if (response.success) {
            modal.style.display = 'none';
            showAlert(response.data || 'Link aktywacyjny został wysłany ponownie. Sprawdź swoją skrzynkę email.');
          } else {
            resendBtn.disabled = false;
            resendBtn.textContent = 'Wyślij ponownie';
            showAlert(response.data || 'Wystąpił błąd podczas wysyłania emaila');
          }
        },
        error: function() {
          resendBtn.disabled = false;
          resendBtn.textContent = 'Wyślij ponownie';
          showAlert('Wystąpił błąd podczas wysyłania emaila');
        }
      });
    };

    var closeModal = function() {
      modal.style.display = 'none';
      document.removeEventListener('keydown', handleKeyDown);
    };

    cancelBtn.onclick = closeModal;

    modal.onclick = function(e) {
      if (e.target === modal) {
        closeModal();
      }
    };

    // Handle Enter key to close modal (prevent form resubmission)
    var handleKeyDown = function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        e.stopPropagation();
        closeModal();
      }
    };
    document.addEventListener('keydown', handleKeyDown);
  }

  function showRateLimitModal(message, secondsRemaining, actionType) {
    ensureModalsExist();
    var modal = document.getElementById('jg-modal-alert');
    if (!modal) {
      alert(message);
      return;
    }

    var contentEl = modal.querySelector('.jg-modal-message-content');
    var buttonsEl = modal.querySelector('.jg-modal-message-buttons');

    var timeLeft = secondsRemaining;
    var countdownText = '';

    function formatTime(seconds) {
      var hours = Math.floor(seconds / 3600);
      var minutes = Math.floor((seconds % 3600) / 60);
      var secs = seconds % 60;

      if (hours > 0) {
        return hours + ' ' + (hours === 1 ? 'godzina' : 'godzin') + ' ' +
               minutes + ' ' + (minutes === 1 ? 'minuta' : (minutes < 5 ? 'minuty' : 'minut'));
      } else if (minutes > 0) {
        return minutes + ' ' + (minutes === 1 ? 'minuta' : (minutes < 5 ? 'minuty' : 'minut')) + ' ' +
               secs + ' ' + (secs === 1 ? 'sekunda' : (secs < 5 ? 'sekundy' : 'sekund'));
      } else {
        return secs + ' ' + (secs === 1 ? 'sekunda' : (secs < 5 ? 'sekundy' : 'sekund'));
      }
    }

    function updateCountdown() {
      if (timeLeft <= 0) {
        modal.style.display = 'none';
        return;
      }

      countdownText = formatTime(timeLeft);
      contentEl.innerHTML =
        '<div style="font-size:64px;margin-bottom:20px">⏱️</div>' +
        '<h2 style="margin:0 0 16px 0;font-size:24px;font-weight:600">Zbyt wiele prób</h2>' +
        '<p style="margin:0 0 16px 0;color:#666">' + message + '</p>' +
        '<div style="background:#f3f4f6;padding:16px;border-radius:8px;margin-bottom:16px">' +
        '<div style="font-size:32px;font-weight:700;color:#8d2324;font-family:monospace">' + countdownText + '</div>' +
        '</div>' +
        '<p style="margin:0;color:#999;font-size:14px">Odliczanie zostanie automatycznie zaktualizowane</p>';
    }

    updateCountdown();

    buttonsEl.innerHTML =
      '<button class="jg-btn" id="jg-rate-limit-close" style="padding:8px 16px;background:#f3f4f6;color:#374151;border:none;border-radius:4px;cursor:pointer;font-weight:600;">Zamknij</button>';

    modal.style.display = 'flex';

    var closeBtn = document.getElementById('jg-rate-limit-close');

    var closeModal = function() {
      modal.style.display = 'none';
      clearInterval(countdownInterval);
      document.removeEventListener('keydown', handleKeyDown);
    };

    closeBtn.onclick = closeModal;

    modal.onclick = function(e) {
      if (e.target === modal) {
        closeModal();
      }
    };

    // Handle Enter key to close modal (prevent form resubmission)
    var handleKeyDown = function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        e.stopPropagation();
        closeModal();
      }
    };
    document.addEventListener('keydown', handleKeyDown);

    // Update countdown every second
    var countdownInterval = setInterval(function() {
      timeLeft--;
      updateCountdown();
      if (timeLeft <= 0) {
        clearInterval(countdownInterval);
        closeModal();
      }
    }, 1000);
  }

  function showAttemptsWarningModal(message, attemptsRemaining, attemptsUsed, isLastAttempt, warning, actionType) {
    ensureModalsExist();
    var modal = document.getElementById('jg-modal-alert');
    if (!modal) {
      alert(message + '\nPozostało prób: ' + attemptsRemaining);
      return;
    }

    var contentEl = modal.querySelector('.jg-modal-message-content');
    var buttonsEl = modal.querySelector('.jg-modal-message-buttons');

    var icon = isLastAttempt ? '⚠️' : 'ℹ️';
    var title = isLastAttempt ? 'Ostatnia próba!' : 'Uwaga';
    var bgColor = isLastAttempt ? '#fef3c7' : '#e0f2fe';
    var borderColor = isLastAttempt ? '#f59e0b' : '#0ea5e9';
    var textColor = isLastAttempt ? '#92400e' : '#075985';

    var content = '<div style="font-size:64px;margin-bottom:20px">' + icon + '</div>' +
      '<h2 style="margin:0 0 16px 0;font-size:24px;font-weight:600;color:#1f2937">' + title + '</h2>' +
      '<p style="margin:0 0 20px 0;color:#4b5563;font-size:16px">' + message + '</p>' +
      '<div style="background:' + bgColor + ';border:2px solid ' + borderColor + ';border-radius:8px;padding:16px;margin-bottom:16px">' +
      '<div style="font-size:18px;font-weight:700;color:' + textColor + ';margin-bottom:8px">Pozostało prób: ' + attemptsRemaining + '</div>' +
      '<div style="font-size:14px;color:' + textColor + '">Wykorzystano: ' + attemptsUsed + '</div>' +
      '</div>';

    if (isLastAttempt && warning) {
      content += '<div style="background:#fee2e2;border:2px solid #dc2626;border-radius:8px;padding:12px;margin-bottom:16px">' +
        '<p style="margin:0;color:#991b1b;font-size:14px;font-weight:600">' + warning + '</p>' +
        '</div>';
    }

    contentEl.innerHTML = content;

    buttonsEl.innerHTML =
      '<button class="jg-btn" id="jg-attempts-warning-ok" style="padding:8px 16px;background:#8d2324;color:#fff;border:none;border-radius:4px;cursor:pointer;font-weight:600;">OK, rozumiem</button>';

    modal.style.display = 'flex';

    var okBtn = document.getElementById('jg-attempts-warning-ok');

    var closeModal = function() {
      modal.style.display = 'none';
      document.removeEventListener('keydown', handleKeyDown);
    };

    okBtn.onclick = closeModal;

    modal.onclick = function(e) {
      if (e.target === modal) {
        closeModal();
      }
    };

    // Handle Enter key to close modal (prevent form resubmission)
    var handleKeyDown = function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        e.stopPropagation();
        closeModal();
      }
    };
    document.addEventListener('keydown', handleKeyDown);
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
    ensureModalsExist();
    var CFG = window.JG_AUTH_CFG || {};
    var modalEdit = document.getElementById('jg-map-modal-edit');
    if (!modalEdit) {
      alert('Nie można otworzyć modala logowania.');
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
            // Check if it's a pending activation error
            if (response.data && typeof response.data === 'object' && response.data.type === 'pending_activation') {
              showPendingActivationModal(response.data.username, response.data.email);
            } else if (response.data && typeof response.data === 'object' && response.data.type === 'rate_limit') {
              // Show rate limit modal with countdown
              showRateLimitModal(response.data.message, response.data.seconds_remaining, response.data.action);
            } else if (response.data && typeof response.data === 'object' && response.data.type === 'attempts_warning') {
              // Show attempts warning modal
              showAttemptsWarningModal(
                response.data.message,
                response.data.attempts_remaining,
                response.data.attempts_used,
                response.data.is_last_attempt,
                response.data.warning,
                response.data.action
              );
            } else {
              showAlert(response.data && response.data.message ? response.data.message : (response.data || 'Błąd logowania'));
            }
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
    ensureModalsExist();
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

    // Check registration status on server (real-time check)
    $.ajax({
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
          showRegisterForm();
        } else {
          showAlert('Wystąpił błąd podczas sprawdzania dostępności rejestracji.');
        }
      },
      error: function() {
        showAlert('Wystąpił błąd połączenia. Spróbuj ponownie później.');
      }
    });
  }

  function showRegisterForm() {
    var CFG = window.JG_AUTH_CFG || {};
    ensureModalsExist();
    var modalEdit = document.getElementById('jg-map-modal-edit');
    if (!modalEdit) {
      alert('Nie można otworzyć modala rejestracji.');
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
      '<label style="display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px">Nazwa użytkownika (max 60 znaków)</label>' +
      '<input type="text" id="register-username" class="jg-input" required maxlength="60" style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:14px;transition:border-color 0.2s" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#ddd\'">' +
      '</div>' +
      '<div class="jg-form-group" style="margin-bottom:20px">' +
      '<label style="display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px">Adres email</label>' +
      '<input type="email" id="register-email" class="jg-input" required style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:14px;transition:border-color 0.2s" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#ddd\'">' +
      '</div>' +
      '<div class="jg-form-group" style="margin-bottom:20px">' +
      '<label style="display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px">Hasło</label>' +
      '<input type="password" id="register-password" class="jg-input" required style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:14px;transition:border-color 0.2s" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#ddd\'">' +
      '</div>' +
      '<div class="jg-form-group" style="margin-bottom:20px">' +
      '<label style="display:flex;align-items:flex-start;gap:8px;font-size:14px;color:#333;cursor:pointer;line-height:1.5">' +
      '<input type="checkbox" id="register-privacy-policy" required style="margin-top:4px;cursor:pointer;width:16px;height:16px;flex-shrink:0">' +
      '<span>Oświadczam, że zapoznałem/am się i akceptuję <a href="/oswiadczenie-o-ochronie-prywatnosci-eu/" target="_blank" style="color:#8d2324;text-decoration:underline;font-weight:600" onmouseover="this.style.textDecoration=\'none\'" onmouseout="this.style.textDecoration=\'underline\'">Politykę prywatności</a> serwisu *</span>' +
      '</label>' +
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
      var privacyPolicyAccepted = document.getElementById('register-privacy-policy').checked;

      if (!username || !email || !password) {
        showAlert('Proszę wypełnić wszystkie pola');
        return;
      }

      if (!privacyPolicyAccepted) {
        showAlert('Musisz zaakceptować Politykę prywatności, aby się zarejestrować');
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
            // Check if it's a rate limit error
            if (response.data && typeof response.data === 'object' && response.data.type === 'rate_limit') {
              showRateLimitModal(response.data.message, response.data.seconds_remaining, response.data.action);
            } else if (response.data && typeof response.data === 'object' && response.data.type === 'attempts_warning') {
              // Show attempts warning modal
              showAttemptsWarningModal(
                response.data.message,
                response.data.attempts_remaining,
                response.data.attempts_used,
                response.data.is_last_attempt,
                response.data.warning,
                response.data.action
              );
            } else {
              showAlert(response.data && response.data.message ? response.data.message : (response.data || 'Błąd rejestracji'));
            }
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
    ensureModalsExist();
    var CFG = window.JG_AUTH_CFG || {};
    var modalEdit = document.getElementById('jg-map-modal-edit');
    if (!modalEdit) {
      alert('Nie można otworzyć modala edycji profilu.');
      return;
    }

    var html = '<div class="jg-modal-header" style="background:#8d2324;color:#fff;padding:20px 24px;border-radius:8px 8px 0 0">' +
      '<h2 style="margin:0;font-size:20px;font-weight:600">Zmiana hasła</h2>' +
      '</div>' +
      '<div class="jg-modal-body" style="padding:24px">' +
      '<form id="jg-edit-profile-form">' +
      '<div class="jg-form-group" style="margin-bottom:20px">' +
      '<label style="display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px">Nowe hasło</label>' +
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

    // Load current user data to check if can delete profile
    $.ajax({
      url: CFG.ajax,
      type: 'POST',
      data: {
        action: 'jg_map_get_current_user',
        nonce: CFG.nonce
      },
      success: function(response) {
        if (response.success && response.data) {
          // Add delete profile button if user can delete their profile (not admin/moderator)
          if (response.data.can_delete_profile) {
            var deleteBtn = '<button class="jg-btn jg-btn--danger" id="delete-profile-btn" style="padding:10px 20px;background:#dc2626;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;transition:all 0.2s;margin-right:auto" onmouseover="this.style.background=\'#b91c1c\'" onmouseout="this.style.background=\'#dc2626\'">Usuń profil</button>';
            document.querySelector('.jg-modal-footer').insertAdjacentHTML('afterbegin', deleteBtn);

            document.getElementById('delete-profile-btn').addEventListener('click', function() {
              openDeleteProfileConfirmation();
            });
          }
        }
      }
    });

    // Save profile (password only)
    document.getElementById('save-profile-btn').addEventListener('click', function() {
      var password = document.getElementById('profile-password').value;
      var passwordConfirm = document.getElementById('profile-password-confirm').value;

      if (!password) {
        showAlert('Proszę podać nowe hasło');
        return;
      }

      if (password !== passwordConfirm) {
        showAlert('Hasła nie pasują do siebie');
        return;
      }

      $.ajax({
        url: CFG.ajax,
        type: 'POST',
        data: {
          action: 'jg_map_update_profile',
          nonce: CFG.nonce,
          password: password
        },
        success: function(response) {
          if (response.success) {
            showAlert('Hasło zostało zmienione').then(function() {
              close(modalEdit);
            });
          } else {
            showAlert(response.data || 'Wystąpił błąd podczas zmiany hasła');
          }
        },
        error: function() {
          showAlert('Wystąpił błąd podczas komunikacji z serwerem');
        }
      });
    });
  }

  // Open delete profile confirmation modal
  function openDeleteProfileConfirmation() {
    var CFG = window.JG_AUTH_CFG || {};

    // Show password confirmation modal
    var modal = document.getElementById('jg-modal-alert');
    if (!modal) {
      var password = prompt('Aby usunąć profil, podaj swoje hasło:');
      if (password) {
        deleteProfileWithPassword(password);
      }
      return;
    }

    var contentEl = modal.querySelector('.jg-modal-message-content');
    var buttonsEl = modal.querySelector('.jg-modal-message-buttons');

    contentEl.innerHTML = '<div style="margin-bottom:16px;font-weight:600;font-size:18px">Czy na pewno chcesz usunąć swój profil?</div>' +
      '<div style="margin-bottom:16px;color:#666">Ta operacja jest <strong style="color:#dc2626">nieodwracalna</strong>. Zostaną usunięte:</div>' +
      '<ul style="margin-bottom:16px;text-align:left;line-height:1.8">' +
      '<li>Wszystkie Twoje pinezki</li>' +
      '<li>Wszystkie przesłane przez Ciebie zdjęcia</li>' +
      '<li>Twój profil i wszystkie dane</li>' +
      '</ul>' +
      '<div style="margin-bottom:12px;font-weight:600">Aby potwierdzić, podaj swoje hasło:</div>' +
      '<input type="password" id="jg-delete-password-input" style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:14px" placeholder="Twoje hasło">';
    buttonsEl.innerHTML = '<button class="jg-btn jg-btn--ghost" id="jg-confirm-no">Anuluj</button><button class="jg-btn jg-btn--danger" id="jg-confirm-yes">Usuń profil</button>';

    modal.style.display = 'flex';

    var passwordInput = document.getElementById('jg-delete-password-input');
    var yesBtn = document.getElementById('jg-confirm-yes');
    var noBtn = document.getElementById('jg-confirm-no');

    // Focus password input after a brief delay
    setTimeout(function() {
      if (passwordInput) passwordInput.focus();
    }, 100);

    // Handle Enter key in password input
    passwordInput.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        yesBtn.click();
      }
    });

    yesBtn.onclick = function() {
      var password = passwordInput.value;
      if (!password) {
        showAlert('Musisz podać hasło aby usunąć profil');
        return;
      }
      modal.style.display = 'none';
      deleteProfileWithPassword(password);
    };

    noBtn.onclick = function() {
      modal.style.display = 'none';
    };

    // Close on background click = cancel
    modal.onclick = function(e) {
      if (e.target === modal) {
        modal.style.display = 'none';
      }
    };
  }

  // Delete profile with password verification
  function deleteProfileWithPassword(password) {
    var CFG = window.JG_AUTH_CFG || {};

    $.ajax({
      url: CFG.ajax,
      type: 'POST',
      data: {
        action: 'jg_map_delete_profile',
        nonce: CFG.nonce,
        password: password
      },
      success: function(response) {
        if (response.success) {
          showAlert('Twój profil został pomyślnie usunięty').then(function() {
            window.location.href = '/';
          });
        } else {
          showAlert(response.data || 'Wystąpił błąd podczas usuwania profilu');
        }
      },
      error: function() {
        showAlert('Wystąpił błąd podczas komunikacji z serwerem');
      }
    });
  }

  // Open my profile modal with statistics
  function openMyProfileModal() {
    ensureModalsExist();
    var CFG = window.JG_AUTH_CFG || {};
    var modalEdit = document.getElementById('jg-map-modal-edit');
    if (!modalEdit) {
      alert('Nie można otworzyć modala profilu.');
      return;
    }

    // Show loading state
    var loadingHtml = '<div class="jg-modal-header" style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:#fff;padding:20px 24px;border-radius:8px 8px 0 0">' +
      '<h2 style="margin:0;font-size:20px;font-weight:600">Mój profil</h2>' +
      '</div>' +
      '<div style="padding:40px;text-align:center">' +
      '<div style="display:inline-block;width:40px;height:40px;border:3px solid #e5e7eb;border-top-color:#667eea;border-radius:50%;animation:jg-spin 1s linear infinite"></div>' +
      '<div style="margin-top:12px;color:#6b7280">Ładowanie statystyk...</div>' +
      '</div>' +
      '<style>@keyframes jg-spin { to { transform: rotate(360deg); } }</style>';

    open(modalEdit, loadingHtml);

    // Fetch user stats
    $.ajax({
      url: CFG.ajax,
      type: 'POST',
      data: {
        action: 'jg_map_get_my_stats',
        nonce: CFG.nonce
      },
      success: function(response) {
        if (response.success && response.data) {
          var data = response.data;
          var stats = data.stats;
          var memberSince = data.member_since ? new Date(data.member_since).toLocaleDateString('pl-PL') : '-';

          var roleIcon = '';
          if (data.is_admin) {
            roleIcon = '<span style="color:#fbbf24;font-size:18px;margin-left:8px" title="Administrator">⭐</span>';
          } else if (data.is_moderator) {
            roleIcon = '<span style="color:#60a5fa;font-size:18px;margin-left:8px" title="Moderator">🛡️</span>';
          }
          if (data.has_sponsored) {
            roleIcon += '<span style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;background:#f59e0b;border-radius:50%;color:#fff;font-size:14px;margin-left:6px;font-weight:bold" title="Użytkownik sponsorowany">$</span>';
          }

          var html = '<div class="jg-modal-header" style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:#fff;padding:20px 24px;border-radius:8px 8px 0 0;position:relative">' +
            '<h2 style="margin:0;font-size:20px;font-weight:600">👤 ' + esc(data.display_name) + roleIcon + '</h2>' +
            '<div style="font-size:14px;margin-top:4px;opacity:0.9">' + esc(data.role) + '</div>' +
            '<button id="jg-profile-close" style="position:absolute;top:12px;right:12px;background:none;border:none;color:#fff;font-size:24px;cursor:pointer;opacity:0.8;line-height:1">&times;</button>' +
            '</div>' +
            '<div style="padding:20px;max-height:70vh;overflow-y:auto">' +
            // Basic info
            '<div style="display:grid;grid-template-columns:repeat(2, 1fr);gap:12px;margin-bottom:20px">' +
            '<div style="padding:16px;background:#f9fafb;border-radius:8px">' +
            '<div style="font-size:12px;color:#6b7280;margin-bottom:4px">📅 Członek od</div>' +
            '<div style="font-weight:600">' + memberSince + '</div>' +
            '</div>' +
            '<div style="padding:16px;background:#f9fafb;border-radius:8px">' +
            '<div style="font-size:12px;color:#6b7280;margin-bottom:4px">🏷️ Rola</div>' +
            '<div style="font-weight:600">' + esc(data.role) + '</div>' +
            '</div>' +
            '</div>' +
            // Stats section
            '<h3 style="margin:0 0 16px 0;color:#374151;font-size:16px;border-bottom:1px solid #e5e7eb;padding-bottom:8px">📊 Podsumowanie aktywności</h3>' +
            '<div style="display:grid;grid-template-columns:repeat(2, 1fr);gap:12px">' +
            // Places
            '<div style="padding:16px;background:#ecfdf5;border-radius:8px;border-left:4px solid #10b981">' +
            '<div style="font-size:12px;color:#6b7280;margin-bottom:4px">📍 Dodane miejsca</div>' +
            '<div style="font-weight:700;font-size:24px;color:#059669">' + stats.places_added + '</div>' +
            (stats.places_pending > 0 ? '<div style="font-size:11px;color:#9ca3af;margin-top:2px">+ ' + stats.places_pending + ' oczekujących</div>' : '') +
            '</div>' +
            // Edits
            '<div style="padding:16px;background:#fef3c7;border-radius:8px;border-left:4px solid #f59e0b">' +
            '<div style="font-size:12px;color:#6b7280;margin-bottom:4px">📝 Edycje</div>' +
            '<div style="font-weight:700;font-size:24px;color:#d97706">' + stats.edits_submitted + '</div>' +
            (stats.edits_approved > 0 ? '<div style="font-size:11px;color:#9ca3af;margin-top:2px">' + stats.edits_approved + ' zatwierdzonych</div>' : '') +
            '</div>' +
            // Photos
            '<div style="padding:16px;background:#fce7f3;border-radius:8px;border-left:4px solid #ec4899">' +
            '<div style="font-size:12px;color:#6b7280;margin-bottom:4px">📷 Dodane zdjęcia</div>' +
            '<div style="font-weight:700;font-size:24px;color:#db2777">' + stats.photos_added + '</div>' +
            '</div>' +
            // Visited places
            '<div style="padding:16px;background:#e0e7ff;border-radius:8px;border-left:4px solid #6366f1">' +
            '<div style="font-size:12px;color:#6b7280;margin-bottom:4px">🗺️ Odwiedzone miejsca</div>' +
            '<div style="font-weight:700;font-size:24px;color:#4f46e5">' + stats.places_visited + '</div>' +
            '</div>' +
            '</div>' +
            // Votes section
            '<h3 style="margin:20px 0 16px 0;color:#374151;font-size:16px;border-bottom:1px solid #e5e7eb;padding-bottom:8px">👍 Głosowanie</h3>' +
            '<div style="display:grid;grid-template-columns:repeat(2, 1fr);gap:12px">' +
            // Votes given
            '<div style="padding:16px;background:#f9fafb;border-radius:8px">' +
            '<div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:8px">Oddane głosy</div>' +
            '<div style="display:flex;gap:16px">' +
            '<div><span style="color:#10b981;font-weight:600">👍 ' + stats.upvotes_given + '</span></div>' +
            '<div><span style="color:#ef4444;font-weight:600">👎 ' + stats.downvotes_given + '</span></div>' +
            '</div>' +
            '</div>' +
            // Votes received
            '<div style="padding:16px;background:#f9fafb;border-radius:8px">' +
            '<div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:8px">Otrzymane głosy</div>' +
            '<div style="display:flex;gap:16px">' +
            '<div><span style="color:#10b981;font-weight:600">👍 ' + stats.upvotes_received + '</span></div>' +
            '<div><span style="color:#ef4444;font-weight:600">👎 ' + stats.downvotes_received + '</span></div>' +
            '</div>' +
            '</div>' +
            '</div>' +
            // Other activity section
            '<h3 style="margin:20px 0 16px 0;color:#374151;font-size:16px;border-bottom:1px solid #e5e7eb;padding-bottom:8px">📋 Inne aktywności</h3>' +
            '<div style="display:grid;grid-template-columns:1fr;gap:12px">' +
            // Reports
            '<div style="padding:12px;background:#fef2f2;border-radius:8px;text-align:center">' +
            '<div style="font-size:11px;color:#6b7280;margin-bottom:4px">🚨 Wysłane zgłoszenia</div>' +
            '<div style="font-weight:700;font-size:20px;color:#dc2626">' + stats.reports_submitted + '</div>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '<div style="padding:16px 24px;background:#f9f9f9;border-top:1px solid #e5e5e5;display:flex;justify-content:flex-end;border-radius:0 0 8px 8px">' +
            '<button id="jg-profile-close-btn" style="padding:10px 20px;background:#667eea;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;transition:all 0.2s" onmouseover="this.style.background=\'#5a67d8\'" onmouseout="this.style.background=\'#667eea\'">Zamknij</button>' +
            '</div>';

          open(modalEdit, html);

          // Add close handlers
          document.getElementById('jg-profile-close').addEventListener('click', function() {
            close(modalEdit);
          });
          document.getElementById('jg-profile-close-btn').addEventListener('click', function() {
            close(modalEdit);
          });
        } else {
          showAlert(response.data || 'Wystąpił błąd podczas pobierania statystyk');
          close(modalEdit);
        }
      },
      error: function() {
        showAlert('Wystąpił błąd podczas komunikacji z serwerem');
        close(modalEdit);
      }
    });
  }

  // Initialize buttons when DOM is ready
  function initAuthButtons() {
    var loginBtn = document.getElementById('jg-login-btn');
    var registerBtn = document.getElementById('jg-register-btn');
    var editProfileBtn = document.getElementById('jg-edit-profile-btn');
    var myProfileLink = document.getElementById('jg-my-profile-link');

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

    if (myProfileLink && !myProfileLink.jgHandlerAttached) {
      myProfileLink.addEventListener('click', function(e) {
        e.preventDefault();
        openMyProfileModal();
      });
      myProfileLink.jgHandlerAttached = true;
    }
  }

  // Add Escape key handler to close modals
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      var modalEdit = document.getElementById('jg-map-modal-edit');
      var modalAlert = document.getElementById('jg-modal-alert');
      if (modalEdit && modalEdit.style.display === 'flex') {
        modalEdit.style.display = 'none';
      }
      if (modalAlert && modalAlert.style.display === 'flex') {
        modalAlert.style.display = 'none';
      }
    }
  });

  // Check for activation/registration success messages in URL
  function checkUrlMessages() {
    var urlParams = new URLSearchParams(window.location.search);

    // Check for activation success
    if (urlParams.get('activation') === 'success') {
      showAlert('Twoje konto zostało pomyślnie aktywowane! Możesz się teraz zalogować.').then(function() {
        // Remove activation parameter from URL
        var newUrl = window.location.pathname;
        window.history.replaceState({}, document.title, newUrl);
        // Open login modal
        openLoginModal();
      });
    }

    // Check for already activated
    if (urlParams.get('activation') === 'already') {
      showAlert('To konto zostało już aktywowane. Możesz się zalogować.').then(function() {
        // Remove activation parameter from URL
        var newUrl = window.location.pathname;
        window.history.replaceState({}, document.title, newUrl);
        // Open login modal
        openLoginModal();
      });
    }
  }

  // Run on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      initAuthButtons();
      checkUrlMessages();
    });
  } else {
    initAuthButtons();
    checkUrlMessages();
  }

})(jQuery);
