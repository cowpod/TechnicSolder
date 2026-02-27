$("#pn").on("keyup", function(){
    var slug = slugify($(this).val());
    console.log(slug);
    $("#slug").val(slug);
});
                      
$(document).ready(function(){
    $("#nav-mods").trigger('click')
    $("#author-input").on("keyup", function() {
        $("#author-save").html("Set all").attr("disabled", false)
        $("#author-input").removeClass("is-invalid").removeClass("is-valid")
    });
    $("#link-input").on("keyup", function() {
        $("#link-save").html("Set all").attr("disabled", false)
        $("#link-input").removeClass("is-invalid").removeClass("is-valid")
    });
    $("#donlink-input").on("keyup", function() {
        $("#donlink-save").html("Set all").attr("disabled", false)
        $("#donlink-input").removeClass("is-invalid").removeClass("is-valid")
    })
})

function authorsave() {
    $("#author-save").html("<em class='fas fa-cog fa-spin'></em>").attr("disabled",true);

    var request = new XMLHttpRequest();
    request.open('POST', './functions/edit-mod.php');
    request.onreadystatechange = function() {
        if (request.readyState == 4 && request.status == 200) {
            console.log(request.responseText)
            let json = JSON.parse(request.responseText)
            switch (json.status) {
            case "succ":
            case "info":
            case "warning":
                $('#author-input').removeClass('is-invalid')
                $('#author-input').addClass('is-valid')
                break
            case "error":
            default:
                $('#author-input').removeClass('is-valid')
                $('#author-input').addClass('is-invalid')
                break
            }
            $("#author-save").html("Set all").attr("disabled", false)
        }
    }

    let formData = new FormData()
    formData.set('id', $('#modv-id').val())
    formData.set('author', $("#author-input").val())
    request.send(formData)
}
function linksave() {
    $("#link-save").html("<em class='fas fa-cog fa-spin'></em>").attr("disabled",true);

    var request = new XMLHttpRequest();
    request.open('POST', './functions/edit-mod.php');
    request.onreadystatechange = function() {
        if (request.readyState == 4 && request.status == 200) {
            console.log(request.responseText)
            let json = JSON.parse(request.responseText)
            switch (json.status) {
            case "succ":
            case "info":
            case "warning":
                $('#link-input').removeClass('is-invalid')
                $('#link-input').addClass('is-valid')
                break
            case "error":
            default:
                $('#link-input').removeClass('is-valid')
                $('#link-input').addClass('is-invalid')
                break
            }
            $("#link-save").html("Set all").attr("disabled", false)
        }
    }

    let formData = new FormData()
    formData.set('id', $('#modv-id').val())
    formData.set('link', $("#link-input").val())
    request.send(formData)
}
function donlinksave() {
    $("#donlink-save").html("<em class='fas fa-cog fa-spin'></em>").attr("disabled",true);

    var request = new XMLHttpRequest();
    request.open('POST', './functions/edit-mod.php');
    request.onreadystatechange = function() {
        if (request.readyState == 4 && request.status == 200) {
            console.log(request.responseText)
            let json = JSON.parse(request.responseText)
            switch (json.status) {
            case "succ":
            case "info":
            case "warning":
                $('#donlink-input').removeClass('is-invalid')
                $('#donlink-input').addClass('is-valid')
                break
            case "error":
            default:
                $('#donlink-input').removeClass('is-valid')
                $('#donlink-input').addClass('is-invalid')
                break
            }
            $("#donlink-save").html("Set all").attr("disabled", false)
        }
    }

    let formData = new FormData()
    formData.set('id', $('#modv-id').val())
    formData.set('donlink', $("#donlink-input").val())
    request.send(formData)
}

$('#modvform').on("submit", function(event) {
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