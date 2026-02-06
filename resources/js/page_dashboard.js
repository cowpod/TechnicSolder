async function send_mod(file) {
    let response = undefined;
    if ($('#versions option').length > 0) {
        response = await sendFile(file, $('#versions option:selected').attr('mc')) // here we await actually getting a response
    } else {
        response = await sendFile(file) // here we await actually getting a response
    }
    
    if (response.status != "error") {
        let filename = file.name.replace(/[^\w\-]/g, '_')
        $('#name-'+filename).text(response['name'])

        if ($('#modliststr').val() == "") {
            $('#modlist').val(response.modid);
            $('#modliststr').val(response.name);
        } else {
            $('#modlist').val($('#modlist').val() + "," + response.modid);
            $('#modliststr').val($('#modliststr').val() + "," + response.name);
        }
    }
}

function showFile(file, i) {
    return new Promise((resolve, reject) => {
        let filename = file.name.replace(/[^\w\-]/g, '_')
        $("#table-mods").append(`<tr>
            <td id="name-${filename}" scope="row">${file.name}</td> 
            <td>
                <em id="cog-${filename}" class="fas fa-cog fa-spin"></em>
                <em id="check-${filename}" style="display:none" class="text-success fas fa-check"></em>
                <em id="inf-${filename}" style="display:none" class="text-info fas fa-info"></em>
                <em id="exc-${filename}" style="display:none" class="text-warning fas fa-exclamation"></em>
                <em id="times-${filename}" style="display:none" class="text-danger fas fa-times"></em>
                <small id="info-${filename}" class="text-muted"></small>
                <div class="progress">
                    <div id="prog-${filename}" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%"></div>
                </div>
            </td>
        </tr>`)
        resolve(true)
    })
}

function againMods() {
    $("#upload-card").show();
    $("#u-mods").hide();
}

$(':file').change(async function() {
    $("#btn-done").attr("disabled", true);
    $("#submit").attr("disabled", true);

    $("#upload-card").hide();
    $("#u-mods").show();

    for (var i = 0; i < this.files.length; i++) {
        await showFile(this.files[i], i); // await that the rows are created
    }
    for (var i = 0; i < this.files.length; i++) {
        send_mod(this.files[i]); // no need to await the uploads
    }

    $("#btn-done").attr("disabled", false);
    $("#submit").attr("disabled", false);
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

$("#dn").on("keyup", function(){
    var slug = slugify($(this).val());
    $("#slug").val(slug);
});