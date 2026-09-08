import { useEffect, useState } from 'react';
import { api } from '../../api/client';

export default function AuditLogs() {
  const [logs, setLogs] = useState([]);
  const [action, setAction] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    api.listAuditLogs(action ? { action } : {}).then((r) => setLogs(r.data)).catch((e) => setError(e.message));
  }, [action]);

  return (
    <>
      <div className="portal-header"><h1>Audit Logs</h1></div>
      <div className="filters-row">
        <input placeholder="Filter by action, e.g. SALE_CREATED" value={action} onChange={(e) => setAction(e.target.value)} />
      </div>
      {error && <p className="error-text">{error}</p>}
      <table>
        <thead><tr><th>When</th><th>User</th><th>Role</th><th>Action</th><th>Record</th><th>Reason</th></tr></thead>
        <tbody>
          {logs.map((l) => (
            <tr key={l.id}>
              <td>{l.created_at}</td>
              <td>{l.user_name || 'system'}</td>
              <td>{l.role_name}</td>
              <td>{l.action}</td>
              <td>{l.record_type} #{l.record_id}</td>
              <td>{l.reason}</td>
            </tr>
          ))}
          {logs.length === 0 && <tr><td colSpan="6">No matching audit entries.</td></tr>}
        </tbody>
      </table>
    </>
  );
}
