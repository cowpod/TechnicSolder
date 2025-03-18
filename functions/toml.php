<?php
/*
TOML Parser
O(n(m+t)), where n = number of lines in file, m = chars in line, t = number of nested tables (aaa.bbb.ccc. etc. up to m) specified in a line.
todo: make static?
*/

final class Toml {
    /**
     * @param string[] $arr
     *
     * @psalm-param non-empty-list<string> $arr
     *
     * @return string[]
     *
     * @psalm-return list<string>
     */
    private function clean_lines(array $arr): array {
        $ret=[];
        for ($i=0; $i<sizeof($arr); $i++) {
            $line=ltrim($arr[$i]); // deal with indented lines, todo: handles tabs?
            if (($line)==='') {
                continue;
            }
            // eliminate line-comments
            if ($line[0]=="#") {
                continue;
            }
            
            // eliminate in-line comments
            $pos = strpos($line, '#');
            if (!empty($pos)) {
                $line = substr($line, 0, $pos);
            }

            array_push($ret, rtrim($line));
        }
        return $ret;
    }

    private function actual_parse($arr) {
        // O(nm)
        $ret=[];
        $table=&$ret;

        $json_key=null;
        $json_str=null;
        
        $multiline_string=null; // different from ''. later points to a value for a key.
        
        foreach ($arr as $element) {
            if (strlen($element)>4 && $element[0]=='[' && $element[1]=='[' && $element[-1]==']' && $element[-2]==']') {
                // [[ TABLE ]]
                $table_name=ltrim($element,'[[');
                $table_name=rtrim($table_name,']]');
                $nested_table_array=explode('.',$table_name);

                // start at root
                $temp_nested_table_ref=&$ret; 
                    
                foreach($nested_table_array as $t) { 
                    if (!array_key_exists($t, $temp_nested_table_ref)) {
                        $temp_nested_table_ref[$t]=[]; 
                    }   
                    $temp_nested_table_ref = &$temp_nested_table_ref[$t]; 
                }
                $table=&$temp_nested_table_ref[sizeof($temp_nested_table_ref)];
                unset($temp_nested_table_ref);
            } else if (strlen($element)>2 && $element[0]=='[' && $element[-1]==']') {
                // [ TABLE ]
                $table_name=ltrim($element,'[[');
                $table_name=rtrim($table_name,']]');
                $nested_table_array=explode('.',$table_name);
                
                // start at root
                $temp_nested_table_ref=&$ret; 
                    
                foreach($nested_table_array as $t) { 
                    if (!array_key_exists($t, $temp_nested_table_ref)) {
                        $temp_nested_table_ref[$t]=[]; // create it if necessary. 
                    }
                    // update pointer to nested table
                    $temp_nested_table_ref = &$temp_nested_table_ref[$t];
                }
                $table=&$temp_nested_table_ref;
                unset($temp_nested_table_ref);
            } 

            else if ($json_key && trim($element)==']') { 
                $json_str=trim($json_str, ',');
                $json_str.=']';
                $parsedjson = preg_replace('/([,{])\s*([\w$]+)\s*=\s*/', '$1 "$2": ', $json_str);
                $parsedjson = preg_replace('/\'([^\']*)\'/', '"$1"', $parsedjson);
                // error_log('got complete json: '.$json_key.'='.$parsedjson);
                $ret[$json_key] = json_decode($parsedjson,true);
                $json_key=NULL;
                $json_str=NULL;
            } else if ($json_key && trim($element)[strlen($element)-1]==']') { // also non-danging ]
                $json_str .= trim($element);
                $parsedjson = preg_replace('/([,{])\s*([\w$]+)\s*=\s*/', '$1 "$2": ', $json_str);
                $parsedjson = preg_replace('/\'([^\']*)\'/', '"$1"', $parsedjson);
                // error_log('got complete json: '.$json_key.'='.$parsedjson);
                $ret[$json_key] = json_decode($parsedjson,true);
                $json_key=NULL;
                $json_str=NULL;
            } else if ($json_key) { 
                // error_log('got json data row: '.$element);
                $json_str.=$element;
            } 

            else {
                // KEY=VALUE
                $keyval = explode("=", $element, 2);
                if (sizeof($keyval)==1) {
                    // got a string?
                    $value=$keyval[0];
                    
                    if (strlen($value)>=3 && (($value[-1]=="'" && $value[-2]=="'" && $value[-3]=="'") || ($value[-1]=='"' && $value[-2]=='"' && $value[-3]=='"'))) {
                        // END OF MULTI-LINE STRING
                        $value=rtrim($value, "'''");
                        $value=rtrim($value, '"""');
                        $multiline_string=$multiline_string.$value;
                        unset($multiline_string); 
                    } else {
                        $multiline_string=$multiline_string.$value."\n";
                    }
                    
                } else {
                    $key_r=trim($keyval[0]);
                    $value_r=trim($keyval[1]);
                    $key=trim($key_r,'"');
                    $value=trim($value_r,'"');
                        
                    if (strlen($value)>=3 && (($value[0]=="'" && $value[1]=="'" && $value[2]=="'") || ($value[0]=='"' && $value[1]=='"' && $value[2]=='"'))) {
                        // START OF MULTILINE STRING
                        $value=ltrim($value,"'''");
                        $value=ltrim($value,'"""');
                        $table[$key]=$value."\n";
                        $multiline_string=&$table[$key];
                        
                        // sometimes, a multi-line string can actually be a single-line string...
                        if (strlen($value)>=6 && (($value[-1]=="'" && $value[-2]=="'" && $value[-3]=="'") || ($value[-1]=='"' && $value[-2]=='"' && $value[-3]=='"'))) {
                            // END OF MULTI-LINE STRING
                            $value=rtrim($value, "'''");
                            $value=rtrim($value, '"""');
                            $table[$key]=$value; // unecessary write, with a refactor?
                            unset($multiline_string); // unecessary set+unset, with a refactor?
                        }
                    } 

                    // this is a bit weird, treat 'js' array object as json!
                    else if (strlen($value_r)>=1 && $value_r=='[') { // EXPECT JSON
                        // error_log('start json data');
                        $json_key=$key;
                        $json_str='[';
                    } else if (strlen($value_r)>=2 && $value_r[0]=='[') { // EXPECT JSON
                        // error_log('start json data');
                        $json_key=$key;
                        $json_str=$value_r;
                    } else if (strlen($value_r)>=1 && $value_r==']') { // DONE WITH JSON
                        $json_str=trim($json_str, ',');
                        $json_str.=']';
                        // technically not json. so needs some work.
                        $parsedjson = preg_replace('/([,{])\s*([\w$]+)\s*=\s*/', '$1 "$2": ', $json_str);
                        $parsedjson = preg_replace('/\'([^\']+)\'/', '"$1"', $parsedjson);
                        // error_log('got complete json: '.$json_key.'='.$parsedjson);
                        $ret[$json_key] = json_decode($parsedjson,true);
                        $json_key=NULL;
                        $json_str=NULL;
                    } else if (strlen($value_r)>=2 && $value_r[strlen($value_r)-1]==']') { // DONE WITH JSON
                        $json_str.=$value_r;
                        // technically not json. so needs some work.
                        $parsedjson = preg_replace('/([,{])\s*([\w$]+)\s*=\s*/', '$1 "$2": ', $json_str);
                        $parsedjson = preg_replace('/\'([^\']+)\'/', '"$1"', $parsedjson);
                        // error_log('got complete json: '.$json_key.'='.$parsedjson);
                        $ret[$json_key] = json_decode($parsedjson,true);
                        $json_key=NULL;
                        $json_str=NULL;
                    } 

                    else {
                        $table[$key]=$value;
                    }
                }
            }
        }
        // error_log(json_encode($ret, JSON_UNESCAPED_SLASHES));
        return $ret;
    }

    public function parse($raw) {
        $raw_arr=explode("\n", $raw);
        $cleaned=$this->clean_lines($raw_arr);
        $parsed=$this->actual_parse($cleaned);
        
        return $parsed;
    }
}

?>
