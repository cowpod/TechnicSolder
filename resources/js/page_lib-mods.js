const SEARCH_LIMIT=20;

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

                        // console.log(response['status']);
                        if (response['status']!="error") {
                            let num_versions = response['modid'].length;
                            let author = response['author'];
                            let name = response['name'];
                            let pretty_name = response['pretty_name'];
                            let mcversion = response['mcversion'];
                            let version = response['version'];
                            // console.log('add new row');
                            let i=0;
                            // for (let id of response['modid']) {
                                $('#table-available-mods').append(`
                                    <tr id="mod-row-${name[i]}">
                                        <td scope="row" data-value="${pretty_name[i]}">${pretty_name[i]}</td>
                                        <td data-value="${author[i]}" class="d-none d-sm-table-cell">${author[i]}</td>
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


var results={};
var details={};
var versions={};
var installed=null;

function addrow(hit) {
    let description = hit['description'];
    if (description.length>80) {
       description = description.substring(0,200)+"...";
    }
    let categories = hit['display_categories'].join(', ');
    if (categories.length>40) {
       categories = categories.substring(0,40)+"...";
    }

    let author = hit['author'];
    let instv=is_installed(hit['title']);
    if (instv) {
        if (instv['mcversion']==''||instv['version']=='') {
            var btn=`<a id="install-${hit['slug']}" class="btn btn-secondary btn-warning" href="mod?id=${instv['name']}" pid="${hit['slug']}" target="_blank">Issue(s)</a>`;
        } else {
            var btn = `<a id="install-${hit['slug']}" class="btn btn-secondary btn-success" href="javascript:void(0)" pid="${hit['slug']}" disabled>Installed</a>`;
        }
    } else {
        var btn = `<a id="install-${hit['slug']}" class="btn btn-secondary" href="javascript:void(0)" pid="${hit['slug']}" onclick="getversions('${hit['slug']}').then(result => {showinstallation(result)});">Install</a>`;
    }
    var row = `
        <tr>
            <td data-value='${hit['title']}'>${hit['title']}</td>
            <td data-value='str${categories}...' class="d-none d-md-table-cell" style='overflow-wrap: break-word;' onclick="showcategories('${hit['slug']}')"><a href="javascript:void(0)"onclick="showcategories('${hit['slug']}')" style="word-wrap: break-all">${categories}</a></td>
            <td data-value='${description}' style='overflow-wrap: break-word;' onclick="getdescription('${hit['slug']}')"><a href="javascript:void(0)" onclick="getdescription('${hit['slug']}')" style="word-wrap: break-all">${description}</a></td>
            <td data-value='${author}' class="d-none d-md-table-cell">${author}</td>
            <td>${btn}</td>
        </tr>
    `;
    $('#searchresults').append(row);
}

function getdescription(id) {
    if (id in details) {
        console.log('got cached details');
        showdetails(id);
    } else if (get_cached('details_'+id)) {
        console.log('got cached details from localstorage');
        details[id]=JSON.parse(localStorage['details_'+id]);
        showdetails(id);
    } else {
        var request = new XMLHttpRequest();
        let mcv = JSON.stringify([$('#mcv option:selected').attr('mc')]);
        let loader = JSON.stringify([$('#mcv option:selected').attr('type')]);
        let url = `https://api.modrinth.com/v2/project/${id}`;
        request.open('GET', url, true);
        request.setRequestHeader('User-Agent','TheGameSpider/TechnicSolder/1.4.0');
        request.onreadystatechange = function() {
            if (request.readyState == 4 && request.status == 200) {
                let obj = JSON.parse(request.responseText);
                set_cached('details_'+id, request.responseText,1800);
                details[id]=obj;
                console.log('got new description');
                showdetails(id);
            }
        };
        request.send();
    }
}
function showdetails(id) {
    $('#description').modal('show');
    $('#description-title').text('Desciption: '+details[id]['title']);
    const md =marked.parse(details[id]['body']);
    const htmlWithTargetBlank = md.replace(/<a /g, '<a target="_blank" ');
    $('#description-body').html(htmlWithTargetBlank);
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

async function getversions(id) {
    return new Promise((resolve, reject) => {
        $('#installations-cancel').attr('disabled',true);
        $('#installations-button').attr('disabled',true);
        $('#installation-message').hide();
        $('#installation-versions').empty();
        $('#installation').modal('show');
        $('#installation-title').text('Install '+id);
        $('#installations-button').text('Loading...');
        $('#installations-cancel').text('Cancel');
        $('#installations-button').attr('onclick','installmod()');

        if (id in versions) {
            console.log('got cached versions');
            resolve(id)
        } else if (get_cached('versions_'+id)) {
            console.log('got cached versions from localstorage');
            versions[id]=JSON.parse(get_cached('versions_'+id));

            resolve(id)
        } else {
            var request = new XMLHttpRequest();
            let mcv = JSON.stringify([$('#mcv option:selected').attr('mc')]);
            let loader = JSON.stringify([$('#mcv option:selected').attr('type')]);
            let url = `https://api.modrinth.com/v2/project/${id}/version?game_versions=${encodeURIComponent(mcv)}&loaders=${encodeURIComponent(loader)}`;
            request.open('GET', url, true);
            request.onerror = function () {
              reject(new Error('Network error'));
            };
            request.setRequestHeader('User-Agent','TheGameSpider/TechnicSolder/1.4.0');
            request.onreadystatechange = function() {
                if (request.readyState == 4 && request.status == 200) {
                    let obj = JSON.parse(request.responseText);
                    set_cached('versions_'+id, request.responseText, 1800);
                    console.log('got new versions for id='+id);
                    versions[id] = obj;

                    resolve(id)
                }
            };
            request.send();
        }
    });
}
function showinstallation(id) {
    versions[id].forEach(function(vs) {
        let mc = '';
        if (vs['game_versions'].length==1) {
            mc = vs['game_versions'][0];
        } else {
            mc = '['+vs['game_versions'][0]+','+vs['game_versions'][vs['game_versions'].length-1]+']';
        }
        let v = vs['version_number'];
        let sha1=vs['files'][0]['hashes']['sha1'];
        let filename=vs['files'][0]['filename'];
        let url = vs['files'][0]['url'];
        $('#installation-versions').append(`
            <option slug='${id}' filename='${filename}' url='${url}' mc='${mc}' sha1='${sha1}' v='${v}'>${v}</option>
        `);
        return;
    });
    $('#installations-cancel').attr('disabled',false);
    $('#installations-button').attr('disabled',false);
    $('#installations-cancel').text('Cancel');
    $('#installations-button').text('Install');
}

function installmod() {
    if ($('#installation-versions option:selected').attr("disabled")) {
        return;
    }

    let v = $('#installation-versions').val();
    let mc = $('#installation-versions option:selected').attr('mc');
    let url = $('#installation-versions option:selected').attr('url');
    // let sha1 = $('#installation-versions option:selected').attr('sha1');
    let slug = $('#installation-versions option:selected').attr('slug');
    let title = '';
    // let author = '';
    // // let authorlink = '';
    // let description = '';
    results[$('#searchquery').val()].forEach(function(hit) {
        if (hit['slug']==slug){
            title=hit['title'];
            // author=hit['author'];
            // description=hit['description'];
            // authorlink=hit['link'];
            return;
        }
    });
    // let loader = $('#mcv option:selected').attr('type');


    $('#installations-button').text('Installing...');
    $('#installations-button').attr('disabled',true);
    $('#installations-cancel').attr('disabled',true);
    $('#installation-message').text('Installing '+title+'...');
    $('#installation-message').show();

    console.log(url);

    let mcv = $('#mcv option:selected').attr('mc');
    let type = $('#mcv option:selected').attr('type');

    var request = new XMLHttpRequest();
    var postdata=new FormData();
    postdata.append('url',url);
    if (url.endsWith('.jar')) {
        var filename=url.substring(url.lastIndexOf('/') + 1);
        postdata.append('filename', filename);
    }
    postdata.append('fallback_mcversion', mcv);
    // postdata.append('fallback_type', type);

    request.open("POST", "./functions/send_mods.php", true);
    request.onreadystatechange = function() {
        if (request.readyState == 4 && request.status == 200) {
            console.log(request.response);
            json = JSON.parse(this.response);
            if (json['status']=='succ'||json['status']=='info') {
                console.log('success!');
                $('#installation-versions option:selected').attr("disabled",true);
                $('#installations-button').attr('disabled',true);
                $('#installation').modal('hide');
                $('#install-'+slug).attr('disabled',true);
                $('#install-'+slug).text('Installed');
                $('#install-'+slug).addClass('btn-success');
                fetch_installed();
            } else if (json['status']=='warn') {
                $('#installation-versions option:selected').attr("disabled",true);
                $('#installations-button').attr('disabled',false);
                $('#installations-cancel').attr('disabled',false);
                $('#installations-button').attr('onclick',`window.location.replace("mod?id=${json['name']}")`)
                $('#installations-button').text('Fix now');
                $('#installations-cancel').text('Ignore');
                $('#install-'+slug).attr('disabled',true);
                $('#install-'+slug).text('Issue(s)');
                $('#install-'+slug).addClass('btn-warning');
                fetch_installed();
            } else {
                $('#installation-versions option:selected').attr("disabled",true);
                // $('#installations-button').attr('disabled',false);
                $('#installations-cancel').attr('disabled',false);
                $('#installations-cancel').text('Cancel');
                $('#installations-button').text('Install');
            }
            $('#installations-versions').attr('disabled',false);
            $('#installations-title').text('Install '+title);
            $('#installation-message').text(json['message']);

            if (json['status']!='error') {
                let num_versions = json['modid'].length;
                let author = json['author'];
                let name = json['name'];
                let pretty_name = json['pretty_name'];
                let mcversion = json['mcversion'];
                let version = json['version'];
                let i=0;
                $('#table-available-mods').append(`
                    <tr id="mod-row-${name[i]}">
                        <td scope="row" data-value="${pretty_name[i]}">${pretty_name[i]}</td>
                        <td data-value="${author[i]}" class="d-none d-md-table-cell">${author[i]}</td>
                        <td data-value="${num_versions}">${num_versions}</td>
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
    request.send(postdata);
}

function fetch_installed_from_api() {
    return new Promise((resolve, reject) => {
        var request = new XMLHttpRequest();
        request.open("GET", "api/mod", true);
        request.onreadystatechange = function() {
            if (request.readyState == 4 && request.status == 200) {
                // console.log(request.response);
                resolve(JSON.parse(request.response));
            }
        }
        request.onerror = function () {
          reject(new Error('Network error'));
        };
        request.send();
    });
}
async function fetch_installed() {
    res = await fetch_installed_from_api();
    installed=res;
    return res;

}
function is_installed(pretty_name) {
    let result_names=[];
    for (let res of results[$('#searchquery').val()]) {
        result_names.push(res['title'].toLowerCase());
    }
    for (let inst of installed) {
        if (inst['pretty_name'].toLowerCase()==pretty_name.toLowerCase()) {
            if (result_names.includes(inst['pretty_name'].toLowerCase())) {
                return inst;
            }
        }
    }
    return false;
}

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
    $('#mcv').removeClass('is-invalid');
    if ($('#searchquery').val()=="") {
        $('#searchbutton').attr('disabled',true);
    }
    else if (!$('#searchquery').hasClass('is-invalid')) {
        $('#searchbutton').attr('disabled',false);
    }
});

$('#searchbutton').on('click', async function() {
    let installed = await fetch_installed();
    $('#searchbutton').attr('disabled',true);
    $('#noresults').hide();

    let searchquery = $('#searchquery').val();
    let mc = $('#mcv option:selected').attr('mc');
    let type = $('#mcv option:selected').attr('type');
    if (!searchquery || searchquery=='' || searchquery==undefined || !mc || mc=='' || mc==undefined  || !type || type=='' || type==undefined) {
        $('#mcv').addClass('is-invalid');
        $('#searchquery').addClass('is-invalid');
        return;
    }
    else {
        $('#mcv').removeClass('is-invalid');
        $('#searchquery').removeClass('is-invalid');
        if (searchquery in results) { // tmp
            $('#searchresults').empty();
            results[searchquery].forEach(function(hit) {
                addrow(hit);
            });
        } else if (get_cached(searchquery)) {
            $('#searchresults').empty();
            results[searchquery]=get_cached(searchquery);
            results[searchquery].forEach(function(hit) {
                addrow(hit);
            });
        } else {
            console.log('searching...');
            $('#searchresults').empty();
            results[searchquery]=[];

            var request = new XMLHttpRequest();
            let facets=JSON.stringify([[`categories:${type}`],[`versions:${mc}`],["project_type:mod"]]);
            let url = `https://api.modrinth.com/v2/search?query=${searchquery}&facets=${encodeURIComponent(facets)}&limit=${SEARCH_LIMIT}`;
            request.open('GET', url, true);
            request.setRequestHeader('User-Agent','TheGameSpider/TechnicSolder/1.4.0');
            request.onreadystatechange = function() {
                if (request.readyState == 4 && request.status == 200) {
                    // console.log(request.responseText);
                    let json = JSON.parse(request.responseText);
                    if (json['hits'] && json['hits'].length>0) {
                        let hits=json['hits'];

                        for (let hit of hits) {
                            addrow(hit);
                        };

                        results[searchquery]=hits;
                    
                        set_cached(searchquery, hits, 1800);
                        
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
        installed=fetch_installed()
        loop_count+=1
    }
});
