(function(){
'use strict';
window.JennieTools=window.JennieTools||{};

window.JennieTools['exif-camera']=async function(file,opts){
  // Load ExifReader — free, open source, pure JS EXIF parser
  if(!window.ExifReader){
    await new Promise(function(res,rej){
      var s=document.createElement('script');
      s.src='https://cdn.jsdelivr.net/npm/exifreader@4.14.1/dist/exif-reader.js';
      s.onload=res;
      s.onerror=function(){rej(new Error('Metadata module could not be loaded.'));};
      document.head.appendChild(s);
    });
  }

  var buf=await file.arrayBuffer();
  var tags;
  try{
    tags=window.ExifReader.load(buf,{expanded:true});
  }catch(e){
    throw new Error('This image does not contain readable metadata, or the file format is not supported.');
  }

  var exif=tags.exif||{};
  var gps=tags.gps||{};
  var img=tags.image||{};

  function g(obj,key){
    var v=obj[key];
    if(!v)return null;
    if(v.description!=null&&v.description!=='')return String(v.description).trim();
    if(v.value!=null&&v.value!=='')return String(v.value).trim();
    return null;
  }

  var camera=g(exif,'Make')||g(img,'Make');
  var model=g(exif,'Model')||g(img,'Model');
  var lens=g(exif,'LensModel')||g(exif,'Lens')||g(exif,'LensInfo');
  var focalLen=g(exif,'FocalLength');
  var aperture=g(exif,'FNumber')||g(exif,'ApertureValue');
  var shutter=g(exif,'ExposureTime')||g(exif,'ShutterSpeedValue');
  var iso=g(exif,'ISOSpeedRatings')||g(exif,'PhotographicSensitivity');
  var width=g(exif,'PixelXDimension')||g(img,'ImageWidth');
  var height=g(exif,'PixelYDimension')||g(img,'ImageLength');
  var software=g(exif,'Software');
  var date=g(exif,'DateTimeOriginal')||g(exif,'DateTime');
  var flash=g(exif,'Flash');
  var wb=g(exif,'WhiteBalance');
  var expMode=g(exif,'ExposureMode');
  var expProg=g(exif,'ExposureProgram');
  var latRef=gps['GPSLatitudeRef']?g(gps,'GPSLatitudeRef'):null;
  var lat=gps['GPSLatitude']?g(gps,'GPSLatitude'):null;
  var lon=gps['GPSLongitude']?g(gps,'GPSLongitude'):null;
  var hasGps=!!(lat&&lon);

  var hasCamera=!!(camera||model);
  var hasLens=!!lens;
  var hasExposure=!!(aperture||shutter||iso);

  if(!hasCamera&&!hasLens&&!hasExposure&&!date&&!width){
    throw new Error('No camera metadata found in this image. The file may be a screenshot, social media re-upload, or stripped of EXIF data.');
  }

  var sentences=[];

  // Opening — camera or fallback
  if(hasCamera&&model){
    var camStr=(camera&&!model.toLowerCase().includes(camera.toLowerCase()))?camera+' '+model:model;
    sentences.push('This image was captured using a '+camStr+'.');
  } else if(hasLens&&!hasCamera){
    sentences.push('This photograph was taken with a decent camera — the specific body model was not stored in the file metadata.');
  } else if(!hasCamera&&!hasLens){
    sentences.push('This image was captured with a camera, though the device information was not embedded in the file.');
  }

  // Lens
  if(hasLens){
    var lensStr=lens;
    if(focalLen)lensStr+=' at '+focalLen+' mm';
    sentences.push('The lens used was a '+lensStr+'.');
  } else if(focalLen&&!hasLens){
    sentences.push('A focal length of '+focalLen+' mm was recorded, though the specific lens model is not stored.');
  }

  // Exposure settings
  if(hasExposure){
    var expParts=[];
    if(aperture){var ap=parseFloat(aperture);if(!isNaN(ap))expParts.push('an aperture of f/'+ap.toFixed(1));}
    if(shutter){
      var sv=shutter.toString();
      if(sv.includes('/')){expParts.push('a shutter speed of '+sv+' sec');}
      else{var sf=parseFloat(sv);if(!isNaN(sf)){expParts.push('a shutter speed of '+(sf<1?'1/'+Math.round(1/sf):sf.toFixed(1))+' sec');}}
    }
    if(iso)expParts.push('ISO '+iso);
    if(expParts.length>0){
      sentences.push('The exposure was set to '+expParts.join(', ')+'.');
    }
  }

  // Flash
  if(flash){
    var fl=flash.toLowerCase();
    if(fl.includes('no flash')||fl.includes('did not fire')||fl==='0'){
      sentences.push('No flash was used — this is a natural or ambient light shot.');
    } else if(fl.includes('fired')||fl.includes('flash')){
      sentences.push('Flash was used during the exposure.');
    }
  }

  // White balance / mode
  if(wb){
    sentences.push('White balance was set to '+(wb==='0'||wb.toLowerCase()==='auto'?'Auto':'Manual')+' mode.');
  }

  // Exposure program
  if(expProg&&expProg!=='0'){
    var progMap={'1':'Manual','2':'Program Auto','3':'Aperture Priority','4':'Shutter Priority','5':'Creative (Depth of Field)','6':'Action (High Speed)','7':'Portrait','8':'Landscape'};
    var prog=progMap[expProg]||expProg;
    sentences.push('The shooting mode was '+prog+'.');
  }

  // Date
  if(date){
    try{
      var clean=date.replace(/^(\d{4}):(\d{2}):(\d{2})/,'$1-$2-$3');
      var d=new Date(clean);
      if(!isNaN(d.getTime())){
        var opts2={year:'numeric',month:'long',day:'numeric',hour:'2-digit',minute:'2-digit'};
        sentences.push('The photograph was taken on '+d.toLocaleDateString('en-GB',{year:'numeric',month:'long',day:'numeric'})+'.');
      }
    }catch(e){}
  }

  // Resolution
  if(width&&height){
    var mp=(parseInt(width)*parseInt(height)/1000000).toFixed(1);
    sentences.push('The image resolution is '+width+' × '+height+' pixels ('+mp+' megapixels).');
  }

  // GPS
  if(hasGps){
    sentences.push('Location data is embedded in this image — GPS coordinates were recorded at the time of capture.');
  }

  // Software
  if(software&&!software.toLowerCase().includes('picasa')&&software.length<60){
    sentences.push('Post-processing or export was done via '+software+'.');
  }

  // Closing
  if(sentences.length<=2){
    sentences.push('The remaining metadata fields were not recorded or were removed during export or upload.');
  }

  return {
    type:'text',
    lines:sentences,
    label:'Camera & Lens Analysis'
  };
};
})();
