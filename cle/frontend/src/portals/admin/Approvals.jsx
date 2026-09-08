import { useEffect, useState } from 'react';
import { api } from '../../api/client';

export default function Approvals() {
  const [approvals, setApprovals] = useState([]);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  const load = () => api.listApprovals().then((r) => setApprovals(r.data)).catch((e) => setError(e.message));
  useEffect(() => {
    load();
  }, []);

  async function decide(approval, decision) {
    setError(''); setMessage('');
    try {
      if (approval.type === 'DISCOUNT') {
        const res = await api.decideDiscount({ discount_id: approval.reference_id, decision });
        setMessage(res.message);
      } else {
        setMessage(`${approval.type} decision endpoints are noted as still-to-build in the backend README — this one needs its own /api/.../decide.php following the discounts pattern.`);
      }
      load();
    } catch (e) { setError(e.message); }
  }

  return (
    <>
      <div className="portal-header"><h1>Pending Approvals</h1></div>
      {error && <p className="error-text">{error}</p>}
      {message && <p style={{ color: 'var(--sage)', fontSize: '0.85rem' }}>{message}</p>}
      <table>
        <thead><tr><th>Type</th><th>Requested by</th><th>Branch</th><th>Notes</th><th>Requested</th><th></th></tr></thead>
        <tbody>
          {approvals.map((a) => (
            <tr key={a.id}>
              <td>{a.type.replace(/_/g, ' ')}</td>
              <td>{a.requested_by_name}</td>
              <td>{a.location_name || '—'}</td>
              <td>{a.notes}</td>
              <td>{a.created_at}</td>
              <td style={{ display: 'flex', gap: '0.4rem' }}>
                <button className="btn btn-primary" onClick={() => decide(a, 'APPROVED')}>Approve</button>
                <button className="btn btn-ghost" onClick={() => decide(a, 'REJECTED')}>Reject</button>
              </td>
            </tr>
          ))}
          {approvals.length === 0 && <tr><td colSpan="6">Nothing pending.</td></tr>}
        </tbody>
      </table>
    </>
  );
}
