-- Brukerprofil (telefon, navn, avtaler) for deltaker-registrering og onboarding.
-- Tilsvarer jaktfelt v2_010_user_profiles.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS jaktfelt_user_profiles (
    user_id VARCHAR(50) NOT NULL PRIMARY KEY COMMENT 'auth_users.id som streng',
    phone VARCHAR(20) NULL,
    first_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    date_of_birth DATE NULL,
    user_agreement_version VARCHAR(50) NULL,
    user_agreement_accepted_at DATETIME NULL,
    organizer_agreement_version VARCHAR(50) NULL,
    organizer_agreement_accepted_at DATETIME NULL,
    profile_note TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
