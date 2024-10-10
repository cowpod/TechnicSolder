function remove_box(id,version,name) {
    $("#mod-name-title").text(name+" "+version);
    $("#mod-name").text(name+" "+version);
    $("#remove-button").attr("onclick","remove("+id+")");
}
function remove(id) {
    var request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        $("#mod-row-"+id).remove();
        if ($("#table-mods tr").length==0) {
            window.location = "./lib-mods";
        }
    }
    request.open("GET", "./functions/delete-modv.php?id="+id);
    request.send();
}

$("#pn").on("keyup", function(){
    var slug = slugify($(this).val());
    console.log(slug);
    $("#slug").val(slug);
});

$(document).ready(function(){
    $("#nav-mods").trigger('click');
});