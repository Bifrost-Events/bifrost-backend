-- Backfill Bifrost tenancy from existing jaktfeltkarusell_prod data (idempotent).

SET NAMES utf8mb4;

-- Platform tenant (Bifrost Admin / API)
INSERT INTO tenants (slug, name, tenant_type, status)
VALUES ('bifrost-admin', 'Bifrost Admin', 'platform', 'active')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    tenant_type = VALUES(tenant_type),
    status = VALUES(status);

-- Namdal cup tenant
INSERT INTO tenants (slug, name, tenant_type, status)
VALUES ('namdal', 'Namdal Jaktfeltkarusell', 'cup', 'active')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    tenant_type = VALUES(tenant_type),
    status = VALUES(status);

-- Platform domains (local dev hosts)
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

-- Namdal cup record
INSERT INTO cups (tenant_id, slug, name, status)
SELECT t.id, 'namdal-jaktfeltkarusell', 'Namdal Jaktfeltkarusell', 'active'
FROM tenants t WHERE t.slug = 'namdal'
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    status = VALUES(status);

-- Domains from jaktfelt_seasons.domain_host (production hosts)
INSERT INTO tenant_domains (tenant_id, host, purpose, is_primary)
SELECT
    (SELECT id FROM tenants WHERE slug = 'namdal' LIMIT 1),
    LOWER(TRIM(s.domain_host)),
    'public',
    1
FROM jaktfelt_seasons s
WHERE s.domain_host IS NOT NULL
  AND TRIM(s.domain_host) <> ''
GROUP BY LOWER(TRIM(s.domain_host))
ON DUPLICATE KEY UPDATE
    tenant_id = VALUES(tenant_id),
    purpose = VALUES(purpose);

-- Local dev hosts for Namdal (if not already present)
INSERT INTO tenant_domains (tenant_id, host, purpose, is_primary)
SELECT t.id, 'namdal.jaktfeltkarusell.local', 'public', 1
FROM tenants t WHERE t.slug = 'namdal'
ON DUPLICATE KEY UPDATE tenant_id = VALUES(tenant_id);

INSERT INTO tenant_domains (tenant_id, host, purpose, is_primary)
SELECT t.id, 'arrangor.namdal.jaktfeltkarusell.local', 'arrangor', 1
FROM tenants t WHERE t.slug = 'namdal'
ON DUPLICATE KEY UPDATE tenant_id = VALUES(tenant_id);

-- Legacy bindings: link Namdal tenant to jaktfelt seasons
INSERT INTO tenant_legacy_bindings (tenant_id, legacy_table, legacy_id, note)
SELECT
    (SELECT id FROM tenants WHERE slug = 'namdal' LIMIT 1),
    'jaktfelt_seasons',
    s.id,
    CONCAT('Backfill from season: ', s.name)
FROM jaktfelt_seasons s
ON DUPLICATE KEY UPDATE
    tenant_id = VALUES(tenant_id),
    note = VALUES(note);
