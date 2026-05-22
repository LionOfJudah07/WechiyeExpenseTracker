DROP DATABASE IF EXISTS wechiye_db;
CREATE DATABASE IF NOT EXISTS wechiye_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wechiye_db;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    google_id VARCHAR(255) UNIQUE,
    account_type ENUM('personal','couple') NOT NULL,
    avatar_type ENUM('upload','avatar') DEFAULT 'avatar',
    avatar_url VARCHAR(255) DEFAULT 'default_1.svg',
    gender ENUM('male', 'female', 'other') NULL,
    occupation VARCHAR(255) NULL,
    education_level VARCHAR(255) NULL,
    has_kids BOOLEAN DEFAULT FALSE,
    kids_allowance_amount DECIMAL(12,2) DEFAULT 0.00,
    kids_allowance_interval ENUM('weekly', 'monthly', 'none') DEFAULT 'none',
    is_onboarded BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

CREATE TABLE bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    initial_balance DECIMAL(12,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE couple_relationships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user1_id INT NOT NULL,
    user2_id INT NOT NULL,
    linked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE couple_link_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (sender_id, receiver_id)
);

CREATE TABLE couple_dates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inviter_id INT NOT NULL,
    invitee_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    scheduled_date DATETIME NOT NULL,
    estimated_cost DECIMAL(12,2) NOT NULL,
    rsvp_status ENUM('pending', 'accepted', 'declined') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inviter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (invitee_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    name VARCHAR(50) NOT NULL,
    type ENUM('income','expense') NOT NULL,
    UNIQUE KEY (user_id, name)
);

CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bank_account_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    category_id INT NOT NULL,
    type ENUM('income','expense') NOT NULL,
    transaction_date DATE NOT NULL,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE TABLE budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    month_year DATE NOT NULL,
    amount_limit DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (category_id) REFERENCES categories(id),
    UNIQUE KEY (user_id, category_id, month_year)
);

CREATE TABLE alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    alert_type ENUM('general', 'link_request', 'date_rsvp') DEFAULT 'general',
    alert_meta JSON NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Seed predefined categories
INSERT INTO categories (user_id, name, type) VALUES
(NULL, 'Salary', 'income'),
(NULL, 'Freelance', 'income'),
(NULL, 'Food', 'expense'),
(NULL, 'Transport', 'expense'),
(NULL, 'Housing', 'expense'),
(NULL, 'Utilities', 'expense'),
(NULL, 'Entertainment', 'expense'),
(NULL, 'Health', 'expense'),
(NULL, 'Shopping', 'expense');
