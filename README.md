# JG Interactive Map - Interaktywna Mapa Jelenia Góra

## 📋 Opis Projektu

**JG Interactive Map** to zaawansowana wtyczka WordPress, która tworzy społecznościową, interaktywną platformę mapową dla miasta Jelenia Góra. System umożliwia mieszkańcom zgłaszanie problemów infrastrukturalnych, dzielenie się lokalnymi ciekawostkami oraz zarządzanie informacjami o mieście poprzez intuicyjny interfejs webowy z funkcjami moderacji i zaangażowania społeczności.

**Wersja:** 3.5.3
**Licencja:** GPL v2 lub nowsza
**Język:** Polski
**Wymagania:** PHP 7.4+, WordPress 5.8+

---

## 🎯 Założenia i Cele Projektu

Wtyczka została stworzona jako **platforma zaangażowania obywatelskiego** z następującymi celami:

### Główne Założenia:
1. **Crowdsourcing Problemów Miejskich** - Umożliwienie mieszkańcom zgłaszania problemów infrastrukturalnych, zagrożeń bezpieczeństwa i potrzeb konserwacyjnych
2. **Dzielenie się Wiedzą Lokalną** - Możliwość oznaczania ciekawych miejsc, zabytków historycznych i lokalnych atrakcji
3. **Współpraca Miasto-Mieszkańcy** - Utworzenie kanału komunikacji między mieszkańcami a administratorami miasta
4. **Informacje w Czasie Rzeczywistym** - Dostarczanie aktualnych, opartych na lokalizacji informacji widocznych dla całej społeczności
5. **Demokratyczna Moderacja** - System głosowania społeczności (up/down) połączony z nadzorem administracyjnym

---

## ⚡ Kluczowe Funkcje

### 🗺️ Funkcje dla Użytkowników:

#### 1. Interaktywna Mapa
- Mapa oparta na Leaflet.js i OpenStreetMap
- Renderowanie w czasie rzeczywistym z grupowaniem markerów
- Responsywny design dla urządzeń mobilnych i desktopowych
- Konfigurowalna wysokość, zoom i centrum mapy przez shortcode

#### 2. System Zgłaszania Punktów
- Dodawanie punktów przez kliknięcie w mapę (tylko na maksymalnym zoomie)
- **Trzy typy punktów** z różnymi kolorami:
  - 🔴 **Zgłoszenie** - Czerwona pinezka (problemy infrastrukturalne, bezpieczeństwo)
  - 🔵 **Ciekawostka** - Niebieska pinezka (ciekawe miejsca)
  - 🟢 **Miejsce** - Zielona pinezka (ważne lokalizacje)

#### 3. Bogate Treści dla Każdego Punktu
- Tytuł i szczegółowy opis
- **Do 6 zdjęć** na punkt z wyborem zdjęcia głównego
- Informacje kontaktowe (strona www, telefon)
- Linki do mediów społecznościowych (Facebook, Instagram, LinkedIn, TikTok)
- Współrzędne GPS z automatycznym geokodowaniem adresu
- Kategorie tematyczne dla zgłoszeń

#### 4. Zaangażowanie Społeczności
- **System głosowania** (👍/👎) - jedna osoba = jeden głos
- **System raportowania** nieprawidłowych lub nieaktualnych treści
- **Głosowanie na aktualność** ("Czy to jest nadal aktualne?")
- Prośby o usunięcie punktów przez autorów
- Śledzenie odwiedzin punktów
- Statystyki użytkownika (stworzone punkty, otrzymane głosy)

#### 5. Wyszukiwanie i Filtrowanie
- Pełnotekstowe wyszukiwanie w tytułach i opisach
- Filtr po typie punktu
- Filtr "Moje miejsca"
- Filtr punktów sponsorowanych
- Lista boczna z sortowaniem (najnowsze, najstarsze, alfabetycznie, najpopularniejsze)

#### 6. Konta Użytkowników i Profile
- Rejestracja z weryfikacją email
- Login/wylogowanie
- Edycja profilu (nazwa, email, zmiana hasła)
- Reset hasła przez email (ważność 24h)
- Linki aktywacyjne (ważność 48h)
- Status konta (oczekujące/aktywne)

---

### 👨‍💼 Funkcje dla Administratorów i Moderatorów:

#### 1. Panel Moderacji
- **Zatwierdzanie/odrzucanie** nowych zgłoszeń punktów
- **Przegląd i akceptacja** edycji punktów
- **Obsługa raportów** użytkowników z komentarzami
- **Zarządzanie prośbami** o usunięcie punktów
- Historia edycji każdego punktu

