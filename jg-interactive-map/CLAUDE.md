# Indeks nawigacyjny — jg-interactive-map/

> Szczegółowe numery linii i sekcji dla chirurgicznej pracy `grep -n` + `Read`.
> Ładowany automatycznie gdy pracujesz w tym katalogu.
> Architektura, receptury i design decisions → `../CLAUDE.md`

## jg-map.js — indeks sekcji

> **TOKEN BUDGET RULE — `jg-map.js` jest 16 207 linii. NEVER read it whole.**
> Przed każdą operacją zajrzyj tu po numer linii, potem `Read` tylko ±150 linii wokół celu. Nigdy nie czytaj pliku całego.

| Linia | Sekcja / Symbol | Co zawiera |
|-------|----------------|------------|
| 44 | SECTION: UTILITIES & HELPERS | `setupEmojiObserver`, `getCategoryEmojis`, `getOfferingsLabel`, `getCategoryLabel` |
| 283 | BODY SCROLL LOCK | `lockBodyScroll`, `unlockBodyScroll` |
| 302 | MESSAGE MODALS | `showAlert`, `showConfirm`, `showRejectReasonModal`, `showApprovalNotification` |
| 600 | CONFETTI UTILITIES | `_prestigeConfettiColors`, `shootMapMarkerConfetti` (689) |
| 822 | SECTION: MAP INIT | `function init()` — inicjalizacja Leaflet, ładowanie danych, guest engagement |
| 883 | Custom Top Bar | przyciski profilu, `openLoginModal` (1081), ranking modal |
| 1296 | OPENING HOURS PICKER | `initOpeningHoursPicker` (1385) |
| 1460 | RICH TEXT EDITOR | `initRichEditor` (1747) |
| 1514 | TAG INPUT | `initTagInput` (1555) |
| 2108 | SECTION: MODAL HELPERS | `open()`, `close()`, `jgFitModal`, `saveEditModalState`, `setMapCookie`/`getMapCookie` |
| 3357 | SECTION: MAP SIDEBAR & NAVIGATION | `syncNotifications`, lista sidebar, custom nav, filtry UI |
| 3668 | SECTION: PIN RENDERING & CLUSTERING | `setupFsPromo`, `showMap` (~4517), klastry Leaflet (~4556 / ~4662), promo marker GP (~4851) |
| 4252 | MOBILE SWIPE-IN SIDEBAR | `openMobileSb`, `closeMobileSb` — swipe z prawej krawędzi ekranu otwiera sidebar jako drawer (tylko mobile + fullscreen) |
| 5877 | SECTION: VOTING & RATING | `voteReq` (5878), `addPulsingMarker` (6107), `loadFromCache` (6155), `removeMarkersById` (6245) |
| 6597 | SECTION: USER MODALS & LIGHTBOX | `openLightbox`, `openAuthorModal` (6604), `openUserModal` (7260), `openAllAchievementsModal` (7629), `openVisitorsModal` (7739) |
| 7817 | SECTION: PLACE DETAIL EDITORS | `loadMenuSection` (7818), `openOfferingsEditor` (8025), `openMenuEditor` (8165) |
| 8728 | SECTION: POINT MANAGEMENT MODALS | `openStatsModal` (8729), `openReportModal` (8863), `openEditModal` (9301), `openDeletionRequestModal` (10094), `openPromoModal` (10140) |
| 9774 | `searchEditAddressSuggestions()` | Autouzupełnianie adresu w formularzu edycji |
| 10396 | `loadUsers()` | Ładuje listę użytkowników do formularza |
| 10725 | SECTION: DETAILS MODAL | `openDetails` (10725), `openDetailsModalContent` (10763), `doVote` (12337) |
| 12981 | SECTION: POINT HISTORY MODAL | `openPointHistoryModal` (12981) — historia edycji z workflow zatwierdzania |
| 13281 | NEW SEARCH FUNCTIONALITY | `performSearch` (13461), `searchAddressSuggestions` (14520), `closeSearchPanel` (13616) |
| 13685 | SECTION: CATEGORY FILTERS | `initMapCategoryFilters` (13686) |
| 13866 | REAL-TIME SYNCHRONIZATION | `createSyncStatusIndicator` (13875), `updateSyncStatus` (13886) — Heartbeat API |
| 14166 | FLOATING ACTION BUTTON (FAB) | `openAddPlaceModal` (14846), `searchAddressSuggestions` (14520) |
| 15364 | ADMIN/MOD USER COUNT INDICATOR | `updateUserCountIndicator` (15487) — złoty krąg z liczbą użytkowników |
| 15517 | CHALLENGE WIDGETS | `showChallengeCompleteModal` (15774) — do 4 wyzwań jednocześnie |
| 15927 | REAL-TIME LEVEL / XP BAR UPDATE | `updateLevelDisplay` (15938) — pasek XP bez przeładowania |
| 15994 | `window.jgUpdateLevelDisplay` | Publiczne API — aktualizacja poziomu i paska XP |
| 15997 | LEVEL-UP & ACHIEVEMENT NOTIFICATION SYSTEM | `showLevelUpModal` (16047), `showAchievementModal` (16082) |
| 16149 | `window.jgOpenPointById()` | Publiczne API — otwiera punkt po ID (szuka w tablicy ALL) |
| 16160 | `window.jgZoomToPoint()` | Publiczne API — zoom do współrzędnych punktu |

## `wp_jg_map_points` — schemat kolumn

| Kolumna | Typ | Uwagi |
|---------|-----|-------|
| `id` | bigint UNSIGNED | PK, AUTO_INCREMENT |
| `case_id` | varchar(20) | numer zgłoszenia (NULL dla ciekawostek/miejsc) |
| `title` | varchar(255) | |
| `slug` | varchar(255) | UNIQUE — URL punktu |
| `content` | longtext | opis HTML (rich editor) |
| `excerpt` | text | auto-generowany skrót |
| `lat` | decimal(10,6) | |
| `lng` | decimal(10,6) | |
| `address` | varchar(500) | |
| `type` | varchar(50) | `zgloszenie`\|`ciekawostka`\|`miejsce` |
| `category` | varchar(100) | klucz kategorii z `JG_MAP_CFG` |
| `status` | varchar(20) | `pending`\|`publish`\|`rejected` |
| `report_status` | varchar(50) | `added`\|`resolved`\|`rejected` |
| `resolved_delete_at` | datetime | planowane usunięcie po rozwiązaniu |
| `resolved_summary` | text | opis rozwiązania zgłoszenia |
| `rejected_reason` | text | powód odrzucenia |
| `rejected_delete_at` | datetime | planowane usunięcie po odrzuceniu |
| `author_id` | bigint UNSIGNED | WP user ID |
| `author_hidden` | tinyint(1) | 1 = wyświetl jako „anonimowy" |
| `edit_locked` | tinyint(1) | 1 = user nie może edytować |
| `is_promo` | tinyint(1) | 1 = wyróżniony sponsor |
| `promo_until` | datetime | koniec okresu sponsoringu |
| `admin_note` | text | notatka admina (niepubliczna) |
| `images` | longtext | JSON array URL-ów zdjęć |
| `website` | varchar(255) | |
| `phone` | varchar(50) | |
| `email` | varchar(255) | |
| `cta_enabled` | tinyint(1) | 1 = przycisk CTA aktywny |
| `cta_type` | varchar(20) | `call`\|`email`\|`web` |
| `is_deletion_requested` | tinyint(1) | 1 = user wnioskuje o usunięcie |
| `deletion_reason` | text | |
| `deletion_requested_at` | datetime | |
| `created_at` | datetime | auto CURRENT_TIMESTAMP |
| `approved_at` | datetime | NULL dopóki pending |
| `updated_at` | datetime | auto ON UPDATE |
| `tags` | varchar(500) | przecinkami: `historia,park` |
| `opening_hours` | text | JSON — struct godzin otwarcia |
| `pending_edit` | tinyint(1) | 1 = oczekująca edycja w `wp_jg_map_history` |
| `price_range` | varchar(10) | `$`\|`$$`\|`$$$` |
| `serves_cuisine` | varchar(255) | typ kuchni (restauracje) |
| `ip_address` | varchar(100) | IP przy dodaniu punktu |

