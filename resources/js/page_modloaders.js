const CACHE_INSTALLER_TTL=3600;

function remove_box(id,name,minecraft,version) {
    if (used_loaders.includes(name+'-'+minecraft+'-'+version)) {
        $("#mod-name-title").html(name+' <b>(in use)</b>');
        $("#mod-name").html(name+' <b>(in use)</b>');
        $("#remove-button").attr("onclick",`remove(${id},'${name}','${minecraft}','${version}',true)`);
        $("#remove-button").text('Force delete')
    } else {
        $("#mod-name-title").text(name);
        $("#mod-name").text(name);
        $("#remove-button").attr("onclick",`remove(${id},'${name}','${minecraft}','${version}',false)`);
        $("#remove-button").text('Delete')
    }
}
function remove(id,name,minecraft,version,force) {
    var request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            response = JSON.parse(this.response);
            if (response['status']=='succ') {
                console.log('success!');
                $("#mod-row-"+id).remove();
                if (used_loaders.includes(name+'-'+minecraft+'-'+version)) {
                    const index = used_loaders.indexOf(name+'-'+minecraft+'-'+version);
                    if (index > -1) {
                      used_loaders.splice(index, 1);
                    }
                }
            }
        }
        
    }
    request.open("GET", "./functions/delete-mod.php?id="+id+"&force="+force);
    request.send();
}

// Fabric Download
let download_fabric = () => {
    let minecraft = $("#ver").children("option:selected").val();
    let version = $("#lod").children("option:selected").val();
    if ($("#ver").children("option:selected").attr("disabled")!==undefined && $("#ver").children("option:selected").attr("disabled")!==false) {
        return;
    } else {
        $("#sub-button").attr("disabled","true")
        $("#sub-button")[0].innerHTML = "<i class='fas fa-cog fa-spin'></i>"
        let packager = new XMLHttpRequest();
        packager.open('GET', './functions/add-modloader-fabric.php?version='+encodeURIComponent(minecraft)+"&loader="+encodeURIComponent(version))
        packager.onreadystatechange = () => {
            if (packager.readyState === 4) {
                if (packager.status === 200) {
                    console.log(packager.response)
                    parsed = JSON.parse(packager.response)
                    if (parsed["status"] === "succ") {
                        $("#ver").children("option:selected").attr("disabled",true);
                        $("#sub-button")[0].innerHTML = "Install";
                        add_item(parsed['id'], 'fabric', minecraft, version);
                        $('#installfabricinfo').addClass('text-success')
                        $('#installfabricinfo').removeClass('text-danger')
                        installed_loaders.push('fabric-'+minecraft+'-'+parsed['version'])
                        $('#ver option:selected').attr('disabled',true);
                    } else {
                        $('#installfabricinfo').addClass('text-danger')
                        $('#installfabricinfo').removeClass('text-success')
                    }
                    $("#sub-button").removeAttr("disabled");
                    $('#installfabricinfo').html(parsed['message'])
                    $('#installfabricinfo').show()
                }
            }
        }
        packager.send()
    }
}

