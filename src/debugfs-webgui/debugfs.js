var CUT_STRING_LIMIT = 20;
var NLINES = 30;

var debugfs_data;

function init(){
    
    $("body").html("<h3>DebugFS:</h3>");
    
    var b0 = $("<button>",{id:"b0"}).html("toggle hidden");
    b0.prop("state",0);
    
    b0.click(function(){
        if ($(this).prop("state")==0){
            $(this).prop("state",1)
            $(".hidden_rows").show();
        }else{
            $(this).prop("state",0)
            $(".hidden_rows").hide();
        }
    });
    
    var b1 = $("<button>",{id:"b1"}).css({margin:"0px 0px 0px 10px"}).html("save to fs");
    
    b1.click(function(){
        $.ajax({
            url: "debugfs.php?cmd=savetofs"
        });
    });
    
    $("body").append($("<div>").css({padding:"0px 0px 10px 0px"}).append(b0).append(b1));
    
    var t = $("<table border=\"1\">").html("\
        <tr>\
            <th style='display:none;' class='hidden_rows'>Show</th>\
            <th>File</th>\
        </tr>\
    ");
    
    $("body").append(t);
    
    $.ajax({
        url: "debugfs.php",
        success: function(data){
            var r = jQuery.parseJSON(data);
            //global
            debugfs_data = r;
            
            for(var i=0;i<r.length;i++){
                                
                l = $("<tr>",{id:"row_"+i}).html("\
                    <td class='hidden_rows' style='text-align:center;display:none' >\
                        <input id='cb_"+i+"' class='tp visibility_cb' type='checkbox'>\
                    </td>\
                    <td class='special filename' id='header_"+i+" '>"+r[i].file+"</td>\
                ");
                
                if (r[i].state==0){
                    l.addClass("hidden_rows").hide();
                    l.find("input").prop("checked",false);
                }else{
                    l.removeClass("hidden_rows").show();
                    l.find("input").prop("checked",true);
                }
                
                content = $("<tr>",{
                    id: "content_"+i
                }).css({
                    display: "none",
                    border: "0px solid rgba(255,255,255,0)"
                });                
                
                content.append(
                    $("<td>").addClass("hidden_rows").hide()
                ).append(
                    $("<td>",{id:"content_td"})
                );
                
                //$("<table>").css({margin:"5px"})
                
                //.append($("<td>").addClass("hidden_rows").css({display:"none"}))
                
                t.append(l).append(content);
                
                var r1 = r[i].configs[0].lines;
                
                var table_index=0;
                
                for (var j=0;j<r1.length;j++){
                    table_index = Math.floor(j/NLINES);
                    
                    if (j%2==0) oddeven = "even";
                    else        oddeven = "odd";
                    
                    //create those tables?!
                    if (content.find("#ctbl_"+table_index).length==0) {
                        ctbl = $("<table>",{id:"ctbl_"+table_index}).css({margin:"5px 30px 5px 5px",display:"inline"});
                        content.find("#content_td").append(ctbl);
                    }
                    
                    ttl  = "module:      "+r1[j].module+"\n";
                    ttl += "function:    "+r1[j].function+"\n";
                    ttl += "format:      "+r1[j].format;
                
                    if (r1[j].flags=="p"){
                        checked = "checked";
                    }else{
                        checked = "";
                    }
                
                    if (r1[j].function.length>CUT_STRING_LIMIT) cut_function = "...";
                    else                                        cut_function = "";
           
                    if (r1[j].format.length>CUT_STRING_LIMIT) cut_format = "...";
                    else                                      cut_format = "";
           
                    l  = "<tr class='"+oddeven+"'>";
                    l += "  <td style='text-align:center' title='"+ttl+"'>"+r1[j].lineno+"</td>";
                    l += "  <td style='text-align:center'><input type='checkbox' class='tp debug' "+checked+" file='"+r1[j].file+"' line='"+r1[j].lineno+"' /></td>";
                    l += "  <td>"+r1[j].function.substr(0,20)+"...</td>";
                    l += "  <td>"+r1[j].format.substr(0,20)+"...</td>";
                    l += "</tr>";
                    ctbl.append(l);
                }
            }
            
            //init actions
            $(".debug").change(function(){
                console.log($(this).attr("file")+", "+$(this).attr("line")+", "+$(this).prop("checked"));
                $.ajax({
                    url: "debugfs.php?cmd=echo&file="+$(this).attr("file")+"&line="+$(this).attr("line")+"&pflag="+$(this).prop("checked")
                });
            });
            
            $(".filename").click(function(){
                var id = $(this).attr("id");
                id = id.substr(id.indexOf("_")+1);
                console.log(id);
                $("#content_"+id).toggle();
            });
            
            $(".visibility_cb").change(function(){
                var id = $(this).attr("id");
                id = id.substr(id.indexOf("_")+1);
                if ($(this).prop("checked")){
                    $("#row_"+id).removeClass("hidden_rows");
                    debugfs_data[id].state = 1;
                }else{
                    $("#row_"+id).addClass("hidden_rows");
                    $("#content_"+id).hide();
                    debugfs_data[id].state = 0;
                }
                update_debugfs_config();
            });
            
            //when everything is parsed. do something.
            // unique IDs
            // save config
        }
    });
}

function update_debugfs_config(){
    console.log("syncing debugfs config");
    //console.log(debugfs_data);
    $.ajax({
        type: "POST",
        url: "debugfs.php?cmd=sync",
        data: JSON.stringify(debugfs_data),
        dataType: "json"
    });
}
