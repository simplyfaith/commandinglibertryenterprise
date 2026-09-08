const BASE = (import.meta.env.VITE_API_URL || '/api').replace(/\/$/, '');

function getToken() {
  return sessionStorage.getItem('cle_token');
}

function getCustomerToken() {
  return sessionStorage.getItem('cle_customer_token');
}

async function request(path, { method = 'GET', body, auth = true } = {}) {
  const isFormData = body instanceof FormData;
  const headers = isFormData ? {} : { 'Content-Type': 'application/json' };
  const token = getToken();
  if (auth && token) headers.Authorization = `Bearer ${token}`;

  const res = await fetch(`${BASE}${path}`, {
    method,
    headers,
    body: body ? (isFormData ? body : JSON.stringify(body)) : undefined,
  });

  let payload;
  try {
    payload = await res.json();
  } catch {
    payload = { success: false, message: 'Unexpected server response' };
  }

  if (!res.ok) {
    const err = new Error(payload.message || 'Request failed');
    err.status = res.status;
    err.errors = payload.errors;
    err.payload = payload;
    throw err;
  }
  return payload;
}

async function customerRequest(path, { method = 'GET', body, auth = true } = {}) {
  const isFormData = body instanceof FormData;
  const headers = isFormData ? {} : { 'Content-Type': 'application/json' };
  const token = getCustomerToken();
  if (auth && token) headers.Authorization = `Bearer ${token}`;
  const res = await fetch(`${BASE}${path}`, { method, headers, body: body ? (isFormData ? body : JSON.stringify(body)) : undefined });
  let payload;
  try { payload = await res.json(); } catch { payload = { success: false, message: 'Unexpected server response' }; }
  if (!res.ok) {
    const err = new Error(payload.message || 'Request failed');
    err.status = res.status;
    err.errors = payload.errors;
    throw err;
  }
  return payload;
}

