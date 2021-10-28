<?php 

/*  Random URL image file list generator for the Image Viewer app on the Squeezebox Touch, Radio etc. with:
	-Random selection of files and random shuffle of the lists between every call to this PHP-file on
	 independent web servers, ,or with periodic cron jobs on the LMS internal web server.
	-Locally cached copies of the images with reduced sizes to minimize load on the LMS, players and network
	-Image files hosted on a web server of your chosing and fittet to the players' screen sizes
	-Choice between images with or without capitons
	-Choice of number of cached files and expiry time for reload of new batches of random images
	-Added support for multiple image folders with internal probability weighing for the random image selection
	 and with the choice between serial and parallel processing of the ImageMagick conversions between folders.

    Choice between total independence from the LMS server on any web server, or run in LMS internal web server. 

    No more versions or plugins conflicts, no more spinning
    disks on the NAS, heavy machinery required to run 24/7 etc... ;-)

    This was my very first complete PHP-script ever, so sorry for the bad and ugly coding, without
    any error or exception handling. It barely does what it's supposed to do, but gets the job done
    for the time being, if you can get it to work. I'm on the forum from time to time, but don't have
    the resources to provide any reliable support. 

    Feel free to copy and improve as you feel fit! I did so much research and copying myself,
    that I don't remember all the people I should be thanking.


    !! Special thanks to the primus motors on the Squeezebox community forum who keeps both the
    best audio community and the best music server/player ecosystem ever still alive and kicking!!

    Nice also if any suggestions for improvements to this script were posted back on this forum thread!


    by vegz78...

    Originally posted in this Squeezebox forum thread:
    https://forums.slimdevices.com/showthread.php?108498-Announce-GRUVI-generate-random-URLs_for-viewing-images
*/



//Global variables
$LIST_SIZE = 60; //Number of random images to be processed and  included in the image URL list
$FILE_AGE_MAX = 2500; //Time in minutes before a random image file pointed to in the list is changed
$CAPTIONS = 1; //0 = Captions OFF, 1 = Captions ON
$IMG_SOURCE = array("/mnt/Path_to_image_folder1", "/mnt/Path_to_image_folder2"); //Path to one or more image folders
$SOURCE_WEIGHT = array(0.3, 0.7); //Relative weights between the above chosen image folders

$URL_ROOT = 'http://192.168.x.y:9000/html/'; //Host web server address on internal LMS web server
//$URL_ROOT = 'http://192.168.x.y/'; //Host web server address on most independent web serversr
$IMAGE_ROOT = 'gruvi_img/'; //Storage folder for images in the www-directory
$PARALLEL_CONVERT = False;  //If multiple $IMG_SOURCEs, each can be converted in parallel, but takes a toll on weaker e.g. RPi servers


//Check consistency between number of sources and weights, exit otherwise
$noOfSources = count($IMG_SOURCE);
$noOfWeights = count($SOURCE_WEIGHT);
$sumOfWeights = array_sum($SOURCE_WEIGHT);
if ($noOfSources != $noOfWeights || $sumOfWeights != 1) {
	exit("Inconsistency between number of sources and weights.\n");
}


