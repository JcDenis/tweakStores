/*global $, dotclear */
'use strict';

$(function(){
  $("#ts_copy_button").click(function() {
    var style = $("#gen_xml").attr('style');
    $("#gen_xml").attr('style', '').attr("contenteditable", true)
      .select()
      .on("focus", function() {
        document.execCommand('selectAll', false, null)
      })
      .focus()
    document.execCommand("Copy");
    $("#gen_xml").removeAttr("contenteditable").attr('style', style);
    $("#ts_copy_button").focus();

     alert(dotclear.ts_copied);
    return false;
  });
});