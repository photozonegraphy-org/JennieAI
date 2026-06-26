(function(){
'use strict';
window.JennieTools=window.JennieTools||{};

window.JennieTools['title-photo']=async function(file,opts){
  function analyse(file){
    return new Promise(function(res,rej){
      var reader=new FileReader();
      reader.onload=function(e){
        var img=new Image();
        img.onload=function(){
          var S=80;
          var canvas=document.createElement('canvas');
          canvas.width=canvas.height=S;
          var ctx=canvas.getContext('2d');
          ctx.drawImage(img,0,0,S,S);
          var d=ctx.getImageData(0,0,S,S).data;
          var r=0,g=0,b=0,br=0,px=S*S;
          for(var i=0;i<d.length;i+=4){r+=d[i];g+=d[i+1];b+=d[i+2];br+=d[i]*0.299+d[i+1]*0.587+d[i+2]*0.114;}
          r/=px;g/=px;b/=px;br/=px;
          var dominant=r>g&&r>b?'warm':g>r&&g>b?'nature':b>r&&b>g?'cool':'neutral';
          var mood=br>185?'bright':br>110?'natural':'moody';
          var aspect=img.naturalWidth>img.naturalHeight?'landscape':img.naturalWidth<img.naturalHeight?'portrait':'square';
          res({dominant:dominant,mood:mood,aspect:aspect,br:Math.round(br)});
        };
        img.onerror=function(){rej(new Error('Image could not be analysed.'));};
        img.src=e.target.result;
      };
      reader.onerror=function(){rej(new Error('File read failed.'));};
      reader.readAsDataURL(file);
    });
  }

  var TITLES={
    warm:{bright:['Golden Hour','Sunlit','Amber Afternoon','First Light','Gilded Moment','Warm Horizon'],natural:['Autumn Tone','Russet Light','Terra Cotta','Saffron Dusk','Warm Side'],moody:['Firelit','Deep Ember','Smouldering','Charcoal Glow','After the Flame']},
    nature:{bright:['Into the Green','Fresh Perspective','Canopy Light','Nature Speaks','Verdant Morning'],natural:['Forest Floor','Breathing Room','Moss & Stone','The Still Place'],moody:['Undergrowth','Shadows & Leaves','Deep Forest','The Hidden Garden']},
    cool:{bright:['Open Sky','Clarity','Blue Horizon','Crisp Air','Crystal Light'],natural:['Overcast','Silver Lining','Quiet Ocean','Steel Blue'],moody:['After Midnight','Cold Silence','Into the Deep','Night Water']},
    neutral:{bright:['Pure Light','Simplicity','White Space','Ethereal','Minimal'],natural:['Balanced Tones','In Between','Neutral Ground'],moody:['Monochrome','Shadow Study','Dark Matter','The Void']}
  };

  var ASPECT_SUFFIX={landscape:['Wide Open','Horizon','Panoramic View'],portrait:['Close Study','Intimate Frame','Vertical Lines'],square:['Centered','Balanced Frame','Square Focus']};

  var info=await analyse(file);
  var pool=TITLES[info.dominant][info.mood];
  var suffixes=ASPECT_SUFFIX[info.aspect];
  var picked=[];var used={};
  while(picked.length<3&&picked.length<pool.length){
    var t=pool[Math.floor(Math.random()*pool.length)];
    if(!used[t]){used[t]=true;picked.push(t);}
  }
  var withSuffix=picked.map(function(t,i){return i%2===1&&suffixes[Math.floor(i/2)]?t+' — '+suffixes[Math.floor(i/2)]:t;});
  // Add two more creative variants
  var extras=['The '+picked[0]+' Series','Study in '+(info.dominant==='warm'?'Gold':info.dominant==='cool'?'Blue':info.dominant==='nature'?'Green':'Grey')];
  var all=withSuffix.concat(extras).slice(0,5);

  return{type:'text',lines:all,label:'Creative Title Suggestions'};
};
})();
