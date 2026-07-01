-- Auth core schema (idempotent). Source: platformstandard/auth-service legacy_auth_schema.sql
-- On jaktfeltkarusell_prod these tables typically already exist from auth-service migrations.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS auth_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    date_of_birth DATE,
    address TEXT,
    postal_code VARCHAR(10),
    city VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    phone_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_test_data BOOLEAN DEFAULT FALSE,
    INDEX idx_auth_users_email (email),
    INDEX idx_auth_users_active (is_active),
    INDEX idx_auth_users_test_data (is_test_data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_test_data BOOLEAN DEFAULT FALSE,
    INDEX idx_auth_roles_test_data (is_test_data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_user_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_test_data BOOLEAN DEFAULT FALSE,
    UNIQUE KEY unique_auth_user_role (user_id, role_id),
    INDEX idx_auth_user_roles_user (user_id),
    INDEX idx_auth_user_roles_role (role_id),
    INDEX idx_auth_user_roles_test_data (is_test_data),
    CONSTRAINT fk_auth_user_roles_user FOREIGN KEY (user_id) REFERENCES auth_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_auth_user_roles_role FOREIGN KEY (role_id) REFERENCES auth_roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_user_app_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    application_id INT NOT NULL,
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_auth_user_app_role (user_id, application_id, role_id),
    CONSTRAINT fk_auth_user_app_roles_user FOREIGN KEY (user_id) REFERENCES auth_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_auth_user_app_roles_app FOREIGN KEY (application_id) REFERENCES auth_applications(id) ON DELETE CASCADE,
    CONSTRAINT fk_auth_user_app_roles_role FOREIGN KEY (role_id) REFERENCES auth_roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_application_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    application_id INT NOT NULL,
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_app_role (application_id, role_id),
    INDEX idx_application_id (application_id),
    INDEX idx_role_id (role_id),
    CONSTRAINT fk_auth_application_roles_app
        FOREIGN KEY (application_id) REFERENCES auth_applications(id) ON DELETE CASCADE,
    CONSTRAINT fk_auth_application_roles_role
        FOREIGN KEY (role_id) REFERENCES auth_roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO auth_applications (name, description) VALUES
    ('bifrost-admin', 'Bifrost platform admin UI'),
    ('bifrost-arrangor', 'Bifrost arrangørportal'),
    ('bifrost-public', 'Bifrost offentlig / deltaker');
