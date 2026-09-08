-- ============================================================
-- COMMANDING LIBERTY ENTERPRISE
-- Multi-Location Bookstore Management System - Database Schema
-- Engine: MySQL 8+ / MariaDB 10.5+
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET default_storage_engine = InnoDB;

-- ------------------------------------------------------------
-- 1. ROLES & PERMISSIONS (RBAC)
-- ------------------------------------------------------------
CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(30) NOT NULL UNIQUE,           -- SUPER_ADMIN, ADMIN, MANAGER, STAFF
  description VARCHAR(255),
  max_discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0, -- configurable ceiling
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE permissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(80) NOT NULL UNIQUE,          -- e.g. 'sales.create', 'reports.company_wide'
  description VARCHAR(255)
);

CREATE TABLE role_permissions (
  role_id INT NOT NULL,
  permission_id INT NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- 2. LOCATIONS
-- ------------------------------------------------------------
CREATE TABLE locations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,           -- e.g. 'ABJ', 'IBD'
  name VARCHAR(120) NOT NULL,
  address VARCHAR(255),
  state VARCHAR(80),
  phone VARCHAR(30),
  email VARCHAR(120),
  manager_id INT NULL,                        -- FK to users, set after users table exists
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- 3. USERS (staff / managers / admins) & CUSTOMERS (separate table)
-- ------------------------------------------------------------
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  staff_code VARCHAR(20) UNIQUE,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(30),
  password_hash VARCHAR(255) NOT NULL,
  role_id INT NOT NULL,
  location_id INT NULL,                       -- primary assigned location (NULL for SUPER_ADMIN/ADMIN)
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  mfa_secret VARCHAR(255) NULL,
  mfa_enabled TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at TIMESTAMP NULL,
  failed_login_attempts INT NOT NULL DEFAULT 0,
  locked_until TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id),
  FOREIGN KEY (location_id) REFERENCES locations(id)
);

ALTER TABLE locations ADD CONSTRAINT fk_location_manager
  FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL;

-- Additional table for staff assigned to MULTIPLE locations (many-to-many), beyond primary
CREATE TABLE user_locations (
  user_id INT NOT NULL,
  location_id INT NOT NULL,
  PRIMARY KEY (user_id, location_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE
);

CREATE TABLE sessions (
  id VARCHAR(64) PRIMARY KEY,                 -- token id (hashed)
  user_id INT NOT NULL,
  ip_address VARCHAR(45),
  user_agent VARCHAR(255),
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(150) UNIQUE,
  phone VARCHAR(30),
  gender VARCHAR(20),
  password_hash VARCHAR(255) NULL,            -- NULL if created by staff as a walk-in record
  default_address VARCHAR(255),
  created_by_user_id INT NULL,                -- staff who created walk-in customer
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by_user_id) REFERENCES users(id)
);

