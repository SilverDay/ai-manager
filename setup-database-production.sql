-- Production Database setup for AI Governance Platform
-- Run these commands as MySQL root user

CREATE DATABASE IF NOT EXISTS aigov_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'aigov_user_production'@'localhost' IDENTIFIED BY 'phG5STSmaR!oVwN_';
GRANT ALL PRIVILEGES ON aigov_production.* TO 'aigov_user_production'@'localhost';
FLUSH PRIVILEGES;

-- Verify the setup
SHOW DATABASES LIKE 'aigov_production';
SELECT User, Host FROM mysql.user WHERE User = 'aigov_user_production';