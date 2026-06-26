/**
 * JennieAI Tool — any-to-jpg.js
 * Converts any image (PNG, WebP, GIF, etc.) to JPEG.
 * Upload this file to your Cloudflare CDN.
 *
 * Registered as: window.JennieTools['any-to-jpg']
 */
(function () {
  'use strict';

  window.JennieTools = window.JennieTools || {};

  window.JennieTools['any-to-jpg'] = async function (file, opts) {
    const quality = (opts && opts.quality != null) ? opts.quality : 0.92;

    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = function (e) {
        const img = new Image();
        img.onload = function () {
          const canvas = document.createElement('canvas');
          canvas.width  = img.naturalWidth;
          canvas.height = img.naturalHeight;
          const ctx = canvas.getContext('2d');
          ctx.fillStyle = '#ffffff'; // flatten transparency
          ctx.fillRect(0, 0, canvas.width, canvas.height);
          ctx.drawImage(img, 0, 0);
          canvas.toBlob(blob => {
            if (!blob) { reject(new Error('Conversion to JPG failed.')); return; }
            resolve({ type:'image', blob, ext:'jpg', width:img.naturalWidth, height:img.naturalHeight, label:'Converted to JPG' });
          }, 'image/jpeg', quality);
        };
        img.onerror = () => reject(new Error('Could not load image.'));
        img.src = e.target.result;
      };
      reader.onerror = () => reject(new Error('Could not read file.'));
      reader.readAsDataURL(file);
    });
  };

})();
