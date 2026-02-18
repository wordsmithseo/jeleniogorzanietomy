# PRZEKAZANIE PROJEKTU — Treść, SEO, Widoczność i Sprzedaż

**Data przekazania:** 2026-02-18
**Projekt:** Jeleniogórzanie to my — Interaktywna Mapa Jeleniej Góry
**Strona:** https://jeleniogorzanietomy.pl
**Faza:** Budowanie widoczności + start sprzedaży (zasięgi na razie małe)

---

## 1. CO TO JEST I GDZIE STOIMY

Interaktywna mapa Jeleniej Góry, na której mieszkańcy zgłaszają problemy, dzielą się ciekawostkami i oznaczają ważne miejsca. Plugin WordPress z mapą Leaflet.

**Co już działa (technika jest gotowa):**
- Mapa z 3 typami punktów (zgłoszenia, ciekawostki, miejsca)
- Rejestracja i logowanie użytkowników
- Głosowanie, moderacja, upload zdjęć
- System gamifikacji (XP, odznaki, ranking)
- SEO techniczne (structured data, sitemap XML, OG tagi)
- System banerów reklamowych ze śledzeniem wyświetleń i kliknięć
- Punkty sponsorowane z danymi firmowymi (telefon, strona, CTA)
- Udostępnianie punktów (Facebook, WhatsApp, kopiuj link)

**Gdzie stoimy:** Technika gotowa. Zasięgi małe. Trzeba je zbudować treścią i SEO, a potem powoli monetyzować przez sponsorowane miejsca i banery.

---

## 2. TRZY TYPY TREŚCI NA MAPIE

Na mapie są **trzy typy punktów** — każdy ma inną funkcję i inną wartość SEO:

### Zgłoszenie (czerwona pinezka)
- **Co to:** Problem infrastrukturalny — dziura, zepsuty chodnik, brak oświetlenia
- **URL:** `/zgloszenie/nazwa-slug/`
- **Wartość SEO:** Niska bezpośrednio, ale wysoka pośrednio — generuje ruch z social media i angażuje społeczność
- **Kategorie:** infrastruktura (drogi, chodniki, oznakowanie), bezpieczeństwo (śmieci, graffiti), zieleń (drzewa, nasadzenia), transport (przejścia, przystanki), inicjatywy

### Ciekawostka (niebieska pinezka)
- **Co to:** Ciekawe miejsce, historia, legenda, architektura
- **URL:** `/ciekawostka/nazwa-slug/`
- **Wartość SEO:** WYSOKA — long-tail frazy typu "ciekawostki Jelenia Góra", "historia [nazwa miejsca]", "co zobaczyć w Jeleniej Górze"
- **Kategorie:** historyczne, przyrodnicze, architektoniczne, legendy

### Miejsce (zielona pinezka)
- **Co to:** Firma, restauracja, usługa, instytucja, park
- **URL:** `/miejsce/nazwa-slug/`
- **Wartość SEO:** NAJWYŻSZA — frazy komercyjne, lokalne wyszukiwania, Google Maps intent
- **Kategorie:** gastronomia, kultura, usługi, sport, historia, zieleń
- **BONUS:** To jednocześnie produkt sprzedażowy — miejsce może być sponsorowane (patrz sekcja monetyzacja)

**Każdy punkt dostaje:**
- Własną stronę z unikalnym URL (SEO-friendly slug)
- Structured data JSON-LD (Schema.org: LocalBusiness lub Place)
- OG tagi (podgląd na FB, WhatsApp, LinkedIn)
- Wpis w sitemap XML (priorytet 0.8 dla miejsc, 0.6 dla reszty)
- Meta description, keywords, geo-tagi
- Breadcrumbs w Google (Strona główna → Typ → Nazwa punktu)

---

## 3. CO MAMY POD MASKĄ SEO

Techniczne SEO jest **zrobione i działa**. Oto co generator stron punktów produkuje:

