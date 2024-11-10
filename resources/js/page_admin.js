function validatePassword(password) {
    const minLength = password.length >= 8;
    const hasNumber = /[0-9]/.test(password);
    const hasLowerCase = /[a-z]/.test(password);
    const hasUpperCase = /[A-Z]/.test(password);
    const hasSpecialChar = /[!@#$%^&*(),.?":{}|<>]/.test(password);

    if (!minLength) {
        return false;
    }
    if (!hasNumber || !hasUpperCase || !hasLowerCase || !hasSpecialChar) {
        return false;
    }
    return true;
}


function remove(id) {
    var request = new XMLHttpRequest();
    request.open('POST', './functions/remove_user.php');
    request.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    request.onreadystatechange = function() {
        if (request.readyState == 4) {
            console.log(request.responseText);
            // $("#info").html(request.responseText + "<br />");
            // setTimeout(function(){ window.location.reload(); }, 500);
            $("#user-"+id).remove();
        }

    }
    request.send("id="+id);
}
function remove_box(id,name) {
    $("#user-name").text(name);
    $("#user-name-title").text(name);
    $("#remove-button").attr("onclick","remove("+id+")");
}
function edit(id,mail,name) {
    let perms = $('#user-perms-'+id).attr('perms');
    $("#save-button-2").attr("disabled", true);
    $("#mail2").val(mail);
    $("#name2").val(name);
    $("#edit-user-id").val(id);
    if (perms.match("^[01]+$")) {
        $("#perms").val(perms);
    } else {
        $("#perms").val("0000000");
    }
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
}
function edit_user(mail,name,perms) {
    var request = new XMLHttpRequest();
    request.open('POST', './functions/edit_user.php');
    request.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    request.onreadystatechange = function() {
        if (request.readyState == 4) {
            console.log(request.responseText);

            json = JSON.parse(request.responseText);
            if (json['status']=='succ') {
                // $("#editUser-message").hide();
                // $("#editUser-message").html('<span class="text-success"'+json['message'] + "</span><br />");
                let id=$("#edit-user-id").val()
                $('#user-perms-'+id).attr('perms',perms);
                $('#editUser').modal('hide');
            } else {
                $("#editUser-message").show();
                $("#editUser-message").html('<span class="text-danger"'+json['message'] + "</span><br />");
            }
        }

    }
    request.send("name="+mail+"&display_name="+name+"&perms="+perms);
}

function new_user(email,name,pass) {
    var request = new XMLHttpRequest();
    request.open('POST', './functions/new_user.php');
    request.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    request.onreadystatechange = function() {
        if (request.readyState == 4) {
            console.log(request.responseText);

            json=JSON.parse(request.responseText);
            if (json['status']=='succ') {
                // $("#info").html('<span class="text-success">'+json['message']+"</span><br />");
                // setTimeout(function(){ window.location.reload(); }, 500);

                // if editing remember to change in index.php
                let newuserrow = $("<tr>", {
                    id: "user-"+json['id'], 
                    html: `<td scope="row">${name}</td>
                    <td>${email}</td>
                    <td>
                        <font style="display:hidden" id="user-perms-${json['id']}" perms="0000000"></font>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Actions">
                        <button id="user-edit-${json['id']}" onclick="edit(${json['id']},'${email}','${email}')" class="btn btn-primary" data-toggle="modal" data-target="#editUser" >Edit</button>
                        <button onclick="remove_box(${json['id']},'${email}')" data-toggle="modal" data-target="#removeUser" class="btn btn-danger">Remove</button>
                        </div>
                    </td>
                    `
                });
                $('#users').append(newuserrow);
                $('#newUser').modal('hide');
            } else {
                $("#newUser-message").html('<span class="text-danger">'+json['message']+"</span><br />");
                $("#newUser-message").show();
            }
        }

    }
    request.send("name="+email+"&display_name="+name+"&pass="+pass);
}

// https://gist.github.com/endel/321925f6cafa25bbfbde
Number.prototype.pad = function(size) {
  var s = String(this);
  while (s.length < (size || 2)) {s = "0" + s;}
  return s;
}
$("#perm1").change(function(){
    if ($("#perm1").is(":checked")) {
        $("#perms").val((parseInt($("#perms").val())+1000000).pad(7));
    } else {
        $("#perms").val((parseInt($("#perms").val())-1000000).pad(7));
    }
    if ($("#name2").val()!=="") {
        $("#save-button-2").attr("disabled", false);
    }
});
$("#perm2").change(function(){
    if ($("#perm2").is(":checked")) {
        $("#perms").val((parseInt($("#perms").val())+100000).pad(7));
    } else {
        $("#perms").val((parseInt($("#perms").val())-100000).pad(7));
    }
    if ($("#name2").val()!=="") {
        $("#save-button-2").attr("disabled", false);
    }
});
$("#perm3").change(function(){
    if ($("#perm3").is(":checked")) {
        $("#perms").val((parseInt($("#perms").val())+10000).pad(7));
    } else {
        $("#perms").val((parseInt($("#perms").val())-10000).pad(7));
    }
    if ($("#name2").val()!=="") {
        $("#save-button-2").attr("disabled", false);
    }
});
$("#perm4").change(function(){
    if ($("#perm4").is(":checked")) {
        $("#perms").val((parseInt($("#perms").val())+1000).pad(7));
    } else {
        $("#perms").val((parseInt($("#perms").val())-1000).pad(7));
    }
    if ($("#name2").val()!=="") {
        $("#save-button-2").attr("disabled", false);
    }
});
$("#perm5").change(function(){
    if ($("#perm5").is(":checked")) {
        $("#perms").val((parseInt($("#perms").val())+100).pad(7));
    } else {
        $("#perms").val((parseInt($("#perms").val())-100).pad(7));
    }
    if ($("#name2").val()!=="") {
        $("#save-button-2").attr("disabled", false);
    }
});
$("#perm6").change(function(){
    if ($("#perm6").is(":checked")) {
        $("#perms").val((parseInt($("#perms").val())+10).pad(7));
    } else {
        $("#perms").val((parseInt($("#perms").val())-10).pad(7));
    }
    if ($("#name2").val()!=="") {
        $("#save-button-2").attr("disabled", false);
    }
});
$("#perm7").change(function(){
    if ($("#perm7").is(":checked")) {
        $("#perms").val((parseInt($("#perms").val())+1).pad(7));
    } else {
        $("#perms").val((parseInt($("#perms").val())-1).pad(7));
    }
    if ($("#name2").val()!=="") {
        $("#save-button-2").attr("disabled", false);
    }
});
$("#name2").on("keyup", function() {
    if ($("#name2").val()!=="") {
        $("#name2").addClass("is-valid");
        $("#name2").removeClass("is-invalid");
        if ($("#name2").val()!=="") {
            $("#save-button-2").attr("disabled", false);
        }
    } else {
        $("#name2").addClass("is-invalid");
        $("#name2").removeClass("is-valid");
        $("#save-button-2").attr("disabled", true);
    }
});
$("#email").on("keyup", function() {
    if ($("#email").val()!=="") {
        $("#email").addClass("is-valid");
        $("#email").removeClass("is-invalid");
        if ($("#email").val()!==""&&$("#name").val()!==""&&$("#pass1").val()!==""&&$("#pass2").val()!==""&&$("#pass1").val()==$("#pass2").val()) {
            $("#save-button").attr("disabled", false);
        }
    } else {
        $("#email").addClass("is-invalid");
        $("#email").removeClass("is-valid");
        $("#save-button").attr("disabled", true);
    }
});
$("#name").on("keyup", function() {
    if ($("#name").val()!=="") {
        $("#name").addClass("is-valid");
        $("#name").removeClass("is-invalid");
        if ($("#email").val()!==""&&$("#name").val()!==""&&$("#pass1").val()!==""&&$("#pass2").val()!==""&&$("#pass1").val()==$("#pass2").val()) {
            $("#save-button").attr("disabled", false);
        }
    } else {
        $("#name").addClass("is-invalid");
        $("#name").removeClass("is-valid");
        $("#save-button").attr("disabled", true);
    }
});
$("#pass1").on("keyup", function() {
    if ($("#pass1").val()!=="" && validatePassword($("#pass1").val())) {
        $("#pass1").addClass("is-valid");
        $("#pass1").removeClass("is-invalid");
        if ($("#email").val()!==""&&$("#name").val()!==""&&$("#pass1").val()!==""&&$("#pass2").val()!==""&&$("#pass1").val()==$("#pass2").val()) {
            $("#save-button").attr("disabled", false);
        }
    } else {
        $("#pass1").addClass("is-invalid");
        $("#pass1").removeClass("is-valid");
        $("#save-button").attr("disabled", true);
    }
});
$("#pass2").on("keyup", function() {
    if ($("#pass2").val()!==""&&$("#pass2").val()==$("#pass1").val() && validatePassword($("#pass2").val())) {
        $("#pass2").addClass("is-valid");
        $("#pass2").removeClass("is-invalid");
        if ($("#email").val()!==""&&$("#name").val()!==""&&$("#pass1").val()!==""&&$("#pass2").val()!==""&&$("#pass1").val()==$("#pass2").val()) {
            $("#save-button").attr("disabled", false);
        }
    } else {
        $("#pass2").addClass("is-invalid");
        $("#pass2").removeClass("is-valid");
        $("#save-button").attr("disabled", true);
    }
});

$(document).ready(function(){
    $("#nav-settings").trigger('click');
});