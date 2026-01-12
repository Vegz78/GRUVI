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


//GLOBAL SETTINGS
$LIST_SIZE = 60; //Number of random images to be processed and included in the image URL list
$FILE_AGE_MAX = 2880; //Time in minutes before a random image file pointed to in the list is changed
$CAPTIONS = True; //True = Captions ON, False = Captions OFF
$GRUVI_LOGO = True; //True = GRUVI logo as first image = ON, False = GRUVI logo OFF
$IMG_SOURCE = array("//Path_to/_image_folder1", "C:/Path_to_/image_folder2"); //Path to one or more image folders,
									//always forward slashes, also on WIndows, and no trailing slashes
$SOURCE_WEIGHT = array(0.3, 0.7);	//Relative weights between the above chosen image folders. There must be as many 
									//weights as the number of folders in the $IMG_SOURCE above and add up to 1 exactly
$URL_ROOT = 'http://192.168.x.y:9000/html/'; //Host web server address on internal LMS web server
//$URL_ROOT = 'http://192.168.x.y/'; //Host web server address on most independent web servers
$IMAGE_ROOT = 'gruvi_img'; //Storage folder for converted images in gruvi's working directory
$PARALLEL_CONVERT = False;  //If multiple $IMG_SOURCEs, each can be converted in parallel, for faster execution,
							//but this takes a toll on weaker, e.g. RPi, servers
//IMAGE FORMAT SETTINGS
//Format support between different ImageMagick versions vary, older versions typically do not support .HEIC and .WEBP
$BMP = True;
$CR2 = True;
$GIF = True;
$HEIC = True;
$JPEG = True;
$PNG = True;
$TIFF = True;
$WEBP = True;


