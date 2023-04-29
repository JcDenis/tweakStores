/*global $, dotclear */
'use strict';

Object.assign(dotclear.msg, dotclear.getData('tweakstore_copied'));

$(() => {

  $('#tweakstore_form #tweakstore_submit').hide();
  $('#tweakstore_form #tweakstore_id').on('change', function () {
    if (this.value != '0'){this.form.submit();}
  });
  dotclear.condSubmit('#tweakstore_form #tweakstore_id', '#tweakstore_form #tweakstore_submit');


  $("#tweakstore_copy").click(function() {
    var style = $("#tweakstore_gen").attr('style');
    $("#tweakstore_gen").attr('style', '').attr("contenteditable", true)
      .select()
      .on("focus", function() {
        document.execCommand('selectAll', false, null)
      })
      .focus()
    document.execCommand("Copy");
    $("#tweakstore_gen").removeAttr("contenteditable").attr('style', style);
    $("#tweakstore_copy").focus();

     alert(dotclear.msg.alert);
    return false;
  });
});