-- Backfill organizations + organization_members fra jaktfelt_organizers_v2 (jaktfeltnamdalen).
-- Krever: bifrost_009, tenants (namdal), auth_users.
-- Kjører bare INSERT når jaktfelt_organizers_v2 finnes (idempotent).

SET NAMES utf8mb4;

-- Unik per cup + legacy id
SET @idx_exists = 0;
SELECT COUNT(*) INTO @idx_exists
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'organizations'
  AND INDEX_NAME = 'uq_organizations_tenant_legacy_jaktfelt';

SET @sql = IF(
    @idx_exists = 0,
    'ALTER TABLE organizations ADD UNIQUE KEY uq_organizations_tenant_legacy_jaktfelt (tenant_id, legacy_jaktfelt_organizer_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Sjekk kilde-tabell
SET @has_source = 0;
SELECT COUNT(*) INTO @has_source
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jaktfelt_organizers_v2';

SET @has_districts = 0;
SELECT COUNT(*) INTO @has_districts
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jaktfelt_organizers_v2' AND COLUMN_NAME = 'districts';

SET @has_org_number = 0;
SELECT COUNT(*) INTO @has_org_number
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jaktfelt_organizers_v2' AND COLUMN_NAME = 'organization_number';

SET @has_postal = 0;
SELECT COUNT(*) INTO @has_postal
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jaktfelt_organizers_v2' AND COLUMN_NAME = 'postal_code';

SET @has_competitions = 0;
SELECT COUNT(*) INTO @has_competitions
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jaktfelt_competitions';

-- Full import (standard jaktfeltnamdalen-prod)
SET @sql = IF(
    @has_source > 0 AND @has_districts > 0 AND @has_org_number > 0 AND @has_postal > 0 AND @has_competitions > 0,
    'INSERT INTO organizations (
        tenant_id, legacy_jaktfelt_organizer_id, name, organization_number, organization_type,
        contact_person, email, phone, postal_code, city, districts_json, status, created_at, updated_at
    )
    SELECT
        COALESCE(tmap.tenant_id, (SELECT id FROM tenants WHERE slug = ''namdal'' AND tenant_type = ''cup'' LIMIT 1)),
        jo.id,
        jo.name,
        NULLIF(TRIM(jo.organization_number), ''''),
        COALESCE(NULLIF(TRIM(jo.organization_type), ''''), ''skytterlag''),
        NULLIF(TRIM(jo.contact_person), ''''),
        NULLIF(TRIM(jo.email), ''''),
        NULLIF(TRIM(jo.phone), ''''),
        NULLIF(TRIM(jo.postal_code), ''''),
        NULLIF(TRIM(jo.city), ''''),
        CASE
            WHEN jo.districts IS NULL OR TRIM(jo.districts) = '''' THEN NULL
            WHEN JSON_VALID(jo.districts) THEN jo.districts
            ELSE NULL
        END,
        ''active'',
        jo.created_at,
        jo.updated_at
    FROM jaktfelt_organizers_v2 jo
    LEFT JOIN (
        SELECT jc.organizer_id, MIN(tlb.tenant_id) AS tenant_id
        FROM jaktfelt_competitions jc
        INNER JOIN tenant_legacy_bindings tlb
            ON tlb.legacy_table = ''jaktfelt_seasons'' AND tlb.legacy_id = jc.season_id
        WHERE jc.organizer_id IS NOT NULL
        GROUP BY jc.organizer_id
    ) tmap ON tmap.organizer_id = jo.id
    WHERE COALESCE(tmap.tenant_id, (SELECT id FROM tenants WHERE slug = ''namdal'' AND tenant_type = ''cup'' LIMIT 1)) IS NOT NULL
      AND NOT EXISTS (
        SELECT 1 FROM organizations o
        WHERE o.legacy_jaktfelt_organizer_id = jo.id
          AND o.tenant_id = COALESCE(tmap.tenant_id, (SELECT id FROM tenants WHERE slug = ''namdal'' AND tenant_type = ''cup'' LIMIT 1))
    )',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Uten stevner-kobling (alle til namdal-cup)
SET @sql = IF(
    @has_source > 0 AND @has_districts > 0 AND @has_org_number > 0 AND @has_postal > 0 AND @has_competitions = 0,
    'INSERT INTO organizations (
        tenant_id, legacy_jaktfelt_organizer_id, name, organization_number, organization_type,
        contact_person, email, phone, postal_code, city, districts_json, status, created_at, updated_at
    )
    SELECT
        (SELECT id FROM tenants WHERE slug = ''namdal'' AND tenant_type = ''cup'' LIMIT 1),
        jo.id,
        jo.name,
        NULLIF(TRIM(jo.organization_number), ''''),
        COALESCE(NULLIF(TRIM(jo.organization_type), ''''), ''skytterlag''),
        NULLIF(TRIM(jo.contact_person), ''''),
        NULLIF(TRIM(jo.email), ''''),
        NULLIF(TRIM(jo.phone), ''''),
        NULLIF(TRIM(jo.postal_code), ''''),
        NULLIF(TRIM(jo.city), ''''),
        CASE
            WHEN jo.districts IS NULL OR TRIM(jo.districts) = '''' THEN NULL
            WHEN JSON_VALID(jo.districts) THEN jo.districts
            ELSE NULL
        END,
        ''active'',
        jo.created_at,
        jo.updated_at
    FROM jaktfelt_organizers_v2 jo
    WHERE (SELECT id FROM tenants WHERE slug = ''namdal'' AND tenant_type = ''cup'' LIMIT 1) IS NOT NULL
      AND NOT EXISTS (
        SELECT 1 FROM organizations o
        WHERE o.legacy_jaktfelt_organizer_id = jo.id
          AND o.tenant_id = (SELECT id FROM tenants WHERE slug = ''namdal'' AND tenant_type = ''cup'' LIMIT 1)
    )',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Minimal import (eldre jaktfelt-skjema)
SET @sql = IF(
    @has_source > 0 AND NOT (@has_districts > 0 AND @has_org_number > 0 AND @has_postal > 0),
    'INSERT INTO organizations (
        tenant_id, legacy_jaktfelt_organizer_id, name, organization_type,
        contact_person, email, phone, status, created_at, updated_at
    )
    SELECT
        (SELECT id FROM tenants WHERE slug = ''namdal'' AND tenant_type = ''cup'' LIMIT 1),
        jo.id,
        jo.name,
        COALESCE(NULLIF(TRIM(jo.organization_type), ''''), ''skytterlag''),
        NULLIF(TRIM(jo.contact_person), ''''),
        NULLIF(TRIM(jo.email), ''''),
        NULLIF(TRIM(jo.phone), ''''),
        ''active'',
        jo.created_at,
        jo.updated_at
    FROM jaktfelt_organizers_v2 jo
    WHERE (SELECT id FROM tenants WHERE slug = ''namdal'' AND tenant_type = ''cup'' LIMIT 1) IS NOT NULL
      AND NOT EXISTS (
        SELECT 1 FROM organizations o WHERE o.legacy_jaktfelt_organizer_id = jo.id
    )',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Medlemmer
SET @has_members = 0;
SELECT COUNT(*) INTO @has_members
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jaktfelt_organizer_members';

SET @sql = IF(
    @has_members > 0,
    'INSERT INTO organization_members (organization_id, auth_user_id, role, created_at)
    SELECT o.id, m.user_id, m.role, m.created_at
    FROM jaktfelt_organizer_members m
    INNER JOIN organizations o ON o.legacy_jaktfelt_organizer_id = m.organizer_id
    WHERE NOT EXISTS (
        SELECT 1 FROM organization_members om
        WHERE om.organization_id = o.id AND om.auth_user_id = m.user_id
    )',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Legacy binding
SET @has_bindings = 0;
SELECT COUNT(*) INTO @has_bindings
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_legacy_bindings';

SET @sql = IF(
    @has_source > 0 AND @has_bindings > 0,
    'INSERT INTO tenant_legacy_bindings (tenant_id, legacy_table, legacy_id, note)
    SELECT o.tenant_id, ''jaktfelt_organizers_v2'', o.legacy_jaktfelt_organizer_id,
           CONCAT(''organizations.id='', o.id)
    FROM organizations o
    WHERE o.legacy_jaktfelt_organizer_id IS NOT NULL
    ON DUPLICATE KEY UPDATE tenant_id = VALUES(tenant_id), note = VALUES(note)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
