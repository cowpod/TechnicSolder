function remove_box(name) {
    $("#mod-name-title").text(name);
    $("#mod-name").text(name);
    $("#remove-button").attr("onclick","remove('"+name+"')");
}

function remove(id) {
    var request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        $("#mod-row-"+id).remove();
    }
    request.open("GET", "./functions/delete-mod.php?id="+id);
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