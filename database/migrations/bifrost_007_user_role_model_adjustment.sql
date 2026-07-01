-- User/role model: global auth_users, auth_system_roles, auth_tenant_admin_access, auth_participant_profiles.
-- Maps conceptual model (users, system_roles, tenant_admin_access, participant_profiles) to auth_* prefix.
-- See reference/auth-design.md and reference/database-naming.md.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- auth_users: first_registered_tenant_id, name, last_login_at
-- ---------------------------------------------------------------------------

SET @db = DATABASE();

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'auth_users' AND COLUMN_NAME = 'first_registered_tenant_id') = 0,
    'ALTER TABLE auth_users ADD COLUMN first_registered_tenant_id BIGINT UNSIGNED NULL COMMENT ''Origin tenant at first registration (historical)'' AFTER is_active',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'auth_users' AND COLUMN_NAME = 'name') = 0,
    'ALTER TABLE auth_users ADD COLUMN name VARCHAR(255) NULL AFTER last_name',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'auth_users' AND COLUMN_NAME = 'last_login_at') = 0,
    'ALTER TABLE auth_users ADD COLUMN last_login_at TIMESTAMP NULL AFTER updated_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE auth_users
SET name = TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')))
WHERE (name IS NULL OR TRIM(name) = '')
  AND (TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) <> '');

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'auth_users' AND CONSTRAINT_NAME = 'fk_auth_users_first_registered_tenant') = 0,
    'ALTER TABLE auth_users ADD CONSTRAINT fk_auth_users_first_registered_tenant FOREIGN KEY (first_registered_tenant_id) REFERENCES tenants(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- auth_system_roles (SystemAdmin — Bifrost platform)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS auth_system_roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    auth_user_id INT NOT NULL,
    role VARCHAR(64) NOT NULL COMMENT 'SystemAdmin',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_auth_system_roles_user_role (auth_user_id, role),
    KEY idx_auth_system_roles_user (auth_user_id),
    CONSTRAINT fk_auth_system_roles_user
        FOREIGN KEY (auth_user_id) REFERENCES auth_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- auth_participant_profiles (deltaker per tenant — renamed to event_* in bifrost_008)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS auth_participant_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    auth_user_id INT NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    display_name VARCHAR(255) NULL,
    club_name VARCHAR(255) NULL,
    class_id INT UNSIGNED NULL COMMENT 'event_classes.id when class table exists',
    external_member_id VARCHAR(128) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_auth_participant_profiles_user_tenant (auth_user_id, tenant_id),
    KEY idx_auth_participant_profiles_tenant (tenant_id),
    CONSTRAINT fk_auth_participant_profiles_user
        FOREIGN KEY (auth_user_id) REFERENCES auth_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_auth_participant_profiles_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- auth_tenant_admin_access: migrate legacy rows, normalize column names/roles
-- ---------------------------------------------------------------------------

INSERT INTO auth_system_roles (auth_user_id, role)
SELECT ata.auth_user_id, 'SystemAdmin'
FROM auth_tenant_admin_access ata
WHERE ata.tenant_id = 0
  AND ata.role_key IN ('system_admin', 'SystemAdmin')
ON DUPLICATE KEY UPDATE created_at = auth_system_roles.created_at;

DELETE FROM auth_tenant_admin_access
WHERE tenant_id = 0
   OR role_key IN ('system_admin', 'SystemAdmin', 'organizer', 'participant', 'Organizer', 'Participant');

UPDATE auth_tenant_admin_access
SET role_key = 'CupAdmin'
WHERE role_key = 'cup_admin';

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'auth_tenant_admin_access' AND COLUMN_NAME = 'role') = 0
    AND (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'auth_tenant_admin_access' AND COLUMN_NAME = 'role_key') > 0,
    'ALTER TABLE auth_tenant_admin_access CHANGE COLUMN role_key role VARCHAR(64) NOT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'auth_tenant_admin_access' AND COLUMN_NAME = 'created_at') = 0
    AND (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'auth_tenant_admin_access' AND COLUMN_NAME = 'granted_at') > 0,
    'ALTER TABLE auth_tenant_admin_access CHANGE COLUMN granted_at created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DELETE FROM auth_tenant_admin_access WHERE tenant_id = 0;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'auth_tenant_admin_access' AND CONSTRAINT_NAME = 'fk_auth_tenant_admin_access_tenant') = 0,
    'ALTER TABLE auth_tenant_admin_access ADD CONSTRAINT fk_auth_tenant_admin_access_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
