<?php 


/*  Random URL image file list generator for the Image Viewer app on the Squeezebox Touch, Radio etc. with:
	-Random selection of files and random shuffle of the lists between every call to this PHP-file
	-Locally cached copies of the images with reduced sizes to minimize load on the LMS, players and network
	-Image files hosted on a web server of your chosing and fittet to the players' screens
	-Choice between images with or without capitons
	-Choice of number of cached files and expiry time for reload of new batches of random images

    Totally independent from the LMS server etc. No more versions or plugins conflicts, no more spinning
    disks on the NAS, heavy machinery required to rund 24/7 etc... ;-)

    This was my very first complete PHP-script ever, so sorry for the bad and ugly coding, without
    any error or exception handling. It barely does what it's supposed to do, but gets the jobb done
    for the time being, if you can get it to work. I'm on the forum from time to time, but don't have
    the resources to provide any reliable support. 

    Feel free to copy and improve as you feel fit! I certainly did a lot of copying from a lot of
    amazing resources and competent and sharing people on the web. So much research and copying 
    that I don't remember all the people I should be thanking.


    !! Anyways, a special thanks to the primus motors on the Squeezebox community forum who keeps both
    the best audio community and the best music server/player ecosystem ever still alive and kicking!!

    Nice also if any suggestions for improvements to this script were posted back on this forum thread!


    by vegz78... */



//Global variables
$LIST_SIZE = 60; //Number of random images to be processed and  included in the image URL list
$FILE_AGE_MAX = 2500; //Time in minutes before a random image file pointed to in the list is changed
$CAPTIONS = 1; //0 = Captions OFF, 1 = Captions ON
$IMG_SOURCE = '/mnt/Some_path_to_your_images'; //Path to original large image files 

$URL_ROOT = 'http://192.168.x.x/'; //Host server address
$IMAGE_ROOT = 'sbtouch_img/'; //Storage folder for images in the www-directory


//Identify player type and set corresponding player specific variables
$isTouch = false;
$FILE_NAME = 'sbradio.lst';
$FILE_SUFFIX = '.jpg';
$BEGIN_NAME = '.radio_start';
$X_WIDTH = 320;
$Y_HEIGHT = 240;
$C_HEIGHT = 22;
$P_SIZE = 10;
if(isset($_SERVER['HTTP_USER_AGENT'])) { 
	$playerType = $_SERVER['HTTP_USER_AGENT']; //Identifier for the different player types
	if (strpos($playerType, 'fab4') !== false) {
		$isTouch = true; //TRUE if Squeezebox Touch, FALSE otherwise
		$FILE_NAME = 'sbtouch.lst'; //URL list file name for the Touch
		$FILE_SUFFIX = '_fab4.jpg'; //Image file suffix for the Touch
		$BEGIN_NAME = '.touch_start';
		$X_WIDTH = 480;
		$Y_HEIGHT = 272;
		$C_HEIGHT = 25;
		$P_SIZE = 11;
	}
}


//Check if the local optimized copies of files exist or must be created OR if they are too old and must be replaced with new images OR
//if they're too small, which is assumed to be a Gruvi fill-images from first run.
$fileArray = array();  //Array of files which should be updated or created
for ($i = 1; $i <= $LIST_SIZE; $i++) {
	if (!file_exists("{$IMAGE_ROOT}{$i}{$FILE_SUFFIX}") || (time() - filemtime("{$IMAGE_ROOT}{$i}{$FILE_SUFFIX}"))/60 > $FILE_AGE_MAX || filesize("{$IMAGE_ROOT}{$i}{$FILE_SUFFIX}") < 5120) {
		$fileArray[] = $i;
	}
}


//Special case where no image no. 1 exists, typically first run of script, where all image files need to be generated before the player's 
//image viewer starts. A background shell job for generating the correct amount of Gruvi fill-images is here generated and run. 
if (!file_exists("{$IMAGE_ROOT}1{$FILE_SUFFIX}")) {
	$fp = fopen ($IMAGE_ROOT . $BEGIN_NAME, 'w');
	fwrite($fp, '/usr/bin/convert -background \'#0005\' -fill white -gravity center -size ' . escapeshellarg($X_WIDTH) . 'x' . escapeshellarg($Y_HEIGHT) . ' -pointsize  60 caption:GRUVI ' . escapeshellarg($IMAGE_ROOT) . '1' . escapeshellarg($FILE_SUFFIX) . "\n");
	$fileArray = array();
	$fileArray[] = 1;
	for ($i=2; $i <= $LIST_SIZE; $i++) {
		fwrite($fp, "cp {$IMAGE_ROOT}1{$FILE_SUFFIX} {$IMAGE_ROOT}{$i}{$FILE_SUFFIX}\n");
		$fileArray[] = $i;
	}
	fclose($fp);
	exec('chmod 777 ' . escapeshellarg($IMAGE_ROOT . $BEGIN_NAME));
	exec(escapeshellarg($IMAGE_ROOT . $BEGIN_NAME) . ' >> /dev/null 2>&1 &');
}


