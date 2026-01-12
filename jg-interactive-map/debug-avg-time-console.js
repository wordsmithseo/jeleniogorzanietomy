/**
 * DEBUG: Test average time spent tracking
 *
 * HOW TO USE:
 * 1. Find a SPONSORED pin on the map (with "PROMOWANE" badge)
 * 2. Open browser console (F12)
 * 3. Copy-paste this entire script
 * 4. Script will:
 *    - Open the pin modal
 *    - Wait 10 seconds
 *    - Close the modal
 *    - Check if stats_avg_time_spent was updated
 */

(function() {
  console.log('[AVG TIME DEBUG] Starting diagnostic...\n');

  // Find first sponsored pin
  var sponsoredPin = window.ALL ? window.ALL.find(function(p) { return p.sponsored; }) : null;

  if (!sponsoredPin) {
    console.error('[AVG TIME DEBUG] ❌ No sponsored pins found!');
    console.log('[AVG TIME DEBUG] Please promote a pin first:');
    console.log('[AVG TIME DEBUG] Admin Panel → Pinezki → Edit pin → Check "Promowane"');
    return;
  }

  console.log('[AVG TIME DEBUG] ✅ Found sponsored pin: ' + sponsoredPin.title);
  console.log('[AVG TIME DEBUG] Pin ID: ' + sponsoredPin.id);
  console.log('[AVG TIME DEBUG] Current stats_views: ' + (sponsoredPin.stats ? sponsoredPin.stats.views : 'N/A'));
  console.log('[AVG TIME DEBUG] Current stats_avg_time_spent: ' + (sponsoredPin.stats ? sponsoredPin.stats.avg_time_spent : 'N/A') + ' seconds\n');

  console.log('[AVG TIME DEBUG] Opening modal and waiting 10 seconds...');

  // Intercept trackStat calls
  var originalFetch = window.fetch;
  var trackStatCalls = [];

  window.fetch = function(url, options) {
    if (url.includes('admin-ajax.php') && options && options.body) {
      var body = new URLSearchParams(options.body);
      if (body.get('action') === 'jg_track_stat') {
        trackStatCalls.push({
          action_type: body.get('action_type'),
          point_id: body.get('point_id'),
          time_spent: body.get('time_spent'),
          timestamp: Date.now()
        });
        console.log('[AVG TIME DEBUG] 📤 trackStat called: ' + body.get('action_type') +
                    (body.get('time_spent') ? ' (time: ' + body.get('time_spent') + 's)' : ''));
      }
    }
    return originalFetch.apply(this, arguments);
  };

  // Open modal
  if (typeof openDetails === 'function') {
    openDetails(sponsoredPin);
  } else {
    console.error('[AVG TIME DEBUG] ❌ openDetails function not found!');
    window.fetch = originalFetch;
    return;
  }

  // Wait 10 seconds then close
  setTimeout(function() {
    console.log('[AVG TIME DEBUG] 10 seconds passed, closing modal...');

    var closeBtn = document.querySelector('#dlg-close');
    if (closeBtn) {
      closeBtn.click();

      // Wait for response then check results
      setTimeout(function() {
        console.log('\n[AVG TIME DEBUG] === RESULTS ===');
        console.log('[AVG TIME DEBUG] Total trackStat calls: ' + trackStatCalls.length);

        trackStatCalls.forEach(function(call, i) {
          console.log('[AVG TIME DEBUG] Call #' + (i+1) + ': ' + call.action_type +
                      (call.time_spent ? ' (' + call.time_spent + 's)' : ''));
        });

        var timeSpentCall = trackStatCalls.find(function(c) { return c.action_type === 'time_spent'; });

        if (!timeSpentCall) {
          console.error('\n[AVG TIME DEBUG] ❌ time_spent was NOT sent!');
          console.log('[AVG TIME DEBUG] Possible causes:');
          console.log('[AVG TIME DEBUG] 1. Pin is not marked as sponsored');
          console.log('[AVG TIME DEBUG] 2. trackStat function failed');
          console.log('[AVG TIME DEBUG] 3. JavaScript error prevented execution');
        } else {
          console.log('\n[AVG TIME DEBUG] ✅ time_spent was sent: ' + timeSpentCall.time_spent + ' seconds');
          console.log('[AVG TIME DEBUG] Now fetch fresh stats from server...');

          // Fetch fresh stats
          fetch(window.CFG.ajax, {
            method: 'POST',
            body: new URLSearchParams({
              action: 'jg_get_point_stats',
              point_id: sponsoredPin.id,
              _ajax_nonce: window.CFG.nonce
            })
          })
          .then(function(r) { return r.json(); })
          .then(function(data) {
            if (data.success && data.data.stats) {
              var newAvg = data.data.stats.avg_time_spent;
              var oldAvg = sponsoredPin.stats ? sponsoredPin.stats.avg_time_spent : 0;

              console.log('[AVG TIME DEBUG] Old avg_time_spent: ' + oldAvg + 's');
              console.log('[AVG TIME DEBUG] New avg_time_spent: ' + newAvg + 's');

              if (newAvg !== oldAvg) {
                console.log('[AVG TIME DEBUG] ✅ SUCCESS! avg_time_spent was updated!');
              } else {
                console.error('[AVG TIME DEBUG] ❌ FAIL! avg_time_spent did NOT change!');
                console.log('[AVG TIME DEBUG] Check server logs for errors.');
              }
            } else {
              console.error('[AVG TIME DEBUG] ❌ Failed to fetch stats: ' + (data.data ? data.data.message : 'Unknown error'));
            }

            // Restore fetch
            window.fetch = originalFetch;
          })
          .catch(function(err) {
            console.error('[AVG TIME DEBUG] ❌ Error fetching stats: ' + err.message);
            window.fetch = originalFetch;
          });
        }

      }, 2000); // Wait 2s for async requests to complete

    } else {
      console.error('[AVG TIME DEBUG] ❌ Close button not found!');
      window.fetch = originalFetch;
    }
  }, 10000);

})();
