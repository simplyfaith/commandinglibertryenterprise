import { useEffect, useState } from 'react';
import { api } from '../../api/client';

export default function Account() {
  const [customer, setCustomer] = useState(() => {
    try {
      return JSON.parse(localStorage.getItem('cle_customer') || 'null');
    } catch {
      return null;
    }
  });
  const [customerId, setCustomerId] = useState(() => localStorage.getItem('cle_customer_id') || '');
  const [orders, setOrders] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    if (customer?.id) loadOrders(customer.id);
    else if (customerId) loadOrders(customerId);
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  async function loadOrders(id) {
    setError('');
    try {
      const res = await api.listOrders({ customer_id: id });
      setOrders(res.data);
    } catch (err) {
      setError(err.message);
    }
  }

  async function lookup(e) {
    e.preventDefault();
    localStorage.setItem('cle_customer_id', customerId);
    loadOrders(customerId);
  }

  function clearDashboard() {
    localStorage.removeItem('cle_customer');
    localStorage.removeItem('cle_customer_id');
    setCustomer(null);
    setCustomerId('');
    setOrders(null);
  }

  return (
    <div className="cart-page">
      <h1>My Account</h1>
      <p className="meta">Your saved customer dashboard and order history.</p>
      {customer && (
        <div className="card" style={{ marginTop: '1.5rem', marginBottom: '1.5rem' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '1rem', flexWrap: 'wrap' }}>
            <div>
              <p className="cart-eyebrow">Customer profile</p>
              <h2 style={{ margin: '0.2rem 0 0.8rem' }}>{customer.full_name}</h2>
              <p style={{ margin: 0 }}>{customer.phone}{customer.email ? ` · ${customer.email}` : ''}</p>
              {customer.location_name && <p className="meta" style={{ margin: '0.35rem 0 0' }}>Preferred location: {customer.location_name}</p>}
            </div>
            <button type="button" className="btn btn-ghost" onClick={clearDashboard}>Clear saved record</button>
          </div>
        </div>
      )}
      {!customer && (
        <form className="search-bar" onSubmit={lookup}>
          <input value={customerId} onChange={(e) => setCustomerId(e.target.value)} placeholder="Customer ID" />
          <button className="btn btn-primary">Look up</button>
        </form>
      )}
      {error && <p className="error-text">{error}</p>}
      {orders && (
        <div style={{ marginTop: '1.5rem' }}>
          <div className="kpi-grid">
            <div className="kpi-card"><span className="kpi-label">Orders</span><span className="kpi-value">{orders.length}</span></div>
            <div className="kpi-card"><span className="kpi-label">Books bought</span><span className="kpi-value">{orders.reduce((sum, order) => sum + order.items.reduce((count, item) => count + Number(item.quantity), 0), 0)}</span></div>
            <div className="kpi-card"><span className="kpi-label">Total spent</span><span className="kpi-value">₦{orders.reduce((sum, order) => sum + Number(order.total), 0).toLocaleString()}</span></div>
          </div>
          <table>
            <thead><tr><th>Order</th><th>Books</th><th>Status</th><th>Total</th><th>Placed</th></tr></thead>
            <tbody>
              {orders.map((o) => (
                <tr key={o.id}>
                  <td>{o.order_code}</td>
                  <td>{o.items.map((item) => `${item.product_name} × ${item.quantity}`).join(', ')}</td>
                  <td>{o.status}</td>
                  <td>₦{Number(o.total).toLocaleString()}</td>
                  <td>{o.created_at}</td>
                </tr>
              ))}
              {orders.length === 0 && <tr><td colSpan="5">No orders found for this customer ID.</td></tr>}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