CREATE TABLE customer_sessions (
  id VARCHAR(64) PRIMARY KEY,
  customer_id INT NOT NULL,
  ip_address VARCHAR(45),
  user_agent VARCHAR(255),
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

CREATE TABLE customer_addresses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  label VARCHAR(50),
  address VARCHAR(255) NOT NULL,
  city VARCHAR(80),
  state VARCHAR(80),
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- 4. PRODUCT CATALOGUE
-- ------------------------------------------------------------
CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  parent_id INT NULL,
  FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(30),
  email VARCHAR(150),
  address VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(200) NOT NULL,
  category_id INT NULL,
  subcategory_id INT NULL,
  description TEXT,
  author VARCHAR(150) NULL,
  publisher VARCHAR(150) NULL,
  isbn VARCHAR(30) NULL,
  image_url VARCHAR(255),
  cost_price DECIMAL(12,2) NOT NULL DEFAULT 0 CHECK (cost_price >= 0),
  selling_price DECIMAL(12,2) NOT NULL DEFAULT 0 CHECK (selling_price >= 0),
  discount_price DECIMAL(12,2) NULL CHECK (discount_price IS NULL OR discount_price >= 0),
  status ENUM('AVAILABLE','PREORDER','OUT_OF_STOCK','DISCONTINUED','COMING_SOON') NOT NULL DEFAULT 'AVAILABLE',
  is_preorder TINYINT(1) NOT NULL DEFAULT 0,
  preorder_expected_date DATE NULL,
  preorder_quantity_limit INT NULL,
  reorder_level INT NOT NULL DEFAULT 5,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id),
  FOREIGN KEY (subcategory_id) REFERENCES categories(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Price change history (section 36 - price security)
CREATE TABLE price_change_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  field_changed ENUM('cost_price','selling_price','discount_price') NOT NULL,
  old_value DECIMAL(12,2),
  new_value DECIMAL(12,2),
  changed_by INT NOT NULL,
  reason VARCHAR(255),
  approval_status ENUM('PENDING','APPROVED','REJECTED','NOT_REQUIRED') NOT NULL DEFAULT 'NOT_REQUIRED',
  approved_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (changed_by) REFERENCES users(id),
  FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- ------------------------------------------------------------
-- 5. LOCATION-SPECIFIC INVENTORY
-- ------------------------------------------------------------
CREATE TABLE inventory (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  location_id INT NOT NULL,
  quantity_on_hand INT NOT NULL DEFAULT 0 CHECK (quantity_on_hand >= 0),
  reorder_level INT NOT NULL DEFAULT 5,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_product_location (product_id, location_id),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE
);

-- Every stock movement of any kind is captured here (immutable ledger).
CREATE TABLE inventory_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  location_id INT NOT NULL,
  type ENUM('OPENING','RECEIVED','SALE','TRANSFER_IN','TRANSFER_OUT',
            'RETURN','DAMAGED','ADJUSTMENT_ADD','ADJUSTMENT_REMOVE') NOT NULL,
  quantity INT NOT NULL,                      -- positive number; direction implied by `type`
  reference_type VARCHAR(40),                 -- 'sale', 'stock_transfer', 'stock_purchase', etc.
  reference_id INT NULL,
  reason VARCHAR(255),
  performed_by INT NOT NULL,
  approved_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (location_id) REFERENCES locations(id),
  FOREIGN KEY (performed_by) REFERENCES users(id),
  FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Weekly master-inventory view (section 19: legacy structure support)
CREATE TABLE weekly_stock_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  location_id INT NOT NULL,
  iso_week VARCHAR(8) NOT NULL,               -- e.g. '2026-W36'
  wk1 INT NOT NULL DEFAULT 0,
  wk2 INT NOT NULL DEFAULT 0,
  wk3 INT NOT NULL DEFAULT 0,
  wk4 INT NOT NULL DEFAULT 0,
  wk5 INT NOT NULL DEFAULT 0,
  stock_remaining INT NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_product_loc_week (product_id, location_id, iso_week),
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (location_id) REFERENCES locations(id)
);

-- ------------------------------------------------------------
-- 6. STOCK TRANSFERS
-- ------------------------------------------------------------
CREATE TABLE stock_transfers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  quantity INT NOT NULL CHECK (quantity > 0),
  source_location_id INT NOT NULL,
  destination_location_id INT NOT NULL,
  status ENUM('REQUESTED','APPROVED','DISPATCHED','IN_TRANSIT','RECEIVED','CANCELLED') NOT NULL DEFAULT 'REQUESTED',
  reason VARCHAR(255),
  requested_by INT NOT NULL,
  approved_by INT NULL,
  dispatched_by INT NULL,
  received_by INT NULL,
  requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  approved_at TIMESTAMP NULL,
  dispatched_at TIMESTAMP NULL,
  received_at TIMESTAMP NULL,
  CHECK (source_location_id <> destination_location_id),
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (source_location_id) REFERENCES locations(id),
  FOREIGN KEY (destination_location_id) REFERENCES locations(id),
  FOREIGN KEY (requested_by) REFERENCES users(id),
  FOREIGN KEY (approved_by) REFERENCES users(id),
  FOREIGN KEY (dispatched_by) REFERENCES users(id),
  FOREIGN KEY (received_by) REFERENCES users(id)
);

-- ------------------------------------------------------------
-- 7. STOCK PURCHASES (new stock intake)
-- ------------------------------------------------------------
CREATE TABLE stock_purchases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  supplier_id INT NULL,
  location_id INT NOT NULL,
  quantity INT NOT NULL CHECK (quantity > 0),
  cost_price DECIMAL(12,2) NOT NULL CHECK (cost_price >= 0),
  total_cost DECIMAL(12,2) NOT NULL,
  invoice_reference VARCHAR(80),
  payment_status ENUM('PENDING','PAID','PARTIALLY_PAID') NOT NULL DEFAULT 'PENDING',
  approval_status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  recorded_by INT NOT NULL,
  approved_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  FOREIGN KEY (location_id) REFERENCES locations(id),
  FOREIGN KEY (recorded_by) REFERENCES users(id),
  FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- ------------------------------------------------------------
