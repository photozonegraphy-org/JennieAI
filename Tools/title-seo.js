/**
 * JennieAI Tool — title-seo.js
 * Generates SEO-optimised titles, meta descriptions, and alt text
 * based on in-browser image analysis (colour, brightness, dimensions).
 * No API calls. Runs 100% in the browser.
 * Upload this file to your Cloudflare CDN.
 *
 * Registered as: window.JennieTools['title-seo']
 */
(function () {
  'use strict';

  window.JennieTools = window.JennieTools || {};

  /* ── Shared image analyser (same pattern as title-photo.js) ── */
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
          r /= pixels; g /= pixels; b /= pixels;
          brightness /= pixels;

          const dominant = r>g&&r>b ? 'warm' : g>r&&g>b ? 'nature' : b>r&&b>g ? 'cool' : 'neutral';
          const mood     = brightness > 180 ? 'bright' : brightness > 100 ? 'natural' : 'moody';
          const aspect   = img.naturalWidth > img.naturalHeight ? 'landscape'
                         : img.naturalWidth < img.naturalHeight ? 'portrait' : 'square';
          const res      = img.naturalWidth >= 3000 ? 'high-resolution'
                         : img.naturalWidth >= 1200 ? 'professional' : 'standard';

          resolve({ dominant, mood, aspect, res, width: img.naturalWidth, height: img.naturalHeight });
        };
        img.onerror = () => reject(new Error('Image load failed.'));
        img.src = e.target.result;
      };
      reader.onerror = () => reject(new Error('File read failed.'));
      reader.readAsDataURL(file);
    });
  }

  /* ── Template banks ── */
  const SEO_TITLES = {
    warm:    ['Warm Tone Photography | Golden Light Photo', 'Sunlit Photography | Warm Colour Photo'],
    nature:  ['Nature Photography | Green Landscape Photo', 'Outdoor Photography | Natural Light Image'],
    cool:    ['Blue Tone Photography | Cool Light Photo', 'Sky & Water Photography | Cool Tones'],
    neutral: ['Fine Art Photography | Neutral Tone Image', 'Minimalist Photography | Clean Composition'],
  };

  const ALT_TEMPLATES = {
    warm:    ['A warm-toned {aspect} photograph with golden light and rich amber hues',
              'A sunlit {aspect} photo featuring warm orange and yellow colour tones'],
    nature:  ['A natural light {aspect} photograph featuring green tones and organic textures',
              'An outdoor {aspect} photo with lush greenery and natural colour palette'],
    cool:    ['A cool-toned {aspect} photograph with blue and silver colour palette',
              'A serene {aspect} photo featuring cool blue tones and calm atmosphere'],
    neutral: ['A {aspect} photograph with balanced neutral tones and clean composition',
              'A minimalist {aspect} photo with neutral colour palette and strong composition'],
  };

  const META_TEMPLATES = {
    bright:  ['A {res} {aspect} photograph with bright, airy tones. Perfect for editorial, stock, or portfolio use.',
              'Bright and vibrant {aspect} photography showcasing clean light and strong composition.'],
    natural: ['A {res} {aspect} photograph with balanced natural lighting. Suitable for professional portfolio and editorial use.',
              'Well-exposed {aspect} photography with natural tones and professional quality.'],
    moody:   ['A {res} {aspect} photograph with moody, dramatic tones. Ideal for creative editorial and artistic projects.',
              'Dark and atmospheric {aspect} photography with cinematic quality and strong visual impact.'],
  };

  function pick(arr) { return arr[Math.floor(Math.random() * arr.length)]; }

  function fill(template, info) {
    return template
      .replace(/{aspect}/g, info.aspect)
      .replace(/{res}/g, info.res)
      .replace(/{mood}/g, info.mood)
      .replace(/{dominant}/g, info.dominant);
  }

  window.JennieTools['title-seo'] = async function (file) {
    const info = await analyseImage(file);

    const seoTitle  = pick(SEO_TITLES[info.dominant]);
    const altText   = fill(pick(ALT_TEMPLATES[info.dominant]), info);
    const metaDesc  = fill(pick(META_TEMPLATES[info.mood]), info);
    const slugBase  = seoTitle.toLowerCase().split('|')[0].trim().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
    const imageSlug = slugBase + '-' + info.width + 'x' + info.height;
    const keywords  = [
      info.dominant + ' photography',
      info.aspect + ' photo',
      info.mood + ' tones',
      info.res + ' image',
      'photography portfolio',
      'photozone graphy',
    ].join(', ');

    return {
      type  : 'text',
      lines : [
        '📌 SEO Title:  ' + seoTitle,
        '🖼️ Alt Text:   ' + altText,
        '📝 Meta Desc:  ' + metaDesc,
        '🔗 File Slug:  ' + imageSlug + '.jpg',
        '🏷️ Keywords:   ' + keywords,
      ],
      label : 'SEO Title & Alt Text',
    };
  };

})();
