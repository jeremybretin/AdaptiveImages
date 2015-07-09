<?php
define('ROOT', __DIR__);

class AdaptiveImages{

    /**
     *
     * Picture request
     *
     * @var String
     */
    private $_requestUri;

    /**
     *
     * Picture file name
     *
     * @var String
     */
    private $_fileName;

    /**
     *
     * Config
     *
     * @var Array
     */
    private $_config = array(
        "cachePath"         => "ai-cache",
        "jpgQuality"        => 75,
        "browserCache"      => 60*60*24*7,

        "pattern"           => array (
	        "pattern-name"  => array(
		        "width"		=> 90,
		        "height"	=> 90
	        ),
            "tiny"          => array(
                "width"     => 100,
                "height"    => 100
            ),
            "mini"          => array(
                "width"     => 300,
                "height"    => 300
            ),
            "maxi"          => array(
                "width"     => 500,
                "height"    => 500
            ),
            "16x9"          => array(
                "width"     => 900,
                "height"    => 506
            ),
            "fixed-height"  => array(
                "width"     => 0,
                "height"    => 200
            ),
            "fixed-width"   => array(
                "width"     => 200,
                "height"    => 0
            ),
        ),

        "resolutions"       => array(1382, 992, 768, 480)
    );

    /**
     *
     * Partern to use
     *
     * @var Array
     */
    private $_activePatern;

    /**
     *
     * Folder for genrated cache picture
     *
     * @var String
     */
    private $_cacheFolder;

    /**
     *
     * Constructor
     *
     * Check cache folder (create it if not)
     * Search the best picture pattern to use
     */
    public function __construct(){

        //Init call picture
        $this->_requestUri = parse_url(urldecode($_SERVER['REQUEST_URI']), PHP_URL_PATH);

        if(substr($this->_requestUri, 0,1) != "/") { // add / if it not begin by
            $this->_requestUri  = "/".$this->_requestUri;
        }

        //Create cache folder if not exist
        $this->cacheFolder();

        //Get the pattern if existe in request_uri
        $this->getPattern();

        //If not get the right size from resoltion (original adaptive image)
        if(is_null($this->_activePatern)){
            $this->_cacheFolder = $this->resolution();
            $this->_activePatern = array(
                "width"     => $this->_cacheFolder,
                "height"    => 0
            );
        }


        $cacheFile = ROOT . "/" . $this->_config['cachePath'] . "/". $this->_cacheFolder . $this->_requestUri;

        //Check if the original file is up to date
        if (file_exists($cacheFile)) {
            if (filemtime($cacheFile) >= filemtime( ROOT . $this->_requestUri )) {
                $this->sendImage($cacheFile);
            }
            else{
                unlink($cacheFile);
            }
        }

        $this->generateImage($cacheFile);

    }

    /**
     *
     * Generate Image
     *
     * @param String $cacheFile
     */
    private function generateImage($cacheFile){

        list($pictureDetails['width'], $pictureDetails['height']) = getimagesize(ROOT . $this->_requestUri);

        //if picture is to small send the original
        if( ($pictureDetails['width'] < $this->_activePatern['width'] and $this->_activePatern['width'] != 0) OR ($pictureDetails['height'] < $this->_activePatern['height'] and $this->_activePatern['height'] != 0)){
            $this->sendImage(ROOT . $this->_requestUri);
        }

        $imagick = new \Imagick( ROOT . $this->_requestUri);
        $imagick->setImageCompression(\Imagick::COMPRESSION_JPEG);
        $imagick->setImageCompressionQuality($this->_config['jpgQuality']);

        // if one of element size = 0 resize else crop
        if($this->_activePatern['width'] != 0 and $this->_activePatern['height'] != 0){
            $imagick->cropThumbnailImage( $this->_activePatern['width'], $this->_activePatern['height'] );
        }
        else{
            $imagick->resizeImage( $this->_activePatern['width'], $this->_activePatern['height'],  2, 1, FALSE);
        }

        $imagick->unsharpMaskImage(0 , 0.5 , 1 , 0.05);

        //Create the cache folder is not exist
        if (!is_dir( dirname($cacheFile) )) {
            mkdir( dirname($cacheFile) , 0755, true);
        }

        //Create the cache picture
        file_put_contents($cacheFile, "");
        $imagick->writeImage($cacheFile);

        $this->sendImage($cacheFile);
    }

    /**
     *
     * Check if pattern passed to url is right
     */
    private function getPattern(){
        $pattern = strstr( substr($this->_requestUri,1) , '/', true) ;
        if( isset( $this->_config['pattern'] [$pattern] )){
            $this->_activePatern = $this->_config['pattern'][$pattern];

            $this->_cacheFolder = $pattern;

            $this->_requestUri = str_replace("/$pattern/", "/", $this->_requestUri);
        }
    }