-- 8. DISCOUNTS
-- ------------------------------------------------------------
CREATE TABLE discounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  scope ENUM('PRODUCT','ORDER') NOT NULL,
  type ENUM('PERCENTAGE','FIXED') NOT NULL,
  value DECIMAL(12,2) NOT NULL CHECK (value >= 0),
  reason_code ENUM('BULK_PURCHASE','STUDENT','RETURNING_CUSTOMER','PROMOTION',
                    'DAMAGED_PACKAGING','SPECIAL_REQUEST','CORPORATE','CLEARANCE','OTHER') NOT NULL,
  reason_note VARCHAR(255) NULL,              -- required when reason_code = OTHER (enforced in app layer)
  requested_by INT NOT NULL,
  location_id INT NOT NULL,
  approval_status ENUM('AUTO_APPROVED','PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  approved_by INT NULL,
  approved_at TIMESTAMP NULL,
  reference_type ENUM('SALE','ORDER') NOT NULL,
  reference_id INT NULL,                      -- filled once the sale/order is finalized
  original_price DECIMAL(12,2) NOT NULL,
  discount_amount DECIMAL(12,2) NOT NULL,
  final_price DECIMAL(12,2) NOT NULL CHECK (final_price >= 0),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (requested_by) REFERENCES users(id),
  FOREIGN KEY (approved_by) REFERENCES users(id),
  FOREIGN KEY (location_id) REFERENCES locations(id)
);

-- ------------------------------------------------------------
-- 9. ORDERS (online / in-store) + ORDER ITEMS
-- ------------------------------------------------------------
CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_code VARCHAR(30) NOT NULL UNIQUE,     -- e.g. CL-1024
  order_kind ENUM('AVAILABLE','PREORDER') NOT NULL DEFAULT 'AVAILABLE',
  channel ENUM('ONLINE','IN_STORE') NOT NULL DEFAULT 'ONLINE',
  customer_id INT NOT NULL,
  location_id INT NOT NULL,                   -- fulfilling branch
  status ENUM('PENDING','CONFIRMED','PROCESSING','READY','DISPATCHED',
              'DELIVERED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  customer_paid_transport DECIMAL(12,2) NOT NULL DEFAULT 0,
  enterprise_transport_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  delivery_address VARCHAR(255),
  staff_id INT NULL,                          -- staff who processed (NULL for pure self-serve online order)
  cancelled_reason VARCHAR(255) NULL,
  cancelled_by INT NULL,
  cancel_approved_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CHECK (total >= 0),
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  FOREIGN KEY (location_id) REFERENCES locations(id),
  FOREIGN KEY (staff_id) REFERENCES users(id),
  FOREIGN KEY (cancelled_by) REFERENCES users(id),
  FOREIGN KEY (cancel_approved_by) REFERENCES users(id)
);

CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL CHECK (quantity > 0),
  unit_cost_price DECIMAL(12,2) NOT NULL,     -- snapshot at time of sale
  unit_selling_price DECIMAL(12,2) NOT NULL,  -- snapshot at time of sale
  discount_id INT NULL,
  line_total DECIMAL(12,2) NOT NULL CHECK (line_total >= 0),
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (discount_id) REFERENCES discounts(id)
);

-- ------------------------------------------------------------
-- 10. PREORDERS (separate lifecycle from normal orders)
-- ------------------------------------------------------------
CREATE TABLE preorders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  preorder_code VARCHAR(30) NOT NULL UNIQUE,
  order_id INT NULL,                          -- linked once converted to a fulfillment order
  customer_id INT NOT NULL,
  product_id INT NOT NULL,
  location_id INT NOT NULL,
  quantity INT NOT NULL CHECK (quantity > 0),
  unit_price DECIMAL(12,2) NOT NULL CHECK (unit_price >= 0),
  total_amount DECIMAL(12,2) NOT NULL,
  amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_option ENUM('FULL','DEPOSIT','ON_ARRIVAL') NOT NULL,
  payment_status ENUM('PENDING','PARTIALLY_PAID','PAID','REFUNDED') NOT NULL DEFAULT 'PENDING',
  status ENUM('OPEN','PAYMENT_PENDING','CONFIRMED','AWAITING_STOCK','STOCK_RECEIVED',
              'PROCESSING','READY','DISPATCHED','DELIVERED','CANCELLED') NOT NULL DEFAULT 'OPEN',
  expected_arrival_date DATE,
  terms_acknowledged TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id),
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (location_id) REFERENCES locations(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
);

