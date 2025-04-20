// var builds = "<?php echo addslashes(json_encode($mpab)) ?>";
// var sbn = "<?php echo addslashes(json_encode($sbn)) ?>";
var bd = (builds && builds.length>=0) ? JSON.parse(builds).reverse() : '{}';
var sbna =(sbn && sbn.length>=0) ? JSON.parse(sbn) : '{}';

async function fillBuildlist() {
    $("#buildlist").children().each(function(){ 
        this.remove()
    })

    for (let element of bd) {
        if ($("#mplist").val() == element['mpid']) {
            $("#buildlist").append("<option value='"+element['id']+"'>"+element['mpname']+" - "+element['name']+"</option>")
        }
    }
}
$("#mplist").change(async function() {
    await fillBuildlist();
});

$("#newbname").on("keyup",function(){
    if (sbna.includes($("#newbname").val())) {
        $("#newbname").addClass("is-invalid");
        $("#warn_newbname").show();
        $("#create1").prop("disabled",true);
        $("#create2").prop("disabled",true);
    } else {
        $("#newbname").removeClass("is-invalid");
        $("#warn_newbname").hide();
        $("#create1").prop("disabled",false);
        $("#create2").prop("disabled",false);
    }
});
$("#newname").on("keyup",function(){
    if (sbna.includes($("#newname").val())) {
        $("#newname").addClass("is-invalid");
        $("#warn_newname").show();
        $("#copybutton").prop("disabled",true);
    } else {
        $("#newname").removeClass("is-invalid");
        $("#warn_newname").hide();
        $("#copybutton").prop("disabled",false);
    }
});

function edit(id) {
    window.location = "./build?id="+id;
}
function remove_box(id,name) {
    $("#build-title").text(name);
    $("#build-text").text(name);
    $("#remove-button").attr("onclick","remove("+id+")");
}
function set_public(id) {
    $("#cog-"+id).show();
    var request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            console.log(this.response);
            response = JSON.parse(this.response);
            $("#cog-"+id).hide();
            if (response['status']=="succ") {
                $("#pub-"+id).hide();
                if (response['recommended']==id) {
                    $("#recd-"+id).show();
                } else {
                    $("#rec-"+id).show();
                }
                // $("#rec-name").text(response['name']);
                // $("#rec-mc").text(response['mc']);
                $("#latest-name").text(response['latestname']);
                $("#latest-mc").text(response['latestmc']);
            } else {
                console.log(response);
            }
        }
    };
    request.open("GET", `./functions/set-public-build.php?buildid=${id}&modpackid=${getQueryVariable('id')}&ispublic=1`);
    request.send();

}
function remove(id) {
    $("#cog-"+id).show();
    var request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            response = JSON.parse(this.response);
            $("#cog-"+id).hide();
            $("#b-"+id).hide();
            if ($("#b-"+id).attr("rec")=="true") {
                $("#rec-v-li").hide();
                $("#rec-mc-li").hide();
            }
            console.log(response);
            if (response['exists']==true) {
                $("#latest-v-li").show();
                $("#latest-mc-li").show();
                $("#latest-name").text(response['name']);
                $("#latest-mc").text(response['mc']);
                if (response['name']==null) {
                    $("#latest-v-li").hide();
                    $("#latest-mc-li").hide();
                }
            } else {
                $("#latest-v-li").hide();
                $("#latest-mc-li").hide();
            }

            for (let b of bd) {
                if (b['id']==id) {
                    let name=b['name'];
                    bd.splice(bd.indexOf(b),1);
                    sbna.splice(sbna.indexOf(name),1);
                }
            }

        }
    }
    request.open("GET", `./functions/delete-build.php?buildid=${id}&modpackid=${getQueryVariable('id')}`);
    request.send();
}
function set_recommended(id) {
    $("#cog-"+id).show();
    var request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            console.log(this.response)
            response = JSON.parse(this.response);

            $("[id^='recd-']").each(function() {
                let otherid = $(this).attr("id").substring(5);
                if (otherid==id) { 
                    return;
                } else {
                    // hide all recd
                    $(this).hide();
                    // show all rec
                    $("#rec-"+otherid).show();
                }
            });
            $("#recd-"+id).show(); // recommended
            $("#rec-"+id).hide(); // reccommend
            $("#rec-v-li").show();
            $("#rec-mc-li").show();
            $("#cog-"+id).hide();
            // $("#rec-"+id).attr('disabled', true);
            var bid = $("#rec-disabled").attr('bid');
            // $("#rec-disabled").attr('disabled', false);
            // $("#rec-disabled").attr('id', 'rec-'+bid);
            // $("#rec-"+id).attr('id', 'rec-disabled');
            $("#rec-name").text(response['name']);
            $("#rec-mc").text(response['mc']);
            $("#table-builds tr").attr('rec','false');
            $("#b-"+id).attr('rec','true');
        }
    };
    request.open("GET", `./functions/set-recommended.php?buildid=${id}&modpackid=${getQueryVariable('id')}`);
    request.send();
}

