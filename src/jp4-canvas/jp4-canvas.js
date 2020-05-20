/** 
 * @file jp4-canvas.js
 * @brief simple init for jp4-canvas.html
 * @copyright Copyright (C) 2016 Elphel Inc.
 * @author Oleg Dzhimiev <oleg@elphel.com>
 *
 * @licstart  The following is the entire license notice for the 
 * JavaScript code in this page.
 *
 *   The JavaScript code in this page is free software: you can
 *   redistribute it and/or modify it under the terms of the GNU
 *   General Public License (GNU GPL) as published by the Free Software
 *   Foundation, either version 3 of the License, or (at your option)
 *   any later version.  The code is distributed WITHOUT ANY WARRANTY;
 *   without even the implied warranty of MERCHANTABILITY or FITNESS
 *   FOR A PARTICULAR PURPOSE.  See the GNU GPL for more details.
 *
 *   As additional permission under GNU GPL version 3 section 7, you
 *   may distribute non-source (e.g., minimized or compacted) forms of
 *   that code without the copy of the GNU GPL normally required by
 *   section 4, provided you include this license notice and a URL
 *   through which recipients can access the Corresponding Source.
 *
 *  @licend  The above is the entire license notice
 *  for the JavaScript code in this page.
 */

$(function(){

  let t1 = $("#test1").jp4({src:"http://"+location.host+":"+2323+"/img",width:600,fast:true,lowres:2,debug:false,refresh:false});
  let t2 = $("#test2").jp4({src:"http://"+location.host+":"+2324+"/img",width:600,fast:true,lowres:2,debug:false,refresh:false});
  let t3 = $("#test3").jp4({src:"http://"+location.host+":"+2325+"/img",width:600,fast:true,lowres:2,debug:false,refresh:false});
  let t4 = $("#test4").jp4({src:"http://"+location.host+":"+2326+"/img",width:600,fast:true,lowres:2,debug:false,refresh:false});

});
