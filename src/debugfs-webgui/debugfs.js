var CUT_STRING_LIMIT = 20;
var NLINES = 30;

var debugfs_data;

function init(){
    
    $("body").html("<h3>Linux Kernel Dynamic Debug, DebugFS:</h3>");
    
    var b0 = $("<button>",{
        id:"b0",
        title: "select/hide files"
    }).html("Edit list");
    b0.prop("state",0);
    
    b0.click(function(){
        if ($(this).prop("state")==0){
            $(this).prop("state",1)
            $(".hidden_rows").show();
        }else{
            $(this).prop("state",0)
            $(".hidden_rows").hide();
            $(".hidden_content").hide();
        }
    });
    
    var b1 = $("<button>",{
        id:"b1",
        title:"Copy /tmp/debugfs.json to /<path-to-debugfs.php>/debugfs.json"
    }).css({margin:"0px 0px 0px 10px"}).html("Save to persistent storage");
    
    b1.click(function(){
        $.ajax({
            url: "debugfs.php?cmd=savetofs",
            queue: true
        });
    });
    
    var b2 = $("<button>",{
        id:"b2",
        title:"Apply configuration of the selected files in GUI to DebugFS"
    }).css({margin:"0px 0px 0px 10px"}).html("Apply to debugfs (selected files)");
    
    b2.click(function(){
        $.ajax({
            url: "debugfs.php?cmd=restore",
            queue: true
        });
    });    
    
    $("body").append($("<div>").css({padding:"0px 0px 10px 0px"}).append(b0).append(b1).append(b2));
    
    //list header
    var t = $("<table border=\"1\">").html("\
        <tr>\
            <th style='display:none;' class='hidden_rows'>Show</th>\
            <th>File</th>\
        </tr>\
    ");
    
    $("body").append(t);
    
    //everything's initialized on response
    $.ajax({
        url: "debugfs.php",
        success: function(data){
            var r = jQuery.parseJSON(data);
            //global
            debugfs_data = r;
            
            var l,content,controls;
            
            //file walk
            for(var i=0;i<r.length;i++){         
                l        = init_ui_file(r[i],i);
                content  = init_ui_content(r[i],i);
                controls = init_ui_controls(r[i],i);                 
                t.append(l).append(controls).append(content);
                //line walk
                fill_content(r[i].configs[0].lines,i,content.find("#content_td"));
            }

            fill_content_rebind_events();
              
            $(".filename").click(function(){
                var index = $(this).attr("index");
                $("#content_"+index).toggle();
                $("#controls_"+index).toggle();
            });
            
            $(".visibility_cb").change(function(){
                var index = $(this).attr("index");
                if ($(this).prop("checked")){
                    $("#row_"+index).removeClass("hidden_rows");
                    $("#content_"+index).removeClass("hidden_content");
                    $("#controls_"+index).removeClass("hidden_content");
                    debugfs_data[index].state = 1;
                }else{
                    $("#row_"+index).addClass("hidden_rows");
                    $("#content_"+index).addClass("hidden_content");
                    $("#controls_"+index).addClass("hidden_content");
                    debugfs_data[index].state = 0;
                }
                update_debugfs_config();
            });
            
            //when everything is parsed. do something.
            // unique IDs
            // save config
        }
    });
}

