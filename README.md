# GRUVI 
### (.generate.random.URLs_for.viewing.images.)
Image file URL list generator for displaying a list of image files on a web server or for screensaver/slideshow of random images via the Image Viewer app(photo frame) on the Squeezebox/Logitech/Lyrion Touch, Radio etc. 

![alt text](https://github.com/Vegz78/GRUVI/blob/master/Images/Play.gif)

gruvi.php functions as a command line tool to fairly quickly produce lists of file
paths or URL-links to images from a selection of folders and their subfolders
Or to convert the images in similar folder selections to copies in the default
gruvi_img folder in the working directory of the script, with corresponding lists
of file paths or URL-links (LMS default) to these copies.

gruvi.php should work on any webserver with PHP support and when called directly from either
any webbrowser or any LMS player with a screen to display images. gruvi.php identifies the
SB Radio or Touch players automatically based on the HTTP_USER_AGENT provided by the
Image Viewer application and adjusts the corresponding image dimensions accordingly to show
correctly on their screens. Additional adjustments to settings in the gruvi.php script files
itself can be made to accomodate other players and screen sizes.

## Features, Prerequisites, Installation & Usage on LMS:
See my original post: https://forums.slimdevices.com/showthread.php?108498-Announce-GRUVI-generate-random-URLs_for-viewing-image

## Features

## Usage
```plaintext
php gruvi.php ARG1, ARG2, ... , ARGN       -Often needs sudo in front for permissions

Example:
php gruvi.php . RAND CONV 50               -Find and convert 50 random images from the
                                            current folder including subfolders
```
COMMAND LINE ARGUMENTS:
```plaintext
(Default argument values can be omitted)
FOLDER1, FOLDER2, ... , FOLDERN            -Folders where GRUVI should look for images to
                                            convert (EMPTY = use settings inside gruvi.php)
NUMBER, e.g. 50                            -NUMBER = The no. of image files to pick, e.g. 50
FULL                                       -FULL = Make list as big as the number of files
                                            actually found, instead of a preset selection
                                            size from the NUMBER argument or the 60
                                            setting inside gruvi.php
FILE|URL (default)                         -FILE = Make list as folder paths
                                            URL = Make list of images as web urls with
                                            server address inside gruvi.php as root
RAND|SORT (default)                        -RAND = Shuffle the list,
                                            SORT = Present list as found in the scanned
                                            directory tree
CONV|LINK (default)                        -CONV = Make converted copies of images to the
                                            folder specified in gruvi.php(default: gruvi_img)
                                            linked to in the file sbradio.txt,
                                            LINK = Only link to the found files, no
                                            conversions and links found in the file
                                            gruvi.txt
GRUVI|NOLOGO (default)                     -GRUVI = Add extra image file with the GRUVI logo
                                            NOLOGO = Do not at GRUVI logo image file
CAPT|NOCAP (default)                       -CAPT = Add image caption of file and folder names
                                            NOCAP = Do not add captions
CUST|STND (default)                        -CUST = Make image copies of custom dimensions
                                            STND = Standard dimension 320x240
CUSTOM DIMENSION e.g. 1280x720             -Custom image copy dimension as width x height
DRYRUN                                     -DRYRUN = Stop before execution, showing settings
HELP (or /? or -h or --help)               -HELP = Show this help screen

SPECIAL CASES AND FOR LMS/WEB:
php gruvi.php                              -Produces images for the Squeezebox Radio with
                                            settings set inside the gruvi.php script file
php gruvi.php Touch                        -Produces images for the Squeezebox Touch with
                                            settings set insidethe gruvi.php script file
php gruvi.php CUST                         -Produces images for a custom player screen or
                                            web browsers with settings set insidethe
                                            gruvi.php script file
```
IN WEB BROWSERS / THE SB PLAYER'S IMAGE VIEWER SOURCE SPECIFIC SETTINGS:
```plaintext
(replace with webserver's true IP address and root folder):
http://192.168.0.1/gruvi.php               -Produces image list directly with images for the
                                            SB Radio with settings set inside the gruvi.php
                                            script file
http://192.168.0.1/gruvi.php?player=Touch  -Produces image list directly with images for the
                                            SB Touch with settings set inside the gruvi.php
http://192.168.0.1/gruvi.php?player=Custom -Produces image list directly with images for a
                                            custom player, e.g. the O2 Joggler 800x480, with
                                            custom settings inside the gruvi.php script file

For the Lyrion/Logitech/Squeezebox Music Server's built in webserver
(replace with the LMS' true IP address):
http://192.168.0.1:9000/html/sbradio.txt   -Image list directly to images for the SB Radio
                                            with settings set inside the gruvi.php script
http://192.168.0.1:9000/html/sbtouch.txt   -Image list directly to images for the SB Touch
                                            with settings set inside the gruvi.php script

Since the LMS internal webserver does not support PHP, the images pointed to in the lists
must be produced by a scheduled task on the computer running LMS.
```


Feel free to copy, modify and use as you want. The script does what it's supposed to on my home system and won't be very actively supported, updated or maintained.

## Updates history
2026.01.23: Extended functionality as a command line tool, added URL arguments, added option to search all image files in selected folders, cleaned up image thumbs file handling and support for newer versions of ImageMagick, tested on Windows, Linux and MacOS and some other small bugfixes and improvements<br>
2026.01.12: Added support for bmp, cr2, gif, heic, png, tiff and webp in addition to jpeg, new ability to run on Windows in addition to Linux and MacOS, and various bug fixes and clean-ups<br>
2021.10.30: Bugfixed a race condition and added option for GRUVI logo and output buffer flush for faster display of image URL list from gruvi.php<br>
2021.10.28: Added support for running directly in LMS' internal web server and choice between serial and parallel conversion of image files.<br>
2020.09.10: Added the possibility to choose multiple image folders with internal weighting of selections from each folder.<br>
