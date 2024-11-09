function remove_box(id,name) {
    $("#mod-name-title").text(name);
    $("#mod-name").text(name);
    $("#remove-button").attr("onclick","remove("+id+",false)");
    $("#remove-button-force").attr("onclick","remove("+id+",true)");
}
function remove(id,force) {
    var request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            response = JSON.parse(this.response);
            if (response['status']=='succ') {
                console.log('success!');
                $("#mod-row-"+id).remove();
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
    request.open("GET", "./functions/delete-mod.php?id="+id+"&force="+force);
    request.send();
}

// Fabric Download
let download = () => {
    let minecraft = $("#ver").children("option:selected").val();
    let version = $("#lod").children("option:selected").val();
    if ($("#ver").children("option:selected").attr("disabled")!==undefined && $("#ver").children("option:selected").attr("disabled")!==false) {
        return;
    } else {
        $("#sub-button").attr("disabled","true")
        $("#sub-button")[0].innerHTML = "<i class='fas fa-cog fa-spin'></i>"
        let packager = new XMLHttpRequest();
        packager.open('GET', './functions/package-fabric.php?version='+encodeURIComponent(minecraft)+"&loader="+encodeURIComponent(version))
        packager.onreadystatechange = () => {
            if (packager.readyState === 4) {
                if (packager.status === 200) {
                    parsed = JSON.parse(packager.response)
                    if (parsed["status"] === "succ") {
                        // $("#sub-button")[0].classList.remove("btn-primary")
                        // $("#sub-button")[0].classList.add("btn-success")
                        // $("#sub-button")[0].innerHTML = "<i class='fas fa-check'></i> Please reload the page."
                        // window.location.reload();

                        $("#ver").children("option:selected").attr("disabled",true);
                        // $("#ver").children("option:selected").innerHTML=$("#ver").children("option:selected").innerHTML+" (installed)";
                        $("#sub-button")[0].innerHTML = "Install";

                        add_item(minecraft, version, 'fabric', parsed['id']);

                        // $("#lod").children("option:selected").attr("disabled",true);
                        // $("#ver").next().attr("selected","selected");
                    }
                    // $("#fetch-forge").removeAttr("disabled");
                    // $("#fetch-neoforge").removeAttr("disabled");
                    $("#sub-button").removeAttr("disabled");
                }
            }
        }
        packager.send()
    }
}
// Fetch Fabric Versions
let fetchfabric = () => {
    $("#fetch-fabric").attr("disabled",true)
    // $("#fetch-forge").attr("disabled",true)
    // $("#fetch-neoforge").attr("disabled",true)
    $("#fetch-fabric").html("Loading...<i class='fas fa-cog fa-spin fa-sm'></i>")
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
                        if (!installed_mc_loaders.includes('fabric-'+response[key]["version"])) {
                            ver = document.createElement("option")
                            ver.text = response[key]["version"]
                            ver.value = response[key]["version"]
                            $("#ver")[0].add(ver)
                            installed_mc_loaders.push('fabric-'+response[key]["version"]);
                        }
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
                $("#fetch-fabric").html("Show Fabric Installer")
                $("#fabrics")[0].style.display = "flex";
            }
        }
    }
    loaders.send()
    versions.send()
}
let download_neoforge = () => {
    let VERSIONS_ENDPOINT = 'https://maven.neoforged.net/api/maven/versions/releases/'
    let FORGE_GAV = 'net/neoforged/neoforge'
    let DOWNLOAD_URL = 'https://maven.neoforged.net/releases'

    let version = $("#lod-neoforge").children("option:selected").val();
    let minecraft = "1."+version.slice(0,version.lastIndexOf('.'));
    let download_link = `${DOWNLOAD_URL}/${FORGE_GAV}/${encodeURIComponent(version)}/neoforge-${encodeURIComponent(version)}-installer.jar`;
    
    let function_url = "./functions/add-modloader.php?type=neoforge&version="+encodeURIComponent(version)+"&dl="+download_link+"&mcversion="+encodeURIComponent(minecraft);

    if ($("#lod-neoforge").children("option:selected").attr("disabled")!==undefined && $("#lod-neoforge").children("option:selected").attr("disabled")!==false) {
        return;
    } else {
        $("#sub-button-neoforge").attr("disabled","true")
        $("#sub-button-neoforge-message").hide();
        $("#sub-button-neoforge")[0].innerHTML = "<i class='fas fa-cog fa-spin'></i>"

        let packager = new XMLHttpRequest();
        packager.open('GET', function_url);
        packager.onreadystatechange = () => {
            if (packager.readyState === 4) {
                if (packager.status === 200) {
                    console.log(packager.response);
                    parsed = JSON.parse(packager.response)
                    if (parsed["status"] === "succ") {
                        // $("#sub-button-neoforge")[0].classList.remove("btn-primary")
                        // $("#sub-button-neoforge")[0].classList.add("btn-success")
                        // $("#sub-button-neoforge")[0].innerHTML = "<i class='fas fa-check'></i> Please reload the page."
                        // window.location.reload();
                        $("#lod-neoforge").children("option:selected").attr("disabled",true);
                        $("#lod-neoforge").children("option:selected").innerHTML=$("#lod-neoforge").children("option:selected").innerHTML+" (installed)";
                        $("#sub-button-neoforge")[0].innerHTML = "Install";

                        add_item(minecraft, version, 'neoforge', parsed['id']);
                        $("#lod-neoforge").next().attr("selected","selected");
                    } else {
                        $("#sub-button-neoforge").removeAttr("disabled")
                        $("#sub-button-neoforge-message").show();
                        $("#sub-button-neoforge-message")[0].innerHTML = parsed["message"];
                        $("#lod-neoforge").children("option:selected").attr("disabled",true);
                        $("#sub-button-neoforge")[0].innerHTML = "Install"
                    }
                    // $("#fetch-forge").removeAttr("disabled");
                    // $("#fetch-fabric").removeAttr("disabled");
                    $("#sub-button-neoforge").removeAttr("disabled")
                }
            }
        }
        packager.send()
    }
}
// Fetch Neoforge Versions
let fetch_neoforge = () => {
    // $("#fetch-fabric").attr("disabled",true)
    // $("#fetch-forge").attr("disabled",true)
    $("#fetch-neoforge").attr("disabled",true)
    $("#fetch-neoforge").html("Loading...<i class='fas fa-cog fa-spin fa-sm'></i>")
    let loaders = new XMLHttpRequest()
    loaders.open('GET','https://maven.neoforged.net/api/maven/versions/releases/net/neoforged/neoforge')
    loaders.onreadystatechange = () => {
        if (loaders.readyState === 4) {
            if (loaders.status === 200) {
                response = JSON.parse(loaders.response)
                if (response["versions"]) {
                    let versions = response["versions"].reverse();
                    for (key in versions) {
                        let value = versions[key]
                        if (!value.endsWith("-beta")) {
                            if (!installed_mc_loaders.includes('neoforge-'+value)) {
                                ver = document.createElement("option")
                                ver.text = '1.'+value.substring(0, value.lastIndexOf('.'))+' - '+value;
                                ver.value = value
                                $("#lod-neoforge")[0].add(ver)
                                installed_mc_loaders.push('neoforge-'+value);
                            }
                        }
                    }
                }
                $("#fetch-neoforge").html("Show Neoforge Installer");
                $("#neoforges")[0].style.display = "flex";
            }
        }
    }
    loaders.send()
}

