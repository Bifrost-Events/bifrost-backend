-- Bifrost tenant/platform admin access on top of auth_users.
-- Table name: auth_tenant_admin_access (see reference/database-naming.md).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS auth_tenant_admin_access (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    auth_user_id INT NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = platform-scoped role',
    role_key VARCHAR(64) NOT NULL,
    granted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_auth_tenant_admin_access (auth_user_id, tenant_id, role_key),
    KEY idx_auth_tenant_admin_access_user (auth_user_id),
    KEY idx_auth_tenant_admin_access_tenant (tenant_id),
    CONSTRAINT fk_auth_tenant_admin_access_user
        FOREIGN KEY (auth_user_id) REFERENCES auth_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
