$('#versions').change(function(){
    $('#editBuild').modal('show');
});
function fnone(){
    $('#versions').val(INSTALLED_MODS[0]); // first item in list is modloader id
    $('#forgec').val('none');
};
function fchange(){
    $('#forgec').val('change');
    $('#build-details-save').trigger('click');
};
function fwipe(){
    $('#forgec').val('wipe');
    $('#build-details-save').trigger('click');
    setTimeout(function() {
        window.location.reload();
    }, 250);
};
function remove_mod(id,name) {
    var request = new XMLHttpRequest();
    request.open("GET", "./functions/remove-mod.php?bid="+BUILD_ID+"&id="+id);
    request.onreadystatechange = function() {
        if (request.readyState == 4 && request.status == 200) {
            if (request.responseText=='Mod removed') {
                $("#mod-"+id).remove();
                let index = INSTALLED_MODS.indexOf(id);
                if (index!==-1) {
                    INSTALLED_MODS.splice(index, 1);
                }
                // let index = INSTALLED_MOD_NAMES.indexOf(name);
                if (index!==-1) {
                    INSTALLED_MOD_NAMES.splice(index, 1);
                }
                
            }
        }
    }
    request.send();
}
function changeversion(id_new, id_old, name, compatible) {
    if (!compatible) {
        $("#mod-"+name).removeClass("table-warning");
        $("#warn-incompatible-"+name).hide();
        /*$("#bmversions-"+name).children().each(function(){
            if (this.value == id_old) {
                this.remove();
            }
        });*/
    }
    $("#bmversions-"+name).attr("onchange","changeversion(this.value,"+id_new+",'"+name+"',true)");
    $("#spinner-"+name).show();
    var request = new XMLHttpRequest();
    request.open("GET", "./functions/change-version.php?bid="+BUILD_ID+"&id_new="+id_new+"&id_old="+id_old);
    request.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            $("#spinner-"+name).hide();

            var index = INSTALLED_MODS.indexOf(id_old);
            if (index!==-1) {
                INSTALLED_MODS.splice(index, 1);
            }
            INSTALLED_MODS.push(id_new);

            // var index = INSTALLED_MOD_NAMES.indexOf(name);
            if (index!==-1) {
                INSTALLED_MOD_NAMES.splice(index, 1);
            }
            INSTALLED_MOD_NAMES.push(name);
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
    request.open("GET", "./functions/add-mod.php?bid="+BUILD_ID+"&id="+id);
    request.send();
}
function add(name, pretty_name, id, mcv) {
    if ($("#versionselect-"+name+' option:selected').attr('missing')=='true') {
        return;
    }
    if ($("#versionselect-"+name+' option:selected').val()==null) {
        return;
    }
    if ($("#versionselect-"+name).val()==null) {
        return;
    }
    $("#versionselect-"+name).attr("disabled", true);
    $("#btn-add-mod-"+name).attr("disabled", true);
    // $('#btn-add-mod-'+name).html('<em class="fas fa-cog fa-spin">');
    let v = $("#versionselect-"+name+' option:selected').val(); // todo: this is valid, but id is not!

    var request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        if (request.readyState == 4 && request.status == 200) {
            if (request.responseText=="Mod added") {
                $("#mod-add-row-"+name).remove();
                // $('#btn-add-mod-'+name).html('<em class="text-success fas fa-check"></em>');

                // remember to modify index.php/build too
                $("#mods-in-build").append(`
                    <tr id="mod-${id}">
                        <td scope="row" data-value="${pretty_name}">${pretty_name}</td>
                        <td data-value="${v}">${v}</td>
                        <td data-value="${mcv}" class="d-none d-sm-table-cell">${mcv}</td>
                        <td>
                            <button onclick="remove_mod(${id},${name})" class="btn btn-danger">
                                <em class="fas fa-times"></em>
                            </button>
                        </td>
                    </tr>
                `);

                INSTALLED_MODS.push(id);
                INSTALLED_MOD_NAMES.push(name)

                // remove from list of available mods
                const index = rows_mods_available.indexOf(name);
                if (index > -1) { 
                  rows_mods_available.splice(index, 1);
                }
            } else {
                // $('#btn-add-mod-'+name).html('Add to build');
            }
        }
    };
    console.log("./functions/add-mod.php?bid="+BUILD_ID+"&id="+id)
    request.open("GET", "./functions/add-mod.php?bid="+BUILD_ID+"&id="+id);
    request.send();
}

function add_mod_row(id,pretty_name,name,versions,mcv) {
    // console.log('adding row',name);
    if (pretty_name=='' || name=='' || versions=='' || mcv=='') {
        var addbutton=`<a id="btn-add-mod-${name}" href="mod?id=${name}" class="btn btn-warning">Issue(s)</a>`;
    } else {
        var addbutton=`<a id="btn-add-mod-${name}" onclick="add('${name}', '${pretty_name}', '${id}', '${mcv}')" class="btn btn-primary">Add to build</a>`;
    }
    
    var versions_str = ``;
    for (let v of versions) {
        versions_str += `<option>${v}</option>`;
    }

    $('#build-available-mods').append(`
        <tr id="mod-add-row-${name}">
            <td scope="row" data-value="${pretty_name}">${pretty_name}</td>
            <td data-value="${versions}"><select id="versionselect-${name}" class="form-control">${versions_str}</select></td>
            <td data-value="${mcv}">${mcv}</td>
            <td data-value="Add to build">
                ${addbutton}
            </td>
        </tr>
    `);
    // console.log('added')
}

