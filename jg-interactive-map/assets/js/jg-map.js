/**
 * JG Interactive Map - Frontend JavaScript
 * Version: 3.0.0
 */

// Save native history.replaceState before GTM (GT-TX2S859S / GTM-5PFNSKQH) patches it.
// GTM wraps replaceState to detect URL changes and fires GA4 page_view via its
// History Change trigger — causing a duplicate alongside our manual gtag call.
// By capturing the native function here (synchronously, before GTM's async script
// runs) we can update the browser URL bar without GTM detecting the change.
// Our manual gtag('event','page_view') in openDetailsModalContent remains the
// sole source of pin page_view events.
var _jgNativeReplaceState = (window.history && window.history.replaceState)
  ? window.history.replaceState.bind(window.history)
  : null;

(function($) {
  'use strict';

  // Debug logging disabled for production
  function debugLog() {}
  function debugWarn() {}
  function debugError() {}

  // Parse emoji in a DOM element using Twemoji (cross-platform consistency)
  function parseEmoji(el) {
    if (window.twemoji && el) {
      twemoji.parse(el, { folder: 'svg', ext: '.svg' });
    }
  }

  // Quick check: does this element (or its subtree) contain any emoji text?
  // Avoids calling the full twemoji.parse on nodes that have no emoji at all
  // (e.g. Leaflet tile layers, plain text nodes, already-replaced img.emoji).
  var _emojiRe = /[\u{1F000}-\u{1FFFF}\u{2600}-\u{27BF}\u{2190}-\u{2BFF}]/u;
  function _hasEmojiText(el) {
    return el.textContent && _emojiRe.test(el.textContent);
  }

  // Watch for dynamically added content (popups, modals, filters, notifications)
  // and automatically replace emoji with Twemoji images.
  // Parsing each added node immediately (no debounce) prevents the flash caused
  // by the browser painting text emoji before Twemoji replaces them.
  // ===== SECTION: UTILITIES & HELPERS =====
  function setupEmojiObserver() {
    if (!window.MutationObserver || !window.twemoji) return;
    var observer = new MutationObserver(function(mutations) {
      for (var i = 0; i < mutations.length; i++) {
        var added = mutations[i].addedNodes;
        for (var j = 0; j < added.length; j++) {
          var node = added[j];
          if (node.nodeType === 1 && _hasEmojiText(node)) {
            parseEmoji(node);
          }
        }
      }
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

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
  function isMenuCategory(cat) {
    var cats = (window.JG_MAP_CFG && JG_MAP_CFG.menuCategories) || [];
    return cats.indexOf(cat) !== -1;
  }

  function isOfferingsCategory(cat) {
    var cats = (window.JG_MAP_CFG && JG_MAP_CFG.offeringsCategories) || {};
    return Object.prototype.hasOwnProperty.call(cats, cat);
  }

  function getOfferingsLabel(cat) {
    var cats = (window.JG_MAP_CFG && JG_MAP_CFG.offeringsCategories) || {};
    return cats[cat] || 'Oferta';
  }

  function isPriceRangeCategory(cat) {
    var cats = (window.JG_MAP_CFG && JG_MAP_CFG.priceRangeCategories) || [];
    return cats.indexOf(cat) !== -1;
  }

  function isServesCuisineCategory(cat) {
    var cats = (window.JG_MAP_CFG && JG_MAP_CFG.servesCuisineCategories) || [];
    return cats.indexOf(cat) !== -1;
  }

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

  // Register tile-caching Service Worker (intercepts maptiler/arcgis requests)
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
      // /jg-tile-sw.js is a WordPress rewrite rule → plugin's tile-sw.js
      // with Service-Worker-Allowed: / so it can intercept all tile requests
      navigator.serviceWorker.register('/jg-tile-sw.js', { scope: '/' }).catch(function(e) {
        console.warn('[JG Map] Service Worker registration failed:', e);
      });

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
  // BODY SCROLL LOCK (when any modal is open)
  // ====================================
  function lockBodyScroll() {
    var scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
    if (scrollbarWidth > 0) {
      document.body.style.paddingRight = scrollbarWidth + 'px';
    }
    document.body.classList.add('jg-modal-open');
  }
  function unlockBodyScroll() {
    var bgs = document.querySelectorAll('.jg-modal-bg');
    for (var i = 0; i < bgs.length; i++) {
      if (bgs[i].style.display === 'flex' || bgs[i].classList.contains('active')) return;
    }
    document.body.classList.remove('jg-modal-open');
    document.body.style.paddingRight = '';
  }

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
      lockBodyScroll();

      var okBtn = document.getElementById('jg-alert-ok');
      okBtn.onclick = function() {
        modal.style.display = 'none';
        unlockBodyScroll();
        resolve();
      };

      // Close on background click
      modal.onclick = function(e) {
        if (e.target === modal) {
          modal.style.display = 'none';
          unlockBodyScroll();
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
      lockBodyScroll();

      var yesBtn = document.getElementById('jg-confirm-yes');
      var noBtn = document.getElementById('jg-confirm-no');

      yesBtn.onclick = function() {
        modal.style.display = 'none';
        unlockBodyScroll();
        resolve(true);
      };

      noBtn.onclick = function() {
        modal.style.display = 'none';
        unlockBodyScroll();
        resolve(false);
      };

      // Close on background click = cancel
      modal.onclick = function(e) {
        if (e.target === modal) {
          modal.style.display = 'none';
          unlockBodyScroll();
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
      lockBodyScroll();

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
        unlockBodyScroll();
        resolve(reason || '');
      };

      noBtn.onclick = function() {
        modal.style.display = 'none';
        unlockBodyScroll();
        resolve(null);
      };

      // Close on background click = cancel
      modal.onclick = function(e) {
        if (e.target === modal) {
          modal.style.display = 'none';
          unlockBodyScroll();
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

    function doHide() {
      // Signal coordinator: map is ready.
      // The loader is hidden by _jgLoad._check() only after BOTH map AND sidebar are ready.
      if (window._jgLoad) window._jgLoad.setMap();
    }

    if (remaining > 0) {
      setTimeout(doHide, remaining);
    } else {
      doHide();
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

  // Coordinated loading: reveal sidebar only after both map AND sidebar data are ready.
  // jg-sidebar.js calls window._jgLoad.setSidebar() when its data is loaded.
  // hideLoading() below calls window._jgLoad.setMap() when map is ready.
  // If no sidebar element exists on the page, sidebar is auto-marked ready.
  window._jgLoad = {
    map: false,
    sidebar: !document.getElementById('jg-map-sidebar'),
    _check: function() {
      if (this.map && this.sidebar) {
        // Reveal sidebar
        var s = document.getElementById('jg-map-sidebar');
        if (s) s.style.opacity = '1';
        // Fade out and hide the full-screen loader
        if (loadingEl) {
          loadingEl.style.opacity = '0';
          // Restore page scroll that was locked during loading
          document.documentElement.style.removeProperty('overflow');
          setTimeout(function() {
            if (loadingEl) loadingEl.style.display = 'none';
          }, 300);
        }
      }
    },
    setMap: function() { this.map = true; this._check(); },
    setSidebar: function() { this.sidebar = true; this._check(); }
  };

  wait(init, 100);

  // ===== SECTION: MAP INIT =====
  function init() {
    try {
      // Static content (top bar, notifications) was already parsed by the inline
      // Twemoji script that runs immediately after twemoji.min.js loads.
      // Start observer here to catch all dynamic content from this point on.
      setupEmojiObserver();

      // Move modals to <body> so they are in the root stacking context.
      // Without this, parent containers (e.g. Elementor sections) may create
      // their own stacking context, capping the effective z-index of modals
      // below the sticky nav bar regardless of the declared z-index value.
      document.querySelectorAll('.jg-modal-bg').forEach(function(el) {
        document.body.appendChild(el);
      });

      var CFG = window.JG_MAP_CFG || {};
      if (!CFG.ajax || !CFG.nonce) {
        showError('Brak konfiguracji JG_MAP_CFG');
        return;
      }

      if (!CFG.isLoggedIn) {
        var _jgEngage = {
          shown: !!sessionStorage.getItem('jg_join_modal_shown'),
          placesViewed: 0,
          trigger: function(reason) {
            if (this.shown) return;
            this.shown = true;
            sessionStorage.setItem('jg_join_modal_shown', '1');
            if (typeof window.openJoinModal === 'function') window.openJoinModal({trigger: reason});
          }
        };
        window._jgGuestEngagement = _jgEngage;
        setTimeout(function() { window._jgGuestEngagement && window._jgGuestEngagement.trigger('timer'); }, 20000);
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
      var modalPlaceContact = document.getElementById('jg-place-contact-modal');
      var lightbox = document.getElementById('jg-map-lightbox');

      // Stats modal refresh interval
      var statsRefreshInterval = null;

      if (!elMap) {
        showError('Nie znaleziono #jg-map');
        return;
      }

      if ((elMap.offsetHeight || 0) < 50) elMap.style.minHeight = '520px';

      // ====================================
      // Custom Top Bar: Profile
      // ====================================
      var editProfileBtn = document.getElementById('jg-edit-profile-btn');

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

      function openLoginModal() {
        if (typeof window.openJoinModal === 'function') {
          window.openJoinModal({view: 'login'});
        }
      }

      var authBtn = document.getElementById('jg-auth-btn');
      if (authBtn && !authBtn.jgHandlerAttached) {
        authBtn.addEventListener('click', function() {
          if (typeof window.openJoinModal === 'function') window.openJoinModal({view: 'register'});
        });
        authBtn.jgHandlerAttached = true;
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

      // ── Duplicate-detection helpers ──────────────────────────────────────────
      function jgNormStr(s) {
        return (s || '').toLowerCase()
          .replace(/ą/g,'a').replace(/ć/g,'c').replace(/ę/g,'e')
          .replace(/ł/g,'l').replace(/ń/g,'n').replace(/ó/g,'o')
          .replace(/ś/g,'s').replace(/ź/g,'z').replace(/ż/g,'z')
          .replace(/[^a-z0-9\s]/g,' ').replace(/\s+/g,' ').trim();
      }
      var _jgStop = {i:1,w:1,z:1,na:1,do:1,ul:1,al:1,pl:1,przy:1,nad:1,pod:1,za:1,o:1,a:1,ze:1,po:1,dla:1,nr:1,ten:1,ta:1,to:1,jest:1,sie:1};
      function jgWordsOf(norm) {
        return norm.split(' ').filter(function(w){ return w.length >= 2 && !_jgStop[w]; });
      }
      function jgStripHtml(h) {
        return (h || '').replace(/<[^>]+>/g,' ').replace(/\s+/g,' ').trim();
      }
      function jgJaccard(wa, wb) {
        if (!wa.length || !wb.length) return 0;
        var sb = {};
        wb.forEach(function(w){ sb[w]=1; });
        var inter = wa.filter(function(w){ return sb[w]; }).length;
        var union = wa.length + wb.length - inter;
        return union <= 0 ? 0 : inter / union;
      }
      function jgHaversineM(lat1, lng1, lat2, lng2) {
        var R = 6371000, toR = Math.PI/180;
        var dLat = (lat2-lat1)*toR, dLng = (lng2-lng1)*toR;
        var a = Math.sin(dLat/2)*Math.sin(dLat/2) +
                Math.cos(lat1*toR)*Math.cos(lat2*toR)*Math.sin(dLng/2)*Math.sin(dLng/2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
      }
      function jgFindDuplicates(title, content, lat, lng, type) {
        var DIST_MAX   = 150;   // metry — twardy limit zasięgu
        var JAC_TITLE  = 0.50;  // próg Jaccarda dla tytułu
        var MIN_COMMON = 2;     // min. wspólnych słów znaczących
        var CONT_SIM   = 0.28;  // próg podobieństwa treści (fallback)
        var normT = jgNormStr(title);
        var wT    = jgWordsOf(normT);
        var wC    = jgWordsOf(jgNormStr(jgStripHtml(content)));
        var hits  = [];
        (ALL || []).forEach(function(p) {
          if (p.type !== type)      return;
          if (p.status === 'trash') return;
          var dist = jgHaversineM(lat, lng, p.lat, p.lng);
          if (dist > DIST_MAX) return;
          var wP     = jgWordsOf(jgNormStr(p.title));
          var jac    = jgJaccard(wT, wP);
          var common = wT.filter(function(w){ return wP.indexOf(w) >= 0; }).length;
          var titleOk = (common >= MIN_COMMON && jac >= JAC_TITLE) || jac >= 0.80;
          var wPc  = jgWordsOf(jgNormStr(p.excerpt || jgStripHtml(p.content)));
          var cSim = (dist <= 100 && wC.length >= 5) ? jgJaccard(wC, wPc) : 0;
          if (!titleOk && cSim < CONT_SIM) return;
          hits.push({ point: p, dist: Math.round(dist), score: jac*0.7 + cSim*0.3 });
        });
        return hits.sort(function(a,b){ return b.score-a.score; }).slice(0,3);
      }
      function jgShowDuplicateWarning(candidates, addModal, onContinue) {
        var t = candidates[0] && candidates[0].point && candidates[0].point.type;
        var subj = t === 'ciekawostka' ? 'ciekawostkę' : 'miejsce';
        var listHtml = candidates.map(function(c) {
          var p = c.point;
          var addr = p.address ? ' · ' + esc(p.address.split(',')[0]) : '';
          return '<div style="border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin-bottom:8px;background:#f9fafb">' +
            '<div style="font-weight:700;font-size:14px;color:#111;margin-bottom:4px">' + esc(p.title) + '</div>' +
            '<div style="font-size:12px;color:#6b7280;margin-bottom:8px">📍 ~' + c.dist + ' m' + addr + '</div>' +
            '<button data-dup-pid="' + p.id + '" style="padding:5px 12px;background:#8d2324;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600">Przejdź do istniejącego</button>' +
          '</div>';
        }).join('');
        var overlay = document.createElement('div');
        overlay.id = 'jg-dup-overlay';
        overlay.style.cssText = 'position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.55)';
        overlay.innerHTML =
          '<div style="background:#fff;border-radius:12px;width:min(460px,92vw);max-height:80vh;overflow-y:auto;box-shadow:0 8px 40px rgba(0,0,0,0.28)">' +
            '<div style="background:#d97706;color:#fff;padding:16px 20px;border-radius:12px 12px 0 0;display:flex;align-items:center;gap:10px">' +
              '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' +
              '<strong style="font-size:15px">Podobny wpis już istnieje</strong>' +
            '</div>' +
            '<div style="padding:16px 20px">' +
              '<p style="margin:0 0 14px;font-size:14px;color:#374151">Znaleźliśmy podobn' + (t === 'miejsce' ? 'e <strong>miejsce</strong>' : 'ą <strong>' + subj + '</strong>') + ' w pobliżu. Sprawdź czy to nie ten sam obiekt — zamiast tworzyć duplikat możesz edytować istniejący wpis.</p>' +
              listHtml +
            '</div>' +
            '<div style="padding:12px 20px 16px;border-top:1px solid #e5e7eb;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap">' +
              '<button id="jg-dup-cancel" style="padding:8px 16px;background:#fff;color:#374151;border:1.5px solid #d1d5db;border-radius:7px;cursor:pointer;font-size:13px;font-weight:600">Anuluj</button>' +
              '<button id="jg-dup-continue" style="padding:8px 16px;background:#374151;color:#fff;border:none;border-radius:7px;cursor:pointer;font-size:13px;font-weight:600">Mimo to dodaj nowe</button>' +
            '</div>' +
          '</div>';
        document.body.appendChild(overlay);
        function _closeWarn() { if (overlay.parentNode) overlay.parentNode.removeChild(overlay); }
        overlay.querySelector('#jg-dup-cancel').onclick = _closeWarn;
        overlay.querySelector('#jg-dup-continue').onclick = function() { _closeWarn(); onContinue(); };
        overlay.querySelectorAll('[data-dup-pid]').forEach(function(btn) {
          btn.onclick = function() {
            var pid = parseInt(btn.getAttribute('data-dup-pid'), 10);
            _closeWarn();
            if (addModal) addModal.style.display = 'none';
            window.location.hash = '#point-' + pid;
          };
        });
      }
      // ── koniec helpers duplicate-detection ──────────────────────────────────

      // ===== OPENING HOURS PICKER =====
      var OH_DAYS = [
        { key: 'Mo', label: 'Pon' },
        { key: 'Tu', label: 'Wt'  },
        { key: 'We', label: 'Śr'  },
        { key: 'Th', label: 'Czw' },
        { key: 'Fr', label: 'Pt'  },
        { key: 'Sa', label: 'Sob' },
        { key: 'Su', label: 'Niedz' }
      ];

      // Build time <select> options in 15-min steps 00:00–23:45, plus 00:00
      var _timeOptionsBase = null;
      function buildTimeOptions(selected) {
        if (!_timeOptionsBase) {
          var parts = [];
          for (var h = 0; h < 24; h++) {
            for (var m = 0; m < 60; m += 15) {
              var mm = m === 0 ? '00' : (m < 10 ? '0' + m : '' + m);
              var t = (h < 10 ? '0' : '') + h + ':' + mm;
              parts.push('<option value="' + t + '">' + t + '</option>');
            }
          }
          parts.push('<option value="00:00">00:00</option>');
          _timeOptionsBase = parts.join('');
        }
        if (!selected) return _timeOptionsBase;
        return _timeOptionsBase.replace('value="' + selected + '"', 'value="' + selected + '" selected');
      }

      // Parse opening_hours string (one line per day: "Mo 09:00-17:00") into map
      function parseOpeningHours(val) {
        var parsed = {};
        if (!val) return parsed;
        val.split('\n').forEach(function(line) {
          var m = line.trim().match(/^(Mo|Tu|We|Th|Fr|Sa|Su)\s+(\d{2}:\d{2})[-–](\d{2}:\d{2})$/);
          if (m) parsed[m[1]] = { open: m[2], close: m[3] };
        });
        return parsed;
      }

      // Build price range SELECT HTML
      function buildPriceRangeSelectHtml(prefix, currentValue) {
        var options = [
          { val: '', label: '— brak —' },
          { val: '$', label: '$ – bardzo tanie' },
          { val: '$$', label: '$$ – umiarkowane' },
          { val: '$$$', label: '$$$ – droższe' },
          { val: '$$$$', label: '$$$$ – ekskluzywne' }
        ];
        var html = '<select name="price_range" id="' + prefix + '-price-range-select" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">';
        options.forEach(function(o) {
          html += '<option value="' + o.val + '"' + (currentValue === o.val ? ' selected' : '') + '>' + o.label + '</option>';
        });
        html += '</select>';
        return html;
      }

      // Build the picker HTML (hidden input + 7 day rows)
      function buildOpeningHoursPickerHtml(prefix, currentValue) {
        var parsed = parseOpeningHours(currentValue);
        var rows = OH_DAYS.map(function(d) {
          var active = !!parsed[d.key];
          var is24h  = active && parsed[d.key].open === '00:00' && parsed[d.key].close === '24:00';
          var openT  = (parsed[d.key] && parsed[d.key].open  && !is24h) ? parsed[d.key].open  : '09:00';
          var closeT = (parsed[d.key] && parsed[d.key].close && !is24h) ? parsed[d.key].close : '17:00';
          return '<div class="jg-oh-row" style="display:flex;align-items:center;gap:6px;padding:3px 0">' +
            '<label style="display:flex;align-items:center;gap:5px;min-width:70px;cursor:pointer;font-weight:' + (active ? '600' : '400') + ';color:' + (active ? '#111' : '#9ca3af') + '">' +
            '<input type="checkbox" class="jg-oh-check" data-day="' + d.key + '" data-prefix="' + prefix + '"' + (active ? ' checked' : '') + ' style="cursor:pointer">' +
            '<span>' + d.label + '</span></label>' +
            '<div class="jg-oh-times-' + d.key + '" style="display:flex;align-items:center;gap:4px;' + (!active || is24h ? 'opacity:0.3;pointer-events:none' : '') + '">' +
            '<select class="jg-oh-open" data-day="' + d.key + '" data-prefix="' + prefix + '" style="padding:3px 4px;border:1px solid #ddd;border-radius:5px;font-size:13px">' + buildTimeOptions(openT) + '</select>' +
            '<span style="color:#6b7280;font-size:13px">–</span>' +
            '<select class="jg-oh-close" data-day="' + d.key + '" data-prefix="' + prefix + '" style="padding:3px 4px;border:1px solid #ddd;border-radius:5px;font-size:13px">' + buildTimeOptions(closeT) + '</select>' +
            '</div>' +
            '<label class="jg-oh-allday" style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:12px;color:#6b7280;white-space:nowrap;' + (!active ? 'opacity:0.3;pointer-events:none' : '') + '">' +
            '<input type="checkbox" class="jg-oh-allday-check" data-day="' + d.key + '"' + (is24h ? ' checked' : '') + ' style="cursor:pointer">' +
            '<span>Cała doba</span></label>' +
            '<span class="jg-oh-closed-label" style="font-size:12px;color:#9ca3af;' + (active ? 'display:none' : '') + '">nieczynne</span>' +
            '</div>';
        }).join('');

        return '<div class="jg-oh-picker" id="' + prefix + '-oh-picker" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:10px 12px">' +
          rows +
          '<input type="hidden" name="opening_hours" id="' + prefix + '-oh-hidden">' +
          '</div>';
      }

      // Init interactivity for the picker; returns { syncHidden() }
      function initOpeningHoursPicker(prefix, container) {
        var picker = (container || document).querySelector('#' + prefix + '-oh-picker');
        if (!picker) return null;

        function syncHidden() {
          var lines = [];
          OH_DAYS.forEach(function(d) {
            var check = picker.querySelector('.jg-oh-check[data-day="' + d.key + '"]');
            if (check && check.checked) {
              var alldayChk = picker.querySelector('.jg-oh-allday-check[data-day="' + d.key + '"]');
              if (alldayChk && alldayChk.checked) {
                lines.push(d.key + ' 00:00-24:00');
              } else {
                var openSel  = picker.querySelector('.jg-oh-open[data-day="' + d.key + '"]');
                var closeSel = picker.querySelector('.jg-oh-close[data-day="' + d.key + '"]');
                var openVal  = openSel  ? openSel.value  : '09:00';
                var closeVal = closeSel ? closeSel.value : '17:00';
                lines.push(d.key + ' ' + openVal + '-' + closeVal);
              }
            }
          });
          var hidden = picker.querySelector('#' + prefix + '-oh-hidden');
          if (hidden) hidden.value = lines.join('\n');
        }

        // Checkbox toggle: enable/disable times row
        picker.querySelectorAll('.jg-oh-check').forEach(function(check) {
          check.addEventListener('change', function() {
            var day = this.getAttribute('data-day');
            var timesDiv = picker.querySelector('.jg-oh-times-' + day);
            var closedLabel = this.closest('.jg-oh-row').querySelector('.jg-oh-closed-label');
            var label = this.closest('label');
            var alldayLabel = this.closest('.jg-oh-row').querySelector('.jg-oh-allday');
            var alldayChk = this.closest('.jg-oh-row').querySelector('.jg-oh-allday-check');
            if (this.checked) {
              var alldayOn = alldayChk && alldayChk.checked;
              if (timesDiv) { timesDiv.style.opacity = alldayOn ? '0.3' : '1'; timesDiv.style.pointerEvents = alldayOn ? 'none' : ''; }
              if (closedLabel) closedLabel.style.display = 'none';
              if (label) { label.style.fontWeight = '600'; label.style.color = '#111'; }
              if (alldayLabel) { alldayLabel.style.opacity = ''; alldayLabel.style.pointerEvents = ''; }
            } else {
              if (timesDiv) { timesDiv.style.opacity = '0.3'; timesDiv.style.pointerEvents = 'none'; }
              if (closedLabel) closedLabel.style.display = '';
              if (label) { label.style.fontWeight = '400'; label.style.color = '#9ca3af'; }
              if (alldayLabel) { alldayLabel.style.opacity = '0.3'; alldayLabel.style.pointerEvents = 'none'; }
            }
            syncHidden();
          });
        });

        // Cała doba toggle: hide/show time selects
        picker.querySelectorAll('.jg-oh-allday-check').forEach(function(alldayChk) {
          alldayChk.addEventListener('change', function() {
            var day = this.getAttribute('data-day');
            var timesDiv = picker.querySelector('.jg-oh-times-' + day);
            if (this.checked) {
              if (timesDiv) { timesDiv.style.opacity = '0.3'; timesDiv.style.pointerEvents = 'none'; }
            } else {
              if (timesDiv) { timesDiv.style.opacity = '1'; timesDiv.style.pointerEvents = ''; }
            }
            syncHidden();
          });
        });

        // Sync on any select change
        picker.querySelectorAll('.jg-oh-open, .jg-oh-close').forEach(function(sel) {
          sel.addEventListener('change', syncHidden);
        });

        // Populate hidden on init
        syncHidden();

        return { syncHidden: syncHidden };
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
            '<span class="jg-rte-sep"></span>' +
            '<button type="button" data-cmd="markSection" title="Oznacz zaznaczony fragment jako wymagający uzupełnienia" class="jg-rte-btn">&#9888; Zachęta</button>' +
            '<button type="button" data-cmd="unmarkSection" title="Usuń oznaczenie zachęty z fragmentu" class="jg-rte-btn">&#10006; Usuń zachętę</button>' +
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
            '<input type="text" id="' + id + '-input" class="jg-tags-input" placeholder="Wpisz tag i naciśnij Enter lub , (max 5)..." autocomplete="off" maxlength="30">' +
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

          if (e.key === 'Enter') {
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

        input.addEventListener('input', function() {
          var val = input.value;
          if (val.indexOf(',') !== -1) {
            var parts = val.split(',');
            var last = parts.pop();
            parts.forEach(function(p) {
              p = p.trim();
              if (p) addTag(p);
            });
            input.value = last;
            if (!last) hideSuggestions();
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

        // Prevent editor from losing focus when clicking toolbar buttons (preserves selection)
        toolbar.addEventListener('mousedown', function(e) {
          if (e.target.closest('.jg-rte-btn')) e.preventDefault();
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

          if (cmd === 'markSection') {
            var selMark = window.getSelection();
            if (!selMark.rangeCount || selMark.isCollapsed) return;
            var rangeMark = selMark.getRangeAt(0);
            if (!editor.contains(rangeMark.commonAncestorContainer)) return;
            var fragMark = rangeMark.extractContents();
            // Apply class directly to all block elements
            fragMark.querySelectorAll('ul,ol,li,p,div,h1,h2,h3,h4,h5,h6,blockquote,table,tr,td,th').forEach(function(el) {
              el.classList.add('jg-section-incomplete');
            });
            // Use div wrapper when content has block elements (span wrapping blocks is invalid HTML)
            var hasBlocksMark = fragMark.querySelector('ul,ol,li,p,div,h1,h2,h3,h4,h5,h6,blockquote,table,tr,td,th') !== null;
            var wrapperMark = document.createElement(hasBlocksMark ? 'div' : 'span');
            wrapperMark.className = 'jg-section-incomplete';
            wrapperMark.appendChild(fragMark);
            rangeMark.insertNode(wrapperMark);
            selMark.collapse(wrapperMark, wrapperMark.childNodes.length);
            syncContent();
            return;
          }

          if (cmd === 'unmarkSection') {
            var selUnmark = window.getSelection();
            var nodeUnmark = null;
            if (selUnmark.rangeCount > 0) {
              nodeUnmark = selUnmark.getRangeAt(0).commonAncestorContainer;
              if (nodeUnmark.nodeType === 3) nodeUnmark = nodeUnmark.parentNode;
            }
            if (!nodeUnmark) return;
            // Find the TOPMOST .jg-section-incomplete ancestor within the editor
            // (not just the closest — nested elements like ul>li all have the class)
            var topUnmark = null;
            var currUnmark = nodeUnmark;
            while (currUnmark && currUnmark !== editor) {
              if (currUnmark.classList && currUnmark.classList.contains('jg-section-incomplete')) {
                topUnmark = currUnmark;
              }
              currUnmark = currUnmark.parentNode;
            }
            if (!topUnmark) return;
            // Remove class from all descendants first
            topUnmark.querySelectorAll('.jg-section-incomplete').forEach(function(el) {
              el.classList.remove('jg-section-incomplete');
            });
            var tagUnmark = topUnmark.tagName.toLowerCase();
            if (tagUnmark === 'span' || tagUnmark === 'div') {
              // Wrapper element added by markSection — unwrap it entirely
              var parentUnmark = topUnmark.parentNode;
              while (topUnmark.firstChild) {
                parentUnmark.insertBefore(topUnmark.firstChild, topUnmark);
              }
              parentUnmark.removeChild(topUnmark);
            } else {
              // Block element (ul, li, etc.) — just remove the class
              topUnmark.classList.remove('jg-section-incomplete');
            }
            syncContent();
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

      // Measure nav bar + footer positions; apply backdrop padding and modal
      // max-height so the modal sits symmetrically between nav and footer.
      function jgFitModal(bg, c) {
        if (!bg || !c) return;
        /* Both helpers defined in the inline PHP script (render_nav_bar).
           jgGetNavBottom — max bottom of all VISIBLE top-nav elements.
           jgGetFooterTop — top of any visible footer (innerHeight when none). */
        var navBottom = window.jgGetNavBottom ? window.jgGetNavBottom() : 52;
        var footerTop = window.jgGetFooterTop ? window.jgGetFooterTop() : window.innerHeight;
        var gap = window.innerWidth <= 768 ? 14 : 18;
        bg.style.paddingTop    = (navBottom + gap) + 'px';
        bg.style.paddingBottom = (window.innerHeight - footerTop + gap) + 'px';
        bg.style.paddingLeft   = '10px';
        bg.style.paddingRight  = '10px';
        if (!c.classList.contains('jg-lightbox')) {
          var available = footerTop - navBottom - gap * 2;
          c.style.maxHeight = Math.max(available, 100) + 'px';
        }
      }

      // ===== SECTION: MODAL HELPERS =====
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
        jgFitModal(bg, c);
        lockBodyScroll();

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


            // Use native replaceState (saved before GTM patches it) so GTM's
            // History Change trigger doesn't fire a duplicate GA4 page_view.
            if (_jgNativeReplaceState) {
              _jgNativeReplaceState(
                { pointId: point.id, pointSlug: point.slug, pointType: point.type },
                point.title || '',
                newUrl
              );
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
          c.style.maxHeight = '';
        }
        bg.style.paddingTop = bg.style.paddingBottom = bg.style.paddingLeft = bg.style.paddingRight = '';
        bg.style.display = 'none';

        // Reset URL to homepage when closing point detail modal
        if (bg.id === 'jg-map-modal-view') {
          if (_jgNativeReplaceState) {
            var currentPath = window.location.pathname;
            if (currentPath.match(/^\/(miejsce|ciekawostka|zgloszenie)\//)) {
              _jgNativeReplaceState({}, '', '/');
            }
          }
          // Also close lightbox if it was opened from within this modal
          if (lightbox && lightbox.style.display !== 'none') {
            var lc = qs('.jg-modal, .jg-lightbox', lightbox);
            if (lc) lc.className = lc.className.replace(/\bjg-modal--\w+/g, '');
            lightbox.style.display = 'none';
          }
        }
        unlockBodyScroll();
      }

      // Save current edit modal form state to sessionStorage (invoked on backdrop/Escape close).
      function saveEditModalState() {
        var form = qs('#edit-form', modalEdit);
        if (!form) return;
        var pointIdInput = qs('#edit-point-id', modalEdit);
        if (!pointIdInput) return;
        var editorEl = qs('#edit-rte-editor', form);
        var state = {
          pointId: parseInt(pointIdInput.value, 10),
          title: (qs('input[name="title"]', form) || {value: ''}).value,
          type: (qs('#edit-type-select', form) || {value: ''}).value,
          category: (qs('#edit-category-select', form) || {value: ''}).value,
          place_category: (qs('#edit-place-category-select', form) || {value: ''}).value,
          curiosity_category: (qs('#edit-curiosity-category-select', form) || {value: ''}).value,
          address: (qs('#edit-address-input', form) || {value: ''}).value,
          lat: (qs('#edit-lat-input', form) || {value: ''}).value,
          lng: (qs('#edit-lng-input', form) || {value: ''}).value,
          description: editorEl ? editorEl.innerHTML : '',
          tags: (qs('#edit-tags-hidden', form) || {value: ''}).value,
          website: (qs('#edit-website-input', form) || {value: ''}).value,
          phone: (qs('#edit-phone-input', form) || {value: ''}).value,
          contact_email: (qs('#edit-email-input', form) || {value: ''}).value,
          facebook_url: (qs('#edit-facebook-input', form) || {value: ''}).value,
          instagram_url: (qs('#edit-instagram-input', form) || {value: ''}).value,
          linkedin_url: (qs('#edit-linkedin-input', form) || {value: ''}).value,
          tiktok_url: (qs('#edit-tiktok-input', form) || {value: ''}).value,
          cta_enabled: !!(qs('#edit-cta-enabled-checkbox', form) || {}).checked,
          cta_type: ''
        };
        var ctaTypeRadios = form.querySelectorAll('input[name="cta_type"]');
        ctaTypeRadios.forEach(function(radio) {
          if (radio.checked) state.cta_type = radio.value;
        });
        try {
          sessionStorage.setItem('jg_edit_modal_state', JSON.stringify(state));
        } catch(e) {}
      }

      // Close regular modals by clicking their backdrop (lightbox handled separately below).
      // We track mousedown target to avoid closing when user merely drags text selection
      // starting inside the modal and releasing over the backdrop.
      [modalAdd, modalView, modalReport, modalReportsList, modalEdit, modalAuthor, modalStatus, modalRanking].forEach(function(bg) {
        if (!bg) return;
        var mouseDownOnBg = false;
        bg.addEventListener('mousedown', function(e) {
          mouseDownOnBg = (e.target === bg);
        });
        bg.addEventListener('click', function(e) {
          if (!(e.target === bg && mouseDownOnBg)) return;
          if (bg === modalAdd) {
            // Confirm before discarding new pin creation
            showConfirm(
              'Czy na pewno chcesz przerwać tworzenie nowego miejsca? Wszystkie wprowadzone dane zostaną utracone.',
              'Przerwij tworzenie?',
              'Tak, porzuć'
            ).then(function(confirmed) {
              if (confirmed) close(bg);
            });
          } else if (bg === modalEdit) {
            // Save form state so the user can resume, then close
            saveEditModalState();
            close(bg);
          } else {
            close(bg);
          }
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
          [modalView, modalReport, modalReportsList, modalAuthor, modalStatus, modalRanking, lightbox].forEach(close);
          if (modalAdd && modalAdd.style.display === 'flex') {
            showConfirm(
              'Czy na pewno chcesz przerwać tworzenie nowego miejsca? Wszystkie wprowadzone dane zostaną utracone.',
              'Przerwij tworzenie?',
              'Tak, porzuć'
            ).then(function(confirmed) {
              if (confirmed) close(modalAdd);
            });
          }
          if (modalEdit && modalEdit.style.display === 'flex') {
            saveEditModalState();
            close(modalEdit);
          }
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
        zoomControl: !isMobile, // On mobile, zoom is handled by the custom controls row
        scrollWheelZoom: !isMobile, // Disable scroll zoom on mobile
        dragging: true, // Always enable dragging (mobile: one finger pans map, two fingers zoom)
        minZoom: 12,
        maxZoom: 19,
        maxBounds: bounds,
        maxBoundsViscosity: 1.0,
        bounceAtZoomLimits: false, // Prevent elastic bounce at min/max zoom on mobile
        tap: isMobile, // Enable tap on mobile
        touchZoom: true, // Enable pinch zoom (two fingers) on mobile
        fadeAnimation: false // Tiles snap in instantly instead of fading from gray
      }).setView([lat, lng], zoom);

      // Enforce bounds after drag ends (not during drag, to avoid gray tile artifacts)
      map.on('dragend', function() {
        if (!bounds.contains(map.getCenter())) {
          map.panInsideBounds(bounds, { animate: true });
        }
      });

      map.on('zoomend', function() {
        if (!bounds.contains(map.getCenter())) {
          map.panInsideBounds(bounds, { animate: true });
        }
      });

      // Tile layers – caching handled transparently by the Service Worker (tile-sw.js)
      var tileLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
        subdomains: 'abcd',
        maxZoom: 20,
        crossOrigin: true,
        className: 'jg-map-tiles',
        keepBuffer: 6,
        updateWhenIdle: isMobile,
        detectRetina: true
      });


      var satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: '© Esri',
        maxZoom: 19,
        crossOrigin: true,
        keepBuffer: 6,
        updateWhenIdle: isMobile
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

      // Shared ref: dwGetFabCenterX() is defined inside FullscreenControl.onAdd
      // but also needed by createUserCountIndicator() in the outer scope.
      var _jgDwGetFabCenterX = null;
      // Shared ref: immediately hides the desk-wide promo element.
      // Set inside FullscreenControl.onAdd; used by the orientationchange handler
      // to hide the banner before the stale iOS resize fires.
      var _jgHideDeskPromo = null;

      if (currentLayerIsSatellite) {
        satelliteLayer.addTo(map);
        elMap.classList.add('jg-map--satellite');
      } else {
        tileLayer.addTo(map);
      }

      // Apply filter to tile pane as a single rendered unit — no gap/grid artifacts.
      // Satellite view should not be affected by colour grading.
      map.getPanes().tilePane.style.filter = currentLayerIsSatellite ? '' : 'brightness(0.85) contrast(1.35) saturate(1.45)';

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
              elMap.classList.remove('jg-map--satellite');
              toggle.setAttribute('data-active', 'map');
              labelMap.classList.add('jg-map-toggle-label--active');
              labelSat.classList.remove('jg-map-toggle-label--active');
              map.getPanes().tilePane.style.filter = 'brightness(0.85) contrast(1.35) saturate(1.45)';
              setMapCookie('jg_map_layer', 'map', 365);
            } else {
              map.removeLayer(tileLayer);
              satelliteLayer.addTo(map);
              currentLayerIsSatellite = true;
              elMap.classList.add('jg-map--satellite');
              toggle.setAttribute('data-active', 'satellite');
              labelSat.classList.add('jg-map-toggle-label--active');
              labelMap.classList.remove('jg-map-toggle-label--active');
              map.getPanes().tilePane.style.filter = '';
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

      // Mobile: unified controls row (filter button | search | map/sat toggle | +/-)
      if (isMobile) {
        // ── Build the controls row container ──────────────────────────────────
        var mcrRow = document.createElement('div');
        mcrRow.id = 'jg-mobile-controls-row';
        mcrRow.className = 'jg-mobile-controls-row';
        L.DomEvent.disableClickPropagation(mcrRow);
        L.DomEvent.disableScrollPropagation(mcrRow);
        mcrRow.addEventListener('click', function(e) { e.stopPropagation(); });
        mcrRow.addEventListener('touchstart', function(e) { e.stopPropagation(); });
        mcrRow.addEventListener('mousedown', function(e) { e.stopPropagation(); });

        // ── Filter button (left) ──────────────────────────────────────────────
        var mcrFilterBtn = document.createElement('button');
        mcrFilterBtn.type = 'button';
        mcrFilterBtn.className = 'jg-mcr-filter-btn';
        mcrFilterBtn.title = 'Filtry';
        mcrFilterBtn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>';

        // ── Search wrapper (middle, flex-grow) ────────────────────────────────
        var mcrSearchWrap = document.createElement('div');
        mcrSearchWrap.className = 'jg-mcr-search';
        var mcrSearchIcon = document.createElement('span');
        mcrSearchIcon.className = 'jg-mcr-search-icon';
        mcrSearchIcon.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>';
        var mcrInput = document.createElement('input');
        mcrInput.type = 'text';
        mcrInput.className = 'jg-mcr-search-input';
        mcrInput.placeholder = 'Szukaj...';
        mcrInput.setAttribute('autocomplete', 'off');
        var mcrClearBtn = document.createElement('button');
        mcrClearBtn.type = 'button';
        mcrClearBtn.className = 'jg-mcr-search-clear';
        mcrClearBtn.innerHTML = '&times;';
        mcrClearBtn.title = 'Wyczyść';
        mcrClearBtn.style.display = 'none';
        mcrSearchWrap.appendChild(mcrSearchIcon);
        mcrSearchWrap.appendChild(mcrInput);
        mcrSearchWrap.appendChild(mcrClearBtn);

        // ── Zoom buttons (right) ──────────────────────────────────────────────
        var mcrZoom = document.createElement('div');
        mcrZoom.className = 'jg-mcr-zoom';
        var mcrZoomIn = document.createElement('button');
        mcrZoomIn.type = 'button';
        mcrZoomIn.className = 'jg-mcr-zoom-btn';
        mcrZoomIn.title = 'Powiększ';
        mcrZoomIn.innerHTML = '+';
        var mcrZoomOut = document.createElement('button');
        mcrZoomOut.type = 'button';
        mcrZoomOut.className = 'jg-mcr-zoom-btn';
        mcrZoomOut.title = 'Pomniejsz';
        mcrZoomOut.innerHTML = '&minus;';
        mcrZoomIn.addEventListener('click', function() { map.zoomIn(); });
        mcrZoomOut.addEventListener('click', function() { map.zoomOut(); });
        mcrZoom.appendChild(mcrZoomIn);
        mcrZoom.appendChild(mcrZoomOut);

        // ── Locate me button (inserted before map/sat toggle in rAF) ─────────
        var mcrLocateBtn = document.createElement('button');
        mcrLocateBtn.type = 'button';
        mcrLocateBtn.className = 'jg-mcr-locate-btn';
        mcrLocateBtn.title = 'Moja lokalizacja';
        mcrLocateBtn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8"/><line x1="12" y1="2" x2="12" y2="7"/><line x1="12" y1="17" x2="12" y2="22"/><line x1="2" y1="12" x2="7" y2="12"/><line x1="17" y1="12" x2="22" y2="12"/><circle cx="12" cy="12" r="2" fill="currentColor" stroke="none"/></svg>';

        var _jgLocateMarker = null;
        var _jgLocateCircle = null;

        mcrLocateBtn.addEventListener('click', function() {
          if (!navigator.geolocation) {
            alert('Geolokalizacja nie jest obsługiwana przez tę przeglądarkę.');
            return;
          }
          mcrLocateBtn.classList.add('jg-mcr-locate-btn--loading');
          mcrLocateBtn.classList.remove('jg-mcr-locate-btn--active');
          navigator.geolocation.getCurrentPosition(
            function(pos) {
              mcrLocateBtn.classList.remove('jg-mcr-locate-btn--loading');
              mcrLocateBtn.classList.add('jg-mcr-locate-btn--active');
              var lat = pos.coords.latitude;
              var lng = pos.coords.longitude;
              var acc = pos.coords.accuracy;
              if (_jgLocateMarker) { map.removeLayer(_jgLocateMarker); }
              if (_jgLocateCircle) { map.removeLayer(_jgLocateCircle); }
              _jgLocateCircle = L.circle([lat, lng], {
                radius: acc,
                color: '#1a73e8',
                fillColor: '#1a73e8',
                fillOpacity: 0.15,
                weight: 1
              }).addTo(map);
              _jgLocateMarker = L.circleMarker([lat, lng], {
                radius: 8,
                color: '#fff',
                weight: 2.5,
                fillColor: '#1a73e8',
                fillOpacity: 1
              }).addTo(map);
              map.setView([lat, lng], 16);
            },
            function(err) {
              mcrLocateBtn.classList.remove('jg-mcr-locate-btn--loading');
              if (err.code === 1) {
                alert('Odmówiono dostępu do lokalizacji. Sprawdź ustawienia przeglądarki.');
              } else {
                alert('Nie udało się pobrać lokalizacji. Spróbuj ponownie.');
              }
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
          );
        });

        mcrRow.appendChild(mcrFilterBtn);
        mcrRow.appendChild(mcrSearchWrap);
        mcrRow.appendChild(mcrZoom);

        var mobileOverlays = document.getElementById('jg-mobile-overlays');
        if (mobileOverlays) {
          mobileOverlays.appendChild(mcrRow);
        }

        // ── Filter panel (clone of desktop filters) ───────────────────────────
        var mcrFilterPanel = document.createElement('div');
        mcrFilterPanel.className = 'jg-mobile-map-filter-panel';
        mcrFilterPanel.style.display = 'none';

        var filtersEl = document.getElementById('jg-map-filters');
        if (filtersEl) {
          var filtersClone = filtersEl.cloneNode(true);
          filtersClone.removeAttribute('id');
          filtersClone.querySelectorAll('[id]').forEach(function(el) { el.removeAttribute('id'); });
          var cloneSearch = filtersClone.querySelector('.jg-search');
          if (cloneSearch) cloneSearch.parentNode.removeChild(cloneSearch);
          mcrFilterPanel.appendChild(filtersClone);
          var clonedCheckboxes = filtersClone.querySelectorAll('input[type="checkbox"]');
          var origCheckboxes = filtersEl.querySelectorAll('input[type="checkbox"]');
          clonedCheckboxes.forEach(function(cloneChk, i) {
            if (origCheckboxes[i]) {
              cloneChk.checked = origCheckboxes[i].checked;
              cloneChk.addEventListener('change', function() {
                origCheckboxes[i].checked = cloneChk.checked;
                origCheckboxes[i].dispatchEvent(new Event('change', { bubbles: true }));
              });
            }
          });
        }

        L.DomEvent.disableClickPropagation(mcrFilterPanel);
        L.DomEvent.disableScrollPropagation(mcrFilterPanel);
        mcrFilterPanel.addEventListener('click', function(e) { e.stopPropagation(); });
        mcrFilterPanel.addEventListener('touchstart', function(e) { e.stopPropagation(); });
        mcrFilterPanel.addEventListener('mousedown', function(e) { e.stopPropagation(); });
        elMap.appendChild(mcrFilterPanel);

        mcrFilterBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          var isOpen = mcrFilterPanel.style.display !== 'none';
          mcrFilterPanel.style.display = isOpen ? 'none' : 'block';
          mcrFilterBtn.classList.toggle('jg-active', !isOpen);
          if (!isOpen) {
            // Position filter panel below the controls row
            var rowRect = mcrRow.getBoundingClientRect();
            var mapRect = elMap.getBoundingClientRect();
            var topOffset = rowRect.bottom - mapRect.top + 8;
            mcrFilterPanel.style.setProperty('top', topOffset + 'px', 'important');
          }
        });
        elMap.addEventListener('click', function() {
          if (mcrFilterPanel.style.display !== 'none') {
            mcrFilterPanel.style.display = 'none';
            mcrFilterBtn.classList.remove('jg-active');
          }
        });

        // ── Search wiring ─────────────────────────────────────────────────────
        var mcrSuggestions = document.createElement('div');
        mcrSuggestions.className = 'jg-search-suggestions jg-mobile-search-bar-suggestions';
        mcrSuggestions.style.display = 'none';
        document.body.appendChild(mcrSuggestions);

        function mcrPositionSugg() {
          // Use the full controls row width so suggestions are wider than just the input
          var rowRect  = mcrRow.getBoundingClientRect();
          var rect     = mcrInput.getBoundingClientRect();
          var vv = window.visualViewport;
          var visibleBottom = vv ? (vv.offsetTop + vv.height) : window.innerHeight;
          var spaceBelow = visibleBottom - rect.bottom - 4;
          mcrSuggestions.style.position = 'fixed';
          mcrSuggestions.style.left  = rowRect.left + 'px';
          mcrSuggestions.style.right = (window.innerWidth - rowRect.right) + 'px';
          mcrSuggestions.style.width = 'auto';
          mcrSuggestions.style.zIndex = '99999';
          if (spaceBelow >= 80) {
            mcrSuggestions.style.top = (rect.bottom + 4) + 'px';
            mcrSuggestions.style.bottom = 'auto';
            mcrSuggestions.style.maxHeight = Math.min(spaceBelow - 4, 300) + 'px';
          } else {
            mcrSuggestions.style.top = 'auto';
            mcrSuggestions.style.bottom = (window.innerHeight - visibleBottom + 4) + 'px';
            mcrSuggestions.style.maxHeight = Math.min(rect.top - 10, 250) + 'px';
          }
        }
        if (window.visualViewport) {
          window.visualViewport.addEventListener('resize', function() {
            if (document.activeElement === mcrInput && mcrSuggestions.style.display !== 'none') {
              mcrPositionSugg();
            }
          });
        }

        var origMobInput = document.getElementById('jg-search-input');
        var origMobBtn   = document.getElementById('jg-search-btn');

        var mcrBuildSugg = function(q) {
          if (_jgFsBuildSuggestions) {
            _jgFsBuildSuggestions(q, mcrSuggestions, mcrInput);
            if (mcrSuggestions.style.display !== 'none') mcrPositionSugg();
          }
        };
        var mcrHideSugg = function() {
          if (_jgFsHideSuggestions) _jgFsHideSuggestions(mcrSuggestions);
        };
        var mcrDebounce = null;

        mcrInput.addEventListener('input', function() {
          var val = this.value;
          if (origMobInput) origMobInput.value = val;
          mcrClearBtn.style.setProperty('display', val ? 'flex' : 'none', 'important');
          clearTimeout(mcrDebounce);
          mcrDebounce = setTimeout(function() { mcrBuildSugg(val.toLowerCase().trim()); }, 200);
        });
        mcrInput.addEventListener('blur', function() { setTimeout(mcrHideSugg, 150); });
        mcrInput.addEventListener('keydown', function(e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            var activeItem = mcrSuggestions.querySelector('.jg-suggest-active');
            if (activeItem) {
              var fill = activeItem.getAttribute('data-fill');
              if (fill) { mcrInput.value = fill; if (origMobInput) origMobInput.value = fill; }
            }
            mcrHideSugg();
            if (origMobBtn) origMobBtn.click();
          } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            var items = mcrSuggestions.querySelectorAll('.jg-suggest-item');
            var ai = Array.prototype.indexOf.call(items, mcrSuggestions.querySelector('.jg-suggest-active'));
            if (items[ai]) items[ai].classList.remove('jg-suggest-active');
            items[(ai + 1) % items.length] && items[(ai + 1) % items.length].classList.add('jg-suggest-active');
          } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            var items2 = mcrSuggestions.querySelectorAll('.jg-suggest-item');
            var ai2 = Array.prototype.indexOf.call(items2, mcrSuggestions.querySelector('.jg-suggest-active'));
            if (items2[ai2]) items2[ai2].classList.remove('jg-suggest-active');
            var prev = ai2 > 0 ? ai2 - 1 : items2.length - 1;
            items2[prev] && items2[prev].classList.add('jg-suggest-active');
          }
        });
        mcrSuggestions.addEventListener('mousedown', function(e) {
          e.preventDefault();
          var item = e.target.closest('.jg-suggest-item');
          if (item) {
            var fill = item.getAttribute('data-fill');
            if (fill) { mcrInput.value = fill; if (origMobInput) origMobInput.value = fill; }
            mcrHideSugg();
            if (origMobBtn) origMobBtn.click();
          }
        });
        mcrClearBtn.addEventListener('click', function() {
          mcrInput.value = '';
          mcrClearBtn.style.setProperty('display', 'none', 'important');
          if (origMobInput) { origMobInput.value = ''; origMobInput.dispatchEvent(new Event('input', { bubbles: true })); }
          mcrHideSugg();
          mcrInput.focus();
        });
        mcrSuggestions.addEventListener('click', function(e) { e.stopPropagation(); });
        mcrSuggestions.addEventListener('touchstart', function(e) { e.stopPropagation(); });

        // ── Wire mobile user panel buttons to top-bar button handlers ─────────
        setTimeout(function() {
          var mupRankingBtn = document.getElementById('jg-mup-ranking-btn');
          var rankingBtn    = document.getElementById('jg-ranking-btn');
          if (mupRankingBtn && rankingBtn) mupRankingBtn.addEventListener('click', function() { rankingBtn.click(); });

          var mupProfileBtn  = document.getElementById('jg-mup-profile-btn');
          var editProfileBtn = document.getElementById('jg-edit-profile-btn');
          if (mupProfileBtn && editProfileBtn) mupProfileBtn.addEventListener('click', function() { editProfileBtn.click(); });

          var mupAuthBtn = document.getElementById('jg-mup-auth-btn');
          var authBtn    = document.getElementById('jg-auth-btn');
          if (mupAuthBtn && authBtn) mupAuthBtn.addEventListener('click', function() { authBtn.click(); });

          var mupUsernameLink = document.getElementById('jg-mup-username-link');
          var myProfileLink   = document.getElementById('jg-my-profile-link');
          if (mupUsernameLink && myProfileLink) {
            mupUsernameLink.addEventListener('click', function(e) { e.preventDefault(); myProfileLink.click(); });
          }
        }, 0);

        // ── Move map/sat toggle into controls row & move banner to overlays ───
        requestAnimationFrame(function() {
          var toggleCtrl = elMap.querySelector('.jg-map-toggle-control');
          if (toggleCtrl && mcrZoom) {
            mcrRow.insertBefore(toggleCtrl, mcrZoom);
          }
          // Insert locate button before the map/sat toggle
          if (mcrLocateBtn && (toggleCtrl || mcrZoom)) {
            mcrRow.insertBefore(mcrLocateBtn, toggleCtrl || mcrZoom);
          }
          // Move ad banner slot into overlays container
          var slotWrap = document.querySelector('[data-cid]');
          if (slotWrap && mobileOverlays) {
            var bannerContainer = document.createElement('div');
            bannerContainer.className = 'jg-mobile-banner-slot';
            bannerContainer.appendChild(slotWrap);
            // Append after controls row so banner appears below it
            mobileOverlays.appendChild(bannerContainer);
          }
        });

        // ── Legacy: keep MobileFilterControl stub so rest of code doesn't break ─
        var MobileFilterControl = L.Control.extend({
          options: { position: 'topright' },
          onAdd: function() {
            var container = L.DomUtil.create('div', 'jg-mobile-map-filter-control leaflet-bar');
            var filterIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>';
            container.innerHTML =
              '<button class="jg-mobile-map-filter-btn" type="button" title="Filtry">' +
                filterIcon +
              '</button>';

            L.DomEvent.disableClickPropagation(container);
            L.DomEvent.disableScrollPropagation(container);

            var btn = container.querySelector('.jg-mobile-map-filter-btn');

            // Create floating filter panel on the map
            var filterPanel = document.createElement('div');
            filterPanel.className = 'jg-mobile-map-filter-panel';
            filterPanel.style.display = 'none';

            // Clone filters into the panel
            var filtersEl = document.getElementById('jg-map-filters');
            if (filtersEl) {
              var filtersClone = filtersEl.cloneNode(true);
              filtersClone.removeAttribute('id');

              // Remove duplicate IDs from cloned elements to avoid DOM conflicts
              filtersClone.querySelectorAll('[id]').forEach(function(el) {
                el.removeAttribute('id');
              });

              // Remove search from filter panel – search has its own bar on mobile
              var cloneSearchDivMob = filtersClone.querySelector('.jg-search');
              if (cloneSearchDivMob) cloneSearchDivMob.parentNode.removeChild(cloneSearchDivMob);

              // Wire up cloned search with autocomplete suggestions
              var clonedSearchInput = filtersClone.querySelector('input[type="text"]');
              var clonedSearchBtn = filtersClone.querySelector('.jg-search-btn');
              var origSearchInput = document.getElementById('jg-search-input');
              var origSearchBtn = document.getElementById('jg-search-btn');

              if (clonedSearchInput) {
                // Create suggestions dropdown inside the search wrapper
                var mobSuggestionsEl = document.createElement('div');
                mobSuggestionsEl.className = 'jg-search-suggestions';
                // The search container (.jg-search) needs position:relative for the dropdown
                var searchWrap = clonedSearchInput.closest('.jg-search') || clonedSearchInput.parentNode;
                searchWrap.style.position = 'relative';
                searchWrap.appendChild(mobSuggestionsEl);

                var mobSuggestDebounce = null;

                // Bridge to buildSuggestions/hideSuggestions (defined later in setTimeout)
                var mobBuildSugg = function(q) {
                  if (_jgFsBuildSuggestions) _jgFsBuildSuggestions(q, mobSuggestionsEl, clonedSearchInput);
                };
                var mobHideSugg = function() {
                  if (_jgFsHideSuggestions) _jgFsHideSuggestions(mobSuggestionsEl);
                };

                clonedSearchInput.addEventListener('input', function() {
                  var val = this.value;
                  if (origSearchInput) origSearchInput.value = val;
                  clearTimeout(mobSuggestDebounce);
                  var q = val.toLowerCase().trim();
                  mobSuggestDebounce = setTimeout(function() {
                    mobBuildSugg(q);
                  }, 200);
                });

                clonedSearchInput.addEventListener('blur', function() {
                  setTimeout(function() {
                    mobHideSugg();
                  }, 150);
                });

                clonedSearchInput.addEventListener('keydown', function(e) {
                  if (e.key === 'Enter') {
                    e.preventDefault();
                    // If a suggestion is highlighted, use it
                    var activeItem = mobSuggestionsEl.querySelector('.jg-suggest-active');
                    if (activeItem) {
                      var fill = activeItem.getAttribute('data-fill');
                      if (fill) {
                        clonedSearchInput.value = fill;
                        if (origSearchInput) origSearchInput.value = fill;
                      }
                    }
                    mobHideSugg();
                    if (origSearchInput) {
                      origSearchInput.value = clonedSearchInput.value;
                      origSearchInput.dispatchEvent(new Event('input', { bubbles: true }));
                      if (origSearchBtn) origSearchBtn.click();
                    }
                    clonedSearchInput.blur();
                  } else if (e.key === 'Escape') {
                    mobHideSugg();
                  } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    var items = mobSuggestionsEl.querySelectorAll('.jg-suggest-item');
                    if (!items.length) return;
                    var current = mobSuggestionsEl.querySelector('.jg-suggest-active');
                    var currentIdx = current ? Array.prototype.indexOf.call(items, current) : -1;
                    if (current) current.classList.remove('jg-suggest-active');
                    var nextIdx = e.key === 'ArrowDown' ? currentIdx + 1 : currentIdx - 1;
                    if (nextIdx >= items.length) nextIdx = 0;
                    if (nextIdx < 0) nextIdx = items.length - 1;
                    items[nextIdx].classList.add('jg-suggest-active');
                    items[nextIdx].scrollIntoView({ block: 'nearest' });
                  }
                });

                // Also stop suggestion clicks from closing the panel
                mobSuggestionsEl.addEventListener('click', function(e) { e.stopPropagation(); });
                mobSuggestionsEl.addEventListener('touchstart', function(e) { e.stopPropagation(); });
              }

              if (clonedSearchBtn && origSearchBtn && clonedSearchInput && origSearchInput) {
                clonedSearchBtn.addEventListener('click', function(e) {
                  e.preventDefault();
                  e.stopPropagation();
                  origSearchInput.value = clonedSearchInput.value;
                  origSearchBtn.click();
                });
              }

              filterPanel.appendChild(filtersClone);

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

              // Also sync original → clone when original changes
              var origCheckboxes = filtersEl.querySelectorAll('input[type="checkbox"]');
              origCheckboxes.forEach(function(origCb) {
                origCb.addEventListener('change', function() {
                  var cloneCb;
                  if (origCb.dataset.type) {
                    cloneCb = filtersClone.querySelector('input[data-type="' + origCb.dataset.type + '"]');
                  } else if (origCb.hasAttribute('data-my-places')) {
                    cloneCb = filtersClone.querySelector('input[data-my-places]');
                  } else if (origCb.hasAttribute('data-promo')) {
                    cloneCb = filtersClone.querySelector('input[data-promo]');
                  }
                  if (cloneCb && cloneCb.checked !== origCb.checked) {
                    cloneCb.checked = origCb.checked;
                  }
                });
              });
            }

            // Prevent map interactions in the filter panel
            L.DomEvent.disableClickPropagation(filterPanel);
            L.DomEvent.disableScrollPropagation(filterPanel);

            // Also stop native click/touch propagation so the elMap close handler doesn't fire
            filterPanel.addEventListener('click', function(e) { e.stopPropagation(); });
            filterPanel.addEventListener('touchstart', function(e) { e.stopPropagation(); });
            filterPanel.addEventListener('mousedown', function(e) { e.stopPropagation(); });

            elMap.appendChild(filterPanel);

            btn.addEventListener('click', function(e) {
              e.stopPropagation();
              var isOpen = filterPanel.style.display !== 'none';
              filterPanel.style.display = isOpen ? 'none' : 'block';
              btn.classList.toggle('jg-active', !isOpen);
            });

            // Close panel when clicking on the map
            elMap.addEventListener('click', function() {
              if (filterPanel.style.display !== 'none') {
                filterPanel.style.display = 'none';
                btn.classList.remove('jg-active');
              }
            });

            return container;
          }
        });

        // Note: MobileFilterControl is defined as legacy stub above — do NOT add as Leaflet control.
        // The new unified controls row (mcrRow) replaces it on mobile.
        void MobileFilterControl; // reference to prevent unused-variable linting warnings
      }

      // Mobile: standalone search bar replaced by unified controls row above.
      if (false && isMobile) { // DISABLED — replaced by jg-mobile-controls-row
        var mobileSearchBar = document.createElement('div');
        mobileSearchBar.className = 'jg-mobile-search-bar';

        var mobSbIcon = document.createElement('span');
        mobSbIcon.className = 'jg-mobile-search-bar-icon';
        mobSbIcon.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>';

        var mobSbInput = document.createElement('input');
        mobSbInput.type = 'text';
        mobSbInput.className = 'jg-mobile-search-bar-input';
        mobSbInput.placeholder = 'Szukaj...';
        mobSbInput.setAttribute('autocomplete', 'off');

        var mobSbClearBtn = document.createElement('button');
        mobSbClearBtn.type = 'button';
        mobSbClearBtn.className = 'jg-mobile-search-bar-clear';
        mobSbClearBtn.innerHTML = '&times;';
        mobSbClearBtn.title = 'Wyczyść';
        mobSbClearBtn.style.display = 'none';

        // Suggestions appended to body so position:fixed is not clipped by map overflow:hidden
        var mobSbSuggestions = document.createElement('div');
        mobSbSuggestions.className = 'jg-search-suggestions jg-mobile-search-bar-suggestions';
        mobSbSuggestions.style.display = 'none';
        document.body.appendChild(mobSbSuggestions);

        mobileSearchBar.appendChild(mobSbIcon);
        mobileSearchBar.appendChild(mobSbInput);
        mobileSearchBar.appendChild(mobSbClearBtn);

        L.DomEvent.disableClickPropagation(mobileSearchBar);
        L.DomEvent.disableScrollPropagation(mobileSearchBar);
        mobileSearchBar.addEventListener('click', function(e) { e.stopPropagation(); });
        mobileSearchBar.addEventListener('touchstart', function(e) { e.stopPropagation(); });
        mobileSearchBar.addEventListener('mousedown', function(e) { e.stopPropagation(); });

        elMap.appendChild(mobileSearchBar);

        // Align search bar width with the topright controls (filter btn + map/satellite toggle)
        // Must use setProperty(..., 'important') to override CSS !important declarations
        requestAnimationFrame(function() {
          var toprightEl = elMap.querySelector('.leaflet-top.leaflet-right');
          if (toprightEl) {
            var trRect = toprightEl.getBoundingClientRect();
            var mapRect = elMap.getBoundingClientRect();
            mobileSearchBar.style.setProperty('left', (trRect.left - mapRect.left) + 'px', 'important');
            mobileSearchBar.style.setProperty('right', (mapRect.right - trRect.right) + 'px', 'important');
          }
        });

        // Position suggestions using fixed coords, aware of virtual keyboard via visualViewport
        function positionMobSuggestions() {
          var rect = mobileSearchBar.getBoundingClientRect();
          var vv = window.visualViewport;
          var visibleBottom = vv ? (vv.offsetTop + vv.height) : window.innerHeight;
          var spaceBelow = visibleBottom - rect.bottom - 4;

          mobSbSuggestions.style.position = 'fixed';
          mobSbSuggestions.style.left = rect.left + 'px';
          mobSbSuggestions.style.right = (window.innerWidth - rect.right) + 'px';
          mobSbSuggestions.style.width = 'auto';
          mobSbSuggestions.style.zIndex = '99999';

          if (spaceBelow >= 80) {
            // Enough space below – show under the search bar
            mobSbSuggestions.style.top = (rect.bottom + 4) + 'px';
            mobSbSuggestions.style.bottom = 'auto';
            mobSbSuggestions.style.maxHeight = Math.min(spaceBelow - 4, 300) + 'px';
          } else {
            // Keyboard is taking space below – anchor just above the keyboard
            mobSbSuggestions.style.top = 'auto';
            mobSbSuggestions.style.bottom = (window.innerHeight - visibleBottom + 4) + 'px';
            mobSbSuggestions.style.maxHeight = Math.min(rect.top - 10, 250) + 'px';
          }
        }

        if (window.visualViewport) {
          window.visualViewport.addEventListener('resize', function() {
            if (document.activeElement === mobSbInput && mobSbSuggestions.style.display !== 'none') {
              positionMobSuggestions();
            }
          });
        }

        var origMobSbInput = document.getElementById('jg-search-input');
        var origMobSbBtn = document.getElementById('jg-search-btn');

        var mobSbBuildSugg = function(q) {
          if (_jgFsBuildSuggestions) {
            _jgFsBuildSuggestions(q, mobSbSuggestions, mobSbInput);
            if (mobSbSuggestions.style.display !== 'none') {
              positionMobSuggestions();
            }
          }
        };
        var mobSbHideSugg = function() {
          if (_jgFsHideSuggestions) _jgFsHideSuggestions(mobSbSuggestions);
        };

        var mobSbDebounce = null;

        mobSbInput.addEventListener('input', function() {
          var val = this.value;
          if (origMobSbInput) origMobSbInput.value = val;
          mobSbClearBtn.style.display = val ? 'flex' : 'none';
          clearTimeout(mobSbDebounce);
          var q = val.toLowerCase().trim();
          mobSbDebounce = setTimeout(function() {
            mobSbBuildSugg(q);
          }, 200);
        });

        mobSbInput.addEventListener('blur', function() {
          setTimeout(function() { mobSbHideSugg(); }, 150);
        });

        mobSbInput.addEventListener('keydown', function(e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            var activeItem = mobSbSuggestions.querySelector('.jg-suggest-active');
            if (activeItem) {
              var fill = activeItem.getAttribute('data-fill');
              if (fill) {
                mobSbInput.value = fill;
                if (origMobSbInput) origMobSbInput.value = fill;
              }
            }
            mobSbHideSugg();
            if (origMobSbInput) {
              origMobSbInput.value = mobSbInput.value;
              origMobSbInput.dispatchEvent(new Event('input', { bubbles: true }));
              if (origMobSbBtn) origMobSbBtn.click();
            }
            mobSbInput.blur();
          } else if (e.key === 'Escape') {
            mobSbHideSugg();
          } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            var items = mobSbSuggestions.querySelectorAll('.jg-suggest-item');
            if (!items.length) return;
            var current = mobSbSuggestions.querySelector('.jg-suggest-active');
            var currentIdx = current ? Array.prototype.indexOf.call(items, current) : -1;
            if (current) current.classList.remove('jg-suggest-active');
            var nextIdx = e.key === 'ArrowDown' ? currentIdx + 1 : currentIdx - 1;
            if (nextIdx >= items.length) nextIdx = 0;
            if (nextIdx < 0) nextIdx = items.length - 1;
            items[nextIdx].classList.add('jg-suggest-active');
            items[nextIdx].scrollIntoView({ block: 'nearest' });
          }
        });

        mobSbClearBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          mobSbInput.value = '';
          mobSbClearBtn.style.display = 'none';
          if (origMobSbInput) {
            origMobSbInput.value = '';
            origMobSbInput.dispatchEvent(new Event('input', { bubbles: true }));
          }
          mobSbHideSugg();
          mobSbInput.focus();
        });

        mobSbSuggestions.addEventListener('click', function(e) { e.stopPropagation(); });
        mobSbSuggestions.addEventListener('touchstart', function(e) { e.stopPropagation(); });
        mobSbSuggestions.addEventListener('mousedown', function(e) { e.stopPropagation(); });
      }

      // ── Orientation change: re-fit map after portrait/landscape switch ─────
      if (isMobile) {
        window.addEventListener('orientationchange', function() {
          // Scroll to top first — prevents grey gap caused by the page
          // remaining at a non-zero scroll position after rotation.
          window.scrollTo(0, 0);
          // Hide the desk-wide promo immediately on any orientation change.
          // Both Android Chrome and iOS Safari can fire resize with stale
          // dimensions, leaving the banner visible at portrait size (small,
          // with rounded corners) for up to ~550 ms.  Hiding here is safe:
          // in portrait the banner is already hidden, and when rotating to
          // landscape enterDeskWide() will re-show it after layout settles.
          if (_jgHideDeskPromo) _jgHideDeskPromo();
          // Wait for the browser to finish reflowing after rotation
          setTimeout(function() {
            window.scrollTo(0, 0);
            map.invalidateSize();
            // Re-fire resize with updated dimensions so the desk-wide resize
            // handler picks up the correct innerWidth after orientation change.
            // On iOS Safari, the 'resize' event fires before layout updates, so
            // the handler may see stale landscape dimensions and fail to call
            // exitDeskWide() when rotating back to portrait.
            window.dispatchEvent(new Event('resize'));
            // Re-position filter panel if open
            if (typeof mcrFilterPanel !== 'undefined' && mcrFilterPanel &&
                mcrFilterPanel.style.display !== 'none' &&
                typeof mcrRow !== 'undefined' && mcrRow) {
              var rowRect = mcrRow.getBoundingClientRect();
              var mapRect = elMap.getBoundingClientRect();
              var topOffset = rowRect.bottom - mapRect.top + 8;
              mcrFilterPanel.style.setProperty('top', topOffset + 'px', 'important');
            }
          }, 350);
        });
      }

      // Fullscreen control - positioned next to zoom controls (topleft)
      var isFullscreen = false;
      var isDeskWide = false;
      var _wasDeskWideBeforeFs = false;


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
          fsFilterIconBtn.style.display = 'none';
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

          // Create floating content container for fullscreen
          var fsPromoWrap = document.createElement('div');
          var _extCls = (window.JG_EXT_CFG && window.JG_EXT_CFG.cls) || {};
          fsPromoWrap.className = _extCls.fs || '';
          fsPromoWrap.style.display = 'none';
          elMap.appendChild(fsPromoWrap);

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
          // ===== SECTION: MAP SIDEBAR & NAVIGATION =====
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

            // Pause desktop-wide mode so fullscreen CSS takes full control
            _wasDeskWideBeforeFs = isDeskWide;
            if (isDeskWide) {
              isDeskWide = false;
              mapWrap.classList.remove('jg-desktop-wide');
              document.body.classList.remove('jg-desktop-wide-active');
              if (sidebar) sidebar.classList.remove('jg-sidebar-desktop-wide-overlay');
              if (typeof deskPromoWrap !== 'undefined' && deskPromoWrap) {
                deskPromoWrap.style.display = 'none';
                deskPromoWrap.innerHTML = '';
              }
              if (typeof dwPlaceholder !== 'undefined' && dwPlaceholder && dwPlaceholder.parentNode) {
                dwPlaceholder.parentNode.removeChild(dwPlaceholder);
              }
              // Restore satellite toggle to its original parent so fullscreen can
              // re-acquire it cleanly via its own toggleCtrl._origParent flow.
              var _toggleDwPause = elMap.querySelector('.jg-map-toggle-control');
              if (_toggleDwPause && _toggleDwPause._origParentDw) {
                _toggleDwPause._origParentDw.appendChild(_toggleDwPause);
                _toggleDwPause._origParentDw = null;
              }
              // Restore mapWrap inline style so fullscreen positioning starts clean
              if (mapWrap._origInlineStyle !== undefined) {
                mapWrap.setAttribute('style', mapWrap._origInlineStyle || '');
              }
            }

            mapWrap.classList.add('jg-fullscreen');
            document.body.classList.add('jg-fullscreen-active');

            // Close mobile nav menu if open
            var navBtn     = document.getElementById('jg-hamburger-btn');
            var navMenu    = document.getElementById('jg-nav-menu');
            var navOverlay = document.getElementById('jg-nav-overlay');
            if (navBtn)     { navBtn.classList.remove('jg-nav-open'); navBtn.setAttribute('aria-expanded', 'false'); }
            if (navMenu)    { navMenu.classList.remove('jg-nav-open'); navMenu.setAttribute('aria-hidden', 'true'); }
            if (navOverlay) { navOverlay.classList.remove('jg-nav-open'); }

            if (sidebar) {
              // Save original inline height and override for fullscreen
              sidebar._origHeight = sidebar.style.height;
              sidebar.style.setProperty('height', 'calc(100% - 24px)', 'important');
              elMap.appendChild(sidebar);
              sidebar.classList.add('jg-sidebar-fullscreen-overlay');
              // Prevent scroll wheel and clicks on sidebar from affecting the map
              L.DomEvent.disableScrollPropagation(sidebar);
              L.DomEvent.disableClickPropagation(sidebar);
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

            // Show floating content in fullscreen
            // ===== SECTION: PIN RENDERING & CLUSTERING =====
            (function setupFsPromo() {
              var $wrap = document.querySelector('[data-cid]');
              if (!$wrap) return;
              var lidAttr = $wrap.getAttribute('data-lid');
              var iidAttr = $wrap.getAttribute('data-iid');
              var origLink = lidAttr ? document.getElementById(lidAttr) : null;
              var origImg  = iidAttr ? document.getElementById(iidAttr) : null;
              if (!origLink || !origImg || !origImg.src || origImg.src === '' || origLink.style.display === 'none') return;

              var extCls = (window.JG_EXT_CFG && window.JG_EXT_CFG.cls) || {};
              fsPromoWrap.innerHTML = '';

              var label = document.createElement('div');
              label.className = extCls.fsTag || '';
              label.textContent = 'Sponsorowane';
              fsPromoWrap.appendChild(label);

              var inner = document.createElement('div');
              inner.className = extCls.fsIn || '';

              var link = document.createElement('a');
              link.href = origLink.href;
              link.target = '_blank';
              link.rel = 'noopener';

              var img = document.createElement('img');
              img.src = origImg.src;
              img.alt = '';

              link.appendChild(img);
              inner.appendChild(link);
              fsPromoWrap.appendChild(inner);

              var extCfg = window.JG_EXT_CFG || {};
              var ajaxUrl = extCfg.ajax || '';
              var act = extCfg.act || {};

              link.addEventListener('click', function() {
                var bannerId = origLink.closest('[data-bid]') ? origLink.closest('[data-bid]').getAttribute('data-bid')
                             : (document.querySelector('[data-bid]') ? document.querySelector('[data-bid]').getAttribute('data-bid') : null);
                if (bannerId && ajaxUrl && navigator.sendBeacon) {
                  var fd = new FormData();
                  fd.append('action', act.engage || '');
                  fd.append('banner_id', bannerId);
                  navigator.sendBeacon(ajaxUrl, fd);
                }
              });

              var cid = $wrap ? $wrap.getAttribute('data-cid') : null;
              var bannerBox = cid ? document.getElementById(cid) : null;
              var bannerId = bannerBox ? bannerBox.getAttribute('data-bid') : null;
              if (bannerId && ajaxUrl && window.jQuery) {
                jQuery.ajax({
                  url: ajaxUrl,
                  type: 'POST',
                  data: { action: act.view || '', banner_id: bannerId }
                });
              }

              fsPromoWrap.style.display = '';
            })();

            btn.innerHTML = exitIcon;
            btn.title = 'Zamknij pełny ekran';
            setTimeout(function() { map.invalidateSize(); }, 350);
          }

          function exitFullscreen() {
            isFullscreen = false;
            mapWrap.classList.remove('jg-fullscreen');
            document.body.classList.remove('jg-fullscreen-active');
            // Clean up mobile sidebar drawer if it was open
            var _mbb = document.querySelector('.jg-sidebar-mobile-backdrop');
            var _swh = document.querySelector('.jg-sidebar-swipe-handle');
            if (_mbb) { _mbb.classList.remove('active'); _mbb.style.opacity = ''; }
            if (sidebar) {
              sidebar.classList.remove('jg-sidebar-mobile-open');
              sidebar.style.transform = ''; sidebar.style.transition = '';
              if (sidebar._sbMobileOrigHeight !== undefined) {
                sidebar.style.height = sidebar._sbMobileOrigHeight;
                sidebar._sbMobileOrigHeight = undefined;
              }
            }
            if (_swh) { _swh.classList.remove('hidden'); }
            var _cch = document.querySelector('.jg-sidebar-close-handle');
            if (_cch) { _cch.classList.remove('visible'); }
            // If sidebar was moved to body by mobile drawer, schedule restore after fullscreen cleanup
            if (sidebar && sidebar._sbMobileOrigParent) {
              sidebar._sbPendingMobileRestore = true;
            }
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
              // If mobile drawer previously moved sidebar to body, restore to original Elementor position
              if (sidebar._sbPendingMobileRestore && sidebar._sbMobileOrigParent) {
                var _mobP = sidebar._sbMobileOrigParent;
                var _mobN = sidebar._sbMobileOrigNext;
                sidebar._sbMobileOrigParent = null;
                sidebar._sbMobileOrigNext = null;
                sidebar._sbPendingMobileRestore = false;
                if (_mobN && _mobN.parentNode === _mobP) { _mobP.insertBefore(sidebar, _mobN); }
                else { _mobP.appendChild(sidebar); }
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
            // Hide floating content
            fsPromoWrap.style.display = 'none';
            fsPromoWrap.innerHTML = '';
            btn.innerHTML = enterIcon;
            btn.title = 'Pełny ekran';
            setTimeout(function() { map.invalidateSize(); }, 350);
            // Re-enter desktop-wide mode if it was active before fullscreen
            if (_wasDeskWideBeforeFs && window.innerWidth > 768) {
              setTimeout(enterDeskWide, 100);
            }
          }

          // ── Desktop-Wide Mode ──
          // Auto-activated on desktop (≥769 px). The map fills the full viewport
          // area between the Elementor header and footer. Sidebar, filters and
          // search float as overlays; the banner floats at the top of the map.

          var dwPlaceholder = document.createElement('div');
          dwPlaceholder.id = 'jg-desktop-wide-placeholder';

          var deskPromoWrap = document.createElement('div');
          var _dwExtCls = (window.JG_EXT_CFG && window.JG_EXT_CFG.cls) || {};
          deskPromoWrap.className = _dwExtCls.fs || '';
          deskPromoWrap.style.display = 'none';
          elMap.appendChild(deskPromoWrap);
          L.DomEvent.disableClickPropagation(deskPromoWrap);
          L.DomEvent.disableScrollPropagation(deskPromoWrap);

          // References to header/footer elements so we can restore them on exit
          var dwHeaderEl = null;
          var dwFooterEl = null;
          var dwFooterOrigStyle = null;

          function dwDetectHeaderFooter() {
            // Header: scan all known header selectors, take the maximum
            // getBoundingClientRect().bottom value (works for sticky, fixed, or
            // static headers and correctly accumulates stacked bars like #jg-top-bar
            // + .elementor-location-header).
            var hSel = ['#jg-custom-top-bar', '.elementor-location-header', 'header.elementor-section', '#masthead', '.site-header', 'header'];
            var hMax = 0;
            for (var _hi = 0; _hi < hSel.length; _hi++) {
              var _hEl = document.querySelector(hSel[_hi]);
              if (_hEl) {
                var _hRect = _hEl.getBoundingClientRect();
                // Only count elements whose bottom is in the top 40 % of the viewport
                if (_hRect.bottom > hMax && _hRect.bottom < window.innerHeight * 0.4) {
                  hMax = _hRect.bottom;
                  dwHeaderEl = _hEl;
                }
              }
            }
            var headerH = Math.ceil(hMax);

            // Footer: fix it to the viewport bottom so it remains visible,
            // and use its height (capped at 30 % of the viewport) as the map's
            // bottom offset to prevent an oversized footer from squashing the map.
            var footerH = 0;
            if (!dwFooterEl) {
              var fSel = ['.elementor-location-footer', 'footer.elementor-section', '#colophon', '.site-footer', 'footer'];
              for (var _fi = 0; _fi < fSel.length; _fi++) {
                var _fEl = document.querySelector(fSel[_fi]);
                if (_fEl && _fEl.offsetHeight) { dwFooterEl = _fEl; break; }
              }
            }
            if (dwFooterEl) {
              // Cap so a tall multi-section footer doesn't shrink the map excessively
              var cappedFooterH = Math.min(dwFooterEl.offsetHeight, Math.floor(window.innerHeight * 0.3));
              // Make footer stick to the viewport bottom (save original style once)
              if (dwFooterOrigStyle === null) {
                dwFooterOrigStyle = dwFooterEl.getAttribute('style') || '';
                dwFooterEl.style.setProperty('position', 'fixed', 'important');
                dwFooterEl.style.setProperty('bottom', '0', 'important');
                dwFooterEl.style.setProperty('left', '0', 'important');
                dwFooterEl.style.setProperty('right', '0', 'important');
                dwFooterEl.style.setProperty('width', '100vw', 'important');
                dwFooterEl.style.setProperty('box-sizing', 'border-box', 'important');
                dwFooterEl.style.setProperty('z-index', '999', 'important');
                dwFooterEl.style.setProperty('max-height', cappedFooterH + 'px', 'important');
                dwFooterEl.style.setProperty('overflow-y', 'hidden', 'important');
              }
              footerH = cappedFooterH;
            }
            return { top: headerH, bottom: footerH };
          }

          // Returns the X coordinate (px from map left edge) of the midpoint
          // between the onboarding FAB (#jg-help-fab, left) and the add-places
          // FAB (#jg-fab-container, right).  In desktop-wide mode the right FAB
          // is shifted left because the sidebar occupies the right side, so we
          // must read the actual DOM positions rather than assuming 50 %.
          function dwGetFabCenterX() {
            var mapRect  = elMap.getBoundingClientRect();
            var leftFab  = document.getElementById('jg-help-fab');
            var rightFab = document.getElementById('jg-fab-container');
            var leftEdge  = leftFab  ? (leftFab.getBoundingClientRect().right  - mapRect.left) : 0;
            var rightEdge = rightFab ? (rightFab.getBoundingClientRect().left  - mapRect.left) : mapRect.width;
            return Math.round((leftEdge + rightEdge) / 2);
          }
          _jgDwGetFabCenterX = dwGetFabCenterX;
          _jgHideDeskPromo = function() { deskPromoWrap.style.display = 'none'; };

          function dwShowPromo() {
            // Never show the floating banner when in portrait/mobile layout —
            // guards against stale setTimeout callbacks firing after exitDeskWide.
            if (window.innerWidth <= 768) return;
            var $dwWrap = document.querySelector('[data-cid]');
            if (!$dwWrap) return;
            var lidAttr = $dwWrap.getAttribute('data-lid');
            var iidAttr = $dwWrap.getAttribute('data-iid');
            var origLink = lidAttr ? document.getElementById(lidAttr) : null;
            var origImg  = iidAttr ? document.getElementById(iidAttr) : null;

            // hideSlot() case: ext.js replaced #id_cid.innerHTML with CTA, removing origLink/origImg.
            // Detect this: origLink is null but #id_cid has content → clone it into deskPromoWrap.
            if (!origLink || !origImg) {
              var cidAttr = $dwWrap.getAttribute('data-cid');
              var cidEl = cidAttr ? document.getElementById(cidAttr) : null;
              if (cidEl && cidEl.children.length > 0) {
                deskPromoWrap.innerHTML = '';
                var clonedCta = cidEl.cloneNode(true);
                clonedCta.style.maxWidth = '728px';
                clonedCta.style.width = '100%';
                clonedCta.style.margin = '0';
                deskPromoWrap.appendChild(clonedCta);
                deskPromoWrap.style.setProperty('position', 'absolute', 'important');
                deskPromoWrap.style.setProperty('top', 'auto', 'important');
                deskPromoWrap.style.setProperty('right', 'auto', 'important');
                deskPromoWrap.style.setProperty('bottom', '15px', 'important');
                deskPromoWrap.style.setProperty('left', dwGetFabCenterX() + 'px', 'important');
                deskPromoWrap.style.setProperty('transform', 'translateX(-50%)', 'important');
                deskPromoWrap.style.display = '';
                // Move user count indicator to the right of the banner
                setTimeout(function() {
                  var uci = document.getElementById('jg-user-count-indicator');
                  if (uci && deskPromoWrap.offsetWidth > 0) {
                    var br = deskPromoWrap.getBoundingClientRect();
                    var mr = elMap.getBoundingClientRect();
                    uci.style.left = (br.right - mr.left + 12) + 'px';
                    uci.style.transform = 'none';
                  }
                }, 50);
              }
              return;
            }

            // Banner not loaded yet — will be retried by caller
            if (!origImg.src || origImg.src === '' || origLink.style.display === 'none') return;

            var extCls = (window.JG_EXT_CFG && window.JG_EXT_CFG.cls) || {};
            deskPromoWrap.innerHTML = '';

            var dwLabel = document.createElement('div');
            dwLabel.className = extCls.fsTag || '';
            dwLabel.textContent = 'Sponsorowane';
            deskPromoWrap.appendChild(dwLabel);

            var dwInner = document.createElement('div');
            dwInner.className = extCls.fsIn || '';

            var dwLink = document.createElement('a');
            dwLink.href = origLink.href;
            dwLink.target = '_blank';
            dwLink.rel = 'noopener';

            var dwImg = document.createElement('img');
            dwImg.src = origImg.src;
            dwImg.alt = '';

            dwLink.appendChild(dwImg);
            dwInner.appendChild(dwLink);
            deskPromoWrap.appendChild(dwInner);

            var extCfg = window.JG_EXT_CFG || {};
            var dwAjaxUrl = extCfg.ajax || '';
            var dwAct = extCfg.act || {};

            dwLink.addEventListener('click', function() {
              var dwBid = document.querySelector('[data-bid]') ? document.querySelector('[data-bid]').getAttribute('data-bid') : null;
              if (dwBid && dwAjaxUrl && navigator.sendBeacon) {
                var fd = new FormData();
                fd.append('action', dwAct.engage || '');
                fd.append('banner_id', dwBid);
                navigator.sendBeacon(dwAjaxUrl, fd);
              }
            });

            // Position banner: bottom of the map, horizontally centred between
            // the onboarding FAB (#jg-help-fab, bottom-left) and the add-places
            // FAB (#jg-fab-container, bottom-right).  dwGetFabCenterX() reads the
            // actual DOM positions so the sidebar shift is automatically accounted for.
            deskPromoWrap.style.setProperty('position', 'absolute', 'important');
            deskPromoWrap.style.setProperty('top', 'auto', 'important');
            deskPromoWrap.style.setProperty('right', 'auto', 'important');
            deskPromoWrap.style.setProperty('bottom', '15px', 'important');
            deskPromoWrap.style.setProperty('left', dwGetFabCenterX() + 'px', 'important');
            deskPromoWrap.style.setProperty('transform', 'translateX(-50%)', 'important');

            deskPromoWrap.style.display = '';
            // Move user count indicator to the right of the banner
            setTimeout(function() {
              var uci = document.getElementById('jg-user-count-indicator');
              if (uci && deskPromoWrap.offsetWidth > 0) {
                var br = deskPromoWrap.getBoundingClientRect();
                var mr = elMap.getBoundingClientRect();
                uci.style.left = (br.right - mr.left + 12) + 'px';
                uci.style.transform = 'none';
              }
            }, 50);
          }

          function enterDeskWide() {
            if (window.innerWidth <= 768) return;
            if (isFullscreen) return;
            isDeskWide = true;

            var dims = dwDetectHeaderFooter();

            // Save original inline style and override with fixed positioning
            mapWrap._origInlineStyle = mapWrap.getAttribute('style');
            mapWrap.style.setProperty('position', 'fixed', 'important');
            mapWrap.style.setProperty('top', dims.top + 'px', 'important');
            mapWrap.style.setProperty('left', '0', 'important');
            mapWrap.style.setProperty('right', '0', 'important');
            mapWrap.style.setProperty('bottom', dims.bottom + 'px', 'important');
            mapWrap.style.setProperty('width', '100vw', 'important');
            // Use an explicit pixel height instead of 'auto' so the flex child
            // (#jg-map with flex:1;height:0) always gets the correct computed height
            // regardless of browser behaviour for fixed+top+bottom+height:auto.
            mapWrap.style.setProperty('height', Math.max(0, window.innerHeight - dims.top - dims.bottom) + 'px', 'important');
            mapWrap.style.setProperty('display', 'flex', 'important');
            mapWrap.style.setProperty('flex-direction', 'column', 'important');
            mapWrap.style.setProperty('border-radius', '0', 'important');
            mapWrap.style.setProperty('z-index', '1000', 'important');
            // Clear any max-height left behind by the mobile viewport-fitting routine
            // (jgFitMobileViewport) which never cleans up when returning to desktop.
            mapWrap.style.removeProperty('max-height');

            mapWrap.classList.add('jg-desktop-wide');
            document.body.classList.add('jg-desktop-wide-active');

            // Insert a placeholder div to preserve the Elementor column height
            if (!document.getElementById('jg-desktop-wide-placeholder')) {
              dwPlaceholder.style.height = (mapWrap._origInlineStyle && mapWrap._origInlineStyle.match(/height:\s*([\d.]+px)/) ? mapWrap._origInlineStyle.match(/height:\s*([\d.]+px)/)[1] : '600px');
              if (mapWrap.parentNode) {
                mapWrap.parentNode.insertBefore(dwPlaceholder, mapWrap.nextSibling);
              }
            }

            // Move map/satellite toggle to topleft (satellite toggle is normally
            // in topright, which is hidden by the floating sidebar in desktop-wide)
            var toggleCtrlDw = elMap.querySelector('.jg-map-toggle-control');
            if (toggleCtrlDw && !toggleCtrlDw._origParentDw) {
              toggleCtrlDw._origParentDw = toggleCtrlDw.parentNode;
              var leftContainerDw = elMap.querySelector('.leaflet-top.leaflet-left');
              if (leftContainerDw) leftContainerDw.appendChild(toggleCtrlDw);
            }

            // Move sidebar into the map as a floating overlay
            if (sidebar && !sidebar.classList.contains('jg-sidebar-desktop-wide-overlay')) {
              // Only save the original height on the very first call
              if (sidebar._origHeightDw === undefined) {
                sidebar._origHeightDw = sidebar.style.height;
              }
              sidebar.style.setProperty('height', 'calc(100% - 24px)', 'important');
              elMap.appendChild(sidebar);
              sidebar.classList.add('jg-sidebar-desktop-wide-overlay');
              L.DomEvent.disableScrollPropagation(sidebar);
              L.DomEvent.disableClickPropagation(sidebar);
            }

            // Show the banner after a short delay. The banner data (origImg.src)
            // is loaded asynchronously, so retry until it appears (up to ~15 s).
            setTimeout(function() { if (isDeskWide && !isFullscreen) dwShowPromo(); }, 700);
            setTimeout(function() { if (isDeskWide && !isFullscreen && !deskPromoWrap.innerHTML) dwShowPromo(); }, 2000);
            setTimeout(function() { if (isDeskWide && !isFullscreen && !deskPromoWrap.innerHTML) dwShowPromo(); }, 5000);
            setTimeout(function() { if (isDeskWide && !isFullscreen && !deskPromoWrap.innerHTML) dwShowPromo(); }, 10000);
            setTimeout(function() { if (isDeskWide && !isFullscreen && !deskPromoWrap.innerHTML) dwShowPromo(); }, 15000);
            setTimeout(function() {
              if (!isDeskWide) return;
              // Re-read dims in case the viewport shifted during the 100 ms
              var _dims2 = dwDetectHeaderFooter();
              var _h2 = Math.max(0, window.innerHeight - _dims2.top - _dims2.bottom);
              mapWrap.style.setProperty('top', _dims2.top + 'px', 'important');
              mapWrap.style.setProperty('bottom', _dims2.bottom + 'px', 'important');
              mapWrap.style.setProperty('height', _h2 + 'px', 'important');
              map.invalidateSize();
            }, 100);
          }

          function exitDeskWide() {
            // Restore elMap to full width before leaving desktop-wide mode
            isDeskWide = false;

            // Restore original inline style
            if (mapWrap._origInlineStyle !== null && mapWrap._origInlineStyle !== undefined) {
              mapWrap.setAttribute('style', mapWrap._origInlineStyle);
            } else {
              mapWrap.removeAttribute('style');
            }

            mapWrap.classList.remove('jg-desktop-wide');
            document.body.classList.remove('jg-desktop-wide-active');

            // Restore sidebar to its original DOM position
            if (sidebar && sidebar.classList.contains('jg-sidebar-desktop-wide-overlay')) {
              sidebar.classList.remove('jg-sidebar-desktop-wide-overlay');
              if (sidebar._origHeightDw !== undefined) {
                sidebar.style.setProperty('height', sidebar._origHeightDw, 'important');
              }
              if (sidebarOriginalNext) {
                sidebarOriginalParent.insertBefore(sidebar, sidebarOriginalNext);
              } else {
                sidebarOriginalParent.appendChild(sidebar);
              }
            }

            // Restore footer to its original positioning
            if (dwFooterEl) {
              if (dwFooterOrigStyle) {
                dwFooterEl.setAttribute('style', dwFooterOrigStyle);
              } else {
                dwFooterEl.removeAttribute('style');
              }
              dwFooterEl = null;
              dwFooterOrigStyle = null;
            }

            // Restore map/satellite toggle to its original parent
            var toggleCtrlDwExit = elMap.querySelector('.jg-map-toggle-control');
            if (toggleCtrlDwExit && toggleCtrlDwExit._origParentDw) {
              toggleCtrlDwExit._origParentDw.appendChild(toggleCtrlDwExit);
              toggleCtrlDwExit._origParentDw = null;
            }

            // Remove placeholder
            if (dwPlaceholder.parentNode) {
              dwPlaceholder.parentNode.removeChild(dwPlaceholder);
            }

            // Hide and clear the floating banner
            deskPromoWrap.style.display = 'none';
            deskPromoWrap.innerHTML = '';
            // Reset user count indicator to centre between FABs (no banner)
            var uciExit = document.getElementById('jg-user-count-indicator');
            if (uciExit) {
              uciExit.style.left = dwGetFabCenterX() + 'px';
              uciExit.style.transform = 'translateX(-50%)';
            }
          }

          // Recalculate on window resize
          var _dwResizeTimer;
          window.addEventListener('resize', function() {
            clearTimeout(_dwResizeTimer);
            _dwResizeTimer = setTimeout(function() {
              if (isFullscreen) return;
              if (window.innerWidth <= 768) {
                if (isDeskWide) exitDeskWide();
              } else {
                if (isDeskWide) {
                  // Recalculate header/footer offsets using the same method as enterDeskWide
                  // (getBoundingClientRect().bottom) so the map fills the full viewport
                  // correctly after non-standard resize → full-screen transitions.
                  var dims2 = dwDetectHeaderFooter();
                  var _newH = Math.max(0, window.innerHeight - dims2.top - dims2.bottom);
                  mapWrap.style.removeProperty('max-height');
                  mapWrap.style.setProperty('top', dims2.top + 'px', 'important');
                  mapWrap.style.setProperty('bottom', dims2.bottom + 'px', 'important');
                  mapWrap.style.setProperty('height', _newH + 'px', 'important');
                  map.invalidateSize();
                  // Second pass after paint to catch any remaining rendering lag
                  setTimeout(function() { if (isDeskWide) map.invalidateSize(); }, 200);
                  // Reposition banner and user count indicator after resize
                  setTimeout(function() {
                    if (!isDeskWide) return;
                    var isBannerVisible = deskPromoWrap.style.display !== 'none' && deskPromoWrap.innerHTML !== '';
                    if (isBannerVisible) {
                      deskPromoWrap.style.setProperty('left', dwGetFabCenterX() + 'px', 'important');
                      var uciResize = document.getElementById('jg-user-count-indicator');
                      if (uciResize && deskPromoWrap.offsetWidth > 0) {
                        var brResize = deskPromoWrap.getBoundingClientRect();
                        var mrResize = elMap.getBoundingClientRect();
                        uciResize.style.left = (brResize.right - mrResize.left + 12) + 'px';
                        uciResize.style.transform = 'none';
                      }
                    } else {
                      var uciResize2 = document.getElementById('jg-user-count-indicator');
                      if (uciResize2) {
                        uciResize2.style.left = dwGetFabCenterX() + 'px';
                        uciResize2.style.transform = 'translateX(-50%)';
                      }
                    }
                  }, 250);
                } else {
                  enterDeskWide();
                }
              }
            }, 200);
          });

          // Auto-enter desktop-wide mode on initial page load (desktop only)
          if (window.innerWidth > 768) {
            setTimeout(enterDeskWide, 150);
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

          // ── Mobile: swipe-to-open sidebar from right edge ──
          if (isMobile) {
            var swipeHandle = document.createElement('div');
            swipeHandle.className = 'jg-sidebar-swipe-handle';
            swipeHandle.setAttribute('aria-label', 'Otwórz listę');
            swipeHandle.setAttribute('role', 'button');
            swipeHandle.setAttribute('tabindex', '0');
            document.body.appendChild(swipeHandle);

            var mobileBackdrop = document.createElement('div');
            mobileBackdrop.className = 'jg-sidebar-mobile-backdrop';
            document.body.appendChild(mobileBackdrop);

            var closeHandle = document.createElement('div');
            closeHandle.className = 'jg-sidebar-close-handle';
            closeHandle.setAttribute('aria-label', 'Zamknij listę');
            closeHandle.setAttribute('role', 'button');
            closeHandle.setAttribute('tabindex', '0');
            document.body.appendChild(closeHandle);

            var mobileSbOpen = false;
            var swTouchId = null;
            var swStartX = 0;
            var swStartY = 0;
            var swMode = ''; // 'open' | 'close'
            var swSidebar = null;
            var EDGE_ZONE = 22; // px from right viewport edge
            var OPEN_THRESHOLD = 0.28;
            var CLOSE_THRESHOLD = 0.25;

            function openMobileSb() {
              var sb = document.getElementById('jg-map-sidebar');
              if (!sb) return;
              // Move to body to escape stacking contexts or display:none parents
              if (!sb._sbMobileOrigParent && sb.parentNode !== document.body) {
                sb._sbMobileOrigParent = sb.parentNode;
                sb._sbMobileOrigNext = sb.nextSibling;
                document.body.appendChild(sb);
              }
              // Clear inline height so CSS height:100dvh can take effect
              if (sb._sbMobileOrigHeight === undefined) {
                sb._sbMobileOrigHeight = sb.style.height;
                sb.style.height = '';
              }
              mobileSbOpen = true;
              swipeHandle.classList.add('hidden');
              closeHandle.classList.add('visible');
              mobileBackdrop.classList.add('active');
              mobileBackdrop.style.opacity = '';
              sb.classList.add('jg-sidebar-mobile-open');
              sb.style.transition = '';
              requestAnimationFrame(function() { sb.style.transform = ''; });
            }

            swipeHandle.addEventListener('click', function() { openMobileSb(); });
            closeHandle.addEventListener('click', function() { closeMobileSb(); });

            function closeMobileSb() {
              var sb = document.getElementById('jg-map-sidebar');
              if (!sb) return;
              mobileSbOpen = false;
              mobileBackdrop.classList.remove('active');
              mobileBackdrop.style.opacity = '';
              sb.style.transition = '';
              closeHandle.style.transition = '';
              requestAnimationFrame(function() {
                sb.style.transform = 'translateX(100%)';
                closeHandle.style.transform = 'translateY(-50%) translateX(calc(min(85vw, 340px) + 28px))';
                var done = false;
                function onEnd() {
                  if (done) return;
                  done = true;
                  sb.removeEventListener('transitionend', onEnd);
                  sb.classList.remove('jg-sidebar-mobile-open');
                  sb.style.transform = '';
                  closeHandle.classList.remove('visible');
                  closeHandle.style.transform = '';
                  closeHandle.style.transition = '';
                  // Restore inline height
                  if (sb._sbMobileOrigHeight !== undefined) {
                    sb.style.height = sb._sbMobileOrigHeight;
                    sb._sbMobileOrigHeight = undefined;
                  }
                  // Restore sidebar to original DOM position
                  if (sb._sbMobileOrigParent) {
                    var _p = sb._sbMobileOrigParent;
                    var _n = sb._sbMobileOrigNext;
                    sb._sbMobileOrigParent = null;
                    sb._sbMobileOrigNext = null;
                    if (_n && _n.parentNode === _p) { _p.insertBefore(sb, _n); }
                    else { _p.appendChild(sb); }
                  }
                  swipeHandle.classList.remove('hidden');
                }
                sb.addEventListener('transitionend', onEnd);
                setTimeout(onEnd, 350);
              });
            }

            window.closeMobileSb = closeMobileSb;

            mobileBackdrop.addEventListener('touchstart', function(e) {
              e.preventDefault();
              closeMobileSb();
            }, { passive: false });

            mobileBackdrop.addEventListener('click', closeMobileSb);

            elMap.addEventListener('touchstart', function(e) {
              var touch = e.touches[0];

              if (!mobileSbOpen) {
                if (touch.clientX >= window.innerWidth - EDGE_ZONE) {
                  swMode = 'open';
                  swTouchId = touch.identifier;
                  swStartX = touch.clientX;
                  swStartY = touch.clientY;
                  swSidebar = document.getElementById('jg-map-sidebar');
                  if (swSidebar) {
                    // Move to body to escape stacking contexts or display:none parents
                    if (!swSidebar._sbMobileOrigParent && swSidebar.parentNode !== document.body) {
                      swSidebar._sbMobileOrigParent = swSidebar.parentNode;
                      swSidebar._sbMobileOrigNext = swSidebar.nextSibling;
                      document.body.appendChild(swSidebar);
                    }
                    // Clear inline height so CSS height:100dvh can take effect
                    if (swSidebar._sbMobileOrigHeight === undefined) {
                      swSidebar._sbMobileOrigHeight = swSidebar.style.height;
                      swSidebar.style.height = '';
                    }
                    swSidebar.classList.add('jg-sidebar-mobile-open');
                    swSidebar.style.transition = 'none';
                    swSidebar.style.transform = 'translateX(100%)';
                    mobileBackdrop.classList.add('active');
                    mobileBackdrop.style.opacity = '0';
                  }
                  e.preventDefault();
                }
              } else {
                swSidebar = document.getElementById('jg-map-sidebar');
                if (swSidebar) {
                  var sbRect = swSidebar.getBoundingClientRect();
                  if (touch.clientX >= sbRect.left) {
                    swMode = 'close';
                    swTouchId = touch.identifier;
                    swStartX = touch.clientX;
                    swStartY = touch.clientY;
                    closeHandle.style.transition = 'none';
                  }
                }
              }
            }, { passive: false });

            elMap.addEventListener('touchmove', function(e) {
              if (!swMode || !swSidebar) return;
              var touch = null;
              for (var i = 0; i < e.touches.length; i++) {
                if (e.touches[i].identifier === swTouchId) { touch = e.touches[i]; break; }
              }
              if (!touch) return;
              var dx = touch.clientX - swStartX;
              var dy = touch.clientY - swStartY;
              var sbW = swSidebar.offsetWidth || 300;

              if (swMode === 'open') {
                if (Math.abs(dy) > Math.abs(dx) + 8) {
                  swSidebar.classList.remove('jg-sidebar-mobile-open');
                  swSidebar.style.transform = '';
                  swSidebar.style.transition = '';
                  mobileBackdrop.classList.remove('active');
                  mobileBackdrop.style.opacity = '';
                  swMode = '';
                  swSidebar = null;
                  swTouchId = null;
                  return;
                }
                var progress = Math.min(1, Math.max(0, -dx / sbW));
                swSidebar.style.transform = 'translateX(' + ((1 - progress) * 100) + '%)';
                mobileBackdrop.style.opacity = String(progress);
                e.preventDefault();
              } else if (swMode === 'close' && dx > 0) {
                swSidebar.style.transform = 'translateX(' + dx + 'px)';
                closeHandle.style.transform = 'translateY(-50%) translateX(' + dx + 'px)';
                mobileBackdrop.style.opacity = String(1 - Math.min(1, dx / sbW));
                e.preventDefault();
              }
            }, { passive: false });

            elMap.addEventListener('touchend', function(e) {
              if (!swMode || !swSidebar) return;
              var touch = null;
              for (var i = 0; i < e.changedTouches.length; i++) {
                if (e.changedTouches[i].identifier === swTouchId) { touch = e.changedTouches[i]; break; }
              }
              if (!touch) { swMode = ''; swSidebar = null; swTouchId = null; return; }
              var dx = touch.clientX - swStartX;
              var sbW = swSidebar.offsetWidth || 300;
              var _sb = swSidebar;
              var _mode = swMode;
              swMode = ''; swSidebar = null; swTouchId = null;
              _sb.style.transition = '';
              closeHandle.style.transition = '';

              if (_mode === 'open') {
                if (-dx > sbW * OPEN_THRESHOLD) {
                  openMobileSb();
                } else {
                  // Snap back closed
                  requestAnimationFrame(function() {
                    _sb.style.transform = 'translateX(100%)';
                    var done = false;
                    function onSnapEnd() {
                      if (done) return; done = true;
                      _sb.removeEventListener('transitionend', onSnapEnd);
                      _sb.classList.remove('jg-sidebar-mobile-open');
                      _sb.style.transform = '';
                      // Restore inline height
                      if (_sb._sbMobileOrigHeight !== undefined) {
                        _sb.style.height = _sb._sbMobileOrigHeight;
                        _sb._sbMobileOrigHeight = undefined;
                      }
                      // Restore sidebar to original DOM position
                      if (_sb._sbMobileOrigParent) {
                        var _p = _sb._sbMobileOrigParent;
                        var _n = _sb._sbMobileOrigNext;
                        _sb._sbMobileOrigParent = null;
                        _sb._sbMobileOrigNext = null;
                        if (_n && _n.parentNode === _p) { _p.insertBefore(_sb, _n); }
                        else { _p.appendChild(_sb); }
                      }
                      mobileBackdrop.classList.remove('active');
                      mobileBackdrop.style.opacity = '';
                    }
                    _sb.addEventListener('transitionend', onSnapEnd);
                    setTimeout(onSnapEnd, 350);
                  });
                }
              } else if (_mode === 'close') {
                if (dx > sbW * CLOSE_THRESHOLD) {
                  closeMobileSb();
                } else {
                  // Snap back open
                  requestAnimationFrame(function() {
                    _sb.style.transform = '';
                    closeHandle.style.transform = '';
                    mobileBackdrop.style.opacity = '';
                  });
                }
              }
            }, { passive: false });

            elMap.addEventListener('touchcancel', function() {
              if (!swMode || !swSidebar) return;
              var _sb = swSidebar;
              var _mode = swMode;
              swMode = ''; swSidebar = null; swTouchId = null;
              if (_mode === 'open') {
                _sb.classList.remove('jg-sidebar-mobile-open');
                _sb.style.transform = ''; _sb.style.transition = '';
                // Restore inline height
                if (_sb._sbMobileOrigHeight !== undefined) {
                  _sb.style.height = _sb._sbMobileOrigHeight;
                  _sb._sbMobileOrigHeight = undefined;
                }
                // Restore sidebar to original DOM position on cancelled swipe
                if (_sb._sbMobileOrigParent) {
                  var _p = _sb._sbMobileOrigParent;
                  var _n = _sb._sbMobileOrigNext;
                  _sb._sbMobileOrigParent = null;
                  _sb._sbMobileOrigNext = null;
                  if (_n && _n.parentNode === _p) { _p.insertBefore(_sb, _n); }
                  else { _p.appendChild(_sb); }
                }
                mobileBackdrop.classList.remove('active');
                mobileBackdrop.style.opacity = '';
              } else if (_mode === 'close') {
                _sb.style.transform = ''; _sb.style.transition = '';
                closeHandle.style.transform = '';
                mobileBackdrop.style.opacity = '';
              }
            }, { passive: true });
          }

          return container;
        }
      });

      map.addControl(new FullscreenControl());

      var cluster = null;
      var sponsoredCluster = null;
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
            // maxClusterRadius as function: breaks apart naturally when zooming in.
            // Radius must be monotonically non-increasing so that zooming in
            // never re-clusters markers that were already visible (unclustered).
            cluster = L.markerClusterGroup({
              showCoverageOnHover: false,
              maxClusterRadius: function(zoom) {
                // zoom < 17: Normal clusters (80px radius)
                // zoom 17-18: Special clusters (35px radius) - only very close places
                // zoom 19 (max): Tiny radius (5px) - only truly overlapping markers
                if (zoom >= 19) return 5;
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

            // Sponsored-only cluster: sponsored pins only cluster among themselves.
            // A lone sponsored pin (no nearby sponsored pins) stays as a standalone marker.
            sponsoredCluster = L.markerClusterGroup({
              showCoverageOnHover: false,
              maxClusterRadius: function(zoom) {
                if (zoom >= 19) return 5;
                if (zoom >= 17) return 35;
                return 80;
              },
              spiderfyOnMaxZoom: false,
              zoomToBoundsOnClick: false,
              spiderfyDistanceMultiplier: 2,
              animate: true,
              animateAddingMarkers: true,
              disableClusteringAtZoom: 20,
              iconCreateFunction: function(clusterGroup) {
                var childMarkers = clusterGroup.getAllChildMarkers();
                var count = childMarkers.length;
                var html = '<div class="jg-cluster-grid">' +
                  '<div class="jg-cluster-grid-top">' +
                  '<div class="jg-cluster-cell jg-cluster-cell--sponsored">' +
                  '<span class="jg-cluster-icon">⭐</span>' +
                  '<span class="jg-cluster-num">' + count + '</span>' +
                  '</div></div></div>';
                return L.divIcon({ html: html, className: 'jg-cluster-wrapper', iconSize: [80, 50] });
              }
            });

            map.addLayer(sponsoredCluster);

            // Shared cluster click handler - spiderfy for normal clusters, list for special clusters
            function handleClusterClick(e) {
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
            }

            cluster.on('clusterclick', handleClusterClick);
            sponsoredCluster.on('clusterclick', handleClusterClick);

            clusterReady = true;

            // Ghost pin: identical appearance to a real sponsored pin, semi-transparent
            if (CFG.ghostPin && CFG.ghostPin.lat && CFG.ghostPin.lng) {
              var gp = CFG.ghostPin;

              // Build via iconFor() so it stays pixel-perfect with real sponsored pins
              var gpFakePoint = {
                sponsored: true, id: 'ghost-pin', title: 'Zareklamuj swoją firmę na mapie',
                type: 'miejsce', images: [], featured_image_index: 0,
                is_pending: false, is_edit: false, is_deletion_requested: false,
                reports_count: 0, user_has_reported: false, author_id: -1
              };
              var gpRealIcon = iconFor(gpFakePoint);
              var gpW = gpRealIcon.options.iconSize[0];
              var gpH = gpRealIcon.options.iconSize[1];

              // Inject "AD" text inside the gold circle (which is inside jg-pin-svg-wrapper)
              // so it lifts together with the pin on hover.
              // The empty gold circle ends with: background:#f0e68c;"></div>
              var gpInnerHtml = gpRealIcon.options.html.replace(
                'background:#f0e68c;"></div>',
                'background:#f0e68c;display:flex;align-items:center;justify-content:center;">' +
                  '<span style="font-size:11px;font-weight:900;color:#92400e;letter-spacing:0.5px;' +
                    'text-shadow:0 0 3px rgba(255,255,255,0.85);pointer-events:none;">AD</span>' +
                '</div>'
              );

              var gpHtml =
                '<div style="opacity:1;position:relative;cursor:pointer;width:' + gpW + 'px;height:' + gpH + 'px;">' +
                  gpInnerHtml +
                  '<div style="position:absolute;top:-3px;right:-3px;width:10px;height:10px;' +
                    'background:#f59e0b;border-radius:50%;border:1.5px solid #fff;' +
                    'animation:jg-pulse 1.5s ease-in-out infinite;"></div>' +
                '</div>';

              var gpIcon = L.divIcon({
                className: 'jg-ghost-pin-marker jg-pin-marker jg-pin-marker--promo',
                html: gpHtml,
                iconSize: [gpW, gpH],
                iconAnchor: [gpW / 2, gpH],
                popupAnchor: [0, -gpH + 5]
              });
              // zIndexOffset bardzo wysoki → ghost pin zawsze na froncie przed innymi pinezkami
              var gpMarker = L.marker([gp.lat, gp.lng], { icon: gpIcon, zIndexOffset: 9000 });
              gpMarker.bindTooltip(
                '<strong style="font-size:13px">📣 Twoja firma mogłaby być tutaj</strong>' +
                '<br><span style="font-size:11px;color:#6b7280">Kliknij, aby dowiedzieć się więcej o reklamie</span>',
                { direction: 'top', offset: [0, -gpH], className: 'jg-ghost-pin-tooltip' }
              );
              gpMarker.on('click', function() { window.open('/reklama', '_blank'); });
              gpMarker.addTo(map);

              if (!document.getElementById('jg-ghost-pin-style')) {
                var s = document.createElement('style');
                s.id = 'jg-ghost-pin-style';
                s.textContent = '@keyframes jg-pulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.4);opacity:0.6}}' +
                  '.jg-ghost-pin-tooltip{background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:6px 10px;box-shadow:0 2px 8px rgba(0,0,0,.15);white-space:nowrap;}' +
                  // Ghost pin zawsze na froncie (bije z-index:1000 prawdziwych sponsorowanych)
                  '.jg-ghost-pin-marker{z-index:10001!important;}';
                document.head.appendChild(s);
              }

              // Rotacja pozycji przy zmianie godziny — ta sama formuła co PHP: hour % count
              var gpCandidates = [
                [50.9068,15.7441],[50.9063,15.7381],[50.9095,15.7399],[50.9101,15.7427],
                [50.9072,15.7460],[50.9055,15.7410],[50.9087,15.7356],[50.9049,15.7389],
                [50.9114,15.7451],[50.9040,15.7420],[50.9031,15.7374],[50.9120,15.7395],
                [50.9079,15.7330],[50.9060,15.7490],[50.9035,15.7445],[50.9108,15.7480],
                [50.9025,15.7355],[50.9145,15.7418],[50.9155,15.7435],[50.9132,15.7402],
                [50.9162,15.7460],[50.9143,15.7380],[50.9078,15.7520],[50.9065,15.7555],
                [50.9090,15.7540],[50.9021,15.7480],[50.9015,15.7440],[50.9098,15.7302],
                [50.9082,15.7280],[50.9118,15.7340],[50.9044,15.7510],[50.9070,15.7580],
                [50.9035,15.7320],[50.9133,15.7460],[50.9058,15.7350],[50.9112,15.7510],
                [50.9089,15.7410],[50.9077,15.7448],[50.9047,15.7465],[50.9102,15.7368]
              ];

              var gpCurrentPeriod = Math.floor(Date.now() / 300000);

              // Natychmiast ustaw pozycję według aktualnego okresu czasu.
              // PHP mógł podać cache'owaną pozycję — JS musi ją nadpisać bez animacji.
              var gpInitialIdx = gpCurrentPeriod % gpCandidates.length;
              gpMarker.setLatLng([gpCandidates[gpInitialIdx][0], gpCandidates[gpInitialIdx][1]]);

              function gpMoveTo(newPos) {
                var el = gpMarker.getElement();
                if (!el) return;
                // Fade out
                el.style.transition = 'opacity 0.45s ease';
                el.style.opacity = '0';
                setTimeout(function() {
                  gpMarker.setLatLng([newPos[0], newPos[1]]);
                  // Krótka pauza by Leaflet przeliczył pozycję, potem fade in
                  setTimeout(function() {
                    el.style.opacity = '1';
                  }, 60);
                }, 460);
              }

              // Sprawdzaj co 30 sekund czy minął 5-minutowy okres
              var gpRotateTimer = setInterval(function() {
                if (!map || !gpMarker) { clearInterval(gpRotateTimer); return; }
                var nowPeriod = Math.floor(Date.now() / 300000);
                if (nowPeriod !== gpCurrentPeriod) {
                  gpCurrentPeriod = nowPeriod;
                  var newIdx = nowPeriod % gpCandidates.length;
                  gpMoveTo(gpCandidates[newIdx]);
                }
              }, 30000);
            }

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
            if (typeof window.openJoinModal === 'function') window.openJoinModal({trigger: 'action'});
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
                '<form id="add-form" class="jg-grid cols-2" novalidate>' +
                '<input type="hidden" name="lat" id="add-lat-input" value="' + lat + '">' +
                '<input type="hidden" name="lng" id="add-lng-input" value="' + lng + '">' +
                '<input type="hidden" name="address" id="add-address-input" value="">' +
                limitsHtml +
                '<div class="cols-2" id="add-address-display" style="padding:8px 12px;background:#f3f4f6;border-left:3px solid #8d2324;border-radius:4px;font-size:13px;color:#374151;margin-bottom:8px"><strong>📍 Wczytywanie adresu...</strong></div>' +
                '<label style="display:block"><span style="display:block;margin-bottom:4px">Tytuł*</span><input name="title" required placeholder="Nazwa miejsca" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px"></label>' +
                '<label style="display:block"><span style="display:block;margin-bottom:4px">Typ*</span><select name="type" id="add-type-select" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' +
                '<option value="zgloszenie">Zgłoszenie</option>' +
                '<option value="ciekawostka">Ciekawostka</option>' +
                '<option value="miejsce" selected>Miejsce</option>' +
                '</select></label>' +
                '<label class="cols-2" id="add-category-field" style="display:block"><span style="display:block;margin-bottom:4px;color:#dc2626">Kategoria zgłoszenia*</span><select name="category" id="add-category-select" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' +
                generateCategoryOptions('') +
                '</select></label>' +
                '<label class="cols-2" id="add-place-category-field" style="display:none"><span>Kategoria miejsca</span> <select name="place_category" id="add-place-category-select" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' +
                generatePlaceCategoryOptions('') +
                '</select></label>' +
                '<label class="cols-2" id="add-curiosity-category-field" style="display:none"><span>Kategoria ciekawostki</span> <select name="curiosity_category" id="add-curiosity-category-select" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' +
                generateCuriosityCategoryOptions('') +
                '</select></label>' +
                '<div class="cols-2"><label style="display:block;margin-bottom:4px">Opis*</label>' + buildRichEditorHtml('add-rte', 800, '', 4) + '</div>' +
                '<div class="cols-2" id="add-opening-hours-field" style="display:none"><label style="display:block;margin-bottom:6px;font-weight:500">Godziny otwarcia</label>' + buildOpeningHoursPickerHtml('add', '') + '</div>' +
                '<div class="cols-2" id="add-price-range-field" style="display:none"><label style="display:block;margin-bottom:6px;font-weight:500">💰 Zakres cenowy</label>' + buildPriceRangeSelectHtml('add', '') + '</div>' +
                '<div class="cols-2" id="add-serves-cuisine-field" style="display:none"><label style="display:block;margin-bottom:4px;font-weight:500">🥗 Rodzaj kuchni <input type="text" name="serves_cuisine" id="add-serves-cuisine-input" placeholder="np. polska, włoska, pizza…" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></label></div>' +
                '<div class="cols-2"><label style="display:block;margin-bottom:4px">Tagi (max 5)</label>' + buildTagInputHtml('add-tags') + '</div>' +
                '<div class="cols-2" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:12px;margin:4px 0">' +
                '<strong style="display:block;margin-bottom:10px;color:#0369a1">📋 Dane kontaktowe (opcjonalnie)</strong>' +
                '<label style="display:block;margin-bottom:8px">Telefon <input type="text" name="phone" id="add-phone-input" placeholder="np. 123 456 789" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></label>' +
                '<label style="display:block;margin-bottom:8px">Email kontaktowy <input type="email" name="contact_email" id="add-email-input" placeholder="np. kontakt@firma.pl" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></label>' +
                '<label style="display:block;margin-bottom:0">Strona internetowa <input type="text" name="website" id="add-website-input" placeholder="np. jeleniagora.pl" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></label>' +
                '</div>' +
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

              // Initialize opening hours picker
              var addOhPicker = initOpeningHoursPicker('add', modalAdd);

              // Reset scroll after init — contenteditable triggers browser auto-scroll
              setTimeout(function() {
                var modalC = qs('.jg-modal', modalAdd);
                if (modalC) modalC.scrollTop = 0;
                form.scrollTop = 0;
              }, 0);

              // On form submit, ensure the hidden input has content
              var origAddSubmit = form.onsubmit;
              form.addEventListener('submit', function() {
                if (addRte) addRte.syncContent();
                if (addTagInput) addTagInput.syncHidden();
                if (addOhPicker) addOhPicker.syncHidden();
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

              var addOpeningHoursField = qs('#add-opening-hours-field', modalAdd);
              var addPriceRangeField = qs('#add-price-range-field', modalAdd);
              var addServesCuisineField = qs('#add-serves-cuisine-field', modalAdd);

              function updateAddExtraFields() {
                var cat = placeCategorySelect ? placeCategorySelect.value : '';
                var selectedType = typeSelect ? typeSelect.value : '';
                if (addPriceRangeField) addPriceRangeField.style.display = (selectedType === 'miejsce' && isPriceRangeCategory(cat)) ? 'block' : 'none';
                if (addServesCuisineField) addServesCuisineField.style.display = (selectedType === 'miejsce' && isServesCuisineCategory(cat)) ? 'block' : 'none';
              }

              if (placeCategorySelect) placeCategorySelect.addEventListener('change', updateAddExtraFields);

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

                  // Show opening hours only for miejsce
                  if (addOpeningHoursField) {
                    addOpeningHoursField.style.display = selectedType === 'miejsce' ? 'block' : 'none';
                  }

                  updateAddExtraFields();
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

            function markErr(container, text) {
              container.style.background = '#fff0f0';
              container.style.borderRadius = '8px';
              container.style.boxShadow = '0 0 0 2px #b91c1c';
              container.style.padding = '8px';
              var existing = container.querySelector('.jg-val-err');
              if (!existing) {
                var errDiv = document.createElement('div');
                errDiv.className = 'jg-val-err';
                errDiv.style.cssText = 'font-size:12px;color:#b91c1c;font-weight:600;margin-top:6px';
                errDiv.textContent = '⚠ ' + text;
                container.appendChild(errDiv);
              }
            }
            function clearErr(container) {
              container.style.background = '';
              container.style.borderRadius = '';
              container.style.boxShadow = '';
              container.style.padding = '';
              var errDiv = container.querySelector('.jg-val-err');
              if (errDiv) errDiv.remove();
            }

            var firstErrContainer = null;

            var titleInput = qs('input[name="title"]', form);
            var titleContainer = titleInput && titleInput.closest('label');
            if (titleInput && !titleInput.value.trim()) {
              if (titleContainer) { markErr(titleContainer, 'Podaj nazwę miejsca.'); if (!firstErrContainer) firstErrContainer = titleContainer; }
            } else if (titleContainer) { clearErr(titleContainer); }

            var contentVal = qs('#add-rte-hidden', modalAdd);
            var rteWrap = qs('#add-rte-wrap', modalAdd);
            var rteContainer = rteWrap && rteWrap.parentElement;
            if (contentVal && !contentVal.value.replace(/<\/?[^>]+(>|$)/g, '').trim()) {
              if (rteContainer) { markErr(rteContainer, 'Dodaj opis miejsca.'); if (!firstErrContainer) firstErrContainer = rteContainer; }
            } else if (rteContainer) { clearErr(rteContainer); }

            var addTypeEl = qs('#add-type-select', form);
            var catField = qs('#add-category-field', modalAdd);
            var catSelect = qs('#add-category-select', form);
            if (addTypeEl && addTypeEl.value === 'zgloszenie' && catSelect && !catSelect.value) {
              if (catField) { markErr(catField, 'Wybierz kategorię zgłoszenia.'); if (!firstErrContainer) firstErrContainer = catField; }
            } else if (catField) { clearErr(catField); }

            if (firstErrContainer) {
              msg.textContent = '';
              firstErrContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
              return;
            }

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

            function _doAddFetch() {
              var submitBtn = qs('button[type="submit"]', form);
              if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Wysyłanie…'; }
              msg.textContent = '';
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
                    var duplicatePointId = parseInt(j.data.duplicate_point_id, 10);
                    msg.innerHTML = (j.data.message || 'Błąd') + ' <br><button style="margin-top:8px;padding:6px 12px;background:#8d2324;color:#fff;border:none;border-radius:4px;cursor:pointer" onclick="' +
                      'document.getElementById(\'jg-map-modal-add\').style.display=\'none\';' +
                      'window.location.hash=\'#point-' + duplicatePointId + '\';' +
                      '">Zobacz istniejące zgłoszenie</button>';
                    msg.style.color = '#b91c1c';
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Wyślij do moderacji'; }
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
                refreshChallengeProgress();

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
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Wyślij do moderacji'; }
              });
            }

            // Duplicate check: only for miejsca and ciekawostki (zgłoszenia have server-side check)
            var _addType = fd.get('type') || '';
            var _addLat  = parseFloat(fd.get('lat'));
            var _addLng  = parseFloat(fd.get('lng'));
            if (_addType !== 'zgloszenie' && !isNaN(_addLat) && !isNaN(_addLng)) {
              var _addDups = jgFindDuplicates(fd.get('title') || '', fd.get('content') || '', _addLat, _addLng, _addType);
              if (_addDups.length) {
                msg.textContent = '';
                jgShowDuplicateWarning(_addDups, modalAdd, _doAddFetch);
                return;
              }
            }
            _doAddFetch();
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

        svgPin += '</defs>';

        // Pin shape: rounded Google Maps style with smooth curves
        svgPin += '<path d="M16 0 C7.163 0 0 7.163 0 16 C0 19 1 22 4 26 L16 40 L28 26 C31 22 32 19 32 16 C32 7.163 24.837 0 16 0 Z" ' +
          'fill="url(#' + gradientId + ')"/>';

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

      // ===== SECTION: VOTING & RATING =====
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
                  if (_jgNativeReplaceState) _jgNativeReplaceState({}, '', newUrl);
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
                  if (_jgNativeReplaceState) _jgNativeReplaceState({}, '', newUrl);
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
                  if (_jgNativeReplaceState) {
                    var cleanUrl = window.location.origin + window.location.pathname;
                    _jgNativeReplaceState(null, '', cleanUrl);
                  }
                };

                if (fromPoint) {
                  // Coming from point page: skip pulsing, open modal immediately.
                  // HTML pin page already fired GA4 page_view — skip the modal's hit.
                  // Clean URL immediately to prevent a second checkDeepLink() call
                  // (triggered by background cache refresh) from seeing from=point again.
                  if (_jgNativeReplaceState) {
                    _jgNativeReplaceState(null, '', window.location.origin + window.location.pathname);
                  }
                  skipNextGaPageView = true;
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
        var maxPulses = 4; // 4 pulses over ~1.2 seconds
        var pulseInterval = setInterval(function() {
          pulseCount++;

          // Toggle opacity for pulse effect
          if (pulseCount % 2 === 0) {
            pulsingCircle.setStyle({ fillOpacity: 0.6, weight: 4 });
          } else {
            pulsingCircle.setStyle({ fillOpacity: 0.2, weight: 2 });
          }

          // Remove after animation ends, then call callback (modal opens AFTER circle disappears)
          if (pulseCount >= maxPulses) {
            clearInterval(pulseInterval);
            setTimeout(function() {
              map.removeLayer(pulsingCircle);

              // Call callback AFTER circle is removed — modal opens only now
              if (callback && typeof callback === 'function') {
                callback();
              }
            }, 100);
          }
        }, 300); // Pulse every 300ms (shorter: 4×300ms = 1.2s total)
      }

      var ALL = [];
      var dataLoaded = false; // Track if data has been loaded (even if empty)
      var skipNextGaPageView = false; // Set when modal auto-opens from HTML pin redirect
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
        if (sponsoredCluster) allMarkers = allMarkers.concat(sponsoredCluster.getLayers());

        for (var i = 0; i < allMarkers.length; i++) {
          var marker = allMarkers[i];
          if (marker.options && marker.options.pointId && pointIds.indexOf(marker.options.pointId) !== -1) {
            markersToRemove.push(marker);
          }
        }

        if (markersToRemove.length > 0) {
          var regularToRemove = markersToRemove.filter(function(m) { return !m.options.isPromo; });
          var promoToRemove = markersToRemove.filter(function(m) { return !!m.options.isPromo; });
          if (regularToRemove.length > 0) cluster.removeLayers(regularToRemove);
          if (promoToRemove.length > 0 && sponsoredCluster) sponsoredCluster.removeLayers(promoToRemove);
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
            var p = {
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
              email: r.email || null,
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
              rating: +(r.rating || 0),
              ratings_count: +(r.ratings_count || 0),
              my_rating: (r.my_rating || ''),
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
              tags: r.tags || [],
              opening_hours: r.opening_hours || null,
              price_range: r.price_range || null,
              serves_cuisine: r.serves_cuisine || null
            };
            // Forward-compatible: copy any new server fields not yet explicitly mapped
            for (var _k in r) {
              if (Object.prototype.hasOwnProperty.call(r, _k) && !(_k in p)) {
                p[_k] = r[_k];
              }
            }
            return p;
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
          if (sponsoredCluster) sponsoredCluster.clearLayers();
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

        // Add markers to their respective clusters (reduces animation flicker)
        // Sponsored pins go to sponsoredCluster so they only cluster among themselves,
        // preventing a lone sponsored pin from being absorbed into a regular-pin cluster.
        if (clusterReady && cluster && newMarkers.length > 0) {
          var regularMarkers = [];
          var promoMarkers = [];
          newMarkers.forEach(function(m) {
            if (m.options.isPromo) {
              promoMarkers.push(m);
            } else {
              regularMarkers.push(m);
            }
          });
          if (regularMarkers.length > 0) cluster.addLayers(regularMarkers);
          if (promoMarkers.length > 0 && sponsoredCluster) sponsoredCluster.addLayers(promoMarkers);
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
            }, 400);
          }, 300);
        } else {
          // Wait for cluster animation to complete before showing map
          setTimeout(function() {
            showMap();
            hideLoading();
            // Check for deep-linked point after map is fully ready
            checkDeepLink();
          }, 400);
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

      function colorForRating(r) {
        if (!r || r === 0) return 'color:#9ca3af';
        if (r >= 4.5) return 'color:#b45309;font-weight:800';
        if (r >= 3.5) return 'color:#15803d;font-weight:700';
        if (r >= 2.5) return 'color:#374151;font-weight:600';
        return 'color:#b91c1c;font-weight:600';
      }

      // Keep legacy alias used by sidebar sort
      function colorForVotes(n) { return colorForRating(n); }

      function ratingCountLabel(count) {
        if (count === 1) return 'ocena';
        var mod10 = count % 10;
        var mod100 = count % 100;
        if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return 'oceny';
        return 'ocen';
      }

      function pluralVotes(n) { return ratingCountLabel(n); }

      function starsHtml(avg, myRating) {
        var myR      = parseInt(myRating, 10) || 0;
        var fillUpTo = myR > 0 ? myR : Math.round(avg);
        var html = '';
        for (var s = 1; s <= 5; s++) {
          var isFilled = s <= fillUpTo;
          var isActive = s <= fillUpTo;
          html += '<button class="jg-star-btn' + (isActive ? ' active' : '') + '" id="v-star-' + s + '" data-star="' + s + '" title="' + s + ' ' + (s === 1 ? 'gwiazdka' : (s <= 4 ? 'gwiazdki' : 'gwiazdek')) + '">' + (isFilled ? '★' : '☆') + '</button>';
        }
        return html;
      }

      // ===== SECTION: USER MODALS & LIGHTBOX =====
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
          _ajax_nonce: CFG.nonce || '',
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
      function openUserModal(userId, pointsPage, photosPage, editedPointsPage) {
        pointsPage = pointsPage || 1;
        photosPage = photosPage || 1;
        editedPointsPage = editedPointsPage || 1;

        api('jg_get_user_info', { user_id: userId, points_page: pointsPage, photos_page: photosPage, edited_points_page: editedPointsPage }).then(function(user) {
          if (!user) {
            showAlert('Błąd pobierania informacji o użytkowniku');
            return;
          }
          var memberSince = user.member_since ? new Date(user.member_since).toLocaleDateString('pl-PL') : '-';
          var lastActivity = user.last_activity ? new Date(user.last_activity).toLocaleDateString('pl-PL') : 'Brak aktywności';
          var lastActivityType = user.last_activity_type || '';

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

          // Edited points list
          var editedPointsHtml = '';
          if (user.edited_points && user.edited_points.length > 0) {
            editedPointsHtml = '<div style="margin-top:20px">' +
              '<h4 style="margin:0 0 8px 0;color:#374151;font-size:16px;border-bottom:1px solid #e5e7eb;padding-bottom:8px">✏️ Edytowane pinezki (' + user.edited_points_total + ')</h4>' +
              '<div style="margin-top:12px">';
            for (var ei = 0; ei < user.edited_points.length; ei++) {
              var ep = user.edited_points[ei];
              var epTypeLabels = {
                'miejsce': '📍 Miejsce',
                'ciekawostka': '💡 Ciekawostka',
                'zgloszenie': '📢 Zgłoszenie'
              };
              var epTypeLabel = epTypeLabels[ep.type] || ep.type;
              var epEditedAt = ep.last_edited_at ? new Date(ep.last_edited_at).toLocaleDateString('pl-PL') : '-';

              editedPointsHtml += '<div style="padding:10px;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:8px">' +
                '<div style="font-weight:600;margin-bottom:4px">' + esc(ep.title) + '</div>' +
                '<div style="font-size:12px;color:#6b7280">' +
                '<span style="margin-right:12px">' + epTypeLabel + '</span>' +
                '<span>Ostatnia edycja: ' + epEditedAt + '</span>' +
                '</div>' +
                '</div>';
            }
            editedPointsHtml += '</div>';

            // Edited points pagination
            if (user.edited_points_pages > 1) {
              editedPointsHtml += '<div style="display:flex;justify-content:center;align-items:center;gap:8px;margin-top:12px">';
              editedPointsHtml += '<button class="jg-user-modal-edited-points-prev" style="padding:6px 14px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer;font-size:13px' + (editedPointsPage <= 1 ? ';opacity:0.4;pointer-events:none' : '') + '">&laquo; Poprzednie</button>';
              editedPointsHtml += '<span style="font-size:13px;color:#6b7280">Strona ' + user.edited_points_page + ' z ' + user.edited_points_pages + '</span>';
              editedPointsHtml += '<button class="jg-user-modal-edited-points-next" style="padding:6px 14px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer;font-size:13px' + (editedPointsPage >= user.edited_points_pages ? ';opacity:0.4;pointer-events:none' : '') + '">Następne &raquo;</button>';
              editedPointsHtml += '</div>';
            }

            editedPointsHtml += '</div>';
          } else if (user.edited_points_total === 0) {
            editedPointsHtml = '<div style="margin-top:20px">' +
              '<h4 style="margin:0 0 8px 0;color:#374151;font-size:16px;border-bottom:1px solid #e5e7eb;padding-bottom:8px">✏️ Edytowane pinezki</h4>' +
              '<div style="padding:20px;text-align:center;color:#9ca3af">Brak edytowanych pinezek</div>' +
              '</div>';
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
            (CFG.isAdmin ?
              '<div style="padding:16px;background:#f9fafb;border-radius:8px">' +
              '<div style="font-size:12px;color:#6b7280;margin-bottom:4px">⏱️ Ostatnia aktywność</div>' +
              '<div style="font-weight:600">' + lastActivity + '</div>' +
              (lastActivityType ? '<div style="font-size:11px;color:#9ca3af;margin-top:3px">' + lastActivityType + '</div>' : '') +
              (user.last_activity ? '<div style="font-size:11px;color:#6366f1;margin-top:5px;cursor:pointer;text-decoration:underline" onclick="openUserActivityModal(' + userId + ')">Zobacz historię aktywności →</div>' : '') +
              '</div>'
            : '') +
            '<div style="padding:16px;background:#f9fafb;border-radius:8px">' +
            '<div style="font-size:12px;color:#6b7280;margin-bottom:4px">📍 Dodane miejsca</div>' +
            '<div style="font-weight:600;font-size:24px">' + user.points_count + '</div>' +
            '</div>' +
            '</div>' +
            '<h4 style="margin:0 0 12px 0;color:#374151;font-size:16px;border-bottom:1px solid #e5e7eb;padding-bottom:8px">📊 Statystyki pinezek</h4>' +
            typeStatsHtml +
            '<div>' +
            '<h4 style="margin:0 0 8px 0;color:#374151;font-size:16px;border-bottom:1px solid #e5e7eb;padding-bottom:8px">📍 Dodane miejsca (' + user.points_count + ')</h4>' +
            pointsHtml +
            '</div>' +
            editedPointsHtml +
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
                  '<span class="jg-ach-icon-emoji">' + esc(ach.icon) + '</span>' +
                  '<span class="jg-ach-icon-name">' + esc(ach.name) + '</span>' +
                  '<span class="jg-ach-icon-desc">' + esc(ach.description) + '</span>' +
                  '</div>';
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
            pointsPrev.onclick = function() { openUserModal(userId, pointsPage - 1, photosPage, editedPointsPage); };
          }
          if (pointsNext && pointsPage < user.points_pages) {
            pointsNext.onclick = function() { openUserModal(userId, pointsPage + 1, photosPage, editedPointsPage); };
          }

          // Edited points pagination handlers
          var editedPrev = modalReport.querySelector('.jg-user-modal-edited-points-prev');
          var editedNext = modalReport.querySelector('.jg-user-modal-edited-points-next');
          if (editedPrev && editedPointsPage > 1) {
            editedPrev.onclick = function() { openUserModal(userId, pointsPage, photosPage, editedPointsPage - 1); };
          }
          if (editedNext && editedPointsPage < user.edited_points_pages) {
            editedNext.onclick = function() { openUserModal(userId, pointsPage, photosPage, editedPointsPage + 1); };
          }

          // Photos pagination handlers
          var photosPrev = modalReport.querySelector('.jg-user-modal-photos-prev');
          var photosNext = modalReport.querySelector('.jg-user-modal-photos-next');
          if (photosPrev && photosPage > 1) {
            photosPrev.onclick = function() { openUserModal(userId, pointsPage, photosPage - 1, editedPointsPage); };
          }
          if (photosNext && photosPage < user.photos_pages) {
            photosNext.onclick = function() { openUserModal(userId, pointsPage, photosPage + 1, editedPointsPage); };
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
       * Open last-10-activity modal for a user
       */
      function openUserActivityModal(userId) {
        var html = '<header style="background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;padding:16px 20px;border-radius:12px 12px 0 0;display:flex;justify-content:space-between;align-items:center">' +
          '<h3 style="margin:0;font-size:16px">⏱️ Ostatnia aktywność</h3>' +
          '<button class="jg-close" id="activity-modal-close" style="color:#fff;opacity:0.9">&times;</button>' +
          '</header>' +
          '<div style="padding:20px"><p style="color:#6b7280;text-align:center">Ładowanie...</p></div>';
        open(modalEdit, html);
        qs('#activity-modal-close', modalEdit).onclick = function() { close(modalEdit); };

        api('jg_get_user_activity', { user_id: userId }).then(function(items) {
          if (!items || !items.length) {
            qs('.jg-modal', modalEdit).querySelector('div[style*="padding:20px"]').innerHTML = '<p style="color:#6b7280;text-align:center">Brak zarejestrowanych aktywności.</p>';
            return;
          }
          var listHtml = '<ul style="list-style:none;margin:0;padding:0">';
          for (var i = 0; i < items.length; i++) {
            var it = items[i];
            var date = it.ts ? new Date(it.ts).toLocaleString('pl-PL', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';
            listHtml +=
              '<li style="display:flex;gap:12px;align-items:flex-start;padding:12px 0;border-bottom:1px solid #f1f5f9">' +
              '<span style="font-size:20px;flex-shrink:0;margin-top:1px">' + it.icon + '</span>' +
              '<div style="flex:1;min-width:0">' +
              '<div style="font-weight:600;font-size:13px;color:#1f2937">' + esc(it.label) + '</div>' +
              '<div style="font-size:12px;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="' + esc(it.point_title) + '">' + esc(it.point_title) + '</div>' +
              '</div>' +
              '<div style="font-size:11px;color:#9ca3af;white-space:nowrap;flex-shrink:0">' + date + '</div>' +
              '</li>';
          }
          listHtml += '</ul>';
          var container = qs('.jg-modal', modalEdit);
          container.querySelector('div[style*="padding:20px"]').innerHTML = listHtml;
        }).catch(function() {
          var container = qs('.jg-modal', modalEdit);
          if (container) container.querySelector('div[style*="padding:20px"]').innerHTML = '<p style="color:#ef4444;text-align:center">Błąd ładowania danych.</p>';
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

          var isAdminView = CFG.isAdmin;

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
            var earned = !!a.earned;
            var zeroed = !!a.zeroed;
            var earnedDate = a.earned_at ? new Date(a.earned_at).toLocaleDateString('pl-PL') : '';
            var showLocked = !earned;

            html += '<div class="jg-achievement-card' +
              (showLocked ? ' jg-achievement-locked' : '') +
              (zeroed ? ' jg-achievement-zeroed' : '') +
              '" style="border-color:' + color + '"' +
              ' data-ach-id="' + parseInt(a.id) + '"' +
              ' data-zeroed="' + (zeroed ? '1' : '0') + '"' +
              ' data-challenge-ach="' + (a.condition_type === 'challenge_completed' ? '1' : '0') + '">' +
              (isAdminView ? '<button class="jg-ach-admin-remove" title="Usuń osiągnięcie">\xd7</button>' : '') +
              '<div class="jg-achievement-card-icon" style="box-shadow:' + (earned ? '0 0 12px ' + color : 'none') + ';border-color:' + color + '">' +
              '<span style="font-size:28px">' + (earned ? esc(a.icon) : '🔒') + '</span></div>' +
              '<div class="jg-achievement-card-info">' +
              '<div class="jg-achievement-card-name">' + esc(a.name) + '</div>' +
              '<div class="jg-achievement-card-desc">' + esc(a.description) + '</div>' +
              '<div class="jg-achievement-card-rarity" style="color:' + color + '">' + label + '</div>' +
              (earnedDate && !zeroed ? '<div class="jg-achievement-card-date">Zdobyto: ' + earnedDate + '</div>' : '') +
              '</div></div>';
          }

          html += '</div></div>';

          open(modalReportsList, html);
          qs('#ach-modal-close', modalReportsList).onclick = function() {
            close(modalReportsList);
          };

          // Admin X button handlers
          if (isAdminView) {
            var removeBtns = modalReportsList.querySelectorAll('.jg-ach-admin-remove');
            for (var j = 0; j < removeBtns.length; j++) {
              (function(btn) {
                btn.onclick = function(e) {
                  e.stopPropagation();
                  var card           = btn.parentNode;
                  var achId          = parseInt(card.getAttribute('data-ach-id'));
                  var isZeroed       = card.getAttribute('data-zeroed') === '1';
                  var isChallengeAch = card.getAttribute('data-challenge-ach') === '1';
                  var action         = (isChallengeAch || isZeroed) ? 'block' : 'zero';
                  var achName        = (card.querySelector('.jg-achievement-card-name') || {}).textContent || 'to osiągnięcie';
                  var msg            = action === 'block'
                    ? 'Czy na pewno chcesz <strong>trwale usunąć</strong> osiągnięcie <em>' + esc(achName) + '</em>?'
                    : 'Czy na pewno chcesz <strong>wyzerować</strong> osiągnięcie <em>' + esc(achName) + '</em>? (będzie widoczne jako zablokowane)';

                  showConfirm(msg).then(function(confirmed) {
                    if (!confirmed) return;
                    api('jg_admin_manage_user_achievement', {
                      user_id: userId,
                      achievement_id: achId,
                      manage_action: action
                    }).then(function() {
                      if (action === 'block') {
                        card.parentNode.removeChild(card);
                      } else {
                        card.classList.add('jg-achievement-locked', 'jg-achievement-zeroed');
                        card.setAttribute('data-zeroed', '1');
                        var iconSpan = card.querySelector('.jg-achievement-card-icon span');
                        if (iconSpan) iconSpan.textContent = '🔒';
                        var iconBox = card.querySelector('.jg-achievement-card-icon');
                        if (iconBox) iconBox.style.boxShadow = 'none';
                        var dateEl = card.querySelector('.jg-achievement-card-date');
                        if (dateEl) dateEl.parentNode.removeChild(dateEl);
                      }
                    }).catch(function() {});
                  });
                };
              })(removeBtns[j]);
            }
          }
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

      // -----------------------------------------------------------------------
      // Menu section (modal preview)
      // -----------------------------------------------------------------------

      // ===== SECTION: PLACE DETAIL EDITORS =====
      function loadMenuSection(p, menuSection) {
        if (!menuSection) return;
        var menuContent = menuSection.querySelector('#jg-menu-content') || menuSection;
        menuContent.innerHTML = '<div style="color:#9ca3af;padding:8px 0">Ładowanie menu\u2026</div>';

        var fd = new FormData();
        fd.append('action', 'jg_get_menu');
        fd.append('_ajax_nonce', CFG.nonce);
        fd.append('point_id', p.id);

        fetch(CFG.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function(r) { return r.json(); })
          .then(function(j) {
            var sections = (j && j.success && j.data && j.data.sections) ? j.data.sections : [];
            var photos   = (j && j.success && j.data && j.data.photos)   ? j.data.photos   : [];

            if (!sections.length && !photos.length) {
              menuSection.style.display = 'none';
              return;
            }
            menuSection.style.display = '';

            var html = '';

            // Photos row (thumbnails → lightbox)
            if (photos.length) {
              html += '<div class="jg-menu-modal-photos">';
              photos.forEach(function(ph, idx) {
                html += '<div class="jg-menu-modal-photo" data-idx="' + idx + '" data-full="' + esc(ph.url) + '">' +
                  '<img src="' + esc(ph.thumb_url || ph.url) + '" alt="Zdjęcie menu" loading="lazy">' +
                  '</div>';
              });
              html += '</div>';
            }

            // Sections + items preview (up to 6 items total)
            var shownItems = 0;
            var maxItems = 6;
            sections.forEach(function(sec) {
              if (!sec.items || !sec.items.length) return;
              if (shownItems >= maxItems) return;
              html += '<div class="jg-menu-modal-sec-name">' + esc(sec.name) + '</div>';
              sec.items.forEach(function(item) {
                if (shownItems >= maxItems) return;
                shownItems++;
                // Variants-aware price
                var priceStr = '';
                var variants = [];
                try { variants = item.variants ? JSON.parse(item.variants) : []; } catch(e) {}
                if (variants && variants.length > 1) {
                  var minP = null;
                  variants.forEach(function(v) {
                    var vp = parseFloat(v.price);
                    if (!isNaN(vp) && (minP === null || vp < minP)) minP = vp;
                  });
                  priceStr = minP !== null ? 'od ' + minP.toFixed(2).replace('.', ',') + '\u00a0z\u0142' : '';
                } else if (variants && variants.length === 1) {
                  var vp = parseFloat(variants[0].price);
                  var vlabel = (variants[0].label || '').trim();
                  if (!isNaN(vp)) priceStr = (vlabel ? vlabel + ': ' : '') + vp.toFixed(2).replace('.', ',') + '\u00a0z\u0142';
                } else if (item.price) {
                  var p2 = parseFloat(item.price);
                  priceStr = !isNaN(p2) ? p2.toFixed(2).replace('.', ',') + '\u00a0z\u0142' : '';
                }
                html += '<div class="jg-menu-modal-row">' +
                  '<span class="jg-menu-modal-name">' + esc(item.name) + '</span>' +
                  (priceStr ? '<span class="jg-menu-modal-price">' + priceStr + '</span>' : '') +
                  '</div>';
              });
            });

            // Link to full menu page
            var menuUrl = (CFG.homeUrl || '/') + 'miejsce/' + encodeURIComponent(p.slug) + '/menu/';
            html += '<div style="margin-top:10px">' +
              '<a href="' + menuUrl + '" target="_blank" class="jg-menu-modal-link">Zobacz pełne menu \u2192</a>' +
              '</div>';

            menuContent.innerHTML = html;

            // Bind lightbox to photo thumbnails
            var photoEls = menuSection.querySelectorAll('.jg-menu-modal-photo');
            var photoList = photos.map(function(ph) { return ph.url; });

            function openLightbox(startIdx) {
              var current = startIdx;

              var overlay = document.createElement('div');
              overlay.id = 'jg-menu-lb';
              overlay.style.cssText = 'position:fixed;inset:0;z-index:200000;background:rgba(0,0,0,0.92);display:flex;align-items:center;justify-content:center;';

              var img = document.createElement('img');
              img.style.cssText = 'max-width:90vw;max-height:88vh;border-radius:6px;box-shadow:0 4px 32px rgba(0,0,0,0.6);display:block;';

              var closeBtn = document.createElement('button');
              closeBtn.innerHTML = '&times;';
              closeBtn.setAttribute('aria-label', 'Zamknij');
              closeBtn.style.cssText = 'position:absolute;top:16px;right:20px;background:none;border:none;color:#fff;font-size:2.4rem;cursor:pointer;line-height:1;z-index:1;';

              var prevBtn = document.createElement('button');
              prevBtn.innerHTML = '&#8249;';
              prevBtn.style.cssText = 'position:absolute;left:12px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:2rem;cursor:pointer;border-radius:4px;padding:8px 14px;z-index:1;';

              var nextBtn = document.createElement('button');
              nextBtn.innerHTML = '&#8250;';
              nextBtn.style.cssText = 'position:absolute;right:12px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:2rem;cursor:pointer;border-radius:4px;padding:8px 14px;z-index:1;';

              function setImg(idx) {
                img.src = photoList[idx];
                prevBtn.style.display = photoList.length > 1 ? '' : 'none';
                nextBtn.style.display = photoList.length > 1 ? '' : 'none';
              }

              function closeLb() { if (overlay.parentNode) overlay.parentNode.removeChild(overlay); }

              closeBtn.onclick = closeLb;
              overlay.onclick = function(e) { if (e.target === overlay) closeLb(); };
              prevBtn.onclick = function(e) { e.stopPropagation(); current = (current - 1 + photoList.length) % photoList.length; setImg(current); };
              nextBtn.onclick = function(e) { e.stopPropagation(); current = (current + 1) % photoList.length; setImg(current); };

              document.addEventListener('keydown', function kh(e) {
                if (e.key === 'Escape') { closeLb(); document.removeEventListener('keydown', kh); }
                if (e.key === 'ArrowLeft') { current = (current - 1 + photoList.length) % photoList.length; setImg(current); }
                if (e.key === 'ArrowRight') { current = (current + 1) % photoList.length; setImg(current); }
              });

              overlay.appendChild(closeBtn);
              if (photoList.length > 1) { overlay.appendChild(prevBtn); overlay.appendChild(nextBtn); }
              overlay.appendChild(img);
              document.body.appendChild(overlay);
              setImg(current);
            }

            photoEls.forEach(function(el) {
              el.style.cursor = 'pointer';
              el.onclick = function(e) {
                e.stopPropagation();
                openLightbox(parseInt(this.getAttribute('data-idx')) || 0);
              };
            });
          })
          .catch(function() {
            menuSection.innerHTML = '<div style="color:#ef4444;padding:8px 0">Błąd ładowania menu.</div>';
          });
      }

      // -----------------------------------------------------------------------
      // Menu editor
      // -----------------------------------------------------------------------

      // -----------------------------------------------------------------------
      // Offerings (services / products)
      // -----------------------------------------------------------------------

      function loadOfferingsSection(p, offeringsSection) {
        if (!offeringsSection) return;
        var content = offeringsSection.querySelector('#jg-offerings-content') || offeringsSection;
        content.innerHTML = '<div style="color:#9ca3af;padding:8px 0">Ładowanie\u2026</div>';

        var fd = new FormData();
        fd.append('action', 'jg_get_offerings');
        fd.append('_ajax_nonce', CFG.nonce);
        fd.append('point_id', p.id);

        fetch(CFG.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function(r) { return r.json(); })
          .then(function(j) {
            var items = (j && j.success && j.data && j.data.items) ? j.data.items : [];

            if (!items.length) {
              offeringsSection.style.display = 'none';
              return;
            }
            offeringsSection.style.display = '';

            var html = '';
            var shown = 0;
            var maxShown = 6;
            items.forEach(function(item) {
              if (shown >= maxShown) return;
              shown++;
              var priceStr = '';
              if (item.price !== null && item.price !== '') {
                var p2 = parseFloat(item.price);
                priceStr = !isNaN(p2) ? p2.toFixed(2).replace('.', ',') + '\u00a0z\u0142' : '';
              }
              html += '<div class="jg-menu-modal-row">' +
                '<span class="jg-menu-modal-name">' + esc(item.name) + '</span>' +
                (priceStr ? '<span class="jg-menu-modal-price">' + priceStr + '</span>' : '') +
                '</div>';
            });
            if (items.length > maxShown) {
              html += '<div style="font-size:0.8rem;color:#9ca3af;margin-top:4px">+ ' + (items.length - maxShown) + ' więcej\u2026</div>';
            }

            // Link to full offerings page
            var offUrl = (CFG.homeUrl || '/') + 'miejsce/' + encodeURIComponent(p.slug) + '/oferta/';
            html += '<div style="margin-top:10px">' +
              '<a href="' + offUrl + '" target="_blank" class="jg-menu-modal-link">Zobacz pe\u0142n\u0105 ofert\u0119 \u2192</a>' +
              '</div>';

            content.innerHTML = html;
          })
          .catch(function() {
            offeringsSection.style.display = 'none';
          });
      }

      function openOfferingsEditor(p) {
        var ofLabel = getOfferingsLabel(p.category);

        fetch(CFG.ajax, {
          method: 'POST',
          credentials: 'same-origin',
          body: (function() {
            var fd = new FormData();
            fd.append('action', 'jg_get_offerings');
            fd.append('_ajax_nonce', CFG.nonce);
            fd.append('point_id', p.id);
            return fd;
          })()
        })
          .then(function(r) { return r.json(); })
          .then(function(j) {
            var items = (j && j.success && j.data && j.data.items) ? j.data.items : [];
            renderOfferingsEditor(p, items, ofLabel);
          })
          .catch(function() {
            renderOfferingsEditor(p, [], ofLabel);
          });
      }

      function renderOfferingsEditor(p, items, ofLabel) {
        var itemsHtml = '';
        items.forEach(function(item, idx) {
          itemsHtml += buildOfferingItemHtml(idx, item);
        });

        var editorHtml =
          '<h3 style="margin:0 0 14px 0;font-size:1rem;font-weight:700">Zarządzaj: ' + esc(ofLabel) + '</h3>' +
          '<div id="jg-off-ed-items">' + itemsHtml + '</div>' +
          '<button type="button" id="jg-off-ed-add" class="jg-btn jg-btn--ghost" style="width:100%;margin-top:8px">+ Dodaj pozycję</button>' +
          '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">' +
          '<button type="button" class="jg-btn jg-btn--ghost" id="jg-off-ed-cancel">Anuluj</button>' +
          '<button type="button" class="jg-btn jg-btn--primary" id="jg-off-ed-save">Zapisz</button>' +
          '</div>' +
          '<div id="jg-off-ed-msg" style="margin-top:10px;font-size:0.85rem"></div>';

        open(modalEdit, editorHtml);

        var itemsCont = qs('#jg-off-ed-items', modalEdit);

        function addItem() {
          var idx = itemsCont.querySelectorAll('.jg-off-ed-item').length;
          var div = document.createElement('div');
          div.innerHTML = buildOfferingItemHtml(idx, {});
          itemsCont.appendChild(div.firstElementChild);
          bindItemEvents(div.firstElementChild);
        }

        function bindItemEvents(el) {
          var delBtn = el.querySelector('.jg-off-ed-del');
          if (delBtn) delBtn.onclick = function() { el.parentNode.removeChild(el); };
        }

        itemsCont.querySelectorAll('.jg-off-ed-item').forEach(bindItemEvents);

        qs('#jg-off-ed-add', modalEdit).onclick = addItem;

        qs('#jg-off-ed-cancel', modalEdit).onclick = function() { close(modalEdit); };

        qs('#jg-off-ed-save', modalEdit).onclick = function() {
          var rows = itemsCont.querySelectorAll('.jg-off-ed-item');
          var payload = [];
          rows.forEach(function(row) {
            var nameEl  = row.querySelector('.jg-off-ed-name');
            var descEl  = row.querySelector('.jg-off-ed-desc');
            var priceEl = row.querySelector('.jg-off-ed-price');
            var availEl = row.querySelector('.jg-off-ed-avail');
            var name = nameEl ? nameEl.value.trim() : '';
            if (!name) return;
            payload.push({
              name:         name,
              description:  descEl ? descEl.value.trim() : '',
              price:        priceEl && priceEl.value.trim() !== '' ? priceEl.value.trim() : '',
              is_available: availEl && availEl.checked ? 1 : 0,
            });
          });

          var msgEl = qs('#jg-off-ed-msg', modalEdit);
          var saveBtn = qs('#jg-off-ed-save', modalEdit);
          saveBtn.disabled = true;
          msgEl.textContent = 'Zapisywanie\u2026';
          msgEl.style.color = '#6b7280';

          var fd = new FormData();
          fd.append('action', 'jg_save_offerings');
          fd.append('_ajax_nonce', CFG.nonce);
          fd.append('point_id', p.id);
          fd.append('items', JSON.stringify(payload));

          fetch(CFG.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(j) {
              saveBtn.disabled = false;
              if (j && j.success) {
                msgEl.style.color = '#15803d';
                msgEl.textContent = j.data.pending
                  ? 'Przesłano do moderacji.'
                  : 'Zapisano!';
                refreshChallengeProgress();
                // Refresh offerings section in the view modal
                var offSec = qs('#jg-offerings-section', modalView);
                if (offSec) loadOfferingsSection(p, offSec);
                setTimeout(function() { close(modalEdit); }, 1200);
              } else {
                msgEl.style.color = '#dc2626';
                msgEl.textContent = (j && j.data && j.data.message) ? j.data.message : 'Błąd zapisu.';
              }
            })
            .catch(function() {
              saveBtn.disabled = false;
              msgEl.style.color = '#dc2626';
              msgEl.textContent = 'Błąd połączenia.';
            });
        };
      }

      function buildOfferingItemHtml(idx, item) {
        var name  = esc(item.name || '');
        var desc  = esc(item.description || '');
        var price = item.price !== null && item.price !== undefined && item.price !== '' ? esc(String(item.price)) : '';
        var avail = item.is_available === undefined || parseInt(item.is_available) ? 'checked' : '';
        return '<div class="jg-off-ed-item" style="display:flex;flex-wrap:wrap;gap:6px;align-items:flex-start;padding:10px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;margin-bottom:6px">' +
          '<div style="flex:1 1 180px">' +
          '<input type="text" class="jg-off-ed-name" value="' + name + '" placeholder="Nazwa pozycji" maxlength="255" style="width:100%;padding:6px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:0.85rem">' +
          '<input type="text" class="jg-off-ed-desc" value="' + desc + '" placeholder="Opis (opcjonalny)" maxlength="500" style="width:100%;padding:6px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:0.85rem;margin-top:4px">' +
          '</div>' +
          '<div style="flex:0 0 100px">' +
          '<input type="number" class="jg-off-ed-price" value="' + price + '" placeholder="Cena (zł)" min="0" step="0.01" style="width:100%;padding:6px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:0.85rem">' +
          '</div>' +
          '<div style="display:flex;align-items:center;gap:6px;padding-top:6px">' +
          '<label style="display:flex;align-items:center;gap:4px;font-size:0.8rem;white-space:nowrap"><input type="checkbox" class="jg-off-ed-avail" ' + avail + '> Dostępne</label>' +
          '<button type="button" class="jg-off-ed-del jg-btn jg-btn--ghost" style="padding:4px 8px;color:#ef4444" title="Usuń">\u2715</button>' +
          '</div>' +
          '</div>';
      }

      function openMenuEditor(p) {
        var dietary_options = [
          { key: 'wegetarianskie', label: '🌿 wegetariańskie' },
          { key: 'weganskie',      label: '🌱 wegańskie' },
          { key: 'bezglutenowe',   label: '🌾 bezglutenowe' },
          { key: 'ostre',          label: '🌶️ ostre' },
          { key: 'bez_laktozy',    label: '🥛 bez laktozy' }
        ];

        // Load existing menu data then render editor
        var fd = new FormData();
        fd.append('action', 'jg_get_menu');
        fd.append('_ajax_nonce', CFG.nonce);
        fd.append('point_id', p.id);

        open(modalEdit,
          '<header><div style="font-weight:700;font-size:1.05rem">🍽️ Menu – ' + esc(p.title) + '</div>' +
          '<button class="jg-close" id="menu-ed-close">&times;</button></header>' +
          '<div class="jg-grid" style="padding:20px"><div id="menu-ed-loading" style="color:#9ca3af">Ładowanie\u2026</div></div>'
        );

        qs('#menu-ed-close', modalEdit).onclick = function() { close(modalEdit); };

        fetch(CFG.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function(r) { return r.json(); })
          .then(function(j) {
            var sections    = (j && j.success && j.data.sections)    ? j.data.sections    : [];
            var photos      = (j && j.success && j.data.photos)      ? j.data.photos      : [];
            var size_labels = (j && j.success && j.data.size_labels) ? j.data.size_labels : [];
            renderMenuEditor(p, sections, photos, dietary_options, size_labels);
          })
          .catch(function() {
            var loadEl = qs('#menu-ed-loading', modalEdit);
            if (loadEl) loadEl.textContent = 'Błąd ładowania menu.';
          });
      }

      function renderMenuEditor(p, sections, photos, dietary_options, size_labels) {
        size_labels = size_labels || [];
        var container = qs('.jg-grid', modalEdit);
        if (!container) return;

        // Build dietary checkbox HTML helper
        function dietaryCheckboxes(selectedStr, prefix) {
          var selected = selectedStr ? selectedStr.split(',').map(function(s){ return s.trim(); }) : [];
          var html = '<div class="jg-menu-ed-dietary">';
          dietary_options.forEach(function(opt) {
            var checked = selected.indexOf(opt.key) !== -1 ? ' checked' : '';
            html += '<label class="jg-menu-ed-dtag"><input type="checkbox" value="' + esc(opt.key) + '"' + checked + '> ' + opt.label + '</label>';
          });
          html += '</div>';
          return html;
        }

        // Build variants rows HTML helper
        function buildVariantsHtml(variants) {
          var html = '<div class="jg-menu-ed-variants"' + (variants && variants.length ? '' : ' style="display:none"') + '>';
          html += '<div class="jg-menu-ed-variant-presets" style="display:none"></div>'; // populated dynamically on open
          (variants || []).forEach(function(v) {
            var vp = (v.price !== null && v.price !== undefined && v.price !== '') ? parseFloat(v.price).toFixed(2) : '';
            html += '<div class="jg-menu-ed-variant-row">' +
              '<input class="jg-menu-ed-variant-label" type="text" placeholder="np. Mała" value="' + esc(v.label || '') + '">' +
              '<input class="jg-menu-ed-variant-price" type="number" min="0" step="0.01" placeholder="Cena" value="' + esc(vp) + '" style="width:80px">' +
              '<button type="button" class="jg-menu-ed-variant-del" title="Usuń rozmiar">&times;</button>' +
              '</div>';
          });
          html += '<button type="button" class="jg-menu-ed-add-variant">+ Rozmiar własny</button></div>';
          return html;
        }

        // Build section HTML
        function buildSectionHtml(sec, secIdx) {
          var itemsHtml = '';
          (sec.items || []).forEach(function(item, iIdx) {
            var variants = [];
            try { variants = item.variants ? JSON.parse(item.variants) : []; } catch(e) {}
            var hasVariants = variants && variants.length > 0;
            var priceVal = (item.price !== null && item.price !== undefined && item.price !== '') ? parseFloat(item.price).toFixed(2) : '';
            itemsHtml += '<div class="jg-menu-ed-item" data-sec="' + secIdx + '" data-item="' + iIdx + '">' +
              '<div class="jg-menu-ed-item-top">' +
              '<input class="jg-menu-ed-item-name" type="text" placeholder="Nazwa pozycji*" value="' + esc(item.name || '') + '">' +
              '<input class="jg-menu-ed-item-price" type="number" min="0" step="0.01" placeholder="Cena (zł)" value="' + esc(priceVal) + '" style="width:90px' + (hasVariants ? ';display:none' : '') + '">' +
              '<button type="button" class="jg-menu-ed-variants-toggle" title="Warianty/rozmiary dania" style="' + (hasVariants ? 'background:#e0f2fe;border-color:#0284c7' : '') + '">Rozmiary</button>' +
              '<button type="button" class="jg-menu-ed-item-del" title="Usuń pozycję">\uD83D\uDDD1</button>' +
              '</div>' +
              '<textarea class="jg-menu-ed-item-desc" rows="2" placeholder="Opis (opcjonalnie)">' + esc(item.description || '') + '</textarea>' +
              buildVariantsHtml(variants) +
              dietaryCheckboxes(item.dietary_tags || '', 's' + secIdx + 'i' + iIdx) +
              '</div>';
          });

          return '<div class="jg-menu-ed-section" data-sec="' + secIdx + '">' +
            '<div class="jg-menu-ed-sec-header">' +
            '<span class="jg-menu-ed-drag">&#8942;</span>' +
            '<input class="jg-menu-ed-sec-name" type="text" placeholder="Nazwa sekcji*" value="' + esc(sec.name || '') + '">' +
            '<button type="button" class="jg-menu-ed-sec-del" title="Usuń sekcję">Usuń</button>' +
            '</div>' +
            '<div class="jg-menu-ed-items">' + itemsHtml + '</div>' +
            '<button type="button" class="jg-menu-ed-add-item">+ Dodaj pozycję</button>' +
            '</div>';
        }

        // Build predefined size labels UI
        var sizesHtml = '<div class="jg-menu-ed-sizes-area" id="jg-menu-ed-sizes-area">' +
          '<div class="jg-menu-ed-photos-title">Rozmiary dań dla tego miejsca' +
          '<span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:0.85em;margin-left:6px;color:#9ca3af">(np. Mała, Duża)</span></div>' +
          '<div class="jg-menu-ed-size-tags" id="jg-menu-ed-size-tags">';
        size_labels.forEach(function(lbl) {
          sizesHtml += '<span class="jg-menu-ed-size-tag" data-label="' + esc(lbl) + '">' + esc(lbl) +
            '<button type="button" class="jg-menu-ed-size-tag-del" title="Usuń">&times;</button></span>';
        });
        sizesHtml += '</div>' +
          '<div class="jg-menu-ed-size-add">' +
          '<input type="text" id="jg-menu-ed-size-input" class="jg-menu-ed-size-input" placeholder="np. Mała" maxlength="50">' +
          '<button type="button" id="jg-menu-ed-size-btn" class="jg-btn jg-btn--ghost" style="padding:5px 10px">Dodaj</button>' +
          '</div></div>';

        // Build photos area
        var photosHtml = '<div class="jg-menu-ed-photos-area">' +
          '<div class="jg-menu-ed-photos-title">Karta menu (zdjęcia, max 4)</div>' +
          '<div class="jg-menu-ed-photos" id="jg-menu-ed-photos">';
        photos.forEach(function(ph) {
          photosHtml += '<div class="jg-menu-ed-photo" data-id="' + esc(ph.id) + '">' +
            '<img src="' + esc(ph.thumb_url || ph.url) + '" alt="">' +
            '<button type="button" class="jg-menu-ed-photo-del" title="Usuń zdjęcie">&times;</button>' +
            '</div>';
        });
        if (photos.length < 4) {
          photosHtml += '<label class="jg-menu-ed-photo-add" title="Dodaj zdjęcie karty menu">' +
            '<input type="file" id="jg-menu-photo-input" accept="image/*" style="display:none">' +
            '<span>+</span>' +
            '</label>';
        }
        photosHtml += '</div>' +
          '<div id="jg-menu-photo-msg" style="font-size:0.8rem;margin-top:4px"></div>' +
          '</div>';

        // Build sections
        var sectionsHtml = '<div id="jg-menu-ed-sections">';
        sections.forEach(function(sec, i) { sectionsHtml += buildSectionHtml(sec, i); });
        sectionsHtml += '</div>';

        container.innerHTML =
          sizesHtml +
          photosHtml +
          sectionsHtml +
          '<button type="button" id="jg-menu-ed-add-sec" class="jg-btn jg-btn--ghost" style="width:100%;margin-top:8px">+ Dodaj sekcję</button>' +
          '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">' +
          '<button type="button" class="jg-btn jg-btn--ghost" id="menu-ed-cancel">Anuluj</button>' +
          '<button type="button" class="jg-btn" id="menu-ed-save">Zapisz menu</button>' +
          '</div>' +
          '<div id="menu-ed-msg" style="font-size:0.85rem;margin-top:8px;text-align:right"></div>';

        qs('#menu-ed-cancel', modalEdit).onclick = function() { close(modalEdit); };

        // Size labels management
        (function() {
          var tagsEl = qs('#jg-menu-ed-size-tags', modalEdit);
          var sizeInput = qs('#jg-menu-ed-size-input', modalEdit);
          var sizeBtn = qs('#jg-menu-ed-size-btn', modalEdit);
          if (!tagsEl || !sizeInput || !sizeBtn) return;

          function addSizeTag(label) {
            label = label.trim();
            if (!label) return;
            // Prevent duplicates
            var existing = tagsEl.querySelectorAll('.jg-menu-ed-size-tag');
            for (var i = 0; i < existing.length; i++) {
              if (existing[i].getAttribute('data-label') === label) return;
            }
            var span = document.createElement('span');
            span.className = 'jg-menu-ed-size-tag';
            span.setAttribute('data-label', label);
            span.innerHTML = esc(label) + '<button type="button" class="jg-menu-ed-size-tag-del" title="Usuń">&times;</button>';
            span.querySelector('.jg-menu-ed-size-tag-del').onclick = function() { tagsEl.removeChild(span); };
            tagsEl.appendChild(span);
          }

          // Bind existing tag delete buttons
          tagsEl.querySelectorAll('.jg-menu-ed-size-tag-del').forEach(function(btn) {
            var span = btn.parentNode;
            btn.onclick = function() { tagsEl.removeChild(span); };
          });

          sizeBtn.onclick = function() {
            addSizeTag(sizeInput.value);
            sizeInput.value = '';
            sizeInput.focus();
          };
          sizeInput.onkeydown = function(e) {
            if (e.key === 'Enter') { e.preventDefault(); addSizeTag(sizeInput.value); sizeInput.value = ''; }
          };
        })();

        // Add section
        qs('#jg-menu-ed-add-sec', modalEdit).onclick = function() {
          var secList = qs('#jg-menu-ed-sections', modalEdit);
          var secIdx = secList.querySelectorAll('.jg-menu-ed-section').length;
          var div = document.createElement('div');
          div.innerHTML = buildSectionHtml({ name: '', items: [] }, secIdx);
          secList.appendChild(div.firstElementChild);
          bindSectionEvents(secList.lastElementChild);
          qs('.jg-menu-ed-sec-name', secList.lastElementChild).focus();
        };

        // Bind events to all existing sections
        modalEdit.querySelectorAll('.jg-menu-ed-section').forEach(function(secEl) {
          bindSectionEvents(secEl);
        });

        // Photo upload
        var photoInput = qs('#jg-menu-photo-input', modalEdit);
        if (photoInput) {
          photoInput.onchange = function() {
            var file = photoInput.files[0];
            if (!file) return;
            var photoMsg = qs('#jg-menu-photo-msg', modalEdit);
            photoMsg.textContent = 'Przesyłanie\u2026';
            photoMsg.style.color = '#9ca3af';
            photoInput.disabled = true;
            var uploadFd = new FormData();
            uploadFd.append('action', 'jg_upload_menu_photo');
            uploadFd.append('_ajax_nonce', CFG.nonce);
            uploadFd.append('point_id', p.id);
            uploadFd.append('menu_photo', file);
            fetch(CFG.ajax, { method: 'POST', body: uploadFd, credentials: 'same-origin' })
              .then(function(r) { return r.json(); })
              .then(function(resp) {
                photoInput.disabled = false;
                if (resp && resp.success) {
                  photoMsg.textContent = 'Zdjęcie dodane.';
                  photoMsg.style.color = '#15803d';
                  refreshChallengeProgress();
                  var photosContainer = qs('#jg-menu-ed-photos', modalEdit);
                  var addLabel = qs('.jg-menu-ed-photo-add', photosContainer);
                  var newDiv = document.createElement('div');
                  newDiv.className = 'jg-menu-ed-photo';
                  newDiv.dataset.id = resp.data.id;
                  newDiv.innerHTML = '<img src="' + esc(resp.data.thumb_url || resp.data.url) + '" alt="">' +
                    '<button type="button" class="jg-menu-ed-photo-del" title="Usuń zdjęcie">&times;</button>';
                  photosContainer.insertBefore(newDiv, addLabel || null);
                  bindPhotoDelBtn(newDiv.querySelector('.jg-menu-ed-photo-del'), newDiv, p.id, modalEdit);
                  // Hide add button if 4 photos
                  if (photosContainer.querySelectorAll('.jg-menu-ed-photo').length >= 4 && addLabel) {
                    addLabel.style.display = 'none';
                  }
                  // Update sidebar has_menu badge (photo alone counts as having a menu)
                  if (typeof window.jgUpdatePointHasMenu === 'function') window.jgUpdatePointHasMenu(p.id);
                } else {
                  photoMsg.textContent = (resp && resp.data && resp.data.message) ? resp.data.message : 'Błąd uploadu.';
                  photoMsg.style.color = '#b91c1c';
                }
              })
              .catch(function() {
                photoInput.disabled = false;
                var photoMsg2 = qs('#jg-menu-photo-msg', modalEdit);
                if (photoMsg2) { photoMsg2.textContent = 'Błąd sieci.'; photoMsg2.style.color = '#b91c1c'; }
              });
            photoInput.value = '';
          };
        }

        // Bind photo delete buttons
        modalEdit.querySelectorAll('.jg-menu-ed-photo').forEach(function(photoEl) {
          var btn = photoEl.querySelector('.jg-menu-ed-photo-del');
          if (btn) bindPhotoDelBtn(btn, photoEl, p.id, modalEdit);
        });

        // Save menu
        qs('#menu-ed-save', modalEdit).onclick = function() {
          var msgEl = qs('#menu-ed-msg', modalEdit);
          msgEl.textContent = 'Zapisywanie\u2026';
          msgEl.style.color = '#9ca3af';

          var collectedSections = collectMenuData();
          var collectedSizes = collectSizeLabels();
          var saveFd = new FormData();
          saveFd.append('action', 'jg_save_menu');
          saveFd.append('_ajax_nonce', CFG.nonce);
          saveFd.append('point_id', p.id);
          saveFd.append('sections', JSON.stringify(collectedSections));
          saveFd.append('size_labels', JSON.stringify(collectedSizes));

          fetch(CFG.ajax, { method: 'POST', body: saveFd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(resp) {
              if (resp && resp.success) {
                msgEl.textContent = 'Menu zapisano.';
                msgEl.style.color = '#15803d';
                refreshChallengeProgress();
                // Live-refresh the menu section in the place modal (still open behind)
                var menuSec = qs('#jg-menu-section', modalView);
                if (menuSec) loadMenuSection(p, menuSec);
                // Update sidebar has_menu badge based on current editor state
                var _hasSections = collectedSections && collectedSections.length > 0;
                var _hasPhotos = modalEdit.querySelectorAll('.jg-menu-ed-photo').length > 0;
                if (_hasSections || _hasPhotos) {
                  if (typeof window.jgUpdatePointHasMenu === 'function') window.jgUpdatePointHasMenu(p.id);
                } else {
                  if (typeof window.jgUpdatePointNoMenu === 'function') window.jgUpdatePointNoMenu(p.id);
                }
                setTimeout(function() { close(modalEdit); }, 900);
              } else {
                msgEl.textContent = (resp && resp.data && resp.data.message) ? resp.data.message : 'Błąd zapisu.';
                msgEl.style.color = '#b91c1c';
              }
            })
            .catch(function() {
              var msgEl2 = qs('#menu-ed-msg', modalEdit);
              if (msgEl2) { msgEl2.textContent = 'Błąd sieci.'; msgEl2.style.color = '#b91c1c'; }
            });
        };
      }

      function bindSectionEvents(secEl) {
        // Delete section
        var delBtn = secEl.querySelector('.jg-menu-ed-sec-del');
        if (delBtn) {
          delBtn.onclick = function() {
            if (confirm('Usunąć tę sekcję wraz ze wszystkimi pozycjami?')) {
              secEl.parentNode.removeChild(secEl);
            }
          };
        }
        // Add item
        var addItemBtn = secEl.querySelector('.jg-menu-ed-add-item');
        if (addItemBtn) {
          addItemBtn.onclick = function() {
            var itemsContainer = secEl.querySelector('.jg-menu-ed-items');
            var secIdx = secEl.dataset.sec || 0;
            var iIdx = itemsContainer.querySelectorAll('.jg-menu-ed-item').length;
            var dietary_options = [
              { key: 'wegetarianskie', label: '🌿 wegetariańskie' },
              { key: 'weganskie',      label: '🌱 wegańskie' },
              { key: 'bezglutenowe',   label: '🌾 bezglutenowe' },
              { key: 'ostre',          label: '🌶️ ostre' },
              { key: 'bez_laktozy',    label: '🥛 bez laktozy' }
            ];
            var dietHtml = '<div class="jg-menu-ed-dietary">';
            dietary_options.forEach(function(opt) {
              dietHtml += '<label class="jg-menu-ed-dtag"><input type="checkbox" value="' + esc(opt.key) + '"> ' + opt.label + '</label>';
            });
            dietHtml += '</div>';

            var div = document.createElement('div');
            div.innerHTML = '<div class="jg-menu-ed-item" data-sec="' + secIdx + '" data-item="' + iIdx + '">' +
              '<div class="jg-menu-ed-item-top">' +
              '<input class="jg-menu-ed-item-name" type="text" placeholder="Nazwa pozycji*" value="">' +
              '<input class="jg-menu-ed-item-price" type="number" min="0" step="0.01" placeholder="Cena (zł)" style="width:90px">' +
              '<button type="button" class="jg-menu-ed-variants-toggle" title="Warianty/rozmiary dania">Rozmiary</button>' +
              '<button type="button" class="jg-menu-ed-item-del" title="Usuń">\uD83D\uDDD1</button>' +
              '</div>' +
              '<textarea class="jg-menu-ed-item-desc" rows="2" placeholder="Opis (opcjonalnie)"></textarea>' +
              '<div class="jg-menu-ed-variants" style="display:none"><div class="jg-menu-ed-variant-presets" style="display:none"></div><button type="button" class="jg-menu-ed-add-variant">+ Rozmiar własny</button></div>' +
              dietHtml +
              '</div>';
            var newItem = div.firstElementChild;
            itemsContainer.appendChild(newItem);
            bindItemDelBtn(newItem);
            bindVariantEvents(newItem);
            newItem.querySelector('.jg-menu-ed-item-name').focus();
          };
        }
        // Bind existing item delete + variant buttons
        secEl.querySelectorAll('.jg-menu-ed-item').forEach(function(itemEl) {
          bindItemDelBtn(itemEl);
          bindVariantEvents(itemEl);
        });
      }

      function bindItemDelBtn(itemEl) {
        var btn = itemEl.querySelector('.jg-menu-ed-item-del');
        if (btn) {
          btn.onclick = function() {
            itemEl.parentNode.removeChild(itemEl);
          };
        }
      }

      function bindVariantEvents(itemEl) {
        var toggleBtn = itemEl.querySelector('.jg-menu-ed-variants-toggle');
        var variantsDiv = itemEl.querySelector('.jg-menu-ed-variants');
        var priceInput = itemEl.querySelector('.jg-menu-ed-item-price');
        if (!toggleBtn || !variantsDiv) return;

        function addVariantRow(label, price) {
          var row = document.createElement('div');
          row.className = 'jg-menu-ed-variant-row';
          row.innerHTML = '<input class="jg-menu-ed-variant-label" type="text" placeholder="np. Mała" value="' + esc(label || '') + '">' +
            '<input class="jg-menu-ed-variant-price" type="number" min="0" step="0.01" placeholder="Cena" value="' + esc(price || '') + '" style="width:80px">' +
            '<button type="button" class="jg-menu-ed-variant-del" title="Usuń">&times;</button>';
          var addBtn = variantsDiv.querySelector('.jg-menu-ed-add-variant');
          variantsDiv.insertBefore(row, addBtn);
          row.querySelector('.jg-menu-ed-variant-del').onclick = function() {
            variantsDiv.removeChild(row);
            refreshPresetPills(); // restore pill when row deleted
          };
          return row;
        }

        // Refresh preset pills based on current predefined sizes and existing rows
        function refreshPresetPills() {
          var presetsEl = variantsDiv.querySelector('.jg-menu-ed-variant-presets');
          if (!presetsEl) return;

          var tagsEl = document.getElementById('jg-menu-ed-size-tags');
          var predefined = [];
          if (tagsEl) {
            tagsEl.querySelectorAll('.jg-menu-ed-size-tag').forEach(function(span) {
              var lbl = span.getAttribute('data-label') || '';
              if (lbl) predefined.push(lbl);
            });
          }

          if (!predefined.length) {
            presetsEl.style.display = 'none';
            presetsEl.innerHTML = '';
            return;
          }

          // Labels already in use by existing rows
          var usedLabels = {};
          variantsDiv.querySelectorAll('.jg-menu-ed-variant-row').forEach(function(row) {
            var inp = row.querySelector('.jg-menu-ed-variant-label');
            if (inp && inp.value.trim()) usedLabels[inp.value.trim()] = true;
          });

          presetsEl.innerHTML = '';
          predefined.forEach(function(lbl) {
            if (usedLabels[lbl]) return; // already added, skip pill
            var pill = document.createElement('button');
            pill.type = 'button';
            pill.className = 'jg-menu-ed-preset-pill';
            pill.textContent = '+ ' + lbl;
            pill.onclick = function() {
              var row = addVariantRow(lbl, '');
              row.querySelector('.jg-menu-ed-variant-price').focus();
              refreshPresetPills();
            };
            presetsEl.appendChild(pill);
          });

          presetsEl.style.display = presetsEl.children.length ? '' : 'none';
        }

        // Bind existing del buttons in already-rendered rows
        variantsDiv.querySelectorAll('.jg-menu-ed-variant-row').forEach(function(row) {
          var del = row.querySelector('.jg-menu-ed-variant-del');
          if (del) del.onclick = function() {
            variantsDiv.removeChild(row);
            refreshPresetPills();
          };
        });

        // "+ Rozmiar własny" always adds empty free-text row
        var addBtn = variantsDiv.querySelector('.jg-menu-ed-add-variant');
        if (addBtn) addBtn.onclick = function() {
          addVariantRow('', '').querySelector('.jg-menu-ed-variant-label').focus();
        };

        // Toggle show/hide
        function syncToggleState() {
          var visible = variantsDiv.style.display !== 'none';
          if (priceInput) priceInput.style.display = visible ? 'none' : '';
          toggleBtn.style.background = visible ? '#e0f2fe' : '';
          toggleBtn.style.borderColor = visible ? '#0284c7' : '';
        }

        toggleBtn.onclick = function() {
          var willShow = variantsDiv.style.display === 'none';
          variantsDiv.style.display = willShow ? '' : 'none';
          if (willShow) {
            refreshPresetPills();
            // If no presets and no existing rows, open with one empty row
            var presetsEl = variantsDiv.querySelector('.jg-menu-ed-variant-presets');
            var hasPills = presetsEl && presetsEl.style.display !== 'none' && presetsEl.children.length > 0;
            if (!hasPills && !variantsDiv.querySelector('.jg-menu-ed-variant-row')) {
              addVariantRow('', '').querySelector('.jg-menu-ed-variant-label').focus();
            }
          }
          syncToggleState();
        };

        syncToggleState();
      }

      function bindPhotoDelBtn(btn, photoEl, pointId, menuEditorEl) {
        btn.onclick = function() {
          var photoId = photoEl.dataset.id;
          if (!photoId || !confirm('Usunąć to zdjęcie?')) return;
          var fd = new FormData();
          fd.append('action', 'jg_delete_menu_photo');
          fd.append('_ajax_nonce', CFG.nonce);
          fd.append('point_id', pointId);
          fd.append('photo_id', photoId);
          fetch(CFG.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(resp) {
              if (resp && resp.success) {
                var photosContainer = photoEl.parentNode;
                photosContainer.removeChild(photoEl);
                // Show add button if now < 4
                var addLabel = qs('.jg-menu-ed-photo-add', photosContainer);
                if (addLabel) addLabel.style.display = '';
                // Update sidebar badge: check if any photos or sections remain
                var hasPhotosLeft = photosContainer.querySelectorAll('.jg-menu-ed-photo').length > 0;
                var hasSectionsLeft = menuEditorEl ? menuEditorEl.querySelectorAll('.jg-menu-ed-section').length > 0 : false;
                if (!hasPhotosLeft && !hasSectionsLeft) {
                  if (typeof window.jgUpdatePointNoMenu === 'function') window.jgUpdatePointNoMenu(pointId);
                }
              }
            });
        };
      }

      function collectMenuData() {
        var sections = [];
        modalEdit.querySelectorAll('.jg-menu-ed-section').forEach(function(secEl) {
          var secName = (secEl.querySelector('.jg-menu-ed-sec-name') || {}).value;
          if (!secName || !secName.trim()) return;
          var items = [];
          secEl.querySelectorAll('.jg-menu-ed-item').forEach(function(itemEl) {
            var name  = (itemEl.querySelector('.jg-menu-ed-item-name')  || {}).value || '';
            var price = (itemEl.querySelector('.jg-menu-ed-item-price') || {}).value || '';
            var desc  = (itemEl.querySelector('.jg-menu-ed-item-desc')  || {}).value || '';
            if (!name.trim()) return;
            var dtags = [];
            itemEl.querySelectorAll('.jg-menu-ed-dietary input:checked').forEach(function(cb) { dtags.push(cb.value); });
            // Collect variants
            var variants = [];
            var varDiv = itemEl.querySelector('.jg-menu-ed-variants');
            if (varDiv && varDiv.style.display !== 'none') {
              varDiv.querySelectorAll('.jg-menu-ed-variant-row').forEach(function(row) {
                var lbl = (row.querySelector('.jg-menu-ed-variant-label') || {}).value || '';
                var vp  = (row.querySelector('.jg-menu-ed-variant-price') || {}).value || '';
                if (lbl.trim()) {
                  variants.push({ label: lbl.trim(), price: vp !== '' ? parseFloat(vp) : null });
                }
              });
            }
            var effectivePrice = (variants.length === 0 && price !== '') ? parseFloat(price) : null;
            items.push({ name: name.trim(), price: effectivePrice, variants: variants, description: desc.trim(), dietary_tags: dtags.join(','), is_available: 1 });
          });
          sections.push({ name: secName.trim(), items: items });
        });
        return sections;
      }

      function collectSizeLabels() {
        var labels = [];
        var tagsEl = qs('#jg-menu-ed-size-tags', modalEdit);
        if (tagsEl) {
          tagsEl.querySelectorAll('.jg-menu-ed-size-tag').forEach(function(span) {
            var lbl = span.getAttribute('data-label') || '';
            if (lbl) labels.push(lbl);
          });
        }
        return labels;
      }

      /**
       * Open stats modal with real-time updates
       */
      // ===== SECTION: POINT MANAGEMENT MODALS =====
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
            p.email = updatedPoint.email;
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
            refreshChallengeProgress();
            f.reset();

            // Update marker appearance immediately if admin
            if (CFG.isAdmin && cluster && cluster.getLayers) {
              try {
                var allMarkers = cluster.getLayers();
                if (sponsoredCluster) allMarkers = allMarkers.concat(sponsoredCluster.getLayers());
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

      // ── Place contact modal ───────────────────────────────────────────────
      // Opens a form letting the user send a message to the place's email.
      // The email address is never exposed to the client — all sending is
      // handled server-side by the jg_contact_place AJAX action.
      function openPlaceContactModal(p) {
        if (!modalPlaceContact) return;

        var prefillName = CFG.currentUserDisplayName || '';

        open(modalPlaceContact,
          '<header>' +
            '<h3>✉️ Napisz do: ' + esc(p.title) + '</h3>' +
            '<button class="jg-close" id="place-contact-close">&times;</button>' +
          '</header>' +
          '<form id="place-contact-form" class="jg-place-contact-form" autocomplete="on">' +
            '<label>Twoje imię lub nazwa' +
              '<input type="text" name="sender_name" required maxlength="120" value="' + esc(prefillName) + '" placeholder="Wpisz swoje imię">' +
            '</label>' +
            '<label>Twój adres e-mail' +
              '<input type="email" name="sender_email" required maxlength="200" value="" placeholder="twoj@email.pl">' +
            '</label>' +
            '<label>Wiadomość' +
              '<textarea name="message" required maxlength="2000" placeholder="Treść wiadomości..."></textarea>' +
            '</label>' +
            '<p class="jg-place-contact-status" id="place-contact-status"></p>' +
            '<div style="display:flex;gap:10px;justify-content:flex-end">' +
              '<button type="button" class="jg-btn jg-btn--ghost" id="place-contact-cancel">Anuluj</button>' +
              '<button type="submit" class="jg-btn" id="place-contact-submit">Wyślij wiadomość</button>' +
            '</div>' +
          '</form>'
        );

        qs('#place-contact-close', modalPlaceContact).onclick = function() {
          close(modalPlaceContact);
        };
        qs('#place-contact-cancel', modalPlaceContact).onclick = function() {
          close(modalPlaceContact);
        };

        var form   = qs('#place-contact-form', modalPlaceContact);
        var status = qs('#place-contact-status', modalPlaceContact);
        var submitBtn = qs('#place-contact-submit', modalPlaceContact);

        form.onsubmit = function(e) {
          e.preventDefault();

          var senderName  = (form.sender_name.value  || '').trim();
          var senderEmail = (form.sender_email.value || '').trim();
          var message     = (form.message.value      || '').trim();

          if (!senderName || !senderEmail || !message) {
            status.textContent = 'Uzupełnij wszystkie pola.';
            status.className = 'jg-place-contact-status jg-place-contact-status--error';
            return;
          }

          submitBtn.disabled = true;
          status.textContent = 'Wysyłanie…';
          status.className = 'jg-place-contact-status';

          var fd = new FormData();
          fd.append('action',       'jg_contact_place');
          fd.append('nonce',        CFG.nonce);
          fd.append('point_id',     p.id);
          fd.append('sender_name',  senderName);
          fd.append('sender_email', senderEmail);
          fd.append('message',      message);

          fetch(CFG.ajax, { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(res) {
              if (res.success) {
                status.textContent = 'Wiadomość wysłana. Dziękujemy!';
                status.className = 'jg-place-contact-status jg-place-contact-status--ok';
                form.reset();
                setTimeout(function() { close(modalPlaceContact); }, 1800);
              } else {
                status.textContent = (res.data && res.data.message) || 'Wystąpił błąd. Spróbuj ponownie.';
                status.className = 'jg-place-contact-status jg-place-contact-status--error';
                submitBtn.disabled = false;
              }
            })
            .catch(function() {
              status.textContent = 'Błąd połączenia. Spróbuj ponownie.';
              status.className = 'jg-place-contact-status jg-place-contact-status--error';
              submitBtn.disabled = false;
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

      // --- Input mask helpers for sponsored pin fields ---

      function normalizeSocialValue(v, prefixes) {
        v = v.trim();
        if (!v) return v;
        v = v.replace(/^https?:\/\//i, '').replace(/^www\./i, '');
        for (var i = 0; i < prefixes.length; i++) {
          if (v.toLowerCase().indexOf(prefixes[i].toLowerCase()) === 0) {
            v = v.slice(prefixes[i].length);
            break;
          }
        }
        v = v.replace(/^@+/, '');
        return v;
      }

      function isValidPhone(v) {
        if (!v) return true;
        var digits = v.replace(/[^0-9]/g, '');
        return digits.length >= 9 && digits.length <= 15 && /^[\+]?[0-9\s\-\(\)]+$/.test(v);
      }

      function isValidWebsite(v) {
        if (!v) return true;
        v = v.replace(/^https?:\/\//i, '').trim();
        return /^[a-zA-Z0-9][a-zA-Z0-9\-\.]*\.[a-zA-Z]{2,}(\/[^\s]*)?$/.test(v);
      }

      function isValidSocialUsername(v) {
        if (!v) return true;
        return v.length > 0 && v.length <= 150 && !/\s/.test(v);
      }

      function applyPhoneMask(input) {
        if (!input) return;
        function setPhoneValidity(inp) {
          var v = inp.value.trim();
          if (!v) { inp.style.borderColor = '#ddd'; inp.title = ''; return; }
          var digits = v.replace(/[^0-9]/g, '');
          var ok = isValidPhone(v);
          inp.style.borderColor = ok ? '#22c55e' : '#ef4444';
          inp.title = ok ? '' : (digits.length < 9 ? 'Numer musi zawierać co najmniej 9 cyfr (wpisano: ' + digits.length + ')' : 'Niedozwolone znaki w numerze');
        }
        input.addEventListener('input', function() {
          var pos = this.selectionStart;
          var clean = this.value.replace(/[^0-9\s\-\+\(\)]/g, '').slice(0, 20);
          if (this.value !== clean) {
            this.value = clean;
            try { this.setSelectionRange(Math.min(pos, clean.length), Math.min(pos, clean.length)); } catch (e) {}
          }
          setPhoneValidity(this);
        });
        input.addEventListener('blur', function() { setPhoneValidity(this); });
        input.addEventListener('focus', function() { this.style.borderColor = '#ddd'; this.title = ''; });
      }

      function applyWebsiteMask(input) {
        if (!input) return;
        function setWebsiteValidity(inp) {
          var v = inp.value.trim();
          if (!v) { inp.style.borderColor = '#ddd'; inp.title = ''; return; }
          var ok = isValidWebsite(v);
          inp.style.borderColor = ok ? '#22c55e' : '#ef4444';
          inp.title = ok ? '' : 'Podaj poprawny adres strony, np. jeleniagora.pl';
        }
        input.addEventListener('input', function() {
          var clean = this.value.replace(/\s+/g, '');
          if (this.value !== clean) this.value = clean;
          setWebsiteValidity(this);
        });
        input.addEventListener('blur', function() {
          this.value = this.value.replace(/^https?:\/\//i, '').trim();
          setWebsiteValidity(this);
        });
        input.addEventListener('focus', function() { this.style.borderColor = '#ddd'; this.title = ''; });
      }

      function applySocialMask(input, prefixes) {
        if (!input) return;
        function setValidity(inp) {
          var v = inp.value.trim();
          if (!v) { inp.style.borderColor = '#ddd'; inp.title = ''; return; }
          var ok = isValidSocialUsername(v);
          inp.style.borderColor = ok ? '#22c55e' : '#ef4444';
          inp.title = ok ? '' : 'Nazwa profilu nie może zawierać spacji';
        }
        input.addEventListener('input', function() {
          var clean = this.value.replace(/\s+/g, '');
          if (this.value !== clean) this.value = clean;
          setValidity(this);
        });
        input.addEventListener('blur', function() {
          this.value = normalizeSocialValue(this.value, prefixes);
          setValidity(this);
        });
        input.addEventListener('focus', function() { this.style.borderColor = '#ddd'; this.title = ''; });
      }

      // --- End input mask helpers ---

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

            // Contact fields for all points (phone, email, website)
            var contactFieldsHtml = '<div class="cols-2" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:12px;margin:12px 0">' +
              '<strong style="display:block;margin-bottom:12px;color:#0369a1">📋 Dane kontaktowe</strong>' +
              '<label style="display:block;margin-bottom:8px">Telefon <input type="text" name="phone" id="edit-phone-input" value="' + esc(p.phone || '') + '" placeholder="np. 123 456 789" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></label>' +
              '<label style="display:block;margin-bottom:8px">Email kontaktowy <input type="email" name="contact_email" id="edit-email-input" value="' + esc(p.email || '') + '" placeholder="np. kontakt@firma.pl" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></label>' +
              '<label style="display:block;margin-bottom:0">Strona internetowa <input type="text" name="website" id="edit-website-input" value="' + esc(p.website || '') + '" placeholder="np. jeleniagora.pl" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></label>' +
              '</div>';

            // Sponsored contact fields (only for sponsored points) - social media + CTA
            var sponsoredContactHtml = '';
            if (isSponsored) {
              sponsoredContactHtml = '<div class="cols-2" style="background:#fef3c7;border:2px solid #f59e0b;border-radius:8px;padding:12px;margin:12px 0">' +
                '<strong style="display:block;margin:0 0 8px;color:#92400e">Media społecznościowe</strong>' +
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
              '<input type="hidden" id="edit-point-id" value="' + p.id + '">' +
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
              (p.type === 'miejsce' ? '<div class="cols-2"><label style="display:block;margin-bottom:6px;font-weight:500">Godziny otwarcia</label>' + buildOpeningHoursPickerHtml('edit', p.opening_hours || '') + '</div>' : '') +
              (p.type === 'miejsce' && isPriceRangeCategory(p.category || '') ? '<div class="cols-2" id="edit-price-range-field"><label style="display:block;margin-bottom:6px;font-weight:500">💰 Zakres cenowy</label>' + buildPriceRangeSelectHtml('edit', p.price_range || '') + '</div>' : '') +
              (p.type === 'miejsce' && isServesCuisineCategory(p.category || '') ? '<div class="cols-2" id="edit-serves-cuisine-field"><label style="display:block;margin-bottom:4px;font-weight:500">🥗 Rodzaj kuchni <input type="text" name="serves_cuisine" id="edit-serves-cuisine-input" value="' + esc(p.serves_cuisine || '') + '" placeholder="np. polska, włoska, pizza…" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></label></div>' : '') +
              '<div class="cols-2"><label style="display:block;margin-bottom:4px">Tagi (max 5)</label>' + buildTagInputHtml('edit-tags') + '</div>' +
              contactFieldsHtml +
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
          try { sessionStorage.removeItem('jg_edit_modal_state'); } catch(e) {}
          close(modalEdit);
        };

        qs('#edt-cancel', modalEdit).onclick = function() {
          try { sessionStorage.removeItem('jg_edit_modal_state'); } catch(e) {}
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

        // Initialize opening hours picker (only rendered for miejsce)
        var editOhPicker = initOpeningHoursPicker('edit', modalEdit);

        // Restore saved draft state if the user re-opens after an accidental backdrop/Escape close
        try {
          var savedStateStr = sessionStorage.getItem('jg_edit_modal_state');
          if (savedStateStr) {
            var savedState = JSON.parse(savedStateStr);
            if (savedState && +savedState.pointId === +p.id) {
              var titleInput = qs('input[name="title"]', form);
              if (titleInput && savedState.title !== undefined) titleInput.value = savedState.title;

              var typeSelectEl = qs('#edit-type-select', form);
              if (typeSelectEl && savedState.type) {
                typeSelectEl.value = savedState.type;
                typeSelectEl.dispatchEvent(new Event('change'));
              }

              var catSelect = qs('#edit-category-select', form);
              if (catSelect && savedState.category !== undefined) catSelect.value = savedState.category;

              var placeCatSelect = qs('#edit-place-category-select', form);
              if (placeCatSelect && savedState.place_category !== undefined) placeCatSelect.value = savedState.place_category;

              var curiosityCatSelect = qs('#edit-curiosity-category-select', form);
              if (curiosityCatSelect && savedState.curiosity_category !== undefined) curiosityCatSelect.value = savedState.curiosity_category;

              var addressInputEl = qs('#edit-address-input', form);
              if (addressInputEl && savedState.address !== undefined) addressInputEl.value = savedState.address;

              var latInputEl = qs('#edit-lat-input', form);
              if (latInputEl && savedState.lat) latInputEl.value = savedState.lat;

              var lngInputEl = qs('#edit-lng-input', form);
              if (lngInputEl && savedState.lng) lngInputEl.value = savedState.lng;

              if (editRte && savedState.description !== undefined) {
                editRte.setContent(savedState.description);
              }

              if (editTagInput && savedState.tags) {
                editTagInput.setTags(savedState.tags.split(',').filter(Boolean));
              }

              // Contact fields (all pins)
              var websiteInput = qs('#edit-website-input', form);
              if (websiteInput && savedState.website !== undefined) websiteInput.value = savedState.website;

              var phoneInput = qs('#edit-phone-input', form);
              if (phoneInput && savedState.phone !== undefined) phoneInput.value = savedState.phone;

              var emailInput = qs('#edit-email-input', form);
              if (emailInput && savedState.contact_email !== undefined) emailInput.value = savedState.contact_email;

              var facebookInput = qs('#edit-facebook-input', form);
              if (facebookInput && savedState.facebook_url !== undefined) facebookInput.value = savedState.facebook_url;

              var instagramInput = qs('#edit-instagram-input', form);
              if (instagramInput && savedState.instagram_url !== undefined) instagramInput.value = savedState.instagram_url;

              var linkedinInput = qs('#edit-linkedin-input', form);
              if (linkedinInput && savedState.linkedin_url !== undefined) linkedinInput.value = savedState.linkedin_url;

              var tiktokInput = qs('#edit-tiktok-input', form);
              if (tiktokInput && savedState.tiktok_url !== undefined) tiktokInput.value = savedState.tiktok_url;

              var ctaCheckbox = qs('#edit-cta-enabled-checkbox', form);
              if (ctaCheckbox && savedState.cta_enabled !== undefined) {
                ctaCheckbox.checked = savedState.cta_enabled;
                ctaCheckbox.dispatchEvent(new Event('change'));
              }

              if (savedState.cta_type) {
                var ctaRadios = form.querySelectorAll('input[name="cta_type"]');
                ctaRadios.forEach(function(radio) {
                  radio.checked = radio.value === savedState.cta_type;
                });
              }

              msg.style.color = '#2563eb';
              msg.innerHTML = 'Wznowiono edycję z poprzedniej sesji. <button type="button" style="background:none;border:none;color:#2563eb;text-decoration:underline;cursor:pointer;padding:0;font-size:12px" id="edit-discard-saved-state">Zacznij od nowa</button>';
              var discardBtn = qs('#edit-discard-saved-state', form);
              if (discardBtn) {
                discardBtn.onclick = function() {
                  try { sessionStorage.removeItem('jg_edit_modal_state'); } catch(e) {}
                  openEditModal(p, fromReports);
                };
              }
            }
          }
        } catch(e) {}

        // On form submit, sync the rich editor content
        form.addEventListener('submit', function() {
          if (editRte) editRte.syncContent();
          if (editTagInput) editTagInput.syncHidden();
          if (editOhPicker) editOhPicker.syncHidden();
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

          // Apply input masks to sponsored contact fields
          applyPhoneMask(qs('#edit-phone-input', modalEdit));
          applyWebsiteMask(qs('#edit-website-input', modalEdit));
          applySocialMask(qs('#edit-facebook-input', modalEdit), ['facebook.com/', 'fb.com/', 'm.facebook.com/']);
          applySocialMask(qs('#edit-instagram-input', modalEdit), ['instagram.com/', 'instagr.am/', 'm.instagram.com/']);
          applySocialMask(qs('#edit-linkedin-input', modalEdit), ['linkedin.com/in/', 'linkedin.com/company/', 'linkedin.com/']);
          applySocialMask(qs('#edit-tiktok-input', modalEdit), ['tiktok.com/@', 'tiktok.com/']);
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

          // Validate contact fields (available for all points)
          var editPhoneEl = qs('#edit-phone-input', modalEdit);
          var editWebsiteEl = qs('#edit-website-input', modalEdit);
          var editEmailEl = qs('#edit-email-input', modalEdit);

          if (editWebsiteEl) editWebsiteEl.value = editWebsiteEl.value.replace(/^https?:\/\//i, '').trim();

          var phoneVal = editPhoneEl ? editPhoneEl.value.trim() : '';
          var websiteVal = editWebsiteEl ? editWebsiteEl.value.trim() : '';
          var emailVal = editEmailEl ? editEmailEl.value.trim() : '';

          if (phoneVal && !isValidPhone(phoneVal)) {
            var phoneDigits = phoneVal.replace(/[^0-9]/g, '');
            msg.textContent = phoneDigits.length < 9
              ? 'Numer telefonu musi zawierać co najmniej 9 cyfr (wpisano: ' + phoneDigits.length + ').'
              : 'Numer telefonu zawiera niedozwolone znaki (dozwolone: cyfry, spacje, +, -, nawiasy).';
            msg.style.color = '#b91c1c';
            if (editPhoneEl) { editPhoneEl.style.borderColor = '#ef4444'; editPhoneEl.focus(); }
            return;
          }

          if (websiteVal && !isValidWebsite(websiteVal)) {
            msg.textContent = 'Podaj poprawny adres strony internetowej (np. jeleniagora.pl).';
            msg.style.color = '#b91c1c';
            if (editWebsiteEl) { editWebsiteEl.style.borderColor = '#ef4444'; editWebsiteEl.focus(); }
            return;
          }

          if (emailVal && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
            msg.textContent = 'Podaj poprawny adres email kontaktowy.';
            msg.style.color = '#b91c1c';
            if (editEmailEl) { editEmailEl.style.borderColor = '#ef4444'; editEmailEl.focus(); }
            return;
          }

          // Normalize and validate sponsored social media fields
          if (isSponsored) {
            var editFacebookEl = qs('#edit-facebook-input', modalEdit);
            var editInstagramEl = qs('#edit-instagram-input', modalEdit);
            var editLinkedinEl = qs('#edit-linkedin-input', modalEdit);
            var editTiktokEl = qs('#edit-tiktok-input', modalEdit);

            if (editFacebookEl) editFacebookEl.value = normalizeSocialValue(editFacebookEl.value, ['facebook.com/', 'fb.com/', 'm.facebook.com/']);
            if (editInstagramEl) editInstagramEl.value = normalizeSocialValue(editInstagramEl.value, ['instagram.com/', 'instagr.am/', 'm.instagram.com/']);
            if (editLinkedinEl) editLinkedinEl.value = normalizeSocialValue(editLinkedinEl.value, ['linkedin.com/in/', 'linkedin.com/company/', 'linkedin.com/']);
            if (editTiktokEl) editTiktokEl.value = normalizeSocialValue(editTiktokEl.value, ['tiktok.com/@', 'tiktok.com/']);

            var socialValidations = [
              { el: editFacebookEl, name: 'Facebook' },
              { el: editInstagramEl, name: 'Instagram' },
              { el: editLinkedinEl, name: 'LinkedIn' },
              { el: editTiktokEl, name: 'TikTok' }
            ];
            for (var si = 0; si < socialValidations.length; si++) {
              var sv = socialValidations[si];
              if (sv.el && sv.el.value.trim() && !isValidSocialUsername(sv.el.value.trim())) {
                msg.textContent = 'Profil ' + sv.name + ' zawiera niedozwolone znaki.';
                msg.style.color = '#b91c1c';
                sv.el.style.borderColor = '#ef4444';
                sv.el.focus();
                return;
              }
            }
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
            refreshChallengeProgress();
            // Clear any saved draft state on successful submission
            try { sessionStorage.removeItem('jg_edit_modal_state'); } catch(e) {}
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

        // Apply input masks to phone and website fields
        applyPhoneMask(phoneInput);
        applyWebsiteMask(websiteInput);

        saveBtn.onclick = function() {
          var selectedSponsored = qs('input[name="sponsored_status"]:checked', modalStatus);
          if (!selectedSponsored) {
            msg.textContent = 'Wybierz status sponsorowania';
            msg.style.color = '#b91c1c';
            return;
          }

          var isSponsored = selectedSponsored.value === '1';
          var sponsoredUntil = dateInput.value || '';

          // Normalize before reading (replicates blur-handler logic)
          websiteInput.value = websiteInput.value.replace(/^https?:\/\//i, '').trim();
          var website = websiteInput.value.trim();
          var phone = phoneInput.value.trim();
          var ctaEnabled = ctaEnabledCheckbox.checked;
          var ctaType = null;

          // Validate phone format
          if (phone && !isValidPhone(phone)) {
            var promoPhoneDigits = phone.replace(/[^0-9]/g, '');
            msg.textContent = promoPhoneDigits.length < 9
              ? 'Numer telefonu musi zawierać co najmniej 9 cyfr (wpisano: ' + promoPhoneDigits.length + ').'
              : 'Numer telefonu zawiera niedozwolone znaki (dozwolone: cyfry, spacje, +, -, nawiasy).';
            msg.style.color = '#b91c1c';
            phoneInput.style.borderColor = '#ef4444';
            phoneInput.focus();
            return;
          }

          // Validate website format
          if (website && !isValidWebsite(website)) {
            msg.textContent = 'Podaj poprawny adres strony internetowej (np. jeleniagora.pl).';
            msg.style.color = '#b91c1c';
            websiteInput.style.borderColor = '#ef4444';
            websiteInput.focus();
            return;
          }

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
          '<input type="radio" name="status" value="processing" ' + (currentStatus === 'processing' ? 'checked' : '') + ' style="width:20px;height:20px">' +
          '<div><strong>Procesowanie</strong></div>' +
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

      // ===== SECTION: DETAILS MODAL =====
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
        if (!CFG.isLoggedIn && window._jgGuestEngagement) {
          window._jgGuestEngagement.placesViewed++;
          if (window._jgGuestEngagement.placesViewed >= 2) {
            setTimeout(function() { window._jgGuestEngagement.trigger('places'); }, 300);
          }
        }

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

        var datePart = (p.date && p.date.human) ? '<span class="jg-meta-date" title="' + (p.date.full ? esc(p.date.full) : '') + '" style="cursor:default">Dodano <strong>' + esc(p.date.human) + '</strong></span>' : '';

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

          // Show place/curiosity category changes
          if (p.type === 'miejsce' || p.type === 'ciekawostka') {
            var prevCat = p.edit_info.prev_category ? formatCategorySlug(p.edit_info.prev_category) : '(brak)';
            var newCat = p.edit_info.new_category ? formatCategorySlug(p.edit_info.new_category) : '(brak)';
            if (p.edit_info.prev_category !== p.edit_info.new_category) {
              changes.push('<div><strong>Kategoria:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + esc(prevCat) + '</span><br><span style="color:#16a34a">→ ' + esc(newCat) + '</span></div>');
            }
          }

          // Show address changes
          if (p.edit_info.prev_address !== undefined && p.edit_info.new_address !== undefined && (p.edit_info.prev_address || '') !== (p.edit_info.new_address || '')) {
            changes.push('<div><strong>📍 Adres:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + esc(p.edit_info.prev_address || '(brak)') + '</span><br><span style="color:#16a34a">→ ' + esc(p.edit_info.new_address || '(brak)') + '</span></div>');
          }

          // Show tags changes
          if (p.edit_info.prev_tags !== undefined && p.edit_info.new_tags !== undefined) {
            var prevTags = '';
            var newTags = '';
            try { prevTags = JSON.parse(p.edit_info.prev_tags || '[]').join(', ') || '(brak)'; } catch(e) { prevTags = p.edit_info.prev_tags || '(brak)'; }
            try { newTags = JSON.parse(p.edit_info.new_tags || '[]').join(', ') || '(brak)'; } catch(e) { newTags = p.edit_info.new_tags || '(brak)'; }
            if (prevTags !== newTags) {
              changes.push('<div><strong>🏷️ Tagi:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + esc(prevTags) + '</span><br><span style="color:#16a34a">→ ' + esc(newTags) + '</span></div>');
            }
          }

          // Show lat/lng changes (position on map)
          if (p.edit_info.prev_lat !== undefined && p.edit_info.new_lat !== undefined) {
            var latDiff = Math.abs((parseFloat(p.edit_info.new_lat) || 0) - (parseFloat(p.edit_info.prev_lat) || 0));
            var lngDiff = Math.abs((parseFloat(p.edit_info.new_lng) || 0) - (parseFloat(p.edit_info.prev_lng) || 0));
            if (latDiff > 0.00001 || lngDiff > 0.00001) {
              changes.push('<div><strong>📌 Pozycja na mapie:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + parseFloat(p.edit_info.prev_lat).toFixed(5) + ', ' + parseFloat(p.edit_info.prev_lng).toFixed(5) + '</span><br><span style="color:#16a34a">→ ' + parseFloat(p.edit_info.new_lat).toFixed(5) + ', ' + parseFloat(p.edit_info.new_lng).toFixed(5) + '</span></div>');
            }
          }

          // Show opening_hours changes
          if (p.edit_info.prev_opening_hours !== undefined && p.edit_info.new_opening_hours !== undefined && (p.edit_info.prev_opening_hours || '') !== (p.edit_info.new_opening_hours || '')) {
            changes.push('<div><strong>🕐 Godziny otwarcia:</strong><br><span style="text-decoration:line-through;color:#dc2626;white-space:pre-line">' + esc(p.edit_info.prev_opening_hours || '(brak)') + '</span><br><span style="color:#16a34a;white-space:pre-line">→ ' + esc(p.edit_info.new_opening_hours || '(brak)') + '</span></div>');
          }

          // Show website changes if present
          if (p.edit_info.prev_website !== undefined && p.edit_info.new_website !== undefined && p.edit_info.prev_website !== p.edit_info.new_website) {
            changes.push('<div><strong>🌐 Strona internetowa:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + (p.edit_info.prev_website || '(brak)') + '</span><br><span style="color:#16a34a">→ ' + (p.edit_info.new_website || '(brak)') + '</span></div>');
          }

          // Show phone changes if present
          if (p.edit_info.prev_phone !== undefined && p.edit_info.new_phone !== undefined && p.edit_info.prev_phone !== p.edit_info.new_phone) {
            changes.push('<div><strong>📞 Telefon:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + (p.edit_info.prev_phone || '(brak)') + '</span><br><span style="color:#16a34a">→ ' + (p.edit_info.new_phone || '(brak)') + '</span></div>');
          }

          // Show email changes if present
          if (p.edit_info.prev_email !== undefined && p.edit_info.new_email !== undefined && p.edit_info.prev_email !== p.edit_info.new_email) {
            changes.push('<div><strong>✉️ Email kontaktowy:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + (p.edit_info.prev_email || '(brak)') + '</span><br><span style="color:#16a34a">→ ' + (p.edit_info.new_email || '(brak)') + '</span></div>');
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

          // Build list of changes made by this user
          var myChangesList = [];
          var mei = p.edit_info;
          if (mei.prev_title !== mei.new_title) {
            myChangesList.push('<div style="margin:4px 0"><strong>Tytuł:</strong> <span style="color:#991b1b;text-decoration:line-through">' + esc(mei.prev_title || '(brak)') + '</span> → <span style="color:#166534">' + esc(mei.new_title || '(brak)') + '</span></div>');
          }
          if (mei.prev_type !== mei.new_type) {
            var myTypeLabels = { zgloszenie: 'Zgłoszenie', ciekawostka: 'Ciekawostka', miejsce: 'Miejsce' };
            myChangesList.push('<div style="margin:4px 0"><strong>Typ:</strong> <span style="color:#991b1b">' + (myTypeLabels[mei.prev_type] || esc(mei.prev_type || '(brak)')) + '</span> → <span style="color:#166534">' + (myTypeLabels[mei.new_type] || esc(mei.new_type || '(brak)')) + '</span></div>');
          }
          if (mei.prev_category !== mei.new_category) {
            myChangesList.push('<div style="margin:4px 0"><strong>Kategoria:</strong> <span style="color:#991b1b">' + esc(mei.prev_category || '(brak)') + '</span> → <span style="color:#166534">' + esc(mei.new_category || '(brak)') + '</span></div>');
          }
          if (mei.prev_content !== mei.new_content) {
            myChangesList.push('<div style="margin:4px 0"><strong>Opis:</strong> <em style="color:#6b7280">(zmieniony)</em></div>');
          }
          if ((mei.prev_address || '') !== (mei.new_address || '') && (mei.prev_address || mei.new_address)) {
            myChangesList.push('<div style="margin:4px 0"><strong>📍 Adres:</strong> <span style="color:#991b1b">' + esc(mei.prev_address || '(brak)') + '</span> → <span style="color:#166534">' + esc(mei.new_address || '(brak)') + '</span></div>');
          }
          if (mei.prev_lat !== undefined && mei.new_lat !== undefined) {
            var myLatDiff = Math.abs((parseFloat(mei.new_lat) || 0) - (parseFloat(mei.prev_lat) || 0));
            var myLngDiff = Math.abs((parseFloat(mei.new_lng) || 0) - (parseFloat(mei.prev_lng) || 0));
            if (myLatDiff > 0.00001 || myLngDiff > 0.00001) {
              myChangesList.push('<div style="margin:4px 0"><strong>📌 Pozycja na mapie:</strong> <em style="color:#6b7280">(zmieniona)</em></div>');
            }
          }
          if ((mei.prev_website || '') !== (mei.new_website || '') && mei.new_website !== undefined) {
            myChangesList.push('<div style="margin:4px 0"><strong>🌐 Strona:</strong> <span style="color:#991b1b">' + esc(mei.prev_website || '(brak)') + '</span> → <span style="color:#166534">' + esc(mei.new_website || '(brak)') + '</span></div>');
          }
          if ((mei.prev_phone || '') !== (mei.new_phone || '') && mei.new_phone !== undefined) {
            myChangesList.push('<div style="margin:4px 0"><strong>📞 Telefon:</strong> <span style="color:#991b1b">' + esc(mei.prev_phone || '(brak)') + '</span> → <span style="color:#166534">' + esc(mei.new_phone || '(brak)') + '</span></div>');
          }
          if (mei.new_images && mei.new_images.length > 0) {
            myChangesList.push('<div style="margin:4px 0"><strong>🖼️ Nowe zdjęcia:</strong> +' + mei.new_images.length + '</div>');
          }

          var myChangesHtml = myChangesList.length > 0
            ? '<div style="font-size:13px;margin-top:10px;padding-top:10px;border-top:1px solid #e9d5ff">' +
              '<div style="font-weight:600;color:#6b21a8;margin-bottom:6px">Twoje zmiany:</div>' +
              myChangesList.join('') +
              '</div>'
            : '';

          editInfo = '<div style="background:#faf5ff;border:2px solid #9333ea;border-radius:8px;padding:12px;margin:16px 0">' +
            '<div style="font-weight:700;margin-bottom:8px;color:#6b21a8">📝 Twoja propozycja zmian oczekuje na zatwierdzenie</div>' +
            '<div style="font-size:12px;color:#7c3aed;margin-bottom:12px">Zgłoszone ' + esc(p.edit_info.edited_at) + '</div>' +
            '<div style="background:#f3e8ff;padding:12px;border-radius:8px;display:flex;flex-direction:column;gap:8px">' +
            ownerStatus +
            adminStatus +
            '</div>' +
            myChangesHtml +
            '<div style="margin-top:12px">' +
            '<button class="jg-btn" id="btn-user-revert-edit" style="background:#6b7280;padding:8px 16px;font-size:13px" data-history-id="' + p.edit_info.history_id + '">↩ Cofnij moje zmiany</button>' +
            '</div>' +
            '</div>';
        }

        // Show pending edit status to the owner who edited their own place (not admin)
        if (!CFG.isAdmin && p.is_own_place && p.is_edit && p.edit_info && p.edit_info.is_my_edit && !p.edit_info.is_external_edit) {
          var ownEditChangesList = [];
          var oei = p.edit_info;
          if (oei.prev_title !== oei.new_title) {
            ownEditChangesList.push('<div style="margin:4px 0"><strong>Tytuł:</strong> <span style="color:#991b1b;text-decoration:line-through">' + esc(oei.prev_title || '(brak)') + '</span> → <span style="color:#166534">' + esc(oei.new_title || '(brak)') + '</span></div>');
          }
          if (oei.prev_type !== oei.new_type) {
            var ownTypeLabels = { zgloszenie: 'Zgłoszenie', ciekawostka: 'Ciekawostka', miejsce: 'Miejsce' };
            ownEditChangesList.push('<div style="margin:4px 0"><strong>Typ:</strong> <span style="color:#991b1b">' + (ownTypeLabels[oei.prev_type] || esc(oei.prev_type || '(brak)')) + '</span> → <span style="color:#166534">' + (ownTypeLabels[oei.new_type] || esc(oei.new_type || '(brak)')) + '</span></div>');
          }
          if (oei.prev_category !== oei.new_category) {
            ownEditChangesList.push('<div style="margin:4px 0"><strong>Kategoria:</strong> <span style="color:#991b1b">' + esc(oei.prev_category || '(brak)') + '</span> → <span style="color:#166534">' + esc(oei.new_category || '(brak)') + '</span></div>');
          }
          if (oei.prev_content !== oei.new_content) {
            ownEditChangesList.push('<div style="margin:4px 0"><strong>Opis:</strong> <em style="color:#6b7280">(zmieniony)</em></div>');
          }
          if ((oei.prev_address || '') !== (oei.new_address || '') && (oei.prev_address || oei.new_address)) {
            ownEditChangesList.push('<div style="margin:4px 0"><strong>📍 Adres:</strong> <span style="color:#991b1b">' + esc(oei.prev_address || '(brak)') + '</span> → <span style="color:#166534">' + esc(oei.new_address || '(brak)') + '</span></div>');
          }
          if (oei.prev_lat !== undefined && oei.new_lat !== undefined) {
            var ownLatDiff = Math.abs((parseFloat(oei.new_lat) || 0) - (parseFloat(oei.prev_lat) || 0));
            var ownLngDiff = Math.abs((parseFloat(oei.new_lng) || 0) - (parseFloat(oei.prev_lng) || 0));
            if (ownLatDiff > 0.00001 || ownLngDiff > 0.00001) {
              ownEditChangesList.push('<div style="margin:4px 0"><strong>📌 Pozycja na mapie:</strong> <em style="color:#6b7280">(zmieniona)</em></div>');
            }
          }
          if ((oei.prev_website || '') !== (oei.new_website || '') && oei.new_website !== undefined) {
            ownEditChangesList.push('<div style="margin:4px 0"><strong>🌐 Strona:</strong> <span style="color:#991b1b">' + esc(oei.prev_website || '(brak)') + '</span> → <span style="color:#166534">' + esc(oei.new_website || '(brak)') + '</span></div>');
          }
          if ((oei.prev_phone || '') !== (oei.new_phone || '') && oei.new_phone !== undefined) {
            ownEditChangesList.push('<div style="margin:4px 0"><strong>📞 Telefon:</strong> <span style="color:#991b1b">' + esc(oei.prev_phone || '(brak)') + '</span> → <span style="color:#166534">' + esc(oei.new_phone || '(brak)') + '</span></div>');
          }
          if (oei.new_images && oei.new_images.length > 0) {
            ownEditChangesList.push('<div style="margin:4px 0"><strong>🖼️ Nowe zdjęcia:</strong> +' + oei.new_images.length + '</div>');
          }

          var ownChangesHtml = ownEditChangesList.length > 0
            ? '<div style="font-size:13px;margin-top:10px;padding-top:10px;border-top:1px solid #e9d5ff">' +
              '<div style="font-weight:600;color:#6b21a8;margin-bottom:6px">Twoje zmiany:</div>' +
              ownEditChangesList.join('') +
              '</div>'
            : '';

          editInfo = '<div style="background:#faf5ff;border:2px solid #9333ea;border-radius:8px;padding:12px;margin:16px 0">' +
            '<div style="font-weight:700;margin-bottom:8px;color:#6b21a8">📝 Twoja edycja oczekuje na zatwierdzenie przez moderatora</div>' +
            '<div style="font-size:12px;color:#7c3aed;margin-bottom:8px">Zgłoszone ' + esc(p.edit_info.edited_at) + '</div>' +
            ownChangesHtml +
            '<div style="margin-top:12px">' +
            '<button class="jg-btn" id="btn-user-revert-edit" style="background:#6b7280;padding:8px 16px;font-size:13px" data-history-id="' + p.edit_info.history_id + '">↩ Cofnij moje zmiany</button>' +
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
          if (p.edit_info.prev_category !== p.edit_info.new_category) {
            ownerChanges.push('<div><strong>Kategoria:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + esc(p.edit_info.prev_category || '(brak)') + '</span><br><span style="color:#16a34a">→ ' + esc(p.edit_info.new_category || '(brak)') + '</span></div>');
          }
          if (p.edit_info.prev_tags !== undefined && p.edit_info.new_tags !== undefined) {
            var prevTagsOwner = '';
            var newTagsOwner = '';
            try { prevTagsOwner = JSON.parse(p.edit_info.prev_tags || '[]').join(', ') || '(brak)'; } catch(e) { prevTagsOwner = p.edit_info.prev_tags || '(brak)'; }
            try { newTagsOwner = JSON.parse(p.edit_info.new_tags || '[]').join(', ') || '(brak)'; } catch(e) { newTagsOwner = p.edit_info.new_tags || '(brak)'; }
            if (prevTagsOwner !== newTagsOwner) {
              ownerChanges.push('<div><strong>🏷️ Tagi:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + esc(prevTagsOwner) + '</span><br><span style="color:#16a34a">→ ' + esc(newTagsOwner) + '</span></div>');
            }
          }
          if ((p.edit_info.prev_address || '') !== (p.edit_info.new_address || '')) {
            ownerChanges.push('<div><strong>📍 Adres:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + esc(p.edit_info.prev_address || '(brak)') + '</span><br><span style="color:#16a34a">→ ' + esc(p.edit_info.new_address || '(brak)') + '</span></div>');
          }
          if (p.edit_info.prev_lat !== undefined && p.edit_info.new_lat !== undefined) {
            var latDiffOwner = Math.abs((parseFloat(p.edit_info.new_lat) || 0) - (parseFloat(p.edit_info.prev_lat) || 0));
            var lngDiffOwner = Math.abs((parseFloat(p.edit_info.new_lng) || 0) - (parseFloat(p.edit_info.prev_lng) || 0));
            if (latDiffOwner > 0.00001 || lngDiffOwner > 0.00001) {
              ownerChanges.push('<div><strong>📌 Pozycja na mapie:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + parseFloat(p.edit_info.prev_lat).toFixed(5) + ', ' + parseFloat(p.edit_info.prev_lng).toFixed(5) + '</span><br><span style="color:#16a34a">→ ' + parseFloat(p.edit_info.new_lat).toFixed(5) + ', ' + parseFloat(p.edit_info.new_lng).toFixed(5) + '</span></div>');
            }
          }
          if ((p.edit_info.prev_opening_hours || '') !== (p.edit_info.new_opening_hours || '')) {
            ownerChanges.push('<div><strong>🕐 Godziny otwarcia:</strong><br><span style="text-decoration:line-through;color:#dc2626;white-space:pre-line">' + esc(p.edit_info.prev_opening_hours || '(brak)') + '</span><br><span style="color:#16a34a;white-space:pre-line">→ ' + esc(p.edit_info.new_opening_hours || '(brak)') + '</span></div>');
          }
          if (p.edit_info.prev_website !== undefined && p.edit_info.new_website !== undefined && p.edit_info.prev_website !== p.edit_info.new_website) {
            ownerChanges.push('<div><strong>🌐 Strona internetowa:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + esc(p.edit_info.prev_website || '(brak)') + '</span><br><span style="color:#16a34a">→ ' + esc(p.edit_info.new_website || '(brak)') + '</span></div>');
          }
          if (p.edit_info.prev_phone !== undefined && p.edit_info.new_phone !== undefined && p.edit_info.prev_phone !== p.edit_info.new_phone) {
            ownerChanges.push('<div><strong>📞 Telefon:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + esc(p.edit_info.prev_phone || '(brak)') + '</span><br><span style="color:#16a34a">→ ' + esc(p.edit_info.new_phone || '(brak)') + '</span></div>');
          }
          if (p.edit_info.prev_email !== undefined && p.edit_info.new_email !== undefined && p.edit_info.prev_email !== p.edit_info.new_email) {
            ownerChanges.push('<div><strong>✉️ Email kontaktowy:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + esc(p.edit_info.prev_email || '(brak)') + '</span><br><span style="color:#16a34a">→ ' + esc(p.edit_info.new_email || '(brak)') + '</span></div>');
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

            var ownerOverrideBtn = (ei.requires_owner_approval && ei.owner_approval_status !== 'approved')
              ? '<button class="jg-btn" id="btn-override-owner-approval" style="background:#7c3aed;padding:8px 12px;font-size:13px;white-space:nowrap" title="Zatwierdź edycję bez czekania na akceptację właściciela">⚡ Obejdź akceptację właściciela</button>'
              : '';

            pendingChanges.push(
              '<div style="background:#faf5ff;border-left:4px solid #9333ea;padding:12px;border-radius:6px;margin-bottom:8px">' +
              '<div style="display:flex;justify-content:space-between;align-items:start;gap:12px">' +
              '<div style="flex:1">' +
              '<div style="font-weight:700;color:#6b21a8;margin-bottom:4px">📝 Edycja oczekuje</div>' +
              '<div style="font-size:13px;color:#7e22ce">Edytowano: <strong>' + esc(editedAt) + '</strong></div>' +
              changesHtml +
              '</div>' +
              '<div style="display:flex;gap:6px;flex-shrink:0;flex-wrap:wrap;justify-content:flex-end">' +
              '<button class="jg-btn" id="btn-approve-edit" style="background:#15803d;padding:8px 12px;font-size:13px;white-space:nowrap">✓ Akceptuj edycję</button>' +
              '<button class="jg-btn" id="btn-reject-edit" style="background:#b91c1c;padding:8px 12px;font-size:13px;white-space:nowrap">✗ Odrzuć edycję</button>' +
              ownerOverrideBtn +
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
        var myRating = p.my_rating || '';

        // Don't show voting for promo points
        var voteHtml = '';
        if (!p.sponsored && !isOwnPoint) {
          var ra = +(p.rating || 0);
          var rc = +(p.ratings_count || 0);
          var avgTxt = rc > 0 ? (ra.toFixed(1) + ' (' + rc + ' ' + ratingCountLabel(rc) + ')') : 'Brak ocen';
          voteHtml = '<div class="jg-vote">' +
            '<div class="jg-vote-row">' +
            '<div class="jg-vote-stars" id="v-stars">' + starsHtml(ra, myRating) + '</div>' +
            '<div class="jg-vote-avg" id="v-avg" style="' + colorForRating(ra) + '">' + avgTxt + '</div>' +
            '</div>' +
            (CFG.isLoggedIn ? '' : '<div class="jg-vote-hint">Zaloguj się, aby ocenić</div>') +
            '</div>';
        } else if (!p.sponsored && isOwnPoint) {
          // Show read-only average for own points (same star display, no interaction)
          var ra = +(p.rating || 0);
          var rc = +(p.ratings_count || 0);
          var avgTxt = rc > 0 ? (ra.toFixed(1) + ' (' + rc + ' ' + ratingCountLabel(rc) + ')') : 'Brak ocen';
          voteHtml = '<div class="jg-vote jg-vote--own">' +
            '<div class="jg-vote-row">' +
            '<div class="jg-vote-stars">' + starsHtml(ra, '') + '</div>' +
            '<div class="jg-vote-avg" id="v-cnt" style="' + colorForRating(ra) + '">' + avgTxt + '</div>' +
            '</div>' +
            '</div>';
        }

        // Combine dateInfo and voteHtml into a single row
        var metaRow = '';
        if (dateInfo || voteHtml) {
          metaRow = '<div class="jg-meta-row">' + dateInfo + voteHtml + '</div>';
        }

        // Community verification badge (based on star rating)
        var verificationBadge = '';
        if (!p.sponsored && p.ratings_count >= 10) {
          if (p.rating >= 4.5) {
            verificationBadge = '<div style="padding:10px;background:#d1fae5;border:2px solid #10b981;border-radius:8px;margin:10px 0;text-align:center"><strong style="color:#065f46">✅ Wysoko oceniane przez społeczność Jeleniej Góry</strong><div style="font-size:12px;color:#047857;margin-top:4px">Średnia ocen: ' + p.rating.toFixed(1) + ' / 5 (' + p.ratings_count + ' ocen)</div></div>';
          } else if (p.rating <= 2.0) {
            verificationBadge = '<div style="padding:10px;background:#fee2e2;border:2px solid #ef4444;border-radius:8px;margin:10px 0;text-align:center"><strong style="color:#991b1b">⚠️ Nisko oceniane przez społeczność Jeleniej Góry</strong><div style="font-size:12px;color:#b91c1c;margin-top:4px">Średnia ocen: ' + p.rating.toFixed(1) + ' / 5 (' + p.ratings_count + ' ocen)</div></div>';
          }
        }

        // Kontakt section for all points (phone, email, website)
        var kontaktInfo = '';
        var kontaktBoxHtml = '';
        if (p.phone || p.email || p.website) {
          var kontaktItems = [];
          if (p.phone) {
            kontaktItems.push('<a href="tel:' + esc(p.phone) + '" class="jg-place-contact-open-btn"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg> ' + esc(p.phone) + '</a>');
          }
          if (p.email) {
            kontaktItems.push('<button type="button" class="jg-place-contact-open-btn" data-point-id="' + esc(String(p.id)) + '"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg> Napisz wiadomość</button>');
          }
          if (p.website) {
            var kontaktWebUrl = p.website.startsWith('http') ? p.website : 'https://' + p.website;
            var kontaktWebDisplay = p.website.replace(/^https?:\/\/(www\.)?/, '').replace(/[/?#].*$/, '');
            kontaktItems.push('<a href="' + esc(kontaktWebUrl) + '" class="jg-place-contact-open-btn" target="_blank" rel="noopener"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg> ' + esc(kontaktWebDisplay) + '</a>');
          }
          kontaktBoxHtml = '<div style="font-weight:700;font-size:0.875rem;text-transform:uppercase;letter-spacing:0.05em;color:#0369a1;margin-bottom:8px">Kontakt</div>' +
            '<div style="display:flex;flex-wrap:wrap;gap:8px">' + kontaktItems.join('') + '</div>';
        }

        // Directions button – inline next to address
        var dirBtnHtml = '';
        if (p.lat && p.lng) {
          var dirUrl = 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(p.lat + ',' + p.lng);
          dirBtnHtml = '<a href="' + dirUrl + '" target="_blank" rel="noopener" class="jg-dir-btn-inline">' +
            '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M21.71 11.29l-9-9a1 1 0 0 0-1.42 0l-9 9a1 1 0 0 0 0 1.42l9 9a1 1 0 0 0 1.42 0l9-9a1 1 0 0 0 0-1.42zM14 14.5V12h-4v3H8v-4a1 1 0 0 1 1-1h5V7.5l3.5 3.5-3.5 3.5z"/></svg>' +
            '<span>Wyznacz trasę</span></a>';
        }

        // Assemble kontaktInfo – contacts only, button moved to address row
        if (kontaktBoxHtml) {
          kontaktInfo = '<div style="margin:12px 0">' + kontaktBoxHtml + '</div>';
        }

        // Social media and CTA for sponsored points
        var contactInfo = '';
        var contactItemsHtml = '';
        if (p.sponsored && (p.facebook_url || p.instagram_url || p.linkedin_url || p.tiktok_url)) {
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
            contactItemsHtml = '<div style="display:flex;gap:10px;margin-top:8px">' + socialIcons.join('') + '</div>';
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
                ctaText = 'Zadzwoń';
                ctaIcon = '<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" style="display:block;margin:0 auto 2px"><path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56-.35-.12-.74-.03-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z"/></svg>';
              }
              break;
            case 'website':
              if (p.website) {
                ctaUrl = p.website.startsWith('http') ? p.website : 'https://' + p.website;
                ctaText = 'Strona';
                ctaIcon = '<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" style="display:block;margin:0 auto 2px"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>';
              }
              break;
            case 'facebook':
              if (p.facebook_url) {
                ctaUrl = p.facebook_url.startsWith('http') ? p.facebook_url : 'https://' + p.facebook_url;
                ctaText = 'Facebook';
                ctaIcon = '<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" style="display:block;margin:0 auto 2px"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>';
              }
              break;
            case 'instagram':
              if (p.instagram_url) {
                ctaUrl = p.instagram_url.startsWith('http') ? p.instagram_url : 'https://' + p.instagram_url;
                ctaText = 'Instagram';
                ctaIcon = '<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" style="display:block;margin:0 auto 2px"><path d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m-.2 2A3.6 3.6 0 0 0 4 7.6v8.8C4 18.39 5.61 20 7.6 20h8.8a3.6 3.6 0 0 0 3.6-3.6V7.6C20 5.61 18.39 4 16.4 4H7.6m9.65 1.5a1.25 1.25 0 0 1 1.25 1.25A1.25 1.25 0 0 1 17.25 8 1.25 1.25 0 0 1 16 6.75a1.25 1.25 0 0 1 1.25-1.25M12 7a5 5 0 0 1 5 5 5 5 0 0 1-5 5 5 5 0 0 1-5-5 5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3z"/></svg>';
              }
              break;
            case 'linkedin':
              if (p.linkedin_url) {
                ctaUrl = p.linkedin_url.startsWith('http') ? p.linkedin_url : 'https://' + p.linkedin_url;
                ctaText = 'LinkedIn';
                ctaIcon = '<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" style="display:block;margin:0 auto 2px"><path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z"/></svg>';
              }
              break;
            case 'tiktok':
              if (p.tiktok_url) {
                ctaUrl = p.tiktok_url.startsWith('http') ? p.tiktok_url : 'https://' + p.tiktok_url;
                ctaText = 'TikTok';
                ctaIcon = '<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" style="display:block;margin:0 auto 2px"><path d="M16.6 5.82s.51.5 0 0A4.278 4.278 0 0 1 15.54 3h-3.09v12.4a2.592 2.592 0 0 1-2.59 2.5c-1.42 0-2.6-1.16-2.6-2.6 0-1.72 1.66-3.01 3.37-2.48V9.66c-3.45-.46-6.47 2.22-6.47 5.64 0 3.33 2.76 5.7 5.69 5.7 3.14 0 5.69-2.55 5.69-5.7V9.01a7.35 7.35 0 0 0 4.3 1.38V7.3s-1.88.09-3.24-1.48z"/></svg>';
              }
              break;
          }

          if (ctaUrl) {
            var targetAttr = (p.cta_type === 'call') ? '' : ' target="_blank" rel="noopener"';
            ctaButton = '<a href="' + ctaUrl + '"' + targetAttr + ' class="jg-btn-cta-sponsored">' + ctaIcon + '<span>' + ctaText + '</span></a>';
          }
        }

        // Assemble contact info frame, placing CTA button on the right side
        if (contactItemsHtml || ctaButton) {
          if (contactItemsHtml && ctaButton) {
            contactInfo = '<div style="margin-top:10px;padding:12px;background:#fef3c7;border-radius:8px;border:2px solid #f59e0b;display:flex;align-items:center;gap:12px"><div style="flex:1;min-width:0">' + contactItemsHtml + '</div>' + ctaButton + '</div>';
          } else if (contactItemsHtml) {
            contactInfo = '<div style="margin-top:10px;padding:12px;background:#fef3c7;border-radius:8px;border:2px solid #f59e0b">' + contactItemsHtml + '</div>';
          } else {
            contactInfo = '<div style="margin-top:10px;padding:12px;background:#fef3c7;border-radius:8px;border:2px solid #f59e0b;display:flex;justify-content:flex-end">' + ctaButton + '</div>';
          }
          ctaButton = '';
        }

        // Add deletion request button for authors only (non-admins)
        var deletionBtn = '';
        if (isOwnPoint && !CFG.isAdmin && !p.is_deletion_requested) {
          deletionBtn = '<button id="btn-request-deletion" class="jg-btn jg-btn--danger">Zgłoś usunięcie</button>';
        }

        // Address info + inline directions button
        var addressInfo = '';
        if (p.address && p.address.trim()) {
          addressInfo = '<div class="jg-address-row"><span class="jg-address-text"><span style="font-weight:500;color:#374151">📍</span> ' + esc(p.address) + '</span>' + dirBtnHtml + '</div>';
        } else if (dirBtnHtml) {
          addressInfo = '<div class="jg-address-row">' + dirBtnHtml + '</div>';
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
            'processing': { bg: '#ffedd5', border: '#f97316', text: '#9a3412' },
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

        // Business promotion section — shown for pins whose category has show_promo enabled,
        // but NOT for places that are already sponsored (is_promo/sponsored flag on the pin itself)
        var businessPromoHtml = '';
        var promoCategories = Array.isArray(CFG.promoCategories) ? CFG.promoCategories : [];
        if (p.type === 'miejsce' && !p.sponsored && p.category && promoCategories.indexOf(p.category) !== -1) {
          businessPromoHtml = '<div class="jg-business-promo">' +
            '<div class="jg-business-promo__icon">💼</div>' +
            '<div class="jg-business-promo__text">' +
              '<strong>Jesteś właścicielem tego biznesu? Zwiększ jego widoczność na mapie już od 49 zł / miesiąc.</strong>' +
              '<p style="margin:6px 0 0;font-size:0.9rem;color:#4b5563">Promuj swoją firmę! Lepsza widoczność na mapie, możliwość dodania danych kontaktowych i priorytet w wyświetlaniu w naszym portalu.</p>' +
            '</div>' +
            '<button id="btn-business-promo" class="jg-business-promo__btn">Zapytaj o ofertę</button>' +
          '</div>';
        }

        // Build opening hours + price range display
        var openingHoursHtml = '';
        var _ohBoxHtml = '';
        var _priceRangeBoxHtml = '';

        // Price range box (independent of opening hours)
        if (p.type === 'miejsce' && p.price_range && isPriceRangeCategory(p.category || '')) {
          var prPriceLabels = { '$': 'Bardzo tanie', '$$': 'Umiarkowane', '$$$': 'Droższe', '$$$$': 'Ekskluzywne' };
          _priceRangeBoxHtml = '<div style="padding:10px 14px;background:#f0fdf4;border-radius:8px;border:1px solid #bbf7d0;font-size:0.875rem;color:#166534;flex:0 0 auto;min-width:140px;max-width:100%;box-sizing:border-box">' +
            '<div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;opacity:0.7">Zakres cenowy</div>' +
            '<div style="font-size:1.2rem;font-weight:800;letter-spacing:0.05em">' + esc(p.price_range) + '</div>' +
            '<div style="font-size:0.8rem;opacity:0.85;margin-top:2px">' + esc(prPriceLabels[p.price_range] || '') + '</div>' +
            '</div>';
        }

        // Opening hours box
        if (p.opening_hours && p.opening_hours.trim() && (p.type === 'miejsce' || p.type === 'ciekawostka')) {
          var ohDayLabels = { Mo: 'Pon', Tu: 'Wt', We: 'Śr', Th: 'Czw', Fr: 'Pt', Sa: 'Sob', Su: 'Niedz' };
          var ohAllDayKeys = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
          var todayKey = ohAllDayKeys[(new Date().getDay() + 6) % 7]; // Mon=0..Sun=6
          var ohColors = p.type === 'ciekawostka'
            ? { bg: '#fefce8', border: '#fde047', text: '#854d0e', btn: '#92400e' }
            : { bg: '#f0fdf4', border: '#bbf7d0', text: '#166534', btn: '#14532d' };

          // Parse all days from opening_hours string
          var ohParsed = {};
          p.opening_hours.trim().split('\n').forEach(function(line) {
            var m2 = line.trim().match(/^(Mo|Tu|We|Th|Fr|Sa|Su)\s+(\d{2}:\d{2})-(\d{2}:\d{2})$/);
            if (m2) ohParsed[m2[1]] = { open: m2[2], close: m2[3] };
          });

          if (Object.keys(ohParsed).length > 0) {
            var todayData = ohParsed[todayKey] || null;
            var now = new Date();
            var nowMins = now.getHours() * 60 + now.getMinutes();
            var ohIsOpen = false;
            if (todayData) {
              var ohOpenMins = parseInt(todayData.open.split(':')[0]) * 60 + parseInt(todayData.open.split(':')[1]);
              var ohCloseMins = parseInt(todayData.close.split(':')[0]) * 60 + parseInt(todayData.close.split(':')[1]);
              ohIsOpen = nowMins >= ohOpenMins && nowMins < ohCloseMins;
            }
            var todayIs24h = todayData && todayData.open === '00:00' && todayData.close === '24:00';
            var minsToClose = (todayData && ohIsOpen && !todayIs24h) ? (parseInt(todayData.close.split(':')[0]) * 60 + parseInt(todayData.close.split(':')[1])) - nowMins : -1;
            var closingWarning = (ohIsOpen && minsToClose > 0 && minsToClose < 60) ? 'Uwaga, zamknięcie za ' + minsToClose + ' min' : '';

            // Today row
            var todayRowHtml;
            if (!todayData) {
              todayRowHtml = '<div><span style="color:#dc2626;font-weight:600">Nieczynne</span></div>';
            } else if (!ohIsOpen) {
              // Find next opening time
              var ohNextOpen = '';
              var ohTodayIdx = ohAllDayKeys.indexOf(todayKey);
              if (todayData && nowMins < ohOpenMins) {
                ohNextOpen = 'Otwiera o ' + todayData.open;
              } else {
                for (var ohDi = 1; ohDi <= 7; ohDi++) {
                  var ohNextKey = ohAllDayKeys[(ohTodayIdx + ohDi) % 7];
                  if (ohParsed[ohNextKey]) {
                    var ohNextLabel = ohDi === 1 ? 'Jutro' : (ohDayLabels[ohNextKey] || ohNextKey);
                    ohNextOpen = ohNextLabel + ' o ' + ohParsed[ohNextKey].open;
                    break;
                  }
                }
              }
              todayRowHtml = '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">' +
                '<span style="color:#dc2626;font-weight:600">Zamknięte</span>' +
                (ohNextOpen ? '<span style="font-size:0.8rem;opacity:0.75">· ' + esc(ohNextOpen) + '</span>' : '') +
                '</div>';
            } else if (todayIs24h) {
              todayRowHtml = '<div style="display:flex;align-items:center;gap:8px">' +
                '<span style="font-weight:600">Otwarte całą dobę</span>' +
                '</div>';
            } else {
              todayRowHtml = '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">' +
                  '<span style="font-weight:600">' + esc(ohDayLabels[todayKey] || todayKey) + '</span>' +
                  '<span>' + esc(todayData.open) + ' – ' + esc(todayData.close) + '</span>' +
                  (closingWarning ? '<span id="jg-oh-warning" style="color:#d97706;font-size:0.8rem;font-weight:700">' + esc(closingWarning) + '</span>' : '') +
                '</div>';
            }

            // All days rows
            var allDaysHtml = ohAllDayKeys.map(function(dk) {
              var dd = ohParsed[dk] || null;
              var isToday = dk === todayKey;
              return '<div style="display:flex;justify-content:space-between;gap:16px' + (isToday ? ';font-weight:700' : '') + '">' +
                '<span style="min-width:36px">' + esc(ohDayLabels[dk] || dk) + '</span>' +
                (dd ? '<span>' + (dd.open === '00:00' && dd.close === '24:00' ? 'Całą dobę' : esc(dd.open) + ' – ' + esc(dd.close)) + '</span>' : '<span style="color:#dc2626">Nieczynne</span>') +
                '</div>';
            }).join('');

            _ohBoxHtml = '<div class="jg-opening-hours" style="padding:10px 14px;background:' + ohColors.bg + ';border-radius:8px;border:1px solid ' + ohColors.border + ';font-size:0.875rem;color:' + ohColors.text + ';flex:1;min-width:0">' +
              '<div id="jg-oh-title" style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;opacity:0.7">Dzisiejsze godziny otwarcia</div>' +
              '<div id="jg-oh-today">' + todayRowHtml + '</div>' +
              '<div id="jg-oh-all" style="display:none;margin-top:6px;line-height:2;color:' + ohColors.text + '">' + allDaysHtml + '</div>' +
              '<button id="btn-oh-expand" type="button" style="margin-top:6px;background:none;border:none;padding:0;color:' + ohColors.btn + ';font-size:0.8rem;cursor:pointer;text-decoration:underline;font-weight:600">Pokaż wszystkie dni</button>' +
              '</div>';
          }
        }

        // Show flex row if either section exists
        if (_ohBoxHtml || _priceRangeBoxHtml) {
          openingHoursHtml = '<div style="display:flex;gap:8px;margin:0 0 12px 0;flex-wrap:wrap;min-width:0;width:100%">' + _ohBoxHtml + _priceRangeBoxHtml + '</div>';
        }

        // Build menu section placeholder (async-loaded after modal opens)
        var menuSectionHtml = '';
        if (p.type === 'miejsce' && isMenuCategory(p.category)) {
          menuSectionHtml = '<div id="jg-menu-section" class="jg-menu-modal-section" style="margin:0 0 12px 0;padding:10px 14px;background:#f0fdf4;border-radius:8px;border:1px solid #bbf7d0;font-size:0.875rem;color:#166534">' +
            '<div id="jg-menu-title" style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;opacity:0.7">Aktualne menu</div>' +
            '<div id="jg-menu-content"><div style="font-size:0.85rem;color:#9ca3af">Ładowanie menu\u2026</div></div>' +
            '</div>';
        }

        // Build offerings section placeholder (async-loaded after modal opens)
        var offeringsSectionHtml = '';
        if (p.type === 'miejsce' && isOfferingsCategory(p.category)) {
          var ofLabel = getOfferingsLabel(p.category);
          offeringsSectionHtml = '<div id="jg-offerings-section" class="jg-menu-modal-section" style="margin:0 0 12px 0;padding:10px 14px;background:#f0fdf4;border-radius:8px;border:1px solid #bbf7d0;font-size:0.875rem;color:#166534">' +
            '<div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;opacity:0.7">📋 ' + esc(ofLabel) + '</div>' +
            '<div id="jg-offerings-content"><div style="font-size:0.85rem;color:#9ca3af">Ładowanie\u2026</div></div>' +
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

        var menuBtn = (canEdit && p.type === 'miejsce' && isMenuCategory(p.category))
          ? '<button id="btn-manage-menu" class="jg-btn jg-btn--ghost">🍽️ Menu</button>'
          : '';

        var offeringsBtn = (canEdit && p.type === 'miejsce' && isOfferingsCategory(p.category))
          ? '<button id="btn-manage-offerings" class="jg-btn jg-btn--ghost">📋 ' + esc(getOfferingsLabel(p.category)) + '</button>'
          : '';

        var html = '<header style="display:flex;align-items:center;justify-content:space-between;gap:12px;border-bottom:1px solid #e5e7eb"><div style="display:flex;align-items:center;gap:12px;min-width:0;overflow:hidden">' + sponsoredBadgeHeader + typeBadge + categoryBadgeHeader + '</div><div style="display:flex;align-items:center;gap:12px;flex-shrink:0">' + statusBadge + caseIdBadge + '<button class="jg-close" id="dlg-close" style="margin:0">&times;</button></div></header><div class="jg-grid" style="overflow-y:auto;overflow-x:hidden;padding:20px"><h3 class="jg-place-title" style="margin:0 0 16px 0;font-size:2.5rem;font-weight:400;line-height:1.2">' + esc(p.title || 'Szczegóły') + lockIcon + '</h3>' + openingHoursHtml + menuSectionHtml + offeringsSectionHtml + metaRow + addressInfo + (p.content ? ('<div class="jg-place-content">' + p.content + '</div>') : (p.excerpt ? ('<p class="jg-place-excerpt">' + esc(p.excerpt) + '</p>') : '')) + kontaktInfo + tagsHtml + ctaButton + (gal ? ('<div class="jg-gallery" style="margin-top:10px">' + gal + '</div>') : '') + contactInfo + (who ? ('<div style="margin-top:10px">' + who + '</div>') : '') + verificationBadge + reportsWarning + userReportNotice + editInfo + deletionInfo + adminNote + resolvedNotice + rejectedNotice + businessPromoHtml + shareHtml + adminBox + '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px">' + statsBtn + (canEdit ? '<button id="btn-edit" class="jg-btn jg-btn--ghost">Edytuj</button>' : '') + menuBtn + offeringsBtn + deletionBtn + '<button id="btn-report" class="jg-btn jg-btn--ghost">Zgłoś</button></div></div>';

        open(modalView, html, { addClass: (promoClass + typeClass).trim(), pointData: p });

        // Pin links in content already have SEO page URLs in href - let them navigate naturally

        // Track view for sponsored pins with unique visitor detection
        var viewStartTime = Date.now();
        if (p.sponsored) {
          var isUnique = isUniqueVisitor(p.id);
          trackStat(p.id, 'view', { is_unique: isUnique }, p.author_id);
        }

        // GA4: virtual page view — identical URL to standalone pin HTML page
        // Must use page_location (full URL) — GA4 ignores page_path for path reporting
        // and instead derives page path from page_location. Without page_location,
        // all modal opens would be attributed to '/' (the map page URL).
        // Skip if the modal was auto-opened via redirect from the HTML pin page,
        // because that page already fired its own GA4 page_view hit.
        if (skipNextGaPageView) {
          skipNextGaPageView = false;
        } else if (typeof gtag === 'function' && p.slug && p.type) {
          var gaTypePath = p.type === 'ciekawostka' ? 'ciekawostka' : (p.type === 'zgloszenie' ? 'zgloszenie' : 'miejsce');
          var gaPinPath = '/' + gaTypePath + '/' + p.slug + '/';
          // Build page_title matching the PHP <title> tag to avoid GA4 reporting
          // the same pin under two different titles (modal vs standalone page).
          // PHP appends " – {category_label} w Jeleniej Górze" (miejsce with category)
          // or " – {type_label} w Jeleniej Górze" (all other cases).
          var gaPageTitle = p.title || '';
          if (gaPageTitle) {
            var gaPlaceCats = (window.JG_MAP_CFG && JG_MAP_CFG.placeCategories) || {};
            var gaTypeLbls = { miejsce: 'Miejsce', ciekawostka: 'Ciekawostka', zgloszenie: 'Zgłoszenie' };
            var gaTypeLabel = gaTypeLbls[p.type] || 'Punkt';
            if (p.type === 'miejsce' && p.category && gaPlaceCats[p.category] && gaPlaceCats[p.category].label) {
              gaPageTitle = p.title + ' \u2013 ' + gaPlaceCats[p.category].label.toLowerCase() + ' w Jeleniej G\u00f3rze';
            } else {
              gaPageTitle = p.title + ' \u2013 ' + gaTypeLabel + ' w Jeleniej G\u00f3rze';
            }
          }
          gtag('event', 'page_view', {
            page_location: window.location.origin + gaPinPath,
            page_title: gaPageTitle
          });
        }

        // Opening hours expand/collapse toggle
        var ohExpandBtn = qs('#btn-oh-expand', modalView);
        if (ohExpandBtn) {
          ohExpandBtn.onclick = function() {
            var allDiv = qs('#jg-oh-all', modalView);
            var titleEl = qs('#jg-oh-title', modalView);
            var warningEl = qs('#jg-oh-warning', modalView);
            var isExpanded = ohExpandBtn.getAttribute('data-expanded') === '1';
            if (!isExpanded) {
              if (allDiv) allDiv.style.display = 'block';
              if (titleEl) titleEl.textContent = 'Godziny otwarcia';
              if (warningEl) warningEl.style.display = 'none';
              ohExpandBtn.textContent = 'Zwiń';
              ohExpandBtn.setAttribute('data-expanded', '1');
            } else {
              if (allDiv) allDiv.style.display = 'none';
              if (titleEl) titleEl.textContent = 'Dzisiejsze godziny otwarcia';
              if (warningEl) warningEl.style.display = '';
              ohExpandBtn.textContent = 'Pokaż wszystkie dni';
              ohExpandBtn.setAttribute('data-expanded', '0');
            }
          };
        }

        // Load and display menu for gastronomic places
        var menuSection = qs('#jg-menu-section', modalView);
        if (menuSection && p.type === 'miejsce' && isMenuCategory(p.category)) {
          loadMenuSection(p, menuSection);
        }

        // "Zarządzaj menu" button — open menu editor panel
        var manageMenuBtn = qs('#btn-manage-menu', modalView);
        if (manageMenuBtn) {
          manageMenuBtn.onclick = function() { openMenuEditor(p); };
        }

        // Load and display offerings for service/product places
        var offeringsSection = qs('#jg-offerings-section', modalView);
        if (offeringsSection && p.type === 'miejsce' && isOfferingsCategory(p.category)) {
          loadOfferingsSection(p, offeringsSection);
        }

        // "Zarządzaj ofertą" button — open offerings editor panel
        var manageOfferingsBtn = qs('#btn-manage-offerings', modalView);
        if (manageOfferingsBtn) {
          manageOfferingsBtn.onclick = function() { openOfferingsEditor(p); };
        }

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
              if (typeof window.openJoinModal === 'function') {
                window.openJoinModal({view: 'register', message: 'Aby wysłać zapytanie o ofertę promocji, musisz posiadać konto w naszym portalu. Zarejestruj się lub zaloguj, aby kontynuować.'});
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

        // Setup star rating handlers only if not promo and not own point
        if (!p.sponsored && !isOwnPoint) {
          var starsContainer = qs('#v-stars', modalView);
          var avgDisplay     = qs('#v-avg', modalView);

          if (starsContainer) {
            function refreshStars(avg, count, myR) {
              starsContainer.innerHTML = starsHtml(avg, myR);
              if (avgDisplay) {
                var avgTxt = count > 0 ? (avg.toFixed(1) + ' (' + count + ' ' + ratingCountLabel(count) + ')') : 'Brak ocen';
                avgDisplay.textContent = avgTxt;
                avgDisplay.setAttribute('style', colorForRating(avg));
              }
              // Rebind star button events after innerHTML update
              bindStarButtons();
            }

            function doVote(star) {
              if (!CFG.isLoggedIn) {
                showAlert('Zaloguj się, aby ocenić to miejsce.');
                return;
              }

              if (window.JG_USER_RESTRICTIONS) {
                if (window.JG_USER_RESTRICTIONS.is_banned) {
                  showAlert('Nie możesz oceniać - Twoje konto jest zbanowane.');
                  return;
                }
                if (window.JG_USER_RESTRICTIONS.restrictions && window.JG_USER_RESTRICTIONS.restrictions.indexOf('voting') !== -1) {
                  showAlert('Nie możesz oceniać - masz aktywną blokadę głosowania.');
                  return;
                }
              }

              var starBtns = starsContainer.querySelectorAll('.jg-star-btn');
              starBtns.forEach(function(b) { b.disabled = true; });

              voteReq({ post_id: p.id, dir: String(star) })
                .then(function(d) {
                  p.rating        = +(d.rating || 0);
                  p.ratings_count = +(d.ratings_count || 0);
                  p.my_rating     = d.my_rating || '';
                  refreshStars(p.rating, p.ratings_count, p.my_rating);
                  // Live-update sidebar item without full reload
                  if (typeof window.jgUpdatePointRating === 'function') {
                    window.jgUpdatePointRating(p.id, p.rating, p.ratings_count);
                  }
                  if (p.my_rating) {
                    var pressedBtn = qs('#v-star-' + star, starsContainer);
                    var starColors = ['#f59e0b', '#fbbf24', '#fcd34d', '#fde68a', '#fffbeb'];
                    if (pressedBtn) shootButtonConfetti(pressedBtn, starColors, 25);
                  }
                  refreshChallengeProgress();
                })
                .catch(function(e) {
                  showAlert((e && e.message) || 'Błąd');
                  var starBtnsAfter = starsContainer.querySelectorAll('.jg-star-btn');
                  starBtnsAfter.forEach(function(b) { b.disabled = false; });
                });
            }

            function bindStarButtons() {
              var btns = starsContainer.querySelectorAll('.jg-star-btn');
              btns.forEach(function(btn) {
                btn.onclick = function() {
                  doVote(parseInt(btn.getAttribute('data-star'), 10));
                };
                // Hover preview
                btn.onmouseenter = function() {
                  var hoverStar = parseInt(btn.getAttribute('data-star'), 10);
                  btns.forEach(function(b) {
                    var bs = parseInt(b.getAttribute('data-star'), 10);
                    b.textContent = bs <= hoverStar ? '★' : '☆';
                    if (bs <= hoverStar) {
                      b.classList.add('active');
                    } else {
                      b.classList.remove('active');
                    }
                  });
                };
                btn.onmouseleave = function() {
                  var myR = parseInt(p.my_rating, 10) || 0;
                  var fillUpTo = myR > 0 ? myR : Math.round(p.rating);
                  btns.forEach(function(b) {
                    var s = parseInt(b.getAttribute('data-star'), 10);
                    b.textContent = s <= fillUpTo ? '★' : '☆';
                    if (s <= fillUpTo) {
                      b.classList.add('active');
                    } else {
                      b.classList.remove('active');
                    }
                  });
                };
              });
            }

            bindStarButtons();
          }
        }

        qs('#btn-report', modalView).onclick = function() {
          openReportModal(p);
        };

        // Wire up "Napisz wiadomość" contact button (only present when place has email)
        var placeContactBtn = qs('.jg-place-contact-open-btn', modalView);
        if (placeContactBtn) {
          placeContactBtn.onclick = function() {
            openPlaceContactModal(p);
          };
        }

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

          // Override owner approval handler (admin/mod only)
          var btnOverrideOwnerApproval = qs('#btn-override-owner-approval', modalView);
          if (btnOverrideOwnerApproval) {
            btnOverrideOwnerApproval.onclick = function() {
              showConfirm('Zatwierdź edycję bez akceptacji właściciela?\n\nWłaściciel miejsca nie zostanie zapytany o zgodę — edycja zostanie natychmiast zatwierdzona.').then(function(confirmed) {
                if (!confirmed) return;

                btnOverrideOwnerApproval.disabled = true;
                btnOverrideOwnerApproval.textContent = 'Zatwierdzanie...';

                api('jg_admin_approve_edit', { history_id: p.edit_info.history_id, override_owner: 1 })
                  .then(function(result) {
                    cachedAllTags = null;
                    cachedAllTagsTime = 0;
                    shootMapMarkerConfetti(p.lat, p.lng,
                      ['#7c3aed', '#a78bfa', '#c4b5fd', '#fbbf24', '#ffffff', '#f5f3ff'], 40);
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
                    btnOverrideOwnerApproval.disabled = false;
                    btnOverrideOwnerApproval.textContent = '⚡ Obejdź akceptację właściciela';
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

        // User revert own pending edit handler
        var btnUserRevertEdit = qs('#btn-user-revert-edit', modalView);
        if (btnUserRevertEdit) {
          btnUserRevertEdit.onclick = function() {
            var historyId = this.getAttribute('data-history-id');
            showConfirm('Cofnąć swoje zmiany?\n\nEdycja zostanie anulowana i zniknie z listy oczekujących powiadomień moderatorów.').then(function(confirmed) {
              if (!confirmed) return;

              btnUserRevertEdit.disabled = true;
              btnUserRevertEdit.textContent = 'Cofanie...';

              api('jg_user_revert_edit', { history_id: historyId })
                .then(function(result) {
                  return refreshAll();
                })
                .then(function() {
                  close(modalView);
                  showAlert('Twoje zmiany zostały cofnięte.');
                  var updatedPoint = ALL.find(function(x) { return x.id === p.id; });
                  if (updatedPoint) {
                    setTimeout(function() {
                      openDetails(updatedPoint);
                    }, 200);
                  }
                })
                .catch(function(err) {
                  showAlert('Błąd: ' + (err.message || '?'));
                  btnUserRevertEdit.disabled = false;
                  btnUserRevertEdit.textContent = '↩ Cofnij moje zmiany';
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
      // ===== SECTION: POINT HISTORY MODAL =====
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
        }
        // data-promo and data-my-places may be in the place-categories dropdown
        var pr = document.querySelector('input[data-promo]');
        promoOnly = !!(pr && pr.checked);
        var myPlaces = document.querySelector('input[data-my-places]');
        myPlacesOnly = !!(myPlaces && myPlaces.checked);

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

          // Close panel and (on mobile) scroll to map
          setTimeout(function() {
            closeSearchPanel();
            if (window.innerWidth <= 768) {
              var mapEl = document.getElementById('jg-map');
              if (mapEl) {
                mapEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
              }
            }
          }, 300); // Small delay to show selection

          // Wait for zoom, then show FAST pulsing circle, then open modal
          setTimeout(function() {
            addFastPulsingMarker(point.lat, point.lng, function() {
              openDetails(point);
            });
          }, 600);
        }

        // Add fast pulsing red circle (~1.2s total), then call callback (modal opens AFTER circle ends)
        function addFastPulsingMarker(lat, lng, callback) {
          var pulsingCircle = L.circle([lat, lng], {
            color: '#ef4444',
            fillColor: '#ef4444',
            fillOpacity: 0.3,
            radius: 12,
            weight: 3
          }).addTo(map);

          var pulseCount = 0;
          var maxPulses = 6; // 6 pulses × 200ms = 1.2s
          var pulseInterval = setInterval(function() {
            pulseCount++;

            if (pulseCount % 2 === 0) {
              pulsingCircle.setStyle({ fillOpacity: 0.3, opacity: 1 });
            } else {
              pulsingCircle.setStyle({ fillOpacity: 0.1, opacity: 0.4 });
            }

            // After animation: remove circle, THEN call callback (modal opens)
            if (pulseCount >= maxPulses) {
              clearInterval(pulseInterval);
              setTimeout(function() {
                map.removeLayer(pulsingCircle);
                if (callback && typeof callback === 'function') {
                  callback();
                }
              }, 100);
            }
          }, 200);
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
              // When type filter (miejsce/ciekawostka) is toggled, sync all its category checkboxes
              var type = cb.getAttribute('data-type');
              if (type === 'miejsce') {
                document.querySelectorAll('input[data-map-place-category]').forEach(function(catCb) {
                  catCb.checked = cb.checked;
                });
              } else if (type === 'ciekawostka') {
                document.querySelectorAll('input[data-map-curiosity-category]').forEach(function(catCb) {
                  catCb.checked = cb.checked;
                });
              }
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
      // ===== SECTION: CATEGORY FILTERS =====
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
            html += '<label class="jg-category-filter-label"><input type="checkbox" data-map-place-category="' + cat.key + '" checked><span class="jg-filter-icon">' + (cat.icon || '📍') + '</span><span class="jg-category-filter-label__text">' + cat.label + '</span></label>';
          }
          html += '</div>';
          html += '<div class="jg-category-dropdown-section-header">Dodatkowe filtry:</div><div class="jg-category-extra-filters">';
          html += '<label class="jg-category-filter-label"><input type="checkbox" data-my-places><span class="jg-filter-icon">👤</span><span class="jg-category-filter-label__text">Moje miejsca</span></label>';
          html += '<label class="jg-category-filter-label"><input type="checkbox" data-promo><span class="jg-filter-icon">⭐</span><span class="jg-category-filter-label__text">Tylko miejsca sponsorowane</span></label>';
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

        // Add event listeners to expand buttons (accordion: only one open at a time)
        var expandBtns = document.querySelectorAll('.jg-filter-expand-btn');
        expandBtns.forEach(function(btn) {
          btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var target = this.getAttribute('data-expand-target');
            var dropdown = document.getElementById('jg-' + target);
            if (dropdown && dropdown.innerHTML.trim()) {
              var isVisible = dropdown.style.display !== 'none';

              // Close all other dropdowns (accordion behaviour)
              expandBtns.forEach(function(otherBtn) {
                var otherTarget = otherBtn.getAttribute('data-expand-target');
                if (otherTarget !== target) {
                  var otherDropdown = document.getElementById('jg-' + otherTarget);
                  if (otherDropdown) {
                    otherDropdown.style.display = 'none';
                  }
                  otherBtn.textContent = '▼';
                }
              });

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
            // If a category is checked and the parent type is off, re-enable the parent type
            if (cb.checked) {
              if (cb.hasAttribute('data-map-place-category')) {
                var placeTypeCb = elFilters && elFilters.querySelector('input[data-type="miejsce"]');
                if (placeTypeCb && !placeTypeCb.checked) {
                  placeTypeCb.checked = true;
                }
              } else if (cb.hasAttribute('data-map-curiosity-category')) {
                var curiosityTypeCb = elFilters && elFilters.querySelector('input[data-type="ciekawostka"]');
                if (curiosityTypeCb && !curiosityTypeCb.checked) {
                  curiosityTypeCb.checked = true;
                }
              }
            }
            apply(true);
          });
        });

        // Add event listeners to extra filters (my-places, promo) rendered inside place dropdown
        var extraFilters = document.querySelectorAll('#jg-place-categories input[data-my-places], #jg-place-categories input[data-promo]');
        extraFilters.forEach(function(cb) {
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
          .addClass('jg-sync-indicator');

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

        var dot = '<span class="jg-sync-dot"></span>';
        var text = '';
        var tooltipText = 'Status synchronizacji';

        if (state === 'online') {
          syncOnline = true;
          dot = '<span class="jg-sync-dot jg-sync-dot--online jg-sync-dot--pulse"></span>';
          text = '<span class="jg-sync-icon">⟳</span>';
          tooltipText = 'Synchronizacja: Online';
        } else if (state === 'syncing') {
          dot = '<span class="jg-sync-dot jg-sync-dot--syncing jg-sync-dot--pulse-fast"></span>';
          text = '<span class="jg-sync-icon jg-sync-icon--spin">⟳</span>';
          tooltipText = 'Synchronizacja w trakcie...';
        } else if (state === 'completed') {
          dot = '<span class="jg-sync-dot jg-sync-dot--online"></span>';
          text = '<span class="jg-sync-icon">✓</span>';
          tooltipText = 'Synchronizacja ukończona';

          // Return to "online" after 3 seconds
          syncCompletedTimeout = setTimeout(function() {
            updateSyncStatus('online');
          }, 3000);
        } else {
          // offline or error
          syncOnline = false;
          dot = '<span class="jg-sync-dot jg-sync-dot--offline"></span>';
          text = '<span class="jg-sync-icon">⚠</span>';
          tooltipText = 'Synchronizacja: ' + (state || 'Offline');
        }

        syncStatusIndicator.html(dot + text).attr('title', tooltipText).attr('data-state', state || 'offline');
      }

      // Animations are defined in jg-map.css (jg-sync-pulse, jg-sync-spin)

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

            // Update user count indicator for admins/mods
            if (CFG.isAdmin && syncData.online_users) {
              updateUserCountIndicator(syncData.online_users);
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
            // Re-sync Leaflet map size in case the container shifted while tab was hidden
            inv();
          } else {
            // Tab hidden (switch, screen-off, phone sleep) – signal server immediately
            jgSendLeaveBeacon();
          }
        });

        // Also fire on hard close / navigation away
        window.addEventListener('beforeunload', jgSendLeaveBeacon);
      }); // End of $(document).ready()

      /**
       * Fire-and-forget beacon to remove the current user from the online list immediately.
       * Uses sendBeacon so it survives tab/browser close and doesn't block navigation.
       * Only sent for logged-in users (guest heartbeats don't affect the admin indicator).
       */
      function jgSendLeaveBeacon() {
        if (!CFG || !CFG.ajax || !CFG.nonce || !(CFG.currentUserId > 0)) return;
        if (!navigator.sendBeacon) return;
        var fd = new FormData();
        fd.append('action',      'jg_user_leave');
        fd.append('_ajax_nonce', CFG.nonce);
        navigator.sendBeacon(CFG.ajax, fd);
      }

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
        var fabOverlayMouseDownOnBg = false;
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
          .on('mousedown', function(e) {
            fabOverlayMouseDownOnBg = (e.target === this);
          })
          .on('click', function(e) {
            if (e.target === this && fabOverlayMouseDownOnBg) {
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
        var fabCoordsMouseDownOnBg = false;
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
          .on('mousedown', function(e) {
            fabCoordsMouseDownOnBg = (e.target === this);
          })
          .on('click', function(e) {
            if (e.target === this && fabCoordsMouseDownOnBg) {
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
            placeholder: 'np. 50.9029, 15.7277',
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
          .on('blur', function() {
            $(this).css('border-color', '#e5e7eb');
          })
          .on('keydown', function(e) {
            if (e.key === 'Enter') {
              e.preventDefault();
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
          .text('Wpisz współrzędne (szerokość, długość) i naciśnij Enter lub kliknij przycisk');

        var submitBtn = $('<button>')
          .attr('type', 'button')
          .css({
            marginTop: '12px',
            width: '100%',
            padding: '12px',
            background: 'linear-gradient(135deg, #dc2626 0%, #b91c1c 100%)',
            color: '#fff',
            border: 'none',
            borderRadius: '8px',
            fontSize: '14px',
            fontWeight: '600',
            cursor: 'pointer',
            fontFamily: 'inherit'
          })
          .text('🎯 Przejdź do współrzędnych')
          .on('click', function() {
            var coords = input.val().trim();
            if (coords) {
              parseAndGoToCoords(coords);
              overlay.remove();
            }
          });

        inputBox.append(title, input, hint, submitBtn);
        overlay.append(inputBox);
        $('body').append(overlay);

        // Focus input after animation completes
        setTimeout(function() {
          input.focus();
        }, 350);
      }

      function geocodeAddress(address) {
        // Use Nominatim for geocoding (free, no API key needed)
        // Use map bounding box as viewbox to cover the entire visible area
        // viewbox format: left,top,right,bottom (min_lon,max_lat,max_lon,min_lat)
        var url = 'https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(address) + '&limit=1&viewbox=15.58,50.98,15.85,50.75&bounded=1&countrycodes=pl';

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
        // Fly to location with maximum zoom (19), offset for sidebar when active
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
          if (typeof window.openJoinModal === 'function') window.openJoinModal({trigger: 'action'});
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
              '<form id="add-form" class="jg-grid cols-2" novalidate>' +
              '<input type="hidden" name="lat" id="add-lat-input" value="' + latFixed + '">' +
              '<input type="hidden" name="lng" id="add-lng-input" value="' + lngFixed + '">' +
              '<input type="hidden" name="address" id="add-address-input" value="">' +
              limitsHtml +
              '<div class="cols-2" id="add-address-display" style="padding:8px 12px;background:#f3f4f6;border-left:3px solid #8d2324;border-radius:4px;font-size:13px;color:#374151;margin-bottom:8px"><strong>📍 Wczytywanie adresu...</strong></div>' +
              '<label style="display:block"><span style="display:block;margin-bottom:4px">Tytuł*</span><input name="title" required placeholder="Nazwa miejsca" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px"></label>' +
              '<label style="display:block"><span style="display:block;margin-bottom:4px">Typ*</span><select name="type" id="add-type-select" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' +
              '<option value="zgloszenie">Zgłoszenie</option>' +
              '<option value="ciekawostka">Ciekawostka</option>' +
              '<option value="miejsce" selected>Miejsce</option>' +
              '</select></label>' +
              '<label class="cols-2" id="add-category-field" style="display:block"><span style="display:block;margin-bottom:4px;color:#dc2626">Kategoria zgłoszenia*</span><select name="category" id="add-category-select" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' +
              generateCategoryOptions('') +
              '</select></label>' +
              '<label class="cols-2" id="add-place-category-field" style="display:none"><span>Kategoria miejsca (opcjonalna)</span> <select name="place_category" id="add-place-category-select" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' +
              generatePlaceCategoryOptions('') +
              '</select></label>' +
              '<label class="cols-2" id="add-curiosity-category-field" style="display:none"><span>Kategoria ciekawostki (opcjonalna)</span> <select name="curiosity_category" id="add-curiosity-category-select" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' +
              generateCuriosityCategoryOptions('') +
              '</select></label>' +
              '<div class="cols-2"><label style="display:block;margin-bottom:4px">Opis* (max 800 znaków)</label>' + buildRichEditorHtml('fab-rte', 800, '', 4) + '</div>' +
              '<div class="cols-2" id="fab-opening-hours-field" style="display:none"><label style="display:block;margin-bottom:6px;font-weight:500">Godziny otwarcia</label>' + buildOpeningHoursPickerHtml('fab', '') + '</div>' +
              '<div class="cols-2" id="fab-price-range-field" style="display:none"><label style="display:block;margin-bottom:6px;font-weight:500">💰 Zakres cenowy</label>' + buildPriceRangeSelectHtml('fab', '') + '</div>' +
              '<div class="cols-2" id="fab-serves-cuisine-field" style="display:none"><label style="display:block;margin-bottom:4px;font-weight:500">🥗 Rodzaj kuchni <input type="text" name="serves_cuisine" id="fab-serves-cuisine-input" placeholder="np. polska, włoska, pizza…" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></label></div>' +
              '<div class="cols-2"><label style="display:block;margin-bottom:4px">Tagi (max 5)</label>' + buildTagInputHtml('fab-tags') + '</div>' +
              '<div class="cols-2" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:12px;margin:4px 0">' +
              '<strong style="display:block;margin-bottom:10px;color:#0369a1">📋 Dane kontaktowe (opcjonalnie)</strong>' +
              '<label style="display:block;margin-bottom:8px">Telefon <input type="text" name="phone" id="add-phone-input" placeholder="np. 123 456 789" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></label>' +
              '<label style="display:block;margin-bottom:8px">Email kontaktowy <input type="email" name="contact_email" id="add-email-input" placeholder="np. kontakt@firma.pl" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></label>' +
              '<label style="display:block;margin-bottom:0">Strona internetowa <input type="text" name="website" id="add-website-input" placeholder="np. jeleniagora.pl" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-top:4px"></label>' +
              '</div>' +
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

            // Initialize opening hours picker for FAB add form
            var fabOhPicker = initOpeningHoursPicker('fab', modalAdd);

            // Reset scroll after init — contenteditable triggers browser auto-scroll
            setTimeout(function() {
              var modalC = qs('.jg-modal', modalAdd);
              if (modalC) modalC.scrollTop = 0;
              form.scrollTop = 0;
            }, 0);

            // On form submit, sync the rich editor content and tags
            form.addEventListener('submit', function() {
              if (fabRte) fabRte.syncContent();
              if (fabTagInput) fabTagInput.syncHidden();
              if (fabOhPicker) fabOhPicker.syncHidden();
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

            var fabOpeningHoursField = qs('#fab-opening-hours-field', modalAdd);
            var fabPriceRangeField = qs('#fab-price-range-field', modalAdd);
            var fabServesCuisineField = qs('#fab-serves-cuisine-field', modalAdd);

            function updateFabExtraFields() {
              var cat = placeCategorySelect ? placeCategorySelect.value : '';
              var selectedType = typeSelect ? typeSelect.value : '';
              if (fabPriceRangeField) fabPriceRangeField.style.display = (selectedType === 'miejsce' && isPriceRangeCategory(cat)) ? 'block' : 'none';
              if (fabServesCuisineField) fabServesCuisineField.style.display = (selectedType === 'miejsce' && isServesCuisineCategory(cat)) ? 'block' : 'none';
            }

            if (placeCategorySelect) placeCategorySelect.addEventListener('change', updateFabExtraFields);

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

                // Show opening hours only for miejsce
                if (fabOpeningHoursField) {
                  fabOpeningHoursField.style.display = selectedType === 'miejsce' ? 'block' : 'none';
                }

                updateFabExtraFields();
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

              // Validate all required fields
              function fabMarkErr(container, text) {
                container.style.background = '#fff0f0';
                container.style.borderRadius = '8px';
                container.style.boxShadow = '0 0 0 2px #b91c1c';
                container.style.padding = '8px';
                var existing = container.querySelector('.jg-val-err');
                if (!existing) {
                  var errDiv = document.createElement('div');
                  errDiv.className = 'jg-val-err';
                  errDiv.style.cssText = 'font-size:12px;color:#b91c1c;font-weight:600;margin-top:6px';
                  errDiv.textContent = '⚠ ' + text;
                  container.appendChild(errDiv);
                }
              }
              function fabClearErr(container) {
                container.style.background = '';
                container.style.borderRadius = '';
                container.style.boxShadow = '';
                container.style.padding = '';
                var errDiv = container.querySelector('.jg-val-err');
                if (errDiv) errDiv.remove();
              }

              var fabFirstErrContainer = null;

              var fabTitleInput = qs('input[name="title"]', form);
              var fabTitleContainer = fabTitleInput && fabTitleInput.closest('label');
              if (fabTitleInput && !fabTitleInput.value.trim()) {
                if (fabTitleContainer) { fabMarkErr(fabTitleContainer, 'Podaj nazwę miejsca.'); if (!fabFirstErrContainer) fabFirstErrContainer = fabTitleContainer; }
              } else if (fabTitleContainer) { fabClearErr(fabTitleContainer); }

              var fabContentVal = qs('#fab-rte-hidden', modalAdd);
              var fabRteWrap = qs('#fab-rte-wrap', modalAdd);
              var fabRteContainer = fabRteWrap && fabRteWrap.parentElement;
              if (fabContentVal && !fabContentVal.value.replace(/<\/?[^>]+(>|$)/g, '').trim()) {
                if (fabRteContainer) { fabMarkErr(fabRteContainer, 'Dodaj opis miejsca.'); if (!fabFirstErrContainer) fabFirstErrContainer = fabRteContainer; }
              } else if (fabRteContainer) { fabClearErr(fabRteContainer); }

              var fabTypeEl = qs('#add-type-select', form);
              var fabCatField = qs('#add-category-field', modalAdd);
              var fabCatSelect = qs('#add-category-select', form);
              if (fabTypeEl && fabTypeEl.value === 'zgloszenie' && fabCatSelect && !fabCatSelect.value) {
                if (fabCatField) { fabMarkErr(fabCatField, 'Wybierz kategorię zgłoszenia.'); if (!fabFirstErrContainer) fabFirstErrContainer = fabCatField; }
              } else if (fabCatField) { fabClearErr(fabCatField); }

              if (fabFirstErrContainer) {
                msg.textContent = '';
                fabFirstErrContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
              }

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

              function _doFabFetch() {
                var submitBtn = qs('button[type="submit"]', form);
                if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Wysyłanie…'; }
                msg.textContent = '';
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
                      var duplicatePointId = parseInt(j.data.duplicate_point_id, 10);
                      msg.innerHTML = (j.data.message || 'Błąd') + ' <br><button style="margin-top:8px;padding:6px 12px;background:#8d2324;color:#fff;border:none;border-radius:4px;cursor:pointer" onclick="' +
                        'document.getElementById(\'jg-map-modal-add\').style.display=\'none\';' +
                        'window.location.hash=\'#point-' + duplicatePointId + '\';' +
                        '">Zobacz istniejące zgłoszenie</button>';
                      msg.style.color = '#b91c1c';
                      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Wyślij do moderacji'; }
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
                  refreshChallengeProgress();

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
                  if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Wyślij do moderacji'; }
                });
              }

              // Duplicate check: only for miejsca and ciekawostki (zgłoszenia have server-side check)
              var _fabType = fd.get('type') || '';
              var _fabNewLat = parseFloat(fd.get('lat'));
              var _fabNewLng = parseFloat(fd.get('lng'));
              if (_fabType !== 'zgloszenie' && !isNaN(_fabNewLat) && !isNaN(_fabNewLng)) {
                var _fabDups = jgFindDuplicates(fd.get('title') || '', fd.get('content') || '', _fabNewLat, _fabNewLng, _fabType);
                if (_fabDups.length) {
                  msg.textContent = '';
                  jgShowDuplicateWarning(_fabDups, modalAdd, _doFabFetch);
                  return;
                }
              }
              _doFabFetch();
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
      // ADMIN/MOD USER COUNT INDICATOR
      // =========================================================

      var userCountIndicator = null;

      /**
       * Create the golden circle indicator showing total registered users.
       * Visible only to admins/moderators. Positioned centered at the bottom of the map
       * (halfway between the onboarding FAB on the left and the add-places FAB on the right).
       *
       * - Circle: solid gold fill, white bold number = total registered users
       * - When someone else is currently logged in:
       *     • pulsating gold border around the circle
       *     • red FB-style badge (top-right) with count of other active sessions
       */
      function createUserCountIndicator() {
        if (!CFG.isAdmin) return;

        // Inject keyframes once
        if (!document.getElementById('jg-uci-style')) {
          $('<style>').attr('id', 'jg-uci-style').text(
            '@keyframes jg-uci-pulse {' +
              '0%   { outline-color: rgba(251,191,36,0.9); outline-offset: 0px; }' +
              '50%  { outline-color: rgba(251,191,36,0.3); outline-offset: 4px; }' +
              '100% { outline-color: rgba(251,191,36,0.9); outline-offset: 0px; }' +
            '}'
          ).appendTo('head');
        }

        // Wrapper – horizontally centred between the two FABs.
        // _jgDwGetFabCenterX() accounts for the sidebar shifting the right FAB.
        var _uciInitLeft = _jgDwGetFabCenterX ? (_jgDwGetFabCenterX() + 'px') : '50%';
        var _uciInitTransform = 'translateX(-50%)';
        userCountIndicator = $('<div>')
          .attr('id', 'jg-user-count-indicator')
          .css({
            position: 'absolute',
            bottom: '30px',
            left: _uciInitLeft,
            transform: _uciInitTransform,
            zIndex: 9997,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center'
          })
          .on('click',     function(e) { e.stopPropagation(); })
          .on('dblclick',  function(e) { e.stopPropagation(); e.preventDefault(); })
          .on('wheel',     function(e) { e.stopPropagation(); e.preventDefault(); })
          .on('mousedown', function(e) { e.stopPropagation(); });

        // Gold circle
        var circle = $('<div>')
          .attr('id', 'jg-uci-circle')
          .css({
            width: '60px',
            height: '60px',
            borderRadius: '50%',
            background: '#f59e0b',
            boxShadow: '0 4px 16px rgba(0,0,0,0.25)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            cursor: CFG.usersUrl ? 'pointer' : 'default',
            userSelect: 'none',
            position: 'relative',
            outline: '3px solid transparent',
            outlineOffset: '0px',
            transition: 'outline-color 0.3s ease, outline-offset 0.3s ease'
          });

        if (CFG.usersUrl) {
          circle.on('click', function(e) {
            e.stopPropagation();
            window.location.href = CFG.usersUrl;
          });
        }

        // White number label
        var countLabel = $('<span>')
          .attr('id', 'jg-uci-count')
          .css({
            fontSize: '20px',
            fontWeight: '700',
            color: '#fff',
            lineHeight: '1',
            letterSpacing: '-0.5px'
          })
          .text('…');

        // FB-style red badge – top-right corner, hidden by default
        var badge = $('<div>')
          .attr('id', 'jg-uci-badge')
          .css({
            position: 'absolute',
            top: '-5px',
            right: '-5px',
            minWidth: '20px',
            height: '20px',
            borderRadius: '10px',
            background: '#e11d48',
            color: '#fff',
            fontSize: '11px',
            fontWeight: '700',
            display: 'none',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '0 5px',
            border: '2px solid #fff',
            boxShadow: '0 1px 4px rgba(0,0,0,0.35)',
            lineHeight: '1'
          });

        circle.append(countLabel, badge);
        userCountIndicator.append(circle);
        $(elMap).append(userCountIndicator);
      }

      /**
       * Update the user count indicator with fresh data from heartbeat.
       * @param {Object} data  – { registered: int, others: int }
       *   registered – total registered users (shown in circle)
       *   others     – other currently active sessions, excluding the viewer (badge/border)
       */
      function updateUserCountIndicator(data) {
        if (!userCountIndicator || !data) return;

        var registered = data.registered != null ? data.registered : '?';
        var others     = data.others || 0;

        // Update the number displayed in the circle
        $('#jg-uci-count').text(registered);

        var circle = $('#jg-uci-circle');
        var badge  = $('#jg-uci-badge');

        if (others > 0) {
          // Pulsating gold outline
          circle.css('animation', 'jg-uci-pulse 1.6s ease-in-out infinite');

          // Show red badge with count of other active sessions
          badge.text(others).css('display', 'flex');
        } else {
          circle.css('animation', 'none');
          badge.hide();
        }
      }

      // Initialise indicator for admins/mods (desktop only – hidden on mobile via CSS)
      if (CFG.isAdmin) {
        createUserCountIndicator();
      }

      // =========================================================
      // CHALLENGE WIDGETS  (up to 4 simultaneous)
      // =========================================================

      var _mobileSelChId  = null;  // ID of challenge currently shown in mobile pill
      var _mobileChList   = [];    // non-dismissed active challenges for mobile

      // Rebuild mobile pill from current _mobileChList / _mobileSelChId.
      // Safe to call repeatedly; replaces innerHTML each time.
      function _buildMobilePill() {
        var mw = document.getElementById('jg-challenge-widget-mobile');
        if (!mw || !_mobileChList.length) return;

        // Validate selection
        var selIdx = -1;
        for (var _i = 0; _i < _mobileChList.length; _i++) {
          if (_mobileChList[_i].id === _mobileSelChId) { selIdx = _i; break; }
        }
        if (selIdx === -1) { selIdx = 0; _mobileSelChId = _mobileChList[0].id; }

        var selCh   = _mobileChList[selIdx];
        var others  = _mobileChList.filter(function(c) { return c.id !== selCh.id; });
        var radius  = 28;
        var circ    = Math.round(2 * Math.PI * radius * 10) / 10;

        var sp   = Math.min(selCh.progress, selCh.target_count);
        var spct = selCh.target_count > 0 ? Math.round((sp / selCh.target_count) * 100) : 0;
        var sd   = spct >= 100;
        var isGuest = !!selCh.user_is_guest;

        var selTitleHtml = $('<div>').text(selCh.title).html();
        var selDescRaw   = selCh.description ? selCh.description.slice(0, 60) + (selCh.description.length > 60 ? '\u2026' : '') : '';
        var selDescHtml  = selDescRaw ? $('<div>').text(selDescRaw).html() : '';

        // Dropdown items (other challenges)
        var dropHtml = '';
        if (others.length) {
          dropHtml = '<div class="jg-cw-m-dropdown"><div class="jg-cw-m-dropdown-title">Inne wyzwania</div>';
          for (var _oi = 0; _oi < others.length; _oi++) {
            var _oc = others[_oi];
            var _op = Math.min(_oc.progress, _oc.target_count);
            var _opct = _oc.target_count > 0 ? Math.round((_op / _oc.target_count) * 100) : 0;
            var _od = _opct >= 100;
            dropHtml += '<div class="jg-cw-m-dropdown-item' + (_od ? ' jg-cw-di-done' : '') + '" data-ch-id="' + _oc.id + '">' +
              '<span class="jg-cw-m-di-icon">' + (_od ? '\u2705' : '\ud83c\udfc6') + '</span>' +
              '<div class="jg-cw-m-di-body">' +
                '<div class="jg-cw-m-di-title">' + $('<div>').text(_oc.title).html() + '</div>' +
                '<div class="jg-cw-m-di-progress' + (_od ? ' jg-cw-done-text' : '') + '">' +
                  (_oc.user_is_guest ? '\ud83d\udd12 Zaloguj si\u0119' : (_od ? '\u2713 Uko\u0144czono' : _op + '/' + _oc.target_count)) +
                '</div>' +
              '</div></div>';
          }
          dropHtml += '</div>';
        }

        mw.className = sd ? 'jg-cw-done' : (isGuest ? 'jg-cw-guest' : '');
        mw.innerHTML =
          dropHtml +
          '<div class="jg-cw-m-bar">' +
            '<span class="jg-cw-m-icon">' + (isGuest ? '\ud83d\udd12' : (sd ? '\u2705' : '\ud83c\udfc6')) + '</span>' +
            '<div class="jg-cw-m-body">' +
              '<div class="jg-cw-m-title">' + selTitleHtml + '</div>' +
              (selDescHtml ? '<div class="jg-cw-m-desc">' + selDescHtml + '</div>' : '') +
              (isGuest
                ? '<div class="jg-cw-m-guest-hint">Zaloguj si\u0119, by \u015bledzi\u0107 post\u0119p \u2192</div>'
                : '<div class="jg-cw-m-progress-track"><div class="jg-cw-m-progress-fill" style="width:' + spct + '%"></div></div>') +
            '</div>' +
            (isGuest
              ? ''
              : '<div class="jg-cw-m-count">' + (sd ? '\u2713' : sp + '/' + selCh.target_count) + '</div>') +
            (others.length ? '<button class="jg-cw-m-expand-btn" title="Inne wyzwania">&#9650;</button>' : '') +
          '</div>' +
          (sd ? '<button class="jg-cw-close-btn" title="Zamknij">\xd7</button>' : '');

        // Block all Leaflet-triggering events on the bar itself — this is the
        // element users actually tap. The container has pointer-events:none so
        // disableClickPropagation on the container is unreliable; putting it on
        // the bar (which has pointer-events:auto) guarantees interception.
        var barEl = mw.querySelector('.jg-cw-m-bar');
        if (barEl) {
          L.DomEvent.disableClickPropagation(barEl);
          if (isGuest) {
            barEl.onclick = function(e) {
              e.stopPropagation();
              if (typeof window.openJoinModal === 'function') window.openJoinModal({trigger: 'action'});
            };
          }
        }

        // Bind expand button
        var expBtn  = mw.querySelector('.jg-cw-m-expand-btn');
        var dropdown = mw.querySelector('.jg-cw-m-dropdown');
        if (expBtn && dropdown) {
          expBtn.onclick = function(e) {
            e.stopPropagation();
            var open = dropdown.classList.contains('jg-cw-open');
            dropdown.classList.toggle('jg-cw-open', !open);
            expBtn.classList.toggle('jg-cw-open', !open);
          };
        }

        // Bind dropdown item clicks
        var items = mw.querySelectorAll('.jg-cw-m-dropdown-item');
        for (var _ii = 0; _ii < items.length; _ii++) {
          (function(item) {
            item.onclick = function(e) {
              e.stopPropagation();
              _mobileSelChId = parseInt(item.getAttribute('data-ch-id'), 10);
              _buildMobilePill();
            };
          })(items[_ii]);
        }

        // Bind dismiss button (shown when done)
        var closeBtn = mw.querySelector('.jg-cw-close-btn');
        if (closeBtn) {
          (function(dismissId) {
            closeBtn.onclick = function(e) {
              e.stopPropagation();
              try { localStorage.setItem('jg_ch_dismissed_' + dismissId, '1'); } catch(e) {}
              _mobileChList = _mobileChList.filter(function(c) { return c.id !== dismissId; });
              if (_mobileChList.length) {
                _mobileSelChId = _mobileChList[0].id;
                _buildMobilePill();
              } else {
                mw.style.setProperty('display', 'none', 'important');
              }
            };
          })(selCh.id);
        }

        // Silently mark done on page load (suppress completion modal)
        if (sd) { try { localStorage.setItem('jg_ch_done_' + selCh.id, '1'); } catch(e) {} }
      }

      (function() {
        var challenges = CFG.activeChallenges;
        if (!challenges || !challenges.length) return;

        var radius = 28;
        var circ   = Math.round(2 * Math.PI * radius * 10) / 10;

        function _isDismissed(id) {
          try { return !!localStorage.getItem('jg_ch_dismissed_' + id); } catch(e) { return false; }
        }
        function _chPct(ch) {
          var p = Math.min(ch.progress, ch.target_count);
          var pct = ch.target_count > 0 ? Math.round((p / ch.target_count) * 100) : 0;
          return { prog: p, pct: pct, done: pct >= 100 };
        }

        // ── DESKTOP: one widget per challenge, stacked vertically ──────────
        if (window.innerWidth > 768) {
          var deskWidgets = [];

          for (var di = 0; di < challenges.length; di++) {
            var ch = challenges[di];
            var v  = _chPct(ch);
            if (v.done && _isDismissed(ch.id)) continue;

            var offset    = v.done ? 0 : (ch.target_count > 0 ? Math.round((1 - v.prog / ch.target_count) * circ * 10) / 10 : circ);
            var msLeft    = new Date(ch.end_date.replace(' ', 'T')) - new Date();
            var daysLeft  = Math.max(0, Math.ceil(msLeft / 86400000));
            var timeStr   = daysLeft > 1 ? 'jeszcze ' + daysLeft + ' dni' : (daysLeft === 1 ? 'ostatni dzie\u0144!' : 'ko\u0144czy si\u0119 dzisiaj!');
            var titleHtml = $('<div>').text(ch.title).html();
            var descRaw   = ch.description ? ch.description.slice(0, 100) + (ch.description.length > 100 ? '\u2026' : '') : '';
            var descHtml  = descRaw ? $('<div>').text(descRaw).html() : '';

            var dw = document.createElement('div');
            dw.id  = 'jg-cw-desk-' + ch.id;
            dw.setAttribute('data-ch-id', ch.id);
            if (v.done) dw.classList.add('jg-cw-done');

            if (ch.user_is_guest) {
              dw.classList.add('jg-cw-guest');
              dw.innerHTML =
                '<div class="jg-cw-ctrl-inner">' +
                  '<div class="jg-cw-ctrl-lock">\ud83d\udd12</div>' +
                  '<div class="jg-cw-ctrl-body">' +
                    '<div class="jg-cw-ctrl-label">\ud83c\udfc6 Wyzwanie</div>' +
                    '<div class="jg-cw-ctrl-title">' + titleHtml + '</div>' +
                    (descHtml ? '<div class="jg-cw-ctrl-desc">' + descHtml + '</div>' : '') +
                    '<div class="jg-cw-ctrl-meta jg-cw-guest-hint">Zaloguj si\u0119, by \u015bledzi\u0107 post\u0119p \u2192</div>' +
                  '</div>' +
                '</div>';
              dw.onclick = function(e) { e.stopPropagation(); if (typeof window.openJoinModal === 'function') window.openJoinModal({trigger: 'action'}); };
            } else {
              dw.innerHTML =
                (v.done ? '<button class="jg-cw-close-btn" title="Zamknij">\xd7</button>' : '') +
                '<div class="jg-cw-ctrl-inner">' +
                  '<svg class="jg-cw-ctrl-svg" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">' +
                    '<circle cx="32" cy="32" r="' + radius + '" class="jg-cw-ctrl-track"/>' +
                    '<circle cx="32" cy="32" r="' + radius + '" class="jg-cw-ctrl-fill"' +
                      ' stroke-dasharray="' + circ + '" stroke-dashoffset="' + offset + '"/>' +
                    '<text x="32" y="30" class="jg-cw-ctrl-pct">' + (v.done ? '\u2713' : v.pct + '%') + '</text>' +
                    '<text x="32" y="42" class="jg-cw-ctrl-ratio">' + (v.done ? 'Gotowe!' : v.prog + '/' + ch.target_count) + '</text>' +
                  '</svg>' +
                  '<div class="jg-cw-ctrl-body">' +
                    '<div class="jg-cw-ctrl-label">' + (v.done ? '\u2705 Uko\u0144czono!' : '\ud83c\udfc6 Wyzwanie') + '</div>' +
                    '<div class="jg-cw-ctrl-title">' + titleHtml + '</div>' +
                    (descHtml ? '<div class="jg-cw-ctrl-desc">' + descHtml + '</div>' : '') +
                    '<div class="jg-cw-ctrl-meta">' + (v.done ? 'Osi\u0105gni\u0119cie odblokowane' : timeStr + (ch.xp_reward ? ' \xb7 +' + ch.xp_reward + ' XP' : '')) + '</div>' +
                  '</div>' +
                '</div>';

              if (v.done) {
                try { localStorage.setItem('jg_ch_done_' + ch.id, '1'); } catch(e) {}
                (function(widget, chId) {
                  var cb = widget.querySelector('.jg-cw-close-btn');
                  if (cb) cb.onclick = function(e) {
                    e.stopPropagation();
                    try { localStorage.setItem('jg_ch_dismissed_' + chId, '1'); } catch(e) {}
                    widget.style.setProperty('display', 'none', 'important');
                  };
                })(dw, ch.id);
              }
            }

            elMap.appendChild(dw);
            L.DomEvent.disableClickPropagation(dw);
            deskWidgets.push(dw);
          }

          // Stack widgets vertically next to zoom controls
          setTimeout(function() {
            var zoomCtrl = elMap.querySelector('.leaflet-control-zoom');
            if (!zoomCtrl || !deskWidgets.length) return;
            var mapRect  = elMap.getBoundingClientRect();
            var zoomRect = zoomCtrl.getBoundingClientRect();
            if (!mapRect.height || !zoomRect.height) return;
            var curTop  = Math.round(zoomRect.top  - mapRect.top);
            var baseLeft = Math.round(zoomRect.right - mapRect.left + 12);
            for (var wi = 0; wi < deskWidgets.length; wi++) {
              deskWidgets[wi].style.setProperty('top',  curTop + 'px', 'important');
              deskWidgets[wi].style.setProperty('left', baseLeft + 'px', 'important');
              curTop += deskWidgets[wi].getBoundingClientRect().height + 8;
            }
          }, 150);
        }

        // ── MOBILE: pill for selected challenge + expand dropdown ──────────
        if (window.innerWidth <= 768) {
          _mobileChList = challenges.filter(function(c) {
            var v = _chPct(c);
            return !(v.done && _isDismissed(c.id));
          });
          if (!_mobileChList.length) return;
          _mobileSelChId = _mobileChList[0].id;

          var mobilePill = document.createElement('div');
          mobilePill.id  = 'jg-challenge-widget-mobile';
          elMap.appendChild(mobilePill);
          L.DomEvent.disableClickPropagation(mobilePill);
          _buildMobilePill();
        }
      }());

      // ── Challenge completion modal ────────────────────────────────────────
      function showChallengeCompleteModal(ch) {
        var modalAlert = document.getElementById('jg-modal-alert');
        if (!modalAlert) return;

        var rarityColors = { common:'#d1d5db', uncommon:'#10b981', rare:'#3b82f6', epic:'#8b5cf6', legendary:'#f59e0b' };
        var rarityLabels = { common:'Zwykłe', uncommon:'Niepospolite', rare:'Rzadkie', epic:'Epickie', legendary:'Legendarne' };

        var achHtml = '';
        if (ch.ach_name) {
          var achColor = rarityColors[ch.ach_rarity] || '#f59e0b';
          var achLabel = rarityLabels[ch.ach_rarity] || '';
          achHtml =
            '<div style="margin:16px 0 4px;padding:14px 12px;background:rgba(0,0,0,0.06);border-radius:10px;border:1.5px solid ' + achColor + ';box-shadow:0 0 14px ' + achColor + '55">' +
              '<div style="font-size:11px;font-weight:700;letter-spacing:1px;color:' + achColor + ';margin-bottom:10px;text-transform:uppercase">🏅 Odblokowane osiągnięcie</div>' +
              '<div style="display:flex;align-items:center;gap:12px">' +
                '<div style="width:52px;height:52px;border-radius:50%;border:2.5px solid ' + achColor + ';display:flex;align-items:center;justify-content:center;font-size:26px;flex-shrink:0;box-shadow:0 0 12px ' + achColor + '">' + esc(ch.ach_icon || '🏆') + '</div>' +
                '<div style="text-align:left">' +
                  '<div style="font-size:15px;font-weight:800;color:#111827">' + esc(ch.ach_name) + '</div>' +
                  (achLabel ? '<div style="font-size:11px;font-weight:700;color:' + achColor + ';text-transform:uppercase;letter-spacing:.5px;margin-top:2px">' + achLabel + '</div>' : '') +
                '</div>' +
              '</div>' +
            '</div>';
        }

        var content = modalAlert.querySelector('.jg-modal-message-content');
        var buttons  = modalAlert.querySelector('.jg-modal-message-buttons');
        content.innerHTML =
          '<div style="text-align:center;padding:8px 0">' +
            '<div style="font-size:52px;margin-bottom:6px">🎉</div>' +
            '<div style="font-size:11px;font-weight:700;letter-spacing:1.5px;color:#6b7280;text-transform:uppercase;margin-bottom:6px">Gratulacje!</div>' +
            '<div style="font-size:20px;font-weight:800;color:#111827;margin-bottom:8px">Wyzwanie ukończone!</div>' +
            '<div style="font-size:13px;color:#6b7280">' + esc(ch.title) + '</div>' +
            (ch.xp_reward ? '<div style="font-size:14px;color:#d97706;font-weight:700;margin-top:6px">+' + ch.xp_reward + ' XP</div>' : '') +
            achHtml +
          '</div>';
        buttons.innerHTML = '<button id="jg-ch-done-ok" style="padding:10px 32px;background:#10b981;border:none;color:#fff;border-radius:8px;font-weight:700;cursor:pointer;font-size:15px">Super! 🎉</button>';

        modalAlert.style.display = 'flex';
        lockBodyScroll();

        function closeIt() { modalAlert.style.display = 'none'; unlockBodyScroll(); }
        document.getElementById('jg-ch-done-ok').onclick = closeIt;
        modalAlert.onclick = function(e) { if (e.target === modalAlert) closeIt(); };
      }

      // ── Live challenge progress refresh ──────────────────────────────────
      // Called after any relevant AJAX action succeeds.
      function refreshChallengeProgress() {
        if (!CFG.activeChallenges || !CFG.activeChallenges.length) return;
        if (!CFG.isLoggedIn) return;
        var fd = new FormData();
        fd.append('action', 'jg_get_active_challenge');
        fd.append('_ajax_nonce', CFG.nonce);
        fetch(CFG.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function(r) { return r.json(); })
          .then(function(res) {
            if (!res || !res.success || !Array.isArray(res.data)) return;
            var challenges = res.data;
            var radius = 28;
            var circ   = Math.round(2 * Math.PI * radius * 10) / 10;
            var justCompleted = []; // challenge objects newly completed this tick

            for (var i = 0; i < challenges.length; i++) {
              var ch   = challenges[i];
              var prog = Math.min(ch.progress, ch.target_count);
              var pct  = ch.target_count > 0 ? Math.round((prog / ch.target_count) * 100) : 0;
              var done = pct >= 100;
              var offset = done ? 0 : (ch.target_count > 0 ? Math.round((1 - prog / ch.target_count) * circ * 10) / 10 : circ);

              // Update desktop widget for this challenge
              var dw = document.getElementById('jg-cw-desk-' + ch.id);
              if (dw) {
                dw.classList.toggle('jg-cw-done', done);
                var pctEl   = dw.querySelector('.jg-cw-ctrl-pct');
                var ratioEl = dw.querySelector('.jg-cw-ctrl-ratio');
                var fillEl  = dw.querySelector('.jg-cw-ctrl-fill');
                var labelEl = dw.querySelector('.jg-cw-ctrl-label');
                var metaEl  = dw.querySelector('.jg-cw-ctrl-meta');
                if (pctEl)   pctEl.textContent   = done ? '\u2713'       : pct + '%';
                if (ratioEl) ratioEl.textContent = done ? 'Gotowe!'      : prog + '/' + ch.target_count;
                if (fillEl)  fillEl.setAttribute('stroke-dashoffset', String(offset));
                if (labelEl) labelEl.textContent = done ? '\u2705 Uko\u0144czono!' : '\ud83c\udfc6 Wyzwanie';
                if (metaEl && done) metaEl.textContent = 'Osi\u0105gni\u0119cie odblokowane';
                // Inject dismiss button when newly done
                if (done && !dw.querySelector('.jg-cw-close-btn')) {
                  var _dCb = document.createElement('button');
                  _dCb.className = 'jg-cw-close-btn';
                  _dCb.title = 'Zamknij';
                  _dCb.textContent = '\xd7';
                  (function(w, id) {
                    _dCb.onclick = function(e) {
                      e.stopPropagation();
                      try { localStorage.setItem('jg_ch_dismissed_' + id, '1'); } catch(e) {}
                      w.style.setProperty('display', 'none', 'important');
                    };
                  })(dw, ch.id);
                  dw.insertBefore(_dCb, dw.firstChild);
                }
              }

              // Track first-time completions for modal
              if (done) {
                try {
                  if (!localStorage.getItem('jg_ch_done_' + ch.id)) {
                    localStorage.setItem('jg_ch_done_' + ch.id, '1');
                    justCompleted.push(ch);
                  }
                } catch(e) {}
              }
            }

            // Update _mobileChList with fresh data
            _mobileChList = _mobileChList.map(function(old) {
              for (var j = 0; j < challenges.length; j++) {
                if (challenges[j].id === old.id) return challenges[j];
              }
              return old;
            });

            // Auto-select newly completed non-selected mobile challenges
            var newDoneOther = justCompleted.filter(function(c) { return c.id !== _mobileSelChId; });
            if (newDoneOther.length === 1) {
              _mobileSelChId = newDoneOther[0].id;
              _buildMobilePill();
            } else if (newDoneOther.length > 1) {
              _buildMobilePill();
              // Open dropdown so user sees what completed
              var mw = document.getElementById('jg-challenge-widget-mobile');
              if (mw) {
                var dd = mw.querySelector('.jg-cw-m-dropdown');
                var eb = mw.querySelector('.jg-cw-m-expand-btn');
                if (dd) dd.classList.add('jg-cw-open');
                if (eb) eb.classList.add('jg-cw-open');
              }
            } else {
              _buildMobilePill();
            }

            // Show completion modals (queue sequentially to avoid overlap)
            (function showNext(queue) {
              if (!queue.length) return;
              showChallengeCompleteModal(queue[0]);
              var ok = document.getElementById('jg-ch-done-ok');
              if (ok) {
                var orig = ok.onclick;
                ok.onclick = function() { if (orig) orig(); showNext(queue.slice(1)); };
              }
            })(justCompleted);
          })
          .catch(function() {});
      }

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

        modalAlert.style.display = 'flex';
        lockBodyScroll();

        document.getElementById('jg-levelup-ok').onclick = function() {
          modalAlert.style.display = 'none';
          unlockBodyScroll();
          if (onClose) onClose();
        };

        modalAlert.onclick = function(e) {
          if (e.target === modalAlert) {
            modalAlert.style.display = 'none';
            unlockBodyScroll();
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

        modalAlert.style.display = 'flex';
        lockBodyScroll();

        document.getElementById('jg-ach-ok').onclick = function() {
          modalAlert.style.display = 'none';
          unlockBodyScroll();
          if (onClose) onClose();
        };

        modalAlert.onclick = function(e) {
          if (e.target === modalAlert) {
            modalAlert.style.display = 'none';
            unlockBodyScroll();
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
      window.openUserActivityModal = openUserActivityModal;

      // Export function to open a point modal by ID (looks up full data from ALL)
      window.jgOpenPointById = function(id) {
        var p = null;
        for (var i = 0; i < ALL.length; i++) {
          if (+ALL[i].id === +id) { p = ALL[i]; break; }
        }
        if (p) openDetails(p);
      };

      // Export zoom-to-point function for sidebar use
      // Zooms the map to given coordinates and shows a pulsing marker animation
      // Optional callback is fired after the animation completes
      window.jgZoomToPoint = function(lat, lng, callback) {
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
                if (typeof callback === 'function') callback();
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
