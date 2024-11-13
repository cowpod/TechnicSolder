function compareVersions(version1, version2) {
  const v1 = version1.split('.').map(num => parseInt(num, 10));
  const v2 = version2.split('.').map(num => parseInt(num, 10));

  // Compare each part
  const length = Math.max(v1.length, v2.length);
  for (let i = 0; i < length; i++) {
    const val1 = v1[i] || 0;  // If version 1 has fewer parts, treat as 0
    const val2 = v2[i] || 0;  // If version 2 has fewer parts, treat as 0

    if (val1 < val2) return -1;
    if (val1 > val2) return 1;
  }

  return 0; // Versions are equal
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
function isLocalStorageAvailable(){
    var test = 'test';
    try {
        localStorage.setItem(test, test);
        localStorage.removeItem(test);
        return true;
    } catch(e) {
        return false;
    }
}
function get_cached(key) {
    if (havelocalstorage) {
        if (key in localStorage) {
            tv = JSON.parse(localStorage[key]);
            if (Math.round(Date.now() / 1000) < tv[0]) {
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
function set_cached(key,value) {
    // cache for 30 mins
    if (havelocalstorage) {
        timestamp = Math.round(Date.now() / 1000)+1800;
        val=JSON.stringify([timestamp, value]);
        localStorage[key]=val
        return value;
    } else {
        temp_cache[key]=value;
        return value;
    }
}