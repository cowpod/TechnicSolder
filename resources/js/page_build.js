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

$("#search").on('keyup',function(){
    tr = document.getElementById("modstable").getElementsByTagName("tr");

    for (var i = 0; i < tr.length; i++) {

        td = tr[i].getElementsByTagName("td")[0];
        if (td) {

            console.log(td);
            console.log(td.innerHTML.toUpperCase())
            if (td.innerHTML.toUpperCase().indexOf($("#search").val().toUpperCase()) > -1) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
});
$("#search2").on('keyup',function(){
    tr = document.getElementById("filestable").getElementsByTagName("tr");

    for (var i = 0; i < tr.length; i++) {

        td = tr[i].getElementsByTagName("td")[0];
        if (td) {

            console.log(td);
            console.log(td.innerHTML.toUpperCase())
            if (td.innerHTML.toUpperCase().indexOf($("#search").val().toUpperCase()) > -1) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
});
function add(name) {
    if ($("#versionselect-"+name+' option:selected').attr('missing')=='true') {
        return;
    }
    if ($("#versionselect-"+name).val()==null) {
        return;
    }
    $("#btn-add-mod-"+name).attr("disabled", true);
    // $("#cog-"+name).show();
    var request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            if (this.responseText=="Insufficient permission!") {
                // $("#cog-"+name).hide();
                // $("#times-"+name).show();
            } else {
                // $("#cog-"+name).hide();
                // $("#check-"+name).show();
                // setTimeout(function() {
                    $("#mod-add-row-"+name).remove();
                // }, 0);

            }
        }
    };
    request.open("GET", "./functions/add-mod.php?bid="+build_id+"&id="+$("#versionselect-"+name).val());
    request.send();
}

$('#showall').change(function() {
    var request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            window.location.reload();
        }
    };
    if ( $('#showall').is(':checked') ) {
        request.open("GET", "./functions/change_show_all.php?showall=true");
    } else {
        request.open("GET", "./functions/change_show_all.php?showall=false");
    }
    request.send();
});

$(document).on('change', '.form-control', function() {
    if ($(this).attr('id').startsWith('versionselect-')) {
        var id=$(this).attr('id');
        var name=$(this).attr('modname');

        var addButton = $('#btn-add-mod-'+name);
        var selected = $('#'+id+' option:selected');

        if(selected.attr('missing')==='true') {
            addButton.attr('disabled','true');
        } else {
            addButton.removeAttr('disabled');
        }
        console.log('#btn-add-mod-'+modid);
    }
});