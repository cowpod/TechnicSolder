function validatePassword(password) {
    const minLength = password.length >= 8;
    const hasNumber = /[0-9]/.test(password);
    const hasLowerCase = /[a-z]/.test(password);
    const hasUpperCase = /[A-Z]/.test(password);

    if (!minLength) {
        return false;
    }
    if (!hasNumber || !hasUpperCase || !hasLowerCase) {
        return false;
    }
    return true;
}

$("#newname").on("keyup", function() {
    if ($("#newname").val()!=="") {
        if ($("#newname").val()!==$("#newname").attr("oldvalue")) {
            // $("#newname").addClass("is-valid");
            $("#newname").removeClass("is-invalid");
            $("#newnamesubbmit").attr("disabled", false);
        } else {
            // $("#newname").removeClass("is-valid");
            $("#newname").removeClass("is-invalid");
            $("#newnamesubbmit").attr("disabled", true);
        }
    } else {
        $("#newname").addClass("is-invalid");
        // $("#newname").removeClass("is-valid");
        $("#newnamesubbmit").attr("disabled", true);
    }
});

$("#pass1").on("keyup", function() {
    $("#oldpass").removeClass("is-invalid");
    if ($("#pass1").val()!=="" && validatePassword($("#pass1").val())) {
        $("#pass1").addClass("is-valid");
        $("#pass1").removeClass("is-invalid");
        if ($("#pass1").val()!==""&&$("#pass2").val()!==""&&$("#pass1").val()==$("#pass2").val()) {
            $("#save-button").attr("disabled", false);
        }
        if ($("#pass2").val()!="") {
            if ($("#pass1").val()==$("#pass2").val()) {
                $("#pass2").addClass("is-valid");
                $("#pass2").removeClass("is-invalid");
            } else {
                $("#pass2").removeClass("is-valid");
                $("#pass2").addClass("is-invalid");
            }
        }
    } else {
        $("#pass1").addClass("is-invalid");
        $("#pass1").removeClass("is-valid");
        $("#save-button").attr("disabled", true);
    }
});
$("#pass2").on("keyup", function() {
    $("#oldpass").removeClass("is-invalid");
    $('#pass1').keyup();
    if ($("#pass2").val()!=="" && $("#pass2").val()==$("#pass1").val() && validatePassword($("#pass2").val())) {
        $("#pass2").addClass("is-valid");
        $("#pass2").removeClass("is-invalid");
        if ($("#pass1").val()!==""&&$("#pass2").val()!==""&&$("#pass1").val()==$("#pass2").val()) {
            $("#save-button").attr("disabled", false);
        }
    } else {
        $("#pass2").addClass("is-invalid");
        $("#pass2").removeClass("is-valid");
        $("#save-button").attr("disabled", true);
    }
});
$("#oldpass").on("keyup", function() {
    if ($("#oldpass").hasClass("is-invalid")) { // if we got marked as bad
        $("#oldpass").removeClass("is-invalid"); // we don't verify its actually changed
        $("#save-button").attr("disabled", false);
    }
})
$("#newIcon").change(function(){
    var formData = new FormData();
    var request = new XMLHttpRequest();
    icon = document.getElementById('newIcon');
    formData.set('newIcon', icon.files[0]);
    request.open('POST', './functions/update-user.php');
    request.onreadystatechange = function() {
        if (request.readyState == 4 && request.status == 200) {
            console.log(request.responseText);
            obj = JSON.parse(request.responseText)

            if (obj['status']=='succ') {
                $("#user-photo-preview").attr('src', `data:${obj['type']};base64,${obj["data"]}`)
                $("#user-photo").attr('src', `data:${obj['type']};base64,${obj["data"]}`)
                // $("#user-photo-message").text('You may need to clear your cache to see your new photo.')
                // $("#user-photo-message").show();
            }
        }
    }
    request.send(formData);
});
var perm1 = $("#perms").val().substr(0,1);
var perm2 = $("#perms").val().substr(1,1);
var perm3 = $("#perms").val().substr(2,1);
var perm4 = $("#perms").val().substr(3,1);
var perm5 = $("#perms").val().substr(4,1);
var perm6 = $("#perms").val().substr(5,1);
var perm7 = $("#perms").val().substr(6,1);
if (perm1==1) {
    $('#perm1').prop('checked', true);
} else {
    $('#perm1').prop('checked', false);
}
if (perm2==1) {
    $('#perm2').prop('checked', true);
} else {
    $('#perm2').prop('checked', false);
}
if (perm3==1) {
    $('#perm3').prop('checked', true);
} else {
    $('#perm3').prop('checked', false);
}
if (perm4==1) {
    $('#perm4').prop('checked', true);
} else {
    $('#perm4').prop('checked', false);
}
if (perm5==1) {
    $('#perm5').prop('checked', true);
} else {
    $('#perm5').prop('checked', false);
}
if (perm6==1) {
    $('#perm6').prop('checked', true);
} else {
    $('#perm6').prop('checked', false);
}
if (perm7==1) {
    $('#perm7').prop('checked', true);
} else {
    $('#perm7').prop('checked', false);
}