//Function for running the generated image conversion tasks
function ConvertImages($fp, $doLoop=False) {
	global $WINDOWS, $IMAGE_ROOT, $BEGIN_NAME;
	fclose($fp);
	if (!$WINDOWS) {
		exec('chmod 777 ' . escapeshellarg($IMAGE_ROOT . $BEGIN_NAME));
		exec(escapeshellarg($IMAGE_ROOT . $BEGIN_NAME) . ' >> /dev/null 2>&1 &');
	}else {
		if (!$doLoop) {
			exec("type " . escapeshellarg($IMAGE_ROOT . $BEGIN_NAME) . " | cmd 2>&1");
		}else {
			//Looping through lines in the convert commands file, since Windows pipes with "cmd"
			//does not allow certain special characters even in double quoted commands
			$lines = file($IMAGE_ROOT . $BEGIN_NAME, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			if ($lines !== false) {
				foreach ($lines as $line) {
					exec($line . " 2>&1");
				}
			} else {
				echo "Error: Could not read the file.";
			}
		}
	}
}


//Check operating system
$WINDOWS = False;
if (strtoupper(substr(php_uname('s'), 0, 3)) === 'WIN') {
	$WINDOWS = True;
}

//Clean up paths
$IMAGE_ROOT_UNMODIFIED = $IMAGE_ROOT;
$IMAGE_ROOT = realpath($IMAGE_ROOT) . DIRECTORY_SEPARATOR;


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


//Check whether the lock file has not been cleanly deleted on any previous runs, perhaps by an interrupted run
$lockFolder = $IMAGE_ROOT . 'gruvi.lock';
$safeTime = 5;
if (is_dir($lockFolder) && (time() - filemtime($lockFolder))/60 > $safeTime) {
	rmdir($lockFolder);
}


//Check if the local optimized copies of images exist or must be created OR if they are too old and must be replaced with new images OR
//if they're too small, which is assumed to be a Gruvi fill-images from the first run.
$fileArray = array();  //Array of files which should be updated or created
for ($i = 1; $i <= $LIST_SIZE; $i++) {
	if (!file_exists("{$IMAGE_ROOT}{$i}{$FILE_SUFFIX}") || (time() - filemtime("{$IMAGE_ROOT}{$i}{$FILE_SUFFIX}"))/60 > $FILE_AGE_MAX || filesize("{$IMAGE_ROOT}{$i}{$FILE_SUFFIX}") < 5120) {
		$fileArray[] = $i;
	}
}


//Special case where no image no. 1 exists, typically the first run of the script, where all image files need to be generated before
//the player's image viewer starts. A background shell job for generating the correct amount of Gruvi fill-images is here generated and run.
if (!file_exists("{$IMAGE_ROOT}1{$FILE_SUFFIX}")) {
	$fp = fopen ($IMAGE_ROOT . $BEGIN_NAME, 'w');
	fwrite($fp, 'convert -background \'#0005\' -fill white -gravity center -size ' . escapeshellarg($X_WIDTH) . 'x' . escapeshellarg($Y_HEIGHT) . ' -pointsize  60 caption:GRUVI ' . escapeshellarg($IMAGE_ROOT) . '1' . escapeshellarg($FILE_SUFFIX) . "\n");
	$fileArray = array();
	$fileArray[] = 1;
	for ($i=2; $i <= $LIST_SIZE; $i++) {
		fwrite($fp, ($WINDOWS ? "copy " : "cp ") . "{$IMAGE_ROOT}1{$FILE_SUFFIX} {$IMAGE_ROOT}{$i}{$FILE_SUFFIX}\n");
		$fileArray[] = $i;
	}
	fwrite($fp, ($WINDOWS ? "copy " : "cp ") . "{$IMAGE_ROOT}1{$FILE_SUFFIX} {$IMAGE_ROOT}gruvi_logo{$FILE_SUFFIX}\n");
	ConvertImages($fp);
}


//Update the file for the players with the URL list for the Image Viewer applet to read
$indexList = array();
for ($i = 1; $i <=  $LIST_SIZE; $i++) {
	$indexList[] = $i;
}
shuffle($indexList); //For random image viewer starts
$fp = fopen($FILE_NAME, 'w');
if ($GRUVI_LOGO) {
	fwrite($fp, $URL_ROOT . $IMAGE_ROOT_UNMODIFIED . "/gruvi_logo" . $FILE_SUFFIX ."\n");
}
for ($i = 0; $i <=  $LIST_SIZE-1; $i++) {
	fwrite($fp, $URL_ROOT . $IMAGE_ROOT_UNMODIFIED . "/" . $indexList[$i] . $FILE_SUFFIX ."\n");
}
fclose($fp);


//Send image URL file to Image Viewer via gruvi.php HTML output
//Flush output buffer to allow web server to show URL list before gruvi.php is finished
//Solution inspired by https://stackoverflow.com/a/14469376/12802435
readfile($FILE_NAME);
if (ob_get_contents()) {
	ob_end_flush();
}
flush();
session_write_close();


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

//Check if conversions from a previous run of gruvi.php is already running, exit to prevent system overload
//Solution borrowed from https://www.exakat.io/en/prevent-multiple-php-scripts-at-the-same-time/
$lockFolder = $IMAGE_ROOT . 'gruvi.lock';
if (@mkdir($lockFolder, 0700)) {
}else {
	exit("Did not suceed in creating gruvi.lock folder.\nMaybe 5 minutes from the safetime variable has not yet passed or folder in use?");
}


//Figure out regex from IMAGE FORMAT SETTINGS
$formatArray = array($BMP, $CR2, $GIF, $HEIC, $JPEG, $PNG, $TIFF, $WEBP);
$regExpression = '/^(?!._).+';
$counter = 0;
foreach($formatArray as $index => $supported) {
	if ($supported) {
		if ($counter >= 1) $regExpression .= '|';
		switch ($index) {
			case 0: $regExpression .= '(.bmp)'; break;
			case 1: $regExpression .= '(.cr2)'; break;
			case 2: $regExpression .= '(.gif)'; break;
			case 3: $regExpression .= '(.hei[cf])'; break;
			case 4: $regExpression .= '(.jpe?g)'; break;
			case 5: $regExpression .= '(.png)'; break;
			case 6: $regExpression .= '(.tif?f)'; break;
			case 7: $regExpression .= '(.webp)'; break;
		}
		$counter++;
	}
}
$regExpression .= '$/i';


//Start image files processing
//If parallel conversion not allowed, open file once
if (!$PARALLEL_CONVERT) {
	$fp = fopen ($IMAGE_ROOT . $BEGIN_NAME, 'w');
}

 //For each image folder that needs processing
for ($x=0; $x<$noOfSources;$x++){

	//Traverse directory and subdirectories recursively and populate array with filenames
	$fileArraySize = $sourceDist[$x];
	$fileNameArray = array();
	$flags = FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS;
	$Directory = new RecursiveDirectoryIterator($IMG_SOURCE[$x], $flags);
	$Iterator = new RecursiveIteratorIterator($Directory, RecursiveIteratorIterator::LEAVES_ONLY);
	$Regex = new RegexIterator($Iterator, $regExpression, RecursiveRegexIterator::MATCH, RegexIterator::USE_KEY);
	$Regex->rewind();

	foreach($Regex as $name) {
		array_push($fileNameArray, $name);
	}


	//Shuffle the array, and generate and run shell script to extract and resize the needed number of random new image files
	shuffle($fileNameArray);

	//If parallel conversion allowed, open file for every image folder
	if ($PARALLEL_CONVERT) {
		$fp = fopen ($IMAGE_ROOT . $BEGIN_NAME, 'w');
	}

	//For each image file that needs processing in each folder
	for ($i = 0; $i <= $fileArraySize-1; $i++) {

		$exploded = ($WINDOWS) ? explode("\\", realpath($fileNameArray[$i])) : $exploded = explode("/", realpath($fileNameArray[$i]));
		$event = $exploded[count($exploded)-2]; //Makes parent directory available as string for caption text
		$fileName = $exploded[count($exploded)-1];  //Makes file name available as string for caption text

		if ($WINDOWS) {
			$captionArgs = ($CAPTIONS) ? '-background "#0005" -fill white -gravity west -size "' . $X_WIDTH . 'x' . $C_HEIGHT . '" -pointsize "' . $P_SIZE . '" caption:"' . $fileName . '\n' . $event . '" -gravity south -composite ' : '';
			fwrite($fp, 'convert "' . realpath($fileNameArray[$i]) . '" -auto-orient -background none -resize "' . $X_WIDTH . 'x' . $X_WIDTH . '" -gravity center -extent "' . $X_WIDTH . 'x' . $Y_HEIGHT . '" "' . $captionArgs . realpath($IMAGE_ROOT . $fileArray[$fileNameIndex] . $FILE_SUFFIX) . '"' . PHP_EOL);
		}else {
			$captionArgs = ($CAPTIONS) ? '\\( -background \'#0005\' -fill white -gravity west -size \'' . $X_WIDTH . 'x' . $C_HEIGHT . '\' -pointsize \'' . $P_SIZE . '\' caption:\'' . $fileName . '\n' . $event . '\' \\) -gravity south -composite ' : '';
			fwrite($fp, '/usr/bin/convert \\( \'' . $fileNameArray[$i] . '\' -auto-orient -background none -resize \'' . $X_WIDTH . 'x' . $X_WIDTH . '\' -gravity center -extent \'' . $X_WIDTH . 'x' . $Y_HEIGHT . '\' \\) ' . $captionArgs . '\'' . $IMAGE_ROOT . $fileArray[$fileNameIndex] . $FILE_SUFFIX . '\'' . PHP_EOL);
		}

		//Early break out of loop if there are fewer images in the $IMG_SOURCE folder than expected from the weighted distribution between
		//the folders according to $SOURCE_WEIGHT. Fixes an earlier out-of-bounds bug when this was true.
		if ($i >= count($fileNameArray)-1) {
			break;
		}else {
			$fileNameIndex++;
		}
	}


	//Remove lock folder
	if ($x == ($noOfSources - 1)) {
		//fwrite($fp, 'rmdir ' . escapeshellarg($lockFolder) . "\n");
		rmdir($lockFolder);
	}

	//If parallel conversion is allowed, process each folder immediately
	if ($PARALLEL_CONVERT) {
		ConvertImages($fp, $WINDOWS);
	}
}

//If parallel conversion is not allowed, process serially when all folders are finished set up
if (!$PARALLEL_CONVERT) {
	ConvertImages($fp, $WINDOWS);
}
}

?>