**Indeksy:** PRIMARY (`id`), UNIQUE (`slug`), KEY `author_id`, `status`, `type`, (`status`,`type`), (`lat`,`lng`), `case_id`

---

## Kluczowe przepływy danych

### Dodanie punktu → moderacja → publikacja

```
User (JS: openAddPlaceModal:14846) → submit_point (trait-points-write.php:17)
  → JG_Map_Database::insert_point(:1264)          [status=pending]
  → JG_Map_Sync_Manager::queue_point_created(:585)
  → award_xp($user_id, 'point_submitted')          [jeśli auto-publish też 'point_approved']

Admin panel (JS) → admin_approve_point (trait-admin-moderation.php:495)
  → JG_Map_Database::update_point [status=publish]
  → JG_Map_Sync_Manager::queue_point_approved(:591)
  → award_xp($author_id, 'point_approved')
  → e-mail powiadomienie do autora
```

Edycje istniejącego punktu przez usera idą przez `update_point` (trait-points-write.php:320) → `JG_Map_Database::add_history(:1969)` → `pending_edit=1`. Admin zatwierdza przez `admin_approve_edit` (trait-admin-edits.php:321) → `JG_Map_Database::approve_history(:2084)`.

### Heartbeat / real-time sync

```
JS (jg-map.js:13875) co ~15s → wp.heartbeat.send({jg_map_sync: {last_sync_id, user_id}})
  → WP Heartbeat → heartbeat_received (class-admin.php:614)
  → JG_Map_Sync_Manager::heartbeat_handler(:383)
      → get_pending_syncs(last_sync_id) → nowe zdarzenia z wp_jg_map_sync_queue
      → track_and_get_online_users()   → lista aktywnych userów
  → JS otrzymuje {jg_map_sync: {events[], online_users[]}}
  → jg-map.js aktualizuje piny na mapie
  → jg-sidebar.js setupSync(:1144) odświeża listę punktów
```

### Przyznanie XP → poziom → osiągnięcia → powiadomienie JS

```
award_xp($user_id, 'źródło', $ref_id) (class-levels-achievements.php:337)
  → sprawdza duplikat w wp_jg_map_xp_log
  → INSERT xp_log + UPDATE user meta jg_map_xp_total
  → calculate_level() → jeśli nowy poziom: INSERT notifications (type=level_up)
  → check_achievements($user_id) (:553)
      → każde osiągnięcie: sprawdza warunek → jeśli nowe: INSERT achievements + notifications

JS (co ~30s / po akcji AJAX) → ajax_check_notifications (:821)
  → zwraca nowe powiadomienia z DB
  → showLevelUpModal (jg-map.js:16047) / showAchievementModal (:16082)
  → jgUpdateLevelDisplay (jg-map.js:15994) → re-fetch XP bar bez przeładowania
```

---

## Kod - Mapa Drogowa

> Indeks nawigacyjny do chirurgicznej pracy `grep -n` + `sed -n`. Zamiast czytać plik w całości, używaj tych numerów linii jako punktów startu.

### `includes/class-levels-achievements.php` (1 282 linii)

| Linia | Metoda | Opis |
|-------|--------|------|
| 52 | `maybe_upgrade_achievements_db` *(private)* | Migracje schematu tabel gamifikacji |
| 69 | `create_tables` | Tworzy tabele `user_achievements`, `xp_log`, `notifications` |
| **XP** | | |
| 228 | `xp_for_level` | Zwraca próg XP dla danego poziomu |
| 236 | `calculate_level` | Oblicza poziom i procent paska na podstawie sumy XP |
| 247 | `get_user_xp_data` | Pobiera XP, poziom i postęp paska dla użytkownika |
| 278 | `count_valid_words` | Liczy „wartościowe" słowa w tekście (do XP za opis) |
| 337 | `award_xp` | Przyznaje XP za akcję; sprawdza duplikaty; wyzwala `check_achievements` |
| 456 | `revoke_xp` | Odbiera XP (np. przy odrzuceniu punktu) |
| 540 | `get_xp_sources` | Pobiera konfigurację źródeł XP z opcji WP |
| **Osiągnięcia** | | |
| 553 | `check_achievements` | Sprawdza i przyznaje wszystkie osiągnięcia dla użytkownika |
| 666 | `get_user_achievements` | Pobiera listę osiągnięć użytkownika |
| **AJAX — użytkownik** | | |
| 688 | `ajax_get_user_level_info` | Zwraca poziom, XP, pasek postępu (używane przez `jgUpdateLevelDisplay`) |
| 725 | `ajax_get_user_achievements` | Zwraca osiągnięcia użytkownika do modalu |
| 821 | `ajax_check_notifications` | Sprawdza nowe powiadomienia (level-up, achievement) |
| 851 | `ajax_mark_notifications_seen` | Oznacza powiadomienia jako widziane |
| 889 | `ajax_dismiss_notification` | Dismissuje pojedyncze powiadomienie |
| **AJAX — admin** | | |
| 782 | `ajax_admin_manage_user_achievement` | Ręczne przyznanie/odebranie osiągnięcia przez admina |
| 917 | `ajax_get_xp_sources` | Pobiera konfigurację XP (edytor admina) |
| 926 | `ajax_save_xp_sources` | Zapisuje konfigurację XP |
| 954 | `ajax_get_achievements` | Pobiera definicje osiągnięć (edytor admina) |
| 967 | `ajax_save_achievements` | Zapisuje definicje osiągnięć |
| 1264 | `ajax_delete_achievement` | Usuwa osiągnięcie |
| **Narzędzia administracyjne** | | |
| 1019 | `recalculate_all_xp` | Przelicza XP wszystkich użytkowników od zera (operacja masowa) |
| 1160 | `recheck_all_achievements` | Ponownie sprawdza osiągnięcia dla wszystkich użytkowników |

