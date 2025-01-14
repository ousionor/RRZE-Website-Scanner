<?php

$servertypen = [
                1       => "RRZE-Webdienst-Server",
                2       => "RRZE Server fuer Spezialdienste",
                3       => "RRZE Webserver (nicht Webteam)",
                4       => "RRZE Virtual Serverhousing",
                5       => "Externer Server in FAU",
                6       => "Externer Server",
                14      => "Housing Server",
                15      => "ZUV-Webserver",
                18      => "RRZE CMS Server"
      ];


$ignore_domains = [
    '/cms\.rrze\.uni\-erlangen\.de$/',
    '/cms\.tun\.rrze\.net$/',
    '/[a-z0-9\-]+\.cms\.rrze\.de/',
    '/[0-9]+\.kurse.rrze\.fau\.de$/',
    '/[a-z0-9\-]+\.kurse\.rrze\uni\-erlangen\.de/',
    '/\.webspace.rrze\.fau\.de$/',
    '/webserver\-default\.uni\-erlangen\.de/',
    '/infoload\.rrze\.uni\-erlangen\.de/',
    '/name\-harbour\.rrze\.uni\-erlangen\.de/',
    '/real\-name\-harbour\.rrze\.uni\-erlangen\.de/',
    '/cmslb\.rrze\.uni\-erlangen\.de/',
    '/dev[0-9\-]+\.fau\.tv/',
    '/dev[a-z0-9\-\.]*\.rrze\.uni\-erlangen\.de/',
    '/info[0-9\-]+\.rrze\.uni\-erlangen\.de/',
    '/zuv[0-9\-]+\.fau\.info/',
    '/[a-z0-9\-]+\.test\.rrze\.fau\.de/',
    '/[a-z0-9\-]+\.test\.rrze\.uni\-erlangen\.de/',
    '/[a-z0-9\-]+\.webhummel\.rrze\uni\-erlangen\.de/',
    '/[a-z0-9\-]+\.tindu\.rrze\uni\-erlangen\.de/',
    '/berta\.wmp\.rrze\uni\-erlangen\.de/',
];

		

$outputfile = "";
$prefix_outhtmlfile = "domain-analyse";
$prefix_outjsonfile = "domain-analyse";
$outjson = true;
$defaultservertyp = 18;
$json_data = array();


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
    
$shortopts = "s:o:j:";
$longopts  = array(
    "server:",
    "output:",
    "json:"    // Optional value
);
$displayhelp = false;

// Script example.php
$options = getopt($shortopts,$longopts);

if (isset($options['s'])) {
   $servertyp = $options['s'];
} elseif (isset($options['server'])) {
   $servertyp = $options['server'];
} else {
    $servertyp = $defaultservertyp;
    $displayhelp = true;
}
$servertyp = trim($servertyp);
if (empty($servertyp)) {
     $displayhelp = true;
}

$list_servertypes = preg_split('/[\s,]+/', $servertyp, -1, PREG_SPLIT_NO_EMPTY);
if (preg_match('/[\s,]+/',$servertyp)) {
    // mehr als ein Eintrag, durch Komma oder Leerzeichen getrennt

    $list_servertypes = preg_split('/[\s,]+/', $servertyp, -1, PREG_SPLIT_NO_EMPTY);
    $filenamepart = preg_replace('/[\s,]+/', '-', $servertyp);
} else {
    $filenamepart = $servertyp;
}

if (isset($options['o'])) {
   $outputfile = $options['o'];
} elseif (isset($options['output'])) {
   $outputfile = $options['output'];
} else {
    $outputfile = $prefix_outhtmlfile.'-'.$filenamepart.'.html';
}

if (isset($options['j'])) {
   $jsonfile = $options['j'];
} elseif (isset($options['json'])) {
   $jsonfile = $options['json'];
} else {
    $jsonfile = $prefix_outjsonfile.'-'.$filenamepart.'.json';
}
    
if ($displayhelp) {
    echo "Usage: php create-domain-analyse.php -s \"1, 2, 3\" -o output-file.html\n";
    
    echo "\tmit:\n";
    echo "\t-s|--server: \n";
    foreach ($servertypen as $num => $val) {
	echo "\t\t".$num."\t".$val."\n";
    }
    echo "\t\t(Servertypen können auch mit \"n1,n2\" kombiniert werden).\n";
    echo "\t-o|--output\n";
    echo "\t\tHTML Ausgabedatei\n";
    echo "\t\tDefault: $prefix_outhtmlfile-$defaultservertyp.html\n";
    exit;
   
} 


