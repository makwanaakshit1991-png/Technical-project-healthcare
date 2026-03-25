-- ============================================================
--  Smart Health Record System (SHRS) — Database Setup
--  Import this file via phpMyAdmin or: mysql -u root -p < shrs_database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS shrs_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE shrs_db;

-- ============================================================
--  TABLE 1: users
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('patient','clinician','pharmacist','insurer','admin') NOT NULL,
  institution VARCHAR(200),
  phone VARCHAR(20),
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
--  TABLE 2: patients
-- ============================================================
CREATE TABLE IF NOT EXISTS patients (
  patient_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  date_of_birth DATE,
  gender ENUM('male','female','other'),
  blood_group VARCHAR(5),
  address TEXT,
  emergency_contact VARCHAR(150),
  emergency_phone VARCHAR(20),
  allergies TEXT,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ============================================================
--  TABLE 3: health_records
-- ============================================================
CREATE TABLE IF NOT EXISTS health_records (
  record_id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  clinician_id INT NOT NULL,
  record_type ENUM('consultation','lab_result','prescription','imaging','discharge_summary','immunization') NOT NULL,
  title VARCHAR(255),
  description TEXT,
  diagnosis TEXT,
  icd_code VARCHAR(20),
  fhir_resource_hash VARCHAR(64),
  is_sensitive TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
  FOREIGN KEY (clinician_id) REFERENCES users(user_id)
);

-- ============================================================
--  TABLE 4: vital_signs
-- ============================================================
CREATE TABLE IF NOT EXISTS vital_signs (
  vital_id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  recorded_by INT NOT NULL,
  heart_rate INT,
  respiratory_rate INT,
  spo2 DECIMAL(5,2),
  mean_arterial_pressure DECIMAL(5,2),
  temperature DECIMAL(4,1),
  blood_glucose DECIMAL(6,2),
  bmi DECIMAL(5,2),
  recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
  FOREIGN KEY (recorded_by) REFERENCES users(user_id)
);

-- ============================================================
--  TABLE 5: lab_results
-- ============================================================
CREATE TABLE IF NOT EXISTS lab_results (
  lab_id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  ordered_by INT NOT NULL,
  test_name VARCHAR(200),
  result_value VARCHAR(100),
  unit VARCHAR(50),
  normal_range VARCHAR(100),
  is_abnormal TINYINT(1) DEFAULT 0,
  lab_name VARCHAR(200),
  result_date DATE,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
  FOREIGN KEY (ordered_by) REFERENCES users(user_id)
);

-- ============================================================
--  TABLE 6: prescriptions
-- ============================================================
CREATE TABLE IF NOT EXISTS prescriptions (
  prescription_id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  prescribing_clinician_id INT NOT NULL,
  medication_name VARCHAR(200) NOT NULL,
  dosage VARCHAR(100),
  frequency VARCHAR(100),
  duration_days INT,
  instructions TEXT,
  dispensed_by INT,
  dispensed_at TIMESTAMP NULL,
  status ENUM('active','dispensed','cancelled','expired') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
  FOREIGN KEY (prescribing_clinician_id) REFERENCES users(user_id),
  FOREIGN KEY (dispensed_by) REFERENCES users(user_id)
);

-- ============================================================
--  TABLE 7: appointments
-- ============================================================
CREATE TABLE IF NOT EXISTS appointments (
  appointment_id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  clinician_id INT NOT NULL,
  appointment_date DATE NOT NULL,
  appointment_time TIME NOT NULL,
  purpose TEXT,
  status ENUM('scheduled','completed','cancelled','no_show') DEFAULT 'scheduled',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
  FOREIGN KEY (clinician_id) REFERENCES users(user_id)
);

-- ============================================================
--  TABLE 8: consents
-- ============================================================
CREATE TABLE IF NOT EXISTS consents (
  consent_id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  granted_to_user_id INT NOT NULL,
  record_type VARCHAR(100),
  institution VARCHAR(200),
  purpose TEXT,
  is_active TINYINT(1) DEFAULT 1,
  is_sensitive_consent TINYINT(1) DEFAULT 0,
  granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NULL,
  revoked_at TIMESTAMP NULL,
  FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
  FOREIGN KEY (granted_to_user_id) REFERENCES users(user_id)
);

-- ============================================================
--  TABLE 9: insurance_claims
-- ============================================================
CREATE TABLE IF NOT EXISTS insurance_claims (
  claim_id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  insurer_id INT NOT NULL,
  record_id INT,
  claim_amount DECIMAL(10,2),
  description TEXT,
  status ENUM('submitted','under_review','approved','rejected') DEFAULT 'submitted',
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reviewed_at TIMESTAMP NULL,
  remarks TEXT,
  FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
  FOREIGN KEY (insurer_id) REFERENCES users(user_id),
  FOREIGN KEY (record_id) REFERENCES health_records(record_id)
);

-- ============================================================
--  TABLE 10: messages
-- ============================================================
CREATE TABLE IF NOT EXISTS messages (
  message_id INT AUTO_INCREMENT PRIMARY KEY,
  sender_id INT NOT NULL,
  receiver_id INT NOT NULL,
  subject VARCHAR(255),
  body TEXT NOT NULL,
  is_read TINYINT(1) DEFAULT 0,
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sender_id) REFERENCES users(user_id),
  FOREIGN KEY (receiver_id) REFERENCES users(user_id)
);

