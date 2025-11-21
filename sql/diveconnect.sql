-- ==========================================
-- DiveConnect Database Schema - UPDATED WITH PRICE MANAGEMENT
-- ==========================================

CREATE DATABASE IF NOT EXISTS dive_connect;
USE dive_connect;

-- Updated Admins table with GCash QR and owner name
CREATE TABLE admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','superadmin') DEFAULT 'admin',
    gcash_amount DECIMAL(10,2) NULL,
    gcash_qr VARCHAR(255) NULL, -- QR code image path
    gcash_owner VARCHAR(100) NULL, -- GCash account owner name
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    vat_percent DECIMAL(5,2) DEFAULT 12.00
);

-- 🔹 Divers Table (Professional Divers) - UPDATED with payment fields
CREATE TABLE divers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    whatsapp_number VARCHAR(50) NOT NULL,
    pro_org VARCHAR(100), -- Certifying Agency
    pro_diver_id VARCHAR(100) NOT NULL, -- Diver ID from agency
    specialty VARCHAR(150),
    profile_pic VARCHAR(255),
    valid_id VARCHAR(255),
    qr_code VARCHAR(255), -- Diver's own QR code
    gcash_receipt VARCHAR(255), -- Payment receipt
    level VARCHAR(100) DEFAULT '', -- Changed from ENUM
    nationality VARCHAR(100),
    language VARCHAR(100),
    price DECIMAL(10,2) DEFAULT 1000.00, -- ✅ UPDATED: Default price set to 1000
    max_pax INT DEFAULT 6, -- Maximum diver capacity
    verification_status ENUM('pending','verified','rejected','approved') DEFAULT 'pending',
    approved_by INT NULL,
    rejected_by INT NULL,
    action_date DATETIME NULL,
    rating DECIMAL(3,2) DEFAULT 0.00,
    experience TEXT NULL,
    tin_id VARCHAR(255) NULL,
    signature VARCHAR(255) NULL,
    reset_token VARCHAR(255) NULL,
    token_expiry DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 🔹 Users Table (Normal Divers / Customers) - UPDATED with new fields
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    whatsapp VARCHAR(50) NULL,
    profile_pic VARCHAR(255) NULL,
    valid_id VARCHAR(255) NULL,
    diver_id_file VARCHAR(255) NULL, -- Diver certification file
    certify_agency VARCHAR(100) NULL, -- Certifying agency
    certification_level VARCHAR(100) NULL, -- Certification level
    diver_id_number VARCHAR(100) NULL, -- Diver ID number
    signature VARCHAR(255) NULL, -- User signature
    is_email_verified TINYINT(1) DEFAULT 0, -- Email verification status
    email_verify_token VARCHAR(255) NULL, -- Email verification token
    verify_token VARCHAR(255) NULL,
    verify_token_expires DATETIME NULL,
    admin_approved TINYINT(1) DEFAULT 0, -- Admin approval status
    approved_by INT NULL,
    rejected_by INT NULL,
    action_date DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 🔹 Diver Destinations Table (Diver-specific dive sites)
CREATE TABLE diver_destinations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    diver_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    location VARCHAR(255) NOT NULL,
    rating TINYINT DEFAULT 5,
    description TEXT NULL,
    price_per_diver DECIMAL(10,2) DEFAULT 1000.00, -- ✅ UPDATED: Default price set to 1000
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (diver_id) REFERENCES divers(id) ON DELETE CASCADE
);

-- 🔹 Availability Table (Diver Schedule with Slot Management) - UPDATED
CREATE TABLE availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    diver_id INT NOT NULL,
    available_date DATE NOT NULL,
    available_time VARCHAR(50) NOT NULL, -- Time range (e.g., "09:00 - 12:00")
    start_time TIME NULL, -- Start time
    end_time TIME NULL, -- End time
    max_slots INT DEFAULT 6, -- Maximum slots
    available_slots INT DEFAULT 6, -- Available slots
    booked_slots INT DEFAULT 0, -- Booked slots
    booking_deadline VARCHAR(50) DEFAULT '2 hours', -- Booking deadline
    status ENUM('available','fully_booked','completed') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (diver_id) REFERENCES divers(id) ON DELETE CASCADE
);

-- 🔹 Bookings Table (Users Booking Divers) - UPDATED with slot management
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    diver_id INT NOT NULL,
    booking_date DATE NOT NULL,
    pax_count INT DEFAULT 1, -- Number of divers
    status ENUM('pending','confirmed','cancelled','declined') DEFAULT 'pending',
    remarks TEXT NULL, -- Diver remarks
    user_signature VARCHAR(255) NULL, -- User signature for payment
    gcash_receipt VARCHAR(255) NULL, -- GCash receipt
    payment_method VARCHAR(50) NULL, -- Payment method
    grand_total DECIMAL(10,2) DEFAULT 0.00, -- Total amount
    dive_site VARCHAR(255) NULL,
    dive_start_time TIME NULL,
    dive_end_time TIME NULL,
    actual_dive_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (diver_id) REFERENCES divers(id) ON DELETE CASCADE
);

-- 🔹 Diver Gears Table (Gear rental management)
CREATE TABLE diver_gears (
    id INT AUTO_INCREMENT PRIMARY KEY,
    diver_id INT NOT NULL,
    gear_name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (diver_id) REFERENCES divers(id) ON DELETE CASCADE
);

-- 🔹 Booking Gears Table (Selected gears for each booking)
CREATE TABLE booking_gears (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    gear_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (gear_id) REFERENCES diver_gears(id) ON DELETE CASCADE
);

-- 🔹 User Gears Table (User's own gears)
CREATE TABLE user_gears (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    gear_type VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 🔹 Ratings Table (User ratings for divers)
CREATE TABLE ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    diver_id INT NOT NULL,
    rating INT NOT NULL,
    review TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (diver_id) REFERENCES divers(id) ON DELETE CASCADE
);

-- 🔹 Payments Table
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending','paid','failed') DEFAULT 'pending',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);

-- 🔹 Terms and Conditions (Dynamic Management sa System)
CREATE TABLE terms_conditions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 🔹 Destinations Table (Dive sites)
CREATE TABLE destinations (
    destination_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    location VARCHAR(255) NOT NULL,
    rating TINYINT DEFAULT 4,
    description VARCHAR(500) NOT NULL,
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 🔹 Password Resets Table
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 🔹 Temporary Users Table (for OTP verification)
CREATE TABLE users_temp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    otp_expires DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sample Admin Account with GCash details
INSERT INTO admins (fullname, email, password, role, gcash_amount, gcash_owner) VALUES 
('Admin', 'admin@diveconnect.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1000.00, 'DiveConnect Admin');

-- Sample Terms and Conditions
INSERT INTO terms_conditions (content, is_active) VALUES 
('By using DiveConnect, you agree to our terms and conditions...', 1);

-- Sample Diver for testing
INSERT INTO divers (fullname, email, password, whatsapp_number, pro_org, pro_diver_id, specialty, level, nationality, language, price, verification_status) VALUES 
('John Dive Master', 'john@diveconnect.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+639123456789', 'PADI', 'PADI12345', 'Deep Diving', 'Master Scuba Diver', 'Filipino', 'English, Tagalog', 1000.00, 'approved');

-- Sample Diver Destination
INSERT INTO diver_destinations (diver_id, title, image_path, location, rating, description, price_per_diver) VALUES 
(1, 'Twin Rocks Marine Sanctuary', 'assets/images/twinrocks.jpg', 'Anilao, Mabini', 5, 'Premier marine sanctuary with diverse marine life', 1000.00);