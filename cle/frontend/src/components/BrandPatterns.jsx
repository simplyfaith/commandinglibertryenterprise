import React from 'react';

export function BookPatternBackground({ opacity = 0.05, className = '' }) {
  return (
    <div
      className={`brand-pattern-bg ${className}`}
      style={{
        position: 'absolute',
        inset: 0,
        pointerEvents: 'none',
        opacity: opacity,
        zIndex: 0,
        overflow: 'hidden'
      }}
    >
      <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <pattern id="bookGridPattern" width="60" height="60" patternUnits="userSpaceOnUse">
            <g transform="translate(30, 30) scale(0.28)">
              <path d="M -3,38 C -25,25 -52,22 -72,25 L -72,-35 C -50,-38 -25,-32 -3,-18 Z" fill="#060C3B" />
              <path d="M 3,38 C 25,25 52,22 72,25 L 72,-35 C 50,-38 25,-32 3,-18 Z" fill="#060C3B" />
              <line x1="0" y1="-30" x2="0" y2="40" stroke="#FFDD00" strokeWidth="3" />
            </g>
          </pattern>
        </defs>
        <rect width="100%" height="100%" fill="url(#bookGridPattern)" />
      </svg>
    </div>
  );
}

export function SubmarkPatternBackground({ opacity = 0.06, className = '' }) {
  return (
    <div
      className={`brand-submark-pattern-bg ${className}`}
      style={{
        position: 'absolute',
        inset: 0,
        pointerEvents: 'none',
        opacity: opacity,
        zIndex: 0,
        overflow: 'hidden'
      }}
    >
      <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <pattern id="submarkPattern" width="70" height="70" patternUnits="userSpaceOnUse">
            <circle cx="35" cy="35" r="22" fill="#060C3B" stroke="#FFDD00" strokeWidth="2" />
            <g transform="translate(35, 35) scale(0.18)">
              <path d="M -3,38 C -25,25 -52,22 -72,25 L -72,-35 C -50,-38 -25,-32 -3,-18 Z" fill="#FFDD00" />
              <path d="M 3,38 C 25,25 52,22 72,25 L 72,-35 C 50,-38 25,-32 3,-18 Z" fill="#FFDD00" />
            </g>
          </pattern>
        </defs>
        <rect width="100%" height="100%" fill="url(#submarkPattern)" />
      </svg>
    </div>
  );
}
