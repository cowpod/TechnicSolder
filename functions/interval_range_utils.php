<?php

function fabric_to_interval_range($str) {
	// convert fabric's ">=X <=X" to interval notation
	$matches=[];
	if (preg_match("/^(>=|>)(.*)\s(<=|<)(.*)$/", $str, $matches)) { // >=|>... <=|<...
		[$full,$op1,$num1,$op2,$num2] = $matches;
		$op1 = ($op1=='>=') ? '[' : '(';
		$op2 = ($op2=='<=') ? ']' : ')';
		return $op1.$num1.','.$num2.$op2;
	}
	elseif (preg_match("/^(>=|>)(.*)$/", $str, $matches)) { // >=|>...
		[$full,$op,$num]=$matches;
		$op = ($op=='>=') ? '[' : '(';
		return $op.$num.',)';
	}
	elseif (preg_match("/^(<=|<)(.*)$/", $str, $matches)) { // <=|<...
		[$full,$op,$num]=$matches;
		$op = ($op=='<=') ? ']' : ')';
		return '(,'.$num.$op;
	}
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
	
	if (empty($range)) return FALSE;
	if (empty($num)) return FALSE;

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

function mcversion_to_range($mcversion) {
	//todo: make this like above
    $min=null;
    $max=null;
    $mcvrange=explode(",", trim($mcversion,"[]()"));

    if (sizeof($mcvrange)==2) {
        $min=$mcvrange[0];
        $max=$mcvrange[1];
    }
    elseif (sizeof($mcvrange)==1) {
        $min=$mcvrange[0];
        // don't specify a max
    }

    return ["min"=>$min, "max"=>$max, "min_inclusivity"=>$mcversion[0], "max_inclusivity"=>$mcversion[-1]];
}

?>