echo "Servertypen: \"$servertyp\"\n";
// echo "Filenamepart: \"$filenamepart\"\n";
echo "JSONFIle: \"$jsonfile\"\n";
echo "HTMLFIle: \"$outputfile\"\n";



$index = get_index();
usort($index, function($a, $b) {
    return $a['url'] <=> $b['url'];
});


$table = create_indextable($index,4,$list_servertypes);
// Schreibt den Inhalt in die Datei zurück
file_put_contents($outputfile, $table);

if ($outjson) {
    $json = json_encode(array('data' => $json_data));
    if (file_put_contents($jsonfile, $json))
        echo "JSON file $jsonfile created successfully...\n";
    else 
	echo "Oops! Error creating json file $jsonfile...\n";
}

exit;

function sanitize_filename($name) {
// remove illegal file system characters https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
    if ((isset($name)) && (!empty(trim($name)))) {
	$file = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $name);
	// Remove any runs of periods (thanks falstro!)
	$file = mb_ereg_replace("([\.]{2,})", '', $file);
	return $file;
    } else {
	return "";
    }
}

function create_indextable($index, $refstatus = 4, $refserver = array("1"), $wppagebreaks = true) {
    global $json_data;
    
    if (!isset($index)){
	return;
    }
    
    $line = '';
    $table = '';
    $cnt = 0;
    $maxcnt = 2000;
    $breakat = 100;
    $breakcnt = 0;
     
    
    foreach ($index as $num => $entry) {
	$line = '';
	$json_grunddata = array();

	
	if (($refstatus==-1) || (($refstatus > -1) && (in_array($entry['wmp_refservertyp'], $refserver)) )) {
	   
		
		$json_grunddata['url'] = $entry['url'];
		$json_grunddata['wmp']['refservertyp'] = $entry['wmp_refservertyp'];
		$json_grunddata['wmp']['refstatus'] = $entry['wmp_refstatus'];
		$json_grunddata['wmp']['refid'] = $entry['wmp_id'];
		$json_grunddata['wmp']['internal_domain'] = $entry['internal_domain'];
		$json_grunddata['redirect'] = '';
		
		if ($cnt > $maxcnt) {
		    break;
		}
		$cnt = $cnt +1;
		
	        $cc = new cURL();
		$data = $cc->get($entry['url']);
		$locationchange = $cc->is_url_location_host(true);
		$certinfo = $cc->get_ssl_info();
		
		echo $cc->url;
		
		$json_grunddata['httpstatus'] = $data['meta']['http_code'];
		
		
		if ($locationchange &&  $data['meta']['http_code'] >= 200 && $data['meta']['http_code'] < 500) {
		   
		    $analyse = new Analyse($cc->url);
		    $analyse->header = $cc->header;
		    @ $analyse->init($data);
    
		    echo " \t Ok\n";
		    $line .= '<tr>';
		   
		    $line .= '<td class="title">';
		    if (isset($analyse->lang)){
			$line .= '<h2 lang="'.$analyse->lang.'">';
		    }
		    
		    $line .= $analyse->title;
		    if (isset($analyse->lang)){
			$line .= '</h2>';
		    }
		    $line .=  '<span class="url"><a href="'.$analyse->url.'">'.$analyse->url.'</a></span></td>';

		    if (isset($analyse->logosrc) && !empty($analyse->logosrc)) {
			$line .= '<td class="logo"><img class="borderless noshadow" src="'.$analyse->logosrc.'" style="max-width: 240px; max-height: 65px;" alt=""></td>';
		    } else {
			$line .= '<td class="logo"></td>';
		    }
		    if (isset($analyse->favicon) && !empty($analyse->favicon)) {
			$line .= '<td class="favicon center"><img class="borderless noshadow" src="'.$analyse->favicon['href'].'" style="width: 32px; height: 32px;" alt=""></td>';
		    } else {
			$line .= '<td class="favicon center"></td>';
		    }

		    if ($analyse->toslinks) {

			 if (($analyse->toslinks['Impressum']) && (!empty($analyse->toslinks['Impressum']['href']))) {
			     $line .= '<td class="center">';
			     $line .= '<a title="Impressum von '.$analyse->url.'" href="'.$analyse->toslinks['Impressum']['href'].'">[ok]</a>';
			     $line .= '</td>';
			 } else {
			     $line .= '<td class="center">';
			     $line .= '[fail]';
			     $line .= '</td>';
			 }
			if (($analyse->toslinks['Datenschutz']) && (!empty($analyse->toslinks['Datenschutz']['href']))) {
			     $line .= '<td class="center">';
			     $line .= '<a title="Datenschutzerklärung von '.$analyse->url.'"  href="'.$analyse->toslinks['Datenschutz']['href'].'">[ok]</a>';
			     $line .= '</td>';
			 } else {
			      $line .= '<td class="center">';
			     $line .= '[fail]';
			     $line .= '</td>';
			 }
			if (($analyse->toslinks['Barrierefreiheit']) && (!empty($analyse->toslinks['Barrierefreiheit']['href']))) {
			     $line .= '<td class="center">';
			     $line .= '<a title="Barrierefreiheitserklärung von '.$analyse->url.'"  href="'.$analyse->toslinks['Barrierefreiheit']['href'].'">[ok]</a>';
			     $line .= '</td>';
			 } else {
			      $line .= '<td class="center">';
			     $line .= '[fail]';
			     $line .= '</td>';
			 }
			
		    } else {
			$line .= '<td class="center">[fail]</td><td class="center">[fail]</td><td class="center">[fail]</td>';
		    }
		    
		    
		    if ((isset($analyse->generator)) && (!empty($analyse->generator['name']))) {
		       $line .= '<td class="generator">';
		       $line .= '<span class="'.$analyse->generator['classname'].'">'.$analyse->generator['name'].'</span>';

		//	if (isset($analyse->generator['version'])) {
		//	     $line .= " (".$analyse->generator['version'].")";
		//	}
			
			
			if ((isset($analyse->template)) && ($analyse->template !== $analyse->generator['name'])) {
			    $line .= '<br><span class="template">'.$analyse->template;
			    if (isset($analyse->template_version)) {
				$line .=  " (".$analyse->template_version.")";
			    }
			     $line .= '</span>';
			}
			
			
		       $line .= '</td>';

		    } else {
			$line .= '<td class="generator"></td>';
		    }
	
		    
		     $line .= '</tr>'."\n";
		     
		     $analysedata = $analyse->get_analyse_data();  
		     
		     $jsonadd =  array_merge($json_grunddata, $analysedata);
		     $json_data[] = $jsonadd;
	     
	        } elseif (!$locationchange) {
		    echo "\t wird umgelenkt auf: ".$cc->header['location']."\n";
		    $json_grunddata['redirect'] = $cc->header['location'];
		    $json_data[] = $json_grunddata;
		    
		} else {
		    echo " \t Status Error (".$data['meta']['http_code'].")\n";
		    $json_data[] = $json_grunddata;
		}
		sleep(1);
	   

	}
	if (!empty($line)) {
	    $table .= $line."\n";
	    $tablecell[] = $line;
	    
	}
    }
    if (!empty($table)) {
	$head = '<table class="sorttable">';
	$head .= '<thead>';
	$head .= '<tr class="center">';
	
	$head .= '<th scope="col" rowspan="2">Titel / URL</th>';
	$head .= '<th scope="col" rowspan="2">Logo</th>';
	$head .= '<th scope="col" rowspan="2">Favicon</th>';
	$head .= '<th scope="col" colspan="3">Rechtstexte</th>';
	$head .= '<th scope="col" rowspan="2">CMS</th>';
	$head .= '</tr>';
	$head .= '<tr class="center">';
	$head .= '<td class="small vertical">Impressum</td>';
	$head .= '<td class="small vertical">Datenschutz</td>';
	$head .= '<td class="small vertical">Barrierefreiheit</td>';
	$head .= '</tr>';	
	$head .= '</thead>'."\n";
	$output = $head;
	
	if ($wppagebreaks) {
	   $output .= '<tbody>';
	    foreach ($tablecell as $cell) {
		
		$breakcnt = $breakcnt + 1;
		if ($breakcnt == $breakat) {
		    $breakcnt = 0;
		    $output .= '</tbody>';
		    $output .= '</table>';
		    
		   $output .= '<!--nextpage-->'."\n";
		   
		   $output .= $head;
		    $output .= '<tbody>';
		}
		$output .= $cell;

	    }
	    $output .= '</tbody>';
	} else {
	    $output .= '<tbody>';
	    $output .= $table;
	    $output .= '</tbody>';
	}
	
	$output .= '</table>';
	return $output;
	
    }
    
    return $table;
    
}