function fill_content(record,index,target){
    
    target.html("");
    
    var table_index=0;

    for (var j=0;j<record.length;j++){
        table_index = Math.floor(j/NLINES);
        //shift because of 'all' checkbox
        if (j==((table_index+1)*NLINES-1)) table_index++;
        
        if (j%2==0) oddeven = "even";
        else        oddeven = "odd";
        
        //create those tables?!
        if (target.find("#ctbl_"+table_index).length==0) {
            ctbl = $("<table>",{id:"ctbl_"+table_index}).css({margin:"5px 30px 5px 5px",display:"inline"});
            
            if (table_index==0){
                //add all/none checkbox
                l  = "<tr>";
                l += "  <td style='text-align:center' title='check/uncheck all'>all</td>";
                l += "  <td style='text-align:center'><input id='all_"+index+"' title='check flags' type='checkbox' class='tp debugall' index='"+index+"' /></td>";
                l += "  <td></td>";
                l += "  <td></td>";
                l += "</tr>";
                
                ctbl.append(l);
            }
            
            target.append(ctbl);
        }
        
        ttl  = "module:      "+record[j].module+"\n";
        ttl += "function:    "+record[j].function+"\n";
        ttl += "format:      "+record[j].format;

        if (record[j].flags=="p"){
            checked = "checked";
        }else{
            checked = "";
        }

        if (record[j].function.length>CUT_STRING_LIMIT) cut_function = "...";
        else                                            cut_function = "";

        if (record[j].format.length>CUT_STRING_LIMIT) cut_format = "...";
        else                                          cut_format = "";

        l  = "<tr class='"+oddeven+"'>";
        l += "  <td style='text-align:center' title='"+ttl+"'>"+record[j].lineno+"</td>";
        l += "  <td style='text-align:center'><input title='pflag' type='checkbox' class='tp debug' "+checked+" file='"+record[j].file+"' line='"+record[j].lineno+"' index='"+index+"' subindex='"+j+"' /></td>";
        l += "  <td title=\"function:   "+record[j].function+"\">"+record[j].function.substr(0,20)+cut_function+"</td>";
        l += "  <td title=\"format:   "+record[j].format+"\">"+record[j].format.substr(0,20)+cut_format+"</td>";
        l += "</tr>";
        ctbl.append(l);   
    }
}

function fill_content_rebind_events(){
    //init actions
    $(".debug").off("change");
    $(".debug").change(function(){
        var index    = $(this).attr("index");
        var subindex = $(this).attr("subindex");
        
        var flags = "";
        if ($("#tflag_"+index).prop("checked")) flags += "t";
        if ($("#mflag_"+index).prop("checked")) flags += "m";
        if ($("#lflag_"+index).prop("checked")) flags += "l";
        if ($("#fflag_"+index).prop("checked")) flags += "f";
        
        if ($(this).prop("checked")) flags = "p"+flags;
        else                         flags = "_";
        
        debugfs_data[index].configs[0].lines[subindex].flags = flags;
        //console.log($(this).attr("file")+", "+$(this).attr("line")+", "+$(this).prop("checked"));
        $.ajax({
            url: "debugfs.php?cmd=echo&file="+$(this).attr("file")+"&line="+$(this).attr("line")+"&flags="+flags,
            queue: true
        });
    });
    
    $(".debugall").off("change");
    $(".debugall").change(function(){
        var index = $(this).attr("index");
        var checked = $(this).prop("checked");
        $("#content_"+index).find(".debug").prop("checked",checked).change();
    });
    
}

function init_ui_file(record,index){
    var l = $("<tr>",{id:"row_"+index}).html("\
        <td class='hidden_rows' style='text-align:center;display:none' >\
            <input id='cb_"+index+"' class='tp visibility_cb' type='checkbox' index='"+index+"' >\
        </td>\
        <td class='special filename' id='header_"+index+"' index='"+index+"' >"+record.file+"</td>\
    ");
    
    if (record.state==0){
        l.addClass("hidden_rows").hide();
        l.find("input").prop("checked",false);
    }else{
        l.find("input").prop("checked",true);
    }
    
    return l;
}

function init_ui_content(record,index){
    var content = $("<tr>",{
        id: "content_"+index
    }).css({
        display: "none",
        border: "0px solid rgba(255,255,255,0)"
    });
    
    if (record.state==0){
        content.addClass("hidden_content").hide();
    }
    
    content.append(
        $("<td>").addClass("hidden_rows").hide()
    ).append(
        $("<td>",{id:"content_td"})
    );
    
    return content;
}

