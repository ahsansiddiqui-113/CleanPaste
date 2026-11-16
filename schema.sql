-- CloakPaste schema (MySQL 8 / MariaDB 10.4+)
CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    email         VARCHAR(255) NOT NULL UNIQUE,          -- always stored lowercase
    password_hash VARCHAR(255) NULL,
    is_pro        TINYINT(1) NOT NULL DEFAULT 0,
    plan_type     VARCHAR(50) NOT NULL DEFAULT 'free',   -- 'free', 'monthly', 'lifetime'
    customer_id   VARCHAR(255) NULL,
    license_key   VARCHAR(100) NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Billing state keyed by email, so a purchase made before signup still applies.
CREATE TABLE entitlements (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(255) NOT NULL,
    plan_type   VARCHAR(50) NOT NULL,                    -- 'monthly' | 'lifetime'
    active      TINYINT(1) NOT NULL DEFAULT 1,
    customer_id VARCHAR(255) NULL,
    license_key VARCHAR(100) NOT NULL,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email_plan (email, plan_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE saved_rules (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    user_id   INT NOT NULL,
    rule_name VARCHAR(100) NOT NULL,
    pattern   TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
