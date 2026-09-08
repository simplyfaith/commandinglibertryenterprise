import { Link, useLocation } from 'react-router-dom';
import { useCart } from '../../context/CartContext';
import { BrandLogo } from '../../components/BrandLogos';
import { IconCart, IconUser } from '../../components/BrandIcons';

export default function StorefrontNav() {
  const { items } = useCart();
  const location = useLocation();
  const count = items.reduce((s, i) => s + i.quantity, 0);

  const isActive = (path) => location.pathname === path;
  const isCategoryActive = (category) => location.search.includes(category);

  const menus = [
    {
      label: 'Journals',
      items: ['All Journals', 'Prayer Journals', 'Study Journals', 'Gratitude Journals']
    },
    {
      label: 'Books',
      items: [
        'African Fiction',
        'Finance Books',
        'Business Books',
        'Personal Development Books',
        'Christian Books',
        'Career Books',
        'Foreign Fiction',
        'Memoirs & Biography',
        'Relationship & Marriage',
        'Leadership & Politics',
        'Children & Teens',
        'Book Bundles For Less',
        'Thrifted Books'
      ]
    },
    {
      label: 'Stationery',
      items: ['Writing Materials', 'School Supplies', 'Office Supplies', 'Art & Craft']
    },
    {
      label: 'African Authors',
      items: ['African Authors']
    }
  ];

  const categoryLink = (item) => {
    if (item === 'New Arrivals') return '/shop?sort=newest';
    if (item === 'Preorders') return '/shop?status=PREORDER';
    return `/shop?category=${encodeURIComponent(item)}`;
  };

  return (
    <header className="storefront-nav">
      <div className="container">
        <Link to="/" className="brand-link" aria-label="Commanding Liberty home">
          <BrandLogo width={62} height={62} />
        </Link>
        <nav>
          <Link to="/shop?category=Bundle" className={isCategoryActive('Bundle') ? 'active' : ''}>Bundle</Link>
          {menus.map((menu) => (
            <div className={`nav-menu-item nav-menu-${menu.label.toLowerCase().replace(/\s+/g, '-')}`} key={menu.label}>
              <Link
                to={categoryLink(menu.items[0])}
                className={menu.items.some((item) => isCategoryActive(item)) ? 'active' : ''}
                aria-haspopup="true"
              >
                {menu.label}<span className="nav-chevron" aria-hidden="true" />
              </Link>
              <div className="nav-dropdown" role="menu">
                {menu.items.map((item) => (
                  <Link key={item} to={categoryLink(item)} role="menuitem">{item}</Link>
                ))}
              </div>
            </div>
          ))}
        </nav>
        <div className="nav-actions">
          <Link to="/account" className="btn btn-ghost" style={{ color: '#111111', padding: '0.4rem 0.8rem' }}>
            <IconUser size={18} /> My Account
          </Link>
          <Link to="/cart" className="cart-pill">
            <IconCart size={18} /> Cart · {count}
          </Link>
        </div>
      </div>
    </header>
  );
}
