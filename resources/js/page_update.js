var local_json=null;
var remote_json=null;

async function local_version() {
    const parentElement = document.currentScript;
    let newElement = document.createElement("font");
    if (local_json!==null) { // if we are certain it has been (or attempted) loaded yet
        newElement.textContent=local_json['version']
        insertAfter(parentElement, newElement);
    } else {
        local_json = await getData('/api/version.json', {cache: "no-store"})
        if (local_json['version'][0]=='v') {
            local_json['version']=local_json['version'].slice(1)
        }
        newElement.textContent=local_json['version']
        insertAfter(parentElement, newElement);
    }
}

async function remote_version() {
    const parentElement = document.currentScript;
    let newElement = document.createElement("font");
    if (remote_json!==null) { // if we are certain it has been (or attempted) loaded yet
        newElement.textContent=remote_json['version']
        insertAfter(parentElement, newElement);
    } else {
        remote_json = await getData('https://raw.githubusercontent.com/cowpod/TechnicSolder/refs/heads/dev/api/version.json', {cache: "no-store"})
        if (remote_json['version'][0]=='v') {
            remote_json['version']=remote_json['version'].slice(1)
        }
        newElement.textContent=remote_json['version']
        insertAfter(parentElement, newElement);
    }
}

async function local_changelog() {
    const parentElement = document.currentScript;
    let newElement = document.createElement("font");
    if (local_json!==null) { // if we are certain it has been (or attempted) loaded yet
        newElement.innerHTML=local_json['changelog']
        insertAfter(parentElement, newElement);
    } else {
        local_json = await getData('/api/version.json', {cache: "no-store"})
        if (local_json['version'][0]=='v') {
            local_json['version']=local_json['version'].slice(1)
        }
        newElement.innerHTML=local_json['changelog']
        insertAfter(parentElement, newElement);
    }
}

async function remote_changelog() {
    const parentElement = document.currentScript;
    let newElement = document.createElement("font");
    if (remote_json!==null) { // if we are certain it has been (or attempted) loaded yet
        newElement.innerHTML=remote_json['changelog']
        insertAfter(parentElement, newElement);
    } else {
        remote_json = await getData('https://raw.githubusercontent.com/cowpod/TechnicSolder/refs/heads/dev/api/version.json', {cache: "no-store"})
        if (remote_json['version'][0]=='v') {
            remote_json['version']=remote_json['version'].slice(1)
        }
        newElement.innerHTML=remote_json['changelog']
        insertAfter(parentElement, newElement);
    }
}

async function check_for_json_updates() {
    $('#updater-loading').show();
    const parentElement = document.currentScript;
    let newElement = document.createElement("font");

    let ret="";
    if (local_json===null) {
        local_json = await getData('/api/version.json', {cache: "no-store"})
        if (local_json['version'][0]=='v') {
            local_json['version']=local_json['version'].slice(1)
        }
    }
    if (remote_json===null) { 
        remote_json = await getData('https://raw.githubusercontent.com/cowpod/TechnicSolder/refs/heads/dev/api/version.json', {cache: "no-store"})
        if (remote_json['version'][0]=='v') {
            remote_json['version']=remote_json['version'].slice(1)
        }
    }

    let comp = compareVersions(local_json['version'], remote_json['version'])
    if (comp === 1 || comp == 0) { // ahead or equal
        ret = "Up to date!"
        $('#updater-container').addClass('alert-success')
    } else if (comp === -1) { // behind
        ret = "Outdated!"
        $('#updater-container').addClass('alert-info')
    }

    newElement.innerHTML=ret
    insertAfter(parentElement, newElement);

    $('#updater-loading').hide();
}

async function check_for_json_updates_changelog() {
    $('#updater-loading').show();
    const parentElement = document.currentScript;
    let newElement = document.createElement("font");

    let ret="";
    if (local_json===null) {
        local_json = await getData('/api/version.json', {cache: "no-store"})
    }
    if (remote_json===null) { 
        remote_json = await getData('https://raw.githubusercontent.com/cowpod/TechnicSolder/refs/heads/dev/api/version.json', {cache: "no-store"})
    }

    let comp = compareVersions(local_json['version'], remote_json['version'])
    if (comp === 1 || comp == 0) { // ahead or equal
        ret = local_json['changelog']
    } else if (comp === -1) { // behind
        ret = remote_json['changelog']
    }

    newElement.innerHTML=ret
    insertAfter(parentElement, newElement);
    $('#updater-loading').hide();
}

$('#install-updates').click(async function() {
    $('#install-updates').attr('disabled',true)
    $('#install-updates-in-progress').show()

    var formData = new FormData();
    formData.set('install-updates', 'true');

    var request = new XMLHttpRequest();
    request.open('POST', './functions/solder-updater.php');
    request.onreadystatechange = function() {
        if (request.readyState == 4 && request.status == 200) {
            console.log(request.responseText);
            obj = JSON.parse(request.responseText)
            if (obj['status']==='error') {
                $('#updater-container').removeClass('alert-success')
                $('#updater-container').removeClass('alert-info')
                $('#updater-container').removeClass('alert-warning')
                $('#updater-container').addClass('alert-danger')
            }
            $('#install-updates-in-progress').html(obj['message']+"<pre class='code'>"+obj['logs']+"</pre>");
        }
    }
    request.send(formData);
})