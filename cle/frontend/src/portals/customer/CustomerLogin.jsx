import { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useCustomerAuth } from '../../context/CustomerAuthContext';

export default function CustomerLogin() {
  const { login } = useCustomerAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [form, setForm] = useState({ email: '', password: '' });
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  async function submit(event) {
    event.preventDefault();
    setSubmitting(true);
    setError('');
    try {
      await login(form);
      const next = new URLSearchParams(location.search).get('next') || '/account';
      navigate(next);
    } catch (err) {
      setError(err.message);
    } finally { setSubmitting(false); }
  }

  return <div className="cart-page"><div className="account-auth-card card">
    <p className="cart-eyebrow">Customer account</p><h1>Welcome back</h1>
    <p className="meta">Sign in to view your orders and account dashboard.</p>
    <form onSubmit={submit}>
      <div className="field"><label>Email</label><input type="email" required value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></div>
      <div className="field"><label>Password</label><input type="password" required value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} /></div>
      {error && <p className="error-text">{error}</p>}
      <button className="btn btn-primary" disabled={submitting}>{submitting ? 'Signing in...' : 'Sign in'}</button>
    </form>
    <p className="meta">New here? <Link to="/account/signup">Create an account</Link></p>
  </div></div>;
}
