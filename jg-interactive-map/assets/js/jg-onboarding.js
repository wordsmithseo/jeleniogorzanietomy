/**
 * JG Interactive Map - Onboarding & Help System
 *
 * Three layers:
 * 1. Welcome modal (shown once on first visit)
 * 2. Help button + panel (always available)
 * 3. Contextual tips (shown once per feature)
 */
(function() {
  'use strict';

  var STORAGE_PREFIX = 'jg_onboarding_';
  var WELCOME_KEY = STORAGE_PREFIX + 'welcome_seen';
  var TIPS_KEY = STORAGE_PREFIX + 'tips_seen';

  // Pin colors matching the map markers
  var PIN_COLORS = {
    zgloszenie:  { start: '#000000', mid: '#1f1f1f', end: '#000000' },
    ciekawostka: { start: '#1e40af', mid: '#3b82f6', end: '#1e40af' },
    miejsce:     { start: '#15803d', mid: '#22c55e', end: '#15803d' }
  };

  // Build a small SVG pin in the type's color
  function pinSvg(type, size) {
    size = size || 28;
    var c = PIN_COLORS[type] || PIN_COLORS.zgloszenie;
    return '<svg width="' + size + '" height="' + Math.round(size * 1.25) + '" viewBox="0 0 32 40" xmlns="http://www.w3.org/2000/svg">' +
      '<defs><linearGradient id="onb-' + type + '" x1="0%" y1="0%" x2="100%" y2="100%">' +
      '<stop offset="0%" style="stop-color:' + c.start + '"/>' +
      '<stop offset="50%" style="stop-color:' + c.mid + '"/>' +
      '<stop offset="100%" style="stop-color:' + c.end + '"/>' +
      '</linearGradient></defs>' +
      '<path d="M16 0 C7.163 0 0 7.163 0 16 C0 19 1 22 4 26 L16 40 L28 26 C31 22 32 19 32 16 C32 7.163 24.837 0 16 0 Z" fill="url(#onb-' + type + ')"/>' +
      '<circle cx="16" cy="16" r="5.5" fill="rgba(255,255,255,0.85)"/>' +
      '</svg>';
  }

  // ====================================
  // STORAGE HELPERS
  // ====================================

  function getFlag(key) {
    try {
      return localStorage.getItem(key) === '1';
    } catch (e) {
      return false;
    }
  }

  function setFlag(key) {
    try {
      localStorage.setItem(key, '1');
    } catch (e) {}
  }

  function getSeenTips() {
    try {
      var val = localStorage.getItem(TIPS_KEY);
      return val ? JSON.parse(val) : {};
    } catch (e) {
      return {};
    }
  }

  function markTipSeen(tipId) {
    try {
      var seen = getSeenTips();
      seen[tipId] = 1;
      localStorage.setItem(TIPS_KEY, JSON.stringify(seen));
    } catch (e) {}
  }

  function resetOnboarding() {
    try {
      localStorage.removeItem(WELCOME_KEY);
      localStorage.removeItem(TIPS_KEY);
    } catch (e) {}
  }

  // ====================================
  // WELCOME MODAL (5-step wizard)
  // ====================================

  var currentStep = 0;
  var totalSteps = 5;

  var steps = [
    // Step 1: Point types
    {
      title: 'Odkrywaj Jeleni\u0105 G\u00f3r\u0119',
      content:
        '<div class="jg-onboarding-type-list">' +
          '<div class="jg-onboarding-type-item">' +
            '<span class="jg-onb-icon">' + pinSvg('zgloszenie') + '</span>' +
            '<div><strong>Zg\u0142oszenia</strong><p>Informuj o problemach infrastrukturalnych: dziury, uszkodzone chodniki, nielegalne wysypiska, graffiti.</p></div>' +
          '</div>' +
          '<div class="jg-onboarding-type-item">' +
            '<span class="jg-onb-icon">' + pinSvg('ciekawostka') + '</span>' +
            '<div><strong>Ciekawostki</strong><p>Dziel si\u0119 wiedz\u0105: historia, architektura, lokalne legendy i nieoczywiste miejsca.</p></div>' +
          '</div>' +
          '<div class="jg-onboarding-type-item">' +
            '<span class="jg-onb-icon">' + pinSvg('miejsce') + '</span>' +
            '<div><strong>Miejsca</strong><p>Oznaczaj lokalizacje: restauracje, kawiarnie, kultura, sport, us\u0142ugi, zabytki, przyroda.</p></div>' +
          '</div>' +
        '</div>'
    },

    // Step 2: How to add a point
    {
      title: 'Jak doda\u0107 punkt?',
      content:
        '<div class="jg-onboarding-how-list">' +
          '<div class="jg-onboarding-how-item"><span>Zaloguj si\u0119 lub za\u0142\u00f3\u017c konto</span></div>' +
          '<div class="jg-onboarding-how-item"><span>Przybli\u017c map\u0119 do poziomu ulicy (zoom 17+)</span></div>' +
          '<div class="jg-onboarding-how-item"><span>Kliknij na map\u0119 w wybranym miejscu</span></div>' +
          '<div class="jg-onboarding-how-item"><span>Opisz punkt, wybierz kategori\u0119 i dodaj zdj\u0119cia</span></div>' +
          '<div class="jg-onboarding-how-item"><span>Gotowe! Punkt pojawi si\u0119 po moderacji</span></div>' +
        '</div>' +
        '<p class="jg-onb-note">\uD83D\uDCA1 Mo\u017cesz te\u017c u\u017cy\u0107 przycisku \u201e+\u201d w prawym dolnym rogu mapy, aby doda\u0107 punkt po adresie.</p>'
    },

    // Step 3: Rating, photos, search & report (replacing old "Co jeszcze mogę robić?" with accurate content)
    {
      title: 'Oceniaj, fotografuj i szukaj',
      content:
        '<div class="jg-onboarding-type-list">' +
          '<div class="jg-onboarding-type-item">' +
            '<span class="jg-onb-icon jg-onb-icon--round" style="background:linear-gradient(135deg,#b45309,#d97706)">\u2B50</span>' +
            '<div><strong>Oceniaj gwiazdkami (1\u20135)</strong><p>Kliknij pin \u2192 otw\u00f3rz szczeg\u00f3\u0142y \u2192 przyznaj od 1 do 5 gwiazdek. Najlepiej oceniane miejsca zyskuj\u0105 wyr\u00f3\u017cnienie.</p></div>' +
          '</div>' +
          '<div class="jg-onboarding-type-item">' +
            '<span class="jg-onb-icon jg-onb-icon--round" style="background:linear-gradient(135deg,#1e40af,#3b82f6)">\uD83D\uDCF7</span>' +
            '<div><strong>Dodawaj zdj\u0119cia</strong><p>Otw\u00f3rz dowolny punkt i kliknij przycisk aparatu. Zdj\u0119cia pomagaj\u0105 innym rozpozna\u0107 lokalizacj\u0119.</p></div>' +
          '</div>' +
          '<div class="jg-onboarding-type-item">' +
            '<span class="jg-onb-icon jg-onb-icon--round" style="background:linear-gradient(135deg,#8d2324,#a02829)">\uD83D\uDD0D</span>' +
            '<div><strong>Szukaj, filtruj i zg\u0142aszaj</strong><p>Filtruj typy punkt\u00f3w i wyszukuj po nazwie. Nieodpowiedni\u0105 tre\u015b\u0107 zg\u0142o\u015b przyciskiem w szczeg\u00f3\u0142ach punktu.</p></div>' +
          '</div>' +
        '</div>'
    },

    // Step 4: XP & levels (new)
    {
      title: 'Zdobywaj XP i awansuj!',
      content:
        '<div class="jg-onboarding-xp-wrap">' +
          '<div class="jg-onboarding-xp-list">' +
            '<div class="jg-onboarding-xp-row"><span class="jg-xp-action">Dodajesz nowy punkt</span><span class="jg-xp-badge">+50 XP</span></div>' +
            '<div class="jg-onboarding-xp-row"><span class="jg-xp-action">Punkt zostaje zatwierdzony</span><span class="jg-xp-badge">+30 XP</span></div>' +
            '<div class="jg-onboarding-xp-row"><span class="jg-xp-action">Dodajesz zdj\u0119cie</span><span class="jg-xp-badge">+10 XP</span></div>' +
            '<div class="jg-onboarding-xp-row"><span class="jg-xp-action">Edytujesz sw\u00f3j punkt</span><span class="jg-xp-badge">+15 XP</span></div>' +
            '<div class="jg-onboarding-xp-row"><span class="jg-xp-action">Oceniasz inny punkt</span><span class="jg-xp-badge">+2 XP</span></div>' +
          '</div>' +
          '<div class="jg-onboarding-levels">' +
            '<span class="jg-lvl-badge jg-lvl-bronze">Br\u0105z</span>' +
            '<span class="jg-lvl-sep">\u2192</span>' +
            '<span class="jg-lvl-badge jg-lvl-silver">Srebro</span>' +
            '<span class="jg-lvl-sep">\u2192</span>' +
            '<span class="jg-lvl-badge jg-lvl-gold">Z\u0142oto</span>' +
            '<span class="jg-lvl-sep">\u2192</span>' +
            '<span class="jg-lvl-badge jg-lvl-legend">Legenda</span>' +
          '</div>' +
          '<p class="jg-onb-note">\uD83D\uDCC8 Tw\u00f3j poziom i pasek XP widoczne s\u0105 na pasku u g\u00f3ry strony. Sprawd\u017a ranking \u2014 kto jest najbardziej aktywny!</p>' +
        '</div>'
    },

    // Step 5: Restaurant menu
    {
      title: 'Menu restauracji',
      content:
        '<div class="jg-onboarding-type-list">' +
          '<div class="jg-onboarding-type-item">' +
            '<span class="jg-onb-icon jg-onb-icon--round" style="background:linear-gradient(135deg,#15803d,#22c55e)">\uD83C\uDF7D\uFE0F</span>' +
            '<div><strong>Aktualne menu</strong><p>Restauracje i caf\u00e9 mog\u0105 publikowa\u0107 pe\u0142ne menu \u2014 sekcje, dania, ceny i warianty wielko\u015bci.</p></div>' +
          '</div>' +
          '<div class="jg-onboarding-type-item">' +
            '<span class="jg-onb-icon jg-onb-icon--round" style="background:linear-gradient(135deg,#15803d,#22c55e)">\uD83D\uDCF7</span>' +
            '<div><strong>Zdj\u0119cia karty menu</strong><p>W\u0142a\u015bciciel miejsca mo\u017ce doda\u0107 zdj\u0119cia fizycznej karty menu \u2014 do 4 skan\u00f3w.</p></div>' +
          '</div>' +
          '<div class="jg-onboarding-type-item">' +
            '<span class="jg-onb-icon jg-onb-icon--round" style="background:linear-gradient(135deg,#15803d,#22c55e)">\uD83D\uDD17</span>' +
            '<div><strong>Indeksowane przez Google</strong><p>Ka\u017cde menu ma dedykowan\u0105 stron\u0119 /menu/ ze schema.org, widoczn\u0105 w wynikach wyszukiwania.</p></div>' +
          '</div>' +
        '</div>'
    }
  ];

  function renderWelcomeModal() {
    var modal = document.getElementById('jg-onboarding-modal');
    var content = document.getElementById('jg-onboarding-content');
    if (!modal || !content) return;

    var step = steps[currentStep];

    var dotsHtml = '';
    for (var i = 0; i < totalSteps; i++) {
      dotsHtml += '<div class="jg-onboarding-dot' + (i === currentStep ? ' active' : '') + '"></div>';
    }

    var isFirst = currentStep === 0;
    var isLast = currentStep === totalSteps - 1;

    var navHtml = '<div class="jg-onboarding-nav">';
    if (!isFirst) {
      navHtml += '<button class="jg-btn jg-btn--ghost" id="jg-onb-prev">Wstecz</button>';
    }
    navHtml += '<button class="jg-btn jg-btn--ghost" id="jg-onb-skip">Pomi\u0144</button>';
    if (isLast) {
      navHtml += '<button class="jg-btn jg-onboarding-btn-primary" id="jg-onb-finish">Zaczynamy!</button>';
    } else {
      navHtml += '<button class="jg-btn jg-onboarding-btn-primary" id="jg-onb-next">Dalej</button>';
    }
    navHtml += '</div>';

    var headerHtml = '';
    if (isFirst) {
      headerHtml =
        '<div class="jg-onboarding-header">' +
          '<h2>Witaj na mapie Jeleniej G\u00f3ry!</h2>' +
          '<p>Interaktywna mapa, na kt\u00f3rej mieszka\u0144cy zg\u0142aszaj\u0105 problemy, dziel\u0105 si\u0119 ciekawostkami i oznaczaj\u0105 wa\u017cne miejsca.</p>' +
        '</div>';
    }

    content.innerHTML =
      headerHtml +
      '<div class="jg-onboarding-steps">' +
        '<div class="jg-onboarding-step active">' +
          '<h3>' + step.title + '</h3>' +
          step.content +
        '</div>' +
      '</div>' +
      '<div class="jg-onboarding-footer">' +
        '<div class="jg-onboarding-dots">' + dotsHtml + '</div>' +
        navHtml +
      '</div>';

    modal.style.display = 'flex';
    document.body.classList.add('jg-modal-open');

    // Bind navigation buttons
    var nextBtn = document.getElementById('jg-onb-next');
    var prevBtn = document.getElementById('jg-onb-prev');
    var skipBtn = document.getElementById('jg-onb-skip');
    var finishBtn = document.getElementById('jg-onb-finish');

    if (nextBtn) nextBtn.addEventListener('click', function() {
      currentStep++;
      renderWelcomeModal();
    });

    if (prevBtn) prevBtn.addEventListener('click', function() {
      currentStep--;
      renderWelcomeModal();
    });

    if (skipBtn) skipBtn.addEventListener('click', closeWelcome);
    if (finishBtn) finishBtn.addEventListener('click', closeWelcome);
  }

  function closeWelcome() {
    var modal = document.getElementById('jg-onboarding-modal');
    if (modal) modal.style.display = 'none';
    // Unlock body scroll if no other modal is open
    var bgs = document.querySelectorAll('.jg-modal-bg');
    var anyOpen = false;
    for (var i = 0; i < bgs.length; i++) {
      if (bgs[i].style.display === 'flex' || bgs[i].classList.contains('active')) { anyOpen = true; break; }
    }
    if (!anyOpen) document.body.classList.remove('jg-modal-open');
    setFlag(WELCOME_KEY);
    currentStep = 0;

    // Show first contextual tip after a short delay
    setTimeout(function() {
      showNextTip();
    }, 1000);
  }

  function showWelcome() {
    currentStep = 0;
    renderWelcomeModal();
  }

  // ====================================
  // HELP FAB + PANEL
  // ====================================

  // Prevent all Leaflet map interactions (click, touch, scroll) on an element
  function shieldFromMap(el) {
    if (window.L && L.DomEvent) {
      L.DomEvent.disableClickPropagation(el);
      L.DomEvent.disableScrollPropagation(el);
    }
    // Fallback / extra safety
    el.addEventListener('click', function(e) { e.stopPropagation(); });
    el.addEventListener('touchstart', function(e) { e.stopPropagation(); }, { passive: true });
  }

  // Create the help FAB dynamically and append to #jg-map
  function createHelpFAB(mapEl) {
    var container = document.createElement('div');
    container.id = 'jg-help-fab';
    container.style.cssText = 'position:absolute;bottom:30px;left:30px;z-index:9998;display:flex;flex-direction:column;align-items:flex-start;gap:12px;';

    // Prevent map interactions when clicking the FAB area
    shieldFromMap(container);
    container.addEventListener('wheel', function(e) { e.stopPropagation(); e.preventDefault(); }, { passive: false });

    var btn = document.createElement('button');
    btn.id = 'jg-help-btn';
    btn.title = 'Pomoc';
    btn.style.cssText = 'width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,#8d2324 0%,#a02829 100%);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(141,35,36,0.4);transition:all 0.3s ease;outline:none;';
    btn.innerHTML = '<span style="color:#fff;font-size:26px;font-weight:700;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;line-height:1;">?</span>';

    btn.addEventListener('mouseenter', function() {
      btn.style.transform = 'scale(1.1)';
      btn.style.boxShadow = '0 6px 16px rgba(141,35,36,0.5)';
    });
    btn.addEventListener('mouseleave', function() {
      btn.style.transform = 'scale(1)';
      btn.style.boxShadow = '0 4px 12px rgba(141,35,36,0.4)';
    });

    container.appendChild(btn);
    mapEl.appendChild(container);

    return btn;
  }

  function initHelpPanel(mapEl) {
    // Move help panel and tip container into #jg-map so they position inside the map
    var helpPanel = document.getElementById('jg-help-panel');
    var tipContainer = document.getElementById('jg-tip-container');
    if (helpPanel) {
      mapEl.appendChild(helpPanel);
      shieldFromMap(helpPanel);
    }
    if (tipContainer) {
      mapEl.appendChild(tipContainer);
      shieldFromMap(tipContainer);
    }

    // Shield the onboarding modal from map interactions too
    var onbModal = document.getElementById('jg-onboarding-modal');
    if (onbModal) shieldFromMap(onbModal);

    // Create FAB button dynamically inside #jg-map
    var helpBtn = createHelpFAB(mapEl);

    var closeBtn = document.getElementById('jg-help-panel-close');
    var restartBtn = document.getElementById('jg-help-restart-onboarding');

    if (!helpBtn || !helpPanel) return;

    helpBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      var isOpen = helpPanel.style.display !== 'none';
      helpPanel.style.display = isOpen ? 'none' : 'flex';
    });

    if (closeBtn) {
      closeBtn.addEventListener('click', function() {
        helpPanel.style.display = 'none';
      });
    }

    if (restartBtn) {
      restartBtn.addEventListener('click', function() {
        helpPanel.style.display = 'none';
        resetOnboarding();
        showWelcome();
      });
    }

    // Close help panel when clicking outside
    document.addEventListener('click', function(e) {
      if (helpPanel.style.display === 'none') return;
      var fab = document.getElementById('jg-help-fab');
      if (helpPanel.contains(e.target)) return;
      if (fab && fab.contains(e.target)) return;
      helpPanel.style.display = 'none';
    });
  }

  // ====================================
  // CONTEXTUAL TIPS
  // ====================================

  var tipQueue = [
    {
      id: 'click_map',
      text: 'Kliknij na map\u0119 (po przybli\u017ceniu do poziomu ulicy), aby doda\u0107 nowy punkt.',
      delay: 0
    },
    {
      id: 'use_filters',
      text: 'U\u017cyj checkbox\u00f3w powy\u017cej, aby filtrowa\u0107 widoczne typy punkt\u00f3w \u2014 Zg\u0142oszenia, Ciekawostki lub Miejsca.',
      delay: 8000
    },
    {
      id: 'use_search',
      text: 'Wpisz nazw\u0119 w pole wyszukiwania, aby szybko znale\u017a\u0107 punkt na mapie.',
      delay: 16000
    },
    {
      id: 'restaurant_menu',
      text: '\uD83C\uDF7D\uFE0F Restauracje maj\u0105 menu! Kliknij zielony pin gastronomiczny i sprawd\u017a aktualne dania oraz ceny.',
      delay: 25000
    },
    {
      id: 'rate_point',
      text: '\u2B50 Oce\u0144 dowolne miejsce! Kliknij pin \u2192 otw\u00f3rz szczeg\u00f3\u0142y \u2192 przyznaj od 1 do 5 gwiazdek.',
      delay: 36000
    },
    {
      id: 'add_photo',
      text: '\uD83D\uDCF7 Masz zdj\u0119cie miejsca? Kliknij pin, otw\u00f3rz szczeg\u00f3\u0142y i dodaj fotografi\u0119 \u2014 pomagasz innym u\u017cytkownikom!',
      delay: 48000
    },
    {
      id: 'earn_xp',
      text: '\uD83C\uDFC6 Za ka\u017cd\u0105 aktywno\u015b\u0107 zdobywasz XP: +50 za nowy punkt, +30 gdy zostanie zatwierdzony, +10 za zdj\u0119cie. Awansuj przez 8 poziom\u00f3w!',
      delay: 62000
    },
    {
      id: 'check_ranking',
      text: '\uD83D\uDCCA Sprawd\u017a, kto jest najbardziej aktywny na mapie \u2014 kliknij \u201eRanking\u201d w menu g\u0142\u00f3wnym.',
      delay: 77000
    }
  ];

  // Mobile-only fullscreen encouragement tip (prepended in init if on mobile)
  var mobileFsTip = {
    id: 'mobile_fullscreen',
    text: 'Wskaz\u00f3wka dla telefonu: naci\u015bnij ikon\u0119 pe\u0142nego ekranu w lewym g\u00f3rnym rogu mapy \u2197\ufe0f, aby prze\u0142\u0105czy\u0107 na tryb pe\u0142noekranowy \u2014 du\u017co wygodniej na telefonie!',
    delay: 600,
    onShow: function() {
      var fsCtrl = document.querySelector('.jg-fullscreen-control');
      if (fsCtrl) fsCtrl.classList.add('jg-onboarding-fs-pulse');
    },
    onDismiss: function() {
      var fsCtrl = document.querySelector('.jg-fullscreen-control');
      if (fsCtrl) fsCtrl.classList.remove('jg-onboarding-fs-pulse');
    }
  };

  var tipTimeout = null;
  var currentTipIndex = 0;

  function showNextTip() {
    var seen = getSeenTips();

    // Find next unseen tip
    while (currentTipIndex < tipQueue.length && seen[tipQueue[currentTipIndex].id]) {
      currentTipIndex++;
    }

    if (currentTipIndex >= tipQueue.length) return;

    var tip = tipQueue[currentTipIndex];

    tipTimeout = setTimeout(function() {
      showTip(tip.id, tip.text, tip);
    }, tip.delay);
  }

  function showTip(id, text, tip) {
    var container = document.getElementById('jg-tip-container');
    var textEl = document.getElementById('jg-tip-text');
    var dismissBtn = document.getElementById('jg-tip-dismiss');

    if (!container || !textEl) return;

    textEl.textContent = text;
    container.style.display = 'block';

    // Fire optional onShow callback (e.g. highlight a UI element)
    if (tip && tip.onShow) tip.onShow();

    // Force re-trigger animation
    container.style.animation = 'none';
    container.offsetHeight; // trigger reflow
    container.style.animation = '';

    var autoDismiss = setTimeout(function() {
      dismissTip(id, tip);
    }, 10000);

    function onDismiss() {
      clearTimeout(autoDismiss);
      dismissTip(id, tip);
      dismissBtn.removeEventListener('click', onDismiss);
    }

    if (dismissBtn) {
      dismissBtn.addEventListener('click', onDismiss);
    }
  }

  function dismissTip(id, tip) {
    var container = document.getElementById('jg-tip-container');
    if (container) container.style.display = 'none';

    // Fire optional onDismiss callback (e.g. remove highlight from UI element)
    if (tip && tip.onDismiss) tip.onDismiss();

    markTipSeen(id);
    currentTipIndex++;

    // Show next tip after a pause
    setTimeout(function() {
      showNextTip();
    }, 3000);
  }

  // ====================================
  // MOBILE COLLAPSIBLE FILTERS
  // ====================================

  function initMobileFilters() {
    if (window.innerWidth > 768) return;

    var wrapper = document.getElementById('jg-map-filters-wrapper');
    if (!wrapper || wrapper.querySelector('.jg-mobile-filters-toggle')) return;

    // Create toggle button
    var toggle = document.createElement('button');
    toggle.className = 'jg-mobile-filters-toggle';
    toggle.type = 'button';
    toggle.innerHTML = '<span>Filtry i wyszukiwanie</span><span class="jg-toggle-arrow">&#x25BC;</span>';

    // Start collapsed
    wrapper.classList.add('collapsed');

    toggle.addEventListener('click', function() {
      var isCollapsed = wrapper.classList.contains('collapsed');
      if (isCollapsed) {
        wrapper.classList.remove('collapsed');
        toggle.classList.add('expanded');
      } else {
        wrapper.classList.add('collapsed');
        toggle.classList.remove('expanded');
      }
    });

    wrapper.insertBefore(toggle, wrapper.firstChild);
  }

  // ====================================
  // INITIALIZATION
  // ====================================

  function init() {
    var mapEl = document.getElementById('jg-map');
    if (!mapEl) return;

    initHelpPanel(mapEl);
    initMobileFilters();

    // Prepend mobile fullscreen encouragement tip (shown only on mobile, only once)
    if (window.innerWidth <= 768) {
      tipQueue.unshift(mobileFsTip);
    }

    // Show welcome modal on first visit
    if (!getFlag(WELCOME_KEY)) {
      // Small delay so user sees the map loaded first
      setTimeout(showWelcome, 800);
    } else {
      // For returning users, show any unseen contextual tips
      setTimeout(showNextTip, 3000);
    }
  }

  // Start when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
