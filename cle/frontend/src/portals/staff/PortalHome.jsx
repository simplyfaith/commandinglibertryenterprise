import { useAuth } from '../../context/AuthContext';
import Dashboard from './Dashboard';
import StaffDashboard from './StaffDashboard';

export default function PortalHome() {
  const { user } = useAuth();
  const roleName = String(user?.role_name || '').trim().toUpperCase();

  if (roleName === 'ADMIN' || roleName === 'SUPER_ADMIN') {
    return <Dashboard />;
  }

  return <StaffDashboard />;
}
