ALTER TABLE users
  ADD COLUMN staff_role ENUM('cashier','receptionist','groomer','bather','manager') NULL DEFAULT NULL AFTER role;


