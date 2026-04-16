/**
 * JG Interactive Map — Help FAB & Tooltips
 *
 * Provides:
 *  1. Help FAB button (?) with instruction panel
 *  2. Mobile collapsible filters
 *  3. Lightweight JS tooltips on all key UI elements
 */
(function () {
  'use strict';

  // ──────────────────────────────────────────
  //  SHIELD (prevent Leaflet from consuming events)
  // ──────────────────────────────────────────
  function shieldFromMap(el) {
    if (window.L && L.DomEvent) {
      L.DomEvent.disableClickPropagation(el);
      L.DomEvent.disableScrollPropagation(el);
    }
    el.addEventListener('click',      function (e) { e.stopPropagation(); });
    el.addEventListener('touchstart', function (e) { e.stopPropagation(); }, { passive: true });
  }

  // ──────────────────────────────────────────
  //  HELP FAB
  // ──────────────────────────────────────────
  function createHelpFAB(mapEl) {
    var wrap = document.createElement('div');
    wrap.id = 'jg-help-fab';
    wrap.style.cssText = 'position:absolute;bottom:30px;left:30px;z-index:9998;';

    shieldFromMap(wrap);
    wrap.addEventListener('wheel', function (e) {
      e.stopPropagation(); e.preventDefault();
    }, { passive: false });

    var btn = document.createElement('button');
    btn.id   = 'jg-help-btn';
    btn.type = 'button';
    btn.style.cssText = [
      'width:60px;height:60px;border-radius:50%',
      'background:linear-gradient(135deg,#8d2324 0%,#a02829 100%)',
      'border:none;cursor:pointer;display:flex;align-items:center;justify-content:center',
      'box-shadow:0 4px 12px rgba(141,35,36,0.4);transition:all 0.3s ease;outline:none'
    ].join(';');
    btn.innerHTML = '<span style="color:#fff;font-size:26px;font-weight:700;'
      + 'font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;line-height:1">?</span>';

    btn.addEventListener('mouseenter', function () {
      btn.style.transform = 'scale(1.1)';
      btn.style.boxShadow = '0 6px 16px rgba(141,35,36,0.5)';
    });
    btn.addEventListener('mouseleave', function () {
      btn.style.transform = 'scale(1)';
      btn.style.boxShadow = '0 4px 12px rgba(141,35,36,0.4)';
    });

    wrap.appendChild(btn);
    mapEl.appendChild(wrap);
    return btn;
  }

  function initHelpPanel(mapEl) {
    var panel  = document.getElementById('jg-help-panel');
    if (panel) { mapEl.appendChild(panel); shieldFromMap(panel); }

    var helpBtn  = createHelpFAB(mapEl);
    var closeBtn = document.getElementById('jg-help-panel-close');

    if (!helpBtn || !panel) return;

    helpBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      panel.style.display = (panel.style.display === 'flex') ? 'none' : 'flex';
    });

    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        panel.style.display = 'none';
      });
    }

    // Close when clicking outside panel + FAB
    document.addEventListener('click', function (e) {
      if (panel.style.display === 'none') return;
      var fab = document.getElementById('jg-help-fab');
      if (panel.contains(e.target)) return;
      if (fab && fab.contains(e.target)) return;
      panel.style.display = 'none';
    });
  }

  // ──────────────────────────────────────────
  //  MOBILE COLLAPSIBLE FILTERS
  // ──────────────────────────────────────────
  function initMobileFilters() {
    if (window.innerWidth > 768) return;

    var wrapper = document.getElementById('jg-map-filters-wrapper');
    if (!wrapper || wrapper.querySelector('.jg-mobile-filters-toggle')) return;

    var toggle = document.createElement('button');
    toggle.className = 'jg-mobile-filters-toggle';
    toggle.type = 'button';
    toggle.innerHTML = '<span>Filtry i wyszukiwanie</span>'
      + '<span class="jg-toggle-arrow">&#x25BC;</span>';

    wrapper.classList.add('collapsed');

    toggle.addEventListener('click', function () {
      var isCol = wrapper.classList.contains('collapsed');
      wrapper.classList.toggle('collapsed', !isCol);
      toggle.classList.toggle('expanded', isCol);
    });

    wrapper.insertBefore(toggle, wrapper.firstChild);
  }

  // ──────────────────────────────────────────
  //  TOOLTIPS
  //  Lightweight single-element tooltip that
  //  follows the hovered element's top edge.
  // ──────────────────────────────────────────

  var _ttEl    = null;
  var _ttTimer = null;

  function getTooltipEl() {
    if (!_ttEl) {
      _ttEl = document.createElement('div');
      _ttEl.id = 'jg-tt';
      _ttEl.style.cssText = [
        'position:fixed;z-index:999999;pointer-events:none',
        'background:#1e2329;color:#f0f1f3',
        'font-size:12.5px;line-height:1.4',
        'padding:5px 10px;border-radius:6px',
        'box-shadow:0 2px 8px rgba(0,0,0,0.28)',
        'font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial',
        'max-width:220px;white-space:normal;text-align:center',
        'opacity:0;transition:opacity 0.15s'
      ].join(';');
      document.body.appendChild(_ttEl);
    }
    return _ttEl;
  }

  function positionTooltip(el) {
    var tt = getTooltipEl();
    var r  = el.getBoundingClientRect();
    var vw = window.innerWidth;
    var tw = tt.offsetWidth;
    var th = tt.offsetHeight;
    var gap = 8;

    var left = r.left + r.width / 2 - tw / 2;
    var top  = r.top - th - gap;

    // Flip below if not enough room above
    if (top < 4) top = r.bottom + gap;

    // Clamp horizontally
    if (left < 6)          left = 6;
    if (left + tw > vw - 6) left = vw - tw - 6;

    tt.style.left = Math.round(left) + 'px';
    tt.style.top  = Math.round(top)  + 'px';
  }

  function showTooltip(text, el) {
    var tt = getTooltipEl();
    tt.textContent = text;
    tt.style.opacity = '0';
    tt.style.display = 'block';
    positionTooltip(el);

    if (_ttTimer) clearTimeout(_ttTimer);
    _ttTimer = setTimeout(function () { tt.style.opacity = '1'; }, 20);
  }

  function hideTooltip() {
    if (_ttTimer) { clearTimeout(_ttTimer); _ttTimer = null; }
    var tt = getTooltipEl();
    tt.style.opacity = '0';
  }

  // Spec: { sel, text }
  var TOOLTIPS = [
    // Help FAB
    { sel: '#jg-help-btn',
      text: 'Pomoc — instrukcja korzystania z mapy' },
    // Add-point FAB
    { sel: '#jg-fab-button',
      text: 'Dodaj nowy punkt na mapę' },
    // Search
    { sel: '#jg-search-input',
      text: 'Szukaj po nazwie, adresie lub tagu' },
    // Desktop sidebar search
    { sel: '#jg-sidebar-search-input',
      text: 'Szukaj punktów na mapie' },
    // Filters wrapper
    { sel: '#jg-map-filters-wrapper',
      text: 'Pokaż lub ukryj typy punktów i kategorie' },
    // Mobile filter button
    { sel: '.jg-mcr-filter-btn',
      text: 'Otwórz panel filtrów' },
    // Locate (my position)
    { sel: '.jg-mcr-locate-btn',
      text: 'Pokaż moją lokalizację na mapie' },
    // Map/satellite toggle
    { sel: '[data-layer="map"]',
      text: 'Widok mapy ulicznej' },
    { sel: '[data-layer="satellite"]',
      text: 'Widok satelitarny (zdjęcia lotnicze)' },
    // Zoom buttons (Leaflet adds title, these are fallback)
    { sel: '.leaflet-control-zoom-in',
      text: 'Powiększ mapę' },
    { sel: '.leaflet-control-zoom-out',
      text: 'Pomniejsz mapę' },
    // Mobile login button
    { sel: '#jg-mup-auth-btn',
      text: 'Zaloguj się, aby dodawać punkty i zdobywać XP' },
    // Mobile filters toggle
    { sel: '.jg-mobile-filters-toggle',
      text: 'Rozwiń filtry i wyszukiwanie' },
    // Fullscreen control
    { sel: '.jg-fullscreen-control',
      text: 'Tryb pełnoekranowy mapy' },
    // Zoom-in / clear search
    { sel: '.jg-mcr-zoom-in',
      text: 'Powiększ mapę' },
    { sel: '.jg-mcr-zoom-out',
      text: 'Pomniejsz mapę' },
    { sel: '.jg-mcr-clear-btn',
      text: 'Wyczyść wyszukiwanie' },
  ];

  function bindTooltip(el, text) {
    if (el._jgTTBound) return;
    el._jgTTBound = true;

    el.addEventListener('mouseenter', function () { showTooltip(text, el); });
    el.addEventListener('mouseleave', hideTooltip);
    el.addEventListener('focus',      function () { showTooltip(text, el); });
    el.addEventListener('blur',       hideTooltip);
    el.addEventListener('click',      hideTooltip);
  }

  function initTooltips() {
    var pending = TOOLTIPS.slice();

    function bindReady() {
      pending = pending.filter(function (spec) {
        var el = document.querySelector(spec.sel);
        if (el) { bindTooltip(el, spec.text); return false; }
        return true;
      });
      return pending.length === 0;
    }

    if (bindReady()) return;

    // Watch for dynamically-created elements (FAB, controls, etc.)
    var obs = new MutationObserver(function () {
      if (bindReady()) obs.disconnect();
    });
    obs.observe(document.body, { childList: true, subtree: true });
  }

  // ──────────────────────────────────────────
  //  INIT
  // ──────────────────────────────────────────
  function init() {
    if (window.JG_MAP_CFG && JG_MAP_CFG.onboardingEnabled === false) return;

    var mapEl = document.getElementById('jg-map');
    if (!mapEl) return;

    initHelpPanel(mapEl);
    initMobileFilters();
    initTooltips();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