// add forge version
function add(v,link,mcv,id) {
    $("#button-add-"+id).attr("disabled",true);
    $("#cog-"+id).show();
    var request = new XMLHttpRequest();
    request.open('GET', './functions/add-modloader.php?type=forge&version='+v+'&dl='+link+'&mcversion='+mcv);
    request.onreadystatechange = function() {
        if (request.readyState == 4) {
            if (request.status == 200) {
                response = JSON.parse(this.response);
                $("#cog-"+id).hide();
                $("#fetch-fabric").removeAttr("disabled");
                $("#fetch-neoforge").removeAttr("disabled");
                if (response['status']=="succ") {
                    $("#check-"+id).show();
                    add_item(mcv,v,'forge',response['id']);
                } else {
                    $("#times-"+id).show();
                    $("#info").text(response['message']);
                }

            }
        }
    }
    request.send();
}

// Fetch forge versions
var nof = 0;
var fiq = 0;
let fetch = ()=> {
    $("#fetch-forge").attr("disabled", true);
    // $("#fetch-fabric").attr("disabled", true);
    // $("#fetch-neoforge").attr("disabled",true);
    $("#fetch-forge").html("Loading...<i class='fas fa-cog fa-spin fa-sm'></i>");
    var request = new XMLHttpRequest();
    request.open('GET', './functions/forge-links.php');
    request.onreadystatechange = function() {
        if (request.readyState == 4) {
            if (request.status == 200) {
                response = JSON.parse(this.response);
                nof=0;
                fiq=0;
                for (var key in response) {
                    nof++;
                    chf(response[key]["link"],response[key]["name"],response[key]["id"],response[key]["mc"]);
                }
            }
        }
    }
    request.send();
}

