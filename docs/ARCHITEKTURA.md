# Architektura Etapu 2

Jedna tożsamość = jeden rekord users. Publiczna rejestracja zawsze tworzy users.role=client. Rola admin jest rolą administratora platformy; employee zachowano dla kompatybilności Etapu 1. Dostęp do obszaru pracownika wymaga dodatkowo aktywnego członkostwa employee. Dostęp właściciela wymaga członkostwa owner konkretnej aktywnej firmy. Właściciel nie uzyskuje globalnego admina.

company_memberships ma PK(user_id,company_id), więc w jednej firmie użytkownik ma jedną rolę. Jeden użytkownik może mieć członkostwa w wielu firmach. Obecny profil employees nadal zachowuje UNIQUE(user_id) z Etapu 1 (jeden profil pracownika). Kategorie usług są globalnym słownikiem. Usługi i pracownicy mają company_id, a złożone FK employee_services zabraniają powiązania pracownika z usługą innej firmy. Rezerwacja dziedziczy firmę przez parę pracownik–usługa; dostępność przez pracownika.

## Przepływ żądania

```text
public/index.php
  → app/bootstrap.php (konfiguracja sesji, nagłówki, neutralne błędy)
  → app/controller.php (zamknięta lista tras, tożsamość, uprawnienia, CSRF)
  → walidacja w security.php
  → przypadek użycia / zapytania PDO w accounts.php
  → MySQL/MariaDB
  → 303 redirect
  → GET + wspólny layout i komponenty formularzy
```

Mały projekt nie ma oddzielnej klasy repozytorium dla każdej tabeli: accounts.php skupia dostęp do kont i transakcje. Wszystkie parametry zapytań przechodzą przez PDO prepare/execute. Wyjątkiem dynamicznej nazwy kolumny jest wewnętrzny bool wybierający password albo pin_hash; nie pochodzi ona z formularza.

Każdy POST przechodzi wspólny CSRF, a trasy chronione także sprawdzenie zwykłej sesji. Pola user_id, role, company_id, active i auth_version w POST są odrzucane. Edycja profilu używa wyłącznie użytkownika z sesji. Guard firmy wykonuje zapytanie z user_id, company_id, role, aktywnością członkostwa i firmy przed renderowaniem nazwy.

## Trasy i moduły

Wszystkie trasy są parametrem route w ./index.php względem katalogu aplikacji. Zasoby, linki, formularze i nagłówki Location używają ścieżek względnych, również przy uruchomieniu w podkatalogu. Mapowanie UI i akcji znajduje się w README. GET pokazuje formularz; POST zapisuje i wykonuje 303. Niepoprawny formularz zachowuje tylko jawne pola i komunikaty w jednorazowym stanie form. Jedna implementacja komponentów obejmuje wszystkie widoki hasła i PIN-u.

Po rejestracji klient trafia do onboarding/client, właściciel do onboarding/company. Po logowaniu kolejność: admin → właściciel → członek employee → klient. Pierwsza własna firma jest domyślna; company_id może wskazać inną, ale tylko po autoryzacji. Onboarding pozostaje szkieletem i nie zmienia pending na ready.

## Dwa różne stany sesji

Zwykła sesja auth: id, version, started, last. Dane użytkownika i uprawnienia odczytywane są z DB w każdym żądaniu. Brak haseł, PIN-ów i kopii roli w sesji.

Sesja recovery: id, version, expires. Nie zawiera auth, więc nie przechodzi żadnego guardu zwykłego konta. Ma 300 sekund i wyłącznie prawo ustawienia hasła. Warunkowy UPDATE według auth_version inkrementuje wersję: wcześniejsze sesje i granty stają się nieważne, a drugi równoległy zapis z tej samej wersji nie może się udać. Po resecie nie następuje automatyczne logowanie.

Ustawienia hasła/PIN-u zachowują tylko własną sesję przez odczyt nowej wersji i rotację ID. Zmiana PIN-u wymaga obecnego hasła. Przy zmianie hasła wymagane jest obecne hasło albo ważny ograniczony grant.

## Rozwój

Pełne profile działalności, uploady, usługi, grafik i rezerwacje pozostają na kolejne etapy. Nowe akcje muszą używać tych samych guardów i sprawdzać tenant przed zapytaniami o jego dane. Obecne role nie są edytowalne przez publiczne formularze.
