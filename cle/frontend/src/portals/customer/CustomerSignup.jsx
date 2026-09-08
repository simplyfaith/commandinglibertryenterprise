import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useCustomerAuth } from '../../context/CustomerAuthContext';

export default function CustomerSignup() {
  const { register } = useCustomerAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({ full_name: '', email: '', phone: '', password: '', default_address: '' });
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  async function submit(event) {
    event.preventDefault();
    setSubmitting(true);
    setError('');
    try { await register(form); navigate('/account'); }
    catch (err) { setError(err.message); }
    finally { setSubmitting(false); }
  }

  return <div className="cart-page"><div className="account-auth-card card">
    <p className="cart-eyebrow">Customer account</p><h1>Create your account</h1>
    <p className="meta">Save your details and keep your order history in one place.</p>
    <form onSubmit={submit}>
      <div className="field"><label>Full name</label><input required value={form.full_name} onChange={(e) => setForm({ ...form, full_name: e.target.value })} /></div>
      <div className="field"><label>Email</label><input type="email" required value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></div>
      <div className="field"><label>Phone</label><input required value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} /></div>
      <div className="field"><label>Default address</label><textarea rows="3" value={form.default_address} onChange={(e) => setForm({ ...form, default_address: e.target.value })} /></div>
      <div className="field"><label>Password</label><input type="password" minLength="8" required value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} /></div>
      {error && <p className="error-text">{error}</p>}
      <button className="btn btn-primary" disabled={submitting}>{submitting ? 'Creating...' : 'Create account'}</button>
    </form>
    <p className="meta">Already registered? <Link to="/account/login">Sign in</Link></p>
  </div></div>;
}
