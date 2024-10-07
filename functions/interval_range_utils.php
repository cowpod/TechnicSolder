<?php

function in_range($range, $num) {
	// compares two minecraft versions. 
	// returns true if $num is in interval range $range.
	// returns false otherwise.
	
	if (empty($range)) return false;
	if (empty($num)) return false;

	$exploded = explode(',', str_replace(' ', '', $range));
	// explode [a,b] into '[a' and 'b]'
	// or [a,] into '[a' and ']'
	
	foreach ($exploded as $endpoint) {
		$trimmed=trim($endpoint, '[(])');
		if (str_contains($endpoint, '[')) {
			// left side [
			if (! version_compare($num, $trimmed, '>='))
				return false;
		}
		if (str_contains($endpoint, '(')) {
			// left side (
			if (! version_compare($num, $trimmed, '>'))
				return false;
		}
		if (str_contains($endpoint, ']')) {
			// right side ]
			if (! version_compare($num, $trimmed, '<='))
				return false;
		}
		if (str_contains($endpoint, ')')) {
			// right side )
			if (! version_compare($num, $trimmed, '<'))
				return false;
		}
	}
	
	return true;
}

function mcversion_to_range($mcversion) {
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