---

### `includes/class-enqueue.php` (1 766 linii)

| Linia | Metoda | Opis |
|-------|--------|------|
| 31 | `__construct` | Bootstrap: rejestracja wszystkich hooków |
| 84 | `grant_plugin_caps` | Daje moderatorom caps `edit_posts` / `upload_files` |
| 98 | `hide_admin_bar_for_users` | Ukrywa WP admin bar dla nie-adminów |
| 108 | `redirect_unauthenticated_wp_admin` | Przekierowuje niezalogowanych z `/wp-admin/` |
| 133 | `block_non_admin_access` | Blokuje dostęp do WP admin dla moderatorów (tylko plugin) |
| 178 | `enqueue_topbar_css` | Rejestruje CSS top baru + Twemoji (emoji rendering) |
| 288 | `defer_css_on_non_plugin_pages` | Odkłada ładowanie CSS na stronach bez mapy |
| 323 | `enqueue_frontend_assets` | Główna rejestracja wszystkich JS/CSS frontendu + `JG_MAP_CFG` (wp_localize_script) |
| 598 | `get_catalog_page_url` *(private static)* | Zwraca URL strony katalogu |
| 622 | `enqueue_admin_assets` | CSS/JS dla panelu admina |
| 648 | `render_nav_bar` | Wstrzykuje HTML `#jg-nav-bar` (hamburger + logo + menu) + inline JS nawigacji |
| 1022 | `render_top_bar` | Wstrzykuje HTML `#jg-custom-top-bar` (profil, XP, powiadomienia) + inline JS pozycjonowania modali |
| 1318 | `hide_register_on_maintenance` | Ukrywa rejestrację w trybie maintenance |
| 1349 | `is_map_page` *(private)* | Sprawdza czy bieżąca strona zawiera mapę |
| 1360 | `add_map_body_class` | Dodaje klasę `jg-map-page` do `<body>` |
| 1367 | `hide_elementor_header_early` | Ukrywa header Elementora na stronie mapy (priorytet 1) |
| 1387 | `add_tile_preconnect` | Dodaje `<link rel="preconnect">` do hostów kafelków |
| 1394 | `disable_mobile_zoom` | Wyłącza zoom pinchem na stronie mapy (meta viewport) |
| 1420 | `handle_email_activation` | Obsługuje link aktywacyjny konta z emaila |
| 1478 | `handle_password_reset` | Obsługuje link resetowania hasła z emaila |
| 1549 | `show_reset_password_form` *(private)* | Renderuje formularz nowego hasła |
| 1737 | `render_footer_bar` | Wstrzykuje HTML `#jg-footer-bar` (sticky kontakt na mobile) + inline JS `--jg-footer-h` |

**Uwaga:** `enqueue_frontend_assets` (323) to główne miejsce konfiguracji `JG_MAP_CFG` — tu dodawaj nowe klucze do obiektu JS przekazywanego do frontendu.

---

### `includes/class-database.php` (2 917 linii)

Wszystkie metody są `static` — wywołuj jako `JG_Map_Database::metoda()`. Nigdy nie hardkoduj nazw tabel — używaj accessorów `get_*_table()`.

| Linia | Metoda / Blok | Opis |
|-------|--------------|------|
| 16 | `activate()` | Tworzy wszystkie tabele przy aktywacji wtyczki |
| 303 | `check_and_update_schema()` | Migracje schematu (ALTER TABLE) — dodaje brakujące kolumny |
| 732 | `migrate_generate_slugs()` | Jednorazowa migracja: generuje slugi dla punktów bez slug |
| 774 | `migrate_strip_slashes()` | Jednorazowa migracja: usuwa podwójne backslashe z opisów |
| 799 | `migrate_fix_unicode_tags()` | Jednorazowa migracja: naprawia tagi z błędnym encodingiem |
| 854 | `migrate_fix_special_char_slugs()` | Jednorazowa migracja: naprawia slugi ze znakami specjalnymi |
| 927 | `deactivate()` | Hook deaktywacji wtyczki |
| 935 | `generate_slug()` | Generuje slug z tytułu (transliteracja PL → ASCII) |
| 988 | `generate_unique_slug()` | Jak wyżej, ale gwarantuje unikalność w DB |
| **Accessory tabel** | | |
| 1037 | `get_points_table()` | → `wp_jg_map_points` |
| 1045 | `get_votes_table()` | → `wp_jg_map_votes` |
| 1053 | `get_reports_table()` | → `wp_jg_map_reports` |
| 1061 | `get_relevance_votes_table()` | → `wp_jg_map_relevance_votes` |
| 1084 | `get_slug_redirects_table()` | → `wp_jg_map_slug_redirects` |
| 1922 | `get_history_table()` | → `wp_jg_map_history` |
| 2572 | `get_menu_sections_table()` / `get_menu_items_table()` / `get_menu_photos_table()` | Tabele menu restauracji |
| 2839 | `get_offerings_table()` | → `wp_jg_map_offerings` |
| **Punkty — CRUD** | | |
| 1092 | `get_published_points()` | Pobiera wszystkie opublikowane punkty (opcjonalnie + pending) |
| 1207 | `get_user_pending_points()` | Punkty oczekujące danego użytkownika |
| 1242 | `get_point()` | Pobiera jeden punkt po ID |
| 1264 | `insert_point()` | Wstawia nowy punkt |
| 1301 | `update_point()` | Aktualizuje dane punktu |
| 1337 | `save_slug_redirect()` | Zapisuje przekierowanie 301 przy zmianie sluga |
| 1370 | `soft_delete_point()` | Przenosi punkt do kosza (status `deleted`) |
| 1393 | `restore_point()` | Przywraca punkt z kosza |
| 1416 | `delete_point()` | Trwale usuwa punkt + obrazy + powiązane rekordy |
| 1193 | `invalidate_points_cache()` | Czyści transient cache listy punktów |
| 1130 | `get_all_tags()` | Pobiera wszystkie tagi z liczbą użyć |
| 1171 | `get_all_place_categories()` | Pobiera kategorie miejsc z DB |
| **Głosy i oceny** | | |
| 1502 | `get_rating_data()` | Średnia ocena + liczba głosów dla punktu |
| 1537 | `get_user_vote()` | Głos konkretnego użytkownika na punkt |
| 1553 | `set_vote()` | Zapisuje/aktualizuje głos gwiazdkowy |
| 1589 | `get_votes_counts_batch()` | Liczba głosów dla wielu punktów naraz |
| 1634 | `get_user_votes_batch()` | Głosy użytkownika dla wielu punktów naraz |
| **Zgłoszenia (reports)** | | |
| 1669 | `get_reports_counts_batch()` | Liczba zgłoszeń dla wielu punktów |
| 1713 | `has_user_reported_batch()` | Czy użytkownik zgłosił któryś z punktów |
| 1835 | `get_reports()` | Lista zgłoszeń dla punktu |
| 1884 | `add_report()` | Dodaje zgłoszenie nadużycia |
| 1904 | `resolve_reports()` | Rozstrzyga zgłoszenia (keep/reject) |
| **Historia edycji** | | |
| 1930 | `ensure_history_table()` | Tworzy tabelę historii jeśli nie istnieje |
| 1969 | `add_history()` | Zapisuje wpis historii edycji |
| 1998 | `add_admin_edit_history()` | Jak wyżej, ale dla edycji adminów (auto-approve) |
| 2020 | `get_pending_history()` | Oczekujące na zatwierdzenie edycje punktu |
| 2043 | `get_rejected_history()` | Odrzucone edycje (ostatnie N dni) |
| 2066 | `get_point_history()` | Pełna historia edycji punktu |
| 2084 | `approve_history()` | Zatwierdza edycję i aktualizuje punkt |
| 2102 | `reject_history()` | Odrzuca edycję z powodem |
| **Zapytania administracyjne** | | |
| 2128 | `get_all_places_with_status()` | Złożone zapytanie (5 subqueries): miejsca + raporty + edycje |
| 2331 | `get_places_count_by_status()` | Liczniki punktów wg statusu |
| 2361 | `get_tags_paginated()` | Tagi z paginacją i wyszukiwaniem |
| 2422 | `rename_tag()` | Zmienia nazwę taga we wszystkich punktach |
| 2482 | `delete_tag()` | Usuwa tag ze wszystkich punktów |
| **Menu restauracji** | | |
| 2590 | `get_menu()` | Pobiera pełne menu (sekcje + pozycje + zdjęcia) |
| 2643 | `save_menu()` | Zapisuje całe menu (diff + upsert) |
| 2722 | `get_menu_photos()` / `add_menu_photo()` / `delete_menu_photo()` | Zdjęcia menu (2722 / 2737 / 2754) |
| 2807 | `point_has_menu()` | Sprawdza czy punkt ma menu |
| 2820 | `get_menu_point_ids_batch()` | Które z podanych ID mają menu |
| **Oferty (offerings)** | | |
| 2847 | `get_offerings()` | Pobiera oferty miejsca |
| 2863 | `save_offerings()` | Zapisuje oferty |
| 2898 | `point_has_offerings()` / `get_offerings_point_ids_batch()` | Analogicznie do menu |

