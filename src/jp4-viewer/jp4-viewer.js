
/*
 * 'quality' values 1,2,4,8, where
 * 1 - best resolution but slowest
 * 8 - worst resolution but fastest
 * 
 * 'width' - canvas width
 */


var SETTINGS = {
   width: 800,
   quality: 1
};

function parseURL(){
    var parameters=location.href.replace(/\?/ig,"&").split("&");
    for (var i=0;i<parameters.length;i++) parameters[i]=parameters[i].split("=");
    for (var i=1;i<parameters.length;i++) {
        switch (parameters[i][0]) {
            case "width":   SETTINGS.width = parseInt(parameters[i][1]); break;
            case "quality": SETTINGS.quality = parseInt(parameters[i][1]); break;
        }
    }
}
 
$(function(){
    
    $.ajax({
      url: location.host+":2323",
      success: function(){
        console.log("success");
      }
    });
  
    parseURL();
    init();
    
});

function init(){
    
    var imageLoader = document.getElementById('up_input');
    //console.log(imageLoader);
    imageLoader.addEventListener('change', handleImage, false);
    
}

function handleImage(e) {
    
    $("#help").hide();
    
    var reader = new FileReader();
    reader.onload = function(event){
        //$("#smallimage").attr("src",event.target.result);
        
        console.log("File loaded");

        $("#jp4view").html("");
        var view = $("<div>",{id:"img"});

        //data:image/jpeg;base64,  
        myimg = event.target.result;
        myimg = myimg.replace(/^data:;base64,/ig,"data:image/jpeg;base64,");

        //console.log(SETTINGS.width);
        
        view.jp4({image:myimg, fromhtmlinput: true, width:SETTINGS.width,fast:true, lowres:SETTINGS.quality, webworker_path:"../js"});
        //view.jp4({image:"test.jp4", input: false, width:1200,fast:true, lowres:1});
        $("#jp4view").append(view);
        
    }
    reader.readAsDataURL(e.target.files[0]);

}