### Meta tagi (dla każdego punktu)
- `<title>` — Nazwa punktu + "Jelenia Góra"
- `<meta description>` — Pierwsze 25 słów opisu
- `<meta keywords>` — Tytuł + tagi użytkownika + "Jelenia Góra"
- `<link rel="canonical">` — Unikalny URL punktu

### Open Graph (podglądy w social media)
- `og:title`, `og:description`, `og:image` (ze zdjęciem głównym)
- `og:type` = "article"
- `og:locale` = "pl_PL"
- `article:section` = typ punktu
- `article:tag` = tagi + "Jelenia Góra"

### Structured Data (JSON-LD)
- **LocalBusiness** (dla punktów z telefonem/stroną) lub **Place**
- GeoCoordinates (lat/lng)
- PostalAddress (ulica, "Jelenia Góra", "Dolnośląskie", "PL")
- Jeśli sponsorowany: telephone, url, sameAs (Facebook, Instagram, LinkedIn, TikTok)
- **BreadcrumbList** — ścieżka nawigacji
- **WebPage** — metadane strony

### Sitemap XML
- URL: `/jg-map-sitemap.xml`
- Automatycznie generowany, cache 1h
- Zawiera wszystkie opublikowane punkty z datą modyfikacji
- Zintegrowany z Yoast SEO (jeśli zainstalowany)
- Dodany do `robots.txt`
- Obrazki punktów w sitemap (image sitemap)

### Katalog HTML (crawlowalne linki)
- Shortcode `[jg_map_directory]` — tabelaryczny widok wszystkich punktów
- **Kluczowe dla SEO:** Daje botom Google ścieżkę do odkrycia KAŻDEGO punktu bez JavaScriptu
- Chmura tagów z liczbą punktów
- Paginacja (50 na stronę, konfigurowalna)
- Filtrowanie po tagach: `?tag=nazwa-tagu`

---

## 4. STRATEGIA SŁÓW KLUCZOWYCH

### Frazy, na które już możemy rankować (dzięki strukturze URL i structured data)

**Frazy lokalne — informacyjne (ciekawostki):**
- "co zobaczyć w Jeleniej Górze"
- "ciekawostki Jelenia Góra"
- "historia [nazwa miejsca] Jelenia Góra"
- "zabytki Jelenia Góra"
- "ukryte miejsca Jelenia Góra"
- "spacer po Jeleniej Górze"
- "legendy Jelenia Góra"
- "architektura Jelenia Góra"

**Frazy lokalne — komercyjne (miejsca):**
- "restauracje Jelenia Góra"
- "co zjeść w Jeleniej Górze"
- "[kategoria] Jelenia Góra" (np. "fryzjer Jelenia Góra", "mechanik Jelenia Góra")
- "najlepsze [kategoria] Jelenia Góra"
- "polecane miejsca Jelenia Góra"

**Frazy problemowe (zgłoszenia):**
- "problemy infrastrukturalne Jelenia Góra"
- "stan dróg Jelenia Góra"
- "zgłoś problem Jelenia Góra"

**Frazy dzielnicowe (OGROMNY potencjał — jeszcze niewykorzystany):**
- "Cieplice co zobaczyć"
- "Sobieszów atrakcje"
- "Zabobrze Jelenia Góra"
- "Maciejowa spacer"
- Każda dzielnica JG to osobna nisza z zerową konkurencją

### Co trzeba robić, żeby rankować

1. **Dodawać treść na mapę** — każdy punkt = nowa strona indeksowana przez Google
2. **Pisać bogate opisy** — nie "fajne miejsce" tylko 200+ słów z kontekstem, historią, szczegółami
3. **Dodawać zdjęcia** — image search to dodatkowy kanał ruchu
4. **Tagować punkty mądrze** — tagi = long-tail keywords (max 5 na punkt)
5. **Budować katalog dzielnicowy** — strony per dzielnica to przyszły fundament SEO

---

## 5. PLAN BUDOWANIA TREŚCI

### Faza 1: Fundament (teraz — najbliższe 4 tygodnie)

