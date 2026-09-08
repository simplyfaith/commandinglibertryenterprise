import { Navigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export default function ProtectedRoute({ children, allowedRoles }) {
  const { user, loading } = useAuth();

  if (loading) return <div style={{ padding: '3rem', textAlign: 'center' }}>Loading…</div>;
  if (!user) return <Navigate to="/portal/login" replace />;
  const roleName = String(user.role_name || '').trim().toUpperCase();
  if (allowedRoles && !allowedRoles.includes(roleName)) {
    return (
      <div className="access-denied">
        <h1>Access denied</h1>
        <p>This page is available to administrators only.</p>
        <p>Your current role is: <strong>{roleName || 'Unknown'}</strong></p>
        <a className="btn btn-primary" href="/portal">Back to dashboard</a>
      </div>
    );
  }
  return children;
}
