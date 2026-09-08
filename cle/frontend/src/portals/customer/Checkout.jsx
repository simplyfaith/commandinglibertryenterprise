import { useEffect, useState } from 'react';
import { api } from '../../api/client';
import { useCart } from '../../context/CartContext';

const PAYSTACK_PUBLIC_KEY = 'pk_test_75cf617970048604fd90b790a3ea98f560c1e624';

function loadPaystackScript() {
  return new Promise((resolve, reject) => {
    if (window.PaystackPop) return resolve();

    const existing = document.querySelector('script[src="https://js.paystack.co/v1/inline.js"]');
    if (existing) {
      existing.addEventListener('load', () => resolve(), { once: true });
      existing.addEventListener('error', () => reject(new Error('Paystack script failed to load')), { once: true });
      return;
    }

    const script = document.createElement('script');
    script.src = 'https://js.paystack.co/v1/inline.js';
    script.async = true;
    script.onload = () => resolve();
    script.onerror = () => reject(new Error('Paystack script failed to load'));
    document.body.appendChild(script);
  });
}

export default function Checkout() {
  const { items, subtotal, clear } = useCart();
  const [locations, setLocations] = useState([]);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [result, setResult] = useState(null);

  useEffect(() => {
    api.listLocations().then((r) => setLocations(r.data || [])).catch(() => setLocations([]));
  }, []);

  async function handleSubmit(e) {
    e.preventDefault();
    setSubmitting(true);
    setError('');
    const form = new FormData(e.target);
    try {
      const customerRes = await api.createCustomer({
        full_name: form.get('full_name'),
        phone: form.get('phone'),
        email: form.get('email') || undefined,
      });

      const defaultLocationId = Number(locations[0]?.id || 1);
      const orderRes = await api.createOrder({
        customer_id: customerRes.data.id,
        location_id: defaultLocationId,
        delivery_address: form.get('delivery_address'),
        payment_provider: 'paystack',
        items: items.map((i) => ({ product_id: i.product.id, quantity: i.quantity })),
      });

      const customerRecord = {
        id: customerRes.data.id,
        full_name: form.get('full_name'),
        phone: form.get('phone'),
        email: form.get('email') || '',
      };

      localStorage.setItem('cle_customer_id', String(customerRes.data.id));
      localStorage.setItem('cle_customer', JSON.stringify(customerRecord));

      await loadPaystackScript();
      if (!window.PaystackPop) {
        throw new Error('Paystack failed to initialize. Please refresh and try again.');
      }

      const handler = window.PaystackPop.setup({
        key: PAYSTACK_PUBLIC_KEY,
        email: form.get('email') || `${customerRes.data.id}@guest.local`,
        amount: Math.round(Number(orderRes.data.total) * 100),
        currency: 'NGN',
        ref: `CLE-${orderRes.data.id}-${Date.now()}`,
        label: `Order ${orderRes.data.order_code}`,
        metadata: {
          custom_fields: [{
            display_name: 'Order Code',
            variable_name: 'order_code',
            value: orderRes.data.order_code,
          }],
        },
        callback: function(response) {
          api.verifyPayment({
            order_id: orderRes.data.id,
            provider: 'paystack',
            provider_reference: response.reference,
          })
            .then(() => {
              setResult(orderRes.data);
              clear();
            })
            .catch((err) => {
              setError(err.message || 'Payment verification failed');
            });
        },
        onClose: function() {
          setResult(orderRes.data);
          clear();
        },
      });

      handler.openIframe();
    } catch (err) {
      setError(err.message || 'Unexpected server response');
    } finally {
      setSubmitting(false);
    }
  }

  if (result) {
    return (
      <div className="cart-page">
        <h1>Order placed 🎉</h1>
        <p>Your order <strong>{result.order_code}</strong> has been created. Total due: ₦{Number(result.total).toLocaleString()}.</p>
        <p>Payment integration is wired to <code>/api/orders/verify-payment.php</code> — an order is only marked confirmed once the payment provider verifies it server-side.</p>
      </div>
    );
  }

  return (
    <div className="cart-page">
      <h1>Checkout</h1>
      <form className="checkout-grid" onSubmit={handleSubmit}>
        <div className="card">
          <div className="field"><label>Full name</label><input name="full_name" required /></div>
          <div className="field"><label>Phone</label><input name="phone" required /></div>
          <div className="field"><label>Email (optional)</label><input name="email" type="email" /></div>
          <div className="field">
            <label>Delivery address</label>
            <textarea name="delivery_address" rows="3" required placeholder="Enter the full delivery address" />
          </div>
          <p className="field" style={{ color: 'var(--text-muted)' }}>
            Standard transportation charges will be communicated separately.
          </p>
          {error && <p className="error-text">{error}</p>}
          <button className="btn btn-primary" disabled={submitting || items.length === 0 || locations.length === 0}>
            {submitting ? 'Placing order…' : 'Place Order'}
          </button>
        </div>
        <div className="card">
          <h3 style={{ marginBottom: '1rem' }}>Order summary</h3>
          {items.map(({ product, quantity }) => (
            <div key={product.id} style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem', fontSize: '0.9rem' }}>
              <span>{product.name} × {quantity}</span>
              <span>₦{Number((product.discount_price || product.selling_price) * quantity).toLocaleString()}</span>
            </div>
          ))}
          <div className="cart-summary" style={{ fontSize: '1.1rem' }}>
            <span>Subtotal</span>
            <span>₦{Number(subtotal).toLocaleString()}</span>
          </div>
        </div>
      </form>
    </div>
  );
}
