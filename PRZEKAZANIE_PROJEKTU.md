# PRZEKAZANIE PROJEKTU — JG Interactive Map

**Data przekazania:** 2026-02-18
**Projekt:** JG Interactive Map — Interaktywna Mapa Jeleniej Góry
**Wersja:** 3.16.0
**Strona produkcyjna:** https://jeleniogorzanietomy.pl
**Repozytorium:** wordsmithseo/jeleniogorzanietomy

---

## 1. O CO CHODZI W PROJEKCIE

To plugin WordPress, który daje mieszkańcom Jeleniej Góry interaktywną mapę miasta. Ludzie mogą:

- **Zgłaszać problemy** (dziura w drodze, zepsuty chodnik, brak oświetlenia) — czerwone pinezki
- **Dzielić się ciekawostkami** (historia miejsca, ukryty zaułek, street art) — niebieskie pinezki
- **Oznaczać ważne miejsca** (szpitale, urzędy, szkoły) — zielone pinezki
- **Głosować** na punkty innych (w górę / w dół)
- **Zdobywać osiągnięcia** i wspinać się w rankingu (gamifikacja)

Projekt jest **obywatelski** — chodzi o to, żeby mieszkańcy mieli realne narzędzie do poprawy swojego miasta i poczucie sprawczości.

---

## 2. STOS TECHNOLOGICZNY

| Warstwa | Technologia | Uwagi |
|---------|-------------|-------|
| CMS | WordPress 5.8+ | Plugin, nie theme |
| Backend | PHP 7.4+ | Klasy OOP, singleton pattern |
| Baza danych | MySQL 5.6+ | 10 własnych tabel (`wp_jg_map_*`) |
| Frontend | Vanilla JS + jQuery | Bez Reacta/Vue — wszystko natywne |
| Mapa | Leaflet.js | Open-source, nie Google Maps |
| CSS | Czysty CSS3 | Jeden plik, 5952 linii |
| Testy | PHPUnit 9.5 | 75 testów, 549 asercji, 100% pass |
| Jakość kodu | PHPStan + CodeSniffer | WordPress coding standards |
| Pakiety PHP | Composer | Dev-only dependencies |

**Brak** npm, webpacka, TypeScripta ani żadnego bundlera. Frontend jest vanilla.

---

## 3. ARCHITEKTURA — CO GDZIE LEŻY

```
jeleniogorzanietomy/
├── GROWTH_PLAN.md                    # Plan wzrostu i marketingu
├── PRZEKAZANIE_PROJEKTU.md           # TEN DOKUMENT
│
└── jg-interactive-map/               # <-- Cały plugin WordPress
    ├── jg-interactive-map.php         # Punkt wejścia pluginu (singleton)
    ├── composer.json                  # Zależności PHP
    ├── phpunit.xml.dist               # Konfiguracja testów
    │
    ├── includes/                      # BACKEND (PHP)
    │   ├── class-database.php         # Schemat bazy, migracje, CRUD
    │   ├── class-ajax-handlers.php    # ~97 endpointów AJAX (SERCE BACKENDU)
    │   ├── class-admin.php            # Panel admina WordPress
    │   ├── class-enqueue.php          # Ładowanie skryptów i styli
    │   ├── class-shortcode.php        # Shortcodes [jg_map], [jg_map_sidebar] etc.
    │   ├── class-levels-achievements.php  # System XP, poziomów, odznak
    │   ├── class-activity-log.php     # Logowanie akcji użytkowników
    │   ├── class-sync-manager.php     # Synchronizacja danych
    │   ├── class-maintenance.php      # Tryb konserwacji, czyszczenie
    │   ├── class-banner-manager.php   # Rotacja banerów reklamowych
    │   └── class-banner-admin.php     # Admin banerów
    │
    ├── assets/
    │   ├── js/
    │   │   ├── jg-map.js             # GŁÓWNY plik JS (10595 linii!)
    │   │   ├── jg-auth.js            # Logowanie, rejestracja, reset hasła
    │   │   ├── jg-sidebar.js         # Lista punktów w sidebarze
    │   │   ├── jg-onboarding.js      # Tutorial dla nowych użytkowników
    │   │   ├── jg-notifications.js   # Powiadomienia real-time
    │   │   ├── jg-session-monitor.js # Monitoring sesji
    │   │   ├── jg-banner.js          # Rotacja banerów na froncie
    │   │   └── jg-banner-admin.js    # Upload banerów w adminie
    │   │
    │   └── css/
    │       └── jg-map.css            # Wszystkie style (5952 linii)
    │
    ├── tests/                         # Testy PHPUnit
    └── docs/                          # Raporty audytów i optymalizacji
```

