<?php
/*
TOML Parser
O(n(m+t)), where n = number of lines in file, m = chars in line, t = number of nested tables (aaa.bbb.ccc. etc. up to m) specified in a line.

Licensed under CC-BY-NC-SA 4.0
https://creativecommons.org/licenses/by-nc-sa/4.0/

Written by Henry G.H
*/

function clean_lines($arr) {
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

function fast_str_len_compare($str, $len) {
    // O(1) check to see if string is length.
    // arguably O(L) where L is $len, however we assume that $len is some hardcoded number.
    // returns 0 if equal, 
    // -1, or -2,-3... up to -$len if less, 
    // 1 if greater. doesn't check greater as that'd cause an O(m) runtime which I'm trying to avoid.
    
    // technically useless since there are other O(N) calls besides this, such as ltrim/rtrim/substr/strpos
    
    for ($i=0; $i<$len; $i++) {
        if (!isset($str[$i])) {
            assert($i-$len != 0); // obviously if we return 0 then we're fine, WHICH WE'RE NOT!
            return $i-$len; // we are $i-$len short (negative)!
        }
    }
    if (!isset($str[$len])) {
        // if all chars up to $len are set, but $len itself isn't, we are $len in length!
        return 0;
    } else {
        return 1; // FUQ U i'm not counting the whole string length. we're 1+ over $len!
    } 
}

// code is weird, and will likely break for toml files other than those found in minecraft mods.
// specifically, the behaviour of nested tables is... awkward.
function parseToml_actual_parse($arr) {
    // O(nm)
    $ret=[];
    $table=&$ret;
    
    $multiline_string=null; // different from ''. later points to a value for a key.
    
    foreach ($arr as $element) {
        if (fast_str_len_compare($element, 4)>0 && $element[0]=='[' && $element[1]=='[' && $element[-1]==']' && $element[-2]==']') {
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

        } else if (fast_str_len_compare($element, 2)>0 && $element[0]=='[' && $element[-1]==']') {
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
        } else {
            // KEY=VALUE
            $keyval = explode("=", $element, 2);
            if (sizeof($keyval)==1) {
                // got a string?
                $value=$keyval[0];
                
//                print($value."<br/>");
                
                if (fast_str_len_compare($value,3)>=0 && (($value[-1]=="'" && $value[-2]=="'" && $value[-3]=="'") || ($value[-1]=='"' && $value[-2]=='"' && $value[-3]=='"'))) {
                    // END OF MULTI-LINE STRING
                    $value=rtrim($value, "'''");
                    $value=rtrim($value, '"""');
                    $multiline_string=$multiline_string.$value;
                    unset($multiline_string); 
                } else {
                    $multiline_string=$multiline_string.$value."\n";
                }
                
            } else {
                $key=$keyval[0];
                $value=$keyval[1];
                
//                print($key."=>".$value."<br/>");
                    
                if (($value[0]=="'" && $value[1]=="'" && $value[2]=="'") || ($value[0]=='"' && $value[1]=='"' && $value[2]=='"')) {
                    // START OF MULTILINE STRING
                    $value=ltrim($value,"'''");
                    $value=ltrim($value,'"""');
                    $table[$key]=$value."\n";
                    $multiline_string=&$table[$key];
                    
                    // sometimes, a multi-line string can actually be a single-line string...
                    if (fast_str_len_compare($value,6)>=0 && (($value[-1]=="'" && $value[-2]=="'" && $value[-3]=="'") || ($value[-1]=='"' && $value[-2]=='"' && $value[-3]=='"'))) {
                        // END OF MULTI-LINE STRING
                        $value=rtrim($value, "'''");
                        $value=rtrim($value, '"""');
                        $table[$key]=$value; // unecessary write, with a refactor?
                        unset($multiline_string); // unecessary set+unset, with a refactor?
                    }
                    
                } else {
                    $table[$key]=trim($value,'"');
                }
            }
        }
    }
    
    return $ret;
}

function parseToml($raw) {
    $raw_arr=explode("\n", $raw);
    $cleaned=clean_lines($raw_arr);
    $parsed=parseToml_actual_parse($cleaned);
    
    return ($parsed);
}

if (isset($_GET['a']) && $_GET['a']=='test') {
    $data=parseToml(file_get_contents('./test.toml'));
    header('Content-Type: application/json');
//    print("<hr/>");
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
}

?>
