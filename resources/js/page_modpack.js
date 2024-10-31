// var builds = "<?php echo addslashes(json_encode($mpab)) ?>";
// var sbn = "<?php echo addslashes(json_encode($sbn)) ?>";
var bd = JSON.parse(builds);
var sbna = JSON.parse(sbn);
console.log(sbna);
$("#mplist").change(function() {
    $("#buildlist").children().each(function(){this.remove();});
    Object.keys(bd).forEach(function(element){

        if ($("#mplist").val() == bd[element]['mpid']) {
            $("#buildlist").append("<option value='"+bd[element]['id']+"'>"+bd[element]['mpname']+" - "+bd[element]['name']+"</option>")
        }
    });
});
$("#newbname").on("keyup",function(){
    if (sbna.indexOf($("#newbname").val())==false) {
        $("#newbname").addClass("is-invalid");
        $("#warn_newbname").show();
        $("#create1").prop("disabled",true);
        $("#create2").prop("disabled",true);
    } else {
        $("#newbname").removeClass("is-invalid");
        $("#warn_newbname").hide();
        $("#create1").prop("disabled",false);
        $("#create2").prop("disabled",false);
    }
});
$("#newname").on("keyup",function(){
    if (sbna.indexOf($("#newname").val())==false) {
        $("#newname").addClass("is-invalid");
        $("#warn_newname").show();
        $("#copybutton").prop("disabled",true);
    } else {
        $("#newname").removeClass("is-invalid");
        $("#warn_newname").hide();
        $("#copybutton").prop("disabled",false);
    }
});

                        function edit(id) {
    window.location = "./build?id="+id;
}
function remove_box(id,name) {
    $("#build-title").text(name);
    $("#build-text").text(name);
    $("#remove-button").attr("onclick","remove("+id+")");
}
function set_public(id) {
    $("#cog-"+id).show();
    var request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            response = JSON.parse(this.response);
            $("#cog-"+id).hide();
            if (response['status']=="succ") {
                // if (response['latest']==id) {
                // }
                $("#pub-"+id).hide();
                if (response['recommended']==id) {
                    $("#recd-"+id).show();
                } else {
                    $("#rec-"+id).show();
                }
            } else {
                console.log(response);
            }
        }
    };
    request.open("GET", "./functions/set-public-build.php?id="+id+"&ispublic=1");
    request.send();

}
function remove(id) {
    $("#cog-"+id).show();
    var request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            response = JSON.parse(this.response);
            $("#cog-"+id).hide();
            $("#b-"+id).hide();
            if ($("#b-"+id).attr("rec")=="true") {
                $("#rec-v-li").hide();
                $("#rec-mc-li").hide();
            }
            console.log(response);
            if (response['exists']==true) {
                $("#latest-v-li").show();
                $("#latest-mc-li").show();
                $("#latest-name").text(response['name']);
                $("#latest-mc").text(response['mc']);
                if (response['name']==null) {
                    $("#latest-v-li").hide();
                    $("#latest-mc-li").hide();
                }
            } else {
                $("#latest-v-li").hide();
                $("#latest-mc-li").hide();
            }

        }
    };
    // request.open("GET", "./functions/delete-build.php?id="+id+"&pack=<?php echo $_GET['id'] ?>");
    request.open("GET", "./functions/delete-build.php?id="+id+"&pack="+getQueryVariable('id'));
    request.send();
}
function set_recommended(id) {
    $("#cog-"+id).show();
    var request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            response = JSON.parse(this.response);

            $("[id^='recd-']").each(function() {
                let otherid = $(this).attr("id").substring(5);
                if (otherid==id) { 
                    return;
                } else {
                    // hide all recd
                    $(this).hide();
                    // show all rec
                    $("#rec-"+otherid).show();
                }
            });
            $("#recd-"+id).show(); // recommended
            $("#rec-"+id).hide(); // reccommend
            $("#rec-v-li").show();
            $("#rec-mc-li").show();
            $("#cog-"+id).hide();
            // $("#rec-"+id).attr('disabled', true);
            var bid = $("#rec-disabled").attr('bid');
            // $("#rec-disabled").attr('disabled', false);
            // $("#rec-disabled").attr('id', 'rec-'+bid);
            // $("#rec-"+id).attr('id', 'rec-disabled');
            $("#rec-name").text(response['name']);
            $("#rec-mc").text(response['mc']);
            $("#table-builds tr").attr('rec','false');
            $("#b-"+id).attr('rec','true');
        }
    };
    request.open("GET", "./functions/set-recommended.php?id="+id);
    request.send();
}