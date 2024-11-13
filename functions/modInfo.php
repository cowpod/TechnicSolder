<?php
define('FABRIC_INFO_PATH', 'fabric.mod.json');
define('FORGE_INFO_PATH', 'META-INF/mods.toml');
define('NEOFORGE_INFO_PATH', 'META-INF/neoforge.mods.toml');
define('FORGE_OLD_INFO_PATH', 'mcmod.info');
require('toml.php');
require('interval_range_utils.php');

class modInfo {
    private $warn=[];

    private function getModTypes(string $filePath): array {
        // returns a dictionary ['fabric'=>bool,'forge'=>bool,'forge_old'=>bool] 
        // representing each detected mod type
        // yes, there can be multiple types of the same mod in a file.

        // todo: or even multiple mods in one file, but we're not handling that.

        // todo: check finfo to ensure it's actually a zip

        // error_log("getModTypes()");
        assert (file_exists($filePath));

        $has_fabric=FALSE;
        $has_neoforge=FALSE;
        $has_forge=FALSE;
        $has_forge_old=FALSE;

        $zip = new ZipArchive;

        if ($zip->open($filePath)===TRUE) {
            // statName returns Array or FALSE.
            $has_fabric = $zip->statName(FABRIC_INFO_PATH)!==FALSE;
            $has_neoforge = $zip->statName(NEOFORGE_INFO_PATH)!==FALSE;
            $has_forge = $zip->statName(FORGE_INFO_PATH)!==FALSE; // MC 1.13+
            $has_forge_old = $zip->statName(FORGE_OLD_INFO_PATH)!==FALSE; // MC 1.12-
            $zip->close();
        }

        return ['fabric'=> $has_fabric, 'neoforge'=>$has_neoforge, 'forge'=> $has_forge, 'forge_old'=> $has_forge_old];
    }

