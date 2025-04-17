const SEARCH_LIMIT=20;
const SEARCH_CACHE_TTL=60*60*12; // 12 hr
const DESC_CACHE_TTL=60*60*12;
const VERSION_CACHE_TTL=60*30*6; // 6 hr

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
            console.log(this.response)
            response = JSON.parse(this.response);
            if (response['status']=='succ') {
                if ('remaining' in response && response['remaining']>0) {
                    $('#mod-row-'+name+'-num').text(response['remaining']);
                    console.log(response['remaining']);
                } else {
                    console.log('success!');
                    $("#mod-row-"+name).remove();
                }
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
    let mcv = $('#mcv option:selected').attr('mc');
    var formData = new FormData();
    var request = new XMLHttpRequest();
    formData.set('fiels', file);
    formData.set('fallback_mcversion', mcv)
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

                        // console.log(response['status']);
                        if (response['status']!="error") {
                            let num_versions = response['modid'].length;
                            let author = response['author'];
                            if (author instanceof Array && author.length>=1) {
                                author=author[0];
                            }
                            let name = response['name'];
                            if (name instanceof Array && name.length>=1) {
                                name=name[0];
                            }
                            let pretty_name = response['pretty_name'];
                            if (pretty_name instanceof Array && pretty_name.length>=1) {
                                pretty_name=pretty_name[0];
                            }
                            let mcversion = response['mcversion'];
                            let version = response['version'];
                            // console.log('add new row');

                                $('#table-available-mods').append(`
                                    <tr id="mod-row-${name[i]}">
                                        <td scope="row" data-value="${pretty_name}">${pretty_name}</td>
                                        <td data-value="${author}" class="d-none d-sm-table-cell">${author}</td>
                                        <td id="mod-row-${name[i]}-num" data-value="${num_versions}">${num_versions}</td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group" aria-label="Actions">
                                                <button onclick="window.location='./mod?id=${name}'" class="btn btn-primary">Edit</button>
                                                <button onclick="remove_box('${name}')" data-toggle="modal" data-target="#removeMod" class="btn btn-danger">Remove</button>
                                            </div>
                                        </td>
                                    </tr>
                                `);
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


var results={};
var details={};
var versions={};
var versions2={}
var installed=null;

async function addrow(hit) {
    let description = hit['description'];
    if (description.length>80) {
       description = description.substring(0,200)+"...";
    }
    let categories = hit['display_categories'].join(', ');
    if (categories.length>40) {
       categories = categories.substring(0,40)+"...";
    }

    let author = hit['author'];
    let instv = await is_installed(hit['slug'], hit['title'], hit['author']);
    if (instv) {
        // console.log(hit['slug'], 'is installed')
        if (instv['mcversion']==''||instv['version']=='') {
            var btn=`<a id="install-${hit['slug']}" class="btn btn-secondary btn-warning" href="mod?id=${instv['name']}" pid="${hit['slug']}" target="_blank">Issue(s)</a>`;
        } else {
            var btn = `<a id="install-${hit['slug']}" class="btn btn-secondary btn-success" href="javascript:void(0)" pid="${hit['slug']}" disabled>Installed</a>`;
        }
    } else {
        // console.log(hit['slug'], 'is not installed')
        var btn = `<a id="install-${hit['slug']}" class="btn btn-secondary" href="javascript:void(0)" pid="${hit['slug']}" onclick="getversions('${hit['slug']}').then(result => {showinstallation(result)});">Install</a>`;
    }
    var row = `
        <tr>
            <td data-value='${hit['title']}'>${hit['title']}</td>
            <td data-value='str${categories}...' class="d-none d-md-table-cell" style='overflow-wrap: break-word;' onclick="showcategories('${hit['slug']}')"><a href="javascript:void(0)"onclick="showcategories('${hit['slug']}')" style="word-wrap: break-all">${categories}</a></td>
            <td data-value='${description}' style='overflow-wrap: break-word;' onclick="getproject('${hit['slug']}',true)"><a href="javascript:void(0)" onclick="getproject('${hit['slug']}',true)" style="word-wrap: break-all">${description}</a></td>
            <td data-value='${author}' class="d-none d-md-table-cell">${author}</td>
            <td>${btn}</td>
        </tr>
    `;
    $('#searchresults').append(row);
}

async function getproject(id,show) {
    return new Promise((resolve, reject) => {
        if (id in details) {
            console.log('got cached details');
            if (show) showdetails(id);
            resolve(id)
        } else if (get_cached('details_'+id)) {
            console.log('got cached details from localstorage');
            details[id] = JSON.parse(get_cached('details_'+id));
            if (show) showdetails(id);
            resolve(id)
        } else {
            var request = new XMLHttpRequest();
            let mcv = JSON.stringify([$('#mcv option:selected').attr('mc')]);
            let loader = JSON.stringify([$('#mcv option:selected').attr('type')]);
            let url = `https://api.modrinth.com/v2/project/${id}`;
            request.open('GET', url, true);    
            request.setRequestHeader("X-Custom-User-Agent", "cowpod/TechnicSolder/2.0.0 self-hosted-please-contact-via-requesting-address");
            request.onreadystatechange = function() {
                if (request.readyState == 4 && request.status == 200) {
                    let obj = JSON.parse(request.responseText);
                    set_cached('details_'+id, request.responseText,DESC_CACHE_TTL);
                    details[id]=obj;
                    console.log('got new description');
                    if (show) showdetails(id);
                    resolve(id)
                }
            };
            request.onerror = function() {
                console.log('getproject() error while fetching from api')
                reject(id)
            }
            request.send();
        }
    })
}
function showdetails(id) {
    $('#description').modal('show');
    $('#description-title').text('Desciption: '+details[id]['title']);
    const md =marked.parse(details[id]['body']);
    const parsed_md = md.replace(/<a /g, '<a target="_blank" ')
        .replace(/<img /g,'<img style="max-width:766px" ')
        // .replace(/href\s?=\s?['"](https?:\/\/(?:w{3}\.)?modrinth\.com\/[\w\-\/]+)['"]/g, 'href="javascript:void(0)" onclick="alert(\'$1\')"');
    $('#description-body').html(parsed_md);
}
function showcategories(id) {
    $('#description').modal('show');
    // console.log(results)
    results[$('#searchquery').val()].forEach(function(hit) {
        // console.log(hit)
        if (hit['slug']==id) {
            $('#description-title').text('Categories: '+hit['title']);
            $('#description-body').html(hit['categories'].join('<br/>'));
            return;
        }
    })
}

async function getversions(id, versionId='', updateUi=true) {
    // get versions for an id

    // note that versions2 is preferred, and we will eventually
    // completely replace versions with versions2 (and drop the 2)
    return new Promise((resolve, reject) => {
        if (updateUi) {
            $('#installations-cancel').attr('disabled',true);
            $('#installations-button').attr('disabled',true);
            $('#installation-message').hide();
            $('#installation-versions').empty();
            $('#installation').modal('show');
            $('#installation-title').text('Install '+id);
            $('#installations-button').text('Loading...');
            $('#installations-cancel').text('Cancel');
            $('#installations-button').attr('onclick','installmod()');
        }

        if (id in versions) {
            console.log('got cached version');
 
            let project_id = versions[id][0]['project_id']

            if (versions2[project_id]===undefined) {
                versions2[project_id]={}
            }
            for (let version of versions[id]) {
                let versionId = version['id']
                versions2[project_id][versionId] = version
            }

            resolve(id) // resolve(project_id)
            return id
        } else if (id in versions2 && versionId!==undefined && versionId in versions2[id]) {
            console.log('got cached version2');
            resolve(id)
            return id
        } else if (versionId!=='' && get_cached('version_'+id+'_'+versionId)) {
            console.log('got cached versionId from localstorage');
            let obj = JSON.parse(get_cached('version_'+id+'_'+versionId));
            if (versions2[id]===undefined) {
                versions2[id]={}
            }
            versions2[id][versionId] = obj

            resolve(id)
            return id
        } else if (get_cached('versions_'+id)) {
            console.log('got cached version from localstorage');
            let obj = JSON.parse(get_cached('versions_'+id));
            versions[id] = obj
            let project_id = obj[0]['project_id']

            if (versions2[project_id]===undefined) {
                versions2[project_id]={}
            }
            for (let version of obj) {
                let versionId = version['id']
                versions2[project_id][versionId] = version
            }
            
            resolve(id) // resolve(project_id)
            return id
        } else {
            var request = new XMLHttpRequest();
            if (versionId!=='') {
                var url = `https://api.modrinth.com/v2/project/${id}/version/${versionId}`;
            } else {
                let mcv = JSON.stringify([$('#mcv option:selected').attr('mc')]);
                let loader = JSON.stringify([$('#mcv option:selected').attr('type')]);
                var url = `https://api.modrinth.com/v2/project/${id}/version?game_versions=${encodeURIComponent(mcv)}&loaders=${encodeURIComponent(loader)}`;
            }
            request.open('GET', url, true);
            request.onerror = function () {
              reject(new Error('Network error'));
            };
            request.setRequestHeader('User-Agent','TheGameSpider/TechnicSolder/1.4.0');
            request.onreadystatechange = function() {
                if (request.readyState == 4 && request.status == 200) {
                    let obj = JSON.parse(request.responseText);
                    if (versionId!=='') {
                        set_cached('version_'+id+'_'+versionId, request.responseText, VERSION_CACHE_TTL);
                        if (versions2[id]===undefined) {
                            versions2[id]={}
                        }
                        versions2[id][versionId] = obj;
                        console.log('got new versions2 for id='+id);
                    }else{
                        set_cached('versions_'+id, request.responseText, VERSION_CACHE_TTL);
                        if (obj.length==0) {
                            console.log('project version does not exist! is a dependency incorrectly labeled as required, or did the author not update the entry on modrinth!?')
                            reject(new Error('No result'))
                            return id
                        } else {
                            let project_id = obj[0]['project_id']

                            if (versions2[project_id]===undefined) {
                                versions2[project_id]={}
                            }
                            for (let version of obj) {
                                let versionId = version['id']
                                versions2[project_id][versionId] = version
                            }

                            versions[id]=obj
                            console.log('got new versions for id='+id);
                        }
                    }

                    resolve(id) // resolve(project_id)
                    return id
                }
            };
            request.send();
        }
    });
}

var proj_version_deps={}
async function process_deps(vs) {  
    let deps = vs['dependencies']
    let requiredByProject = vs['project_id']
    let requiredByProjectVersion = vs['id']
    let mcv = $('#mcv option:selected').attr('mc');
    let loader = $('#mcv option:selected').attr('type');

    if (deps!==undefined && deps.length>0) {

        console.log('processing dependencies for', requiredByProject)
        for (let dep of deps) {
            if (dep['dependency_type']==='required'){
                console.log('processing', dep)
                let dep_v = null;

                if (dep['version_id']==null || dep['version_id']==undefined || dep['version_id']=='') { 
                    console.log('no version id specified')
                    try {
                        await getversions(dep['project_id'],'',false)
                    } catch (error) {
                        continue;
                    }

                    console.log('got dep version1:', versions[dep['project_id']])

                    let dep_vs = versions[dep['project_id']] // list of vs

                    for (let v of dep_vs) {
                        if (v['game_versions'].includes(mcv) && v['loaders'].includes(loader)) {
                            dep_v = v
                            console.log('got v', dep_v)
                            break
                        }
                    }
                } else {
                    console.log('getting by project+version id')
                    try {
                        await getversions(dep['project_id'], dep['version_id'],false)
                    } catch(error) {
                        continue;
                    }
                    console.log('dep version2:', versions2[dep['project_id']][dep['version_id']])
                    dep_v = versions2[dep['project_id']][dep['version_id']]
                }

                let name = dep_v['project_id'] // todo: get descriptive name
                let version = dep_v['name']

                if (dep_v['loaders'].includes(loader)) {
                    if (proj_version_deps[requiredByProject]==undefined) {
                        proj_version_deps[requiredByProject]={}
                    }
                    if (proj_version_deps[requiredByProject][requiredByProjectVersion]==undefined) {
                        proj_version_deps[requiredByProject][requiredByProjectVersion]=[]
                    }
                    if (!proj_version_deps[requiredByProject][requiredByProjectVersion].includes(dep_v)) {
                        proj_version_deps[requiredByProject][requiredByProjectVersion].push(dep_v)
                    }

                    $('#dependencies').append(`<div project_id="${name}" version="${version}">${name} - ${version}</div>`)
                }
            }
        }
    }
}

async function showinstallation(id) {
    // show version ui for id 
    let firstVersion=true
    for (let vs of versions[id]) {
        let mc = '';
        if (vs['game_versions'].length==1) {
            mc = vs['game_versions'][0];
        } else {
            mc = '['+vs['game_versions'][0]+','+vs['game_versions'][vs['game_versions'].length-1]+']';
        }
        let v = vs['version_number'];
        let sha1 = vs['files'][0]['hashes']['sha1'];
        let filename = vs['files'][0]['filename'];
        let url = vs['files'][0]['url'];

        if (firstVersion) {
            firstVersion=false;
            if (vs['dependencies']!==undefined && vs['dependencies'].length>0) {
                $('#dependencies').empty()
                $('#installation-deps').show()
                // console.log('showinstalltion()',vs)
                await process_deps(vs) // we process deps here instead of on install as we want to show the info to the user before installing
            }
        }

        $('#installation-versions').append(`
            <option slug='${id}' filename='${filename}' url='${url}' mc='${mc}' sha1='${sha1}' v='${v}' project_id="${vs['project_id']}" version_id="${vs['id']}">${v}</option>
        `);
    }

    $('#installations-cancel').attr('disabled',false);
    $('#installations-button').attr('disabled',false);
    $('#installations-cancel').text('Cancel');
    $('#installations-button').text('Install');
}

async function installmod() {
    var installed_project_versions=[]
    var jsons=[]
    async function install(project_id,version_id,mcv) {
        if (installed_project_versions.includes(String([project_id,version_id]))) {
            console.log('already installed',project_id,version_id)
            return // this returns undefined
        }
        return new Promise(async (resolve, reject) => {

            installed_project_versions.push(String([project_id,version_id]))

            await getversions(project_id,version_id) // get version info (likely cached)

            // todo: we use project_id here, but earlier we use slug...
            console.log(project_id,version_id)
            console.log(proj_version_deps)

            if (proj_version_deps[project_id]!==undefined && proj_version_deps[project_id][version_id]!==undefined && proj_version_deps[project_id][version_id].length>0) {
                let deps = proj_version_deps[project_id][version_id]

                for (let dep of deps) {
                    console.log('installing dependency', dep)

                    await process_deps(dep) // get depdendencies of depdendency
                    await install(dep['project_id'], dep['id'], mcv) // install depdendency (and it's dependencies)
                }
            }

            let v = versions2[project_id][version_id]
            let url = v['files'][0]['url'] // get the first file

            var request = new XMLHttpRequest();

            var postdata = new FormData();
            postdata.append('url',url);
            if (url.endsWith('.jar')) {
                postdata.append('filename', url.substring(url.lastIndexOf('/') + 1));
            }
            postdata.append('fallback_mcversion', mcv);

            request.open("POST", "./functions/send_mods.php", true);
            request.onreadystatechange = function() {
                if (request.readyState == 4 && request.status == 200) {
                    console.log(request.response);
                    let json = JSON.parse(this.response);
                    jsons.push(json)
                    resolve(jsons)
                    return jsons
                }
            }
            request.onerror = function() {
                reject('install(): could not install via send_mods')
            }
            request.send(postdata);
        })
    }

    if ($('#installation-versions option:selected').attr("disabled")) {
        return;
    }

    let project_id = $('#installation-versions option:selected').attr('project_id');
    let version_id = $('#installation-versions option:selected').attr('version_id');

    // let mc = $('#installation-versions option:selected').attr('mc');
    // let url = $('#installation-versions option:selected').attr('url');
    let slug = $('#installation-versions option:selected').attr('slug');
    var title = '';
    for (let hit of results[$('#searchquery').val()]) {
        if (hit['slug']==slug){
            title = hit['title'];
            break
        }
    }

    $('#installations-button').text('Installing...');
    $('#installations-button').attr('disabled',true);
    $('#installations-cancel').attr('disabled',true);
    $('#installation-message').text('Installing '+title+'...');
    $('#installation-message').show();

    let mcv = $('#mcv option:selected').attr('mc');
    let type = $('#mcv option:selected').attr('type');

    let installed_json_results = await install(project_id,version_id,mcv)
    for (let json of installed_json_results) {

        if (json['status']=='succ'||json['status']=='info') {
            console.log('success!');
            $('#installation-versions option:selected').attr("disabled",true);
            $('#installations-button').attr('disabled',true);
            $('#installation').modal('hide');
            $('#install-'+slug).attr('disabled',true);
            $('#install-'+slug).text('Installed');
            $('#install-'+slug).addClass('btn-success');
        } else if (json['status']=='warn') {
            $('#installation-versions option:selected').attr("disabled",true);
            $('#installations-button').attr('disabled',false);
            $('#installations-cancel').attr('disabled',false);
            $('#installations-button').attr('onclick',`window.location.replace("mod?id=${json['name'][0]}")`)
            $('#installations-button').text('Fix now');
            $('#installations-cancel').text('Ignore');
            $('#install-'+slug).attr('disabled',true);
            $('#install-'+slug).text('Issue(s)');
            $('#install-'+slug).attr('onclick',`window.location.replace("mod?id=${json['name'][0]}")`)
            $('#install-'+slug).addClass('btn-warning');
        }

        if (json['status']=='error') {
            $('#installation-versions option:selected').attr("disabled",true);
            $('#installations-cancel').attr('disabled',false);
            $('#installations-cancel').text('Cancel');
            $('#installations-button').text('Install');
        } else {
            $('#installations-versions').attr('disabled',false);
            $('#installations-title').text('Install '+title);
            $('#installation-message').text(json['message']);

            let num_versions = json['modid'].length;
            let author = json['author'];
            let name = json['name'];
            let pretty_name = json['pretty_name'];
            let mcversion = json['mcversion'];
            let version = json['version'];
            let i = 0;
            $('#table-available-mods').append(`
                <tr id="mod-row-${name[i]}">
                    <td scope="row" data-value="${pretty_name[i]}">${pretty_name[i]}</td>
                    <td data-value="${author[i]}" class="d-none d-md-table-cell">${author[i]}</td>
                    <td id="num-row-${name}-version" data-value="${num_versions}">${num_versions}</td>
                    <td>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Actions">
                            <button onclick="window.location='./mod?id=${name[i]}'" class="btn btn-primary">Edit</button>
                            <button onclick="remove_box('${name[i]}')" data-toggle="modal" data-target="#removeMod" class="btn btn-danger">Remove</button>
                        </div>
                    </td>
                </tr>
            `);
        }
    }
}

async function fetch_installed() {
    if (get_cached('installed_mods')) {
        installed = get_cached('installed_mods');
    } else {
        try {
            const response = await fetch("api/mod");
            if (!response.ok) {
                throw new Error('Network response error');
            }
            installed = await response.json();
            set_cached('installed_mods', installed, 5);
        } catch (error) {
            console.log('error in fetch_installed_from_api');
        }
    }
    return installed;
}

async function is_installed(name, pretty_name, author) {
    return false; 
    // since mod slug (name) does not always match modrinth slug, check either
    // also, authors are often mismatched.
    for (let inst of installed) {
        if (name===inst['name']) { // match slug
            return inst;
        } else if (inst['pretty_name'].toLowerCase()===pretty_name.toLowerCase()) { // match projec name
            if (inst['author'].toLowerCase().includes(author.toLowerCase())) { // AND contains author
                return inst; // Return the installed object if it matches
            }
        }
    }
    return false;
}

$('#installation-versions').on('change', async function() {
    let project_id = $('#installation-versions option:selected').attr('project_id');
    let version_id = $('#installation-versions option:selected').attr('version_id');
    let vs = versions2[project_id][version_id]
    $('#dependencies').empty()
    if (vs['dependencies']!==undefined && vs['dependencies'].length>0) {
        $('#installation-deps').show()
        await process_deps(vs)
    } else {
        // console.log('no deps')
        $('#installation-deps').hide()
    }
})

$('#searchquery').on('keyup', function() {
    if (!$('#searchquery').val() || $('#searchquery').val()=='' || $('#searchquery').val() == undefined) {
        $('#searchbutton').attr('disabled',true);
        $('#searchquery').removeClass('is-valid');
    } else {
        $('#searchquery').removeClass('is-invalid');
        if (!$('#mcv').hasClass('is-invalid')) {
            $('#searchbutton').attr('disabled',false);
        }
    }
});
$('#mcv').on('change', function() {
    console.log(`"${$('#mcv option:selected').val()}"`)
    set_cached('selected_loader', btoa($('#mcv option:selected').val()), -1)

    $('#mcv').removeClass('is-invalid');
    if ($('#searchquery').val()=="") {
        $('#searchbutton').attr('disabled',true);
    }
    else if (!$('#searchquery').hasClass('is-invalid')) {
        $('#searchbutton').attr('disabled',false);
    }
    // clear previous cached search results
    // todo: on a version change, figure out how to invalidate search results without invalidating all of them
    for (let key in localStorage) {
        if (localStorage.hasOwnProperty(key) && key.startsWith('search_')) {
            localStorage.removeItem(key);
        }
    }
});

$('#pageRangeInput').on('keyup', function() {
    if (!(new RegExp($('#pageRangeInput').attr('pattern')).test($('#pageRangeInput').val()))) {
        $('#pageRangeInput').addClass('is-invalid');
    } else {
        let search_start = parseInt($('#pageRangeInput').val().split('-')[0]);
        let search_end = parseInt($('#pageRangeInput').val().split('-')[1]);
        let diff=search_end-search_start+1;
        if (diff>SEARCH_LIMIT) {
            $('#pageRangeInput').addClass('is-invalid');
            $('#pageRangeInput').attr('title','max 20 search results!');
        } else {
            $('#pageRangeInput').removeClass('is-invalid');
            $('#pageRangeInput').removeAttr('title');
        }
    }
});

$('#leftButton').on('click', function() {
    let search_start = parseInt($('#pageRangeInput').val().split('-')[0]);
    let search_end = parseInt($('#pageRangeInput').val().split('-')[1]);
    let diff=search_end-search_start+1;
    $('#pageRangeInput').val(`${search_start-diff}-${search_end-diff}`);
    if (diff>0 && diff<SEARCH_LIMIT) {
        $('#searchbutton').click();
    }
});

$('#rightButton').on('click', function() {
    let search_start = parseInt($('#pageRangeInput').val().split('-')[0]);
    let search_end = parseInt($('#pageRangeInput').val().split('-')[1]);
    let diff=search_end-search_start+1;
    $('#pageRangeInput').val(`${search_start+diff}-${search_end+diff}`);
    if (diff>0 && diff<SEARCH_LIMIT) {
        $('#searchbutton').click();
    }
});

$('#searchbutton').on('click', async function() {
    await fetch_installed();
    console.log(installed)
    $('#searchbutton').attr('disabled',true);
    $('#noresults').hide();

    if (!(new RegExp($('#pageRangeInput').attr('pattern')).test($('#pageRangeInput').val()))) {
        $('#pageRangeInput').addClass('is-invalid');
        return;
    } else {
        $('#pageRangeInput').removeClass('is-invalid');
    }
    let search_start = $('#pageRangeInput').val().split('-')[0];
    let search_end = $('#pageRangeInput').val().split('-')[1];

    let search_len = search_end-search_start+1
    let search_offset = search_start-1;

    if (search_offset<0) {
        search_offset=0;
    }
    if (search_len<0 || search_len>SEARCH_LIMIT) {
        search_len=SEARCH_LIMIT;
    }

    let searchquery = $('#searchquery').val();
    let mc = $('#mcv option:selected').attr('mc');
    let type = $('#mcv option:selected').attr('type');
    if (!searchquery || searchquery=='' || searchquery==undefined || !mc || mc=='' || mc==undefined  || !type || type=='' || type==undefined) {
        $('#mcv').addClass('is-invalid');
        $('#searchquery').addClass('is-invalid');
        return;
    } else {
        $('#mcv').removeClass('is-invalid');
        $('#searchquery').removeClass('is-invalid');
        if (get_cached('search_'+searchquery+'_'+$('#pageRangeInput').val())) {
            console.log('cached')
            $('#searchresults').empty();
            results[searchquery]=get_cached('search_'+searchquery+'_'+$('#pageRangeInput').val());
            for (let hit of results[searchquery]) {
                await addrow(hit);
            }
        } else {
            console.log('searching...');
            $('#searchresults').empty();
            results[searchquery] = [];

            var request = new XMLHttpRequest();
            let facets = JSON.stringify([[`categories:${type}`],[`versions:${mc}`],["project_type:mod"]]);
            let url = `https://api.modrinth.com/v2/search?query=${searchquery}&facets=${encodeURIComponent(facets)}&limit=${search_len}&offset=${search_offset}`;

            request.open('GET', url, true);
            request.setRequestHeader('User-Agent','TheGameSpider/TechnicSolder/1.4.0');
            request.onreadystatechange = async function() {
                if (request.readyState == 4 && request.status == 200) {
                    // console.log(request.responseText);
                    let json = JSON.parse(request.responseText);
                    if (json['hits'] && json['hits'].length>0) {
                        let hits=json['hits'];

                        for (let hit of hits) {
                            await addrow(hit);
                        };

                        results[searchquery]=hits;
                    
                        set_cached('search_'+searchquery+'_'+$('#pageRangeInput').val(), hits, SEARCH_CACHE_TTL);
                        
                    } else {
                        console.log('no hits');
                        $('#noresults').show();
                    }
                }
            };
            request.send();
        }
    }
});

$(document).ready(function() {
    $("#nav-mods").trigger('click');
    
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

    let loop_count=0;
    while (installed==null && loop_count < 3) {
        fetch_installed()
        loop_count+=1
    }

    if (get_cached('selected_loader')) {
        $('#mcv').val(atob(get_cached('selected_loader')))
    }
});
