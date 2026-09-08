import { Link } from 'react-router-dom';
import { useCart } from '../../context/CartContext';

export default function Cart() {
  const { items, updateQuantity, removeItem, subtotal } = useCart();

  if (items.length === 0) {
    return (
      <div className="cart-page cart-empty-state">
        <div className="cart-empty-mark">0</div>
        <p className="cart-eyebrow">Your reading list</p>
        <h1>Your cart is empty</h1>
        <p>Find your next book in the <Link to="/shop">shop</Link>.</p>
      </div>
    );
  }

  return (
    <div className="cart-page">
      <div className="cart-heading">
        <div>
          <p className="cart-eyebrow">Your reading list</p>
          <h1>Your Cart</h1>
          <p className="meta">{items.reduce((sum, item) => sum + item.quantity, 0)} items ready for checkout</p>
        </div>
        <Link to="/shop" className="btn btn-secondary">Continue shopping</Link>
      </div>

      <div className="cart-layout">
        <div className="cart-items-panel">
          {items.map(({ product, quantity }) => {
            const unitPrice = Number(product.discount_price || product.selling_price);
            return (
            <div className="cart-line" key={product.id}>
              <div className="cart-product-cover">
                {product.image_url ? <img src={product.image_url} alt="" /> : <span>{product.name.slice(0, 1)}</span>}
              </div>
              <div className="cart-product-info">
                <div className="cart-product-category">{product.category || 'Book'}</div>
                <div className="cart-product-name">{product.name}</div>
                {product.author && <div className="meta">By {product.author}</div>}
                <div className="cart-unit-price">₦{unitPrice.toLocaleString()} each</div>
              </div>
              <div className="cart-quantity-control">
                <button type="button" aria-label={`Decrease ${product.name} quantity`} onClick={() => updateQuantity(product.id, quantity - 1)}>−</button>
                <span>{quantity}</span>
                <button type="button" aria-label={`Increase ${product.name} quantity`} onClick={() => updateQuantity(product.id, quantity + 1)}>+</button>
              </div>
              <div className="cart-line-total">₦{(unitPrice * quantity).toLocaleString()}</div>
              <button type="button" className="cart-remove" onClick={() => removeItem(product.id)}>Remove</button>
            </div>
            );
          })}
        </div>

        <aside className="cart-summary-card">
          <p className="cart-eyebrow">Order summary</p>
          <h2>Ready when you are</h2>
          <div className="cart-summary-row"><span>Items</span><span>{items.reduce((sum, item) => sum + item.quantity, 0)}</span></div>
          <div className="cart-summary-row cart-summary-total"><span>Subtotal</span><strong>₦{Number(subtotal).toLocaleString()}</strong></div>
          <p className="cart-summary-note">Delivery and transport costs are selected at checkout.</p>
          <Link to="/checkout" className="btn btn-primary cart-checkout-button">Proceed to checkout</Link>
        </aside>
      </div>
    </div>
  );
}