// Fetch Fabric Versions
async function fetch_fabric() {
    async function fetch_versions() {
        return new Promise((resolve, reject) => {
            let versions = new XMLHttpRequest()
            versions.open('GET','https://meta.fabricmc.net/v2/versions/game')
            versions.onreadystatechange = () => {
                if (versions.readyState === 4) {
                    if (versions.status === 200) {
                        set_cached('installer_fabric_versions', versions.response, CACHE_INSTALLER_TTL)
                        resolve(versions.response)
                        return versions.response
                    }
                }
            }
            versions.onerror = function() {
                resolve([]);
            };
            versions.send()
        });
    }
    async function fetch_loaders() {
        return new Promise((resolve, reject) => {
            let loaders = new XMLHttpRequest()
            loaders.open('GET','https://meta.fabricmc.net/v2/versions/loader')
            loaders.onreadystatechange = () => {
                if (loaders.readyState === 4) {
                    if (loaders.status === 200) {
                        set_cached('installer_fabric_loaders', loaders.response, CACHE_INSTALLER_TTL)
                        // response = JSON.parse(loaders.response)
                        resolve(loaders.response)
                        return loaders.response
                    }
                }
            }
            loaders.onerror = function() {
                resolve([])
            }
            loaders.send()
        });
    }

    if (get_cached('installer_fabric_versions')) {
        var versions=JSON.parse(get_cached('installer_fabric_versions'));
    } else {
        var versions=JSON.parse(await fetch_versions());
    }
    if (get_cached('installer_fabric_loaders')) {
        var loaders=JSON.parse(get_cached('installer_fabric_loaders'));
    } else {
        var loaders=JSON.parse(await fetch_loaders());
    }

    versions = versions.filter((v) => v['stable'] == true);
    loaders = loaders.filter((v) => v['stable'] == true);

    let merged = versions.map((v, i) => [v['version'], loaders[0]['version']]);

    for (v of merged) {
        let minecraft=v[0];
        let loader=v[1]
            // console.log($("#ver")[0])
        if (!installed_loaders.includes('fabric-'+minecraft+'-'+loader)) {
            if ($(`#lod option[value='${loader}']`).length == 0) {
                lod = document.createElement("option")
                lod.text = loader
                lod.value = loader
                $("#lod")[0].add(lod)
            }
            if ($(`#ver option[value='${minecraft}']`).length == 0) {
                mcv = document.createElement("option")
                mcv.text = minecraft
                mcv.value = minecraft
                $("#ver")[0].add(mcv)
            }
        }
        
    }

    $('#sub-button').attr('disabled',false);
    $('#sub-button').text('Install');
    $("#fetch-fabric").html("Show Fabric Installer")
    $("#fabrics")[0].style.display = "flex";
    

}
// neoforge download
let download_neoforge = () => {
    let VERSIONS_ENDPOINT = 'https://maven.neoforged.net/api/maven/versions/releases/'
    let FORGE_GAV = 'net/neoforged/neoforge'
    let DOWNLOAD_URL = 'https://maven.neoforged.net/releases'

    let version = $("#lod-neoforge").children("option:selected").val();
    // let minecraft = $("#ver-neoforge").children("option:selected").val();  
    let minecraft = "1."+version.slice(0,version.lastIndexOf('.'));
    let download_link = `${DOWNLOAD_URL}/${FORGE_GAV}/${encodeURIComponent(version)}/neoforge-${encodeURIComponent(version)}-installer.jar`;
    
    let function_url = "./functions/add-modloader.php?type=neoforge&version="+encodeURIComponent(version)+"&dl="+download_link+"&mcversion="+encodeURIComponent(minecraft);

    if ($("#lod-neoforge").children("option:selected").attr("disabled")!==undefined && $("#lod-neoforge").children("option:selected").attr("disabled")!==false) {
        return;
    } else {
        $("#sub-button-neoforge").attr("disabled","true")
        $("#installneoforgeinfo").hide();
        $("#sub-button-neoforge")[0].innerHTML = "<i class='fas fa-cog fa-spin'></i>"

        let packager = new XMLHttpRequest();
        packager.open('GET', function_url);
        packager.onreadystatechange = () => {
            if (packager.readyState === 4) {
                if (packager.status === 200) {
                    console.log(packager.response);
                    parsed = JSON.parse(packager.response)
                    if (parsed["status"] === "succ") {
                        $("#lod-neoforge").children("option:selected").attr("disabled",true);
                        $("#lod-neoforge").children("option:selected").innerHTML=$("#lod-neoforge").children("option:selected").innerHTML+" (installed)";
                        $("#sub-button-neoforge")[0].innerHTML = "Install";

                        add_item(parsed['id'], 'neoforge', minecraft, version);
                        $("#lod-neoforge").next().attr("selected","selected");
                        $('#installneoforgeinfo').addClass('text-success');
                        $('#installneoforgeinfo').removeClass('text-danger');
                        installed_loaders.push('neoforge-'+minecraft+'-'+parsed['version'])
                    } else {
                        $("#sub-button-neoforge").removeAttr("disabled")
                        $("#installneoforgeinfo").show();
                        $("#installneoforgeinfo")[0].innerHTML = parsed["message"];
                        $("#lod-neoforge").children("option:selected").attr("disabled",true);
                        $("#sub-button-neoforge")[0].innerHTML = "Install"
                        $('#installneoforgeinfo').addClass('text-danger');
                        $('#installneoforgeinfo').removeClass('text-success');
                    }
                    $("#sub-button-neoforge").removeAttr("disabled")
                    $('#installneoforgeinfo').html(parsed['message'])
                    $('#installneoforgeinfo').show();
                }
            }
        }
        packager.send()
    }
}
// Fetch Neoforge Versions
let fetch_neoforge = () => {
    function parse_versions(versions) {
        for (key in versions) {
            let loader = versions[key]
            let minecraft = '1.'+loader.substring(0, loader.lastIndexOf('.'));
            if (!installed_loaders.includes('neoforge-'+minecraft+'-'+loader)) {
                if ($(`#lod-neoforge option[value='${loader}']`).length == 0) {
                    lod = document.createElement("option")
                    lod.text = minecraft+' - '+loader;
                    lod.value = loader
                    $("#lod-neoforge")[0].add(lod)
                }

                // if ($(`#ver-neoforge option[value='${minecraft}']`).length == 0) {
                //     ver = document.createElement("option")
                //     ver.text = minecraft;
                //     ver.value = minecraft
                //     $("#ver-neoforge")[0].add(ver)
                // }
            }
        }
        $('#sub-button-neoforge').attr('disabled',false);
        $('#sub-button-neoforge').text('Install');
    }
    $("#fetch-neoforge").attr("disabled",true)
    $("#fetch-neoforge").html("Loading...<i class='fas fa-cog fa-spin fa-sm'></i>")
    if (get_cached('installer_neoforge_versions')) {
        let versions=JSON.parse(get_cached('installer_neoforge_versions'));
        parse_versions(versions);
    }
    let loaders = new XMLHttpRequest()
    loaders.open('GET','https://maven.neoforged.net/api/maven/versions/releases/net/neoforged/neoforge')
    loaders.onreadystatechange = () => {
        if (loaders.readyState === 4) {
            if (loaders.status === 200) {
                response = JSON.parse(loaders.response)
                if (response["versions"]) {
                    let versions = response["versions"].reverse();
                    versions = versions.filter((v) => !v.endsWith('beta'));
                    set_cached('installer_neoforge_versions', JSON.stringify(versions), CACHE_INSTALLER_TTL) // 1 hour
                    parse_versions(versions);
                }
                $("#fetch-neoforge").html("Show Neoforge Installer");
                $("#neoforges")[0].style.display = "flex";
            }
        }
    }
    loaders.send()
}

