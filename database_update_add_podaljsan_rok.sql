-- Database modification: Add podaljsan_rok column to oddaja table
-- This column stores student-specific deadline extensions for re-submission after receiving grade 1

ALTER TABLE `oddaja` 
ADD COLUMN `podaljsan_rok` DATETIME NULL DEFAULT NULL 
AFTER `datum_oddaje`;

-- Note: This allows students to have individual deadline extensions when they receive a grade of 1
-- The extension is typically set to 7 days from the grading date

