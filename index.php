<?php

//include download library
include("downloadCache.php");

$config = json_decode(file_get_contents('config.json'), true);

$downloaded_folder = $config['downloaded_folder'];
$downloading_folder = $config['downloading_folder'];
$external_link_to_done_folder = rawurlencode($config['public_link_downloaded_folder']);
$torrent_folder = $config['torrent_folder'];

//download magnet link, creates a torrent file in a folder that is then picked up by rtorrent
if(isset($_GET['link'])){
	$linkSheme = parse_url($_GET['link'], PHP_URL_SCHEME);
	if($linkSheme === 'magnet'){
		$query = parse_url($_GET['link'], PHP_URL_QUERY);
		$parms = explode('&', $query);
		$prms = array();
		$infohash = 'notresolved';
		foreach($parms AS $prm){
			list($ky, $vl) = explode('=', $prm);
			if($ky == 'dn'){
				echo "torrent name: ".urldecode($vl)."<br>\n";
			}
			if($ky == 'xt'){
				$infohash = strtoupper(substr($vl, 9));
				if(strlen($infohash) == 32){
					require_once("Base32.php");
					$infohash = strtoupper(bin2hex(Base32\Base32::decode($infohash)));
				}
				echo "infohash: ".$infohash."<br>\n";
			}
		}
		$hash_file = 'db/'.$infohash.'.hash';
		if(file_exists($hash_file)){
			$result_file = file_get_contents($hash_file);
			if(file_exists($downloaded_folder.$result_file)){
				$_GET['downloading'] = $result_file;
			}
		}
		if(!isset($_GET['downloading'])){
			$meta_file = $downloading_folder.'/'.$infohash.'.meta';
			if(file_exists($meta_file)){
				echo "Found metafile $infohash<br>\n";
				try{

					//We checkout the 1.x version of https://github.com/arokettu/bencode/tree/1.x
					include("bencode/src/Bencode.php");
					include("bencode/src/Engine/Decoder.php");
					include("bencode/src/Util/Util.php");
					include("bencode/src/Exceptions/BencodeException.php");
					include("bencode/src/Exceptions/InvalidArgumentException.php");
					include("bencode/src/Exceptions/ParseErrorException.php");
					include("bencode/src/Exceptions/RuntimeException.php");
					


					$bencodeData = \SandFox\Bencode\Bencode::load($meta_file);
					if(isset($bencodeData['name'])){
						$_GET['downloading'] = $bencodeData['name'];
						file_put_contents($hash_file, $_GET['downloading']);
					}else{
						echo "no name yet<br>\n<pre>";
						//var_dump($bencodeData);
						echo "</pre>";
					}
				}catch (Exception $e){
					echo "Resolving info hash<br>\n";
					echo "metafile under construction<br>\n";

					echo "<pre>";
					//var_dump($e);
					echo "</pre>";
				}
			}else{
				file_put_contents($torrent_folder.'meta-'.$infohash.'.torrent', make_rtorrent_meta_file($_GET['link']));
			}
			echo('<style>body{background-color:rgb(23,24,27);color:white;font-family: Arial;}</style>');
			echo "initated loading of torrent<br>\n";
		}
	}
	if(!isset($_GET['downloading'])){
?>
<script>
	setTimeout(function(){
		document.location.reload();
	}, 5000)
</script>
have not resolved infohash yet
<?php
		exit;
	}
}
if(isset($_GET['downloading'])){
	echo('<style>body{background-color:rgb(23,24,27);color:white;font-family: Arial;}</style>');
	if(file_exists($downloaded_folder.$_GET['downloading'])){

		if(is_file($downloaded_folder.$_GET['downloading'])){
			echo "file is done redirecting";
			header('Location: live_stream.php?f='.$external_link_to_done_folder.rawurlencode($_GET['downloading']));
		}else{
			echo "torrent is a folder, find bigest file and redirect";
			$files = array();
			$downloaded_folder_len = strlen($downloaded_folder);
			getDirContents($downloaded_folder.$_GET['downloading'], $files);
			foreach ($files as $file) {
				$sortedfiles[substr($file, $downloaded_folder_len)] = filemtime($file);
			}
			arsort($sortedfiles);
			$sortedfiles = array_keys($sortedfiles);
			header('Location: live_stream.php?f='.$external_link_to_done_folder.rawurlencode($sortedfiles[0]));
		}
	}else{
?>
	downloading...<br>
	<?= $_GET['downloading'] ?><br>
	redirecting when done

<script>
	setTimeout(function(){
		document.location.search = '?downloading=<?= rawurlencode($_GET['downloading']) ?>';
	}, 7000)
</script>
<?php
	}
	exit;
}

//Load information about the API
$api_config = json_decode(file_get_contents('api-info.json'), true);
$apiurl = 'https://movies-v2.api-fetch.sh/';

//Clean the input so it can be printed
foreach($_GET AS $k => $v){
	$_GET[$k] = htmlspecialchars($v);
}

//if we have not selected a media type we pic one from the api
if(!isset($_GET['type'])){
	$_GET['type'] = key($api_config);
}
$type_val = $_GET['type'];

//Only media types from the API config is allowed
if(!isset($api_config[$type_val])){
	echo "non valid type";
	exit;
}
$type = $api_config[$type_val];

//Set a default genere
if(!isset($_GET['genre'])){
	$_GET['genre'] = current($type['genres']);
}
//Set default sorting field
if(!isset($_GET['sort'])){
	$_GET['sort'] = current($type['sorts']);
}

