-- Local admin user for Bifrost Admin (idempotent).

--

-- Krever: auth_001, bifrost_005/006, bifrost_007, tenants (001_local_tenants eller bifrost_003)

--

-- LOCAL DEV ONLY — testpassord: local-admin-change-me



SET NAMES utf8mb4;



INSERT INTO auth_users (email, password_hash, first_name, last_name, name, is_active, first_registered_tenant_id)

VALUES (

    'admin@bifrost.local',

    '$2y$10$WD3Kn4Y59/anSWYK22LQuu2QyaW1.OQsazsFrxXzLZ.SLUlGUWaoq',

    'Bifrost',

    'Admin',

    'Bifrost Admin',

    1,

    NULL

)

ON DUPLICATE KEY UPDATE

    password_hash = VALUES(password_hash),

    first_name = VALUES(first_name),

    last_name = VALUES(last_name),

    name = VALUES(name),

    is_active = VALUES(is_active),

    first_registered_tenant_id = VALUES(first_registered_tenant_id);



-- SystemAdmin (global — Bifrost plattform)

INSERT INTO auth_system_roles (auth_user_id, role)

SELECT au.id, 'SystemAdmin'

FROM auth_users au

WHERE au.email = 'admin@bifrost.local'

ON DUPLICATE KEY UPDATE created_at = auth_system_roles.created_at;



-- CupAdmin for Namdal

INSERT INTO auth_tenant_admin_access (auth_user_id, tenant_id, role)

SELECT au.id, t.id, 'CupAdmin'

FROM auth_users au

INNER JOIN tenants t ON t.slug = 'namdal'

WHERE au.email = 'admin@bifrost.local'

ON DUPLICATE KEY UPDATE created_at = auth_tenant_admin_access.created_at;



-- CupAdmin for Jaktfeltcup (lokal multi-cup-test)

INSERT INTO auth_tenant_admin_access (auth_user_id, tenant_id, role)

SELECT au.id, t.id, 'CupAdmin'

FROM auth_users au

INNER JOIN tenants t ON t.slug = 'jaktfeltcup'

WHERE au.email = 'admin@bifrost.local'

ON DUPLICATE KEY UPDATE created_at = auth_tenant_admin_access.created_at;



-- Fjern eventuelle legacy rader (system_admin på tenant_id=0)

DELETE ata FROM auth_tenant_admin_access ata

INNER JOIN auth_users au ON au.id = ata.auth_user_id

WHERE au.email = 'admin@bifrost.local'

  AND (ata.tenant_id = 0 OR ata.role IN ('system_admin', 'SystemAdmin', 'Participant', 'Organizer'));

