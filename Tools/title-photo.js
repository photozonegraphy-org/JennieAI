/**
 * JennieAI Tool — title-photo.js
 * Generates creative photo titles based on image analysis (colour, brightness, dimensions).
 * 100% in-browser — no AI API, uses canvas sampling + pattern library.
 * Upload this file to your Cloudflare CDN.
 *
 * Registered as: window.JennieTools['title-photo']
 */
(function () {
  'use strict';

  window.JennieTools = window.JennieTools || {};

  /* ── Colour analysis ── */
  function analyseImage(file) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = e => {
        const img = new Image();
        img.onload = () => {
          const SIZE = 80;
          const canvas = document.createElement('canvas');
          canvas.width = canvas.height = SIZE;
          const ctx = canvas.getContext('2d');
          ctx.drawImage(img, 0, 0, SIZE, SIZE);
          const d = ctx.getImageData(0, 0, SIZE, SIZE).data;

          let r=0,g=0,b=0,brightness=0, pixels = SIZE*SIZE;
          for (let i=0; i<d.length; i+=4) {
            r+=d[i]; g+=d[i+1]; b+=d[i+2];
            brightness += (d[i]*0.299 + d[i+1]*0.587 + d[i+2]*0.114);
          }
          r=r/pixels; g=g/pixels; b=b/pixels;
          brightness=brightness/pixels;

          const dominant = r>g&&r>b ? 'warm' : g>r&&g>b ? 'green' : b>r&&b>g ? 'cool' : 'neutral';
          const mood = brightness > 180 ? 'bright' : brightness > 100 ? 'balanced' : 'dark';
          const aspect = img.naturalWidth > img.naturalHeight ? 'landscape' : img.naturalWidth < img.naturalHeight ? 'portrait' : 'square';

          resolve({ dominant, mood, aspect, brightness: Math.round(brightness), width: img.naturalWidth, height: img.naturalHeight });
        };
        img.onerror = () => reject(new Error('Image load failed for analysis.'));
        img.src = e.target.result;
      };
      reader.onerror = () => reject(new Error('File read failed.'));
      reader.readAsDataURL(file);
    });
  }

  /* ── Title templates keyed by [dominant][mood] ── */
  const TITLES = {
    warm: {
      bright: ['Golden Hour', 'Sunlit Story', 'Warmth & Light', 'Amber Afternoon', 'Gilded Moment', 'First Light', 'Burnt Horizon'],
      balanced: ['Autumn Haze', 'Russet Dreams', 'The Warm Side', 'Embers', 'Terra Cotta', 'Saffron Dusk'],
      dark:   ['Firelit', 'Deep Ember', 'Smouldering', 'After the Flame', 'Charcoal Glow'],
    },
    green: {
      bright: ['Nature Speaks', 'Canopy Light', 'Into the Green', 'Verdant Morning', 'Fresh Perspective'],
      balanced: ['Forest Floor', 'The Still Life', 'Moss & Stone', 'Breathing Room', 'Overgrowth'],
      dark:   ['Undergrowth', 'Shadows & Leaves', 'Deep Forest', 'The Hidden Garden'],
    },
    cool: {
      bright: ['Clarity', 'Open Sky', 'Blue Horizon', 'Ice & Light', 'The Calm', 'Crisp Air'],
      balanced: ['Storm Light', 'Overcast', 'Silver Lining', 'The Quiet Ocean', 'Midnight Blue'],
      dark:   ['Into the Deep', 'Night Water', 'Cold Silence', 'After Midnight', 'The Abyss'],
    },
    neutral: {
      bright: ['Pure Light', 'Minimal', 'White Space', 'Simplicity', 'Ethereal'],
      balanced: ['Balanced Tones', 'In Between', 'The Grey Scale', 'Neutral Ground'],
      dark:   ['Monochrome', 'Shadow Study', 'Dark Matter', 'The Void'],
    },
  };

  function pick(arr) { return arr[Math.floor(Math.random() * arr.length)]; }

  window.JennieTools['title-photo'] = async function (file) {
    const info = await analyseImage(file);
    const pool  = TITLES[info.dominant][info.mood];
    const picks = [];
    const used  = new Set();
    while (picks.length < 5 && picks.length < pool.length) {
      const t = pick(pool);
      if (!used.has(t)) { used.add(t); picks.push(t); }
    }

    return {
      type  : 'text',
      lines : picks,
      label : 'Photo Title Suggestions',
    };
  };

})();
