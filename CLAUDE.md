# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

WordPress plugin **JG Interactive Map** for jeleniogorzanietomy.pl — an interactive Leaflet map of Jelenia Góra where registered users add map points (reports, curiosities, places), vote, and earn XP/achievements.

The entire plugin lives in `jg-interactive-map/`. Everything else in the repo root is auxiliary:
- `andata/` — SEO analytics data (CSV/XLSX), not code
- `modals-new/` and `proponowane zmiany/` — design drafts, not deployed

## Deploy

```bash
./ship.sh      # Full workflow: AI-generated commit msg, version bump, rsync deploy, git commit+push
./deploy.sh    # Rsync only (no version bump, no git)
```

`ship.sh` calls Claude CLI (`claude -p`) to suggest a semantic version bump and Polish commit message based on `git diff`. SSH key: `~/.ssh/id_nowy_klucz`, host: `h59.seohost.pl:57185`.

## Tests & linting (run inside `jg-interactive-map/`)

```bash
composer test           # PHPUnit
composer run phpcs      # WordPress coding standards
composer run phpstan    # Static analysis (level 5)
composer run check      # All three
```

## Architecture

### PHP class map

| File | Class | Responsibility |
|------|-------|---------------|
| `jg-interactive-map.php` | `JG_Interactive_Map` | Plugin bootstrap, hook wiring, rewrite rules, SEO URLs |
| `includes/class-database.php` | `JG_Map_Database` | Schema creation, migrations, static table-name accessors |
| `includes/class-ajax-handlers.php` | `JG_Map_Ajax_Handlers` | All `wp_ajax_*` endpoints — point CRUD, voting, reports, categories (~10k lines) |
| `includes/class-admin.php` | `JG_Map_Admin` | Admin panel UI, moderation queue, Heartbeat-based notifications (~8k lines) |
| `includes/class-enqueue.php` | `JG_Map_Enqueue` | Asset registration, top bar, mobile nav bar, footer bar HTML injection |
| `includes/class-shortcode.php` | `JG_Map_Shortcode` | `[jg_map]`, `[jg_map_sidebar]`, `[jg_map_directory]`, `[jg_banner]` |
| `includes/class-sync-manager.php` | `JG_Map_Sync_Manager` | DB-backed real-time sync queue for pin state changes (no transients) |
| `includes/class-levels-achievements.php` | `JG_Map_Levels_Achievements` | XP, user levels, achievements, admin editors |
| `includes/class-challenges.php` | `JG_Map_Challenges` | Weekly challenges system |
| `includes/class-banner-manager.php` | `JG_Map_Banner_Manager` | 728×90 ad banner rotation with impression tracking |
| `includes/class-banner-admin.php` | `JG_Map_Banner_Admin` | Banner admin UI |
| `includes/class-info-bar.php` | `JG_Map_Info_Bar` | Dismissible info bar above nav (stored in WP options) |
| `includes/class-activity-log.php` | `JG_Map_Activity_Log` | Activity logging |
| `includes/class-maintenance.php` | `JG_Map_Maintenance` | Cron-based cleanup tasks |
| `includes/class-slot-keys.php` | `JG_Slot_Keys` | Per-request random CSS class/ID names for promotional slot (ad-blocker evasion) |

### Database tables

All prefixed `wp_jg_map_*`. Use `JG_Map_Database::get_points_table()` etc. — never hardcode table names.

- `wp_jg_map_points` — map points (type, status, lat/lng, slug, author, images, tags, opening_hours, etc.)
- `wp_jg_map_votes` — star ratings per user/point
- `wp_jg_map_reports` — user abuse reports
- `wp_jg_map_history` — edit history with owner-approval workflow
- `wp_jg_map_relevance_votes` — "Is this still relevant?" voting
- `wp_jg_map_point_visits` — visit tracking
- `wp_jg_map_slug_redirects` — 301s when a point's slug changes
- Plus gamification tables: `wp_jg_map_user_achievements`, `wp_jg_map_challenges`, `wp_jg_map_banners`, `wp_jg_map_banner_impressions`, `wp_jg_map_sync_queue`

**Point types:** `zgloszenie` (red pin, infra issue), `ciekawostka` (blue pin, curiosity), `miejsce` (green pin, place)  
**Point statuses:** `pending` → `publish` or `rejected`