var rows_mods_available=[];

function parsemods(obj) {
    let added_num=0;

    let versions={};
    for (let mod of obj) {
        if (!INSTALLED_MODS.includes(''+mod['id']) && mod['loader']==TYPE) {
            if (mod['name'] in versions) {
                versions[mod['name']].push(mod['version']);
            } else {
                versions[mod['name']]=[mod['version']];
            }
        }
    }
    let filter=$("#search").val();
    for (let mod of obj) {
        if (filter=='' || filter==undefined || mod['pretty_name'].toLowerCase().includes(filter)||mod['name'].toLowerCase().includes(filter)) {

            if (INSTALLED_MOD_NAMES.includes(mod['name']) || INSTALLED_MODS.includes(''+mod['id']) || rows_mods_available.includes(mod['name'])) {
            } else if ($('#showall').is(':checked') || mod['mcversion']=='' || MCV==mod['mcversion'] || isVersionInInterval(`'${MCV}'`, mod['mcversion'])) {
                rows_mods_available.push(mod['name']);
                add_mod_row(mod['id'], mod['pretty_name'], mod['name'], versions[mod['name']], mod['mcversion']);
                added_num+=1;
            }
        }
    
    }
    if (added_num==0) {
        if (filter!='') {
            $('#no-mods-available-search-results').show();
        } else {
            $('#no-mods-available').show();
        }
    }
}

function getmods() {
    $('#build-available-mods').empty();
    $('#no-mods-available').hide();
    $('#no-mods-available-search-results').hide();
    if  (get_cached('available_mods')) {
        parsemods(JSON.parse(get_cached('available_mods')));
    } else {
        var request = new XMLHttpRequest();
        request.onreadystatechange = function() {
            if (request.readyState == 4 && request.status == 200) {
                set_cached('available_mods',request.responseText,5);
                parsemods(JSON.parse(request.responseText));
            }
        }
        request.onerror = function() {
            console.log('could not get mods from api');

        }
        request.open("GET", `api/mod?loadertype=${TYPE}`);
        request.send();
    }
}

$("#search").on('keyup', function(){
    rows_mods_available=[];
    getmods(); // get mods on type
});

$("#search2").on('keyup',function(){
    tr = document.getElementById("filestable").getElementsByTagName("tr");

    for (var i = 0; i < tr.length; i++) {

        td = tr[i].getElementsByTagName("td")[0];
        if (td) {
            if (td.innerHTML.toUpperCase().indexOf($("#search").val().toUpperCase()) > -1) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
});
function toggled_showall(){
    rows_mods_available=[];
    if ($('#showall').is(':checked')) {
        $('#mods-for-version-string').text('');
        $('#mods-for-version-string').hide();
    } else {
        $('#mods-for-version-string').text(' for Minecraft '+MCV);
        $('#mods-for-version-string').show();
    }
    set_cached('showall', $('#showall').is(':checked'), -1);
    getmods();
}

$('#showall').change(function() {
    toggled_showall()
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
        console.log('#btn-add-mod-'+id);
    }
});

// todo: save build details should do this, instead of on every change
function saveAllowedClients() {
    let checkedItems = [];
    $('.buildClientId:checked').each(function(){
        checkedItems.push($(this).val());
    });
    console.log(checkedItems);

    let formData = new FormData();
    formData.set('build_id', $("#build_id").val());
    formData.set('client_ids', checkedItems.join(','));
    let request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        if (request.readyState == 4 && request.status == 200) {
            console.log(request.responseText);
            let json = JSON.parse(request.responseText);
            if (json['status']==='succ') {
                $('#update-build-clients-submit').attr('disabled', true);
            }
        }
    }
    request.onerror = function() {
        console.log('could not set build clients');
    }
    request.open('POST', 'functions/update-allowed-clients.php');
    request.send(formData);
}

$('#public').on('change', function() {
    if ($('#public').is(':checked')) {
        $('#card-allowed-clients').hide();
    } else {
        $('#card-allowed-clients').show();
    }
})

$('#build-details :input').on('change input', function () {
    $('#build-details-save').removeAttr('disabled');
});

$('#build-details').on('submit', function(e) {
    e.preventDefault();
    let formData = $(this).serialize();
    let request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        if (request.readyState == 4 && request.status == 200) {
            console.log(request.responseText);
            let json = JSON.parse(request.responseText);
            if (json['status']==='succ') {
                saveAllowedClients();
                $('#build-details-save').attr('disabled', true);
                if ($('#build-details-save').attr('custom_reload') !== undefined && $('#build-details-save').attr('custom_reload')) {
                    setTimeout(function() {
                        window.location.reload();
                    }, 250);
                }
            }
        }
    }
    request.onerror = function() {
        console.log('could not set build clients');
    }
    request.open('POST', 'functions/update-build.php');
    request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    request.send(formData);

    $(this).serializeArray().forEach(function(field) {
        console.log(field.name + ': ' + field.value);
    });
})

$(document).ready(function() {
    if (get_cached('showall') && $('#showall').prop('checked', get_cached('showall'))) {
        toggled_showall()
    } else {
        //rows_mods_available=[];
        getmods();
    }
});