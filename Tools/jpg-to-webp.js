/**
 * JennieAI Tool — jpg-to-webp.js
 * Also handles: any image → WebP (same logic as compress-webp but without quality framing)
 * Upload this file to your Cloudflare CDN.
 *
 * Registered as: window.JennieTools['jpg-to-webp']
 */
(function () {
  'use strict';

  window.JennieTools = window.JennieTools || {};

  window.JennieTools['jpg-to-webp'] = async function (file, opts) {
    const quality = (opts && opts.quality != null) ? opts.quality : 0.90;

    const supported = document.createElement('canvas')
      .toDataURL('image/webp')
      .indexOf('data:image/webp') === 0;

    if (!supported) {
      throw new Error('Your browser does not support WebP encoding. Try Chrome, Edge, or Opera.');
    }

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
            if (!blob) { reject(new Error('Conversion to WebP failed.')); return; }
            resolve({ type:'image', blob, ext:'webp', width:img.naturalWidth, height:img.naturalHeight, label:'Converted to WebP' });
          }, 'image/webp', quality);
        };
        img.onerror = () => reject(new Error('Could not load image.'));
        img.src = e.target.result;
      };
      reader.onerror = () => reject(new Error('Could not read file.'));
      reader.readAsDataURL(file);
    });
  };

})();
