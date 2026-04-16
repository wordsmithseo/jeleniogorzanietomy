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

    // User profile modal (mirrors jg-map-modal-report from map shortcode)
    if (!document.getElementById('jg-map-modal-report')) {
      var modalReport = document.createElement('div');
      modalReport.id = 'jg-map-modal-report';
      modalReport.className = 'jg-modal-bg';
      modalReport.style.cssText = 'display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:999999;align-items:center;justify-content:center;';
      modalReport.innerHTML = '<div class="jg-modal" style="background:#fff;border-radius:12px;max-width:600px;width:90%;max-height:90vh;overflow-y:auto;position:relative;"></div>';
      document.body.appendChild(modalReport);
      modalReport.addEventListener('click', function(e) { if (e.target === modalReport) modalReport.style.display = 'none'; });
    }

    // Ranking modal (mirrors jg-map-modal-ranking from map shortcode)
    if (!document.getElementById('jg-map-modal-ranking')) {
      var modalRanking = document.createElement('div');
      modalRanking.id = 'jg-map-modal-ranking';
      modalRanking.className = 'jg-modal-bg';
      modalRanking.style.cssText = 'display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:999999;align-items:center;justify-content:center;';
      modalRanking.innerHTML = '<div class="jg-modal" style="background:#fff;border-radius:12px;max-width:480px;width:90%;max-height:90vh;overflow-y:auto;position:relative;"></div>';
      document.body.appendChild(modalRanking);
      modalRanking.addEventListener('click', function(e) { if (e.target === modalRanking) modalRanking.style.display = 'none'; });
    }

    // Achievements modal (mirrors jg-map-modal-reports-list from map shortcode)
    if (!document.getElementById('jg-map-modal-reports-list')) {
      var modalReportsList = document.createElement('div');
      modalReportsList.id = 'jg-map-modal-reports-list';
      modalReportsList.className = 'jg-modal-bg';
      modalReportsList.style.cssText = 'display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000000;align-items:center;justify-content:center;';
      modalReportsList.innerHTML = '<div class="jg-modal" style="background:#fff;border-radius:12px;max-width:600px;width:90%;max-height:90vh;overflow-y:auto;position:relative;"></div>';
      document.body.appendChild(modalReportsList);
      modalReportsList.addEventListener('click', function(e) { if (e.target === modalReportsList) modalReportsList.style.display = 'none'; });
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
    openAuthModal('login');
  }

  function openRegisterModal() {
    openAuthModal('register');
  }

  /**
   * Unified tabbed auth modal (login + register)
   * @param {string} activeTab - 'login' or 'register'
   * @param {string|null} infoMessage - optional info message shown at top
   */
  function openAuthModal(activeTab, infoMessage) {
    var CFG = window.JG_AUTH_CFG || {};
    ensureModalsExist();
    var modalEdit = document.getElementById('jg-map-modal-edit');
    if (!modalEdit) {
      alert('Nie można otworzyć modala.');
      return;
    }

    activeTab = activeTab || 'register';

    var tabStyle = 'padding:10px 20px;border:none;cursor:pointer;font-size:14px;font-weight:600;border-radius:6px 6px 0 0;transition:all 0.2s;';
    var activeTabBg = 'background:#fff;color:#8d2324;';
    var inactiveTabBg = 'background:transparent;color:rgba(255,255,255,0.7);';

    var termsUrl = CFG.termsUrl || '';
    var privacyUrl = CFG.privacyUrl || '';
    var termsContent = CFG.termsContent || '';
    var privacyContent = CFG.privacyContent || '';

    // Build checkbox labels with links or inline content triggers
    var termsLabel = 'Akceptuję <a href="' + (termsUrl ? esc(termsUrl) : '#') + '" ' + (termsUrl ? 'target="_blank"' : 'id="auth-terms-inline"') + ' style="color:#8d2324;text-decoration:underline;font-weight:600">Regulamin</a> serwisu *';
    var privacyLabel = 'Akceptuję <a href="' + (privacyUrl ? esc(privacyUrl) : '#') + '" ' + (privacyUrl ? 'target="_blank"' : 'id="auth-privacy-inline"') + ' style="color:#8d2324;text-decoration:underline;font-weight:600">Politykę prywatności</a> serwisu *';

    var infoBannerHtml = '';
    if (infoMessage) {
      infoBannerHtml = '<div style="background:#fef3c7;border-bottom:1px solid #f59e0b;padding:12px 24px;display:flex;align-items:center;gap:10px">' +
        '<span style="font-size:18px;flex-shrink:0">&#9432;</span>' +
        '<p style="margin:0;font-size:13px;color:#92400e;line-height:1.4">' + infoMessage + '</p>' +
      '</div>';
    }

    var html = '<div class="jg-modal-header" style="background:#8d2324;color:#fff;padding:16px 24px 0;border-radius:8px 8px 0 0">' +
      '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">' +
        '<h2 style="margin:0;font-size:20px;font-weight:600">' + (infoMessage ? 'Konto wymagane' : 'Zarejestruj / Zaloguj') + '</h2>' +
        '<button id="auth-modal-close" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;padding:0 4px;line-height:1">&times;</button>' +
      '</div>' +
      '<div style="display:flex;gap:4px">' +
        '<button id="auth-tab-register" style="' + tabStyle + (activeTab === 'register' ? activeTabBg : inactiveTabBg) + '">Rejestracja</button>' +
        '<button id="auth-tab-login" style="' + tabStyle + (activeTab === 'login' ? activeTabBg : inactiveTabBg) + '">Logowanie</button>' +
      '</div>' +
    '</div>' +
    infoBannerHtml +
    // Register panel
    '<div id="auth-register-panel" class="jg-modal-body" style="padding:24px;' + (activeTab !== 'register' ? 'display:none' : '') + '">' +
      '<form id="auth-register-form">' +
      '<div style="position:absolute;left:-9999px;top:-9999px">' +
        '<label for="auth-register-website">Website</label>' +
        '<input type="text" id="auth-register-website" name="website" tabindex="-1" autocomplete="off">' +
      '</div>' +
      '<div class="jg-form-group" style="margin-bottom:20px">' +
        '<label style="display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px">Nazwa użytkownika (max 60 znaków)</label>' +
        '<input type="text" id="auth-register-username" class="jg-input" required maxlength="60" style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:14px;transition:border-color 0.2s" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#ddd\'">' +
      '</div>' +
      '<div class="jg-form-group" style="margin-bottom:20px">' +
        '<label style="display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px">Adres email</label>' +
        '<input type="email" id="auth-register-email" class="jg-input" required style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:14px;transition:border-color 0.2s" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#ddd\'">' +
      '</div>' +
      '<div class="jg-form-group" style="margin-bottom:20px">' +
        '<label style="display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px">Hasło</label>' +
        '<input type="password" id="auth-register-password" class="jg-input" required style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:14px;transition:border-color 0.2s" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#ddd\'">' +
      '</div>' +
      '<div class="jg-form-group" style="margin-bottom:12px">' +
        '<label style="display:flex;align-items:flex-start;gap:8px;font-size:14px;color:#333;cursor:pointer;line-height:1.5">' +
          '<input type="checkbox" id="auth-register-terms" required style="margin-top:4px;cursor:pointer;width:16px;height:16px;flex-shrink:0">' +
          '<span>' + termsLabel + '</span>' +
        '</label>' +
      '</div>' +
      '<div class="jg-form-group" style="margin-bottom:20px">' +
        '<label style="display:flex;align-items:flex-start;gap:8px;font-size:14px;color:#333;cursor:pointer;line-height:1.5">' +
          '<input type="checkbox" id="auth-register-privacy" required style="margin-top:4px;cursor:pointer;width:16px;height:16px;flex-shrink:0">' +
          '<span>' + privacyLabel + '</span>' +
        '</label>' +
      '</div>' +
      '<div style="font-size:12px;color:#666;margin-top:8px">Na podany adres email zostanie wysłany link aktywacyjny</div>' +
      '</form>' +
    '</div>' +
    // Login panel
    '<div id="auth-login-panel" class="jg-modal-body" style="padding:24px;' + (activeTab !== 'login' ? 'display:none' : '') + '">' +
      '<form id="auth-login-form">' +
      '<div style="position:absolute;left:-9999px;top:-9999px">' +
        '<label for="auth-login-website">Website</label>' +
        '<input type="text" id="auth-login-website" name="website" tabindex="-1" autocomplete="off">' +
      '</div>' +
      '<div class="jg-form-group" style="margin-bottom:20px">' +
        '<label style="display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px">Nazwa użytkownika lub email</label>' +
        '<input type="text" id="auth-login-username" class="jg-input" required style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:14px;transition:border-color 0.2s" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#ddd\'">' +
      '</div>' +
      '<div class="jg-form-group" style="margin-bottom:20px">' +
        '<label style="display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px">Hasło</label>' +
        '<input type="password" id="auth-login-password" class="jg-input" required style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:14px;transition:border-color 0.2s" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#ddd\'">' +
      '</div>' +
      '<div style="text-align:right;margin-bottom:20px">' +
        '<a href="#" id="auth-forgot-password-link" style="color:#8d2324;font-size:13px;text-decoration:none;font-weight:600" onmouseover="this.style.textDecoration=\'underline\'" onmouseout="this.style.textDecoration=\'none\'">Zapomniałeś hasła?</a>' +
      '</div>' +
      '</form>' +
    '</div>' +
    // Footer - register
    '<div id="auth-footer-register" class="jg-modal-footer" style="padding:16px 24px;background:#f9f9f9;border-top:1px solid #e5e5e5;' + (activeTab !== 'register' ? 'display:none;' : 'display:flex;') + 'gap:12px;justify-content:flex-end;border-radius:0 0 8px 8px">' +
      '<button class="jg-btn jg-btn--secondary" id="auth-cancel-reg" style="padding:10px 20px;background:#fff;color:#333;border:2px solid #ddd;border-radius:6px;font-weight:600;cursor:pointer;transition:all 0.2s" onmouseover="this.style.background=\'#f5f5f5\'" onmouseout="this.style.background=\'#fff\'">Anuluj</button>' +
      '<button class="jg-btn jg-btn--primary" id="auth-submit-register" style="padding:10px 24px;background:#8d2324;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;transition:all 0.2s" onmouseover="this.style.background=\'#a02829\'" onmouseout="this.style.background=\'#8d2324\'">Zarejestruj się</button>' +
    '</div>' +
    // Footer - login
    '<div id="auth-footer-login" class="jg-modal-footer" style="padding:16px 24px;background:#f9f9f9;border-top:1px solid #e5e5e5;' + (activeTab !== 'login' ? 'display:none;' : 'display:flex;') + 'gap:12px;justify-content:flex-end;border-radius:0 0 8px 8px">' +
      '<button class="jg-btn jg-btn--secondary" id="auth-cancel-login" style="padding:10px 20px;background:#fff;color:#333;border:2px solid #ddd;border-radius:6px;font-weight:600;cursor:pointer;transition:all 0.2s" onmouseover="this.style.background=\'#f5f5f5\'" onmouseout="this.style.background=\'#fff\'">Anuluj</button>' +
      '<button class="jg-btn jg-btn--primary" id="auth-submit-login" style="padding:10px 24px;background:#8d2324;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;transition:all 0.2s" onmouseover="this.style.background=\'#a02829\'" onmouseout="this.style.background=\'#8d2324\'">Zaloguj się</button>' +
    '</div>';

    open(modalEdit, html);

    // Tab switching
    var tabRegister = document.getElementById('auth-tab-register');
    var tabLogin = document.getElementById('auth-tab-login');
    var panelRegister = document.getElementById('auth-register-panel');
    var panelLogin = document.getElementById('auth-login-panel');
    var footerRegister = document.getElementById('auth-footer-register');
    var footerLogin = document.getElementById('auth-footer-login');

    function switchTab(tab) {
      if (tab === 'register') {
        tabRegister.style.background = '#fff';
        tabRegister.style.color = '#8d2324';
        tabLogin.style.background = 'transparent';
        tabLogin.style.color = 'rgba(255,255,255,0.7)';
        panelRegister.style.display = '';
        panelLogin.style.display = 'none';
        footerRegister.style.display = 'flex';
        footerLogin.style.display = 'none';
      } else {
        tabLogin.style.background = '#fff';
        tabLogin.style.color = '#8d2324';
        tabRegister.style.background = 'transparent';
        tabRegister.style.color = 'rgba(255,255,255,0.7)';
        panelLogin.style.display = '';
        panelRegister.style.display = 'none';
        footerLogin.style.display = 'flex';
        footerRegister.style.display = 'none';
      }
    }

    tabRegister.addEventListener('click', function() { switchTab('register'); });
    tabLogin.addEventListener('click', function() { switchTab('login'); });

    // Close handlers
    var closeModal = function() { modalEdit.style.display = 'none'; };
    document.getElementById('auth-modal-close').addEventListener('click', closeModal);
    document.getElementById('auth-cancel-reg').addEventListener('click', closeModal);
    document.getElementById('auth-cancel-login').addEventListener('click', closeModal);

    // Forgot password link
    document.getElementById('auth-forgot-password-link').addEventListener('click', function(e) {
      e.preventDefault();
      showForgotPasswordModal();
    });

    // Inline terms/privacy content viewers (when no URL, show content in alert)
    var termsInlineLink = document.getElementById('auth-terms-inline');
    if (termsInlineLink && termsContent) {
      termsInlineLink.addEventListener('click', function(e) {
        e.preventDefault();
        showAlert('<div style="text-align:left;max-height:400px;overflow:auto"><h3 style="margin:0 0 12px">Regulamin</h3>' + termsContent + '</div>');
      });
    }
    var privacyInlineLink = document.getElementById('auth-privacy-inline');
    if (privacyInlineLink && privacyContent) {
      privacyInlineLink.addEventListener('click', function(e) {
        e.preventDefault();
        showAlert('<div style="text-align:left;max-height:400px;overflow:auto"><h3 style="margin:0 0 12px">Polityka prywatności</h3>' + privacyContent + '</div>');
      });
    }

    // Register submission
    function submitRegistration() {
      var username = document.getElementById('auth-register-username').value;
      var email = document.getElementById('auth-register-email').value;
      var password = document.getElementById('auth-register-password').value;
      var honeypot = document.getElementById('auth-register-website').value;
      var termsAccepted = document.getElementById('auth-register-terms').checked;
      var privacyAccepted = document.getElementById('auth-register-privacy').checked;

      if (!username || !email || !password) {
        showAlert('Proszę wypełnić wszystkie pola');
        return;
      }

      if (!termsAccepted) {
        showAlert('Musisz zaakceptować Regulamin, aby się zarejestrować');
        return;
      }

      if (!privacyAccepted) {
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
              '<h2 style="margin:0;font-size:20px;font-weight:600">Rejestracja zakończona pomyślnie!</h2>' +
              '</div>' +
              '<div class="jg-modal-body" style="padding:24px;text-align:center">' +
              '<div style="font-size:48px;margin:20px 0">&#128231;</div>' +
              '<p style="font-size:16px;line-height:1.6;color:#333;margin-bottom:20px">Na adres email <strong style="color:#8d2324">' + esc(email) + '</strong> wysłaliśmy wiadomość z linkiem aktywacyjnym.</p>' +
              '<p style="font-size:14px;color:#666;margin-bottom:20px">Sprawdź swoją skrzynkę pocztową (również folder SPAM) i kliknij w link, aby dokończyć rejestrację.</p>' +
              '<div style="background:#fef3c7;border:2px solid #f59e0b;border-radius:8px;padding:12px;margin-top:20px">' +
              '<p style="font-size:13px;color:#92400e;margin:0">Link aktywacyjny jest ważny przez 48 godzin</p>' +
              '</div>' +
              '</div>' +
              '<div class="jg-modal-footer" style="padding:16px 24px;background:#f9f9f9;border-top:1px solid #e5e5e5;display:flex;gap:12px;justify-content:center;border-radius:0 0 8px 8px">' +
              '<button class="jg-btn jg-btn--primary" onclick="document.getElementById(\'jg-map-modal-edit\').style.display=\'none\';location.reload()" style="padding:10px 24px;background:#15803d;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer">OK, rozumiem</button>' +
              '</div>';
            open(modalEdit, successHtml);
          } else {
            if (response.data && typeof response.data === 'object' && response.data.type === 'rate_limit') {
              showRateLimitModal(response.data.message, response.data.seconds_remaining, response.data.action);
            } else if (response.data && typeof response.data === 'object' && response.data.type === 'attempts_warning') {
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

    document.getElementById('auth-submit-register').addEventListener('click', submitRegistration);
    document.getElementById('auth-register-form').addEventListener('keypress', function(e) {
      if (e.key === 'Enter') { e.preventDefault(); submitRegistration(); }
    });

    // Login submission
    function submitLogin() {
      var username = document.getElementById('auth-login-username').value;
      var password = document.getElementById('auth-login-password').value;
      var honeypot = document.getElementById('auth-login-website').value;

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
            closeModal();
            location.reload();
          } else {
            if (response.data && typeof response.data === 'object' && response.data.type === 'pending_activation') {
              showPendingActivationModal(response.data.username, response.data.email);
            } else if (response.data && typeof response.data === 'object' && response.data.type === 'rate_limit') {
              showRateLimitModal(response.data.message, response.data.seconds_remaining, response.data.action);
            } else if (response.data && typeof response.data === 'object' && response.data.type === 'attempts_warning') {
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

    document.getElementById('auth-submit-login').addEventListener('click', submitLogin);
    document.getElementById('auth-login-form').addEventListener('keypress', function(e) {
      if (e.key === 'Enter') { e.preventDefault(); submitLogin(); }
    });
  }

  // Expose openAuthModal globally so jg-map.js can call it
  window.openAuthModal = openAuthModal;

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

  // openRegisterModal and showRegisterForm are now handled by openAuthModal above

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
            // Ratings section
            '<h3 style="margin:20px 0 16px 0;color:#374151;font-size:16px;border-bottom:1px solid #e5e7eb;padding-bottom:8px">⭐ Oceny</h3>' +
            '<div style="display:grid;grid-template-columns:repeat(2, 1fr);gap:12px">' +
            // Ratings given
            '<div style="padding:16px;background:#f9fafb;border-radius:8px">' +
            '<div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:8px">Wystawione oceny</div>' +
            '<div><span style="color:#f59e0b;font-weight:700;font-size:18px">⭐ ' + (stats.ratings_given || 0) + '</span></div>' +
            '</div>' +
            // Ratings received
            '<div style="padding:16px;background:#f9fafb;border-radius:8px">' +
            '<div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:8px">Otrzymane oceny</div>' +
            '<div><span style="color:#f59e0b;font-weight:700;font-size:18px">⭐ ' + (stats.ratings_received || 0) + '</span></div>' +
            (stats.avg_rating_received > 0 ? '<div style="font-size:11px;color:#9ca3af;margin-top:4px">śr. ' + stats.avg_rating_received + '/5</div>' : '') +
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

  // AJAX helper – uses _ajax_nonce key as required by verify_nonce() in PHP
  function apiAjax(action, data) {
    var CFG = window.JG_AUTH_CFG || {};
    var params = { action: action, _ajax_nonce: CFG.nonce };
    if (data) {
      for (var k in data) { if (data.hasOwnProperty(k)) params[k] = data[k]; }
    }
    return new Promise(function(resolve, reject) {
      $.ajax({
        url: CFG.ajax,
        type: 'POST',
        data: params,
        success: function(r) { (r && r.success) ? resolve(r.data) : reject(r && r.data); },
        error: function() { reject(null); }
      });
    });
  }

  // Open user activity modal (admin only, called from openUserModal)
  function openUserActivityModal(userId) {
    var modalEdit = document.getElementById('jg-map-modal-edit');
    if (!modalEdit) return;
    var html = '<header style="background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;padding:16px 20px;border-radius:12px 12px 0 0;display:flex;justify-content:space-between;align-items:center">' +
      '<h3 style="margin:0;font-size:16px">⏱️ Ostatnia aktywność</h3>' +
      '<button id="jg-activity-close" style="background:none;border:none;color:#fff;font-size:24px;cursor:pointer;opacity:0.8;line-height:1">&times;</button>' +
      '</header>' +
      '<div style="padding:20px"><p style="color:#6b7280;text-align:center">Ładowanie...</p></div>';
    open(modalEdit, html);
    document.getElementById('jg-activity-close').onclick = function() { close(modalEdit); };
    apiAjax('jg_get_user_activity', { user_id: userId }).then(function(items) {
      var container = modalEdit.querySelector('.jg-modal');
      if (!items || !items.length) {
        container.querySelector('div[style*="padding:20px"]').innerHTML = '<p style="color:#6b7280;text-align:center">Brak zarejestrowanych aktywności.</p>';
        return;
      }
      var listHtml = '<ul style="list-style:none;margin:0;padding:0">';
      for (var i = 0; i < items.length; i++) {
        var it = items[i];
        var date = it.ts ? new Date(it.ts).toLocaleString('pl-PL', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';
        listHtml += '<li style="display:flex;gap:12px;align-items:flex-start;padding:12px 0;border-bottom:1px solid #f1f5f9">' +
          '<span style="font-size:20px;flex-shrink:0;margin-top:1px">' + it.icon + '</span>' +
          '<div style="flex:1;min-width:0"><div style="font-weight:600;font-size:13px;color:#1f2937">' + esc(it.label) + '</div>' +
          '<div style="font-size:12px;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + esc(it.point_title) + '</div></div>' +
          '<div style="font-size:11px;color:#9ca3af;white-space:nowrap;flex-shrink:0">' + date + '</div></li>';
      }
      listHtml += '</ul>';
      container.querySelector('div[style*="padding:20px"]').innerHTML = listHtml;
    }).catch(function() {});
  }

  // Open all achievements modal for a user
  function openAllAchievementsModal(userId) {
    var modalList = document.getElementById('jg-map-modal-reports-list');
    if (!modalList) return;
    var rarityLabels = { common: 'Zwykłe', uncommon: 'Niepospolite', rare: 'Rzadkie', epic: 'Epickie', legendary: 'Legendarne' };
    var rarityColors = { common: '#d1d5db', uncommon: '#10b981', rare: '#3b82f6', epic: '#8b5cf6', legendary: '#f59e0b' };
    var CFG = window.JG_AUTH_CFG || {};
    $.ajax({
      url: CFG.ajax, type: 'POST',
      data: { action: 'jg_get_user_achievements', user_id: userId },
      success: function(response) {
        if (!response || !response.success || !Array.isArray(response.data)) return;
        var achievements = response.data;
        var html = '<header style="background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%);padding:20px;border-radius:12px 12px 0 0">' +
          '<h3 style="margin:0;color:#fff;font-size:20px">🏆 Osiągnięcia</h3>' +
          '<button class="jg-close" id="ach-modal-close" style="color:#fff;opacity:0.9">&times;</button>' +
          '</header>' +
          '<div style="padding:20px;max-height:70vh;overflow-y:auto"><div class="jg-achievements-grid">';
        for (var i = 0; i < achievements.length; i++) {
          var a = achievements[i];
          var color = rarityColors[a.rarity] || rarityColors.common;
          var label = rarityLabels[a.rarity] || 'Zwykłe';
          var earned = a.earned;
          var earnedDate = a.earned_at ? new Date(a.earned_at).toLocaleDateString('pl-PL') : '';
          html += '<div class="jg-achievement-card' + (earned ? '' : ' jg-achievement-locked') + '" style="border-color:' + color + '">' +
            '<div class="jg-achievement-card-icon" style="box-shadow:' + (earned ? '0 0 12px ' + color : 'none') + ';border-color:' + color + '">' +
            '<span style="font-size:28px">' + (earned ? esc(a.icon) : '🔒') + '</span></div>' +
            '<div class="jg-achievement-card-info"><div class="jg-achievement-card-name">' + esc(a.name) + '</div>' +
            '<div class="jg-achievement-card-desc">' + esc(a.description) + '</div>' +
            '<div class="jg-achievement-card-rarity" style="color:' + color + '">' + label + '</div>' +
            (earnedDate ? '<div class="jg-achievement-card-date">Zdobyto: ' + earnedDate + '</div>' : '') +
            '</div></div>';
        }
        html += '</div></div>';
        open(modalList, html);
        document.getElementById('ach-modal-close').onclick = function() { close(modalList); };
      }
    });
  }

  // Open user profile modal – identical to jg-map.js openUserModal, uses same CSS classes.
  // Exported as window.openUserModal so it works on all pages.
  // On map pages jg-map.js overrides this with its own version after loading.
  function openUserModal(userId, pointsPage, photosPage, editedPointsPage) {
    ensureModalsExist();
    pointsPage = pointsPage || 1;
    photosPage = photosPage || 1;
    editedPointsPage = editedPointsPage || 1;
    var CFG = window.JG_AUTH_CFG || {};
    var isAdmin = !!(CFG.isAdmin);
    var modalReport = document.getElementById('jg-map-modal-report');
    if (!modalReport) return;

    open(modalReport, '<div style="padding:40px;text-align:center"><div style="display:inline-block;width:40px;height:40px;border:3px solid #e5e7eb;border-top-color:#8d2324;border-radius:50%;animation:jg-spin 1s linear infinite"></div>' +
      '<style>@keyframes jg-spin{to{transform:rotate(360deg)}}</style></div>');

    apiAjax('jg_get_user_info', { user_id: userId, points_page: pointsPage, photos_page: photosPage, edited_points_page: editedPointsPage })
      .then(function(user) {
        if (!user) { showAlert('Błąd pobierania informacji o użytkowniku'); return; }
        var memberSince = user.member_since ? new Date(user.member_since).toLocaleDateString('pl-PL') : '-';
        var lastActivity = user.last_activity ? new Date(user.last_activity).toLocaleDateString('pl-PL') : 'Brak aktywności';
        var lastActivityType = user.last_activity_type || '';
        var tc = user.type_counts || {};

        var typeStatsHtml = '<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(100px, 1fr));gap:12px;margin-bottom:20px">' +
          '<div style="padding:14px;background:#ecfdf5;border-radius:8px;text-align:center;border-left:4px solid #10b981"><div style="font-size:12px;color:#6b7280;margin-bottom:4px">📍 Miejsca</div><div style="font-weight:700;font-size:22px;color:#059669">' + (tc.miejsce || 0) + '</div></div>' +
          '<div style="padding:14px;background:#fef3c7;border-radius:8px;text-align:center;border-left:4px solid #f59e0b"><div style="font-size:12px;color:#6b7280;margin-bottom:4px">💡 Ciekawostki</div><div style="font-weight:700;font-size:22px;color:#d97706">' + (tc.ciekawostka || 0) + '</div></div>' +
          '<div style="padding:14px;background:#fce7f3;border-radius:8px;text-align:center;border-left:4px solid #ec4899"><div style="font-size:12px;color:#6b7280;margin-bottom:4px">📢 Zgłoszenia</div><div style="font-weight:700;font-size:22px;color:#db2777">' + (tc.zgloszenie || 0) + '</div></div>' +
          '<div style="padding:14px;background:#eff6ff;border-radius:8px;text-align:center;border-left:4px solid #3b82f6"><div style="font-size:12px;color:#6b7280;margin-bottom:4px">👍 Głosowania</div><div style="font-weight:700;font-size:22px;color:#2563eb">' + (tc.votes || 0) + '</div></div>' +
          '<div style="padding:14px;background:#f5f3ff;border-radius:8px;text-align:center;border-left:4px solid #8b5cf6"><div style="font-size:12px;color:#6b7280;margin-bottom:4px">✏️ Edycje</div><div style="font-weight:700;font-size:22px;color:#7c3aed">' + (tc.edits || 0) + '</div></div>' +
          '</div>';

        var pointsHtml = '';
        if (user.points && user.points.length > 0) {
          pointsHtml = '<div style="margin-top:12px">';
          for (var i = 0; i < user.points.length; i++) {
            var point = user.points[i];
            var typeLabels = { miejsce: '📍 Miejsce', ciekawostka: '💡 Ciekawostka', zgloszenie: '📢 Zgłoszenie' };
            var createdAt = point.created_at ? new Date(point.created_at).toLocaleDateString('pl-PL') : '-';
            pointsHtml += '<div style="padding:10px;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:8px">' +
              '<div style="font-weight:600;margin-bottom:4px">' + esc(point.title) + '</div>' +
              '<div style="font-size:12px;color:#6b7280"><span style="margin-right:12px">' + (typeLabels[point.type] || point.type) + '</span><span>Dodano: ' + createdAt + '</span></div></div>';
          }
          pointsHtml += '</div>';
          if (user.points_pages > 1) {
            pointsHtml += '<div style="display:flex;justify-content:center;align-items:center;gap:8px;margin-top:12px">' +
              '<button class="jg-user-modal-points-prev" style="padding:6px 14px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer;font-size:13px' + (pointsPage <= 1 ? ';opacity:0.4;pointer-events:none' : '') + '">&laquo; Poprzednie</button>' +
              '<span style="font-size:13px;color:#6b7280">Strona ' + user.points_page + ' z ' + user.points_pages + '</span>' +
              '<button class="jg-user-modal-points-next" style="padding:6px 14px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer;font-size:13px' + (pointsPage >= user.points_pages ? ';opacity:0.4;pointer-events:none' : '') + '">Następne &raquo;</button></div>';
          }
        } else {
          pointsHtml = '<div style="padding:20px;text-align:center;color:#9ca3af">Brak dodanych miejsc</div>';
        }

        var editedPointsHtml = '';
        if (user.edited_points && user.edited_points.length > 0) {
          editedPointsHtml = '<div style="margin-top:20px"><h4 style="margin:0 0 8px 0;color:#374151;font-size:16px;border-bottom:1px solid #e5e7eb;padding-bottom:8px">✏️ Edytowane pinezki (' + user.edited_points_total + ')</h4><div style="margin-top:12px">';
          for (var ei = 0; ei < user.edited_points.length; ei++) {
            var ep = user.edited_points[ei];
            var epTypeLabels = { miejsce: '📍 Miejsce', ciekawostka: '💡 Ciekawostka', zgloszenie: '📢 Zgłoszenie' };
            var epEditedAt = ep.last_edited_at ? new Date(ep.last_edited_at).toLocaleDateString('pl-PL') : '-';
            editedPointsHtml += '<div style="padding:10px;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:8px">' +
              '<div style="font-weight:600;margin-bottom:4px">' + esc(ep.title) + '</div>' +
              '<div style="font-size:12px;color:#6b7280"><span style="margin-right:12px">' + (epTypeLabels[ep.type] || ep.type) + '</span><span>Ostatnia edycja: ' + epEditedAt + '</span></div></div>';
          }
          editedPointsHtml += '</div>';
          if (user.edited_points_pages > 1) {
            editedPointsHtml += '<div style="display:flex;justify-content:center;align-items:center;gap:8px;margin-top:12px">' +
              '<button class="jg-user-modal-edited-points-prev" style="padding:6px 14px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer;font-size:13px' + (editedPointsPage <= 1 ? ';opacity:0.4;pointer-events:none' : '') + '">&laquo; Poprzednie</button>' +
              '<span style="font-size:13px;color:#6b7280">Strona ' + user.edited_points_page + ' z ' + user.edited_points_pages + '</span>' +
              '<button class="jg-user-modal-edited-points-next" style="padding:6px 14px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer;font-size:13px' + (editedPointsPage >= user.edited_points_pages ? ';opacity:0.4;pointer-events:none' : '') + '">Następne &raquo;</button></div>';
          }
          editedPointsHtml += '</div>';
        } else if (user.edited_points_total === 0) {
          editedPointsHtml = '<div style="margin-top:20px"><h4 style="margin:0 0 8px 0;color:#374151;font-size:16px;border-bottom:1px solid #e5e7eb;padding-bottom:8px">✏️ Edytowane pinezki</h4>' +
            '<div style="padding:20px;text-align:center;color:#9ca3af">Brak edytowanych pinezek</div></div>';
        }

        var photosHtml = '';
        if (user.photos_total > 0) {
          photosHtml = '<div><h4 style="margin:20px 0 12px 0;color:#374151">📷 Galeria zdjęć (' + user.photos_total + ')</h4>' +
            '<div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(120px, 1fr));gap:12px">';
          for (var j = 0; j < user.photos.length; j++) {
            var photo = user.photos[j];
            var photoUrl = typeof photo === 'string' ? photo : (photo.url || photo.full || '');
            var thumbUrl = typeof photo === 'string' ? photo : (photo.thumbnail || photo.thumb || photo.url || photo.full || '');
            if (photoUrl && thumbUrl) {
              photosHtml += '<div class="user-photo-item" data-photo-url="' + esc(photoUrl) + '" style="position:relative;padding-bottom:100%;border-radius:8px;overflow:hidden;cursor:pointer;transition:transform 0.2s,box-shadow 0.2s">' +
                '<img src="' + esc(thumbUrl) + '" alt="User photo" style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover"></div>';
            }
          }
          photosHtml += '</div>';
          if (user.photos_pages > 1) {
            photosHtml += '<div style="display:flex;justify-content:center;align-items:center;gap:8px;margin-top:12px">' +
              '<button class="jg-user-modal-photos-prev" style="padding:6px 14px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer;font-size:13px' + (photosPage <= 1 ? ';opacity:0.4;pointer-events:none' : '') + '">&laquo; Poprzednie</button>' +
              '<span style="font-size:13px;color:#6b7280">Strona ' + user.photos_page + ' z ' + user.photos_pages + '</span>' +
              '<button class="jg-user-modal-photos-next" style="padding:6px 14px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer;font-size:13px' + (photosPage >= user.photos_pages ? ';opacity:0.4;pointer-events:none' : '') + '">Następne &raquo;</button></div>';
          }
          photosHtml += '</div>';
        }

        var modalHtml = '<header style="background:linear-gradient(135deg, #8d2324 0%, #6b1a1b 100%);padding:20px;border-radius:12px 12px 0 0">' +
          '<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">' +
          '<h3 style="margin:0;color:#fff;font-size:20px;flex-shrink:0">👤 ' + esc(user.username) + '</h3>' +
          '<span id="jg-user-level-badge" class="jg-level-badge" style="display:none"></span>' +
          '<div id="jg-user-xp-bar-wrap" class="jg-xp-bar-wrap" style="display:none;flex:1;min-width:120px">' +
          '<div class="jg-xp-bar"><div class="jg-xp-bar-fill" id="jg-user-xp-fill" style="width:0%"></div></div>' +
          '<div class="jg-xp-bar-text" id="jg-user-xp-text"></div></div>' +
          '<div id="jg-user-achievements-panel" class="jg-achievements-panel" style="display:none;cursor:pointer" title="Kliknij aby zobaczyć wszystkie osiągnięcia"></div>' +
          '</div>' +
          '<button class="jg-close" id="user-modal-close" style="color:#fff;opacity:0.9">&times;</button>' +
          '</header>' +
          '<div style="padding:20px;max-height:70vh;overflow-y:auto">' +
          '<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin-bottom:20px">' +
          '<div style="padding:16px;background:#f9fafb;border-radius:8px"><div style="font-size:12px;color:#6b7280;margin-bottom:4px">📅 Członek od</div><div style="font-weight:600">' + memberSince + '</div></div>' +
          (isAdmin ?
            '<div style="padding:16px;background:#f9fafb;border-radius:8px"><div style="font-size:12px;color:#6b7280;margin-bottom:4px">⏱️ Ostatnia aktywność</div>' +
            '<div style="font-weight:600">' + lastActivity + '</div>' +
            (lastActivityType ? '<div style="font-size:11px;color:#9ca3af;margin-top:3px">' + lastActivityType + '</div>' : '') +
            (user.last_activity ? '<div style="font-size:11px;color:#6366f1;margin-top:5px;cursor:pointer;text-decoration:underline" id="jg-view-activity-link">Zobacz historię aktywności →</div>' : '') +
            '</div>'
          : '') +
          '<div style="padding:16px;background:#f9fafb;border-radius:8px"><div style="font-size:12px;color:#6b7280;margin-bottom:4px">📍 Dodane miejsca</div><div style="font-weight:600;font-size:24px">' + user.points_count + '</div></div>' +
          '</div>' +
          '<h4 style="margin:0 0 12px 0;color:#374151;font-size:16px;border-bottom:1px solid #e5e7eb;padding-bottom:8px">📊 Statystyki pinezek</h4>' +
          typeStatsHtml +
          '<div><h4 style="margin:0 0 8px 0;color:#374151;font-size:16px;border-bottom:1px solid #e5e7eb;padding-bottom:8px">📍 Dodane miejsca (' + user.points_count + ')</h4>' +
          pointsHtml + '</div>' +
          editedPointsHtml + photosHtml +
          '</div>';

        open(modalReport, modalHtml);
        document.getElementById('user-modal-close').onclick = function() { close(modalReport); };
        var actLink = document.getElementById('jg-view-activity-link');
        if (actLink) actLink.onclick = function() { openUserActivityModal(userId); };

        // Load level/XP/achievements asynchronously
        $.ajax({
          url: CFG.ajax, type: 'POST',
          data: { action: 'jg_get_user_level_info', user_id: userId },
          success: function(r) {
            if (!r || !r.success || !r.data) return;
            var ld = r.data;
            var badge = document.getElementById('jg-user-level-badge');
            if (badge) {
              badge.textContent = 'Poz. ' + ld.level;
              badge.style.display = 'inline-block';
              var lvl = ld.level;
              var tier = lvl >= 50 ? 'legend' : lvl >= 40 ? 'ruby' : lvl >= 30 ? 'diamond' : lvl >= 20 ? 'purple' : lvl >= 15 ? 'emerald' : lvl >= 10 ? 'gold' : lvl >= 5 ? 'silver' : 'bronze';
              badge.className = 'jg-level-badge jg-badge-' + tier;
            }
            var barWrap = document.getElementById('jg-user-xp-bar-wrap');
            var barFill = document.getElementById('jg-user-xp-fill');
            var barText = document.getElementById('jg-user-xp-text');
            if (barWrap && barFill && barText) {
              barWrap.style.display = 'block';
              barFill.style.width = ld.progress + '%';
              barText.textContent = ld.xp_in_level + ' / ' + ld.xp_needed + ' XP';
            }
            var achPanel = document.getElementById('jg-user-achievements-panel');
            if (achPanel && ld.recent_achievements && ld.recent_achievements.length > 0) {
              var rarityGlows = { common: '0 0 8px rgba(209,213,219,0.8)', uncommon: '0 0 8px rgba(16,185,129,0.8)', rare: '0 0 8px rgba(59,130,246,0.8)', epic: '0 0 8px rgba(139,92,246,0.8)', legendary: '0 0 10px rgba(245,158,11,0.9), 0 0 20px rgba(245,158,11,0.4)' };
              var rarityBorders = { common: '#d1d5db', uncommon: '#10b981', rare: '#3b82f6', epic: '#8b5cf6', legendary: '#f59e0b' };
              var achHtml = '';
              for (var a = 0; a < ld.recent_achievements.length; a++) {
                var ach = ld.recent_achievements[a];
                achHtml += '<div class="jg-achievement-icon" title="' + esc(ach.name) + ': ' + esc(ach.description) + '" style="border-color:' + (rarityBorders[ach.rarity] || rarityBorders.common) + ';box-shadow:' + (rarityGlows[ach.rarity] || rarityGlows.common) + '"><span>' + esc(ach.icon) + '</span></div>';
              }
              if (ld.total_achievements > 4) achHtml += '<div class="jg-achievement-more">+' + (ld.total_achievements - 4) + '</div>';
              achPanel.innerHTML = achHtml;
              achPanel.style.display = 'flex';
              achPanel.onclick = function() { openAllAchievementsModal(userId); };
            }
          }
        });

        // Pagination handlers
        var pPrev = modalReport.querySelector('.jg-user-modal-points-prev');
        var pNext = modalReport.querySelector('.jg-user-modal-points-next');
        if (pPrev && pointsPage > 1) pPrev.onclick = function() { window.openUserModal(userId, pointsPage - 1, photosPage, editedPointsPage); };
        if (pNext && pointsPage < user.points_pages) pNext.onclick = function() { window.openUserModal(userId, pointsPage + 1, photosPage, editedPointsPage); };
        var ePrev = modalReport.querySelector('.jg-user-modal-edited-points-prev');
        var eNext = modalReport.querySelector('.jg-user-modal-edited-points-next');
        if (ePrev && editedPointsPage > 1) ePrev.onclick = function() { window.openUserModal(userId, pointsPage, photosPage, editedPointsPage - 1); };
        if (eNext && editedPointsPage < user.edited_points_pages) eNext.onclick = function() { window.openUserModal(userId, pointsPage, photosPage, editedPointsPage + 1); };
        var phPrev = modalReport.querySelector('.jg-user-modal-photos-prev');
        var phNext = modalReport.querySelector('.jg-user-modal-photos-next');
        if (phPrev && photosPage > 1) phPrev.onclick = function() { window.openUserModal(userId, pointsPage, photosPage - 1, editedPointsPage); };
        if (phNext && photosPage < user.photos_pages) phNext.onclick = function() { window.openUserModal(userId, pointsPage, photosPage + 1, editedPointsPage); };

        // Photo clicks – open in new tab (lightbox not available outside map page)
        var photoItems = modalReport.querySelectorAll('.user-photo-item');
        for (var pk = 0; pk < photoItems.length; pk++) {
          photoItems[pk].onmouseover = function() { this.style.transform = 'scale(1.05)'; this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)'; };
          photoItems[pk].onmouseout = function() { this.style.transform = 'scale(1)'; this.style.boxShadow = 'none'; };
          photoItems[pk].onclick = function() { var u = this.getAttribute('data-photo-url'); if (u) window.open(u, '_blank'); };
        }
      })
      .catch(function(err) { showAlert((err && err.message) || 'Błąd pobierania informacji o użytkowniku'); });
  }

  // Export so it can be called from top-bar profile link on any page
  window.openUserModal = openUserModal;

  // Ranking modal – matches jg-map.js styling (uses jg-ranking-* CSS classes from jg-map.css)
  function openRankingModal() {
    ensureModalsExist();
    var modalRanking = document.getElementById('jg-map-modal-ranking');
    if (!modalRanking) return;

    var loadingHtml = '<header class="jg-ranking-header"><div class="jg-ranking-header-inner"><h3 class="jg-ranking-title">🏆 Ranking użytkowników</h3></div>' +
      '<button class="jg-close" id="ranking-modal-close" style="color:#fff;opacity:0.9">&times;</button></header>' +
      '<div style="padding:40px;text-align:center;color:#6b7280">Ładowanie rankingu...</div>';
    open(modalRanking, loadingHtml);
    document.getElementById('ranking-modal-close').onclick = function() { close(modalRanking); };

    apiAjax('jg_get_ranking', {}).then(function(ranking) {
      if (!ranking || !ranking.length) {
        var emptyHtml = '<header class="jg-ranking-header"><div class="jg-ranking-header-inner"><h3 class="jg-ranking-title">🏆 Ranking użytkowników</h3></div>' +
          '<button class="jg-close" id="ranking-modal-close" style="color:#fff;opacity:0.9">&times;</button></header>' +
          '<div style="padding:40px;text-align:center;color:#6b7280">Brak danych rankingu.</div>';
        open(modalRanking, emptyHtml);
        document.getElementById('ranking-modal-close').onclick = function() { close(modalRanking); };
        return;
      }
      var rowsHtml = '';
      for (var i = 0; i < ranking.length; i++) {
        var r = ranking[i];
        var pos = i + 1;
        var rowClass = 'jg-ranking-row' + (pos === 1 ? ' jg-ranking-gold' : pos === 2 ? ' jg-ranking-silver' : pos === 3 ? ' jg-ranking-bronze' : '');
        var starHtml = pos === 1 ? '<span class="jg-ranking-star">⭐</span> ' : '';
        rowsHtml += '<div class="' + rowClass + '" data-user-id="' + r.user_id + '">' +
          '<div class="jg-ranking-pos">' + pos + '</div>' +
          '<div class="jg-ranking-info"><div class="jg-ranking-name">' + starHtml + '<a href="#" class="jg-ranking-user-link" data-user-id="' + r.user_id + '">' + esc(r.display_name) + '</a></div>' +
          '<div class="jg-ranking-meta"><span class="jg-ranking-level">Poz. ' + r.level + '</span><span class="jg-ranking-places">📍 ' + r.places_count + ' miejsc</span></div></div>' +
          '<div class="jg-ranking-count">' + r.places_count + '</div></div>';
      }
      for (var k = ranking.length; k < 10; k++) {
        rowsHtml += '<div class="jg-ranking-row jg-ranking-empty"><div class="jg-ranking-pos">' + (k + 1) + '</div>' +
          '<div class="jg-ranking-info"><div class="jg-ranking-empty-bar"></div><div class="jg-ranking-empty-bar jg-ranking-empty-bar--short"></div></div>' +
          '<div class="jg-ranking-count jg-ranking-empty-count"></div></div>';
      }
      var html = '<header class="jg-ranking-header"><div class="jg-ranking-header-inner"><div class="jg-ranking-trophy">🏆</div>' +
        '<div><h3 class="jg-ranking-title">Ranking użytkowników</h3><p class="jg-ranking-subtitle">Top 10 najbardziej aktywnych użytkowników</p></div></div>' +
        '<button class="jg-close" id="ranking-modal-close" style="color:#fff;opacity:0.9">&times;</button></header>' +
        '<div class="jg-ranking-body"><div class="jg-ranking-list">' + rowsHtml + '</div></div>';
      open(modalRanking, html);
      document.getElementById('ranking-modal-close').onclick = function() { close(modalRanking); };
      var userLinks = modalRanking.querySelectorAll('.jg-ranking-user-link');
      for (var j = 0; j < userLinks.length; j++) {
        (function(link) {
          link.addEventListener('click', function(e) {
            e.preventDefault();
            close(modalRanking);
            var uid = parseInt(link.getAttribute('data-user-id'), 10);
            if (uid) window.openUserModal(uid);
          });
        })(userLinks[j]);
      }
    }).catch(function() { showAlert('Błąd pobierania rankingu'); });
  }

  // Initialize buttons when DOM is ready
  function initAuthButtons() {
    var authBtn = document.getElementById('jg-auth-btn');
    var editProfileBtn = document.getElementById('jg-edit-profile-btn');
    var myProfileLink = document.getElementById('jg-my-profile-link');

    // Single auth button opens tabbed modal (register tab by default)
    if (authBtn && !authBtn.jgHandlerAttached) {
      authBtn.addEventListener('click', function() { openAuthModal('register'); });
      authBtn.jgHandlerAttached = true;
    }

    if (editProfileBtn && !editProfileBtn.jgHandlerAttached) {
      editProfileBtn.addEventListener('click', openEditProfileModal);
      editProfileBtn.jgHandlerAttached = true;
    }

    if (myProfileLink && !myProfileLink.jgHandlerAttached) {
      myProfileLink.addEventListener('click', function(e) {
        e.preventDefault();
        var userId = parseInt(myProfileLink.getAttribute('data-user-id'), 10);
        if (userId && typeof window.openUserModal === 'function') {
          window.openUserModal(userId);
        } else {
          openMyProfileModal();
        }
      });
      myProfileLink.jgHandlerAttached = true;
    }

    // Ranking button – only handle here when jg-map.js is NOT loaded (non-map pages)
    var rankingBtn = document.getElementById('jg-ranking-btn');
    if (rankingBtn && !rankingBtn.jgHandlerAttached) {
      rankingBtn.addEventListener('click', openRankingModal);
      rankingBtn.jgHandlerAttached = true;
    }
  }

  // Add Escape key handler to close modals
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      var ids = ['jg-map-modal-edit', 'jg-map-modal-report', 'jg-map-modal-ranking', 'jg-map-modal-reports-list', 'jg-modal-alert'];
      for (var i = 0; i < ids.length; i++) {
        var m = document.getElementById(ids[i]);
        if (m && m.style.display === 'flex') { m.style.display = 'none'; break; }
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