### JavaScript files

> **TOKEN BUDGET RULE — `jg-map.js` is ~16k lines / 800KB. NEVER read it whole.**
> Always `grep -n` first to find the relevant function, then `Read` only ±300 lines around it.
> The file is a single IIFE with shared closure state — do not propose splitting it without a bundler (Vite/webpack).

| File | Purpose |
|------|---------|
| `assets/js/jg-map.js` | Main frontend (~16k lines): Leaflet map, pin rendering, detail modals, add/edit forms, filters, GA4 events |
| `assets/js/jg-auth.js` | Login/register modals — loaded on **all pages**, not just the map page |
| `assets/js/jg-sidebar.js` | Sidebar list with filtering, sorting, lazy loading |
| `assets/js/jg-map-ext.js` | Map extensions (additional controls) |
| `assets/js/jg-notifications.js` | Push notification handling |
| `assets/js/jg-onboarding.js` | New-user onboarding flow |
| `assets/js/jg-session-monitor.js` | Session expiry detection |
| `assets/js/jg-banner-admin.js` | Banner admin UI |
| `assets/js/tile-sw.js` | Service worker for map tile caching |

### PHP → JS config bridge

`JG_MAP_CFG` is the central JS config object injected via `wp_localize_script` in `class-enqueue.php`. It carries AJAX URL, nonce, user info, map defaults, and all category definitions. Always reference this object from JS rather than hardcoding values.

### Non-obvious design decisions

- **`_jgNativeReplaceState`** — captured synchronously at the top of `jg-map.js` before GTM loads. GTM wraps `history.replaceState` to fire GA4 `page_view` events; capturing the native version lets us update the URL bar when opening pin modals without triggering a duplicate GTM event. Our manual `gtag('event','page_view')` call in `openDetailsModalContent` is the only source for pin page-views.

- **`JG_Slot_Keys`** — generates random CSS class names and element IDs once per PHP process (cached in static `$k`). Injected as inline `<style>` so ad-blocker lists can't target predictable selectors. `class-enqueue.php` and `class-shortcode.php` must call `JG_Slot_Keys::get()` to share the same keys within one request.

- **Table names via SQL string interpolation** — `$wpdb->prepare()` cannot parameterize table names. The codebase uses `esc_sql($table_name)` + string interpolation for `SHOW COLUMNS FROM` and `ALTER TABLE` queries. This is intentional and safe — don't change to `%s` placeholders.

- **Sync queue is DB-backed** — `class-sync-manager.php` deliberately avoids WordPress transients for the sync queue because transients are unreliable under heavy load. All sync events go to `wp_jg_map_sync_queue`.

- **`jg-auth.js` on all pages** — login/register modals can be triggered from any page (nav bar, map sidebar, etc.), so `jg-auth.js` is enqueued globally, not conditionally.

## Kod - Mapa Drogowa

> Indeks nawigacyjny do chirurgicznej pracy `grep -n` + `sed -n`. Zamiast czytać plik w całości, używaj tych numerów linii jako punktów startu.

### `includes/class-admin.php` (8 867 linii)

