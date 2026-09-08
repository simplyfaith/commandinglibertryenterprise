<?php
/**
 * Commanding Liberty Enterprise — Backend API & System Control Panel
 * Fully styled interactive API explorer, health monitor, and backend documentation.
 */

// Basic health check
$db_connected = false;
$db_error = null;
$product_count = 0;
$user_count = 0;
$order_count = 0;

try {
    require_once __DIR__ . '/config/database.php';
    $db = Database::getInstance();
    $db_connected = true;

    $stmt = $db->query("SELECT COUNT(*) FROM products");
    $product_count = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM users");
    $user_count = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM orders");
    $order_count = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    $db_error = $e->getMessage();
}

$endpoints = [
    'Auth' => [
        ['POST', '/api/auth/login.php', 'Authenticate staff/admin user and return JWT token'],
        ['POST', '/api/auth/logout.php', 'Revoke active user session token'],
        ['GET',  '/api/auth/me.php',     'Get current authenticated user profile & permissions'],
    ],
    'Products' => [
        ['GET',  '/api/products/index.php', 'List catalog products with status & category filters'],
        ['POST', '/api/products/index.php', 'Create new product entry (Admin/Super Admin)'],
        ['PUT',  '/api/products/update.php', 'Update product details & pricing'],
    ],
    'Inventory' => [
        ['GET',  '/api/inventory/index.php', 'Get inventory levels across store locations'],
        ['POST', '/api/inventory/adjust.php', 'Submit stock level adjustment request'],
        ['POST', '/api/stock-transfers/create.php', 'Transfer stock between store locations'],
    ],
    'Sales & Orders' => [
        ['GET',  '/api/sales/index.php',  'List recorded sales & counter transactions'],
        ['POST', '/api/sales/create.php', 'Process instant sale with stock deduct'],
        ['POST', '/api/orders/create.php', 'Submit customer storefront online order'],
        ['POST', '/api/orders/verify-payment.php', 'Verify payment status with payment gateway'],
    ],
    'Approvals & Audit' => [
        ['GET',  '/api/approvals/index.php', 'List pending discount/expense approval requests'],
        ['POST', '/api/discounts/decide.php', 'Approve or reject discount request (Manager+)'],
        ['GET',  '/api/audit-logs/index.php', 'Query system audit trail (Admin/Super Admin)'],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commanding Liberty Enterprise — Backend API & System Control Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Inter:wght@300;400;500;600;700;800;900&family=Montserrat:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-navy: #060C3B;
            --brand-navy-light: #0B1B6D;
            --brand-yellow: #FFDD00;
            --brand-yellow-hover: #FFE500;
            --brand-bg: #F4F6FC;
            --card-bg: #FFFFFF;
            --text-dark: #0F172A;
            --text-muted: #475569;
            --border: #E2E8F0;
            --success: #10B981;
            --danger: #EF4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: var(--brand-bg); color: var(--text-dark); line-height: 1.5; }
        
        header { background: var(--brand-navy); color: #FFFFFF; padding: 1.5rem 2rem; border-bottom: 4px solid var(--brand-yellow); box-shadow: 0 4px 20px rgba(6,12,59,0.3); }
        header .header-content { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; }
        header .brand-title { font-family: 'Montserrat', sans-serif; font-weight: 900; font-size: 1.4rem; letter-spacing: 0.05em; text-transform: uppercase; color: #FFFFFF; }
        header .brand-title span { color: var(--brand-yellow); }
        header .tagline { font-family: 'Great Vibes', cursive; font-size: 1.4rem; color: var(--brand-yellow); margin-top: -2px; }

        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1.5rem; }
        
        .status-banner { display: flex; align-items: center; justify-content: space-between; background: var(--card-bg); border-radius: 12px; padding: 1.25rem 1.75rem; border: 1px solid var(--border); box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 2rem; }
        .status-indicator { display: flex; align-items: center; gap: 0.75rem; font-weight: 700; font-size: 1.05rem; }
        .status-dot { width: 14px; height: 14px; border-radius: 50%; }
        .status-dot.online { background: var(--success); box-shadow: 0 0 10px rgba(16, 185, 129, 0.6); }
        .status-dot.offline { background: var(--danger); box-shadow: 0 0 10px rgba(239, 68, 68, 0.6); }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2.5rem; }
        .stat-card { background: var(--card-bg); border-radius: 12px; padding: 1.25rem 1.5rem; border: 1px solid var(--border); border-top: 4px solid var(--brand-navy); box-shadow: 0 2px 6px rgba(0,0,0,0.04); }
        .stat-card.yellow { border-top-color: var(--brand-yellow); }
        .stat-card .lbl { font-size: 0.78rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; }
        .stat-card .val { font-family: 'Montserrat', sans-serif; font-size: 2rem; font-weight: 900; color: var(--brand-navy); margin-top: 0.3rem; }

        .section-card { background: var(--card-bg); border-radius: 12px; padding: 1.75rem; border: 1px solid var(--border); box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        .section-card h2 { font-family: 'Montserrat', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--brand-navy); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.6rem; }

        .endpoint-group { margin-bottom: 1.5rem; }
        .endpoint-group h3 { font-size: 0.95rem; font-weight: 800; text-transform: uppercase; color: var(--brand-navy-light); letter-spacing: 0.04em; margin-bottom: 0.75rem; border-bottom: 2px solid var(--brand-bg); padding-bottom: 0.4rem; }

        .endpoint-item { display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: var(--brand-bg); border-radius: 8px; margin-bottom: 0.5rem; gap: 1rem; }
        .method-badge { font-size: 0.75rem; font-weight: 800; padding: 0.25rem 0.6rem; border-radius: 4px; color: #FFFFFF; text-transform: uppercase; min-width: 55px; text-align: center; }
        .method-GET { background: #3B82F6; }
        .method-POST { background: #10B981; }
        .method-PUT { background: #F59E0B; }

        .endpoint-path { font-family: monospace; font-weight: 700; font-size: 0.9rem; color: var(--brand-navy); }
        .endpoint-desc { font-size: 0.85rem; color: var(--text-muted); flex: 1; text-align: right; }

        .test-btn { background: var(--brand-navy); color: #FFFFFF; font-weight: 700; font-size: 0.78rem; padding: 0.4rem 0.8rem; border-radius: 6px; text-decoration: none; border: none; cursor: pointer; }
        .test-btn:hover { background: var(--brand-navy-light); }

        footer { text-align: center; padding: 2rem 0; color: var(--text-muted); font-size: 0.85rem; border-top: 1px solid var(--border); margin-top: 3rem; }
    </style>
</head>
<body>

    <header>
        <div class="header-content">
            <div>
                <div class="brand-title">COMMANDING <span>LIBERTY</span></div>
                <div class="tagline">Empowering Minds, Liberating Souls</div>
            </div>
            <div style="text-align: right;">
                <span style="font-size: 0.78rem; background: var(--brand-yellow); color: var(--brand-navy); padding: 0.3rem 0.8rem; border-radius: 99px; font-weight: 800; text-transform: uppercase;">
                    Backend v1.0 • REST API
                </span>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="status-banner">
            <div class="status-indicator">
                <div class="status-dot <?php echo $db_connected ? 'online' : 'offline'; ?>"></div>
                <span>Database Connection Status: <?php echo $db_connected ? 'Healthy & Connected' : 'Connection Error'; ?></span>
            </div>
            <?php if ($db_error): ?>
                <span style="color: var(--danger); font-size: 0.85rem; font-weight: 600;"><?php echo htmlspecialchars($db_error); ?></span>
            <?php else: ?>
                <span style="color: var(--success); font-weight: 700; font-size: 0.9rem;">✓ PDO MySQL Engine Active</span>
            <?php endif; ?>
        </div>

        <div class="stats-grid">
            <div class="stat-card yellow">
                <div class="lbl">Catalog Titles</div>
                <div class="val"><?php echo number_format($product_count); ?></div>
            </div>
            <div class="stat-card">
                <div class="lbl">Registered Staff / Admin Users</div>
                <div class="val"><?php echo number_format($user_count); ?></div>
            </div>
            <div class="stat-card">
                <div class="lbl">Customer Orders</div>
                <div class="val"><?php echo number_format($order_count); ?></div>
            </div>
            <div class="stat-card">
                <div class="lbl">RBAC Security Guard</div>
                <div class="val" style="font-size: 1.4rem; color: var(--success);">ACTIVE</div>
            </div>
        </div>

        <div class="section-card">
            <h2>REST API Endpoint Registry &amp; Controller Index</h2>
            <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.9rem;">
                All endpoints emit JSON responses compliant with CORS allow-list rules and JWT authorization tokens.
            </p>

            <?php foreach ($endpoints as $group => $list): ?>
                <div class="endpoint-group">
                    <h3><?php echo htmlspecialchars($group); ?></h3>
                    <?php foreach ($list as $ep): ?>
                        <div class="endpoint-item">
                            <span class="method-badge method-<?php echo $ep[0]; ?>"><?php echo $ep[0]; ?></span>
                            <span class="endpoint-path"><?php echo htmlspecialchars($ep[1]); ?></span>
                            <span class="endpoint-desc"><?php echo htmlspecialchars($ep[2]); ?></span>
                            <a href="<?php echo htmlspecialchars($ep[1]); ?>" target="_blank" class="test-btn">Test Endpoint</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <footer>
        &copy; <?php echo date('Y'); ?> Commanding Liberty Enterprise. All Rights Reserved. Empowering Minds, Liberating Souls.
    </footer>

</body>
</html>
