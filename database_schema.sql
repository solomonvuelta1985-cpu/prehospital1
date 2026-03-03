-- ============================================
-- PRE-HOSPITAL CARE FORM DATABASE SCHEMA
-- Version: 1.0.0
-- ============================================

CREATE DATABASE IF NOT EXISTS pre_hospital_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pre_hospital_db;

-- Drop tables in correct order (child tables first)
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS injuries;
DROP TABLE IF EXISTS prehospital_forms;
DROP TABLE IF EXISTS vehicles;
DROP TABLE IF EXISTS users;

-- ============================================
-- USERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'user', 'viewer') DEFAULT 'user',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PRE-HOSPITAL CARE FORMS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS prehospital_forms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_number VARCHAR(50) UNIQUE NOT NULL,
    
    -- Basic Information
    form_date DATE NOT NULL,
    departure_time TIME NULL,
    arrival_time TIME NULL,
    vehicle_used ENUM('ambulance', 'fireTruck', 'others') NULL,
    vehicle_details VARCHAR(100) NULL,
    driver_name VARCHAR(100) NULL,
    
    -- Scene Information
    arrival_scene_location VARCHAR(255) NULL,
    arrival_scene_time TIME NULL,
    departure_scene_location VARCHAR(255) NULL,
    departure_scene_time TIME NULL,
    
    -- Hospital Information
    arrival_hospital_name VARCHAR(255) NULL,
    arrival_hospital_time TIME NULL,
    departure_hospital_location VARCHAR(255) NULL,
    departure_hospital_time TIME NULL,
    arrival_station_time TIME NULL,
    
    -- Persons Present (JSON array)
    persons_present JSON NULL,
    
    -- Patient Information
    patient_name VARCHAR(150) NOT NULL,
    date_of_birth DATE NOT NULL,
    age INT UNSIGNED NOT NULL,
    gender ENUM('male', 'female') NOT NULL,
    civil_status ENUM('single', 'married', 'widowed', 'divorced', 'separated') NULL,
    address TEXT NULL,
    zone VARCHAR(50) NULL,
    occupation VARCHAR(100) NULL,
    place_of_incident VARCHAR(255) NULL,
    zone_landmark VARCHAR(100) NULL,
    incident_time TIME NULL,
    
    -- Informant Details
    informant_name VARCHAR(150) NULL,
    informant_address TEXT NULL,
    arrival_type ENUM('walkIn', 'call') NULL,
    call_arrival_time TIME NULL,
    contact_number VARCHAR(20) NULL,
    relationship_victim VARCHAR(100) NULL,
    personal_belongings JSON NULL,
    other_belongings TEXT NULL,
    
    -- Emergency Call Type
    emergency_medical BOOLEAN DEFAULT FALSE,
    emergency_medical_details TEXT NULL,
    emergency_trauma BOOLEAN DEFAULT FALSE,
    emergency_trauma_details TEXT NULL,
    emergency_ob BOOLEAN DEFAULT FALSE,
    emergency_ob_details TEXT NULL,
    emergency_general BOOLEAN DEFAULT FALSE,
    emergency_general_details TEXT NULL,
    
    -- Care Management (JSON array)
    care_management JSON NULL,
    oxygen_lpm VARCHAR(100) NULL,
    other_care TEXT NULL,
    
    -- Initial Vitals
    initial_time TIME NULL,
    initial_bp VARCHAR(20) NULL,
    initial_temp DECIMAL(4,1) NULL,
    initial_pulse INT NULL,
    initial_resp_rate INT NULL,
    initial_pain_score INT NULL,
    initial_spo2 INT NULL,
    initial_spinal_injury ENUM('yes', 'no') NULL,
    initial_consciousness VARCHAR(255) NULL,
    initial_helmet ENUM('ab', 'none') NULL,
    
    -- Follow-up Vitals
    followup_time TIME NULL,
    followup_bp VARCHAR(20) NULL,
    followup_temp DECIMAL(4,1) NULL,
    followup_pulse INT NULL,
    followup_resp_rate INT NULL,
    followup_pain_score INT NULL,
    followup_spo2 INT NULL,
    followup_spinal_injury ENUM('yes', 'no') NULL,
    followup_consciousness VARCHAR(255) NULL,
    
    -- Chief Complaints (JSON array)
    chief_complaints JSON NULL,
    other_complaints TEXT NULL,
    
    -- FAST Assessment
    fast_face_drooping ENUM('positive', 'negative') NULL,
    fast_arm_weakness ENUM('positive', 'negative') NULL,
    fast_speech_difficulty ENUM('positive', 'negative') NULL,
    fast_time_to_call ENUM('positive', 'negative') NULL,
    fast_sample_details TEXT NULL,
    
    -- OB Information
    ob_baby_status VARCHAR(100) NULL,
    ob_delivery_time TIME NULL,
    ob_placenta ENUM('in', 'out') NULL,
    ob_lmp DATE NULL,
    ob_aog VARCHAR(50) NULL,
    ob_edc DATE NULL,
    
    -- Team Information
    team_leader_notes TEXT NULL,
    team_leader VARCHAR(100) NULL,
    data_recorder VARCHAR(100) NULL,
    logistic VARCHAR(100) NULL,
    first_aider VARCHAR(100) NULL,
    second_aider VARCHAR(100) NULL,
    
    -- Hospital Endorsement
    endorsement VARCHAR(255) NULL,
    hospital_name VARCHAR(255) NULL,
    received_by VARCHAR(100) NULL,
    endorsement_datetime DATETIME NULL,
    
    -- Narrative Report
    narrative_report TEXT NULL,

    -- Waiver
    waiver_patient_signature VARCHAR(255) NULL,
    waiver_witness_signature VARCHAR(255) NULL,
    
    -- Metadata
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('draft', 'completed', 'archived') DEFAULT 'draft',
    
    INDEX idx_form_date (form_date),
    INDEX idx_patient_name (patient_name),
    INDEX idx_status (status),
    INDEX idx_created_by (created_by),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INJURIES TABLE (Body Diagram Data)
