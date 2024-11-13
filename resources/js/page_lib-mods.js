function remove_box(name) {
    $("#mod-name-title").text(name);
    $("#mod-name").text(name);
    $("#remove-button").attr("onclick","remove('"+name+"',false)");
    $("#remove-button-force").attr("onclick","remove('"+name+"',true)");
}

function remove(name,force) {
    var request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            response = JSON.parse(this.response);
            console.log(response);
            if (response['status']=='succ') {
                console.log('success!');
                $("#mod-row-"+name).remove();
            } else {
                // todo: use styled alert instead of this
                console.log('error!?');
                if (confirm(response['message']+" Press OK to go to '"+response['bname']+"'")) {
                    window.location.href="/build?id="+response['bid'];
                }
            }
        }
        
    }
    request.open("GET", "./functions/delete-mod.php?name="+name+"&force="+force);
    request.send();
}

mn = 1;
function sendFile(file, i) {
    var formData = new FormData();
    var request = new XMLHttpRequest();
    formData.set('fiels', file);
    request.open('POST', './functions/send_mods.php');
    request.upload.addEventListener("progress", function(evt) {
        if (evt.lengthComputable) {
            var percentage = evt.loaded / evt.total * 100;
            $("#" + i).attr('aria-valuenow', percentage + '%');
            $("#" + i).css('width', percentage + '%');
            request.onreadystatechange = function() {
                if (request.readyState == 4) {
                    if (request.status == 200) {
                        if ( mn == modcount ) {
                            $("#btn-done").attr("disabled",false);
                        } else {
                            mn = mn + 1;
                        }
                        console.log(request.response);
                        response = JSON.parse(request.response);
                        switch(response.status) {
                            case "succ":
                            {
                                $("#cog-" + i).hide();
                                $("#check-" + i).show();
                                $("#" + i).removeClass("progress-bar-striped progress-bar-animated");
                                $("#" + i).addClass("bg-success");
                                $("#info-" + i).text(response.message);
                                $("#" + i).attr("id", i + "-done");
                                break;
                            }
                            case "info":
                            {
                                $("#cog-" + i).hide();
                                $("#inf-" + i).show();
                                $("#" + i).removeClass("progress-bar-striped progress-bar-animated");
                                $("#" + i).addClass("bg-success");
                                $("#info-" + i).text(response.message);
                                $("#" + i).attr("id", i + "-done");
                                break;
                            }
                            case "warn":
                            {
                                $("#cog-" + i).hide();
                                $("#exc-" + i).show();
                                $("#" + i).removeClass("progress-bar-striped progress-bar-animated");
                                $("#" + i).addClass("bg-warning");
                                $("#info-" + i).text(response.message);
                                $("#" + i).attr("id", i + "-done");
                                break;
                            }
                            case "error":
                            {
                                $("#cog-" + i).hide();
                                $("#times-" + i).show();
                                $("#" + i).removeClass("progress-bar-striped progress-bar-animated");
                                $("#" + i).addClass("bg-danger");
                                $("#info-" + i).text(response.message);
                                $("#" + i).attr("id", i + "-done");
                                break;
                            }
                        }
                        let num_versions = response['modid'].length;
                        let author = response['author'];
                        let name = response['name'];
                        let pretty_name = response['pretty_name'];
                        let mcversion = response['mcversion'];
                        let version = response['version'];
                        // console.log(response['status']);
                        if (response['status']!="error") {
                            // console.log('add new row');
                            let i=0;
                            // for (let id of response['modid']) {
                                $('#table-available-mods').append(`
                                    <tr id="mod-row-${name[i]}">
                                        <td scope="row" data-value="${pretty_name[i]}">${pretty_name[i]}</td>
                                        <td data-value="${author[i]}">${author[i]}</td>
                                        <td data-value="${num_versions}">${num_versions}</td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group" aria-label="Actions">
                                                <button onclick="window.location='./mod?id=${name[i]}'" class="btn btn-primary">Edit</button>
                                                <button onclick="remove_box('${name[i]}')" data-toggle="modal" data-target="#removeMod" class="btn btn-danger">Remove</button>
                                            </div>
                                        </td>
                                    </tr>
                                `);
                                // i==1;
                            // }
                        }
                    } else {
                        $("#cog-" + i).hide();
                        $("#times-" + i).show();
                        $("#" + i).removeClass("progress-bar-striped progress-bar-animated");
                        $("#" + i).addClass("bg-danger");
                        $("#info-" + i).text("An error occured: " + request.status);
                        $("#" + i).attr("id", i + "-done");
                    }
                }
            }
        }
    }, false);
    request.send(formData);
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

function showFile(file, i) {
    $("#table-mods").append('<tr><td scope="row">' + file.name + '</td> <td><em id="cog-' + i + '" class="fas fa-cog fa-spin"></em><em id="check-' + i + '" style="display:none" class="text-success fas fa-check"></em><em id="times-' + i + '" style="display:none" class="text-danger fas fa-times"></em><em id="exc-' + i + '" style="display:none" class="text-warning fas fa-exclamation"></em><em id="inf-' + i + '" style="display:none" class="text-info fas fa-info"></em> <small class="text-muted" id="info-' + i + '"></small></h4><div class="progress"><div id="' + i + '" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%"></div></div></td></tr>');
}
$(document).ready(function() {
    $(':file').change(function() {
        $("#upload-card").hide();
        $("#u-mods").show();
        modcount = this.files.length;
        for (var i = 0; i < this.files.length; i++) {
            var file = this.files[i];
            showFile(file, i);
        }
        for (var i = 0; i < this.files.length; i++) {
            var file = this.files[i];
            sendFile(file, i);
        }
    });
});

$(document).ready(function(){
    $("#nav-mods").trigger('click');
});