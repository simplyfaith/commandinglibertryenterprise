import { useEffect, useState } from 'react';
import { api } from '../../api/client';
import StatusBadge from '../../components/StatusBadge';

const STATUSES = ['OPEN', 'PAYMENT_PENDING', 'CONFIRMED', 'AWAITING_STOCK', 'STOCK_RECEIVED', 'PROCESSING', 'READY', 'DISPATCHED', 'DELIVERED', 'CANCELLED'];

export default function Preorders() {
  const [preorders, setPreorders] = useState([]);
  const [error, setError] = useState('');

  const load = () => api.listPreorders().then((r) => setPreorders(r.data)).catch((e) => setError(e.message));
  useEffect(() => {
    load();
  }, []);

  async function updateStatus(preorder, status) {
    try {
      await api.updatePreorderStatus({ id: preorder.id, status });
      load();
    } catch (e) { setError(e.message); }
  }

  return (
    <>
      <div className="portal-header">
        <div>
          <h1>Preorders</h1>
          <p style={{ margin: '0.35rem 0 0', color: 'var(--text-muted)', fontSize: '0.85rem' }}>
            Setting a preorder to Confirmed records full payment and adds its value to sales revenue.
          </p>
        </div>
      </div>
      {error && <p className="error-text">{error}</p>}
      <table>
        <thead><tr><th>Code</th><th>Customer contact</th><th>Product</th><th>Qty</th><th>Paid / Total</th><th>Status</th><th>Update</th></tr></thead>
        <tbody>
          {preorders.map((p) => (
            <tr key={p.id}>
              <td>{p.preorder_code}</td>
              <td className="preorder-customer-contact">
                <strong>{p.customer_name}</strong>
                {p.customer_phone && <a href={`tel:${p.customer_phone}`}>{p.customer_phone}</a>}
                {p.customer_email && <a href={`mailto:${p.customer_email}`}>{p.customer_email}</a>}
                {p.customer_address && <small>{p.customer_address}</small>}
              </td>
              <td>{p.product_name}</td>
              <td>{p.quantity}</td>
              <td>₦{Number(p.amount_paid).toLocaleString()} / ₦{Number(p.total_amount).toLocaleString()}</td>
              <td><StatusBadge status={p.status} /></td>
              <td>
                <select defaultValue="" onChange={(e) => e.target.value && updateStatus(p, e.target.value)}>
                  <option value="">Change status…</option>
                  {STATUSES.map((s) => <option key={s} value={s}>{s === 'CONFIRMED' ? 'CONFIRMED — payment received' : s.replace(/_/g, ' ')}</option>)}
                </select>
              </td>
            </tr>
          ))}
          {preorders.length === 0 && <tr><td colSpan="7">No preorders yet.</td></tr>}
        </tbody>
      </table>
    </>
  );
}
