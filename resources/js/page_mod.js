function remove_box(id,version,name) {
    $("#mod-name-title").text(name+" "+version);
    $("#mod-name").text(name+" "+version);
    $("#remove-button").attr("onclick","remove("+id+",'"+name+"',false)");
    $("#remove-button").text('Delete')
    $('#rm-message').empty()
}

function remove(id,name,force) {
    var request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            console.log(this.response);
            response = JSON.parse(this.response);
            if (response['status']=='succ') {
                console.log('success!');
                $("#mod-row-"+id).remove();
                if ($("#table-mods tr").length==0) {
                    window.location = "./lib-mods";
                }
                $("#removeMod").modal('hide')
            } else {
                $('#rm-message').html(`<br/><p><b>${name} is in use!</b></p>`);
                $("#remove-button").attr("onclick","remove("+id+",'"+name+"',true)")
                $("#remove-button").text('Force delete')
            }
        }
    }
    request.open("GET", "./functions/delete-mod.php?id="+id+'&force='+force);
    request.send();
}

$('#modform').on("submit", function(event) {
    var btn = event.originalEvent.submitter;
    event.preventDefault()

    $('#savebtn').prop('disabled',true)
    $('#saveclosebtn').prop('disabled',true)


    var request = new XMLHttpRequest();
    request.open('POST', './functions/edit-mod.php');
    request.onreadystatechange = function() {
        if (request.readyState == 4 && request.status == 200) {
            console.log(request.responseText)
            let json = JSON.parse(request.responseText)
            $('#errortext').text(json.message)
            $('#errortext').removeClass("text-danger text-warning text-info text-success")

            switch (json.status) {
            case "succ":
                $('#errortext').addClass("text-success")
                break
            case "info":
                $('#errortext').addClass("text-info")
                break
            case "warning":
                $('#errortext').addClass("text-warning")
                break
            case "error":
                $('#errortext').addClass("text-danger")
                break
            default:
                console.log("Got invalid status", json.status)
                console.log("Full response:", request.responseText)
                break
            }

            $('#savebtn').prop('disabled',false)
            $('#saveclosebtn').prop('disabled',false)

            if ($(btn).attr('id') == 'saveclosebtn') {
                $('#backbtn').click()
            }
        }
    }
    let formData = new FormData(this)
    request.send(formData)
})

$(document).ready(function(){
    $("#nav-mods").trigger('click');
});