| Blok | Linia | Opis |
|------|-------|------|
| `__construct` | 31 | Bootstrap: rejestracja wszystkich hooków |
| `add_admin_bar_notifications` | 87 | Ikonka powiadomień w górnym pasku WP |
| `handle_manual_activate_user` | 186 | POST handler: ręczna aktywacja konta |
| `modify_admin_title` | 228 | Filter: tytuł zakładki przeglądarki |
| `add_admin_menu` | 367 | Rejestracja stron menu w panelu admina |
| `restrict_sidebar_for_non_wp_admins` | 552 | Ukrywanie menu dla nie-adminów WP |
| `render_main_page` | 585 | Strona główna panelu (dashboard) |
| `render_places_page` | 816 | Lista miejsc do moderacji |
| `render_place_row` *(private)* | 1398 | Wiersz tabeli miejsca |
| `render_place_actions` *(private)* | 1445 | Przyciski akcji (zatwierdź/odrzuć) |
| `render_moderation_page` | 1572 | Kolejka moderacji |
| `render_reports_page` | 2354 | Zgłoszenia nadużyć |
| `render_promos_page` | 2407 | Zarządzanie promocjami/slotami |
| `render_all_points_page` | 2590 | Wszystkie punkty (lista zbiorcza) |
| `render_roles_page` | 2687 | Zarządzanie rolami użytkowników |
| `render_deletions_page` | 2879 | Historia usunięć |
| `render_gallery_page` | 3010 | Galeria zdjęć |
| `render_users_page` | 3376 | Lista użytkowników |
| `render_activity_log_page` | 4411 | Dziennik aktywności |
| `render_settings_page` | 4616 | Ustawienia wtyczki |
| `render_report_reasons_page` | 5041 | Powody zgłoszeń |
| `render_filter_reset_card` *(private)* | 5750 | Karta resetu filtrów |
| `heartbeat_received` | 5860 | Heartbeat: odpowiedź AJAX (live notifications) |
| `render_page_header` *(private)* | 6023 | Nagłówek strony admina |
| `enqueue_admin_styles` | 5943 | Rejestracja stylów CSS panelu |
| `render_maintenance_page` | 6138 | Strona konserwacji / crony |
| `render_place_categories_page` | 6302 | Kategorie miejsc |
| `render_curiosity_categories_page` | 6756 | Kategorie ciekawostek |
| `render_xp_editor_page` | 7126 | Edytor punktów XP |
| `render_achievements_editor_page` | 7253 | Edytor osiągnięć |
| `render_challenges_page` | 7423 | Wyzwania tygodniowe |
| `render_tags_page` | 7832 | Tagi punktów |
| `render_nav_menu_page` | 8355 | Edytor menu nawigacyjnego |
| `render_seo_page` | 8700 | Ustawienia SEO |
| `enqueue_admin_bar_script` | 6036 | Skrypt paska admina |

**Hooki zarejestrowane w `__construct` (linia 31):** `admin_menu` (×2), `admin_bar_menu`, `admin_title`, `heartbeat_received`, `admin_enqueue_scripts` (×2), `admin_post_jg_map_activate_user`

---

### `assets/js/jg-map.js` (15 839 linii)

| Blok | Linia | Opis |
|------|-------|------|
| `init()` | 821 | Inicjalizacja całej mapy Leaflet + ładowanie danych |
| `ALL` (dane punktów) | ~5947 | Tablica wszystkich punktów mapy (wypełniana przez AJAX) |
| `addPulsingMarker` | 5757 | Dodaje animowany marker (pulsujący) |
| `voteReq` | 5528 | Funkcja wysyłająca głos (ocena gwiazdkowa) AJAX |
| `doVote` (handler kliknięcia gwiazdki) | 11975 | Obsługa kliknięcia gwiazdki w modalu |
| `removeMarkersById` | 5895 | Usuwa markery z mapy po ID |
| `openDetails` | 10368 | Otwiera modal ze szczegółami punktu |
| `openDetailsModalContent` | 10406 | Wypełnia treść modalu + ręczny `gtag page_view` |
| `initMapCategoryFilters` | 13322 | Inicjalizacja przycisków filtrów kategorii |
| `searchAddressSuggestions` | 14156 | Autouzupełnianie wyszukiwarki adresu (główna mapa) |
| `searchEditAddressSuggestions` | 9418 | Autouzupełnianie w formularzu edycji |
| `loadUsers` | 10040 | Ładuje listę userów do formularza |
| `closeIt` | 15446 | Zamyka modal alertu |
| `shootMapMarkerConfetti` | 688 | Efekt konfetti przy dodaniu punktu |
| `initTagInput` | 1525 | Inicjalizacja pola tagów |
| `initRichEditor` | 1717 | Inicjalizacja edytora tekstu |
| `initOpeningHoursPicker` | 1379 | Picker godzin otwarcia |
| Klastry Leaflet (MCR) | ~4211 | `iconCreateFunction` — renderowanie klastrów (pełnoekranowy) |
| Klastry (sidebar) | ~4317 | `iconCreateFunction` — klastry w sidebarze |
| Promo marker (GP) | 4498–4512 | Marker reklamowy (typ `gp`) |

**Wzorzec `grep` do odnajdywania funkcji:**
```bash
grep -n "function nazwaFunkcji" jg-map.js
sed -n '10368,10440p' jg-map.js   # czyta ~70 linii od celu
```
