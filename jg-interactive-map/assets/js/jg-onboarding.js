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
  // WELCOME MODAL (3-step wizard)
  // ====================================

  var currentStep = 0;
  var totalSteps = 3;

  var steps = [
    {
      title: 'Odkrywaj Jeleni\u0105 G\u00f3r\u0119',
      content:
        '<div class="jg-onboarding-type-list">' +
          '<div class="jg-onboarding-type-item">' +
            '<span class="jg-onb-icon">' + pinSvg('zgloszenie') + '</span>' +
            '<div><strong>Zg\u0142oszenia</strong><p>Informuj o problemach: dziury, uszkodzone chodniki, nielegalne wysypiska, graffiti.</p></div>' +
          '</div>' +
          '<div class="jg-onboarding-type-item">' +
            '<span class="jg-onb-icon">' + pinSvg('ciekawostka') + '</span>' +
            '<div><strong>Ciekawostki</strong><p>Dziel si\u0119 wiedz\u0105: ciekawe miejsca, historia, architektura, legendy.</p></div>' +
          '</div>' +
          '<div class="jg-onboarding-type-item">' +
            '<span class="jg-onb-icon">' + pinSvg('miejsce') + '</span>' +
            '<div><strong>Miejsca</strong><p>Dodawaj lokalizacje: gastronomia, kultura, sport, us\u0142ugi, przyroda.</p></div>' +
          '</div>' +
        '</div>'
    },
    {
      title: 'Jak doda\u0107 punkt?',
      content:
        '<div class="jg-onboarding-how-list">' +
          '<div class="jg-onboarding-how-item"><span>Zaloguj si\u0119 lub za\u0142\u00f3\u017c konto</span></div>' +
          '<div class="jg-onboarding-how-item"><span>Przybli\u017c map\u0119 do poziomu ulicy</span></div>' +
          '<div class="jg-onboarding-how-item"><span>Kliknij na map\u0119 w wybranym miejscu</span></div>' +
          '<div class="jg-onboarding-how-item"><span>Opisz punkt i dodaj zdj\u0119cia</span></div>' +
          '<div class="jg-onboarding-how-item"><span>Gotowe! Punkt pojawi si\u0119 po moderacji</span></div>' +
        '</div>'
    },
    {
      title: 'Co jeszcze mog\u0119 robi\u0107?',
      content:
        '<div class="jg-onboarding-type-list">' +
          '<div class="jg-onboarding-type-item">' +
            '<span class="jg-onb-icon jg-onb-icon--round" style="background:linear-gradient(135deg,#8d2324,#a02829)">&#x1F44D;</span>' +
            '<div><strong>G\u0142osuj</strong><p>Oceniaj punkty kciukiem w g\u00f3r\u0119 lub w d\u00f3\u0142, aby wyr\u00f3\u017cni\u0107 najwa\u017cniejsze.</p></div>' +
          '</div>' +
          '<div class="jg-onboarding-type-item">' +
            '<span class="jg-onb-icon jg-onb-icon--round" style="background:linear-gradient(135deg,#8d2324,#a02829)">&#x1F50D;</span>' +
            '<div><strong>Szukaj i filtruj</strong><p>U\u017cyj paska nad map\u0105, aby filtrowa\u0107 typy i kategorie punkt\u00f3w lub wyszuka\u0107 po nazwie.</p></div>' +
          '</div>' +
          '<div class="jg-onboarding-type-item">' +
            '<span class="jg-onb-icon jg-onb-icon--round" style="background:linear-gradient(135deg,#8d2324,#a02829)">&#x1F6A9;</span>' +
            '<div><strong>Zg\u0142aszaj problemy</strong><p>Widzisz nieodpowiedni\u0105 tre\u015b\u0107? Zg\u0142o\u015b j\u0105, a moderacja sprawdzi to.</p></div>' +
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
          '<p>Interaktywna mapa, na kt\u00f3rej mieszka\u0144cy mog\u0105 zg\u0142asza\u0107 problemy, dzieli\u0107 si\u0119 ciekawostkami i oznacza\u0107 wa\u017cne miejsca.</p>' +
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
  // HELP PANEL
  // ====================================

  function initHelpPanel() {
    var helpBtn = document.getElementById('jg-help-btn');
    var helpPanel = document.getElementById('jg-help-panel');
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
      text: 'Kliknij na map\u0119 (po przybli\u017ceniu), aby doda\u0107 nowy punkt.',
      delay: 0
    },
    {
      id: 'use_filters',
      text: 'U\u017cyj checkbox\u00f3w powy\u017cej, aby filtrowa\u0107 widoczne typy punkt\u00f3w.',
      delay: 8000
    },
    {
      id: 'use_search',
      text: 'Wpisz nazw\u0119 w pole wyszukiwania, aby szybko znale\u017a\u0107 punkt na mapie.',
      delay: 16000
    }
  ];

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
      showTip(tip.id, tip.text);
    }, tip.delay);
  }

  function showTip(id, text) {
    var container = document.getElementById('jg-tip-container');
    var textEl = document.getElementById('jg-tip-text');
    var dismissBtn = document.getElementById('jg-tip-dismiss');

    if (!container || !textEl) return;

    textEl.textContent = text;
    container.style.display = 'block';

    // Force re-trigger animation
    container.style.animation = 'none';
    container.offsetHeight; // trigger reflow
    container.style.animation = '';

    var autoDismiss = setTimeout(function() {
      dismissTip(id);
    }, 10000);

    function onDismiss() {
      clearTimeout(autoDismiss);
      dismissTip(id);
      dismissBtn.removeEventListener('click', onDismiss);
    }

    if (dismissBtn) {
      dismissBtn.addEventListener('click', onDismiss);
    }
  }

  function dismissTip(id) {
    var container = document.getElementById('jg-tip-container');
    if (container) container.style.display = 'none';
    markTipSeen(id);
    currentTipIndex++;

    // Show next tip after a pause
    setTimeout(function() {
      showNextTip();
    }, 3000);
  }

  // ====================================
  // INITIALIZATION
  // ====================================

  function init() {
    var mapEl = document.getElementById('jg-map');
    if (!mapEl) return;

    initHelpPanel();

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