---

### `includes/class-shortcode.php` (1 015 linii)

| Linia | Metoda | Opis |
|-------|--------|------|
| 43 | `render_map` | Shortcode `[jg_map]` — pełny widok mapy z sidebar |
| 416 | `render_directory` | Shortcode `[jg_map_directory]` — katalog punktów (lista + filtry + tagi) |
| 781 | `render_tag_cloud` *(private)* | Renderuje chmurę tagów |
| 829 | `render_category_cloud` *(private)* | Renderuje chmurę kategorii |
| 864 | `render_sidebar` | Shortcode `[jg_map_sidebar]` — sama lista sidebar bez mapy |
| 981 | `render_banner` | Shortcode `[jg_banner]` — slot banera 728×90 |

---

### `includes/class-challenges.php` (796 linii)

| Linia | Metoda | Opis |
|-------|--------|------|
| 17 | `get_condition_types` | Definicje typów warunków wyzwań (słownik) |
| 80 | `auto_deactivate_expired` | Cron: dezaktywuje wyzwania po upływie deadline |
| 95 | `maybe_upgrade_db` | Migracje schematu tabeli wyzwań |
| 119 | `create_table` | Tworzy tabelę `wp_jg_map_challenges` |
| 153 | `get_active_with_progress` | Aktywne wyzwania z postępem dla bieżącego użytkownika |
| 158 | `get_all_active_with_progress` | Jak wyżej, ale dla wszystkich użytkowników naraz |
| 239 | `calculate_progress` *(private)* | Oblicza postęp wyzwania (obsługuje ~12 typów warunków) |
| 549 | `get_all` | Pobiera wszystkie wyzwania (panel admina) |
| 555 | `save` | Tworzy lub aktualizuje wyzwanie |
| 619 | `delete` | Usuwa wyzwanie |
| 684 | `maybe_award_challenge_xp` *(private)* | Przyznaje XP po ukończeniu wyzwania |
| 701 | `maybe_award_challenge_achievement` *(private)* | Przyznaje osiągnięcie po ukończeniu wyzwania |
| 759 | `ajax_get_active_challenge` | AJAX: aktywne wyzwania dla użytkownika |
| 772 | `ajax_save` | AJAX: zapis wyzwania (admin) |
| 788 | `ajax_delete` | AJAX: usunięcie wyzwania (admin) |

---

### `includes/class-sync-manager.php` (665 linii)

Zarządza kolejką synchronizacji real-time (DB-backed, bez transientów). Odpytywany przez WordPress Heartbeat API.

| Linia | Metoda | Opis |
|-------|--------|------|
| 103 | `ajax_user_leave` | AJAX: usuwa użytkownika z listy online przy zamknięciu strony |
| 117 | `on_user_logout` | Hook: usuwa użytkownika z online przy wylogowaniu |
| 147 | `queue_sync` | Dodaje zdarzenie do kolejki synchronizacji |
| 196 | `invalidate_map_cache` *(private)* | Czyści transient cache mapy po zmianie |
| 214 | `get_pending_syncs` | Pobiera zdarzenia oczekujące od podanego timestamp |
| 246 | `mark_completed` | Oznacza zdarzenie jako przetworzone |
| 279 | `mark_failed` | Oznacza zdarzenie jako błędne |
| 348 | `track_and_get_online_users` *(private)* | Aktualizuje i zwraca listę aktywnych użytkowników |
| 383 | `heartbeat_handler` | Główny handler Heartbeat — zwraca sync events + online users |
| 483 | `cleanup_old_queue_entries` | Cron: czyści stare wpisy z kolejki |
| 554 | `create_table` | Tworzy tabelę `wp_jg_map_sync_queue` |
| 585–662 | `queue_point_created` … `queue_deletion_rejected` | Shorthandy do kolejkowania konkretnych zdarzeń (12 metod) |

---

### `includes/class-maintenance.php` (709 linii)

Wszystkie metody są `static`. Crony rejestrowane przez `init()`.

