-- Local development seed: tenants, domains, cups (additive-safe).
--
-- Profil: jaktfeltkarusell_prod etter bifrost_001–003, auth_001, bifrost_005.
-- Bruker slug-oppslag — ingen hardkodede tenant-ID-er.
--
-- Cup-data (seasons, rounds, classes, organizations): se 001_local_greenfield_cup_data.sql
-- (kun etter php bin/console migrate --greenfield).

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Tenants
-- ---------------------------------------------------------------------------

INSERT INTO tenants (slug, name, tenant_type, status) VALUES
    ('bifrost-admin', 'Bifrost Admin', 'platform', 'active'),
    ('jaktfeltcup', 'Jaktfeltcup', 'cup', 'active'),
    ('namdal', 'Namdal Jaktfeltkarusell', 'cup', 'active')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    tenant_type = VALUES(tenant_type),
    status = VALUES(status);

-- ---------------------------------------------------------------------------
-- Domains — platform
-- ---------------------------------------------------------------------------

INSERT INTO tenant_domains (tenant_id, host, purpose, is_primary)
SELECT t.id, 'admin.bifrost.local', 'admin', 1
FROM tenants t WHERE t.slug = 'bifrost-admin'
ON DUPLICATE KEY UPDATE
    tenant_id = VALUES(tenant_id),
    purpose = VALUES(purpose),
    is_primary = VALUES(is_primary);

INSERT INTO tenant_domains (tenant_id, host, purpose, is_primary)
SELECT t.id, 'api.bifrost.local', 'api', 1
FROM tenants t WHERE t.slug = 'bifrost-admin'
ON DUPLICATE KEY UPDATE
    tenant_id = VALUES(tenant_id),
    purpose = VALUES(purpose),
    is_primary = VALUES(is_primary);

-- ---------------------------------------------------------------------------
-- Domains — Jaktfeltcup (lokal)
-- ---------------------------------------------------------------------------

INSERT INTO tenant_domains (tenant_id, host, purpose, is_primary)
SELECT t.id, 'jaktfeltcup.local', 'public', 1
FROM tenants t WHERE t.slug = 'jaktfeltcup'
ON DUPLICATE KEY UPDATE
    tenant_id = VALUES(tenant_id),
    purpose = VALUES(purpose),
    is_primary = VALUES(is_primary);

INSERT INTO tenant_domains (tenant_id, host, purpose, is_primary)
SELECT t.id, 'arrangor.jaktfeltcup.local', 'arrangor', 1
FROM tenants t WHERE t.slug = 'jaktfeltcup'
ON DUPLICATE KEY UPDATE
    tenant_id = VALUES(tenant_id),
    purpose = VALUES(purpose),
    is_primary = VALUES(is_primary);

-- ---------------------------------------------------------------------------
-- Domains — Namdal (lokal; prod-hosts kommer fra bifrost_003 / jaktfelt_seasons)
-- ---------------------------------------------------------------------------

INSERT INTO tenant_domains (tenant_id, host, purpose, is_primary)
SELECT t.id, 'namdal.jaktfeltkarusell.local', 'public', 1
FROM tenants t WHERE t.slug = 'namdal'
ON DUPLICATE KEY UPDATE
    tenant_id = VALUES(tenant_id),
    purpose = VALUES(purpose),
    is_primary = VALUES(is_primary);

INSERT INTO tenant_domains (tenant_id, host, purpose, is_primary)
SELECT t.id, 'arrangor.namdal.jaktfeltkarusell.local', 'arrangor', 1
FROM tenants t WHERE t.slug = 'namdal'
ON DUPLICATE KEY UPDATE
    tenant_id = VALUES(tenant_id),
    purpose = VALUES(purpose),
    is_primary = VALUES(is_primary);

-- ---------------------------------------------------------------------------
-- Cups
-- ---------------------------------------------------------------------------

INSERT INTO cups (tenant_id, slug, name, status)
SELECT t.id, 'jaktfeltcup', 'Jaktfeltcup', 'active'
FROM tenants t WHERE t.slug = 'jaktfeltcup'
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    status = VALUES(status);

INSERT INTO cups (tenant_id, slug, name, status)
SELECT t.id, 'namdal-jaktfeltkarusell', 'Namdal Jaktfeltkarusell', 'active'
FROM tenants t WHERE t.slug = 'namdal'
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    status = VALUES(status);
