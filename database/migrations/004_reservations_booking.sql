-- Etap 4. Uruchom po preflight_004.sql, na kopii zapasowej i przy wstrzymanych zapisach.
-- DDL MySQL/MariaDB wykonuje implicit commit.
ALTER TABLE reservations
    ADD COLUMN booking_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    ADD CONSTRAINT uq_reservations_booking_token UNIQUE (booking_token),
    ADD INDEX idx_reservations_employee_active_range (employee_id, reservation_date, status, start_time, end_time);

CREATE TABLE reservation_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    reservation_id BIGINT UNSIGNED NOT NULL,
    actor_id BIGINT UNSIGNED NOT NULL,
    action ENUM('created','status','rescheduled') NOT NULL,
    old_status ENUM('pending','confirmed','completed','cancelled') NULL,
    new_status ENUM('pending','confirmed','completed','cancelled') NULL,
    old_date DATE NULL,
    old_start_time TIME NULL,
    old_end_time TIME NULL,
    new_date DATE NULL,
    new_start_time TIME NULL,
    new_end_time TIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reservation_history_reservation (reservation_id, created_at, id),
    INDEX idx_reservation_history_actor (actor_id, created_at, id),
    CONSTRAINT fk_reservation_history_reservation FOREIGN KEY (reservation_id)
        REFERENCES reservations(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_reservation_history_actor FOREIGN KEY (actor_id)
        REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_reservation_history_status CHECK (old_status IS NOT NULL OR new_status IS NOT NULL)
) ENGINE=InnoDB;