| Linia | Metoda | Opis |
|-------|--------|------|
| 26 | `init` | Rejestruje crony i handlery ręcznego wyzwalania |
| 87 | `run_xp_sync` | Cron nocny: synchronizuje XP wszystkich użytkowników |
| 103 | `handle_manual_trigger` | POST handler: ręczne uruchomienie maintenance z panelu |
| 142 | `run_maintenance` | Główna funkcja — uruchamia wszystkie zadania czyszczenia |
| 189–661 | Zadania prywatne | `clean_orphaned_votes/reports/history`, `validate_coordinates/content`, `disable_expired_sponsorships`, `clean_old_pending/deleted_points`, `clean_expired_reports`, `clean_orphaned_images`, `clean_old_activity_logs/point_visits`, `clear_caches`, `optimize_tables` |
| 698 | `deactivate` | Usuwa crony przy deaktywacji wtyczki |

---

### `includes/class-banner-manager.php` (366 linii)

Wszystkie metody są `static`.

| Linia | Metoda | Opis |
|-------|--------|------|
| 17 | `create_table` | Tworzy tabele banerów i impresji |
| 73 | `get_table_name` / `get_impressions_table_name` | Accessory nazw tabel |
| 81 | `get_user_fingerprint` | Generuje fingerprint użytkownika (IP + UA) do deduplikacji impresji |
| 92 | `get_active_banners` | Pobiera aktywne banery (w terminie, z limitem wyświetleń) |
| 115 | `get_next_banner_for_rotation` | Zwraca kolejny baner do rotacji (round-robin) |
| 146 | `insert_banner` / `update_banner` / `delete_banner` | CRUD banerów |
| 204 | `track_impression` | Rejestruje wyświetlenie (z deduplikacją per fingerprint/24h) |
| 261 | `track_click` | Rejestruje kliknięcie banera |
| 276 | `get_banner_stats` | Statystyki banera (impresje, kliknięcia, CTR) |
| 329 | `deactivate_expired_banners` | Dezaktywuje banery po upływie daty |
| 352 | `clean_old_impressions` | Czyści stare wpisy impresji |

---

### `includes/class-info-bar.php` (486 linii)

| Linia | Metoda | Opis |
|-------|--------|------|
| 17 | `init` | Rejestruje hooki frontendu i panelu admina |
| 35 | `get_active_content` | Pobiera aktywną treść paska z opcji WP |
| 60 | `render_frontend` | Renderuje HTML paska + inline JS (dismiss, ticker, tap-to-pause) |
| 231 | `add_admin_menu` | Rejestruje stronę admina paska informacyjnego |
| 246 | `handle_reset` | POST: resetuje pasek do domyślnych wartości |
| 265 | `handle_save` | POST: zapisuje treść i ustawienia paska |
| 294 | `render_admin_page` | Renderuje formularz edycji paska w panelu admina |

---

### `jg-interactive-map.php` (~4 510 linii)

| Linia | Sekcja / Metoda | Co zawiera |
|-------|----------------|------------|
| 46 | SECTION: BOOTSTRAP & INIT HOOKS | `get_instance()`, `__construct()`, `load_dependencies()`, `init_hooks()` (85) |
| 185 | SECTION: CORE SETUP | `init_components()`, `disable_wp_emoji()`, `add_security_headers()`, `add_rewrite_rules()`, `handle_tile_sw()`, `is_bot()` |
| 534 | SECTION: POINT PAGE RENDERING | `handle_point_page()` (535), `render_menu_page()` (719), `render_offerings_page()` (1043), `get_opening_hours_title_part()` (1204), `render_point_page()` (1219), `render_related_points()` (2365), `render_fallback_page()` (2434) |
| 2825 | SECTION: POINT SEO META | `add_theme_color_meta()` (2826), `add_point_meta_tags()` (2830) — Open Graph, structured data, meta tagi punktu; OG/Twitter title zawiera godziny otwarcia |
| 3237 | SECTION: SITEMAP | `handle_sitemap()` (3414), `generate_sitemap_xml_string()` (3481), `regenerate_sitemap_cache()` (3300) |
| 3595 | SECTION: CATEGORY SEO | `add_category_page_meta_tags()` (3887), `get_category_seo_title()` (3768), `get_category_intro()` (3716), `get_og_image_for_points()` (3835) |
| 3989 | SECTION: TAG & CATALOG SEO | `add_tag_page_meta_tags()` (4214), `resolve_catalog_category/tag()` (4131), `redirect_legacy_tag_urls()` (4176), `ping_indexnow_url()` (4455), `handle_indexnow_key_file()` (4475) |
| 4499 | SECTION: PLUGIN ENTRY POINT | `jg_interactive_map()` (4500) — singleton bootstrap, `add_action('plugins_loaded')` |

---

### `includes/class-admin.php` + traity (877 + 8 067 linii w `includes/admin/`)

**`class-admin.php` — metody główne:**

| Blok | Linia | Opis |
|------|-------|------|
| `__construct` | 58 | Bootstrap: rejestracja wszystkich hooków |
| `add_admin_bar_notifications` | 114 | Ikonka powiadomień w górnym pasku WP |
| `handle_manual_activate_user` | 213 | POST handler: ręczna aktywacja konta |
| `modify_admin_title` | 255 | Filter: tytuł zakładki przeglądarki |
| `add_admin_menu` | 394 | Rejestracja stron menu w panelu admina |
| `restrict_sidebar_for_non_wp_admins` | 579 | Ukrywanie menu dla nie-adminów WP |
| `heartbeat_received` | 614 | Heartbeat: odpowiedź AJAX (live notifications) |
| `enqueue_admin_styles` | 697 | Rejestracja stylów CSS panelu |
| `enqueue_admin_bar_script` | 777 | Skrypt paska admina |

**Hooki zarejestrowane w `__construct` (linia 58):** `admin_menu` (×2), `admin_bar_menu`, `admin_title`, `heartbeat_received`, `admin_enqueue_scripts` (×2), `admin_post_jg_map_activate_user`

**Traity (`includes/admin/`) — nawigacja po metodach:**

| Blok | Plik | Linia |
|------|------|-------|
| `render_main_page` | `trait-admin-dashboard.php` | 6 |
| `render_filter_reset_card` *(private)* | `trait-admin-helpers.php` | 6 |
| `render_page_header` *(private)* | `trait-admin-helpers.php` | 113 |
| `render_places_page` | `trait-admin-places.php` | 6 |
| `render_place_row` *(private)* | `trait-admin-places.php` | 585 |
| `render_place_actions` *(private)* | `trait-admin-places.php` | 629 |
| `render_moderation_page` | `trait-admin-moderation.php` | 6 |
| `render_reports_page` | `trait-admin-reports.php` | 12 |
| `render_report_reasons_page` | `trait-admin-reports.php` | 62 |
| `render_promos_page` | `trait-admin-promos.php` | 7 |
| `render_all_points_page` | `trait-admin-promos.php` | 187 |
| `render_roles_page` | `trait-admin-other.php` | 11 |
| `render_deletions_page` | `trait-admin-other.php` | 203 |
| `render_maintenance_page` | `trait-admin-other.php` | 334 |
| `render_tags_page` | `trait-admin-other.php` | 498 |
| `render_nav_menu_page` | `trait-admin-other.php` | 1021 |
| `render_seo_page` | `trait-admin-other.php` | 1366 |
| `render_gallery_page` | `trait-admin-gallery.php` | 10 |
| `render_users_page` | `trait-admin-users.php` | 133 |
| `render_activity_log_page` | `trait-admin-activity.php` | 10 |
| `render_settings_page` | `trait-admin-settings.php` | 10 |
| `render_place_categories_page` | `trait-admin-categories.php` | 8 |
| `render_curiosity_categories_page` | `trait-admin-categories.php` | 459 |
| `render_xp_editor_page` | `trait-admin-gamification.php` | 11 |
| `render_achievements_editor_page` | `trait-admin-gamification.php` | 138 |
| `render_challenges_page` | `trait-admin-gamification.php` | 308 |

