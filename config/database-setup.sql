-- Database setup for AI Governance Platform
-- Run these commands in your MySQL/MariaDB console

CREATE DATABASE IF NOT EXISTS aigov_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'aigov_user'@'localhost' IDENTIFIED BY 'YOUR_SECURE_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON aigov_production.* TO 'aigov_user'@'localhost';
FLUSH PRIVILEGES;