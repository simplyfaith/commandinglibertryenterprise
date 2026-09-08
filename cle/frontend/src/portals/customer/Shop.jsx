import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { api } from '../../api/client';
import ProductTile from './ProductTile';

export default function Shop() {
  const [params, setParams] = useSearchParams();
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);
  const search = params.get('search') || '';
  const status = params.get('status') || '';
  const category = params.get('category') || '';

  useEffect(() => {
    setLoading(true);
    const query = {};
    if (search) query.search = search;
    if (status) query.status = status;
    if (category) query.category = category;
    api.listProducts(query).then((r) => setProducts(r.data)).finally(() => setLoading(false));
  }, [search, status, category]);

  return (
    <section className="section container">
      <div className="section-heading">
        <h2>Shop</h2>
      </div>
      <form
        className="search-bar"
        style={{ marginBottom: '2rem' }}
        onSubmit={(e) => {
          e.preventDefault();
          const term = new FormData(e.target).get('search');
          setParams(term ? { search: term } : {});
        }}
      >
        <input name="search" defaultValue={search} placeholder="Search by title, author, or ISBN…" />
        <button className="btn btn-primary" type="submit">Search</button>
        {(status || category) && <button type="button" className="btn btn-secondary" onClick={() => setParams({})}>Clear filter: {category || status}</button>}
      </form>

      {loading ? <p>Loading catalogue…</p> : (
        <div className="shelf-grid">
          {products.map((p) => <ProductTile key={p.id} product={p} />)}
          {products.length === 0 && <p style={{ padding: '2rem' }}>No products match your search.</p>}
        </div>
      )}
    </section>
  );
}
