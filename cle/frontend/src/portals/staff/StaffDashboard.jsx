import { useEffect, useState } from 'react';
import { api } from '../../api/client';

export default function StaffDashboard() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.dashboard()
      .then((res) => setData(res.data))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return <div className="card" style={{ padding: '2rem' }}>Loading Staff Dashboard...</div>;
  }

  return (
    <div>
      <div className="portal-header">
        <div>
          <h1>Staff Dashboard</h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', margin: 0 }}>
            Your branch activity and assigned work
          </p>
        </div>
      </div>

      <div className="kpi-grid">
        <div className="kpi-card yellow-accent">
          <span className="kpi-label">Today's Sales</span>
          <span className="kpi-value">{data?.todays_sales_count || 0}</span>
          <span className="kpi-trend">Transactions completed today</span>
        </div>
        <div className="kpi-card">
          <span className="kpi-label">Sales Revenue</span>
          <span className="kpi-value">₦{Number(data?.todays_sales_revenue || 0).toLocaleString()}</span>
          <span className="kpi-trend">Your revenue today</span>
        </div>
        <div className="kpi-card red-accent">
          <span className="kpi-label">Pending Orders</span>
          <span className="kpi-value">{data?.pending_orders || 0}</span>
          <span className="kpi-trend">Orders awaiting action</span>
        </div>
        <div className="kpi-card green-accent">
          <span className="kpi-label">Low Stock Alerts</span>
          <span className="kpi-value">{data?.low_stock_alerts || 0}</span>
          <span className="kpi-trend">Items at your location</span>
        </div>
      </div>

      <div className="card">
        <h3 style={{ marginBottom: '0.75rem', fontSize: '1.1rem' }}>Your Work Queue</h3>
        <p style={{ color: 'var(--text-muted)', margin: 0 }}>
          Pending preorders: <strong>{data?.pending_preorders || 0}</strong>
        </p>
      </div>
    </div>
  );
}
