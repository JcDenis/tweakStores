/*global $, dotclear */
'use strict';

$(function(){
  $("#ts_copy_button").click(function() {
    tsCopy("#gen_xml");
    return false;
  });

  function tsCopy(element_id) {
    $(element_id).attr("contenteditable", true)
      .select()
      .on("focus", function() {
        document.execCommand('selectAll', false, null)
      })
      .focus()
    document.execCommand("Copy");
    $(element_id).removeAttr("contenteditable");
     alert(dotclear.ts_copied);
  }
});