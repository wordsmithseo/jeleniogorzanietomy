// ============================================================
// KOMPLETNY TEST - case_id + status change
// Wklej w konsole (F12) NA STRONIE MAPY
// Skrypt poczeka na załadowanie danych automatycznie
// ============================================================

(function() {
  console.clear();
  console.log('%c=== JG MAP COMPLETE DIAGNOSTIC ===', 'background: #000; color: #0f0; font-size: 18px; padding: 10px');

  // Funkcja czekająca na załadowanie danych
  function waitForData(callback) {
    if (window.ALL && window.ALL.length > 0) {
      callback();
    } else {
      console.log('⏳ Waiting for map data to load...');
      setTimeout(function() { waitForData(callback); }, 500);
    }
  }

  waitForData(function() {
    console.log('✓ Map data loaded:', window.ALL.length, 'points\n');

    // ========================================
    // TEST 1: case_id w danych
    // ========================================
    console.log('%c--- TEST 1: case_id MAPPING ---', 'background: #333; color: #ff0; padding: 5px');

    var reports = window.ALL.filter(function(p) { return p.type === 'zgloszenie'; });
    var withCaseId = reports.filter(function(r) { return r.case_id; });

    console.log('Total zgłoszeń:', reports.length);
    console.log('Zgłoszeń z case_id:', withCaseId.length);

    if (withCaseId.length === 0) {
      console.error('❌ PROBLEM 1: Backend NIE wysyła case_id do frontendu!');
      console.log('Przyczyna: Plik class-database.php nie jest zaktualizowany na serwerze');
      console.log('Rozwiązanie: Zrestartuj PHP-FPM lub poczekaj 5 min na opcache reload');
    } else {
      console.log('✓ Backend wysyła case_id:', withCaseId[0].case_id);

      // Sprawdź czy badge się tworzy
      console.log('\n🔍 Teraz otwórz dowolne ZGŁOSZENIE i uruchom:');
      console.log('%cdocument.querySelector(".jg-case-id-badge")', 'background: #ff0; color: #000; padding: 5px');
      console.log('null = badge się nie tworzy (problem JS)');
      console.log('element = badge istnieje (sprawdź CSS)');
    }

    // Przykładowe dane
    if (reports.length > 0) {
      console.log('\n📊 Przykładowe zgłoszenie:');
      var sample = reports[0];
      console.table({
        'ID': sample.id,
        'Title': sample.title.substring(0, 40),
        'case_id': sample.case_id || '❌ BRAK',
        'report_status': sample.report_status,
        'report_status_label': sample.report_status_label
      });
    }

    // ========================================
    // TEST 2: Status change - backend validation
    // ========================================
    console.log('\n%c--- TEST 2: STATUS CHANGE TEST ---', 'background: #333; color: #ff0; padding: 5px');

    console.log('Backend powinien akceptować statusy:');
    console.log('  ✓ added');
    console.log('  ✓ needs_better_documentation');
    console.log('  ✓ reported');
    console.log('  ✓ resolved');

    console.log('\n🧪 Aby przetestować zmianę statusu:');
    console.log('1. Otwórz ZGŁOSZENIE');
    console.log('2. Kliknij "Zmień status"');
    console.log('3. Wklej poniższy kod i naciśnij Enter:');
    console.log('%c' + `
// TEST INTERCEPTOR - wklej to PRZED kliknięciem "Zapisz"
var originalFetch = window.fetch;
window.fetch = function(url, options) {
  return originalFetch(url, options).then(function(response) {
    var clone = response.clone();
    clone.text().then(function(text) {
      try {
        var data = JSON.parse(text);
        if (options && options.body && options.body.includes('jg_admin_change_status')) {
          console.log('%c=== STATUS CHANGE RESPONSE ===', 'background: #0f0; color: #000; padding: 10px');
          console.log('Success:', data.success);
          if (data.success) {
            console.log('✓ Status saved:', data.data.report_status);
            console.log('✓ Label:', data.data.report_status_label);
            if (data.data.report_status === 'needs_better_documentation') {
              console.log('✓✓✓ Status "needs_better_documentation" działa!');
            }
          } else {
            console.error('❌ Error:', data.data);
          }
          console.log('Full response:', data);
        }
      } catch(e) {}
    });
    return response;
  });
};
console.log('✓ Interceptor aktywny. Teraz wybierz status i kliknij Zapisz.');
    `, 'background: #333; color: #0ff; padding: 10px; font-family: monospace');

    // ========================================
    // TEST 3: JavaScript validation check
    // ========================================
    console.log('\n%c--- TEST 3: JAVASCRIPT CODE CHECK ---', 'background: #333; color: #ff0; padding: 5px');

    // Sprawdź czy funkcja tworzy badge
    var jsSource = '';
    try {
      // Próba znalezienia funkcji w kodzie
      if (window.openDetailsModalContent) {
        jsSource = window.openDetailsModalContent.toString();
      }
    } catch(e) {}

    if (jsSource.indexOf('caseIdBadge') > -1) {
      console.log('✓ JavaScript ma kod dla case_id badge');
    } else {
      console.warn('⚠ JavaScript może nie mieć kodu dla case_id badge');
      console.log('Sprawdź czy plik assets/js/jg-map.js jest zaktualizowany');
    }

    // ========================================
    // PODSUMOWANIE
    // ========================================
    console.log('\n%c=== SUMMARY ===', 'background: #000; color: #0f0; font-size: 16px; padding: 10px');
    console.log('1. case_id w danych:', withCaseId.length > 0 ? '✓ OK' : '❌ BRAK');
    console.log('2. Status test:', 'Uruchom interceptor (kod powyżej)');
    console.log('\n📋 SKOPIUJ CAŁY OUTPUT Z KONSOLI I PRZEŚLIJ!');
  });
})();
