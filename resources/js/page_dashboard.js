var formdisabled = true;
$('#modsform').submit(function() {
    if (formDisabled) {
        return false;
    } else {
        return true;
    }
});
var addedmodslist = [];
var addedmodsliststr = [];
mn = 1;
function againMods() {
    $("#btn-done").attr("disabled",true);
    $("#table-mods").html("");
    $("#upload-card").show();
    $("#u-mods").hide();
    addedmodslist = [];
    addedmodsliststr= [];
    mn = 1;
}
function sendFile(file, i) {
    formdisabled = true;
    $("#submit").attr("disabled",true);
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
                        console.log(request.response);
                        response = JSON.parse(request.response);
                        if (response.modid) {
                            if (!addedmodslist.includes(response.modid)) {
                                addedmodslist.push(response.modid);
                                addedmodsliststr.push(response.name);
                            }
                        }
                        if ( mn == modcount ) {
                            if (addedmodslist.length > 0) {
                                if ($('#modliststr').val().length > 0) {
                                    $('#modliststr').val($('#modliststr').val() + "," + addedmodsliststr);
                                    $('#modlist').val($('#modlist').val() + "," + addedmodslist);
                                } else {
                                    $('#modliststr').val($('#modliststr').val() + addedmodsliststr);
                                    $('#modlist').val($('#modlist').val() + addedmodslist);
                                }
                            }
                            if ($('#modlist').val().length > 0) {
                                console.log($('#modlist').val().length);
                                $("#submit").attr("disabled",false);
                                formdisabled = false;
                            }
                            $("#btn-done").attr("disabled",false);
                        } else {
                            mn = mn + 1;
                        }

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
                                $("#" + i).addClass("bg-info");
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

$("#submitmigration").click(function(){
    $("#submitmigration").attr('disabled',true);
    $("#submitmigration").text('Migrating...');
    var http = new XMLHttpRequest();
    var params = 'db-pass='+ $("#origpass").val() +'&db-name='+ $("#origdatabase").val() +'&db-user='+ $("#origname").val() +'&db-host='+ $("#orighost").val() +'&solder-orig='+$("#origdir").val() ;
    http.open('POST', './functions/migrate.php');
    http.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    http.onreadystatechange = function() {
        if (http.readyState == 4 && http.status == 200) {
            if (http.responseText == "error") {
                $("#errtext").text("Migration failed!");
                $("#errtext").removeClass("text-muted text-success");
                $("#errtext").addClass("text-danger");
                $("#submitmigration").attr('disabled',false);
                $("#submitmigration").text('Start Migration');
            } else {
                $("#errtext").text("Migration was successful!");
                $("#errtext").removeClass("text-muted text-danger");
                $("#errtext").addClass("text-success");
                $("#submitmigration").text("Done");
            }
        }
    }
    http.send(params);
});
$("#submitdbform").click(function() {
    $("#submitdbform").attr("disabled", true);
    $("#submitdbform").text("Connecting...");
    var http = new XMLHttpRequest();
    var params = 'db-pass='+ $("#origpass").val() +'&db-name='+ $("#origdatabase").val() +'&db-user='+ $("#origname").val() +'&db-host='+ $("#orighost").val() ;
    http.open('POST', './functions/conntest.php');
    http.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    http.onreadystatechange = function() {
        if (http.readyState == 4 && http.status == 200) {
            if (http.responseText == "error") {
                $("#errtext").text("Cannot connect to database");
                $("#errtext").removeClass("text-muted text-success");
                $("#errtext").addClass("text-danger");
                $("#submitdbform").attr("disabled", false);
                $("#submitdbform").text("Connect");
            } else {
                $("#errtext").text("Connected to database");
                $("#errtext").removeClass("text-muted text-danger");
                $("#errtext").addClass("text-success");
                $("#dbform").hide();
                $("#migrating").show();
            }
        }
    }
    http.send(params);
});

document.getElementById("link").addEventListener("keyup", function(event) {
    if (event.keyCode === 13) {
        document.getElementById("search").click();
        document.getElementById("responseRaw").innerHTML = "Loading...";

    }
});
function get(){
    console.log("working");
    var link = document.getElementById("link").value;
    if (link=="") {
        return;
    }
    var request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            response = request.responseText;
            console.log(response);
            var responseDIV = document.getElementById("responseR");
            var feedDIV = document.getElementById("feed");
            var solderInfoDIV = document.getElementById("solderInfo");
            var solderDIV = document.getElementById("solder");
            responseObj = JSON.parse(response);
            if (Object.keys(responseObj).length==0) {
                    console.log("Could not get data from Technic API");
                    document.getElementById("response-title").innerHTML ="Could not get data from Technic API";
            } else if (responseObj.error=="Modpack does not exist") {
                responseDIV.innerHTML = "<strong>This modpack does not exists</strong>";
            } else {
                if (responseObj.solder!==null) {
                    solderRequest = new XMLHttpRequest();
                    console.log("Getting info from solder");
                    solderRequest.onreadystatechange = function() {
                        if (this.readyState == 4 && this.status == 200) {
                            document.getElementById("response-title").innerHTML ="Response from Technic API:<br>";
                            solderRaw = solderRequest.responseText;
                            solder = JSON.parse(solderRaw);
                            var solderDIV = document.getElementById("solder");
                            solderDIV.innerHTML = "<strong class='text-success'>This modpack is using Solder API - "+solder.api+" "+solder.version+" "+solder.stream+"</strong>";
                            console.log(solderRaw);
                            console.log("done");
                        }
                    }
                    solderRequest.open("GET", "./functions/resolder.php?link="+responseObj.solder);
                    solderRequest.send();

                } else {
                    solderDIV.innerHTML = "<strong class='text-danger'>This modpack is not using Solder API</strong>";
                }
                responseDIV.innerHTML = "<br /><strong>Modpack Name: </strong>"+responseObj.displayName;
                responseDIV.innerHTML += "<br /><strong>Author: </strong>"+responseObj.user;
                responseDIV.innerHTML += "<br /><strong>Minecraft Version: </strong>"+responseObj.minecraft;
                responseDIV.innerHTML += "<br /><strong>Downloads: </strong>"+responseObj.downloads;
                responseDIV.innerHTML += "<br /><strong>Runs: </strong>"+responseObj.runs;
                responseDIV.innerHTML += "<br /><strong>Official Modpack: </strong>"+responseObj.isOfficial;
                responseDIV.innerHTML += "<br /><strong>Server Modpack: </strong>"+responseObj.isServer;
                responseDIV.innerHTML += "<br /><strong>Platform Site: </strong><a target='_blank' href='"+responseObj.platformUrl+"'>"+responseObj.platformUrl+"</a>";
                if (responseObj.url!==null) {
                    responseDIV.innerHTML += "<br /><strong>Download Link: </strong><a target='_blank' href='"+responseObj.url+"'>"+responseObj.url+"</a>";
                }
                if (responseObj.solder!==null) {
                    responseDIV.innerHTML += "<br /><strong>Solder API: </strong><a target='_blank' href='"+responseObj.solder+"'>"+responseObj.solder+"</a>";
                }
                responseDIV.innerHTML += "<br /><strong>Description: </strong>"+responseObj.description
                if (responseObj.discordServerId!=="") {
                    responseDIV.innerHTML += "<br /><br /><iframe src='https://discordapp.com/widget?id="+responseObj.discordServerId+"&theme=dark' width='350' height='500' allowtransparency='true' frameborder='0'></iframe>";
                }
                feedDIV.innerHTML = "<br /><h3>Updates: </h3><div class='card-columns' id='cards'></div>"
                i=0;
                responseObj.feed.forEach(element => {
                    i++
                    document.getElementById("cards").innerHTML += "<div style='padding:0px' class='card'><div class='card-header'><h5><img class='rounded-circle' src='"+element.avatar+"' height='32px' width='32px' /> "+element.user+"</h5></div><div class='card-body'><p>"+element.content+"</p></div></div>";
                });
                if (i==0) {
                    feedDIV.innerHTML = "";
                }
            }
        }
    };
    request.open("GET", "./functions/platform.php?slug="+link+"&build="+SOLDER_BUILD);
    request.send();
}

$("#dn").on("keyup", function(){
    var slug = slugify($(this).val());
    $("#slug").val(slug);
});