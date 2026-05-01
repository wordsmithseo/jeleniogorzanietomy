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
| `jg-interactive-map.php` | `JG_Interactive_Map` | Plugin bootstrap, hook wiring, rewrite rules, point pages, SEO URLs (4 465 linii) |
| `includes/class-database.php` | `JG_Map_Database` | Schema creation, migrations, static table-name accessors |
| `includes/class-ajax-handlers.php` | `JG_Map_Ajax_Handlers` | Shell klasy — wszystkie `wp_ajax_*` delegowane do traitów w `includes/ajax/` |
| `includes/class-admin.php` | `JG_Map_Admin` | Shell klasy — panel admina delegowany do traitów w `includes/admin/` |
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

**Traity AJAX** (`includes/ajax/` — używane przez `class-ajax-handlers.php`):

| Trait | Linie | Odpowiedzialność |
|-------|-------|-----------------|
| `trait-points-read.php` | 1 807 | Pobieranie punktów, wyszukiwanie, sortowanie, filtry |
| `trait-admin-edits.php` | 1 402 | Edycja punktów przez adminów/modów |
| `trait-auth.php` | 1 295 | Logowanie, rejestracja, profil użytkownika |
| `trait-admin-users.php` | 1 169 | Zarządzanie użytkownikami (admin) |
| `trait-points-write.php` | 1 085 | Dodawanie i edycja punktów przez użytkowników |
| `trait-admin-categories.php` | 782 | Zarządzanie kategoriami (admin) |
| `trait-admin-moderation.php` | 620 | Moderacja punktów |
| `trait-place-features.php` | 535 | Menu, oferty, godziny otwarcia |
| `trait-categories.php` | 529 | Kategorie (endpointy publiczne) |
| `trait-images.php` | 473 | Upload i zarządzanie zdjęciami |
| `trait-geocoding.php` | 330 | Geocoding / autouzupełnianie adresów |
| `trait-notifications.php` | 233 | Powiadomienia push |

**Traity Admin** (`includes/admin/` — używane przez `class-admin.php`):

| Trait | Linie | Odpowiedzialność |
|-------|-------|-----------------|
| `trait-admin-other.php` | 1 532 | SEO, menu nawigacyjne, tagi, info bar i inne strony |
| `trait-admin-users.php` | 1 165 | Strony zarządzania użytkownikami |
| `trait-admin-categories.php` | 825 | Strony kategorii miejsc i ciekawostek |
| `trait-admin-moderation.php` | 785 | Kolejka moderacji |
| `trait-admin-reports.php` | 767 | Zgłoszenia nadużyć |
| `trait-admin-places.php` | 753 | Lista miejsc do moderacji |
| `trait-admin-gamification.php` | 713 | Edytory XP, osiągnięć, wyzwań |
| `trait-admin-settings.php` | 431 | Ustawienia wtyczki |
| `trait-admin-promos.php` | 280 | Zarządzanie slotami promocyjnymi |
| `trait-admin-gallery.php` | 248 | Galeria zdjęć |
| `trait-admin-dashboard.php` | 234 | Dashboard (strona główna panelu) |
| `trait-admin-activity.php` | 211 | Dziennik aktywności |
| `trait-admin-helpers.php` | 123 | Współdzielone helpery panelu |

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

### jg-map.js — indeks sekcji

> Zasada: przed każdą operacją na jg-map.js zajrzyj tu po numer linii,
> potem `Read` tylko ±150 linii wokół celu. Nigdy nie czytaj pliku całego.

