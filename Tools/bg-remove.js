(function(){
'use strict';
window.JennieTools=window.JennieTools||{};

window.JennieTools['bg-remove']=async function(file,opts){
  // Load @imgly/background-removal — free, runs fully in browser via WebAssembly
  if(!window.ImglyBgRemoval){
    await new Promise(function(res,rej){
      var s=document.createElement('script');
      s.src='https://cdn.jsdelivr.net/npm/@imgly/background-removal@1.4.5/dist/background-removal.js';
      s.onload=res;
      s.onerror=function(){rej(new Error('AI module unavailable. Please try again.'));};
      document.head.appendChild(s);
    });
  }

  try{
    var config={
      publicPath:'https://cdn.jsdelivr.net/npm/@imgly/background-removal@1.4.5/dist/',
      debug:false,
      model:'small',
      output:{format:'image/png',quality:0.9},
    };

    var resultBlob=await window.ImglyBgRemoval.removeBackground(file,config);
    if(!resultBlob)throw new Error('No output returned from AI model.');

    return new Promise(function(resolve,reject){
      var reader=new FileReader();
      reader.onload=function(e){
        var img=new Image();
        img.onload=function(){
          resolve({
            type:'image',
            blob:resultBlob,
            ext:'png',
            width:img.naturalWidth,
            height:img.naturalHeight,
            label:'Background Removed',
          });
        };
        img.onerror=function(){
          resolve({type:'image',blob:resultBlob,ext:'png',width:0,height:0,label:'Background Removed'});
        };
        img.src=e.target.result;
      };
      reader.onerror=function(){reject(new Error('Output read failed.'));};
      reader.readAsDataURL(resultBlob);
    });
  }catch(err){
    throw new Error('Background removal failed: '+err.message);
  }
};
})();
