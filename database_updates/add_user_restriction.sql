-- Add user restriction feature
-- Run this SQL in phpMyAdmin on BOTH localhost and production databases

-- Add is_restricted column to users table
ALTER TABLE `users`
ADD COLUMN `is_restricted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'User restricted from login (1 = restricted, 0 = not restricted)'
AFTER `status`;

-- Add index for faster queries
ALTER TABLE `users`
ADD INDEX `idx_is_restricted` (`is_restricted`);

-- Update description:
-- is_restricted = 0 : User can login normally
-- is_restricted = 1 : User is restricted from logging in
