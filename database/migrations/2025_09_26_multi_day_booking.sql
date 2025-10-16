-- Migration: Introduce multi-day booking columns and availability ledger
-- Run with appropriate privileges. Always BACK UP the database before executing.

START TRANSACTION;

-- 1. Rename existing columns to new semantics
ALTER TABLE bookings
    CHANGE COLUMN booking_date pickup_date DATE NOT NULL,
    CHANGE COLUMN start_time pickup_time TIME NULL,
    CHANGE COLUMN end_time return_time TIME NULL;

-- 2. Add return_date column (initially nullable for backfill)
ALTER TABLE bookings
    ADD COLUMN return_date DATE NULL AFTER pickup_date;

-- 3. Backfill return_date with existing pickup_date values
UPDATE bookings
SET return_date = pickup_date
WHERE return_date IS NULL;

-- 4. Normalise time defaults
UPDATE bookings
SET pickup_time = COALESCE(pickup_time, '09:00'),
    return_time = COALESCE(return_time, '18:00');

ALTER TABLE bookings
    MODIFY pickup_time TIME DEFAULT '09:00',
    MODIFY return_time TIME DEFAULT '18:00',
    MODIFY return_date DATE NOT NULL;

-- 5. Add generated rental_days column and constraint
ALTER TABLE bookings
    ADD COLUMN rental_days INT GENERATED ALWAYS AS (DATEDIFF(return_date, pickup_date) + 1) STORED AFTER return_time,
    ADD CONSTRAINT chk_return_after_pickup CHECK (return_date >= pickup_date);

-- 6. Recreate date-based indexes
ALTER TABLE bookings
    DROP INDEX idx_booking_date,
    ADD INDEX idx_pickup_date (pickup_date),
    ADD INDEX idx_return_date (return_date),
    ADD INDEX idx_package_range (package_id, pickup_date, return_date);

-- 7. Create equipment_availability ledger table if it does not exist
CREATE TABLE IF NOT EXISTS equipment_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_id INT NOT NULL,
    booking_id INT NULL,
    date DATE NOT NULL,
    status ENUM('reserved', 'picked_up', 'returned', 'maintenance') DEFAULT 'reserved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_package_date (package_id, date),
    INDEX idx_package_date (package_id, date),
    INDEX idx_booking (booking_id)
) ENGINE=InnoDB;

-- 8. Seed ledger rows for existing bookings
WITH RECURSIVE booking_days AS (
    SELECT 
        id AS booking_id,
        package_id,
        pickup_date,
        return_date,
        pickup_date AS current_date
    FROM bookings
    UNION ALL
    SELECT 
        booking_id,
        package_id,
        pickup_date,
        return_date,
        DATE_ADD(current_date, INTERVAL 1 DAY)
    FROM booking_days
    WHERE current_date < return_date
)
INSERT INTO equipment_availability (package_id, booking_id, date, status)
SELECT 
    package_id,
    booking_id,
    current_date,
    'reserved'
FROM booking_days
WHERE NOT EXISTS (
    SELECT 1 FROM equipment_availability ea
    WHERE ea.package_id = booking_days.package_id
      AND ea.booking_id = booking_days.booking_id
      AND ea.date = booking_days.current_date
);

COMMIT;
