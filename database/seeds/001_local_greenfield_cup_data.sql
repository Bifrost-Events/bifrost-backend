-- Greenfield-only cup demo data (seasons, rounds, classes, organizations).
--
-- Krever: php bin/console migrate --greenfield (001_initial_bifrost_schema.sql).
-- Kjør etter 001_local_tenants.sql på en ren bifrost-database.
--
-- Ikke kjør på jaktfeltkarusell_prod — bruk legacy jaktfelt_* eller fremtidige event_*-tabeller der.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Seasons (2026)
-- ---------------------------------------------------------------------------

INSERT INTO seasons (tenant_id, cup_id, name, year, is_active, start_date, end_date, cup_standings_mode, cup_standings_count_best)
SELECT t.id, c.id, 'Jaktfeltcup 2026', 2026, 1, '2026-01-01', '2026-12-31', 'total_score', 6
FROM tenants t
INNER JOIN cups c ON c.tenant_id = t.id AND c.slug = 'jaktfeltcup'
WHERE t.slug = 'jaktfeltcup'
  AND NOT EXISTS (
    SELECT 1 FROM seasons s
    WHERE s.cup_id = c.id AND s.year = 2026
  );

INSERT INTO seasons (tenant_id, cup_id, name, year, is_active, start_date, end_date, cup_standings_mode, cup_standings_count_best)
SELECT t.id, c.id, 'Namdal Jaktfeltkarusell 2026', 2026, 1, '2026-01-01', '2026-12-31', 'placement_points', 6
FROM tenants t
INNER JOIN cups c ON c.tenant_id = t.id AND c.slug = 'namdal-jaktfeltkarusell'
WHERE t.slug = 'namdal'
  AND NOT EXISTS (
    SELECT 1 FROM seasons s
    WHERE s.cup_id = c.id AND s.year = 2026
  );

-- ---------------------------------------------------------------------------
-- Rounds (4 per season)
-- ---------------------------------------------------------------------------

INSERT INTO rounds (tenant_id, season_id, round_number, name, start_date, end_date, result_deadline, is_active)
SELECT t.id, s.id, r.round_number, r.name, r.start_date, r.end_date, r.result_deadline, 1
FROM tenants t
INNER JOIN cups c ON c.tenant_id = t.id AND c.slug = 'jaktfeltcup'
INNER JOIN seasons s ON s.cup_id = c.id AND s.year = 2026
CROSS JOIN (
    SELECT 1 AS round_number, 'Runde 1' AS name, '2026-03-01' AS start_date, '2026-03-31' AS end_date, '2026-04-07' AS result_deadline
    UNION ALL SELECT 2, 'Runde 2', '2026-04-01', '2026-04-30', '2026-05-07'
    UNION ALL SELECT 3, 'Runde 3', '2026-09-01', '2026-09-30', '2026-10-07'
    UNION ALL SELECT 4, 'Runde 4', '2026-10-01', '2026-10-31', '2026-11-07'
) r
WHERE t.slug = 'jaktfeltcup'
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO rounds (tenant_id, season_id, round_number, name, start_date, end_date, result_deadline, is_active)
SELECT t.id, s.id, r.round_number, r.name, r.start_date, r.end_date, r.result_deadline, 1
FROM tenants t
INNER JOIN cups c ON c.tenant_id = t.id AND c.slug = 'namdal-jaktfeltkarusell'
INNER JOIN seasons s ON s.cup_id = c.id AND s.year = 2026
CROSS JOIN (
    SELECT 1 AS round_number, 'Runde 1' AS name, '2026-03-01' AS start_date, '2026-03-31' AS end_date, '2026-04-07' AS result_deadline
    UNION ALL SELECT 2, 'Runde 2', '2026-04-01', '2026-04-30', '2026-05-07'
    UNION ALL SELECT 3, 'Runde 3', '2026-09-01', '2026-09-30', '2026-10-07'
    UNION ALL SELECT 4, 'Runde 4', '2026-10-01', '2026-10-31', '2026-11-07'
) r
WHERE t.slug = 'namdal'
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ---------------------------------------------------------------------------
-- Classes
-- ---------------------------------------------------------------------------

INSERT INTO classes (tenant_id, code, name, sort_order, public_list_mode)
SELECT t.id, c.code, c.name, c.sort_order, c.public_list_mode
FROM tenants t
CROSS JOIN (
    SELECT 'apen_junior' AS code, 'Åpen junior' AS name, 1 AS sort_order, 'roster' AS public_list_mode
    UNION ALL SELECT 'apen_voksen', 'Åpen voksen', 2, 'roster'
    UNION ALL SELECT 'sta', 'STÅ', 3, 'scoring'
    UNION ALL SELECT 'sittende', 'SITTENDE', 4, 'scoring'
) c
WHERE t.slug IN ('jaktfeltcup', 'namdal')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    sort_order = VALUES(sort_order),
    public_list_mode = VALUES(public_list_mode);

-- ---------------------------------------------------------------------------
-- Sample organizations
-- ---------------------------------------------------------------------------

INSERT INTO organizations (tenant_id, name, organization_type, postal_code, city, districts_json, status)
SELECT t.id, 'Namdal Skytterlag', 'skytterlag', '7860', 'Skage', JSON_ARRAY('namdalen', 'Indre Namdal'), 'active'
FROM tenants t
WHERE t.slug = 'namdal'
  AND NOT EXISTS (
    SELECT 1 FROM organizations o WHERE o.tenant_id = t.id AND o.name = 'Namdal Skytterlag'
  );

INSERT INTO organizations (tenant_id, name, organization_type, postal_code, city, districts_json, status)
SELECT t.id, 'Eksempel Skytterlag', 'skytterlag', '0001', 'Oslo', JSON_ARRAY('oslo'), 'active'
FROM tenants t
WHERE t.slug = 'jaktfeltcup'
  AND NOT EXISTS (
    SELECT 1 FROM organizations o WHERE o.tenant_id = t.id AND o.name = 'Eksempel Skytterlag'
  );
