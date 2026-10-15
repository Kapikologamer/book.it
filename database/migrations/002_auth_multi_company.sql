-- Etap 2: wykonać RAZ na kopii/zabezpieczonej bazie Etapu 1.
-- DDL MySQL wykonuje implicit commit; przed importem konieczny backup.
-- Przed migracją sprawdzić kolizje LOWER(TRIM(email)) i rozwiązać je ręcznie.
ALTER TABLE users
    ADD COLUMN pin_hash VARCHAR(255) NULL,
    ADD COLUMN auth_version INT UNSIGNED NOT NULL DEFAULT 1;
UPDATE users SET email = LOWER(TRIM(email));

CREATE TABLE companies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    onboarding_status ENUM('pending','ready') NOT NULL DEFAULT 'pending',
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE company_memberships (
    user_id BIGINT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    role ENUM('owner','employee') NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, company_id),
    INDEX idx_membership_company (company_id, role),
    CONSTRAINT fk_membership_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_membership_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Stare usługi, pracownicy i wizyty trafiają do jednego jawnego tenanta.
-- Nie nadajemy istniejącym administratorom automatycznie własności firmy.
INSERT INTO companies (name) VALUES ('Firma demonstracyjna — Etap 1');
SET @legacy_company_id = LAST_INSERT_ID();
ALTER TABLE services ADD COLUMN company_id BIGINT UNSIGNED NULL;
ALTER TABLE employees ADD COLUMN company_id BIGINT UNSIGNED NULL;
UPDATE services SET company_id = @legacy_company_id;
UPDATE employees SET company_id = @legacy_company_id;
INSERT INTO company_memberships (user_id, company_id, role)
    SELECT user_id, company_id, 'employee' FROM employees;
ALTER TABLE services
    MODIFY company_id BIGINT UNSIGNED NOT NULL,
    DROP INDEX uq_services_category_name,
    ADD CONSTRAINT uq_services_company_category_name UNIQUE (company_id, category_id, name),
    ADD CONSTRAINT uq_services_id_company UNIQUE (id, company_id),
    ADD CONSTRAINT fk_services_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT;
ALTER TABLE employees
    MODIFY company_id BIGINT UNSIGNED NOT NULL,
    ADD CONSTRAINT uq_employees_id_company UNIQUE (id, company_id),
    ADD CONSTRAINT fk_employees_membership FOREIGN KEY (user_id, company_id)
        REFERENCES company_memberships(user_id, company_id) ON DELETE RESTRICT;
ALTER TABLE employee_services ADD COLUMN company_id BIGINT UNSIGNED NULL;
UPDATE employee_services es JOIN employees e ON e.id = es.employee_id SET es.company_id = e.company_id;
ALTER TABLE employee_services
    MODIFY company_id BIGINT UNSIGNED NOT NULL,
    ADD CONSTRAINT fk_es_employee_company FOREIGN KEY (employee_id, company_id)
        REFERENCES employees(id, company_id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_es_service_company FOREIGN KEY (service_id, company_id)
        REFERENCES services(id, company_id) ON DELETE RESTRICT;

CREATE TABLE auth_limits (
    scope_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    failures SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at BIGINT UNSIGNED NOT NULL,
    INDEX idx_auth_limits_expiry (expires_at)
) ENGINE=InnoDB;