//Are we searching for something
$keywords = '';
if(isset($_GET['keywords'])){
	$keywords = $_GET['keywords'];
}

//are we trying to view a particular piece of media
$imdb_id = NULL;
if(isset($_GET['imdb_id'])){
	$imdb_id = $_GET['imdb_id'];
}
$details = NULL;
$extra = '';
if(isset($imdb_id)){
	$details = json_decode(GetPage($apiurl.substr($type_val, 0, -1).'/'.$imdb_id, false, false, 'json', false, true), true);
	$extra = ' - '.$details['title'].' ('.$details['year'].')';
}


?><html>
	<title>popcorn-web: <?= ucfirst($type_val) ?><?= $extra ?></title>
<style>
body{
	background-color:rgb(23,24,27);
	color:white;
	font-family: Arial;
}
.row::after {
  content: "";
  clear: both;
  display: table;
}
a:link, a:visited{
	color:white;
}
.filter{
	float:right;
}
.synopsis{
max-width:70em;
}
</style>
<body>
	<div>
<?php	foreach($api_config AS $k => $tp){ ?>
		<a href="?type=<?= $k ?>"><?= $k ?></a>
<?php } ?>
		<form method="get" class="filter">
			<input type="hidden" name="type" value="<?= $type_val ?>">
			<select name="genre" onchange="this.form.submit()">
<?php foreach($type['genres'] AS $genre){ ?>
				<option <?= ($genre == $_GET['genre'])?'selected':'' ?> value="<?= $genre ?>"><?= $genre ?></option>
<?php } ?>
			</select>
			<select name="sort" onchange="this.form.submit()">
<?php foreach($type['sorts'] AS $sort){ ?>
				<option <?= ($sort == $_GET['sort'])?'selected':'' ?> value="<?= $sort ?>"><?= $sort ?></option>
<?php } ?>
			</select>
		</form>
		<form method="get" class="filter">
			<input type="hidden" name="type" value="<?= $type_val ?>">
			<input type="text" name="keywords" value="<?= $keywords ?>">
		</form>
	</div>
<?php

if(isset($details)){
	$stat = "";
	if(isset($details["status"])){
		$stat = $details["status"]." • ";
	}
?>
<div class="row">
	<div style="float:left;">
		<img height="190" src="<?= $details["images"]["poster"] ?>">
	</div>
	<div style="float:left;margin:1em;">
		<h2><?= $details["title"] ?></h2>
		<h5><?= $details["year"] ?> • <?= $details["runtime"] ?> min • <?= $stat ?><?= implode($details["genres"], ', ') ?> • <?= $details["rating"]["percentage"]/10 ?>/10</h5>
		<p class="synopsis"><?= $details["synopsis"] ?></p>
	</div>
</div>
<div class="row">
<?php
	if(isset($details['episodes'])){
		$seasons = array();
		foreach($details['episodes'] AS $episode){
			if(!isset($seasons[$episode['season']])){
				$seasons[$episode['season']] = array();
			}
			$seasons[$episode['season']][] = $episode;
		}
		ksort($seasons);
		foreach($seasons AS $season => $episodes){?>
	<div>
		<h5>Season <?= $season ?></h5>
		<table>
<?php foreach($episodes AS $episode){?>
		<tr>
			<td><?= $episode['episode'] ?></td>
			<td><?= $episode['title'] ?></td>
			<td><?= date('Y-m-d', $episode['first_aired']) ?></td>
			<td>
<?php foreach($episode['torrents'] AS $tid => $tor){
	if(is_string($tid)){
?>
<a href="?link=<?= urlencode($tor['url']) ?>"><?= $tid ?></a>
<?php }
} ?>
</td>
		</tr>
<?php } ?>
		<table>
	</div>
<?php }
	}else{
foreach($details['torrents']['en'] AS $tid => $tor){
	if(is_string($tid)){
?>
<a href="?link=<?= urlencode($tor['url']) ?>"><?= $tid ?></a>
<?php }
}
} ?>
</div>
<pre>
<?php
	//var_dump($details);
?>
</pre>
<?php
}else{ //browsing
	$page = GetPage($apiurl.$type_val, false, false, 'json', false, true);
	$filmpages = json_decode($page, true);
	foreach($filmpages AS $id => $endpoint){
		if($id > 2){
			break;
		}

		$url = $apiurl.$endpoint.'?genre='.$_GET['genre'].'&sort='.$_GET['sort'];
		if(!empty($keywords)){
			$url .= '&keywords='.urlencode($keywords);
		}
		$movies = json_decode(GetPage($url, false, false, 'json', false, true), true);
		foreach($movies AS $movie){

			if(!isset($movie['torrents'])){
				$movie['torrents'] = array('en' => array());
			}
?>
<div style="float:left;width:12em;height:24em;">
<?php
			if(!isset($movie['torrents'])){
				echo("<pre>");
				var_dump($movie);
				echo("</pre>");
			}
?>
	<a href="?type=<?= $type_val ?>&imdb_id=<?= $movie["imdb_id"] ?>">
		<img height="190" src="<?= $movie["images"]["poster"] ?>">
		<h4><?= $movie["title"] ?></h4>
		<h6 style="color:rgb(70,70,70);"><?= $movie["year"] ?></h6>
	</a>
<?php foreach($movie['torrents']['en'] AS $res => $tor){ ?>
	<p><a href="?link=<?= urlencode($tor['url']) ?>"><?= $res ?></a></p>
<?php } ?>
</div>
<?php

		}
	}
}
?>
</body>
</html>