#### 2. Zarządzanie Treścią
- Promowanie punktów do statusu "sponsorowane" (z zakresem dat)
- Dodawanie notatek administratora do punktów
- Ukrywanie/pokazywanie autorów punktów
- Zmiana statusu punktów (oczekujące → opublikowane → odrzucone)
- Operacje zbiorcze na wielu punktach

#### 3. Zarządzanie Użytkownikami
- Ban/odban użytkowników
- Ustawianie **dziennych limitów** na użytkownika:
  - Liczba punktów dziennie
  - Liczba edycji dziennie
  - Liczba usunięć dziennie
- Kontrola limitu zdjęć na użytkownika
- Blokowanie adresów IP
- Przeglądanie aktywności użytkowników

#### 4. Powiadomienia w Czasie Rzeczywistym
- Powiadomienia oparte na WordPress Heartbeat API
- **Odznaki** w górnym pasku pokazujące:
  - ➕ Nowe punkty oczekujące
  - 📝 Oczekujące edycje
  - 🚨 Raporty użytkowników
  - 🗑️ Prośby o usunięcie
- Bezpośrednie linki do sekcji moderacyjnych
- Aktualizacje co 15 sekund bez przeładowania strony

#### 5. Historia i Ścieżka Audytu
- **Kompletna historia edycji** z wartościami przed/po
- Logowanie działań administracyjnych
- Śledzenie adresów IP i user agent
- Znaczniki czasowe dla wszystkich operacji

---

## 🏗️ Architektura i Technologie

### Technologie Frontend:
- **Leaflet.js 1.9.4** - Renderowanie interaktywnej mapy
- **Leaflet MarkerCluster 1.5.3** - Grupowanie markerów na różnych poziomach zoomu
- **OpenStreetMap** - Dostawca kafelków mapy
- **jQuery** - Manipulacja DOM i AJAX
- **Vanilla JavaScript** - System modali, aktualizacje real-time
- **CSS3** - Responsywny design, flexbox, grid

### Technologie Backend:
- **PHP 7.4+** - Główny język
- **WordPress 5.8+** - Platforma
- **MySQL 5.6+** - Baza danych
- **WordPress Heartbeat API** - Powiadomienia push w czasie rzeczywistym
- **AJAX** - Operacje asynchroniczne

### Narzędzia Deweloperskie:
- **PHPUnit** - Framework do testów jednostkowych
- **PHPStan** - Statyczna analiza kodu
- **PHPCS** - Sprawdzanie standardów kodu (WordPress Coding Standards)
- **Mockery** - Biblioteka do mockowania w testach

### Usługi Zewnętrzne:
- **Nominatim API** - Odwrotne geokodowanie adresów
- **OpenStreetMap Tiles** - Kafelki mapy

---

## 📊 Struktura Bazy Danych

Wtyczka tworzy **7 tabel** w bazie danych:

### 1. **wp_jg_map_points** - Główna tabela punktów
Przechowuje wszystkie punkty mapy (zgłoszenia, ciekawostki, miejsca)
- Podstawowe pola: id, title, slug, content, excerpt
- Lokalizacja: lat, lng, address
- Metadane: type, status, category, author_id
- Media: images (JSON), featured_image
- Kontakt: website, phone, social media
- Administracja: admin_note, is_promo, promo_until
- Timestamps: created_at, updated_at, approved_at

### 2. **wp_jg_map_votes** - Głosowanie
Przechowuje głosy użytkowników (👍/👎)
- Constraint: jeden użytkownik = jeden głos na punkt
- Pola: point_id, user_id, vote_type, created_at

### 3. **wp_jg_map_reports** - Raporty
Zgłoszenia problemów z punktami
- Pola: point_id, user_id, email, reason, status
- Status: pending/resolved
- Admin decision dla rozwiązanych

### 4. **wp_jg_map_votes_relevance** - Głosowanie aktualności
Osobne głosowanie "Czy to jest nadal aktualne?"

### 5. **wp_jg_map_history** - Historia edycji
Kompletny audit trail wszystkich zmian
- Przechowuje old_values i new_values jako JSON
- Status: pending/completed (wymaga zatwierdzenia admina)

### 6. **wp_jg_map_point_visits** - Odwiedziny
Śledzenie kto oglądał które punkty
- Pola: point_id, user_id, visited_at, ip_address

### 7. **wp_jg_map_activity_log** - Log aktywności
Kompleksowe logowanie akcji administracyjnych
- Pola: user_id, action, object_type, object_id, description
- Śledzenie IP i user agent

