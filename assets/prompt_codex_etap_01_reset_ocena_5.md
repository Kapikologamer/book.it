# Prompt dla Codexa — reset projektu i Etap 1 na ocenę 5

Pracujesz w istniejącym repozytorium projektu szkolnego **System rezerwacji usług / book.it**.

## Cel tego zadania

Masz **wycofać dotychczasową implementację aplikacji i zbudować projekt od nowa jako minimalną bazę spełniającą wyłącznie wymagania pierwszego terminu (Etap 1) na ocenę 5 — bardzo dobrą**.

Termin Etapu 1 według specyfikacji: **25.09.2026**.

Na tym etapie **nie implementuj** rejestracji, logowania, sesji, paneli klienta/pracownika/administratora, procesu rezerwacji, AJAX, kalendarzy, maili, resetowania haseł ani innych funkcji z kolejnych etapów.

Chodzi o bardzo czysty, łatwy do obrony fundament:
- poprawna struktura repozytorium,
- relacyjna i znormalizowana baza danych,
- kompletne `database.sql`,
- czytelny ERD,
- README,
- podstawowy szkielet aplikacji,
- sensowna organizacja plików,
- brak sekretów w repozytorium.

---

# 1. Najpierw przeanalizuj repozytorium

Zanim cokolwiek zmienisz:

1. znajdź katalog główny repozytorium,
2. wykonaj:
   - `git status`,
   - `git branch --show-current`,
   - `git log --oneline -10`,
3. sprawdź obecną strukturę plików,
4. ustal, które elementy pochodzą z wcześniejszej, zbyt rozbudowanej wersji projektu.

Nie próbuj zachowywać starej architektury aplikacji.

---

# 2. Reset projektu do czystej bazy

Chcę rozpocząć implementację praktycznie od zera.

## Bardzo ważne zasady resetu

- **NIE usuwaj katalogu `.git`.**
- **NIE przepisuj historii Git.**
- **NIE używaj force push.**
- **NIE fałszuj commitów ani autorów.**
- Dotychczasową historię Git pozostaw jako historię projektu.
- Usuń z aktualnego drzewa roboczego stare pliki aplikacji, które nie należą do nowej minimalnej wersji.
- Nowy stan projektu ma zostać zapisany jako normalna kolejna zmiana/commit, dzięki czemu operacja będzie odwracalna z historii Git.

Jeżeli istnieją pliki konfiguracyjne IDE, cache, vendor, node_modules, lokalne dane lub sekrety, nie przenoś ich do nowej wersji projektu.

---

# 3. Zakres wymagany na Etap 1 — ocena 5

Projekt ma spełniać wszystkie wymagania ocen 2, 3, 4 i 5 dla Etapu 1.

## Wymagania bazowe

Repozytorium ma zawierać rozpoczętą, uporządkowaną strukturę aplikacji.

Baza danych musi zawierać najważniejsze elementy systemu:
- użytkowników,
- kategorie usług,
- usługi,
- pracowników,
- powiązanie pracowników z usługami,
- dostępność pracowników,
- rezerwacje.

Muszą występować:
- klucze główne,
- klucze obce,
- relacje 1:N,
- minimum jedna relacja N:M,
- poprawne typy danych,
- status rezerwacji,
- dane testowe.

Rezerwacja musi być powiązana z:
- klientem,
- pracownikiem,
- usługą.

## Wymagania na ocenę 4, które również są obowiązkowe

- mechanizm przechowywania dostępności pracowników,
- relacja N:M między pracownikami i usługami,
- poprawne klucze obce,
- status rezerwacji,
- brak oczywistego, niepotrzebnego powielania danych,
- diagram ERD lub inny czytelny schemat bazy,
- `database.sql` odtwarzający strukturę bazy,
- uporządkowane katalogi.

## Dodatkowe wymagania na ocenę 5

- baza danych ma być poprawnie znormalizowana,
- zastosuj ograniczenia integralności danych,
- użyj `UNIQUE` tam, gdzie ma to sens — przede wszystkim dla adresu e-mail,
- dodaj uzasadnione indeksy,
- dodaj przykładowe dane testowe,
- przygotuj kompletny `database.sql` z tabelami, relacjami i danymi,
- przygotuj kompletne `README.md`,
- zachowaj czytelną organizację projektu.

Historia regularnej pracy obu osób oraz praca na gałęziach jest częścią procesu zespołu i **nie wolno jej sztucznie tworzyć**. W README można natomiast opisać zalecany workflow Git na dalsze etapy.

---

# 4. Docelowa minimalna struktura repozytorium

Zbuduj projekt mniej więcej w takiej postaci:

```text
/
├── README.md
├── .gitignore
├── database/
│   └── database.sql
├── docs/
│   └── ERD.md
└── public/
    ├── index.php
    ├── css/
    │   └── style.css
    ├── js/
    │   └── app.js
    └── assets/
        └── .gitkeep
```

Możesz dokonać drobnych zmian w strukturze, jeśli będą lepiej uzasadnione, ale nie rozbudowuj projektu bez potrzeby.

Nie dodawaj frameworka.

Nie dodawaj Composer dependencies.

Nie dodawaj Node.js.

Na tym etapie wybieramy:
- PHP,
- HTML5,
- CSS3,
- JavaScript,
- MySQL/MariaDB,
- Git.

---

# 5. Minimalna aplikacja

`public/index.php` ma być bardzo prostą stroną startową.

Nie twórz jeszcze prawdziwego systemu.

Strona powinna jedynie:
- pokazywać nazwę projektu `book.it`,
- zawierać krótki opis typu „System rezerwacji usług”,
- informować, że aktualnie zaimplementowany jest Etap 1 projektu,
- mieć prosty, schludny i responsywny wygląd,
- korzystać z `public/css/style.css`,
- poprawnie ładować `public/js/app.js`.

Nie twórz:
- formularza logowania,
- formularza rejestracji,
- dashboardu,
- panelu firmy,
- panelu klienta,
- terminarza,
- formularza tworzenia rezerwacji.

Te elementy pojawią się dopiero w następnych etapach.

---

# 6. Projekt bazy danych

Zaprojektuj bazę w sposób zgodny z wymaganiami nauczyciela i wystarczający do dalszej rozbudowy.

Minimalny model powinien zawierać następujące tabele.

## `users`

Przykładowe pola:

```text
id
name
surname
email
password
phone
role
active
created_at
updated_at
```

Wymagania:
- `id` — PK,
- `email` — `UNIQUE`,
- `role` umożliwia co najmniej:
  - `client`,
  - `employee`,
  - `admin`,
- `active` jako logiczna aktywność konta,
- właściwe typy danych,
- indeksy tylko wtedy, gdy mają sens.

Hasło jest tutaj wyłącznie elementem modelu danych. Logowania jeszcze nie implementujemy.

---

## `service_categories`

Przykładowe pola:

```text
id
name
description
active
created_at
```

---

## `services`

Przykładowe pola:

```text
id
category_id
name
description
duration_minutes
price
active
created_at
updated_at
```

Wymagania:
- FK do `service_categories`,
- cena jako typ numeryczny odpowiedni dla wartości pieniężnych,
- czas trwania jako liczba minut,
- indeks dla `category_id`.

---

## `employees`

Przykładowe pola:

```text
id
user_id
description
active
created_at
updated_at
```

Wymagania:
- FK do `users`,
- `user_id` powinien być unikalny, aby jedno konto użytkownika nie tworzyło wielu rekordów tego samego pracownika.

Nie powielaj w tej tabeli imienia, nazwiska, e-maila i telefonu — te dane są już w `users`.

---

## `employee_services`

Tabela łącząca pracowników z usługami.

Przykład:

```text
employee_id
service_id
```

Wymagania:
- relacja N:M,
- FK do `employees`,
- FK do `services`,
- najlepiej złożony PK lub przynajmniej `UNIQUE(employee_id, service_id)`.

---

## `employee_availability`

Przykładowe pola:

```text
id
employee_id
day_of_week
start_time
end_time
created_at
```

Wymagania:
- FK do `employees`,
- poprawne typy czasu,
- ograniczenie zakresu `day_of_week`,
- jeśli silnik bazy na to pozwala, dodaj sensowny `CHECK`, np. `end_time > start_time`,
- indeks pomagający później pobierać dostępność konkretnego pracownika dla danego dnia.

Na tym etapie nie implementuj jeszcze algorytmu generowania wolnych terminów.

---

## `reservations`

Przykładowe pola:

```text
id
user_id
employee_id
service_id
reservation_date
start_time
end_time
status
comment
created_at
updated_at
```

Wymagania:
- FK `user_id -> users.id`,
- FK `employee_id -> employees.id`,
- FK `service_id -> services.id`,
- status obsługujący co najmniej:
  - `pending`,
  - `confirmed`,
  - `completed`,
  - `cancelled`,
- indeksy istotne dla późniejszego wyszukiwania rezerwacji:
  - po kliencie,
  - po pracowniku i dacie,
  - po usłudze,
  - ewentualnie po statusie, jeśli jest to uzasadnione.

Na tym etapie nie implementuj jeszcze logiki wykrywania konfliktów.