async function copylatest() {
    if ($('#mplist').prop('selectedIndex', 1)) {
        if (await fillBuildlist()) {
            $('#buildlist').prop('selectedIndex', 0);
        }
    }
}
$('#copybuild').on('submit', async function(event){
    // return new Promise((resolve,reject) => {
        event.preventDefault();
        var formData = new FormData(this);

        var request = new XMLHttpRequest();
        request.onreadystatechange = function() {
            if (request.readyState === 4) {
                if (request.status === 200) {
                    console.log(request.responseText)
                    let json = JSON.parse(request.responseText)

                    if (json['status']==='succ') {
                        // window.location.reload()
                        let details = json['details'] // id,name,modpack,minecraft,java,mods
                        let id = details['id']
                        let name = details['name']
                        let modpack=details['modpack']
                        let minecraft=details['minecraft']
                        let java=details['java']
                        let mods=details['mods'].split(',')
                        let modcount=mods.length
                        let table = $('#table-builds')
                        let row = `<tr rec="false" id="b-${id}">
                                <td scope="row" data-value="${name}">${name}</td>
                                <td data-value="${minecraft}">${minecraft}</td>
                                <td class="d-none d-md-table-cell" data-value="${java}">${java}</td>
                                <td data-value="${modcount}">${modcount}</td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group" aria-label="Actions">
                                        <button onclick="edit(${id})" class="btn btn-primary">Edit</button>
                                        <button onclick="remove_box(${id},'${name}')" data-toggle="modal" data-target="#removeModal" class="btn btn-danger">Remove</button> 
                                            <button bid="${id}" id="pub-${id}" class="btn btn-success" onclick="set_public(${id})" style="display:block">Publish</button>
                                        <button bid="${id}" id="rec-${id}" class="btn btn-success" onclick="set_recommended(${id})" style="display:none">Recommend</button>
                                        <button bid="${id}" id="recd-${id}" class="btn btn-success" style="display:none" disabled>Recommended</button>
                                    </div>
                                </td>
                                <td>
                                    <em id="cog-<?php echo $user['id'] ?>" class="fas fa-cog fa-lg fa-spin" style="margin-top: 0.5rem" hidden></em>
                                </td>
                            </tr>`
                        table.prepend(row)
                    } else {            
                        console.log(json)
                                
                        let modal = $('#responseModal')
                        let label = $('#responseModalLabel')
                        let message = $('#responseModalMessage')

                        label.text(json['status'])
                        message.text(json['message'])
                        modal.modal('show')
                        // reject(new Error(json['message']))
                    }
                } else {
                    console.log('non-200 response')
                    // reject(new Error("non-200 response"))
                }
            }
        }
        request.onerror = function() {
            console.log('request error')
            // reject(new Error("Request error"))
        }
        request.open("POST", `./functions/copy-build.php`);
        request.send(formData);
    // })
    
})
$('#copylatestbutton').on('click', async function(){
    await copylatest()
})

function saveAllowedClients() {
    let checkedItems = [];
    $('.modpackClientId:checked').each(function(){
        checkedItems.push($(this).val());
    });
    console.log(checkedItems);

    let formData = new FormData();
    formData.set('modpack_id', $("#modpack_id").val());
    formData.set('client_ids', checkedItems.join(','));
    let request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        if (request.readyState == 4 && request.status == 200) {
            console.log(request.responseText);
            let json = JSON.parse(request.responseText);
            if (json['status']==='succ') {
                $('#update-modpack-clients-submit').attr('disabled', true);
            }
        }
    }
    request.onerror = function() {
        console.log('could not set modpack clients');
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

$('#modpack-details :input').on('change input', function () {
    $('#modpack-details-save').removeAttr('disabled');
});

$('#modpack-details').on('submit', function(e) {
    e.preventDefault();
    let formData = $(this).serialize();
    let request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        if (request.readyState == 4 && request.status == 200) {
            console.log(request.responseText);
            let json = JSON.parse(request.responseText);
            if (json['status']==='succ') {
                saveAllowedClients();
                $('#modpack-details-save').attr('disabled', true);
            }
        }
    }
    request.onerror = function() {
        console.log('could not set modpack clients');
    }
    request.open('POST', 'functions/edit-modpack.php');
    request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    request.send(formData);

    $(this).serializeArray().forEach(function(field) {
        console.log(field.name + ': ' + field.value);
    });
})
