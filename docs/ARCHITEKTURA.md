# Architektura book.it — Etap 3

## Granice ról i danych

Jedna tożsamość odpowiada jednemu rekordowi `users`. `users.role='admin'` jest rolą globalną. Publiczna rejestracja klienta i firmy tworzy konto typu `client`; właścicielstwo wynika wyłącznie z aktywnego `company_memberships.role='owner'` aktywnej firmy. Dostęp pracownika wymaga członkostwa `employee` oraz profilu `employees`.

Właściciel może należeć do wielu firm i przed wejściem do panelu wybiera `company_id`. Identyfikator z URL nie nadaje uprawnień: `management_context()` ponownie sprawdza konto, firmę, członkostwo i rolę. Administrator może pracować globalnie albo jawnie zawęzić kontekst do firmy.

Kategorie są słownikiem globalnym. `services` i `employees` mają `company_id`; złożone FK w `employee_services` uniemożliwiają połączenie rekordów różnych firm. Rezerwacja nie duplikuje `company_id` — firma wynika ze spójnej pary pracownik–usługa.

## Przepływ żądania

```text
public/index.php
  → app/bootstrap.php
  → app/controller.php
      ├─ konto i uwierzytelnianie → app/accounts.php
      └─ admin/company → app/management/controller.php
           → management_context (tożsamość i zakres)
           → allowlista pól + CSRF
           → transakcja
           → app/management/domain.php (walidacja i mutacja)
           → MySQL/MariaDB + admin_audit_logs
           → COMMIT → 303 → GET
  → app/management/read.php (filtrowany odczyt, KPI, paginacja)
  → app/views/layout.php + app/views/management.php
```

Routing pozostaje zamkniętą listą opartą na `?route=...`. Każdy zapis wymaga POST, tokena CSRF, jawnej allowlisty pól i świeżego sprawdzenia aktora w transakcji. Id obiektu jest odczytywane z kontrolowanego parametru GET, a jego własność jest sprawdzana na podstawie rekordu z bazy. Po sukcesie stosowany jest PRG 303. Dane formularza przechowywane po błędzie nie obejmują sekretów.

## Moduły

- `app/management/controller.php` — mapa tras, kontekst admin/owner, metody HTTP, CSRF, allowlisty, eksport i PRG;
- `app/management/domain.php` — przypadki użycia kategorii, usług, pracowników, zaproszeń, grafiku, nieobecności, rezerwacji, moderacji, profilu i audytu;
- `app/management/read.php` — wspólne listy z przygotowanymi parametrami, filtry, paginacja, KPI, wykresy i reguła gotowości;
- `app/management/media.php` — walidacja, dekodowanie i ponowny zapis obrazów poza document root;
- `app/media.php` — odczyt tylko nazw mediów faktycznie przypisanych do firmy;
- `app/views/management*.php` — wspólne formularze, tabele, statusy, potwierdzenia i layout obu paneli.

Logika jest współdzielona przez admina i ownera. Różnicę stanowi wyłącznie autoryzowany kontekst: globalny admin może mieć `company_id=NULL`, owner zawsze pracuje w konkretnej firmie. Kategorie, użytkownicy i lista firm są dostępne wyłącznie globalnemu administratorowi.

## Mutacje i integralność

Mutacje wymagają transakcji utworzonej przez kontroler. W transakcji blokowany jest aktor, odpowiednie rekordy i — dla operacji kont — lista administratorów. Wpis audytu powstaje w tej samej transakcji co zmiana; rollback usuwa oba. Plik uploadu jest kasowany po rollbacku, a stary obraz dopiero po udanym commitcie.

- kategoria powiązana z usługą nie jest fizycznie usuwana;
- usługa z rezerwacją może być dezaktywowana, ale nie usunięta;
- usunięte przypisanie pracownik–usługa staje się nieaktywne, aby zachować złożony FK rezerwacji;
- aktywna rezerwacja blokuje zmianę grafiku lub nieobecność, która pozostawiłaby ją poza dostępnością;
- status rezerwacji podlega zamkniętej mapie przejść;
- wyłączenie konta zwiększa `auth_version`, a ostatni aktywny administrator jest chroniony.

## Dashboard i listy

KPI i wykresy nie są zapisane na stałe. `management_dashboard()` agreguje aktualne wiersze. Data „dzisiaj” jest liczona w `Europe/Warsaw`. Wykres siedmiu dni zawiera również dni z zerową liczbą, statusy obejmują wszystkie rezerwacje, a popularność usługi jest liczbą rezerwacji wszystkich statusów. W kontekście firmy każde zapytanie wykorzystuje wcześniej zweryfikowane `company_id`.

Listy stosują stabilne sortowanie z `id`, 25 rekordów na stronę, przygotowane parametry i allowlisty filtrów. Filtry pozostają w GET. Eksport rezerwacji używa tej samej definicji zapytania i zakresu, ogranicza wynik do 10 000 wierszy oraz zapisuje audyt eksportu.

## Onboarding firmy

`onboarding_status` nie jest deklaracją ustawianą ręcznie. Po każdej mutacji zarządzania `management_refresh_readiness()` wylicza go ponownie. `ready` wymaga jednocześnie:

1. aktywnej firmy oraz niepustych: nazwy, krótkiego opisu, telefonu, e-maila, miasta i adresu;
2. aktywnej usługi w aktywnej kategorii;
3. aktywnego konta, członkostwa i profilu pracownika;
4. aktywnego przypisania tej usługi pracownikowi;
5. co najmniej jednego poprawnego przedziału godzin pracy.

Utrata dowolnego warunku przywraca `pending`. Po zalogowaniu owner jednej gotowej firmy trafia do `company/dashboard`; owner firmy niegotowej do `onboarding/company`; właściciel wielu firm do `company/choose`.

## Sesje i odzyskiwanie

Zwykła sesja `auth` przechowuje wyłącznie id, wersję, początek i ostatnią aktywność. Użytkownik oraz uprawnienia są czytane ponownie z DB. Sesja wygasa po 30 minutach bezczynności lub 8 godzinach i jest unieważniana przez zmianę `auth_version`.

Stan `recovery` jest oddzielnym, pięciominutowym grantem do ustawienia hasła. Nie przechodzi guardów panelu i nie tworzy zwykłej sesji. Po resecie użytkownik loguje się ponownie.

## Świadome ograniczenia

`employees.user_id` pozostaje unikalne, więc jedno konto może mieć profil pracownika tylko w jednej firmie. System administruje istniejącymi rezerwacjami, ale nie tworzy ich po stronie klienta. Wyznaczanie slotów, transakcyjna rezerwacja i pełny panel pracownika należą do Etapu 4.
