define('TRIMCHARS', '[(])');

function mcVersionCompare($modVersion, $userVersion) {
	// compares two minecraft versions. 
	// returns true if  $userversion is in interval range $modVersion.
	// returns false otherwise.
	
	if (empty($modVersion)) return false;
	if (empty($userVersion)) return false;

	$explodedModVersion = explode(',', str_replace(' ', '', $modVersion));
	// explode [a,b] into '[a' and 'b]'
	// or [a,] into '[a' and ']'
	
	foreach ($explodedModVersion as $endpoint) {
		$trimmed=trim($endpoint, TRIMCHARS);
		if (str_contains($endpoint, '[')) {
			// left side [
			if (! version_compare($userVersion, $trimmed, '>='))
				return false;
		}
		if (str_contains($endpoint, '(')) {
			// left side (
			if (! version_compare($userVersion, $trimmed, '>'))
				return false;
		}
		if (str_contains($endpoint, ']')) {
			// right side ]
			if (! version_compare($userVersion, $trimmed, '<='))
				return false;
		}
		if (str_contains($endpoint, ')')) {
			// right side )
			if (! version_compare($userVersion, $trimmed, '<'))
				return false;
		}
	}
	
	return true;
}
