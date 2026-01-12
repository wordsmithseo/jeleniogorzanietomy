/**
 * Debug Script - Add to browser console on map page
 *
 * Run this in browser console (F12) when viewing a report to debug:
 * 1. Why case_id badge doesn't show
 * 2. Why status doesn't save
 */

// Get all points data
console.log('=== JG MAP DEBUG ===');
console.log('Total points loaded:', window.ALL ? window.ALL.length : 'NOT LOADED');

if (window.ALL) {
  // Find reports
  var reports = window.ALL.filter(function(p) { return p.type === 'zgloszenie'; });
  console.log('Total reports (zgloszenie):', reports.length);

  if (reports.length > 0) {
    console.log('\n=== First 3 Reports ===');
    reports.slice(0, 3).forEach(function(r, idx) {
      console.log('\nReport #' + (idx + 1) + ':');
      console.log('  ID:', r.id);
      console.log('  Title:', r.title);
      console.log('  case_id:', r.case_id || 'NULL/MISSING');
      console.log('  report_status:', r.report_status);
      console.log('  report_status_label:', r.report_status_label);
      console.log('  resolved_delete_at:', r.resolved_delete_at || 'NULL');
    });

    // Check if case_id is in data
    var reportsWithCaseId = reports.filter(function(r) { return r.case_id; });
    console.log('\n=== Summary ===');
    console.log('Reports with case_id:', reportsWithCaseId.length, '/', reports.length);

    if (reportsWithCaseId.length === 0) {
      console.error('❌ PROBLEM: No reports have case_id in frontend data!');
      console.log('Possible causes:');
      console.log('1. Backend SQL query not updated (needs case_id in SELECT)');
      console.log('2. PHP opcache/server cache not cleared');
      console.log('3. Browser cache not cleared');
    } else {
      console.log('✓ Backend sends case_id correctly');
      console.log('\nIf badge still doesn\'t show, check:');
      console.log('1. CSS file loaded (.jg-case-id-badge class)');
      console.log('2. JavaScript creates badge HTML (search for "caseIdBadge")');
      console.log('3. Badge HTML is inserted into modal header');
    }
  }
}

// Test status change
console.log('\n=== Status Change Test ===');
console.log('To test status save:');
console.log('1. Open a report modal');
console.log('2. Click "Zmień status"');
console.log('3. Select "Wymaga lepszego udokumentowania"');
console.log('4. Open browser Network tab (F12 → Network)');
console.log('5. Click "Zapisz"');
console.log('6. Look for jg_admin_change_status request');
console.log('7. Check Response - should have: report_status: "needs_better_documentation"');
console.log('\nIf response shows different status, backend validation may be rejecting it.');

console.log('\n=== END DEBUG ===');
