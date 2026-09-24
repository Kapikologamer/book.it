# Diagram ERD

Diagram odpowiada strukturze z pliku `database/database.sql`. Wartości `1`–`7` w `employee_availability.day_of_week` oznaczają dni od poniedziałku do niedzieli.

```mermaid
erDiagram
    USERS {
        BIGINT id PK
        VARCHAR name
        VARCHAR surname
        VARCHAR email UK
        VARCHAR password
        VARCHAR phone
        ENUM role
        BOOLEAN active
        TIMESTAMP created_at
        TIMESTAMP updated_at
    }

    SERVICE_CATEGORIES {
        BIGINT id PK
        VARCHAR name UK
        VARCHAR description
        BOOLEAN active
        TIMESTAMP created_at
    }

    SERVICES {
        BIGINT id PK
        BIGINT category_id FK
        VARCHAR name
        VARCHAR description
        SMALLINT duration_minutes
        DECIMAL price
        BOOLEAN active
        TIMESTAMP created_at
        TIMESTAMP updated_at
    }

    EMPLOYEES {
        BIGINT id PK
        BIGINT user_id FK,UK
        VARCHAR description
        BOOLEAN active
        TIMESTAMP created_at
        TIMESTAMP updated_at
    }

    EMPLOYEE_SERVICES {
        BIGINT employee_id PK,FK
        BIGINT service_id PK,FK
    }

    EMPLOYEE_AVAILABILITY {
        BIGINT id PK
        BIGINT employee_id FK
        TINYINT day_of_week
        TIME start_time
        TIME end_time
        TIMESTAMP created_at
    }

    RESERVATIONS {
        BIGINT id PK
        BIGINT user_id FK
        BIGINT employee_id FK
        BIGINT service_id FK
        DATE reservation_date
        TIME start_time
        TIME end_time
        ENUM status
        VARCHAR comment
        TIMESTAMP created_at
        TIMESTAMP updated_at
    }

    USERS ||--o| EMPLOYEES : "posiada profil"
    SERVICE_CATEGORIES ||--o{ SERVICES : "grupuje"
    EMPLOYEES ||--o{ EMPLOYEE_SERVICES : "realizuje"
    SERVICES ||--o{ EMPLOYEE_SERVICES : "jest przypisana"
    EMPLOYEES ||--o{ EMPLOYEE_AVAILABILITY : "ma dostępność"
    USERS ||--o{ RESERVATIONS : "składa"
    EMPLOYEES ||--o{ RESERVATIONS : "obsługuje"
    SERVICES ||--o{ RESERVATIONS : "dotyczy"
```

## Relacje

- `users` 1:0..1 `employees` — jedno konto może mieć najwyżej jeden profil pracownika; zapewnia to `UNIQUE(employees.user_id)`.
- `service_categories` 1:N `services` — usługa należy do jednej kategorii.
- `employees` N:M `services` — relację realizuje tabela łącząca `employee_services` ze złożonym kluczem głównym.
- `employees` 1:N `employee_availability` — pracownik może mieć wiele przedziałów dostępności.
- `users` 1:N `reservations` — użytkownik będący klientem może utworzyć wiele rezerwacji.
- `employees` 1:N `reservations` — pracownik może obsłużyć wiele rezerwacji.
- `services` 1:N `reservations` — usługa może wystąpić w wielu rezerwacjach.
- Złożony klucz obcy `reservations(employee_id, service_id)` gwarantuje, że rezerwacja dotyczy usługi przypisanej danemu pracownikowi.