**Cel:** Zapełnić mapę wartościową treścią. Każdy punkt to strona SEO.

| Zadanie | Kto | Częstotliwość | Cel |
|---------|-----|---------------|-----|
| Dodawanie **ciekawostek** z bogatym opisem (200+ słów) | Osoba contentowa | 5/tydzień | Budowanie indeksu pod frazy informacyjne |
| Dodawanie **miejsc** (gastronomia, usługi, kultura) | Osoba contentowa | 5/tydzień | Budowanie indeksu pod frazy komercyjne |
| Zdjęcia do każdego punktu (min. 2-3, najlepiej 5-6) | Osoba contentowa | Przy każdym punkcie | Image search + lepsze OG preview |
| Tagi na każdym punkcie (3-5 trafnych) | Osoba contentowa | Przy każdym punkcie | Long-tail SEO |
| Sprawdzanie, czy opisy są unikalne i wartościowe | Moderator | Przy akceptacji | Jakość > ilość |

**Docelowo:** 50+ punktów "ciekawostka" + 50+ punktów "miejsce" w ciągu miesiąca. To 100 nowych stron indeksowanych w Google.

### Jakie ciekawostki dodawać (pomysły na treść)

- Historia kamienic w centrum (każda kamienica = osobny punkt)
- Legendy jeleniogórskie (legenda Bolka, duchy Cieplic, etc.)
- Ukryte detale architektoniczne (sztukaterie, portale, zegary słoneczne)
- Przyroda (pomnikowe drzewa, źródełka, punkty widokowe)
- Ciekawe fakty o dzielnicach
- Miejsca filmowe (jeśli kręcono filmy w JG)
- Historyczne zdjęcia "dawniej vs dziś" (w opisie)
- Street art i murale
- Dawne nazwy ulic i ich historia

### Jakie miejsca dodawać (pipeline sprzedażowy)

- Restauracje i kawiarnie (najłatwiej potem sprzedać sponsoring)
- Usługi lokalne (mechanicy, fryzjerzy, lekarze)
- Hotele i pensjonaty (sezon turystyczny!)
- Sklepy lokalne / rzemieślnicy
- Atrakcje turystyczne
- Miejsca kultury (muzea, galerie, teatr)
- Sport i rekreacja (baseny, siłownie, szlaki)
- Instytucje publiczne (dla kompletności mapy)

**Ważne:** Każde dodane "miejsce" to potencjalny klient na sponsoring. Dodając restaurację na mapę, tworzysz stronę, która będzie rankować na "restauracja [nazwa] Jelenia Góra" — a potem możesz zaproponować właścicielowi upgrade do wersji sponsorowanej.

---

## 6. PLAN BUDOWANIA ZASIĘGÓW

### Kanał 1: Grupy facebookowe Jeleniej Góry (natychmiastowy zasięg)

Grupy jeleniogórskie mają tysiące członków. Jeden dobry post = setki kliknięć.

**Format postów:**
- "Czy wiedzieliście, że [ciekawostka]? Sprawdźcie na mapie → [link]"
- "47 zgłoszonych problemów w JG — zobacz co najczęściej przeszkadza mieszkańcom → [link]"
- Screenshot mapy z zaznaczonymi punktami + kontekst
- "Które miejsce w JG zasługuje na więcej uwagi? Zagłosuj → [link]"

**Częstotliwość:** 2-3 posty/tydzień. **Nie spam** — zawsze z wartością i kontekstem.

### Kanał 2: Lokalne portale (Jelonka.com, Nowiny Jeleniogórskie)

**Pitch:** "Mapa problemów i ciekawostek Jeleniej Góry — sprawdź co zgłaszają mieszkańcy"

**Formaty współpracy:**
- Cykliczna rubryka "Zgłoszenie tygodnia" — co tydzień najciekawsze zgłoszenie z mapy
- Artykuł "10 miejsc w JG, o których nie wiedziałeś" — na podstawie ciekawostek z mapy
- "Historia sukcesu: zgłoszono → naprawiono" — gdy urząd zareaguje na zgłoszenie

