function remove_box(id,version,name) {
    $("#mod-name-title").text(name+" "+version);
    $("#mod-name").text(name+" "+version);
    $("#remove-button").attr("onclick","remove("+id+",'"+name+"',false)");
    $("#remove-button").text('Delete')
    $('#rm-message').empty()
}
function remove(id,name,force) {
    var request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            console.log(this.response);
            response = JSON.parse(this.response);
            if (response['status']=='succ') {
                console.log('success!');
                $("#mod-row-"+id).remove();
                if ($("#table-mods tr").length==0) {
                    window.location = "./lib-mods";
                }
                $("#removeMod").modal('hide')
            } else {
                $('#rm-message').html(`<br/><p><b>${name} is in use!</b></p>`);
                $("#remove-button").attr("onclick","remove("+id+",'"+name+"',true)")
                $("#remove-button").text('Force delete')
            }
        }
    }
    request.open("GET", "./functions/delete-mod.php?id="+id+'&force='+force);
    request.send();
}

$(document).ready(function(){
    $("#nav-mods").trigger('click');
});