// download forge version
function download_forge(id,minecraft,version,link) {
    $("#button-add-"+id).attr("disabled",true);
    $("#cog-"+id).show();
    var request = new XMLHttpRequest();
    request.open('GET', './functions/add-modloader.php?type=forge&version='+version+'&dl='+link+'&mcversion='+minecraft);
    request.onreadystatechange = function() {
        if (request.readyState == 4) {
            if (request.status == 200) {
                response = JSON.parse(this.response);
                $("#cog-"+id).hide();
                $("#fetch-fabric").removeAttr("disabled");
                $("#fetch-neoforge").removeAttr("disabled");
                if (response['status']=="succ") {
                    $("#check-"+id).show();
                    add_item(response['id'], 'forge', minecraft, version);
                    $("#installforgeinfo").addClass('text-success');
                    $("#installforgeinfo").removeClass('text-danger');
                    installed_loaders.push('forge-'+minecraft+'-'+version)
                } else {
                    $("#times-"+id).show();
                    $("#installforgeinfo").addClass('text-danger');
                    $("#installforgeinfo").removeClass('text-success');
                }
                $("#installforgeinfo").text(response['message']);
                $("#installforgeinfo").show();
            }
        }
    }
    request.send();
}

const forge_link = "https://maven.minecraftforge.net/net/minecraftforge/forge";

