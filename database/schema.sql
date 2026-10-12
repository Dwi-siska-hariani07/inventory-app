-- Database Schema for Inventory Management System
-- Pertemuan 4: Relational Database + Advanced PHP

CREATE DATABASE IF NOT EXISTS p4_inventory_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE p4_inventory_db;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. User Profiles Table (One-to-One dengan users)
CREATE TABLE IF NOT EXISTS user_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Categories Table (One-to-Many dengan products)
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Suppliers Table
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Products Table (Child dari categories, Parent dari stock_movements)
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    sku VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    stock INT NOT NULL DEFAULT 0,
    minimum_stock INT NOT NULL DEFAULT 5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Product Suppliers (Junction Table: Many-to-Many products <-> suppliers)
CREATE TABLE IF NOT EXISTS product_suppliers (
    product_id INT NOT NULL,
    supplier_id INT NOT NULL,
    supplier_price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id, supplier_id),
    CONSTRAINT fk_ps_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ps_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Stock Movements (Histori Transaksi Masuk/Keluar, Child dari products & users)
CREATE TABLE IF NOT EXISTS stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    type ENUM('IN', 'OUT') NOT NULL,
    quantity INT NOT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sm_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_sm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SEED DATA AWAL
-- Password default: 'admin123' dan 'staff123'
-- Hash dibuat dengan BCRYPT
INSERT INTO users (id, username, password_hash, role) VALUES
(1, 'admin', '$2y$10$wNq.756QfD7V3L6gO091kO3v9XwzS4wYx5N7uT/1Zf28iZqE6e2jK', 'admin'),
(2, 'staff', '$2y$10$64oYF2W3F3M.v0QO5y9gB.9Vb5uAcmW2p7lM9L6aGf6kF7GfU5tqu', 'staff')
ON DUPLICATE KEY UPDATE id=id;

-- Seed Profil
INSERT INTO user_profiles (user_id, full_name, phone, address) VALUES
(1, 'Administrator Gudang', '081234567890', 'Kantor Pusat'),
(2, 'Staff Petugas Lapangan', '089876543210', 'Gudang Logistik')
ON DUPLICATE KEY UPDATE id=id;

-- Seed Kategori
INSERT INTO categories (id, name, description) VALUES
(1, 'Elektronik & Gadget', 'Perangkat keras komputer dan aksesori digital'),
(2, 'Perlengkapan Kantor', 'Alat tulis kantor dan administrasi'),
(3, 'Aksesoris Jaringan', 'Kabel LAN, router, switch, dan konektor')
ON DUPLICATE KEY UPDATE id=id;

-- Seed Supplier
INSERT INTO suppliers (id, name, contact_person, phone, email, address) VALUES
(1, 'PT Megah Teknologi', 'Budi Santoso', '021-5551234', 'budi@megachtek.com', 'Jakarta Pusat'),
(2, 'CV Maju Lancar Logistik', 'Siti Rahma', '022-7778899', 'siti@majulancar.id', 'Bandung'),
(3, 'Global Partsindo', 'Hendra Tan', '031-4445566', 'hendra@globalparts.com', 'Surabaya')
ON DUPLICATE KEY UPDATE id=id;

-- Seed Produk
INSERT INTO products (id, category_id, sku, name, price, stock, minimum_stock) VALUES
(1, 1, 'PRD-KB-001', 'Keyboard Mechanical RGB', 350000.00, 15, 5),
(2, 1, 'PRD-MS-002', 'Wireless Mouse Ergonomic', 125000.00, 4, 10),
(3, 1, 'PRD-MN-003', 'Monitor LED 24 Inch Full HD', 1450000.00, 2, 5),
(4, 2, 'PRD-PP-004', 'Kertas HVS A4 80gr 1 Rim', 55000.00, 30, 10),
(5, 3, 'PRD-CB-005', 'Kabel UTP Cat6 305 Meter', 890000.00, 8, 3)
ON DUPLICATE KEY UPDATE id=id;

-- Seed Relasi Many-to-Many Produk <-> Supplier
INSERT INTO product_suppliers (product_id, supplier_id, supplier_price) VALUES
(1, 1, 280000.00),
(1, 3, 290000.00),
(2, 1, 95000.00),
(3, 1, 1200000.00),
(4, 2, 42000.00),
(5, 3, 750000.00)
ON DUPLICATE KEY UPDATE product_id=product_id;

-- Seed Histori Stock Movements
INSERT INTO stock_movements (product_id, user_id, type, quantity, notes, created_at) VALUES
(1, 1, 'IN', 20, 'Stok awal gudang batch 1', NOW() - INTERVAL 5 DAY),
(1, 2, 'OUT', 5, 'Pengeluaran untuk lab komputer', NOW() - INTERVAL 3 DAY),
(2, 1, 'IN', 10, 'Pengadaan awal', NOW() - INTERVAL 5 DAY),
(2, 2, 'OUT', 6, 'Distribusi ke divisi finance', NOW() - INTERVAL 2 DAY),
(3, 1, 'IN', 5, 'Pembelian monitor', NOW() - INTERVAL 4 DAY),
(3, 1, 'OUT', 3, 'Pemasangan di ruang meeting', NOW() - INTERVAL 1 DAY),
(4, 1, 'IN', 35, 'Pengadaan kertas kantor', NOW() - INTERVAL 6 DAY),
(4, 2, 'OUT', 5, 'Divisi HRD', NOW() - INTERVAL 2 DAY),
(5, 1, 'IN', 10, 'Pengadaan jaringan IT', NOW() - INTERVAL 7 DAY),
(5, 2, 'OUT', 2, 'Instalasi lantai 2', NOW() - INTERVAL 1 DAY);
