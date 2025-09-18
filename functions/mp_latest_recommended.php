<?php

/**
 * @return false|string
 */
function get_modpack_latest_recommended(Db $db, int $id): array
{
    assert($db->status());

    $modpackq = $db->query("SELECT latest,recommended FROM modpacks WHERE id = {$id}");

    if (empty($modpackq)) {
        return ["recommended" => null, "latest" => null];
    }

    return ["recommended" => $modpackq[0]['recommended'], "latest" => $modpackq[0]['latest']];
}
