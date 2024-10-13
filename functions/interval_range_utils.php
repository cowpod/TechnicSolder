<?php

function fabric_to_interval_range($str) {
	// convert fabric's ">=X <=X" to interval notation
	$matches=[];
	if (preg_match("/^(>=|>)([0-9\.]*)\s(<=|<)([0-9\.]*)$/", $str, $matches)) { // >=|>... <=|<...
		[$full,$op1,$num1,$op2,$num2] = $matches;
		$op1 = ($op1=='>=') ? '[' : '(';
		$op2 = ($op2=='<=') ? ']' : ')';
		return $op1.$num1.','.$num2.$op2;
	}
	elseif (preg_match("/^(>=|>)([0-9\.]*)$/", $str, $matches)) { // >=|>...
		[$full,$op,$num]=$matches;
		$op = ($op=='>=') ? '[' : '(';
		return $op.$num.',)';
	}
	elseif (preg_match("/^(<=|<)([0-9\.]*)$/", $str, $matches)) { // <=|<...
		[$full,$op,$num]=$matches;
		$op = ($op=='<=') ? ']' : ')';
		return '(,'.$num.$op;
	}

	elseif (str_starts_with(strtolower($str), '~')) {
		if (str_ends_with(strtolower($str),'.x')) // in case it ALSO ends in .x
			$str=str_replace('.x','',$str);
		$lower=str_replace('~', '', strtolower($str));
		$exp=explode('.',$lower);
		$incremented_last=$exp[sizeof($exp)-1]+1; // increment last
		array_pop($exp); // remove last
		array_push($exp, $incremented_last);
		$upper=implode('.', $exp);
		return '['.$lower.','.$upper.')';
	}
	elseif (str_ends_with(strtolower($str), ".x")) {
		$lower=str_replace('.x', '', strtolower($str));
		$exp=explode('.',$lower);
		$incremented_last=$exp[sizeof($exp)-1]+1; // increment last
		array_pop($exp); // remove last
		array_push($exp, $incremented_last);
		$upper=implode('.', $exp);
		return '['.$lower.','.$upper.')';
	}

	if (empty($str))
		return '*';

	// just to clarify what we consider to be 'any'
	if ($str=='*') {
		return '*';
	}

	return $str;
}

// used to trim trailing .0 as php (as of 8.3) believes that 1.2.0 is greater than 1.2
function removeEnding($string, $remove) {
    if (substr($string, -strlen($remove)) === $remove) {
        return substr($string, 0, -strlen($remove));
    }
    return $string;
}

function in_range($range, $num) {
	// returns true if $num is in interval range $range.
	// returns false otherwise.
	
	if (empty($range) || empty($num)) return FALSE;

	$num=removeEnding($num,'.0');

	$matches=[];
	if     (preg_match("/^(\[)([0-9\.]*),\s?([0-9\.]*)(\])$/", $range, $matches)) { // [, ]
		[$full,$not1,$low,$high,$not2]=$matches;
		return (version_compare($num, $low, '>=') && version_compare($num, $high, '<='));
	}
	elseif (preg_match("/^(\[)([0-9\.]*),\s?([0-9\.]*)(\))$/", $range, $matches)) { // [, )
		[$full,$not1,$low,$high,$not2]=$matches;
		return (version_compare($num, $low, '>=') && version_compare($num, $high, '<'));
	}
	elseif (preg_match("/^(\()([0-9\.]*),\s?([0-9\.]*)(\])$/", $range, $matches)) { // (, ]
		[$full,$not1,$low,$high,$not2]=$matches;
		return (version_compare($num, $low, '>') && version_compare($num, $high, '<='));
	}
	elseif (preg_match("/^(\()([0-9\.]*),\s?([0-9\.]*)(\))$/", $range, $matches)) { // (, )
		[$full,$not1,$low,$high,$not2]=$matches;
		return (version_compare($num, $low, '>') && version_compare($num, $high, '<'));
	}
	
	// assume range is just a string?
	return version_compare($range,$num,'=');
}


function parse_interval_range($mcversion) {
    $min=null;
    $max=null;

    // blank or any
	if ($mcversion=='*'||empty($mcversion)) {
		return ['min'=>PHP_INT_MIN, 'max'=>PHP_INT_MAX, 'min_inclusivity'=>'>', 'max_inclusivity'=>'<'];
	}
    // is it actually interval notation?
    elseif (str_contains($mcversion, ',') && (str_starts_with($mcversion,'(')||str_starts_with($mcversion,'[')) && (str_ends_with($mcversion,')')||str_ends_with($mcversion,']'))) {
	    $mcvrange=explode(",", trim($mcversion,"[]()"));

	    if (sizeof($mcvrange)==2) {
	        $min=$mcvrange[0];
	        $max=$mcvrange[1];
	    }
	    elseif (sizeof($mcvrange)==1) {
	        $min=$mcvrange[0];
	        // don't specify a max
	    }
    	return ["min"=>$min, "max"=>$max, "min_inclusivity"=>$mcversion[0]=='[' ? '>=' : '>', "max_inclusivity"=>$mcversion[-1]==']' ? '<=' : '<'];
	} 
	// we assume it's a number
	else {
		$min=$mcversion;
		$exp=explode('.',$mcversion);
		error_log($exp[sizeof($exp)-1]);
		$incremented_last=$exp[sizeof($exp)-1]+1; // increment last
		array_pop($exp); // remove last
		array_push($exp, $incremented_last);
		$max=implode('.', $exp);
		return ['min'=>$min, 'max'=>$max, 'min_inclusivity'=>'>=', 'max_inclusivity'=>'<'];
	}

}

?>