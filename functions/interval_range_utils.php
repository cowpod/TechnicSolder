<?php

function in_range($range, $num) {
	// compares two minecraft versions. 
	// returns true if $num is in interval range $range.
	// returns false otherwise.
	
	if (empty($range)) return false;
	if (empty($num)) return false;

	$matches=[];
	if (preg_match("/^(?:\[)?(.*),(.*)(?:\])?$/", $range, $matches)) { // [, ]
		[$full,$low,$high]=$matches;
		return (version_compare($num, $low, '>=') && version_compare($num, $high, '<='));
	}
	$matches=[];
	if (preg_match("/^(?:\[)?(.*),(.*)(?:\))?$/", $range, $matches)) { // [, )
		[$full,$low,$high]=$matches;
		return (version_compare($num, $low, '>=') && version_compare($num, $high, '<'));
	}
	$matches=[];
	if (preg_match("/^(?:\()?(.*),(.*)(?:\])?$/", $range, $matches)) { // (, ]
		[$full,$low,$high]=$matches;
		return (version_compare($num, $low, '>') && version_compare($num, $high, '<='));
	}
	$matches=[];
	if (preg_match("/^(?:\()?(.*),(.*)(?:\))?$/", $range, $matches)) { // (, )
		[$full,$low,$high]=$matches;
		return (version_compare($num, $low, '>') && version_compare($num, $high, '<'));
	}
	
	// assume range is just a string?
	return version_compare($range,$num,'=');
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