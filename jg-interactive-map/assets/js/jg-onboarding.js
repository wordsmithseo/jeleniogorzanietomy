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
      title: 'Odkrywaj Jelenią Górę',
      content:
        '<div class="jg-onboarding-type-list">' +
          '<div class="jg-onboarding-type-item">' +
            '<span class="jg-onb-icon">⚠️</span>' +
            '<div><strong>Zgłoszenia</strong><p>Informuj o problemach: dziury, uszkodzone chodniki, nielegalne wysypiska, graffiti.</p></div>' +
          '</div>' +
          '<div class="jg-onboarding-type-item">' +
            '<span class="jg-onb-icon">💡</span>' +
            '<div><strong>Ciekawostki</strong><p>Dziel się wiedzą: ciekawe miejsca, historia, architektura, legendy.</p></div>' +
          '</div>' +
          '<div class="jg-onboarding-type-item">' +
            '<span class="jg-onb-icon">📍</span>' +
            '<div><strong>Miejsca</strong><p>Dodawaj lokalizacje: gastronomia, kultura, sport, usługi, przyroda.</p></div>' +
          '</div>' +
        '</div>'
    },
    {
      title: 'Jak dodać punkt?',
      content:
        '<div class="jg-onboarding-how-list">' +
          '<div class="jg-onboarding-how-item"><span>Zaloguj się lub załóż konto</span></div>' +
          '<div class="jg-onboarding-how-item"><span>Przybliż mapę do poziomu ulicy</span></div>' +
          '<div class="jg-onboarding-how-item"><span>Kliknij na mapę w wybranym miejscu</span></div>' +
          '<div class="jg-onboarding-how-item"><span>Opisz punkt i dodaj zdjęcia</span></div>' +
          '<div class="jg-onboarding-how-item"><span>Gotowe! Punkt pojawi się po moderacji</span></div>' +
        '</div>'
    },
    {
      title: 'Co jeszcze mogę robić?',
      content:
        '<div class="jg-onboarding-type-list">' +
          '<div class="jg-onboarding-type-item">' +
            '<span class="jg-onb-icon">👍</span>' +
            '<div><strong>Głosuj</strong><p>Oceniaj punkty kciukiem w górę lub w dół, aby wyróżnić najważniejsze.</p></div>' +
          '</div>' +
          '<div class="jg-onboarding-type-item">' +
            '<span class="jg-onb-icon">🔍</span>' +
            '<div><strong>Szukaj i filtruj</strong><p>Użyj paska nad mapą, aby filtrować typy i kategorie punktów lub wyszukać po nazwie.</p></div>' +
          '</div>' +
          '<div class="jg-onboarding-type-item">' +
            '<span class="jg-onb-icon">🚩</span>' +
            '<div><strong>Zgłaszaj problemy</strong><p>Widzisz nieodpowiednią treść? Zgłoś ją — moderacja sprawdzi to.</p></div>' +
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
    navHtml += '<button class="jg-btn jg-btn--ghost" id="jg-onb-skip">Pomiń</button>';
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
          '<h2>Witaj na mapie Jeleniej Góry!</h2>' +
          '<p>Interaktywna mapa, na której mieszkańcy mogą zgłaszać problemy, dzielić się ciekawostkami i oznaczać ważne miejsca.</p>' +
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

    helpBtn.addEventListener('click', function() {
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
      if (helpPanel.contains(e.target) || helpBtn.contains(e.target)) return;
      helpPanel.style.display = 'none';
    });
  }

  // ====================================
  // CONTEXTUAL TIPS
  // ====================================

  var tipQueue = [
    {
      id: 'click_map',
      text: 'Kliknij na mapę (po przybliżeniu), aby dodać nowy punkt.',
      delay: 0
    },
    {
      id: 'use_filters',
      text: 'Użyj checkboxów powyżej, aby filtrować widoczne typy punktów.',
      delay: 8000
    },
    {
      id: 'use_search',
      text: 'Wpisz nazwę w pole wyszukiwania, aby szybko znaleźć punkt na mapie.',
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
    // Wait for map to be loaded (check for #jg-map element with opacity 1)
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
