import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useCart } from '../../context/CartContext';
import StatusBadge from '../../components/StatusBadge';
import { LogoMark } from '../../components/BrandLogos';

export default function ProductTile({ product }) {
  const { addItem } = useCart();
  const navigate = useNavigate();
  const [added, setAdded] = useState(false);
  const hasDiscount = product.discount_price && Number(product.discount_price) < Number(product.selling_price);
  const discountPercent = hasDiscount
    ? Math.round((100 - (Number(product.discount_price) / Number(product.selling_price) * 100)) * 100) / 100
    : 0;

  function addToCart(e) {
    e.preventDefault();
    e.stopPropagation();
    addItem(product);
    setAdded(true);
    setTimeout(() => setAdded(false), 1600);
  }

  function orderPreorder(e) {
    e.preventDefault();
    e.stopPropagation();
    navigate(`/product/${product.id}`);
  }

  return (
    <Link to={`/product/${product.id}`} className="product-tile">
      <div className="cover-box">
        {hasDiscount && <span className="product-discount-badge">-{discountPercent}%</span>}
        {product.image_url ? (
          <img src={product.image_url} alt={product.name} />
        ) : (
          <div className="cover-placeholder">
            <LogoMark width={42} height={42} color="#060C3B" />
            <span style={{ fontSize: '0.75rem', fontWeight: 700, color: 'var(--brand-navy)' }}>
              {product.author || 'Commanding Liberty'}
            </span>
          </div>
        )}
      </div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.4rem' }}>
        <StatusBadge status={product.status} />
        {product.category && (
          <span style={{ fontSize: '0.72rem', color: 'var(--text-muted)', fontWeight: 600 }}>{product.category}</span>
        )}
      </div>
      <div className="title">{product.name}</div>
      {product.author && <div className="author">By {product.author}</div>}
      <div className="price-row">
        <div className="price">
          {hasDiscount && <span className="was">₦{Number(product.selling_price).toLocaleString()}</span>}
          ₦{Number(product.discount_price || product.selling_price).toLocaleString()}
        </div>
      </div>
      {product.status !== 'OUT_OF_STOCK' && product.status !== 'PREORDER' && (
        <button type="button" className="btn btn-primary" style={{ width: '100%', marginTop: '0.75rem' }} onClick={addToCart}>
          {added ? 'Added to cart ✓' : 'Add to cart'}
        </button>
      )}
      {product.status === 'PREORDER' && (
        <button type="button" className="btn btn-primary" style={{ width: '100%', marginTop: '0.75rem' }} onClick={orderPreorder}>
          Order Now
        </button>
      )}
    </Link>
  );
}
