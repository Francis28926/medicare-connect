-- MediCare Connect - database schema + demo data
-- Import via phpMyAdmin (Import tab) or: mysql -u root < medicare_connect.sql
-- All demo accounts use the password:  Password@123

CREATE DATABASE IF NOT EXISTS medicare_connect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE medicare_connect;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS backups, audit_logs, notifications, payments, prescription_items, prescriptions,
  medical_records, appointments, doctor_schedules, doctors, patients, specialties, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role ENUM('patient','doctor','staff','admin') NOT NULL DEFAULT 'patient',
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  phone VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,           -- bcrypt
  email_verified TINYINT(1) NOT NULL DEFAULT 0,
  verify_token VARCHAR(64) NULL,
  mfa_enabled TINYINT(1) NOT NULL DEFAULT 1,
  mfa_code_hash VARCHAR(255) NULL,
  mfa_expires DATETIME NULL,
  failed_attempts INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE specialties (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE patients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  dob DATE NULL,
  gender ENUM('female','male','other') NULL,
  blood_group VARCHAR(5) NULL,
  address VARCHAR(255) NULL,
  allergies TEXT NULL,
  chronic_conditions TEXT NULL,
  current_medications TEXT NULL,
  past_surgeries TEXT NULL,
  family_history TEXT NULL,
  smoker ENUM('no','yes','former') NULL,
  emergency_name VARCHAR(120) NULL,
  emergency_relation VARCHAR(60) NULL,
  emergency_phone VARCHAR(30) NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE doctors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  specialty_id INT NOT NULL,
  license_no VARCHAR(40) NULL,
  bio TEXT NULL,
  consultation_fee DECIMAL(10,2) NOT NULL DEFAULT 100.00,
  room VARCHAR(20) NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (specialty_id) REFERENCES specialties(id)
) ENGINE=InnoDB;

CREATE TABLE doctor_schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  doctor_id INT NOT NULL,
  weekday TINYINT NOT NULL,                      -- 1 = Monday ... 7 = Sunday
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  slot_minutes INT NOT NULL DEFAULT 30,
  FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  doctor_id INT NOT NULL,
  appt_date DATE NOT NULL,
  appt_time TIME NOT NULL,
  reason VARCHAR(255) NULL,
  status ENUM('booked','checked_in','in_consultation','completed','cancelled','no_show') NOT NULL DEFAULT 'booked',
  slot_key VARCHAR(40) NULL UNIQUE,              -- prevents double booking; NULL once cancelled
  queue_number INT NULL,
  checked_in_at DATETIME NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  reminder_sent TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(id),
  FOREIGN KEY (doctor_id) REFERENCES doctors(id),
  INDEX (appt_date, doctor_id),
  INDEX (status)
) ENGINE=InnoDB;

CREATE TABLE medical_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT NOT NULL UNIQUE,
  patient_id INT NOT NULL,
  doctor_id INT NOT NULL,
  blood_pressure VARCHAR(15) NULL,
  temperature DECIMAL(4,1) NULL,
  pulse INT NULL,
  weight_kg DECIMAL(5,1) NULL,
  symptoms TEXT NULL,
  diagnosis TEXT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (appointment_id) REFERENCES appointments(id),
  FOREIGN KEY (patient_id) REFERENCES patients(id),
  FOREIGN KEY (doctor_id) REFERENCES doctors(id)
) ENGINE=InnoDB;

CREATE TABLE prescriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT NOT NULL,
  patient_id INT NOT NULL,
  doctor_id INT NOT NULL,
  instructions TEXT NULL,
  issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (appointment_id) REFERENCES appointments(id),
  FOREIGN KEY (patient_id) REFERENCES patients(id),
  FOREIGN KEY (doctor_id) REFERENCES doctors(id)
) ENGINE=InnoDB;

