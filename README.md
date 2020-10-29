# popcorn-web
A web-interface for the popcorn time api, connect with ffmpeg and rtorrrent for a seamless experience.

## Screenshot (blured for intellectual rights reasons)
![Screenshot](interface_blur.jpg)

## Install
In your webfolder belonging to your php webserver 
```bash
git clone https://github.com/callesg/popcorn-web
cd popcorn-web
mkdir db
chmod 777 db
ln -s /tmp/videos videos
```
### Other dependecies
Build and install ffmpeg and ffprobe with h264 support

https://trac.ffmpeg.org/wiki/CompilationGuide/Ubuntu

install rtorrent sudo apt-get install rtorrent

edit config.json with the 4 required folders.

setup ~/.rtorrent.rc
* replace -downloaded_folder- with the folder for finished downloads
* replace -downloading_folder- with the folder for torrents that are currently downloading.
* replace -torrent_folder- with the folder where torrents will be placed
```
system.method.set = group.seeding.ratio.command, d.close=, d.erase=

system.method.set_key = event.download.finished,move_complete,"d.set_directory=-downloaded_folder-;execute=/opt/torrent_done.sh,$d.get_base_path="

directory = -downloading_folder-

schedule = watch_directory,5,5,load_start=-torrent_folder-/*.torrent

```

create /opt/torrent_done.sh
* replace -downloaded_folder- with the folder for finished downloads
```bash
#!/bin/bash
chown -R self:www-data "$1"
mv -u "$1" "-downloaded_folder-"
```
