$("#pn").on("keyup", function(){
  var slug = slugify($(this).val());
  console.log(slug);
  $("#slug").val(slug);
});

$(document).ready(function(){
  $("#nav-mods").trigger('click');
  $(function () {
      $('[data-toggle="popover"]').popover()
  })
});