-- ============================================================
--  TABLE 11: ai_predictions
-- ============================================================
CREATE TABLE IF NOT EXISTS ai_predictions (
  prediction_id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  model_type ENUM('diabetes_risk','hypertension_risk','icu_deterioration') NOT NULL,
  risk_score DECIMAL(5,4),
  risk_level ENUM('low','moderate','high') NOT NULL,
  feature_summary TEXT,
  recommendation TEXT,
  is_machine_generated TINYINT(1) DEFAULT 1,
  generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(patient_id)
);

-- ============================================================
--  TABLE 12: blockchain_audit_log
-- ============================================================
CREATE TABLE IF NOT EXISTS blockchain_audit_log (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  transaction_type ENUM('RecordCreate','RecordUpdate','AccessRequest','ConsentUpdate','LoginEvent') NOT NULL,
  actor_user_id INT NOT NULL,
  affected_record_id INT,
  resource_hash VARCHAR(64) NOT NULL,
  previous_hash VARCHAR(64),
  block_hash VARCHAR(64) NOT NULL,
  timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ip_address VARCHAR(45),
  details TEXT,
  FOREIGN KEY (actor_user_id) REFERENCES users(user_id)
);

-- ============================================================
--  TABLE 13: role_permissions
-- ============================================================
CREATE TABLE IF NOT EXISTS role_permissions (
  permission_id INT AUTO_INCREMENT PRIMARY KEY,
  role ENUM('patient','clinician','pharmacist','insurer','admin') NOT NULL,
  module VARCHAR(100) NOT NULL,
  can_read TINYINT(1) DEFAULT 0,
  can_write TINYINT(1) DEFAULT 0,
  can_delete TINYINT(1) DEFAULT 0,
  can_approve TINYINT(1) DEFAULT 0
);

-- ============================================================
--  TABLE 14: system_logs
-- ============================================================
CREATE TABLE IF NOT EXISTS system_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action VARCHAR(255),
  module VARCHAR(100),
  status ENUM('success','failure','warning') DEFAULT 'success',
  ip_address VARCHAR(45),
  user_agent TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- ============================================================
