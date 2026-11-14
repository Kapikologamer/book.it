# Rezerwacje — kontrakt Etapu 4

## Źródło prawdy

Wspólny serwis znajduje się w `app/booking/domain.php`. JavaScript wyłącznie pobiera jego wynik z `booking/slots`; tworzenie, przełożenie i zmiana statusu nie ufają cenie, długości, końcowi, statusowi ani użytkownikowi z formularza.

## Wyznaczanie slotów

Wejście: zweryfikowane `service_id`, `employee_id`, lokalna data i opcjonalne `exclude_reservation_id`. Kontekst SQL potwierdza aktywną i gotową firmę, kategorię, usługę, pracownika, konto, członkostwo `employee` oraz przypisanie pracownik–usługa w tym samym `company_id`.

Pseudokod:

```text
sprawdź datę: dziś..dziś+90 dni
odczytaj duration_minutes z usługi
scal nachodzące/stykające się przedziały grafiku danego dnia ISO 1..7
dla każdego startu co 15 minut:
    end = start + duration_minutes
    wymagaj end <= koniec jednego scalonego przedziału pracy
    odrzuć start <= aktualny czas Europe/Warsaw
    odrzuć przecięcie z employee_time_off
    odrzuć przecięcie z pending/confirmed
zwróć unikalne, posortowane [start,end)
```

Konflikt dwóch półotwartych przedziałów zachodzi dokładnie, gdy:

```text
new_start < existing_end AND new_end > existing_start
```

Dlatego `09:00–10:00` i `10:00–11:00` mogą się stykać. Ta sama reguła obowiązuje dla nieobecności. `cancelled` i `completed` nie blokują przyszłego slotu.

## Czas

Strefa aplikacji to `Europe/Warsaw`. `DATE`, `TIME` i `DATETIME` w modelu są lokalnym czasem ściennym. Nocne przedziały są wykluczone przez `end_time > start_time`. Ponieważ model nie przechowuje offsetu, silnik konserwatywnie nie publikuje slotów w dniu zmiany DST; eliminuje to dwuznaczne lokalne czasy.

## Transakcja i race condition

Tworzenie:

```text
BEGIN
sprawdź idempotencyjny booking_token
SELECT id FROM employees WHERE id=? FOR UPDATE
ponownie pobierz kontekst i wylicz dostępne sloty
INSERT reservations z end_time, pending, price_snapshot i booking_token
INSERT reservation_history(action=created)
COMMIT
```

Wiersz pracownika jest punktem serializacji. Drugi zapis tego samego pracownika czeka, po czym ponownie widzi pierwszą rezerwację i otrzymuje kontrolowany konflikt. Zmiany grafiku i nieobecności również blokują ten wiersz. Przełożenie używa tego samego silnika, wyklucza wyłącznie bieżącą rezerwację, zachowuje `price_snapshot` i zapisuje stary/nowy termin w tej samej transakcji.

## Statusy i historia

Dozwolone przejścia:

- `pending → confirmed | cancelled`;
- `confirmed → completed | cancelled`;
- `completed`, `cancelled` są końcowe.

Klient może tylko anulować własną przyszłą aktywną wizytę. Pracownik może zmienić status wyłącznie swojej wizyty; przyszłej nie oznaczy jako completed. Owner i admin korzystają z tego samego serwisu, a adminowy audyt nadal jest zapisywany. `reservation_history` przechowuje aktora, czas, akcję, stare/nowe statusy i terminy.

## Endpointy

Publiczne: `companies`, `company/show`, `booking/employees`, `booking/date`, `booking/time`, `booking/slots`. Zalogowany nie-admin: `booking/confirm`, `booking/create`, `client/reservations*`. Pracownik: `employee/reservations*`. Każdy POST wymaga CSRF i allowlisty pól.
