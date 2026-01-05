-- ============================================
-- PATIENTS MODULE PERFORMANCE INDEXES
-- Run these SQL queries directly on your database
-- ============================================

-- 1. Composite index for base patient queries (user_type_id, account_id, active)
-- This speeds up the main datatable query filtering
CREATE INDEX idx_users_patient_base ON users (user_type_id, account_id, active);

-- 2. Index for created_at sorting and date range filtering
CREATE INDEX idx_users_created_at ON users (created_at);

-- 3. Index for phone search
CREATE INDEX idx_users_phone ON users (phone);

-- 4. Index for memberships patient_id lookup
CREATE INDEX idx_memberships_patient_id ON memberships (patient_id);

-- 5. Composite index for memberships lookup with type filter
CREATE INDEX idx_memberships_patient_type ON memberships (patient_id, membership_type_id);

-- 6. Index for appointments patient_id (used in child record checks)
CREATE INDEX idx_appointments_patient_id ON appointments (patient_id);

-- 7. Index for packages patient_id (used in child record checks)
CREATE INDEX idx_packages_patient_id ON packages (patient_id);

-- 8. Index for package_advances patient_id (used in child record checks)
CREATE INDEX idx_package_advances_patient_id ON package_advances (patient_id);

-- ============================================
-- PATIENT PREVIEW PAGE INDEXES
-- ============================================

-- 9. Composite index for appointments in patient preview (patient_id, account_id, scheduled_date)
CREATE INDEX idx_appointments_patient_account ON appointments (patient_id, account_id, scheduled_date);

-- 10. Index for user_vouchers lookup
CREATE INDEX idx_user_vouchers_user_id ON user_vouchers (user_id);

-- 11. Index for documents in patient preview
CREATE INDEX idx_documents_user_id ON documents (user_id);

-- 12. Composite index for invoices in patient preview
CREATE INDEX idx_invoices_patient_account ON invoices (patient_id, account_id);

-- 13. Composite index for leads in patient preview
CREATE INDEX idx_leads_patient_account ON leads (patient_id, account_id);


-- ============================================
-- OPTIONAL: Check if indexes already exist before creating
-- Run these queries to check existing indexes
-- ============================================

-- Check existing indexes on users table
-- SHOW INDEX FROM users;

-- Check existing indexes on memberships table
-- SHOW INDEX FROM memberships;

-- Check existing indexes on appointments table
-- SHOW INDEX FROM appointments;


-- ============================================
-- SAFE VERSION: Only create if not exists
-- Use these if you want to avoid errors on duplicate indexes
-- ============================================

-- For MySQL 8.0+, you can use:
-- CREATE INDEX IF NOT EXISTS idx_users_patient_base ON users (user_type_id, account_id, active);

-- For older MySQL versions, use stored procedure or check manually:
/*
DROP PROCEDURE IF EXISTS create_index_if_not_exists;
DELIMITER //
CREATE PROCEDURE create_index_if_not_exists()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'idx_users_patient_base') THEN
        CREATE INDEX idx_users_patient_base ON users (user_type_id, account_id, active);
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'idx_users_created_at') THEN
        CREATE INDEX idx_users_created_at ON users (created_at);
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'idx_users_phone') THEN
        CREATE INDEX idx_users_phone ON users (phone);
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'memberships' AND index_name = 'idx_memberships_patient_id') THEN
        CREATE INDEX idx_memberships_patient_id ON memberships (patient_id);
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'memberships' AND index_name = 'idx_memberships_patient_type') THEN
        CREATE INDEX idx_memberships_patient_type ON memberships (patient_id, membership_type_id);
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'appointments' AND index_name = 'idx_appointments_patient_id') THEN
        CREATE INDEX idx_appointments_patient_id ON appointments (patient_id);
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'packages' AND index_name = 'idx_packages_patient_id') THEN
        CREATE INDEX idx_packages_patient_id ON packages (patient_id);
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'package_advances' AND index_name = 'idx_package_advances_patient_id') THEN
        CREATE INDEX idx_package_advances_patient_id ON package_advances (patient_id);
    END IF;
END //
DELIMITER ;

CALL create_index_if_not_exists();
DROP PROCEDURE IF EXISTS create_index_if_not_exists;
*/


-- ============================================
-- TO REMOVE INDEXES (if needed)
-- ============================================
/*
DROP INDEX idx_users_patient_base ON users;
DROP INDEX idx_users_created_at ON users;
DROP INDEX idx_users_phone ON users;
DROP INDEX idx_memberships_patient_id ON memberships;
DROP INDEX idx_memberships_patient_type ON memberships;
DROP INDEX idx_appointments_patient_id ON appointments;
DROP INDEX idx_appointments_patient_account ON appointments;
DROP INDEX idx_packages_patient_id ON packages;
DROP INDEX idx_package_advances_patient_id ON package_advances;
DROP INDEX idx_user_vouchers_user_id ON user_vouchers;
DROP INDEX idx_documents_user_id ON documents;
DROP INDEX idx_invoices_patient_account ON invoices;
DROP INDEX idx_leads_patient_account ON leads;
*/
