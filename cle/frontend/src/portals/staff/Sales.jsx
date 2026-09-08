import { useEffect, useState } from 'react';
import { api } from '../../api/client';
import { useAuth } from '../../context/AuthContext';
import StatusBadge from '../../components/StatusBadge';

export default function Sales() {
  const { user } = useAuth();
  const [products, setProducts] = useState([]);
  const [sales, setSales] = useState([]);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const load = () => {
    api.listProducts({ status: 'AVAILABLE' }).then((r) => setProducts(r.data));
    api.listSales(user.location_id ? { location_id: user.location_id } : {}).then((r) => setSales(r.data));
  };

  useEffect(() => {
    load();
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  async function handleSubmit(e) {
    e.preventDefault();
    setSubmitting(true);
    setError('');
    setMessage('');
    const form = new FormData(e.target);
    try {
      const res = await api.createSale({
        product_id: Number(form.get('product_id')),
        location_id: user.location_id,
        quantity: Number(form.get('quantity')),
        order_type: form.get('order_type'),
        discount_percent: Number(form.get('discount_percent')) || 0,
        discount_reason: form.get('discount_reason') || undefined,
        customer_paid_transport: Number(form.get('transport')) || 0,
        payment_status: form.get('payment_status'),
      });
      if (res.data?.status === 'PENDING_APPROVAL') {
        setMessage(res.message);
      } else {
        setMessage(`Sale ${res.data.sale_code} recorded — total ₦${Number(res.data.total).toLocaleString()}`);
      }
      e.target.reset();
      load();
    } catch (err) {
      setError(err.message);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <>
      <div className="portal-header"><h1>Sales</h1></div>

      <form className="card" style={{ marginBottom: '2rem' }} onSubmit={handleSubmit}>
        <div className="form-grid">
          <div className="field">
            <label>Product</label>
            <select name="product_id" required>
              <option value="">Select a product…</option>
              {products.map((p) => <option key={p.id} value={p.id}>{p.name} — ₦{Number(p.selling_price).toLocaleString()}</option>)}
            </select>
          </div>
          <div className="field"><label>Quantity</label><input name="quantity" type="number" min="1" defaultValue="1" required /></div>
          <div className="field">
            <label>Order type</label>
            <select name="order_type"><option value="WALK_IN">Walk-in</option><option value="ONLINE">Online</option><option value="PHONE">Phone</option></select>
          </div>
          <div className="field">
            <label>Discount % (your limit: {user.max_discount_percent}%)</label>
            <input name="discount_percent" type="number" min="0" max="100" step="0.5" defaultValue="0" />
          </div>
          <div className="field">
            <label>Discount reason</label>
            <select name="discount_reason">
              <option value="">— none —</option>
              <option value="BULK_PURCHASE">Bulk purchase</option>
              <option value="STUDENT">Student discount</option>
              <option value="RETURNING_CUSTOMER">Returning customer</option>
              <option value="PROMOTION">Promotion</option>
              <option value="DAMAGED_PACKAGING">Damaged packaging</option>
              <option value="SPECIAL_REQUEST">Special customer request</option>
              <option value="CORPORATE">Corporate customer</option>
              <option value="CLEARANCE">Clearance</option>
              <option value="OTHER">Other</option>
            </select>
          </div>
          <div className="field"><label>Customer-paid transport (₦)</label><input name="transport" type="number" min="0" defaultValue="0" /></div>
          <div className="field">
            <label>Payment status</label>
            <select name="payment_status"><option value="PAID">Paid</option><option value="PENDING">Pending</option><option value="PARTIALLY_PAID">Partially paid</option></select>
          </div>
        </div>
        {error && <p className="error-text">{error}</p>}
        {message && <p style={{ color: 'var(--sage)', fontSize: '0.85rem' }}>{message}</p>}
        <button className="btn btn-primary" disabled={submitting}>{submitting ? 'Recording…' : 'Record Sale'}</button>
      </form>

      <h3 style={{ marginBottom: '1rem' }}>Recent sales</h3>
      <table>
        <thead>
          <tr><th>Code</th><th>Product</th><th>Qty</th><th>Discount</th><th>Total</th>{sales[0]?.line_profit !== undefined && <th>Profit</th>}<th>Payment</th><th>Status</th><th>Date</th></tr>
        </thead>
        <tbody>
          {sales.map((s) => (
            <tr key={s.id}>
              <td>{s.sale_code}</td>
              <td>{s.product_name}</td>
              <td>{s.quantity}</td>
              <td>{s.discount_amount > 0 ? `${s.discount_type === 'PERCENTAGE' ? `${s.discount_value}%` : 'Fixed'} off (₦${Number(s.discount_amount).toLocaleString()})` : '—'}</td>
              <td>₦{Number(s.line_revenue).toLocaleString()}</td>
              {s.line_profit !== undefined && <td>₦{Number(s.line_profit).toLocaleString()}</td>}
              <td><StatusBadge status={s.payment_status} /></td>
              <td><StatusBadge status={s.status} /></td>
              <td>{s.sold_at}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </>
  );
}
