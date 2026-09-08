import { useEffect, useState, useRef } from 'react';
import { api } from '../../api/client';
import { BookPatternBackground } from '../../components/BrandPatterns';

const DEFAULT_SLIDES = [
  {
    id: 101,
    title: 'COMMANDING LIBERTY ENTERPRISE',
    subtitle: 'Empowering Minds, Liberating Souls',
    description: "Discover a wide selection of Bibles, educational books, journals, literature, and school supplies. Check real-time stock across our branch locations or place preorders before stock arrives.",
    cta_text: 'Shop Catalog',
    cta_link: '/shop',
    image_url: null
  },
  {
    id: 102,
    title: 'BIBLES & DEVOTIONALS COLLECTION',
    subtitle: 'Spiritual Growth & Study Resources',
    description: "Explore authentic Bibles, study editions, reference commentaries, and daily devotionals available in stock across all store branches.",
    cta_text: 'Explore Bibles',
    cta_link: '/shop?category=Bibles',
    image_url: null
  },
  {
    id: 103,
    title: 'PREORDER UPCOMING TITLES',
    subtitle: 'Reserve Your Copy Early',
    description: "Be the first to get newly released book titles, academic publications, and special stationery editions before they arrive.",
    cta_text: 'View Preorders',
    cta_link: '/shop?status=PREORDER',
    image_url: null
  }
];

export default function HeroSlider({ locationsCount = 3, availableTitlesCount = 120 }) {
  const [slides, setSlides] = useState(DEFAULT_SLIDES);
  const [currentIndex, setCurrentIndex] = useState(0);
  const [isPaused, setIsPaused] = useState(false);
  const timerRef = useRef(null);

  useEffect(() => {
    api.listSliders()
      .then((res) => {
        if (res.data && res.data.length > 0) {
          setSlides(res.data);
        }
      })
      .catch(() => {});
  }, []);

  useEffect(() => {
    if (slides.length <= 1 || isPaused) return;
    timerRef.current = setInterval(() => {
      setCurrentIndex((prev) => (prev + 1) % slides.length);
    }, 6000);
    return () => clearInterval(timerRef.current);
  }, [slides, isPaused]);

  const handlePrev = () => {
    setCurrentIndex((prev) => (prev - 1 + slides.length) % slides.length);
  };

  const handleNext = () => {
    setCurrentIndex((prev) => (prev + 1) % slides.length);
  };

  const currentSlide = slides[currentIndex] || DEFAULT_SLIDES[0];

  return (
    <section
      className="hero-section"
      onMouseEnter={() => setIsPaused(true)}
      onMouseLeave={() => setIsPaused(false)}
      style={{
        backgroundImage: currentSlide.image_url
          ? `url(${currentSlide.image_url})`
          : undefined,
        backgroundSize: 'cover',
        backgroundPosition: 'center',
        transition: 'all 0.5s ease-in-out'
      }}
    >
      <BookPatternBackground opacity={0.06} />

      {/* Slider Navigation Arrows */}
      {slides.length > 1 && (
        <>
          <button
            onClick={handlePrev}
            aria-label="Previous Slide"
            style={{
              position: 'absolute',
              left: '1.25rem',
              top: '50%',
              transform: 'translateY(-50%)',
              background: 'rgba(255, 255, 255, 0.15)',
              backdropFilter: 'blur(8px)',
              border: '1px solid rgba(255, 221, 0, 0.4)',
              color: '#FFFFFF',
              width: '44px',
              height: '44px',
              borderRadius: '50%',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              fontSize: '1.4rem',
              fontWeight: 900,
              cursor: 'pointer',
              zIndex: 10,
              transition: 'all 0.2s ease'
            }}
          >
            ‹
          </button>
          <button
            onClick={handleNext}
            aria-label="Next Slide"
            style={{
              position: 'absolute',
              right: '1.25rem',
              top: '50%',
              transform: 'translateY(-50%)',
              background: 'rgba(255, 255, 255, 0.15)',
              backdropFilter: 'blur(8px)',
              border: '1px solid rgba(255, 221, 0, 0.4)',
              color: '#FFFFFF',
              width: '44px',
              height: '44px',
              borderRadius: '50%',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              fontSize: '1.4rem',
              fontWeight: 900,
              cursor: 'pointer',
              zIndex: 10,
              transition: 'all 0.2s ease'
            }}
          >
            ›
          </button>

          {/* Indicator Dots */}
          <div
            style={{
              position: 'absolute',
              bottom: '1.25rem',
              left: '50%',
              transform: 'translateX(-50%)',
              display: 'flex',
              gap: '0.6rem',
              zIndex: 10
            }}
          >
            {slides.map((s, idx) => (
              <button
                key={s.id || idx}
                onClick={() => setCurrentIndex(idx)}
                aria-label={`Go to slide ${idx + 1}`}
                style={{
                  width: idx === currentIndex ? '32px' : '10px',
                  height: '10px',
                  borderRadius: '999px',
                  background: idx === currentIndex ? 'var(--brand-yellow)' : 'rgba(255, 255, 255, 0.4)',
                  border: 'none',
                  cursor: 'pointer',
                  transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)'
                }}
              />
            ))}
          </div>
        </>
      )}
    </section>
  );
}
