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

// mods list can be empty! so we ignore modslist/str
const imp_fields = $('#dn, #slug, #versions, #java, #memory')
$('#collapseMp').on('shown.bs.collapse', function () {
    imp_fields.prop('required', true)

});
$('#collapseMp').on('hidden.bs.collapse', function () {
    imp_fields.prop('required', false)
});
$('#instant-modpack').on('submit', function(event){
    event.preventDefault()

    $("#submit").attr("disabled", true)
    $("#submit").text("Creating...")

    var formData = new FormData($("#instant-modpack")[0])

    var http = new XMLHttpRequest()
    http.open('POST', './functions/instant-modpack.php')
    http.onreadystatechange = function() {
        if (http.readyState == 4 && http.status == 200) {
            let json = JSON.parse(http.responseText)
            $("#instant-modpack-message").text(json.message)

            if (json.status == "error") {
                $("#instant-modpack-message").addClass("text-danger")

                $("#submit").attr("disabled", false)
                $("#submit").text("Create")
            } else {
                $("#instant-modpack-message").addClass("text-success")

                $("#submit").text("Created")
                
                window.location.href = `modpack?id=${json.id}`
            }
        }
    }
    http.send(formData)
})

const dbform_fields = $('#dbform-host, #dbform-user, #dbform-pass, #dbform-name')
$('#collapseMigr').on('shown.bs.collapse', function () {
    dbform_fields.prop('required', true)
    // we don't make the solder-orig required yet
});
$('#collapseMigr').on('hidden.bs.collapse', function () {
    dbform_fields.prop('required', false)
    $('#solder-orig').prop('required',false)
});

// handle first form connection
$("#dbform").on('submit', function(event) {
    event.preventDefault()

    $('#dbform-message').text('')
    $("#dbform-message").removeClass("text-success text-danger")

    $("#dbform-submit").attr("disabled", true)
    $("#dbform-submit").text("Connecting...")

    var formData = new FormData($("#dbform")[0])

    var http = new XMLHttpRequest()
    http.open('POST', './functions/conntest.php')
    http.onreadystatechange = function() {
        if (http.readyState == 4 && http.status == 200) {
            let json = JSON.parse(http.responseText)
            $("#dbform-message").text(json.message)

            if (json.status == "error") {
                $("#dbform-message").addClass("text-danger")

                $("#dbform-submit").attr("disabled", false)
                $("#dbform-submit").text("Connect")
            } else {
                $("#dbform-message").addClass("text-success")

                $("#dbform").hide()
                $("#dbform2").show()

                // now make solder-orig required
                $('#solder-orig').prop('required',true)
            }
        }
    }
    http.send(formData)
});

// handle second form actual migration
$("#dbform2").on('submit', function(event){
    event.preventDefault();

    $('#dbform-message').text('')
    $("#dbform-message").removeClass("text-success text-danger")

    $("#dbform2-submit").attr('disabled',true)
    $("#dbform2-submit").text('Migrating...')

    // get db credentials from previous form
    var formData = new FormData($("#dbform")[0]);
    formData.set('solder-orig', $('#solder-orig').val())

    var http = new XMLHttpRequest();
    http.open('POST', './functions/migrate.php');
    http.onreadystatechange = function() {
        if (http.readyState == 4 && http.status == 200) {
            let json = JSON.parse(http.responseText)
            $("#dbform-message").text(json.message)

            if (json.status == "error") {
                $("#dbform-message").addClass("text-danger")

                $("#dbform2-submit").attr('disabled',false)
                $("#dbform2-submit").text('Migrate')
            } else {
                $("#dbform-message").addClass("text-success")

                $("#dbform2-submit").text("Done")
                $('#dbform2').hide()
            }
        }
    }
    http.send(formData)
});

$("#dn").on("keyup", function(){
    var slug = slugify($(this).val());
    $("#slug").val(slug);
});