// Fetch forge versions
async function fetch_forges() {
    async function verify_link_then_add(id,minecraft,version,link) {
        console.log('verifying '+link)
        // check each version and then add it to list
        return new Promise((resolve, reject) => {
            fetch(link, { method:'HEAD' })
            .then(response=> {
                if (response.status == 404) {
                    reject(id);
                } else if (response.status == 200) {
                    $("#forge-table").append(`
                    <tr id="forge-${id}">
                        <td scope="row" data-value="${minecraft}">${minecraft}</td>
                        <td data-value="${version}">${version}</td>
                        <td data-value="${link}" class="d-none d-md-table-cell" style="overflow-wrap: break-word;"><a href="${link}" style="word-break: break-all;">${link}</a></td>
                        <td><button id="button-add-${id}" onclick="download_forge('${minecraft}', ${id}, '${version}', '${link}')" class="btn btn-primary btn-sm">Add to Database</button></td>
                        <td><em id="cog-${id}" style="display:none" class="fas fa-spin fa-cog fa-2x"></em><em id="check-${id}" style="display:none" class="text-success fas fa-check fa-2x"></em><em id="times-${id}" style="display:none" class="text-danger fas fa-times fa-2x"></em></td>
                    </tr>`);
                    resolve(true);
                }
            })
            .catch(error=>{
                reject(false);
            });
        }).catch(error=>{
            reject(false);
        });
    }
    async function parse_versions(response){
        if (get_cached('installer_forge_versions')) {
            let response=JSON.parse(get_cached('installer_forge_versions'));
            for (var key in response) {
                console.log(response[key])
                await verify_link_then_add(response[key]["id"], response[key]["mc"], response[key]['name'], response[key]["link"]);
            }
        } else {
            let valid=[];
            console.log("ignore 404s, they're supposed to be suppressed/catched with onerror")
            let onesix_worked=false;
            for (var key in response) {
                if (!installed_loaders.includes('forge-'+response[key]['minecraft']+'-'+response[key]['name'])) {
                    if (onesix_worked || compareVersions(key,'1.6.0')>0) {
                        // if 1.6.0 worked, or we are above 1.6.0
                        try {
                            let isvalid = await verify_link_then_add(response[key]['id'], response[key]['name'], response[key]['mc'], response[key]['link']);
                            if (isvalid) {
                                valid.push(response[key]);
                            if (compareVersions(key,'1.6.0')<0) {
                                onesix_worked=false;
                            }
                            }
                        } catch (e) {
                            if (compareVersions(key,'1.6.0')<0) {
                                onesix_worked=false;
                            }
                        }
                    }
                }
            }
            set_cached('installer_forge_versions', JSON.stringify(valid), CACHE_INSTALLER_TTL);
        }
        $("#fetch-forge").hide();
    }

    $("#fetch-forge").attr("disabled", true);
    $("#fetch-forge").html("Loading...<i class='fas fa-cog fa-spin fa-sm'></i>");
    $("#table-fetched-mods").show();

    if (get_cached('installer_forge_versions')) {
        let response=JSON.parse(get_cached('installer_forge_versions'));
        parse_versions(response);
    } else {
        var request = new XMLHttpRequest();
        request.open('GET', './functions/forge-links.php');
        request.onreadystatechange = async function() {
            if (request.readyState == 4 && request.status == 200) {
                response = JSON.parse(this.response);
                response = Object.fromEntries(Object.entries(response).reverse());
                // cached in parse_versions
                parse_versions(response);
            }
        }
        
        request.send();
    }
}

// add item to installed list
function add_item(id,name,minecraft,version) {
    let item = $('<tr>', { 
        id: 'mod-row-'+id, 
        html: `
        <td scope="row" data-value="${minecraft}">${minecraft}</td>
        <td data-value="${version}">${version}</td>
        <td data-value="${name}">${name}</td>
        <td data-value="Remove"><button onclick="remove_box(${id},'${name}','${minecraft}','${version}')" data-toggle="modal" data-target="#removeMod" class="btn btn-danger btn-sm">Remove</button></td>
        <td data-value><em style="display: none" class="fas fa-cog fa-spin fa-sm"></em></td>` 
    });
    $("#forge-available").append(item);
}

$(document).ready(function(){
    $("#nav-mods").trigger('click');
    fetch_fabric()
    fetch_neoforge()
});

$('#forge').on('change', function () {
    const fileInput = $(this);
    const label = fileInput.next('.custom-file-label');
    const fileIcon = $('#file-icon');

    if (fileInput[0].files.length > 0) {
        // Add a check icon and update the file name
        fileIcon.attr('class', 'fas fa-check text-success');
        label.html(`<i id="file-icon" class="fas fa-check text-success"></i> ${fileInput[0].files[0].name}`);
    } else {
        // Reset icon and text when no file is selected
        fileIcon.attr('class', 'fas fa-upload');
        label.html('<i id="file-icon" class="fas fa-upload"></i> Choose modpack.jar file...');
    }
});