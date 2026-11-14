-- Wynik obu zapytań musi być pusty przed migracją 004.
SELECT r.id, r.employee_id, r.reservation_date, r.start_time, r.end_time
FROM reservations r
WHERE r.end_time <= r.start_time;

SELECT a.id first_id, b.id second_id, a.employee_id, a.reservation_date
FROM reservations a
JOIN reservations b ON b.employee_id=a.employee_id AND b.reservation_date=a.reservation_date AND b.id>a.id
WHERE a.status IN ('pending','confirmed') AND b.status IN ('pending','confirmed')
  AND a.start_time < b.end_time AND a.end_time > b.start_time;
