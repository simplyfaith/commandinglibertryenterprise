import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { PrimaryLogo } from '../../components/BrandLogos';
import { BookPatternBackground } from '../../components/BrandPatterns';

export default function AdminLogin() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(e) {
    e.preventDefault();
    setSubmitting(true);
    setError('');
    try {
      await login(email, password);
      navigate('/portal');
    } catch (err) {
      setError(err.message || 'Login failed. Check your credentials.');
    } finally {
      setSubmitting(false);
    }
  }

  function fillDemoCredentials() {
    setEmail('admin@commandingliberty.com');
    setPassword('Liberty2026');
  }

  return (
    <div className="login-screen login-screen-admin">
      <BookPatternBackground opacity={0.04} />
      <form className="login-card" onSubmit={handleSubmit}>
        <div className="logo-wrapper"><PrimaryLogo width={120} height={120} /></div>
        <p className="login-kicker">Company-wide administration</p>
        <h1>Admin Console</h1>
        <p className="brand-tagline-text" style={{ fontSize: '1.25rem', color: 'var(--brand-navy-light)', marginBottom: '0.2rem' }}>
          Empowering Minds, Liberating Souls
        </p>
        <p className="subtitle">Manage branches, products, approvals, and audit records.</p>

        <div className="field" style={{ textAlign: 'left' }}>
          <label>Admin email</label>
          <input name="email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="admin@commandingliberty.com" required autoFocus />
        </div>
        <div className="field" style={{ textAlign: 'left' }}>
          <label>Password</label>
          <input name="password" type="password" value={password} onChange={(e) => setPassword(e.target.value)} placeholder="••••••••" required />
        </div>

        {error && <p className="error-text" style={{ marginBottom: '1rem' }}>{error}</p>}
        <button className="btn btn-yellow" style={{ width: '100%', fontWeight: 800 }} disabled={submitting}>
          {submitting ? 'Signing in…' : 'Sign In to Admin Console'}
        </button>

        <div className="role-demo-buttons">
          <p>Quick Demo Credentials</p>
          <div className="role-btn-group">
            <button type="button" onClick={fillDemoCredentials}>Super Admin</button>
          </div>
        </div>
        <a className="login-switch" href="/portal/login/staff">Staff sign in</a>
      </form>
    </div>
  );
}