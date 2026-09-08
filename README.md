# book.it

book.it to internetowa platforma do rezerwacji usług, która umożliwia klientom znalezienie firmy, sprawdzenie jej oferty oraz zarezerwowanie dostępnego terminu online.

W przeciwieństwie do klasycznego systemu rezerwacji przeznaczonego dla jednej firmy, book.it obsługuje wielu niezależnych usługodawców w ramach jednej aplikacji. Każda firma może posiadać własny profil, ofertę usług, pracowników, godziny dostępności oraz rezerwacje.

Projekt powstaje w ramach przedmiotu **Tworzenie stron i aplikacji internetowych**.

## O projekcie

Celem projektu jest stworzenie uniwersalnego systemu rezerwacji, z którego mogą korzystać różnego rodzaju firmy świadczące usługi, między innymi:

- salony fryzjerskie,
- salony kosmetyczne,
- warsztaty samochodowe,
- serwisy komputerowe,
- studia fotograficzne,
- firmy sprzątające,
- szkoły językowe,
- korepetytorzy,
- trenerzy,
- inni usługodawcy pracujący w oparciu o rezerwacje terminów.

Użytkownik korzysta z jednego konta book.it i za jego pomocą może rezerwować usługi w różnych firmach dostępnych na platformie.

Każda firma posiada własną ofertę i pracowników. Poszczególni pracownicy mogą wykonywać różne usługi oraz posiadać własne godziny pracy i dostępność.

Podstawowy proces rezerwacji wygląda następująco:

```text
Firma -> Usługa -> Pracownik -> Data -> Godzina -> Potwierdzenie
```

System automatycznie wyznacza dostępne terminy na podstawie czasu trwania wybranej usługi, godzin pracy pracownika oraz istniejących rezerwacji.

## Funkcjonalności

### Klient

Klient może:

- utworzyć konto,
- zalogować się i wylogować,
- edytować podstawowe dane swojego profilu,
- przeglądać firmy dostępne na platformie,
- przeglądać ofertę wybranej firmy,
- przeglądać szczegóły usług,
- wybrać usługę i pracownika,
- sprawdzić dostępne terminy,
- utworzyć rezerwację,
- przeglądać własne przyszłe rezerwacje,
- przeglądać historię rezerwacji,
- anulować własną przyszłą rezerwację.

### Firmy

Każda firma posiada własną przestrzeń w systemie.

Z firmą mogą być powiązane:

- podstawowe informacje o firmie,
- kategorie usług,
- oferowane usługi,
- pracownicy,
- usługi wykonywane przez poszczególnych pracowników,
- godziny pracy,
- dostępność pracowników,
- rezerwacje klientów.

Dane poszczególnych firm są od siebie oddzielone, dzięki czemu jedna platforma może obsługiwać wielu niezależnych usługodawców.

### Pracownik

Pracownik po zalogowaniu ma dostęp do własnego panelu.

Może w nim:

- sprawdzić wizyty zaplanowane na dzisiaj,
- przeglądać przyszłe wizyty,
- przeglądać historię wizyt,
- wyświetlać szczegóły własnych rezerwacji,
- sprawdzać informacje o kliencie i wybranej usłudze,
- zmieniać status obsługiwanej wizyty.

Pracownik nie posiada dostępu do rezerwacji innych pracowników, jeżeli nie ma do tego odpowiednich uprawnień.

### Administrator

Administrator posiada dostęp do panelu umożliwiającego zarządzanie platformą.

Panel administratora pozwala między innymi na:

- zarządzanie użytkownikami,
- zarządzanie firmami,
- zarządzanie pracownikami,
- zarządzanie kategoriami usług,
- dodawanie, edytowanie i dezaktywowanie usług,
- przypisywanie usług do pracowników,
- określanie dostępności pracowników,
- przeglądanie wszystkich rezerwacji,
- filtrowanie rezerwacji,
- zmianę statusów rezerwacji,
- anulowanie rezerwacji,
- przeglądanie podstawowych statystyk systemu.

## System rezerwacji

System rezerwacji jest jednym z głównych elementów aplikacji.

Podczas wyznaczania dostępnych terminów uwzględniane są:

- wybrana firma,
- wybrana usługa,
- pracownik wykonujący usługę,
- czas trwania usługi,
- godziny pracy pracownika,
- dostępność pracownika,
- istniejące aktywne rezerwacje.

System nie pozwala na utworzenie rezerwacji:

- w przeszłości,
- poza godzinami pracy pracownika,
- u pracownika, który nie wykonuje wybranej usługi,
- w terminie kolidującym z inną aktywną rezerwacją.

Konflikty terminów są sprawdzane po stronie serwera bezpośrednio przed zapisaniem rezerwacji w bazie danych.

Dzięki temu użytkownik nie może ominąć mechanizmu dostępności poprzez ręczną zmianę danych formularza lub parametrów żądania.

## Statusy rezerwacji

Każda rezerwacja posiada status określający jej aktualny stan.

Dostępne statusy:

- oczekująca,
- potwierdzona,
- zrealizowana,
- anulowana.

Klient może anulować wyłącznie własną przyszłą rezerwację.

Pracownik może zmieniać status wizyt, które są do niego przypisane.

Administrator może zarządzać rezerwacjami z poziomu panelu administracyjnego.

## Baza danych

book.it wykorzystuje relacyjną bazę danych przystosowaną do obsługi wielu firm.