-- ------------------------------------------------------------
-- 11. SALES (in-store point-of-sale record; distinct from online orders
--     but same financial mechanics; an order may generate a sale record
--     when fulfilled in-branch)
-- ------------------------------------------------------------
CREATE TABLE sales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_code VARCHAR(30) NOT NULL UNIQUE,
  order_id INT NULL,
  product_id INT NOT NULL,
  location_id INT NOT NULL,
  staff_id INT NULL,                          -- NULL for customer-paid online orders not yet assigned to staff
  customer_id INT NULL,
  quantity INT NOT NULL CHECK (quantity > 0),
  cost_price DECIMAL(12,2) NOT NULL,
  selling_price DECIMAL(12,2) NOT NULL,       -- original unit price before discount
  discount_id INT NULL,
  actual_unit_price DECIMAL(12,2) NOT NULL,   -- price after discount
  line_revenue DECIMAL(12,2) NOT NULL,        -- actual_unit_price * quantity
  line_profit DECIMAL(12,2) NOT NULL,         -- (actual_unit_price - cost_price) * quantity
  profit_percent DECIMAL(6,2) NOT NULL,
  order_type ENUM('WALK_IN','ONLINE','PHONE') NOT NULL DEFAULT 'WALK_IN',
  destination VARCHAR(150),
  customer_paid_transport DECIMAL(12,2) NOT NULL DEFAULT 0,
  enterprise_transport_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_status ENUM('PENDING','PAID','PARTIALLY_PAID','FAILED','REFUNDED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  status ENUM('COMPLETED','CANCELLED') NOT NULL DEFAULT 'COMPLETED',
  cancelled_reason VARCHAR(255) NULL,
  cancelled_by INT NULL,
  sold_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CHECK (line_revenue >= 0),
  FOREIGN KEY (order_id) REFERENCES orders(id),
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (location_id) REFERENCES locations(id),
  FOREIGN KEY (staff_id) REFERENCES users(id),
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  FOREIGN KEY (discount_id) REFERENCES discounts(id),
  FOREIGN KEY (cancelled_by) REFERENCES users(id)
);

-- ------------------------------------------------------------
-- 12. PAYMENTS (verified server-side against a provider)
-- ------------------------------------------------------------
CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reference_type ENUM('ORDER','PREORDER','SALE') NOT NULL,
  reference_id INT NOT NULL,
  provider VARCHAR(40) NOT NULL,              -- e.g. 'paystack','flutterwave','cash'
  provider_reference VARCHAR(120) UNIQUE,
  amount DECIMAL(12,2) NOT NULL CHECK (amount >= 0),
  status ENUM('PENDING','PAID','FAILED','PARTIALLY_PAID','REFUNDED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  verified_at TIMESTAMP NULL,
  verified_by_system TINYINT(1) NOT NULL DEFAULT 0, -- 1 only after server-side provider verification
  raw_provider_payload JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- 13. EXPENSES
-- ------------------------------------------------------------
CREATE TABLE expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category ENUM('TRANSPORT','PRINTING','PACKAGING','DELIVERY','UTILITIES',
                'STOCK_PURCHASE','OTHER') NOT NULL,
  amount DECIMAL(12,2) NOT NULL CHECK (amount >= 0),
  description VARCHAR(255),
  location_id INT NOT NULL,
  recorded_by INT NOT NULL,
  receipt_url VARCHAR(255) NULL,
  approval_status ENUM('PENDING','APPROVED','REJECTED','NOT_REQUIRED') NOT NULL DEFAULT 'PENDING',
  approved_by INT NULL,
  spent_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (location_id) REFERENCES locations(id),
  FOREIGN KEY (recorded_by) REFERENCES users(id),
  FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- ------------------------------------------------------------
-- 14. APPROVALS (generic queue for any sensitive action)
-- ------------------------------------------------------------
CREATE TABLE approval_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('DISCOUNT','PRICE_CHANGE','STOCK_ADJUSTMENT','STOCK_PURCHASE',
            'ORDER_CANCELLATION','SALE_CANCELLATION','REFUND','EXPENSE',
            'STOCK_TRANSFER') NOT NULL,
  reference_id INT NOT NULL,
  location_id INT NULL,
  requested_by INT NOT NULL,
  status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  decided_by INT NULL,
  decided_at TIMESTAMP NULL,
  notes VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (location_id) REFERENCES locations(id),
  FOREIGN KEY (requested_by) REFERENCES users(id),
  FOREIGN KEY (decided_by) REFERENCES users(id)
);

-- ------------------------------------------------------------
-- 15. AUDIT LOG (immutable — application layer must never UPDATE/DELETE)
-- ------------------------------------------------------------
CREATE TABLE audit_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  role_name VARCHAR(30),
  location_id INT NULL,
  action VARCHAR(60) NOT NULL,                -- e.g. 'SALE_CREATED','DISCOUNT_APPROVED'
  record_type VARCHAR(60),
  record_id INT NULL,
  previous_value JSON NULL,
  new_value JSON NULL,
  reason VARCHAR(255) NULL,
  ip_address VARCHAR(45),
  user_agent VARCHAR(255),
  approval_status VARCHAR(20) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_user (user_id),
  INDEX idx_audit_location (location_id),
  INDEX idx_audit_action (action),
  INDEX idx_audit_created (created_at)
);