-- ============================================
CREATE TABLE IF NOT EXISTS injuries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_id INT UNSIGNED NOT NULL,
    injury_number INT NOT NULL,
    injury_type ENUM('laceration', 'fracture', 'burn', 'contusion', 'abrasion', 'other') NOT NULL,
    body_view ENUM('front', 'back') NOT NULL,
    coordinate_x DECIMAL(6,2) NOT NULL,
    coordinate_y DECIMAL(6,2) NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_form_id (form_id),
    INDEX idx_injury_type (injury_type),
    FOREIGN KEY (form_id) REFERENCES prehospital_forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ACTIVITY LOGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- VEHICLES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS vehicles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_id VARCHAR(20) UNIQUE NOT NULL,
    vehicle_type ENUM('ambulance', 'fire_truck') NOT NULL,
    vehicle_subtype VARCHAR(50) NULL,
    plate_number VARCHAR(20) NOT NULL,
    status ENUM('available', 'in_use', 'maintenance') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_vehicle_type (vehicle_type),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INSERT DEFAULT ADMIN USER
-- Password: admin123 (CHANGE THIS IN PRODUCTION!)
-- ============================================
INSERT INTO users (username, password, email, full_name, role, status) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@prehospital.local', 'System Administrator', 'admin', 'active');

-- ============================================
-- INSERT SAMPLE VEHICLES
-- ============================================
INSERT INTO vehicles (vehicle_id, vehicle_type, vehicle_subtype, plate_number, status) VALUES
('V1', 'ambulance', NULL, 'ABC1234', 'available'),
('V2', 'ambulance', NULL, 'DEF5678', 'available'),
('V3', 'ambulance', NULL, 'GHI9012', 'available'),
('V4', 'ambulance', NULL, 'JKL3456', 'available'),
('V5', 'ambulance', NULL, 'MNO7890', 'available'),
('V6', 'ambulance', NULL, 'PQR1234', 'available'),
('V7', 'ambulance', NULL, 'STU5678', 'available'),
('V8', 'ambulance', NULL, 'VWX9012', 'available'),
('V9', 'ambulance', NULL, 'YZA3456', 'available'),
('V10', 'ambulance', NULL, 'BCD7890', 'available'),
('V11', 'ambulance', NULL, 'EFG1234', 'available'),
('V12', 'ambulance', NULL, 'HIJ5678', 'available'),
('FT1', 'fire_truck', 'penetrator', 'FTP9999', 'available'),
('FT2', 'fire_truck', 'tanker', 'FTT8888', 'available');

-- ============================================
-- VIEWS FOR REPORTING
-- ============================================
CREATE OR REPLACE VIEW form_summary AS
SELECT 
    f.id,
    f.form_number,
    f.form_date,
    f.patient_name,
    f.age,
    f.gender,
    f.vehicle_used,
    f.status,
    u.full_name as created_by_name,
    f.created_at,
    COUNT(i.id) as injury_count
FROM prehospital_forms f
LEFT JOIN users u ON f.created_by = u.id
LEFT JOIN injuries i ON f.id = i.form_id
GROUP BY f.id;
