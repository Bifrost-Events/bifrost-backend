-- Rename bifrost_tenant_roles → auth_tenant_admin_access (domenebasert auth_*-prefiks).
-- Idempotent: hopper over hvis allerede rename't.

SET NAMES utf8mb4;

-- MySQL: RENAME only if source exists and target does not
SET @has_old = (
    SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'bifrost_tenant_roles'
);
SET @has_new = (
    SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'auth_tenant_admin_access'
);

SET @sql = IF(
    @has_old > 0 AND @has_new = 0,
    'RENAME TABLE bifrost_tenant_roles TO auth_tenant_admin_access',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