---

### `includes/class-ajax-handlers.php` + traity (306 + 10 237 linii w `includes/ajax/`)

**`class-ajax-handlers.php` — metody pomocnicze:**

| Blok | Linia | Opis |
|------|-------|------|
| `__construct` | 58 | Bootstrap: rejestracja wszystkich hooków `wp_ajax_*` |
| `track_last_login` | 229 | Aktualizuje `last_login` przy logowaniu |
| `verify_nonce` *(private)* | 236 | Weryfikacja nonce; rzuca wyjątek przy błędzie |
| `check_admin` *(private)* | 251 | Sprawdza uprawnienia admina/moda |
| `get_user_ip` *(private)* | 262 | Pobiera IP użytkownika |
| `get_status_label` *(private)* | 279 | Tłumaczy status punktu na etykietę |
| `get_report_status_label` *(private)* | 293 | Tłumaczy status zgłoszenia na etykietę |

**Traity (`includes/ajax/`) — kluczowe handlery:**

| Blok | Plik | Linia |
|------|------|-------|
| `get_points` | `trait-points-read.php` | 94 |
| `get_tags` | `trait-points-read.php` | 498 |
| `get_point_stats` | `trait-points-read.php` | 506 |
| `get_point_visitors` | `trait-points-read.php` | 588 |
| `get_user_info` | `trait-points-read.php` | 681 |
| `get_user_activity` | `trait-points-read.php` | 906 |
| `get_ranking` | `trait-points-read.php` | 1017 |
| `get_sidebar_points` | `trait-points-read.php` | 1187 |
| `submit_point` | `trait-points-write.php` | 17 |
| `update_point` | `trait-points-write.php` | 320 |
| `request_deletion` | `trait-points-write.php` | 786 |
| `vote` | `trait-points-write.php` | 895 |
| `report_point` | `trait-points-write.php` | 984 |
| `get_current_user` | `trait-auth.php` | 123 |
| `update_profile` | `trait-auth.php` | 277 |
| `login_user` | `trait-auth.php` | 503 |
| `register_user` | `trait-auth.php` | 674 |
| `forgot_password` | `trait-auth.php` | 856 |
| `google_oauth_callback` | `trait-auth.php` | 1072 |
| `get_point_history` | `trait-admin-edits.php` | 6 |
| `admin_approve_edit` | `trait-admin-edits.php` | 321 |
| `admin_reject_edit` | `trait-admin-edits.php` | 602 |
| `admin_approve_deletion` | `trait-admin-edits.php` | 1056 |
| `get_reports` | `trait-admin-moderation.php` | 16 |
| `admin_change_status` | `trait-admin-moderation.php` | 405 |
| `admin_approve_point` | `trait-admin-moderation.php` | 495 |
| `admin_reject_point` | `trait-admin-moderation.php` | 554 |
| `admin_delete_point` | `trait-admin-users.php` | 11 |
| `admin_ban_user` | `trait-admin-users.php` | 78 |
| `admin_delete_user` | `trait-admin-users.php` | 705 |
| `admin_change_owner` | `trait-admin-users.php` | 1035 |
| `get_place_categories` | `trait-categories.php` | 147 |
| `get_curiosity_categories` | `trait-categories.php` | 171 |
| `save_place_category` | `trait-admin-categories.php` | 348 |
| `save_curiosity_category` | `trait-admin-categories.php` | 519 |
| `delete_image` | `trait-images.php` | 261 |
| `set_featured_image` | `trait-images.php` | 392 |
| `reverse_geocode` | `trait-geocoding.php` | 18 |
| `search_address` | `trait-geocoding.php` | 104 |
| `request_promotion` | `trait-place-features.php` | 66 |
| `get_menu` | `trait-place-features.php` | 159 |
| `save_menu` | `trait-place-features.php` | 181 |
| `get_offerings` | `trait-place-features.php` | 395 |

---

### `assets/js/jg-map.js` (16 207 linii)

> Szczegółowy indeks sekcji → patrz **jg-map.js — indeks sekcji** wyżej.

| Blok | Linia | Opis |
|------|-------|------|
| `init()` | 822 | Inicjalizacja całej mapy Leaflet + ładowanie danych |
| `ALL` (dane punktów) | 6145 | Tablica wszystkich punktów mapy (wypełniana przez AJAX) |
| `addPulsingMarker` | 6107 | Dodaje animowany marker (pulsujący) |
| `voteReq` | 5878 | Funkcja wysyłająca głos (ocena gwiazdkowa) AJAX |
| `doVote` (handler kliknięcia gwiazdki) | 12337 | Obsługa kliknięcia gwiazdki w modalu |
| `removeMarkersById` | 6245 | Usuwa markery z mapy po ID |
| `openDetails` | 10725 | Otwiera modal ze szczegółami punktu |
| `openDetailsModalContent` | 10763 | Wypełnia treść modalu + ręczny `gtag page_view` |
| `initMapCategoryFilters` | 13686 | Inicjalizacja przycisków filtrów kategorii |
| `searchAddressSuggestions` | 14520 | Autouzupełnianie wyszukiwarki adresu (FAB / add form) |
| `searchEditAddressSuggestions` | 9774 | Autouzupełnianie w formularzu edycji |
| `loadUsers` | 10396 | Ładuje listę userów do formularza |
| `closeIt` | 15814 | Zamyka modal alertu |
| `shootMapMarkerConfetti` | 689 | Efekt konfetti przy dodaniu punktu |
| `initTagInput` | 1555 | Inicjalizacja pola tagów |
| `initRichEditor` | 1747 | Inicjalizacja edytora tekstu |
| `initOpeningHoursPicker` | 1385 | Picker godzin otwarcia |
| Klastry Leaflet (MCR) | ~4556 | `iconCreateFunction` — renderowanie klastrów (pełnoekranowy) |
| Klastry (sidebar) | ~4662 | `iconCreateFunction` — klastry w sidebarze |
| Promo marker (GP) | ~4851 | Marker reklamowy (typ `gp`) |

