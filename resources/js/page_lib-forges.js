function remove_box(id,name) {
    $("#mod-name-title").text(name);
    $("#mod-name").text(name);
    $("#remove-button").attr("onclick","remove("+id+")");
}
function remove(id) {
    var request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        $("#mod-row-"+id).remove();
    }
    request.open("GET", "./functions/delete-modv.php?id="+id);
    request.send();
}

var nof = 0;
var fiq = 0;
function chf(link,name,id,mc) {
    var chf = new XMLHttpRequest();
    chf.open('GET', "./functions/chf.php?link="+link);
    chf.onreadystatechange = function() {
        if (chf.readyState == 4) {
            if (chf.status == 200) {
                fiq++;
                if (chf.response == "OK") {
                    $("#fetched-mods").show();
                    $("#forge-table").append('<tr id="forge-'+id+'"><td scope="row">'+mc+'</td><td>'+name+'</td><td><a href="'+link+'">'+link+'</a></td><td><button id="button-add-'+id+'" onclick="add(\''+name+'\',\''+link+'\',\''+mc+'\',\''+id+'\')" class="btn btn-primary btn-sm">Add to Database</button></td><td><em id="cog-'+id+'" style="display:none" class="fas fa-spin fa-cog fa-2x"></em><em id="check-'+id+'" style="display:none" class="text-success fas fa-check fa-2x"></em><em id="times-'+id+'" style="display:none" class="text-danger fas fa-times fa-2x"></em></td></tr>');
                    if (fiq==nof) {
                        $("#fetch-forge").hide();
                        $("#save").show();
                    }
                }
            }
        }
    }
    chf.send();
}
// Fabric Download
let download = () => {
    $("#sub-button").attr("disabled","true")
    $("#sub-button")[0].innerHTML = "<i class='fas fa-cog fa-spin'></i>"
    let packager = new XMLHttpRequest();
    packager.open('GET', './functions/package-fabric.php?version='+encodeURIComponent($("#ver").children("option:selected").val())+"&loader="+encodeURIComponent($("#lod").children("option:selected").val()))
    packager.onreadystatechange = () => {
        if (packager.readyState === 4) {
            if (packager.status === 200) {
                retur = JSON.parse(packager.response)
                if (retur["status"] === "succ") {
                    $("#sub-button")[0].classList.remove("btn-primary")
                    $("#sub-button")[0].classList.add("btn-success")
                    $("#sub-button")[0].innerHTML = "<i class='fas fa-check'></i> Please reload the page."
                }
            }
        }
    }
    packager.send()
}
// Fetch Fabric Versions
let fetchfabric = () => {
    $("#fetch-fabric").attr("disabled",true)
    $("#fetch-forge").attr("disabled",true)
    $("#fetch-fabric").html("Fetching...<i class='fas fa-cog fa-spin fa-sm'></i>")
    let versions = new XMLHttpRequest()
    let loaders = new XMLHttpRequest()
    versions.open('GET','https://meta.fabricmc.net/v2/versions/game')
    loaders.open('GET','https://meta.fabricmc.net/v2/versions/loader')
    versions.onreadystatechange = () => {
        if (versions.readyState === 4) {
            if (versions.status === 200) {
                response = JSON.parse(versions.response)
                for (key in response) {
                    if (response[key]["stable"]) {
                        ver = document.createElement("option")
                        ver.text = response[key]["version"]
                        ver.value = response[key]["version"]
                        $("#ver")[0].add(ver)
                    }
                }
            }
        }
    }
    loaders.onreadystatechange = () => {
        if (loaders.readyState === 4) {
            if (loaders.status === 200) {
                response = JSON.parse(loaders.response)
                for (key in response) {
                    if (response[key]["stable"]) {
                        ver = document.createElement("option")
                        ver.text = response[key]["version"]
                        ver.value = response[key]["version"]
                        $("#lod")[0].add(ver)
                    }
                }
                $("#fetch-fabric").html("Fetch Fabric Versions")
                $("#fabrics")[0].style.display = "flex";
            }
        }
    }
    loaders.send()
    versions.send()
}
// Fetch versions
let fetch = ()=> {
    $("#fetch-forge").attr("disabled", true);
    $("#fetch-fabric").attr("disabled", true);
    $("#fetch-forge").html("Fetching...<i class='fas fa-cog fa-spin fa-sm'></i>");
    var request = new XMLHttpRequest();
    request.open('GET', './functions/forge-links.php');
    request.onreadystatechange = function() {
        if (request.readyState == 4) {
            if (request.status == 200) {
                response = JSON.parse(this.response);
                for (var key in response) {
                    nof++;
                    chf(response[key]["link"],response[key]["name"],response[key]["id"],response[key]["mc"]);
                }
            }
        }
    }
    request.send();
}
function add(v,link,mcv,id) {
    $("#button-add-"+id).attr("disabled",true);
    $("#cog-"+id).show();
    var request = new XMLHttpRequest();
    request.open('GET', './functions/add-forge.php?version='+v+'&link='+link+'&mcversion='+mcv);
    request.onreadystatechange = function() {
        if (request.readyState == 4) {
            if (request.status == 200) {
                response = JSON.parse(this.response);
                $("#cog-"+id).hide();
                if (response['status']=="succ") {
                    $("#check-"+id).show();
                } else {
                    $("#times-"+id).show();
                    $("#info").text(response['message']);
                }

            }
        }
    }
    request.send();
}
$(document).ready(function(){
    $("#nav-mods").trigger('click');
});