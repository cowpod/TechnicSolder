$('#versions').change(function(){
    $('#editBuild').modal('show');
});
function fnone(){
    $('#versions').val(modslist_0);
    $('#forgec').val('none');
};
function fchange(){
    $('#forgec').val('change');
    $('#submit-button').trigger('click');
};
function fwipe(){
    $('#forgec').val('wipe');
    $('#submit-button').trigger('click');
};

function remove_mod(id,name) {
    $("#mod-"+name).remove();
    var request = new XMLHttpRequest();
    request.open("GET", "./functions/remove-mod.php?bid="+build_id+"&id="+id);
    request.send();
}
function changeversion(id, mod, name, compatible) {
    if (!compatible) {
        $("#mod-"+name).removeClass("table-warning");
        $("#warn-incompatible-"+name).hide();
        /*$("#bmversions-"+name).children().each(function(){
            if (this.value == mod) {
                this.remove();
            }
        });*/
    }
    $("#bmversions-"+name).attr("onchange","changeversion(this.value,"+id+",'"+name+"',true)");
    $("#spinner-"+name).show();
    var request = new XMLHttpRequest();
    request.open("GET", "./functions/change-version.php?bid="+build_id+"&id="+id+"&mod="+mod);
    request.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            $("#spinner-"+name).hide();
        }
    }
    request.send();
}

function add_o(id) {
    $("#btn-add-o-"+id).attr("disabled", true);
    $("#cog-o-"+id).show();
    var request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            $("#cog-o-"+id).hide();
            $("#check-o-"+id).show();
        }
    };
    request.open("GET", "./functions/add-mod.php?bid="+build_id+"&id="+id);
    request.send();
}