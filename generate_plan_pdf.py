#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Generate promotion plan PDF for Jeleniogórzanie To My"""

from fpdf import FPDF

class PlanPDF(FPDF):
    def __init__(self):
        super().__init__()
        self.add_font('DejaVu', '', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', uni=True)
        self.add_font('DejaVu', 'B', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', uni=True)
        self.set_auto_page_break(auto=True, margin=20)

    def header(self):
        if self.page_no() > 1:
            self.set_font('DejaVu', '', 7)
            self.set_text_color(130, 130, 130)
            self.cell(0, 6, 'Jeleniogórzanie To My — Plan Promocji luty 2026', align='R')
            self.ln(8)

    def footer(self):
        self.set_y(-15)
        self.set_font('DejaVu', '', 7)
        self.set_text_color(130, 130, 130)
        self.cell(0, 10, f'Strona {self.page_no()}/{{nb}}', align='C')

    def title_page(self):
        self.add_page()
        self.ln(60)
        self.set_font('DejaVu', 'B', 28)
        self.set_text_color(141, 35, 36)  # #8d2324
        self.cell(0, 15, 'Jeleniogórzanie To My', align='C', new_x="LMARGIN", new_y="NEXT")
        self.ln(5)
        self.set_font('DejaVu', 'B', 18)
        self.set_text_color(60, 60, 60)
        self.cell(0, 12, 'Plan Promocji Strony', align='C', new_x="LMARGIN", new_y="NEXT")
        self.cell(0, 12, '10–28 lutego 2026', align='C', new_x="LMARGIN", new_y="NEXT")
        self.ln(15)
        self.set_font('DejaVu', '', 11)
        self.set_text_color(100, 100, 100)
        self.cell(0, 8, 'Interaktywna mapa Jeleniej Góry', align='C', new_x="LMARGIN", new_y="NEXT")
        self.cell(0, 8, 'jeleniogorzanietomy.pl', align='C', new_x="LMARGIN", new_y="NEXT")
        self.ln(30)
        self.set_font('DejaVu', '', 9)
        self.set_text_color(150, 150, 150)
        self.cell(0, 8, 'Dokument wygenerowany: 10 lutego 2026', align='C', new_x="LMARGIN", new_y="NEXT")

    def section_title(self, text, r=141, g=35, b=36):
        self.set_font('DejaVu', 'B', 16)
        self.set_text_color(r, g, b)
        self.ln(4)
        self.cell(0, 10, text, new_x="LMARGIN", new_y="NEXT")
        # underline
        self.set_draw_color(r, g, b)
        self.set_line_width(0.5)
        self.line(self.l_margin, self.get_y(), self.w - self.r_margin, self.get_y())
        self.ln(6)

    def day_header(self, date, title):
        if self.get_y() > 245:
            self.add_page()
        self.set_fill_color(141, 35, 36)
        self.set_text_color(255, 255, 255)
        self.set_font('DejaVu', 'B', 11)
        self.cell(0, 8, f'  {date} — {title}', fill=True, new_x="LMARGIN", new_y="NEXT")
        self.ln(3)
        self.set_text_color(0, 0, 0)

    def task(self, num, title, details, goal):
        if self.get_y() > 255:
            self.add_page()
        x = self.l_margin
        w = self.w - self.l_margin - self.r_margin

        # Task number + title
        self.set_font('DejaVu', 'B', 9)
        self.set_text_color(141, 35, 36)
        self.cell(7, 5, f'{num}.', new_x="END")
        self.set_text_color(30, 30, 30)
        self.multi_cell(w - 7, 5, title, new_x="LMARGIN", new_y="NEXT")

        # Details
        self.set_x(x + 7)
        self.set_font('DejaVu', '', 8)
        self.set_text_color(60, 60, 60)
        self.multi_cell(w - 7, 4.5, details, new_x="LMARGIN", new_y="NEXT")

        # Goal
        self.set_x(x + 7)
        self.set_font('DejaVu', 'B', 8)
        self.set_text_color(0, 100, 50)
        self.cell(10, 4.5, 'CEL: ', new_x="END")
        self.set_font('DejaVu', '', 8)
        self.multi_cell(w - 17, 4.5, goal, new_x="LMARGIN", new_y="NEXT")
        self.ln(3)

    def info_box(self, title, text, color=(41, 98, 155)):
        if self.get_y() > 250:
            self.add_page()
        r, g, b = color
        self.set_fill_color(r, g, b)
        self.set_text_color(255, 255, 255)
        self.set_font('DejaVu', 'B', 9)
        self.cell(0, 6, f'  {title}', fill=True, new_x="LMARGIN", new_y="NEXT")
        self.set_fill_color(240, 245, 250)
        self.set_text_color(40, 40, 40)
        self.set_font('DejaVu', '', 8)
        self.multi_cell(0, 4.5, text, fill=True, new_x="LMARGIN", new_y="NEXT")
        self.ln(4)

    def summary_table(self, rows):
        self.set_font('DejaVu', 'B', 9)
        self.set_fill_color(141, 35, 36)
        self.set_text_color(255, 255, 255)
        col1 = 120
        col2 = self.w - self.l_margin - self.r_margin - col1
        self.cell(col1, 7, '  Metryka', fill=True, new_x="END")
        self.cell(col2, 7, '  Target', fill=True, new_x="LMARGIN", new_y="NEXT")
        self.set_text_color(30, 30, 30)
        for i, (metric, target) in enumerate(rows):
            bg = (245, 245, 245) if i % 2 == 0 else (255, 255, 255)
            self.set_fill_color(*bg)
            self.set_font('DejaVu', '', 8)
            self.cell(col1, 6, f'  {metric}', fill=True, new_x="END")
            self.set_font('DejaVu', 'B', 8)
            self.cell(col2, 6, f'  {target}', fill=True, new_x="LMARGIN", new_y="NEXT")
        self.ln(5)


def build_pdf():
    pdf = PlanPDF()
    pdf.alias_nb_pages()

    # ============ TITLE PAGE ============
    pdf.title_page()

    # ============ TABLE OF CONTENTS ============
    pdf.add_page()
    pdf.section_title('Spis Treści')
    toc = [
        '1. Legenda priorytetów i oznaczeń',
        '2. Funkcje systemu do promowania',
        '3. Tydzień 1 (10–16 lutego)',
        '4. Tydzień 2 (17–23 lutego)',
        '5. Tydzień 3 (24–28 lutego)',
        '6. Podsumowanie celów na koniec lutego',
        '7. Kluczowe zasady na cały miesiąc',
    ]
    pdf.set_font('DejaVu', '', 10)
    pdf.set_text_color(50, 50, 50)
    for item in toc:
        pdf.cell(0, 7, f'   {item}', new_x="LMARGIN", new_y="NEXT")
    pdf.ln(5)

    # ============ LEGENDA ============
    pdf.add_page()
    pdf.section_title('1. Legenda priorytetów i oznaczeń')
    pdf.set_font('DejaVu', 'B', 9)
    pdf.set_text_color(30, 30, 30)
    legends = [
        ('P1', 'Krytyczne — wykonaj tego dnia bezwzględnie'),
        ('P2', 'Ważne — da się przesunąć maksymalnie o 1 dzień'),
        ('P3', 'Nice-to-have — wykonaj jeśli starczy czasu'),
        ('Pinezka zielona (Miejsca)', 'Lokalne firmy, usługi, atrakcje — budują wartość mapy'),
        ('Pinezka niebieska (Ciekawostki)', 'Ciekawostki historyczne, przyrodnicze — wiralowy content'),
        ('Pinezka czerwona (Zgłoszenia)', 'Problemy infrastrukturalne — budują wiarygodność platformy'),
        ('GAMIFIKACJA', 'Działania promujące system poziomów, odznak i rankingu'),
    ]
    for label, desc in legends:
        pdf.set_font('DejaVu', 'B', 8)
        pdf.set_text_color(141, 35, 36)
        pdf.cell(55, 5, f'  {label}', new_x="END")
        pdf.set_font('DejaVu', '', 8)
        pdf.set_text_color(60, 60, 60)
        pdf.cell(0, 5, desc, new_x="LMARGIN", new_y="NEXT")
    pdf.ln(5)

    # ============ FEATURES TO PROMOTE ============
    pdf.section_title('2. Funkcje systemu do promowania')

    pdf.info_box('SYSTEM GAMIFIKACJI — Poziomy i XP', (
        'Użytkownicy zdobywają punkty doświadczenia (XP) za aktywność:\n'
        '• Dodanie pinezki: 50 XP  •  Zatwierdzenie przez moderację: 30 XP\n'
        '• Otrzymanie głosu "w górę": 5 XP  •  Głosowanie: 2 XP\n'
        '• Dodanie zdjęcia: 10 XP  •  Edycja pinezki: 15 XP  •  Codzienny login: 5 XP\n'
        'Poziomy rosną wg formuły level² × 100 XP. System widoczny w górnym pasku nawigacji.'
    ), (41, 98, 155))

    pdf.info_box('ODZNAKI I OSIĄGNIĘCIA (12 domyślnych)', (
        'Rzadkość: Common → Uncommon → Rare → Epic → Legendary\n\n'
        '• Pierwszy krok (1 pinezka) — Common\n'
        '• Aktywny mieszkaniec (5 pinezek) — Uncommon\n'
        '• Lokalny ekspert (10 pinezek) — Rare\n'
        '• Kartograf (25 pinezek) — Epic\n'
        '• Legenda Jeleniej Góry (50 pinezek) — Legendary\n'
        '• Głos obywatelski (1 głos) — Common\n'
        '• Aktywny głosujący (10 głosów) — Uncommon\n'
        '• Fotoreporter (1 zdjęcie) — Common\n'
        '• Doświadczony (poziom 5) — Uncommon\n'
        '• Weteran (poziom 10) — Rare\n'
        '• Mistrz mapy (poziom 20) — Epic\n'
        '• Wszechstronny (każdy typ pinezki) — Rare'
    ), (160, 82, 45))

    pdf.info_box('RANKING TOP 10', (
        'Publiczny ranking najaktywniejszych użytkowników. Kryteria:\n'
        '1) Liczba dodanych miejsc  2) Aktualny poziom  3) Data rejestracji\n'
        'Dostępny dla wszystkich przez przycisk "Ranking" w górnym pasku.'
    ), (76, 140, 50))

    pdf.info_box('GŁOSOWANIE I WERYFIKACJA SPOŁECZNA', (
        'Każda pinezka może otrzymać głosy w górę/w dół od użytkowników.\n'
        '• 50+ głosów netto: odznaka "Zweryfikowane przez społeczność" (zielona)\n'
        '• -50 głosów netto: odznaka "Kontrowersyjne" (czerwona)\n'
        'Głosowanie buduje zaangażowanie i wiarygodność danych na mapie.'
    ), (100, 50, 150))

    pdf.info_box('PROFIL UŻYTKOWNIKA — Statystyki', (
        'Każdy zalogowany użytkownik widzi swoje statystyki:\n'
        '• Dodane miejsca, edycje, zdjęcia, odwiedzone miejsca\n'
        '• Oddane głosy (w górę/w dół), otrzymane głosy\n'
        '• Aktualny poziom i pasek postępu XP\n'
        '• Zdobyte odznaki z opisami i rzadkością'
    ), (50, 50, 50))

    pdf.info_box('ONBOARDING — Samouczek dla nowych', (
        'Trzywarstwowy system wprowadzenia nowych użytkowników:\n'
        '1) Kreator powitalny (3 kroki) — typy pinezek, jak dodawać, co jeszcze można robić\n'
        '2) Przycisk pomocy "?" — zawsze dostępny, restart samouczka\n'
        '3) Kontekstowe podpowiedzi — pojawiają się progresywnie przy pierwszej wizycie'
    ), (41, 98, 155))

    # ============ TYDZIEŃ 1 ============
    pdf.add_page()
    pdf.section_title('3. Tydzień 1 (10–16 lutego)')

    # --- Poniedziałek 10 ---
    pdf.day_header('Poniedziałek 10.02', 'KICKOFF & FUNDAMENT')

    pdf.task(1, '5 pinezek "Miejsca" — znane lokale gastronomiczne z centrum',
        'Dodaj 5 popularnych restauracji/kawiarni. Każda pinezka: nazwa, adres, telefon, link do strony, 2-3 zdjęcia, opis 2-3 zdania. Pełne dane kontaktowe.',
        'Zapełnić mapę atrakcyjnym contentem — nowi odwiedzający widzą żywą, aktywną mapę')

    pdf.task(2, 'Post FB: Ogłoszenie startu projektu (typ: informacyjny)',
        '"Ruszamy z codziennym mapowaniem Jeleniej Góry! Codziennie nowe miejsca, ciekawostki i zgłoszenia. Dołącz do nas!" + screenshot mapy z nowymi pinezkami.',
        'Budowanie nawyku śledzenia profilu, zapowiedź regularności publikacji')

    pdf.task(3, '5 maili do firm z mapy — weryfikacja danych',
        'Wyślij do 5 firm, które JUŻ są na mapie. Treść: "Państwa firma znajduje się na interaktywnej mapie Jeleniej Góry. Prosimy o weryfikację danych (adres, telefon, godziny). Jeśli coś wymaga korekty, prosimy o odpowiedź."',
        'Nawiązanie relacji z lokalnymi firmami, weryfikacja danych, potencjalne udostępnienia')

    pdf.task(4, 'Post FB: Teaser systemu gamifikacji (typ: angażujący)',
        '"Czy wiesz, że na naszej mapie możesz zdobywać odznaki i awansować na kolejne poziomy? Dodaj swoją pierwszą pinezkę i odblokuj odznakę PIERWSZY KROK! Kto pierwszy zdobędzie odznakę LEGENDA JELENIEJ GÓRY?" + grafika z listą odznak.',
        'Świadomość systemu gamifikacji od pierwszego dnia — motywacja do rejestracji')

    # --- Wtorek 11 ---
    pdf.day_header('Wtorek 11.02', 'CONTENT SEO #1')

    pdf.task(1, 'Artykuł SEO #1: "10 miejsc w Jeleniej Górze, które musisz odwiedzić zimą 2026"',
        '800-1200 słów. Linkuj do pinezek na mapie. Słowa kluczowe: "Jelenia Góra co zobaczyć", "atrakcje Jelenia Góra zima". Każde miejsce = link do pinezki.',
        'Pozycjonowanie na frazy turystyczne, ruch organiczny z Google')

    pdf.task(2, '3 pinezki "Ciekawostki" — historia centrum miasta',
        'Ciekawostki historyczne: historia Placu Ratuszowego, wiek najstarszego budynku, legenda Cieplic. Opis 3-4 zdania + zdjęcie.',
        'Content o wysokim potencjale wiralowym — ludzie chętnie udostępniają ciekawostki')

    pdf.task(3, 'Post FB: Ciekawostka dnia (typ: edukacyjny)',
        '"Czy wiesz, że... [ciekawostka z pinezki]? Sprawdź na mapie, gdzie dokładnie to miejsce się znajduje! [link]". Dodaj zdjęcie z pinezki.',
        'Ruch na stronę, budowanie wizerunku "ciekawego źródła wiedzy o mieście"')

    pdf.task(4, '5 maili do firm — paczka weryfikacyjna',
        'Kolejnych 5 firm z mapy — ten sam szablon weryfikacyjny.',
        'Ciągłość kontaktu z biznesami lokalnymi')

    # --- Środa 12 ---
    pdf.day_header('Środa 12.02', 'ZGŁOSZENIA & AKTYWIZM OBYWATELSKI')

    pdf.task(1, '5 pinezek "Zgłoszenia" — realne problemy infrastrukturalne',
        'Przejdź się po mieście lub użyj Google Street View. Znajdź 5 realnych problemów: dziura w chodniku, zepsuta latarnia, brudna ściana. Zdjęcie + opis + lokalizacja.',
        'Pokazanie, że mapa DZIAŁA i służy mieszkańcom — fundament wiarygodności')

    pdf.task(2, 'Post FB: "Zgłoszenie tygodnia" (typ: aktywizujący)',
        '"Ta dziura na ul. [nazwa] czeka na naprawę. Zagłosuj na mapie, żeby podnieść priorytet! [link]". Zdjęcie problemu. Wytłumacz system głosowania.',
        'Zaangażowanie społeczności + edukacja o głosowaniu na pinezki')

    pdf.task(3, '3 pinezki "Miejsca" — usługi codzienne',
        'Dodaj 3 miejsca z kategorii usług: fryzjer, mechanik, apteka. Pełne dane kontaktowe.',
        'Rozbudowa bazy o kategorie przydatne na co dzień')

    pdf.task(4, 'Mail do Urzędu Miasta Jelenia Góra (instytucja)',
        '"Prowadzimy interaktywną mapę miasta, na której mieszkańcy zgłaszają problemy. Chcielibyśmy nawiązać współpracę — czy zgłoszenia z mapy mogą trafiać do wydziałów? Załączamy przykłady."',
        'Oficjalna legitymizacja projektu, boost zasięgu instytucjonalnego')

    pdf.task(5, 'Post FB: Ranking TOP 10 (typ: gamifikacja/społecznościowy)',
        '"Kto jest na szczycie rankingu naszej mapy? Sprawdź TOP 10 najaktywniejszych mieszkańców! Każda dodana pinezka to punkty XP i szansa na odznakę. [link]" + screenshot rankingu.',
        'Promowanie rankingu i rywalizacji — motywacja do zakładania kont i dodawania pinezek')

    # --- Czwartek 13 ---
    pdf.day_header('Czwartek 13.02', 'WALENTYNKI PREP & PARTNERSTWA')

    pdf.task(1, 'Artykuł SEO #2: "Walentynki w Jeleniej Górze — 7 romantycznych miejsc na randkę"',
        '600-900 słów. Linkuj do pinezek restauracji, kawiarni, parków. Frazy: "walentynki Jelenia Góra", "randka Jelenia Góra".',
        'Sezonowe SEO — fraza z dużym ruchem w tym tygodniu')

    pdf.task(2, '5 pinezek "Miejsca" — miejsca walentynkowe',
        'Kawiarnie, parki, restauracje z klimatem romantycznym. Kompletne dane.',
        'Wspieranie artykułu SEO konkretnymi pinezkami na mapie')

    pdf.task(3, 'Post FB: Walentynkowy (typ: sezonowy)',
        '"Gdzie zabierasz swoją drugą połówkę na walentynki? Sprawdź nasze TOP 7! [link do artykułu]".',
        'Sezonowy boost zasięgu, potencjał udostępnień')

    pdf.task(4, '5 maili do firm — restauracje/kawiarnie',
        'Weryfikacja danych — skup się na gastronomii (kontekst walentynkowy).',
        'Firmy walentynkowe mogą udostępnić z wdzięczności za promocję')

    pdf.task(5, 'Mail do portalu Jelonka.com (media)',
        '"Prowadzimy mapę JG. Czy bylibyście zainteresowani cotygodniową rubryką \'Tydzień na mapie Jeleniej Góry\' — zgłoszenia, nowe miejsca, ciekawostki?"',
        'Partnerstwo medialne = ogromny zasięg lokalny')

    # --- Piątek 14 ---
    pdf.day_header('Piątek 14.02', 'WALENTYNKI — SOCIAL PUSH')

    pdf.task(1, 'Post FB #1: Walentynkowy konkurs UGC (typ: interaktywny)',
        '"Happy Valentine\'s! Pokaż SWOJE ulubione miejsce w JG — dodaj je na mapę i zdobądź odznakę PIERWSZY KROK + 50 XP! Najbardziej aktywni użytkownicy dnia otrzymają wyróżnienie. [link]"',
        'UGC — mieszkańcy sami dodają pinezki + edukacja o systemie XP')

    pdf.task(2, 'Post FB #2: Ankieta (typ: engagement)',
        '"Które miejsce w JG jest najbardziej romantyczne? A) Cieplice B) Park Norweskiego C) Rynek D) Inne — napisz w komentarzu!"',
        'Algorytm FB nagradza komentarze — organiczny zasięg')

    pdf.task(3, '3 pinezki "Ciekawostki" — romantyczne/walentynkowe',
        'Ciekawostki romantyczne o mieście — legenda, historia, tradycje.',
        'Content sezonowy pasujący do dnia')

    pdf.task(4, '5 maili do firm — kontynuacja weryfikacji',
        'Kolejna paczka firm.',
        'Systematyczność buduje bazę zweryfikowanych danych')

    # --- Sobota 15 ---
    pdf.day_header('Sobota 15.02', 'WEEKEND — LŻEJSZY DZIEŃ')

    pdf.task(1, '3 pinezki "Miejsca" — weekendowe',
        'Parki, place zabaw, szlaki spacerowe — miejsca na sobotni spacer.',
        'Content dopasowany do weekendu')

    pdf.task(2, 'Post FB: Weekendowa inspiracja (typ: lifestyle)',
        '"Sobotni spacer? Sprawdź na mapie najlepsze trasy! Podziel się swoim ulubionym miejscem. A przy okazji — ile XP już zdobyłeś/aś? Sprawdź swój profil na mapie!" + link.',
        'Lżejszy content + przypomnienie o profilu użytkownika i XP')

    # --- Niedziela 16 ---
    pdf.day_header('Niedziela 16.02', 'PLANOWANIE & LEKKI CONTENT')

    pdf.task(1, '2 pinezki "Ciekawostki" — niedzielne',
        'Historia kościołów, tradycje niedzielne w JG.',
        'Tematyczny content')

    pdf.task(2, 'Zaplanuj posty i grafiki na tydzień 2',
        'Przygotuj grafiki i teksty na cały nadchodzący tydzień (Canva). Uwzględnij grafiki promujące odznaki i ranking.',
        'Efektywność — nie tracisz czasu w tygodniu na tworzenie')

    pdf.task(3, 'Post FB: Niedzielne podsumowanie z elementem gamifikacji',
        '"Pierwszy tydzień za nami! Na mapie już X pinezek. Kto zdobył pierwsze odznaki? Sprawdź ranking i zobacz, czy Twoi sąsiedzi Cię nie wyprzedzają! [link]"',
        'Budowanie nawyku niedzielnego podsumowania + gamifikacja')

    # ============ TYDZIEŃ 2 ============
    pdf.add_page()
    pdf.section_title('4. Tydzień 2 (17–23 lutego)')

    # --- Poniedziałek 17 ---
    pdf.day_header('Poniedziałek 17.02', 'PODSUMOWANIE TYGODNIA 1 & NOWY START')

    pdf.task(1, 'Post FB: Podsumowanie tygodnia (typ: raport)',
        '"Tydzień 1: dodano X miejsc, Y ciekawostek, Z zgłoszeń. Najpopularniejsza pinezka: [nazwa] z X głosami! Użytkownicy zdobyli łącznie Y odznak. Dołącz!" + infografika z liczbami.',
        'Social proof — pokazujesz, że projekt żyje i rośnie')

    pdf.task(2, '5 pinezek "Miejsca" — sklepy i usługi',
        'Piekarnie, kwiaciarnie, sklepy z rękodziełem.',
        'Rozbudowa bazy o kolejne kategorie')

    pdf.task(3, '5 maili do firm — nowa paczka',
        'Kontynuacja weryfikacji danych.',
        'Systematyczność')

    pdf.task(4, 'Mail do Biblioteki Miejskiej (instytucja)',
        '"Chcielibyśmy dodać wszystkie filie na mapę i zaproponować akcję: \'Mapuj z biblioteką\' — warsztaty dodawania pinezek dla seniorów i młodzieży. Pokażemy onboarding, system odznak i ranking."',
        'Partnerstwo z instytucją + nowa grupa docelowa + pokazanie onboardingu na żywo')

    # --- Wtorek 18 ---
    pdf.day_header('Wtorek 18.02', 'CONTENT SEO #3 & INSTAGRAM')

    pdf.task(1, 'Artykuł SEO #3: "Problemy infrastrukturalne w Jeleniej Górze 2026"',
        '800-1000 słów. Podsumowanie zgłoszeń z mapy, statystyki, zdjęcia. Wytłumacz jak działa system głosowania i jak mieszkańcy mogą wpływać na priorytety.',
        'SEO na frazy problemowe + budowanie wizerunku platformy obywatelskiej')

    pdf.task(2, '5 pinezek "Zgłoszenia" — nowa dzielnica',
        'Zgłoszenia z innej dzielnicy niż wcześniej — np. Zabobrze.',
        'Pokrycie geograficzne miasta')

    pdf.task(3, 'Post FB: "Znasz ten problem?" (typ: aktywizujący)',
        'Zdjęcie problemu + "Zagłosuj! Przy 50 głosach pinezka otrzyma odznakę ZWERYFIKOWANE PRZEZ SPOŁECZNOŚĆ. [link]".',
        'Edukacja o systemie weryfikacji społecznej + engagement')

    pdf.task(4, 'Założenie konta na Instagramie @jeleniogorzanietomy',
        'Bio: "Interaktywna mapa Jeleniej Góry. Zgłaszaj, odkrywaj, zmieniaj! Zdobywaj odznaki i awansuj! jeleniogorzanietomy.pl". Link in bio.',
        'Nowy kanał zasięgu — Instagram idealny do zdjęć miejsc')

    # --- Środa 19 ---
    pdf.day_header('Środa 19.02', 'INSTAGRAM LAUNCH & DZIELNICE')

    pdf.task(1, '3 posty na Instagram — launch',
        'Post 1: Carousel "5 ukrytych miejsc w JG". Post 2: Infografika "Jak zdobywać odznaki na mapie" (lista 12 odznak). Post 3: Screenshot mapy z podpisem "Ile miejsc znasz?" Hashtagi: #JeleniaGora #JeleniogorzanieToMy #MapujemyJG.',
        'Budowanie contentu na IG + promowanie gamifikacji wizualnie')

    pdf.task(2, '5 pinezek "Miejsca" — dzielnica Cieplice',
        'Komplet miejsc z jednej dzielnicy: restauracja, park, apteka, szkoła, atrakcja.',
        'Strategia "dzielnica po dzielnicy" — kompletne pokrycie')

    pdf.task(3, 'Post FB: Cross-promo IG (typ: informacyjny)',
        '"Jesteśmy na Instagramie! Śledź @jeleniogorzanietomy po codzienną dawkę JG [link do IG]".',
        'Przekierowanie ruchu na nowy kanał')

    pdf.task(4, '5 maili do firm — Cieplice',
        'Firmy z Cieplic — spójność z pinezkami.',
        'Spójność tematyczna')

    # --- Czwartek 20 ---
    pdf.day_header('Czwartek 20.02', 'OUTREACH DO INSTYTUCJI')

    pdf.task(1, 'Mail do Karkonoskiego Parku Narodowego',
        '"Dodajemy szlaki i punkty widokowe KPN na mapę JG. Czy moglibyśmy wykorzystać Państwa opisy? Oferujemy promocję KPN + odznakę \'Odkrywca Karkonoszy\' dla użytkowników odwiedzających punkty KPN."',
        'Partnerstwo z rozpoznawalną instytucją + pomysł na nową odznakę tematyczną')

    pdf.task(2, 'Maile do 3 lokalnych szkół',
        '"Proponujemy projekt: uczniowie mapują okolicę. System odznak motywuje jak gra — zdobywają XP, odznaki i awansują. Uczymy aktywności obywatelskiej przez technologię."',
        'Szkoły = rodzice = viralowy zasięg + gamifikacja przemawia do młodzieży')

    pdf.task(3, '3 pinezki "Ciekawostki" — przyrodnicze',
        'Ciekawostki przyrodnicze — kontekst KPN.',
        'Content wspierający outreach do KPN')

    pdf.task(4, 'Post FB: "Czy wiesz, że...?" (typ: edukacyjny)',
        'Seria "Czy wiesz, że w JG..." z ciekawostką przyrodniczą + "Dodaj swoją ciekawostkę i odblokuj odznakę WSZECHSTRONNY!" + link.',
        'Edukacja + promowanie odznaki "Wszechstronny" (za dodanie każdego typu pinezki)')

    pdf.task(5, 'Post IG: Zdjęcie przyrody z JG',
        'Widok na Karkonosze + "Dodaj swoje ulubione miejsce z widokiem! Link in bio."',
        'Budowanie IG, spójność z outreachem')

    # --- Piątek 21 ---
    pdf.day_header('Piątek 21.02', 'COMMUNITY BUILDING & GAMIFIKACJA')

    pdf.task(1, 'Post FB: PEŁNA PREZENTACJA GAMIFIKACJI (typ: edukacyjny/tutorial)',
        'Długi post z grafiką: "Jak działa system poziomów i odznak na naszej mapie?" Wyjaśnij: XP za pinezki (50), za głosy (2-5), za zdjęcia (10), za login (5). Pokaż listę 12 odznak od Common do Legendary. "Kto pierwszy zdobędzie LEGENDĘ JELENIEJ GÓRY (50 pinezek)?"',
        'Główny post edukacyjny o gamifikacji — punkt odniesienia do linkowania w przyszłości')

    pdf.task(2, '5 pinezek "Miejsca" — dzielnica Zabobrze',
        'Kolejna dzielnica.',
        'Pokrycie geograficzne')

    pdf.task(3, '5 maili do firm — Zabobrze',
        'Firmy z Zabobrza.',
        'Spójność z pinezkami')

    pdf.task(4, 'Artykuł SEO #4: "Mapa Jeleniej Góry online — jak zgłosić problem?"',
        '600-800 słów. Poradnik krok-po-kroku ze screenshotami. Pokaż onboarding, dodawanie pinezki, głosowanie, profil z XP. Frazy: "mapa Jelenia Góra", "zgłoś problem Jelenia Góra".',
        'SEO na frazy brandowe + tutorial promujący WSZYSTKIE funkcje systemu')

    # --- Sobota 22 ---
    pdf.day_header('Sobota 22.02', 'WEEKEND CONTENT')

    pdf.task(1, '3 pinezki "Miejsca" — weekendowe',
        'Restauracje z obiadem niedzielnym, kawiarnie, szlaki.',
        'Użyteczny content weekendowy')

    pdf.task(2, 'Post FB: "Weekendowy spacer z mapą" (typ: lifestyle)',
        '"Wydrukuj/otwórz mapę i rusz na spacer! Znajdź 3 miejsca z mapy, zrób zdjęcie i dodaj je do pinezki — to +10 XP za każde! Kto zdobędzie odznakę FOTOREPORTER w ten weekend?"',
        'Offline engagement + promowanie dodawania zdjęć i odznaki Fotoreporter')

    pdf.task(3, 'Post IG: Stories z mini-quizem',
        '3-4 stories z ankietami o Jeleniej Górze ("Który budynek jest starszy?" itp.).',
        'Stories mają wysoki reach na IG')

    # --- Niedziela 23 ---
    pdf.day_header('Niedziela 23.02', 'ANALIZA & PRZYGOTOWANIA')

    pdf.task(1, '2 pinezki "Ciekawostki" — niedzielne',
        'Tematyczne ciekawostki.',
        'Utrzymanie tempa dodawania')

    pdf.task(2, 'Analiza wyników tygodnia 1-2',
        'Sprawdź: nowi użytkownicy, pinezki dodane przez innych, reach postów, odpowiedzi firm, odznaki przyznane, głosy oddane. Zapisz wnioski.',
        'Decyzje oparte na danych dla tygodnia 3')

    pdf.task(3, 'Post FB: Niedzielne podsumowanie + ranking',
        '"Koniec tygodnia 2! Ranking TOP 10 — kto prowadzi? [screenshot]. W tym tygodniu przyznano X odznak! Czy jesteś wśród nich? Sprawdź swój profil [link]".',
        'Cotygodniowy rytuał + ranking jako motywator')

    pdf.task(4, 'Przygotowanie grafik na tydzień 3',
        'Canva — szablony: "odznaka tygodnia", infografiki, podsumowanie miesiąca.',
        'Efektywność')

    # ============ TYDZIEŃ 3 ============
    pdf.add_page()
    pdf.section_title('5. Tydzień 3 (24–28 lutego)')

    # --- Poniedziałek 24 ---
    pdf.day_header('Poniedziałek 24.02', 'PODSUMOWANIE & WYZWANIE KOŃCA MIESIĄCA')

    pdf.task(1, 'Post FB: Podsumowanie 2 tygodni + WYZWANIE (typ: raport + CTA)',
        '"2 tygodnie, X miejsc, Y ciekawostek, Z zgłoszeń! Ale to początek. WYZWANIE KOŃCA MIESIĄCA: kto do 28 lutego zdobędzie odznakę AKTYWNY MIESZKANIEC (5 pinezek), wygra wyróżnienie na stronie głównej! START!" + infografika.',
        'Milestone marketing + wyzwanie gamifikacyjne na ostatnie dni miesiąca')

    pdf.task(2, '5 pinezek "Miejsca" — Sobieszów/Jagniątków',
        'Kolejna dzielnica.',
        'Pokrycie peryferiów miasta')

    pdf.task(3, '5 maili do firm — nowa paczka',
        'Kontynuacja.',
        'Systematyczność')

    pdf.task(4, 'Mail do Dolnośląskiej Izby Turystyki (instytucja)',
        '"Tworzymy mapę turystyczną JG. Czy moglibyśmy zostać w bazie rekomendowanych serwisów? Oferujemy wzajemną promocję."',
        'Backlink + zasięg w środowisku turystycznym')

    # --- Wtorek 25 ---
    pdf.day_header('Wtorek 25.02', 'SEO PUSH — CIEPLICE')

    pdf.task(1, 'Artykuł SEO #5: "Cieplice Śląskie-Zdrój — kompletny przewodnik"',
        '1000-1500 słów. Linkuj do pinezek. Frazy: "Cieplice Jelenia Góra", "termy Cieplice", "co zobaczyć Cieplice". Wplej informację o mapie i jak dodawać własne miejsca.',
        'Cieplice = najczęściej wyszukiwana fraza związana z JG')

    pdf.task(2, '5 pinezek "Zgłoszenia" — nowa dzielnica',
        'Zgłoszenia infrastrukturalne z kolejnego obszaru.',
        'Pokrycie + aktywizm obywatelski')

    pdf.task(3, 'Post FB: Teaser artykułu (typ: content promotion)',
        '"Cieplice — znasz wszystkie sekrety? Kompletny przewodnik [link]. Dodaj miejsca z Cieplic na mapę i zdobywaj XP!"',
        'Ruch na artykuł + CTA do dodawania pinezek')

    pdf.task(4, 'Post IG: Carousel Cieplice',
        '5-7 zdjęć z Cieplic + opisy. W ostatnim slajdzie: "Dodaj swoje miejsce na mapie — link in bio!".',
        'Cross-content z artykułem')

    # --- Środa 26 ---
    pdf.day_header('Środa 26.02', 'AMBASADORZY & INFLUENCERZY')

    pdf.task(1, 'Mail do 5 lokalnych mikro-influencerów',
        'Osoby aktywne na FB/IG z JG (500-2000 followersów). "Szukamy ambasadorów dzielnic. Dodaj 10 miejsc z okolicy — zdobędziesz odznakę LOKALNY EKSPERT (Rare!) i wyróżnimy Cię na stronie i w social mediach."',
        'Ambasadorzy = darmowy zasięg + UGC + gamifikacja jako zachęta')

    pdf.task(2, '5 pinezek "Miejsca" — kultura',
        'Muzea, galerie, teatr, kino.',
        'Nowa kategoria contentu')

    pdf.task(3, 'Post FB: "Zostań ambasadorem dzielnicy" (typ: rekrutacyjny)',
        '"Znasz swoją dzielnicę jak nikt? Dodaj 10 miejsc = odznaka LOKALNY EKSPERT (Rare!). 25 miejsc = KARTOGRAF (Epic!). 50 = LEGENDA JELENIEJ GÓRY (Legendary!). Kto podejmie wyzwanie? [link]" + grafika ścieżki odznak.',
        'Rekrutacja aktywnych + wizualizacja ścieżki gamifikacyjnej')

    pdf.task(4, '5 maili do firm — kontynuacja',
        'Kolejna paczka weryfikacyjna.',
        'Systematyczność')

    pdf.task(5, 'Post FB: "Odznaka tygodnia" (typ: gamifikacja — NOWY FORMAT)',
        '"ODZNAKA TYGODNIA: WSZECHSTRONNY (Rare!). Jak ją zdobyć? Dodaj po jednej pinezce każdego typu: Miejsce + Ciekawostka + Zgłoszenie. To tylko 3 kliknięcia! Kto zdobędzie ją do niedzieli? [link]".',
        'Nowy format cykliczny — cotygodniowa prezentacja jednej odznaki')

    # --- Czwartek 27 ---
    pdf.day_header('Czwartek 27.02', 'SPRINT KOŃCOWY')

    pdf.task(1, 'Artykuł SEO #6: "Jak mieszkańcy zmieniają Jelenią Górę — historia naszej mapy"',
        '600-800 słów. Case study: co zgłoszono, co się zmieniło. Pokaż system głosowania, rankingi, odznaki. Frazy: "mapa Jelenia Góra mieszkańcy", "aktywność obywatelska JG".',
        'SEO + storytelling + pełna prezentacja funkcji systemu')

    pdf.task(2, '5 pinezek mix — 2 miejsca + 2 ciekawostki + 1 zgłoszenie',
        'Uzupełnienie mapy.',
        'Finalne pokrycie luk')

    pdf.task(3, 'Post FB: Success story (typ: storytelling)',
        '"Miesiąc temu tego miejsca nie było na mapie. Dziś ma X głosów i jest ZWERYFIKOWANE PRZEZ SPOŁECZNOŚĆ! Wasza aktywność zmienia miasto. [link]" + zdjęcie before/after jeśli możliwe.',
        'Emocjonalny content + demonstracja systemu weryfikacji społecznej')

    pdf.task(4, 'Mail follow-up do firm, które nie odpowiedziały',
        'Przypomnienie: "Wysłaliśmy wiadomość X dni temu dot. weryfikacji danych na mapie. Czy mieli Państwo okazję sprawdzić?"',
        'Podbicie — zwykle 20-30% odpowiada na follow-up')

    pdf.task(5, 'Post IG: Behind the scenes',
        'Screen profilu użytkownika z odznkami + "Tyle osiągnięć czeka na Ciebie! Ile odznak zdobędziesz?".',
        'Promowanie profilu i odznak na IG')

    pdf.task(6, 'Post FB: Aktualizacja wyzwania końca miesiąca (typ: gamifikacja)',
        '"Do końca wyzwania zostały 2 dni! X osób zdobyło odznakę AKTYWNY MIESZKANIEC. Czy zdążysz? Potrzebujesz jeszcze Y pinezek! Sprawdź profil [link]".',
        'Urgency + gamifikacja — ostatni push aktywności')

    # --- Piątek 28 ---
    pdf.day_header('Piątek 28.02', 'WIELKIE PODSUMOWANIE MIESIĄCA')

    pdf.task(1, 'Post FB: PODSUMOWANIE LUTEGO (typ: raport + cel na marzec)',
        'Infografika: ile pinezek, użytkowników, głosów, firm zweryfikowanych, artykułów, odznak przyznanych, najwyższy poziom. "WYNIKI WYZWANIA: [lista zwycięzców]. W marcu nowe odznaki, nowe wyzwania! [link]".',
        'Zamknięcie cyklu, celebration moment, momentum na marzec')

    pdf.task(2, 'Post IG: Carousel podsumowanie',
        'Najlepsze momenty miesiąca + statystyki gamifikacji.',
        'Cross-platform zamknięcie')

    pdf.task(3, '5 maili do firm — ostatnia paczka lutego',
        'Zamknięcie pierwszej fali outreach.',
        'Dobicie targetu')

    pdf.task(4, 'Raport wewnętrzny (nie publikowany)',
        'Spisz: reach postów, odpowiedzi firm, pinezki użytkowników, nowe rejestracje, aktywność gamifikacji (odznaki, głosy, poziomy), co zadziałało, co zmienić w marcu.',
        'Bez analizy nie ma poprawy — dane do planowania marca')

    pdf.task(5, 'Artykuł SEO #7: "Podsumowanie lutego na mapie Jeleniej Góry"',
        'Podsumowanie z linkami do najciekawszych pinezek. Wzmianka o systemie odznak i rankingu.',
        'Evergreen content + linkowanie wewnętrzne')

    pdf.task(6, 'Post FB: Zapowiedź marca (typ: teaser)',
        '"W marcu: nowe odznaki tematyczne, wyzwania dzielnicowe, i coś czego jeszcze nie widzieliście... Stay tuned! Na początek — zaloguj się codziennie od 1 marca po 5 XP dziennie!"',
        'Podtrzymanie zaangażowania + promowanie daily login XP')

    # ============ PODSUMOWANIE CELÓW ============
    pdf.add_page()
    pdf.section_title('6. Podsumowanie celów na koniec lutego')

    pdf.summary_table([
        ('Pinezki "Miejsca" dodane przez Ciebie', '~55-60'),
        ('Pinezki "Ciekawostki"', '~15-18'),
        ('Pinezki "Zgłoszenia"', '~15'),
        ('Artykuły SEO opublikowane', '6-7'),
        ('Posty Facebook', '~25-28 (w tym 6-8 o gamifikacji)'),
        ('Posty Instagram', '~8-10'),
        ('Maile do firm (weryfikacja)', '~45-50'),
        ('Maile do instytucji', '5-7'),
        ('Maile do influencerów/blogerów', '5'),
        ('Dzielnice pokryte na mapie', '4-5'),
        ('Konto Instagram założone i aktywne', 'TAK'),
        ('Posty promujące gamifikację', 'min. 8'),
        ('Posty promujące ranking', 'min. 3'),
        ('Posty promujące odznaki', 'min. 4'),
        ('Posty promujące system głosowania', 'min. 3'),
    ])

    pdf.ln(3)
    pdf.set_font('DejaVu', 'B', 10)
    pdf.set_text_color(141, 35, 36)
    pdf.cell(0, 7, 'Cele gamifikacyjne do osiągnięcia na koniec lutego:', new_x="LMARGIN", new_y="NEXT")
    pdf.set_font('DejaVu', '', 9)
    pdf.set_text_color(50, 50, 50)
    gami_goals = [
        'Min. 10 użytkowników z odznką "Pierwszy krok"',
        'Min. 3 użytkowników z odznką "Aktywny mieszkaniec"',
        'Min. 1 użytkownik z odznką "Wszechstronny"',
        'Min. 100 głosów oddanych łącznie na pinezki',
        'Min. 1 pinezka z odznką "Zweryfikowane przez społeczność" (50+ głosów)',
        'Min. 5 użytkowników na poziomie 2 lub wyższym',
        'Ranking TOP 10 wypełniony aktywnymi użytkownikami',
    ]
    for g in gami_goals:
        pdf.cell(5, 5, '•', new_x="END")
        pdf.cell(0, 5, f' {g}', new_x="LMARGIN", new_y="NEXT")

    # ============ ZASADY ============
    pdf.add_page()
    pdf.section_title('7. Kluczowe zasady na cały miesiąc')

    rules = [
        ('Konsekwencja > intensywność',
         'Lepiej robić mniej, ale codziennie, niż dużo raz i potem tydzień przerwy. Algorytmy social media nagradzają regularność.'),
        ('Każda pinezka = pełne dane',
         'Nazwa, adres, opis, zdjęcie, kontakt. Puste pinezki szkodzą wizerunkowi i nie dają wartości SEO.'),
        ('Każdy artykuł SEO linkuje do min. 5 pinezek',
         'Buduje wewnętrzne linkowanie, kieruje ruch na mapę, wzmacnia pozycjonowanie.'),
        ('Każdy post FB ma CTA (call to action)',
         '"Zagłosuj", "dodaj miejsce", "sprawdź na mapie", "napisz w komentarzu", "sprawdź swój poziom".'),
        ('Maile do firm wysyłaj rano (8:00-10:00)',
         'Najwyższy open rate. Krótki, konkretny temat. Podpis z linkiem do strony.'),
        ('Odpowiadaj na KAŻDY komentarz',
         'Algorytm FB promuje posty z dyskusją. Odpowiedź w ciągu 1h podnosi zasięg o 30-50%.'),
        ('Mierz i zapisuj wyniki',
         'Bez danych nie wiesz co działa. Notuj: reach, kliknięcia, nowe rejestracje, pinezki od użytkowników.'),
        ('Gamifikację promuj minimum 2x w tygodniu',
         'Co tydzień: 1 post o odznakach/rankingu, 1 post z wynikami/wyzwaniem. System jest wartościowy tylko gdy ludzie o nim wiedzą.'),
        ('Używaj screenshotów systemu',
         'Pokazuj realne zrzuty ekranu: profil z XP, popup odznaki, ranking TOP 10, kreator pinezki. Ludzie muszą zobaczyć jak to wygląda.'),
        ('Każdy nowy format postów testuj przez 2 tygodnie',
         '"Odznaka tygodnia", "Zgłoszenie tygodnia", "Ciekawostka dnia" — po 2 tygodniach analizuj reach i engagement. Zostaw to co działa.'),
    ]

    for i, (title, desc) in enumerate(rules, 1):
        if pdf.get_y() > 260:
            pdf.add_page()
        pdf.set_font('DejaVu', 'B', 9)
        pdf.set_text_color(141, 35, 36)
        pdf.cell(0, 6, f'{i}. {title}', new_x="LMARGIN", new_y="NEXT")
        pdf.set_font('DejaVu', '', 8)
        pdf.set_text_color(60, 60, 60)
        pdf.multi_cell(0, 4.5, desc, new_x="LMARGIN", new_y="NEXT")
        pdf.ln(2)

    # Save
    output_path = '/home/user/jeleniogorzanietomy/Plan_Promocji_Luty_2026.pdf'
    pdf.output(output_path)
    return output_path

if __name__ == '__main__':
    path = build_pdf()
    print(f'PDF saved to: {path}')