### Kluczowe pliki, które trzeba znać

| Plik | Rozmiar | Co robi | Kiedy dotykasz |
|------|---------|---------|----------------|
| `class-ajax-handlers.php` | 8607 linii | Obsługuje WSZYSTKIE requestów z frontendu | Prawie zawsze |
| `jg-map.js` | 10595 linii | Cała logika mapy, modali, formularzy | Każda zmiana UI |
| `class-admin.php` | 6180 linii | Cały panel admina | Zmiany w zarządzaniu |
| `class-database.php` | 1866 linii | Schemat bazy, migracje | Nowe tabele/kolumny |
| `jg-map.css` | 5952 linii | Wszystkie style | Każda zmiana wyglądu |
| `class-levels-achievements.php` | 999 linii | Gamifikacja (XP, odznaki, rankingi) | Zmiany w grywalizacji |

---

## 4. BAZA DANYCH — 10 TABEL

Wszystkie tabele mają prefix `wp_jg_map_`. Oto co przechowują:

| Tabela | Cel | Relacje |
|--------|-----|---------|
| `points` | Punkty na mapie (tytuł, opis, GPS, typ, status, zdjęcia) | Główna tabela |
| `votes` | Głosy up/down na punkty | → points, → users |
| `reports` | Zgłoszenia nieprawidłowych punktów | → points, → users |
| `history` | Historia edycji punktów (kto, co, kiedy zmienił) | → points, → users |
| `relevance_votes` | Głosy "Czy punkt nadal aktualny?" | → points, → users |
| `point_visits` | Statystyki odwiedzin punktów | → points |
| `user_xp` | Aktualne XP i poziom użytkownika | → users |
| `xp_log` | Historia zdobywania XP | → users |
| `achievements` | Definicje osiągnięć (admin konfiguruje) | — |
| `user_achievements` | Odblokowane osiągnięcia użytkowników | → users, → achievements |

**Migracje** — schemat jest wersjonowany przez opcję `jg_map_schema_version`. Każda zmiana schematu wymaga bumpu wersji w `class-database.php`.

---

## 5. JAK DZIAŁA KOMUNIKACJA FRONT ↔ BACK

Cała komunikacja odbywa się przez **WordPress AJAX** (`admin-ajax.php`).

```
Frontend (jg-map.js)  →  POST /wp-admin/admin-ajax.php?action=jg_submit_point
                      ←  JSON response { success: true, data: {...} }
```

Jest **~97 endpointów AJAX** w `class-ajax-handlers.php`. Dzielą się na:

- **Publiczne** (`wp_ajax_nopriv_*`) — dostępne bez logowania (pobieranie punktów, logowanie, rejestracja)
- **Zalogowani** (`wp_ajax_*`) — wymagają sesji (dodawanie, głosowanie, edycja)
- **Adminowe** — wymagają `manage_options` lub `jg_map_moderate` (moderacja, bany, konfiguracja)

Każdy endpoint ma:
1. Weryfikację nonce (CSRF)
2. Sprawdzenie uprawnień
3. Sanityzację inputu
4. Przygotowane zapytania SQL (`$wpdb->prepare()`)

---

## 6. BEZPIECZEŃSTWO — CO WARTO WIEDZIEĆ

Projekt przeszedł audyt bezpieczeństwa (ocena **9.2/10**). Zrobione:

- 116+ miejsc z `$wpdb->prepare()` (ochrona SQL injection)
- 234+ wywołań sanityzacji/escapowania (ochrona XSS)
- Nonce na każdym endpoincie (ochrona CSRF)
- Rate limiting — 60s między dodawaniem punktów
- System banów i ograniczeń użytkowników
- Blokowanie IP
- Nagłówki Content Security Policy

**Czerwone flagi** na które uważać:
- Plik `class-ajax-handlers.php` ma 8607 linii — przy każdej zmianie upewnij się, że nonce i sanityzacja są na miejscu
- Upload zdjęć — max 6 na punkt, walidacja typów MIME
- Dane geolokalizacyjne — walidować lat/lng jako float

---

## 7. TESTY — JAK URUCHOMIĆ

```bash
cd jg-interactive-map

# Wszystkie testy
composer test

# Testy z pokryciem kodu
composer test:coverage

# Linting (WordPress coding standards)
composer phpcs

# Analiza statyczna
composer phpstan

# Wszystko naraz
composer check
```

