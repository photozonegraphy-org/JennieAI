/**
 * JennieAI Tool — compress-jpg.js
 * Upload this file to your Cloudflare CDN.
 * Runs 100% in the browser using Canvas API. No server upload needed.
 *
 * Registered as: window.JennieTools['compress-jpg']
 * Signature: async (file, opts) => result
 *   opts.quality: 0.0–1.0
 *   result: { type:'image', blob, ext:'jpg', width, height, label }
 */
(function () {
  'use strict';

  window.JennieTools = window.JennieTools || {};

  window.JennieTools['compress-jpg'] = async function (file, opts) {
    const quality = (opts && opts.quality != null) ? opts.quality : 0.80;

    return new Promise((resolve, reject) => {
      const reader = new FileReader();

      reader.onload = function (e) {
        const img = new Image();

        img.onload = function () {
          const canvas = document.createElement('canvas');
          canvas.width  = img.naturalWidth;
          canvas.height = img.naturalHeight;

          const ctx = canvas.getContext('2d');
          // White background (JPG doesn't support transparency)
          ctx.fillStyle = '#ffffff';
          ctx.fillRect(0, 0, canvas.width, canvas.height);
          ctx.drawImage(img, 0, 0);

          canvas.toBlob(
            function (blob) {
              if (!blob) { reject(new Error('Compression failed — canvas returned null.')); return; }
              resolve({
                type   : 'image',
                blob   : blob,
                ext    : 'jpg',
                width  : img.naturalWidth,
                height : img.naturalHeight,
                label  : 'Compressed to JPG',
              });
            },
            'image/jpeg',
            quality
          );
        };

        img.onerror = () => reject(new Error('Could not load image for compression.'));
        img.src = e.target.result;
      };

      reader.onerror = () => reject(new Error('Could not read file.'));
      reader.readAsDataURL(file);
    });
  };

})();