---

## 🔒 Bezpieczeństwo

### Status Audytu Bezpieczeństwa:
✅ **9.2/10** - Zatwierdzone do produkcji (13 stycznia 2026)

### Zaimplementowane zabezpieczenia:

#### Ochrona CSRF:
- ✅ Wszystkie endpointy AJAX weryfikują nonce
- ✅ 42+ endpointów z weryfikacją `verify_nonce()`
- ✅ 100% pokrycie operacji modyfikujących dane

#### Zapobieganie SQL Injection:
- ✅ 116+ użyć `$wpdb->prepare()`
- ✅ Wszystkie zapytania używają prepared statements
- ✅ Brak bezpośredniej interpolacji zmiennych w SQL
- ✅ `intval()` i `floatval()` dla parametrów numerycznych

#### Zapobieganie XSS:
- ✅ 234+ instancji funkcji sanitizacji
- ✅ `sanitize_text_field()`, `sanitize_textarea_field()` na wejściu
- ✅ `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` na wyjściu
- ✅ Silny Content Security Policy (CSP)

#### Autentykacja i Autoryzacja:
- ✅ Sprawdzanie uprawnień użytkownika (`current_user_can()`)
- ✅ Weryfikacja operacji tylko dla adminów
- ✅ Wsparcie dla roli moderatora
- ✅ Blokowanie IP dla złośliwych użytkowników
- ✅ Banowanie użytkowników
- ✅ Rate limiting per użytkownika (dzienne limity zgłoszeń)

#### Dodatkowe zabezpieczenia:
- ✅ Ochrona `.htaccess` w folderze uploads
- ✅ Walidacja uploadowanych plików (sprawdzanie MIME type)
- ✅ Ochrona przed floodem (60-sekundowy cooldown między zgłoszeniami)
- ✅ Nagłówki bezpieczeństwa: X-Frame-Options, X-Content-Type-Options, X-XSS-Protection
- ✅ Wymuszanie Referrer Policy
- ✅ Permissions-Policy dla API urządzeń

### Testy:
- ✅ **75/75 testów ZALICZONYCH (100%)**
- ✅ **549/549 asercji ZALICZONYCH**
- Pokrycie testów: bezpieczeństwo, autoryzacja, walidacja, operacje DB, handlery AJAX

---

## 📦 Instalacja i Użycie

### Instalacja:

**Metoda 1 - Przez panel WordPress:**
1. Idź do: Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer
2. Wybierz plik ZIP
3. Kliknij "Zainstaluj teraz"
4. Aktywuj wtyczkę

**Metoda 2 - Przez FTP:**
1. Skopiuj folder do `/wp-content/plugins/`
2. Aktywuj wtyczkę w panelu WordPress

Wtyczka automatycznie tworzy wymagane tabele bazy danych i folder uploads przy aktywacji.

### Shortcody:

**Podstawowa mapa:**
```
[jg_map]
```

**Mapa z niestandardowymi parametrami:**
```
[jg_map lat="50.904" lng="15.734" zoom="13" height="600px"]
```

**Lista boczna:**
```
[jg_map_sidebar height="80dvh"]
```

**Parametry:**
- `lat` - Szerokość geograficzna centrum mapy (domyślnie: 50.904)
- `lng` - Długość geograficzna centrum mapy (domyślnie: 15.734)
- `zoom` - Poziom przybliżenia (domyślnie: 13)
- `height` - Wysokość mapy (domyślnie: 500px, akceptuje vh, dvh, px)

---

## 🚀 Kluczowe Moduły

### 1. Moduł Mapy (jg-map.js)
- Inicjalizacja mapy Leaflet
- Tworzenie i grupowanie markerów
- Obsługa kliknięć do dodawania punktów
- System modali (dodaj/wyświetl/edytuj/raportuj)
- Funkcje głosowania i raportowania
- Aktualizacje w czasie rzeczywistym
- Lightbox do zdjęć
- Obsługa błędów i stanów ładowania

### 2. Moduł Bocznej Listy (jg-sidebar.js)
- Wyświetlanie listy punktów
- Sortowanie i filtrowanie
- Wyświetlanie statystyk
- Panele szczegółów punktów

### 3. Moduł Autentykacji (jg-auth.js)
- Formularze modali logowania/rejestracji
- Walidacja formularzy
- Przepływ weryfikacji email
- Integracja resetu hasła
- Zarządzanie sesją