**Stan aktualny:** 75/75 testów PASS, 549/549 asercji PASS.

---

## 8. AKTUALNY STAN PROJEKTU — CO DZIAŁA

### Zrealizowane funkcjonalności

| Funkcja | Status | Uwagi |
|---------|--------|-------|
| Mapa interaktywna (Leaflet) | DONE | W pełni działająca |
| 3 typy punktów (zgłoszenie/ciekawostka/miejsce) | DONE | Z kolorowymi pinezkami |
| System rejestracji/logowania | DONE | Własny, nie WordPress-owy |
| Dodawanie punktów z walidacją | DONE | Upload do 6 zdjęć |
| System głosowania up/down | DONE | Z zabezpieczeniem przed wielokrotnym głosem |
| Panel moderacji | DONE | Akceptacja, odrzucenie, notatki |
| System raportowania punktów | DONE | Z kategoriami powodów |
| Historia edycji | DONE | Z możliwością cofnięcia |
| Gamifikacja (XP, poziomy, odznaki) | DONE | 5 poziomów, konfigurowalne osiągnięcia |
| Ranking użytkowników | DONE | Miesięczny i ogólny |
| Wyszukiwarka adresowa | DONE | Z autocomplem i loaderem |
| System banerów reklamowych | DONE | Rotacja, tracking kliknięć |
| Onboarding nowych użytkowników | DONE | Tutorial krok po kroku |
| Powiadomienia email | DONE | Nowy punkt, akceptacja, odrzucenie |
| Udostępnianie punktów (FB, WhatsApp, link) | DONE | Z OG tagami |
| Tryb konserwacji | DONE | Z komunikatem dla użytkowników |
| System banów użytkowników | DONE | Ban + ograniczenia (soft-ban) |
| Responsywny design mobile | DONE | Z dopasowaniem banerów |
| Optymalizacja wydajności (batch loading) | DONE | Redukcja zapytań z 7000 do 300 |

### Ostatnie prace (z historii git)

- Loader i komunikaty błędów w wyszukiwarce adresów
- Zabezpieczenie zapytań SQL (`$wpdb->prepare()`)
- Mechanizm "mark-as-seen" dla powiadomień o osiągnięciach
- Fix nakładania się przycisków usuwania zdjęć
- Dopasowanie pozycji i skali banerów mobilnych

---

## 9. CO JEST DO ZROBIENIA — ZADANIA DLA ZESPOŁU

Poniżej lista zadań pogrupowana priorytetowo. Bazuję na GROWTH_PLAN.md i analizie kodu.

### PRIORYTET WYSOKI — Quick Wins (mało kodu, duży efekt)

| # | Zadanie | Typ | Trudność | Opis |
|---|---------|-----|----------|------|
| 1 | **Web Share API (natywne udostępnianie)** | Frontend | Łatwa | Na mobilkach zamienić przyciski FB/WA na natywny dialog systemu. Fallback na obecne przyciski. Plik: `jg-map.js` |
| 2 | **Kontakt z Jelonka.com** | Marketing | — | Napisać pitch do lokalnego portalu. Cykliczna rubryka "Zgłoszenie tygodnia". Zero kodu. |
| 3 | **Posty na FB grupach JG** | Marketing | — | 2-3 posty tygodniowo ze screenshotami mapy + linkiem. Zero kodu. |
| 4 | **Wyzwanie "Zimowy Patrol"** | Marketing+Frontend | Łatwa | Banner na mapie zachęcający do zgłaszania nieodśnieżonych chodników. Ograniczone czasowo. |
| 5 | **Baseline KPI w Google Analytics** | Analityka | Łatwa | Skonfigurować events/goals w GA dla kluczowych akcji (dodanie punktu, głos, rejestracja) |

### PRIORYTET ŚREDNI — Rozwój funkcjonalności

| # | Zadanie | Typ | Trudność | Opis |
|---|---------|-----|----------|------|
| 6 | **Newsletter tygodniowy** | Backend+Email | Średnia | Automatyczne podsumowanie: nowe punkty, top głosowane, statystyki. Cron job WP + template maila. Pliki: `class-ajax-handlers.php`, nowa klasa |
| 7 | **Rozbudowa onboardingu** | Frontend | Średnia | Obecny `jg-onboarding.js` jest podstawowy. Dodać: zachęcenie do pierwszego głosu, pokazanie systemu odznak, "Twój pierwszy punkt w 60 sekund" |
| 8 | **Web Push Notifications** | Frontend+Backend | Średnia | Powiadomienia o głosach na punkty, nowych punktach w okolicy, nowych wyzwaniach |
| 9 | **System zaproszeń z trackingiem** | Full-stack | Średnia | Link zapraszający z kodem użytkownika. Nagroda (odznaka + XP) za aktywnego zaproszonego. Nowa tabela w bazie |
| 10 | **Komentarze pod punktami** | Full-stack | Średnia | Forum-like komentarze z @wzmiankami. Nowa tabela `wp_jg_map_comments`. Moderacja |
| 11 | **Status zgłoszeń (feedback loop)** | Full-stack | Średnia | Statusy: Nowe → Zweryfikowane → Przekazane → W realizacji → Zrealizowane. Kluczowe dla współpracy z Urzędem Miasta |