function init_ui_controls(record,index){
    var controls = $("<tr>",{
        id: "controls_"+index
    }).css({
        display: "none",
        border: "0px solid rgba(255,255,255,0)"
    });
    
    if (record.state==0){
        controls.addClass("hidden_content").hide();
    }
    
    controls.append(
        $("<td>").addClass("hidden_rows").hide()
    ).append(
        $("<td>",{id:"controls_td"})
    );
    
    var bc0 = $("<button>",{
        id:"bc0_"+index,
        title:"read config from debugfs - useful when changes were made and the line numbers got shifted from the ones in the stored config",
        file:record.file
    }).css({margin:"5px 5px 5px 5px","font-size":"14px"}).html("read from debugfs");
    
    bc0.click(function(){
        var id = $(this).attr("id");
        id = id.substr(id.indexOf("_")+1);
        file = $(this).attr("file");
        $.ajax({
            url:"debugfs.php?cmd=reread&file="+file,
            queue: true,
            success:function(data){
                rec = jQuery.parseJSON(data);
                target = $("#content_"+id).find("#content_td");
                
                //apply existing checkboxes to rec
                oldrec = debugfs_data[id];
                
                lnew = rec.configs[0].lines.length;
                lold = debugfs_data[id].configs[0].lines.length;
                
                for(var i=0;i<lnew;i++){
                    if (i<lold) {
                        rec.configs[0].lines[i].flags=oldrec.configs[0].lines[i].flags;
                    }else{
                        rec.configs[0].lines[i].flags=oldrec.configs[0].lines[lold-1].flags;
                    }
                }
                
                //update debugfs_data
                debugfs_data[id].configs[0] = rec.configs[0];
                
                fill_content(rec.configs[0].lines,id,target);
                fill_content_rebind_events();
            }
        });
    });
        
    var f0 = $("<span title='Include the function name in the printed message'>");
    var f1 = $("<span title='Include line number in the printed message'>");
    var f2 = $("<span title='Include module name in the printed message'>");
    var f3 = $("<span title='Include thread ID in messages not generated from interrupt context'>");
    
    var f0_cb = $("<input>",{id:"fflag_"+index,type:"checkbox",class:"tp"}).css({position:"relative",top:"3px"});
    var f1_cb = $("<input>",{id:"lflag_"+index,type:"checkbox",class:"tp"}).css({position:"relative",top:"3px"});
    var f2_cb = $("<input>",{id:"mflag_"+index,type:"checkbox",class:"tp"}).css({position:"relative",top:"3px"});
    var f3_cb = $("<input>",{id:"tflag_"+index,type:"checkbox",class:"tp"}).css({position:"relative",top:"3px"});
    
    f0.html("&nbsp;&nbsp;f&nbsp;").append(f0_cb);
    f1.html("&nbsp;l&nbsp;").append(f1_cb);
    f2.html("&nbsp;m&nbsp;").append(f2_cb);
    f3.html("&nbsp;t&nbsp;").append(f3_cb);
    
    var pre_bc1 = $("<span>",{title:"Current config name"}).html("&nbsp;&nbsp;config:&nbsp;");
    
    var dc0_b = $("<button>",{
        class:"btn btn-default btn-sm btn-success dropdown-toggle",
        type:"button",
        "data-toggle":"dropdown",
        "aria-haspopup":"true",
        "aria-expanded":"false"
    }).css({
        display:"inline",
        width: "150px",
    }).html("default <span class='caret'></span>");
        
    var dc0_ul = $("<ul>",{class:"dropdown-menu"}).css({padding:"5px","min-width":"100px",border:"1px solid rgba(50,50,50,0.5)"});
    
    dc0_ul.append($("<li>").css({padding:"5px"}).html("<input type='text' style='width:100px;' placeholder='create new'/>"))
          .append($("<li>").css({padding:"5px"}).html("item 1"))
          .append($("<li>").css({padding:"5px"}).html("item 2"));
    
    var dc0 = $("<div>",{class:"btn-group",role:"group"}).append(dc0_b).append(dc0_ul);
    
    controls.find("#controls_td").append(bc0).append(f0).append(f1).append(f2).append(f3).append(pre_bc1).append(dc0);
    
    return controls;
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
