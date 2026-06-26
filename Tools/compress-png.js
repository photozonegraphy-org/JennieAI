/**
 * JennieAI Tool — compress-png.js
 * Upload this file to your Cloudflare CDN.
 * Compresses any image to PNG in the browser.
 * Note: PNG quality slider controls downscaling only (PNG is lossless).
 *
 * Registered as: window.JennieTools['compress-png']
 */
(function () {
  'use strict';

  window.JennieTools = window.JennieTools || {};

  window.JennieTools['compress-png'] = async function (file, opts) {
    const quality = (opts && opts.quality != null) ? opts.quality : 0.80;

    return new Promise((resolve, reject) => {
      const reader = new FileReader();

      reader.onload = function (e) {
        const img = new Image();

        img.onload = function () {
          // PNG is lossless — "quality" maps to scale (0.5–1.0)
          // quality 0.10 → scale 0.50, quality 1.0 → scale 1.0
          const scale  = 0.5 + quality * 0.5;
          const width  = Math.round(img.naturalWidth  * scale);
          const height = Math.round(img.naturalHeight * scale);

          const canvas = document.createElement('canvas');
          canvas.width  = width;
          canvas.height = height;

          const ctx = canvas.getContext('2d');
          ctx.imageSmoothingEnabled = true;
          ctx.imageSmoothingQuality = 'high';
          ctx.drawImage(img, 0, 0, width, height);

          canvas.toBlob(
            function (blob) {
              if (!blob) { reject(new Error('PNG compression failed.')); return; }
              resolve({
                type   : 'image',
                blob   : blob,
                ext    : 'png',
                width  : width,
                height : height,
                label  : 'Compressed to PNG',
              });
            },
            'image/png'
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
