<?php

if(!isset($_GET['f'])){
	exit;
}

$TRY_CUDA = false;

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

$useNvenc = false;
$useCuvid = false;
if($TRY_CUDA){
	if(strpos($header, 'cuvid') !== FALSE){
		$useCuvid = true;
	}
	if(strpos($header, 'nvenc') !== FALSE){
		$useNvenc = true;
	}
}

$stream_data = array(
	'ext' => $ext,
	'v_codec' => false,
	'a_codec' => false,
);
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

$acceptable = array(
	'video' => false,
	'audio' => false,
	'container' => false,
);

if($ext == 'mp4'){
	$acceptable['container'] = true;
}

if($stream_data['a_codec']){
	$acceptable['audio'] = true;
}

//Sometimes h265 is acceptable depends on how new the client device is
if(in_array($stream_data['v_codec'], array('h264','h265'))){
	$acceptable['video'] = true;
}



if($acceptable['container'] && $acceptable['audio'] && $acceptable['video']){
	header('Location: '.$public_link);
	//echo "No need to convert anything\n";
}else{
	if(!$acceptable['audio'] && !$acceptable['video']){
		//echo "Reencode audio and video\n";
		start_ffmpeg($full_path, true, true, $stream_data);
	}else{
		if(!$acceptable['audio']){
			//echo "Reencode the audio\n";
			start_ffmpeg($full_path, true, false, $stream_data);
		}elseif(!$acceptable['video']){
			//echo "Reencode the video\n";
			start_ffmpeg($full_path, false, true, $stream_data);
		}else{
			//echo "Replace the container\n";
			start_ffmpeg($full_path, false, false, $stream_data);
		}
	}
}

function start_ffmpeg($file, $reencode_audio, $reencode_video, $stream_data){
	global $useNvenc, $useCuvid;
	$filename = pathinfo($file, PATHINFO_FILENAME);
	$outputfile = 'videos/'.$filename.'.m3u8';
	$outputfile = preg_replace('/\s/', '_', $outputfile);

	//Streaming mode is a mode where we try to convert the files as the client is digesting it(the stream ing is a bit iffy on the client side so give it a few minutes to buffer first)
	$stream = '&';
	$output = '-f segment -segment_list_flags cache -segment_list_size 0 -segment_time 60 -segment_list /tmp/'.$outputfile.' /tmp/videos/file%03d.ts';
	$audio_encode = '-c:a copy';
	if($reencode_audio){
		$audio_encode = '-c:a libfdk_aac';
	}
	$video_encode = '-c:v copy';
	$extra_tag = '';
	if($reencode_video){
		if($useNvenc){
			$video_encode = '-c:v h264_nvenc -preset fast -pix_fmt yuv420p';
		}else{
			$video_encode = '-c:v libx264 -pix_fmt yuv420p';//use yuv420p sometimes other pixelformats dont work so well
		}
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
		if($useCuvid){
			$input_decoder = '-c:v mpeg4_cuvid';
		}
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
