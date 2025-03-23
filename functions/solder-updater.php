<?php
// NOTE THIS USES EXEC CALLS
// if modifying this be VERY sure of what you're doing.

define('UP_TO_DATE', "UP TO DATE");
define('OUTDATED', "OUTDATED");
define('NO_GIT', "GIT NOT FOUND");
define('UPDATE_ERROR_FETCH', "GIT FETCH ERROR");
define('UPDATE_ERROR_BAD_REPO', "GIT BAD REPO");
define('UPDATE_ERROR_GET_BRANCH', "GIT GET BRANCH ERROR");
define('UPDATE_ERROR_PULL', "GIT PULL ERROR");
define('UPDATE_ERROR_MERGE', "GIT MERGE ERROR");
define('UPDATE_SUCCESS', "GIT UPDATE SUCCESS");
define('UPDATE_ERROR_DECODE',"GIT DECODE ERROR");
define('UPDATE_ERROR', "GIT UPDATE ERROR");
define('UPDATES_DISABLED', "GIT UPDATES ARE DISABLED");

final class Updater {
    private $config = null;
    private $git_return = null;
    private $git_result = null;
    private $repo_path = null;
    private $version = null;

    function __construct($repoPathArg=null, $configArg=null, $versionDataArg=null) {
        /*
            During construction, we fetch all local resources.
            We don't get remote resources until related functions are executed.
        */
        if (empty($configArg)) {
            $config_file = is_file('../functions/configuration.php') ? '../functions/configuration.php' : (
                is_file('./functions/configuration.php') ? './functions/configuration.php' : (
                    is_file('./configuration.php') ? './configuration.php' : false));
            if ($config_file === false) {
                error_log("solder-updater.php: couldn't locate configuration.php");
                die("Couldn't locate configuration.php");
            }
            require_once($config_file);
            if ($this->config === null) {
                $this->config = new Config();
            }
        } else {
            $this->config = $configArg;
        }

        $this->git_result = [];
        $this->git_return = [];

        if (empty($repoPathArg)) {
            $repopath = is_dir(__DIR__.'/api') ? __DIR__ : (
                is_dir(__DIR__.'/../api') ? dirname(__DIR__) : false);
            if ($repopath === false) {
                error_log("solder-updater.php: couldn't locate base directory");
                die("Couldn't locate base directory");
            }
            error_log($repopath);
            error_log(__DIR__ );
            $this->repo_path = $repopath;
        } else {
            $this->repo_path = $repoPathArg;
        }

        if (empty($versionJsonArg)) {
            $version_file = is_file('../api/version.json') ? '../api/version.json' : (
                is_file('./api/version.json') ? './api/version.json' : (
                    is_file('./version.json') ? './version.json' : false));
            if ($version_file === false) {
                error_log("solder-updater.php: Couldn't locate version.json");
                die("Couldn't locate version.json");
            }
            $version_data = @json_decode(file_get_contents($version_file),true);
            if (!$version_data) {
                error_log("solder-updater.php: Couldn't decode version.json");
                die("Couldn't get version.json");
            }
            if ($version_data['version'][0]==='v') {
                 $version_data['version'] = substr($version_data['version'], 1);
            }
            $this->version = $version_data;
        } else {
            $this->version = $versionDataArg;
        }
    }

    private function json($status, $message, $data=null) {
        $prnt = ["status"=>$status,"message"=>$message];
        if (is_array($data)) {
            $prnt = array_merge($prnt, $data);
        }
        return json_encode($prnt,JSON_UNESCAPED_SLASHES);
    }

    private function check_git() {
        if (`which git` || `where git`) { // this is slow. so we do it here instead of at construction.
            // Ensure it's a valid repo
            if (!is_dir($this->repo_path . "/.git")) {
                return UPDATE_ERROR_BAD_REPO;
            }

            // get branch
            exec("cd $this->repo_path && git branch --show-current 2>&1", $this->git_result, $this->git_return);
            if ($this->git_return !== 0) {
                return UPDATE_ERROR_GET_BRANCH;
            }
            // ensure git branch matches release channel from api/version.json
            if (trim(strtolower($this->version['stream'])) !== trim(strtolower(implode(' ', $this->git_result)))) {
                return UPDATE_ERROR_MISMATCHED_CHANNEL;
            } else {
                $this->git_result=[]; // clear result from checking branch
            }

            // Fetch the latest changes from remote
            exec("cd $this->repo_path && git fetch origin 2>&1", $this->git_result, $this->git_return);
            if ($this->git_return !== 0) {
                return UPDATE_ERROR_FETCH;
            }

            // Check if local is behind remote
            exec("cd $this->repo_path && git rev-list --left-right --count origin/master...HEAD | awk '{if ($1 > 0) print \"true\"; else print \"false\"}' 2>&1", $this->git_result, $this->git_return);
            if ($this->git_return !== 0) {
                return UPDATE_ERROR;
            }

            $commitsBehind = (int)trim($this->git_result[0]);

            if ($commitsBehind > 0) {
                return OUTDATED;
            } else {
                return UP_TO_DATE;
            }
        } else {
            return NO_GIT;
        }
    }

    public function update() {
        if ($this->config->get('enable_self_updater')!=='on') { // if updates are disabled
            return UPDATES_DISABLED;
        }
        if ($this->check_git() !== OUTDATED) { // if we can't first check for updates
            return UPDATE_ERROR;
        }
        exec("cd $this->repo_path && git pull 2>&1", $this->git_result, $this->git_return);
        if ($this->git_return !== 0) {
            // attempt recovery
            // return UPDATE_ERROR_PULL;
            error_log("solder-updater.php: update(): hard resetting and pulling again");
            exec("cd $this->repo_path && git reset --hard HEAD 2>&1 && git pull 2>&1", $this->git_result, $this->git_return);
            if ($this->git_return !== 0) {
                return UPDATE_ERROR_MERGE;
            }
        }
        
        return UPDATE_SUCCESS;
    }
    

    public function check() {        
        if ($this->config->get('enable_self_updater')!=='on') { // if updates are disabled
            return UPDATES_DISABLED;
        }
        // wipe previous logs
        $this->git_result=[];
        $this->git_return=[];

        return $this->check_git();
    }

    public function logs() {
        return $this->git_result;
    }
}

if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    session_start();
    if (empty($_SESSION['user'])) {
        die('{"status":"error","message":"Unauthorized request or login session has expired!"}');
    }
    if (empty($_SESSION['privileged']) || !$_SESSION['privileged']) {
        die ('{"status"=>"error","message"=>"Insufficient permission!"}');
    }

    $updater = new Updater();
    if (isset($_POST['install-updates'])) {
        $update_status = $updater->update();
        if ($update_status === UPDATE_SUCCESS) {
            die('{"status":"succ","message":"'.$update_status.'","logs":"'.implode("<br/>",$updater->logs()).'"}');
        } else {
            die('{"status":"error","message":"'.$update_status.'","logs":"'.implode("<br/>",$updater->logs()).'"}');
        }
    }
}

?>