### 4. System Powiadomień (jg-notifications.js)
- Alerty w czasie rzeczywistym przez Heartbeat
- Powiadomienia moderacyjne
- Aktualizacje odznak w górnym pasku
- Bezpośrednie linki do oczekujących elementów

### 5. Monitor Sesji (jg-session-monitor.js)
- Śledzenie statusu logowania
- Monitorowanie ważności sesji
- Auto-wylogowanie przy wygaśnięciu tokena
- Synchronizacja stanu sesji między kartami

### 6. Synchronizacja Real-time (class-sync-manager.php)
- Kolejka zdarzeń oparta na bazie danych
- Integracja z Heartbeat do aktualizacji push
- Automatyczne ponawianie z wykładniczym wycofywaniem
- Typy zdarzeń: point_created, point_updated, point_approved, point_deleted, report_added, edit_submitted, deletion_requested
- Czyszczenie starych zdarzeń przez WordPress cron

---

## 🎨 Główne Przepływy Użytkownika

### Dodawanie Punktu:
1. Użytkownik loguje się
2. Przechodzi do mapy
3. Przybliża do maksimum (zabezpieczenie przed przypadkowym dodaniem)
4. Klika na mapie w wybranej lokalizacji
5. Otwiera się modal z formularzem
6. Wypełnia tytuł, opis, wybiera typ, uploaduje do 6 zdjęć
7. Wysyła do moderacji
8. Admin przegląda i zatwierdza/odrzuca
9. Użytkownik dostaje powiadomienie email

### Głosowanie:
1. Użytkownik widzi punkt na mapie
2. Klika punkt aby zobaczyć szczegóły
3. Klika strzałkę w górę/dół aby zagłosować
4. Głos jest rejestrowany (jeden na użytkownika na punkt)
5. Licznik głosów aktualizuje się w czasie rzeczywistym

### Raportowanie Problemów:
1. Użytkownik klika "Zgłoś" na punkcie
2. Wybiera powód z listy kategorii (infrastruktura, bezpieczeństwo, transport, etc.)
3. Opcjonalnie dodaje komentarz i email
4. Raport trafia do admina
5. Admin przegląda i rozwiązuje lub usuwa punkt

### Edycja Punktów:
1. Właściciel punktu klika "Edytuj" na swoim punkcie
2. Modyfikuje tytuł, opis, zdjęcia, kontakt
3. Wysyła do ponownej moderacji
4. Admin zatwierdza/odrzuca zmiany
5. Zmiany wchodzą w życie po zatwierdzeniu

---

## 🔧 Panel Moderacji

### Zakładki Panelu:

**1. Oczekujące Punkty**
- Nowe zgłoszenia czekające na zatwierdzenie
- Podgląd ze wszystkimi szczegółami
- Przyciski zatwierdź/odrzuć
- Możliwość dodania notatki administratora
- Widok IP zgłaszającego i historii

**2. Raporty**
- Zgłoszone przez użytkowników problemy
- Kategoryzacja po typie problemu
- Opcje decyzji admina: zachowaj punkt lub usuń
- Notatki rozwiązania
- Oznaczanie jako rozwiązane

**3. Edycje**
- Modyfikacje punktów oczekujące na zatwierdzenie
- Porównanie przed/po
- Zatwierdzanie/odrzucanie konkretnych edycji
- Pełna historia edycji

**4. Usunięcia**
- Prośby użytkowników o usunięcie
- Przegląd powodu usunięcia
- Zatwierdzenie (usuń punkt) lub odrzucenie (zachowaj punkt)

**5. Zarządzanie Użytkownikami**
- Widok wszystkich użytkowników i ich wkładów
- Ban/odban użytkowników
- Ustawianie dziennych limitów zgłoszeń
- Kontrola kwot uploadów zdjęć
- Blokowanie podejrzanych IP
- Przeglądanie logu aktywności

---

## ⚙️ Optymalizacje Wydajnościowe

- **Grupowanie markerów** redukuje czas renderowania z O(n) do O(log n)
- **Indeksy bazy danych** na często odpytywanych kolumnach
- **Transient caching** dla URL-i strony mapy
- Aktualizacje w czasie rzeczywistym używają **Heartbeat** (już działającego w WordPress)
- Efektywne kodowanie/dekodowanie JSON
- Optymalizacja zapytań z DISTINCT i odpowiednimi JOIN-ami
- Optymalizacja obrazów (pary URL miniaturka/pełny rozmiar)
- Czyszczenie service worker'ów aby zapobiec problemom z cache

---

## 🌐 Funkcje SEO

