import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { PrimaryLogo } from '../../components/BrandLogos';
import { BookPatternBackground } from '../../components/BrandPatterns';

export default function PortalLogin() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [location, setLocation] = useState('');
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(e) {
    e.preventDefault();
    setSubmitting(true);
    setError('');
    try {
      await login(email, password, location);
      navigate('/portal');
    } catch (err) {
      setError(err.message || 'Login failed. Check your credentials.');
    } finally {
      setSubmitting(false);
    }
  }

  function fillDemoCredentials(demoEmail) {
    setEmail(demoEmail);
    setPassword('Liberty2026');
  }

  return (
    <div className="login-screen login-screen-staff">
      <BookPatternBackground opacity={0.06} />
      <form className="login-card" onSubmit={handleSubmit}>
        <div className="logo-wrapper">
          <PrimaryLogo width={120} height={120} />
        </div>
        <p className="login-kicker">Branch team access</p>
        <h1>Staff Portal</h1>
        <p className="brand-tagline-text" style={{ fontSize: '1.25rem', color: 'var(--brand-navy-light)', marginBottom: '0.2rem' }}>
          Empowering Minds, Liberating Souls
        </p>
        <p className="subtitle">Commanding Liberty Enterprise Management System</p>

        <div className="field" style={{ textAlign: 'left' }}>
          <label>Staff email</label>
          <input
            name="email"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="staff@commandingliberty.com"
            required
            autoFocus
          />
        </div>

        <div className="field" style={{ textAlign: 'left' }}>
          <label>Password</label>
          <input
            name="password"
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            placeholder="••••••••"
            required
          />
        </div>

        <div className="field" style={{ textAlign: 'left' }}>
          <label>Your branch location</label>
          <input
            name="location"
            value={location}
            onChange={(e) => setLocation(e.target.value)}
            placeholder="Enter your branch, e.g. Ogun"
            required
          />
        </div>

        {error && <p className="error-text" style={{ marginBottom: '1rem' }}>{error}</p>}

        <button className="btn btn-yellow" style={{ width: '100%', fontWeight: 800 }} disabled={submitting}>
          {submitting ? 'Signing in…' : 'Sign In as Staff'}
        </button>

        <div className="role-demo-buttons">
          <p>Quick Demo Credentials</p>
          <div className="role-btn-group">
            <button type="button" onClick={() => fillDemoCredentials('manager@commandingliberty.com')}>Manager</button>
            <button type="button" onClick={() => fillDemoCredentials('staff@commandingliberty.com')}>Staff</button>
          </div>
        </div>
        <a className="login-switch" href="/portal/login/admin">Admin sign in</a>
      </form>
    </div>
  );
}
