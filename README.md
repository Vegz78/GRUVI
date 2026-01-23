# GRUVI 
### (.generate.random.URLs_for.viewing.images.)
Image file URL list generator for displaying a list of image files on a web server or for screensaver/slideshow of random images via the Image Viewer app(photo frame) on the Squeezebox/Logitech/Lyrion Touch, Radio etc. 

![alt text](https://github.com/Vegz78/GRUVI/blob/master/Images/Play.gif)


## Features, Prerequisites, Installation & Usage on LMS:
See my original post: https://forums.slimdevices.com/showthread.php?108498-Announce-GRUVI-generate-random-URLs_for-viewing-image

## Features


Feel free to copy, modify and use as you want. The script does what it's supposed to on my home system and won't be very actively supported, updated or maintained.

## Updates history
2026.01.23: Extended functionality as a command line tool, added URL arguments, added option to search all image files in selected folders, cleaned up image thumbs file handling and support for newer versions of ImageMagick, tested on Windows, Linux and MacOS and some other small bugfixes and improvements<br>
2026.01.12: Added support for bmp, cr2, gif, heic, png, tiff and webp in addition to jpeg, new ability to run on Windows in addition to Linux and MacOS, and various bug fixes and clean-ups<br>
2021.10.30: Bugfixed a race condition and added option for GRUVI logo and output buffer flush for faster display of image URL list from gruvi.php<br>
2021.10.28: Added support for running directly in LMS' internal web server and choice between serial and parallel conversion of image files.<br>
2020.09.10: Added the possibility to choose multiple image folders with internal weighting of selections from each folder.<br>
