import { useEffect, useState } from 'react';
import { api } from '../../api/client';

export default function Locations() {
  const [locations, setLocations] = useState([]);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  const load = () => api.listLocations().then((r) => setLocations(r.data)).catch((e) => setError(e.message));
  useEffect(() => {
    load();
  }, []);

  async function handleCreate(e) {
    e.preventDefault();
    setError(''); setMessage('');
    const form = new FormData(e.target);
    try {
      await api.createLocation({
        code: form.get('code'), name: form.get('name'),
        address: form.get('address'), state: form.get('state'),
        phone: form.get('phone'), email: form.get('email'),
      });
      setMessage('Location created.');
      e.target.reset();
      load();
    } catch (err) { setError(err.message); }
  }

  async function toggleActive(loc) {
    try {
      await api.updateLocation({ id: loc.id, is_active: loc.is_active ? 0 : 1 });
      load();
    } catch (e) { setError(e.message); }
  }

  return (
    <>
      <div className="portal-header"><h1>Locations</h1></div>

      <form className="card" style={{ marginBottom: '2rem' }} onSubmit={handleCreate}>
        <div className="form-grid">
          <div className="field"><label>Code</label><input name="code" placeholder="e.g. ABJ" required /></div>
          <div className="field"><label>Name</label><input name="name" placeholder="e.g. Abuja Branch" required /></div>
          <div className="field span-2"><label>Address</label><input name="address" /></div>
          <div className="field"><label>State</label><input name="state" /></div>
          <div className="field"><label>Phone</label><input name="phone" /></div>
        </div>
        {error && <p className="error-text">{error}</p>}
        {message && <p style={{ color: 'var(--sage)', fontSize: '0.85rem' }}>{message}</p>}
        <button className="btn btn-primary">Add Location</button>
      </form>

      <table>
        <thead><tr><th>Code</th><th>Name</th><th>Manager</th><th>Staff</th><th>Status</th><th></th></tr></thead>
        <tbody>
          {locations.map((l) => (
            <tr key={l.id}>
              <td>{l.code}</td>
              <td>{l.name}</td>
              <td>{l.manager_name || '— unassigned —'}</td>
              <td>{l.staff_count ?? '—'}</td>
              <td>{l.is_active ? 'Active' : 'Inactive'}</td>
              <td><button className="btn btn-ghost" onClick={() => toggleActive(l)}>{l.is_active ? 'Deactivate' : 'Activate'}</button></td>
            </tr>
          ))}
        </tbody>
      </table>
    </>
  );
}
