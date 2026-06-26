(function(){
'use strict';
window.JennieTools=window.JennieTools||{};
window.JennieTools['jpg-to-webp']=async function(file,opts){
  var quality=(opts&&opts.quality!=null)?opts.quality:0.90;
  return new Promise(function(resolve,reject){
    var reader=new FileReader();
    reader.onload=function(e){
      var img=new Image();
      img.onload=function(){
        var canvas=document.createElement('canvas');
        canvas.width=img.naturalWidth;canvas.height=img.naturalHeight;
        canvas.getContext('2d').drawImage(img,0,0);
        canvas.toBlob(function(blob){
          if(!blob){reject(new Error('Conversion failed'));return;}
          resolve({type:'image',blob:blob,ext:'webp',width:img.naturalWidth,height:img.naturalHeight,label:'Converted to WebP'});
        },'image/webp',quality);
      };
      img.onerror=function(){reject(new Error('Could not load image'));};
      img.src=e.target.result;
    };
    reader.onerror=function(){reject(new Error('File read failed'));};
    reader.readAsDataURL(file);
  });
};
})();
