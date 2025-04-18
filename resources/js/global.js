function compareVersions(version1, version2) {
    // convert to strings
    version1=''+version1;
    version2=''+version2;
    // handle versions not containing a dot...
    if (!version1.includes('.')&&!version2.includes('.')) {
        if (version1>version2) {
            return 1;
        }
        if (version1<version2) {
            return -1;
        }
        return 0;
    }
    if (!version1.includes('.')) {
        if (version1>version2.split('.')[0]) {
            return 1;
        }
        if (version1<version2.split('.')[0]) {
            return -1;
        }
        return 0;
    }
    if (!version2.includes('.')) {
        if (version1.split('.')[0]>version2) {
            return 1;
        }
        if (version1.split('.')[0]<version2) {
            return -1;
        }
        return 0;
    }

    const v1 = version1.replace(/[\'\"\[\(\]\)]/, '').split('.').map(num => parseInt(num, 10));
    const v2 = version2.replace(/[\'\"\[\(\]\)]/, '').split('.').map(num => parseInt(num, 10));

    // Compare each part
    const length = Math.max(v1.length, v2.length);
    for (let i = 0; i < length; i++) {
        // if one has a trailing zero and the other doesn't have anything
        if (!v1[i] && v2[i]==0 && i==v2.length) {
            return 0;
        }
        if (!v2[i] && v1[i]==0 && i==v1.length) {
            return 0;
        }
        const val1 = v1[i] || 0;  // If version 1 has fewer parts, treat as 0
        const val2 = v2[i] || 0;  // If version 2 has fewer parts, treat as 0

        if (val1 < val2) return -1;
        if (val1 > val2) return 1;
    }

    return 0; // Versions are equal
}

function isVersionInInterval(version, interval) {
    // Remove whitespace
    interval = interval.replace(/\s+/g, '');

    if (!interval.includes(',')) {
        // /^\[?[^,]+\]?$/
        let comp=compareVersions(version, interval.replace(/[\[\]\(\)]/,''))
        return comp==0
    }

    // Check interval boundaries for inclusivity/exclusivity
    const startInclusive = interval[0] === '[';
    const endInclusive = interval[interval.length - 1] === ']';

    // Extract version bounds
    var [startVersion, endVersion] = interval.slice(1, -1).split(',');
    // console.log(startVersion,endVersion);
    if (startVersion=='' || startVersion==undefined) {
        startVersion=-Number.MAX_SAFE_INTEGER;
    }
    if (endVersion=='' || endVersion==undefined) {
        endVersion=Number.MAX_SAFE_INTEGER;
    }
    // console.log(startVersion+','+endVersion);

    // Use compareVersions to check if version is within the range
    const compareStart = compareVersions(version, startVersion);
    const compareEnd = compareVersions(version, endVersion);

    const inLowerBound = startInclusive ? compareStart >= 0 : compareStart > 0;
    const inUpperBound = endInclusive ? compareEnd <= 0 : compareEnd < 0;

    // console.log(inLowerBound && inUpperBound)

    return inLowerBound && inUpperBound;
}

function slugify (str) {
    str = str.replace(/^\s+|\s+$/g, '');
    str = str.toLowerCase();
    var from = "àáãäâèéëêìíïîòóöôùúüûñšç·/_,:;";
    var to = "aaaaaeeeeiiiioooouuuunsc------";
    for (var i=0, l=from.length ; i<l ; i++) {
        str = str.replace(new RegExp(from.charAt(i), 'g'), to.charAt(i));
    }
    str = str.replace(/[^a-z0-9 -]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
    return str;
}

function getQueryVariable(variable) {
  // alert('getQueryVariable called');
  var query = window.location.search.substring(1);
  var vars = query.split("&");
  for (var i=0;i<vars.length;i++) {
    var pair = vars[i].split("=");
    if (pair[0] == variable) {
      return pair[1];
    }
  } 
  console.log('Query Variable ' + variable + ' not found');
}

// caching
var havelocalstorage=null;
var temp_cache={};

// if local storage is unavailable, this will loop the full amount before
// failing.
function isLocalStorageAvailable(attempts_remaining = 1){
    if (attempts_remaining == 0) {
        return false;
    }

    var test = 'test';
    try {
        localStorage.setItem(test, test);
        localStorage.removeItem(test);
        return true;
    } catch(e) {
        // clear storage and try again
        localStorage.clear();
        return isLocalStorageAvailable(attempts_remaining-1);
    }
}

function get_cached(key) {
    if (havelocalstorage==null) {
        havelocalstorage=isLocalStorageAvailable()
    }
    if (havelocalstorage) {
        if (key in localStorage) {
            tv = JSON.parse(localStorage[key]);
            if (tv[0]==-1 || Math.round(Date.now() / 1000) < tv[0]) {
                return tv[1];
            } else {
                localStorage.removeItem(key)
                return undefined;
            }
        }
    } else {
        if (key in temp_cache) {
            return temp_cache[key];
        } else {
            return undefined;
        }
    }
}
function set_cached(key,value,ttl=-1) {
    if (havelocalstorage==null) {
        havelocalstorage=isLocalStorageAvailable()
    }
    // cache for 30 mins
    if (havelocalstorage) {
        if (ttl==Infinity||ttl==-1) {
            var timestamp = -1;
        } else {
            var timestamp = Math.round(Date.now() / 1000)+ttl;
        }
        val=JSON.stringify([timestamp, value]);
        localStorage[key]=val
        return value;
    } else {
        temp_cache[key]=value;
        return value;
    }
}

async function getData(url, cacheoptions=null) {
  try {
    console.log("getData(): fetching "+url);
    let response = null;
    if (cacheoptions !== null) {
        response = await fetch(url, cacheoptions);
    } else {
        response = await fetch(url);
    }
    if (response===null || !response.ok) {
      throw new Error(`Response status: ${response.status}`);
    }

    const json = await response.json();
    console.log("data fetched!");
    return json;
  } catch (error) {
    console.error(error.message);
  }
}

function insertAfter(referenceNode, newNode) {
    referenceNode.parentNode.insertBefore(newNode, referenceNode.nextSibling);
}