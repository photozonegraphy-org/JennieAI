/**
 * JennieAI Tool — any-to-png.js
 * Converts any image to PNG (lossless, preserves transparency).
 * Upload this file to your Cloudflare CDN.
 *
 * Registered as: window.JennieTools['any-to-png']
 */
(function () {
  'use strict';

  window.JennieTools = window.JennieTools || {};

  window.JennieTools['any-to-png'] = async function (file, opts) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = function (e) {
        const img = new Image();
        img.onload = function () {
          const canvas = document.createElement('canvas');
          canvas.width  = img.naturalWidth;
          canvas.height = img.naturalHeight;
          canvas.getContext('2d').drawImage(img, 0, 0);
          canvas.toBlob(blob => {
            if (!blob) { reject(new Error('Conversion to PNG failed.')); return; }
            resolve({ type:'image', blob, ext:'png', width:img.naturalWidth, height:img.naturalHeight, label:'Converted to PNG' });
          }, 'image/png');
        };
        img.onerror = () => reject(new Error('Could not load image.'));
        img.src = e.target.result;
      };
      reader.onerror = () => reject(new Error('Could not read file.'));
      reader.readAsDataURL(file);
    });
  };

})();
