import { SubmarkLogo } from '../../components/BrandLogos';
import {
  IconWhatsApp,
  IconInstagram,
  IconFacebook,
  IconX,
  IconYouTube,
  IconTikTok,
  IconLinkedIn
} from '../../components/BrandIcons';

export default function StorefrontFooter() {
  const waMessage = encodeURIComponent("Hello Commanding Liberty Enterprise, I'd like to inquire about your books and branch services.");

  return (
    <>
      <footer className="storefront-footer">
        <div className="container">
          <div className="footer-grid">
            <div className="col">
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.8rem', marginBottom: '0.8rem' }}>
                <SubmarkLogo width={40} height={40} />
                <div>
                  <h4 style={{ margin: 0 }}>Commanding Liberty Enterprise</h4>
                  <p className="brand-tagline-text" style={{ color: '#FFDD00', fontSize: '1.1rem', margin: 0 }}>
                    Empowering Minds, Liberating Souls
                  </p>
                </div>
              </div>
              <p>
                Your trusted bookstore chain for Bibles, academic books, literature, stationery,
                and educational resources across all branch locations.
              </p>
              <div className="social-links-row">
                <a href={`https://wa.me/2340000000000?text=${waMessage}`} target="_blank" rel="noreferrer" className="social-icon-btn" title="WhatsApp">
                  <IconWhatsApp size={18} />
                </a>
                <a href="https://instagram.com" target="_blank" rel="noreferrer" className="social-icon-btn" title="Instagram">
                  <IconInstagram size={18} />
                </a>
                <a href="https://facebook.com" target="_blank" rel="noreferrer" className="social-icon-btn" title="Facebook">
                  <IconFacebook size={18} />
                </a>
                <a href="https://x.com" target="_blank" rel="noreferrer" className="social-icon-btn" title="X (Twitter)">
                  <IconX size={18} />
                </a>
                <a href="https://youtube.com" target="_blank" rel="noreferrer" className="social-icon-btn" title="YouTube">
                  <IconYouTube size={18} />
                </a>
                <a href="https://tiktok.com" target="_blank" rel="noreferrer" className="social-icon-btn" title="TikTok">
                  <IconTikTok size={18} />
                </a>
                <a href="https://linkedin.com" target="_blank" rel="noreferrer" className="social-icon-btn" title="LinkedIn">
                  <IconLinkedIn size={18} />
                </a>
              </div>
            </div>

            <div className="col">
              <h4>Shop Catalog</h4>
              <a href="/shop">All Titles</a>
              <a href="/shop?category=Bibles">Bibles &amp; Devotionals</a>
              <a href="/shop?status=PREORDER">Preorder Reservations</a>
              <a href="/cart">My Shopping Cart</a>
            </div>

            <div className="col">
              <h4>Quick Links</h4>
              <a href="/about">About Our Mission</a>
              <a href="/contact">Store Locations</a>
              <a href="/portal/login">Staff &amp; Admin Portal</a>
            </div>

            <div className="col">
              <h4>Contact Support</h4>
              <p>Email: info@commandingliberty.com</p>
              <p>Head Office: Main Branch Location</p>
              <p>Customer Support: Mon – Sat (8am – 6pm)</p>
            </div>
          </div>

          <div style={{ textAlign: 'center', paddingTop: '1.5rem', borderTop: '1px solid rgba(255, 255, 255, 0.1)', fontSize: '0.82rem', color: 'rgba(255, 255, 255, 0.6)' }}>
            &copy; {new Date().getFullYear()} Commanding Liberty Enterprise. All Rights Reserved. Empowering Minds, Liberating Souls.
          </div>
        </div>
      </footer>

      {/* Floating WhatsApp Action Button */}
      <a
        href={`https://wa.me/2340000000000?text=${waMessage}`}
        target="_blank"
        rel="noreferrer"
        className="whatsapp-fab"
        aria-label="Chat on WhatsApp"
      >
        <IconWhatsApp size={22} color="#FFFFFF" /> Chat with Us
      </a>
    </>
  );
}