**Wzorzec `grep` do odnajdywania funkcji:**
```bash
grep -n "function nazwaFunkcji" jg-map.js
sed -n '10725,10800p' jg-map.js   # czyta ~70 linii od celu
```

---

### `assets/js/jg-sidebar.js` (1 280 linii)

| Blok | Linia | Opis |
|------|-------|------|
| `init` | 158 | Inicjalizacja sidebaru: AJAX, filtry, eventy |
| `saveFilterPrefs` / `loadFilterPrefs` | 55 / 62 | Zapis i odczyt preferencji filtrów (localStorage) |
| `loadSidebarFromCache` / `saveSidebarToCache` | 132 / 143 | Cache punktów w sessionStorage |
| `initSortPills` | 210 | Inicjalizacja przycisków sortowania |
| `initCategoryFilters` | 320 | Inicjalizacja filtrów kategorii |
| `setupEventListeners` | 360 | Delegacja eventów listy |
| `loadPoints` | 531 | Pobiera punkty z AJAX (z cache + silent refresh) |
| `updateStats` | 597 | Aktualizuje liczniki statystyk |
| `renderPoints` | 608 | Renderuje listę punktów |
| `appendNextBatch` | 664 | Lazy loading kolejnej porcji punktów |
| `buildTodayHoursHtml` | 686 | Generuje HTML godzin otwarcia na dziś |
| `createPointItem` | 757 | Buduje element DOM pojedynczego punktu |
| `buildInfoBadges` | 870 | Generuje badge'y (ocena, głosy, odległość…) |
| `handlePointClick` | 944 | Obsługuje kliknięcie punktu → zoom + openDetails |
| `humanTimeDiffPl` | 1055 | Formatuje różnicę czasu po polsku |
| `refreshOpeningHours` | 1105 | Odświeża stan godzin otwarcia bez przeładowania |
| `setupSync` | 1144 | Nasłuchuje zmian z sync queue (Heartbeat) |

---

### `assets/js/jg-auth.js` (1 533 linii)

| Blok | Linia | Opis |
|------|-------|------|
| `ensureModalsExist` | 9 | Tworzy DOM wszystkich modali auth (lazy, raz) |
| `showPendingActivationModal` | 123 | Modal oczekiwania na aktywację konta |
| `showRateLimitModal` | 205 | Modal blokady z odliczaniem (rate limit) |
| `showAttemptsWarningModal` | 296 | Modal ostrzeżenia o pozostałych próbach logowania |
| `openLoginModal` | 373 | Otwiera modal logowania |
| `openRegisterModal` | 377 | Otwiera modal rejestracji |
| `openJoinModal` | 389 | Otwiera modal login+rejestracja (z opcjami: OAuth, info) |
| `openAuthModal` | 678 | Ogólny modal auth z wyborem zakładki |
| `showForgotPasswordModal` | 687 | Modal resetowania hasła |
| `openEditProfileModal` | 773 | Modal edycji profilu (avatar, bio, hasło) |
| `openDeleteProfileConfirmation` | 868 | Modal potwierdzenia usunięcia konta |
| `openMyProfileModal` | 956 | Modal własnego profilu (punkty, osiągnięcia, aktywność) |
| `apiAjax` | 1095 | Wrapper AJAX dla akcji auth |
| `openUserActivityModal` | 1113 | Modal aktywności użytkownika |
| `openAllAchievementsModal` | 1145 | Modal wszystkich osiągnięć użytkownika |
| `openUserModal` | 1187 | Modal profilu dowolnego użytkownika |
| `openRankingModal` | 1389 | Modal rankingu użytkowników |
| `initAuthButtons` | 1447 | Binduje przyciski logowania/rejestracji w navbarze |
| `checkUrlMessages` | 1496 | Sprawdza parametry URL → otwiera odpowiedni modal |

---

### `assets/js/jg-map-ext.js` (124 linii)

Obiekt `JG_Ext` — rotacja banerów 728×90 w slocie mapy (bez jQuery).

| Blok | Linia | Opis |
|------|-------|------|
| `JG_Ext.init` | 12 | Inicjalizacja: odczytuje config z atrybutów DOM |
| `JG_Ext.loadContent` | 29 | AJAX fetch listy banerów |
| `JG_Ext.initRotation` | 49 | Wznawia rotację od ostatniego indeksu (sessionStorage) |
| `JG_Ext.displayItem` | 58 | Renderuje baner (obraz + link) w slocie |
| `JG_Ext.recordView` | 84 | Rejestruje wyświetlenie banera |
| `JG_Ext.recordAction` | 92 | Rejestruje kliknięcie banera |
| `JG_Ext.hideSlot` | 108 | Wyświetla CTA „Tu może być Twoja reklama" gdy brak banerów |

---

### `assets/js/jg-notifications.js` (172 linii)

| Blok | Linia | Opis |
|------|-------|------|
| `updateNotifications` | 48 | Aktualizuje ikonki powiadomień w top barze i mobilnym panelu użytkownika |
| Heartbeat send/receive | ~146 | Wysyła żądanie i odbiera odpowiedź z Heartbeat API |
| Initial load | ~162 | Pierwsze załadowanie powiadomień po 1 s |
| Backup interval | ~167 | Fallback odświeżanie co 10 s |

---

### `assets/js/jg-onboarding.js` (307 linii)

| Blok | Linia | Opis |
|------|-------|------|
| `shieldFromMap` | 15 | Blokuje propagację eventów click/touch do mapy pod spodem |
| `createHelpFAB` | 27 | Tworzy pływający przycisk „?" pomocy |
| `initHelpPanel` | 63 | Inicjalizacja panelu pomocy (otwieranie, zamykanie, klik poza) |
| `initMobileFilters` | 96 | Inicjalizacja mobilnych filtrów (toggle collapse) |
| `showTooltip` / `hideTooltip` | 169 / 180 | System tooltipów dla elementów mapy |
| `TOOLTIPS` | 188 | Definicje tooltipów (selektor → treść) |
| `initTooltips` | 258 | Binduje tooltips do elementów przez MutationObserver |
| `init` | 291 | Główna inicjalizacja onboardingu (DOMContentLoaded) |

---

### `assets/js/jg-session-monitor.js` (244 linii)

| Blok | Linia | Opis |
|------|-------|------|
| `showLogoutModal` | 22 | Modal wylogowania z opcjonalnym potwierdzeniem (Enter) |
| `showInfoModal` | 79 | Generyczny modal informacyjny z callbackiem |
| `logoutUser` | 125 | Wylogowuje przez AJAX i przekierowuje |
| `checkSessionStatus` | 142 | Odpytuje serwer o status sesji (wygaśnięcie, zmiana roli) |
| `startMonitoring` | 231 | Uruchamia polling sesji co N sekund |

---

### `assets/js/jg-banner-admin.js` (59 linii)

