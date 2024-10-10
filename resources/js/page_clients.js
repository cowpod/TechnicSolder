(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
        form.addEventListener('submit', function(event) {
            if (form.checkValidity() === false) {
                event.preventDefault();
                event.stopPropagation();
            }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
function remove_box(id,name) {
    $("#mod-name-title").text(name);
    $("#mod-name").text(name);
    $("#remove-button").attr("onclick","remove("+id+")");
}
function remove(id) {
    var request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        $("#mod-row-"+id).remove();
    }
    request.open("GET", "./functions/delete-client.php?id="+id);
    request.send();
}

$(document).ready(function(){
    $("#nav-settings").trigger('click');
});