**Koszt:** Zero. To content marketing — dajemy im gotową treść w zamian za link i zasięg.

### Kanał 3: SEO organiczne (długoterminowe)

Już mamy infrastrukturę. Trzeba ją napełnić treścią (patrz Faza 1 wyżej).

**Dodatkowe działania SEO:**
- Google Business Profile — utworzyć profil projektu jako organizacji
- Linki zwrotne — wpis na Wikipedii (artykuł o JG, sekcja "linki zewnętrzne"), lokalne fora
- Google Search Console — monitorować indeksację, frazy, pozycje

### Kanał 4: Udostępnianie przez użytkowników (wirusowe)

Każdy punkt ma przyciski udostępniania (FB, WhatsApp, kopiuj link). OG tagi robią resztę — podgląd ze zdjęciem i opisem.

**Co zrobić:**
- Zachęcać do udostępniania w onboardingu i po dodaniu punktu
- Dodać natywne udostępnianie mobilne (Web Share API) — 1-2h pracy, plik: `jg-map.js`
- Przy każdym punkcie komunikat: "Podziel się z sąsiadami"

---

## 7. MONETYZACJA — JAK ZACZĄĆ SPRZEDAWAĆ

Mamy dwa gotowe mechanizmy zarabiania. Oba działają, trzeba tylko zacząć z nich korzystać.

### Produkt 1: Sponsorowane Miejsca

Każdy punkt typu "miejsce" może być **sponsorowany** (pole `is_promo` w bazie). Sponsorowane miejsce dostaje:

| Feature | Zwykły punkt | Sponsorowany |
|---------|-------------|-------------|
| Zdjęcia | max 6 | max 12 |
| Numer telefonu | brak | wyświetlany |
| Strona WWW | brak | wyświetlana z linkiem |
| Przycisk CTA ("Zadzwoń" / "Odwiedź stronę") | brak | wyświetlany |
| Social media (FB, IG, LinkedIn, TikTok) | brak | linki wyświetlane |
| Structured data (Schema.org) | Place | LocalBusiness (lepszy w Google) |
| Limit edycji dziennych | 2 | 4 |
| Wyróżnienie wizualne | brak | złota odznaka |

**Jak to działa od strony technicznej:**
- Admin w panelu ustawia `is_promo = 1` + datę wygaśnięcia `promo_until`
- Dodaje: website, phone, linki social media, włącza CTA
- System automatycznie zmienia schema z "Place" na "LocalBusiness" (lepsze SEO dla firmy)
- Punkt dostaje rozszerzone wyświetlanie w widoku szczegółów

**Ścieżka sprzedaży:**
1. Dodajesz firmę na mapę jako zwykłe "miejsce" (z dobrym opisem, zdjęciami)
2. Strona zaczyna się indeksować na "[nazwa firmy] Jelenia Góra"
3. Kontaktujesz właściciela: "Twoja firma jest już na naszej mapie. Za [kwotę]/miesiąc możemy dodać: numer telefonu, stronę, przycisk CTA, 12 zdjęć i wyróżnienie"
4. To nie cold call — dajesz coś za darmo (widoczność) i proponujesz upgrade

**Wbudowany mechanizm:** Użytkownik/firma może sama kliknąć "Zapytaj o promocję" na swoim punkcie. System wysyła maila na `oferty@jeleniogorzanietomy.pl` z danymi użytkownika i miejsca. Max 3 zapytania/dzień/użytkownik.

### Produkt 2: Banery reklamowe (728x90)

System banerów jest **gotowy i w pełni zaimplementowany**:

- Rotacja banerów z priorytetami wyświetlania
- Śledzenie unikalnych wyświetleń (fingerprint: IP + User-Agent, deduplikacja 24h)
- Śledzenie kliknięć
- Kampanie z datą start/end
- Model oparty na wyświetleniach (impressions_bought / impressions_used)
- Panel admina z raportami (CTR, pozostałe wyświetlenia)
- Shortcode `[jg_banner]` do osadzenia w dowolnym miejscu na stronie

