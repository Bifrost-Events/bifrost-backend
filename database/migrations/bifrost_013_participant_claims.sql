-- Deltaker-krav (forespørsel om å overta eierskap).
-- Tilsvarer jaktfelt v2_014_participant_claims.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS jaktfelt_participant_claims (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    participant_id INT NOT NULL,
    current_owner_user_id INT NULL COMMENT 'auth_user_id til nåværende eier (kan være NULL)',
    new_owner_user_id INT NOT NULL COMMENT 'auth_user_id til den som krever',
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_at TIMESTAMP NULL,
    decided_by INT NULL COMMENT 'auth_user_id til den som godkjente/avviste',
    INDEX idx_participant (participant_id),
    INDEX idx_current_owner (current_owner_user_id),
    INDEX idx_new_owner (new_owner_user_id),
    INDEX idx_status (status),
    UNIQUE KEY uniq_pending_participant_new_owner (participant_id, new_owner_user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
