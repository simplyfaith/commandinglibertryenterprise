import React from 'react';

/**
 * Primary Brand Logo Seal (The complete un-altered circular logo emblem)
 * Features "EMPOWERING MINDS", "LIBERATING SOULS", "COMMANDING LIBERTY",
 * rosettes, and yellow book inside deep navy circle.
 */
export function PrimaryLogo({ width = 180, height = 180, className = '' }) {
  return (
    <svg
      width={width}
      height={height || width}
      viewBox="0 0 400 400"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      aria-label="Commanding Liberty Primary Logo"
    >
      <circle cx="200" cy="200" r="192" fill="#FFFFFF" stroke="#060C3B" strokeWidth="8" />
      <circle cx="200" cy="200" r="182" fill="none" stroke="#FFDD00" strokeWidth="3" />

      <defs>
        <path id="topTextArc" d="M 50,200 A 150,150 0 1,1 350,200" />
        <path id="bottomInnerArc" d="M 345,200 A 145,145 0 0,1 55,200" />
        <path id="bottomOuterArc" d="M 372,200 A 172,172 0 0,1 28,200" />
        <filter id="shadow" x="-10%" y="-10%" width="120%" height="120%">
          <feDropShadow dx="0" dy="4" stdDeviation="4" floodColor="#060C3B" floodOpacity="0.3" />
        </filter>
      </defs>

      <text fill="#060C3B" fontSize="23" fontWeight="900" fontFamily="Arial, 'Montserrat', sans-serif" letterSpacing="4.5">
        <textPath href="#topTextArc" startOffset="50%" textAnchor="middle">
          EMPOWERING MINDS
        </textPath>
      </text>

      <g transform="translate(62, 200)">
        <circle cx="0" cy="0" r="11" stroke="#060C3B" strokeWidth="2" fill="#FFFFFF" />
        <path d="M 0,-11 L 0,11 M -11,0 L 11,0 M -7,-7 L 7,7 M -7,7 L 7,-7" stroke="#060C3B" strokeWidth="1.5" />
        <circle cx="0" cy="0" r="4" fill="#060C3B" />
      </g>
      <g transform="translate(338, 200)">
        <circle cx="0" cy="0" r="11" stroke="#060C3B" strokeWidth="2" fill="#FFFFFF" />
        <path d="M 0,-11 L 0,11 M -11,0 L 11,0 M -7,-7 L 7,7 M -7,7 L 7,-7" stroke="#060C3B" strokeWidth="1.5" />
        <circle cx="0" cy="0" r="4" fill="#060C3B" />
      </g>

      <text fill="#060C3B" fontSize="18" fontWeight="900" fontFamily="Arial, 'Montserrat', sans-serif" letterSpacing="3.5">
        <textPath href="#bottomInnerArc" startOffset="50%" textAnchor="middle">
          LIBERATING SOULS
        </textPath>
      </text>

      <text fill="#060C3B" fontSize="23" fontWeight="900" fontFamily="Arial, 'Montserrat', sans-serif" letterSpacing="4">
        <textPath href="#bottomOuterArc" startOffset="50%" textAnchor="middle">
          COMMANDING LIBERTY
        </textPath>
      </text>

      <circle cx="200" cy="200" r="120" fill="#060C3B" stroke="#FFDD00" strokeWidth="4" />

      <g transform="translate(200, 200)" filter="url(#shadow)">
        <path d="M -3,38 C -25,25 -52,22 -72,25 L -72,-35 C -50,-38 -25,-32 -3,-18 Z" fill="#FFDD00" stroke="#060C3B" strokeWidth="3" />
        <path d="M -8,32 C -28,21 -52,18 -67,21 L -67,-28 C -50,-31 -28,-26 -8,-14 Z" fill="#FFE500" stroke="#060C3B" strokeWidth="2" />
        <path d="M -13,26 C -31,17 -52,14 -62,17 L -62,-21 C -50,-24 -31,-20 -13,-10 Z" fill="#FFF275" stroke="#060C3B" strokeWidth="1.5" />

        <path d="M 3,38 C 25,25 52,22 72,25 L 72,-35 C 50,-38 25,-32 3,-18 Z" fill="#FFDD00" stroke="#060C3B" strokeWidth="3" />
        <path d="M 8,32 C 28,21 52,18 67,21 L 67,-28 C 50,-31 28,-26 8,-14 Z" fill="#FFE500" stroke="#060C3B" strokeWidth="2" />
        <path d="M 13,26 C 31,17 52,14 62,17 L 62,-21 C 50,-24 31,-20 13,-10 Z" fill="#FFF275" stroke="#060C3B" strokeWidth="1.5" />

        <line x1="0" y1="-30" x2="0" y2="40" stroke="#060C3B" strokeWidth="4" strokeLinecap="round" />
      </g>
    </svg>
  );
}

export function BrandLogo({ width = 64, height = 64, className = '' }) {
  return <PrimaryLogo width={width} height={height} className={className} />;
}

export function SubmarkLogo({ width = 48, height = 48, className = '' }) {
  return <PrimaryLogo width={width} height={height} className={className} />;
}

export function SecondaryLogo({ width = 120, className = '' }) {
  return <PrimaryLogo width={width} height={width} className={className} />;
}

export function LogoMark({ width = 32, height = 32, color = '#FFDD00', className = '' }) {
  return (
    <svg
      width={width}
      height={height}
      viewBox="0 0 100 100"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      aria-label="Commanding Liberty Book Mark"
    >
      <g transform="translate(50, 50) scale(0.6)">
        <path d="M -3,38 C -25,25 -52,22 -72,25 L -72,-35 C -50,-38 -25,-32 -3,-18 Z" fill={color} />
        <path d="M 3,38 C 25,25 52,22 72,25 L 72,-35 C 50,-38 25,-32 3,-18 Z" fill={color} />
        <line x1="0" y1="-30" x2="0" y2="40" stroke={color === '#060C3B' ? '#FFDD00' : '#060C3B'} strokeWidth="4" strokeLinecap="round" />
      </g>
    </svg>
  );
}