Prosty skrypt panelu admina — integracja z WordPress Media Library. Brak nazwanych funkcji; trzy handlery zdarzeń w `$(document).ready`:
- `#jg-upload-banner-image` — otwiera `wp.media`, wpisuje URL do `#banner_image_url`
- `.jg-edit-banner` — toggle formularza edycji banera
- `.jg-delete-banner` — confirm przed usunięciem

---

### `assets/js/tile-sw.js` (53 linii)

Service Worker buforujący kafelki mapy (Carto + ArcGIS). Trzy handlery:
- `install` (9) — `skipWaiting()`
- `activate` (13) — `clients.claim()` + czyszczenie starych cache'y
- `fetch` (27) — przechwytuje żądania kafelków: cache-first, fallback do sieci

---

### `assets/css/jg-map.css` (11 181 linii)

> **TOKEN BUDGET RULE — `jg-map.css` jest ~11k linii. NEVER read it whole.**
> Użyj `grep -n "\.jg-nazwa-klasy\|/\* ──.*słowo"` żeby zlokalizować blok, potem `Read` tylko ±100 linii wokół celu.

| Linia | Sekcja | Kluczowe selektory / komponenty |
|-------|--------|---------------------------------|
| 1 | CSS variables & font | `:root` — `--jg`, `--jg-sidebar-w`, `--jg-font`, skala border-radius |
| 117 | Info Bar | `.jg-info-bar` — scrollujący ticker nad navbarem |
| 288 | Custom Top Bar | `.jg-custom-top-bar`, poziomy XP, powiadomienia, logo, dropdown nav |
| 673 | Loading overlay | `.jg-fullscreen-overlay` — spinner przy ładowaniu mapy |
| 713 | Desktop filter bar | `.jg-filter-bar` — izolacja od Elementora |
| 813 | Sync status indicator | `.jg-sync-status` — ikonka synchronizacji real-time |
| 942 | Category filters | `.jg-category-filter` — dropdowny, checkboxy, kolumny kategorii |
| 1128 | Search autocomplete | `.jg-search-suggestions`, `.jg-search-side-panel` — wyniki wyszukiwania |
| 1235 | FAB (Add Point button) | `.jg-fab` — pływający przycisk dodawania punktu |
| 1401 | Map controls | `.leaflet-control-zoom`, `.jg-map-toggle-control` — zoom, satelita |
| 1441 | Fullscreen control | `.jg-fullscreen-control`, `.jg-fullscreen-btn` |
| 1477 | Fullscreen mode | `.jg-fullscreen` — overlaye filtrów i wyszukiwarki w trybie fullscreen |
| 1615 | Fullscreen sidebar overlay | `.jg-sidebar-fullscreen-overlay` — sidebar jako floating panel |
| 1842 | Mobile fullscreen | `.jg-fullscreen` na mobile — uproszczony layout |
| 2034 | Desktop-Wide Mode | Auto-aktywacja na ≥769px, sidebar + mapa obok siebie |
| 2154 | Fullscreen topbar | `.jg-fs-topbar` — controls bar nad mapą w fullscreen |
| 2541 | Base modals | `.jg-modal-bg`, `.jg-modal` — tło i kontener modali |
| 2883 | Modal mobile layout | Modale na mobile: full-screen, scrollable |
| 3138 | Point detail modal | `.jg-modal-bg` szczegóły — gallery, przyciski akcji, voting |
| 3286 | Rich Text Editor | `.jg-rich-editor` — edytor opisu punktu |
| 3350 | Incomplete sections | `.jg-section-incomplete` — badge "niekompletne" |
| 3673 | Elementor isolation | `.jg-btn`, `.jg-input` — reset Elementora dla przycisków i pól |
| 4752 | Cluster markers | `.jg-cluster-wrapper`, `.jg-cluster` — Leaflet klastry |
| 4861 | Satellite mode | Białe obrysy pinów i klastrów na warstwie satelitarnej |
| 4974 | Sidebar widget | `.jg-sidebar-widget` — lista punktów w sidebarze (karty, badge'e) |
| 5514 | Info-badges strip | `.jg-info-badge` — pasek ikon (ocena, dystans, godziny) w kartach |
| 5908 | Help panel & tooltips | `.jg-help-panel`, `.jg-tooltip` — panel pomocy i dymki |
| 6233 | Approval notification | `.jg-approval-notification` — modal zatwierdzenia punktu |
| 6612 | Levels, XP, Achievements | `.jg-level-badge`, `.jg-xp-bar`, `.jg-achievement` |
| 6990 | Ranking modal | `.jg-ranking-modal` — tabela rankingu użytkowników |
| 7489 | Business promo section | `.jg-business-promo` — sekcja promocji w modalu miejsca |
| 7543 | Tag input | `.jg-tag-input` — pole tagów w formularzu |
| 7659 | Tags display | `.jg-tag` — wyświetlanie tagów w modalu szczegółów |
| 7705 | Confetti particles | Współdzielone reguły animacji konfetti |
| 7760 | Mobile filter button | `.jg-mobile-filter-btn` — przycisk filtrów na mapie mobilnej |
| 8056 | Nav bar | `#jg-nav-bar` — hamburger, logo, mobilna szuflada nawigacji |
| 8427 | Mobile search bar | `.jg-mobile-search` — pasek wyszukiwania pod przyciskami |
| 8571 | Mobile redesign | `#jg-mobile-overlays` — floating overlays: user panel, controls, banner |
| 8665 | Mobile user panel | `.jg-mup` — panel użytkownika na mobile (avatar, XP, notyfikacje) |
| 8887 | Mobile controls row | `.jg-mobile-controls` — rząd ikon sterowania mapą |
| 9126 | Mobile banner slot | `.jg-mobile-banner` — baner 728×90 na mobile |
| 9202 | Contact + trasa | Przyciski kontaktu i wyznaczania trasy w modalu miejsca |
| 9256 | Menu modal | `.jg-menu-modal` — wyświetlanie menu restauracji |
| 9346 | Menu editor isolation | Elementor reset dla inputów edytora menu |
| 9629 | Menu editor panel | `.jg-menu-ed` — panel edycji menu wewnątrz modalEdit |
| 10024 | Contact footer bar | `.jg-footer-bar` — stały pasek kontaktowy na dole strony |
| 10238 | Challenge widgets | `.jg-challenge-widget` — widgety wyzwań tygodniowych (desktop + mobile) |
| 10642 | Benefits modal | `.jg-benefits-modal` — modal korzyści z rejestracji |
| 10681 | Reduced motion | `@media (prefers-reduced-motion)` — wyłączenie animacji |
| 10760 | Dark mode | `@media (prefers-color-scheme: dark)` — info bar, filtry, ikony, sortowanie |
| 11022 | Mobile swipe sidebar | `.jg-sidebar-mobile-open`, `.jg-sidebar-mobile-backdrop` — drawer z prawej |
