import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { api } from '../../api/client';
import { useCart } from '../../context/CartContext';
import StatusBadge from '../../components/StatusBadge';

const PAYSTACK_PUBLIC_KEY = 'pk_test_75cf617970048604fd90b790a3ea98f560c1e624';

function loadPaystackScript() {
  return new Promise((resolve, reject) => {
    if (window.PaystackPop) return resolve();
    const source = 'https://js.paystack.co/v1/inline.js';
    const existing = document.querySelector(`script[src="${source}"]`);
    if (existing) {
      existing.addEventListener('load', resolve, { once: true });
      existing.addEventListener('error', () => reject(new Error('Paystack script failed to load')), { once: true });
      return;
    }
    const script = document.createElement('script');
    script.src = source;
    script.async = true;
    script.onload = resolve;
    script.onerror = () => reject(new Error('Paystack script failed to load'));
    document.body.appendChild(script);
  });
}

export default function ProductDetail() {
  const { id } = useParams();
  const [product, setProduct] = useState(null);
  const { addItem } = useCart();
  const [added, setAdded] = useState(false);
  const [showPreorderForm, setShowPreorderForm] = useState(false);
  const [preorderResult, setPreorderResult] = useState(null);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    api.listProducts({}).then((r) => setProduct(r.data.find((p) => String(p.id) === id)));
  }, [id]);

  if (!product) return <div className="container section">Loading…</div>;

  const isPreorder = product.status === 'PREORDER' || product.is_preorder;
  const isOut = product.status === 'OUT_OF_STOCK';

  async function submitPreorder(e) {
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
      const preorderRes = await api.createPreorder({
        product_id: product.id,
        location_id: Number(form.get('location_id')),
        quantity: Number(form.get('quantity')) || 1,
        customer_id: customerRes.data.id,
        payment_option: form.get('payment_option'),
        payment_provider: 'paystack',
        terms_acknowledged: true,
      });
      const preorder = preorderRes.data;
      if (Number(preorder.payment_amount) <= 0) {
        setPreorderResult(preorder);
        return;
      }

      await loadPaystackScript();
      if (!window.PaystackPop) throw new Error('Paystack failed to initialize. Please refresh and try again.');
      const handler = window.PaystackPop.setup({
        key: PAYSTACK_PUBLIC_KEY,
        email: form.get('email') || `${customerRes.data.id}@guest.local`,
        amount: Math.round(Number(preorder.payment_amount) * 100),
        currency: 'NGN',
        ref: `CLE-PO-${preorder.id}-${Date.now()}`,
        label: `Preorder ${preorder.preorder_code}`,
        metadata: { custom_fields: [{ display_name: 'Preorder Code', variable_name: 'preorder_code', value: preorder.preorder_code }] },
        callback: function(response) {
          api.verifyPreorderPayment({ preorder_id: preorder.id, provider: 'paystack', provider_reference: response.reference })
            .then(() => setPreorderResult({ ...preorder, amount_paid: preorder.payment_amount }))
            .catch((err) => setError(err.message || 'Payment verification failed.'));
        },
        onClose: function() {
          setError('Payment was cancelled. Your preorder is awaiting payment.');
        },
      });
      handler.openIframe();
    } catch (err) {
      setError(err.message);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <section className="pdp container">
      <div className="cover-large">
        {product.image_url && <img src={product.image_url} alt={product.name} />}
      </div>
      <div>
        <StatusBadge status={product.status} />
        <h1 style={{ marginTop: '0.6rem' }}>{product.name}</h1>
        {product.author && <div className="meta">by {product.author}{product.publisher ? ` · ${product.publisher}` : ''}</div>}
        {product.isbn && <div className="meta">ISBN {product.isbn}</div>}

        <div className="price-block">
          ₦{Number(product.discount_price || product.selling_price).toLocaleString()}
          {product.discount_price && (
            <span style={{ fontSize: '1rem', color: 'var(--ink-soft)', textDecoration: 'line-through', marginLeft: '0.6rem' }}>
              ₦{Number(product.selling_price).toLocaleString()}
            </span>
          )}
        </div>

        <p>{product.description}</p>

        {isPreorder && !preorderResult && (
          <div className="preorder-note">
            This is a <strong>preorder</strong>{product.preorder_expected_date ? ` — expected arrival ${product.preorder_expected_date}` : ''}.
            You may pay in full, pay a deposit, or pay when stock arrives, depending on what's offered at checkout.
            Preorder stock is reserved separately and is not counted against currently available inventory.
          </div>
        )}

        {preorderResult && (
          <div className="preorder-note">
            Preorder <strong>{preorderResult.preorder_code}</strong> created. Total: ₦{Number(preorderResult.total).toLocaleString()},
            amount paid so far: ₦{Number(preorderResult.amount_paid).toLocaleString()}.
          </div>
        )}

        {!isPreorder && !isOut && (
          <div className="actions">
            <button
              className="btn btn-primary"
              onClick={() => { addItem(product, 1); setAdded(true); }}
            >
              {added ? 'Added ✓' : 'Add to Cart'}
            </button>
          </div>
        )}

        {isPreorder && !preorderResult && !showPreorderForm && (
          <div className="actions">
            <button className="btn btn-primary" onClick={() => setShowPreorderForm(true)}>Order Now</button>
          </div>
        )}

        {isOut && (
          <div className="actions">
            <button className="btn btn-secondary" disabled>Notify Me (coming soon)</button>
          </div>
        )}

        {showPreorderForm && !preorderResult && (
          <form className="card" style={{ marginTop: '1.5rem' }} onSubmit={submitPreorder}>
            <div className="field"><label>Full name</label><input name="full_name" required /></div>
            <div className="field"><label>Phone</label><input name="phone" required /></div>
            <div className="field"><label>Email (optional)</label><input name="email" type="email" /></div>
            <div className="field"><label>Quantity</label><input name="quantity" type="number" min="1" defaultValue="1" /></div>
            <LocationSelect />
            <div className="field">
              <label>Payment option</label>
              <select name="payment_option" defaultValue="FULL">
                <option value="FULL">Pay in full now</option>
                <option value="ON_ARRIVAL">Pay when stock arrives</option>
              </select>
            </div>
            <label style={{ display: 'flex', gap: '0.5rem', alignItems: 'center', fontWeight: 400, marginBottom: '1rem' }}>
              <input type="checkbox" required style={{ width: 'auto' }} />
              I understand that this is a preorder and agree to the preorder terms.
            </label>
            {error && <p className="error-text">{error}</p>}
            <button className="btn btn-primary" disabled={submitting}>{submitting ? 'Opening secure payment…' : 'Order & Pay Now'}</button>
          </form>
        )}
      </div>
    </section>
  );
}

function LocationSelect() {
  const [locations, setLocations] = useState([]);
  useEffect(() => { api.listLocations().then((r) => setLocations(r.data)); }, []);
  return (
    <select name="location_id" required hidden aria-hidden="true" tabIndex={-1} value={locations[0]?.id || ''} onChange={() => {}}>
      <option value="">Select a branch…</option>
      {locations.map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
    </select>
  );
}
