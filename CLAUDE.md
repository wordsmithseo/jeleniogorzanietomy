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
| `jg-interactive-map.php` | `JG_Interactive_Map` | Plugin bootstrap, hook wiring, rewrite rules, point pages, SEO URLs (4 505 linii) |
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
| `trait-images.php` | 450 | Upload i zarządzanie zdjęciami |
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

> Indeks sekcji jg-map.js i wszystkich plików → [`jg-interactive-map/CLAUDE.md`](jg-interactive-map/CLAUDE.md)

### PHP → JS config bridge

`JG_MAP_CFG` is the central JS config object injected via `wp_localize_script` in `class-enqueue.php`. It carries AJAX URL, nonce, user info, map defaults, and all category definitions. Always reference this object from JS rather than hardcoding values.

### Non-obvious design decisions

- **`_jgNativeReplaceState`** — captured synchronously at the top of `jg-map.js` before GTM loads. GTM wraps `history.replaceState` to fire GA4 `page_view` events; capturing the native version lets us update the URL bar when opening pin modals without triggering a duplicate GTM event. Our manual `gtag('event','page_view')` call in `openDetailsModalContent` is the only source for pin page-views.

- **`JG_Slot_Keys`** — generates random CSS class names and element IDs once per PHP process (cached in static `$k`). Injected as inline `<style>` so ad-blocker lists can't target predictable selectors. `class-enqueue.php` and `class-shortcode.php` must call `JG_Slot_Keys::get()` to share the same keys within one request.

- **Table names via SQL string interpolation** — `$wpdb->prepare()` cannot parameterize table names. The codebase uses `esc_sql($table_name)` + string interpolation for `SHOW COLUMNS FROM` and `ALTER TABLE` queries. This is intentional and safe — don't change to `%s` placeholders.

- **Sync queue is DB-backed** — `class-sync-manager.php` deliberately avoids WordPress transients for the sync queue because transients are unreliable under heavy load. All sync events go to `wp_jg_map_sync_queue`.

- **Mobile swipe sidebar** — on mobile fullscreen, `#jg-map-sidebar` is hidden by `.jg-map .jg-sidebar-fullscreen-overlay { display: none }`. A swipe-from-right gesture (last 22px of viewport) shows it as a `position: fixed` drawer via `.jg-sidebar-mobile-open` class. The CSS `transform` on the drawer does NOT use `!important` so inline `style.transform` can override it during drag. `.jg-sidebar-mobile-backdrop` and `.jg-sidebar-swipe-handle` are appended to `document.body`; both are cleaned up inside `exitFullscreen()`.

- **`jg-auth.js` on all pages** — login/register modals can be triggered from any page (nav bar, map sidebar, etc.), so `jg-auth.js` is enqueued globally, not conditionally.

## Receptury zadań

> Dla typowych operacji — lista plików do zmiany i kolejność. Zapobiega pominięciu wymaganego pliku.

### Nowa akcja AJAX

1. **Trait** (`includes/ajax/trait-*.php`) — dodaj `public function nazwa_akcji() { ... }`. Użyj `$this->verify_nonce()` na początku i `$this->check_admin()` jeśli admin-only.
2. **`includes/class-ajax-handlers.php`** (linia 58, `__construct`) — zarejestruj hook:
   ```php
   add_action('wp_ajax_jg_nazwa', array($this, 'nazwa_akcji'));
   // jeśli też dla niezalogowanych:
   add_action('wp_ajax_nopriv_jg_nazwa', array($this, 'nazwa_akcji'));
   ```
3. **JS** (`assets/js/jg-map.js` lub inny plik) — wywołaj przez:
   ```js
   $.ajax({ url: JG_MAP_CFG.ajax_url, type: 'POST',
            data: { action: 'jg_nazwa', nonce: JG_MAP_CFG.nonce, ... } })
   ```

### Nowy klucz w JG_MAP_CFG (PHP → JS)

1. **`includes/class-enqueue.php`** (linia 323, `enqueue_frontend_assets`) — dodaj klucz do tablicy przekazywanej do `wp_localize_script`.
2. **JS** — odczytaj przez `JG_MAP_CFG.nazwa_klucza`. Nigdy nie hardkoduj wartości które mogą się zmienić — zawsze przez `JG_MAP_CFG`.

### Nowa kolumna w istniejącej tabeli DB

1. **`includes/class-database.php`** (linia 303, `check_and_update_schema`) — dodaj blok:
   ```php
   if (!in_array('nazwa_kolumny', $columns)) {
       $wpdb->query("ALTER TABLE $table ADD COLUMN nazwa_kolumny typ DEFAULT wartość");
   }
   ```
2. Zaktualizuj `INSERT`/`UPDATE` w odpowiednich metodach tej samej klasy.
3. Jeśli kolumna jest potrzebna w JS — dodaj do `JG_MAP_CFG` (patrz wyżej).

### Nowe źródło XP

1. **`includes/class-levels-achievements.php`** (linia 540, `get_xp_sources`) — dodaj domyślną wartość dla nowego źródła do zwracanej tablicy.
2. W miejscu gdzie akcja się odbywa (trait AJAX lub main plugin) — wywołaj:
   ```php
   JG_Map_Levels_Achievements::award_xp($user_id, 'nazwa_zrodla', $reference_id);
   ```
3. Przy cofaniu (odrzucenie, usunięcie) — wywołaj `revoke_xp()` z tymi samymi argumentami (linia 456).

### Nowy typ warunku wyzwania

1. **`includes/class-challenges.php`** (linia 17, `get_condition_types`) — dodaj definicję nowego warunku (klucz, etykieta, opis).
2. **`includes/class-challenges.php`** (linia 239, `calculate_progress`) — dodaj `case 'nazwa_warunku':` z logiką obliczania postępu.

### Nowy cron / zadanie maintenance

1. **`includes/class-maintenance.php`** (linia 26, `init`) — zarejestruj harmonogram przez `wp_schedule_event` i podepnij handler.
2. Dodaj prywatną metodę `static` z logiką czyszczenia/synchronizacji.
3. **`includes/class-maintenance.php`** (linia 698, `deactivate`) — dodaj `wp_clear_scheduled_hook('nazwa_crona')`.

### Zmiana wyglądu komponentu CSS

1. Zajrzyj do **indeksu sekcji `jg-map.css`** (w `jg-interactive-map/CLAUDE.md`) — znajdź linię startową bloku.
2. `grep -n "\.jg-nazwa-klasy"` w `assets/css/jg-map.css` jeśli znasz selektor.
3. Sprawdź czy styl nie jest nadpisywany przez media query — przeszukaj cały plik po selektorze.

