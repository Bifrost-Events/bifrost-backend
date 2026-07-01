-- Links Bifrost tenants to legacy jaktfelt_* records during incremental migration.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS tenant_legacy_bindings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    legacy_table VARCHAR(64) NOT NULL COMMENT 'e.g. jaktfelt_seasons',
    legacy_id BIGINT UNSIGNED NOT NULL,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_legacy_binding (legacy_table, legacy_id),
    KEY idx_tenant_legacy_bindings_tenant (tenant_id),
    CONSTRAINT fk_tenant_legacy_bindings_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
