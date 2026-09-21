# Wymagania projektu book.it

## 1. Cel i zakres

`book.it` jest platformą typu marketplace do rezerwowania usług wielu niezależnych firm. Jeden klient korzysta z jednego konta w wielu firmach. Każda firma działa jako osobny tenant i zarządza własnym profilem, ofertą, zespołem, dostępnością oraz rezerwacjami.

Obecny etap obejmuje wyłącznie statyczny prototyp HTML/CSS/JS i fundamenty dokumentacji. Backend PHP, baza MySQL/MariaDB oraz rzeczywiste operacje na danych powstaną później.

## 2. Role i odpowiedzialności

| Rola | Zakres docelowy |
| --- | --- |
| Klient | Konto, profil, wyszukiwanie oferty, rezerwowanie terminu, podgląd i anulowanie własnych przyszłych rezerwacji. |
| Pracownik | Własny terminarz, szczegóły przypisanych wizyt, niezbędne dane klienta i usługi, zmiana statusu własnych wizyt. |
| Administrator firmy / właściciel | Profil firmy, usługi, zespół, przypisania usług, dostępność, rezerwacje i podstawowe statystyki danego tenanta. |
| Administrator platformy | Nadzór nad użytkownikami i firmami całej platformy; rola planowana poza MVP. |

Uprawnienia będą sprawdzane po stronie serwera. Ukrycie elementu interfejsu nie jest mechanizmem bezpieczeństwa.

## 3. MVP wymagane przez szkołę

- Rejestracja, logowanie, wylogowanie i edycja podstawowego profilu klienta.
- Publiczne kategorie, aktywne usługi oraz szczegóły: opis, cena, czas trwania i dostępność.
- Proces rezerwacji: `usługa → pracownik → data → godzina → potwierdzenie`.
- Konto klienta pokazujące wyłącznie jego rezerwacje i pozwalające anulować własną przyszłą wizytę.
- Panel pracownika z wizytami na dziś, przyszłymi, historią i zmianą statusu.
- Panel administratora do zarządzania użytkownikami, pracownikami, kategoriami, usługami, dostępnością i rezerwacjami.
- Statusy rezerwacji: `oczekująca`, `potwierdzona`, `zrealizowana`, `anulowana`.
- Relacyjna baza danych z kluczami głównymi i obcymi, relacjami 1:N oraz co najmniej jedną relacją N:M.
- Responsywny i spójny interfejs.

## 4. Rozszerzenia book.it

- Obsługa wielu firm w jednej aplikacji i ścisła izolacja danych tenantów.
- Publiczny profil firmy z unikalnym slugiem, logo, bannerem, opisem i kontaktem.
- Właściciel firmy zarządzający marką, ofertą, zespołem i dostępnością.
- Konto klienta działające we wszystkich firmach oraz avatar z fallbackiem inicjałów.
- Osobny administrator całej platformy.

Każde zapytanie dotyczące usług, pracowników, dostępności lub rezerwacji musi być ograniczone do właściwej firmy. Identyfikator przesłany przez klienta nie może sam w sobie nadawać dostępu.

## 5. Proces i reguły rezerwacji

Docelowy przebieg: wybór firmy i usługi, wybór uprawnionego pracownika, daty, wolnej godziny oraz końcowe potwierdzenie.

Serwer wyznacza dostępne terminy z uwzględnieniem czasu usługi, grafiku, wyjątków i istniejących aktywnych rezerwacji. Nie pozwala rezerwować w przeszłości ani poza godzinami pracy. Bezpośrednio przed zapisem ponownie sprawdza konflikt przedziałów:

```text
new_start < existing_end AND new_end > existing_start
```

Terminy stykające się granicą są dozwolone, o ile spełniają pozostałe reguły.

## 6. Bezpieczeństwo

- Hasła przechowywane przez `password_hash()` i weryfikowane przez `password_verify()`.
- Zapytania przez prepared statements w PDO/MySQLi albo bezpieczny ORM.
- Walidacja danych i autoryzacja każdej operacji po stronie serwera.
- Kodowanie danych wyświetlanych w HTML oraz ochrona przed XSS i CSRF.
- Bezpieczne sesje, kontrola ról, właściciela zasobu i tenant scope.
- Unikalność adresu e-mail oraz bezpieczne, nieujawniające szczegółów komunikaty błędów.
- Brak haseł, kluczy API i innych sekretów w repozytorium.
- Ochrona przed IDOR: klient nie może odczytać ani zmienić cudzej rezerwacji przez podmianę ID.

## 7. Docelowe encje danych

Planowany model obejmuje co najmniej:

- `users` — wspólna tożsamość i dane logowania,
- `businesses` i `business_profiles` — tenant oraz publiczny branding,
- `business_members` — członkostwo, rola i powiązanie użytkownika z firmą,
- `service_categories` i `services` — oferta ograniczona do firmy,
- `employees` — profil pracownika w konkretnej firmie,
- `employee_services` — relacja N:M pracowników i usług,
- `employee_availability` oraz `availability_exceptions` — grafik i wyjątki,
- `reservations` — klient, firma, usługa, pracownik, przedział czasu i status,
- opcjonalnie `reservation_status_history` oraz `audit_logs`.

Każda encja należąca do firmy powinna mieć jednoznaczne powiązanie z `businesses`. Plik `database/database.sql`, dane testowe i diagram ERD zostaną przygotowane w kolejnym etapie.

## 8. Funkcje przyszłe

- Automatyczne pobieranie wolnych godzin i kalendarz graficzny.
- Urlopy, dni wolne i przerwy w grafiku.
- Weryfikacja e-mail, reset hasła, limity logowania i przypomnienia.
- Wyszukiwanie, filtry, paginacja i ulubione firmy.
- Historia statusów, dziennik operacji, wykresy oraz eksport PDF.
- REST API, migracje i testy automatyczne.

## 9. Granice obecnego prototypu

Widoki nie wykonują logowania, rejestracji, uploadu, CRUD-u ani rezerwacji. JavaScript obsługuje wyłącznie zachowania prezentacyjne, takie jak menu mobilne, komunikaty demonstracyjne, podgląd lokalnie wybranego obrazu i fallback inicjałów. Nie zapisuje danych.
