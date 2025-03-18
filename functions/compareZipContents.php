<?php
function compareZipContents(string $filepath1, string $filepath2): bool {
    if (!file_exists($filepath1) && !file_exists($filepath2)) {
        return true; // true if neither exist
    }
    elseif(!file_exists($filepath1) || !file_exists($filepath2)) {
        return false; // false if only one exists
    }

    $contents1 = [];
    $zip = new ZipArchive();
    if ($zip->open($filepath1) === TRUE) {
        $filecount1=$zip->numFiles;
        for ($i=0; $i<$zip->numFiles; $i++) {
            $fileInfo = $zip->statIndex($i);
            array_push($contents1, ['name'=>$fileInfo['name'], 'crc'=>$fileInfo['crc'],'size'=>$fileInfo['size']]);
        }
        $zip->close();
    } else {
        error_log("compareZipContents(): Couldn't open zip file 1: ".$filepath1);
        return FALSE;
    }

    $contents2 = [];
    $zip = new ZipArchive();
    if ($zip->open($filepath2) === TRUE) {
        $filecount2=$zip->numFiles;
        for ($i=0; $i<$zip->numFiles; $i++) {
            $fileInfo = $zip->statIndex($i);
            array_push($contents2, ['name'=>$fileInfo['name'], 'crc'=>$fileInfo['crc'],'size'=>$fileInfo['size']]);
        }
        $zip->close();
    } else {
        error_log("compareZipContents(): Couldn't open zip file 2: ".$filepath2);
        return FALSE;
    }

    // disregards order
    return ($contents1==$contents2 && $filecount1==$filecount2);
}
?>