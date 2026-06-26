(function(){
'use strict';
window.JennieTools=window.JennieTools||{};

window.JennieTools['face-detect']=async function(file,opts){
  // Load face-api.js from CDN if not already loaded
  if(!window.faceapi){
    await new Promise(function(res,rej){
      var s=document.createElement('script');
      s.src='https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js';
      s.onload=res;s.onerror=function(){rej(new Error('Neural network module failed to load. Check your internet connection.'));};
      document.head.appendChild(s);
    });
  }

  // Load tiny face detector model from a reliable CDN
  var MODEL_URL='https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model';
  try{
    if(!window.faceapi.nets.tinyFaceDetector.isLoaded){
      await window.faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
    }
    if(!window.faceapi.nets.ageGenderNet.isLoaded){
      await window.faceapi.nets.ageGenderNet.loadFromUri(MODEL_URL);
    }
  }catch(e){
    // Fallback: try without age/gender if model fails
  }

  return new Promise(function(resolve,reject){
    var reader=new FileReader();
    reader.onload=function(ev){
      var img=new Image();
      img.onload=async function(){
        try{
          var options=new window.faceapi.TinyFaceDetectorOptions({inputSize:416,scoreThreshold:0.4});
          var detections;
          try{
            detections=await window.faceapi.detectAllFaces(img,options).withAgeAndGender();
          }catch(e){
            detections=await window.faceapi.detectAllFaces(img,options);
          }

          var count=detections?detections.length:0;
          var lines=[];

          if(count===0){
            lines.push('No human faces detected in this image.');
            lines.push('The image may contain objects, scenery, or animals rather than people. JennieAI scanned the full image at high resolution.');
            lines.push('Tip: Try a clearer, well-lit image with visible faces for best results.');
          } else {
            var faceWord=count===1?'face':'faces';
            lines.push('JennieAI detected '+count+' human '+faceWord+' in this image.');

            if(detections[0] && detections[0].age){
              var genders={male:0,female:0};
              var totalAge=0;
              var ageCount=0;
              detections.forEach(function(d){
                if(d.gender){
                  if(d.gender==='male')genders.male++;
                  else genders.female++;
                }
                if(d.age){totalAge+=d.age;ageCount++;}
              });
              if(ageCount>0){
                var avgAge=Math.round(totalAge/ageCount);
                lines.push('Estimated average age of subjects: approximately '+avgAge+' years.');
              }
              if(count>1){
                if(genders.male>0&&genders.female>0){
                  lines.push('Gender composition: '+genders.male+' male and '+genders.female+' female subject'+(genders.female>1?'s':'')+'.');
                } else if(genders.male>0){
                  lines.push('All '+count+' detected subjects appear to be male.');
                } else if(genders.female>0){
                  lines.push('All '+count+' detected subjects appear to be female.');
                }
              }
            }

            if(count===1){
              lines.push('This appears to be a portrait — ideal for profile photos, headshots, or editorial use.');
            } else if(count>=2&&count<=4){
              lines.push('A small group of '+count+' people is present — suitable for team photos or event photography.');
            } else {
              lines.push('A crowd or large group of '+count+' people detected — great for event or documentary photography.');
            }
            lines.push('Confidence threshold: 40% minimum per face. Results are AI estimates and may vary.');
          }

          resolve({type:'text',lines:lines,label:'Face Analysis — '+count+' face'+(count!==1?'s':'')});
        }catch(err){
          reject(new Error('Analysis could not be completed: '+err.message));
        }
      };
      img.onerror=function(){reject(new Error('Could not decode image file.'));};
      img.src=ev.target.result;
    };
    reader.onerror=function(){reject(new Error('File could not be read.'));};
    reader.readAsDataURL(file);
  });
};
})();
