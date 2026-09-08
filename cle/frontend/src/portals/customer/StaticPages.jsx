export function About() {
  return (
    <div className="container section">
      <h1>About Commanding Liberty Enterprise</h1>
      <p style={{ maxWidth: '60ch', marginTop: '1rem', color: 'var(--ink-soft)' }}>
        Commanding Liberty Enterprise runs multiple bookstore branches, stocking books, Bibles,
        journals, stationery, and school & office supplies — with a shared online storefront and
        preorder system across every location.
      </p>
    </div>
  );
}

export function Contact() {
  const waMessage = encodeURIComponent("Hello Commanding Liberty Enterprise, I have a question.");
  return (
    <div className="container section">
      <h1>Contact Us</h1>
      <p style={{ marginTop: '1rem' }}>
        <a href={`https://wa.me/2340000000000?text=${waMessage}`} target="_blank" rel="noreferrer" className="btn btn-primary">
          Chat with us on WhatsApp
        </a>
      </p>
    </div>
  );
}
