/**
 * JennieAI Tool — compress-webp.js
 * Upload this file to your Cloudflare CDN.
 * Compresses any image to WebP in the browser using Canvas API.
 *
 * Registered as: window.JennieTools['compress-webp']
 */
(function () {
  'use strict';

  window.JennieTools = window.JennieTools || {};

  window.JennieTools['compress-webp'] = async function (file, opts) {
    const quality = (opts && opts.quality != null) ? opts.quality : 0.80;

    // Check WebP support
    const supported = document.createElement('canvas')
      .toDataURL('image/webp')
      .indexOf('data:image/webp') === 0;

    if (!supported) {
      throw new Error('Your browser does not support WebP encoding. Please try a Chromium-based browser.');
    }

    return new Promise((resolve, reject) => {
      const reader = new FileReader();

      reader.onload = function (e) {
        const img = new Image();

        img.onload = function () {
          const canvas = document.createElement('canvas');
          canvas.width  = img.naturalWidth;
          canvas.height = img.naturalHeight;

          const ctx = canvas.getContext('2d');
          ctx.drawImage(img, 0, 0);

          canvas.toBlob(
            function (blob) {
              if (!blob) { reject(new Error('WebP conversion failed.')); return; }
              resolve({
                type   : 'image',
                blob   : blob,
                ext    : 'webp',
                width  : img.naturalWidth,
                height : img.naturalHeight,
                label  : 'Compressed to WebP',
              });
            },
            'image/webp',
            quality
          );
        };

        img.onerror = () => reject(new Error('Could not load image.'));
        img.src = e.target.result;
      };

      reader.onerror = () => reject(new Error('Could not read file.'));
      reader.readAsDataURL(file);
    });
  };

})();
