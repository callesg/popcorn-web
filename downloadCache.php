<?php

if(!isset($CacheDir)){
	$CacheDir = "db";
}

if(!is_dir($CacheDir)){
    mkdir($CacheDir, 0755);
}

function GetPage($Url, $PageId = false, $Link = false, $type = 'html', $headers = false, $timeout = false){
	global $CacheDir;
	$PageDbDir = $CacheDir."/pages";
	if(!is_dir($PageDbDir)){
		mkdir($PageDbDir, 0755);
	}
	$UrlInfo = parse_url($Url);
	$HostDir = $PageDbDir."/".$UrlInfo['host'];
	if(!is_dir($HostDir)){
		mkdir($HostDir, 0755);
	}
	$PageCrc = $PageId;
	if($PageId === false){
		$PageCrc = crc32($Url);
	}
	$PageFile = $HostDir."/".$PageCrc.".".$type;
	$download = true;
	if(file_exists($PageFile)){
		$download = false;
		if($timeout && filemtime($PageFile) < strtotime('-1 day')){
			$download = true;
		}

	}
	if($download){
		$PageDat = curl_go($Url, false, false, $headers);
		if($PageDat === false){
			throw new Exception("Could not get URL: $Url");
		}
		file_put_contents($PageFile, $PageDat);
	}
	if($Link !== false){
		return($PageFile);
	}
	return(file_get_contents($PageFile));
}

if(!is_dir("/tmp/downloadCache")){
    mkdir("/tmp/downloadCache", 0755);
}
$coockie_file = @tempnam("/tmp/downloadCache/", "php_curl_cookie_torr");
$curl_Session = false;

function curl_go($url, $data = false, $refer = false, $headers = false, $RetHed = false){
	global $coockie_file, $curl_Session;
	if($curl_Session === false){
		$curl_Session = curl_init();
	}
	curl_setopt($curl_Session, CURLOPT_URL, $url );
	curl_setopt($curl_Session, CURLOPT_COOKIEFILE, $coockie_file);
	curl_setopt($curl_Session, CURLOPT_ENCODING, 'gzip');
	curl_setopt($curl_Session, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl_Session, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($curl_Session, CURLOPT_COOKIEJAR,  $coockie_file);
	curl_setopt($curl_Session, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($curl_Session, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($curl_Session, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.13; rv:61.0) Gecko/20100101 Firefox/61.0');

	if($refer !== false){
		curl_setopt($curl_Session, CURLOPT_REFERER, $refer);
	}
	if($headers !== false) {
		curl_setopt($curl_Session, CURLOPT_HTTPHEADER, $headers);
	}
	if($RetHed) {
		curl_setopt($curl_Session, CURLOPT_VERBOSE, 1);
		curl_setopt($curl_Session, CURLOPT_HEADER, 1);
	}

	if($data !== false){
		curl_setopt($curl_Session, CURLOPT_POSTFIELDS, $data);
	}

	$res = curl_exec($curl_Session);
	if(!$res){
		var_dump(curl_error($curl_Session));
	}
	return($res);
}
