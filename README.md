# book.it — System rezerwacji usług

Szkolny projekt systemu rezerwacji usług. Obecna wersja obejmuje wyłącznie **Etap 1**: uporządkowany szkielet aplikacji oraz projekt relacyjnej bazy danych. Termin etapu: 25.09.2026.

## Autorzy

- Kacper Księżopolski
- Krystian Kurkus

## Technologie

- PHP
- HTML5
- CSS3
- JavaScript
- MySQL/MariaDB
- Git/GitHub

## Aktualny zakres

Projekt zawiera:

- minimalną strukturę aplikacji;
- znormalizowany model relacyjnej bazy danych;
- kompletny skrypt SQL z ograniczeniami integralności i danymi testowymi;
- diagram ERD;
- prostą, responsywną stronę startową.

Logowanie, rejestracja, obsługa ról w aplikacji, panele użytkowników i proces rezerwowania **nie należą do Etapu 1** i nie są jeszcze zaimplementowane.

## Struktura katalogów

```text
.
├── database/
│   └── database.sql
├── docs/
│   └── ERD.md
├── public/
│   ├── assets/
│   │   └── .gitkeep
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── app.js
│   ├── index.html
│   └── index.php
├── .gitignore
└── README.md
```

## Uruchomienie

1. Sklonuj repozytorium i przejdź do jego katalogu.
2. Zaimportuj `database/database.sql` do MySQL lub MariaDB. Skrypt utworzy bazę `book_it`, tabele i dane testowe.
3. Uruchom serwer PHP z katalogiem `public` jako katalogiem głównym:

   ```bash
   php -S localhost:8000 -t public
   ```

   Alternatywnie umieść projekt w katalogu obsługiwanym przez XAMPP i skieruj serwer na folder `public`.
4. Otwórz `http://localhost:8000` w przeglądarce.

## Dane testowe

Skrypt SQL dodaje następujące konta demonstracyjne:

| Rola | E-mail |
| --- | --- |
| Administrator | `admin@bookit.test` |
| Klient | `klient@bookit.test` |
| Pracownik | `anna.nowak@bookit.test` |
| Pracownik | `piotr.wisniewski@bookit.test` |

Wartości w kolumnie `password` są przykładowymi hashami, nie hasłami jawnymi. Logowanie zostanie zaimplementowane dopiero w Etapie 2.

## Workflow Git

Większe funkcje należy rozwijać na osobnych branchach, a zmiany dzielić na małe, logiczne commity. Każdy członek zespołu powinien commitować własną pracę. Historia projektu ma odzwierciedlać rzeczywistą, regularną pracę zespołu.

## Dokumentacja

- [Diagram i opis relacji](docs/ERD.md)
- [Skrypt tworzący bazę](database/database.sql)