**Co sprzedajesz:** "X wyświetleń Twojego banera na mapie Jeleniej Góry za Y zł"

**Na start:** Przy małych zasięgach lepiej zacząć od sponsorowanych miejsc (większa wartość dla klienta, łatwiej sprzedać). Banery wejdą, gdy ruch przekroczy 1000 unikalnych użytkowników/miesiąc.

### Cennik — sugestie na start

| Produkt | Sugerowana cena | Uzasadnienie |
|---------|----------------|--------------|
| Sponsorowane miejsce (miesiąc) | 49-99 zł/mies. | Mały zasięg = mała cena. Rośnie z ruchem |
| Sponsorowane miejsce (rok) | 399-799 zł/rok | Zniżka za roczne zobowiązanie |
| Baner 728x90 (1000 wyświetleń) | 29-49 zł/1000 | Na start. CPM rośnie z ruchem |
| Baner 728x90 (miesiąc unlimited) | 149-299 zł/mies. | Dla stałych klientów |

**Ceny są orientacyjne.** Na starcie ważniejsza jest baza klientów niż marża. Pierwsze 5-10 sponsoringów może być nawet za symboliczną kwotę — budujesz portfolio i social proof.

---

## 8. ZADANIA DO ZLECENIA — LISTA DLA ZESPOŁU

### FAZA 1: Treść i fundament (teraz)

| # | Zadanie | Kto | Opis |
|---|---------|-----|------|
| 1 | **Dodać 50 ciekawostek na mapę** | Content | Bogate opisy (200+ słów), 3-5 zdjęć, 3-5 tagów. Tematy: historia kamienic, legendy, architektura, przyroda, street art, punkty widokowe. Każda ciekawostka = osobna strona SEO |
| 2 | **Dodać 50 miejsc na mapę** | Content | Restauracje, kawiarnie, usługi, atrakcje, hotele, sport. Szczegółowe opisy z godzinami otwarcia, specjalnością, cenami. To przyszły pipeline sprzedażowy |
| 3 | **Stworzyć kalendarz treści FB** | Marketing | Plan 2-3 postów/tydzień na grupy jeleniogórskie. Formaty: ciekawostka dnia, ankieta, screenshot mapy + link |
| 4 | **Napisać pitch dla Jelonka.com** | Marketing | Mail z propozycją współpracy: cykliczna rubryka "Z mapy Jeleniej Góry". Załączyć 3 przykładowe tematy |
| 5 | **Założyć Google Search Console** | SEO/Admin | Zweryfikować domenę, sprawdzić indeksację sitemap `/jg-map-sitemap.xml`, monitorować pozycje |
| 6 | **Założyć Google Business Profile** | SEO/Admin | Profil projektu jako organizacji społecznej. Kategoria: "Civic organization" |
| 7 | **Zrobić audyt obecnych punktów** | Content | Przejrzeć istniejące punkty — uzupełnić opisy, dodać brakujące zdjęcia, poprawić tagi |

### FAZA 2: Zasięgi i SEO (miesiąc 2-3)

| # | Zadanie | Kto | Opis |
|---|---------|-----|------|
| 8 | **Regularnie postować na FB** | Marketing | Minimum 2/tydzień. Track które posty generują ruch (UTM parametry w linkach) |
| 9 | **Nawiązać współpracę z Jelonka.com** | Marketing | Rubryka "Zgłoszenie tygodnia" lub artykuł z danymi z mapy |
| 10 | **Zdobyć linki zwrotne** | SEO | Wikipedia (artykuł o JG), lokalne fora, blogi podróżnicze. Naturalnie — nie kupować |
| 11 | **Pisać artykuły blogowe na WP** | Content | "10 miejsc w JG, o których nie wiedziałeś", "Historia [dzielnicy]", "Dawna Jelenia Góra" — z linkami do punktów na mapie |
| 12 | **Monitorować Search Console** | SEO | Jakie frazy generują wyświetlenia? Na co klikają? Gdzie jest potencjał? |
| 13 | **Wyzwanie sezonowe** | Marketing | Banner na mapie: "Wiosenne porządki — zgłoś problem!" Ograniczone czasowo, poczucie urgency |

