/**
 * JennieAI Tool — title-social.js
 * Generates social media captions, hashtags, and posting tips
 * based on in-browser image analysis.
 * No API calls. Runs 100% in the browser.
 * Upload this file to your Cloudflare CDN.
 *
 * Registered as: window.JennieTools['title-social']
 */
(function () {
  'use strict';

  window.JennieTools = window.JennieTools || {};

  /* ── Image analyser ── */
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

          let r=0, g=0, b=0, brightness=0;
          const pixels = SIZE * SIZE;
          for (let i = 0; i < d.length; i += 4) {
            r += d[i]; g += d[i+1]; b += d[i+2];
            brightness += (d[i]*0.299 + d[i+1]*0.587 + d[i+2]*0.114);
          }
          r /= pixels; g /= pixels; b /= pixels; brightness /= pixels;

          const dominant = r>g&&r>b ? 'warm' : g>r&&g>b ? 'nature' : b>r&&b>g ? 'cool' : 'neutral';
          const mood     = brightness > 180 ? 'bright' : brightness > 100 ? 'natural' : 'moody';
          const aspect   = img.naturalWidth > img.naturalHeight ? 'landscape'
                         : img.naturalWidth < img.naturalHeight ? 'portrait' : 'square';

          resolve({ dominant, mood, aspect });
        };
        img.onerror = () => reject(new Error('Image load failed.'));
        img.src = e.target.result;
      };
      reader.onerror = () => reject(new Error('File read failed.'));
      reader.readAsDataURL(file);
    });
  }

  /* ── Caption templates ── */
  const CAPTIONS = {
    warm: {
      bright:  [
        'Chasing golden light and warm mornings. ☀️',
        'Every sunset is a reminder that endings can be beautiful. 🌅',
        'Bathed in warmth — sometimes the light just gets it right. 🔆',
      ],
      natural: [
        'The world looks better in warm tones. 🍂',
        'A little light goes a long way. ✨',
        'Soft light, strong feelings. 🌄',
      ],
      moody:   [
        'Where the light fades, the story begins. 🔥',
        'Deep tones, deeper thoughts. 🌑',
        'Mood: campfire, closed eyes, slow breaths. 🌘',
      ],
    },
    nature: {
      bright:  [
        'Let nature do the talking. 🌿',
        'Outside is always a good idea. 🍃',
        'Green is not just a colour — it is a feeling. 🌱',
      ],
      natural: [
        'Still. Quiet. Alive. 🌲',
        'Finding peace one frame at a time. 🍀',
        'The forest called. I answered. 🌳',
      ],
      moody:   [
        'Somewhere between lost and found. 🌫️',
        'The wild has its own kind of silence. 🌿',
        'Overgrown and beautiful. 🪴',
      ],
    },
    cool: {
      bright:  [
        'Sky above, earth below, peace within. 🩵',
        'Breathe in the blue. 🌊',
        'Clear skies and clearer thoughts. ☁️',
      ],
      natural: [
        'Cool tones, calm mind. 💙',
        'The water never lies. 🌊',
        'Somewhere between the sky and the sea. 🌐',
      ],
      moody:   [
        'Nights like this remind me why I shoot late. 🌙',
        'Cold light hits different. 💎',
        'The silence after rain. 🌧️',
      ],
    },
    neutral: {
      bright:  [
        'Minimal. Intentional. Honest. 🤍',
        'Less is everything. 🕊️',
        'Clean lines. Clear vision. ◻️',
      ],
      natural: [
        'Balance is a practice, not a destination. ⚖️',
        'Neutral tones, honest stories. 📷',
        'When in doubt, keep it simple. 🖤🤍',
      ],
      moody:   [
        'Somewhere in the grey, the truth lives. 🌫️',
        'Monochrome state of mind. 🔲',
        'Everything stripped back. Just the frame. 📷',
      ],
    },
  };

  /* ── Hashtag sets ── */
  const HASHTAGS = {
    warm:    '#photographylovers #goldenhour #warmtones #sunsetphotography #lightchaser #filmphoto #portraitphotography #photozonegraphy',
    nature:  '#naturephotography #greens #earthpix #outdoorphotography #landscapephotography #getoutdoors #photozonegraphy #nature',
    cool:    '#bluephotography #oceanvibes #skylovers #minimalphotography #cooltones #aestheticphotography #photozonegraphy #artphoto',
    neutral: '#minimalism #fineart #neutraltones #bnwphotography #blackandwhite #cleancomposition #photozonegraphy #artphotography',
  };

  const POSTING_TIPS = {
    landscape: 'Best formats: Instagram landscape (1.91:1) or Facebook cover. Post between 11 AM – 1 PM for highest reach.',
    portrait:  'Best formats: Instagram Stories (9:16), Pinterest, or TikTok. Portraits perform best 8–10 AM and 6–9 PM.',
    square:    'Perfect for Instagram feed (1:1). Square posts get 40% more engagement on average. Best time: 11 AM – 2 PM.',
  };

  function pick(arr) { return arr[Math.floor(Math.random() * arr.length)]; }

  window.JennieTools['title-social'] = async function (file) {
    const info    = await analyseImage(file);
    const caption = pick(CAPTIONS[info.dominant][info.mood]);
    const tags    = HASHTAGS[info.dominant];
    const tip     = POSTING_TIPS[info.aspect];

    return {
      type  : 'text',
      lines : [
        '💬 Caption:   ' + caption,
        '# Hashtags:  ' + tags,
        '💡 Tip:       ' + tip,
      ],
      label : 'Social Media Caption',
    };
  };

})();
