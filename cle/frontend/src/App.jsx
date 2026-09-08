import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import { CartProvider } from './context/CartContext';
import ProtectedRoute from './components/ProtectedRoute';
import PortalLayout from './components/PortalLayout';

import StorefrontLayout from './portals/customer/StorefrontLayout';
import Home from './portals/customer/Home';
import Shop from './portals/customer/Shop';
import ProductDetail from './portals/customer/ProductDetail';
import Cart from './portals/customer/Cart';
import Checkout from './portals/customer/Checkout';
import Account from './portals/customer/Account';
import { About, Contact } from './portals/customer/StaticPages';

import PortalLogin from './portals/staff/PortalLogin';
import AdminLogin from './portals/admin/AdminLogin';
import PortalHome from './portals/staff/PortalHome';
import Sales from './portals/staff/Sales';
import Orders from './portals/staff/Orders';
import Preorders from './portals/staff/Preorders';
import Inventory from './portals/staff/Inventory';
import Expenses from './portals/staff/Expenses';

import Approvals from './portals/admin/Approvals';
import Locations from './portals/admin/Locations';
import Products from './portals/admin/Products';
import Sliders from './portals/admin/Sliders';
import AuditLogs from './portals/admin/AuditLogs';

export default function App() {
  return (
    <AuthProvider>
      <CartProvider>
        <BrowserRouter>
          <Routes>
            {/* Public customer storefront */}
            <Route element={<StorefrontLayout />}>
              <Route path="/" element={<Home />} />
              <Route path="/shop" element={<Shop />} />
              <Route path="/product/:id" element={<ProductDetail />} />
              <Route path="/cart" element={<Cart />} />
              <Route path="/checkout" element={<Checkout />} />
              <Route path="/account" element={<Account />} />
              <Route path="/about" element={<About />} />
              <Route path="/contact" element={<Contact />} />
            </Route>

            {/* Separate entry points for branch staff and company admins. */}
            <Route path="/portal/login" element={<Navigate to="/portal/login/staff" replace />} />
            <Route path="/portal/login/staff" element={<PortalLogin />} />
            <Route path="/portal/login/admin" element={<AdminLogin />} />

            {/* Staff / manager / admin portal — same shell, nav links and
                data returned by the API already differ by role */}
            <Route
              path="/portal"
              element={<ProtectedRoute><PortalLayout /></ProtectedRoute>}
            >
              <Route index element={<PortalHome />} />
              <Route path="sales" element={<Sales />} />
              <Route path="orders" element={<Orders />} />
              <Route path="preorders" element={<Preorders />} />
              <Route path="inventory" element={<Inventory />} />
              <Route
                path="expenses"
                element={<ProtectedRoute allowedRoles={['STAFF', 'MANAGER', 'ADMIN', 'SUPER_ADMIN']}><Expenses /></ProtectedRoute>}
              />
              <Route
                path="approvals"
                element={<ProtectedRoute allowedRoles={['MANAGER', 'ADMIN', 'SUPER_ADMIN']}><Approvals /></ProtectedRoute>}
              />
              <Route
                path="locations"
                element={<ProtectedRoute allowedRoles={['ADMIN', 'SUPER_ADMIN']}><Locations /></ProtectedRoute>}
              />
              <Route
                path="products"
                element={<ProtectedRoute allowedRoles={['STAFF', 'MANAGER', 'ADMIN', 'SUPER_ADMIN']}><Products /></ProtectedRoute>}
              />
              <Route
                path="sliders"
                element={<ProtectedRoute allowedRoles={['ADMIN', 'SUPER_ADMIN']}><Sliders /></ProtectedRoute>}
              />
              <Route
                path="audit-logs"
                element={<ProtectedRoute allowedRoles={['ADMIN', 'SUPER_ADMIN']}><AuditLogs /></ProtectedRoute>}
              />
            </Route>
          </Routes>
        </BrowserRouter>
      </CartProvider>
    </AuthProvider>
  );
}