---

# 7. Integralność bazy

Zwróć szczególną uwagę na:

- sensowne `NOT NULL`,
- `UNIQUE(users.email)`,
- poprawne FK,
- poprawne `ON DELETE` / `ON UPDATE`,
- niewprowadzanie osieroconych rekordów,
- brak zbędnej duplikacji danych,
- właściwe typy kolumn,
- właściwą precyzję `DECIMAL` dla ceny.

Dobierz zachowanie `ON DELETE` rozsądnie.

Nie stosuj bezmyślnie `CASCADE` wszędzie.

Dane historyczne, takie jak rezerwacje, nie powinny znikać przypadkiem tylko dlatego, że administrator usunie inny rekord.

Jeżeli bezpieczniejsze jest użycie `RESTRICT` lub logicznej dezaktywacji przez pole `active`, wybierz takie rozwiązanie.

---

# 8. `database/database.sql`

Plik ma być kompletny i możliwie samowystarczalny.

Powinien:

1. opcjonalnie utworzyć bazę danych o czytelnej nazwie,
2. ustawić kodowanie UTF-8 / `utf8mb4`,
3. tworzyć tabele we właściwej kolejności,
4. definiować PK,
5. definiować FK,
6. definiować `UNIQUE`,
7. definiować indeksy,
8. definiować uzasadnione ograniczenia integralności,
9. dodawać dane testowe.

Plik powinien dać się uruchomić od początku na czystej instancji MySQL/MariaDB.

Nie twórz migracji — na tym etapie wystarczy jeden poprawny `database.sql`.

---

# 9. Dane testowe

Dodaj niewielki, czytelny zestaw danych:

- minimum 1 administrator,
- minimum 1 klient,
- minimum 2 pracowników,
- kilka kategorii,
- kilka usług,
- przypisania usług do pracowników,
- przykładowe godziny dostępności,
- minimum 2 przykładowe rezerwacje o różnych statusach.

Dane mają pozwolić nauczycielowi łatwo przejrzeć relacje.

Nie dodawaj setek rekordów.

Jeżeli dodajesz hasła testowych użytkowników, przechowuj w bazie wyłącznie hash wyglądający jak prawidłowy hash hasła, a nie jawne hasło.

Nie wdrażaj jeszcze mechanizmu `password_hash()` w aplikacji — to należy do Etapu 2.

---

# 10. ERD

Utwórz:

```text
docs/ERD.md
```

W pliku umieść:

1. diagram ERD w Mermaid, jeśli repozytorium/renderer to wspiera,
2. poniżej krótką tekstową listę relacji.

Diagram powinien jasno pokazywać:

- `users 1:1 employees`,
- `service_categories 1:N services`,
- `employees N:M services` przez `employee_services`,
- `employees 1:N employee_availability`,
- `users 1:N reservations`,
- `employees 1:N reservations`,
- `services 1:N reservations`.

Upewnij się, że ERD odpowiada faktycznej implementacji SQL.

---

# 11. README.md

README ma być kompletne, ale krótkie i konkretne.

Powinno zawierać:

## Nazwa projektu

`book.it — System rezerwacji usług`

## Autorzy

Jeżeli nazwisk autorów nie można wiarygodnie ustalić z repozytorium, **nie wymyślaj ich**.

Wstaw czytelny placeholder:

```text
- Autor 1: TODO
- Autor 2: TODO
```

## Opis

Krótko opisz, że jest to szkolny system rezerwacji usług i że obecna wersja obejmuje wyłącznie Etap 1.

## Technologie

- PHP
- HTML5
- CSS3
- JavaScript
- MySQL/MariaDB
- Git/GitHub

## Aktualny zakres

Wymień:
- strukturę aplikacji,
- model relacyjnej bazy,
- ERD,
- dane testowe,
- podstawową stronę startową.

Wyraźnie napisz, że:
- logowanie,
- rejestracja,
- role w warstwie aplikacji,
- panele,
- rezerwowanie

nie są jeszcze częścią Etapu 1.

## Struktura katalogów

Dodaj krótkie drzewo projektu.

## Uruchomienie

Napisz prostą instrukcję:
1. sklonowanie repozytorium,
2. utworzenie/import bazy przez `database/database.sql`,
3. uruchomienie katalogu `public` przez lokalny serwer PHP lub XAMPP,
4. otwarcie strony w przeglądarce.

Nie dodawaj instrukcji dla frameworka.

## Dane testowe

Wymień przykładowe konta znajdujące się w seedach SQL, ale jasno zaznacz, że mechanizm logowania pojawi się dopiero w Etapie 2.

## Workflow Git