| Linia | Sekcja / Symbol | Co zawiera |
|-------|----------------|------------|
| 44 | SECTION: UTILITIES & HELPERS | `setupEmojiObserver`, `getCategoryEmojis`, `getOfferingsLabel`, `getCategoryLabel` |
| 282 | BODY SCROLL LOCK | `lockBodyScroll`, `unlockBodyScroll` |
| 301 | MESSAGE MODALS | `showAlert`, `showConfirm`, `showRejectReasonModal`, `showApprovalNotification` |
| 599 | CONFETTI UTILITIES | `_prestigeConfettiColors`, `shootMapMarkerConfetti` (689) |
| 822 | SECTION: MAP INIT | `function init()` — inicjalizacja Leaflet, ładowanie danych, guest engagement |
| 882 | Custom Top Bar | przyciski profilu, `openLoginModal` (1083), ranking modal |
| 1296 | OPENING HOURS PICKER | `initOpeningHoursPicker` (1385) |
| 1460 | RICH TEXT EDITOR | `initRichEditor` (1747) |
| 1514 | TAG INPUT | `initTagInput` (1555) |
| 2108 | SECTION: MODAL HELPERS | `open()`, `close()`, `jgFitModal`, `saveEditModalState`, `setMapCookie`/`getMapCookie` |
| 3357 | SECTION: MAP SIDEBAR & NAVIGATION | `syncNotifications`, lista sidebar, custom nav, filtry UI |
| 3668 | SECTION: PIN RENDERING & CLUSTERING | `setupFsPromo`, `showMap` (~4205), klastry Leaflet (~4244 / ~4350), promo marker GP (~4539) |
| 5561 | SECTION: VOTING & RATING | `voteReq` (5562), `addPulsingMarker` (5791), `loadFromCache` (5839), `removeMarkersById` (5929) |
| 6281 | SECTION: USER MODALS & LIGHTBOX | `openLightbox`, `openAuthorModal` (6288), `openUserModal` (6944), `openAllAchievementsModal` (7313), `openVisitorsModal` (7423) |
| 7501 | SECTION: PLACE DETAIL EDITORS | `loadMenuSection` (7502), `openOfferingsEditor` (7709), `openMenuEditor` (7849) |
| 8409 | SECTION: POINT MANAGEMENT MODALS | `openStatsModal` (8410), `openReportModal` (8544), `openEditModal` (8982), `openDeletionRequestModal` (9775), `openPromoModal` (9821) |
| 9455 | `searchEditAddressSuggestions()` | Autouzupełnianie adresu w formularzu edycji |
| 10077 | `loadUsers()` | Ładuje listę użytkowników do formularza |
| 10405 | SECTION: DETAILS MODAL | `openDetails` (10406), `openDetailsModalContent` (10444), `doVote` (12018) |
| 12661 | SECTION: POINT HISTORY MODAL | `openPointHistoryModal` (12662) — historia edycji z workflow zatwierdzania |
| 12962 | NEW SEARCH FUNCTIONALITY | `performSearch` (13142), `searchAddressSuggestions` (14201), `closeSearchPanel` |
| 13367 | SECTION: CATEGORY FILTERS | `initMapCategoryFilters` (13367) |
| 13547 | REAL-TIME SYNCHRONIZATION | `createSyncStatusIndicator` (13556), `updateSyncStatus` (13567) — Heartbeat API |
| 13847 | FLOATING ACTION BUTTON (FAB) | `openAddPlaceModal` (14527), `searchAddressSuggestions` (14201) |
| 15041 | ADMIN/MOD USER COUNT INDICATOR | `updateUserCountIndicator` — złoty krąg z liczbą użytkowników |
| 15194 | CHALLENGE WIDGETS | `showChallengeCompleteModal` (15451) — do 4 wyzwań jednocześnie |
| 15604 | REAL-TIME LEVEL / XP BAR UPDATE | `updateLevelDisplay` (15615) — pasek XP bez przeładowania |
| 15671 | `window.jgUpdateLevelDisplay` | Publiczne API — aktualizacja poziomu i paska XP |
| 15674 | LEVEL-UP & ACHIEVEMENT NOTIFICATION SYSTEM | `showLevelUpModal` (15724), `showAchievementModal` (15759) |
| 15826 | `window.jgOpenPointById()` | Publiczne API — otwiera punkt po ID (szuka w tablicy ALL) |
| 15837 | `window.jgZoomToPoint()` | Publiczne API — zoom do współrzędnych punktu |

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

### `jg-interactive-map.php` (4 473 linii)

