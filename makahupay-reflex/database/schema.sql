-- Import this schema into the database configured by DB_NAME in .env.

CREATE TABLE IF NOT EXISTS roles (
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(30) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT IGNORE INTO roles (id,name) VALUES (1,'Administrator'),(2,'Staff'),(3,'Therapist');

CREATE TABLE IF NOT EXISTS therapists (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_code VARCHAR(24) NOT NULL UNIQUE,
  full_name VARCHAR(160) NOT NULL,
  phone VARCHAR(40) NULL,
  address TEXT NULL,
  position VARCHAR(80) NOT NULL DEFAULT 'Therapist',
  specialization VARCHAR(190) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  availability_status ENUM('available','busy','on_break','off_duty','inactive') NOT NULL DEFAULT 'available',
  hire_date DATE NULL,
  profile_image VARCHAR(255) NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_therapist_name (full_name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id TINYINT UNSIGNED NOT NULL,
  therapist_id BIGINT UNSIGNED NULL,
  name VARCHAR(140) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id),
  CONSTRAINT fk_users_therapist FOREIGN KEY (therapist_id) REFERENCES therapists(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_code VARCHAR(24) NOT NULL UNIQUE,
  full_name VARCHAR(160) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  email VARCHAR(190) NULL,
  address TEXT NULL,
  date_of_birth DATE NULL,
  gender VARCHAR(30) NULL,
  emergency_contact VARCHAR(160) NULL,
  notes TEXT NULL,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_customers_name (full_name), INDEX idx_customers_phone (phone)
) ENGINE=InnoDB;




CREATE TABLE IF NOT EXISTS services (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_code VARCHAR(24) NOT NULL UNIQUE,
  name VARCHAR(140) NOT NULL,
  description TEXT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_services_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  appointment_code VARCHAR(30) NOT NULL UNIQUE,
  customer_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  therapist_id BIGINT UNSIGNED NOT NULL,
  appointment_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  status ENUM('pending','confirmed','checked_in','in_session','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
  payment_status ENUM('unpaid','partial','paid','refunded') NOT NULL DEFAULT 'unpaid',
  source ENUM('appointment','walk_in') NOT NULL DEFAULT 'appointment',
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_appt_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_appt_service FOREIGN KEY (service_id) REFERENCES services(id),
  CONSTRAINT fk_appt_therapist FOREIGN KEY (therapist_id) REFERENCES therapists(id),
  CONSTRAINT fk_appt_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_appt_date_status (appointment_date,status),
  INDEX idx_appt_therapist_time (therapist_id,appointment_date,start_time,end_time)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  appointment_id BIGINT UNSIGNED NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  therapist_id BIGINT UNSIGNED NOT NULL,
  session_date DATETIME NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL,
  customer_concerns TEXT NULL,
  session_notes TEXT NULL,
  recommendations TEXT NULL,
  follow_up_notes TEXT NULL,
  recorded_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_session_appt FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
  CONSTRAINT fk_session_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_session_service FOREIGN KEY (service_id) REFERENCES services(id),
  CONSTRAINT fk_session_therapist FOREIGN KEY (therapist_id) REFERENCES therapists(id),
  CONSTRAINT fk_session_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_session_date (session_date),
  UNIQUE KEY uq_session_appointment (appointment_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transaction_code VARCHAR(32) NOT NULL UNIQUE,
  customer_id BIGINT UNSIGNED NULL,
  appointment_id BIGINT UNSIGNED NULL,
  cashier_id BIGINT UNSIGNED NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  discount DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL,
  amount_received DECIMAL(10,2) NOT NULL,
  change_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  payment_method ENUM('cash','gcash','bank_transfer','other') NOT NULL,
  status ENUM('paid','voided','refunded') NOT NULL DEFAULT 'paid',
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tx_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_tx_appt FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
  CONSTRAINT fk_tx_cashier FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_tx_created (created_at), INDEX idx_tx_method (payment_method),
  UNIQUE KEY uq_transaction_appointment (appointment_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transaction_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transaction_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NULL,
  therapist_id BIGINT UNSIGNED NULL,
  description VARCHAR(180) NOT NULL,
  quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  unit_price DECIMAL(10,2) NOT NULL,
  line_total DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_item_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
  CONSTRAINT fk_item_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
  CONSTRAINT fk_item_therapist FOREIGN KEY (therapist_id) REFERENCES therapists(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transaction_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  payment_method ENUM('cash','gcash','bank_transfer','other') NOT NULL,
  reference_number VARCHAR(120) NULL,
  received_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payment_tx FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_user FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_name VARCHAR(160) NOT NULL,
  category VARCHAR(90) NULL,
  unit VARCHAR(40) NOT NULL DEFAULT 'piece',
  current_stock DECIMAL(10,2) NOT NULL DEFAULT 0,
  minimum_stock DECIMAL(10,2) NOT NULL DEFAULT 0,
  cost DECIMAL(10,2) NOT NULL DEFAULT 0,
  supplier VARCHAR(160) NULL,
  expiration_date DATE NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_inventory_name (item_name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inventory_id BIGINT UNSIGNED NOT NULL,
  movement_type ENUM('stock_in','stock_out','adjustment') NOT NULL,
  quantity DECIMAL(10,2) NOT NULL,
  reason VARCHAR(190) NULL,
  user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_move_inventory FOREIGN KEY (inventory_id) REFERENCES inventory(id),
  CONSTRAINT fk_move_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS expenses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  expense_code VARCHAR(30) NOT NULL UNIQUE,
  category ENUM('supplies','utilities','rent','salaries','maintenance','marketing','other') NOT NULL,
  description VARCHAR(220) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  expense_date DATE NOT NULL,
  payment_method ENUM('cash','gcash','bank_transfer','other') NOT NULL DEFAULT 'cash',
  recorded_by BIGINT UNSIGNED NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_expense_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_expense_date (expense_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(90) NOT NULL,
  description VARCHAR(255) NOT NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_log_created (created_at), INDEX idx_log_action (action)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS system_settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT IGNORE INTO system_settings (setting_key,setting_value) VALUES
('business_name','Makahupay Reflex & Massage'),('business_address',''),('contact_number',''),('business_email',''),('receipt_footer','Thank you for choosing Makahupay!'),('business_hours','09:00-21:00'),('currency','PHP'),('appointment_interval_minutes','30'),('booking_notice_hours','0'),('business_logo','');

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  ip_address VARCHAR(45) NULL,
  attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_attempt_window (email,ip_address,attempted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_reset_token (token_hash)
) ENGINE=InnoDB;

INSERT IGNORE INTO services (service_code,name,description,duration_minutes,price,status) VALUES
('SVC-FULLBODY','Full Body Massage','Relaxing full-body massage session',60,500.00,'active'),
('SVC-SWEDISH','Swedish Massage','Gentle Swedish massage session',60,550.00,'active'),
('SVC-DEEPTISSUE','Deep Tissue Massage','Firm pressure massage session',60,650.00,'active'),
('SVC-HILOT','Hilot','Traditional hilot massage session',60,500.00,'active'),
('SVC-FOOTREFLEX','Foot Reflexology','Foot reflexology session',45,350.00,'active'),
('SVC-BACK','Back Massage','Focused back massage session',30,300.00,'active'),
('SVC-HEADSHOULDER','Head and Shoulder Massage','Focused head and shoulder session',30,300.00,'active'),
('SVC-COMBO','Combination Massage','Combination massage and reflexology session',90,800.00,'active');
