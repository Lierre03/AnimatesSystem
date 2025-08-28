-- Ensure users.role includes 'cashier' so new accounts can be saved correctly
-- Safe alter that preserves existing values

ALTER TABLE `users`
  MODIFY `role` ENUM('admin','staff','cashier','customer') NOT NULL DEFAULT 'staff';

-- Optionally normalize any legacy cashier staff-roles to top-level cashier
-- Uncomment if you previously stored cashier under staff_role with role 'staff'
-- UPDATE `users` SET `role` = 'cashier' WHERE `role` = 'staff' AND (COALESCE(`staff_role`, '') = 'cashier');


