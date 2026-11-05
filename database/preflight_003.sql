-- Read-only diagnostics for an Etap 2 database. Select the target database first.
-- Run with application writes paused. Resolve every nonzero violation count.
SELECT DATABASE() AS target_database, VERSION() AS server_version,
       @@sql_mode AS sql_mode, @@foreign_key_checks AS foreign_key_checks;

SELECT table_name, engine, table_collation
FROM information_schema.tables
WHERE table_schema = DATABASE()
ORDER BY table_name;

-- Any returned object means 003 has already started or the schema has diverged.
SELECT table_name, column_name
FROM information_schema.columns
WHERE table_schema = DATABASE() AND (
    (table_name = 'companies' AND column_name IN
        ('short_description','description','phone','email','city','address','website','logo_path','banner_path'))
    OR (table_name = 'employee_services' AND column_name = 'active')
    OR (table_name = 'reservations' AND column_name = 'price_snapshot')
    OR table_name IN ('employee_time_off','employee_invitations','admin_audit_logs')
)
ORDER BY table_name, ordinal_position;

SELECT 'services_invalid_values' AS validation, COUNT(*) AS violations
FROM services WHERE duration_minutes = 0 OR price < 0 OR active NOT IN (0,1)
UNION ALL
SELECT 'services_missing_parent', COUNT(*) FROM services s
LEFT JOIN companies c ON c.id = s.company_id
LEFT JOIN service_categories sc ON sc.id = s.category_id
WHERE c.id IS NULL OR sc.id IS NULL
UNION ALL
SELECT 'employees_missing_membership', COUNT(*) FROM employees e
LEFT JOIN company_memberships cm ON cm.user_id = e.user_id AND cm.company_id = e.company_id
WHERE cm.user_id IS NULL
UNION ALL
SELECT 'memberships_missing_parent', COUNT(*) FROM company_memberships cm
LEFT JOIN users u ON u.id = cm.user_id
LEFT JOIN companies c ON c.id = cm.company_id
WHERE u.id IS NULL OR c.id IS NULL
UNION ALL
SELECT 'employee_services_cross_company_or_orphan', COUNT(*) FROM employee_services es
LEFT JOIN employees e ON e.id = es.employee_id
LEFT JOIN services s ON s.id = es.service_id
WHERE e.id IS NULL OR s.id IS NULL OR es.company_id <> e.company_id OR es.company_id <> s.company_id
UNION ALL
SELECT 'availability_invalid_or_orphan', COUNT(*) FROM employee_availability a
LEFT JOIN employees e ON e.id = a.employee_id
WHERE e.id IS NULL OR a.day_of_week NOT BETWEEN 1 AND 7
   OR a.start_time < '00:00:00' OR a.end_time > '24:00:00' OR a.end_time <= a.start_time
UNION ALL
SELECT 'availability_overlaps', COUNT(*) FROM employee_availability a
JOIN employee_availability b ON a.employee_id = b.employee_id
    AND a.day_of_week = b.day_of_week AND a.id < b.id
    AND a.start_time < b.end_time AND b.start_time < a.end_time
UNION ALL
SELECT 'reservations_invalid_or_orphan', COUNT(*) FROM reservations r
LEFT JOIN users u ON u.id = r.user_id
LEFT JOIN employee_services es ON es.employee_id = r.employee_id AND es.service_id = r.service_id
WHERE u.id IS NULL OR es.employee_id IS NULL OR r.end_time <= r.start_time
    OR r.start_time < '00:00:00' OR r.end_time > '24:00:00'
    OR r.reservation_date < '1000-01-01'
    OR r.status NOT IN ('pending','confirmed','completed','cancelled')
UNION ALL
SELECT 'active_reservation_overlaps', COUNT(*) FROM reservations a
JOIN reservations b ON a.employee_id = b.employee_id AND a.reservation_date = b.reservation_date
    AND a.id < b.id AND a.start_time < b.end_time AND b.start_time < a.end_time
WHERE a.status IN ('pending','confirmed') AND b.status IN ('pending','confirmed')
UNION ALL
SELECT 'users_invalid_role_or_active', COUNT(*) FROM users
WHERE role NOT IN ('client','employee','admin') OR active NOT IN (0,1)
UNION ALL
SELECT 'companies_invalid_status_or_active', COUNT(*) FROM companies
WHERE onboarding_status NOT IN ('pending','ready') OR active NOT IN (0,1)
UNION ALL
SELECT 'memberships_invalid_role_or_active', COUNT(*) FROM company_memberships
WHERE role NOT IN ('owner','employee') OR active NOT IN (0,1)
UNION ALL
SELECT 'employees_invalid_active', COUNT(*) FROM employees WHERE active NOT IN (0,1)
UNION ALL
SELECT 'categories_invalid_active', COUNT(*) FROM service_categories WHERE active NOT IN (0,1);

-- Counts only: do not expose account addresses or credential hashes in diagnostics.
SELECT COUNT(*) AS normalized_email_collision_groups
FROM (SELECT LOWER(TRIM(email)) FROM users GROUP BY LOWER(TRIM(email)) HAVING COUNT(*) > 1) collisions;

SELECT 'users' AS table_name, COUNT(*) AS rows_before FROM users
UNION ALL SELECT 'companies', COUNT(*) FROM companies
UNION ALL SELECT 'company_memberships', COUNT(*) FROM company_memberships
UNION ALL SELECT 'service_categories', COUNT(*) FROM service_categories
UNION ALL SELECT 'services', COUNT(*) FROM services
UNION ALL SELECT 'employees', COUNT(*) FROM employees
UNION ALL SELECT 'employee_services', COUNT(*) FROM employee_services
UNION ALL SELECT 'employee_availability', COUNT(*) FROM employee_availability
UNION ALL SELECT 'reservations', COUNT(*) FROM reservations
UNION ALL SELECT 'auth_limits', COUNT(*) FROM auth_limits;
