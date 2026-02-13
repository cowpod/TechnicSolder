$("#host").on("keyup", function() {
    let hostval = $("#host").val();
    if (hostval.startsWith("https://") || hostval.startsWith("http://")) {
        $("#host-warning").show();
    } else if ($("#host-warning").is(":visible")) {
        $("#host-warning").hide();
    }
});
$("#pass").on("keyup", function() {
    if (validatePassword($("#pass").val())) {
        $("#pass").addClass("is-valid");
        $("#pass").removeClass("is-invalid");
    } else {
        $("#pass").addClass("is-invalid");
        $("#pass").removeClass("is-valid");
    }
    if ($("#pass2").val()==$("#pass").val() && validatePassword($("#pass2").val())) {
        $("#pass2").addClass("is-valid");
        $("#pass2").removeClass("is-invalid");
        $("#pass").addClass("is-valid");
        $("#pass").removeClass("is-invalid");
    } else if($("#pass2").val()!="") {
        $("#pass2").addClass("is-invalid");
        $("#pass2").removeClass("is-valid");
    }
});
$("#pass2").on("keyup", function() {
    if ($("#pass2").val()==$("#pass").val() && validatePassword($("#pass2").val())) {
        $("#pass2").addClass("is-valid");
        $("#pass2").removeClass("is-invalid");
    } else {
        $("#pass2").addClass("is-invalid");
        $("#pass2").removeClass("is-valid");
    }
});
$('#db-type').change(function() {
    if ($(this).val()==="sqlite") {
        $("#db-host").removeAttr('required');
        $("#db-user").removeAttr('required');
        $("#db-name").removeAttr('required');
        $("#db-pass").removeAttr('required');
        $("#mysql-options").hide();
        $("#errtext").hide();
    } else {
        $("#db-host").attr('required','required');
        $("#db-user").attr('required','required');
        $("#db-name").attr('required','required');
        $("#db-pass").attr('required','required');
        $("#mysql-options").show();
    }
});
$("#db-pass").on("keyup", function() {
    let http = new XMLHttpRequest();
    let params = 'db-type='+$("#db-type").val() +'&db-pass='+ $("#db-pass").val() +'&db-name='+ $("#db-name").val() +'&db-user='+
        $("#db-user").val() +'&db-host='+ $("#db-host").val();
    http.open('POST', './functions/conntest.php');
    http.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    http.onreadystatechange = function() {
        if (http.readyState == 4 && http.status == 200) {
            let json = JSON.parse(http.responseText)
            $("#errtext").text(json.message);

            if (json.status == "error") {
                $("#errtext").removeClass("text-success");
                $("#errtext").addClass("text-danger");
            } else {
                $("#errtext").removeClass("text-danger");
                $("#errtext").addClass("text-success");
            }
        }
    }
    http.send(params);
});

$('#cache').change(function() {
    if ($(this).val() === "redis") {
        $("#redis-host").attr('required','required')
        $("#redis-port").attr('required','required')
        $("#redis-password").attr('required','required')
        $('#redis-options').show()
    } else {
        $("#redis-host").removeAttr('required')
        $("#redis-port").removeAttr('required')
        $("#redis-password").removeAttr('required')
        $('#redis-options').hide()
    }
})

$("#api_key").on("keyup", function() {
    if ($("#api_key").val().length==32 && /^[a-zA-Z0-9]+$/.test($('#api_key').val())) {
        $("#api_key").addClass("is-valid");
        $("#api_key").removeClass("is-invalid");
    } else {
        $("#api_key").removeClass("is-valid");
        $("#api_key").addClass("is-invalid");
    }
});

$(document).ready(function() {
    var loc = window.location.pathname;
    var dir = loc.substring(0, loc.lastIndexOf('/'));
    $("#dir").val(dir + "/");
    if ($("#dir").val()=="//") {
        $("#dir").val("/");
    }
});