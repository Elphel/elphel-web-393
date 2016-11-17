/*
FILE NAME  : jp4-canvas.js
DESCRIPTION: Converts jp4/jp46 files into human perceivable format in html5 canvas.             
VERSION: 1.0
AUTHOR: Oleg K Dzhimiev <oleg@elphel.com>
LICENSE: AGPL, see http://www.gnu.org/licenses/agpl.txt
Copyright (C) 2016 Elphel, Inc.
*/

$(function(){
  
  var t1 = $("#test1").jp4({port:2323,width:600,fast:true});
  var t2 = $("#test2").jp4({port:2324,width:600,fast:true});
  var t3 = $("#test3").jp4({port:2325,width:600,fast:true});
  var t4 = $("#test4").jp4({port:2326,width:600,fast:true});

});
