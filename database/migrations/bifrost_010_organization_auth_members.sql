-- Organizations: skjema-oppgradering for eldre installasjoner (greenfield 001 med user_id).

-- Ny installasjon via bifrost_009 trenger ingen endringer her.

-- Datamigrering fra jaktfelt_organizers_v2: bifrost_011_backfill_organizations_from_jaktfelt_v2.sql



SET NAMES utf8mb4;



SET @has_organizations = 0;

SELECT COUNT(*) INTO @has_organizations

FROM information_schema.TABLES

WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'organizations';



SET @has_legacy_col = 0;

SELECT COUNT(*) INTO @has_legacy_col

FROM information_schema.COLUMNS

WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'organizations' AND COLUMN_NAME = 'legacy_jaktfelt_organizer_id';



SET @sql = IF(

    @has_organizations > 0 AND @has_legacy_col = 0,

    'ALTER TABLE organizations ADD COLUMN legacy_jaktfelt_organizer_id INT NULL COMMENT ''ID fra jaktfelt_organizers_v2 ved migrering'' AFTER tenant_id',

    'SELECT 1'

);

PREPARE stmt FROM @sql;

EXECUTE stmt;

DEALLOCATE PREPARE stmt;



SET @has_legacy_idx = 0;

SELECT COUNT(*) INTO @has_legacy_idx

FROM information_schema.STATISTICS

WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'organizations' AND INDEX_NAME = 'idx_organizations_legacy_jaktfelt';



SET @sql = IF(

    @has_organizations > 0 AND @has_legacy_col > 0 AND @has_legacy_idx = 0,

    'ALTER TABLE organizations ADD KEY idx_organizations_legacy_jaktfelt (legacy_jaktfelt_organizer_id)',

    'SELECT 1'

);

PREPARE stmt FROM @sql;

EXECUTE stmt;

DEALLOCATE PREPARE stmt;



SET @has_org_members = 0;

SELECT COUNT(*) INTO @has_org_members

FROM information_schema.TABLES

WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'organization_members';



SET @has_old_fk = 0;

SELECT COUNT(*) INTO @has_old_fk

FROM information_schema.TABLE_CONSTRAINTS

WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'organization_members' AND CONSTRAINT_NAME = 'fk_organization_members_user';



SET @sql = IF(

    @has_org_members > 0 AND @has_old_fk > 0,

    'ALTER TABLE organization_members DROP FOREIGN KEY fk_organization_members_user',

    'SELECT 1'

);

PREPARE stmt FROM @sql;

EXECUTE stmt;

DEALLOCATE PREPARE stmt;



SET @has_user_id = 0;

SELECT COUNT(*) INTO @has_user_id

FROM information_schema.COLUMNS

WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'organization_members' AND COLUMN_NAME = 'user_id';



SET @has_auth_user_id = 0;

SELECT COUNT(*) INTO @has_auth_user_id

FROM information_schema.COLUMNS

WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'organization_members' AND COLUMN_NAME = 'auth_user_id';



SET @sql = IF(

    @has_org_members > 0 AND @has_user_id > 0 AND @has_auth_user_id = 0,

    'ALTER TABLE organization_members CHANGE user_id auth_user_id INT NOT NULL',

    'SELECT 1'

);

PREPARE stmt FROM @sql;

EXECUTE stmt;

DEALLOCATE PREPARE stmt;



SET @has_auth_fk = 0;

SELECT COUNT(*) INTO @has_auth_fk

FROM information_schema.TABLE_CONSTRAINTS

WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'organization_members' AND CONSTRAINT_NAME = 'fk_organization_members_auth_user';



SET @sql = IF(

    @has_org_members > 0 AND @has_auth_fk = 0 AND (@has_auth_user_id > 0 OR @has_user_id > 0),

    'ALTER TABLE organization_members ADD CONSTRAINT fk_organization_members_auth_user FOREIGN KEY (auth_user_id) REFERENCES auth_users(id) ON DELETE CASCADE',

    'SELECT 1'

);

PREPARE stmt FROM @sql;

EXECUTE stmt;

DEALLOCATE PREPARE stmt;