--  SAMPLE DATA: users
--  Passwords (bcrypt of the literal passwords):
--    Patient@123, Doctor@123, Pharma@123, Insurer@123, Admin@123
-- ============================================================
INSERT INTO users (full_name, email, password_hash, role, institution, phone) VALUES
('John Patient',   'patient@shrs.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient',    'N/A',                  '555-0101'),
('Jane Smith',     'patient2@shrs.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient',    'N/A',                  '555-0102'),
('Dr. Alice Brown','doctor@shrs.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'clinician',  'City General Hospital','555-0201'),
('Dr. Bob Green',  'doctor2@shrs.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'clinician',  'Metro Health Clinic',  '555-0202'),
('Phil Pharma',    'pharma@shrs.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pharmacist', 'MedPlus Pharmacy',     '555-0301'),
('Ivan Insurer',   'insurer@shrs.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'insurer',   'HealthFirst Insurance','555-0401'),
('Adam Admin',     'admin@shrs.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',      'SHRS HQ',              '555-0501');

-- NOTE: The above hashes are placeholders. The setup script (setup_passwords.php) will
-- regenerate correct bcrypt hashes for all test accounts on first run.
-- Alternatively, run: php /path/to/shrs/setup_passwords.php

-- ============================================================
--  SAMPLE DATA: patients
-- ============================================================
INSERT INTO patients (user_id, date_of_birth, gender, blood_group, address, emergency_contact, emergency_phone, allergies) VALUES
(1, '1985-06-15', 'male',   'O+', '123 Main St, Springfield', 'Mary Patient', '555-0110', 'Penicillin, Aspirin'),
(2, '1992-11-23', 'female', 'A-', '456 Oak Ave, Shelbyville', 'Tom Smith',    '555-0111', 'Sulfa drugs');

-- ============================================================
--  SAMPLE DATA: health_records
-- ============================================================
INSERT INTO health_records (patient_id, clinician_id, record_type, title, description, diagnosis, icd_code, fhir_resource_hash, is_sensitive) VALUES
(1, 3, 'consultation',      'Initial Consultation',         'Patient presents with fatigue and increased thirst.', 'Type 2 Diabetes Mellitus',       'E11',    SHA2(CONCAT(1,'consultation','Type 2 Diabetes Mellitus',NOW()),256), 0),
(1, 3, 'lab_result',        'HbA1c Test',                   'Glycated haemoglobin test ordered.',                  'Elevated HbA1c - 7.8%',          'E11.65', SHA2(CONCAT(1,'lab_result','Elevated HbA1c',NOW()),256),            0),
(2, 4, 'consultation',      'Hypertension Follow-up',       'BP monitoring visit.',                                'Essential Hypertension',          'I10',    SHA2(CONCAT(2,'consultation','Essential Hypertension',NOW()),256),    0),
(1, 3, 'discharge_summary', 'ER Discharge',                 'Admitted for hypoglycaemia episode.',                 'Hypoglycaemia',                   'E16.0',  SHA2(CONCAT(1,'discharge_summary','Hypoglycaemia',NOW()),256),       0),
(2, 4, 'immunization',      'Influenza Vaccine',            'Annual flu vaccination administered.',                'Influenza vaccination completed', 'Z23',    SHA2(CONCAT(2,'immunization','Influenza',NOW()),256),               0);

-- ============================================================
--  SAMPLE DATA: vital_signs
-- ============================================================
INSERT INTO vital_signs (patient_id, recorded_by, heart_rate, respiratory_rate, spo2, mean_arterial_pressure, temperature, blood_glucose, bmi) VALUES
(1, 3, 88, 18, 97.5, 95.0,  37.2, 145.0, 28.4),
(1, 3, 92, 20, 96.0, 102.0, 37.5, 162.0, 28.4),
(1, 3, 78, 16, 98.0, 88.0,  36.9, 130.0, 28.1),
(2, 4, 75, 14, 99.0, 105.0, 37.0, 95.0,  24.2),
(2, 4, 80, 15, 98.5, 108.0, 37.1, 98.0,  24.5);

-- ============================================================
--  SAMPLE DATA: lab_results
-- ============================================================
INSERT INTO lab_results (patient_id, ordered_by, test_name, result_value, unit, normal_range, is_abnormal, lab_name, result_date, notes) VALUES
(1, 3, 'HbA1c',               '7.8',  '%',      '4.0-5.6',    1, 'City Lab',    CURDATE() - INTERVAL 10 DAY, 'Elevated, review medication'),
(1, 3, 'Fasting Blood Glucose','145',  'mg/dL',  '70-99',      1, 'City Lab',    CURDATE() - INTERVAL 10 DAY, 'Confirm with OGTT'),
(2, 4, 'Serum Creatinine',     '0.9',  'mg/dL',  '0.6-1.2',    0, 'Metro Lab',   CURDATE() - INTERVAL 5 DAY,  'Normal range'),
(1, 3, 'Complete Blood Count', 'WBC 7.2, RBC 4.5', 'x10^9/L', 'WBC 4-11', 0, 'City Lab', CURDATE() - INTERVAL 3 DAY, 'All within normal limits'),
(2, 4, 'Lipid Panel',         'LDL 145', 'mg/dL', 'LDL <100', 1, 'Metro Lab',   CURDATE() - INTERVAL 7 DAY,  'LDL elevated, dietary advice given');

-- ============================================================
--  SAMPLE DATA: prescriptions
-- ============================================================
INSERT INTO prescriptions (patient_id, prescribing_clinician_id, medication_name, dosage, frequency, duration_days, instructions, status) VALUES
(1, 3, 'Metformin',   '500mg',  'Twice daily',  90, 'Take with food. Monitor blood glucose regularly.',        'active'),
(1, 3, 'Lisinopril',  '10mg',   'Once daily',   30, 'Take in the morning. Avoid potassium supplements.',       'active'),
(2, 4, 'Amlodipine',  '5mg',    'Once daily',   60, 'Take at the same time each day.',                         'active'),
(2, 4, 'Atorvastatin','20mg',   'Once at night',90, 'Take with water. Avoid grapefruit juice.',                'dispensed'),
(1, 3, 'Aspirin',     '75mg',   'Once daily',   30, 'Do not use — PATIENT HAS ASPIRIN ALLERGY. [TEST RECORD]', 'cancelled');

-- ============================================================
--  SAMPLE DATA: appointments
-- ============================================================
INSERT INTO appointments (patient_id, clinician_id, appointment_date, appointment_time, purpose, status) VALUES
(1, 3, CURDATE() + INTERVAL 2 DAY,  '10:00:00', 'Diabetes 3-month review',       'scheduled'),
(2, 4, CURDATE() + INTERVAL 1 DAY,  '14:30:00', 'Blood pressure follow-up',       'scheduled'),
(1, 3, CURDATE() - INTERVAL 7 DAY,  '09:00:00', 'Initial consultation',           'completed'),
(2, 4, CURDATE() - INTERVAL 14 DAY, '11:00:00', 'Annual check-up',                'completed'),
(1, 4, CURDATE() + INTERVAL 5 DAY,  '16:00:00', 'Referral for cardiac evaluation','scheduled');

-- ============================================================
--  SAMPLE DATA: consents
-- ============================================================
INSERT INTO consents (patient_id, granted_to_user_id, record_type, institution, purpose, is_active, is_sensitive_consent, expires_at) VALUES
(1, 3, 'all',        'City General Hospital', 'Primary care access',           1, 0, NULL),
(1, 6, 'all',        'HealthFirst Insurance', 'Insurance claim processing',    1, 0, DATE_ADD(NOW(), INTERVAL 1 YEAR)),
(2, 4, 'all',        'Metro Health Clinic',   'Primary care access',           1, 0, NULL),
(2, 6, 'lab_result', 'HealthFirst Insurance', 'Claim for lab tests',           1, 0, DATE_ADD(NOW(), INTERVAL 6 MONTH)),
(1, 5, 'prescription','MedPlus Pharmacy',     'Prescription dispensing access',1, 0, NULL);

-- ============================================================
--  SAMPLE DATA: insurance_claims
-- ============================================================
INSERT INTO insurance_claims (patient_id, insurer_id, record_id, claim_amount, description, status) VALUES
(1, 6, 1, 250.00, 'Consultation fee for diabetes review',    'approved'),
(1, 6, 2, 150.00, 'HbA1c lab test reimbursement',           'under_review'),
(2, 6, 3, 200.00, 'Hypertension consultation claim',         'submitted'),
(1, 6, 4, 500.00, 'Emergency department visit',              'rejected'),
(2, 6, 5, 75.00,  'Immunization administration fee',         'approved');

-- ============================================================
--  SAMPLE DATA: messages
-- ============================================================
INSERT INTO messages (sender_id, receiver_id, subject, body, is_read) VALUES
(3, 1, 'Your HbA1c Results', 'Dear John, your latest HbA1c is 7.8%. Please continue current medication and schedule a follow-up in 3 months.', 0),
(1, 3, 'Question about Metformin', 'Dr. Brown, I have been experiencing nausea after taking Metformin. Should I take it with a larger meal?', 1),
(4, 2, 'Blood Pressure Update', 'Jane, your BP readings are improving. Keep monitoring at home daily and come back in 2 weeks.', 0),
(6, 1, 'Claim Update', 'Your claim #2 for HbA1c test is currently under review. We will notify you within 5 business days.', 0),
(3, 5, 'Prescription for John Patient', 'Phil, please dispense the Metformin prescription for John Patient (patient_id: 1). Thank you.', 1);

-- ============================================================
--  SAMPLE DATA: role_permissions
-- ============================================================
-- patient
INSERT INTO role_permissions (role, module, can_read, can_write, can_delete, can_approve) VALUES
('patient','health_records',  1,0,0,0),
('patient','prescriptions',   1,0,0,0),
('patient','lab_results',     1,0,0,0),
('patient','appointments',    1,1,1,0),
('patient','consents',        1,1,1,0),
('patient','insurance_claims',1,1,0,0),
('patient','messages',        1,1,0,0),
('patient','ai_predictions',  1,0,0,0),
('patient','blockchain_audit',0,0,0,0),
('patient','users',           0,0,0,0),
('patient','vital_signs',     1,0,0,0);

-- clinician
INSERT INTO role_permissions (role, module, can_read, can_write, can_delete, can_approve) VALUES
('clinician','health_records',  1,1,0,0),
('clinician','prescriptions',   1,1,0,0),
('clinician','lab_results',     1,1,0,0),
('clinician','appointments',    1,1,0,0),
('clinician','consents',        1,0,0,0),
('clinician','insurance_claims',0,0,0,0),
('clinician','messages',        1,1,0,0),
('clinician','ai_predictions',  1,1,0,0),
('clinician','blockchain_audit',0,0,0,0),
('clinician','users',           0,0,0,0),
('clinician','vital_signs',     1,1,0,0);

-- pharmacist
INSERT INTO role_permissions (role, module, can_read, can_write, can_delete, can_approve) VALUES
('pharmacist','health_records',  1,0,0,0),
('pharmacist','prescriptions',   1,1,0,0),
('pharmacist','lab_results',     1,0,0,0),
('pharmacist','appointments',    0,0,0,0),
('pharmacist','consents',        0,0,0,0),
('pharmacist','insurance_claims',0,0,0,0),
('pharmacist','messages',        1,1,0,0),
('pharmacist','ai_predictions',  0,0,0,0),
('pharmacist','blockchain_audit',0,0,0,0),
('pharmacist','users',           0,0,0,0),
('pharmacist','vital_signs',     1,0,0,0);

-- insurer
INSERT INTO role_permissions (role, module, can_read, can_write, can_delete, can_approve) VALUES
('insurer','health_records',  1,0,0,0),
('insurer','prescriptions',   0,0,0,0),
('insurer','lab_results',     1,0,0,0),
('insurer','appointments',    0,0,0,0),
('insurer','consents',        0,0,0,0),
('insurer','insurance_claims',1,1,0,1),
('insurer','messages',        1,1,0,0),
('insurer','ai_predictions',  0,0,0,0),
('insurer','blockchain_audit',0,0,0,0),
('insurer','users',           0,0,0,0),
('insurer','vital_signs',     0,0,0,0);

-- admin
INSERT INTO role_permissions (role, module, can_read, can_write, can_delete, can_approve) VALUES
('admin','health_records',  1,1,1,1),
('admin','prescriptions',   1,1,1,1),
('admin','lab_results',     1,1,1,1),
('admin','appointments',    1,1,1,1),
('admin','consents',        1,1,1,1),
('admin','insurance_claims',1,1,1,1),
('admin','messages',        1,1,1,1),
('admin','ai_predictions',  1,1,1,1),
('admin','blockchain_audit',1,1,1,1),
('admin','users',           1,1,1,1),
('admin','vital_signs',     1,1,1,1);

-- ============================================================
--  SAMPLE DATA: blockchain_audit_log (valid hash chain)
-- ============================================================
SET @prev = '0000000000000000000000000000000000000000000000000000000000000000';

SET @r1 = SHA2(CONCAT('{"action":"system_init","ts":"2024-01-01 00:00:00"}'), 256);
SET @b1 = SHA2(CONCAT(@prev, @r1, '1704067200'), 256);
INSERT INTO blockchain_audit_log (transaction_type, actor_user_id, affected_record_id, resource_hash, previous_hash, block_hash, ip_address, details)
VALUES ('LoginEvent', 7, NULL, @r1, @prev, @b1, '127.0.0.1', '{"action":"system_init"}');

SET @prev = @b1;
SET @r2 = SHA2(CONCAT('{"action":"login","user_id":1,"email":"patient@shrs.com"}'), 256);
SET @b2 = SHA2(CONCAT(@prev, @r2, '1704067260'), 256);
INSERT INTO blockchain_audit_log (transaction_type, actor_user_id, affected_record_id, resource_hash, previous_hash, block_hash, ip_address, details)
VALUES ('LoginEvent', 1, NULL, @r2, @prev, @b2, '192.168.1.10', '{"action":"login","email":"patient@shrs.com"}');

SET @prev = @b2;
SET @r3 = SHA2(CONCAT('{"action":"record_create","record_id":1,"patient_id":1}'), 256);
SET @b3 = SHA2(CONCAT(@prev, @r3, '1704067320'), 256);
INSERT INTO blockchain_audit_log (transaction_type, actor_user_id, affected_record_id, resource_hash, previous_hash, block_hash, ip_address, details)
VALUES ('RecordCreate', 3, 1, @r3, @prev, @b3, '192.168.1.20', '{"action":"record_create","patient_id":1,"record_type":"consultation"}');

SET @prev = @b3;
SET @r4 = SHA2(CONCAT('{"action":"consent_update","patient_id":1,"granted_to":3}'), 256);
SET @b4 = SHA2(CONCAT(@prev, @r4, '1704067380'), 256);
INSERT INTO blockchain_audit_log (transaction_type, actor_user_id, affected_record_id, resource_hash, previous_hash, block_hash, ip_address, details)
VALUES ('ConsentUpdate', 1, NULL, @r4, @prev, @b4, '192.168.1.10', '{"action":"consent_granted","granted_to_user_id":3}');

SET @prev = @b4;
SET @r5 = SHA2(CONCAT('{"action":"access_request","record_id":2,"accessor_id":6}'), 256);
SET @b5 = SHA2(CONCAT(@prev, @r5, '1704067440'), 256);
INSERT INTO blockchain_audit_log (transaction_type, actor_user_id, affected_record_id, resource_hash, previous_hash, block_hash, ip_address, details)
VALUES ('AccessRequest', 6, 2, @r5, @prev, @b5, '192.168.1.30', '{"action":"record_viewed","record_id":2,"insurer_id":6}');

-- ============================================================
--  SAMPLE DATA: ai_predictions
-- ============================================================
INSERT INTO ai_predictions (patient_id, model_type, risk_score, risk_level, feature_summary, recommendation) VALUES
(1, 'diabetes_risk',      0.8200, 'high',     '{"hba1c":7.8,"fasting_glucose":145,"bmi":28.4}',      'Intensify glucose monitoring. Consider medication adjustment. Low-carbohydrate diet recommended.'),
(1, 'hypertension_risk',  0.6500, 'moderate', '{"map":95,"bmi":28.4,"heart_rate":88}',               'Regular BP monitoring. Reduce sodium intake. Consider ACE inhibitor review.'),
(2, 'hypertension_risk',  0.7800, 'high',     '{"map":105,"bmi":24.2,"heart_rate":75}',              'Strict BP management required. Review antihypertensive therapy. Daily home monitoring.'),
(1, 'icu_deterioration',  0.3100, 'low',      '{"spo2":97.5,"hr":88,"rr":18,"map":95,"temp":37.2}',  'Patient stable. Continue routine monitoring.'),
(2, 'diabetes_risk',      0.2500, 'low',      '{"hba1c":5.2,"fasting_glucose":95,"bmi":24.2}',       'Low diabetes risk. Maintain healthy lifestyle. Annual screening recommended.');
