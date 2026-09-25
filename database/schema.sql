SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS tbl_coupon_usage;
DROP TABLE IF EXISTS tbl_review;
DROP TABLE IF EXISTS tbl_wishlist;
DROP TABLE IF EXISTS tbl_user_address;
DROP TABLE IF EXISTS tbl_order_item;
DROP TABLE IF EXISTS tbl_order;
DROP TABLE IF EXISTS tbl_cart;
DROP TABLE IF EXISTS tbl_coupon;
DROP TABLE IF EXISTS tbl_food;
DROP TABLE IF EXISTS tbl_category;
DROP TABLE IF EXISTS tbl_user;
DROP TABLE IF EXISTS tbl_admin;

CREATE TABLE tbl_admin (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  username VARCHAR(80) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbl_user (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_name VARCHAR(80) NOT NULL UNIQUE,
  full_name VARCHAR(140) NOT NULL,
  phone_number VARCHAR(30) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  address VARCHAR(500) NOT NULL,
  password VARCHAR(255) NOT NULL,
  image_name VARCHAR(255) DEFAULT 'default_profile.webp',
  account_status ENUM('Active','Blocked') NOT NULL DEFAULT 'Active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_status (account_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbl_user_address (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  label VARCHAR(60) NOT NULL DEFAULT 'Home',
  recipient_name VARCHAR(140) NOT NULL,
  phone_number VARCHAR(30) NOT NULL,
  address VARCHAR(500) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_address_user FOREIGN KEY (user_id) REFERENCES tbl_user(id) ON DELETE CASCADE,
  INDEX idx_address_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbl_category (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  image_name VARCHAR(255) DEFAULT NULL,
  featured ENUM('Yes','No') NOT NULL DEFAULT 'No',
  active ENUM('Yes','No') NOT NULL DEFAULT 'Yes',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_category_active_featured (active,featured)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbl_food (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(160) NOT NULL,
  description TEXT DEFAULT NULL,
  price DECIMAL(10,2) NOT NULL,
  image_name VARCHAR(255) DEFAULT NULL,
  category_id INT UNSIGNED NOT NULL,
  stock_qty INT UNSIGNED NOT NULL DEFAULT 50,
  featured ENUM('Yes','No') NOT NULL DEFAULT 'No',
  active ENUM('Yes','No') NOT NULL DEFAULT 'Yes',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_food_category FOREIGN KEY (category_id) REFERENCES tbl_category(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_food_active_featured (active,featured), INDEX idx_food_category (category_id), INDEX idx_food_stock (stock_qty)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbl_cart (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  food_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES tbl_user(id) ON DELETE CASCADE,
  CONSTRAINT fk_cart_food FOREIGN KEY (food_id) REFERENCES tbl_food(id) ON DELETE CASCADE,
  UNIQUE KEY uq_cart_user_food (user_id,food_id), INDEX idx_cart_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbl_wishlist (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  food_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wishlist_user FOREIGN KEY (user_id) REFERENCES tbl_user(id) ON DELETE CASCADE,
  CONSTRAINT fk_wishlist_food FOREIGN KEY (food_id) REFERENCES tbl_food(id) ON DELETE CASCADE,
  UNIQUE KEY uq_wishlist_user_food (user_id,food_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbl_review (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  food_id INT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  review_text VARCHAR(1000) DEFAULT NULL,
  status ENUM('Published','Hidden') NOT NULL DEFAULT 'Published',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_review_user FOREIGN KEY (user_id) REFERENCES tbl_user(id) ON DELETE CASCADE,
  CONSTRAINT fk_review_food FOREIGN KEY (food_id) REFERENCES tbl_food(id) ON DELETE CASCADE,
  UNIQUE KEY uq_review_user_food (user_id,food_id), INDEX idx_review_food_status (food_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbl_coupon (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  discount_type ENUM('Percentage','Fixed') NOT NULL,
  discount_value DECIMAL(10,2) NOT NULL,
  min_order DECIMAL(10,2) NOT NULL DEFAULT 0,
  max_discount DECIMAL(10,2) DEFAULT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  usage_limit INT UNSIGNED DEFAULT NULL,
  used_count INT UNSIGNED NOT NULL DEFAULT 0,
  active ENUM('Yes','No') NOT NULL DEFAULT 'Yes',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbl_order (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  discount DECIMAL(10,2) NOT NULL DEFAULT 0,
  coupon_code VARCHAR(50) DEFAULT NULL,
  delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL,
  payment_method ENUM('cod','bkash','nagad','sslcommerz') NOT NULL DEFAULT 'cod',
  payment_status ENUM('Pending','Submitted','Paid','Failed','Refunded') NOT NULL DEFAULT 'Pending',
  payment_phone VARCHAR(30) DEFAULT NULL,
  payment_reference VARCHAR(120) DEFAULT NULL,
  gateway_session VARCHAR(160) DEFAULT NULL,
  delivery_address VARCHAR(500) NOT NULL,
  phone_number VARCHAR(30) NOT NULL,
  order_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('Ordered','Preparing','On Delivery','Delivered','Cancelled') NOT NULL DEFAULT 'Ordered',
  cancel_reason VARCHAR(500) DEFAULT NULL,
  cancelled_at DATETIME DEFAULT NULL,
  CONSTRAINT fk_order_user FOREIGN KEY (user_id) REFERENCES tbl_user(id) ON DELETE RESTRICT,
  INDEX idx_order_user (user_id), INDEX idx_order_status (status), INDEX idx_order_date (order_date), INDEX idx_payment_status(payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbl_order_item (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  food_id INT UNSIGNED DEFAULT NULL,
  food_name VARCHAR(160) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_order_item_order FOREIGN KEY (order_id) REFERENCES tbl_order(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_item_food FOREIGN KEY (food_id) REFERENCES tbl_food(id) ON DELETE SET NULL,
  INDEX idx_order_item_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbl_coupon_usage (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  coupon_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  used_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_coupon_usage_coupon FOREIGN KEY (coupon_id) REFERENCES tbl_coupon(id) ON DELETE RESTRICT,
  CONSTRAINT fk_coupon_usage_user FOREIGN KEY (user_id) REFERENCES tbl_user(id) ON DELETE RESTRICT,
  CONSTRAINT fk_coupon_usage_order FOREIGN KEY (order_id) REFERENCES tbl_order(id) ON DELETE CASCADE,
  UNIQUE KEY uq_coupon_user (coupon_id,user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default administrator: admin / Admin@123
INSERT INTO tbl_admin (full_name,username,password) VALUES ('System Administrator','admin','$2y$12$LDFSQ.cwZBQSxKJSoDM6GOtZ6YEma5UON73N5LLZCMwSTO4lW0XjO');
INSERT INTO tbl_category (id,title,image_name,featured,active) VALUES
(1,'Pizza','Food_Category_606.jpg','Yes','Yes'),(2,'Burger & Sandwich','Food_Category_735.jpg','Yes','Yes'),(3,'Snacks','Food_Category_441.jpg','Yes','Yes'),(4,'Drinks','Food_Category_372.jpg','Yes','Yes');
INSERT INTO tbl_food (title,description,price,image_name,category_id,stock_qty,featured,active) VALUES
('Classic Pizza','Cheesy pizza with a soft crust and savoury topping.',320,'Food_name_8950.jpg',1,30,'Yes','Yes'),
('Chicken Pizza','Chicken, cheese and house seasoning baked fresh.',390,'Food-Name-8643.jpg',1,24,'Yes','Yes'),
('Brooklyn Sandwich','A filling toasted sandwich for a quick meal.',220,'Food-Name-5313.jpg',2,40,'Yes','Yes'),
('Classic Burger','Juicy patty, fresh vegetables and signature sauce.',250,'Food-Name-9135.jpeg',2,36,'Yes','Yes'),
('Momo','Steamed dumplings served with a spicy dipping sauce.',180,'Food-Name-1792.jpg',3,42,'Yes','Yes'),
('Samosa','Crispy savoury pastry with a spiced filling.',60,'Food-Name-5462.jpg',3,60,'No','Yes'),
('Cold Coffee','Chilled coffee with a smooth, creamy finish.',160,'Food-Name-5429.jpg',4,28,'Yes','Yes'),
('Black Coffee','Freshly brewed coffee served hot.',100,'Food-Name-432.jpg',4,35,'No','Yes');
INSERT INTO tbl_coupon (code,discount_type,discount_value,min_order,max_discount,starts_at,ends_at,usage_limit,active) VALUES
('WELCOME10','Percentage',10,300,150,'2026-01-01 00:00:00','2030-12-31 23:59:59',500,'Yes'),
('SAVE50','Fixed',50,500,NULL,'2026-01-01 00:00:00','2030-12-31 23:59:59',500,'Yes');
SET FOREIGN_KEY_CHECKS = 1;
