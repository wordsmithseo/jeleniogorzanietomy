/**
 * JG Interactive Map - Frontend JavaScript
 * Version: 3.0.0
 */

(function($) {
  'use strict';

  // Register Service Worker for advanced caching
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
      navigator.serviceWorker.register('/wp-content/plugins/jg-interactive-map/assets/js/service-worker.js')
        .then(function(registration) {
          console.log('[JG MAP] Service Worker registered:', registration.scope);
        })
        .catch(function(error) {
          console.log('[JG MAP] Service Worker registration failed:', error);
        });
    });
  }

  var loadingEl = document.getElementById('jg-map-loading');
  var errorEl = document.getElementById('jg-map-error');
  var errorMsg = document.getElementById('error-msg');

  function showError(msg) {
    console.error('[JG MAP]', msg);
    if (loadingEl) loadingEl.style.display = 'none';
    if (errorEl) errorEl.style.display = 'block';
    if (errorMsg) errorMsg.textContent = msg;
  }

  function hideLoading() {
    if (loadingEl) loadingEl.style.display = 'none';
  }

  function wait(cb, maxAttempts) {
    var attempts = 0;
    var interval = setInterval(function() {
      attempts++;

      if (window.L && L.map && L.markerClusterGroup) {
        clearInterval(interval);
        hideLoading();
        console.log('[JG MAP] Wszystkie biblioteki załadowane');
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

  wait(init, 100);

  function init() {
    try {
      var CFG = window.JG_MAP_CFG || {};
      if (!CFG.ajax || !CFG.nonce) {
        showError('Brak konfiguracji JG_MAP_CFG');
        return;
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
      var lightbox = document.getElementById('jg-map-lightbox');

      if (!elMap) {
        showError('Nie znaleziono #jg-map');
        return;
      }

      if ((elMap.offsetHeight || 0) < 50) elMap.style.minHeight = '520px';

      function qs(s, p) {
        return (p || document).querySelector(s);
      }

      function esc(s) {
        s = String(s || '');
        return s.replace(/[&<>"']/g, function(m) {
          return {"&":"&amp;", "<":"&lt;", ">":"&gt;", '"':"&quot;", "'":"&#39;"}[m];
        });
      }

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
      }

      function close(bg) {
        if (!bg) return;
        var c = qs('.jg-modal, .jg-lightbox', bg);
        if (c) {
          c.className = c.className.replace(/\bjg-modal--\w+/g, '');
          if (!c.classList.contains('jg-modal') && !c.classList.contains('jg-lightbox')) {
            c.className = 'jg-modal';
          }
        }
        bg.style.display = 'none';
      }

      [modalAdd, modalView, modalReport, modalReportsList, modalEdit, modalAuthor, modalStatus, lightbox].forEach(function(bg) {
        if (!bg) return;
        bg.addEventListener('click', function(e) {
          if (e.target === bg) close(bg);
        });
      });

      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          [modalAdd, modalView, modalReport, modalReportsList, modalEdit, modalAuthor, modalStatus, lightbox].forEach(close);
        }
      });

      var lat = (CFG.defaults && typeof CFG.defaults.lat === 'number') ? CFG.defaults.lat : 50.904;
      var lng = (CFG.defaults && typeof CFG.defaults.lng === 'number') ? CFG.defaults.lng : 15.734;
      var zoom = (CFG.defaults && typeof CFG.defaults.zoom === 'number') ? CFG.defaults.zoom : 13;

      // Override from data attributes if present
      if (elMap.dataset.lat) lat = parseFloat(elMap.dataset.lat);
      if (elMap.dataset.lng) lng = parseFloat(elMap.dataset.lng);
      if (elMap.dataset.zoom) zoom = parseInt(elMap.dataset.zoom);

      // Define bounds for Jelenia Góra region (stricter)
      var southWest = L.latLng(50.82, 15.62);
      var northEast = L.latLng(50.96, 15.82);
      var bounds = L.latLngBounds(southWest, northEast);

      var map = L.map(elMap, {
        zoomControl: true,
        scrollWheelZoom: true,
        minZoom: 12,
        maxZoom: 18,
        maxBounds: bounds,
        maxBoundsViscosity: 1.0
      }).setView([lat, lng], zoom);

      // Enforce bounds strictly - reset view if user tries to go outside
      map.on('drag', function() {
        map.panInsideBounds(bounds, { animate: false });
      });

      map.on('zoomend', function() {
        if (!bounds.contains(map.getCenter())) {
          map.panInsideBounds(bounds, { animate: true });
        }
      });

      var tileLayer = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
        crossOrigin: true
      });

      tileLayer.addTo(map);

      var cluster = null;
      var markers = [];
      var clusterReady = false;
      var pendingData = null;

      function showMap() {
        if (elMap) {
          elMap.style.opacity = '1';
          inv();
        }
      }

      map.whenReady(function() {
        setTimeout(function() {
          try {
            cluster = L.markerClusterGroup({
              showCoverageOnHover: false,
              maxClusterRadius: 50,
              spiderfyOnMaxZoom: true,
              zoomToBoundsOnClick: true,
              disableClusteringAtZoom: 16,
              spiderfyDistanceMultiplier: 2,
              animate: true,
              animateAddingMarkers: true
            });

            map.addLayer(cluster);
            clusterReady = true;

            if (markers.length > 0) {
              markers.forEach(function(m) {
                try {
                  m.off();
                  if (map.hasLayer(m)) map.removeLayer(m);
                } catch (e) {}
              });
              markers = [];

              if (pendingData && pendingData.length > 0) {
                setTimeout(function() { draw(pendingData); }, 300);
              }
            }
          } catch (e) {
            cluster = null;
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

      var lastSubmitTime = 0;
      var FLOOD_DELAY = 60000;
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
          alert('Przybliż mapę maksymalnie (zoom ' + MIN_ZOOM_FOR_ADD + '+)!');
          return;
        }

        if (mapClickTimeout) clearTimeout(mapClickTimeout);

        mapClickTimeout = setTimeout(function() {
          if (!CFG.isLoggedIn) {
            if (confirm('Musisz być zalogowany. Przejść do logowania?')) {
              window.location.href = CFG.loginUrl;
            }
            return;
          }

          var now = Date.now();
          if (lastSubmitTime > 0 && (now - lastSubmitTime) < FLOOD_DELAY) {
            var sec = Math.ceil((FLOOD_DELAY - (now - lastSubmitTime)) / 1000);
            alert('Poczekaj jeszcze ' + sec + ' sekund.');
            return;
          }

          var lat = e.latlng.lat.toFixed(6);
          var lng = e.latlng.lng.toFixed(6);

          var formHtml = '<header><h3>Dodaj nowe miejsce</h3><button class="jg-close" id="add-close">&times;</button></header>' +
            '<form id="add-form" class="jg-grid cols-2">' +
            '<input type="hidden" name="lat" value="' + lat + '">' +
            '<input type="hidden" name="lng" value="' + lng + '">' +
            '<label>Tytuł* <input name="title" required placeholder="Nazwa miejsca" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px"></label>' +
            '<label>Typ* <select name="type" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' +
            '<option value="zgloszenie">Zgłoszenie</option>' +
            '<option value="ciekawostka">Ciekawostka</option>' +
            '<option value="miejsce">Miejsce</option>' +
            '</select></label>' +
            '<label class="cols-2">Opis <textarea name="content" rows="4" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px"></textarea></label>' +
            '<label class="cols-2"><input type="checkbox" name="public_name"> Pokaż moją nazwę użytkownika</label>' +
            '<label class="cols-2">Zdjęcia (max 6) <input type="file" name="images" multiple accept="image/*" style="width:100%;padding:8px"></label>' +
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

          form.onsubmit = function(e) {
            e.preventDefault();
            msg.textContent = 'Wysyłanie...';

            var fd = new FormData(form);
            fd.append('action', 'jg_submit_point');
            fd.append('_ajax_nonce', CFG.nonce);

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

              lastSubmitTime = Date.now();

              msg.textContent = 'Wysłano do moderacji! Odświeżanie...';
              msg.style.color = '#15803d';
              form.reset();

              // Immediate refresh for better UX
              refreshData(true).then(function() {
                console.log('[JG MAP] Dane odświeżone po dodaniu punktu');
                msg.textContent = 'Wysłano do moderacji! Miejsce pojawi się po zaakceptowaniu.';
                setTimeout(function() {
                  close(modalAdd);
                }, 800);
              }).catch(function(err) {
                console.error('[JG MAP] Błąd odświeżania:', err);
                setTimeout(function() {
                  close(modalAdd);
                }, 1000);
              });
            })
            .catch(function(err) {
              msg.textContent = err.message || 'Błąd';
              msg.style.color = '#b91c1c';
            });
          };
        }, 200);
      });

      function iconFor(p) {
        var sponsored = !!p.sponsored;
        var isPending = !!p.is_pending;
        var isEdit = !!p.is_edit;
        var hasReports = (CFG.isAdmin && p.reports_count > 0);

        // Larger pins for better visibility - sponsored even bigger
        var size = sponsored ? [56, 56] : [36, 36];
        var anchor = [size[0] / 2, size[1] / 2];
        var c = 'jg-pin';

        if (p.type === 'ciekawostka') c += ' jg-pin--ciekawostka';
        if (p.type === 'miejsce') c += ' jg-pin--miejsce';
        if (p.sponsored) c += ' jg-pin--sponsored';
        if (isPending) c += ' jg-pin--pending';
        if (isEdit) c += ' jg-pin--edit';
        if (hasReports) c += ' jg-pin--reported';

        var lbl = (p.type === 'ciekawostka' ? 'i' : (p.type === 'miejsce' ? 'M' : '!'));

        var labelClass = sponsored ? 'jg-marker-label jg-marker-label--sponsored' : 'jg-marker-label';
        if (isPending) labelClass += ' jg-marker-label--pending';
        if (isEdit) labelClass += ' jg-marker-label--edit';

        var suffix = '';
        if (isEdit) suffix = ' (edycja)';
        else if (isPending) suffix = ' (oczekuje)';

        var labelHtml = '<span class="' + labelClass + '">' + esc(p.title || 'Bez nazwy') + suffix + '</span>';

        // Build icon HTML with reports counter
        var reportsHtml = '';
        if (hasReports) {
          reportsHtml = '<span class="jg-reports-counter">' + p.reports_count + '</span>';
        }

        var iconHtml = '<span class="jg-pin-inner">' + lbl + '</span>' + reportsHtml + labelHtml;

        return L.divIcon({
          className: c,
          html: iconHtml,
          iconSize: size,
          iconAnchor: anchor,
          popupAnchor: [0, -10]
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

        console.log('[JG MAP] API call:', action, 'nonce:', CFG.nonce);

        return fetch(CFG.ajax, {
          method: 'POST',
          body: fd,
          credentials: 'same-origin'
        })
        .then(function(r) {
          console.log('[JG MAP] Response status:', r.status);
          return r.text();
        })
        .then(function(t) {
          console.log('[JG MAP] Response text:', t.substring(0, 500));
          var j = null;
          try {
            j = JSON.parse(t);
          } catch (e) {
            console.error('[JG MAP] JSON parse error:', e);
            console.error('[JG MAP] Raw response:', t);
          }
          if (!j || j.success === false) {
            var errMsg = (j && j.data && (j.data.message || j.data.error)) || 'Błąd';
            console.error('[JG MAP] API error:', errMsg, j);
            throw new Error(errMsg);
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

      var ALL = [];
      var lastModified = 0;
      var CACHE_KEY = 'jg_map_cache';
      var CACHE_VERSION_KEY = 'jg_map_cache_version';

      // Try to load from cache
      function loadFromCache() {
        try {
          var cached = localStorage.getItem(CACHE_KEY);
          var cachedVersion = localStorage.getItem(CACHE_VERSION_KEY);
          if (cached && cachedVersion) {
            var data = JSON.parse(cached);
            lastModified = parseInt(cachedVersion);
            console.log('[JG MAP] Loaded from cache:', data.length, 'points, version:', lastModified);
            return data;
          }
        } catch (e) {
          console.error('[JG MAP] Cache load error:', e);
        }
        return null;
      }

      // Save to cache
      function saveToCache(data, version) {
        try {
          localStorage.setItem(CACHE_KEY, JSON.stringify(data));
          localStorage.setItem(CACHE_VERSION_KEY, version.toString());
          lastModified = version;
          console.log('[JG MAP] Saved to cache:', data.length, 'points, version:', version);
        } catch (e) {
          console.error('[JG MAP] Cache save error:', e);
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
        console.log('[JG MAP] refreshData() called, current points:', ALL.length, 'force:', force);

        // If not forced, check for updates first
        if (!force) {
          return checkForUpdates().then(function(updateInfo) {
            if (!updateInfo.hasUpdates) {
              console.log('[JG MAP] No updates available, skipping refresh');

              // Update pending count in title for moderators
              if (CFG.isAdmin && updateInfo.pendingCount > 0) {
                document.title = '(' + updateInfo.pendingCount + ') ' + document.title.replace(/^\(\d+\)\s*/, '');
              }

              return ALL;
            }

            console.log('[JG MAP] Updates available, fetching...');
            return fetchAndProcessPoints(updateInfo.lastModified);
          });
        }

        return fetchAndProcessPoints();
      }

      function fetchAndProcessPoints(version) {
        return fetchPoints().then(function(data) {
          console.log('[JG MAP] Fetched', data ? data.length : 0, 'points from server');

          ALL = (data || []).map(function(r) {
            return {
              id: r.id,
              title: r.title || '',
              excerpt: r.excerpt || '',
              content: r.content || '',
              lat: +r.lat,
              lng: +r.lng,
              type: r.type || 'zgloszenie',
              promo: !!r.promo,
              status: r.status || '',
              status_label: r.status_label || '',
              report_status: r.report_status || '',
              report_status_label: r.report_status_label || '',
              author_id: +(r.author_id || 0),
              author_name: (r.author_name || ''),
              author_hidden: !!r.author_hidden,
              images: (r.images || []),
              votes: +(r.votes || 0),
              my_vote: (r.my_vote || ''),
              date: r.date || null,
              admin: r.admin || null,
              admin_note: r.admin_note || '',
              is_pending: !!r.is_pending,
              is_edit: !!r.is_edit,
              edit_info: r.edit_info || null,
              reports_count: +(r.reports_count || 0)
            };
          });

          // Save to cache
          if (version) {
            saveToCache(ALL, version);
          }

          console.log('[JG MAP] Processed', ALL.length, 'points, calling apply(true) to skip fitBounds');
          apply(true); // Skip fitBounds on refresh to preserve user's view
          console.log('[JG MAP] apply() completed');
          return ALL;
        });
      }

      var isInitialLoad = true; // Track if this is the first load

      function draw(list, skipFitBounds) {
        console.log('[JG MAP] draw() wywołane, punktów:', list ? list.length : 0, 'skipFitBounds:', skipFitBounds);

        if (!list || list.length === 0) {
          showMap();
          return;
        }

        if (!cluster) {
          console.log('[JG MAP] Tworzę cluster...');
          try {
            cluster = L.markerClusterGroup({
              showCoverageOnHover: false,
              maxClusterRadius: 50,
              spiderfyOnMaxZoom: true,
              zoomToBoundsOnClick: true,
              disableClusteringAtZoom: 16,
              spiderfyDistanceMultiplier: 2,
              animate: true,
              animateAddingMarkers: true,
              iconCreateFunction: function(cluster) {
                // Default cluster icon
                var childCount = cluster.getChildCount();
                var c = ' marker-cluster-';
                if (childCount < 10) {
                  c += 'small';
                } else if (childCount < 100) {
                  c += 'medium';
                } else {
                  c += 'large';
                }
                return L.divIcon({
                  html: '<div><span>' + childCount + '</span></div>',
                  className: 'marker-cluster' + c,
                  iconSize: L.point(40, 40)
                });
              }
            });

            map.addLayer(cluster);
            clusterReady = true;
          } catch (e) {
            console.error('[JG MAP] Błąd tworzenia clustera:', e);
            clusterReady = false;
          }
        } else {
          try {
            cluster.clearLayers();
          } catch (e) {}
        }

        // Remove all existing promo markers from map first
        map.eachLayer(function(layer) {
          if (layer.options && layer.options.isPromo) {
            map.removeLayer(layer);
          }
        });

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

        console.log('[JG MAP] Prawidłowych punktów:', validPoints);

        var addedCount = 0;

        list.forEach(function(p) {
          if (!p.lat || !p.lng) return;

          try {
            var lat = parseFloat(p.lat);
            var lng = parseFloat(p.lng);

            if (isNaN(lat) || isNaN(lng)) return;

            // Create marker with special option for promo
            var markerOptions = {
              icon: iconFor(p),
              isPromo: !!p.sponsored
            };

            var m = L.marker([lat, lng], markerOptions);

            (function(point) {
              m.on('click', function(e) {
                if (e && e.originalEvent) e.originalEvent.stopPropagation();
                L.DomEvent.stopPropagation(e);
                openDetails(point);
              });
            })(p);

            // Promo markers NEVER go into cluster - always added directly to map
            if (p.sponsored) {
              m.addTo(map);
              m.setZIndexOffset(10000); // Always on top
              console.log('[JG MAP] Added sponsored marker:', p.title);
            } else if (clusterReady && cluster) {
              cluster.addLayer(m);
            } else {
              m.addTo(map);
              markers.push(m);
            }
            addedCount++;
          } catch (e) {
            console.error('[JG MAP] Błąd dodawania markera:', e);
          }
        });

        console.log('[JG MAP] Dodano markerów:', addedCount);

        // Only fit bounds on initial load, not on refresh
        if (!skipFitBounds && isInitialLoad && bounds.length > 0) {
          setTimeout(function() {
            try {
              var leafletBounds = L.latLngBounds(bounds);
              // Show more points unclustered - use zoom 14 max
              var maxZoom = 14;

              map.fitBounds(leafletBounds, {
                padding: [50, 50],
                maxZoom: maxZoom,
                animate: false
              });

              console.log('[JG MAP] Initial fitBounds wykonany, zoom:', map.getZoom());
              isInitialLoad = false;
            } catch (e) {
              console.error('[JG MAP] Błąd fitBounds:', e);
            }

            showMap();
            hideLoading();
          }, 400);
        } else {
          showMap();
          hideLoading();
        }
      }

      function chip(p) {
        var h = '';
        if (p.sponsored) h += '<span class="jg-sponsored-tag">MIEJSCE SPONSOROWANE</span>';

        if (p.type === 'zgloszenie' && p.report_status) {
          var statusClass = 'jg-status-badge--' + p.report_status;
          h += '<span class="jg-status-badge ' + statusClass + '">' + esc(p.report_status_label || p.report_status) + '</span>';
        }

        return h;
      }

      function colorForVotes(n) {
        if (n > 100) return 'color:#b58900;font-weight:800';
        if (n > 0) return 'color:#15803d;font-weight:700';
        if (n < 0) return 'color:#b91c1c;font-weight:700';
        return 'color:#111';
      }

      function openLightbox(src) {
        open(lightbox, '<button class="jg-lb-close" id="lb-close">Zamknij</button><img src="' + esc(src) + '" alt="">');
        var b = qs('#lb-close', lightbox);
        if (b) b.onclick = function() {
          close(lightbox);
        };
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

      function openReportModal(p) {
        open(modalReport, '<header><h3>Zgłoś do moderacji</h3><button class="jg-close" id="rpt-close">&times;</button></header><form id="report-form" class="jg-grid"><input type="email" name="email" placeholder="Twój e-mail (opcjonalnie)" style="padding:8px;border:1px solid #ddd;border-radius:8px"><textarea name="reason" rows="3" placeholder="Powód (opcjonalnie)" style="padding:8px;border:1px solid #ddd;border-radius:8px"></textarea><div style="display:flex;gap:8px;justify-content:flex-end"><button class="jg-btn" type="submit">Zgłoś</button></div><div id="report-msg" style="font-size:12px;color:#555"></div></form>');
        qs('#rpt-close', modalReport).onclick = function() {
          close(modalReport);
        };

        var f = qs('#report-form', modalReport);
        var msg = qs('#report-msg', modalReport);

        f.onsubmit = function(e) {
          e.preventDefault();
          msg.textContent = 'Wysyłanie...';
          reportPoint({
            post_id: p.id,
            email: (f.email.value || ''),
            reason: (f.reason.value || '')
          })
          .then(function() {
            msg.textContent = 'Dziękujemy!';
            f.reset();
            setTimeout(function() {
              close(modalReport);
            }, 900);
          })
          .catch(function(err) {
            msg.textContent = (err && err.message) || 'Błąd';
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
            '<div style="display:flex;gap:8px;justify-content:flex-end">' +
            '<button class="jg-btn jg-btn--ghost" id="btn-keep">Pozostaw</button>' +
            '<button class="jg-btn jg-btn--danger" id="btn-remove">Usuń</button>' +
            '</div>' +
            '<div id="handle-msg" style="margin-top:8px;font-size:12px"></div>' +
            '</div>' +
            '</div>';

          holder.innerHTML = html;

          var reasonField = qs('#admin-reason', modalReportsList);
          var handleMsg = qs('#handle-msg', modalReportsList);

          qs('#btn-keep', modalReportsList).onclick = function() {
            if (!confirm('Pozostawić miejsce? Zgłoszenia zostaną usunięte.')) return;

            this.disabled = true;
            handleMsg.textContent = 'Przetwarzanie...';

            handleReports({
              post_id: p.id,
              action_type: 'keep',
              reason: reasonField.value
            })
            .then(function(result) {
              close(modalReportsList);
              return refreshData(true);
            })
            .then(function() {
              console.log('[JG MAP] Reports handled (keep), data refreshed');
            })
            .catch(function(err) {
              handleMsg.textContent = err.message || 'Błąd';
              handleMsg.style.color = '#b91c1c';
              this.disabled = false;
            }.bind(this));
          };

          qs('#btn-remove', modalReportsList).onclick = function() {
            if (!confirm('Usunąć miejsce?')) return;

            this.disabled = true;
            handleMsg.textContent = 'Przetwarzanie...';

            handleReports({
              post_id: p.id,
              action_type: 'remove',
              reason: reasonField.value
            })
            .then(function(result) {
              close(modalReportsList);
              close(modalView);
              return refreshData(true);
            })
            .then(function() {
              console.log('[JG MAP] Reports handled (remove), data refreshed');
            })
            .catch(function(err) {
              handleMsg.textContent = err.message || 'Błąd';
              handleMsg.style.color = '#b91c1c';
              this.disabled = false;
            }.bind(this));
          };

        }).catch(function() {
          qs('#reports-content', modalReportsList).innerHTML = '<p style="color:#b91c1c">Błąd.</p>';
        });
      }

      function openEditModal(p) {
        var contentText = p.content ? p.content.replace(/<\/?[^>]+(>|$)/g, "") : (p.excerpt || '');
        open(modalEdit, '<header><h3>Edytuj</h3><button class="jg-close" id="edt-close">&times;</button></header><form id="edit-form" class="jg-grid cols-2"><label>Tytuł* <input name="title" required value="' + esc(p.title || '') + '" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px"></label><label>Typ* <select name="type" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px"><option value="zgloszenie"' + (p.type === 'zgloszenie' ? ' selected' : '') + '>Zgłoszenie</option><option value="ciekawostka"' + (p.type === 'ciekawostka' ? ' selected' : '') + '>Ciekawostka</option><option value="miejsce"' + (p.type === 'miejsce' ? ' selected' : '') + '>Miejsce</option></select></label><label class="cols-2">Opis <textarea name="content" rows="6" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">' + contentText + '</textarea></label><div class="cols-2" style="display:flex;gap:8px;justify-content:flex-end"><button type="button" class="jg-btn jg-btn--ghost" id="edt-cancel">Anuluj</button><button type="submit" class="jg-btn">Zapisz</button></div><div id="edit-msg" class="cols-2" style="font-size:12px"></div></form>');

        qs('#edt-close', modalEdit).onclick = function() {
          close(modalEdit);
        };

        qs('#edt-cancel', modalEdit).onclick = function() {
          close(modalEdit);
        };

        var form = qs('#edit-form', modalEdit);
        var msg = qs('#edit-msg', modalEdit);

        form.onsubmit = function(e) {
          e.preventDefault();
          msg.textContent = 'Zapisywanie...';
          var fd = {
            post_id: p.id,
            title: form.title.value.trim(),
            type: form.type.value,
            content: form.content.value
          };
          if (!fd.title) {
            form.title.focus();
            msg.textContent = 'Podaj tytuł.';
            msg.style.color = '#b91c1c';
            return;
          }
          updatePoint(fd).then(function() {
            msg.textContent = 'Zaktualizowano.';
            setTimeout(function() {
              close(modalEdit);
              refreshData(true).then(function() {
                alert('Wysłano do moderacji. Zmiany będą widoczne po zaakceptowaniu.');
              });
            }, 300);
          }).catch(function(err) {
            msg.textContent = (err && err.message) || 'Błąd';
            msg.style.color = '#b91c1c';
          });
        };
      }

      function openPromoModal(p) {
        var currentPromoUntil = p.sponsored_until || '';
        var promoDateValue = '';

        if (currentPromoUntil && currentPromoUntil !== 'null') {
          try {
            var d = new Date(currentPromoUntil);
            // Format to YYYY-MM-DDTHH:MM for datetime-local input
            var year = d.getFullYear();
            var month = String(d.getMonth() + 1).padStart(2, '0');
            var day = String(d.getDate()).padStart(2, '0');
            var hours = String(d.getHours()).padStart(2, '0');
            var minutes = String(d.getMinutes()).padStart(2, '0');
            promoDateValue = year + '-' + month + '-' + day + 'T' + hours + ':' + minutes;
          } catch (e) {
            console.error('Error parsing promo date:', e);
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
          '<input type="datetime-local" id="sponsored-until-input" value="' + promoDateValue + '" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-bottom:8px">' +
          '<small style="display:block;color:#666;margin-bottom:16px">Pozostaw puste dla sponsorowania bez limitu czasowego</small>' +
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

        saveBtn.onclick = function() {
          var selectedSponsored = qs('input[name="sponsored_status"]:checked', modalStatus);
          if (!selectedSponsored) {
            msg.textContent = 'Wybierz status sponsorowania';
            msg.style.color = '#b91c1c';
            return;
          }

          var isSponsored = selectedSponsored.value === '1';
          var sponsoredUntil = dateInput.value || '';

          msg.textContent = 'Zapisywanie...';
          msg.style.color = '#666';
          saveBtn.disabled = true;

          // Use new AJAX endpoint for updating sponsored with date
          api('jg_admin_update_sponsored', {
            post_id: p.id,
            is_sponsored: isSponsored ? '1' : '0',
            sponsored_until: sponsoredUntil
          })
            .then(function(result) {
              p.sponsored = !!result.is_sponsored;
              p.sponsored_until = result.sponsored_until || null;
              close(modalStatus);
              close(modalView);
              return refreshData(true);
            })
            .then(function() {
              console.log('[JG MAP] Sponsored updated, data refreshed');
            })
            .catch(function(err) {
              msg.textContent = 'Błąd: ' + (err.message || '?');
              msg.style.color = '#b91c1c';
              saveBtn.disabled = false;
            });
        };
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
          '<input type="radio" name="status" value="reported" ' + (currentStatus === 'reported' ? 'checked' : '') + ' style="width:20px;height:20px">' +
          '<div><strong>Zgłoszone do instytucji</strong></div>' +
          '</label>' +
          '<label style="display:flex;align-items:center;gap:8px;padding:12px;border:2px solid #e5e7eb;border-radius:8px;cursor:pointer">' +
          '<input type="radio" name="status" value="resolved" ' + (currentStatus === 'resolved' ? 'checked' : '') + ' style="width:20px;height:20px">' +
          '<div><strong>Rozwiązane</strong></div>' +
          '</label>' +
          '</div>' +
          '<div style="display:flex;gap:8px;justify-content:flex-end">' +
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

        saveBtn.onclick = function() {
          var selected = qs('input[name="status"]:checked', modalStatus);
          if (!selected) {
            msg.textContent = 'Wybierz status';
            msg.style.color = '#b91c1c';
            return;
          }

          var newStatus = selected.value;
          if (newStatus === currentStatus) {
            close(modalStatus);
            return;
          }

          msg.textContent = 'Zapisywanie...';
          saveBtn.disabled = true;

          adminChangeStatus({ post_id: p.id, new_status: newStatus })
            .then(function(result) {
              p.report_status = result.report_status;
              p.report_status_label = result.report_status_label;
              close(modalStatus);
              close(modalView);
              return refreshData(true);
            })
            .then(function() {
              console.log('[JG MAP] Status changed, data refreshed');
            })
            .catch(function(err) {
              msg.textContent = 'Błąd: ' + (err.message || '?');
              msg.style.color = '#b91c1c';
              saveBtn.disabled = false;
            });
        };
      }

      function openDetails(p) {
        var imgs = Array.isArray(p.images) ? p.images : [];
        var gal = imgs.map(function(img) {
          // Support both old format (string URL) and new format (object with thumb/full)
          var thumbUrl = typeof img === 'object' ? (img.thumb || img.full) : img;
          var fullUrl = typeof img === 'object' ? (img.full || img.thumb) : img;
          return '<img src="' + esc(thumbUrl) + '" data-full="' + esc(fullUrl) + '" alt="" loading="lazy" style="cursor:pointer">';
        }).join('');

        var dateInfo = (p.date && p.date.human) ? '<div class="jg-date-info">Dodano: ' + esc(p.date.human) + '</div>' : '';

        var who = '';
        if (p.author_name && p.author_name.trim() !== '') {
          who = '<div><strong>Autor:</strong> <a href="#" id="btn-author" data-id="' + esc(p.author_id) + '">' + esc(p.author_name) + '</a></div>';
        } else if (p.author_hidden || p.author_id > 0) {
          who = '<div><strong>Autor:</strong> ukryty</div>';
        }

        var adminNote = '';
        if (p.admin_note && p.admin_note.trim()) {
          adminNote = '<div class="jg-admin-note"><div class="jg-admin-note-title">📢 Notatka administratora</div><div class="jg-admin-note-content">' + esc(p.admin_note) + '</div></div>';
        }

        var editInfo = '';
        if (CFG.isAdmin && p.is_edit && p.edit_info) {
          var changes = [];
          if (p.edit_info.prev_title && p.edit_info.prev_title !== p.title) {
            changes.push('<div><strong>Tytuł:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + esc(p.edit_info.prev_title) + '</span><br><span style="color:#16a34a">→ ' + esc(p.title) + '</span></div>');
          }
          if (p.edit_info.prev_type && p.edit_info.prev_type !== p.type) {
            var typeLabels = { zgloszenie: 'Zgłoszenie', ciekawostka: 'Ciekawostka', miejsce: 'Miejsce' };
            changes.push('<div><strong>Typ:</strong><br><span style="text-decoration:line-through;color:#dc2626">' + (typeLabels[p.edit_info.prev_type] || p.edit_info.prev_type) + '</span><br><span style="color:#16a34a">→ ' + (typeLabels[p.type] || p.type) + '</span></div>');
          }
          if (p.edit_info.prev_content && p.edit_info.prev_content !== p.content) {
            var prevContentText = p.edit_info.prev_content.replace(/<\/?[^>]+(>|$)/g, '');
            var newContentText = p.content ? p.content.replace(/<\/?[^>]+(>|$)/g, '') : '';
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

          if (changes.length > 0) {
            editInfo = '<div style="background:#faf5ff;border:2px solid #9333ea;border-radius:8px;padding:12px;margin:16px 0"><div style="font-weight:700;margin-bottom:8px;color:#6b21a8">📝 Zmiany oczekujące:</div>' + changes.join('') + '</div>';
          }
        }

        var reportsWarning = '';
        if (CFG.isAdmin && p.reports_count > 0) {
          reportsWarning = '<div class="jg-reports-warning">' +
            '<div class="jg-reports-warning-title">⚠️ Zgłoszeń: ' + p.reports_count + '</div>' +
            '<button class="jg-btn" id="btn-view-reports" style="margin-top:8px">Zobacz zgłoszenia</button>' +
            '</div>';
        }

        var adminBox = '';
        if (CFG.isAdmin) {
          var adminData = [];
          if (p.admin) {
            adminData.push('<div><strong>Autor:</strong> ' + esc(p.admin.author_name_real || '?') + '</div>');
            adminData.push('<div><strong>Email:</strong> ' + esc(p.admin.author_email || '?') + '</div>');
            if (p.admin.ip && p.admin.ip !== '(brak)' && p.admin.ip.trim() !== '') {
              adminData.push('<div><strong>IP:</strong> ' + esc(p.admin.ip) + '</div>');
            }
          }

          adminData.push('<div><strong>Status:</strong> ' + esc(p.status) + '</div>');

          var controls = '<div class="jg-admin-controls">';

          if (p.is_pending) {
            controls += '<button class="jg-btn" id="btn-approve-point" style="background:#15803d">✓ Akceptuj</button>';
            controls += '<button class="jg-btn" id="btn-reject-point" style="background:#b91c1c">✗ Odrzuć</button>';
          }

          controls += '<button class="jg-btn jg-btn--ghost" id="btn-toggle-sponsored">' + (p.sponsored ? 'Usuń sponsorowanie' : 'Sponsorowane') + '</button>';
          controls += '<button class="jg-btn jg-btn--ghost" id="btn-toggle-author">' + (p.author_hidden ? 'Ujawnij' : 'Ukryj') + ' autora</button>';
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
        var canEdit = (CFG.isAdmin || (CFG.currentUserId > 0 && CFG.currentUserId === +p.author_id));
        var myVote = p.my_vote || '';

        // Don't show voting for promo points
        var voteHtml = '';
        if (!p.sponsored) {
          voteHtml = '<div class="jg-vote"><button id="v-up" ' + (myVote === 'up' ? 'class="active"' : '') + '>⬆️</button><span class="cnt" id="v-cnt" style="' + colorForVotes(+p.votes || 0) + '">' + (p.votes || 0) + '</span><button id="v-down" ' + (myVote === 'down' ? 'class="active"' : '') + '>⬇️</button></div>';
        }

        var html = '<header><h3>' + esc(p.title || 'Szczegóły') + '</h3><button class="jg-close" id="dlg-close">&times;</button></header><div class="jg-grid" style="overflow:auto">' + dateInfo + '<div style="margin-bottom:10px">' + chip(p) + '</div>' + reportsWarning + editInfo + adminNote + (p.content ? ('<div>' + p.content + '</div>') : (p.excerpt ? ('<p>' + esc(p.excerpt) + '</p>') : '')) + (gal ? ('<div class="jg-gallery" style="margin-top:10px">' + gal + '</div>') : '') + (who ? ('<div style="margin-top:10px">' + who + '</div>') : '') + voteHtml + adminBox + '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px">' + (canEdit ? '<button id="btn-edit" class="jg-btn jg-btn--ghost">Edytuj</button>' : '') + '<button id="btn-report" class="jg-btn jg-btn--ghost">Zgłoś</button></div></div>';

        open(modalView, html, { addClass: (promoClass + typeClass).trim() });

        qs('#dlg-close', modalView).onclick = function() {
          close(modalView);
        };

        var g = qs('.jg-gallery', modalView);
        if (g) {
          g.querySelectorAll('img').forEach(function(img) {
            img.addEventListener('click', function() {
              var fullUrl = this.getAttribute('data-full') || this.src;
              openLightbox(fullUrl);
            });
          });
        }

        // Setup voting handlers only if not promo
        if (!p.sponsored) {
          var cnt = qs('#v-cnt', modalView);
          var up = qs('#v-up', modalView);
          var down = qs('#v-down', modalView);

          if (cnt && up && down) {
            function refresh(n, my) {
              cnt.textContent = n;
              cnt.setAttribute('style', colorForVotes(+n || 0));
              up.classList.toggle('active', my === 'up');
              down.classList.toggle('active', my === 'down');
            }

            function doVote(dir) {
              if (!CFG.isLoggedIn) {
                alert('Zaloguj się.');
                return;
              }
              up.disabled = down.disabled = true;
              voteReq({ post_id: p.id, dir: dir })
                .then(function(d) {
                  p.votes = +d.votes || 0;
                  p.my_vote = d.my_vote || '';
                  refresh(p.votes, p.my_vote);
                })
                .catch(function(e) {
                  alert((e && e.message) || 'Błąd');
                })
                .finally(function() {
                  up.disabled = down.disabled = false;
                });
            }

            up.onclick = function() {
              doVote('up');
            };

            down.onclick = function() {
              doVote('down');
            };
          }
        }

        qs('#btn-report', modalView).onclick = function() {
          openReportModal(p);
        };

        if (canEdit) {
          var editBtn = qs('#btn-edit', modalView);
          if (editBtn) editBtn.onclick = function() {
            openEditModal(p);
          };
        }

        var ba = qs('#btn-author', modalView);
        if (ba) {
          ba.addEventListener('click', function(ev) {
            ev.preventDefault();
            openAuthorModal(+this.getAttribute('data-id'), this.textContent);
          });
        }

        if (CFG.isAdmin) {
          var btnViewReports = qs('#btn-view-reports', modalView);
          if (btnViewReports) {
            btnViewReports.onclick = function() {
              openReportsListModal(p);
            };
          }

          var btnAuthor = qs('#btn-toggle-author', modalView);
          var btnStatus = qs('#btn-change-status', modalView);
          var btnNote = qs('#btn-admin-note', modalView);
          var btnApprove = qs('#btn-approve-point', modalView);
          var btnReject = qs('#btn-reject-point', modalView);
          var btnDelete = qs('#btn-delete-point', modalView);

          if (btnApprove) {
            btnApprove.onclick = function() {
              if (!confirm('Zaakceptować?')) return;
              btnApprove.disabled = true;
              btnApprove.textContent = 'Akceptowanie...';

              adminApprovePoint({ post_id: p.id })
                .then(function() {
                  close(modalView);
                  return refreshData(true);
                })
                .then(function() {
                  alert('Zaakceptowano i opublikowano!');
                })
                .catch(function(err) {
                  alert('Błąd: ' + (err.message || '?'));
                  btnApprove.disabled = false;
                  btnApprove.textContent = '✓ Akceptuj';
                });
            };
          }

          if (btnReject) {
            btnReject.onclick = function() {
              var reason = prompt('Powód odrzucenia (zostanie wysłany do autora):');
              if (reason === null) return;

              btnReject.disabled = true;
              btnReject.textContent = 'Odrzucanie...';

              adminRejectPoint({ post_id: p.id, reason: reason })
                .then(function() {
                  close(modalView);
                  return refreshData(true);
                })
                .then(function() {
                  alert('Odrzucono i przeniesiono do kosza.');
                })
                .catch(function(err) {
                  alert('Błąd: ' + (err.message || '?'));
                  btnReject.disabled = false;
                  btnReject.textContent = '✗ Odrzuć';
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
              if (!confirm((p.author_hidden ? 'Ujawnić' : 'Ukryć') + ' autora?')) return;
              btnAuthor.disabled = true;
              btnAuthor.textContent = 'Zapisywanie...';

              adminToggleAuthor({ post_id: p.id })
                .then(function(result) {
                  p.author_hidden = result.author_hidden;
                  // Close modal and refresh data immediately
                  close(modalView);
                  return refreshData(true);
                })
                .then(function() {
                  console.log('[JG MAP] Author visibility toggled, data refreshed');
                })
                .catch(function(err) {
                  alert('Błąd: ' + (err.message || '?'));
                  btnAuthor.disabled = false;
                  btnAuthor.textContent = p.author_hidden ? 'Ujawnij autora' : 'Ukryj autora';
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
              var newNote = prompt('Notatka administratora:', currentNote);
              if (newNote === null) return;

              btnNote.disabled = true;
              btnNote.textContent = 'Zapisywanie...';

              adminUpdateNote({ post_id: p.id, note: newNote })
                .then(function(result) {
                  p.admin_note = newNote;
                  close(modalView);
                  return refreshData(true);
                })
                .then(function() {
                  console.log('[JG MAP] Admin note updated, data refreshed');
                })
                .catch(function(err) {
                  alert('Błąd: ' + (err.message || '?'));
                  btnNote.disabled = false;
                  btnNote.textContent = p.admin_note ? 'Edytuj notatkę' : 'Dodaj notatkę';
                });
            };
          }

          if (btnDelete) {
            btnDelete.onclick = function() {
              if (!confirm('NA PEWNO usunąć to miejsce? Tej operacji nie można cofnąć!')) return;

              btnDelete.disabled = true;
              btnDelete.textContent = 'Usuwanie...';

              adminDeletePoint({ post_id: p.id })
                .then(function() {
                  close(modalView);
                  return refreshData(true);
                })
                .then(function() {
                  alert('Miejsce usunięte trwale!');
                })
                .catch(function(err) {
                  alert('Błąd: ' + (err.message || '?'));
                  btnDelete.disabled = false;
                  btnDelete.textContent = '🗑️ Usuń miejsce';
                });
            };
          }
        }
      }

      function apply(skipFitBounds) {
        var enabled = {};
        var promoOnly = false;
        var searchQuery = '';

        if (elFilters) {
          elFilters.querySelectorAll('input[data-type]').forEach(function(cb) {
            if (cb.checked) enabled[cb.getAttribute('data-type')] = true;
          });
          var pr = elFilters.querySelector('input[data-promo]');
          promoOnly = !!(pr && pr.checked);

          var searchInput = document.getElementById('jg-search-input');
          if (searchInput) {
            searchQuery = searchInput.value.toLowerCase().trim();
          }
        }

        var list = (ALL || []).filter(function(p) {
          // Search filter
          if (searchQuery) {
            var title = (p.title || '').toLowerCase();
            var content = (p.content || '').toLowerCase();
            var excerpt = (p.excerpt || '').toLowerCase();
            if (title.indexOf(searchQuery) === -1 &&
                content.indexOf(searchQuery) === -1 &&
                excerpt.indexOf(searchQuery) === -1) {
              return false;
            }
          }

          // Promo only filter
          if (promoOnly) return p.sponsored;

          // Always show promo places (unless promo-only is active)
          if (p.sponsored) return true;

          // Type filters
          var passType = (Object.keys(enabled).length ? !!enabled[p.type] : true);
          return passType;
        });

        pendingData = list;
        draw(list, skipFitBounds);
      }

      // Setup search input listener
      setTimeout(function() {
        var searchInput = document.getElementById('jg-search-input');
        if (searchInput) {
          console.log('[JG MAP] Search input attached');
          searchInput.addEventListener('input', function() {
            console.log('[JG MAP] Search query:', this.value);
            apply();
          });
        } else {
          console.error('[JG MAP] Search input not found!');
        }
      }, 100);

      // Setup filter listeners
      setTimeout(function() {
        if (elFilters) {
          var allCheckboxes = elFilters.querySelectorAll('input[type="checkbox"]');
          console.log('[JG MAP] Found', allCheckboxes.length, 'filter checkboxes');
          allCheckboxes.forEach(function(cb) {
            cb.addEventListener('change', function() {
              console.log('[JG MAP] Filter changed:', this.getAttribute('data-type') || 'sponsored');
              apply();
            });
          });
        } else {
          console.error('[JG MAP] Filter container not found!');
        }
      }, 100);

      // Try to load from cache first for instant display
      var cachedData = loadFromCache();
      if (cachedData) {
        ALL = cachedData;
        apply();
        console.log('[JG MAP] Displayed cached data, checking for updates...');
      }

      // Then fetch fresh data (or just check for updates)
      refreshData(true)
        .then(function() {
          console.log('[JG MAP] Initial data load complete');
        })
        .catch(function(e) {
          if (!cachedData) {
            showError('Nie udało się pobrać punktów: ' + (e.message || '?'));
          } else {
            console.error('[JG MAP] Update check failed, using cached data:', e);
          }
        });

      // Smart auto-refresh: Check for updates every 15 seconds, only fetch if needed
      var refreshInterval = setInterval(function() {
        console.log('[JG MAP] Auto-refresh triggered - checking for updates');

        refreshData(false).then(function() {
          console.log('[JG MAP] Auto-refresh complete');
        }).catch(function(err) {
          console.error('[JG MAP] Auto-refresh error:', err);
        });
      }, 15000); // 15 seconds

      // Also check for updates when page becomes visible again
      document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
          console.log('[JG MAP] Page visible - checking for updates');
          refreshData(false);
        }
      });

    } catch (e) {
      showError('Błąd: ' + e.message);
    }
  }
})(jQuery);
