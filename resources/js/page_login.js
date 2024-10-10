$(".navbar-brand").click(function(){
    $("#sidenav").toggleClass("sidenavexpand");
});
$("#dark").click(function(){
    if ($("#dark").is(":checked")){
        if (window.location.href.indexOf("?light") > -1 || window.location.href.indexOf("&light") > -1) {
            if (window.location.href.indexOf("?light") > -1) {
                window.location.href = window.location.href.replace("?light","?dark");
            } else {
                window.location.href = window.location.href.replace("&light","&dark");
            }
        } else {
            if (window.location.href.indexOf("?") > -1) {
                window.location.href = window.location.href+"&dark";
            } else {
                window.location.href = window.location.href+"?dark";
            }
        }
    } else {
        if (window.location.href.indexOf("?dark") > -1 || window.location.href.indexOf("&dark") > -1) {
            if (window.location.href.indexOf("?dark") > -1) {
                window.location.href = window.location.href.replace("?dark","?light");
            } else {
                window.location.href = window.location.href.replace("&dark","&light");
            }
        } else {
            if (window.location.href.indexOf("?") > -1) {
                window.location.href = window.location.href+"&light";
            } else {
                window.location.href = window.location.href+"?light";
            }
        }
    }
});