### FAZA 3: Start sprzedaży (miesiąc 3-4)

| # | Zadanie | Kto | Opis |
|---|---------|-----|------|
| 14 | **Wybrać 10 firm do pierwszego kontaktu** | Sprzedaż | Najlepiej restauracje/kawiarnie z ładnymi profilami na mapie. Przygotować dla każdej: screenshot ich strony na mapie, propozycję upgrade'u |
| 15 | **Stworzyć ofertę PDF** | Marketing | 1-stronicowy PDF: "Co zyskujesz ze sponsorowanym miejscem na mapie JG". Mockup: zwykły vs sponsorowany punkt |
| 16 | **Skontaktować 10 firm** | Sprzedaż | Mail/telefon/wizyta. Pitch: "Twoja firma jest już na mapie — za [X] zł/mies. dodajemy telefon, stronę, przycisk CTA i 12 zdjęć" |
| 17 | **Pierwsze 3-5 sponsoringów** | Sprzedaż | Nawet po symbolicznej cenie. Budujemy portfolio i social proof |
| 18 | **Case study z pierwszych klientów** | Marketing | "Restauracja X na mapie JG — Y wyświetleń w miesiąc". Dowód wartości dla następnych klientów |

### FAZA 4: Skalowanie (miesiąc 5+)

| # | Zadanie | Kto | Opis |
|---|---------|-----|------|
| 19 | **Landing pages per dzielnica** | Dev + SEO | `/cieplice`, `/sobieszow`, `/zabobrze` — dedykowane strony z mapą przefiltrowaną na dzielnicę + opis SEO. Frazy: "[dzielnica] co zobaczyć", "[dzielnica] atrakcje" |
| 20 | **Wejście na kolejne kanały social** | Marketing | Instagram (zdjęcia z mapy), TikTok (krótkie filmiki "czy wiedziałeś że...") |
| 21 | **Skalowanie sprzedaży** | Sprzedaż | Kolejne branże: hotele, usługi, sport. Przy większym ruchu — wejście z banerami |
| 22 | **Automatyzacja newslettera** | Dev | Tygodniowy mail do użytkowników: nowe punkty, top głosowane, statystyki. Cron job WP |

---

## 9. JAK DODAWAĆ TREŚĆ — INSTRUKCJA PRAKTYCZNA

### Dodawanie punktu na mapę (przez frontend)

1. Wejdź na https://jeleniogorzanietomy.pl (strona z mapą)
2. Zaloguj się lub zarejestruj
3. Przybliż mapę do maksymalnego zoomu (poziom 17+)
4. Kliknij na lokalizację punktu
5. Wypełnij formularz:
   - **Typ:** Ciekawostka / Miejsce / Zgłoszenie
   - **Tytuł:** Konkretny, opisowy (to będzie H1 strony i slug URL)
   - **Opis:** Minimum 200 słów dla SEO! Bogaty, unikalny, z kontekstem
   - **Kategoria:** Wybierz z listy (dostępne zależą od typu)
   - **Zdjęcia:** Dodaj 3-6 zdjęć (wysokiej jakości)
   - **Tagi:** 3-5 trafnych tagów (to słowa kluczowe!)
   - **Adres:** Wypełnia się automatycznie z GPS, ale sprawdź poprawność
6. Wyślij — punkt trafia do moderacji
7. Po akceptacji: pojawia się na mapie, dostaje URL, indeksuje się w Google

### Dodawanie/edycja punktu przez panel admina

Admini z uprawnieniami `manage_options` mogą:
- Akceptować/odrzucać punkty w kolejce moderacji
- Edytować dowolny punkt (tytuł, opis, zdjęcia, kategoria, tagi)
- Włączać sponsoring: `is_promo` = tak, data wygaśnięcia, telefon, strona, CTA, social media
- Dodawać notatki widoczne tylko dla adminów
- Zmieniać status punktu (publish/pending/trash)

