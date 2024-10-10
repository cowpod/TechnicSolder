function structurize(paths) {
    var items = [];
    for(var i = 0, l = paths.length; i < l; i++) {
        var path = paths[i];
        var name = path[0];
        var rest = path.slice(1);
        var item = null;
        for(var j = 0, m = items.length; j < m; j++) {
            if (items[j].name === name) {
                item = items[j];
                break;
            }
        }
        if (item === null) {
            item = {name: name, children: []};
            items.push(item);
        }
        if (rest.length > 0) {
            item.children.push(rest);
        }
    }
    for(i = 0, l = items.length; i < l; i++) {
        item = items[i];
        item.children = structurize(item.children);
    }
    return items;
}

function stringify(items) {
    var lines = [];
    for(var i = 0, l = items.length; i < l; i++) {
        var item = items[i];
        lines.push(item.name);
        var subLines = stringify(item.children);
        for(var j = 0, m = subLines.length; j < m; j++) {
            lines.push("    " + subLines[j]);
        }
    }
    return lines;
}

$(document).ready(function(){
    $("#nav-mods").trigger('click');
    // var paths = [];
    // <?php
    // $zip = new ZipArchive;
    // if ($zip->open('./others/'.$file['filename'])===TRUE) {
    //     for ($i=0; $i<$zip->numFiles; $i++) {
    //         $fileInfo = $zip->getNameIndex($i);
    //         echo 'paths.push("' . $zip->getNameIndex($i) . '".replace(/\/+$/,\'\'));';
    //     }
    //     $zip->close();
    // }
    // ?>
    paths = paths.map(function(path) { return path.split('/'); });
    $("#files_ul").html(stringify(structurize(paths)).join("\n"));
    $("#loadingfiles").hide();
});