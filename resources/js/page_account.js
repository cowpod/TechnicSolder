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

$("#pass1").on("keyup", function() {
    if ($("#pass1").val()!=="" && validatePassword($("#pass1").val())) {
        $("#pass1").addClass("is-valid");
        $("#pass1").removeClass("is-invalid");
        if ($("#pass1").val()!==""&&$("#pass2").val()!==""&&$("#pass1").val()==$("#pass2").val()) {
            $("#save-button").attr("disabled", false);
        }
    } else {
        $("#pass1").addClass("is-invalid");
        $("#pass1").removeClass("is-valid");
        $("#save-button").attr("disabled", true);
    }
});
$("#pass2").on("keyup", function() {
    if ($("#pass2").val()!==""&$("#pass2").val()==$("#pass1").val() && validatePassword($("#pass2").val())) {
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
$("#newIcon").change(function(){
    var formData = new FormData();
    var request = new XMLHttpRequest();
    icon = document.getElementById('newIcon');
    formData.set('newIcon', icon.files[0]);
    request.open('POST', './functions/new_icon.php');
    request.onreadystatechange = function() {
        if (request.readyState == 4) {
            console.log(this.responseText);
            setTimeout(function(){ window.location.reload(); }, 500);
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
            if (request.readyState == 4) {
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

$(document).ready(function(){
    $("#nav-settings").trigger('click');
});