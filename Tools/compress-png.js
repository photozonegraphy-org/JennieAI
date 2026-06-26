(function(){
'use strict';
window.JennieTools=window.JennieTools||{};
window.JennieTools['compress-png']=async function(file,opts){
  var quality=(opts&&opts.quality!=null)?opts.quality:0.80;
  return new Promise(function(resolve,reject){
    var reader=new FileReader();
    reader.onload=function(e){
      var img=new Image();
      img.onload=function(){
        var scale=0.5+quality*0.5;
        var w=Math.max(1,Math.round(img.naturalWidth*scale));
        var h=Math.max(1,Math.round(img.naturalHeight*scale));
        var canvas=document.createElement('canvas');
        canvas.width=w;canvas.height=h;
        var ctx=canvas.getContext('2d');
        ctx.imageSmoothingEnabled=true;ctx.imageSmoothingQuality='high';
        ctx.drawImage(img,0,0,w,h);
        canvas.toBlob(function(blob){
          if(!blob){reject(new Error('PNG compression failed'));return;}
          resolve({type:'image',blob:blob,ext:'png',width:w,height:h,label:'Compressed to PNG'});
        },'image/png');
      };
      img.onerror=function(){reject(new Error('Could not load image'));};
      img.src=e.target.result;
    };
    reader.onerror=function(){reject(new Error('File read failed'));};
    reader.readAsDataURL(file);
  });
};
})();
