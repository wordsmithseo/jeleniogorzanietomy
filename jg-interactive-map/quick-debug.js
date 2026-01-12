// ========================================
// JG MAP - COMPLETE DEBUG SCRIPT
// Wklej w konsole przeglądarki (F12 -> Console)
// ========================================

console.clear();
console.log('%c=== JG MAP DEBUG START ===', 'color: #00ff00; font-size: 16px; font-weight: bold');

// 1. Sprawdź czy dane są załadowane
if (!window.ALL) {
  console.error('❌ window.ALL is not loaded! Map data not available.');
} else {
  console.log('✓ Map data loaded:', window.ALL.length, 'points');

  // 2. Znajdź zgłoszenia
  var reports = window.ALL.filter(function(p) { return p.type === 'zgloszenie'; });
  console.log('✓ Found', reports.length, 'reports (type: zgloszenie)');

  if (reports.length > 0) {
    // 3. Sprawdź case_id w danych
    var withCaseId = reports.filter(function(r) { return r.case_id; });
    console.log('\n--- CASE_ID CHECK ---');
    console.log('Reports WITH case_id:', withCaseId.length, '/', reports.length);

    if (withCaseId.length === 0) {
      console.error('❌ PROBLEM: Backend nie wysyła case_id do frontendu!');
      console.log('Możliwe przyczyny:');
      console.log('  1. Plik class-database.php nie został zaktualizowany');
      console.log('  2. PHP opcache/server cache nie został wyczyszczony');
      console.log('  3. Używasz starej wersji pliku PHP');
    } else {
      console.log('✓ Backend wysyła case_id prawidłowo');
    }

    // 4. Pokaż przykładowe dane
    console.log('\n--- SAMPLE DATA (first report) ---');
    var sample = reports[0];
    console.log('ID:', sample.id);
    console.log('Title:', sample.title);
    console.log('case_id:', sample.case_id || '❌ NULL');
    console.log('report_status:', sample.report_status);
    console.log('report_status_label:', sample.report_status_label);
    console.log('resolved_delete_at:', sample.resolved_delete_at || 'NULL');

    // 5. Sprawdź HTML modala
    console.log('\n--- MODAL HTML CHECK ---');
    console.log('Otwórz dowolne zgłoszenie, a potem uruchom to w konsoli:');
    console.log('%c' + `
var modal = document.getElementById('jg-map-modal-view');
if (modal && modal.style.display !== 'none') {
  var header = modal.querySelector('header');
  console.log('Modal header HTML:', header ? header.innerHTML : 'NOT FOUND');
  var badge = modal.querySelector('.jg-case-id-badge');
  console.log('Case ID badge found:', badge ? '✓ YES: ' + badge.textContent : '❌ NO');
} else {
  console.log('⚠ Modal is not open. Open a report first!');
}
    `, 'color: #ffff00; background: #333; padding: 10px');
  }
}

// 6. Test zmiany statusu
console.log('\n--- STATUS CHANGE TEST ---');
console.log('Aby przetestować zmianę statusu, wykonaj:');
console.log('%c' + `
// 1. Otwórz zgłoszenie
// 2. Kliknij "Zmień status"
// 3. Wybierz "Wymaga lepszego udokumentowania"
// 4. Wklej poniższy kod PRZED kliknięciem "Zapisz":

var originalFetch = window.fetch;
window.fetch = function() {
  return originalFetch.apply(this, arguments).then(function(response) {
    var clone = response.clone();
    clone.text().then(function(text) {
      if (text.includes('jg_admin_change_status')) {
        console.log('=== STATUS CHANGE RESPONSE ===');
        try {
          var json = JSON.parse(text);
          console.log('Success:', json.success);
          console.log('Status:', json.data.report_status);
          console.log('Label:', json.data.report_status_label);
          console.log('Full response:', json);
        } catch(e) {
          console.log('Raw response:', text);
        }
      }
    });
    return response;
  });
};
console.log('✓ Fetch interceptor active. Now click Zapisz.');
`, 'color: #ffff00; background: #333; padding: 10px');

console.log('\n%c=== DEBUG END ===', 'color: #00ff00; font-size: 16px; font-weight: bold');
console.log('Skopiuj cały output z tej konsoli i prześlij mi!');