CREATE TABLE prescription_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  prescription_id INT NOT NULL,
  medication VARCHAR(120) NOT NULL,
  dosage VARCHAR(80) NULL,
  frequency VARCHAR(80) NULL,
  duration VARCHAR(80) NULL,
  FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT NOT NULL UNIQUE,
  patient_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  method VARCHAR(40) NOT NULL,
  card_last4 CHAR(4) NULL,
  reference VARCHAR(40) NOT NULL,
  status ENUM('paid','refunded') NOT NULL DEFAULT 'paid',
  paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (appointment_id) REFERENCES appointments(id),
  FOREIGN KEY (patient_id) REFERENCES patients(id)
) ENGINE=InnoDB;

-- In-app inbox + simulated Email/SMS outbox (Twilio / SendGrid stand-ins)
CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  channel ENUM('inapp','email','sms') NOT NULL,
  kind VARCHAR(30) NOT NULL DEFAULT 'general',
  subject VARCHAR(160) NOT NULL,
  body TEXT NOT NULL,
  appointment_id INT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'sent',
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX (user_id, channel)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(60) NOT NULL,
  details VARCHAR(255) NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (created_at)
) ENGINE=InnoDB;

CREATE TABLE backups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(120) NOT NULL,
  size_bytes INT NOT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- DEMO DATA  (password for every account: Password@123)
-- ---------------------------------------------------------------
SET @pw = '$2y$10$G/vubVdnrf3SAu9Eb0o9Wea4GoyX7sjyaMpYdjzRWaHHpTL8V3RWG';

INSERT INTO specialties (name) VALUES
 ('General Practice'),('Pediatrics'),('Cardiology'),('Dermatology'),('Gynecology'),('Dentistry');

INSERT INTO users (id, role, full_name, email, phone, password_hash, email_verified) VALUES
 (1,'admin','System Administrator','admin@medicare.test','+233200000001',@pw,1),
 (2,'staff','Abena Mensah (Reception)','staff@medicare.test','+233200000002',@pw,1),
 (3,'doctor','Dr. Kwame Boateng','kwame@medicare.test','+233200000003',@pw,1),
 (4,'doctor','Dr. Efua Owusu','efua@medicare.test','+233200000004',@pw,1),
 (5,'doctor','Dr. Selorm Agbenyo','selorm@medicare.test','+233200000005',@pw,1),
 (6,'patient','Ama Serwaa','patient@medicare.test','+233200000006',@pw,1),
 (7,'patient','Kofi Asante','kofi@medicare.test','+233200000007',@pw,1),
 (8,'patient','Yaw Darko','yaw@medicare.test','+233200000008',@pw,1),
 (9,'patient','Akosua Frimpong','akosua@medicare.test','+233200000009',@pw,1);

INSERT INTO doctors (id, user_id, specialty_id, license_no, bio, consultation_fee, room) VALUES
 (1,3,1,'GMC-10234','Family physician with 12 years of experience in preventive care.',80.00,'Room 1'),
 (2,4,2,'GMC-20871','Pediatrician focused on child growth, vaccination and infections.',100.00,'Room 2'),
 (3,5,3,'GMC-30455','Cardiologist specialising in hypertension and heart-rhythm disorders.',150.00,'Room 3');

-- Mon-Sat clinic hours
INSERT INTO doctor_schedules (doctor_id, weekday, start_time, end_time, slot_minutes)
SELECT d.id, w.n, '08:00:00', '12:00:00', 30 FROM doctors d JOIN (SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) w;
INSERT INTO doctor_schedules (doctor_id, weekday, start_time, end_time, slot_minutes)
SELECT d.id, w.n, '14:00:00', '17:00:00', 30 FROM doctors d JOIN (SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) w;