### Optymalizacja dla Wyszukiwarek:
- **Indywidualne URL** dla każdego punktu:
  - `/miejsce/slug/`
  - `/ciekawostka/slug/`
  - `/zgloszenie/slug/`
- **XML Sitemap**: `jg-map-sitemap.xml`
- **Schema.org JSON-LD** markup dla LocalBusiness/Place
- **Open Graph** i Twitter Card metadata
- **Geo tags** (ICBM, geo.position)
- Odpowiednie dyrektywy robots
- Różne traktowanie botów vs. ludzi

---

## 📈 Funkcje Real-time

- Markery aktualizują się bez przeładowania strony
- Nowe punkty widoczne dla wszystkich użytkowników natychmiast
- Raporty i usunięcia powodują natychmiastowe usunięcie z mapy
- Głosy aktualizują się na żywo
- Odznaki powiadomień aktualizują się bez odświeżania
- Interwał aktualizacji: co 15 sekund (Heartbeat API)

---

## 📝 Informacje o Wersji

**Aktualna Wersja:** 3.5.3
**Branch:** `claude/add-project-description-GNLKv`

### Ostatnie Zmiany:
- Ukończony audyt bezpieczeństwa (13 stycznia 2026)
- Optymalizacja wydajności (15 stycznia 2026)
- Ulepszenia synchronizacji real-time
- Poprawki inicjalizacji globalnej zmiennej $wpdb
- Rozszerzenie wyświetlania informacji o zablokowanych użytkownikach

### Pliki Konfiguracyjne:
- `composer.json` - Zależności PHP (narzędzia testowe)
- `phpunit.xml.dist` - Konfiguracja testów
- `.gitignore` - Wykluczenia vendor/node_modules

### Metadane Wtyczki:
- Text Domain: `jg-map` (dla tłumaczeń)
- Domain Path: `/languages`
- Licencja: GPL v2 lub nowsza

---

## 🎯 Podsumowanie

**JG Interactive Map** to **gotowa do produkcji wtyczka WordPress** dla **zaangażowania obywatelskiego i społecznościowych informacji o mieście**. Łączy przyjazny dla użytkownika interfejs z rozbudowanymi narzędziami administracyjnymi, kompleksowym bezpieczeństwem i synchronizacją w czasie rzeczywistym.

Wtyczka umożliwia społecznościom współpracę w zgłaszaniu problemów, dzieleniu się wiedzą i zaangażowaniu w lokalne zarządzanie poprzez interfejs interaktywnej mapy.

### Kluczowe Liczby:
- ✅ **75/75 zaliczonych testów** (100%)
- 🔒 **9.2/10 ocena bezpieczeństwa**
- 🗄️ **7 tabel bazy danych**
- 🔌 **42+ endpointy AJAX**
- 📊 **234+ instancji sanityzacji**
- 🛡️ **116+ prepared statements**
- 🎨 **3 typy punktów** (Zgłoszenie, Ciekawostka, Miejsce)
- 📸 **Do 6 zdjęć** na punkt
- ⚡ **Aktualizacje co 15 sekund** (real-time)

Wtyczka reprezentuje **dojrzałe, dobrze zaprojektowane rozwiązanie** dla platform społecznościowych skupionych na zaangażowaniu obywatelskim.

---

## 📞 Wsparcie i Rozwój

### Struktura Projektu:
```
jg-interactive-map/
├── jg-interactive-map.php         # Główny plik wtyczki
├── includes/                      # Klasy PHP
│   ├── class-database.php
│   ├── class-ajax-handlers.php
│   ├── class-admin.php
│   ├── class-shortcode.php
│   ├── class-enqueue.php
│   ├── class-activity-log.php
│   ├── class-sync-manager.php
│   └── class-maintenance.php
├── assets/
│   ├── js/                        # Pliki JavaScript
│   └── css/                       # Style CSS
├── tests/                         # Testy PHPUnit
├── composer.json                  # Zależności PHP
├── phpunit.xml.dist              # Konfiguracja testów
└── README.md                      # Ten plik

```

### Dla Deweloperów:

**Uruchomienie testów:**
```bash
composer install
vendor/bin/phpunit
```

**Analiza statyczna:**
```bash
vendor/bin/phpstan analyse
```

**Sprawdzanie standardów kodu:**
```bash
vendor/bin/phpcs
```

---

## 📄 Licencja

Ten projekt jest licencjonowany na zasadach **GPL v2 lub nowszej**.

---

**Stworzone z ❤️ dla społeczności Jeleniej Góry**
