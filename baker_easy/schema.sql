-- BakerEase Database Schema
CREATE DATABASE IF NOT EXISTS bakerease_db;
USE bakerease_db;

-- 1. admins table
CREATE TABLE IF NOT EXISTS admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- 2. customers table
CREATE TABLE IF NOT EXISTS customers (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    email VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. products table
CREATE TABLE IF NOT EXISTS products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    price DECIMAL(10,2) NOT NULL,       -- Selling Price in RM
    cost_price DECIMAL(10,2) NOT NULL,  -- Cost Price in RM
    stock_quantity INT NOT NULL DEFAULT 0,
    image VARCHAR(255) NULL,            -- Filename of uploaded image
    category VARCHAR(50) NOT NULL       -- Category (e.g., Cakes, Pastries, Breads, Cookies)
);

-- 3b. tables table
CREATE TABLE IF NOT EXISTS tables (
    table_id INT AUTO_INCREMENT PRIMARY KEY,
    table_number VARCHAR(10) NOT NULL UNIQUE
);

-- 4. orders table
CREATE TABLE IF NOT EXISTS orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('Pending', 'Paid', 'Completed', 'Pending Payment') DEFAULT 'Pending',
    dining_type ENUM('Dine-In', 'Takeaway') NOT NULL DEFAULT 'Takeaway',
    table_id INT NULL,
    payment_method VARCHAR(50) NOT NULL DEFAULT 'Cash',
    payment_status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    toyyibpay_billcode VARCHAR(100) NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
    FOREIGN KEY (table_id) REFERENCES tables(table_id) ON DELETE SET NULL
);

-- 5. order_items table
CREATE TABLE IF NOT EXISTS order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
);

-- Seed Default Admin (username: admin, password: admin123)
INSERT INTO admins (username, password) 
SELECT 'admin', '$2y$10$AUiaEveEifu7Bc2GyPavWezCWOPMwUpvbf3AjMWLPLbHfcf0Oj2Hi'
WHERE NOT EXISTS (SELECT 1 FROM admins WHERE username = 'admin');

-- Seed Initial Products
INSERT INTO products (product_name, description, price, cost_price, stock_quantity, image, category)
SELECT 'Chocolate Lava Cake', 'Warm chocolate cake with a molten chocolate center. Served best warm.', 15.00, 6.00, 25, 'chocolate_lava.png', 'Cakes'
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_name = 'Chocolate Lava Cake');

INSERT INTO products (product_name, description, price, cost_price, stock_quantity, image, category)
SELECT 'Strawberry Tart', 'Fresh strawberries on a sweet pastry crust with creamy custard fillings.', 12.50, 5.00, 15, 'strawberry_tart.png', 'Pastries'
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_name = 'Strawberry Tart');

INSERT INTO products (product_name, description, price, cost_price, stock_quantity, image, category)
SELECT 'Matcha Cookie', 'Soft-baked green tea cookie with rich white chocolate chips.', 4.50, 1.80, 50, 'matcha_cookie.jpg', 'Cookies'
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_name = 'Matcha Cookie');

INSERT INTO products (product_name, description, price, cost_price, stock_quantity, image, category)
SELECT 'Sourdough Bread', 'Artisanal crusty sourdough loaf baked fresh daily using traditional methods.', 18.00, 7.00, 10, 'sourdough.jpg', 'Breads'
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_name = 'Sourdough Bread');

INSERT INTO products (product_name, description, price, cost_price, stock_quantity, image, category)
SELECT 'Blueberry Cheesecake', 'Creamy baked cheesecake topped with sweet and tangy blueberry compote.', 16.00, 7.50, 3, 'blueberry_cheesecake.jpg', 'Cakes'
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_name = 'Blueberry Cheesecake');

-- Seed Initial Tables
INSERT INTO tables (table_number)
SELECT 'Table 1' WHERE NOT EXISTS (SELECT 1 FROM tables WHERE table_number = 'Table 1')
UNION ALL SELECT 'Table 2' WHERE NOT EXISTS (SELECT 1 FROM tables WHERE table_number = 'Table 2')
UNION ALL SELECT 'Table 3' WHERE NOT EXISTS (SELECT 1 FROM tables WHERE table_number = 'Table 3')
UNION ALL SELECT 'Table 4' WHERE NOT EXISTS (SELECT 1 FROM tables WHERE table_number = 'Table 4')
UNION ALL SELECT 'Table 5' WHERE NOT EXISTS (SELECT 1 FROM tables WHERE table_number = 'Table 5')
UNION ALL SELECT 'Table 6' WHERE NOT EXISTS (SELECT 1 FROM tables WHERE table_number = 'Table 6')
UNION ALL SELECT 'Table 7' WHERE NOT EXISTS (SELECT 1 FROM tables WHERE table_number = 'Table 7')
UNION ALL SELECT 'Table 8' WHERE NOT EXISTS (SELECT 1 FROM tables WHERE table_number = 'Table 8')
UNION ALL SELECT 'Table 9' WHERE NOT EXISTS (SELECT 1 FROM tables WHERE table_number = 'Table 9')
UNION ALL SELECT 'Table 10' WHERE NOT EXISTS (SELECT 1 FROM tables WHERE table_number = 'Table 10')
UNION ALL SELECT 'Table 11' WHERE NOT EXISTS (SELECT 1 FROM tables WHERE table_number = 'Table 11')
UNION ALL SELECT 'Table 12' WHERE NOT EXISTS (SELECT 1 FROM tables WHERE table_number = 'Table 12');