Krótko opisz zalecenie:
- większe funkcje na osobnych branchach,
- małe logiczne commity,
- obie osoby commitują własną pracę,
- historia ma być prawdziwa i regularna.

Nie twórz fikcyjnej historii commitów.

---

# 12. `.gitignore`

Dodaj sensowny `.gitignore`.

Powinien ignorować co najmniej typowe:

```text
.env
.env.*
!.env.example
vendor/
node_modules/
.idea/
.vscode/
.DS_Store
Thumbs.db
*.log
```

Nie ma obowiązku tworzenia `.env`, jeśli projekt go jeszcze nie potrzebuje.

---

# 13. Czego NIE robić

To bardzo ważne.

Nie wyprzedzaj harmonogramu.

Nie implementuj teraz:

- rejestracji,
- logowania,
- wylogowania,
- sesji,
- routingu dla ról,
- panelu klienta,
- panelu pracownika,
- panelu administratora,
- CRUD usług,
- CRUD kategorii,
- CRUD pracowników,
- wyboru terminu,
- algorytmu dostępności,
- wykrywania nakładających się rezerwacji,
- anulowania rezerwacji,
- statusów sterowanych z UI,
- AJAX,
- REST API,
- kalendarza,
- wysyłki e-maili,
- resetowania hasła,
- statystyk,
- eksportu PDF,
- testów automatycznych,
- frameworka.

Model bazy może zawierać dane potrzebne do późniejszych etapów, ponieważ jest to wymagane w Etapie 1, ale kod aplikacji ma pozostać minimalny.

---

# 14. Kontrola jakości

Po wykonaniu zmian:

## PHP

Uruchom:

```bash
php -l public/index.php
```

i popraw wszystkie błędy składni.

## Struktura

Sprawdź, że istnieją:

```text
README.md
database/database.sql
docs/ERD.md
public/index.php
public/css/style.css
public/js/app.js
public/assets/
```

## SQL

Jeżeli lokalnie jest dostępny MySQL/MariaDB:
- utwórz czystą testową bazę,
- zaimportuj `database/database.sql`,
- upewnij się, że import kończy się bez błędów,
- sprawdź istnienie tabel i danych testowych.

Jeżeli silnik bazy nie jest dostępny:
- przeprowadź statyczną kontrolę SQL,
- nie udawaj, że import został wykonany.

## Git

Na końcu wykonaj:

```bash
git status
git diff --stat
git diff
```

Przejrzyj zmiany przed zakończeniem pracy.

---

# 15. Zapis zmian

Jeżeli repozytorium ma poprawnie skonfigurowanego autora Git i obecny workflow projektu pozwala na commit:

utwórz jeden logiczny commit zawierający tę przebudowę, np.:

```text
chore: reset project to stage 1 baseline
```

lub lepiej po polsku, jeżeli dotychczasowe commity są po polsku.

Jeżeli autor Git nie jest skonfigurowany albo nie masz pewności, kto powinien być autorem:
- **nie konfiguruj fałszywych danych autora,**
- pozostaw zmiany w working tree,
- poinformuj użytkownika, że commit musi wykonać właściciel repozytorium.

---

# 16. Raport końcowy

Po zakończeniu pracy podaj szczegółowy, ale krótki raport.

Raport ma zawierać:

## Usunięte elementy

Wymień główne elementy starej implementacji, które zostały usunięte.

## Utworzone elementy

Wymień wszystkie nowe pliki.

## Baza danych

Podaj:
- listę tabel,
- najważniejsze relacje,
- zastosowane `UNIQUE`,
- najważniejsze indeksy,
- ograniczenia integralności,
- liczbę przykładowych rekordów w głównych tabelach.

## Weryfikacja

Napisz:
- czy `php -l` przeszedł,
- czy SQL został realnie zaimportowany,
- jeśli nie — dlaczego,
- jaki jest wynik `git status`.

## Zakres projektu

Wyraźnie potwierdź:

> Projekt celowo kończy się na wymaganiach Etapu 1 na ocenę 5 i nie implementuje funkcji z Etapów 2–5.

---

# 17. Najważniejsza zasada

Priorytetem nie jest liczba plików ani efektowny interfejs.

Priorytetem jest stworzenie **małego, czystego i łatwego do wyjaśnienia projektu**, który w pełni spełnia wymagania **Etapu 1 na ocenę 5**, a jednocześnie stanowi bezpieczny fundament do kolejnych etapów.

Jeżeli masz wybór między:
- rozwiązaniem efektownym i rozbudowanym,
- a rozwiązaniem prostym, znormalizowanym i zgodnym z wymaganiami,

wybierz rozwiązanie prostsze.