//Update the file for the players with the URL list for the Image Viewer applet to read
$indexList = array();
for ($i = 1; $i <=  $LIST_SIZE; $i++) {
	$indexList[] = $i;
}
shuffle($indexList); //For random image viewer starts
$fp = fopen($FILE_NAME, 'w');
for ($i = 0; $i <=  $LIST_SIZE-1; $i++) {
	fwrite($fp, $URL_ROOT . $IMAGE_ROOT . $indexList[$i] . $FILE_SUFFIX ."\n");
}
fclose($fp);


//Send image URL file to Image Viewer
readfile($FILE_NAME); 


//Number of image and file operations until the images and the image URL list is ready
$fileArraySize = count($fileArray);


//Start image and file operations if any images are missing or old
if ($fileArraySize >=1) {


	//Traverse directory and subdirectories recursively and populate array with filenames
	$fileNameArray = array();
	$Directory = new RecursiveDirectoryIterator($IMG_SOURCE);
	$Iterator = new RecursiveIteratorIterator($Directory);
	$Regex = new RegexIterator($Iterator, '/^.+(.jpe?g)$/i', RecursiveRegexIterator::GET_MATCH);

	foreach($Regex as $name => $Regex) {
		$fileNameArray[] = $name;
	}


	//Shuffle the array, and generate and run shell script to extract and resize the needed number of random new image files
	shuffle($fileNameArray);
	$fp = fopen ($IMAGE_ROOT . $BEGIN_NAME, 'w');
	for ($i = 0; $i <= $fileArraySize-1; $i++) {

		//Captions ON
		if ($CAPTIONS == 1) {
			$exploded = explode("/", $fileNameArray[$i]);
			$event = $exploded[count($exploded)-2]; //Makes parent directory available as string for caption text
			$fileName = $exploded[count($exploded)-1];  //Makes file name available as string for caption text

			//exec('/usr/bin/convert \\( ' . escapeshellarg($fileNameArray[$i]) . ' -auto-orient -background none -resize 480x480 -gravity center -extent 480x272 \\) \\( -background \'#0005\' -fill white -gravity west -size 480x25 -pointsize 11 caption:' . escapeshellarg($fileName. "\n") . escapeshellarg($event) . ' \\) -gravity south -composite ' . escapeshellarg($IMAGE_ROOT) . escapeshellarg($fileArray[$i]) . escapeshellarg($FILE_SUFFIX) . ' >> dev/null 2>&1 &');
			fwrite($fp, '/usr/bin/convert \\( \'' . $fileNameArray[$i] . '\' -auto-orient -background none -resize ' . escapeshellarg($X_WIDTH . 'x' . $X_WIDTH) . ' -gravity center -extent ' . escapeshellarg($X_WIDTH . 'x' . $Y_HEIGHT) . ' \\) \\( -background \'#0005\' -fill white -gravity west -size ' . escapeshellarg($X_WIDTH . 'x' . $C_HEIGHT) . ' -pointsize ' . escapeshellarg($P_SIZE) . ' caption:\'' . $fileName . '\n' . $event . '\' \\) -gravity south -composite ' . escapeshellarg($IMAGE_ROOT) . escapeshellarg($fileArray[$i]) . escapeshellarg($FILE_SUFFIX) . "\n");
		}

		//Captions OFF
		else {
			fwrite($fp, '/usr/bin/convert \'' . $fileNameArray[$i] . '\' -auto-orient -background none -resize ' . escapeshellarg($X_WIDTH . 'x' . $X_WIDTH) . ' -gravity center -extent ' . escapeshellarg($X_WIDTH . 'x' . $Y_HEIGHT) . ' ' . escapeshellarg($IMAGE_ROOT) . escapeshellarg($fileArray[$i]) . escapeshellarg($FILE_SUFFIX) . "\n");
		}
	}
	fclose($fp);
	exec('chmod 777 ' . escapeshellarg($IMAGE_ROOT . $BEGIN_NAME));
	exec(escapeshellarg($IMAGE_ROOT . $BEGIN_NAME) . ' >> /dev/null 2>&1 &');
}


?>
