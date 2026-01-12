/**
 * DEBUG: Test average time spent tracking - IMPROVED VERSION
 * Run this in browser console on map page
 */

console.log('=== AVG TIME SPENT DIAGNOSTIC ===\n');

// Step 1: Check if map data is loaded
console.log('Step 1: Checking if map data is loaded...');
if (typeof window.ALL === 'undefined') {
  console.error('❌ window.ALL is undefined - map data not loaded yet!');
  console.log('Please wait for map to fully load and try again.');
  throw new Error('Map data not loaded');
}
console.log('✅ window.ALL exists, found ' + window.ALL.length + ' pins\n');

// Step 2: Check if there are any sponsored pins
console.log('Step 2: Looking for sponsored pins...');
var sponsoredPins = window.ALL.filter(function(p) { return p.sponsored; });
console.log('Found ' + sponsoredPins.length + ' sponsored pins');

if (sponsoredPins.length === 0) {
  console.error('❌ NO SPONSORED PINS FOUND!');
  console.log('\nHow to create a sponsored pin:');
  console.log('1. Go to: Admin Panel → Pinezki');
  console.log('2. Edit any pin');
  console.log('3. Check ☑️ "Promowane" checkbox');
  console.log('4. Save and refresh map');
  throw new Error('No sponsored pins');
}

// List all sponsored pins
console.log('\nSponsored pins:');
sponsoredPins.forEach(function(p, i) {
  console.log((i+1) + '. ID: ' + p.id + ' - "' + p.title + '"');
  if (p.stats) {
    console.log('   Views: ' + (p.stats.views || 0) + ', Avg time: ' + (p.stats.avg_time_spent || 0) + 's');
  } else {
    console.log('   Stats: N/A');
  }
});

var testPin = sponsoredPins[0];
console.log('\nUsing pin: "' + testPin.title + '" (ID: ' + testPin.id + ')');

// Step 3: Check if openDetails function exists
console.log('\nStep 3: Checking if openDetails function exists...');
if (typeof openDetails !== 'function') {
  console.error('❌ openDetails function not found!');
  throw new Error('openDetails function not found');
}
console.log('✅ openDetails function exists\n');

// Step 4: Intercept fetch to monitor trackStat calls
console.log('Step 4: Setting up fetch interceptor...');
var originalFetch = window.fetch;
var trackStatCalls = [];

window.fetch = function(url, options) {
  if (url && url.includes('admin-ajax.php') && options && options.body) {
    var body = options.body instanceof URLSearchParams ? options.body : new URLSearchParams(options.body);
    if (body.get('action') === 'jg_track_stat') {
      var call = {
        action_type: body.get('action_type'),
        point_id: body.get('point_id'),
        time_spent: body.get('time_spent'),
        timestamp: new Date().toLocaleTimeString()
      };
      trackStatCalls.push(call);
      console.log('📤 [' + call.timestamp + '] trackStat: ' + call.action_type +
                  (call.time_spent ? ' (' + call.time_spent + 's)' : ''));
    }
  }
  return originalFetch.apply(this, arguments);
};
console.log('✅ Fetch interceptor active\n');

// Step 5: Open modal
console.log('Step 5: Opening modal for pin "' + testPin.title + '"...');
console.log('Will wait 10 seconds then close...\n');

try {
  openDetails(testPin);
  console.log('✅ Modal opened\n');
} catch(e) {
  console.error('❌ Error opening modal: ' + e.message);
  window.fetch = originalFetch;
  throw e;
}

// Step 6: Wait 10 seconds then close
setTimeout(function() {
  console.log('⏰ 10 seconds elapsed, closing modal...');

  var closeBtn = document.querySelector('#dlg-close');
  if (!closeBtn) {
    console.error('❌ Close button not found!');
    window.fetch = originalFetch;
    return;
  }

  closeBtn.click();
  console.log('✅ Modal closed\n');

  // Wait 2 seconds for async requests
  setTimeout(function() {
    console.log('=== RESULTS ===\n');
    console.log('Total trackStat calls: ' + trackStatCalls.length);

    if (trackStatCalls.length === 0) {
      console.error('❌ NO trackStat calls were made!');
      console.log('\nPossible causes:');
      console.log('1. Pin is not actually marked as sponsored in database');
      console.log('2. trackStat function is not defined');
      console.log('3. JavaScript error prevented execution');
      window.fetch = originalFetch;
      return;
    }

    console.log('\nCalls made:');
    trackStatCalls.forEach(function(c, i) {
      console.log((i+1) + '. [' + c.timestamp + '] ' + c.action_type +
                  (c.time_spent ? ' - ' + c.time_spent + ' seconds' : ''));
    });

    var timeSpentCall = trackStatCalls.find(function(c) { return c.action_type === 'time_spent'; });

    if (!timeSpentCall) {
      console.error('\n❌ time_spent was NOT sent to server!');
      console.log('\nCheck if time_spent tracking code is present in dlg-close.onclick');
      window.fetch = originalFetch;
      return;
    }

    console.log('\n✅ time_spent was sent: ' + timeSpentCall.time_spent + ' seconds');
    console.log('\nFetching fresh stats from server to verify update...');

    // Fetch fresh stats
    fetch(window.CFG.ajax, {
      method: 'POST',
      body: new URLSearchParams({
        action: 'jg_get_point_stats',
        point_id: testPin.id,
        _ajax_nonce: window.CFG.nonce
      }),
      credentials: 'same-origin'
    })
    .then(function(r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function(data) {
      console.log('\n=== SERVER RESPONSE ===');

      if (!data.success) {
        console.error('❌ Server returned error: ' + (data.data ? data.data.message : 'Unknown'));
        window.fetch = originalFetch;
        return;
      }

      if (!data.data.stats) {
        console.error('❌ No stats in response!');
        console.log('Response:', data);
        window.fetch = originalFetch;
        return;
      }

      var oldAvg = testPin.stats ? testPin.stats.avg_time_spent : 0;
      var newAvg = data.data.stats.avg_time_spent;

      console.log('Old avg_time_spent: ' + oldAvg + ' seconds');
      console.log('New avg_time_spent: ' + newAvg + ' seconds');
      console.log('Views: ' + data.data.stats.views);

      if (newAvg !== oldAvg) {
        console.log('\n✅ SUCCESS! avg_time_spent WAS UPDATED!');
        console.log('Difference: ' + (newAvg - oldAvg) + ' seconds');
      } else {
        console.error('\n❌ FAILED! avg_time_spent DID NOT CHANGE!');
        console.log('\nThis means:');
        console.log('1. Request reached server ✅');
        console.log('2. Server accepted time_spent value ✅');
        console.log('3. BUT database was NOT updated ❌');
        console.log('\nCheck WordPress debug.log for SQL errors.');
      }

      console.log('\n=== DIAGNOSTIC COMPLETE ===');
      window.fetch = originalFetch;
    })
    .catch(function(err) {
      console.error('❌ Error fetching stats: ' + err.message);
      console.log('\nThis could mean:');
      console.log('1. Network error');
      console.log('2. Server is not responding');
      console.log('3. Nonce expired');
      window.fetch = originalFetch;
    });

  }, 2000);

}, 10000);

console.log('Diagnostic started. Modal will auto-close in 10 seconds...\n');
