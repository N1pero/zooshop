CREATE DATABASE IF NOT EXISTS zooshop_lr4_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE zooshop_lr4_db;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS contact_messages;
DROP TABLE IF EXISTS guestbook;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(60) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(120) NOT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    registration_date DATE NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    is_blocked TINYINT(1) NOT NULL DEFAULT 0,
    block_reason VARCHAR(255) DEFAULT NULL,
    blocked_until DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    country VARCHAR(80),
    phone VARCHAR(40),
    website VARCHAR(120)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    animal_type VARCHAR(40) NOT NULL,
    product_type VARCHAR(80) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    release_year YEAR NOT NULL,
    supplier_id INT NOT NULL,
    category_id INT NOT NULL,
    photo VARCHAR(255),
    description TEXT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_date DATETIME NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(60) NOT NULL DEFAULT 'Новый',
    customer_name VARCHAR(120) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    address VARCHAR(255) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (order_id, product_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    session_token VARCHAR(128) NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    added_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE guestbook (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    author_name VARCHAR(80) NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL,
    subject VARCHAR(160) NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (id, login, password_hash, email, photo, registration_date, is_admin) VALUES
    (1, 'demo', '$2y$12$tkJHHbdMzjC5or0aFxGQG.e2eu7vfLrXmaVKteYQXwC8E88L506.e', 'demo@zooshop.local', NULL, CURDATE(), 0),
    (2, 'admin', '$2y$12$BeYGNb.myXAMZfDz5JvpVuPwCtLZB7dZcK/iSRCGQM/qRo9fIwXSG', 'admin@zooshop.local', NULL, CURDATE(), 1);

INSERT INTO suppliers (id, name, country, phone, website) VALUES
    (1, 'Royal Canin', 'Франция', '+33 1 41 39 41 39', 'royalcanin.com'),
    (2, 'Purina', 'США', '+1 800 778 7462', 'purina.com'),
    (3, 'Trixie', 'Германия', '+49 5505 9200', 'trixie.de'),
    (4, 'Whiskas', 'США', '+1 800 525 5273', 'whiskas.com'),
    (5, 'Pedigree', 'США', '+1 800 525 5273', 'pedigree.com'),
    (6, 'Nature''s Miracle', 'США', '+1 800 645 5154', 'naturesmiracle.com');

INSERT INTO categories (id, name, description) VALUES
    (1, 'Корма', 'Сухие и влажные корма для ежедневного питания'),
    (2, 'Лакомства', 'Полезные снеки и лакомства для поощрения'),
    (3, 'Игрушки', 'Игрушки для активных игр питомцев'),
    (4, 'Аксессуары', 'Переноски, когтеточки и товары для дома'),
    (5, 'Уход', 'Шампуни и средства гигиены');

INSERT INTO products
(id, name, animal_type, product_type, price, release_year, supplier_id, category_id, photo, description)
VALUES
    (1, 'Royal Canin Mini Adult', 'Собака', 'Сухой корм', 2490.00, 2024, 1, 1, 'images/products/royal_canin.jpg', 'Корм для взрослых собак мелких пород. Подходит для ежедневного рациона.'),
    (2, 'Purina One Cat', 'Кошка', 'Сухой корм', 1290.00, 2024, 2, 1, 'images/products/purina.jpg', 'Сухой корм для кошек с курицей, поддерживает здоровье шерсти и пищеварения.'),
    (3, 'Trixie Мячик с пищалкой', 'Собака', 'Игрушка', 590.00, 2023, 3, 3, 'images/products/trixie_ball.jpg', 'Прочный мячик для активных игр, подходит для собак средних пород.'),
    (4, 'Pedigree Dentastix', 'Собака', 'Лакомство', 890.00, 2024, 5, 2, 'images/products/pedigree.jpg', 'Лакомство для ежедневного ухода за зубами собак.'),
    (5, 'Whiskas Подушечки с лососем', 'Кошка', 'Лакомство', 210.00, 2024, 4, 2, 'images/products/whiskas.jpg', 'Хрустящие подушечки с нежной начинкой для кошек.'),
    (6, 'Nature''s Miracle Shampoo', 'Собака', 'Шампунь', 790.00, 2023, 6, 5, 'images/products/shampoo.jpg', 'Гипоаллергенный шампунь для собак без резкого запаха.'),
    (7, 'Royal Canin Kitten', 'Кошка', 'Сухой корм', 1890.00, 2024, 1, 1, 'images/products/royal_kitten.jpg', 'Корм для котят до 12 месяцев, поддерживает рост и иммунитет.'),
    (8, 'Trixie Переноска Comfort', 'Кошка', 'Переноска', 3290.00, 2023, 3, 4, 'images/products/trixie_bag.jpg', 'Удобная мягкая переноска для кошек и маленьких собак.'),
    (9, 'Trixie Когтеточка', 'Кошка', 'Аксессуар', 2190.00, 2023, 3, 4, 'images/products/trixie_scratch.jpg', 'Когтеточка с мягкой отделкой для домашних кошек.'),
    (10, 'Purina Dentalife', 'Собака', 'Лакомство', 690.00, 2024, 2, 2, 'images/products/dentalife.jpg', 'Dental-лакомство для ухода за полостью рта собак мелких пород.');
