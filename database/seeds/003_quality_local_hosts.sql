-- Ekstra domener for quality/staging (idempotent).
-- Lokale hosts (slatlemcup.local, namdal.jaktfeltkarusell.local) ligger i 001_local_tenants.sql.

SET NAMES utf8mb4;

INSERT INTO tenant_domains (tenant_id, host, purpose, is_primary)
SELECT t.id, 'test.jaktfeltcup.no', 'public', 0
FROM tenants t
WHERE t.slug = 'jaktfeltcup'
  AND NOT EXISTS (
    SELECT 1 FROM tenant_domains td WHERE td.host = 'test.jaktfeltcup.no'
  )
LIMIT 1;

INSERT INTO tenant_domains (tenant_id, host, purpose, is_primary)
SELECT t.id, 'staging.jaktfeltcup.no', 'public', 0
FROM tenants t
WHERE t.slug = 'jaktfeltcup'
  AND NOT EXISTS (
    SELECT 1 FROM tenant_domains td WHERE td.host = 'staging.jaktfeltcup.no'
  )
LIMIT 1;

INSERT INTO tenant_domains (tenant_id, host, purpose, is_primary)
SELECT t.id, 'test.namdal.jaktfeltkarusell.no', 'public', 0
FROM tenants t
WHERE t.slug = 'namdal'
  AND NOT EXISTS (
    SELECT 1 FROM tenant_domains td WHERE td.host = 'test.namdal.jaktfeltkarusell.no'
  )
LIMIT 1;

INSERT INTO tenant_domains (tenant_id, host, purpose, is_primary)
SELECT t.id, 'staging.namdal.jaktfeltkarusell.no', 'public', 0
FROM tenants t
WHERE t.slug = 'namdal'
  AND NOT EXISTS (
    SELECT 1 FROM tenant_domains td WHERE td.host = 'staging.namdal.jaktfeltkarusell.no'
  )
LIMIT 1;
