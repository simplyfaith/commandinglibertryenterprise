import { useEffect, useState } from 'react';
import { api } from '../../api/client';
import StatusBadge from '../../components/StatusBadge';

export default function Sliders() {
  const [sliders, setSliders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [editingSlide, setEditingSlide] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [imageFile, setImageFile] = useState(null);

  const loadSliders = () => {
    setLoading(true);
    api.listSliders({ all: 1 })
      .then((res) => setSliders(res.data || []))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadSliders();
  }, []);

  const handleOpenModal = (slide = null) => {
    setEditingSlide(slide || {
      image_url: '',
      display_order: sliders.length + 1,
      is_active: 1
    });
    setImageFile(null);
    setError('');
    setShowModal(true);
  };

  const handleSave = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    try {
      const form = new FormData();
      form.append('display_order', String(editingSlide.display_order || 1));
      form.append('is_active', String(editingSlide.is_active ? 1 : 0));
      if (imageFile) form.append('image', imageFile);

      if (editingSlide.id) {
        form.append('id', String(editingSlide.id));
        await api.updateSlider(form);
      } else {
        await api.createSlider(form);
      }
      setShowModal(false);
      loadSliders();
    } catch (err) {
      setError(err.message || 'Failed to save hero slide');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (id) => {
    if (!window.confirm('Are you sure you want to delete this hero slide?')) return;
    try {
      await api.deleteSlider(id);
      loadSliders();
    } catch (err) {
      alert(err.message || 'Failed to delete slide');
    }
  };

  const handleToggleActive = async (slide) => {
    try {
      await api.updateSlider({
        ...slide,
        is_active: slide.is_active ? 0 : 1
      });
      loadSliders();
    } catch (err) {
      alert(err.message || 'Failed to update slide status');
    }
  };

  return (
    <div>
      <div className="portal-header">
        <div>
          <h1>Hero Slider Banners</h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', margin: 0 }}>
            Manage the hero banner slides displayed on the storefront home page
          </p>
        </div>
        <button className="btn btn-yellow" onClick={() => handleOpenModal()}>
          + Add Hero Slide
        </button>
      </div>

      {loading ? (
        <div className="card" style={{ padding: '2rem' }}>Loading slides…</div>
      ) : (
        <div className="table-responsive">
          <table>
            <thead>
              <tr>
                <th>Order</th>
                <th>Image</th>
                <th>Status</th>
                <th style={{ textAlign: 'right' }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {sliders.map((s) => (
                <tr key={s.id}>
                  <td style={{ fontWeight: 800, textAlign: 'center' }}>#{s.display_order}</td>
                  <td>
                    {s.image_url ? (
                      <img src={s.image_url} alt="Hero promotion" style={{ width: '120px', height: '55px', objectFit: 'cover', borderRadius: '4px', border: '1px solid var(--brand-border)' }} />
                    ) : (
                      <span style={{ fontSize: '0.78rem', color: 'var(--text-light)' }}>No image</span>
                    )}
                  </td>
                  <td>
                    <button
                      className={`badge ${s.is_active ? 'badge-available' : 'badge-out'}`}
                      onClick={() => handleToggleActive(s)}
                      style={{ cursor: 'pointer', border: 'none' }}
                      title="Click to toggle active status"
                    >
                      {s.is_active ? 'ACTIVE' : 'INACTIVE'}
                    </button>
                  </td>
                  <td style={{ textAlign: 'right' }}>
                    <div style={{ display: 'flex', gap: '0.5rem', justifyContent: 'flex-end' }}>
                      <button className="btn btn-secondary" style={{ padding: '0.35rem 0.75rem', fontSize: '0.8rem' }} onClick={() => handleOpenModal(s)}>
                        Edit
                      </button>
                      <button className="btn btn-ghost" style={{ padding: '0.35rem 0.75rem', fontSize: '0.8rem', color: 'var(--danger)' }} onClick={() => handleDelete(s.id)}>
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
              {sliders.length === 0 && (
                <tr>
                  <td colSpan="4" style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-muted)' }}>
                    No hero slides configured. Click "+ Add Hero Slide" to create one.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {/* Edit / Add Modal */}
      {showModal && (
        <div className="modal-overlay" onClick={() => setShowModal(false)}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h3>{editingSlide.id ? 'Edit Hero Slide' : 'Add New Hero Slide'}</h3>
              <button className="btn btn-ghost" onClick={() => setShowModal(false)}>✕</button>
            </div>

            <form onSubmit={handleSave}>
              <div className="field">
                <label>Promotion Image *</label>
                <input
                  type="file"
                  accept="image/jpeg,image/png,image/webp,image/gif"
                  onChange={(e) => setImageFile(e.target.files?.[0] || null)}
                  required={!editingSlide.id && !editingSlide.image_url}
                />
                {editingSlide.image_url && !imageFile && (
                  <small style={{ color: 'var(--text-muted)' }}>Current image will be kept unless a new file is selected.</small>
                )}
              </div>

              <div className="form-grid" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
                <div className="field">
                  <label>Display Order Priority</label>
                  <input
                    type="number"
                    min="1"
                    value={editingSlide.display_order}
                    onChange={(e) => setEditingSlide({ ...editingSlide, display_order: parseInt(e.target.value) || 1 })}
                  />
                </div>
                <div className="field">
                  <label>Status</label>
                  <select
                    value={editingSlide.is_active}
                    onChange={(e) => setEditingSlide({ ...editingSlide, is_active: parseInt(e.target.value) })}
                  >
                    <option value="1">Active (Visible)</option>
                    <option value="0">Inactive (Hidden)</option>
                  </select>
                </div>
              </div>

              {error && <p className="error-text" style={{ marginBottom: '1rem' }}>{error}</p>}

              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.8rem', marginTop: '1.5rem' }}>
                <button type="button" className="btn btn-secondary" onClick={() => setShowModal(false)}>
                  Cancel
                </button>
                <button type="submit" className="btn btn-yellow" disabled={saving}>
                  {saving ? 'Saving Slide…' : 'Save Hero Slide'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
