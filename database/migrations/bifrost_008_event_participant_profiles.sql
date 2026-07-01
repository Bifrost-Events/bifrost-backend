-- Rename auth_participant_profiles → event_participant_profiles (generisk event-domene).
-- Idempotent: hopper over hvis allerede rename't.

SET NAMES utf8mb4;

SET @has_old = (
    SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'auth_participant_profiles'
);
SET @has_new = (
    SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'event_participant_profiles'
);

SET @sql = IF(
    @has_old > 0 AND @has_new = 0,
    'RENAME TABLE auth_participant_profiles TO event_participant_profiles',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Opprett direkte på nye miljøer som ikke hadde auth_participant_profiles
CREATE TABLE IF NOT EXISTS event_participant_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    auth_user_id INT NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    display_name VARCHAR(255) NULL,
    club_name VARCHAR(255) NULL,
    class_id INT UNSIGNED NULL COMMENT 'event_classes.id when class table exists',
    external_member_id VARCHAR(128) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_event_participant_profiles_user_tenant (auth_user_id, tenant_id),
    KEY idx_event_participant_profiles_tenant (tenant_id),
    CONSTRAINT fk_event_participant_profiles_user
        FOREIGN KEY (auth_user_id) REFERENCES auth_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_event_participant_profiles_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
