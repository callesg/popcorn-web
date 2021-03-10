<?php
include "values.php";

?>
<?php
foreach($qualitys AS $name => $preset){
?>
<div style="border: 1px solid gray;">
	<h4><?= $name ?></h4>
	<p>bitrate: <?= $preset['bitrate'] ?> resolution: <?= implode('x', $preset['resolution']) ?></p>
</div>
<?php
}
?>

<style>body{background-color:rgb(23,24,27);color:white;font-family: Arial;}</style>
<script>
var imageAddr = "image-download-speed-2215981.jpg"; 
var downloadSize = 2215981; //bytes

function ShowProgressMessage(msg) {
    if (console) {
        if (typeof msg == "string") {
            console.log(msg);
        } else {
            for (var i = 0; i < msg.length; i++) {
                console.log(msg[i]);
            }
        }
    }
    
    var oProgress = document.getElementById("progress");
    if (oProgress) {
        var actualHTML = (typeof msg == "string") ? msg : msg.join("<br />");
        oProgress.innerHTML = actualHTML;
    }
}

function InitiateSpeedDetection() {
    ShowProgressMessage("Loading the image, please wait...");
    window.setTimeout(MeasureConnectionSpeed, 1);
};    

if (window.addEventListener) {
    window.addEventListener('load', InitiateSpeedDetection, false);
} else if (window.attachEvent) {
    window.attachEvent('onload', InitiateSpeedDetection);
}

function MeasureConnectionSpeed() {
    var startTime, endTime;
    var download = new Image();
    download.onload = function () {
        endTime = (new Date()).getTime();
        showResults();
    }
    
    download.onerror = function (err, msg) {
        ShowProgressMessage("Invalid image, or error downloading");
    }
    
    startTime = (new Date()).getTime();
    var cacheBuster = "?nnn=" + startTime;
    download.src = imageAddr + cacheBuster;
    
    function showResults() {
        var duration = (endTime - startTime) / 1000;
        var bitsLoaded = downloadSize * 8;
        var speedBps = (bitsLoaded / duration).toFixed(2);
        var speedKbps = (speedBps / 1024).toFixed(2);
        var speedMbps = (speedKbps / 1024).toFixed(2);
        ShowProgressMessage([
            "Your connection speed is:", 
            speedKbps + " kbps", 
            speedMbps + " Mbps"
        ]);
    }
}
</script>
<h2>Run a few times to see if you get outliers</h2>
<h4 id="progress">JavaScript is turned off, or your browser is realllllly slow</h4>