    private function getModInfos(array $modTypes, string $filePath, string $fileName): array {
        /*
        We don't support multiple mods in a file. 
        TODO: support multiple mods in one file.

        Returns a dictionary of 
        [
        'forge' => [info] or null, for forge
        'forge_old' => [info] or null, for old forge
        'fabric' => [info] or null, for fabric
        ]

        Format is similar to that of 1.12 and below forge mcmod.info.
        Except we don't support multiple mods in a file. 
        // (Note the following ISN'T a nested dictionary in an array.)

        */
        // global $this->warn;

        // only make a copy of this object. we don't have const or final :c
        $mcmod_orig = [
            'modid'         =>null, // mandatory 
            'version'       =>null, // mandatory
            'name'          =>null,
            'url'           =>null,
            'credits'       =>null,
            'authors'       =>null,
            'description'   =>null,
            'mcversion'     =>null, // pulled from dependencies, exact or interval notation
            'dependencies'  =>[],   // pulled from dependencies, ['dep mod id']
            'loaderversion' =>null, 
            'loadertype'    =>null, 
            'license'       =>null
        ];

        $mod_info = ['forge'=>null, 'forge_old'=>null, 'fabric'=>null];

        $zip = new ZipArchive();
        if ($zip->open($filePath) === FALSE) {
            error_log ('{"status": "error", "message": "Could not open JAR file as ZIP"}');
            die ('{"status": "error", "message": "Could not open JAR file as ZIP"}');
        }

        // error_log('modtypes: '.json_encode($modTypes));

        foreach (array_keys($modTypes) as $modtype) {

            if ($modtype=='neoforge' && $modTypes['neoforge']===TRUE) { // almost identical to forge
                $raw = $zip->getFromName(NEOFORGE_INFO_PATH);
                if ($raw === FALSE) {
                    error_log  ('{"status": "error", "message": "Neoforge: Could not access info file from Neoforge mod."}');
                    // die ('{"status": "error", "message": "Could not access info file from Neoforge mod."}');
                    $mod_info['neoforge']=null;
                    array_push($this->warn, 'Neoforge: could not access info file');
                    continue;
                }

                $toml = new Toml;
                $parsed = $toml->parse($raw);
                $mod_info['neoforge']=$mcmod_orig;

                // there can be multiple mods entries, we are just getting the first.
                if (!empty($parsed['mods'])) {
                    $maxcount=sizeof($parsed['mods']);
                    foreach ($parsed['mods'] as $mod) {
                        $maxcount-=1;
                        if (empty($mod['modId'])) {
                            error_log ('{"status": "error", "message": "Neoforge: Missing modId!"}');
                            // die ('{"status": "error", "message": "Missing modId!"}');
                            if ($maxcount==0) {
                                array_push($this->warn, 'Neoforge: missing modId');
                                $mod_info['neoforge']=null;
                            }
                            continue; // maybe next one?
                        } else {
                            $mod_info['neoforge']['modid'] = strtolower($mod['modId']);
                        }
                        if (empty($mod['version']) || $mod['version']=='${file.jarVersion}') {
                            $matches=[];
                            $patchedversion='';
                            if (!empty($fileName) && preg_match("/-([0-9a-z\-\.]+)$/", str_replace('.jar','',$fileName), $matches)) {
                                if (!empty($matches[1])) {
                                    $mod_info['neoforge']['version'] = $matches[1];
                                } else {
                                    array_push($this->warn, 'Missing version!');
                                    $mod_info['neoforge']['version'] = '';
                                }
                            } else {
                                array_push($this->warn, 'Missing version!');
                                $mod_info['neoforge']['version'] = '';
                            }
                        } else {
                            $mod_info['neoforge']['version'] = $mod['version'];
                        }
                        if (!empty($mod['displayName']))
                            $mod_info['neoforge']['name'] = $mod['displayName'];
                        if (!empty($mod['displayURL']))
                            $mod_info['neoforge']['url'] = $mod['displayURL'];
                        if (!empty($mod['credits']))
                            $mod_info['neoforge']['credits'] = $mod['credits'];
                        if (!empty($mod['authors']))
                            $mod_info['neoforge']['authors'] = $mod['authors'];
                        if (!empty($mod['description'])) {
                            $mod_info['neoforge']['description'] = $mod['description'];
                        }
                        break;
                    }
                }

                // handle dependencies and get mcversion, sometimes there can be none.
                if (!empty($parsed['dependencies']) && !empty($parsed['dependencies'][$mod_info['neoforge']['modid']])) {
                    // each dependency is an indexed array entry.
                    foreach ($parsed['dependencies'][$mod_info['neoforge']['modid']] as $dep) {
                        if (empty($dep['modId']))
                            continue;
                        if (strtolower($dep['modId'])=='minecraft') {
                            $mod_info['neoforge']['mcversion'] = $dep['versionRange'];
                        }
                        array_push($mod_info['neoforge']['dependencies'], strtolower($dep['modId']));
                    }
                }

                if (empty($mod_info['neoforge']['mcversion'])) {
                    array_push($this->warn, 'Missing mcversion!');
                }

                $mod_info['neoforge']['loadertype'] = 'neoforge';
                if (empty($parsed['loaderVersion'])) {
                    error_log ('{"status": "error", "message": "Neoforge: Missing loaderVersion!"}');
                    // die ('{"status": "error", "message": "Missing loaderVersion!"}');
                    $mod_info['neoforge']=null;
                    array_push($this->warn, 'Neoforge: Missing loaderVersion');
                    continue;
                } else {
                    $mod_info['neoforge']['loaderversion'] = $parsed['loaderVersion'];
                }
                if (!empty($parsed['license']))
                    $mod_info['neoforge']['license'] = $parsed['license'];

                // error_log("modTypes- Neoforge added");
            }

            if ($modtype=='forge' && $modTypes['forge']===TRUE) { // almostidentical to neoforge
                $raw = $zip->getFromName(FORGE_INFO_PATH);
                if ($raw === FALSE) {
                    error_log  ('{"status": "error", "message": "Forge: Could not access info file from Forge mod."}');
                    // die ('{"status": "error", "message": "Could not access info file from Forge mod."}');
                    $mod_info['forge']=null;
                    array_push($this->warn, 'Forge: could not access info file');
                    continue;
                }

                $toml = new Toml;
                $parsed = $toml->parse($raw);
                $mod_info['forge']=$mcmod_orig;

                if (!empty($parsed['mods'])) {
                // there can be multiple mods entries, we are just getting the first.
                    $maxcount=sizeof($parsed['mods']);
                    foreach ($parsed['mods'] as $mod) {
                        $maxcount-=1;
                        if (empty($mod['modId'])) {
                            error_log ('{"status": "error", "message": "Forge: Missing modId!"}');
                            // die ('{"status": "error", "message": "Missing modId!"}');
                            if ($maxcount==0) {
                                $mod_info['forge']=null;
                                array_push($this->warn, 'Forge: missing modId');
                            }
                            continue; // maybe next one?
                        } else {
                            $mod_info['forge']['modid'] = strtolower($mod['modId']);
                        }
                        if (empty($mod['version']) || $mod['version']=='${file.jarVersion}') {
                            $matches=[];
                            $patchedversion='';
                            if (!empty($fileName) && preg_match("/-([0-9a-z\-\.]+)$/", str_replace('.jar','',$fileName), $matches)) {
                                if (!empty($matches[1])) {
                                    $mod_info['forge']['version'] = $matches[1];
                                } else {
                                    array_push($this->warn, 'Missing version!');
                                    $mod_info['forge']['version'] = '';
                                }
                            } else {
                                array_push($this->warn, 'Missing version!');
                                $mod_info['forge']['version'] = '';
                            }
                        } else {
                            $mod_info['forge']['version'] = $mod['version'];
                        }
                        if (!empty($mod['displayName']))
                            $mod_info['forge']['name'] = $mod['displayName'];
                        if (!empty($mod['displayURL']))
                            $mod_info['forge']['url'] = $mod['displayURL'];
                        if (!empty($mod['credits']))
                            $mod_info['forge']['credits'] = $mod['credits'];
                        if (!empty($mod['authors']))
                            $mod_info['forge']['authors'] = $mod['authors'];
                        if (!empty($mod['description']))
                            $mod_info['forge']['description'] = $mod['description'];
                        break;
                    }
                }

                // handle dependencies and get mcversion, sometimes there can be none.
                if (!empty($parsed['dependencies']) && !empty($parsed['dependencies'][$mod_info['forge']['modid']])) {
                    // each dependency is an indexed array entry.
                    foreach ($parsed['dependencies'][$mod_info['forge']['modid']] as $dep) {
                        if (empty($dep['modId']))
                            continue;
                        if (strtolower($dep['modId'])=='minecraft') {
                            $mod_info['forge']['mcversion'] = $dep['versionRange'];
                        }
                        array_push($mod_info['forge']['dependencies'], strtolower($dep['modId']));
                    }
                }

                if (empty($mod_info['forge']['mcversion'])) {
                    array_push($this->warn, 'Missing mcversion!');
                }

                $mod_info['forge']['loadertype'] = 'forge';
                if (empty($parsed['loaderVersion'])) {
                    error_log ('{"status": "error", "message": "Forge: Missing loaderVersion!"}');
                    // die ('{"status": "error", "message": "Missing loaderVersion!"}');
                    $mod_info['forge']=null;
                    array_push($this->warn, 'Forge: missing loaderVersion');
                    continue;
                } else {
                    $mod_info['forge']['loaderversion'] = $parsed['loaderVersion'];
                }
                if (!empty($parsed['license']))
                    $mod_info['forge']['license'] = $parsed['license'];
                // error_log("modTypes- Forge added");
            }

            if ($modtype=='forge_old' && $modTypes['forge_old']===TRUE) {

                $raw = $zip->getFromName(FORGE_OLD_INFO_PATH);
                if ($raw === FALSE) {
                    error_log ('{"status": "error", "message": "Forge_Old: Could not access info file from Old Forge mod."}');
                    // die ('{"status": "error", "message": "Could not access info file from Old Forge mod."}');
                    $mod_info['forge_old']=null;
                    array_push($this->warn, 'Forge Old: Could not access info file');
                    continue;
                }

                $mod_info['forge_old']=$mcmod_orig;

                // dictionary is nested in an array
                $parsed = json_decode(preg_replace('/\r|\n/', '', trim($raw)), true)[0];
                if (empty($parsed['modid'])) {
                    error_log ('{"status": "error", "message": "Forge_Old: Missing modid!"}');
                    // die ('{"status": "error", "message": "Missing modid!"}');
                    array_push($this->warn, 'Forge Old: missing modId');
                    $mod_info['forge_old']=null;
                    continue;
                } else {
                    $mod_info['forge_old']['modid'] = strtolower($parsed['modid']);
                }
                if (empty($parsed['version']) || $parsed['version']=='${file.jarVersion}') {
                    // array_push($this->warn, 'Missing version!');
                    $matches=[];
                    $patchedversion='';
                    if (preg_match("/-([0-9a-z\-\.]+)$/", str_replace('.jar','',$fileName), $matches)) {
                        if (!empty($matches[1])) {
                            $mod_info['old_forge']['version'] = $matches[1];
                        } else {
                            array_push($this->warn, 'Missing version!');
                            $mod_info['old_forge']['version'] = '';
                        }
                    } else {
                        array_push($this->warn, 'Missing version!');
                        $mod_info['old_forge']['version'] = '';
                    }
                } else {
                    $mod_info['forge_old']['version'] = $parsed['version'];
                }
                if (!empty($parsed['name']))
                    $mod_info['forge_old']['name']  = $parsed['name'];
                if (!empty($parsed['url']))
                    $mod_info['forge_old']['url'] = $parsed['url'];
                if (!empty($parsed['credits'])) 
                    $mod_info['forge_old']['credits'] = $parsed['credits'];
                if (!empty($parsed['authorList'])) 
                    $mod_info['forge_old']['authors'] = implode(', ', $parsed['authorList']);
                if (!empty($parsed['description'])) 
                    $mod_info['forge_old']['description'] = $parsed['description'];
                if (!empty($parsed['mcversion']))
                    $mod_info['forge_old']['mcversion']=$parsed['mcversion'];
                else {
                    array_push($this->warn, 'Missing mcversion!');
                    error_log('{"status": "warn", "message": "Forge_Old: Missing mcversion!"}');
                }

                // each dependency is a string
                if (array_key_exists('dependencies', $parsed)) {
                    foreach ($parsed['dependencies'] as $dep) {
                        if (empty($dep)) 
                            continue;
                        array_push($mod_info['forge_old']['dependencies'], strtolower($dep));;
                    }
                }

                $mod_info['forge_old']['loadertype']='forge'; // same
                // no loaderversion
                // no license
                // error_log("modTypes- Forge Old added");
            }

            if ($modtype=='fabric' && $modTypes['fabric']===TRUE) {
                $raw = $zip->getFromName(FABRIC_INFO_PATH);
                if ($raw === FALSE) {
                    error_log ('{"status": "error", "message": "Fabric: Could not access info file from Fabric mod."}');
                    // die ('{"status": "error", "message": "Could not access info file from Fabric mod."}');
                    $mod_info['fabric']=null;
                    array_push($this->warn, 'Fabric: could not access info file');
                    continue;
                }

                $mod_info['fabric']=$mcmod_orig;
                $parsed = json_decode(preg_replace('/\r|\n/', '', trim($raw)), true);

                if (empty($parsed['id'])) {
                    error_log ('{"status": "error", "message": "Fabric: Missing id!"}');
                    // die ('{"status": "error", "message": "Missing id!"}');
                    $mod_info['fabric']=null;
                    array_push($this->warn, 'Fabric: missing id');
                    continue;
                } else {
                    $mod_info['fabric']['modid'] = $parsed['id'];
                }
                if (empty($parsed['version'])) {
                    array_push($this->warn, 'Missing version!');
                    error_log('{"status": "warn", "message": "Fabric: Missing version!"}');
                    $mod_info['fabric']['version'] = '';
                } else{
                    $mod_info['fabric']['version'] = $parsed['version'];
                }
                if (!empty($parsed['name']))
                    $mod_info['fabric']['name'] = $parsed['name'];
                if (!empty($parsed['contact']) && !empty($parsed['contact']['homepage']))
                    $mod_info['fabric']['url'] = $parsed['contact']['homepage'];
                // no credits
                if (!empty($parsed['authors']))
                    $mod_info['fabric']['authors'] = implode(', ', $parsed['authors']);
                if (!empty($parsed['description']))
                    $mod_info['fabric']['description'] = $parsed['description'];

                // each dependency is a key=value entry.
                if (array_key_exists('depends', $parsed)) {
                    foreach (array_keys($parsed['depends']) as $depId) {
                        $dep_version = '*'; // default to any
                        if (!empty($parsed['depends'][$depId])) {
                            $dep_version = fabric_to_interval_range($parsed['depends'][$depId]);
                        }

                        array_push($mod_info['fabric']['dependencies'], $dep_version);

                        if (strtolower($depId)=='minecraft') {
                            $mod_info['fabric']['mcversion']=$dep_version;
                        }
                        if (strtolower($depId)=='fabricloader') {
                            $mod_info['fabric']['loaderversion']=$dep_version;
                        }
                    }
                }

                if (empty($mod_info['fabric']['mcversion'])) {
                    array_push($this->warn, 'Missing mcversion!');
                    error_log('{"status": "warn", "message": "Fabric: Missing mcversion!"}');
                }

                $mod_info['fabric']['loadertype']='fabric';

                if (!empty($parsed['license']))
                    $mod_info['fabric']['license'] = $parsed['license'];
                // error_log("modTypes- Fabric added");
            }
        }

        // error_log("MOD_INFO: ".json_encode($mod_info,JSON_UNESCAPED_SLASHES));
        return $mod_info;
    }

    public function getWarnings() {
        return $this->warn;
    }

    public function getModInfo(string $filePath, $fileName) {
        // return first mod detected
        $mod_types = $this->getModTypes($filePath);
        $mod_infos = $this->getModInfos($mod_types, $filePath, $fileName);
        return $mod_infos;
    }
}
?>