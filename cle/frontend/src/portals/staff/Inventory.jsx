import { useEffect, useState } from 'react';
import { api } from '../../api/client';
import { useAuth } from '../../context/AuthContext';

export default function Inventory() {
  const { user } = useAuth();
  const [items, setItems] = useState([]);
  const [lowStockOnly, setLowStockOnly] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [modal, setModal] = useState(null);
  const [quantity, setQuantity] = useState('');
  const [destination, setDestination] = useState('');
  const [reasonType, setReasonType] = useState('CORRECTION');
  const [submitting, setSubmitting] = useState(false);

  const load = () => {
    const params = {};
    if (lowStockOnly) params.low_stock = 1;
    api.listInventory(params).then((r) => setItems(r.data)).catch((e) => setError(e.message));
  };
  useEffect(() => {
    load();
  }, [lowStockOnly]); // eslint-disable-line react-hooks/exhaustive-deps

  function openTransfer(item) {
    setError('');
    setQuantity('');
    setDestination('');
    setModal({ type: 'TRANSFER', item });
  }

  function openAdjust(item, direction) {
    setError('');
    setQuantity('');
    setReasonType('CORRECTION');
    setModal({ type: direction, item });
  }

  async function submitModal(e) {
    e.preventDefault();
    if (!modal) return;
    setSubmitting(true);
    setError('');
    try {
      if (modal.type === 'TRANSFER') {
        await api.createTransfer({
          product_id: modal.item.product_id,
          quantity: Number(quantity),
          source_location_id: modal.item.location_id,
          destination_location_id: Number(destination),
          reason: 'Requested from Inventory screen',
        });
        setMessage('Transfer requested.');
      } else {
        const res = await api.adjustInventory({
          product_id: modal.item.product_id, location_id: modal.item.location_id,
          quantity: Number(quantity), direction: modal.type, reason_type: reasonType,
        });
        setMessage(res.message);
      }
      setModal(null);
      load();
    } catch (e) {
      setError(e.message);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <>
      <div className="portal-header"><h1>Inventory {user.location_id ? '— your branch' : '— all branches'}</h1></div>
      <div className="filters-row">
        <label style={{ display: 'flex', alignItems: 'center', gap: '0.4rem', fontWeight: 400 }}>
          <input type="checkbox" style={{ width: 'auto' }} checked={lowStockOnly} onChange={(e) => setLowStockOnly(e.target.checked)} />
          Low stock only
        </label>
      </div>
      {error && <p className="error-text">{error}</p>}
      {message && <p style={{ color: 'var(--sage)', fontSize: '0.85rem' }}>{message}</p>}
      <table>
        <thead><tr><th>Product</th><th>Branch</th><th>On hand</th><th>Reorder level</th><th></th></tr></thead>
        <tbody>
          {items.map((i) => (
            <tr key={i.id}>
              <td>{i.product_name} <span className="meta">({i.sku})</span></td>
              <td>{i.location_name}</td>
              <td style={{ color: i.quantity_on_hand <= i.reorder_level ? 'var(--danger)' : 'inherit', fontWeight: 700 }}>{i.quantity_on_hand}</td>
              <td>{i.reorder_level}</td>
              <td style={{ display: 'flex', gap: '0.4rem' }}>
                <button className="btn btn-ghost" onClick={() => openAdjust(i, 'ADD')}>+ Adjust</button>
                <button className="btn btn-ghost" onClick={() => openAdjust(i, 'REMOVE')}>− Adjust</button>
                <button className="btn btn-ghost" onClick={() => openTransfer(i)}>Transfer</button>
              </td>
            </tr>
          ))}
          {items.length === 0 && <tr><td colSpan="5">No inventory records.</td></tr>}
        </tbody>
      </table>

      {modal && (
        <div className="modal-overlay" onClick={() => !submitting && setModal(null)}>
          <form className="modal-content" onSubmit={submitModal} onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h3>{modal.type === 'TRANSFER' ? 'Transfer stock' : `${modal.type === 'ADD' ? 'Add' : 'Remove'} stock`}</h3>
              <button type="button" className="btn btn-ghost" onClick={() => setModal(null)} disabled={submitting}>✕</button>
            </div>
            <p className="meta" style={{ marginBottom: '1.25rem' }}>{modal.item.product_name} · {modal.item.location_name}</p>
            <div className="form-grid">
              <div className="field">
                <label>Quantity</label>
                <input type="number" min="1" step="1" value={quantity} onChange={(e) => setQuantity(e.target.value)} required autoFocus />
              </div>
              {modal.type === 'TRANSFER' ? (
                <div className="field">
                  <label>Destination location ID</label>
                  <input type="number" min="1" step="1" value={destination} onChange={(e) => setDestination(e.target.value)} required />
                </div>
              ) : (
                <div className="field">
                  <label>Reason</label>
                  <select value={reasonType} onChange={(e) => setReasonType(e.target.value)}>
                    <option value="CORRECTION">Correction</option>
                    <option value="DAMAGED">Damaged</option>
                    <option value="LOST">Lost</option>
                    <option value="COUNTING_ERROR">Counting error</option>
                    <option value="EXPIRED">Expired</option>
                    <option value="RETURNED">Returned</option>
                  </select>
                </div>
              )}
            </div>
            {error && <p className="error-text">{error}</p>}
            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.7rem', marginTop: '1.5rem' }}>
              <button type="button" className="btn btn-secondary" onClick={() => setModal(null)} disabled={submitting}>Cancel</button>
              <button className="btn btn-primary" disabled={submitting}>{submitting ? 'Saving…' : 'Confirm'}</button>
            </div>
          </form>
        </div>
      )}
    </>
  );
}