### Zasady dobrej treści na mapie

| Aspekt | Źle | Dobrze |
|--------|-----|--------|
| Tytuł | "Fajne miejsce" | "Kamienica pod Złotą Koroną — ul. 1 Maja 12" |
| Opis | "Ładny budynek" | "Neorenesansowa kamienica z 1892 roku, zaprojektowana przez Karla Schmidta. Na fasadzie zachował się oryginalny portal z alegoryczną rzeźbą..." (200+ słów) |
| Zdjęcia | 1 rozmazane zdjęcie | 5 ostrych zdjęć: fasada, detale, wnętrze, otoczenie, tablica informacyjna |
| Tagi | brak | "architektura", "neorenesans", "ul. 1 Maja", "kamienice", "centrum" |

---

## 10. METRYKI — CO ŚLEDZIĆ

### KPI cotygodniowe

| Metryka | Gdzie sprawdzić | Cel (miesiąc 1) | Cel (miesiąc 3) |
|---------|----------------|-----------------|-----------------|
| Nowe punkty dodane | Panel admina | 10/tydzień | 20/tydzień |
| Strony zaindeksowane w Google | Search Console | 50 | 200 |
| Ruch organiczny (Google) | GA4 / Search Console | baseline | +50% |
| Ruch z social media | GA4 (UTM) | baseline | 100 sesji/tydzień |
| Rejestracje użytkowników | Panel admina | 5/tydzień | 15/tydzień |
| Sponsorowane miejsca (aktywne) | Panel admina (is_promo) | 0 | 3-5 |

### KPI miesięczne

| Metryka | Cel (miesiąc 3) | Cel (miesiąc 6) |
|---------|-----------------|-----------------|
| Łączna liczba punktów na mapie | 200 | 500 |
| Unikalnych użytkowników/miesiąc | 500 | 2000 |
| Przychód z sponsoringów | 150-500 zł | 1000-2000 zł |
| Współprace z mediami | 1 aktywna | 2-3 aktywne |
| Linki zwrotne | 5 | 15 |

---

## 11. STRESZCZENIE — CO ROBIĆ JUTRO

**Jeśli masz jedną osobę:**
1. Dodawaj 2 wartościowe punkty dziennie (1 ciekawostka + 1 miejsce)
2. Postuj 2x/tydzień na FB grupach JG
3. Raz w tygodniu sprawdź Search Console
4. Po miesiącu: kontakt z pierwszymi firmami o sponsoring

**Jeśli masz dwie osoby:**
1. Osoba A: content (punkty na mapie + blog + FB posty)
2. Osoba B: SEO + sprzedaż (Search Console, linki zwrotne, kontakt z firmami)

**Jeśli masz trzy+ osoby:**
1. Content (5-10 punktów/tydzień + artykuły blogowe)
2. Marketing (FB, Instagram, kontakt z mediami, wyzwania sezonowe)
3. Sprzedaż (prospecting firm, tworzenie ofert, kontakt, obsługa klientów)

---

## 12. PLIKI I ZASOBY

| Zasób | Gdzie |
|-------|-------|
| Strona produkcyjna | https://jeleniogorzanietomy.pl |
| Mail ofertowy | oferty@jeleniogorzanietomy.pl |
| Mail powiadomień | powiadomienia@jeleniogorzanietomy.pl |
| Plan wzrostu (szczegółowy) | `GROWTH_PLAN.md` |
| Sitemap XML | https://jeleniogorzanietomy.pl/jg-map-sitemap.xml |
| Katalog HTML (indeksowany) | Strona z shortcodem `[jg_map_directory]` |
| Panel admina WP | /wp-admin/ |
| Repozytorium kodu | GitHub: wordsmithseo/jeleniogorzanietomy |

---

*Technika jest gotowa. Teraz trzeba napełnić ją treścią, zbudować zasięgi i zacząć zarabiać. Krok po kroku.*
