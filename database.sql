CREATE DATABASE IF NOT EXISTS herdsignal_zw CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE herdsignal_zw;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL UNIQUE,
  email VARCHAR(160) NULL,
  community VARCHAR(120) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','farmer','vet') NOT NULL DEFAULT 'farmer',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS animals (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  animal_code VARCHAR(40) NOT NULL UNIQUE,
  owner_id INT UNSIGNED NOT NULL,
  name VARCHAR(80) NULL,
  species VARCHAR(40) NOT NULL DEFAULT 'Cattle',
  breed VARCHAR(80) NULL,
  sex ENUM('Female','Male','Unknown') NOT NULL DEFAULT 'Unknown',
  date_of_birth DATE NULL,
  colour_markings VARCHAR(120) NULL,
  ear_tag VARCHAR(80) NULL,
  status ENUM('Active','Sold','Deceased','Transferred') NOT NULL DEFAULT 'Active',
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_animals_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE RESTRICT,
  INDEX idx_animals_owner (owner_id),
  INDEX idx_animals_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS health_records (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  animal_id INT UNSIGNED NOT NULL,
  record_date DATE NOT NULL,
  service_type VARCHAR(100) NOT NULL,
  details TEXT NULL,
  provider_name VARCHAR(120) NULL,
  next_due_date DATE NULL,
  entered_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_health_animal FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
  CONSTRAINT fk_health_user FOREIGN KEY (entered_by) REFERENCES users(id) ON DELETE RESTRICT,
  INDEX idx_health_date (record_date),
  INDEX idx_health_due (next_due_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reminders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_id INT UNSIGNED NOT NULL,
  animal_id INT UNSIGNED NULL,
  title VARCHAR(140) NOT NULL,
  due_date DATE NOT NULL,
  notes TEXT NULL,
  status ENUM('Pending','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  email_sent_at DATETIME NULL,
  email_last_error VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reminders_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_reminders_animal FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE SET NULL,
  INDEX idx_reminders_due (due_date, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS movement_records (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  animal_id INT UNSIGNED NOT NULL,
  movement_date DATE NOT NULL,
  origin_location VARCHAR(140) NOT NULL,
  destination_location VARCHAR(140) NOT NULL,
  movement_reason VARCHAR(120) NULL,
  document_reference VARCHAR(120) NULL,
  notes TEXT NULL,
  entered_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_movement_animal FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
  CONSTRAINT fk_movement_user FOREIGN KEY (entered_by) REFERENCES users(id) ON DELETE RESTRICT,
  INDEX idx_movement_date (movement_date)
) ENGINE=InnoDB;

-- Demo admin is created through setup_admin.php, then delete that file for safety.