### PRIORYTET NISKI — Skalowanie (po zbudowaniu bazy użytkowników)

| # | Zadanie | Typ | Trudność | Opis |
|---|---------|-----|----------|------|
| 12 | **Embeddable widget (iframe)** | Frontend | Średnia | Mini-mapa do osadzenia na stronach dzielnicowych/blogach. Filtrowanie per dzielnica |
| 13 | **Landing pages per dzielnica (SEO)** | Frontend+Backend | Średnia | Dedykowane strony: `/cieplice`, `/sobieszow`, `/zabobrze` z mapą przefiltrowaną na dzielnicę |
| 14 | **RSS feed** | Backend | Łatwa | Feed z najnowszymi punktami. Standard WP |
| 15 | **Publiczne API REST** | Backend | Trudna | Endpointy WP REST API dla deweloperów. Dokumentacja OpenAPI |
| 16 | **Integracja z Budżetem Obywatelskim** | Full-stack | Trudna | Punkty na mapie jako propozycje do BO. Wymaga współpracy z UM |
| 17 | **Program Ambasadorów Dzielnic** | Full-stack | Trudna | Nowa rola użytkownika, dedykowane uprawnienia, panel ambasadora |

### DŁUG TECHNICZNY — Do posprzątania

| # | Zadanie | Typ | Trudność | Opis |
|---|---------|-----|----------|------|
| 18 | **Rozbicie `jg-map.js`** | Refaktor | Trudna | 10595 linii w jednym pliku to za dużo. Podzielić na moduły: map-core, modals, forms, filters, voting, sharing. **Ale uwaga — bez bundlera to wymaga przemyślenia strategii ładowania** |
| 19 | **Rozbicie `class-ajax-handlers.php`** | Refaktor | Trudna | 8607 linii. Podzielić na kontrolery: PointsController, VotesController, ReportsController, AdminController, AuthController |
| 20 | **Aktualizacja README.md** | Docs | Łatwa | README opisuje 3 tabele, a jest ich 10. Brak info o gamifikacji, banerach, nowych funkcjach |

---

## 10. PEOPLE & ROLES — KOGO POTRZEBUJESZ

Dla tego projektu optymalny skład to:

| Rola | Zakres | Kiedy kluczowy |
|------|--------|---------------|
| **PHP Developer (WordPress)** | Backend, endpointy AJAX, baza danych, email, cron jobs | Nowe funkcje, newsletter, API, komentarze |
| **Frontend Developer (JS/CSS)** | Mapa Leaflet, modale, formularze, responsive | UI/UX, Web Share API, onboarding, widgety |
| **QA / Tester** | Testy manualne + PHPUnit | Przed każdym deployem |
| **Content / Marketing** | Posty FB, kontakt z mediami, wyzwania sezonowe | Ciągle — to napędza wzrost |
| **DevOps** (opcjonalnie) | CI/CD, monitoring, backup | Jednorazowe ustawienie |

---

## 11. JAK DEPLOYOWAĆ

Plugin jest deployowany jako katalog WordPress:

1. Zmiany commitujemy do repo
2. Pakujemy katalog `jg-interactive-map/` do ZIP
3. Upload przez panel WordPress → Wtyczki → Dodaj nową → Prześlij
4. Lub bezpośredni upload FTP do `/wp-content/plugins/`
5. Po aktywacji plugin sam tworzy/migruje tabele bazy danych

**Brak CI/CD** — deployment jest manualny. Warto ustawić pipeline (np. GitHub Actions) który:
- Odpala `composer check` na każdym PR
- Buduje ZIP artefakt
- Opcjonalnie deployuje na staging

---

## 12. KONFIGURACJA ŚRODOWISKA

