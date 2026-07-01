-- Bifrost initial schema
-- Consolidated from jaktfeltnamdalen v2 migrations with multi-tenant model.
-- Principles: tenant_id on cup data, domains in tenant_domains, seasons as records.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- Meta
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS schema_migrations (
    migration VARCHAR(255) NOT NULL PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Tenancy
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS tenants (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(64) NOT NULL COMMENT 'Stable identifier, e.g. jaktfeltcup',
    name VARCHAR(200) NOT NULL,
    tenant_type ENUM('platform', 'cup') NOT NULL DEFAULT 'cup',
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenants_slug (slug),
    KEY idx_tenants_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_domains (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    host VARCHAR(191) NOT NULL COMMENT 'Hostname without port',
    purpose ENUM('admin', 'api', 'public', 'arrangor') NOT NULL DEFAULT 'public',
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_domains_host (host),
    KEY idx_tenant_domains_tenant (tenant_id),
    KEY idx_tenant_domains_purpose (tenant_id, purpose),
    CONSTRAINT fk_tenant_domains_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    slug VARCHAR(64) NOT NULL,
    name VARCHAR(200) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cups_tenant_slug (tenant_id, slug),
    KEY idx_cups_tenant (tenant_id),
    CONSTRAINT fk_cups_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Auth (minimal local stub — production uses external auth service)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NULL,
    first_name VARCHAR(100) NOT NULL DEFAULT '',
    last_name VARCHAR(100) NOT NULL DEFAULT '',
    phone VARCHAR(32) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    external_id VARCHAR(128) NULL COMMENT 'ID from external auth service',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_external (external_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    role_key VARCHAR(64) NOT NULL,
    name VARCHAR(120) NOT NULL,
    scope ENUM('platform', 'tenant') NOT NULL DEFAULT 'tenant',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_roles_key_scope (role_key, scope)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = platform-scoped role',
    granted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_roles_user_role_tenant (user_id, role_id, tenant_id),
    KEY idx_user_roles_user (user_id),
    KEY idx_user_roles_tenant (tenant_id),
    CONSTRAINT fk_user_roles_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Season structure
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS seasons (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    cup_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    year YEAR NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    start_date DATE NULL,
    end_date DATE NULL,
    cup_standings_mode VARCHAR(32) NOT NULL DEFAULT 'total_score'
        COMMENT 'total_score or placement_points',
    cup_placement_points_json TEXT NULL
        COMMENT 'JSON map: placement (1-based) -> cup points',
    cup_standings_competition_ids_json TEXT NULL
        COMMENT 'JSON array of competition IDs included in cup standings',
    cup_standings_count_best INT NOT NULL DEFAULT 6,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_seasons_tenant (tenant_id),
    KEY idx_seasons_cup (cup_id),
    KEY idx_seasons_year (tenant_id, year),
    KEY idx_seasons_active (tenant_id, is_active),
    CONSTRAINT fk_seasons_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_seasons_cup
        FOREIGN KEY (cup_id) REFERENCES cups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rounds (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    season_id BIGINT UNSIGNED NOT NULL,
    round_number INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    result_deadline DATE NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rounds_season_number (season_id, round_number),
    KEY idx_rounds_tenant (tenant_id),
    KEY idx_rounds_season (season_id),
    CONSTRAINT fk_rounds_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_rounds_season
        FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Organizations (arrangører)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS organizations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    organization_number VARCHAR(32) NULL,
    organization_type VARCHAR(50) NOT NULL DEFAULT 'skytterlag',
    contact_person VARCHAR(100) NULL,
    email VARCHAR(100) NULL,
    phone VARCHAR(20) NULL,
    postal_code VARCHAR(16) NULL,
    city VARCHAR(128) NULL,
    districts_json JSON NULL COMMENT 'District tags, e.g. ["namdalen","Indre Namdal"]',
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_organizations_tenant (tenant_id),
    KEY idx_organizations_name (tenant_id, name),
    CONSTRAINT fk_organizations_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organization_members (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('OWNER', 'ADMIN', 'REGISTRAR', 'VIEWER') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_organization_members_org_user (organization_id, user_id),
    KEY idx_organization_members_user (user_id),
    CONSTRAINT fk_organization_members_org
        FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_organization_members_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Competitions (stevner)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS competitions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    season_id BIGINT UNSIGNED NULL,
    round_id BIGINT UNSIGNED NULL,
    organization_id BIGINT UNSIGNED NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    scoring_mode ENUM('njff', 'dfs') NOT NULL DEFAULT 'njff',
    invitation_text TEXT NULL,
    tiebreaker_figure_order JSON NULL,
    location VARCHAR(100) NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    competition_date DATE NULL,
    registration_start DATE NULL,
    registration_end DATE NULL,
    advance_registration_enabled TINYINT(1) NOT NULL DEFAULT 1,
    max_participants INT NULL,
    shooters_per_slot INT NULL COMMENT 'Figures per slot/team',
    slot_count INT NULL COMMENT 'Number of slots/teams',
    minutes_between_slots INT NULL DEFAULT 60,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    is_locked TINYINT(1) NOT NULL DEFAULT 0,
    stevneadmin_approved TINYINT(1) NOT NULL DEFAULT 0,
    stevneadmin_approved_at DATETIME NULL,
    stevneadmin_approved_by BIGINT UNSIGNED NULL,
    offline_sync_token VARCHAR(64) NULL,
    billing_status VARCHAR(32) NOT NULL DEFAULT 'klart_til_fakturering',
    billing_reference VARCHAR(100) NULL,
    invoice_date DATE NULL,
    due_date DATE NULL,
    paid_date DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_competitions_tenant (tenant_id),
    KEY idx_competitions_season (season_id),
    KEY idx_competitions_round (round_id),
    KEY idx_competitions_organization (organization_id),
    KEY idx_competitions_date (tenant_id, competition_date),
    KEY idx_competitions_billing (billing_status),
    CONSTRAINT fk_competitions_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_competitions_season
        FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE SET NULL,
    CONSTRAINT fk_competitions_round
        FOREIGN KEY (round_id) REFERENCES rounds(id) ON DELETE SET NULL,
    CONSTRAINT fk_competitions_organization
        FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Participants
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS participants (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    owner_user_id BIGINT UNSIGNED NULL,
    owner_organization_id BIGINT UNSIGNED NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NULL,
    phone VARCHAR(20) NULL,
    club VARCHAR(200) NULL,
    source ENUM('self_registered', 'organizer_registered', 'imported') NOT NULL DEFAULT 'self_registered',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_participants_tenant (tenant_id),
    KEY idx_participants_owner_user (owner_user_id),
    KEY idx_participants_owner_org (owner_organization_id),
    KEY idx_participants_phone (phone),
    KEY idx_participants_name (last_name, first_name),
    CONSTRAINT fk_participants_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_participants_owner_user
        FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_participants_owner_org
        FOREIGN KEY (owner_organization_id) REFERENCES organizations(id) ON DELETE SET NULL
    -- Eier-regel (user ELLER org): håndheves i applikasjon — MariaDB/ProISP støtter ikke CHECK på kolonnereferanser (error 1901).
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS participant_identifiers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    participant_id BIGINT UNSIGNED NOT NULL,
    identifier_type VARCHAR(50) NOT NULL DEFAULT 'shooter_id',
    value VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_participant_identifiers_type_value (identifier_type, value),
    UNIQUE KEY uq_participant_identifiers_participant_type (participant_id, identifier_type),
    KEY idx_participant_identifiers_participant (participant_id),
    CONSTRAINT fk_participant_identifiers_participant
        FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Classes
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS classes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    public_list_mode ENUM('scoring', 'roster') NOT NULL DEFAULT 'scoring',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_classes_tenant_code (tenant_id, code),
    KEY idx_classes_tenant (tenant_id),
    CONSTRAINT fk_classes_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS participant_classes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    participant_id BIGINT UNSIGNED NOT NULL,
    class_id BIGINT UNSIGNED NOT NULL,
    from_date DATE NOT NULL,
    to_date DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_participant_classes_participant (participant_id),
    KEY idx_participant_classes_class (class_id),
    KEY idx_participant_classes_valid (participant_id, from_date, to_date),
    CONSTRAINT fk_participant_classes_participant
        FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE,
    CONSTRAINT fk_participant_classes_class
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Signups (påmelding)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS signup_slots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    competition_id BIGINT UNSIGNED NOT NULL,
    slot_number INT NOT NULL COMMENT 'Team/slot number 1, 2, 3...',
    start_time TIME NOT NULL,
    is_reserved TINYINT(1) NOT NULL DEFAULT 0,
    is_roster_locked TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Locked for signup changes',
    is_locked TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Locked for result editing',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_signup_slots_competition_slot (competition_id, slot_number),
    KEY idx_signup_slots_competition (competition_id),
    CONSTRAINT fk_signup_slots_competition
        FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS signups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slot_id BIGINT UNSIGNED NOT NULL,
    figure_number INT NOT NULL COMMENT 'Position 1..shooters_per_slot',
    participant_id BIGINT UNSIGNED NULL,
    is_reserved TINYINT(1) NOT NULL DEFAULT 0,
    registered_via ENUM('shooter_id', 'phone', 'manual', 'offline_app', 'stevneadmin') NULL,
    registered_by_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_signups_slot_figure (slot_id, figure_number),
    KEY idx_signups_slot (slot_id),
    KEY idx_signups_participant (participant_id),
    CONSTRAINT fk_signups_slot
        FOREIGN KEY (slot_id) REFERENCES signup_slots(id) ON DELETE CASCADE,
    CONSTRAINT fk_signups_participant
        FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE SET NULL,
    CONSTRAINT fk_signups_registered_by
        FOREIGN KEY (registered_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Results
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS results_raw (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    competition_id BIGINT UNSIGNED NOT NULL,
    roster_registration_key VARCHAR(96) NOT NULL
        COMMENT 'Stable key: c{id}-s{n}-f{f} or c{id}-walkin-{nr}',
    slot_number INT NOT NULL,
    figure_number INT NOT NULL,
    organization_id BIGINT UNSIGNED NULL,
    last_changed_by_user_id BIGINT UNSIGNED NULL,
    reported_shooter_id VARCHAR(100) NULL,
    reported_name VARCHAR(200) NULL,
    score DECIMAL(10, 2) NULL,
    place INT NULL,
    raw_payload JSON NULL COMMENT 'Original import data - holds/tiebreaker live here',
    is_pending TINYINT(1) NOT NULL DEFAULT 0,
    phone VARCHAR(50) NULL,
    first_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    class_id BIGINT UNSIGNED NULL,
    class_name VARCHAR(100) NULL,
    club VARCHAR(200) NULL,
    birth_date DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_results_raw_roster_key (competition_id, roster_registration_key),
    UNIQUE KEY uq_results_raw_slot_figure (competition_id, slot_number, figure_number),
    KEY idx_results_raw_competition (competition_id),
    KEY idx_results_raw_changed_by (last_changed_by_user_id),
    CONSTRAINT fk_results_raw_competition
        FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    CONSTRAINT fk_results_raw_organization
        FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
    CONSTRAINT fk_results_raw_changed_by
        FOREIGN KEY (last_changed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS results (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    competition_id BIGINT UNSIGNED NOT NULL,
    signup_slot_id BIGINT UNSIGNED NOT NULL,
    figure_number INT NOT NULL,
    participant_id BIGINT UNSIGNED NULL,
    class_id BIGINT UNSIGNED NULL,
    score_breakdown JSON NULL COMMENT 'Hold-wise score - totals computed at display time',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_results_competition_slot_figure (competition_id, signup_slot_id, figure_number),
    KEY idx_results_competition (competition_id),
    KEY idx_results_slot (signup_slot_id),
    KEY idx_results_participant (participant_id),
    KEY idx_results_class (class_id),
    CONSTRAINT fk_results_competition
        FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    CONSTRAINT fk_results_signup_slot
        FOREIGN KEY (signup_slot_id) REFERENCES signup_slots(id) ON DELETE CASCADE,
    CONSTRAINT fk_results_participant
        FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE SET NULL,
    CONSTRAINT fk_results_class
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO schema_migrations (migration) VALUES ('001_initial_bifrost_schema.sql')
ON DUPLICATE KEY UPDATE applied_at = applied_at;
