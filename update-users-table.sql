-- Update users table to match the registration form
ALTER TABLE `users` 
ADD COLUMN `first_name` VARCHAR(100) DEFAULT NULL AFTER `id`,
ADD COLUMN `middle_name` VARCHAR(100) DEFAULT NULL AFTER `first_name`,
ADD COLUMN `last_name` VARCHAR(100) DEFAULT NULL AFTER `middle_name`,
ADD COLUMN `verification_token` VARCHAR(255) DEFAULT NULL AFTER `role`,
ADD COLUMN `is_verified` TINYINT(1) DEFAULT 0 AFTER `verification_token`;
-- Update existing records if any, but since it's new, maybe not needed
-- UPDATE users SET first_name = SUBSTRING_INDEX(name, ' ', 1), last_name = SUBSTRING_INDEX(name, ' ', -1) WHERE name IS NOT NULL;