INSERT INTO patients (id, user_id, dob, gender, blood_group, address, allergies, chronic_conditions, current_medications, past_surgeries, family_history, smoker, emergency_name, emergency_relation, emergency_phone) VALUES
 (1,6,'1994-03-12','female','O+','12 Ring Road, Accra','Penicillin','Mild asthma','Salbutamol inhaler (as needed)','None','Hypertension (father)','no','Kojo Serwaa','Brother','+233244000111'),
 (2,7,'1988-07-25','male','A+','5 Spintex Rd, Tema',NULL,NULL,NULL,NULL,NULL,'no','Esi Asante','Wife','+233244000222'),
 (3,8,'2001-11-02','male','B+','Kumasi',NULL,NULL,NULL,NULL,NULL,'no','Nana Darko','Mother','+233244000333'),
 (4,9,'1979-01-30','female','AB+','Takoradi',NULL,'Type 2 diabetes','Metformin 500mg',NULL,'Diabetes (mother)','no','Kwesi Frimpong','Husband','+233244000444');

-- Appointments: 2 completed in the past, 3 today (queue demo), 1 tomorrow (reminder demo)
INSERT INTO appointments (id, patient_id, doctor_id, appt_date, appt_time, reason, status, slot_key, queue_number, checked_in_at, started_at, completed_at, reminder_sent) VALUES
 (1,1,1,CURDATE() - INTERVAL 14 DAY,'09:00:00','Persistent cough','completed',NULL,1,NOW() - INTERVAL 14 DAY,NOW() - INTERVAL 14 DAY,NOW() - INTERVAL 14 DAY,1),
 (2,4,3,CURDATE() - INTERVAL 7 DAY,'10:30:00','Blood pressure review','completed',NULL,1,NOW() - INTERVAL 7 DAY,NOW() - INTERVAL 7 DAY,NOW() - INTERVAL 7 DAY,1),
 (3,2,1,CURDATE(),'08:30:00','Headache and fatigue','checked_in',CONCAT('1-',CURDATE(),'-08:30'),1,NOW() - INTERVAL 20 MINUTE,NULL,NULL,1),
 (4,3,1,CURDATE(),'09:00:00','Skin rash','checked_in',CONCAT('1-',CURDATE(),'-09:00'),2,NOW() - INTERVAL 10 MINUTE,NULL,NULL,1),
 (5,1,2,CURDATE(),'11:00:00','Child vaccination follow-up','booked',CONCAT('2-',CURDATE(),'-11:00'),NULL,NULL,NULL,NULL,0),
 (6,1,1,CURDATE() + INTERVAL 1 DAY,'10:00:00','Asthma check-up','booked',CONCAT('1-',CURDATE() + INTERVAL 1 DAY,'-10:00'),NULL,NULL,NULL,NULL,0);

INSERT INTO medical_records (appointment_id, patient_id, doctor_id, blood_pressure, temperature, pulse, weight_kg, symptoms, diagnosis, notes) VALUES
 (1,1,1,'118/76',37.8,82,62.5,'Dry cough for 10 days, mild wheeze','Acute bronchitis','Advised rest and fluids. Review if no improvement in 7 days.'),
 (2,4,3,'145/92',36.7,78,71.0,'Occasional dizziness','Stage 1 hypertension','Start low-dose antihypertensive; reduce salt intake; recheck in 4 weeks.');

INSERT INTO prescriptions (id, appointment_id, patient_id, doctor_id, instructions) VALUES
 (1,1,1,1,'Take after meals. Complete the full course.'),
 (2,2,4,3,'Monitor blood pressure at home daily.');
INSERT INTO prescription_items (prescription_id, medication, dosage, frequency, duration) VALUES
 (1,'Cough syrup (dextromethorphan)','10 ml','3 times daily','5 days'),
 (1,'Paracetamol','500 mg','Every 6 hours if needed','3 days'),
 (2,'Amlodipine','5 mg','Once daily','30 days');

INSERT INTO payments (appointment_id, patient_id, amount, method, card_last4, reference, status, paid_at) VALUES
 (1,1,80.00,'Card (simulated)','4242','PAY-DEMO0001','paid',NOW() - INTERVAL 14 DAY),
 (2,4,150.00,'Card (simulated)','4242','PAY-DEMO0002','paid',NOW() - INTERVAL 7 DAY);

INSERT INTO audit_logs (user_id, action, details, ip) VALUES
 (1,'system.install','Database created with demo data','127.0.0.1');