//Identify player type and set corresponding player specific variables
$isTouch = false;
$FILE_NAME = 'sbradio.txt';
$FILE_SUFFIX = '.jpg';
$BEGIN_NAME = '.radio_start';
$X_WIDTH = 320;
$Y_HEIGHT = 240;
$C_HEIGHT = 22;
$P_SIZE = 10;
if(isset($_SERVER['HTTP_USER_AGENT']) || isset($argv[1]) && $argv[1] == "Touch") {
	if(isset($_SERVER['HTTP_USER_AGENT'])) {
		$playerType = $_SERVER['HTTP_USER_AGENT']; //Identifier for the different player types
	} elseif(isset($argv[1]) && $argv[1] == "Touch") {
		$playerType = "fab4";
	}
	if (strpos($playerType, 'fab4') !== false) {
		$isTouch = true; //TRUE if Squeezebox Touch, FALSE otherwise
		$FILE_NAME = 'sbtouch.txt'; //URL list file name for the Touch
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


//Distribute weighted number of images pr. source by use of the Largest Remainder Method
$fileArraySize = count($fileArray);
$sourceDist = array();
for ($i=0; $i<$noOfSources; $i++) {
	$sourceDist[] = floor($SOURCE_WEIGHT[$i]*$fileArraySize);
}
arsort($sourceDist);
$sourceSum = 0;
$index = array();
foreach ($sourceDist as $key => $val) {
	$index[] = $key;
	$sourceSum += $val;
}
$sourceDiff = $fileArraySize-$sourceSum;
for ($i=0; $i<$sourceDiff; $i++) {
	$sourceDist[$index[$i]] += 1;
}


//Start image and file operations if any images are missing or old
$fileNameIndex = 0;
if ($fileArraySize >=1) {
if (!$PARALLEL_CONVERT) {
	$fp = fopen ($IMAGE_ROOT . $BEGIN_NAME, 'w');
}
for ($x=0; $x<$noOfSources;$x++){

	//Traverse directory and subdirectories recursively and populate array with filenames
	$fileArraySize = $sourceDist[$x];
	$fileNameArray = array();
	$Directory = new RecursiveDirectoryIterator($IMG_SOURCE[$x]);
	$Iterator = new RecursiveIteratorIterator($Directory);
	$Regex = new RegexIterator($Iterator, '/^.+(.jpe?g)$/i', RecursiveRegexIterator::GET_MATCH);

	foreach($Regex as $name => $Regex) {
		$fileNameArray[] = $name;
	}


	//Shuffle the array, and generate and run shell script to extract and resize the needed number of random new image files
	shuffle($fileNameArray);
	if ($PARALLEL_CONVERT) {
		$fp = fopen ($IMAGE_ROOT . $BEGIN_NAME, 'w');
	}
	for ($i = 0; $i <= $fileArraySize-1; $i++) {

		//Captions ON
		if ($CAPTIONS == 1) {
			$exploded = explode("/", $fileNameArray[$fileNameIndex]);
			$event = $exploded[count($exploded)-2]; //Makes parent directory available as string for caption text
			$fileName = $exploded[count($exploded)-1];  //Makes file name available as string for caption text

			//exec('/usr/bin/convert \\( ' . escapeshellarg($fileNameArray[$i]) . ' -auto-orient -background none -resize 480x480 -gravity center -extent 480x272 \\) \\( -background \'#0005\' -fill white -gravity west -size 480x25 -pointsize 11 caption:' . escapeshellarg($fileName. "\n") . escapeshellarg($event) . ' \\) -gravity south -composite ' . escapeshellarg($IMAGE_ROOT) . escapeshellarg($fileArray[$i]) . escapeshellarg($FILE_SUFFIX) . ' >> dev/null 2>&1 &');
			fwrite($fp, '/usr/bin/convert \\( \'' . $fileNameArray[$fileNameIndex] . '\' -auto-orient -background none -resize ' . escapeshellarg($X_WIDTH . 'x' . $X_WIDTH) . ' -gravity center -extent ' . escapeshellarg($X_WIDTH . 'x' . $Y_HEIGHT) . ' \\) \\( -background \'#0005\' -fill white -gravity west -size ' . escapeshellarg($X_WIDTH . 'x' . $C_HEIGHT) . ' -pointsize ' . escapeshellarg($P_SIZE) . ' caption:\'' . $fileName . '\n' . $event . '\' \\) -gravity south -composite ' . escapeshellarg($IMAGE_ROOT) . escapeshellarg($fileArray[$fileNameIndex]) . escapeshellarg($FILE_SUFFIX) . "\n");
		}

		//Captions OFF
		else {
			fwrite($fp, '/usr/bin/convert \'' . $fileNameArray[$fileNameIndex] . '\' -auto-orient -background none -resize ' . escapeshellarg($X_WIDTH . 'x' . $X_WIDTH) . ' -gravity center -extent ' . escapeshellarg($X_WIDTH . 'x' . $Y_HEIGHT) . ' ' . escapeshellarg($IMAGE_ROOT) . escapeshellarg($fileArray[$fileNameIndex]) . escapeshellarg($FILE_SUFFIX) . "\n");
		}
		$fileNameIndex++;
	}
	if ($PARALLEL_CONVERT) {
		fclose($fp);
		exec('chmod 777 ' . escapeshellarg($IMAGE_ROOT . $BEGIN_NAME));
		exec(escapeshellarg($IMAGE_ROOT . $BEGIN_NAME) . ' >> /dev/null 2>&1 &');
	}
}
if (!$PARALLEL_CONVERT) {
	fclose($fp);
	exec('chmod 777 ' . escapeshellarg($IMAGE_ROOT . $BEGIN_NAME));
	exec(escapeshellarg($IMAGE_ROOT . $BEGIN_NAME) . ' >> /dev/null 2>&1 &');
}
}


?>
