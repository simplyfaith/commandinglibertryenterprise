-- Temporary client-demo accounts only. Replace or remove before production.

-- Make the demo usable even when the base schema was imported before its
-- seed-data section finished.
INSERT IGNORE INTO roles (name, description, max_discount_percent) VALUES
 ('SUPER_ADMIN', 'Full unrestricted access', 100),
 ('ADMIN', 'Company-wide management access', 100),
 ('MANAGER', 'Manages an assigned location', 20),
 ('STAFF', 'Operational staff at a location', 5);

INSERT IGNORE INTO permissions (`key`, description) VALUES
 ('reports.company_wide', 'View company-wide financial reports'),
 ('locations.manage', 'Create/edit locations'),
 ('users.manage', 'Create/edit staff accounts'),
 ('products.manage', 'Create/edit products and pricing'),
 ('discounts.approve', 'Approve discount requests'),
 ('stock.transfer.approve', 'Approve stock transfers'),
 ('expenses.approve', 'Approve expenses'),
 ('sales.cancel.approve', 'Approve sale/order cancellations'),
 ('audit.view.all', 'View audit logs across all staff/locations');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.name IN ('SUPER_ADMIN', 'ADMIN');

INSERT INTO locations (code, name, address, state, phone, email, is_active)
SELECT 'DEMO', 'Demo Branch', 'Client Test Location', 'Lagos', '08000000000', 'demo@commandingliberty.com', 1
WHERE NOT EXISTS (SELECT 1 FROM locations WHERE code = 'DEMO');

INSERT INTO users (staff_code, full_name, email, phone, password_hash, role_id, location_id, is_active)
SELECT 'CL-ADMIN-001', 'Demo Super Admin', 'admin@commandingliberty.com', '08000000001',
       '$2y$10$zTvUy6BqAeeOaK2Aj.YdAetZ4I5ei4265PV3rcmpdfCVy8.gjfjSm', r.id, NULL, 1
FROM roles r
WHERE r.name = 'SUPER_ADMIN'
  AND NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@commandingliberty.com');

INSERT INTO users (staff_code, full_name, email, phone, password_hash, role_id, location_id, is_active)
SELECT 'CL-MANAGER-001', 'Demo Manager', 'manager@commandingliberty.com', '08000000002',
       '$2y$10$zTvUy6BqAeeOaK2Aj.YdAetZ4I5ei4265PV3rcmpdfCVy8.gjfjSm', r.id, l.id, 1
FROM roles r CROSS JOIN locations l
WHERE r.name = 'MANAGER' AND l.code = 'DEMO'
  AND NOT EXISTS (SELECT 1 FROM users WHERE email = 'manager@commandingliberty.com');

INSERT INTO users (staff_code, full_name, email, phone, password_hash, role_id, location_id, is_active)
SELECT 'CL-STAFF-001', 'Demo Staff', 'staff@commandingliberty.com', '08000000003',
       '$2y$10$zTvUy6BqAeeOaK2Aj.YdAetZ4I5ei4265PV3rcmpdfCVy8.gjfjSm', r.id, l.id, 1
FROM roles r CROSS JOIN locations l
WHERE r.name = 'STAFF' AND l.code = 'DEMO'
  AND NOT EXISTS (SELECT 1 FROM users WHERE email = 'staff@commandingliberty.com');

UPDATE locations l
JOIN users u ON u.email = 'manager@commandingliberty.com'
SET l.manager_id = u.id
WHERE l.code = 'DEMO';

-- Make reruns safe: restore known credentials and clear any login lockouts.
UPDATE users
SET password_hash = '$2y$10$zTvUy6BqAeeOaK2Aj.YdAetZ4I5ei4265PV3rcmpdfCVy8.gjfjSm',
    is_active = 1,
    failed_login_attempts = 0,
    locked_until = NULL
WHERE email IN (
  'admin@commandingliberty.com',
  'manager@commandingliberty.com',
  'staff@commandingliberty.com'
);
