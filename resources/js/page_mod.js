function remove_box(id,version,name) {
    $("#mod-name-title").text(name+" "+version);
    $("#mod-name").text(name+" "+version);
    $("#remove-button").attr("onclick","remove("+id+",false)");
    $("#remove-button-force").attr("onclick","remove("+id+",true)");
}
function remove(id,force) {
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
            } else {
                // todo: use styled alert instead of this
                // alert("Cannot delete modloader as it is used by a build.");
                // $("#removeModWarn").show();
                if (confirm(response['message']+" Press OK to go to '"+response['bname']+"'")) {
                    window.location.href="/build?id="+response['bid'];
                }
            }
        }
    }
    request.open("GET", "./functions/delete-mod.php?id="+id+'&force='+force);
    request.send();
}

$(document).ready(function(){
    $("#nav-mods").trigger('click');
});