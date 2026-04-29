# TODO — JG Interactive Map

Kolejność: od najtańszych (backend/CSS) do najdroższych (głęboko w jg-map.js).

## Tanie — backend PHP / małe JS

- [x] **#3** Bug: podmenu w dashboardzie wtyczki nie zapisuje się — dodane elementy znikają po odświeżeniu (`class-admin.php`)
- [ ] **#2** SEO: miejsca z uzupełnionymi godzinami otwarcia powinny mieć je w meta tytule (`jg-interactive-map.php`)
- [ ] **#9** UX: dodać opcję "otwarte całą dobę" do systemu godzin otwarcia (formularz + backend)
- [ ] **#6** Profil: zmiana nazwy użytkownika raz na miesiąc (`trait-auth.php` + modal profilu)
- [ ] **#7** UX: usunąć modal rejestracji po 20 sekundach — zostawić tylko ten po obejrzeniu 2+ pinezek (`jg-auth.js` lub `jg-map.js`, grep po timerze)

## Średnie — jg-sidebar.js / tile-sw.js

- [ ] **#8** Katalog: po wybraniu tagu lub kategorii przefiltrowane miejsca pojawiają się na górze strony i większe (`jg-sidebar.js`)
- [ ] **#10** Wydajność: na mobile przy intensywnym zoom kafelki mapy wolno się ładują lub nie ładują wcale (`tile-sw.js` — service worker)

## Drogie — głęboko w jg-map.js (modalne)

> ⚠️ Przy tych zadaniach: używać grep, czytać tylko ~300-500 linii wokół openDetailsModal. NIE wczytywać całego pliku.

- [ ] **#5** Modal: sekcja polecanych miejsc pod sekcją udostępniania — kliknięcie zamyka modal, centruje mapę i otwiera wybrany (AJAX do backendu po podobne miejsca)
- [ ] **#4** Zdjęcia: obraz wiodący jako mikro-hero pod tytułem modalu (ograniczona wysokość), reszta kafelkowo pod opisem. Kliknięcie wiodącego scrolluje do galerii lub otwiera lightbox jeśli jedyne zdjęcie. Na stronie HTML pinezki — pełny hero pod tytułem, reszta kafelkowo. Polecane miejsca ze zdjęciami.

## Złożone — nowe systemy

- [ ] **#1** Wyzwania: system auto-generowania wyzwań rotacyjnych na podstawie potrzeb mapy (za mało miejsc, miejsca bez zdjęć, bez opisów itp.) — backend `class-challenges.php`, wyświetlanie TBD
