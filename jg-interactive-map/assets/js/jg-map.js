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

    // Sort reasons within each group alphabetically by label
    for (var g in grouped) {
      if (grouped.hasOwnProperty(g)) {
        grouped[g].sort(function(a, b) { return a.label.localeCompare(b.label, 'pl'); });
      }
    }

    // Sort category groups alphabetically by their label
    var sortedGroups = [];
    for (var catKey in categories) {
      if (categories.hasOwnProperty(catKey)) {
        sortedGroups.push({ key: catKey, label: categories[catKey] });
      }
    }
    sortedGroups.sort(function(a, b) { return a.label.localeCompare(b.label, 'pl'); });

    // Generate optgroups in sorted order
    for (var gi = 0; gi < sortedGroups.length; gi++) {
      var grp = sortedGroups[gi];
      if (grouped[grp.key]) {
        html += '<optgroup label="' + grp.label + '">';
        for (var i = 0; i < grouped[grp.key].length; i++) {
          var r = grouped[grp.key][i];
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

  // Helper function to get category emoji map from config (all types)
  function getCategoryEmojis() {
    var reasons = (window.JG_MAP_CFG && JG_MAP_CFG.reportReasons) || {};
    var placeCategories = (window.JG_MAP_CFG && JG_MAP_CFG.placeCategories) || {};
    var curiosityCategories = (window.JG_MAP_CFG && JG_MAP_CFG.curiosityCategories) || {};
    var emojis = {};

    // Report categories
    for (var key in reasons) {
      if (reasons.hasOwnProperty(key)) {
        emojis[key] = reasons[key].icon || '📌';
      }
    }

    // Place categories
    for (var key in placeCategories) {
      if (placeCategories.hasOwnProperty(key)) {
        emojis[key] = placeCategories[key].icon || '📍';
      }
    }

    // Curiosity categories
    for (var key in curiosityCategories) {
      if (curiosityCategories.hasOwnProperty(key)) {
        emojis[key] = curiosityCategories[key].icon || '💡';
      }
    }

    return emojis;
  }

  // Helper function to generate place category select options
  function generatePlaceCategoryOptions(selectedValue) {
    var categories = (window.JG_MAP_CFG && JG_MAP_CFG.placeCategories) || {};
    var html = '<option value="">-- Wybierz kategorię (opcjonalnie) --</option>';

    // Convert to array and sort alphabetically by label
    var sorted = [];
    for (var key in categories) {
      if (categories.hasOwnProperty(key)) {
        sorted.push({ key: key, label: categories[key].label, icon: categories[key].icon });
      }
    }
    sorted.sort(function(a, b) { return a.label.localeCompare(b.label, 'pl'); });

    for (var i = 0; i < sorted.length; i++) {
      var cat = sorted[i];
      var selected = (selectedValue === cat.key) ? ' selected' : '';
      html += '<option value="' + cat.key + '"' + selected + '>' + (cat.icon || '📍') + ' ' + cat.label + '</option>';
    }

    return html;
  }

  // Helper function to generate curiosity category select options
  function generateCuriosityCategoryOptions(selectedValue) {
    var categories = (window.JG_MAP_CFG && JG_MAP_CFG.curiosityCategories) || {};
    var html = '<option value="">-- Wybierz kategorię (opcjonalnie) --</option>';

    // Convert to array and sort alphabetically by label
    var sorted = [];
    for (var key in categories) {
      if (categories.hasOwnProperty(key)) {
        sorted.push({ key: key, label: categories[key].label, icon: categories[key].icon });
      }
    }
    sorted.sort(function(a, b) { return a.label.localeCompare(b.label, 'pl'); });

    for (var i = 0; i < sorted.length; i++) {
      var cat = sorted[i];
      var selected = (selectedValue === cat.key) ? ' selected' : '';
      html += '<option value="' + cat.key + '"' + selected + '>' + (cat.icon || '💡') + ' ' + cat.label + '</option>';
    }

    return html;
  }

  // Helper function to get category label by key (all types)
  function getCategoryLabel(key, type) {
    var reasons = (window.JG_MAP_CFG && JG_MAP_CFG.reportReasons) || {};
    var placeCategories = (window.JG_MAP_CFG && JG_MAP_CFG.placeCategories) || {};
    var curiosityCategories = (window.JG_MAP_CFG && JG_MAP_CFG.curiosityCategories) || {};

    if (type === 'miejsce' && placeCategories[key]) {
      return (placeCategories[key].icon || '📍') + ' ' + placeCategories[key].label;
    }
    if (type === 'ciekawostka' && curiosityCategories[key]) {
      return (curiosityCategories[key].icon || '💡') + ' ' + curiosityCategories[key].label;
    }
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

  /**
   * Escape HTML entities to prevent XSS
   */
  function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  /**
   * Show beautiful approval notification modal
   * This modal is displayed when a user's point or edit is approved by a moderator
   * @param {string} pointTitle - Title of the approved point
   * @param {string} pointType - Type of point (miejsce, ciekawostka, zgloszenie)
   * @param {number} pointId - ID of the point
   * @param {string} approvalType - Type of approval: 'point' or 'edit'
   */
  function showApprovalNotification(pointTitle, pointType, pointId, approvalType) {
    approvalType = approvalType || 'point';

    // Prevent showing duplicates - track shown notifications
    if (!window._jgApprovalNotifications) {
      window._jgApprovalNotifications = {};
    }
    var notificationKey = approvalType + '_' + pointId;
    if (window._jgApprovalNotifications[notificationKey]) {
      return; // Already shown
    }
    window._jgApprovalNotifications[notificationKey] = true;

    // Create modal container if it doesn't exist
    var existingModal = document.getElementById('jg-approval-modal');
    if (existingModal) {
      existingModal.remove();
    }

    // Get type label and icon
    var typeLabels = {
      'miejsce': { label: 'Miejsce', icon: '📍', color: '#10b981' },
      'ciekawostka': { label: 'Ciekawostka', icon: '💡', color: '#3b82f6' },
      'zgloszenie': { label: 'Zgłoszenie', icon: '⚠️', color: '#f59e0b' }
    };
    var typeInfo = typeLabels[pointType] || typeLabels['miejsce'];

    // Set messages based on approval type
    var titleText = approvalType === 'edit' ? 'Edycja zatwierdzona!' : 'Gratulacje!';
    var subtitleText = approvalType === 'edit'
      ? 'Twoja edycja została zaakceptowana'
      : 'Twoje miejsce zostało zaakceptowane';
    var infoText = approvalType === 'edit'
      ? 'Wprowadzone przez Ciebie zmiany są teraz widoczne na mapie.'
      : 'Twój punkt jest teraz widoczny na mapie dla wszystkich użytkowników.';

    // Create modal HTML with animations
    var modalHtml = '<div id="jg-approval-modal" class="jg-approval-modal-bg">' +
      '<div class="jg-approval-modal">' +
        '<div class="jg-approval-modal-icon">' +
          '<span class="jg-approval-checkmark">✓</span>' +
        '</div>' +
        '<div class="jg-approval-modal-content">' +
          '<h2 class="jg-approval-modal-title">' + titleText + '</h2>' +
          '<p class="jg-approval-modal-subtitle">' + subtitleText + '</p>' +
          '<div class="jg-approval-modal-point">' +
            '<span class="jg-approval-modal-type" style="background:' + typeInfo.color + '">' +
              typeInfo.icon + ' ' + typeInfo.label +
            '</span>' +
            '<span class="jg-approval-modal-name">' + escapeHtml(pointTitle) + '</span>' +
          '</div>' +
          '<p class="jg-approval-modal-info">' + infoText + '</p>' +
        '</div>' +
        '<div class="jg-approval-modal-actions">' +
          '<button class="jg-btn jg-btn--ghost" id="jg-approval-close">Zamknij</button>' +
          '<button class="jg-btn" id="jg-approval-view">Zobacz na mapie</button>' +
        '</div>' +
        '<div class="jg-approval-confetti"></div>' +
      '</div>' +
    '</div>';

    // Insert modal into DOM
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    var modal = document.getElementById('jg-approval-modal');

    // Animate confetti
    setTimeout(function() {
      createConfettiAnimation(modal.querySelector('.jg-approval-confetti'));
    }, 100);

    // Event handlers
    var closeBtn = document.getElementById('jg-approval-close');
    var viewBtn = document.getElementById('jg-approval-view');

    closeBtn.onclick = function() {
      modal.classList.add('jg-approval-modal-closing');
      setTimeout(function() {
        modal.remove();
      }, 300);
    };

    viewBtn.onclick = function() {
      modal.classList.add('jg-approval-modal-closing');
      setTimeout(function() {
        modal.remove();
        // Navigate to the point on map by reloading with URL parameter
        if (pointId) {
          var currentUrl = new URL(window.location.href);
          currentUrl.searchParams.set('jg_view_point', pointId);
          window.location.href = currentUrl.toString();
        }
      }, 300);
    };

    // Close on background click
    modal.onclick = function(e) {
      if (e.target === modal) {
        closeBtn.onclick();
      }
    };

    // Auto-close after 15 seconds
    setTimeout(function() {
      if (document.getElementById('jg-approval-modal')) {
        closeBtn.onclick();
      }
    }, 15000);
  }

  /**
   * Create simple confetti animation
   */
  function createConfettiAnimation(container) {
    if (!container) return;

    var colors = ['#8d2324', '#10b981', '#3b82f6', '#f59e0b', '#ec4899', '#8b5cf6'];
    var confettiCount = 50;

    for (var i = 0; i < confettiCount; i++) {
      var confetti = document.createElement('div');
      confetti.className = 'jg-confetti-piece';
      confetti.style.cssText =
        'position:absolute;' +
        'width:' + (Math.random() * 8 + 4) + 'px;' +
        'height:' + (Math.random() * 8 + 4) + 'px;' +
        'background:' + colors[Math.floor(Math.random() * colors.length)] + ';' +
        'left:' + (Math.random() * 100) + '%;' +
        'top:-10px;' +
        'border-radius:' + (Math.random() > 0.5 ? '50%' : '2px') + ';' +
        'animation:jgConfettiFall ' + (Math.random() * 2 + 2) + 's ease-out forwards;' +
        'animation-delay:' + (Math.random() * 0.5) + 's;' +
        'transform:rotate(' + (Math.random() * 360) + 'deg);';
      container.appendChild(confetti);
    }
  }

  // =========================================================
  // CONFETTI UTILITIES
  // =========================================================

  // Prestige tier → dominant colors for confetti
  var _prestigeConfettiColors = {
    'prestige-bronze':  ['#cd7f32', '#a0522d', '#e8a86c', '#f5deb3', '#8b4513'],
    'prestige-silver':  ['#c0c0c0', '#d4d4d4', '#a8a8a8', '#e8e8e8', '#888888'],
    'prestige-gold':    ['#fbbf24', '#f59e0b', '#fde68a', '#fef3c7', '#d97706'],
    'prestige-emerald': ['#34d399', '#10b981', '#6ee7b7', '#d1fae5', '#059669'],
    'prestige-purple':  ['#a78bfa', '#8b5cf6', '#c4b5fd', '#ede9fe', '#7c3aed'],
    'prestige-diamond': ['#67e8f9', '#22d3ee', '#a5f3fc', '#cffafe', '#0891b2'],
    'prestige-ruby':    ['#fb7185', '#e11d48', '#fda4af', '#ffe4e6', '#be123c'],
    'prestige-legend':  ['#fbbf24', '#fb7185', '#a78bfa', '#67e8f9', '#34d399', '#f0abfc']
  };

  /**
   * Low-level: spawn one confetti particle using CSS @keyframes jgConfettiBurst.
   * Uses CSS custom properties --jg-dx / --jg-dy / --jg-rot so the keyframe
   * always fires reliably — no requestAnimationFrame timing tricks required.
   *
   * @param {number} cx       screen X of origin (fixed coords)
   * @param {number} cy       screen Y of origin (fixed coords)
   * @param {number} dx       final horizontal displacement in px
   * @param {number} dy       final vertical displacement in px (positive = down)
   * @param {number} rot      final rotation angle in degrees
   * @param {number} size     particle width/height in px
   * @param {string} color    CSS color string
   * @param {number} duration animation duration in ms
   * @param {number} delay    animation delay in ms
   */
  function _spawnConfettiParticle(cx, cy, dx, dy, rot, size, color, duration, delay) {
    var p = document.createElement('div');
    p.style.cssText =
      '--jg-dx:' + dx.toFixed(1) + 'px;' +
      '--jg-dy:' + dy.toFixed(1) + 'px;' +
      '--jg-rot:' + rot.toFixed(0) + 'deg;' +
      'position:fixed;pointer-events:none;z-index:99999;' +
      'left:' + cx.toFixed(1) + 'px;top:' + cy.toFixed(1) + 'px;' +
      'width:' + size.toFixed(1) + 'px;height:' + size.toFixed(1) + 'px;' +
      'background:' + color + ';' +
      'border-radius:' + (Math.random() > 0.4 ? '50%' : '2px') + ';' +
      'animation:jgConfettiBurst ' + duration + 'ms ease-out ' + (delay || 0) + 'ms both;';
    document.body.appendChild(p);
    setTimeout(function() { if (p.parentNode) p.parentNode.removeChild(p); }, duration + (delay || 0) + 150);
  }

  /**
   * Shoot burst confetti from an anchor DOM element.
   * Uses a downward arc so particles stay in the visible viewport even
   * when the badge is at the very top of the screen.
   *
   * @param {Element} anchorEl  - DOM element to burst from
   * @param {string}  tier      - prestige tier key (e.g. 'prestige-gold')
   * @param {number}  count     - number of particles (default 36)
   */
  function shootPrestigeConfetti(anchorEl, tier, count) {
    if (!anchorEl) return;
    count = count || 36;
    var colors = (_prestigeConfettiColors[tier] || _prestigeConfettiColors['prestige-bronze'])
      .concat(['#ffffff', '#f0f0f0']);

    var rect = anchorEl.getBoundingClientRect();
    var cx = rect.left + rect.width  / 2;
    var cy = rect.top  + rect.height / 2;

    for (var i = 0; i < count; i++) {
      // Lower semicircle only (9° → 171°): all particles go downward/sideways,
      // never upward — safe for an element near the top of the viewport.
      var angle    = Math.PI * (0.05 + Math.random() * 0.9);
      var speed    = Math.random() * 75 + 40;
      var dx       = Math.cos(angle) * speed;
      var dy       = Math.sin(angle) * speed; // always ≥ 0
      var rot      = Math.random() * 720;
      var duration = Math.random() * 500 + 650;
      var delay    = Math.random() * 100;
      var color    = colors[Math.floor(Math.random() * colors.length)];
      _spawnConfettiParticle(cx, cy, dx, dy, rot, Math.random() * 7 + 4, color, duration, delay);
    }
  }

  /**
   * Shoot confetti from a map lat/lng position (converted to screen coords).
   * Full 360° burst with gravity offset so even upward particles arc downward.
   *
   * @param {number} lat
   * @param {number} lng
   * @param {Array}  colors
   * @param {number} count
   */
  function shootMapMarkerConfetti(lat, lng, colors, count) {
    var m = window.jgMap;
    if (!m) return;
    count  = count  || 40;
    colors = colors || ['#10b981', '#fbbf24', '#3b82f6', '#ec4899', '#8b5cf6', '#ffffff'];

    try {
      var containerPt  = m.latLngToContainerPoint([lat, lng]);
      var mapContainer = m.getContainer();
      var mapRect      = mapContainer.getBoundingClientRect();
      var cx = mapRect.left + containerPt.x;
      var cy = mapRect.top  + containerPt.y;

      for (var i = 0; i < count; i++) {
        var angle    = Math.random() * Math.PI * 2;
        var speed    = Math.random() * 70 + 40;
        var dx       = Math.cos(angle) * speed;
        var dy       = Math.sin(angle) * speed + 55; // gravity pulls all downward
        var rot      = Math.random() * 720;
        var duration = Math.random() * 600 + 700;
        var delay    = Math.random() * 100;
        var color    = colors[Math.floor(Math.random() * colors.length)];
        _spawnConfettiParticle(cx, cy, dx, dy, rot, Math.random() * 8 + 4, color, duration, delay);
      }
    } catch (e) { /* map not ready */ }
  }

  /**
   * Shoot confetti burst from a button element, mostly upward with gravity.
   *
   * @param {Element} btn    - DOM button element
   * @param {Array}   colors - color palette (dominant color first)
   * @param {number}  count
   */
  function shootButtonConfetti(btn, colors, count) {
    if (!btn) return;
    count  = count  || 28;
    colors = colors || ['#10b981', '#ffffff'];

    var rect = btn.getBoundingClientRect();
    var cx   = rect.left + rect.width  / 2;
    var cy   = rect.top  + rect.height / 2;

    for (var i = 0; i < count; i++) {
      // Mostly upward fan (-144° to -36°), gravity brings them back down
      var angle    = -Math.PI / 2 + (Math.random() - 0.5) * Math.PI * 1.2;
      var speed    = Math.random() * 55 + 30;
      var dx       = Math.cos(angle) * speed;
      var dy       = Math.sin(angle) * speed + 35; // gravity offset
      var rot      = Math.random() * 720;
      var duration = Math.random() * 400 + 600;
      var delay    = Math.random() * 60;
      var color    = colors[Math.floor(Math.random() * colors.length)];
      _spawnConfettiParticle(cx, cy, dx, dy, rot, Math.random() * 6 + 3, color, duration, delay);
    }
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
      var modalRanking = document.getElementById('jg-map-modal-ranking');
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

      // Ranking button handler
      var rankingBtn = document.getElementById('jg-ranking-btn');
      if (rankingBtn && !rankingBtn.jgHandlerAttached) {
        rankingBtn.addEventListener('click', function() {
          var modalRanking = document.getElementById('jg-map-modal-ranking');
          if (!modalRanking) return;

          // Show loading state
          var loadingHtml = '<header class="jg-ranking-header">' +
            '<div class="jg-ranking-header-inner">' +
            '<h3 class="jg-ranking-title">🏆 Ranking użytkowników</h3>' +
            '</div>' +
            '<button class="jg-close" id="ranking-modal-close" style="color:#fff;opacity:0.9">&times;</button>' +
            '</header>' +
            '<div style="padding:40px;text-align:center;color:#6b7280">Ładowanie rankingu...</div>';
          open(modalRanking, loadingHtml);
          qs('#ranking-modal-close', modalRanking).onclick = function() { close(modalRanking); };

          api('jg_get_ranking', {}).then(function(ranking) {
            if (!ranking || !ranking.length) {
              var emptyHtml = '<header class="jg-ranking-header">' +
                '<div class="jg-ranking-header-inner">' +
                '<h3 class="jg-ranking-title">🏆 Ranking użytkowników</h3>' +
                '</div>' +
                '<button class="jg-close" id="ranking-modal-close" style="color:#fff;opacity:0.9">&times;</button>' +
                '</header>' +
                '<div style="padding:40px;text-align:center;color:#6b7280">Brak danych rankingu.</div>';
              open(modalRanking, emptyHtml);
              qs('#ranking-modal-close', modalRanking).onclick = function() { close(modalRanking); };
              return;
            }

            var rowsHtml = '';
            for (var i = 0; i < ranking.length; i++) {
              var r = ranking[i];
              var pos = i + 1;
              var rowClass = 'jg-ranking-row';
              if (pos === 1) rowClass += ' jg-ranking-gold';
              else if (pos === 2) rowClass += ' jg-ranking-silver';
              else if (pos === 3) rowClass += ' jg-ranking-bronze';

              var starHtml = pos === 1 ? '<span class="jg-ranking-star">⭐</span> ' : '';

              rowsHtml += '<div class="' + rowClass + '" data-user-id="' + r.user_id + '">' +
                '<div class="jg-ranking-pos">' + pos + '</div>' +
                '<div class="jg-ranking-info">' +
                '<div class="jg-ranking-name">' + starHtml + '<a href="#" class="jg-ranking-user-link" data-user-id="' + r.user_id + '">' + esc(r.display_name) + '</a></div>' +
                '<div class="jg-ranking-meta">' +
                '<span class="jg-ranking-level">Poz. ' + r.level + '</span>' +
                '<span class="jg-ranking-places">📍 ' + r.places_count + ' miejsc</span>' +
                '</div>' +
                '</div>' +
                '<div class="jg-ranking-count">' + r.places_count + '</div>' +
                '</div>';
            }

            // Placeholder rows for empty positions
            for (var k = ranking.length; k < 10; k++) {
              var emptyPos = k + 1;
              rowsHtml += '<div class="jg-ranking-row jg-ranking-empty">' +
                '<div class="jg-ranking-pos">' + emptyPos + '</div>' +
                '<div class="jg-ranking-info">' +
                '<div class="jg-ranking-empty-bar"></div>' +
                '<div class="jg-ranking-empty-bar jg-ranking-empty-bar--short"></div>' +
                '</div>' +
                '<div class="jg-ranking-count jg-ranking-empty-count"></div>' +
                '</div>';
            }

            var html = '<header class="jg-ranking-header">' +
              '<div class="jg-ranking-header-inner">' +
              '<div class="jg-ranking-trophy">🏆</div>' +
              '<div>' +
              '<h3 class="jg-ranking-title">Ranking użytkowników</h3>' +
              '<p class="jg-ranking-subtitle">Top 10 najbardziej aktywnych użytkowników</p>' +
              '</div>' +
              '</div>' +
              '<button class="jg-close" id="ranking-modal-close" style="color:#fff;opacity:0.9">&times;</button>' +
              '</header>' +
              '<div class="jg-ranking-body">' +
              '<div class="jg-ranking-list">' + rowsHtml + '</div>' +
              '</div>';

            open(modalRanking, html);

            qs('#ranking-modal-close', modalRanking).onclick = function() { close(modalRanking); };

            // Click handler for user links
            var userLinks = modalRanking.querySelectorAll('.jg-ranking-user-link');
            for (var j = 0; j < userLinks.length; j++) {
              (function(link) {
                link.addEventListener('click', function(e) {
                  e.preventDefault();
                  close(modalRanking);
                  var uid = parseInt(link.getAttribute('data-user-id'));
                  if (uid) openUserModal(uid);
                });
              })(userLinks[j]);
            }
          }).catch(function() {
            showAlert('Błąd pobierania rankingu');
          });
        });
        rankingBtn.jgHandlerAttached = true;
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

      // Single auth button handler (replaces separate login/register buttons)
      var authBtn = document.getElementById('jg-auth-btn');
      if (authBtn && !authBtn.jgHandlerAttached) {
        authBtn.addEventListener('click', function() {
          if (typeof window.openAuthModal === 'function') {
            window.openAuthModal('register');
          } else {
            openLoginModal();
          }
        });
        authBtn.jgHandlerAttached = true;
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

      // Auth modal with tabs (login/register) shown when non-logged user clicks "Zapytaj o ofertę"
      function showPromoAuthModal() {
        var modalEdit = document.getElementById('jg-map-modal-edit');

        var tabStyle = 'padding:10px 20px;border:none;cursor:pointer;font-size:14px;font-weight:600;border-radius:6px 6px 0 0;transition:all 0.2s;';
        var activeTabStyle = 'background:#fff;color:#8d2324;';
        var inactiveTabStyle = 'background:transparent;color:rgba(255,255,255,0.7);';

        var html = '<div class="jg-modal-header" style="background:#8d2324;color:#fff;padding:16px 24px 0;border-radius:8px 8px 0 0">' +
          '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">' +
            '<h2 style="margin:0;font-size:20px;font-weight:600">Konto wymagane</h2>' +
            '<button id="promo-auth-close" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;padding:0 4px;line-height:1">&times;</button>' +
          '</div>' +
          '<div style="display:flex;gap:4px">' +
            '<button id="promo-tab-register" style="' + tabStyle + activeTabStyle + '">Rejestracja</button>' +
            '<button id="promo-tab-login" style="' + tabStyle + inactiveTabStyle + '">Logowanie</button>' +
          '</div>' +
        '</div>' +
        // Info banner
        '<div style="background:#fef3c7;border-bottom:1px solid #f59e0b;padding:12px 24px;display:flex;align-items:center;gap:10px">' +
          '<span style="font-size:18px;flex-shrink:0">&#9432;</span>' +
          '<p style="margin:0;font-size:13px;color:#92400e;line-height:1.4">Aby wysłać zapytanie o ofertę promocji, musisz posiadać konto w naszym portalu. Zarejestruj się lub zaloguj, aby kontynuować.</p>' +
        '</div>' +
        // Register form (visible by default)
        '<div id="promo-register-panel" class="jg-modal-body" style="padding:24px">' +
          '<form id="promo-register-form">' +
          '<div style="position:absolute;left:-9999px;top:-9999px">' +
            '<label for="promo-register-website">Website</label>' +
            '<input type="text" id="promo-register-website" name="website" tabindex="-1" autocomplete="off">' +
          '</div>' +
          '<div class="jg-form-group" style="margin-bottom:20px">' +
            '<label style="display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px">Nazwa użytkownika</label>' +
            '<input type="text" id="promo-register-username" class="jg-input" required style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:14px;transition:border-color 0.2s" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#ddd\'">' +
          '</div>' +
          '<div class="jg-form-group" style="margin-bottom:20px">' +
            '<label style="display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px">Adres email</label>' +
            '<input type="email" id="promo-register-email" class="jg-input" required style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:14px;transition:border-color 0.2s" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#ddd\'">' +
          '</div>' +
          '<div class="jg-form-group" style="margin-bottom:20px">' +
            '<label style="display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px">Hasło</label>' +
            '<input type="password" id="promo-register-password" class="jg-input" required style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:14px;transition:border-color 0.2s" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#ddd\'">' +
          '</div>' +
          '<div style="font-size:12px;color:#666;margin-top:8px">Na podany adres email zostanie wysłany link aktywacyjny</div>' +
          '</form>' +
        '</div>' +
        // Login form (hidden by default)
        '<div id="promo-login-panel" class="jg-modal-body" style="padding:24px;display:none">' +
          '<form id="promo-login-form">' +
          '<div style="position:absolute;left:-9999px;top:-9999px">' +
            '<label for="promo-login-website">Website</label>' +
            '<input type="text" id="promo-login-website" name="website" tabindex="-1" autocomplete="off">' +
          '</div>' +
          '<div class="jg-form-group" style="margin-bottom:20px">' +
            '<label style="display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px">Nazwa użytkownika lub email</label>' +
            '<input type="text" id="promo-login-username" class="jg-input" required style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:14px;transition:border-color 0.2s" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#ddd\'">' +
          '</div>' +
          '<div class="jg-form-group" style="margin-bottom:20px">' +
            '<label style="display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px">Hasło</label>' +
            '<input type="password" id="promo-login-password" class="jg-input" required style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:14px;transition:border-color 0.2s" onfocus="this.style.borderColor=\'#8d2324\'" onblur="this.style.borderColor=\'#ddd\'">' +
          '</div>' +
          '</form>' +
        '</div>' +
        // Footer with action buttons
        '<div id="promo-footer-register" class="jg-modal-footer" style="padding:16px 24px;background:#f9f9f9;border-top:1px solid #e5e5e5;display:flex;gap:12px;justify-content:flex-end;border-radius:0 0 8px 8px">' +
          '<button class="jg-btn jg-btn--secondary" id="promo-cancel-reg" style="padding:10px 20px;background:#fff;color:#333;border:2px solid #ddd;border-radius:6px;font-weight:600;cursor:pointer;transition:all 0.2s" onmouseover="this.style.background=\'#f5f5f5\'" onmouseout="this.style.background=\'#fff\'">Anuluj</button>' +
          '<button class="jg-btn jg-btn--primary" id="promo-submit-register" style="padding:10px 24px;background:#8d2324;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;transition:all 0.2s" onmouseover="this.style.background=\'#a02829\'" onmouseout="this.style.background=\'#8d2324\'">Zarejestruj się</button>' +
        '</div>' +
        '<div id="promo-footer-login" class="jg-modal-footer" style="padding:16px 24px;background:#f9f9f9;border-top:1px solid #e5e5e5;display:none;gap:12px;justify-content:flex-end;border-radius:0 0 8px 8px">' +
          '<button class="jg-btn jg-btn--secondary" id="promo-cancel-login" style="padding:10px 20px;background:#fff;color:#333;border:2px solid #ddd;border-radius:6px;font-weight:600;cursor:pointer;transition:all 0.2s" onmouseover="this.style.background=\'#f5f5f5\'" onmouseout="this.style.background=\'#fff\'">Anuluj</button>' +
          '<button class="jg-btn jg-btn--primary" id="promo-submit-login" style="padding:10px 24px;background:#8d2324;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;transition:all 0.2s" onmouseover="this.style.background=\'#a02829\'" onmouseout="this.style.background=\'#8d2324\'">Zaloguj się</button>' +
        '</div>';

        open(modalEdit, html);

        var tabRegister = document.getElementById('promo-tab-register');
        var tabLogin = document.getElementById('promo-tab-login');
        var panelRegister = document.getElementById('promo-register-panel');
        var panelLogin = document.getElementById('promo-login-panel');
        var footerRegister = document.getElementById('promo-footer-register');
        var footerLogin = document.getElementById('promo-footer-login');

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

        // Close buttons
        var closeModal = function() { modalEdit.style.display = 'none'; };
        document.getElementById('promo-auth-close').addEventListener('click', closeModal);
        document.getElementById('promo-cancel-reg').addEventListener('click', closeModal);
        document.getElementById('promo-cancel-login').addEventListener('click', closeModal);

        // Register submission
        function submitPromoRegister() {
          var username = document.getElementById('promo-register-username').value;
          var email = document.getElementById('promo-register-email').value;
          var password = document.getElementById('promo-register-password').value;
          var honeypot = document.getElementById('promo-register-website').value;

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
                showAlert(response.data || 'Błąd rejestracji');
              }
            },
            error: function() {
              showAlert('Wystąpił błąd podczas rejestracji');
            }
          });
        }

        document.getElementById('promo-submit-register').addEventListener('click', submitPromoRegister);
        document.getElementById('promo-register-form').addEventListener('keypress', function(e) {
          if (e.key === 'Enter') { e.preventDefault(); submitPromoRegister(); }
        });

        // Login submission
        function submitPromoLogin() {
          var username = document.getElementById('promo-login-username').value;
          var password = document.getElementById('promo-login-password').value;
          var honeypot = document.getElementById('promo-login-website').value;

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
                closeModal();
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

        document.getElementById('promo-submit-login').addEventListener('click', submitPromoLogin);
        document.getElementById('promo-login-form').addEventListener('keypress', function(e) {
          if (e.key === 'Enter') { e.preventDefault(); submitPromoLogin(); }
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

      // ===== RICH TEXT EDITOR =====
      // Build the toolbar + contenteditable HTML for a rich editor
      function buildRichEditorHtml(id, maxLength, initialContent, rows) {
        var minH = (rows || 4) * 24;
        return '<div class="jg-rte-wrap" id="' + id + '-wrap">' +
          '<div class="jg-rte-toolbar" id="' + id + '-toolbar">' +
            '<button type="button" data-cmd="bold" title="Pogrubienie" class="jg-rte-btn"><b>B</b></button>' +
            '<button type="button" data-cmd="italic" title="Kursywa" class="jg-rte-btn"><i>I</i></button>' +
            '<button type="button" data-cmd="underline" title="Podkreślenie" class="jg-rte-btn"><u>U</u></button>' +
            '<span class="jg-rte-sep"></span>' +
            '<button type="button" data-cmd="insertUnorderedList" title="Lista punktowana" class="jg-rte-btn">&#8226; Lista</button>' +
            '<button type="button" data-cmd="insertOrderedList" title="Lista numerowana" class="jg-rte-btn">1. Lista</button>' +
            '<span class="jg-rte-sep"></span>' +
            '<button type="button" data-cmd="link" title="Wstaw link" class="jg-rte-btn">&#128279; Link</button>' +
            '<button type="button" data-cmd="pinLink" title="Link do pineski" class="jg-rte-btn">&#128205; Pineska</button>' +
          '</div>' +
          '<div class="jg-rte-editor" id="' + id + '-editor" contenteditable="true" style="min-height:' + minH + 'px" data-placeholder="Opisz miejsce..."></div>' +
          '<input type="hidden" name="content" id="' + id + '-hidden">' +
          '<div class="jg-rte-counter" id="' + id + '-counter">0 / ' + maxLength + ' znaków</div>' +
          // Link insertion dialog (hidden by default)
          '<div class="jg-rte-link-dialog" id="' + id + '-link-dialog" style="display:none">' +
            '<div class="jg-rte-link-dialog-header">' +
              '<strong id="' + id + '-link-dialog-title">Wstaw link</strong>' +
              '<button type="button" class="jg-rte-link-close" id="' + id + '-link-close">&times;</button>' +
            '</div>' +
            '<label>Tekst linku<input type="text" id="' + id + '-link-text" placeholder="Tekst do wyświetlenia" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></label>' +
            '<label style="margin-top:8px;display:block">Adres URL<input type="text" id="' + id + '-link-url" placeholder="https://..." style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></label>' +
            '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:10px">' +
              '<button type="button" class="jg-btn jg-btn--ghost jg-btn--sm" id="' + id + '-link-cancel">Anuluj</button>' +
              '<button type="button" class="jg-btn jg-btn--sm" id="' + id + '-link-insert">Wstaw</button>' +
            '</div>' +
          '</div>' +
          // Pin link dialog (hidden by default)
          '<div class="jg-rte-link-dialog" id="' + id + '-pin-dialog" style="display:none">' +
            '<div class="jg-rte-link-dialog-header">' +
              '<strong>Link do pineski</strong>' +
              '<button type="button" class="jg-rte-link-close" id="' + id + '-pin-close">&times;</button>' +
            '</div>' +
            '<label>Szukaj pineski<input type="text" id="' + id + '-pin-search" placeholder="Zacznij wpisywać nazwę..." autocomplete="off" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></label>' +
            '<div class="jg-rte-pin-results" id="' + id + '-pin-results"></div>' +
            '<input type="hidden" id="' + id + '-pin-selected-id">' +
            '<input type="hidden" id="' + id + '-pin-selected-title">' +
            '<label style="margin-top:8px;display:block">Tekst linku (opcjonalnie)<input type="text" id="' + id + '-pin-link-text" placeholder="Domyślnie: nazwa pineski" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></label>' +
            '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:10px">' +
              '<button type="button" class="jg-btn jg-btn--ghost jg-btn--sm" id="' + id + '-pin-cancel">Anuluj</button>' +
              '<button type="button" class="jg-btn jg-btn--sm" id="' + id + '-pin-insert">Wstaw</button>' +
            '</div>' +
          '</div>' +
        '</div>';
      }

      // ===== TAG INPUT =====
      // Build tag input HTML
      function buildTagInputHtml(id) {
        return '<div class="jg-tags-wrap" id="' + id + '-wrap">' +
          '<div class="jg-tags-list" id="' + id + '-list"></div>' +
          '<div style="position:relative">' +
            '<input type="text" id="' + id + '-input" class="jg-tags-input" placeholder="Wpisz tag i naciśnij Enter (max 5)..." autocomplete="off" maxlength="30">' +
            '<div class="jg-tags-suggestions" id="' + id + '-suggestions" style="display:none"></div>' +
          '</div>' +
          '<input type="hidden" name="tags" id="' + id + '-hidden">' +
          '<div class="jg-tags-counter" id="' + id + '-counter">0 / 5 tagów</div>' +
        '</div>';
      }

      // Cached tags for autocomplete (with TTL)
      var cachedAllTags = null;
      var cachedAllTagsTime = 0;
      var TAGS_CACHE_TTL = 60000; // 60 seconds

      function fetchAllTags(callback) {
        if (cachedAllTags !== null && (Date.now() - cachedAllTagsTime) < TAGS_CACHE_TTL) {
          callback(cachedAllTags);
          return;
        }
        var fd = new FormData();
        fd.append('action', 'jg_get_tags');
        fetch(CFG.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function(r) { return r.json(); })
          .then(function(resp) {
            cachedAllTags = (resp.success && resp.data) ? resp.data : [];
            cachedAllTagsTime = Date.now();
            callback(cachedAllTags);
          })
          .catch(function() {
            cachedAllTags = [];
            cachedAllTagsTime = Date.now();
            callback(cachedAllTags);
          });
      }

      // Initialize tag input behaviors
      function initTagInput(id, parentEl) {
        var input = qs('#' + id + '-input', parentEl);
        var list = qs('#' + id + '-list', parentEl);
        var hidden = qs('#' + id + '-hidden', parentEl);
        var counter = qs('#' + id + '-counter', parentEl);
        var suggestions = qs('#' + id + '-suggestions', parentEl);
        if (!input || !list || !hidden) return null;

        var tags = [];

        function renderTags() {
          list.innerHTML = '';
          tags.forEach(function(tag, idx) {
            var el = document.createElement('span');
            el.className = 'jg-tag-item';
            el.innerHTML = '<span class="jg-tag-text">#' + esc(tag) + '</span><button type="button" class="jg-tag-remove" data-idx="' + idx + '">&times;</button>';
            list.appendChild(el);
          });
          hidden.value = tags.join(',');
          if (counter) counter.textContent = tags.length + ' / 5 tagów';
          if (tags.length >= 5) {
            input.style.display = 'none';
          } else {
            input.style.display = '';
          }
        }

        function addTag(val) {
          val = val.replace(/^#+/, '').trim();
          if (!val || tags.length >= 5) return false;
          // Check for duplicates (case-insensitive)
          var lower = val.toLowerCase();
          for (var i = 0; i < tags.length; i++) {
            if (tags[i].toLowerCase() === lower) return false;
          }
          if (val.length > 30) val = val.substring(0, 30);
          tags.push(val);
          renderTags();
          return true;
        }

        function removeTag(idx) {
          tags.splice(idx, 1);
          renderTags();
        }

        list.addEventListener('click', function(e) {
          var btn = e.target.closest('.jg-tag-remove');
          if (btn) {
            removeTag(parseInt(btn.getAttribute('data-idx'), 10));
          }
        });

        input.addEventListener('keydown', function(e) {
          var sugVisible = suggestions.style.display !== 'none';
          var items = suggestions.querySelectorAll('.jg-tags-suggestion-item');

          if (sugVisible && items.length > 0) {
            if (e.key === 'ArrowDown') {
              e.preventDefault();
              sugSelectedIdx = (sugSelectedIdx + 1) % items.length;
              updateSugHighlight();
              return;
            } else if (e.key === 'ArrowUp') {
              e.preventDefault();
              sugSelectedIdx = sugSelectedIdx <= 0 ? items.length - 1 : sugSelectedIdx - 1;
              updateSugHighlight();
              return;
            } else if (e.key === 'Enter' && sugSelectedIdx >= 0 && sugSelectedIdx < items.length) {
              e.preventDefault();
              var text = items[sugSelectedIdx].textContent.replace(/^#/, '');
              addTag(text);
              input.value = '';
              hideSuggestions();
              return;
            } else if (e.key === 'Escape') {
              e.preventDefault();
              hideSuggestions();
              return;
            }
          }

          if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            var val = input.value.trim();
            if (val) {
              addTag(val);
              input.value = '';
              hideSuggestions();
            }
          } else if (e.key === 'Backspace' && !input.value && tags.length > 0) {
            removeTag(tags.length - 1);
          }
        });

        // Autocomplete suggestions
        var sugTimeout = null;
        var sugSelectedIdx = -1;

        function updateSugHighlight() {
          var items = suggestions.querySelectorAll('.jg-tags-suggestion-item');
          for (var i = 0; i < items.length; i++) {
            if (i === sugSelectedIdx) {
              items[i].classList.add('active');
              items[i].scrollIntoView({ block: 'nearest' });
            } else {
              items[i].classList.remove('active');
            }
          }
        }

        function showSuggestions(query) {
          fetchAllTags(function(allTags) {
            if (!query) { hideSuggestions(); return; }
            var q = query.toLowerCase().replace(/^#+/, '');
            if (!q) { hideSuggestions(); return; }
            var matches = allTags.filter(function(t) {
              var tLower = t.toLowerCase();
              // Don't suggest already-added tags
              for (var i = 0; i < tags.length; i++) {
                if (tags[i].toLowerCase() === tLower) return false;
              }
              return tLower.indexOf(q) !== -1;
            }).slice(0, 8);

            if (matches.length === 0) { hideSuggestions(); return; }

            suggestions.innerHTML = '';
            matches.forEach(function(m) {
              var opt = document.createElement('div');
              opt.className = 'jg-tags-suggestion-item';
              opt.textContent = '#' + m;
              opt.addEventListener('mousedown', function(e) {
                e.preventDefault();
                addTag(m);
                input.value = '';
                hideSuggestions();
                input.focus();
              });
              suggestions.appendChild(opt);
            });
            sugSelectedIdx = -1;
            suggestions.style.display = 'block';
          });
        }

        function hideSuggestions() {
          sugSelectedIdx = -1;
          suggestions.style.display = 'none';
        }

        input.addEventListener('input', function() {
          clearTimeout(sugTimeout);
          sugTimeout = setTimeout(function() {
            showSuggestions(input.value);
          }, 200);
        });

        input.addEventListener('blur', function() {
          setTimeout(hideSuggestions, 200);
        });

        return {
          getTags: function() { return tags.slice(); },
          setTags: function(arr) {
            tags = [];
            if (Array.isArray(arr)) {
              arr.forEach(function(t) { addTag(t); });
            }
            renderTags();
          },
          syncHidden: function() {
            hidden.value = tags.join(',');
          }
        };
      }

      // Initialize the rich editor behaviors after it's inserted into the DOM
      function initRichEditor(id, maxLength, parentEl) {
        var editor = qs('#' + id + '-editor', parentEl);
        var hidden = qs('#' + id + '-hidden', parentEl);
        var counter = qs('#' + id + '-counter', parentEl);
        var toolbar = qs('#' + id + '-toolbar', parentEl);
        if (!editor || !hidden) return null;

        var savedRange = null;

        function saveSelection() {
          var sel = window.getSelection();
          if (sel.rangeCount > 0) {
            var range = sel.getRangeAt(0);
            if (editor.contains(range.commonAncestorContainer)) {
              savedRange = range.cloneRange();
            }
          }
        }

        function restoreSelection() {
          if (savedRange) {
            var sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(savedRange);
          }
        }

        // Sync hidden input
        function syncContent() {
          var html = editor.innerHTML;
          // Clean up empty editor
          if (html === '<br>' || html === '<div><br></div>') html = '';
          hidden.value = html;
        }

        // Character counter (text only, no HTML)
        function updateCounter() {
          var textLen = (editor.textContent || editor.innerText || '').length;
          counter.textContent = textLen + ' / ' + maxLength + ' znaków';
          if (textLen > maxLength * 0.9) {
            counter.style.color = '#d97706';
          } else {
            counter.style.color = '#666';
          }
        }

        // Enforce max length on text content
        editor.addEventListener('input', function() {
          var textLen = (editor.textContent || editor.innerText || '').length;
          if (textLen > maxLength) {
            // Trim excess from the end
            document.execCommand('undo');
          }
          syncContent();
          updateCounter();
        });

        editor.addEventListener('paste', function(e) {
          e.preventDefault();
          var text = (e.clipboardData || window.clipboardData).getData('text/plain');
          var currentLen = (editor.textContent || editor.innerText || '').length;
          var sel = window.getSelection();
          var selectedLen = sel.toString().length;
          var available = maxLength - currentLen + selectedLen;
          if (text.length > available) {
            text = text.substring(0, available);
          }
          document.execCommand('insertText', false, text);
        });

        // Toolbar button commands
        toolbar.addEventListener('click', function(e) {
          var btn = e.target.closest('.jg-rte-btn');
          if (!btn) return;
          e.preventDefault();
          var cmd = btn.getAttribute('data-cmd');

          if (cmd === 'link') {
            saveSelection();
            openLinkDialog();
            return;
          }
          if (cmd === 'pinLink') {
            saveSelection();
            openPinDialog();
            return;
          }

          editor.focus();
          document.execCommand(cmd, false, null);
          syncContent();
        });

        // --- Link dialog ---
        var linkDialog = qs('#' + id + '-link-dialog', parentEl);
        var linkText = qs('#' + id + '-link-text', parentEl);
        var linkUrl = qs('#' + id + '-link-url', parentEl);
        var linkInsertBtn = qs('#' + id + '-link-insert', parentEl);
        var linkCancelBtn = qs('#' + id + '-link-cancel', parentEl);
        var linkCloseBtn = qs('#' + id + '-link-close', parentEl);

        function openLinkDialog() {
          var sel = window.getSelection();
          var selectedText = sel.toString();
          linkText.value = selectedText;
          linkUrl.value = '';
          linkDialog.style.display = 'block';
        }

        function closeLinkDialog() {
          linkDialog.style.display = 'none';
        }

        linkCancelBtn.addEventListener('click', closeLinkDialog);
        linkCloseBtn.addEventListener('click', closeLinkDialog);

        linkInsertBtn.addEventListener('click', function() {
          var url = linkUrl.value.trim();
          var text = linkText.value.trim() || url;
          if (!url) return;
          // Ensure URL has protocol
          if (!/^https?:\/\//i.test(url)) url = 'https://' + url;

          closeLinkDialog();
          editor.focus();
          restoreSelection();

          var link = '<a href="' + esc(url) + '" target="_blank" rel="noopener">' + esc(text) + '</a>';
          document.execCommand('insertHTML', false, link);
          syncContent();
          updateCounter();
        });

        // --- Pin link dialog ---
        var pinDialog = qs('#' + id + '-pin-dialog', parentEl);
        var pinSearch = qs('#' + id + '-pin-search', parentEl);
        var pinResults = qs('#' + id + '-pin-results', parentEl);
        var pinSelectedId = qs('#' + id + '-pin-selected-id', parentEl);
        var pinSelectedTitle = qs('#' + id + '-pin-selected-title', parentEl);
        var pinLinkText = qs('#' + id + '-pin-link-text', parentEl);
        var pinInsertBtn = qs('#' + id + '-pin-insert', parentEl);
        var pinCancelBtn = qs('#' + id + '-pin-cancel', parentEl);
        var pinCloseBtn = qs('#' + id + '-pin-close', parentEl);

        function openPinDialog() {
          pinSearch.value = '';
          pinResults.innerHTML = '';
          pinSelectedId.value = '';
          pinSelectedTitle.value = '';
          pinLinkText.value = '';
          pinDialog.style.display = 'block';
          pinSearch.focus();
        }

        function closePinDialog() {
          pinDialog.style.display = 'none';
        }

        pinCancelBtn.addEventListener('click', closePinDialog);
        pinCloseBtn.addEventListener('click', closePinDialog);

        // Pin autocomplete search
        var pinSearchDebounce = null;
        pinSearch.addEventListener('input', function() {
          clearTimeout(pinSearchDebounce);
          var query = pinSearch.value.trim().toLowerCase();
          if (query.length < 2) {
            pinResults.innerHTML = '<div class="jg-rte-pin-hint">Wpisz min. 2 znaki aby szukać...</div>';
            return;
          }
          pinSearchDebounce = setTimeout(function() {
            var matches = [];
            for (var i = 0; i < ALL.length; i++) {
              var pt = ALL[i];
              if (pt.status !== 'publish') continue;
              if ((pt.title || '').toLowerCase().indexOf(query) !== -1) {
                matches.push(pt);
                if (matches.length >= 10) break;
              }
            }
            if (matches.length === 0) {
              pinResults.innerHTML = '<div class="jg-rte-pin-hint">Nie znaleziono pinesek</div>';
              return;
            }
            var html = '';
            for (var j = 0; j < matches.length; j++) {
              var m = matches[j];
              var typeLabel = m.type === 'zgloszenie' ? 'Zgłoszenie' : (m.type === 'ciekawostka' ? 'Ciekawostka' : 'Miejsce');
              var typeColor = m.type === 'zgloszenie' ? '#dc2626' : (m.type === 'ciekawostka' ? '#3b82f6' : '#15803d');
              html += '<div class="jg-rte-pin-item" data-pin-id="' + m.id + '" data-pin-title="' + esc(m.title) + '" data-pin-slug="' + esc(m.slug || '') + '" data-pin-type="' + esc(m.type || '') + '">' +
                '<span class="jg-rte-pin-type" style="background:' + typeColor + '">' + typeLabel + '</span> ' +
                '<span class="jg-rte-pin-name">' + esc(m.title) + '</span>' +
              '</div>';
            }
            pinResults.innerHTML = html;
          }, 200);
        });

        // Pin result click
        pinResults.addEventListener('click', function(e) {
          var item = e.target.closest('.jg-rte-pin-item');
          if (!item) return;
          // Highlight selected
          var prev = pinResults.querySelector('.jg-rte-pin-item--selected');
          if (prev) prev.classList.remove('jg-rte-pin-item--selected');
          item.classList.add('jg-rte-pin-item--selected');
          pinSelectedId.value = item.getAttribute('data-pin-id');
          pinSelectedTitle.value = item.getAttribute('data-pin-title');
          pinLinkText.value = item.getAttribute('data-pin-title');
        });

        // Insert pin link
        pinInsertBtn.addEventListener('click', function() {
          var pinId = pinSelectedId.value;
          var pinTitle = pinSelectedTitle.value;
          if (!pinId) return;
          var text = pinLinkText.value.trim() || pinTitle;

          // Find the point to build its URL
          var pinPoint = null;
          for (var i = 0; i < ALL.length; i++) {
            if (String(ALL[i].id) === String(pinId)) {
              pinPoint = ALL[i];
              break;
            }
          }

          var pinUrl = '#point-' + pinId;
          if (pinPoint && pinPoint.slug && pinPoint.type) {
            var typePath = pinPoint.type === 'ciekawostka' ? 'ciekawostka' : (pinPoint.type === 'zgloszenie' ? 'zgloszenie' : 'miejsce');
            pinUrl = window.location.origin + '/' + typePath + '/' + pinPoint.slug + '/';
          }

          closePinDialog();
          editor.focus();
          restoreSelection();

          var link = '<a href="' + esc(pinUrl) + '" class="jg-pin-link" data-pin-id="' + esc(pinId) + '">&#128205; ' + esc(text) + '</a>';
          document.execCommand('insertHTML', false, link);
          syncContent();
          updateCounter();
        });

        // Set initial content
        function setContent(html) {
          editor.innerHTML = html || '';
          syncContent();
          updateCounter();
        }

        // Get content
        function getContent() {
          syncContent();
          return hidden.value;
        }

        return {
          editor: editor,
          hidden: hidden,
          setContent: setContent,
          getContent: getContent,
          syncContent: syncContent,
          updateCounter: updateCounter
        };
      }
      // ===== END RICH TEXT EDITOR =====

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
          // Also close lightbox if it was opened from within this modal
          if (lightbox && lightbox.style.display !== 'none') {
            var lc = qs('.jg-modal, .jg-lightbox', lightbox);
            if (lc) lc.className = lc.className.replace(/\bjg-modal--\w+/g, '');
            lightbox.style.display = 'none';
          }
        }
      }

      // Close regular modals by clicking their backdrop (lightbox handled separately below)
      [modalAdd, modalView, modalReport, modalReportsList, modalEdit, modalAuthor, modalStatus, modalRanking].forEach(function(bg) {
        if (!bg) return;
        bg.addEventListener('click', function(e) {
          if (e.target === bg) close(bg);
        });
      });

      // Persistent event delegation on lightbox backdrop.
      // Uses touchstart (not touchend!) so that e.preventDefault() suppresses ALL
      // subsequent events in the touch sequence (touchend, mousedown, mouseup, click).
      // This eliminates the iOS "ghost click" problem: without this, close(lightbox)
      // would fire on touchend, but the browser would still generate a synthetic click
      // at the same coordinates ~300ms later — hitting a gallery thumbnail in the
      // view modal behind the lightbox and immediately reopening it.
      if (lightbox) {
        lightbox.addEventListener('touchstart', function(e) {
          var btn = e.target.closest && e.target.closest('.jg-lb-close');
          if (btn) {
            e.preventDefault(); // suppresses touchend + ghost click entirely
            e.stopPropagation();
            close(lightbox);
            return;
          }
          if (e.target === lightbox) {
            e.preventDefault();
            close(lightbox);
          }
        }, { passive: false });

        // Fallback for non-touch devices (desktop)
        lightbox.addEventListener('click', function(e) {
          var btn = e.target.closest && e.target.closest('.jg-lb-close');
          if (btn) { close(lightbox); return; }
          if (e.target === lightbox) close(lightbox);
        });
      }

      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          [modalAdd, modalView, modalReport, modalReportsList, modalEdit, modalAuthor, modalStatus, modalRanking, lightbox].forEach(close);
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
        bounceAtZoomLimits: false, // Prevent elastic bounce at min/max zoom on mobile
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

      var tileLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager_nolabels/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
        maxZoom: 19,
        crossOrigin: true,
        subdomains: 'abcd',
        className: 'jg-map-tiles'
      });

      // Labels overlay – place names + street names at all zoom levels
      var labelsOverlay = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager_only_labels/{z}/{x}/{y}{r}.png', {
        maxZoom: 19,
        subdomains: 'abcd',
        pane: 'overlayPane'
      });

      var satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: '© Esri',
        maxZoom: 19,
        crossOrigin: true
      });

      // Cookie helpers for map layer preference
      function setMapCookie(name, value, days) {
        var expires = '';
        if (days) {
          var date = new Date();
          date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
          expires = '; expires=' + date.toUTCString();
        }
        document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';
      }

      function getMapCookie(name) {
        var nameEQ = name + '=';
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
          var c = ca[i].trim();
          if (c.indexOf(nameEQ) === 0) return decodeURIComponent(c.substring(nameEQ.length));
        }
        return null;
      }

      // Restore saved layer preference from cookie
      var savedLayer = getMapCookie('jg_map_layer');
      var currentLayerIsSatellite = (savedLayer === 'satellite');

      // Shared refs so enterFullscreen (inside onAdd closure) can call
      // buildSuggestions/hideSuggestions (defined inside setTimeout callback)
      var _jgFsBuildSuggestions = null;
      var _jgFsHideSuggestions = null;

      if (currentLayerIsSatellite) {
        satelliteLayer.addTo(map);
      } else {
        tileLayer.addTo(map);
      }
      labelsOverlay.addTo(map);

      // Persistent location names – always visible regardless of zoom level
      map.createPane('locationNamesPane');
      map.getPane('locationNamesPane').style.zIndex = 450;
      map.getPane('locationNamesPane').style.pointerEvents = 'none';

      var locationNames = [
        // Major towns
        { name: 'Jelenia Góra', lat: 50.8990, lng: 15.7340, major: true },
        { name: 'Karpacz', lat: 50.7760, lng: 15.7620, major: true },
        { name: 'Kowary', lat: 50.7910, lng: 15.8370, major: true },
        { name: 'Piechowice', lat: 50.8560, lng: 15.6160, major: true },
        // Districts and smaller towns
        { name: 'Cieplice Śląskie-Zdrój', lat: 50.8700, lng: 15.6720 },
        { name: 'Sobieszów', lat: 50.8420, lng: 15.6490 },
        { name: 'Jagniątków', lat: 50.8100, lng: 15.6280 },
        { name: 'Podgórzyn', lat: 50.8290, lng: 15.6940 },
        { name: 'Mysłakowice', lat: 50.8440, lng: 15.7830 },
        { name: 'Łomnica', lat: 50.8390, lng: 15.7540 },
        { name: 'Miłków', lat: 50.8170, lng: 15.7330 },
        { name: 'Wojanów', lat: 50.8550, lng: 15.7660 },
        { name: 'Jeżów Sudecki', lat: 50.9000, lng: 15.6580 },
        { name: 'Siedlęcin', lat: 50.9190, lng: 15.6900 },
        { name: 'Janowice Wielkie', lat: 50.8770, lng: 15.8200 },
        { name: 'Stara Kamienica', lat: 50.9230, lng: 15.6190 },
        { name: 'Borowice', lat: 50.8340, lng: 15.7250 },
        { name: 'Staniszów', lat: 50.8520, lng: 15.7260 },
        { name: 'Radomierz', lat: 50.8790, lng: 15.7820 }
      ];

      var locationNamesGroup = L.layerGroup({ pane: 'locationNamesPane' });

      locationNames.forEach(function(loc) {
        var cls = loc.major ? 'jg-location-name jg-location-name--major' : 'jg-location-name';
        var icon = L.divIcon({
          className: cls,
          html: '<span>' + loc.name + '</span>',
          iconSize: null,
          iconAnchor: [0, 0]
        });
        L.marker([loc.lat, loc.lng], { icon: icon, interactive: false, pane: 'locationNamesPane' })
          .addTo(locationNamesGroup);
      });

      locationNamesGroup.addTo(map);

      // Map/Satellite toggle control
      var MapToggleControl = L.Control.extend({
        options: { position: 'topright' },
        onAdd: function() {
          var container = L.DomUtil.create('div', 'jg-map-toggle-control leaflet-bar');
          var activeMap = currentLayerIsSatellite ? '' : ' jg-map-toggle-label--active';
          var activeSat = currentLayerIsSatellite ? ' jg-map-toggle-label--active' : '';
          var activeData = currentLayerIsSatellite ? 'satellite' : 'map';
          var mapIcon = '<svg class="jg-map-toggle-icon" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"/><line x1="9" y1="3" x2="9" y2="18"/><line x1="15" y1="6" x2="15" y2="21"/></svg>';
          var satIcon = '<svg class="jg-map-toggle-icon" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 7 7 13"/><path d="m16 3 5 5-5 5-5-5 5-5z"/><path d="m3 16 5 5 5-5-5-5-5 5z"/><path d="M7 13a6 6 0 0 1 6-6"/></svg>';
          container.innerHTML =
            '<div class="jg-map-toggle">' +
              '<span class="jg-map-toggle-label' + activeMap + '" data-layer="map">' + mapIcon + '<span class="jg-map-toggle-text">Mapa</span></span>' +
              '<div class="jg-map-toggle-switch" data-active="' + activeData + '">' +
                '<div class="jg-map-toggle-thumb"></div>' +
              '</div>' +
              '<span class="jg-map-toggle-label' + activeSat + '" data-layer="satellite">' + satIcon + '<span class="jg-map-toggle-text">Satelita</span></span>' +
            '</div>';

          L.DomEvent.disableClickPropagation(container);
          L.DomEvent.disableScrollPropagation(container);

          var toggle = container.querySelector('.jg-map-toggle-switch');
          var labelMap = container.querySelector('[data-layer="map"]');
          var labelSat = container.querySelector('[data-layer="satellite"]');

          function switchLayer() {
            if (currentLayerIsSatellite) {
              map.removeLayer(satelliteLayer);
              tileLayer.addTo(map);
              currentLayerIsSatellite = false;
              toggle.setAttribute('data-active', 'map');
              labelMap.classList.add('jg-map-toggle-label--active');
              labelSat.classList.remove('jg-map-toggle-label--active');
              setMapCookie('jg_map_layer', 'map', 365);
            } else {
              map.removeLayer(tileLayer);
              satelliteLayer.addTo(map);
              currentLayerIsSatellite = true;
              toggle.setAttribute('data-active', 'satellite');
              labelSat.classList.add('jg-map-toggle-label--active');
              labelMap.classList.remove('jg-map-toggle-label--active');
              setMapCookie('jg_map_layer', 'satellite', 365);
            }
          }

          toggle.addEventListener('click', switchLayer);
          labelMap.addEventListener('click', function() { if (currentLayerIsSatellite) switchLayer(); });
          labelSat.addEventListener('click', function() { if (!currentLayerIsSatellite) switchLayer(); });

          return container;
        }
      });

      map.addControl(new MapToggleControl());

      // Fullscreen control - positioned next to zoom controls (topleft)
      var isFullscreen = false;
      var FullscreenControl = L.Control.extend({
        options: { position: 'topleft' },
        onAdd: function() {
          var container = L.DomUtil.create('div', 'jg-fullscreen-control leaflet-bar');
          var btn = L.DomUtil.create('a', 'jg-fullscreen-btn', container);
          btn.href = '#';
          btn.title = 'Pełny ekran';
          btn.setAttribute('role', 'button');
          btn.setAttribute('aria-label', 'Pełny ekran');
          btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 1 1 1 1 4"/><polyline points="12 1 15 1 15 4"/><polyline points="4 15 1 15 1 12"/><polyline points="12 15 15 15 15 12"/></svg>';

          var exitIcon = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 4 4 4 1"/><polyline points="15 4 12 4 12 1"/><polyline points="1 12 4 12 4 15"/><polyline points="15 12 12 12 12 15"/></svg>';
          var enterIcon = btn.innerHTML;

          L.DomEvent.disableClickPropagation(container);
          L.DomEvent.disableScrollPropagation(container);

          var mapWrap = document.getElementById('jg-map-wrap');
          var sidebar = document.getElementById('jg-map-sidebar');
          var sidebarOriginalParent = sidebar ? sidebar.parentNode : null;
          var sidebarOriginalNext = sidebar ? sidebar.nextSibling : null;

          // Legacy elements kept for backward-compat (not used in new UI)
          var fsFilterBtn = document.createElement('button');
          fsFilterBtn.className = 'jg-fs-filter-btn';
          fsFilterBtn.type = 'button';
          elMap.appendChild(fsFilterBtn);

          var fsFilterIconBtn = document.createElement('button');
          fsFilterIconBtn.className = 'jg-fs-filter-icon-btn';
          fsFilterIconBtn.type = 'button';
          elMap.appendChild(fsFilterIconBtn);

          var fsFilterPanel = document.createElement('div');
          fsFilterPanel.className = 'jg-fs-filter-panel';
          elMap.appendChild(fsFilterPanel);

          // Top controls bar (inserted into Leaflet topright in fullscreen)
          var fsTopControls = null;

          // Create notification circles container (desktop fullscreen)
          var fsNotifContainer = document.createElement('div');
          fsNotifContainer.className = 'jg-fs-notif-container';
          elMap.appendChild(fsNotifContainer);

          // Create fullscreen search results panel
          var fsSearchPanel = document.createElement('div');
          fsSearchPanel.className = 'jg-fs-search-results-panel';
          fsSearchPanel.innerHTML = '<div class="jg-fs-search-header"><span class="jg-fs-search-title">Wyniki wyszukiwania</span><span class="jg-fs-search-count"></span><button class="jg-fs-search-close" type="button">&times;</button></div><div class="jg-fs-search-list"></div>';
          elMap.appendChild(fsSearchPanel);

          // Prevent map interactions when clicking/scrolling the search results panel
          L.DomEvent.disableClickPropagation(fsSearchPanel);
          L.DomEvent.disableScrollPropagation(fsSearchPanel);

          // Create floating promotional content container for fullscreen
          var fsPromoWrap = document.createElement('div');
          fsPromoWrap.className = 'jg-fs-promo-wrap';
          fsPromoWrap.style.display = 'none';
          elMap.appendChild(fsPromoWrap);
          var fsPromoObfInterval = null;

          // Prevent map interactions when clicking the promo area
          L.DomEvent.disableClickPropagation(fsPromoWrap);
          L.DomEvent.disableScrollPropagation(fsPromoWrap);

          fsSearchPanel.querySelector('.jg-fs-search-close').addEventListener('click', function(e) {
            e.stopPropagation();
            fsSearchPanel.classList.remove('active');
            var origClose = document.getElementById('jg-search-close-btn');
            if (origClose) origClose.click();
          });

          fsFilterBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            fsFilterPanel.classList.toggle('active');
          });

          // Mobile filter icon button toggles the filter panel visibility
          fsFilterIconBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (fsFilterPanel.classList.contains('mobile-visible')) {
              fsFilterPanel.classList.remove('mobile-visible');
            } else {
              fsFilterPanel.classList.add('mobile-visible');
            }
          });

          // Close mobile filter panel when clicking on the map
          elMap.addEventListener('click', function() {
            if (fsFilterPanel.classList.contains('active') && window.innerWidth <= 768) {
              fsFilterPanel.classList.remove('active');
            }
          });

          fsFilterPanel.addEventListener('click', function(e) {
            e.stopPropagation();
          });

          // Observe original search panel for fullscreen search results mirroring
          var origSearchPanel = document.getElementById('jg-search-panel');
          if (origSearchPanel) {
            var searchObserver = new MutationObserver(function() {
              if (!isFullscreen) return;
              if (origSearchPanel.classList.contains('active')) {
                // Mirror search results
                var origResults = document.getElementById('jg-search-results');
                var origCount = document.getElementById('jg-search-panel-count');
                if (origResults) {
                  fsSearchPanel.querySelector('.jg-fs-search-list').innerHTML = origResults.innerHTML;
                }
                if (origCount) {
                  fsSearchPanel.querySelector('.jg-fs-search-count').textContent = origCount.textContent;
                }
                fsSearchPanel.classList.add('active');

                // Re-bind click handlers on cloned results
                var resultItems = fsSearchPanel.querySelectorAll('.jg-search-result-item');
                resultItems.forEach(function(item) {
                  item.addEventListener('click', function() {
                    var origItems = origResults.querySelectorAll('.jg-search-result-item');
                    var idx = Array.prototype.indexOf.call(resultItems, item);
                    if (origItems[idx]) origItems[idx].click();
                    fsSearchPanel.classList.remove('active');
                    // Dismiss mobile keyboard after selecting a result
                    if (document.activeElement && document.activeElement.blur) {
                      document.activeElement.blur();
                    }
                    if (fsSearchInput) fsSearchInput.blur();
                  });
                });
              } else {
                fsSearchPanel.classList.remove('active');
              }
            });
            searchObserver.observe(origSearchPanel, { attributes: true, attributeFilter: ['class'] });
          }

          // Notification syncing for fullscreen mode
          function syncNotifications() {
            if (!isFullscreen || window.innerWidth <= 768) {
              fsNotifContainer.innerHTML = '';
              return;
            }
            var topBarNotifs = document.querySelectorAll('#jg-top-bar-notifications .jg-top-bar-notif');
            var html = '';
            topBarNotifs.forEach(function(notif) {
              var badge = notif.querySelector('.jg-notif-badge');
              var icon = notif.querySelector('span:first-child');
              if (badge && icon) {
                var iconText = icon.textContent.trim().split(' ')[0]; // Get emoji
                html += '<a href="' + notif.getAttribute('href') + '" class="jg-fs-notif-circle" title="' + icon.textContent.trim() + '">' +
                  '<span class="jg-fs-notif-icon">' + iconText + '</span>' +
                  '<span class="jg-fs-notif-badge">' + badge.textContent + '</span>' +
                  '</a>';
              }
            });
            fsNotifContainer.innerHTML = html;

            // Position notifications below the zoom controls (top-left area)
            fsNotifContainer.style.left = '12px';
          }

          function enterFullscreen() {
            isFullscreen = true;
            mapWrap.classList.add('jg-fullscreen');
            document.body.classList.add('jg-fullscreen-active');
            if (sidebar) {
              // Save original inline height and override for fullscreen
              sidebar._origHeight = sidebar.style.height;
              sidebar.style.setProperty('height', 'calc(100% - 24px)', 'important');
              elMap.appendChild(sidebar);
              sidebar.classList.add('jg-sidebar-fullscreen-overlay');
              // Prevent scroll wheel on sidebar from zooming the map
              L.DomEvent.disableScrollPropagation(sidebar);
            }

            // Move map/satellite toggle to topleft (under fullscreen button)
            var toggleCtrl = elMap.querySelector('.jg-map-toggle-control');
            if (toggleCtrl) {
              toggleCtrl._origParent = toggleCtrl.parentNode;
              var leftContainer = elMap.querySelector('.leaflet-top.leaflet-left');
              if (leftContainer) leftContainer.appendChild(toggleCtrl);
            }

            // Build topbar: [filter-dropdown-btn + search-input] inserted into Leaflet topright
            var filtersEl = document.getElementById('jg-map-filters');

            // Create the top controls container (acts as a Leaflet control)
            fsTopControls = document.createElement('div');
            fsTopControls.className = 'jg-fs-top-controls';

            // ── Filter section ──
            var fsFilterCtrl = document.createElement('div');
            fsFilterCtrl.className = 'jg-fs-filter-ctrl';

            var fsFilterDropdownBtn = document.createElement('button');
            fsFilterDropdownBtn.type = 'button';
            fsFilterDropdownBtn.className = 'jg-fs-filter-dropdown-btn';
            fsFilterDropdownBtn.innerHTML =
              '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>' +
              '<span class="jg-fs-filter-label-text">Filtry</span>' +
              '<span class="jg-fs-filter-arrow">&#x25BC;</span>';

            var fsFilterDropdownPanel = document.createElement('div');
            fsFilterDropdownPanel.className = 'jg-fs-filter-dropdown-panel';

            if (filtersEl) {
              var filtersClone = filtersEl.cloneNode(true);
              filtersClone.id = 'jg-fs-filters-clone';
              filtersClone.style.display = '';
              // Remove search div and sync-status from the clone (search has its own control now)
              var cloneSearchDiv = filtersClone.querySelector('.jg-search');
              if (cloneSearchDiv) cloneSearchDiv.parentNode.removeChild(cloneSearchDiv);
              var cloneSyncStatus = filtersClone.querySelector('#jg-sync-status');
              if (cloneSyncStatus) cloneSyncStatus.parentNode.removeChild(cloneSyncStatus);

              fsFilterDropdownPanel.appendChild(filtersClone);

              // Sync checkbox clicks from clone to original
              var cloneCheckboxes = filtersClone.querySelectorAll('input[type="checkbox"]');
              cloneCheckboxes.forEach(function(cb) {
                cb.addEventListener('change', function() {
                  var origCb;
                  if (cb.dataset.type) {
                    origCb = filtersEl.querySelector('input[data-type="' + cb.dataset.type + '"]');
                  } else if (cb.hasAttribute('data-my-places')) {
                    origCb = filtersEl.querySelector('input[data-my-places]');
                  } else if (cb.hasAttribute('data-promo')) {
                    origCb = filtersEl.querySelector('input[data-promo]');
                  }
                  if (origCb) {
                    origCb.checked = cb.checked;
                    origCb.dispatchEvent(new Event('change', { bubbles: true }));
                  }
                });
              });
            }

            // Toggle the filter dropdown on button click
            fsFilterDropdownBtn.addEventListener('click', function(e) {
              e.stopPropagation();
              var isOpen = fsFilterDropdownPanel.classList.contains('open');
              if (isOpen) {
                fsFilterDropdownPanel.classList.remove('open');
                fsFilterDropdownBtn.classList.remove('jg-active');
              } else {
                fsFilterDropdownPanel.classList.add('open');
                fsFilterDropdownBtn.classList.add('jg-active');
              }
            });

            L.DomEvent.disableClickPropagation(fsFilterCtrl);
            L.DomEvent.disableScrollPropagation(fsFilterCtrl);
            fsFilterCtrl.appendChild(fsFilterDropdownBtn);
            fsFilterCtrl.appendChild(fsFilterDropdownPanel);
            fsTopControls.appendChild(fsFilterCtrl);

            // ── Search section ──
            var fsSearchCtrl = document.createElement('div');
            fsSearchCtrl.className = 'jg-fs-search-ctrl';

            var fsSearchIconEl = document.createElement('span');
            fsSearchIconEl.className = 'jg-fs-search-icon';
            fsSearchIconEl.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>';

            var fsSearchInput = document.createElement('input');
            fsSearchInput.type = 'text';
            fsSearchInput.className = 'jg-fs-search-ctrl-input';
            fsSearchInput.placeholder = 'Szukaj...';
            fsSearchInput.setAttribute('autocomplete', 'off');
            // Pre-fill with current search value if any
            var origSearchInput = document.getElementById('jg-search-input');
            if (origSearchInput && origSearchInput.value) {
              fsSearchInput.value = origSearchInput.value;
            }

            var fsSearchClearBtn = document.createElement('button');
            fsSearchClearBtn.type = 'button';
            fsSearchClearBtn.className = 'jg-fs-search-clear-btn' + (fsSearchInput.value ? ' visible' : '');
            fsSearchClearBtn.innerHTML = '&times;';
            fsSearchClearBtn.title = 'Wyczyść';

            // Suggestions element inside the search control
            var fsSuggestionsEl = document.createElement('div');
            fsSuggestionsEl.className = 'jg-search-suggestions';
            var fsSuggestDebounce = null;
            // Wrappers that route through the outer-scope refs (bridging setTimeout closure)
            var fsBuildSugg = function(q) {
              if (_jgFsBuildSuggestions) _jgFsBuildSuggestions(q, fsSuggestionsEl, fsSearchInput);
            };
            var fsHideSugg = function() {
              if (_jgFsHideSuggestions) _jgFsHideSuggestions(fsSuggestionsEl);
            };

            fsSearchInput.addEventListener('input', function() {
              var val = this.value;
              var origIn = document.getElementById('jg-search-input');
              if (origIn) origIn.value = val;
              fsSearchClearBtn.classList.toggle('visible', val.trim().length > 0);
              clearTimeout(fsSuggestDebounce);
              var q = val.toLowerCase().trim();
              fsSuggestDebounce = setTimeout(function() {
                fsBuildSugg(q);
              }, 200);
            });

            fsSearchInput.addEventListener('blur', function() {
              setTimeout(function() {
                fsHideSugg();
              }, 150);
            });

            fsSearchInput.addEventListener('keydown', function(e) {
              if (e.key === 'Enter') {
                e.preventDefault();
                var activeItem = fsSuggestionsEl.querySelector('.jg-suggest-active');
                if (activeItem) {
                  var fill = activeItem.getAttribute('data-fill');
                  if (fill) {
                    fsSearchInput.value = fill;
                    var origIn2 = document.getElementById('jg-search-input');
                    if (origIn2) origIn2.value = fill;
                  }
                }
                fsHideSugg();
                var origIn3 = document.getElementById('jg-search-input');
                if (origIn3) {
                  origIn3.value = fsSearchInput.value;
                  origIn3.dispatchEvent(new Event('input', { bubbles: true }));
                  var origBtn = document.getElementById('jg-search-btn');
                  if (origBtn) origBtn.click();
                }
                // Dismiss on-screen keyboard on mobile after Enter search
                fsSearchInput.blur();
              } else if (e.key === 'Escape') {
                fsHideSugg();
              } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                var items = fsSuggestionsEl.querySelectorAll('.jg-suggest-item');
                if (!items.length) return;
                var current = fsSuggestionsEl.querySelector('.jg-suggest-active');
                var currentIdx = current ? Array.prototype.indexOf.call(items, current) : -1;
                if (current) current.classList.remove('jg-suggest-active');
                var nextIdx = e.key === 'ArrowDown' ? currentIdx + 1 : currentIdx - 1;
                if (nextIdx >= items.length) nextIdx = 0;
                if (nextIdx < 0) nextIdx = items.length - 1;
                items[nextIdx].classList.add('jg-suggest-active');
                items[nextIdx].scrollIntoView({ block: 'nearest' });
              }
            });

            fsSearchClearBtn.addEventListener('click', function() {
              fsSearchInput.value = '';
              fsSearchClearBtn.classList.remove('visible');
              fsHideSugg();
              var origIn = document.getElementById('jg-search-input');
              if (origIn) {
                origIn.value = '';
                origIn.dispatchEvent(new Event('input', { bubbles: true }));
              }
              var origCloseBtn = document.getElementById('jg-search-close-btn');
              if (origCloseBtn) origCloseBtn.click();
              fsSearchInput.focus();
            });

            // Add magnifier submit button to the right of the input
            var fsSearchSubmitBtn = document.createElement('button');
            fsSearchSubmitBtn.type = 'button';
            fsSearchSubmitBtn.className = 'jg-fs-search-submit-btn';
            fsSearchSubmitBtn.title = 'Szukaj';
            fsSearchSubmitBtn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>';
            fsSearchSubmitBtn.addEventListener('click', function(e) {
              e.stopPropagation();
              fsHideSugg();
              var origIn = document.getElementById('jg-search-input');
              if (origIn) {
                origIn.value = fsSearchInput.value;
                origIn.dispatchEvent(new Event('input', { bubbles: true }));
                var origBtn = document.getElementById('jg-search-btn');
                if (origBtn) origBtn.click();
              }
              // Dismiss on-screen keyboard on mobile after submitting search
              fsSearchInput.blur();
            });

            L.DomEvent.disableClickPropagation(fsSearchCtrl);
            L.DomEvent.disableScrollPropagation(fsSearchCtrl);
            fsSearchCtrl.appendChild(fsSearchIconEl);
            fsSearchCtrl.appendChild(fsSearchInput);
            fsSearchCtrl.appendChild(fsSearchClearBtn);
            fsSearchCtrl.appendChild(fsSearchSubmitBtn);
            fsSearchCtrl.appendChild(fsSuggestionsEl);
            fsTopControls.appendChild(fsSearchCtrl);

            // Close the filter dropdown when clicking elsewhere on the map
            var fsMapClickHandler = function() {
              if (fsFilterDropdownPanel) {
                fsFilterDropdownPanel.classList.remove('open');
                fsFilterDropdownBtn.classList.remove('jg-active');
              }
            };
            elMap.addEventListener('click', fsMapClickHandler);
            fsTopControls._mapClickHandler = fsMapClickHandler;

            // Append topbar into the map wrapper (not elMap which has overflow:hidden from Leaflet)
            mapWrap.appendChild(fsTopControls);

            syncNotifications();

            // Show floating promotional content in fullscreen
            (function setupFsPromo() {
              var origBanner = document.getElementById('jg-banner-container');
              if (!origBanner) return;

              var origLink = origBanner.querySelector('#jg-banner-link');
              var origImg = origBanner.querySelector('#jg-banner-image');
              if (!origLink || !origImg || !origImg.src || origImg.src === '' || origLink.style.display === 'none') return;

              fsPromoWrap.innerHTML = '';

              // "Reklama" label
              var label = document.createElement('div');
              label.className = 'jg-fs-promo-label';
              label.textContent = 'Reklama';
              fsPromoWrap.appendChild(label);

              // Inner container with anti-adblock obfuscation
              var inner = document.createElement('div');
              inner.className = 'jg-fs-promo-inner';
              var obfClass = 'obf-' + Math.random().toString(36).substr(2, 8);
              inner.classList.add(obfClass);

              // Clone banner content
              var link = document.createElement('a');
              link.href = origLink.href;
              link.target = '_blank';
              link.rel = 'noopener';

              var img = document.createElement('img');
              img.src = origImg.src.split('?')[0] + '?t=' + Date.now();
              img.alt = origImg.alt || '';

              link.appendChild(img);
              inner.appendChild(link);
              fsPromoWrap.appendChild(inner);

              // Track click on fullscreen banner via sendBeacon
              link.addEventListener('click', function() {
                var bannerId = origBanner.getAttribute('data-bid');
                var ajaxUrl = (window.JG_BANNER_CFG && window.JG_BANNER_CFG.ajax) ? window.JG_BANNER_CFG.ajax : '';
                if (bannerId && ajaxUrl) {
                  if (navigator.sendBeacon) {
                    var formData = new FormData();
                    formData.append('action', 'jg_banner_click');
                    formData.append('banner_id', bannerId);
                    navigator.sendBeacon(ajaxUrl, formData);
                  }
                }
              });

              // Track fullscreen impression
              var bannerId = origBanner.getAttribute('data-bid');
              var ajaxUrl = (window.JG_BANNER_CFG && window.JG_BANNER_CFG.ajax) ? window.JG_BANNER_CFG.ajax : '';
              if (bannerId && ajaxUrl && window.jQuery) {
                jQuery.ajax({
                  url: ajaxUrl,
                  type: 'POST',
                  data: { action: 'jg_banner_impression', banner_id: bannerId }
                });
              }

              fsPromoWrap.style.display = '';

              // Start anti-adblock obfuscation refresh (every 15 minutes)
              if (fsPromoObfInterval) clearInterval(fsPromoObfInterval);
              fsPromoObfInterval = setInterval(function() {
                // Remove old obfuscation classes
                var classes = inner.className.split(/\s+/);
                classes.forEach(function(cls) {
                  if (cls.startsWith('obf-')) inner.classList.remove(cls);
                });
                // Add new random class
                inner.classList.add('obf-' + Math.random().toString(36).substr(2, 8));
                // Refresh image cache-busting timestamp
                if (img.src) {
                  img.src = img.src.split('?')[0] + '?t=' + Date.now();
                }
              }, 900000);
            })();

            btn.innerHTML = exitIcon;
            btn.title = 'Zamknij pełny ekran';
            setTimeout(function() { map.invalidateSize(); }, 350);
          }

          function exitFullscreen() {
            isFullscreen = false;
            mapWrap.classList.remove('jg-fullscreen');
            document.body.classList.remove('jg-fullscreen-active');
            if (sidebar) {
              sidebar.classList.remove('jg-sidebar-fullscreen-overlay');
              // Restore original inline height
              if (sidebar._origHeight !== undefined) {
                sidebar.style.setProperty('height', sidebar._origHeight, 'important');
              }
              if (sidebarOriginalNext) {
                sidebarOriginalParent.insertBefore(sidebar, sidebarOriginalNext);
              } else {
                sidebarOriginalParent.appendChild(sidebar);
              }
            }
            // Move map/satellite toggle back to topright
            var toggleCtrl = elMap.querySelector('.jg-map-toggle-control');
            if (toggleCtrl && toggleCtrl._origParent) {
              toggleCtrl._origParent.appendChild(toggleCtrl);
              toggleCtrl._origParent = null;
            }
            // Remove the topbar controls from the Leaflet topright container
            if (fsTopControls) {
              if (fsTopControls._mapClickHandler) {
                elMap.removeEventListener('click', fsTopControls._mapClickHandler);
              }
              if (fsTopControls.parentNode) {
                fsTopControls.parentNode.removeChild(fsTopControls);
              }
            }
            fsTopControls = null;
            fsFilterPanel.innerHTML = '';
            fsNotifContainer.innerHTML = '';
            fsSearchPanel.classList.remove('active');
            fsSearchPanel.querySelector('.jg-fs-search-list').innerHTML = '';
            // Hide floating promotional content
            fsPromoWrap.style.display = 'none';
            fsPromoWrap.innerHTML = '';
            if (fsPromoObfInterval) {
              clearInterval(fsPromoObfInterval);
              fsPromoObfInterval = null;
            }
            btn.innerHTML = enterIcon;
            btn.title = 'Pełny ekran';
            setTimeout(function() { map.invalidateSize(); }, 350);
          }

          L.DomEvent.on(btn, 'click', function(e) {
            L.DomEvent.preventDefault(e);
            if (isFullscreen) {
              exitFullscreen();
            } else {
              enterFullscreen();
            }
          });

          // ESC key to exit fullscreen
          document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isFullscreen) {
              exitFullscreen();
            }
          });

          return container;
        }
      });

      map.addControl(new FullscreenControl());

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
          showAlert('By dodać punkt przybliż mapę maksymalnie i kliknij na miejsce gdzie ma się znaleźć Twoja pinezka.');
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
                '<label class="cols-2" id="add-place-category-field" style="display:none"><span>Kategoria miejsca</span> <select name="place_category" id="add-place-category-select" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' +
                generatePlaceCategoryOptions('') +
                '</select></label>' +
                '<label class="cols-2" id="add-curiosity-category-field" style="display:none"><span>Kategoria ciekawostki</span> <select name="curiosity_category" id="add-curiosity-category-select" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' +
                generateCuriosityCategoryOptions('') +
                '</select></label>' +
                '<div class="cols-2"><label style="display:block;margin-bottom:4px">Opis*</label>' + buildRichEditorHtml('add-rte', 800, '', 4) + '</div>' +
                '<div class="cols-2"><label style="display:block;margin-bottom:4px">Tagi (max 5)</label>' + buildTagInputHtml('add-tags') + '</div>' +
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

              // Initialize rich text editor for description
              var addRte = initRichEditor('add-rte', 800, modalAdd);

              // Initialize tag input
              var addTagInput = initTagInput('add-tags', modalAdd);

              // On form submit, ensure the hidden input has content
              var origAddSubmit = form.onsubmit;
              form.addEventListener('submit', function() {
                if (addRte) addRte.syncContent();
                if (addTagInput) addTagInput.syncHidden();
              }, true);

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
              var placeCategoryField = qs('#add-place-category-field', modalAdd);
              var placeCategorySelect = qs('#add-place-category-select', modalAdd);
              var curiosityCategoryField = qs('#add-curiosity-category-field', modalAdd);
              var curiosityCategorySelect = qs('#add-curiosity-category-select', modalAdd);

              if (typeSelect && categoryField && categorySelect) {
                // Function to toggle category field visibility
                function toggleCategoryField() {
                  var selectedType = typeSelect.value;

                  // Hide all category fields first
                  categoryField.style.display = 'none';
                  categorySelect.removeAttribute('required');
                  if (placeCategoryField) placeCategoryField.style.display = 'none';
                  if (curiosityCategoryField) curiosityCategoryField.style.display = 'none';

                  // Show appropriate field based on type
                  if (selectedType === 'zgloszenie') {
                    categoryField.style.display = 'block';
                    categorySelect.setAttribute('required', 'required');
                  } else if (selectedType === 'miejsce' && placeCategoryField) {
                    placeCategoryField.style.display = 'block';
                  } else if (selectedType === 'ciekawostka' && curiosityCategoryField) {
                    curiosityCategoryField.style.display = 'block';
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

            // Sync rich editor content before building FormData
            if (addRte) addRte.syncContent();

            // Validate content is not empty
            var contentVal = qs('#add-rte-hidden', modalAdd);
            if (contentVal && !contentVal.value.replace(/<\/?[^>]+(>|$)/g, '').trim()) {
              msg.textContent = 'Opis jest wymagany.';
              msg.style.color = '#b91c1c';
              return;
            }

            msg.textContent = 'Wysyłanie...';

            var fd = new FormData(form);
            fd.append('action', 'jg_submit_point');
            fd.append('_ajax_nonce', CFG.nonce);

            // Set category based on type
            var selectedType = fd.get('type');
            if (selectedType === 'miejsce') {
              var placeCategory = fd.get('place_category');
              if (placeCategory) {
                fd.set('category', placeCategory);
              }
              fd.delete('place_category');
              fd.delete('curiosity_category');
            } else if (selectedType === 'ciekawostka') {
              var curiosityCategory = fd.get('curiosity_category');
              if (curiosityCategory) {
                fd.set('category', curiosityCategory);
              }
              fd.delete('place_category');
              fd.delete('curiosity_category');
            } else {
              fd.delete('place_category');
              fd.delete('curiosity_category');
            }

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
              // Invalidate tag cache so newly added tags appear in suggestions immediately
              cachedAllTags = null;
              cachedAllTagsTime = 0;

              // Update level/XP bar immediately if server returned XP data
              if (j.data && j.data.xp_result) { updateLevelDisplay(j.data.xp_result); }

              // For admin/mod: point is published immediately — shoot confetti at pin
              var _addedLat = j.data && j.data.lat;
              var _addedLng = j.data && j.data.lng;
              if (CFG.isAdmin && j.data && j.data.status === 'publish' && _addedLat && _addedLng) {
                setTimeout(function(lat, lng) {
                  return function() {
                    shootMapMarkerConfetti(lat, lng,
                      ['#10b981', '#34d399', '#6ee7b7', '#fbbf24', '#ffffff', '#f0fdf4'], 44);
                  };
                }(_addedLat, _addedLng), 600);
              }

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
        } else if (p.category && categoryEmojis[p.category]) {
          // Show category emoji for all types with category (zgłoszenia, miejsca, ciekawostki)
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
              // Check if coming from point page (skip pulsing animation)
              var fromPoint = urlParams.get('from') === 'point';

              // STEP 1: Map is already scrolled to by earlyScrollCheck()
              // STEP 2: Wait for map to be ready, then zoom
              setTimeout(function() {
                // Zoom to point with maximum zoom level
                map.setView([point.lat, point.lng], 19, { animate: !fromPoint });

                var openAndClean = function() {
                  openDetails(point);
                  // Clean URL (remove parameters and hash) after modal opens
                  if (history.replaceState) {
                    var cleanUrl = window.location.origin + window.location.pathname;
                    history.replaceState(null, '', cleanUrl);
                  }
                };

                if (fromPoint) {
                  // Coming from point page: skip pulsing, open modal immediately
                  setTimeout(openAndClean, 100);
                } else {
                  // Normal deep link: show pulsing marker first
                  setTimeout(function() {
                    addPulsingMarker(point.lat, point.lng, openAndClean);
                  }, 800); // Wait for zoom animation
                }
              }, fromPoint ? 100 : 300);
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

          // Refresh sidebar if function exists
          if (typeof window.jgSidebarRefresh === 'function') {
            window.jgSidebarRefresh();
          }

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
              last_modifier: r.last_modifier || null,
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
              edit_locked: !!r.edit_locked,
              tags: r.tags || []
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

      function pluralVotes(n) {
        var abs = Math.abs(n);
        if (abs === 1) return 'głos';
        var mod10 = abs % 10;
        var mod100 = abs % 100;
        if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return 'głosy';
        return 'głosów';
      }

      function openLightbox(src) {
        // The close button and backdrop are handled via event delegation bound on the
        // lightbox element itself (see setup above) — no per-open binding needed.
        open(lightbox, '<button class="jg-lb-close" id="lb-close">Zamknij</button><img src="' + esc(src) + '" alt="" style="pointer-events:none">');
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
            qs('#ulimit-places', modalAuthor).textContent = result.places_remaining + ' / ' + result.places_limit;
            qs('#ulimit-reports', modalAuthor).textContent = result.reports_remaining + ' / ' + result.reports_limit;
            qs('#ulimit-places-input', modalAuthor).value = result.places_limit;
            qs('#ulimit-reports-input', modalAuthor).value = result.reports_limit;
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
              qs('#ulimit-places', modalAuthor).textContent = result.places_remaining + ' / ' + result.places_limit;
              qs('#ulimit-reports', modalAuthor).textContent = result.reports_remaining + ' / ' + result.reports_limit;
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
                qs('#ulimit-places', modalAuthor).textContent = result.places_remaining + ' / 5';
                qs('#ulimit-reports', modalAuthor).textContent = result.reports_remaining + ' / 5';
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
       * Send event to Google Analytics (via Site Kit gtag)
       */
      function trackGA(eventName, params) {
        if (typeof gtag === 'function') {
          gtag('event', eventName, params || {});
        }
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
      function openUserModal(userId, pointsPage, photosPage) {
        pointsPage = pointsPage || 1;
        photosPage = photosPage || 1;

        api('jg_get_user_info', { user_id: userId, points_page: pointsPage, photos_page: photosPage }).then(function(user) {
          if (!user) {
            showAlert('Błąd pobierania informacji o użytkowniku');
            return;
          }
          var memberSince = user.member_since ? new Date(user.member_since).toLocaleDateString('pl-PL') : '-';
          var lastActivity = user.last_activity ? new Date(user.last_activity).toLocaleDateString('pl-PL') : 'Brak aktywności';

          // Pin type statistics
          var tc = user.type_counts || {};
          var typeStatsHtml = '<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(100px, 1fr));gap:12px;margin-bottom:20px">' +
            '<div style="padding:14px;background:#ecfdf5;border-radius:8px;text-align:center;border-left:4px solid #10b981">' +
            '<div style="font-size:12px;color:#6b7280;margin-bottom:4px">📍 Miejsca</div>' +
            '<div style="font-weight:700;font-size:22px;color:#059669">' + (tc.miejsce || 0) + '</div>' +
            '</div>' +
            '<div style="padding:14px;background:#fef3c7;border-radius:8px;text-align:center;border-left:4px solid #f59e0b">' +
            '<div style="font-size:12px;color:#6b7280;margin-bottom:4px">💡 Ciekawostki</div>' +
            '<div style="font-weight:700;font-size:22px;color:#d97706">' + (tc.ciekawostka || 0) + '</div>' +
            '</div>' +
            '<div style="padding:14px;background:#fce7f3;border-radius:8px;text-align:center;border-left:4px solid #ec4899">' +
            '<div style="font-size:12px;color:#6b7280;margin-bottom:4px">📢 Zgłoszenia</div>' +
            '<div style="font-weight:700;font-size:22px;color:#db2777">' + (tc.zgloszenie || 0) + '</div>' +
            '</div>' +
            '<div style="padding:14px;background:#eff6ff;border-radius:8px;text-align:center;border-left:4px solid #3b82f6">' +
            '<div style="font-size:12px;color:#6b7280;margin-bottom:4px">👍 Głosowania</div>' +
            '<div style="font-weight:700;font-size:22px;color:#2563eb">' + (tc.votes || 0) + '</div>' +
            '</div>' +
            '<div style="padding:14px;background:#f5f3ff;border-radius:8px;text-align:center;border-left:4px solid #8b5cf6">' +
            '<div style="font-size:12px;color:#6b7280;margin-bottom:4px">✏️ Edycje</div>' +
            '<div style="font-weight:700;font-size:22px;color:#7c3aed">' + (tc.edits || 0) + '</div>' +
            '</div>' +
            '</div>';

          // Points list
          var pointsHtml = '';
          if (user.points && user.points.length > 0) {
            pointsHtml = '<div style="margin-top:12px">';
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

            // Points pagination
            if (user.points_pages > 1) {
              pointsHtml += '<div style="display:flex;justify-content:center;align-items:center;gap:8px;margin-top:12px">';
              pointsHtml += '<button class="jg-user-modal-points-prev" style="padding:6px 14px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer;font-size:13px' + (pointsPage <= 1 ? ';opacity:0.4;pointer-events:none' : '') + '">&laquo; Poprzednie</button>';
              pointsHtml += '<span style="font-size:13px;color:#6b7280">Strona ' + user.points_page + ' z ' + user.points_pages + '</span>';
              pointsHtml += '<button class="jg-user-modal-points-next" style="padding:6px 14px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer;font-size:13px' + (pointsPage >= user.points_pages ? ';opacity:0.4;pointer-events:none' : '') + '">Następne &raquo;</button>';
              pointsHtml += '</div>';
            }
          } else {
            pointsHtml = '<div style="padding:20px;text-align:center;color:#9ca3af">Brak dodanych miejsc</div>';
          }

          // Photo gallery
          var photosHtml = '';
          if (user.photos_total > 0) {
            photosHtml = '<div>' +
              '<h4 style="margin:20px 0 12px 0;color:#374151">📷 Galeria zdjęć (' + user.photos_total + ')</h4>' +
              '<div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(120px, 1fr));gap:12px">';

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

            photosHtml += '</div>';

            // Photos pagination
            if (user.photos_pages > 1) {
              photosHtml += '<div style="display:flex;justify-content:center;align-items:center;gap:8px;margin-top:12px">';
              photosHtml += '<button class="jg-user-modal-photos-prev" style="padding:6px 14px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer;font-size:13px' + (photosPage <= 1 ? ';opacity:0.4;pointer-events:none' : '') + '">&laquo; Poprzednie</button>';
              photosHtml += '<span style="font-size:13px;color:#6b7280">Strona ' + user.photos_page + ' z ' + user.photos_pages + '</span>';
              photosHtml += '<button class="jg-user-modal-photos-next" style="padding:6px 14px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer;font-size:13px' + (photosPage >= user.photos_pages ? ';opacity:0.4;pointer-events:none' : '') + '">Następne &raquo;</button>';
              photosHtml += '</div>';
            }

            photosHtml += '</div>';
          }

          // Build modal HTML with placeholder for level data
          var modalHtml = '<header style="background:linear-gradient(135deg, #8d2324 0%, #6b1a1b 100%);padding:20px;border-radius:12px 12px 0 0">' +
            '<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">' +
            '<h3 style="margin:0;color:#fff;font-size:20px;flex-shrink:0">👤 ' + esc(user.username) + '</h3>' +
            '<span id="jg-user-level-badge" class="jg-level-badge" style="display:none"></span>' +
            '<div id="jg-user-xp-bar-wrap" class="jg-xp-bar-wrap" style="display:none;flex:1;min-width:120px">' +
            '<div class="jg-xp-bar"><div class="jg-xp-bar-fill" id="jg-user-xp-fill" style="width:0%"></div></div>' +
            '<div class="jg-xp-bar-text" id="jg-user-xp-text"></div>' +
            '</div>' +
            '<div id="jg-user-achievements-panel" class="jg-achievements-panel" style="display:none;cursor:pointer" title="Kliknij aby zobaczyć wszystkie osiągnięcia"></div>' +
            '</div>' +
            '<button class="jg-close" id="user-modal-close" style="color:#fff;opacity:0.9">&times;</button>' +
            '</header>' +
            '<div style="padding:20px;max-height:70vh;overflow-y:auto">' +
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
            '<h4 style="margin:0 0 12px 0;color:#374151;font-size:16px;border-bottom:1px solid #e5e7eb;padding-bottom:8px">📊 Statystyki pinezek</h4>' +
            typeStatsHtml +
            '<div>' +
            '<h4 style="margin:0 0 8px 0;color:#374151">Dodane miejsca</h4>' +
            pointsHtml +
            '</div>' +
            photosHtml +
            '</div>';

          open(modalReport, modalHtml);

          qs('#user-modal-close', modalReport).onclick = function() {
            close(modalReport);
          };

          // Load level info asynchronously
          api('jg_get_user_level_info', { user_id: userId }).then(function(levelData) {
            if (!levelData) return;

            // Show level badge
            var badge = document.getElementById('jg-user-level-badge');
            if (badge) {
              badge.textContent = 'Poz. ' + levelData.level;
              badge.style.display = 'inline-block';
              // Apply prestige tier class
              var lvl = levelData.level;
              var tier = lvl >= 50 ? 'legend' : lvl >= 40 ? 'ruby' : lvl >= 30 ? 'diamond' : lvl >= 20 ? 'purple' : lvl >= 15 ? 'emerald' : lvl >= 10 ? 'gold' : lvl >= 5 ? 'silver' : 'bronze';
              badge.className = 'jg-level-badge jg-badge-' + tier;
            }

            // Show XP progress bar
            var barWrap = document.getElementById('jg-user-xp-bar-wrap');
            var barFill = document.getElementById('jg-user-xp-fill');
            var barText = document.getElementById('jg-user-xp-text');
            if (barWrap && barFill && barText) {
              barWrap.style.display = 'block';
              barFill.style.width = levelData.progress + '%';
              barText.textContent = levelData.xp_in_level + ' / ' + levelData.xp_needed + ' XP';
            }

            // Show recent achievements
            var achPanel = document.getElementById('jg-user-achievements-panel');
            if (achPanel && levelData.recent_achievements && levelData.recent_achievements.length > 0) {
              var achHtml = '';
              var rarityGlows = {
                'common': '0 0 8px rgba(209,213,219,0.8)',
                'uncommon': '0 0 8px rgba(16,185,129,0.8)',
                'rare': '0 0 8px rgba(59,130,246,0.8)',
                'epic': '0 0 8px rgba(139,92,246,0.8)',
                'legendary': '0 0 10px rgba(245,158,11,0.9), 0 0 20px rgba(245,158,11,0.4)'
              };
              var rarityBorders = {
                'common': '#d1d5db',
                'uncommon': '#10b981',
                'rare': '#3b82f6',
                'epic': '#8b5cf6',
                'legendary': '#f59e0b'
              };
              for (var a = 0; a < levelData.recent_achievements.length; a++) {
                var ach = levelData.recent_achievements[a];
                var glow = rarityGlows[ach.rarity] || rarityGlows.common;
                var border = rarityBorders[ach.rarity] || rarityBorders.common;
                achHtml += '<div class="jg-achievement-icon" title="' + esc(ach.name) + ': ' + esc(ach.description) + '" style="border-color:' + border + ';box-shadow:' + glow + '">' +
                  '<span>' + esc(ach.icon) + '</span></div>';
              }
              if (levelData.total_achievements > 4) {
                achHtml += '<div class="jg-achievement-more">+' + (levelData.total_achievements - 4) + '</div>';
              }
              achPanel.innerHTML = achHtml;
              achPanel.style.display = 'flex';

              // Click to open all achievements modal
              achPanel.onclick = function() {
                openAllAchievementsModal(userId);
              };
            }
          }).catch(function() {});

          // Points pagination handlers
          var pointsPrev = modalReport.querySelector('.jg-user-modal-points-prev');
          var pointsNext = modalReport.querySelector('.jg-user-modal-points-next');
          if (pointsPrev && pointsPage > 1) {
            pointsPrev.onclick = function() { openUserModal(userId, pointsPage - 1, photosPage); };
          }
          if (pointsNext && pointsPage < user.points_pages) {
            pointsNext.onclick = function() { openUserModal(userId, pointsPage + 1, photosPage); };
          }

          // Photos pagination handlers
          var photosPrev = modalReport.querySelector('.jg-user-modal-photos-prev');
          var photosNext = modalReport.querySelector('.jg-user-modal-photos-next');
          if (photosPrev && photosPage > 1) {
            photosPrev.onclick = function() { openUserModal(userId, pointsPage, photosPage - 1); };
          }
          if (photosNext && photosPage < user.photos_pages) {
            photosNext.onclick = function() { openUserModal(userId, pointsPage, photosPage + 1); };
          }

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
       * Open all achievements modal for a user
       */
      function openAllAchievementsModal(userId) {
        api('jg_get_user_achievements', { user_id: userId }).then(function(achievements) {
          if (!achievements || !Array.isArray(achievements)) return;

          var rarityLabels = {
            'common': 'Zwykłe',
            'uncommon': 'Niepospolite',
            'rare': 'Rzadkie',
            'epic': 'Epickie',
            'legendary': 'Legendarne'
          };
          var rarityColors = {
            'common': '#d1d5db',
            'uncommon': '#10b981',
            'rare': '#3b82f6',
            'epic': '#8b5cf6',
            'legendary': '#f59e0b'
          };

          var html = '<header style="background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%);padding:20px;border-radius:12px 12px 0 0">' +
            '<h3 style="margin:0;color:#fff;font-size:20px">🏆 Osiągnięcia</h3>' +
            '<button class="jg-close" id="ach-modal-close" style="color:#fff;opacity:0.9">&times;</button>' +
            '</header>' +
            '<div style="padding:20px;max-height:70vh;overflow-y:auto">' +
            '<div class="jg-achievements-grid">';

          for (var i = 0; i < achievements.length; i++) {
            var a = achievements[i];
            var color = rarityColors[a.rarity] || rarityColors.common;
            var label = rarityLabels[a.rarity] || 'Zwykłe';
            var earned = a.earned;
            var earnedDate = a.earned_at ? new Date(a.earned_at).toLocaleDateString('pl-PL') : '';

            html += '<div class="jg-achievement-card' + (earned ? '' : ' jg-achievement-locked') + '" style="border-color:' + color + '">' +
              '<div class="jg-achievement-card-icon" style="box-shadow:' + (earned ? '0 0 12px ' + color : 'none') + ';border-color:' + color + '">' +
              '<span style="font-size:28px">' + (earned ? esc(a.icon) : '🔒') + '</span></div>' +
              '<div class="jg-achievement-card-info">' +
              '<div class="jg-achievement-card-name">' + esc(a.name) + '</div>' +
              '<div class="jg-achievement-card-desc">' + esc(a.description) + '</div>' +
              '<div class="jg-achievement-card-rarity" style="color:' + color + '">' + label + '</div>' +
              (earnedDate ? '<div class="jg-achievement-card-date">Zdobyto: ' + earnedDate + '</div>' : '') +
              '</div></div>';
          }

          html += '</div></div>';

          open(modalReportsList, html);
          qs('#ach-modal-close', modalReportsList).onclick = function() {
            close(modalReportsList);
          };
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

          var modalHtml = '<header style="background:linear-gradient(135deg, #8d2324 0%, #6b1a1b 100%);padding:20px;border-radius:12px 12px 0 0">' +
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
          .then(function(d) {
            // Save report time to localStorage
            var reportTime = Date.now();
            lastReportTime = reportTime;
            setLastReportTime(reportTime);

            // Update level/XP bar if server returned XP data
            if (d && d.xp_result) { updateLevelDisplay(d.xp_result); }

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

            var contentHtml = p.content || p.excerpt || '';
            var contentText = contentHtml.replace(/<\/?[^>]+(>|$)/g, "");

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
              generateCategoryOptions(p.type === 'zgloszenie' ? (p.category || '') : '') +
              '</select></label>' +
              '<label class="cols-2" id="edit-place-category-field" style="' + (p.type === 'miejsce' ? 'display:block' : 'display:none') + '"><span>Kategoria miejsca</span> <select name="place_category" id="edit-place-category-select" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' +
              generatePlaceCategoryOptions(p.type === 'miejsce' ? (p.category || '') : '') +
              '</select></label>' +
              '<label class="cols-2" id="edit-curiosity-category-field" style="' + (p.type === 'ciekawostka' ? 'display:block' : 'display:none') + '"><span>Kategoria ciekawostki</span> <select name="curiosity_category" id="edit-curiosity-category-select" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' +
              generateCuriosityCategoryOptions(p.type === 'ciekawostka' ? (p.category || '') : '') +
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
              '<div class="cols-2"><label style="display:block;margin-bottom:4px">Opis*</label>' + buildRichEditorHtml('edit-rte', maxDescLength, '', 6) + '</div>' +
              '<div class="cols-2"><label style="display:block;margin-bottom:4px">Tagi (max 5)</label>' + buildTagInputHtml('edit-tags') + '</div>' +
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

        // Initialize rich text editor for edit form and set existing content
        var editRte = initRichEditor('edit-rte', maxDescLength, modalEdit);
        if (editRte) {
          editRte.setContent(contentHtml);
        }

        // Initialize tag input and set existing tags
        var editTagInput = initTagInput('edit-tags', modalEdit);
        if (editTagInput && p.tags) {
          editTagInput.setTags(p.tags);
        }

        // On form submit, sync the rich editor content
        form.addEventListener('submit', function() {
          if (editRte) editRte.syncContent();
          if (editTagInput) editTagInput.syncHidden();
        }, true);

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
        var editPlaceCategoryField = qs('#edit-place-category-field', modalEdit);
        var editPlaceCategorySelect = qs('#edit-place-category-select', modalEdit);
        var editCuriosityCategoryField = qs('#edit-curiosity-category-field', modalEdit);
        var editCuriosityCategorySelect = qs('#edit-curiosity-category-select', modalEdit);

        if (editTypeSelect && editCategoryField && editCategorySelect) {
          function toggleEditCategoryField() {
            var selectedType = editTypeSelect.value;

            // Hide all category fields first
            editCategoryField.style.display = 'none';
            editCategorySelect.removeAttribute('required');
            if (editPlaceCategoryField) editPlaceCategoryField.style.display = 'none';
            if (editCuriosityCategoryField) editCuriosityCategoryField.style.display = 'none';

            // Show appropriate field based on type
            if (selectedType === 'zgloszenie') {
              editCategoryField.style.display = 'block';
              editCategorySelect.setAttribute('required', 'required');
            } else if (selectedType === 'miejsce' && editPlaceCategoryField) {
              editPlaceCategoryField.style.display = 'block';
            } else if (selectedType === 'ciekawostka' && editCuriosityCategoryField) {
              editCuriosityCategoryField.style.display = 'block';
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

            // Debounce search by 200ms (same as FAB)
            editAddressTimeout = setTimeout(function() {
              searchEditAddressSuggestions(query);
            }, 200);
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

          // Search address suggestions function (using $.ajax like FAB for faster response)
          function searchEditAddressSuggestions(query) {
            // Show loader while fetching suggestions
            editAddressSuggestions.innerHTML = '';
            var loader = document.createElement('div');
            loader.style.cssText = 'display:flex;align-items:center;justify-content:center;padding:16px;gap:8px;color:#6b7280;font-size:13px';
            var spinner = document.createElement('div');
            spinner.style.cssText = 'width:18px;height:18px;border:2px solid #e5e7eb;border-top-color:#dc2626;border-radius:50%;animation:jg-fab-spin 0.6s linear infinite';
            var label = document.createElement('span');
            label.textContent = 'Szukam...';
            loader.appendChild(spinner);
            loader.appendChild(label);
            editAddressSuggestions.appendChild(loader);
            editAddressSuggestions.style.display = 'block';

            $.ajax({
              url: CFG.ajax,
              type: 'POST',
              data: {
                action: 'jg_search_address',
                _ajax_nonce: CFG.nonce,
                query: query
              },
              success: function(response) {
                editAddressSuggestions.innerHTML = '';

                if (response.success && response.data && response.data.length > 0) {
                  var results = response.data;

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
                  editAddressSuggestions.innerHTML = '';
                  var noResults = document.createElement('div');
                  noResults.style.cssText = 'padding:14px 12px;font-size:13px;color:#9ca3af;text-align:center';
                  noResults.textContent = 'Nie znaleziono wyników. Spróbuj wpisać inny adres.';
                  editAddressSuggestions.appendChild(noResults);
                  editAddressSuggestions.style.display = 'block';
                }
              },
              error: function(xhr, status, error) {
                console.error('[JG Edit] Address search error:', status, error);
                editAddressSuggestions.innerHTML = '';
                var errMsg = document.createElement('div');
                errMsg.style.cssText = 'padding:14px 12px;font-size:13px;color:#ef4444;text-align:center';
                errMsg.textContent = 'Błąd wyszukiwania. Spróbuj ponownie.';
                editAddressSuggestions.appendChild(errMsg);
                editAddressSuggestions.style.display = 'block';
              }
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

          // Sync rich editor content before building FormData
          if (editRte) editRte.syncContent();

          // Validate content is not empty
          var editContentVal = qs('#edit-rte-hidden', modalEdit);
          if (editContentVal && !editContentVal.value.replace(/<\/?[^>]+(>|$)/g, '').trim()) {
            msg.textContent = 'Opis jest wymagany.';
            msg.style.color = '#b91c1c';
            return;
          }

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

          // Set category based on type
          var selectedType = fd.get('type');
          if (selectedType === 'miejsce') {
            var placeCategory = fd.get('place_category');
            if (placeCategory) {
              fd.set('category', placeCategory);
            } else {
              fd.set('category', '');
            }
            fd.delete('place_category');
            fd.delete('curiosity_category');
          } else if (selectedType === 'ciekawostka') {
            var curiosityCategory = fd.get('curiosity_category');
            if (curiosityCategory) {
              fd.set('category', curiosityCategory);
            } else {
              fd.set('category', '');
            }
            fd.delete('place_category');
            fd.delete('curiosity_category');
          } else {
            fd.delete('place_category');
            fd.delete('curiosity_category');
          }

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
            // Invalidate tag cache so updated tags appear in suggestions immediately
            cachedAllTags = null;
            cachedAllTagsTime = 0;
            // Update level/XP bar if server returned XP data
            if (j.data && j.data.xp_result) { updateLevelDisplay(j.data.xp_result); }
            setTimeout(function() {
              close(modalEdit);
              if (fromReports) {
                close(modalReportsList);
              }
              if (limits.is_admin) {
                close(modalView);
              }
              refreshAll().then(function() {
                if (fromReports) {
                  showAlert('Miejsce edytowane i zgłoszenia zamknięte!');
                  // Reopen view modal with fresh data so admin sees changes immediately
                  var updatedPoint = null;
                  for (var i = 0; i < ALL.length; i++) {
                    if (+ALL[i].id === +p.id) { updatedPoint = ALL[i]; break; }
                  }
                  if (updatedPoint) { openDetailsModalContent(updatedPoint); }
                } else if (limits.is_admin) {
                  // Admin/moderator edits are applied immediately - refresh view modal with fresh data
                  var updatedPoint = null;
                  for (var i = 0; i < ALL.length; i++) {
                    if (+ALL[i].id === +p.id) { updatedPoint = ALL[i]; break; }
                  }
                  if (updatedPoint) { openDetailsModalContent(updatedPoint); }
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
              // Invalidate tag cache so status change reflects in tag suggestions
              cachedAllTags = null;
              cachedAllTagsTime = 0;
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

        var datePart = (p.date && p.date.human) ? '<span class="jg-meta-date">Dodano <strong>' + esc(p.date.human) + '</strong></span>' : '';

        var authorPart = '';
        if (p.author_name && p.author_name.trim() !== '') {
          authorPart = '<span class="jg-meta-author"><a href="#" id="btn-author" data-id="' + esc(p.author_id) + '" class="jg-meta-author-link">' + esc(p.author_name) + '</a></span>';
        }

        var dateInfo = (datePart || authorPart) ? '<div class="jg-date-info">' + datePart + (datePart && authorPart ? '<span class="jg-meta-sep">, przez&nbsp;</span>' : '') + authorPart + '</div>' : '';

        var who = '';

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

          // Ostatni modyfikujący - bezpośrednio pod Autor/Email/IP
          if (p.last_modifier) {
            adminData.push('<div><strong>Ostatni modyfikujący:</strong> <a href="#" class="jg-history-link" data-point-id="' + p.id + '" style="color:#2563eb;text-decoration:underline;cursor:pointer">' + esc(p.last_modifier.user_name) + '</a> <span style="color:#6b7280;font-size:12px">(' + esc(p.last_modifier.date) + ')</span></div>');
          } else {
            adminData.push('<div><strong>Ostatni modyfikujący:</strong> <a href="#" class="jg-history-link" data-point-id="' + p.id + '" style="color:#2563eb;text-decoration:underline;cursor:pointer;color:#9ca3af">brak edycji</a></div>');
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
            var ei = p.edit_info;

            // Build list of changes
            var changesList = [];
            if (ei.prev_title !== ei.new_title) {
              changesList.push('<div style="margin:4px 0"><strong>Tytuł:</strong> <span style="color:#991b1b;text-decoration:line-through">' + esc(ei.prev_title || '(brak)') + '</span> → <span style="color:#166534">' + esc(ei.new_title || '(brak)') + '</span></div>');
            }
            if (ei.prev_type !== ei.new_type) {
              changesList.push('<div style="margin:4px 0"><strong>Typ:</strong> <span style="color:#991b1b">' + esc(ei.prev_type || '(brak)') + '</span> → <span style="color:#166534">' + esc(ei.new_type || '(brak)') + '</span></div>');
            }
            if (ei.prev_category !== ei.new_category) {
              changesList.push('<div style="margin:4px 0"><strong>Kategoria:</strong> <span style="color:#991b1b">' + esc(ei.prev_category || '(brak)') + '</span> → <span style="color:#166534">' + esc(ei.new_category || '(brak)') + '</span></div>');
            }
            if (ei.prev_content !== ei.new_content) {
              changesList.push('<div style="margin:4px 0"><strong>Opis:</strong> <em style="color:#6b7280">(zmieniony)</em></div>');
            }
            if (ei.prev_address !== ei.new_address && (ei.prev_address || ei.new_address)) {
              changesList.push('<div style="margin:4px 0"><strong>📍 Adres:</strong> <span style="color:#991b1b">' + esc(ei.prev_address || '(brak)') + '</span> → <span style="color:#166534">' + esc(ei.new_address || '(brak)') + '</span></div>');
            }
            if ((ei.prev_lat !== ei.new_lat || ei.prev_lng !== ei.new_lng) && ei.new_lat && ei.new_lng) {
              var oldPos = (ei.prev_lat || '?') + ', ' + (ei.prev_lng || '?');
              var newPos = ei.new_lat + ', ' + ei.new_lng;
              if (oldPos !== newPos) {
                changesList.push('<div style="margin:4px 0"><strong>🗺️ Pozycja:</strong> <span style="color:#991b1b">' + oldPos + '</span> → <span style="color:#166534">' + newPos + '</span></div>');
              }
            }
            if (ei.new_images && ei.new_images.length > 0) {
              changesList.push('<div style="margin:4px 0"><strong>🖼️ Nowe zdjęcia:</strong> +' + ei.new_images.length + '</div>');
            }

            var changesHtml = changesList.length > 0
              ? '<div style="font-size:12px;color:#4c1d95;margin-top:8px;padding-top:8px;border-top:1px solid #e9d5ff">' + changesList.join('') + '</div>'
              : '';

            pendingChanges.push(
              '<div style="background:#faf5ff;border-left:4px solid #9333ea;padding:12px;border-radius:6px;margin-bottom:8px">' +
              '<div style="display:flex;justify-content:space-between;align-items:start;gap:12px">' +
              '<div style="flex:1">' +
              '<div style="font-weight:700;color:#6b21a8;margin-bottom:4px">📝 Edycja oczekuje</div>' +
              '<div style="font-size:13px;color:#7e22ce">Edytowano: <strong>' + esc(editedAt) + '</strong></div>' +
              changesHtml +
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
          var vc = +p.votes || 0;
          voteHtml = '<div class="jg-vote"><button id="v-up" ' + (myVote === 'up' ? 'class="active"' : '') + '>⬆️</button><span class="cnt" id="v-cnt" style="' + colorForVotes(vc) + '">' + vc + '</span><span class="jg-vote-label">' + pluralVotes(vc) + '</span><button id="v-down" ' + (myVote === 'down' ? 'class="active"' : '') + '>⬇️</button></div>';
        } else if (!p.sponsored && isOwnPoint) {
          // Show compact vote count for own points (no voting buttons)
          var vc = +p.votes || 0;
          voteHtml = '<div class="jg-vote jg-vote--own"><span class="jg-vote-own-icon">🗳️</span><span class="cnt" id="v-cnt" style="' + colorForVotes(vc) + '">' + vc + '</span><span class="jg-vote-own-label">' + pluralVotes(vc) + '</span></div>';
        }

        // Combine dateInfo and voteHtml into a single row
        var metaRow = '';
        if (dateInfo || voteHtml) {
          metaRow = '<div class="jg-meta-row">' + dateInfo + voteHtml + '</div>';
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
          addressInfo = '<div style="margin:0 0 12px 0;padding:0;font-size:13px;color:#6b7280"><span style="font-weight:500;color:#374151">📍</span> ' + esc(p.address) + '</div>';
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

        // Category badge for header (for all types with category)
        var categoryBadgeHeader = '';
        if (p.category) {
          var categoryEmojis = getCategoryEmojis();
          var emoji = categoryEmojis[p.category] || '📌';
          var catLabel = getCategoryLabel(p.category, p.type);
          // Different colors for different types
          var catBadgeColors = {
            'zgloszenie': { bg: '#fef3c7', border: '#f59e0b', text: '#78350f' },
            'miejsce': { bg: '#dcfce7', border: '#22c55e', text: '#166534' },
            'ciekawostka': { bg: '#dbeafe', border: '#3b82f6', text: '#1e40af' }
          };
          var catColors = catBadgeColors[p.type] || { bg: '#f3f4f6', border: '#6b7280', text: '#374151' };
          categoryBadgeHeader = '<div style="font-size:1rem;padding:6px 14px;background:' + catColors.bg + ';border:1px solid ' + catColors.border + ';border-radius:8px;color:' + catColors.text + ';font-weight:600;white-space:nowrap;display:flex;align-items:center;gap:8px;overflow:hidden;text-overflow:ellipsis;max-width:100%">' + catLabel + '</div>';
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

        // Share buttons - build point URL
        var shareUrl = '';
        if (p.slug && p.type) {
          var shareTypePath = p.type === 'ciekawostka' ? 'ciekawostka' : (p.type === 'zgloszenie' ? 'zgloszenie' : 'miejsce');
          shareUrl = window.location.origin + '/' + shareTypePath + '/' + p.slug + '/';
        }
        var shareTitle = esc(p.title || '');
        var shareText = p.title ? (p.title + ' — Interaktywna Mapa Jeleniej Góry') : 'Interaktywna Mapa Jeleniej Góry';
        var shareHtml = '';
        if (shareUrl) {
          shareHtml = '<div class="jg-share-bar">' +
            '<span class="jg-share-label">Udostępnij:</span>' +
            '<a class="jg-share-btn jg-share-btn--fb" href="https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(shareUrl) + '" target="_blank" rel="noopener" title="Udostępnij na Facebooku"><svg width="16" height="16" viewBox="0 0 24 24" fill="#fff"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>' +
            '<a class="jg-share-btn jg-share-btn--wa" href="https://wa.me/?text=' + encodeURIComponent(shareText + ' ' + shareUrl) + '" target="_blank" rel="noopener" title="Wyślij przez WhatsApp"><svg width="16" height="16" viewBox="0 0 24 24" fill="#fff"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg></a>' +
            '<button class="jg-share-btn jg-share-btn--link" id="btn-copy-link" title="Kopiuj link"><svg width="22" height="22" viewBox="0 0 24 24" fill="#fff"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg></button>' +
            '</div>';
        }

        // Business promotion section for non-sponsored business places
        var businessPromoHtml = '';
        var businessCategories = ['gastronomia', 'uslugi', 'sport', 'kultura'];
        var isOwnPlace = +CFG.currentUserId > 0 && +CFG.currentUserId === +p.author_id;
        if (p.type === 'miejsce' && !p.sponsored && (isOwnPlace || !CFG.isLoggedIn) && p.category && businessCategories.indexOf(p.category) !== -1) {
          businessPromoHtml = '<div class="jg-business-promo">' +
            '<div class="jg-business-promo__icon">💼</div>' +
            '<div class="jg-business-promo__text">' +
              '<strong>Jesteś właścicielem tego biznesu?</strong>' +
              '<p style="margin:6px 0 0;font-size:0.9rem;color:#4b5563">Promuj swoją firmę! Lepsza widoczność na mapie, możliwość dodania danych kontaktowych i priorytet w wyświetlaniu w naszym portalu.</p>' +
            '</div>' +
            '<button id="btn-business-promo" class="jg-business-promo__btn">Zapytaj o ofertę</button>' +
          '</div>';
        }

        // Build tags display (clickable, linking to catalog via clean URLs)
        var tagsHtml = '';
        if (p.tags && p.tags.length > 0) {
          var tagBase = CFG.tagBaseUrl || '';
          tagsHtml = '<div class="jg-place-tags">';
          p.tags.forEach(function(tag) {
            if (tagBase) {
              // Slugify tag: lowercase, replace Polish chars, replace non-alnum with hyphens
              var slug = tag.toLowerCase()
                .replace(/ą/g,'a').replace(/ć/g,'c').replace(/ę/g,'e').replace(/ł/g,'l')
                .replace(/ń/g,'n').replace(/ó/g,'o').replace(/ś/g,'s').replace(/ź/g,'z').replace(/ż/g,'z')
                .replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
              tagsHtml += '<a href="' + esc(tagBase + encodeURIComponent(slug) + '/') + '" class="jg-place-tag" rel="tag">#' + esc(tag) + '</a>';
            } else {
              tagsHtml += '<span class="jg-place-tag">#' + esc(tag) + '</span>';
            }
          });
          tagsHtml += '</div>';
        }

        var html = '<header style="display:flex;align-items:center;justify-content:space-between;gap:12px;border-bottom:1px solid #e5e7eb"><div style="display:flex;align-items:center;gap:12px;min-width:0;overflow:hidden">' + sponsoredBadgeHeader + typeBadge + categoryBadgeHeader + '</div><div style="display:flex;align-items:center;gap:12px;flex-shrink:0">' + statusBadge + caseIdBadge + '<button class="jg-close" id="dlg-close" style="margin:0">&times;</button></div></header><div class="jg-grid" style="overflow:auto;padding:20px"><h3 class="jg-place-title" style="margin:0 0 16px 0;font-size:2.5rem;font-weight:400;line-height:1.2">' + esc(p.title || 'Szczegóły') + lockIcon + '</h3>' + metaRow + addressInfo + (p.content ? ('<div class="jg-place-content">' + p.content + '</div>') : (p.excerpt ? ('<p class="jg-place-excerpt">' + esc(p.excerpt) + '</p>') : '')) + tagsHtml + contactInfo + ctaButton + (gal ? ('<div class="jg-gallery" style="margin-top:10px">' + gal + '</div>') : '') + (who ? ('<div style="margin-top:10px">' + who + '</div>') : '') + verificationBadge + reportsWarning + userReportNotice + editInfo + deletionInfo + adminNote + resolvedNotice + rejectedNotice + businessPromoHtml + shareHtml + adminBox + '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px">' + statsBtn + (canEdit ? '<button id="btn-edit" class="jg-btn jg-btn--ghost">Edytuj</button>' : '') + deletionBtn + '<button id="btn-report" class="jg-btn jg-btn--ghost">Zgłoś</button></div></div>';

        open(modalView, html, { addClass: (promoClass + typeClass).trim(), pointData: p });

        // Pin links in content already have SEO page URLs in href - let them navigate naturally

        // Track view for sponsored pins with unique visitor detection
        var viewStartTime = Date.now();
        if (p.sponsored) {
          var isUnique = isUniqueVisitor(p.id);
          trackStat(p.id, 'view', { is_unique: isUnique }, p.author_id);
        }

        // GA: track pin view for ALL pins
        trackGA('pin_view', {
          pin_id: p.id,
          pin_title: p.title || '',
          pin_type: p.type || '',
          pin_category: p.category || '',
          pin_sponsored: p.sponsored ? 'yes' : 'no'
        });

        // Copy link button handler
        var copyBtn = qs('#btn-copy-link', modalView);
        if (copyBtn && shareUrl) {
          copyBtn.onclick = function() {
            if (navigator.clipboard && navigator.clipboard.writeText) {
              navigator.clipboard.writeText(shareUrl).then(function() {
                copyBtn.classList.add('jg-share-btn--copied');
                copyBtn.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="#fff"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>';
                setTimeout(function() {
                  copyBtn.classList.remove('jg-share-btn--copied');
                  copyBtn.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="#fff"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>';
                }, 2000);
              });
            } else {
              // Fallback for older browsers
              var textArea = document.createElement('textarea');
              textArea.value = shareUrl;
              textArea.style.position = 'fixed';
              textArea.style.opacity = '0';
              document.body.appendChild(textArea);
              textArea.select();
              document.execCommand('copy');
              document.body.removeChild(textArea);
              copyBtn.classList.add('jg-share-btn--copied');
              copyBtn.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="#fff"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>';
              setTimeout(function() {
                copyBtn.classList.remove('jg-share-btn--copied');
                copyBtn.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="#fff"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>';
              }, 2000);
            }
            trackGA('share', { method: 'copy_link', pin_id: p.id, pin_type: p.type || '' });
          };
        }

        // Track share button clicks (Facebook, WhatsApp)
        modalView.querySelectorAll('.jg-share-btn--fb, .jg-share-btn--wa').forEach(function(btn) {
          btn.addEventListener('click', function() {
            var method = btn.classList.contains('jg-share-btn--fb') ? 'facebook' : 'whatsapp';
            trackGA('share', { method: method, pin_id: p.id, pin_type: p.type || '' });
          });
        });

        // Business promotion button handler
        var promoBtn = qs('#btn-business-promo', modalView);
        if (promoBtn) {
          promoBtn.onclick = function() {
            // Non-logged user: show login/register modal on register tab
            if (!CFG.isLoggedIn) {
              if (typeof window.openAuthModal === 'function') {
                window.openAuthModal('register', 'Aby wysłać zapytanie o ofertę promocji, musisz posiadać konto w naszym portalu. Zarejestruj się lub zaloguj, aby kontynuować.');
              } else {
                showPromoAuthModal();
              }
              return;
            }
            promoBtn.disabled = true;
            promoBtn.textContent = 'Wysyłanie...';
            api('jg_request_promotion', {
              point_id: p.id,
              point_title: p.title || '',
              point_category: p.category || '',
              point_address: p.address || '',
              point_lat: p.lat || '',
              point_lng: p.lng || ''
            }).then(function() {
              showAlert('<div style="text-align:center">' +
                '<div style="font-size:3rem;margin-bottom:12px">✅</div>' +
                '<h3 style="margin:0 0 8px;color:#065f46">Prośba o ofertę została przesłana!</h3>' +
                '<p style="margin:0;color:#4b5563">Otrzymasz odpowiedź w ciągu <strong>24 godzin roboczych</strong> na adres e-mail powiązany z Twoim kontem.</p>' +
              '</div>');
              promoBtn.textContent = 'Wysłano ✓';
              promoBtn.style.background = '#d1fae5';
              promoBtn.style.color = '#065f46';
              promoBtn.style.borderColor = '#10b981';
            }).catch(function(err) {
              promoBtn.disabled = false;
              promoBtn.textContent = 'Zapytaj o ofertę';
              var errMsg = (err && err.message) ? err.message : (err || 'Wystąpił błąd podczas wysyłania prośby. Spróbuj ponownie.');
              showAlert(errMsg);
            });
          };
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

              // GA: track gallery click for all pins
              trackGA('pin_gallery_click', { pin_id: p.id, pin_title: p.title || '', image_index: idx });

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

        // GA: track contact clicks for ALL pins (phone, website, social, CTA)
        modalView.querySelectorAll('a[href^="tel:"]').forEach(function(link) {
          link.addEventListener('click', function() {
            trackGA('pin_phone_click', { pin_id: p.id, pin_title: p.title || '' });
          });
        });
        modalView.querySelectorAll('a[href^="http"]:not(.jg-social-link):not(.jg-btn-cta-sponsored)').forEach(function(link) {
          link.addEventListener('click', function() {
            trackGA('pin_website_click', { pin_id: p.id, pin_title: p.title || '', url: this.href });
          });
        });
        modalView.querySelectorAll('.jg-social-link').forEach(function(link) {
          link.addEventListener('click', function() {
            trackGA('pin_social_click', { pin_id: p.id, pin_title: p.title || '', platform: this.getAttribute('data-social') || '' });
          });
        });
        var ctaBtnGA = modalView.querySelector('.jg-btn-cta-sponsored');
        if (ctaBtnGA) {
          ctaBtnGA.addEventListener('click', function() {
            trackGA('pin_cta_click', { pin_id: p.id, pin_title: p.title || '' });
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
              var lbl = qs('.jg-vote-label', modalView);
              if (lbl) lbl.textContent = pluralVotes(+n || 0);
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
                  if (d.xp_result) { updateLevelDisplay(d.xp_result); }
                  // Confetti burst from the pressed vote button
                  var voteBtn = dir === 'up' ? up : down;
                  var voteColors = dir === 'up'
                    ? ['#10b981', '#34d399', '#6ee7b7', '#bbf7d0', '#ffffff', '#d1fae5']
                    : ['#ef4444', '#f87171', '#fca5a5', '#fee2e2', '#ffffff', '#fff1f2'];
                  shootButtonConfetti(voteBtn, voteColors, 30);
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
                    // Invalidate tag cache so approved point's tags appear in suggestions
                    cachedAllTags = null;
                    cachedAllTagsTime = 0;
                    close(modalView);
                    return refreshAll();
                  })
                  .then(function() {
                    showAlert('Zaakceptowano i opublikowano!');
                    // Confetti near the approved map marker (admin/mod)
                    shootMapMarkerConfetti(p.lat, p.lng,
                      ['#10b981', '#34d399', '#6ee7b7', '#fbbf24', '#ffffff', '#f0fdf4'], 44);
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
                    // Invalidate tag cache so edited tags appear in suggestions
                    cachedAllTags = null;
                    cachedAllTagsTime = 0;
                    // Confetti near the marker immediately (coords available before refresh)
                    shootMapMarkerConfetti(p.lat, p.lng,
                      ['#3b82f6', '#60a5fa', '#93c5fd', '#fbbf24', '#ffffff', '#eff6ff'], 40);
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
                  // Invalidate tag cache so edited tags appear in suggestions
                  cachedAllTags = null;
                  cachedAllTagsTime = 0;
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

        // History modal handler
        var historyLink = qs('.jg-history-link', modalView);
        if (historyLink) {
          historyLink.onclick = function(e) {
            e.preventDefault();
            var pointId = this.getAttribute('data-point-id');
            openPointHistoryModal(pointId, p);
          };
        }
      }

      /**
       * Open a full history modal for a point with revert functionality.
       */
      function openPointHistoryModal(pointId, currentPoint) {
        // Create overlay
        var overlay = document.createElement('div');
        overlay.className = 'jg-history-overlay';
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:100000;display:flex;align-items:center;justify-content:center;padding:20px';

        var modal = document.createElement('div');
        modal.style.cssText = 'background:#fff;border-radius:12px;max-width:950px;width:100%;max-height:85vh;overflow:auto;padding:24px;position:relative';
        modal.innerHTML = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><h2 style="margin:0;font-size:18px">Historia zmian: ' + esc(currentPoint.title) + '</h2><button class="jg-history-close" style="background:#dc2626;color:#fff;border:none;border-radius:6px;padding:6px 14px;cursor:pointer;font-weight:700;font-size:14px">✕ Zamknij</button></div><div class="jg-history-content" style="color:#666;text-align:center;padding:40px">Ładowanie...</div>';

        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        overlay.querySelector('.jg-history-close').onclick = function() {
          document.body.removeChild(overlay);
        };
        overlay.addEventListener('click', function(e) {
          if (e.target === overlay) document.body.removeChild(overlay);
        });

        // Fetch full history
        api('jg_get_full_point_history', { post_id: pointId })
          .then(function(entries) {
            var content = overlay.querySelector('.jg-history-content');
            if (!entries || entries.length === 0) {
              content.innerHTML = '<p style="text-align:center;color:#6b7280;padding:30px">Brak wpisów w historii zmian tego miejsca.</p>';
              return;
            }

            var html = '<div style="display:flex;flex-direction:column;gap:8px">';
            entries.forEach(function(entry, idx) {
              var statusBadge = '';
              if (entry.status === 'approved') statusBadge = '<span style="background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600">ZATWIERDZONO</span>';
              else if (entry.status === 'rejected') statusBadge = '<span style="background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600">ODRZUCONO</span>';
              else statusBadge = '<span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600">OCZEKUJE</span>';

              var actionLabel = entry.action_type === 'edit' ? 'Edycja' : entry.action_type === 'delete_request' ? 'Prośba o usunięcie' : entry.action_type;

              // Short summary for header
              var changeSummary = '';
              if (entry.changes && entry.changes.length > 0) {
                var fieldNames = entry.changes.map(function(ch) { return ch.label; });
                changeSummary = ' — zmieniono: ' + fieldNames.join(', ');
              } else if (entry.action_type === 'delete_request') {
                changeSummary = ' — prośba o usunięcie';
              }

              html += '<div class="jg-history-entry" style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">';

              // Clickable header (accordion trigger)
              html += '<div class="jg-history-header" data-entry-idx="' + idx + '" style="background:#f9fafb;padding:10px 14px;cursor:pointer;user-select:none;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;transition:background .15s">';
              html += '<div style="display:flex;align-items:center;gap:6px;flex:1;min-width:0">';
              html += '<span class="jg-history-arrow" style="display:inline-block;transition:transform .2s;font-size:12px;color:#6b7280;flex-shrink:0">▶</span>';
              html += '<strong style="white-space:nowrap">' + esc(entry.user_name) + '</strong>';
              html += '<span style="color:#6b7280;font-size:12px;white-space:nowrap">' + esc(actionLabel) + '</span>';
              html += '<span style="color:#9ca3af;font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + esc(changeSummary) + '</span>';
              html += '</div>';
              html += '<div style="display:flex;align-items:center;gap:8px;flex-shrink:0">' + statusBadge + '<span style="color:#9ca3af;font-size:12px;white-space:nowrap">' + esc(entry.created_ago) + '</span></div>';
              html += '</div>';

              // Collapsible body (hidden by default)
              html += '<div class="jg-history-body" data-entry-idx="' + idx + '" style="display:none">';

              // Changes details - full content, no truncation
              if (entry.changes && entry.changes.length > 0) {
                html += '<div style="padding:10px 14px">';
                html += '<table style="width:100%;border-collapse:collapse;font-size:13px">';
                html += '<tr><th style="text-align:left;padding:4px 8px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:11px;width:80px">Pole</th><th style="text-align:left;padding:4px 8px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:11px">Było</th><th style="text-align:left;padding:4px 8px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:11px">Jest</th></tr>';
                entry.changes.forEach(function(ch) {
                  var oldDisplay = ch.old ? esc(ch.old) : '<em style="color:#9ca3af">(puste)</em>';
                  var newDisplay = ch.new ? esc(ch.new) : '<em style="color:#9ca3af">(puste)</em>';
                  html += '<tr><td style="padding:6px 8px;border-bottom:1px solid #f3f4f6;font-weight:600;white-space:nowrap;vertical-align:top">' + esc(ch.label) + '</td>';
                  html += '<td style="padding:6px 8px;border-bottom:1px solid #f3f4f6;color:#991b1b;background:#fef2f2;word-break:break-word;max-width:350px">' + oldDisplay + '</td>';
                  html += '<td style="padding:6px 8px;border-bottom:1px solid #f3f4f6;color:#166534;background:#f0fdf4;word-break:break-word;max-width:350px">' + newDisplay + '</td></tr>';
                });
                html += '</table></div>';
              } else if (entry.action_type === 'delete_request') {
                html += '<div style="padding:10px 14px;color:#991b1b">Prośba o usunięcie miejsca</div>';
              }

              // Rejection reason
              if (entry.rejection_reason) {
                html += '<div style="padding:8px 14px;background:#fef2f2;color:#991b1b;font-size:12px"><strong>Powód odrzucenia:</strong> ' + esc(entry.rejection_reason) + '</div>';
              }

              // Resolved by info
              if (entry.resolved_by) {
                html += '<div style="padding:6px 14px;font-size:11px;color:#9ca3af">Rozpatrzone przez: ' + esc(entry.resolved_by) + (entry.resolved_at ? ' (' + esc(entry.resolved_at) + ')' : '') + '</div>';
              }

              // Revert button (only for approved edits that have new_values = result state)
              if (entry.status === 'approved' && entry.action_type === 'edit' && entry.new_values && entry.new_values.title) {
                html += '<div style="padding:8px 14px;border-top:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center">';
                html += '<button class="jg-delete-history-btn" data-history-id="' + entry.id + '" style="background:none;border:1px solid #dc2626;color:#dc2626;border-radius:6px;padding:4px 10px;cursor:pointer;font-size:11px" title="Usuń ten wpis z historii">Usuń wpis</button>';
                html += '<button class="jg-revert-btn" data-history-id="' + entry.id + '" style="background:#f59e0b;color:#fff;border:none;border-radius:6px;padding:6px 14px;cursor:pointer;font-size:12px;font-weight:600">↩ Przywróć do tego stanu</button>';
                html += '</div>';
              } else {
                // Delete button only (no revert available)
                html += '<div style="padding:8px 14px;border-top:1px solid #e5e7eb;text-align:left">';
                html += '<button class="jg-delete-history-btn" data-history-id="' + entry.id + '" style="background:none;border:1px solid #dc2626;color:#dc2626;border-radius:6px;padding:4px 10px;cursor:pointer;font-size:11px" title="Usuń ten wpis z historii">Usuń wpis</button>';
                html += '</div>';
              }

              html += '</div>'; // end body
              html += '</div>'; // end entry
            });
            html += '</div>';

            content.innerHTML = html;

            // Accordion behavior - click header to expand/collapse, only one open at a time
            content.querySelectorAll('.jg-history-header').forEach(function(header) {
              header.onmouseenter = function() { this.style.background = '#f3f4f6'; };
              header.onmouseleave = function() { this.style.background = '#f9fafb'; };
              header.onclick = function() {
                var idx = this.getAttribute('data-entry-idx');
                var body = content.querySelector('.jg-history-body[data-entry-idx="' + idx + '"]');
                var arrow = this.querySelector('.jg-history-arrow');
                var isOpen = body.style.display !== 'none';

                // Close all others
                content.querySelectorAll('.jg-history-body').forEach(function(b) { b.style.display = 'none'; });
                content.querySelectorAll('.jg-history-arrow').forEach(function(a) { a.style.transform = 'rotate(0deg)'; });

                // Toggle clicked one
                if (!isOpen) {
                  body.style.display = 'block';
                  arrow.style.transform = 'rotate(90deg)';
                }
              };
            });

            // Attach revert handlers
            content.querySelectorAll('.jg-revert-btn').forEach(function(btn) {
              btn.onclick = function(e) {
                e.stopPropagation();
                var historyId = this.getAttribute('data-history-id');
                var thisBtn = this;
                showConfirm('Czy na pewno chcesz przywrócić punkt do tego stanu? Obecny stan zostanie zapisany w historii.').then(function(confirmed) {
                  if (!confirmed) return;
                  thisBtn.disabled = true;
                  thisBtn.textContent = 'Przywracanie...';

                  api('jg_admin_revert_to_history', { history_id: historyId })
                    .then(function() {
                      if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
                      return refreshAll();
                    })
                    .then(function() {
                      close(modalView);
                      showAlert('Punkt przywrócony do wybranego stanu');
                      var updatedPoint = ALL.find(function(x) { return +x.id === +currentPoint.id; });
                      if (updatedPoint) {
                        setTimeout(function() { openDetails(updatedPoint); }, 300);
                      }
                    })
                    .catch(function(err) {
                      showAlert('Błąd: ' + (err.message || '?'));
                      thisBtn.disabled = false;
                      thisBtn.textContent = '↩ Przywróć do tego stanu';
                    });
                });
              };
            });

            // Attach delete history entry handlers
            content.querySelectorAll('.jg-delete-history-btn').forEach(function(btn) {
              btn.onclick = function(e) {
                e.stopPropagation();
                var historyId = this.getAttribute('data-history-id');
                var thisBtn = this;
                showConfirm('Czy na pewno chcesz usunąć ten wpis z historii? Tej operacji nie można cofnąć.').then(function(confirmed) {
                  if (!confirmed) return;
                  thisBtn.disabled = true;
                  thisBtn.textContent = 'Usuwanie...';

                  api('jg_admin_delete_history_entry', { history_id: historyId })
                    .then(function() {
                      // Re-open history modal to refresh the list
                      if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
                      openPointHistoryModal(pointId, currentPoint);
                    })
                    .catch(function(err) {
                      showAlert('Błąd: ' + (err.message || '?'));
                      thisBtn.disabled = false;
                      thisBtn.textContent = 'Usuń wpis';
                    });
                });
              };
            });
          })
          .catch(function(err) {
            var content = overlay.querySelector('.jg-history-content');
            content.innerHTML = '<p style="text-align:center;color:#991b1b;padding:30px">Błąd ładowania historii: ' + esc(err.message || '?') + '</p>';
          });
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
        var enabledPlaceCategories = {};
        var enabledCuriosityCategories = {};

        if (elFilters) {
          elFilters.querySelectorAll('input[data-type]').forEach(function(cb) {
            if (cb.checked) enabled[cb.getAttribute('data-type')] = true;
          });
          var pr = elFilters.querySelector('input[data-promo]');
          promoOnly = !!(pr && pr.checked);
          var myPlaces = elFilters.querySelector('input[data-my-places]');
          myPlacesOnly = !!(myPlaces && myPlaces.checked);
        }

        // Get enabled place categories
        document.querySelectorAll('input[data-map-place-category]').forEach(function(cb) {
          if (cb.checked) enabledPlaceCategories[cb.getAttribute('data-map-place-category')] = true;
        });

        // Get enabled curiosity categories
        document.querySelectorAll('input[data-map-curiosity-category]').forEach(function(cb) {
          if (cb.checked) enabledCuriosityCategories[cb.getAttribute('data-map-curiosity-category')] = true;
        });


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
          if (!enabled[p.type]) {
            return false;
          }

          // Category filters for places
          if (p.type === 'miejsce' && Object.keys(enabledPlaceCategories).length > 0) {
            // If point has no category, show it (backwards compatibility)
            // If point has category, check if it's enabled
            if (p.category && !enabledPlaceCategories[p.category]) {
              return false;
            }
          }

          // Category filters for curiosities
          if (p.type === 'ciekawostka' && Object.keys(enabledCuriosityCategories).length > 0) {
            if (p.category && !enabledCuriosityCategories[p.category]) {
              return false;
            }
          }

          return true;
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

        // ── Autocomplete suggestions dropdown ──
        var suggestionsEl = document.createElement('div');
        suggestionsEl.className = 'jg-search-suggestions';
        searchInput.parentNode.appendChild(suggestionsEl);
        var suggestDebounce = null;
        var activeSuggestion = -1;

        function buildSuggestions(query, altSuggestEl, altSearchInput) {
          var useEl = altSuggestEl || suggestionsEl;
          var useInput = altSearchInput || searchInput;

          if (!query || query.length < 2) {
            useEl.innerHTML = '';
            useEl.style.display = 'none';
            return;
          }

          var MAX_PER_GROUP = 5;
          var trescItems = [];  // title/excerpt matches
          var tagItems = [];    // tag matches
          var adresItems = [];  // address matches
          var seen = {};        // avoid duplicates

          (ALL || []).forEach(function(p) {
            var title = (p.title || '').toLowerCase();
            var excerpt = (p.excerpt || '').toLowerCase();
            var content = (p.content || '').toLowerCase();
            var address = (p.address || '').toLowerCase();
            var tags = (p.tags || []);
            var key = p.id;

            // TREŚĆ: match in title, excerpt, or content
            if (trescItems.length < MAX_PER_GROUP && !seen['t' + key]) {
              if (title.indexOf(query) !== -1 || excerpt.indexOf(query) !== -1 || content.indexOf(query) !== -1) {
                trescItems.push({ id: p.id, text: p.title, sub: p.excerpt ? p.excerpt.substring(0, 60) : '' });
                seen['t' + key] = true;
              }
            }

            // ADRES: match in address
            if (adresItems.length < MAX_PER_GROUP && !seen['a' + key] && address.indexOf(query) !== -1) {
              adresItems.push({ id: p.id, text: p.title, sub: p.address });
              seen['a' + key] = true;
            }

            // TAGI: match in any tag
            if (tagItems.length < MAX_PER_GROUP) {
              for (var i = 0; i < tags.length; i++) {
                if ((tags[i] || '').toLowerCase().indexOf(query) !== -1) {
                  if (!seen['g' + key + '_' + i]) {
                    tagItems.push({ id: p.id, text: tags[i], sub: p.title });
                    seen['g' + key + '_' + i] = true;
                  }
                  break;
                }
              }
            }
          });

          if (trescItems.length === 0 && tagItems.length === 0 && adresItems.length === 0) {
            useEl.innerHTML = '';
            useEl.style.display = 'none';
            return;
          }

          var html = '';

          if (trescItems.length > 0) {
            html += '<div class="jg-suggest-group"><div class="jg-suggest-header">TREŚĆ</div>';
            trescItems.forEach(function(item) {
              html += '<div class="jg-suggest-item" data-point-id="' + item.id + '">' +
                '<span class="jg-suggest-main">' + esc(item.text) + '</span>' +
                (item.sub ? '<span class="jg-suggest-sub">' + esc(item.sub) + '</span>' : '') +
                '</div>';
            });
            html += '</div>';
          }

          if (tagItems.length > 0) {
            html += '<div class="jg-suggest-group"><div class="jg-suggest-header">TAGI</div>';
            tagItems.forEach(function(item) {
              html += '<div class="jg-suggest-item" data-point-id="' + item.id + '" data-fill="' + esc(item.text) + '">' +
                '<span class="jg-suggest-main">' + esc(item.text) + '</span>' +
                '<span class="jg-suggest-sub">' + esc(item.sub) + '</span>' +
                '</div>';
            });
            html += '</div>';
          }

          if (adresItems.length > 0) {
            html += '<div class="jg-suggest-group"><div class="jg-suggest-header">ADRES</div>';
            adresItems.forEach(function(item) {
              html += '<div class="jg-suggest-item" data-point-id="' + item.id + '" data-fill="' + esc(item.sub) + '">' +
                '<span class="jg-suggest-main">' + esc(item.sub) + '</span>' +
                '<span class="jg-suggest-sub">' + esc(item.text) + '</span>' +
                '</div>';
            });
            html += '</div>';
          }

          useEl.innerHTML = html;
          useEl.style.display = 'block';
          if (!altSuggestEl) activeSuggestion = -1;

          // Click handlers on suggestion items
          var allItems = useEl.querySelectorAll('.jg-suggest-item');
          allItems.forEach(function(el) {
            el.addEventListener('mousedown', function(e) {
              e.preventDefault(); // prevent blur before click fires
              var fill = this.getAttribute('data-fill');
              if (fill) {
                useInput.value = fill;
                // Also sync to original search input if using an alternative
                if (useInput !== searchInput) searchInput.value = fill;
              }
              useEl.innerHTML = '';
              useEl.style.display = 'none';
              if (!altSuggestEl) activeSuggestion = -1;
              performSearch();
              // Dismiss on-screen keyboard on mobile after selecting suggestion
              useInput.blur();
            });
          });
        }

        function hideSuggestions(altSuggestEl) {
          var useEl = altSuggestEl || suggestionsEl;
          useEl.innerHTML = '';
          useEl.style.display = 'none';
          if (!altSuggestEl) activeSuggestion = -1;
        }

        function navigateSuggestions(dir) {
          var items = suggestionsEl.querySelectorAll('.jg-suggest-item');
          if (!items.length) return;
          if (activeSuggestion >= 0) items[activeSuggestion].classList.remove('jg-suggest-active');
          activeSuggestion += dir;
          if (activeSuggestion < 0) activeSuggestion = items.length - 1;
          if (activeSuggestion >= items.length) activeSuggestion = 0;
          items[activeSuggestion].classList.add('jg-suggest-active');
          items[activeSuggestion].scrollIntoView({ block: 'nearest' });
        }

        function selectActiveSuggestion() {
          var items = suggestionsEl.querySelectorAll('.jg-suggest-item');
          if (activeSuggestion >= 0 && items[activeSuggestion]) {
            var fill = items[activeSuggestion].getAttribute('data-fill');
            if (fill) {
              searchInput.value = fill;
            }
            hideSuggestions();
            performSearch();
            return true;
          }
          return false;
        }

        searchInput.addEventListener('input', function() {
          clearTimeout(suggestDebounce);
          var q = this.value.toLowerCase().trim();
          suggestDebounce = setTimeout(function() {
            buildSuggestions(q);
          }, 200);
        });

        searchInput.addEventListener('blur', function() {
          setTimeout(hideSuggestions, 150);
        });

        // Perform search and show results in side panel
        function performSearch() {
          hideSuggestions();
          var query = searchInput.value.toLowerCase().trim();

          if (!query) {
            closeSearchPanel();
            return;
          }


          // Search through ALL points (by title, address, tags, content, excerpt)
          var results = (ALL || []).filter(function(p) {
            var title = (p.title || '').toLowerCase();
            var content = (p.content || '').toLowerCase();
            var excerpt = (p.excerpt || '').toLowerCase();
            var address = (p.address || '').toLowerCase();
            var tags = (p.tags || []);
            if (title.indexOf(query) !== -1 ||
                content.indexOf(query) !== -1 ||
                excerpt.indexOf(query) !== -1 ||
                address.indexOf(query) !== -1) {
              return true;
            }
            for (var i = 0; i < tags.length; i++) {
              if ((tags[i] || '').toLowerCase().indexOf(query) !== -1) {
                return true;
              }
            }
            return false;
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
            if (e.key === 'ArrowDown') {
              e.preventDefault();
              navigateSuggestions(1);
            } else if (e.key === 'ArrowUp') {
              e.preventDefault();
              navigateSuggestions(-1);
            } else if (e.key === 'Escape') {
              hideSuggestions();
            } else if (e.key === 'Enter') {
              e.preventDefault();
              if (!selectActiveSuggestion()) {
                hideSuggestions();
                performSearch();
              }
            }
          });
        }

        if (searchCloseBtn) {
          searchCloseBtn.addEventListener('click', closeSearchPanel);
        }

        // Export to outer scope so enterFullscreen (different closure) can call them
        _jgFsBuildSuggestions = buildSuggestions;
        _jgFsHideSuggestions = hideSuggestions;
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

          // Initialize category filters
          initMapCategoryFilters();
        } else {
          debugError('[JG MAP] Filter container not found!');
        }
      }, 500);

      // Initialize category filters for the main map
      function initMapCategoryFilters() {
        var placeCategories = (window.JG_MAP_CFG && JG_MAP_CFG.placeCategories) || {};
        var curiosityCategories = (window.JG_MAP_CFG && JG_MAP_CFG.curiosityCategories) || {};
        var categoryFiltersContainer = document.getElementById('jg-category-filters');
        var placeCategoriesContainer = document.getElementById('jg-place-categories');
        var curiosityCategoriesContainer = document.getElementById('jg-curiosity-categories');

        // Generate place category checkboxes (sorted alphabetically)
        if (placeCategoriesContainer && Object.keys(placeCategories).length > 0) {
          var sortedPlace = [];
          for (var key in placeCategories) {
            if (placeCategories.hasOwnProperty(key)) {
              sortedPlace.push({ key: key, label: placeCategories[key].label, icon: placeCategories[key].icon });
            }
          }
          sortedPlace.sort(function(a, b) { return a.label.localeCompare(b.label, 'pl'); });
          var html = '<div class="jg-category-dropdown-header">Kategorie miejsc:</div><div class="jg-category-checkboxes">';
          for (var i = 0; i < sortedPlace.length; i++) {
            var cat = sortedPlace[i];
            html += '<label class="jg-category-filter-label"><input type="checkbox" data-map-place-category="' + cat.key + '" checked><span class="jg-filter-icon">' + (cat.icon || '📍') + '</span><span>' + cat.label + '</span></label>';
          }
          html += '</div>';
          placeCategoriesContainer.innerHTML = html;
        }

        // Generate curiosity category checkboxes (sorted alphabetically)
        if (curiosityCategoriesContainer && Object.keys(curiosityCategories).length > 0) {
          var sortedCuriosity = [];
          for (var key in curiosityCategories) {
            if (curiosityCategories.hasOwnProperty(key)) {
              sortedCuriosity.push({ key: key, label: curiosityCategories[key].label, icon: curiosityCategories[key].icon });
            }
          }
          sortedCuriosity.sort(function(a, b) { return a.label.localeCompare(b.label, 'pl'); });
          var html = '<div class="jg-category-dropdown-header">Kategorie ciekawostek:</div><div class="jg-category-checkboxes">';
          for (var i = 0; i < sortedCuriosity.length; i++) {
            var cat = sortedCuriosity[i];
            html += '<label class="jg-category-filter-label"><input type="checkbox" data-map-curiosity-category="' + cat.key + '" checked><span class="jg-filter-icon">' + (cat.icon || '💡') + '</span><span>' + cat.label + '</span></label>';
          }
          html += '</div>';
          curiosityCategoriesContainer.innerHTML = html;
        }

        // Keep category filters container hidden by default
        // It will only be shown when user clicks expand button and there's content
        // Container stays hidden until a dropdown inside it is shown

        // Add event listeners to expand buttons
        var expandBtns = document.querySelectorAll('.jg-filter-expand-btn');
        expandBtns.forEach(function(btn) {
          btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var target = this.getAttribute('data-expand-target');
            var dropdown = document.getElementById('jg-' + target);
            if (dropdown && dropdown.innerHTML.trim()) {
              var isVisible = dropdown.style.display !== 'none';
              dropdown.style.display = isVisible ? 'none' : 'flex';
              this.textContent = isVisible ? '▼' : '▲';

              // Show/hide parent container based on any visible dropdown
              if (categoryFiltersContainer) {
                var anyVisible = false;
                categoryFiltersContainer.querySelectorAll('.jg-category-dropdown').forEach(function(d) {
                  if (d.style.display !== 'none' && d.innerHTML.trim()) anyVisible = true;
                });
                categoryFiltersContainer.style.display = anyVisible ? 'flex' : 'none';
              }
            }
          });
        });

        // Add event listeners to category checkboxes
        var categoryCheckboxes = document.querySelectorAll('input[data-map-place-category], input[data-map-curiosity-category]');
        categoryCheckboxes.forEach(function(cb) {
          cb.addEventListener('change', function() {
            apply(true);
          });
        });
      }

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

            // Check for approval events for the current user
            if (syncData.sync_events && syncData.sync_events.length > 0) {
              if (CFG.debug) {
                console.log('[JG MAP] Sync events received:', syncData.sync_events);
                console.log('[JG MAP] Current user ID:', CFG.currentUserId);
              }

              if (CFG.currentUserId) {
                syncData.sync_events.forEach(function(event) {
                  if (CFG.debug) {
                    console.log('[JG MAP] Processing event:', event.event_type, event.metadata);
                  }

                  // Point approval notification
                  if (event.event_type === 'point_approved' && event.metadata) {
                    var authorId = event.metadata.author_id || event.metadata.user_id;
                    if (CFG.debug) {
                      console.log('[JG MAP] Point approved - author_id:', authorId, 'currentUserId:', CFG.currentUserId);
                    }
                    if (authorId && parseInt(authorId) === parseInt(CFG.currentUserId)) {
                      if (CFG.debug) {
                        console.log('[JG MAP] Showing approval notification for point:', event.point_id);
                      }
                      showApprovalNotification(
                        event.metadata.point_title || 'Twoje miejsce',
                        event.metadata.point_type || 'miejsce',
                        event.point_id,
                        'point'
                      );
                      // Confetti near the approved marker
                      if (event.metadata.lat && event.metadata.lng) {
                        setTimeout(function(lat, lng) {
                          return function() {
                            shootMapMarkerConfetti(lat, lng,
                              ['#10b981', '#34d399', '#6ee7b7', '#fbbf24', '#ffffff', '#f0fdf4'], 44);
                          };
                        }(event.metadata.lat, event.metadata.lng), 400);
                      }
                    }
                  }
                  // Edit approval notification
                  if (event.event_type === 'edit_approved' && event.metadata) {
                    var editorId = event.metadata.editor_id;
                    if (CFG.debug) {
                      console.log('[JG MAP] Edit approved - editor_id:', editorId, 'currentUserId:', CFG.currentUserId);
                    }
                    if (editorId && parseInt(editorId) === parseInt(CFG.currentUserId)) {
                      if (CFG.debug) {
                        console.log('[JG MAP] Showing approval notification for edit:', event.point_id);
                      }
                      showApprovalNotification(
                        event.metadata.point_title || 'Twoja edycja',
                        event.metadata.point_type || 'miejsce',
                        event.point_id,
                        'edit'
                      );
                      // Confetti near the edited marker
                      if (event.metadata.lat && event.metadata.lng) {
                        setTimeout(function(lat, lng) {
                          return function() {
                            shootMapMarkerConfetti(lat, lng,
                              ['#3b82f6', '#60a5fa', '#93c5fd', '#fbbf24', '#ffffff', '#eff6ff'], 44);
                          };
                        }(event.metadata.lat, event.metadata.lng), 400);
                      }
                    }
                  }
                });
              }
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
        // Add spinner keyframes animation if not already added
        if (!document.getElementById('jg-fab-spinner-style')) {
          $('<style>').attr('id', 'jg-fab-spinner-style').text(
            '@keyframes jg-fab-spin { to { transform: rotate(360deg); } }'
          ).appendTo('head');
        }

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

          // Show loader while fetching suggestions
          container.empty();
          container.append(
            $('<div>').css({
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              padding: '16px',
              gap: '8px',
              color: '#6b7280',
              fontSize: '13px'
            }).append(
              $('<div>').css({
                width: '18px',
                height: '18px',
                border: '2px solid #e5e7eb',
                borderTopColor: '#dc2626',
                borderRadius: '50%',
                animation: 'jg-fab-spin 0.6s linear infinite'
              }),
              $('<span>').text('Szukam...')
            )
          );
          container.show();

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
                container.empty();
                container.append(
                  $('<div>').css({
                    padding: '14px 12px',
                    fontSize: '13px',
                    color: '#9ca3af',
                    textAlign: 'center'
                  }).text('Nie znaleziono wyników. Spróbuj wpisać inny adres.')
                );
                container.show();
              }
            },
            error: function(xhr, status, error) {
              debugError('[JG FAB] AJAX Error:', status, error);
              if (xhr.responseJSON) {
                debugError('[JG FAB] Error response:', xhr.responseJSON);
              }
              container.empty();
              container.append(
                $('<div>').css({
                  padding: '14px 12px',
                  fontSize: '13px',
                  color: '#ef4444',
                  textAlign: 'center'
                }).text('Błąd wyszukiwania. Spróbuj ponownie.')
              );
              container.show();
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
              '<label class="cols-2" id="add-place-category-field" style="display:none"><span>Kategoria miejsca (opcjonalna)</span> <select name="place_category" id="add-place-category-select" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' +
              generatePlaceCategoryOptions('') +
              '</select></label>' +
              '<label class="cols-2" id="add-curiosity-category-field" style="display:none"><span>Kategoria ciekawostki (opcjonalna)</span> <select name="curiosity_category" id="add-curiosity-category-select" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' +
              generateCuriosityCategoryOptions('') +
              '</select></label>' +
              '<div class="cols-2"><label style="display:block;margin-bottom:4px">Opis* (max 800 znaków)</label>' + buildRichEditorHtml('fab-rte', 800, '', 4) + '</div>' +
              '<div class="cols-2"><label style="display:block;margin-bottom:4px">Tagi (max 5)</label>' + buildTagInputHtml('fab-tags') + '</div>' +
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

            // Initialize rich text editor for FAB add form
            var fabRte = initRichEditor('fab-rte', 800, modalAdd);

            // Initialize tag input for FAB add form
            var fabTagInput = initTagInput('fab-tags', modalAdd);

            // On form submit, sync the rich editor content and tags
            form.addEventListener('submit', function() {
              if (fabRte) fabRte.syncContent();
              if (fabTagInput) fabTagInput.syncHidden();
            }, true);

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
            var placeCategoryField = qs('#add-place-category-field', modalAdd);
            var placeCategorySelect = qs('#add-place-category-select', modalAdd);
            var curiosityCategoryField = qs('#add-curiosity-category-field', modalAdd);
            var curiosityCategorySelect = qs('#add-curiosity-category-select', modalAdd);

            if (typeSelect && categoryField && categorySelect) {
              // Function to toggle category field visibility
              function toggleCategoryField() {
                var selectedType = typeSelect.value;

                // Hide all category fields first
                categoryField.style.display = 'none';
                categorySelect.removeAttribute('required');
                if (placeCategoryField) placeCategoryField.style.display = 'none';
                if (curiosityCategoryField) curiosityCategoryField.style.display = 'none';

                // Show appropriate field based on type
                if (selectedType === 'zgloszenie') {
                  categoryField.style.display = 'block';
                  categorySelect.setAttribute('required', 'required');
                } else if (selectedType === 'miejsce' && placeCategoryField) {
                  placeCategoryField.style.display = 'block';
                } else if (selectedType === 'ciekawostka' && curiosityCategoryField) {
                  curiosityCategoryField.style.display = 'block';
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

              // Sync rich editor content before building FormData
              if (fabRte) fabRte.syncContent();

              // Validate content is not empty
              var fabContentVal = qs('#fab-rte-hidden', modalAdd);
              if (fabContentVal && !fabContentVal.value.replace(/<\/?[^>]+(>|$)/g, '').trim()) {
                msg.textContent = 'Opis jest wymagany.';
                msg.style.color = '#b91c1c';
                return;
              }

              msg.textContent = 'Wysyłanie...';

              var fd = new FormData(form);
              fd.append('action', 'jg_submit_point');
              fd.append('_ajax_nonce', CFG.nonce);

              // Set category based on type
              var selectedType = fd.get('type');
              if (selectedType === 'miejsce' && placeCategorySelect && placeCategorySelect.value) {
                fd.set('category', placeCategorySelect.value);
              } else if (selectedType === 'ciekawostka' && curiosityCategorySelect && curiosityCategorySelect.value) {
                fd.set('category', curiosityCategorySelect.value);
              }
              // For zgloszenie, the category is already in the form as 'category'

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
                // Invalidate tag cache so newly added tags appear in suggestions immediately
                cachedAllTags = null;
                cachedAllTagsTime = 0;

                // Update level/XP bar immediately if server returned XP data
                if (j.data && j.data.xp_result) { updateLevelDisplay(j.data.xp_result); }

                // For admin/mod: point is published immediately — shoot confetti at pin
                var _fabLat = j.data && j.data.lat;
                var _fabLng = j.data && j.data.lng;
                if (CFG.isAdmin && j.data && j.data.status === 'publish' && _fabLat && _fabLng) {
                  setTimeout(function(lat, lng) {
                    return function() {
                      shootMapMarkerConfetti(lat, lng,
                        ['#10b981', '#34d399', '#6ee7b7', '#fbbf24', '#ffffff', '#f0fdf4'], 44);
                    };
                  }(_fabLat, _fabLng), 600);
                }

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

      // =========================================================
      // REAL-TIME LEVEL / XP BAR UPDATE
      // =========================================================

      /**
       * Update the top-bar level badge and XP progress bar without page reload.
       * Called after any AJAX action that returns xp_result.
       *
       * @param {Object} xpResult - Response from award_xp():
       *   { xp_gained, new_xp, new_level, old_level, level_up,
       *     progress, xp_in_level, xp_needed, level_tier }
       */
      function updateLevelDisplay(xpResult) {
        // xp_gained may be negative (XP deduction); bail only on null/0/undefined
        if (!xpResult || xpResult.xp_gained === null || xpResult.xp_gained === undefined || xpResult.xp_gained === 0) return;

        var levelEl  = document.querySelector('.jg-top-bar-level');
        var numEl    = document.querySelector('.jg-top-bar-level-num');
        var fillEl   = document.querySelector('.jg-top-bar-xp-fill');

        if (!levelEl || !numEl || !fillEl) return;

        var isDeduction = xpResult.xp_gained < 0;

        // Update level number text
        numEl.textContent = 'Poz. ' + xpResult.new_level;

        // Update progress bar
        fillEl.style.width = xpResult.progress + '%';

        // Update prestige tier class (remove old jg-level-* class, add new one)
        var classes = levelEl.className.split(' ');
        var filtered = classes.filter(function(c) { return c.indexOf('jg-level-') !== 0; });
        filtered.push('jg-level-' + xpResult.level_tier);
        levelEl.className = filtered.join(' ');

        // Update title tooltip
        levelEl.title = 'Poziom ' + xpResult.new_level + ' — ' + xpResult.xp_in_level + '/' + xpResult.xp_needed + ' XP do następnego poziomu';

        // Show floating indicator below the level badge.
        // Positive: yellow "+N XP"; Negative: red "-N XP".
        var badgeRect = levelEl.getBoundingClientRect();
        var indicator = document.createElement('span');
        indicator.className = 'jg-xp-gain-indicator' + (isDeduction ? ' jg-xp-gain-indicator--negative' : '');
        indicator.textContent = (isDeduction ? '' : '+') + xpResult.xp_gained + ' XP';
        indicator.style.left = (badgeRect.left + badgeRect.width / 2) + 'px';
        indicator.style.top  = (badgeRect.bottom + 4) + 'px';
        document.body.appendChild(indicator);

        // Animate level badge on level-up (not on deduction)
        if (xpResult.level_up && !isDeduction) {
          levelEl.classList.add('jg-level-levelup-pulse');
          // Confetti burst around the level badge, colors matching new prestige tier
          shootPrestigeConfetti(levelEl, xpResult.level_tier, 48);
          setTimeout(function() {
            levelEl.classList.remove('jg-level-levelup-pulse');
          }, 800);
        }

        // Remove floating indicator after animation completes
        setTimeout(function() {
          if (indicator.parentNode) {
            indicator.parentNode.removeChild(indicator);
          }
        }, 1550);
      }

      // Expose for external use
      window.jgUpdateLevelDisplay = updateLevelDisplay;

      // =========================================================
      // LEVEL-UP & ACHIEVEMENT NOTIFICATION SYSTEM
      // =========================================================
      var _notificationsShowing = false;

      function checkLevelNotifications() {
        if (!CFG.nonce) return; // Not logged in
        if (_notificationsShowing) return; // Already displaying notifications

        api('jg_check_level_notifications', {}).then(function(data) {
          if (!data || !data.notifications || data.notifications.length === 0) return;

          _notificationsShowing = true;

          // Mark all fetched notifications as seen immediately so they won't
          // be fetched again (e.g. by the periodic interval or on next page load)
          var ids = data.notifications.map(function(n) { return n.id; }).join(',');
          api('jg_mark_notifications_seen', { notification_ids: ids }).catch(function() {});

          // Now show them one by one
          showNextNotification(data.notifications, 0);
        }).catch(function() {});
      }

      function showNextNotification(notifications, index) {
        if (index >= notifications.length) {
          _notificationsShowing = false;
          return;
        }
        var n = notifications[index];

        if (n.type === 'level_up') {
          showLevelUpModal(n, function() {
            dismissNotification(n.id);
            showNextNotification(notifications, index + 1);
          });
        } else if (n.type === 'achievement') {
          showAchievementModal(n, function() {
            dismissNotification(n.id);
            showNextNotification(notifications, index + 1);
          });
        } else {
          dismissNotification(n.id);
          showNextNotification(notifications, index + 1);
        }
      }

      function dismissNotification(id) {
        api('jg_dismiss_level_notification', { notification_id: id }).catch(function() {});
      }

      function showLevelUpModal(notification, onClose) {
        var d = notification.data;
        var modalAlert = document.getElementById('jg-modal-alert');
        if (!modalAlert) { onClose(); return; }

        var content = modalAlert.querySelector('.jg-modal-message-content');
        var buttons = modalAlert.querySelector('.jg-modal-message-buttons');

        content.innerHTML = '<div class="jg-levelup-modal">' +
          '<div class="jg-levelup-icon">⬆️</div>' +
          '<div class="jg-levelup-title">Nowy poziom!</div>' +
          '<div class="jg-levelup-level">' + d.new_level + '</div>' +
          '<div class="jg-levelup-subtitle">Gratulacje! Awansowałeś z poziomu ' + d.old_level + ' na poziom ' + d.new_level + '!</div>' +
          '</div>';

        buttons.innerHTML = '<button class="jg-btn jg-btn--primary" id="jg-levelup-ok" style="padding:10px 32px;background:#667eea;border:none;color:#fff;border-radius:8px;font-weight:600;cursor:pointer;font-size:15px">Świetnie!</button>';

        modalAlert.classList.add('active');

        document.getElementById('jg-levelup-ok').onclick = function() {
          modalAlert.classList.remove('active');
          if (onClose) onClose();
        };

        modalAlert.onclick = function(e) {
          if (e.target === modalAlert) {
            modalAlert.classList.remove('active');
            if (onClose) onClose();
          }
        };
      }

      function showAchievementModal(notification, onClose) {
        var d = notification.data;
        var modalAlert = document.getElementById('jg-modal-alert');
        if (!modalAlert) { onClose(); return; }

        var rarityColors = {
          'common': '#d1d5db',
          'uncommon': '#10b981',
          'rare': '#3b82f6',
          'epic': '#8b5cf6',
          'legendary': '#f59e0b'
        };
        var rarityLabels = {
          'common': 'Zwykłe',
          'uncommon': 'Niepospolite',
          'rare': 'Rzadkie',
          'epic': 'Epickie',
          'legendary': 'Legendarne'
        };
        var color = rarityColors[d.rarity] || rarityColors.common;
        var label = rarityLabels[d.rarity] || 'Zwykłe';

        var content = modalAlert.querySelector('.jg-modal-message-content');
        var buttons = modalAlert.querySelector('.jg-modal-message-buttons');

        content.innerHTML = '<div class="jg-achievement-modal">' +
          '<div class="jg-achievement-modal-icon" style="border-color:' + color + ';box-shadow:0 0 20px ' + color + ', 0 0 40px ' + color + '44">' +
          '<span style="font-size:48px">' + esc(d.icon) + '</span></div>' +
          '<div class="jg-achievement-modal-title">Nowe osiągnięcie!</div>' +
          '<div class="jg-achievement-modal-name" style="color:' + color + '">' + esc(d.name) + '</div>' +
          '<div class="jg-achievement-modal-desc">' + esc(d.description) + '</div>' +
          '<div class="jg-achievement-modal-rarity" style="color:' + color + '">' + label + '</div>' +
          '</div>';

        buttons.innerHTML = '<button class="jg-btn jg-btn--primary" id="jg-ach-ok" style="padding:10px 32px;background:' + color + ';border:none;color:#fff;border-radius:8px;font-weight:600;cursor:pointer;font-size:15px">Wspaniale!</button>';

        modalAlert.classList.add('active');

        document.getElementById('jg-ach-ok').onclick = function() {
          modalAlert.classList.remove('active');
          if (onClose) onClose();
        };

        modalAlert.onclick = function(e) {
          if (e.target === modalAlert) {
            modalAlert.classList.remove('active');
            if (onClose) onClose();
          }
        };
      }

      // Check for pending notifications on page load (after 2 second delay)
      setTimeout(checkLevelNotifications, 2000);

      // Also check periodically (every 60 seconds)
      setInterval(checkLevelNotifications, 60000);

      // Export map and openDetails as global functions for use by sidebar widget
      window.jgMap = map;
      window.openDetails = openDetails;
      window.openUserModal = openUserModal;

      // Export zoom-to-point function for sidebar use
      // Zooms the map to given coordinates and shows a pulsing marker animation
      window.jgZoomToPoint = function(lat, lng) {
        map.setView([lat, lng], 19, { animate: true });

        // On mobile: scroll viewport to the map element
        if (window.innerWidth <= 768) {
          setTimeout(function() {
            var mapEl = document.getElementById('jg-map');
            if (mapEl) {
              mapEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
          }, 300);
        }

        // After zoom completes, add fast pulsing circle marker
        setTimeout(function() {
          var pulsingCircle = L.circle([lat, lng], {
            color: '#ef4444',
            fillColor: '#ef4444',
            fillOpacity: 0.3,
            radius: 12,
            weight: 3
          }).addTo(map);

          var pulseCount = 0;
          var maxPulses = 6;
          var pulseInterval = setInterval(function() {
            pulseCount++;
            if (pulseCount % 2 === 0) {
              pulsingCircle.setStyle({ fillOpacity: 0.3, opacity: 1 });
            } else {
              pulsingCircle.setStyle({ fillOpacity: 0.1, opacity: 0.4 });
            }
            if (pulseCount >= maxPulses) {
              clearInterval(pulseInterval);
              setTimeout(function() {
                map.removeLayer(pulsingCircle);
              }, 250);
            }
          }, 250);
        }, 600);
      };

    } catch (e) {
      showError('Błąd: ' + e.message);
    }
  }
})(jQuery);