$("#api_key").on("keyup", function() {
    if (/^[a-zA-Z0-9]{32}$/.test($("#api_key").val())||$("#api_key").val().length==0) {
        $("#save_api_key").attr("disabled",false);
        $("#api_key").removeClass("is-invalid");
    } else {
        $("#save_api_key").attr("disabled",true);
        $("#api_key").removeClass("is-valid");
        $("#api_key").addClass("is-invalid");
    }
});

$("#save_api_key").on("click", function() {
    if (/^[a-zA-Z0-9]{32}$/.test($("#api_key").val())||$("#api_key").val().length==0) {
        let formData = new FormData();
        let request = new XMLHttpRequest();
        formData.set('api_key', $("#api_key").val());
        request.open('POST', './functions/save_api_key.php');
        request.onreadystatechange = function() {
            if (request.readyState == 4 && request.status == 200) {
                console.log(request.responseText);
                let jsondata=JSON.parse(request.responseText);
                if (jsondata['status']=='succ') {
                    $("#api_key").addClass("is-valid");
                    $("#api_key").removeClass("is-invalid");
                } else {
                    $("#api_key").addClass("is-invalid");
                    $("#api_key").removeClass("is-valid");
                }
                $("#save_api_key").attr("disabled",true);
            }
        }
        request.send(formData);
    } else {
        $("#save_api_key").attr("disabled",true);
        $("#api_key").addClass("is-invalid");
        $("#api_key").removeClass("is-valid");
    }
});

$("#change-name").on("submit", function(event) {
    event.preventDefault();
    if ($("#newname").val()!=="" && $("#newname").val()!==$("#newname").attr("oldvalue")) {
        let formData = new FormData();
        let request = new XMLHttpRequest();
        formData.set('display_name', $("#newname").val());
        request.open('POST', './functions/update-user.php');
        request.onreadystatechange = function() {
            if (request.readyState == 4 && request.status == 200) {
                console.log(request.responseText);
                let jsondata=JSON.parse(request.responseText);
                if (jsondata['status']=='succ') {
                    $("#newname").addClass("is-valid");
                    $("#newname").removeClass("is-invalid");
                    $("#newname").attr("oldvalue", jsondata["name"]) // otherwise #newname->onkeyup will mark it as changed
                    $("#user-name").text(jsondata["name"])
                } else {
                    $("#newname").addClass("is-invalid");
                    $("#newname").removeClass("is-valid");
                }
                $("#newnamesubbmit").attr("disabled",true);
                $("#change-name-message").text(jsondata['message'])
                $("#change-name-message").show()
            }
        }
        request.send(formData);
    }
});


$("#change-password").on("submit", function(event) {
    event.preventDefault();
    if ($("#pass1").val()!=="" && $("#pass1").val()===$("#pass2").val() && validatePassword($("#pass1").val())) {
        let formData = new FormData();
        let request = new XMLHttpRequest();
        formData.set('oldpass', $("#oldpass").val());
        formData.set('pass', $("#pass1").val());
        request.open('POST', './functions/update-user.php');
        request.onreadystatechange = function() {
            if (request.readyState == 4 && request.status == 200) {
                console.log(request.responseText);
                let jsondata=JSON.parse(request.responseText);
                if (jsondata['status']=='succ') {
                    $("#oldpass").removeClass("is-invalid");
                    $("#pass1").removeClass("is-valid");
                    $("#pass1").removeClass("is-invalid");
                    $("#pass2").removeClass("is-valid");
                    $("#pass2").removeClass("is-invalid");
                    $("#oldpass").val("")
                    $("#pass1").val("")
                    $("#pass2").val("")
                } else {
                    if (jsondata["message"] == "Could not verify old password") {
                        $("#oldpass").addClass("is-invalid")
                    } else {
                        $("#oldpass").removeClass("is-invalid");
                        $("#pass1").addClass("is-invalid");
                        $("#pass1").removeClass("is-valid");
                        $("#pass2").addClass("is-invalid");
                        $("#pass2").removeClass("is-valid");
                    }
                }
                $("#change-password-message").html(jsondata['message'])
                $("#change-password-message").show()
                $("#save-button").attr("disabled",true);
            }
        }
        request.send(formData);
    }
});

$(document).ready(function(){
    $("#nav-settings").trigger('click');
});