export const api = {
  get: (path) => request(path, { method: 'GET' }),
  post: (path, body) => request(path, { method: 'POST', body }),
  put: (path, body) => request(path, { method: 'PUT', body }),
  patch: (path, body) => request(path, { method: 'PATCH', body }),

  // Auth
  login: (email, password, location) => request('/auth/login.php', {
    method: 'POST',
    body: { email, password, location: location?.trim() || null },
    auth: false,
  }),
  logout: () => request('/auth/logout.php', { method: 'POST' }),
  me: () => request('/auth/me.php'),

  // Customer accounts
  customerRegister: (data) => customerRequest('/auth/customer-register.php', { method: 'POST', body: data, auth: false }),
  customerLogin: (data) => customerRequest('/auth/customer-login.php', { method: 'POST', body: data, auth: false }),
  customerMe: () => customerRequest('/auth/customer-me.php'),
  customerLogout: () => customerRequest('/auth/customer-logout.php', { method: 'POST' }),

  // Products / storefront
  listProducts: (params = {}) => {
    const qs = new URLSearchParams(params).toString();
    return request(`/products/index.php${qs ? `?${qs}` : ''}`, { auth: false });
  },
  createProduct: (data) => request('/products/index.php', { method: 'POST', body: data }),
  updateProduct: (data) => request('/products/update.php', { method: 'POST', body: data }),

  // Locations
  listLocations: () => request('/locations/index.php', { auth: false }),
  createLocation: (data) => request('/locations/index.php', { method: 'POST', body: data }),
  updateLocation: (data) => request('/locations/update.php', { method: 'PUT', body: data }),

  // Categories
  listCategories: () => request('/categories/index.php', { auth: false }),
  createCategory: (data) => request('/categories/index.php', { method: 'POST', body: data }),

  // Inventory
  listInventory: (params = {}) => {
    const qs = new URLSearchParams(params).toString();
    return request(`/inventory/index.php${qs ? `?${qs}` : ''}`);
  },
  adjustInventory: (data) => request('/inventory/adjust.php', { method: 'POST', body: data }),

  // Stock transfers
  createTransfer: (data) => request('/stock-transfers/create.php', { method: 'POST', body: data }),
  updateTransferStatus: (data) => request('/stock-transfers/update-status.php', { method: 'POST', body: data }),

  // Sales
  listSales: (params = {}) => {
    const qs = new URLSearchParams(params).toString();
    return request(`/sales/index.php${qs ? `?${qs}` : ''}`);
  },
  createSale: (data) => request('/sales/create.php', { method: 'POST', body: data }),
  cancelSale: (data) => request('/sales/cancel.php', { method: 'POST', body: data }),

  // Discounts
  decideDiscount: (data) => request('/discounts/decide.php', { method: 'POST', body: data }),

  // Customers
  createCustomer: (data) => request('/customers/create.php', { method: 'POST', body: data, auth: !!getToken() }),

  // Orders
  createOrder: (data) => request('/orders/create.php', { method: 'POST', body: data, auth: !!getToken() }),
  listOrders: (params = {}) => {
    const qs = new URLSearchParams(params).toString();
    return request(`/orders/index.php${qs ? `?${qs}` : ''}`, { auth: !!getToken() });
  },
  updateOrderStatus: (data) => request('/orders/update-status.php', { method: 'POST', body: data }),
  verifyPayment: (data) => request('/orders/verify-payment.php', { method: 'POST', body: data, auth: false }),
  customerOrders: () => customerRequest('/orders/index.php'),

  // Preorders
  createPreorder: (data) => request('/preorders/create.php', { method: 'POST', body: data, auth: !!getToken() }),
  verifyPreorderPayment: (data) => request('/preorders/verify-payment.php', { method: 'POST', body: data, auth: false }),
  listPreorders: (params = {}) => {
    const qs = new URLSearchParams(params).toString();
    return request(`/preorders/index.php${qs ? `?${qs}` : ''}`);
  },
  updatePreorderStatus: (data) => request('/preorders/update-status.php', { method: 'POST', body: data }),

  // Expenses
  listExpenses: (params = {}) => {
    const qs = new URLSearchParams(params).toString();
    return request(`/expenses/index.php${qs ? `?${qs}` : ''}`);
  },
  createExpense: (data) => request('/expenses/create.php', { method: 'POST', body: data }),

  // Reports / approvals / audit
  dashboard: (params = {}) => {
    const qs = new URLSearchParams(params).toString();
    return request(`/reports/dashboard.php${qs ? `?${qs}` : ''}`);
  },
  listApprovals: (params = {}) => {
    const qs = new URLSearchParams(params).toString();
    return request(`/approvals/index.php${qs ? `?${qs}` : ''}`);
  },
  listAuditLogs: (params = {}) => {
    const qs = new URLSearchParams(params).toString();
    return request(`/audit-logs/index.php${qs ? `?${qs}` : ''}`);
  },

  // Hero Sliders / Banners
  listSliders: (params = {}) => {
    const qs = new URLSearchParams(params).toString();
    return request(`/sliders/index.php${qs ? `?${qs}` : ''}`, { auth: !!getToken() });
  },
  createSlider: (data) => request('/sliders/index.php', { method: 'POST', body: data }),
  updateSlider: (data) => request('/sliders/update.php', { method: 'POST', body: data }),
  deleteSlider: (id) => request('/sliders/delete.php', { method: 'POST', body: { id } }),
};

export function setToken(token) {
  if (token) sessionStorage.setItem('cle_token', token);
  else sessionStorage.removeItem('cle_token');
}

export function hasToken() {
  return !!getToken();
}

export function setCustomerToken(token) {
  if (token) sessionStorage.setItem('cle_customer_token', token);
  else sessionStorage.removeItem('cle_customer_token');
}

export function hasCustomerToken() {
  return !!getCustomerToken();
}
