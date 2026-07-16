-- CodeVault Database Schema
-- Compatible with MySQL (v5.7+) and SQLite

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  name VARCHAR(255) NOT NULL,
  role VARCHAR(50) DEFAULT 'buyer', -- 'admin', 'seller', 'buyer'
  avatar TEXT DEFAULT NULL,
  avatar_url TEXT DEFAULT NULL,
  bio TEXT DEFAULT NULL,
  is_verified TINYINT DEFAULT 0,
  referred_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  seller_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  category VARCHAR(100) NOT NULL,
  thumbnail TEXT DEFAULT NULL,
  download_url TEXT DEFAULT NULL,
  preview_images TEXT DEFAULT NULL, -- JSON formatted array of URLs
  live_demo_url TEXT DEFAULT NULL,
  sales_count INT DEFAULT 0,
  rating DECIMAL(3,2) DEFAULT 0.00,
  is_featured TINYINT DEFAULT 0,
  tags TEXT DEFAULT NULL,
  views_count INT DEFAULT 0,
  slug VARCHAR(255) DEFAULT NULL,
  status VARCHAR(50) DEFAULT 'approved', -- 'pending', 'approved', 'rejected'
  discount_price DECIMAL(10,2) DEFAULT NULL,
  sale_ends_at TIMESTAMP NULL DEFAULT NULL,
  version VARCHAR(50) DEFAULT '1.0.0',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) UNIQUE NOT NULL,
  icon VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS purchases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  buyer_id INT NOT NULL,
  product_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sender_id INT NOT NULL,
  receiver_id INT NOT NULL,
  content TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_id INT NOT NULL,
  rating INT NOT NULL,
  comment TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS wishlist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  product_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(user_id, product_id)
);

CREATE TABLE IF NOT EXISTS verification_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  seller_id INT NOT NULL,
  document_url TEXT NOT NULL,
  status VARCHAR(50) DEFAULT 'pending', -- 'pending', 'approved', 'rejected'
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(100) PRIMARY KEY,
  value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS wallets (
  user_id INT PRIMARY KEY,
  balance DECIMAL(10,2) DEFAULT 0.00,
  pending_balance DECIMAL(10,2) DEFAULT 0.00
);

CREATE TABLE IF NOT EXISTS withdrawals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  status VARCHAR(50) DEFAULT 'pending', -- 'pending', 'approved', 'rejected'
  bank_name VARCHAR(100) DEFAULT NULL,
  account_number VARCHAR(50) DEFAULT NULL,
  account_name VARCHAR(150) DEFAULT NULL,
  bank_code VARCHAR(20) DEFAULT NULL,
  charge_amount DECIMAL(10,2) DEFAULT 0.00,
  net_amount DECIMAL(10,2) DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  processed_at TIMESTAMP NULL DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  type VARCHAR(50) NOT NULL, -- 'sale', 'withdrawal'
  status VARCHAR(50) DEFAULT 'pending', -- 'pending', 'completed'
  paystack_ref VARCHAR(100) DEFAULT NULL,
  available_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS blog_posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  content TEXT NOT NULL,
  author VARCHAR(100) NOT NULL,
  thumbnail TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS forum_threads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  category VARCHAR(100) NOT NULL,
  author_id INT NOT NULL,
  author_name VARCHAR(150) NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS forum_posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  thread_id INT NOT NULL,
  author_name VARCHAR(150) NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tutorials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  category VARCHAR(100) NOT NULL,
  content TEXT NOT NULL,
  difficulty VARCHAR(50) NOT NULL, -- 'beginner', 'intermediate', 'advanced'
  youtube_url TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS affiliate_referrals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  referrer_id INT NOT NULL,
  referred_id INT NOT NULL,
  amount DECIMAL(10,2) DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type VARCHAR(50) DEFAULT NULL,
  product_id INT DEFAULT NULL,
  message TEXT DEFAULT NULL,
  `read` TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS collections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS collection_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  collection_id INT NOT NULL,
  product_id INT NOT NULL,
  UNIQUE(collection_id, product_id)
);

CREATE TABLE IF NOT EXISTS coupon_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) UNIQUE NOT NULL,
  type VARCHAR(20) DEFAULT 'percentage',
  value DECIMAL(10,2) NOT NULL,
  expiry_date DATE DEFAULT NULL,
  max_uses INT DEFAULT NULL,
  uses_count INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS follows (
  id INT AUTO_INCREMENT PRIMARY KEY,
  follower_id INT NOT NULL,
  followed_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(follower_id, followed_id)
);


-- Seed default settings
INSERT INTO settings (`key`, value) VALUES 
('paystack_public_key', ''),
('paystack_secret_key', ''),
('withdrawal_duration_hours', '72'),
('withdrawal_charge', '150'),
('currency', '$')
ON DUPLICATE KEY UPDATE value=value;

-- Seed default blog posts
INSERT INTO blog_posts (title, content, author, thumbnail) VALUES
('10 Best Practices for Delivering Blazing Fast Web Apps in 2026', 'Delivering speed in web development is no longer optional. Modern developers must minimize JavaScript bundle sizes, embrace edge rendering and dynamic streaming of assets, and design efficient databases. Here are ten actionable tips ranging from smart CSS choices to database pruning...', 'Admin Master', 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&q=80&w=600'),
('How to Scale Your Code Marketplace Products Safely', 'Sellers face massive challenges handling support and code delivery when sales multiply. Designing responsive documentation portals, automating licensing, and setting active email threads inside CodeVault are some techniques to assure 100% client satisfaction while keeping overhead minimal...', 'CodeVault Expert', 'https://images.unsplash.com/photo-1542744094-3a31f103e35f?auto=format&fit=crop&q=80&w=600');

-- Seed sample tutorials
INSERT INTO tutorials (title, category, content, difficulty, youtube_url) VALUES
('Mastering React Context API & Slide-out Portals', 'Frontend Engineering', 'React Context allows you to share global state like carts, notifications, and logins seamlessly. In this tutorial, we will construct a high-performance CartProvider, connect it to local storage, and implement layout transitions using framer motion. Let\'s see some code snippets...', 'intermediate', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
('How to Verify Paystack Webhooks in Express', 'Backend Integrations', 'Webhooks are essential for capturing successful transactions outside of live client browser tabs. When a user checks out with Paystack, they might close the window before verification finishes. In this guide, you will learn to build a secure \'/api/webhooks\' endpoint verifying HMAC SHA512 signatures...', 'advanced', 'https://www.youtube.com/watch?v=3S91_FcxuX0');

-- Seed Categories
INSERT INTO categories (name) VALUES 
('Scripts'),
('Templates'),
('Plugins'),
('Mobile'),
('Themes');
