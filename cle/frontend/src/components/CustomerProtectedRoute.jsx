import { Navigate, useLocation } from 'react-router-dom';
import { useCustomerAuth } from '../context/CustomerAuthContext';

export default function CustomerProtectedRoute({ children }) {
  const { customer, loading } = useCustomerAuth();
  const location = useLocation();
  if (loading) return <div className="cart-page"><p>Loading account...</p></div>;
  if (!customer) return <Navigate to={`/account/login?next=${encodeURIComponent(location.pathname)}`} replace />;
  return children;
}
