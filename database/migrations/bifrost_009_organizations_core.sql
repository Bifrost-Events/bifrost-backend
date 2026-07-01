-- organizations + organization_members for Bifrost admin (arrangører per cup).
-- Tilsvarer jaktfelt_organizers_v2 + jaktfelt_organizer_members.
-- Datamigrering: bifrost_011_backfill_organizations_from_jaktfelt_v2.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS organizations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    legacy_jaktfelt_organizer_id INT NULL COMMENT 'ID fra jaktfelt_organizers_v2 ved migrering',
    name VARCHAR(200) NOT NULL,
    organization_number VARCHAR(32) NULL,
    organization_type VARCHAR(50) NOT NULL DEFAULT 'skytterlag',
    contact_person VARCHAR(100) NULL,
    email VARCHAR(100) NULL,
    phone VARCHAR(20) NULL,
    postal_code VARCHAR(16) NULL,
    city VARCHAR(128) NULL,
    districts_json JSON NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_organizations_tenant (tenant_id),
    KEY idx_organizations_name (tenant_id, name),
    KEY idx_organizations_legacy_jaktfelt (legacy_jaktfelt_organizer_id),
    CONSTRAINT fk_organizations_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organization_members (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    auth_user_id INT NOT NULL,
    role ENUM('OWNER', 'ADMIN', 'REGISTRAR', 'VIEWER') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_organization_members_org_user (organization_id, auth_user_id),
    KEY idx_organization_members_user (auth_user_id),
    CONSTRAINT fk_organization_members_org
        FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_organization_members_auth_user
        FOREIGN KEY (auth_user_id) REFERENCES auth_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
