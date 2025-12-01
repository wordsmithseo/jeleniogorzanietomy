# JG Interactive Map - Plugin WordPress

Plugin interaktywnej mapy dla Jeleniej Góry umożliwiający dodawanie zgłoszeń, ciekawostek i miejsc przez użytkowników.

## 📋 Opis

JG Interactive Map to kompleksowy plugin WordPress, który pozwala na:

- 🗺️ Wyświetlanie interaktywnej mapy Leaflet
- 📍 Dodawanie punktów przez użytkowników (zgłoszenia, ciekawostki, miejsca)
- 👍 System głosowania (like/dislike)
- 🖼️ Upload zdjęć (max 6 na punkt)
- 🛡️ System moderacji treści
- 📢 Zgłaszanie nieprawidłowych punktów
- ⭐ System promocji punktów
- 👥 Ukrywanie/pokazywanie autorów
- 🔍 Wyszukiwanie i filtrowanie punktów
- 📧 Powiadomienia email dla administratora

## 🚀 Instalacja

### Sposób 1: Przez panel WordPress (zalecany)

1. Spakuj katalog `jg-interactive-map` do pliku ZIP
2. W panelu WordPress przejdź do **Wtyczki → Dodaj nową**
3. Kliknij **Prześlij wtyczkę**
4. Wybierz plik ZIP i zainstaluj
5. Aktywuj plugin

### Sposób 2: Ręczny upload przez FTP

1. Skopiuj cały folder `jg-interactive-map` do `/wp-content/plugins/`
2. W panelu WordPress przejdź do **Wtyczki**
3. Znajdź "JG Interactive Map" i kliknij **Aktywuj**

### Po aktywacji

Plugin automatycznie utworzy:
- Tabele w bazie danych
- Katalog dla zdjęć: `/wp-content/uploads/jg-map/`

## 📝 Użycie

### Podstawowe użycie

Wstaw shortcode w dowolnym miejscu (strona, wpis, widget):

```
[jg_map]
```

### Zaawansowane użycie

Możesz dostosować początkowe ustawienia mapy:

```
[jg_map lat="50.904" lng="15.734" zoom="13" height="600px"]
```

Parametry:
- `lat` - szerokość geograficzna (domyślnie: 50.904)
- `lng` - długość geograficzna (domyślnie: 15.734)
- `zoom` - poziom zoomu (domyślnie: 13)
- `height` - wysokość mapy (domyślnie: 560px)

### Przykład użycia w Elementorze

1. Dodaj widget **Skrót** (Shortcode)
2. Wklej: `[jg_map]`
3. Zapisz

## 🎯 Funkcje

### Dla użytkowników

- **Dodawanie punktów**: Kliknij na mapę (przy maksymalnym zoomie) aby dodać nowy punkt
- **Głosowanie**: Oceniaj punkty przyciskami ⬆️ i ⬇️
- **Zgłaszanie**: Zgłaszaj nieprawidłowe punkty do moderacji
- **Edycja**: Edytuj swoje punkty (wymaga ponownej moderacji)
- **Zdjęcia**: Dodawaj do 6 zdjęć do każdego punktu

### Dla administratorów

Administrator (użytkownik z uprawnieniami `manage_options`) ma dostęp do:

- ✅ **Akceptacja/Odrzucenie** punktów oczekujących
- ⭐ **Promocja** wybranych punktów (większy pin, lepsze wyróżnienie)
- 👁️ **Ukrywanie/pokazywanie** autorów
- 📝 **Notatki** do punktów
- 🚨 **Zarządzanie zgłoszeniami** (pozostaw/usuń punkt)
- 📊 **Status zgłoszeń** (dodane/zgłoszone/rozwiązane)
- 📧 **Powiadomienia email** o nowych punktach i zgłoszeniach

### Typy punktów

1. **Zgłoszenie** (!) - czerwony pin - problemy do naprawienia
2. **Ciekawostka** (i) - niebieski pin - ciekawe miejsca
3. **Miejsce** (M) - zielony pin - ważne lokalizacje

## 🔒 Bezpieczeństwo

