<?php
define('CREATE_EDIT_AND_DELETE_MODPACKS',   0b01000000); // 0
define('CREATE_EDIT_AND_DELETE_BUILDS',     0b00100000); // 1
define('PUBLISH_BUILDS_AND_MODPACKS',       0b00010000); // 2 includes recommended and latest builds
define('UPLOAD_MODS_AND_FILES',             0b00001000); // 3
define('EDIT_AND_DELETE_MODS_AND_FILES',    0b00000100); // 4
define('UPLOAD_AND_DELETE_MODLOADERS',      0b00000010); // 5
define('ADD_AND_DELETE_CLIENTS',            0b00000001); // 6

// the permissions format used previously is the reverse of what is commonly used.
// ie. the first permission node 'create edit and delete modpacks', should be 0b00000001, and not 0b10000000.

// todo: make this also handle setting permissions. currently it just parses/gets.

require_once('sanitize.php');

final class Permissions {
    public readonly bool $priv;
    public readonly int $perms; 

    function __construct(string|int $perms, bool $priv) {
        $this->priv = $priv;
        if ($priv===true) {
            $this->perms = 0b1111111;
        } else {
            if (is_string($perms)) {
                assert(bindec($perms)>=0 && bindec($perms)<=255);
                $this->perms = bindec($perms);
            } elseif (is_int($perms)) {
                assert($perms>=0 && $perms<=255);
                $this->perms = $perms;
            }
        }
    }

    public function privileged() {
        return $this->priv === true;
    }

    public function modpack_create() {
        return ($this->perms & CREATE_EDIT_AND_DELETE_MODPACKS) !== 0;
    }
    public function modpack_edit() {
        return ($this->perms & CREATE_EDIT_AND_DELETE_MODPACKS) !== 0;
    }
    public function modpack_delete() {
        return ($this->perms & CREATE_EDIT_AND_DELETE_MODPACKS) !== 0;
    }
    public function modpack_publish() {
        return ($this->perms & PUBLISH_BUILDS_AND_MODPACKS) !== 0;
    }
    public function modpack_manage_clients() {
        return ($this->perms & PUBLISH_BUILDS_AND_MODPACKS) !== 0;
    }

    public function build_create() {
        return ($this->perms & CREATE_EDIT_AND_DELETE_BUILDS) !== 0;
    }
    public function build_edit() {
        return ($this->perms & CREATE_EDIT_AND_DELETE_BUILDS) !== 0;
    }
    public function build_delete() {
        return ($this->perms & CREATE_EDIT_AND_DELETE_BUILDS) !== 0;
    }
    public function build_publish() {
        return ($this->perms & PUBLISH_BUILDS_AND_MODPACKS) !== 0;
    }
    public function build_manage_clients() {
        return ($this->perms & PUBLISH_BUILDS_AND_MODPACKS) !== 0;
    }

    public function mods_upload() {
        return ($this->perms & UPLOAD_MODS_AND_FILES) !== 0;
    }
    public function mods_edit() {
        return ($this->perms & EDIT_AND_DELETE_MODS_AND_FILES) !== 0;
    }
    public function mods_delete() {
        return ($this->perms & EDIT_AND_DELETE_MODS_AND_FILES) !== 0;
    }

    public function files_upload() {
        return ($this->perms & UPLOAD_MODS_AND_FILES) !== 0;
    }
    public function files_edit() {
        return ($this->perms & EDIT_AND_DELETE_MODS_AND_FILES) !== 0;
    }
    public function files_delete() {
        return ($this->perms & EDIT_AND_DELETE_MODS_AND_FILES) !== 0;
    }

    public function modloaders_upload() {
        return ($this->perms & UPLOAD_AND_DELETE_MODLOADERS) !== 0;
    }
    public function modloaders_delete() {
        return ($this->perms & UPLOAD_AND_DELETE_MODLOADERS) !== 0;
    }

    public function clients_add() {
        return ($this->perms & ADD_AND_DELETE_CLIENTS) !== 0;
    }
    public function clients_delete() {
        return ($this->perms & ADD_AND_DELETE_CLIENTS) !== 0;
    }
}