### Wymagania serwera produkcyjnego
- PHP 7.4+ (zalecane 8.1+)
- MySQL 5.6+
- WordPress 5.8+
- `upload_max_filesize` ≥ 10M (upload zdjęć)
- `post_max_size` ≥ 10M
- Moduł GD lub Imagick (resize zdjęć)
- SSL (HTTPS)

### Konfiguracja w WordPress
Opcje w tabeli `wp_options`:
- `jg_map_schema_version` — wersja schematu bazy (automatyczne migracje)
- `jg_map_registration_enabled` — włączenie/wyłączenie rejestracji
- `jg_map_terms_url` — URL regulaminu
- `jg_map_privacy_url` — URL polityki prywatności

### Email
- From: `powiadomienia@jeleniogorzanietomy.pl`
- Wymaga poprawnej konfiguracji SMTP na serwerze (lub plugin WP Mail SMTP)

---

## 13. ZNANE RYZYKA I PUŁAPKI

| Ryzyko | Opis | Mitygacja |
|--------|------|-----------|
| **Wielkość plików** | `jg-map.js` (10.6K linii) i `class-ajax-handlers.php` (8.6K linii) to monolity. Każda zmiana niesie ryzyko side-effectów | Testy PHPUnit + ręczne testy przed deployem |
| **Brak CI/CD** | Deploy manualny = ryzyko ludzkiego błędu | Wdrożyć GitHub Actions |
| **Brak staging** | Zmiany lądują od razu na produkcji | Ustawić środowisko staging |
| **Jedno-osobowy bus factor** | Projekt robiony przez jedną osobę | Ten dokument + onboarding nowej osoby |
| **Skalowalność** | Przy >10K punktów mogą pojawić się problemy z wydajnością | Batch loading już wdrożony, ale monitoring potrzebny |
| **Brak bundlera** | Brak minifikacji JS/CSS na produkcji | Rozważyć Vite/Webpack lub przynajmniej minifikację |

---

## 14. KOMENDY DO ZAPAMIĘTANIA

```bash
# Klonowanie repo
git clone <repo-url> && cd jeleniogorzanietomy

# Instalacja zależności deweloperskich
cd jg-interactive-map && composer install

# Uruchomienie testów
composer test

# Pełna kontrola jakości (linting + analiza + testy)
composer check

# Sprawdzenie standardów kodowania
composer phpcs

# Analiza statyczna
composer phpstan

# Testy z raportem pokrycia
composer test:coverage
```

---

## 15. SHORTCODES — JAK MAPA JEST OSADZONA NA STRONIE

| Shortcode | Co robi | Parametry |
|-----------|---------|-----------|
| `[jg_map]` | Wyświetla mapę | `lat`, `lng`, `zoom`, `height` |
| `[jg_map_sidebar]` | Lista punktów w sidebarze | — |
| `[jg_map_directory]` | Tabelaryczny widok punktów | — |
| `[jg_banner]` | Baner reklamowy | — |

Domyślne centrum mapy: **50.904°N, 15.734°E** (Jelenia Góra), zoom 13.

---

## 16. KONTAKTY I ZASOBY

| Zasób | Lokalizacja |
|-------|------------|
| Strona produkcyjna | https://jeleniogorzanietomy.pl |
| Repozytorium | GitHub: wordsmithseo/jeleniogorzanietomy |
| Plan wzrostu | `GROWTH_PLAN.md` w root repo |
| Audyt bezpieczeństwa | `jg-interactive-map/SECURITY_AUDIT_2026.md` |
| Raport wydajności | `jg-interactive-map/PERFORMANCE_OPTIMIZATION_2026.md` |
| README pluginu | `jg-interactive-map/README.md` |

---

## PODSUMOWANIE DLA NASTĘPCY

1. **Projekt działa i jest stabilny** — 75/75 testów pass, audyt bezpieczeństwa 9.2/10
2. **Kod jest duży ale uporządkowany** — klasy PHP mają jasny podział odpowiedzialności
3. **Największe pliki to monolity** — `jg-map.js` i `class-ajax-handlers.php` wymagają ostrożności
4. **Wzrost zależy od marketingu** — funkcjonalności są gotowe, teraz potrzeba użytkowników
5. **Quick wins są w GROWTH_PLAN.md** — zacznij od kontaktu z lokalnymi mediami i sezonowych wyzwań
6. **Przed każdym deployem** — `composer check` + test manualny na staging (jeśli masz) lub lokanie
7. **Nie dotykaj bazy ręcznie** — migracje przez `class-database.php`, wersjonowane `jg_map_schema_version`

Powodzenia. Projekt jest solidny i ma potencjał. Teraz potrzebuje ludzi, którzy go poprowadzą.
