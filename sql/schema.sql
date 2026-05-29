-- Money Management Database Schema
-- Run: mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS money_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE money_management;

CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    currency VARCHAR(10) DEFAULT 'THB',
    reset_token VARCHAR(100) NULL,
    reset_expires DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS incomes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type ENUM('salary','bonus','freelance','passive','other') NOT NULL DEFAULT 'salary',
    name VARCHAR(150) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    month INT NOT NULL,
    year INT NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS expenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    category ENUM('food','transportation','utilities','insurance','internet','family','other') NOT NULL DEFAULT 'other',
    name VARCHAR(150) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    expense_type ENUM('recurring','one_time') DEFAULT 'recurring',
    month INT NOT NULL,
    year INT NOT NULL,
    due_day INT DEFAULT 1,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS debts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    category ENUM('housing','car','credit_card','personal','student','business','other') NOT NULL DEFAULT 'personal',
    original_amount DECIMAL(15,2) NOT NULL,
    remaining_balance DECIMAL(15,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    interest_type ENUM('fixed','flat','reducing','compound','credit_card','custom') DEFAULT 'reducing',
    monthly_payment DECIMAL(15,2) NOT NULL,
    due_day INT DEFAULT 1,
    loan_term INT DEFAULT NULL COMMENT 'months',
    start_date DATE NULL,
    penalty_fee DECIMAL(15,2) DEFAULT 0,
    status ENUM('active','paid','overdue') DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS debt_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    debt_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    principal DECIMAL(15,2) DEFAULT 0,
    interest DECIMAL(15,2) DEFAULT 0,
    payment_date DATE NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (debt_id) REFERENCES debts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('due_date','overdue','budget_warning','goal','info') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Sample user (password: demo1234)
INSERT INTO users (name, email, password, currency) VALUES
('Demo User', 'demo@example.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'THB');

-- Sample incomes (month=5, year=2026)
INSERT INTO incomes (user_id, type, name, amount, month, year) VALUES
(1, 'salary', 'Monthly Salary', 55000.00, 5, 2026),
(1, 'freelance', 'Freelance Project', 12000.00, 5, 2026),
(1, 'passive', 'Dividend Income', 3500.00, 5, 2026);

-- Sample expenses
INSERT INTO expenses (user_id, category, name, amount, expense_type, month, year, due_day) VALUES
(1, 'food', 'Groceries & Dining', 8000.00, 'recurring', 5, 2026, 1),
(1, 'transportation', 'Fuel & Maintenance', 3500.00, 'recurring', 5, 2026, 1),
(1, 'utilities', 'Electricity & Water', 1800.00, 'recurring', 5, 2026, 5),
(1, 'insurance', 'Life Insurance', 2500.00, 'recurring', 5, 2026, 10),
(1, 'internet', 'Internet & Phone', 900.00, 'recurring', 5, 2026, 15),
(1, 'family', 'Family Allowance', 5000.00, 'recurring', 5, 2026, 1);

-- Sample debts
INSERT INTO debts (user_id, name, category, original_amount, remaining_balance, interest_rate, interest_type, monthly_payment, due_day, loan_term, start_date, status) VALUES
(1, 'Home Loan', 'housing', 3000000.00, 2450000.00, 4.5, 'reducing', 18500.00, 5, 360, '2021-01-05', 'active'),
(1, 'Car Loan', 'car', 650000.00, 320000.00, 3.2, 'flat', 12000.00, 15, 60, '2023-03-15', 'active'),
(1, 'Credit Card', 'credit_card', 85000.00, 42000.00, 18.0, 'credit_card', 5000.00, 25, NULL, '2024-06-01', 'active'),
(1, 'Personal Loan', 'personal', 120000.00, 75000.00, 7.5, 'reducing', 4500.00, 20, 36, '2024-09-01', 'active');
