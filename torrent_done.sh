#!/bin/bash
chown -R $USER:www-data "$1"
mv -u "$1" "/media/downloaded_folder/"
