export default function StatusBadge({ status }) {
  const map = {
    AVAILABLE: 'badge-available',
    PREORDER: 'badge-preorder',
    OUT_OF_STOCK: 'badge-out',
    DISCONTINUED: 'badge-out',
    COMING_SOON: 'badge-preorder',
    PENDING: 'badge-pending',
    CONFIRMED: 'badge-available',
    PROCESSING: 'badge-pending',
    DISPATCHED: 'badge-available',
    DELIVERED: 'badge-available',
    CANCELLED: 'badge-out',
    PAID: 'badge-available',
    FAILED: 'badge-out',
  };
  return <span className={`badge ${map[status] || 'badge-pending'}`}>{status?.replace(/_/g, ' ')}</span>;
}