Baza przechowuje między innymi informacje o:

- użytkownikach,
- firmach,
- pracownikach,
- kategoriach usług,
- usługach,
- przypisaniu usług do pracowników,
- dostępności pracowników,
- rezerwacjach.

Projekt bazy wykorzystuje:

- klucze główne,
- klucze obce,
- relacje `1:N`,
- relacje `N:M`,
- ograniczenia integralności danych,
- ograniczenia `UNIQUE` tam, gdzie są wymagane,
- odpowiednie typy danych.

Plik umożliwiający utworzenie struktury bazy danych oraz zaimportowanie danych testowych znajduje się w:

```text
database/database.sql
```

Do projektu dołączony jest również diagram ERD przedstawiający strukturę oraz relacje pomiędzy tabelami.

## Technologie

Projekt wykorzystuje:

- PHP,
- HTML5,
- CSS3,
- JavaScript,
- MySQL / MariaDB,
- Git,
- GitHub.

Lista technologii może zostać rozszerzona wraz z rozwojem projektu.

## Bezpieczeństwo

Aplikacja posiada zabezpieczenia zarówno po stronie interfejsu, jak i serwera.

W projekcie wykorzystywane są między innymi:

- bezpieczne hashowanie haseł,
- weryfikacja haseł po stronie serwera,
- prepared statements przy komunikacji z bazą danych,
- walidacja danych po stronie serwera,
- zabezpieczenie danych wyświetlanych w HTML przed XSS,
- kontrola sesji użytkownika,
- system ról i uprawnień,
- zabezpieczenie chronionych podstron,
- kontrola dostępu do danych innych użytkowników,
- sprawdzanie właściciela rezerwacji przed wykonaniem operacji,
- ponowne sprawdzanie dostępności terminu przed utworzeniem rezerwacji.

Samo ukrycie elementu interfejsu nie jest traktowane jako zabezpieczenie. Uprawnienia użytkownika są sprawdzane również po stronie serwera.

Repozytorium nie powinno zawierać haseł do bazy danych, kluczy API ani innych poufnych danych.

## Struktura projektu

Przykładowa struktura repozytorium:

```text
book.it/
├── database/
│   └── database.sql
│
├── public/
├── src/
├── css/
├── js/
├── assets/
│
├── README.md
└── ...
```

Struktura katalogów może ulec zmianie wraz z rozwojem projektu.

## Uruchomienie projektu

### 1. Sklonowanie repozytorium

```bash
git clone <adres-repozytorium>
cd book.it
```

### 2. Przygotowanie bazy danych

Utwórz nową bazę danych MySQL lub MariaDB.

Następnie zaimportuj plik:

```text
database/database.sql
```

Plik tworzy strukturę bazy danych oraz wymagane dane testowe.

### 3. Konfiguracja połączenia z bazą

Uzupełnij dane połączenia z bazą danych zgodnie z konfiguracją lokalnego środowiska.

Przykładowe wymagane dane:

```text
host
database
username
password
```

### 4. Uruchomienie aplikacji

Do działania aplikacji wymagane jest środowisko obsługujące:

- PHP,
- MySQL lub MariaDB,
- serwer HTTP.

Projekt można uruchomić lokalnie np. za pomocą XAMPP.

Po poprawnym skonfigurowaniu aplikacji należy otworzyć ją w przeglądarce pod adresem odpowiadającym konfiguracji lokalnego serwera.

## Konta testowe

### Administrator

```text
E-mail: admin@example.com
Hasło: do_uzupelnienia
```

### Pracownik

```text
E-mail: employee@example.com
Hasło: do_uzupelnienia
```

Dane testowe zostaną zaktualizowane po przygotowaniu finalnych kont.

## Rozszerzenia projektu

Głównym rozszerzeniem book.it względem podstawowej wersji projektu jest obsługa wielu firm w ramach jednej platformy.

Standardowy system rezerwacji może być przeznaczony dla pojedynczej firmy. book.it rozszerza ten model i pozwala wielu niezależnym usługodawcom korzystać z tego samego systemu.

Każda firma posiada własne:

- dane,
- usługi,
- kategorie,
- pracowników,
- godziny pracy,
- dostępność,
- rezerwacje.

Planowane dodatkowe funkcjonalności:

- [ ] obsługa wielu firm,
- [ ] profile firm,
- [ ] wyszukiwanie firm,
- [ ] wyszukiwanie usług,
- [ ] filtrowanie wyników,
- [ ] dynamiczne pobieranie dostępnych terminów,
- [ ] graficzny kalendarz rezerwacji,
- [ ] różne godziny pracy pracowników,
- [ ] urlopy i dni wolne,
- [ ] przerwy w godzinach pracy,
- [ ] powiadomienia e-mail,
- [ ] przypomnienia o nadchodzących rezerwacjach,
- [ ] resetowanie hasła,
- [ ] historia zmian statusów,
- [ ] log operacji administratora,
- [ ] statystyki i wykresy,
- [ ] wyszukiwanie i paginacja,
- [ ] eksport danych.

Lista będzie aktualizowana wraz z rozwojem projektu.

## Autorzy

Projekt realizowany w ramach przedmiotu **Tworzenie stron i aplikacji internetowych**.

Autorzy:

- Imię Nazwisko
- Imię Nazwisko

## Status projektu

Projekt jest obecnie w trakcie realizacji.