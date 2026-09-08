import { NavLink, Outlet, Link, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { SubmarkLogo } from './BrandLogos';

const STAFF_LINKS = [
  { to: '/portal', label: 'Dashboard', end: true },
  { to: '/portal/products', label: 'Add Books' },
  { to: '/portal/sales', label: 'Sales Management' },
  { to: '/portal/orders', label: 'Customer Orders' },
  { to: '/portal/preorders', label: 'Preorders' },
  { to: '/portal/inventory', label: 'Inventory & Stock' },
  { to: '/portal/expenses', label: 'Expense Ledger' },
];

const MANAGER_EXTRA = [
  { to: '/portal/approvals', label: 'Approvals & Requests' },
];

const ADMIN_LINKS = [
  { to: '/portal', label: 'Dashboard', end: true },
  { to: '/portal/locations', label: 'Store Locations' },
  { to: '/portal/products', label: 'Catalog Products' },
  { to: '/portal/sliders', label: 'Hero Sliders' },
  { to: '/portal/sales', label: 'Sales Management' },
  { to: '/portal/orders', label: 'Customer Orders' },
  { to: '/portal/preorders', label: 'Preorders' },
  { to: '/portal/inventory', label: 'Inventory & Stock' },
  { to: '/portal/expenses', label: 'Expense Ledger' },
  { to: '/portal/approvals', label: 'Approvals & Requests' },
  { to: '/portal/audit-logs', label: 'Audit Logs' },
];

export default function PortalLayout() {
  const { user, logout } = useAuth();
  const location = useLocation();
  const roleName = String(user?.role_name || '').trim().toUpperCase();
  const isCompanyWide = roleName === 'SUPER_ADMIN' || roleName === 'ADMIN';
  const links = isCompanyWide ? ADMIN_LINKS : (roleName === 'MANAGER' ? [...STAFF_LINKS, ...MANAGER_EXTRA] : STAFF_LINKS);
  const currentLink = links.find((link) => link.to === location.pathname);
  const currentLabel = currentLink?.label || (location.pathname === '/portal' ? 'Dashboard' : 'Portal');

  return (
    <div className="portal">
      <aside className="portal-sidebar">
        <div className="brand-header">
          <SubmarkLogo width={38} height={38} />
          <div className="brand-info">
            <span className="brand-name">COMMANDING LIBERTY</span>
            <span className="user-pill">{user?.full_name?.split(' ')[0] || 'User'} · {roleName || 'STAFF'}</span>
          </div>
        </div>

        <nav>
          {links.map((l) => (
            <NavLink
              key={l.to}
              to={l.to}
              end={l.end}
              className={({ isActive }) => (isActive ? 'active' : '')}
            >
              {l.label}
            </NavLink>
          ))}
        </nav>

        <div className="sidebar-footer">
          <button className="logout-btn" onClick={logout}>
            Log out of Portal
          </button>
        </div>
      </aside>

      <main className="portal-main">
        <nav className="breadcrumbs" aria-label="Breadcrumb">
          <Link to="/portal">Portal</Link>
          <span aria-hidden="true">/</span>
          <span aria-current="page">{currentLabel}</span>
        </nav>
        <Outlet />
      </main>
    </div>
  );
}
