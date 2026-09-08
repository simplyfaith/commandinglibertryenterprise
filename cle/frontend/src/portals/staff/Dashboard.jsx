import { useEffect, useState } from 'react';
import { api } from '../../api/client';
import StatusBadge from '../../components/StatusBadge';

export default function Dashboard() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.dashboard()
      .then((res) => setData(res.data))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return <div className="card" style={{ padding: '2rem' }}>Loading Enterprise Dashboard…</div>;
  }

  const kpis = data?.kpis || {};
  const recentSales = data?.recent_sales || [];
  const lowStock = data?.low_stock || [];
  const pendingApprovals = data?.pending_approvals || [];
  const inventoryByLocation = data?.inventory_by_location || [];
  const mainInventoryTotal = data?.main_inventory_total || { total_books: 0, different_books: 0 };
  const recentExpenses = data?.recent_expenses || [];
  const totalExpensesRecorded = data?.total_expenses_recorded || 0;
  const salesChart = kpis.sales_chart || [];
  const chartMax = Math.max(...salesChart.map((point) => Number(point.revenue || 0)), 1);

  return (
    <div>
      <div className="portal-header">
        <div>
          <h1>Enterprise Dashboard</h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', margin: 0 }}>
            Real-time branch activity, stock alerts, and revenue metrics
          </p>
        </div>
      </div>

      <div className="kpi-grid">
        <div className="kpi-card yellow-accent">
          <span className="kpi-label">Today's Sales Revenue</span>
          <span className="kpi-value">₦{Number(kpis.today_sales_revenue || 0).toLocaleString()}</span>
          <span className="kpi-trend" style={{ color: 'var(--success)' }}>
            ✓ {kpis.today_sales_count || 0} Transactions today
          </span>
        </div>

        <div className="kpi-card">
          <span className="kpi-label">Weekly Sales</span>
          <span className="kpi-value">₦{Number(kpis.week_sales_revenue || 0).toLocaleString()}</span>
          <span className="kpi-trend" style={{ color: 'var(--brand-navy-muted)' }}>
            {kpis.week_sales_count || 0} Transactions this week
          </span>
        </div>

        <div className="kpi-card">
          <span className="kpi-label">Monthly Sales</span>
          <span className="kpi-value">₦{Number(kpis.month_sales_revenue || 0).toLocaleString()}</span>
          <span className="kpi-trend" style={{ color: 'var(--brand-navy-muted)' }}>
            {kpis.month_sales_count || 0} Transactions this month
          </span>
        </div>

        <div className="kpi-card">
          <span className="kpi-label">Active Orders</span>
          <span className="kpi-value">{kpis.active_orders_count || 0}</span>
          <span className="kpi-trend" style={{ color: 'var(--brand-navy-muted)' }}>
            Processing &amp; dispatching
          </span>
        </div>

        <div className="kpi-card red-accent">
          <span className="kpi-label">Low Stock Warnings</span>
          <span className="kpi-value">{lowStock.length}</span>
          <span className="kpi-trend" style={{ color: 'var(--danger)' }}>
            Items requiring reorder
          </span>
        </div>

        <div className="kpi-card green-accent">
          <span className="kpi-label">Pending Approvals</span>
          <span className="kpi-value">{pendingApprovals.length}</span>
          <span className="kpi-trend" style={{ color: 'var(--warning)' }}>
            Awaiting manager action
          </span>
        </div>
      </div>

      <div className="card" style={{ marginBottom: '2rem' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', gap: '1rem', marginBottom: '1rem' }}>
          <div>
            <h3 style={{ marginBottom: '0.25rem', fontSize: '1.1rem' }}>Sales graph</h3>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.85rem', margin: 0 }}>Confirmed order totals and completed sales this week.</p>
          </div>
          <strong>₦{Number(kpis.month_sales_revenue || 0).toLocaleString()} this month</strong>
        </div>
        <div style={{ display: 'flex', alignItems: 'flex-end', gap: '0.75rem', height: '180px', padding: '1rem 0.5rem 0', borderBottom: '1px solid var(--brand-border)' }}>
          {salesChart.map((point) => (
            <div key={point.date} title={`${point.label}: ₦${Number(point.revenue || 0).toLocaleString()}`} style={{ flex: 1, height: '100%', display: 'flex', flexDirection: 'column', justifyContent: 'flex-end', alignItems: 'center', gap: '0.45rem' }}>
              <div style={{ width: '100%', maxWidth: '56px', height: `${Math.max((Number(point.revenue || 0) / chartMax) * 100, point.revenue ? 5 : 1)}%`, background: 'var(--brand-yellow)', borderRadius: '4px 4px 0 0', minHeight: '2px' }} />
              <span style={{ color: 'var(--text-muted)', fontSize: '0.75rem' }}>{point.label}</span>
            </div>
          ))}
        </div>
      </div>

      <div className="card" style={{ marginBottom: '2rem' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '1rem', marginBottom: '1rem' }}>
          <div>
            <h3 style={{ marginBottom: '0.25rem', fontSize: '1.1rem' }}>Books by location</h3>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.85rem', margin: 0 }}>Current stock across every active branch and the main total.</p>
          </div>
          <div style={{ textAlign: 'right' }}>
            <strong style={{ fontSize: '1.35rem' }}>{Number(mainInventoryTotal.total_books || 0).toLocaleString()}</strong>
            <div className="meta">Main / company total</div>
          </div>
        </div>
        <div className="table-responsive">
          <table>
            <thead><tr><th>Location</th><th>Total books</th><th>Different titles</th></tr></thead>
            <tbody>
              {inventoryByLocation.map((location) => (
                <tr key={location.id}>
                  <td style={{ fontWeight: 700 }}>{location.name}</td>
                  <td>{Number(location.total_books || 0).toLocaleString()}</td>
                  <td>{Number(location.different_books || 0).toLocaleString()}</td>
                </tr>
              ))}
              <tr style={{ fontWeight: 800, borderTop: '2px solid var(--brand-border)' }}>
                <td>Main / company total</td>
                <td>{Number(mainInventoryTotal.total_books || 0).toLocaleString()}</td>
                <td>{Number(mainInventoryTotal.different_books || 0).toLocaleString()}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div className="card" style={{ marginBottom: '2rem' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '1rem', marginBottom: '1rem' }}>
          <div>
            <h3 style={{ marginBottom: '0.25rem', fontSize: '1.1rem' }}>Expense ledger</h3>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.85rem', margin: 0 }}>Recent expenses submitted by staff and branch teams.</p>
          </div>
          <div style={{ textAlign: 'right' }}>
            <strong style={{ fontSize: '1.35rem' }}>₦{Number(totalExpensesRecorded).toLocaleString()}</strong>
            <div className="meta">Total recorded</div>
          </div>
        </div>
        <div className="table-responsive">
          <table>
            <thead><tr><th>Category</th><th>Amount</th><th>Branch</th><th>Recorded by</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
              {recentExpenses.map((expense) => (
                <tr key={expense.id}>
                  <td>{expense.category}</td>
                  <td style={{ fontWeight: 700 }}>₦{Number(expense.amount).toLocaleString()}</td>
                  <td>{expense.location_name}</td>
                  <td>{expense.recorded_by_name}</td>
                  <td><StatusBadge status={expense.approval_status} /></td>
                  <td>{expense.spent_at}</td>
                </tr>
              ))}
              {recentExpenses.length === 0 && <tr><td colSpan="6" style={{ color: 'var(--text-muted)', textTransform: 'none' }}>No expenses recorded yet.</td></tr>}
            </tbody>
          </table>
        </div>
      </div>

      <div className="dashboard-bottom-grid" style={{ display: 'grid', gridTemplateColumns: '1.2fr 0.8fr', gap: '1.5rem', marginBottom: '2rem' }}>
        <div className="card">
          <h3 style={{ marginBottom: '1rem', fontSize: '1.1rem' }}>Recent Storefront &amp; Counter Sales</h3>
          <div className="table-responsive">
            <table>
              <thead>
                <tr>
                  <th>Sale Ref</th>
                  <th>Branch</th>
                  <th>Items</th>
                  <th>Discount</th>
                  <th>Amount</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {recentSales.map((s) => (
                  <tr key={s.id}>
                    <td style={{ fontWeight: 700 }}>#{s.sale_number}</td>
                    <td>{s.location_name || 'Main Branch'}</td>
                    <td>{s.item_count} items</td>
                    <td>{Number(s.discount_amount || 0) > 0 ? `${s.discount_type === 'PERCENTAGE' ? `${s.discount_value}%` : 'Fixed'} off (₦${Number(s.discount_amount).toLocaleString()})` : '—'}</td>
                    <td style={{ fontWeight: 700 }}>₦{Number(s.total_amount).toLocaleString()}</td>
                    <td><StatusBadge status={s.status} /></td>
                  </tr>
                ))}
                {recentSales.length === 0 && (
                  <tr><td colSpan="6" style={{ textTransform: 'none', color: 'var(--text-muted)' }}>No sales recorded today yet.</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </div>

        <div className="card">
          <h3 style={{ marginBottom: '1rem', fontSize: '1.1rem' }}>Attention Required</h3>

          {pendingApprovals.length > 0 && (
            <div style={{ marginBottom: '1.5rem' }}>
              <h4 style={{ fontSize: '0.85rem', color: 'var(--warning)', textTransform: 'uppercase', marginBottom: '0.5rem' }}>
                Pending Manager Approvals ({pendingApprovals.length})
              </h4>
              {pendingApprovals.map((ap) => (
                <div key={ap.id} style={{ background: 'var(--warning-bg)', padding: '0.75rem', borderRadius: 'var(--radius-sm)', marginBottom: '0.5rem', border: '1px solid rgba(245,158,11,0.3)' }}>
                  <div style={{ fontWeight: 700, fontSize: '0.88rem' }}>{ap.request_type}</div>
                  <div style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>Requested by {ap.requester_name}</div>
                </div>
              ))}
            </div>
          )}

          <div>
            <h4 style={{ fontSize: '0.85rem', color: 'var(--danger)', textTransform: 'uppercase', marginBottom: '0.5rem' }}>
              Low Stock Items ({lowStock.length})
            </h4>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
              {lowStock.slice(0, 5).map((ls) => (
                <div key={ls.id} style={{ display: 'flex', justifyContent: 'space-between', padding: '0.6rem', background: 'var(--brand-bg-light)', borderRadius: 'var(--radius-sm)' }}>
                  <span style={{ fontWeight: 600, fontSize: '0.85rem' }}>{ls.product_name}</span>
                  <span className="badge badge-out">{ls.quantity_on_hand} left</span>
                </div>
              ))}
              {lowStock.length === 0 && (
                <p style={{ fontSize: '0.85rem', color: 'var(--success)' }}>✓ All product stock levels healthy across branches.</p>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
