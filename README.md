# popcorn-web
A web-interface for the popcorn time api. Allows for popcorntime like streaming from any device with a modern web broswer like an Ipad or Iphone.
popcorn-web connects to the popcorntime API to list media, uses rtorrent to download torrents and ffmpeg to stream h264 video.

## Screenshot (blured for intellectual rights reasons)
![Screenshot](interface_blur.jpg)

## Install

### Getting the code
In your webfolder belonging to your php webserver 
```bash
git clone https://github.com/callesg/popcorn-web
cd popcorn-web

mkdir db
chmod 777 db

mkdir /tmp/videos
ln -s /tmp/videos videos
```
### Setup folders
Create 3 folders
* -downloaded_folder-   folder for finished downloads needs to be accesible on the web.
* -downloading_folder-  folder for torrents that are currently downloading.
* -torrent_folder-      folder where .torrents will be placed. 


example
```bash
sudo mkdir /media/downloaded_folder /media/downloading_folder /media/torrent_folder
chmod 777 /media/downloaded_folder /media/downloading_folder /media/torrent_folder

```
configure __config.json__ add the 3 folders to config.json and fill in the public web path to the -downloaded_folder-


### ffmpeg
Build and install __ffmpeg__ and __ffprobe__ with h264 support (used to convert media files that can't nativly be streamed to a browser)

https://trac.ffmpeg.org/wiki/CompilationGuide/Ubuntu


### rtorrent

install __rtorrent__
```bash
sudo apt-get install rtorrent
```


setup __~/.rtorrent.rc__ rtorrent needs to monitor the /media/torrent_folder and place downloaded files in the /media/downloaded_folder and downloading files in the /media/downloading_folder
* replace /media/downloaded_folder with the folder for finished downloads
* replace /media/downloading_folder with the folder for torrents that are currently downloading.
* replace /media/torrent_folder with the folder where .torrents will be placed.
```
system.method.set_key = event.download.finished,move_complete,"d.set_directory=/media/downloaded_folder/;execute=/opt/torrent_done.sh,$d.get_base_path="

directory = /media/downloading_folder

schedule = watch_directory,5,5,load_start=/media/torrent_folder/*.torrent

```

create __/opt/torrent_done.sh__ a script that moves finished downloads to /media/downloaded_folder

* replace -downloaded_folder- with the folder for finished downloads
* replace -user- with the username the rtorrent is uning as
```bash
#!/bin/bash
chown -R -user-:www-data "$1"
mv -u "$1" "/media/downloaded_folder/"
```