function get_index() {
    global $ignore_domains;
/*
 * Statistikdatei:
 * 
 * URI:  www.statistiken.rrze.fau.de/webauftritte/domains/
 * Index-Name:
 *    domains-index-$Monat.$Jahr.csv
 *   mit $Monat = Nummer des Monats mit führender Null
 *   mit $Jahr = Letzten beiden Ziffern des Jahres
 * CSV Spalten der domain-index-Datei:
 * 
 *  1. Fortlaufende Nummer
 *  2. URL
 *  3. Fachbereich (aus URL)
 *  4. DocRoot (leer)
 *  5. WMP Id  
 *  6. WMP RefStatus
 *  7. WMP RefServertyp 
 */
    
    $month = date("m");
    $year = date("y");
    $indexurl = 'https://statistiken.rrze.fau.de/webauftritte/domains/domains-index-'.$month.'.'.$year.'.csv';
    $index = new cURL();
    $data = $index->get($indexurl);

    if ($data['meta']['http_code'] == 404 ) {
	// try previous month
	$month = date("m") -1;
	if ($month == 0) {
	    $month = 12;
	    $year = date("y") -1;	    
	}
	if (($month <10) && (strlen($month) < 2)) {
	    $month = '0'.$month;
	}
	$indexurl = 'https://statistiken.rrze.fau.de/webauftritte/domains/domains-index-'.$month.'.'.$year.'.csv';
	// echo "Missing current month index file. Trying last: ".$indexurl."\n";
	$data = $index->get($indexurl);
    }
    $res = array();
    
    if ($data['meta']['http_code'] >= 200 && $data['meta']['http_code'] < 400) {

	$lines = explode("\n",$data['content']);
	foreach ($lines as $line) {
	    if ((!empty($line)) && (!empty(trim($line)))) {
		$testdomain = false;
		list($num, $url, $fachbereich, $docroot, $wmpid, $wmprefstatus, $wmprefservertyp) = explode("\t",$line);
		if ($ignore_domains) {
		    
		    foreach ($ignore_domains as $ignore) {
			if (preg_match($ignore, $url)) {
			    $testdomain = true;
			}
		    }
		}
		
		if (strpos($url, "http") == FALSE) {
		    $url = 'http://'.$url;
		}

		$res[$num]['url'] = $url;
		$res[$num]['fachbereich'] = $fachbereich;
		$res[$num]['wmp_id'] = intval($wmpid);
		$res[$num]['wmp_refstatus'] = intval($wmprefstatus);
		$res[$num]['wmp_refservertyp'] = intval($wmprefservertyp);
		if ($testdomain) {
		   $res[$num]['internal_domain'] = 1;
		} else {
		   $res[$num]['internal_domain'] = 0;
		}
		
	    }
	}
    }
    return $res;
}

function get_servertyp_by_id($id) {
   
    global $servertypen;
   if (($id) && (isset($servertypen[$id]))) { 
       return $servertypen[$id];
    }
    return;

}

function get_status_by_id($id) {
    $refstatus = [
	0 => "Unbekannt",
	1 => "Beantragt",
	2 => "Reserviert",
	3 => "Einrichtungsphase",
	4 => "Aktiv",
	5 => "Deaktiviert",
	6   => "Gesperrt",
	7   => "Wartet auf Autorisierung",
	8   => "Autorisierung erfolgt",
	9   => "Weggezogen",
	10  => "In Betrieb mit Warnung",
	12  => "Domainname reserviert",
	11  => "Deaktiviert durch Bot"
    ];
     if (($id) && (isset($refstatus[$id]))) { 
       return $refstatus[$id];
    }
    return;
}