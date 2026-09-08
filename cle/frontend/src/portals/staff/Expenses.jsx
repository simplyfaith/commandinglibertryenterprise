import { useEffect, useState } from 'react';
import { api } from '../../api/client';
import { useAuth } from '../../context/AuthContext';
import StatusBadge from '../../components/StatusBadge';

export default function Expenses() {
  const { user } = useAuth();
  const [expenses, setExpenses] = useState([]);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  const load = () => api.listExpenses().then((r) => setExpenses(r.data)).catch((e) => setError(e.message));
  useEffect(() => {
    load();
  }, []);

  async function handleSubmit(e) {
    e.preventDefault();
    setError(''); setMessage('');
    const form = new FormData(e.target);
    try {
      const res = await api.createExpense({
        category: form.get('category'),
        amount: Number(form.get('amount')),
        description: form.get('description'),
        location_id: user.location_id,
        spent_at: form.get('spent_at'),
      });
      setMessage(res.message);
      e.target.reset();
      load();
    } catch (err) { setError(err.message); }
  }

  return (
    <>
      <div className="portal-header"><h1>Expenses</h1></div>
      <form className="card" style={{ marginBottom: '2rem' }} onSubmit={handleSubmit}>
        <div className="form-grid">
          <div className="field">
            <label>Category</label>
            <select name="category">
              {['TRANSPORT', 'PRINTING', 'PACKAGING', 'DELIVERY', 'UTILITIES', 'STOCK_PURCHASE', 'OTHER'].map((c) => <option key={c}>{c}</option>)}
            </select>
          </div>
          <div className="field"><label>Amount (₦)</label><input name="amount" type="number" min="0" required /></div>
          <div className="field"><label>Date</label><input name="spent_at" type="date" required /></div>
          <div className="field span-2"><label>Description</label><input name="description" /></div>
        </div>
        {error && <p className="error-text">{error}</p>}
        {message && <p style={{ color: 'var(--sage)', fontSize: '0.85rem' }}>{message}</p>}
        <button className="btn btn-primary">Record Expense</button>
      </form>

      <table>
        <thead><tr><th>Category</th><th>Amount</th><th>Branch</th><th>Recorded by</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
          {expenses.map((ex) => (
            <tr key={ex.id}>
              <td>{ex.category}</td>
              <td>₦{Number(ex.amount).toLocaleString()}</td>
              <td>{ex.location_name}</td>
              <td>{ex.recorded_by_name}</td>
              <td><StatusBadge status={ex.approval_status} /></td>
              <td>{ex.spent_at}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </>
  );
}
