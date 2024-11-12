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
function remove_mod(id) {
    var request = new XMLHttpRequest();
    request.open("GET", "./functions/remove-mod.php?bid="+build_id+"&id="+id);
    request.onreadystatechange = function() {
        if (request.readyState == 4 && request.status == 200) {
            if (request.responseText=='Mod removed') {
                $("#mod-"+id).remove();
            }
        }
    }
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
function add(name, v, mcv) {
    if ($("#versionselect-"+name+' option:selected').attr('missing')=='true') {
        return;
    }
    if ($("#versionselect-"+name).val()==null) {
        return;
    }
    $("#btn-add-mod-"+name).attr("disabled", true);
    $("#versionselect-"+name).attr("disabled", true);
    $("#cog-"+name).show();
    let id = $("#versionselect-"+name).val();
    var request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        if (request.readyState == 4 && request.status == 200) {
            if (request.responseText=="Mod added") {
                    // $("#mod-add-row-"+name).remove();
                $("#cog-"+name).hide();
                $("#check-"+name).show();

                // remember to modify index.php/build too
                $("#mods-in-build").append(`
                    <tr id="mod-${id}">
                        <td scope="row" data-value="${name}">${name}</td>
                        <td data-value="${v}">${v}</td>
                        <td data-value="${mcv}">${mcv}</td>
                        <td>
                            <button onclick="remove_mod(${id})" class="btn btn-danger">
                                <svg class="svg-inline--fa fa-times fa-w-11" aria-hidden="true" data-prefix="fas" data-icon="times" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 352 512" data-fa-i2svg=""><path fill="currentColor" d="M242.72 256l100.07-100.07c12.28-12.28 12.28-32.19 0-44.48l-22.24-22.24c-12.28-12.28-32.19-12.28-44.48 0L176 189.28 75.93 89.21c-12.28-12.28-32.19-12.28-44.48 0L9.21 111.45c-12.28 12.28-12.28 32.19 0 44.48L109.28 256 9.21 356.07c-12.28 12.28-12.28 32.19 0 44.48l22.24 22.24c12.28 12.28 32.2 12.28 44.48 0L176 322.72l100.07 100.07c12.28 12.28 32.2 12.28 44.48 0l22.24-22.24c12.28-12.28 12.28-32.19 0-44.48L242.72 256z"></path></svg><!-- <em class="fas fa-times"></em> -->
                            </button>
                        </td>
                        <td>
                            <svg style="font-size: 2em;display: none;" class="svg-inline--fa fa-cog fa-w-16 fa-spin" id="spinner-jei" aria-hidden="true" data-prefix="fas" data-icon="cog" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M444.788 291.1l42.616 24.599c4.867 2.809 7.126 8.618 5.459 13.985-11.07 35.642-29.97 67.842-54.689 94.586a12.016 12.016 0 0 1-14.832 2.254l-42.584-24.595a191.577 191.577 0 0 1-60.759 35.13v49.182a12.01 12.01 0 0 1-9.377 11.718c-34.956 7.85-72.499 8.256-109.219.007-5.49-1.233-9.403-6.096-9.403-11.723v-49.184a191.555 191.555 0 0 1-60.759-35.13l-42.584 24.595a12.016 12.016 0 0 1-14.832-2.254c-24.718-26.744-43.619-58.944-54.689-94.586-1.667-5.366.592-11.175 5.459-13.985L67.212 291.1a193.48 193.48 0 0 1 0-70.199l-42.616-24.599c-4.867-2.809-7.126-8.618-5.459-13.985 11.07-35.642 29.97-67.842 54.689-94.586a12.016 12.016 0 0 1 14.832-2.254l42.584 24.595a191.577 191.577 0 0 1 60.759-35.13V25.759a12.01 12.01 0 0 1 9.377-11.718c34.956-7.85 72.499-8.256 109.219-.007 5.49 1.233 9.403 6.096 9.403 11.723v49.184a191.555 191.555 0 0 1 60.759 35.13l42.584-24.595a12.016 12.016 0 0 1 14.832 2.254c24.718 26.744 43.619 58.944 54.689 94.586 1.667 5.366-.592 11.175-5.459 13.985L444.788 220.9a193.485 193.485 0 0 1 0 70.2zM336 256c0-44.112-35.888-80-80-80s-80 35.888-80 80 35.888 80 80 80 80-35.888 80-80z"></path></svg>
                        </td>
                    </tr>
                `);
            } else {
                $("#cog-"+name).hide();
                $("#times-"+name).show();
            }
        }
    };
    request.open("GET", "./functions/add-mod.php?bid="+build_id+"&id="+$("#versionselect-"+name).val());
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