| Linia | Sekcja / Metoda | Co zawiera |
|-------|----------------|------------|
| 46 | SECTION: BOOTSTRAP & INIT HOOKS | `get_instance()`, `__construct()`, `load_dependencies()`, `init_hooks()` (85) |
| 185 | SECTION: CORE SETUP | `init_components()`, `disable_wp_emoji()`, `add_security_headers()`, `add_rewrite_rules()`, `handle_tile_sw()`, `is_bot()` |
| 534 | SECTION: POINT PAGE RENDERING | `handle_point_page()` (535), `render_menu_page()` (719), `render_offerings_page()` (1043), `render_point_page()` (1204), `render_related_points()` (2341), `render_fallback_page()` (2410) |
| 2797 | SECTION: POINT SEO META | `add_theme_color_meta()`, `add_point_meta_tags()` (2802) — Open Graph, structured data, meta tagi punktu |
| 3205 | SECTION: SITEMAP | `handle_sitemap()` (3382), `generate_sitemap_xml_string()` (3449), `regenerate_sitemap_cache()` |
| 3563 | SECTION: CATEGORY SEO | `add_category_page_meta_tags()` (3855), `get_category_seo_title()`, `get_category_intro()`, `get_og_image_for_points()` |
| 3957 | SECTION: TAG & CATALOG SEO | `add_tag_page_meta_tags()` (4182), `resolve_catalog_category/tag()`, `redirect_legacy_tag_urls()`, `ping_indexnow_url()` (4423), `handle_indexnow_key_file()` (4443) |
| 4467 | SECTION: PLUGIN ENTRY POINT | `jg_interactive_map()` — singleton bootstrap, `add_action('plugins_loaded')` |

### `includes/class-admin.php` + traity (877 + ~8 000 linii w `includes/admin/`)

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

### `assets/js/jg-map.js` (15 851 linii)

> Szczegółowy indeks sekcji → patrz **jg-map.js — indeks sekcji** wyżej.

| Blok | Linia | Opis |
|------|-------|------|
| `init()` | 822 | Inicjalizacja całej mapy Leaflet + ładowanie danych |
| `ALL` (dane punktów) | ~5947 | Tablica wszystkich punktów mapy (wypełniana przez AJAX) |
| `addPulsingMarker` | 5763 | Dodaje animowany marker (pulsujący) |
| `voteReq` | 5534 | Funkcja wysyłająca głos (ocena gwiazdkowa) AJAX |
| `doVote` (handler kliknięcia gwiazdki) | 11985 | Obsługa kliknięcia gwiazdki w modalu |
| `removeMarkersById` | 5901 | Usuwa markery z mapy po ID |
| `openDetails` | 10378 | Otwiera modal ze szczegółami punktu |
| `openDetailsModalContent` | 10416 | Wypełnia treść modalu + ręczny `gtag page_view` |
| `initMapCategoryFilters` | 13334 | Inicjalizacja przycisków filtrów kategorii |
| `searchAddressSuggestions` | 14168 | Autouzupełnianie wyszukiwarki adresu (FAB / add form) |
| `searchEditAddressSuggestions` | 9427 | Autouzupełnianie w formularzu edycji |
| `loadUsers` | 10049 | Ładuje listę userów do formularza |
| `closeIt` | 15458 | Zamyka modal alertu |
| `shootMapMarkerConfetti` | 689 | Efekt konfetti przy dodaniu punktu |
| `initTagInput` | 1527 | Inicjalizacja pola tagów |
| `initRichEditor` | 1719 | Inicjalizacja edytora tekstu |
| `initOpeningHoursPicker` | 1381 | Picker godzin otwarcia |
| Klastry Leaflet (MCR) | ~4216 | `iconCreateFunction` — renderowanie klastrów (pełnoekranowy) |
| Klastry (sidebar) | ~4322 | `iconCreateFunction` — klastry w sidebarze |
| Promo marker (GP) | ~4511 | Marker reklamowy (typ `gp`) |

**Wzorzec `grep` do odnajdywania funkcji:**
```bash
grep -n "function nazwaFunkcji" jg-map.js
sed -n '10378,10450p' jg-map.js   # czyta ~70 linii od celu
```