-- ------------------------------------------------------------
-- 16. NOTIFICATIONS
-- ------------------------------------------------------------
CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  recipient_type ENUM('USER','CUSTOMER') NOT NULL,
  recipient_id INT NOT NULL,
  type VARCHAR(60) NOT NULL,                  -- 'ORDER_CONFIRMED','LOW_STOCK', etc.
  title VARCHAR(150) NOT NULL,
  message VARCHAR(500) NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- 17. IMPORT STAGING (Excel/CSV safe import workflow)
-- ------------------------------------------------------------
CREATE TABLE import_batches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  file_name VARCHAR(255) NOT NULL,
  import_type ENUM('STOCK','SALES','MONTHLY_INVENTORY') NOT NULL,
  column_mapping JSON NOT NULL,
  status ENUM('UPLOADED','PREVIEWED','VALIDATED','IMPORTED','CANCELLED') NOT NULL DEFAULT 'UPLOADED',
  total_rows INT NOT NULL DEFAULT 0,
  valid_rows INT NOT NULL DEFAULT 0,
  error_rows INT NOT NULL DEFAULT 0,
  uploaded_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

CREATE TABLE import_rows (
  id INT AUTO_INCREMENT PRIMARY KEY,
  batch_id INT NOT NULL,
  row_number INT NOT NULL,
  raw_data JSON NOT NULL,
  is_valid TINYINT(1) NOT NULL DEFAULT 0,
  is_duplicate TINYINT(1) NOT NULL DEFAULT 0,
  errors JSON NULL,
  is_imported TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (batch_id) REFERENCES import_batches(id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- SEED DATA
-- ------------------------------------------------------------
INSERT INTO roles (name, description, max_discount_percent) VALUES
 ('SUPER_ADMIN','Full unrestricted access', 100),
 ('ADMIN','Company-wide management access', 100),
 ('MANAGER','Manages an assigned location', 20),
 ('STAFF','Operational staff at a location', 5);

INSERT INTO permissions (`key`, description) VALUES
 ('reports.company_wide','View company-wide financial reports'),
 ('locations.manage','Create/edit locations'),
 ('users.manage','Create/edit staff accounts'),
 ('products.manage','Create/edit products and pricing'),
 ('discounts.approve','Approve discount requests'),
 ('stock.transfer.approve','Approve stock transfers'),
 ('expenses.approve','Approve expenses'),
 ('sales.cancel.approve','Approve sale/order cancellations'),
 ('audit.view.all','View audit logs across all staff/locations');

-- Grant everything to SUPER_ADMIN and ADMIN
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.name IN ('SUPER_ADMIN','ADMIN');

-- Managers get approval + limited management permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'MANAGER' AND p.`key` IN
 ('discounts.approve','stock.transfer.approve','expenses.approve');

-- ------------------------------------------------------------
-- 18. HERO SLIDERS / BANNERS (Admin editable storefront slides)
-- ------------------------------------------------------------
CREATE TABLE hero_sliders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  subtitle VARCHAR(255) NULL,
  description TEXT NULL,
  cta_text VARCHAR(100) DEFAULT 'Explore Catalog',
  cta_link VARCHAR(255) DEFAULT '/shop',
  image_url VARCHAR(500) NULL,
  display_order INT NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- SEED DATA FOR HERO SLIDERS
INSERT INTO hero_sliders (title, subtitle, description, cta_text, cta_link, display_order, is_active) VALUES
('COMMANDING LIBERTY ENTERPRISE', 'Empowering Minds, Liberating Souls', 'Discover a wide selection of Bibles, educational books, journals, literature, and school supplies. Check real-time stock across our branch locations.', 'Shop Catalog', '/shop', 1, 1),
('BIBLES & DEVOTIONALS COLLECTION', 'Spiritual Growth & Guidance', 'Explore authentic Bibles, study editions, reference books, and daily devotionals available in stock across all branches.', 'Browse Bibles', '/shop?category=Bibles', 2, 1),
('PREORDER UPCOMING ARRIVALS', 'Reserve Before It Sells Out', 'Be the first to get newly released book titles, academic materials, and special stationery editions.', 'View Preorders', '/shop?status=PREORDER', 3, 1);

SET FOREIGN_KEY_CHECKS = 1;
