import { Fragment, useEffect, useState } from 'react';
import { api } from '../../api/client';
import StatusBadge from '../../components/StatusBadge';

const NEXT_STATUS = {
  PENDING: 'CONFIRMED',
  CONFIRMED: 'PROCESSING',
  PROCESSING: 'READY',
  READY: 'DISPATCHED',
  DISPATCHED: 'DELIVERED',
};

export default function Orders() {
  const [orders, setOrders] = useState([]);
  const [expandedId, setExpandedId] = useState(null);
  const [error, setError] = useState('');

  const load = () => api.listOrders().then((r) => setOrders(r.data)).catch((e) => setError(e.message));
  useEffect(() => {
    load();
  }, []);

  async function advance(order) {
    const next = NEXT_STATUS[order.status];
    if (!next) return;
    try {
      await api.updateOrderStatus({ id: order.id, status: next });
      load();
    } catch (e) { setError(e.message); }
  }

  async function cancel(order) {
    const reason = window.prompt('Reason for cancelling this order:');
    if (!reason) return;
    try {
      const res = await api.updateOrderStatus({ id: order.id, status: 'CANCELLED', reason });
      if (res.data === null && res.message.includes('approval')) alert(res.message);
      load();
    } catch (e) { setError(e.message); }
  }

  return (
    <>
      <div className="portal-header"><h1>Orders</h1></div>
      {error && <p className="error-text">{error}</p>}
      <table>
        <thead><tr><th>Order</th><th>Customer</th><th>Branch</th><th>Total</th><th>Status</th><th>Placed</th><th></th></tr></thead>
        <tbody>
          {orders.map((o) => (
            <Fragment key={o.id}>
              <tr>
                <td>{o.order_code}</td>
                <td>{o.customer_name}</td>
                <td>{o.location_name}</td>
                <td>₦{Number(o.total).toLocaleString()}</td>
                <td><StatusBadge status={o.status} /></td>
                <td>{o.created_at}</td>
                <td style={{ display: 'flex', gap: '0.4rem' }}>
                  <button className="btn btn-ghost" onClick={() => setExpandedId(expandedId === o.id ? null : o.id)}>
                    {expandedId === o.id ? 'Hide details' : 'View details'}
                  </button>
                  {NEXT_STATUS[o.status] && <button className="btn btn-ghost" onClick={() => advance(o)}>Mark {NEXT_STATUS[o.status]}</button>}
                  {!['DELIVERED', 'CANCELLED'].includes(o.status) && <button className="btn btn-ghost" onClick={() => cancel(o)}>Cancel</button>}
                </td>
              </tr>
              {expandedId === o.id && (
                <tr key={`${o.id}-details`}>
                  <td colSpan="7">
                    <div className="card" style={{ padding: '1rem' }}>
                      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '0.75rem', marginBottom: '1rem' }}>
                        <div><strong>Customer</strong><br />{o.customer_name}</div>
                        <div><strong>Phone</strong><br />{o.customer_phone || 'Not provided'}</div>
                        <div><strong>Email</strong><br />{o.customer_email || 'Not provided'}</div>
                        <div><strong>Location</strong><br />{o.location_name}</div>
                        <div><strong>Channel</strong><br />{o.channel || 'Not specified'}</div>
                        <div><strong>Payment</strong><br />{o.payment_status || 'Not recorded'}{o.payment_provider ? ` (${o.payment_provider})` : ''}</div>
                      </div>
                      <div style={{ marginBottom: '1rem' }}>
                        <strong>Items</strong>
                        {o.items?.map((item) => (
                          <div key={item.id} style={{ display: 'flex', justifyContent: 'space-between', gap: '1rem', padding: '0.35rem 0' }}>
                            <span>{item.product_name} × {item.quantity}</span>
                            <span>₦{Number(item.line_total).toLocaleString()}</span>
                          </div>
                        ))}
                      </div>
                      <div style={{ display: 'flex', flexWrap: 'wrap', gap: '1rem' }}>
                        <span>Subtotal: ₦{Number(o.subtotal).toLocaleString()}</span>
                        <span>Discount: ₦{Number(o.discount_total).toLocaleString()}</span>
                        <span>Transport: ₦{Number(o.customer_paid_transport).toLocaleString()}</span>
                        <strong>Total: ₦{Number(o.total).toLocaleString()}</strong>
                      </div>
                    </div>
                  </td>
                </tr>
              )}
            </Fragment>
          ))}
          {orders.length === 0 && <tr><td colSpan="7">No orders yet.</td></tr>}
        </tbody>
      </table>
    </>
  );
}
