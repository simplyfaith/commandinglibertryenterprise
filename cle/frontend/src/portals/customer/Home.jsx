import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../api/client';
import ProductTile from './ProductTile';
import HeroSlider from './HeroSlider';

export default function Home() {
  const [featured, setFeatured] = useState([]);
  const [preorders, setPreorders] = useState([]);

  useEffect(() => {
    api.listProducts({ status: 'AVAILABLE' }).then((r) => setFeatured(r.data.slice(0, 8))).catch(() => {});
    api.listProducts({ status: 'PREORDER' }).then((r) => setPreorders(r.data.slice(0, 4))).catch(() => {});
  }, []);

  return (
    <>
      <HeroSlider availableTitlesCount={featured.length + preorders.length} />

      <section className="container" style={{ padding: '3.5rem 1.5rem' }}>
        <div className="section-heading">
          <h2>Featured &amp; In-Stock Titles</h2>
          <Link to="/shop" className="btn btn-secondary">
            View Full Catalog &rarr;
          </Link>
        </div>
        <div className="shelf-grid">
          {featured.map((p) => <ProductTile key={p.id} product={p} />)}
          {featured.length === 0 && (
            <div className="card" style={{ gridColumn: '1/-1', textAlign: 'center', padding: '3rem' }}>
              <p style={{ color: 'var(--text-muted)' }}>Loading products or catalog empty...</p>
            </div>
          )}
        </div>
      </section>

      {preorders.length > 0 && (
        <section className="container" style={{ padding: '0 1.5rem 3.5rem' }}>
          <div className="section-heading">
            <h2>Upcoming Preorders</h2>
            <Link to="/shop?status=PREORDER" className="btn btn-secondary">
              See All Preorders &rarr;
            </Link>
          </div>
          <div className="shelf-grid">
            {preorders.map((p) => <ProductTile key={p.id} product={p} />)}
          </div>
        </section>
      )}

    </>
  );
}
