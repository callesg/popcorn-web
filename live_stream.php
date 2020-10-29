<?php

if(!isset($_GET['f'])){
	exit;
}

$public_link = rawurldecode($_GET['f']);
if(strpos($public_link, '../') !== FALSE){
	exit;//exit if there is shinanigans
}

$ext = pathinfo($public_link, PATHINFO_EXTENSION);

$full_path = $_SERVER["DOCUMENT_ROOT"].$public_link;
if(!stat($full_path)){
	exit;//Exit if it not a real file
}

$ffprobe_dat = shell_exec('ffprobe '.escapeshellarg($full_path).' 2>&1');
list($header, $info) = explode($full_path, $ffprobe_dat);

$stream_data = array(
	'ext' => $ext,
	'v_codec' => false,
	'a_codec' => false,
);
$isMP4 = false;
if($ext == 'mp4'){
	$isMP4 = true;
}
if(strpos($info, 'h264') !== FALSE){
	$stream_data['v_codec'] = 'h264';
}elseif(strpos($info, 'h265') !== FALSE ||
	strpos($info, 'hevc') !== FALSE){
	$stream_data['v_codec'] = 'h265';
}elseif(strpos($info, 'mpeg4') !== FALSE){
	$stream_data['v_codec'] = 'mpeg4';
}
if(strpos($info, 'aac') !== FALSE){
	$stream_data['a_codec'] = 'aac';
}elseif(strpos($info, 'mp3') !== FALSE){
	$stream_data['a_codec'] = 'mp3';
}elseif(strpos($info, 'ac3') !== FALSE){
	$stream_data['a_codec'] = 'ac3';
}

//var_dump($info, $stream_data);
//exit;

$isAAC = false;
if($stream_data['a_codec']){
	$isAAC = true;
}

$isH265 = false;
if(in_array($stream_data['v_codec'], array('h265'))){
	$isH265 = true;
}
$isH264 = false;
if(in_array($stream_data['v_codec'], array('h264'))){
	//we count h265 as ok in some circumatances 2020-05-11 why? i am removing this.
	$isH264 = true;
}


//echo $info;
//var_dump($stream_data,$isMP4, $isAAC, $isH264);
//exit;
if($isMP4 && $isAAC && $isH264){
	header('Location: '.$public_link);
	//echo "No need to convert anything\n";
}else{
	if(!$isAAC && !$isH264){
		//echo "Reencode audio and video\n";
		start_ffmpeg($full_path, true, true, $stream_data);
	}else{
		if(!$isAAC){
			//echo "Reencode the audio\n";
			start_ffmpeg($full_path, true, false, $stream_data);
		//}elseif(!$isH264 && !$isH265){//use this if we want to keep H265 streams They do sort of work on some apple devices
		}elseif(!$isH264){
			//echo "Reencode the video\n";
			start_ffmpeg($full_path, false, true, $stream_data);
		}else{
			//echo "Replace the container\n";
			start_ffmpeg($full_path, false, false, $stream_data);
		}
	}
}

function start_ffmpeg($file, $reencode_audio, $reencode_video, $stream_data){

	$filename = pathinfo($file, PATHINFO_FILENAME);
	$outputfile = 'videos/'.$filename.'.m3u8';
	$stream = '&';
	$output = '-f segment -segment_list_flags cache -segment_list_size 0 -segment_time 60 -segment_list /tmp/'.$outputfile.' /tmp/videos/file%03d.ts';
	$audio_encode = '-c:a copy';
	if($reencode_audio){
		$audio_encode = '-c:a libfdk_aac';
	}
	$video_encode = '-c:v copy';
	$extra_tag = '';
	if($reencode_video){
		$video_encode = '-c:v h264_nvenc -preset fast -pix_fmt yuv420p';
	}else{
		$tag = '';
		if($stream_data['v_codec'] == 'h265'){
			//we cant live stream h265 as ffmpeg does not tag the h265 contents with -tag:v hvc1 and it cant be fixed in segmentation formating
			//So we chnage the output format since we are only copying it should go fast anyway
			$tag = '-tag:v hvc1 ';
			$stream = '';
		}

	}

	//If we are just changing the container we can wait until the entire procces is done it is so fast anyway
	if(!$reencode_video && !$reencode_audio){
		$stream = '';
	}
	if($stream == ''){
		$outputfile = 'videos/'.$filename.'.mp4';
		$output = $tag.'/tmp/'.$outputfile;
	}
	$input_decoder = '';
	if($stream_data['v_codec'] == 'mpeg4'){
		$input_decoder = '-c:v mpeg4_cuvid';
	}

	if(file_exists('/tmp/'.$outputfile)){
		header("Location: ".$outputfile);
		return;
	}

	//Clear old things
	shell_exec('killall ffmpeg');
	deleteDir('/tmp/videos');
	@mkdir('/tmp/videos');

	shell_exec('ffmpeg -y '.$input_decoder.' -i '.escapeshellarg($file).' '.$extra_tag.' '.$video_encode.' '.$audio_encode.' '.$output.' >/tmp/ffmpeg.log 2>&1 '.$stream);

	if($stream != ''){
		//Wait until the conversion has started
		$t = 0;
		while($t<40*5){
			usleep(200000);
			if(file_exists('/tmp/'.$outputfile)){
				break;
			}
			$t++;
		}
	}
	header("Location: ".$outputfile);
}


function deleteDir($dirPath) {
    if (! is_dir($dirPath)) {
	    return;
    }
    if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
        $dirPath .= '/';
    }
    $files = glob($dirPath . '*', GLOB_MARK);
    foreach ($files as $file) {
        if (is_dir($file)) {
            deleteDir($file);
        } else {
            unlink($file);
        }
    }
    rmdir($dirPath);
}
