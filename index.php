<?php
$api_config = json_decode(file_get_contents('api-info.json'), true);

foreach($_GET AS $k => $v){
	$_GET[$k] = htmlspecialchars($v);
}

if(!isset($_GET['type'])){
	$_GET['type'] = key($api_config);
}
$type_val = $_GET['type'];
if(!isset($api_config[$type_val])){
	echo "non valid type";
	exit;
}
$type = $api_config[$type_val];

if(!isset($_GET['genre'])){
	$_GET['genre'] = current($type['genres']);
}
if(!isset($_GET['sort'])){
	$_GET['sort'] = current($type['sorts']);
}
$keywords = '';
if(isset($_GET['keywords'])){
	$keywords = $_GET['keywords'];
}

$imdb_id = NULL;
if(isset($_GET['imdb_id'])){
	$imdb_id = $_GET['imdb_id'];
}


?><html>
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
<?php foreach($api_config AS $k => $tp){ ?>
<a href="?type=<?= $k ?>"><?= $k ?></a>
<?php } ?>
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

include("../torr/downloadCache.php");

$apiurl = 'https://movies-v2.api-fetch.sh/';

if(isset($imdb_id)){
	$details = json_decode(GetPage($apiurl.substr($type_val, 0, -1).'/'.$imdb_id, false, false, 'json', false, true), true);
?>
<div class="row">
	<div style="float:left;">
		<img height="190" src="<?= $details["images"]["poster"] ?>">
	</div>
	<div style="float:left;margin:1em;">
		<h2><?= $details["title"] ?></h2>
		<h5><?= $details["year"] ?> • <?= $details["runtime"] ?> min • <?= $details["status"] ?> • <?= implode($details["genres"], ', ') ?> • <?= $details["rating"]["percentage"]/10 ?>/10</h5>
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
<?php foreach($episodes AS $episode){
	//$tors = array();
	//$episode['torrents']
?>
		<tr>
			<td><?= $episode['episode'] ?></td>
			<td><?= $episode['title'] ?></td>
			<td><?= date('Y-m-d', $episode['first_aired']) ?></td>
			<td>
<?php foreach($episode['torrents'] AS $tid => $tor){
	if(is_string($tid)){
?>
<a href="/torr/get.php?link=<?= urlencode($tor['url']) ?>"><?= $tid ?></a>
<?php }
} ?>
</td>
		</tr>
<?php } ?>
		<table>
	</div>
<?php }
	}else{
foreach($details['torrents']['en'] AS $tid => $tor){
	if(is_string($tid)){
?>
<a href="/torr/get.php?link=<?= urlencode($tor['url']) ?>"><?= $tid ?></a>
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
	//var_dump($filmpages);
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
?>
<div style="float:left;width:12em;height:24em;">
<!--<p><?= $movie["synopsis"] ?></p>-->
<a href="?type=<?= $type_val ?>&imdb_id=<?= $movie["imdb_id"] ?>">
	<img height="190" src="<?= $movie["images"]["poster"] ?>">
	<h4><?= $movie["title"] ?></h4>
	<h6 style="color:rgb(70,70,70);"><?= $movie["year"] ?></h6>
</a>
<?php foreach($movie['torrents']['en'] AS $res => $tor){ ?>
<p><a href="/torr/get.php?link=<?= urlencode($tor['url']) ?>"><?= $res ?></a></p>
<?php } ?>
</div>
<?php

		}
	//var_dump($movies);
	
	}
}
?>
</body>
</html>
