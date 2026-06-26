(function(){
'use strict';
window.JennieTools=window.JennieTools||{};
window.JennieTools['any-to-png']=async function(file,opts){
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
          resolve({type:'image',blob:blob,ext:'png',width:img.naturalWidth,height:img.naturalHeight,label:'Converted to PNG'});
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
