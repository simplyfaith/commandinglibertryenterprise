import { Outlet } from 'react-router-dom';
import StorefrontNav from './StorefrontNav';
import StorefrontFooter from './StorefrontFooter';

export default function StorefrontLayout() {
  const waMessage = encodeURIComponent("Hello Commanding Liberty Enterprise, I'd like to ask about a product.");
  return (
    <div className="storefront">
      <StorefrontNav />
      <Outlet />
      <StorefrontFooter />
      <a
        className="whatsapp-fab"
        href={`https://wa.me/2340000000000?text=${waMessage}`}
        target="_blank"
        rel="noreferrer"
      >
        WhatsApp Us
      </a>
    </div>
  );
}
