CREATE DATABASE IF NOT EXISTS `book_it`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `book_it`;

SET NAMES utf8mb4;

CREATE TABLE `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(80) NOT NULL,
    `surname` VARCHAR(80) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(30) NULL,
    `role` ENUM('client', 'employee', 'admin') NOT NULL DEFAULT 'client',
    `active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `uq_users_email` UNIQUE (`email`)
) ENGINE=InnoDB;

CREATE TABLE `service_categories` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(500) NULL,
    `active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `uq_service_categories_name` UNIQUE (`name`)
) ENGINE=InnoDB;

CREATE TABLE `services` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(120) NOT NULL,
    `description` VARCHAR(1000) NULL,
    `duration_minutes` SMALLINT UNSIGNED NOT NULL,
    `price` DECIMAL(10, 2) UNSIGNED NOT NULL,
    `active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `uq_services_category_name` UNIQUE (`category_id`, `name`),
    INDEX `idx_services_category_id` (`category_id`),
    CONSTRAINT `chk_services_duration_positive` CHECK (`duration_minutes` > 0),
    CONSTRAINT `chk_services_price_nonnegative` CHECK (`price` >= 0),
    CONSTRAINT `fk_services_category`
        FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE `employees` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `description` VARCHAR(1000) NULL,
    `active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `uq_employees_user_id` UNIQUE (`user_id`),
    CONSTRAINT `fk_employees_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE `employee_services` (
    `employee_id` BIGINT UNSIGNED NOT NULL,
    `service_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`employee_id`, `service_id`),
    INDEX `idx_employee_services_service_id` (`service_id`),
    CONSTRAINT `fk_employee_services_employee`
        FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_employee_services_service`
        FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `employee_availability` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_id` BIGINT UNSIGNED NOT NULL,
    `day_of_week` TINYINT UNSIGNED NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `uq_employee_availability_slot`
        UNIQUE (`employee_id`, `day_of_week`, `start_time`, `end_time`),
    INDEX `idx_employee_availability_employee_day` (`employee_id`, `day_of_week`),
    CONSTRAINT `chk_employee_availability_day`
        CHECK (`day_of_week` BETWEEN 1 AND 7),
    CONSTRAINT `chk_employee_availability_time`
        CHECK (`end_time` > `start_time`),
    CONSTRAINT `fk_employee_availability_employee`
        FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `reservations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `employee_id` BIGINT UNSIGNED NOT NULL,
    `service_id` BIGINT UNSIGNED NOT NULL,
    `reservation_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `status` ENUM('pending', 'confirmed', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    `comment` VARCHAR(1000) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_reservations_user_id` (`user_id`),
    INDEX `idx_reservations_employee_date` (`employee_id`, `reservation_date`, `start_time`),
    INDEX `idx_reservations_service_id` (`service_id`),
    INDEX `idx_reservations_status_date` (`status`, `reservation_date`),
    CONSTRAINT `chk_reservations_time` CHECK (`end_time` > `start_time`),
    CONSTRAINT `fk_reservations_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_reservations_employee`
        FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_reservations_service`
        FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_reservations_employee_service`
        FOREIGN KEY (`employee_id`, `service_id`)
        REFERENCES `employee_services` (`employee_id`, `service_id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO `users` (`id`, `name`, `surname`, `email`, `password`, `phone`, `role`) VALUES
    (1, 'Kacper', 'Administrator', 'admin@bookit.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', '+48 500 100 100', 'admin'),
    (2, 'Jan', 'Kowalski', 'klient@bookit.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', '+48 500 200 200', 'client'),
    (3, 'Anna', 'Nowak', 'anna.nowak@bookit.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', '+48 500 300 300', 'employee'),
    (4, 'Piotr', 'Wiśniewski', 'piotr.wisniewski@bookit.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', '+48 500 400 400', 'employee');

INSERT INTO `service_categories` (`id`, `name`, `description`) VALUES
    (1, 'Fryzjerstwo', 'Usługi strzyżenia i stylizacji włosów.'),
    (2, 'Kosmetyka', 'Podstawowe zabiegi kosmetyczne.'),
    (3, 'Masaż', 'Masaże relaksacyjne i sportowe.');

INSERT INTO `services` (`id`, `category_id`, `name`, `description`, `duration_minutes`, `price`) VALUES
    (1, 1, 'Strzyżenie damskie', 'Strzyżenie wraz z modelowaniem.', 60, 120.00),
    (2, 1, 'Strzyżenie męskie', 'Klasyczne strzyżenie męskie.', 30, 70.00),
    (3, 2, 'Manicure klasyczny', 'Pielęgnacja paznokci dłoni.', 45, 85.00),
    (4, 3, 'Masaż relaksacyjny', 'Masaż całego ciała.', 60, 160.00),
    (5, 3, 'Masaż sportowy', 'Masaż ukierunkowany na regenerację mięśni.', 45, 150.00);

INSERT INTO `employees` (`id`, `user_id`, `description`) VALUES
    (1, 3, 'Fryzjerka i stylistka z doświadczeniem w manicure.'),
    (2, 4, 'Masażysta specjalizujący się w masażu sportowym i relaksacyjnym.');

INSERT INTO `employee_services` (`employee_id`, `service_id`) VALUES
    (1, 1),
    (1, 2),
    (1, 3),
    (2, 4),
    (2, 5);

INSERT INTO `employee_availability` (`employee_id`, `day_of_week`, `start_time`, `end_time`) VALUES
    (1, 1, '09:00:00', '17:00:00'),
    (1, 2, '09:00:00', '17:00:00'),
    (1, 3, '10:00:00', '18:00:00'),
    (1, 4, '09:00:00', '17:00:00'),
    (1, 5, '09:00:00', '15:00:00'),
    (2, 1, '12:00:00', '20:00:00'),
    (2, 2, '12:00:00', '20:00:00'),
    (2, 3, '12:00:00', '20:00:00'),
    (2, 4, '12:00:00', '20:00:00'),
    (2, 5, '10:00:00', '18:00:00');

INSERT INTO `reservations`
    (`user_id`, `employee_id`, `service_id`, `reservation_date`, `start_time`, `end_time`, `status`, `comment`)
VALUES
    (2, 1, 1, '2026-09-28', '10:00:00', '11:00:00', 'confirmed', 'Pierwsza wizyta klienta.'),
    (2, 2, 4, '2026-09-21', '14:00:00', '15:00:00', 'completed', 'Wizyta zakończona.');

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

-- Fresh local installation only: fictional demo accounts, password Bookit-Demo-2026!
-- Existing installations run ONLY the incremental migration, never this seed.
UPDATE users SET password = '$argon2id$v=19$m=65536,t=4,p=1$U3dGTlFzY3psR1NUL1k0dg$zP+tI+Qni/GkPUM9rhpoOTfNLNts4+WqjH3HLYcgyJw' WHERE id IN (1, 2, 3, 4);
INSERT INTO users (name, surname, email, password, phone, role)
    VALUES ('Ola', 'Właścicielka', 'firma@bookit.test', '$argon2id$v=19$m=65536,t=4,p=1$U3dGTlFzY3psR1NUL1k0dg$zP+tI+Qni/GkPUM9rhpoOTfNLNts4+WqjH3HLYcgyJw', '+48 500 500 500', 'client');
INSERT INTO company_memberships (user_id, company_id, role)
    VALUES (LAST_INSERT_ID(), @legacy_company_id, 'owner');