    /**
     *
     * Get the best resolution swith of material
     * Orgignal function from MATT WILCOX Adaptive-Images
     */
    private function resolution(){

        if (isset($_COOKIE['resolution'])) {
            $cookie_value = $_COOKIE['resolution'];

            // does the cookie look valid? [whole number, comma, potential floating number]
            if (! preg_match("/^[0-9]+[,]*[0-9\.]+$/", "$cookie_value")) { // no it doesn't look valid
                setcookie("resolution", "$cookie_value", time()-100); // delete the mangled cookie
            }
            else { // the cookie is valid, do stuff with it
                $cookie_data   = explode(",", $_COOKIE['resolution']);
                $client_width  = (int) $cookie_data[0]; // the base resolution (CSS pixels)
                $total_width   = $client_width;
                $pixel_density = 1; // set a default, used for non-retina style JS snippet
                if (@$cookie_data[1]) { // the device's pixel density factor (physical pixels per CSS pixel)
                    $pixel_density = $cookie_data[1];
                }
                $resolutions = $this->_config['resolutions'];
                rsort($resolutions); // make sure the supplied break-points are in reverse size order
                $resolution = $resolutions[0]; // by default use the largest supported break-point
                // if pixel density is not 1, then we need to be smart about adapting and fitting into the defined breakpoints
                if($pixel_density != 1) {
                    $total_width = $client_width * $pixel_density; // required physical pixel width of the image
                    // the required image width is bigger than any existing value in $resolutions
                    if($total_width > $resolutions[0]){
                        // firstly, fit the CSS size into a break point ignoring the multiplier
                        foreach ($resolutions as $break_point) { // filter down
                            if ($total_width <= $break_point) {
                                $resolution = $break_point;
                            }
                        }
                        // now apply the multiplier
                        $resolution = $resolution * $pixel_density;
                    }
                    // the required image fits into the existing breakpoints in $resolutions
                    else {
                        foreach ($resolutions as $break_point) { // filter down
                            if ($total_width <= $break_point) {
                                $resolution = $break_point;
                            }
                        }
                    }
                }
                else { // pixel density is 1, just fit it into one of the breakpoints
                    foreach ($resolutions as $break_point) { // filter down
                        if ($total_width <= $break_point) {
                            $resolution = $break_point;
                        }
                    }
                }
            }
        }
        /* No resolution was found (no cookie or invalid cookie) */
        if (!isset($resolution)) {
            // We send the lowest resolution for mobile-first approach, and highest otherwise
            $resolution = $this->isMobile() ? min($this->_config['resolutions']) : max($this->_config['resolutions']);
        }

        return $resolution;
    }

    /**
     *
     * Define if client material is mobile
     */
    private function isMobile() {
        $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
        return !!strpos($userAgent, 'mobile');
    }

    /**
     *
     * Check if cache folder exist.
     * If not create it
     */
    private function cacheFolder(){
        $cachePath = ROOT . "/" . $this->_config['cachePath'];
        if (!is_dir( $cachePath )) { // no
            if (!mkdir( $cachePath, 0755, true)) { // so make it
                if (!is_dir( $cachePath )) { // check again to protect against race conditions

                    // uh-oh, failed to make that directory
                    $this->sendErrorImage("Failed to create cache directory at: $cachePath");
                }
            }
        }
    }

    /**
     *
     * Send picture with error message
     *
     * @param String $message
     */
    private function sendErrorImage($message) {
        /* get all of the required data from the HTTP request */
        $document_root  = $_SERVER['DOCUMENT_ROOT'];
        $requested_uri  = parse_url(urldecode($_SERVER['REQUEST_URI']), PHP_URL_PATH);
        $requested_file = basename($requested_uri);
        $source_file    = $document_root.$requested_uri;

        if(!isMobile()){
            $is_mobile = "FALSE";
        }
        else {
            $is_mobile = "TRUE";
        }

        $im            = ImageCreateTrueColor(800, 300);
        $text_color    = ImageColorAllocate($im, 233, 14, 91);
        $message_color = ImageColorAllocate($im, 91, 112, 233);

        ImageString($im, 5, 5, 5, "Adaptive Images encountered a problem:", $text_color);
        ImageString($im, 3, 5, 25, $message, $message_color);
        ImageString($im, 5, 5, 85, "Potentially useful information:", $text_color);
        ImageString($im, 3, 5, 105, "DOCUMENT ROOT IS: $document_root", $text_color);
        ImageString($im, 3, 5, 125, "REQUESTED URI WAS: $requested_uri", $text_color);
        ImageString($im, 3, 5, 145, "REQUESTED FILE WAS: $requested_file", $text_color);
        ImageString($im, 3, 5, 165, "SOURCE FILE IS: $source_file", $text_color);
        ImageString($im, 3, 5, 185, "DEVICE IS MOBILE? $is_mobile", $text_color);

        header("Cache-Control: no-store");
        header('Expires: '.gmdate('D, d M Y H:i:s', time()-1000).' GMT');
        header('Content-Type: image/jpeg');
        ImageJpeg($im);
        ImageDestroy($im);
        exit();
    }

    /**
     *
     * Send picture with the right header
     *
     * @param String $filePath
     */
    private function sendImage($filePath = ""){

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($extension, array('png', 'gif', 'jpeg'))) {
            header("Content-Type: image/".$extension);
        } else {
            header("Content-Type: image/jpeg");
        }

        header("Cache-Control: private, max-age=".$this->_config['browserCache']);
        header('Expires: '.gmdate('D, d M Y H:i:s', time() + $this->_config['browserCache']).' GMT');
        header('Content-Length: '.filesize($filePath));

        readfile($filePath);
        exit();


    }

}

$AdaptiveImages = new AdaptiveImages();
