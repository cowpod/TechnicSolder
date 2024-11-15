$("#pn").on("keyup", function(){
    var slug = slugify($(this).val());
    console.log(slug);
    $("#slug").val(slug);
});
                      
$(document).ready(function(){
    $("#nav-mods").trigger('click');
    $("#author-input").on("keyup",function(){
        $("#author-save").html("Set for all versions").attr("disabled",false);
    });
    $("#link-input").on("keyup",function(){
        $("#link-save").html("Set for all versions").attr("disabled",false);
    });
    $("#donlink-input").on("keyup",function(){
        $("#donlink-save").html("Set for all versions").attr("disabled",false);
    });

});
function authorsave() {
    $("#author-save").html("<em class='fas fa-cog fa-spin'></em>").attr("disabled",true);
    var request = new XMLHttpRequest();
    request.open('POST', './functions/authorsave.php');
    request.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    request.onreadystatechange = function() {
        if (request.readyState == 4) {
            $("#author-save").html("<em class='fas fa-check text-success'></em>");
        }
    }
    var value = encodeURIComponent($("#author-input").val());
    request.send("id=<?php echo $mod['name'] ?>&value="+value);
}
function linksave() {
    $("#link-save").html("<em class='fas fa-cog fa-spin'></em>").attr("disabled",true);
    var request = new XMLHttpRequest();
    request.open('POST', './functions/linksave.php');
    request.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    request.onreadystatechange = function() {
        if (request.readyState == 4) {
            $("#link-save").html("<em class='fas fa-check text-success'></em>");
        }
    }
    var value = encodeURIComponent($("#link-input").val());
    request.send("id=<?php echo $mod['name'] ?>&value="+value);
}
function donlinksave() {
    $("#donlink-save").html("<em class='fas fa-cog fa-spin'></em>").attr("disabled",true);
    var request = new XMLHttpRequest();

    request.open('POST', './functions/donlinksave.php');
    request.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    request.onreadystatechange = function() {
        if (request.readyState == 4) {
            $("#donlink-save").html("<em class='fas fa-check text-success'></em>");
        }
    }
    var value = encodeURIComponent($("#donlink-input").val());
    request.send("id=<?php echo $mod['name'] ?>&value="+value);
}