Plugin zawiera:
- ✅ Weryfikacja nonce dla wszystkich akcji AJAX
- ✅ Sprawdzanie uprawnień użytkowników
- ✅ Sanityzacja i walidacja danych wejściowych
- ✅ Escape output dla bezpieczeństwa XSS
- ✅ Prepared statements dla zapytań SQL
- ✅ Limit flood protection (60 sekund między dodawaniem)
- ✅ Ochrona katalogu uploads (.htaccess)

## 🗄️ Struktura bazy danych

Plugin tworzy 3 tabele:

### `wp_jg_map_points`
Przechowuje punkty na mapie

### `wp_jg_map_votes`
Przechowuje głosy użytkowników

### `wp_jg_map_reports`
Przechowuje zgłoszenia punktów

## 🎨 Dostosowywanie

### Style CSS

Edytuj plik `/assets/css/jg-map.css` aby zmienić wygląd mapy.

### JavaScript

Edytuj plik `/assets/js/jg-map.js` aby zmienić zachowanie mapy.

### Domyślne ustawienia

Edytuj plik `/includes/class-enqueue.php`, sekcja `wp_localize_script`:

```php
'defaults' => array(
    'lat' => 50.904,   // Szerokość geograficzna
    'lng' => 15.734,   // Długość geograficzna
    'zoom' => 13       // Poziom zoomu
)
```

## 📧 Powiadomienia Email

Administrator otrzymuje powiadomienia email o:
- Nowych punktach czekających na moderację
- Zgłoszeniach punktów przez użytkowników

Użytkownicy otrzymują powiadomienia o:
- Akceptacji ich punktu
- Odrzuceniu punktu (z powodem)

## 🔧 Wymagania

- WordPress 5.8 lub nowszy
- PHP 7.4 lub nowszy
- MySQL 5.6 lub nowszy

## 📦 Pliki pluginu

```
jg-interactive-map/
├── jg-interactive-map.php          # Główny plik pluginu
├── includes/
│   ├── class-database.php          # Zarządzanie bazą danych
│   ├── class-ajax-handlers.php     # Handlery AJAX
│   ├── class-enqueue.php           # Skrypty i style
│   └── class-shortcode.php         # Shortcode
├── assets/
│   ├── js/
│   │   └── jg-map.js              # JavaScript mapy
│   └── css/
│       └── jg-map.css             # Style CSS
└── README.md                       # Ten plik
```

## 🐛 Rozwiązywanie problemów

### Mapa się nie ładuje

1. Sprawdź konsolę przeglądarki (F12) pod kątem błędów JavaScript
2. Upewnij się, że shortcode `[jg_map]` jest prawidłowo wstawiony
3. Sprawdź czy plugin jest aktywowany

### Błąd "Brak konfiguracji JG_MAP_CFG"

Plugin nie jest prawidłowo załadowany. Sprawdź:
1. Czy plugin jest aktywowany
2. Czy nie ma konfliktów z innymi pluginami
3. Czy skrypty są prawidłowo enqueue'owane

### Punkty nie zapisują się

1. Sprawdź czy użytkownik jest zalogowany
2. Sprawdź logi PHP pod kątem błędów
3. Sprawdź czy tabele zostały utworzone w bazie danych

### Zdjęcia nie uploadują się

1. Sprawdź uprawnienia do katalogu `/wp-content/uploads/jg-map/`
2. Sprawdź limit upload w PHP (upload_max_filesize, post_max_size)
3. Sprawdź czy katalog został utworzony

## 📄 Licencja

GPL v2 lub późniejsza

## 👨‍💻 Autor

JeleniogorzaNieTomy
- Website: https://jeleniogorzanietomy.pl

## 🆘 Wsparcie

W razie problemów:
1. Sprawdź sekcję "Rozwiązywanie problemów" powyżej
2. Sprawdź logi błędów WordPressa
3. Skontaktuj się z administratorem strony

## 📝 Changelog

### 2.8.0 (2024-12-01)
- Pierwsza wersja pluginu WordPress
- Konwersja z HTML snippet do pełnego pluginu
- Integracja z WordPress (users, AJAX, nonce)
- System moderacji
- Powiadomienia email
- Upload zdjęć
- Panel administratora
