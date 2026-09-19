import { useEffect, useState } from 'react';
import { api } from '../../api/client';
import StatusBadge from '../../components/StatusBadge';

export default function Products() {
  const [products, setProducts] = useState([]);
  const [categories, setCategories] = useState([]);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [editing, setEditing] = useState(null);
  const [saving, setSaving] = useState(false);
  const [replacementImage, setReplacementImage] = useState(null);

  const load = () => api.listProducts({}).then((r) => setProducts(r.data)).catch((e) => setError(e.message));
  useEffect(() => {
    load();
    api.listCategories().then((r) => setCategories(r.data)).catch((e) => setError(e.message));
  }, []);

  async function handleCreate(e) {
    e.preventDefault();
    setError(''); setMessage('');
    const form = new FormData(e.target);
    try {
      await api.createProduct(form);
      setMessage('Product created.');
      e.target.reset();
      load();
    } catch (err) {
      const fieldErrors = err.errors
        ? Object.values(err.errors).flat().join(' ')
        : '';
      setError(fieldErrors || err.message || 'Unable to add product.');
    }
  }

  function openEdit(product) {
    setError('');
    setReplacementImage(null);
    setEditing({
      id: product.id,
      name: product.name || '',
      author: product.author || '',
      category_id: product.category_id || '',
      selling_price: product.selling_price || '',
      discount_percent: product.discount_price && Number(product.selling_price) > 0
        ? Math.round((100 - (Number(product.discount_price) / Number(product.selling_price) * 100)) * 100) / 100
        : '',
      status: product.status || 'AVAILABLE',
    });
  }

  async function saveEdit(e) {
    e.preventDefault();
    setSaving(true);
    setError('');
    try {
      const form = new FormData();
      form.append('id', editing.id);
      form.append('name', editing.name);
      form.append('author', editing.author);
      form.append('category_id', editing.category_id);
      form.append('selling_price', editing.selling_price);
      form.append('discount_percent', editing.discount_percent === '' ? '' : editing.discount_percent);
      form.append('status', editing.status);
      if (replacementImage) form.append('image', replacementImage);
      await api.updateProduct(form);
      setMessage('Product updated.');
      setEditing(null);
      load();
    } catch (err) { setError(err.message); }
    finally { setSaving(false); }
  }

  return (
    <>
      <div className="portal-header"><h1>Products</h1></div>

      <form className="card" style={{ marginBottom: '2rem' }} onSubmit={handleCreate}>
        {categories.length === 0 && (
          <p className="error-text">
            No categories are available. An admin must create a category before a book can be added.
          </p>
        )}
        <div className="form-grid">
          <div className="field"><label>SKU</label><input name="sku" required /></div>
          <div className="field"><label>Name</label><input name="name" required /></div>
          <div className="field">
            <label>Category</label>
            <select name="category_id" required defaultValue="">
              <option value="" disabled>Select a category</option>
              {categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
            </select>
          </div>
          <div className="field"><label>Author (optional)</label><input name="author" /></div>
          <div className="field"><label>ISBN (optional)</label><input name="isbn" /></div>
          <div className="field"><label>Cost price (₦)</label><input name="cost_price" type="number" min="0" /></div>
          <div className="field"><label>Selling price (₦)</label><input name="selling_price" type="number" min="0" required /></div>
          <div className="field"><label>Discount (%) optional</label><input name="discount_percent" type="number" min="0" max="100" step="0.01" /></div>
          <div className="field"><label>Initial quantity at your branch</label><input name="initial_quantity" type="number" min="0" step="1" defaultValue="0" required /></div>
          <div className="field"><label>Product image</label><input name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" /></div>
          <div className="field">
            <label>Status</label>
            <select name="status">
              <option value="AVAILABLE">Available</option>
              <option value="PREORDER">Preorder</option>
              <option value="COMING_SOON">Coming soon</option>
              <option value="OUT_OF_STOCK">Out of stock</option>
            </select>
          </div>
          <div className="field"><label>Expected arrival (preorder only)</label><input name="preorder_expected_date" type="date" /></div>
        </div>
        {error && <p className="error-text">{error}</p>}
        {message && <p style={{ color: 'var(--sage)', fontSize: '0.85rem' }}>{message}</p>}
        <button className="btn btn-primary">Add Product</button>
      </form>

      <table>
        <thead><tr><th>SKU</th><th>Product</th><th>Cost</th><th>Selling price</th><th>Status</th><th></th></tr></thead>
        <tbody>
          {products.map((p) => (
            <tr key={p.id}>
              <td>{p.sku}</td>
              <td>
                {p.image_url && <img src={p.image_url} alt="" style={{ width: '42px', height: '56px', objectFit: 'cover', borderRadius: '4px', verticalAlign: 'middle', marginRight: '0.6rem' }} />}
                {p.name}
              </td>
              <td>₦{Number(p.cost_price ?? 0).toLocaleString()}</td>
              <td>₦{Number(p.selling_price).toLocaleString()}</td>
              <td><StatusBadge status={p.status} /></td>
              <td><button type="button" className="btn btn-ghost" onClick={() => openEdit(p)}>Edit</button></td>
            </tr>
          ))}
        </tbody>
      </table>

      {editing && (
        <div className="modal-overlay" onClick={() => !saving && setEditing(null)}>
          <form className="modal-content" onSubmit={saveEdit} onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h3>Edit book</h3>
              <button type="button" className="btn btn-ghost" onClick={() => setEditing(null)} disabled={saving}>✕</button>
            </div>
            <div className="form-grid">
              <div className="field"><label>Book name</label><input value={editing.name} onChange={(e) => setEditing({ ...editing, name: e.target.value })} required /></div>
              <div className="field"><label>Author</label><input value={editing.author} onChange={(e) => setEditing({ ...editing, author: e.target.value })} /></div>
              <div className="field">
                <label>Category</label>
                <select value={editing.category_id} onChange={(e) => setEditing({ ...editing, category_id: e.target.value })} required>
                  <option value="" disabled>Select a category</option>
                  {categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
                </select>
              </div>
              <div className="field"><label>Selling price (₦)</label><input type="number" min="0" value={editing.selling_price} onChange={(e) => setEditing({ ...editing, selling_price: e.target.value })} required /></div>
              <div className="field"><label>Discount (%)</label><input type="number" min="0" max="100" step="0.01" value={editing.discount_percent} onChange={(e) => setEditing({ ...editing, discount_percent: e.target.value })} /></div>
              <div className="field"><label>Replace book image (optional)</label><input type="file" accept="image/jpeg,image/png,image/webp,image/gif" onChange={(e) => setReplacementImage(e.target.files[0] || null)} /></div>
              <div className="field">
                <label>Status</label>
                <select value={editing.status} onChange={(e) => setEditing({ ...editing, status: e.target.value })}>
                  <option value="AVAILABLE">Available</option>
                  <option value="OUT_OF_STOCK">Out of stock</option>
                  <option value="PREORDER">Preorder</option>
                  <option value="COMING_SOON">Coming soon</option>
                  <option value="DISCONTINUED">Discontinued</option>
                </select>
              </div>
            </div>
            {error && <p className="error-text">{error}</p>}
            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.7rem', marginTop: '1.5rem' }}>
              <button type="button" className="btn btn-secondary" onClick={() => setEditing(null)} disabled={saving}>Cancel</button>
              <button className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save changes'}</button>
            </div>
          </form>
        </div>
      )}
    </>
  );
}
