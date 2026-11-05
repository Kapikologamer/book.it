-- Run once after 002, with writes paused and a verified backup.
-- Review database/preflight_003.sql first. DDL commits implicitly.
ALTER TABLE companies
    ADD COLUMN short_description VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN description TEXT NULL,
    ADD COLUMN phone VARCHAR(30) NOT NULL DEFAULT '',
    ADD COLUMN email VARCHAR(190) NOT NULL DEFAULT '',
    ADD COLUMN city VARCHAR(100) NOT NULL DEFAULT '',
    ADD COLUMN address VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN website VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN logo_path VARCHAR(100) NULL,
    ADD COLUMN banner_path VARCHAR(100) NULL;

ALTER TABLE services ALTER COLUMN duration_minutes SET DEFAULT 45;

ALTER TABLE employee_services
    ADD COLUMN active BOOLEAN NOT NULL DEFAULT 1,
    ADD CONSTRAINT chk_employee_services_active CHECK (active IN (0, 1));

-- NULL means that the historical price is unknown; never copy today's price.
ALTER TABLE reservations
    ADD COLUMN price_snapshot DECIMAL(10,2) NULL,
    ADD CONSTRAINT chk_reservations_price_snapshot
        CHECK (price_snapshot IS NULL OR price_snapshot >= 0),
    ADD INDEX idx_reservations_date_id (reservation_date, id);

CREATE TABLE employee_time_off (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    reason VARCHAR(255) NOT NULL,
    kind ENUM('holiday','break','day_off') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_time_off_employee_range (employee_id, starts_at, ends_at),
    CONSTRAINT chk_time_off_range CHECK (ends_at > starts_at),
    CONSTRAINT chk_time_off_kind CHECK (kind IN ('holiday','break','day_off')),
    CONSTRAINT fk_time_off_employee FOREIGN KEY (employee_id)
        REFERENCES employees(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE employee_invitations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(190) NOT NULL,
    invited_by BIGINT UNSIGNED NOT NULL,
    status ENUM('pending','accepted','declined','cancelled') NOT NULL DEFAULT 'pending',
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invitations_company_status (company_id, status, id),
    INDEX idx_invitations_email_status (email, status, expires_at),
    INDEX idx_invitations_invited_by (invited_by),
    CONSTRAINT chk_invitations_email CHECK (CHAR_LENGTH(TRIM(email)) > 0),
    CONSTRAINT chk_invitations_status CHECK (status IN ('pending','accepted','declined','cancelled')),
    CONSTRAINT fk_invitations_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_invitations_actor FOREIGN KEY (invited_by)
        REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE admin_audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    actor_id BIGINT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NULL,
    actor_role VARCHAR(20) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(40) NOT NULL,
    summary VARCHAR(1000) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_created (created_at, id),
    INDEX idx_audit_company_created (company_id, created_at, id),
    INDEX idx_audit_actor_created (actor_id, created_at, id),
    INDEX idx_audit_action_created (action, created_at, id),
    INDEX idx_audit_entity (entity_type, entity_id, id),
    CONSTRAINT chk_audit_actor_role CHECK (CHAR_LENGTH(TRIM(actor_role)) > 0),
    CONSTRAINT chk_audit_entity_type CHECK (CHAR_LENGTH(TRIM(entity_type)) > 0),
    CONSTRAINT chk_audit_action CHECK (CHAR_LENGTH(TRIM(action)) > 0),
    CONSTRAINT chk_audit_summary CHECK (CHAR_LENGTH(TRIM(summary)) > 0),
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id)
        REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_audit_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;
