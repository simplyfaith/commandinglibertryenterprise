import { createContext, useContext, useEffect, useState, useCallback } from 'react';
import { api, hasCustomerToken, setCustomerToken } from '../api/client';

const CustomerAuthContext = createContext(null);

export function CustomerAuthProvider({ children }) {
  const [customer, setCustomer] = useState(null);
  const [loading, setLoading] = useState(true);

  const refresh = useCallback(async () => {
    if (!hasCustomerToken()) {
      setCustomer(null);
      setLoading(false);
      return;
    }
    try {
      const response = await api.customerMe();
      setCustomer(response.data.customer);
    } catch {
      setCustomerToken(null);
      setCustomer(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { refresh(); }, [refresh]);

  async function login(data) {
    const response = await api.customerLogin(data);
    setCustomerToken(response.data.token);
    setCustomer(response.data.customer);
    return response.data.customer;
  }

  async function register(data) {
    const response = await api.customerRegister(data);
    setCustomerToken(response.data.token);
    setCustomer(response.data.customer);
    return response.data.customer;
  }

  async function logout() {
    try { await api.customerLogout(); } catch { /* session can already be expired */ }
    setCustomerToken(null);
    setCustomer(null);
  }

  return <CustomerAuthContext.Provider value={{ customer, loading, login, register, logout, refresh }}>{children}</CustomerAuthContext.Provider>;
}

export function useCustomerAuth() {
  const context = useContext(CustomerAuthContext);
  if (!context) throw new Error('useCustomerAuth must be used within CustomerAuthProvider');
  return context;
}