// add item to installer list
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
                        $("#fetch-forge").html("Show Forge Installer");
                        // $("#fetch-forge").removeAttr("disabled");
                        // $("#fetch-fabric").removeAttr("disabled");
                        // $("#fetch-neoforge").removeAttr("disabled");
                    }
                }
            }
        }
    }
    chf.send();
}

// add item to installed list
function add_item(minecraft,version,type,dbid) {
    let item = $('<tr>', { 
        id: 'mod-row-'+dbid, 
        html: `
        <td scope="row" data-value="${minecraft}">${minecraft}</td>
        <td data-value="${version}">${version}</td>
        <td data-value="${type}">${type}</td>
        <td data-value="Remove"><button onclick="remove_box(${dbid},'${type} ${version}')" data-toggle="modal" data-target="#removeMod" class="btn btn-danger btn-sm">Remove</button></td>
        <td data-value><svg style="display: none;" class="svg-inline--fa fa-cog fa-w-16 fa-spin fa-sm" aria-hidden="true" data-prefix="fas" data-icon="cog" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M444.788 291.1l42.616 24.599c4.867 2.809 7.126 8.618 5.459 13.985-11.07 35.642-29.97 67.842-54.689 94.586a12.016 12.016 0 0 1-14.832 2.254l-42.584-24.595a191.577 191.577 0 0 1-60.759 35.13v49.182a12.01 12.01 0 0 1-9.377 11.718c-34.956 7.85-72.499 8.256-109.219.007-5.49-1.233-9.403-6.096-9.403-11.723v-49.184a191.555 191.555 0 0 1-60.759-35.13l-42.584 24.595a12.016 12.016 0 0 1-14.832-2.254c-24.718-26.744-43.619-58.944-54.689-94.586-1.667-5.366.592-11.175 5.459-13.985L67.212 291.1a193.48 193.48 0 0 1 0-70.199l-42.616-24.599c-4.867-2.809-7.126-8.618-5.459-13.985 11.07-35.642 29.97-67.842 54.689-94.586a12.016 12.016 0 0 1 14.832-2.254l42.584 24.595a191.577 191.577 0 0 1 60.759-35.13V25.759a12.01 12.01 0 0 1 9.377-11.718c34.956-7.85 72.499-8.256 109.219-.007 5.49 1.233 9.403 6.096 9.403 11.723v49.184a191.555 191.555 0 0 1 60.759 35.13l42.584-24.595a12.016 12.016 0 0 1 14.832 2.254c24.718 26.744 43.619 58.944 54.689 94.586 1.667 5.366-.592 11.175-5.459 13.985L444.788 220.9a193.485 193.485 0 0 1 0 70.2zM336 256c0-44.112-35.888-80-80-80s-80 35.888-80 80 35.888 80 80 80 80-35.888 80-80z"></path></svg></td>` 
    });
    $("#forge-available").append(item);
}

$(document).ready(function(){
    $("#nav-mods").trigger('click');
});