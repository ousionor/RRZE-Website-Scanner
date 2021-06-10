<?php


// Automatische Laden von Klassen.
spl_autoload_register(function ($class) {
    $prefix = __NAMESPACE__;
    $base_dir = __DIR__ . '/includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
    



if ($argc !== 2) {
    echo "Usage: php get-website-meta.php <url>\n";
    exit(1);
}
$url = $argv[1];

if (is_valid_url($url)) {
    echo "Checking URL ".$url."\n";
    
   parse_website($url);
} else {
   echo "URL invalid.\n";
   exit(1);   
}



function parse_website($url) {
    if (empty($url)) return false;
    

    $cc = new cURL();
    $data = $cc->get($url);
    
    echo "Status Code:        ".$data['meta']['http_code']."\n";
    echo "connect_time:       ".$data['meta']['connect_time']."\n";
    echo "pretransfer_time:   ".$data['meta']['pretransfer_time']."\n";
    echo "starttransfer_time: ".$data['meta']['starttransfer_time']."\n";
    echo "Total Time:         ".$data['meta']['total_time']."\n";
    echo "Size:               ".$data['meta']['size_download']." Bytes\n";
    echo "primary_ip:         ".$data['meta']['primary_ip']."\n";
    echo "\n";
    
    if ($data['meta']['http_code'] >= 200 && $data['meta']['http_code'] < 500) {

	$analyse = new Analyse($url);
	//$analyse->set_url($url);
	$analyse->init($data);

	echo "Analyse:\n";
	
	
	echo "Title:              ".$analyse->title."\n";
	if (isset($analyse->canonical)){
	    echo "Canonical URL:      ".$analyse->canonical;
	    echo "\n";
	}
	if (isset($analyse->lang)){
	    echo "Language:           ".$analyse->lang;
	    echo "\n";
	}
	if (isset($analyse->generator)){
	    echo "Generator:          ".$analyse->generator['name'];
	    if (isset($analyse->generator['version'])) {
		echo " (".$analyse->generator['version'].")";
	    }
	    echo "\n";
	}
	if (isset($analyse->template)) {
	    echo "Template:           ".$analyse->template;
	    if (isset($analyse->template_version)) {
		echo " (".$analyse->template_version.")";
	    }
	    echo "\n";
	}
	if (isset($analyse->meta) && isset($analyse->meta['description'])) {
	    echo "Meta-Description:   ".$analyse->meta['description']."\n";
	}
	if (isset($analyse->favicon)) {
	    echo "Favicon:            ".$analyse->favicon['href'];
	    if (!empty($analyse->favicon['sizes'])) {
		echo " (".$analyse->favicon['sizes'].")";
	    }
	    echo "\n";
	}
	if (isset($analyse->logosrc)) {
	    echo "Logo:               ".$analyse->logosrc."\n";
	}
	if ($analyse->toslinks) {
	    echo "\nRechtliche Angaben:\n";
	    foreach ($analyse->toslinks as $tos => $value) {
		echo "\t".$tos.":\t".$value['linktext']." (".$value['href'].")\n";
	    }
	}
	if ($analyse->external) {
	    echo "\nExterne Ressourcen:\n";
	     foreach ($analyse->external as $link) {
		echo "\t".$link."\n";
	    }
	}
    }
	
}
    
    
function is_valid_url($urlinput) {
    $url = filter_var($urlinput, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED|FILTER_FLAG_HOST_REQUIRED);
    
    if (empty($url) || (strlen($url) != strlen($urlinput))) {
	return false;
    }
    return true;
}

