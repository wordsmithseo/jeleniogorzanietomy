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
   * Unified join modal: benefits + social login + email form in one place.
   * Replaces the old separate benefits/register/login modals.
   * @param {object|string} opts - options object OR legacy activeTab string
   *   opts.view        - 'register' (default) or 'login'
   *   opts.message     - optional info banner text
   *   opts.trigger     - 'timer'|'places'|'action' (for analytics)
   */
  function openJoinModal(opts) {
    var CFG = window.JG_AUTH_CFG || window.JG_MAP_CFG || {};
    ensureModalsExist();
    var modalEdit = document.getElementById('jg-map-modal-edit');
    if (!modalEdit) return;

    if (typeof opts === 'string') opts = { view: opts };
    opts = opts || {};
    var initialView = (opts.view === 'login' || opts.activeTab === 'login') ? 'login' : 'register';
    var infoMessage = opts.message || opts.infoMessage || null;

    var regEnabled = CFG.registrationEnabled !== false && CFG.registrationEnabled !== 0 && CFG.registrationEnabled !== '0';
    var termsUrl = CFG.termsUrl || '';
    var privacyUrl = CFG.privacyUrl || '';
    var termsContent = CFG.termsContent || '';
    var privacyContent = CFG.privacyContent || '';
    var googleClientId = CFG.googleClientId || '';
    var facebookAppId = CFG.facebookAppId || '';
    var oauthBase = CFG.oauthCallbackBase || '';
    var hasSocial = !!(googleClientId || facebookAppId);

    var termsHref = termsUrl ? 'href="' + esc(termsUrl) + '" target="_blank"' : 'href="#" id="jm-terms-il"';
    var privacyHref = privacyUrl ? 'href="' + esc(privacyUrl) + '" target="_blank"' : 'href="#" id="jm-priv-il"';

    var gSvg = '<svg width="18" height="18" viewBox="0 0 18 18" style="flex-shrink:0"><path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.875 2.684-6.615z"/><path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332C2.438 15.983 5.482 18 9 18z"/><path fill="#FBBC05" d="M3.964 10.71c-.18-.54-.282-1.117-.282-1.71s.102-1.17.282-1.71V4.958H.957C.347 6.173 0 7.548 0 9s.348 2.827.957 4.042l3.007-2.332z"/><path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0 5.482 0 2.438 2.017.957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z"/></svg>';
    var fbSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="#fff" style="flex-shrink:0"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>';

    function mkGBtn(p) {
      if (!googleClientId) return '';
      return '<button id="' + p + '-g" type="button" style="width:100%;padding:11px 16px;background:#fff;color:#3c4043;border:1.5px solid #dadce0;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:10px;box-sizing:border-box" onmouseover="this.style.background=\'#f8f9fa\'" onmouseout="this.style.background=\'#fff\'">' + gSvg + 'Kontynuuj z Google</button>';
    }
    function mkFbBtn(p) {
      if (!facebookAppId) return '';
      return '<button id="' + p + '-fb" type="button" style="width:100%;padding:11px 16px;background:#1877f2;color:#fff;border:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:10px;box-sizing:border-box" onmouseover="this.style.background=\'#1666d9\'" onmouseout="this.style.background=\'#1877f2\'">' + fbSvg + 'Kontynuuj przez Facebook</button>';
    }

    var divider = hasSocial ? '<div style="display:flex;align-items:center;gap:10px;margin-bottom:16px"><div style="flex:1;height:1px;background:#e5e5e5"></div><span style="font-size:11px;color:#9ca3af;white-space:nowrap;text-transform:uppercase;letter-spacing:.5px">lub przez email</span><div style="flex:1;height:1px;background:#e5e5e5"></div></div>' : '';
    var infoBanner = infoMessage ? '<div style="background:#fef3c7;border-bottom:1px solid #f59e0b;padding:10px 20px;display:flex;align-items:flex-start;gap:8px"><span style="flex-shrink:0;margin-top:1px;font-size:16px">&#9432;</span><p style="margin:0;font-size:12px;color:#92400e;line-height:1.5">' + infoMessage + '</p></div>' : '';

    var hS = 'background:linear-gradient(135deg,#8d2324 0%,#b03030 100%);color:#fff;padding:20px 24px 16px;border-radius:8px 8px 0 0;position:relative';
    var cS = 'position:absolute;top:12px;right:14px;background:rgba(255,255,255,0.2);border:none;color:#fff;width:28px;height:28px;border-radius:50%;font-size:18px;cursor:pointer;line-height:1;display:flex;align-items:center;justify-content:center';
    var bS = 'padding:20px 24px 16px';
    var iS = 'width:100%;padding:10px 12px;border:2px solid #e5e5e5;border-radius:6px;font-size:14px;box-sizing:border-box;outline:none;transition:border-color 0.2s';
    var lS = 'display:block;margin-bottom:5px;font-weight:600;color:#333;font-size:13px';
    var pB = 'width:100%;padding:13px;background:#8d2324;color:#fff;border:none;border-radius:6px;font-size:15px;font-weight:700;cursor:pointer;margin-bottom:12px;box-sizing:border-box';
    var lkB = 'background:none;border:none;color:#8d2324;font-size:13px;cursor:pointer;padding:4px;font-weight:600;text-decoration:underline';
    var dmB = 'background:none;border:none;color:#bbb;font-size:11px;cursor:pointer;padding:4px;margin-top:2px';

    var regFormHtml = regEnabled
      ? '<form id="jm-reg-form" autocomplete="on">' +
          '<div style="position:absolute;left:-9999px"><label for="jm-hp">Website</label><input type="text" id="jm-hp" name="website" tabindex="-1" autocomplete="off"></div>' +
          '<div style="margin-bottom:14px"><label style="' + lS + '">Nazwa użytkownika (max 60 znaków)</label><input type="text" id="jm-reg-u" maxlength="60" required autocomplete="username" style="' + iS + '" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#e5e5e5\'"></div>' +
          '<div style="margin-bottom:14px"><label style="' + lS + '">Adres email</label><input type="email" id="jm-reg-e" required autocomplete="email" style="' + iS + '" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#e5e5e5\'"></div>' +
          '<div style="margin-bottom:14px"><label style="' + lS + '">Hasło</label><input type="password" id="jm-reg-p" required autocomplete="new-password" style="' + iS + '" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#e5e5e5\'"></div>' +
          '<div style="margin-bottom:10px"><label style="display:flex;align-items:flex-start;gap:8px;font-size:13px;color:#333;cursor:pointer;line-height:1.4"><input type="checkbox" id="jm-reg-terms" required style="margin-top:2px;flex-shrink:0;width:15px;height:15px;cursor:pointer"><span>Akceptuję <a ' + termsHref + ' style="color:#8d2324;font-weight:600;text-decoration:underline">Regulamin</a> serwisu *</span></label></div>' +
          '<div style="margin-bottom:16px"><label style="display:flex;align-items:flex-start;gap:8px;font-size:13px;color:#333;cursor:pointer;line-height:1.4"><input type="checkbox" id="jm-reg-priv" required style="margin-top:2px;flex-shrink:0;width:15px;height:15px;cursor:pointer"><span>Akceptuję <a ' + privacyHref + ' style="color:#8d2324;font-weight:600;text-decoration:underline">Politykę prywatności</a> serwisu *</span></label></div>' +
          '<p style="font-size:11px;color:#9ca3af;margin:0 0 14px">📧 Link aktywacyjny zostanie wysłany na podany email</p>' +
        '</form>' +
        '<button id="jm-reg-submit" type="button" style="' + pB + '" onmouseover="this.style.background=\'#a32929\'" onmouseout="this.style.background=\'#8d2324\'">Zarejestruj się (bezpłatnie)</button>'
      : '<div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:16px;margin-bottom:16px;text-align:center"><p style="margin:0;font-size:14px;color:#92400e">' + esc(CFG.registrationDisabledMessage || 'Rejestracja jest obecnie wyłączona.') + '</p></div>';

    var html =
      '<div id="jm-reg" style="' + (initialView === 'login' ? 'display:none' : '') + '">' +
        '<div style="' + hS + '"><button id="jm-close" type="button" style="' + cS + '">&times;</button>' +
          '<h2 style="margin:0 0 4px;font-size:19px;font-weight:700">Dołącz do społeczności!</h2>' +
          '<p style="margin:0;font-size:13px;opacity:0.9">Zacznij kształtować mapę Jeleniej Góry</p>' +
        '</div>' + infoBanner +
        '<div style="' + bS + '">' +
          '<div style="display:flex;gap:8px;margin-bottom:18px">' +
            '<div style="flex:1;background:#fef2f2;border-radius:8px;padding:10px 6px;text-align:center;min-width:0"><div style="font-size:20px;margin-bottom:3px">📍</div><div style="font-size:11px;font-weight:700;color:#333;line-height:1.3">Dodawaj<br>miejsca</div><div style="font-size:10px;color:#8d2324;margin-top:3px;font-weight:600">+XP</div></div>' +
            '<div style="flex:1;background:#fff7ed;border-radius:8px;padding:10px 6px;text-align:center;min-width:0"><div style="font-size:20px;margin-bottom:3px">⚠️</div><div style="font-size:11px;font-weight:700;color:#333;line-height:1.3">Zgłaszaj<br>problemy</div><div style="font-size:10px;color:#8d2324;margin-top:3px;font-weight:600">+XP</div></div>' +
            '<div style="flex:1;background:#f0fdf4;border-radius:8px;padding:10px 6px;text-align:center;min-width:0"><div style="font-size:20px;margin-bottom:3px">🏆</div><div style="font-size:11px;font-weight:700;color:#333;line-height:1.3">Zdobywaj<br>nagrody</div><div style="font-size:10px;color:#666;margin-top:3px">Odznaki</div></div>' +
          '</div>' +
          mkGBtn('jm-r') + mkFbBtn('jm-r') + divider + regFormHtml +
          '<div style="text-align:center"><button id="jm-to-login" type="button" style="' + lkB + '">Mam już konto — zaloguj się</button></div>' +
          '<div style="text-align:center"><button id="jm-dismiss" type="button" style="' + dmB + '">Przeglądaj bez logowania</button></div>' +
        '</div>' +
      '</div>' +
      '<div id="jm-log" style="' + (initialView !== 'login' ? 'display:none' : '') + '">' +
        '<div style="' + hS + '"><button id="jm-close-log" type="button" style="' + cS + '">&times;</button>' +
          '<h2 style="margin:0 0 4px;font-size:19px;font-weight:700">Zaloguj się</h2>' +
          '<p style="margin:0;font-size:13px;opacity:0.9">Wróć do swojej mapy Jeleniej Góry</p>' +
        '</div>' + infoBanner +
        '<div style="' + bS + '">' +
          mkGBtn('jm-l') + mkFbBtn('jm-l') + divider +
          '<form id="jm-log-form" autocomplete="on">' +
            '<div style="position:absolute;left:-9999px"><label for="jm-log-hp">Website</label><input type="text" id="jm-log-hp" name="website" tabindex="-1" autocomplete="off"></div>' +
            '<div style="margin-bottom:14px"><label style="' + lS + '">Nazwa użytkownika lub email</label><input type="text" id="jm-log-u" required autocomplete="username" style="' + iS + '" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#e5e5e5\'"></div>' +
            '<div style="margin-bottom:6px"><label style="' + lS + '">Hasło</label><input type="password" id="jm-log-p" required autocomplete="current-password" style="' + iS + '" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#e5e5e5\'"></div>' +
            '<div style="text-align:right;margin-bottom:16px"><a href="#" id="jm-forgot" style="color:#8d2324;font-size:12px;font-weight:600" onmouseover="this.style.textDecoration=\'underline\'" onmouseout="this.style.textDecoration=\'none\'">Zapomniałeś hasła?</a></div>' +
          '</form>' +
          '<button id="jm-log-submit" type="button" style="' + pB + '" onmouseover="this.style.background=\'#a32929\'" onmouseout="this.style.background=\'#8d2324\'">Zaloguj się</button>' +
          '<div style="text-align:center"><button id="jm-to-reg" type="button" style="' + lkB + '">← Nie masz konta? Zarejestruj się</button></div>' +
          '<div style="text-align:center"><button id="jm-dismiss-log" type="button" style="' + dmB + '">Przeglądaj bez logowania</button></div>' +
        '</div>' +
      '</div>';

    open(modalEdit, html);

    var regView = document.getElementById('jm-reg');
    var logView = document.getElementById('jm-log');
    var inner = modalEdit.querySelector('.jg-modal');

    function showView(v) {
      if (v === 'login') {
        if (regView) regView.style.display = 'none';
        if (logView) logView.style.display = '';
      } else {
        if (logView) logView.style.display = 'none';
        if (regView) regView.style.display = '';
      }
      if (inner) inner.scrollTop = 0;
    }

    function closeModal() { modalEdit.style.display = 'none'; }

    var el;
    if ((el = document.getElementById('jm-close'))) el.addEventListener('click', closeModal);
    if ((el = document.getElementById('jm-close-log'))) el.addEventListener('click', closeModal);
    if ((el = document.getElementById('jm-dismiss'))) el.addEventListener('click', closeModal);
    if ((el = document.getElementById('jm-dismiss-log'))) el.addEventListener('click', closeModal);
    if ((el = document.getElementById('jm-to-login'))) el.addEventListener('click', function() { showView('login'); });
    if ((el = document.getElementById('jm-to-reg'))) el.addEventListener('click', function() { showView('register'); });
    if ((el = document.getElementById('jm-forgot'))) el.addEventListener('click', function(e) { e.preventDefault(); showForgotPasswordModal(); });

    var termsIl = document.getElementById('jm-terms-il');
    if (termsIl && termsContent) {
      termsIl.addEventListener('click', function(e) {
        e.preventDefault();
        showAlert('<div style="text-align:left;max-height:400px;overflow:auto"><h3 style="margin:0 0 12px">Regulamin</h3>' + termsContent + '</div>');
      });
    }
    var privIl = document.getElementById('jm-priv-il');
    if (privIl && privacyContent) {
      privIl.addEventListener('click', function(e) {
        e.preventDefault();
        showAlert('<div style="text-align:left;max-height:400px;overflow:auto"><h3 style="margin:0 0 12px">Polityka prywatności</h3>' + privacyContent + '</div>');
      });
    }

    // ── Registration submit ──
    function submitRegistration() {
      var username = (document.getElementById('jm-reg-u') || {}).value || '';
      var email = (document.getElementById('jm-reg-e') || {}).value || '';
      var password = (document.getElementById('jm-reg-p') || {}).value || '';
      var honeypot = (document.getElementById('jm-hp') || {}).value || '';
      var termsEl = document.getElementById('jm-reg-terms');
      var privEl = document.getElementById('jm-reg-priv');
      var btn = document.getElementById('jm-reg-submit');

      if (!username || !email || !password) { showAlert('Proszę wypełnić wszystkie pola'); return; }
      if (termsEl && !termsEl.checked) { showAlert('Musisz zaakceptować Regulamin, aby się zarejestrować'); return; }
      if (privEl && !privEl.checked) { showAlert('Musisz zaakceptować Politykę prywatności, aby się zarejestrować'); return; }
      if (btn) { btn.disabled = true; btn.textContent = 'Rejestracja...'; }

      $.ajax({
        url: CFG.ajax, type: 'POST',
        data: { action: 'jg_map_register', honeypot: honeypot, username: username, email: email, password: password },
        success: function(response) {
          if (response.success) {
            open(modalEdit,
              '<div style="background:#15803d;color:#fff;padding:20px 24px;border-radius:8px 8px 0 0"><h2 style="margin:0;font-size:20px;font-weight:700">Rejestracja zakończona!</h2></div>' +
              '<div style="padding:24px;text-align:center"><div style="font-size:48px;margin:16px 0">📧</div>' +
              '<p style="font-size:16px;line-height:1.6;color:#333">Na <strong style="color:#8d2324">' + esc(email) + '</strong> wysłaliśmy link aktywacyjny.</p>' +
              '<p style="font-size:13px;color:#666;margin-top:8px">Sprawdź skrzynkę (w tym folder SPAM). Link ważny 48h.</p></div>' +
              '<div style="padding:16px 24px;background:#f9f9f9;border-top:1px solid #e5e5e5;border-radius:0 0 8px 8px;text-align:center">' +
              '<button onclick="document.getElementById(\'jg-map-modal-edit\').style.display=\'none\'" style="padding:10px 24px;background:#15803d;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;font-size:14px">OK, rozumiem</button></div>'
            );
          } else {
            if (btn) { btn.disabled = false; btn.textContent = 'Zarejestruj się (bezpłatnie)'; }
            if (response.data && typeof response.data === 'object' && response.data.type === 'rate_limit') {
              showRateLimitModal(response.data.message, response.data.seconds_remaining, response.data.action);
            } else if (response.data && typeof response.data === 'object' && response.data.type === 'attempts_warning') {
              showAttemptsWarningModal(response.data.message, response.data.attempts_remaining, response.data.attempts_used, response.data.is_last_attempt, response.data.warning, response.data.action);
            } else {
              showAlert(response.data && response.data.message ? response.data.message : (response.data || 'Błąd rejestracji'));
            }
          }
        },
        error: function() {
          if (btn) { btn.disabled = false; btn.textContent = 'Zarejestruj się (bezpłatnie)'; }
          showAlert('Wystąpił błąd podczas rejestracji');
        }
      });
    }

    if ((el = document.getElementById('jm-reg-submit'))) el.addEventListener('click', submitRegistration);
    var regForm = document.getElementById('jm-reg-form');
    if (regForm) regForm.addEventListener('keypress', function(e) { if (e.key === 'Enter') { e.preventDefault(); submitRegistration(); } });

    // ── Login submit ──
    function submitLogin() {
      var username = (document.getElementById('jm-log-u') || {}).value || '';
      var password = (document.getElementById('jm-log-p') || {}).value || '';
      var honeypot = (document.getElementById('jm-log-hp') || {}).value || '';
      var btn = document.getElementById('jm-log-submit');

      if (!username || !password) { showAlert('Proszę wypełnić wszystkie pola'); return; }
      if (btn) { btn.disabled = true; btn.textContent = 'Logowanie...'; }

      $.ajax({
        url: CFG.ajax, type: 'POST',
        data: { action: 'jg_map_login', honeypot: honeypot, username: username, password: password },
        success: function(response) {
          if (response.success) {
            closeModal(); location.reload();
          } else {
            if (btn) { btn.disabled = false; btn.textContent = 'Zaloguj się'; }
            if (response.data && typeof response.data === 'object' && response.data.type === 'pending_activation') {
              showPendingActivationModal(response.data.username, response.data.email);
            } else if (response.data && typeof response.data === 'object' && response.data.type === 'rate_limit') {
              showRateLimitModal(response.data.message, response.data.seconds_remaining, response.data.action);
            } else if (response.data && typeof response.data === 'object' && response.data.type === 'attempts_warning') {
              showAttemptsWarningModal(response.data.message, response.data.attempts_remaining, response.data.attempts_used, response.data.is_last_attempt, response.data.warning, response.data.action);
            } else {
              showAlert(response.data && response.data.message ? response.data.message : (response.data || 'Błąd logowania'));
            }
          }
        },
        error: function() {
          if (btn) { btn.disabled = false; btn.textContent = 'Zaloguj się'; }
          showAlert('Wystąpił błąd podczas logowania');
        }
      });
    }

    if ((el = document.getElementById('jm-log-submit'))) el.addEventListener('click', submitLogin);
    var logForm = document.getElementById('jm-log-form');
    if (logForm) logForm.addEventListener('keypress', function(e) { if (e.key === 'Enter') { e.preventDefault(); submitLogin(); } });

    // ── OAuth via popup window ──
    function openOAuthPopup(provider) {
      var state = Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
      sessionStorage.setItem('jg_oauth_state_' + provider, state);

      var callbackUrl = oauthBase + 'jg_' + provider + '_oauth_callback';
      var popupUrl = provider === 'google'
        ? 'https://accounts.google.com/o/oauth2/v2/auth?client_id=' + encodeURIComponent(googleClientId) +
          '&redirect_uri=' + encodeURIComponent(callbackUrl) +
          '&response_type=code&scope=' + encodeURIComponent('email profile') + '&access_type=online&prompt=select_account' +
          '&state=' + encodeURIComponent(state)
        : 'https://www.facebook.com/v18.0/dialog/oauth?client_id=' + encodeURIComponent(facebookAppId) +
          '&redirect_uri=' + encodeURIComponent(callbackUrl) + '&scope=email,public_profile&response_type=code' +
          '&state=' + encodeURIComponent(state);

      var popup = window.open(popupUrl, 'jg_oauth_' + provider, 'width=520,height=620,scrollbars=yes,resizable=yes');
      if (!popup) { showAlert('Przeglądarka zablokowała popup. Zezwól na wyskakujące okna dla tej strony i spróbuj ponownie.'); return; }

      var expectedOrigin = window.location.origin;
      var msgHandler = function(e) {
        if (e.origin !== expectedOrigin) return;
        if (!e.data || e.data.jg_provider !== provider) return;
        var storedState = sessionStorage.getItem('jg_oauth_state_' + provider);
        sessionStorage.removeItem('jg_oauth_state_' + provider);
        if (!storedState || e.data.state !== storedState) {
          window.removeEventListener('message', msgHandler);
          if (popup && !popup.closed) popup.close();
          showAlert('Błąd bezpieczeństwa autoryzacji. Spróbuj ponownie.');
          return;
        }
        window.removeEventListener('message', msgHandler);
        if (popup && !popup.closed) popup.close();
        if (e.data.type === 'jg_oauth_success') { closeModal(); location.reload(); }
        else { showAlert(e.data.message || ('Błąd logowania przez ' + (provider === 'google' ? 'Google' : 'Facebook'))); }
      };
      window.addEventListener('message', msgHandler);
    }

    ['jm-r-g', 'jm-l-g'].forEach(function(id) {
      if ((el = document.getElementById(id))) el.addEventListener('click', function() { openOAuthPopup('google'); });
    });
    ['jm-r-fb', 'jm-l-fb'].forEach(function(id) {
      if ((el = document.getElementById(id))) el.addEventListener('click', function() { openOAuthPopup('facebook'); });
    });
  }

  function openAuthModal(activeTab, infoMessage) {
    if (typeof activeTab === 'object') return openJoinModal(activeTab);
    return openJoinModal({ view: activeTab || 'register', message: infoMessage || null });
  }

  // Expose both auth modal functions globally
  window.openAuthModal = openAuthModal;
  window.openJoinModal = openJoinModal;

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

            var isOauthUser = !!response.data.is_oauth_user;
            document.getElementById('delete-profile-btn').addEventListener('click', function() {
              openDeleteProfileConfirmation(isOauthUser);
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
  function openDeleteProfileConfirmation(isOauthUser) {
    var modal = document.getElementById('jg-modal-alert');
    if (!modal) {
      if (isOauthUser) {
        if (confirm('Czy na pewno chcesz trwale usunąć swój profil?')) deleteProfileWithPassword('');
      } else {
        var password = prompt('Aby usunąć profil, podaj swoje hasło:');
        if (password) deleteProfileWithPassword(password);
      }
      return;
    }

    var contentEl = modal.querySelector('.jg-modal-message-content');
    var buttonsEl = modal.querySelector('.jg-modal-message-buttons');

    var passwordHtml = isOauthUser
      ? '<div style="margin-bottom:12px;color:#92400e;background:#fef3c7;border:1px solid #f59e0b;border-radius:6px;padding:10px;font-size:13px">Twoje konto jest powiązane z logowaniem społecznościowym — nie jest wymagane hasło.</div>'
      : '<div style="margin-bottom:12px;font-weight:600">Aby potwierdzić, podaj swoje hasło:</div>' +
        '<input type="password" id="jg-delete-password-input" style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:14px" placeholder="Twoje hasło">';

    contentEl.innerHTML =
      '<div style="margin-bottom:16px;font-weight:600;font-size:18px">Czy na pewno chcesz usunąć swój profil?</div>' +
      '<div style="margin-bottom:16px;color:#666">Ta operacja jest <strong style="color:#dc2626">nieodwracalna</strong>. Zostaną usunięte:</div>' +
      '<ul style="margin-bottom:16px;text-align:left;line-height:1.8">' +
      '<li>Wszystkie Twoje pinezki</li>' +
      '<li>Wszystkie przesłane przez Ciebie zdjęcia</li>' +
      '<li>Twój profil i wszystkie dane</li>' +
      '</ul>' + passwordHtml;

    buttonsEl.innerHTML = '<button class="jg-btn jg-btn--ghost" id="jg-confirm-no">Anuluj</button><button class="jg-btn jg-btn--danger" id="jg-confirm-yes">Usuń profil</button>';

    modal.style.display = 'flex';

    var passwordInput = document.getElementById('jg-delete-password-input');
    var yesBtn = document.getElementById('jg-confirm-yes');
    var noBtn = document.getElementById('jg-confirm-no');

    if (passwordInput) {
      setTimeout(function() { passwordInput.focus(); }, 100);
      passwordInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') yesBtn.click();
      });
    }

    yesBtn.onclick = function() {
      var password = isOauthUser ? '' : (passwordInput ? passwordInput.value : '');
      if (!isOauthUser && !password) {
        showAlert('Musisz podać hasło aby usunąć profil');
        return;
      }
      modal.style.display = 'none';
      deleteProfileWithPassword(password);
    };

    noBtn.onclick = function() { modal.style.display = 'none'; };
    modal.onclick = function(e) { if (e.target === modal) modal.style.display = 'none'; };
  }

  // Delete profile with password verification
  function deleteProfileWithPassword(password) {
    var CFG = window.JG_AUTH_CFG || {};

    $.ajax({
      url: CFG.ajax,
      type: 'POST',
      data: {
        action: 'jg_map_delete_profile',
        _ajax_nonce: CFG.nonce,
        password: password
      },
      success: function(response) {
        if (response.success) {
          showAlert('Twój profil został pomyślnie usunięty').then(function() {
            window.location.href = '/';
          });
        } else {
          var msg = response.data;
          if (msg && typeof msg === 'object') msg = msg.message || 'Wystąpił błąd podczas usuwania profilu';
          showAlert(msg || 'Wystąpił błąd podczas usuwania profilu');
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
