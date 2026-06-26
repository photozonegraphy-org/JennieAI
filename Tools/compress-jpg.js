(function(){
'use strict';
window.JennieTools=window.JennieTools||{};
window.JennieTools['compress-jpg']=async function(file,opts){
  var quality=(opts&&opts.quality!=null)?opts.quality:0.80;
  return new Promise(function(resolve,reject){
    var reader=new FileReader();
    reader.onload=function(e){
      var img=new Image();
      img.onload=function(){
        var canvas=document.createElement('canvas');
        canvas.width=img.naturalWidth;canvas.height=img.naturalHeight;
        var ctx=canvas.getContext('2d');
        ctx.fillStyle='#ffffff';ctx.fillRect(0,0,canvas.width,canvas.height);
        ctx.drawImage(img,0,0);
        canvas.toBlob(function(blob){
          if(!blob){reject(new Error('Compression failed'));return;}
          resolve({type:'image',blob:blob,ext:'jpg',width:img.naturalWidth,height:img.naturalHeight,label:'Compressed to JPG'});
        },'image/jpeg',quality);
      };
      img.onerror=function(){reject(new Error('Could not load image'));};
      img.src=e.target.result;
    };
    reader.onerror=function(){reject(new Error('File read failed'));};
    reader.readAsDataURL(file);
  });
};
})();
