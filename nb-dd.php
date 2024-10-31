<?php
/*
   Plugin Name: Netbiscuits Device Detection
   Description: Take advantage of the world's largest device database, within your server code, by adding Netbiscuits Device Detection to your site.  Make intelligent, device-specific adaptations to your pages before sending them to the user's device; send only what they need, and nothing they don't.
   Version: 1.7
   Author: Netbiscuits GmbH
   Text Domain: nb_device_detection
*/

// Only load admin if we are in WP Admin
if ( is_admin() ):
	include( plugin_dir_path( __FILE__ ) . 'admin.php' );
endif;

// Always load the dd class
class dd {

	/**
	* Plugin version, used for cache-busting of style and script file references.
	*
	* @since	1.0.0
	* @var		string
	*/
	protected $version = '2.0.0';

	/**
	* Unique identifier for your plugin.
	*
	* Use this value (not the variable name) as the text domain when internationalizing strings of text. It should
	* match the Text Domain file header in the main plugin file.
	*
	* @since	1.0.0
	* @var		string
	*/
	protected $plugin_slug = 'dd';

	/**
	* Instance of this class.
	*
	* @since	1.0.0
	* @var		object
	*/
	protected static $instance = null;

	/**
	* Result object
	*
	* @var
	*/
	public $response;


	/**
	* If true debug information will print in the footer
	*
	* @var bool
	*/
	public $debug = false;

	/**
	* Slug of the plugin screen.
	*
	* @since	1.0.0
	* @var		string
	*/
	protected $plugin_screen_hook_suffix = null;

	/**
	* Activates or Deactivates the caching
	*
	* @var bool
	*/
	public $do_transient_cache = false;

	/**
	*expiration the caching
	*
	* @var bool
	*/
	public $transient_cache_expire_time = HOUR_IN_SECONDS;

	/**
	* Configuration Standart-Object from Wordpress Backend
	*
	* @var StdObj
	*/
	public $config;

	private $header_blacklist = array (
		'Accept-Encoding',
		'Host',
		'Cookie',
		'Connection',
		'HTTP_CLASSIFIER',
		'Content-Type',
		'Content-Length',
		'Cache-Control',
		'Pragma'

	);

	/**
	* Internal, to differentiate Smartphone versus Feature Phone.
	*
	* @since	1.0.0
	*/
	private function isSmartphone() {
		/*$os = strtolower($this->response->device->operatingsystem);
		$osversion = strtolower($this->response->device->operatingsystemversion);
		// Smartphone whitelist
		if ( strpos($os, 'android') > -1
			|| strpos($os, 'ios') > -1
			|| strpos($os, 'webos') > -1
			|| strpos($os, 'firefox') > -1
			|| strpos($os, 'bada') > -1
			|| (strpos($os, 'windows phone') > -1 && $osversion >= 7.5)
			|| (strpos($os, 'blackberryos') > -1 && $osversion >= 7)
		) {
			return true;
		} else {
			return false;
		}*/
		return ( $this->response->device->issmartphone === true ? true : false );
	}

	/**
	* Initialize the plugin by setting localization, filters, and administration functions.
	*
	* @since	1.0.0
	*/
	private function __construct() {
		/*
		*	Don't add this function to the 'plugins_loaded' hook because it allows Image Converter to initialize
		*	before DD has executed its action_init() method. Just directly call the DD action_init() function.
		*/
		// add_action( 'plugins_loaded', array($this, 'action_init') );

		$this->debug = get_option('nb_dd_debug');

		/*$this->do_transient_cache = get_option('nb_dd_cache_active');
		$int_nb_dd_cache_exprire_time = intval(get_option('nb_dd_cache_exprire_time'));
		if($int_nb_dd_cache_exprire_time > 0) {
				$this->transient_cache_expire_time =	$int_nb_dd_cache_exprire_time;
		}*/

		$this->action_init();

		if ($this->debug) {
			add_action('wp_footer', array(
				$this,
				'action_debug'
			));
		}

	}

	/**
	* Return an instance of this class.
	*
	* @since	1.0.0
	*
	* @return	object	A single instance of this class.
	*/
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if (null == self::$instance) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	* Fired when the plugin is activated.
	*
	* @since	1.0.0
	*
	* @param	boolean $network_wide	True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog.
	*/
	public static function activate($network_wide) {
		// TODO: Define activation functionality here
	}

	/**
	* Fired when the plugin is deactivated.
	*
	* @since	1.0.0
	*
	* @param	boolean $network_wide	True if WPMU superadmin uses "Network Deactivate" action, false if WPMU is disabled or plugin is deactivated on an individual blog.
	*/
	public static function deactivate($network_wide) {
		// TODO: Define deactivation functionality here
	}

	private function do_action_init() {

		// we want a silent fail here...
		function exception_error_handler($errno, $errstr, $errfile, $errline ) {
			//throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
			return;
		}
		set_error_handler("exception_error_handler");

		// get the config from the DB
		$client_key = get_option('nb_dd_token');
		$account	= get_option('nb_dd_account');
		$profile	= get_option('nb_dd_profile');

		// make sure we have an account set-up
		if ( !$client_key || !$account || !$profile ) {
			return;
		}

		// create config object
		$this->config				= new stdClass();
		$this->config->client_key 	= $client_key;
		$this->config->account		= $account;
		$this->config->profile		= $profile;

		// create request config object
		$requestConfig				= new stdClass();
		$requestConfig->profile		= ($profile != "" ? $profile : "global_all");
		$requestConfig->nonce		= round(microtime(true) * 1000) . "_random";
		$requestConfig->timestamp 	= round(microtime(true) * 1000);
		$requestConfig->uri			= "/ds/detect/signed/account/" . $account;
		$requestConfig->token		= $client_key;

		// prepare request config for curl
		$request_data = json_encode($requestConfig);

		// start URL for curl
		$url = 'https://dcs.netbiscuits.net/ds/detect/signed/account/' . $account;

		// configure header
		$nb_dd_header = array(
			"Content-Type: text/plain",
			"Content-Length: " . strlen($request_data),
			"Accept-Encoding: gzip, deflate, sdch"
		);
		
		// loop through apache headers, removing any that are blacklisted
		$header = apache_request_headers();
		foreach ($header as $k => $v) {
			foreach ($this->header_blacklist as $remove) {
				if (strtolower($k) == strtolower($remove)) {
					unset($header[$k]);
				}
			}
		}
		// loop again to push remaining headers into $nb_dd_header
		foreach ($header as $k => $v) {
			$nb_dd_header[] = $k . ": " . $v;
		}

		// set-up curl
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $nb_dd_header);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $request_data);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30000); //in miliseconds

		// execute curl
		$fetch = curl_exec($ch);

		// decompress results
		if ( !function_exists('gzdecode') ) {
			function gzdecode($data) { 
				return gzinflate( substr( $data, 10, -8 ) ); 
			}
		}
		$response = gzdecode($fetch);

		// get curl reponse into
		$curl_info = curl_getinfo($ch);
		$http_code = $curl_info["http_code"];

		// check for errors
		$error = "";
		if (curl_errno($ch)) {
			$error = 'curl error: ' . curl_error($ch) . ', ' . $url . ', ' . $request_data;
		} else if ($http_code != 200) {
			$error = 'http error: ' . $http_code;
			if (trim($response) != "") {
				$error .= "; dd error: " . $response;
			}
		} else {
			// if no errors, push decoded response into parent object
			$this->response = json_decode($response);
		}

		// if there are errors, write them to the log file
		if ($error != "") {
			file_put_contents(dirname(__FILE__)."/nb_dd_failures.log", date("c") . " - " . $error . "\n", FILE_APPEND);
			/* AJAX check	*/
			if(empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
				echo "<!-- $error -->";
			}
		}
		
		// close curl
		curl_close($ch);

		// set-up the friendly API
		$this->setupAPI();

		// close this down
		return true;
	}

	/**
	* Get the cache key (if we're using it)
	*
	* @since	1.0.0
	*/
	private function getCachekey() {
		$cache_key = "";
		$header = apache_request_headers();
		foreach ($header as $k => $v)
			foreach ($this->header_blacklist as $remove)
				if ($k == $remove)
					unset($header[$k]);
		foreach ($header as $k => $v)
			$cache_key .= $k . ": " . $v;

		return "nb_dd_" . md5($cache_key);
	}

	/**
	* Determine if the device is a smartphone
	*
	* @since	1.0.0
	*/

	private function isDeviceMobilePhoneAreSmartphone() {
		$deviceoperatingsystem = $this->DeviceOperatingSystem();
		$deviceoperatingsystemversion = $this->DeviceOperatingSystemVersion();

		// Smartphone whitelist
		if ( strpos($deviceoperatingsystem, 'Android') > -1
			|| strpos($deviceoperatingsystem, 'iOS') > -1
			|| strpos($deviceoperatingsystem, 'WebOS') > -1
			|| strpos($deviceoperatingsystem, 'Firefox') > -1
			|| strpos($deviceoperatingsystem, 'Bada') > -1
			|| (strpos($deviceoperatingsystem, 'Windows Phone') > -1 && $deviceoperatingsystemversion >= 7.5)
			|| (strpos($deviceoperatingsystem, 'BlackberryOS') > -1 && $deviceoperatingsystemversion >= 7)
		) {
			return true;
		} else {
			return false;
		}
	}

	/**
	* Main functionality
	*
	* @since	1.0.0
	*/
	public function action_init() {
		if (!$this->do_transient_cache)
			return $this->do_action_init();

		$cache_key = $this->getCachekey();

		if ($this->debug)
			delete_transient($cache_key);

		if ( !($nb_dd = get_transient($cache_key)) ) {
			$this->do_action_init();
			// set caches when model was detected or desktop browser detected
			if ($this->response->device->model != "Unidentified" || $this->response->browser->type == "Desktop-Browser") {
				set_transient($cache_key, $this->response, $this->transient_cache_expire_time);
			}
			else {
				return;
			}
		}
		$this->response = get_transient($cache_key);
	}

	/**
	* Getters methods (old API, keeping for backwards-compatibility)
	*
	* @since	1.0.0
	*/
	public function __get($property) {
		if (property_exists($this, $property)) {
			return $this->$property;
		} else {
			return "Unknown";
		}
	}
	// browser parameteres
	public function BrowserCanTelMakeCall() {
		return $this->response->browser->cantelmakecall;
	}
	public function BrowserCssCanSvg() {
		return $this->response->browser->css->cansvg;
	}
	public function BrowserCssFontSizeSuitable() {
		return $this->response->browser->css->fontsizesuitable;
	}
	public function BrowserDomCanInlineSvg() {
		return $this->response->browser->html5->parsing->cansvg;
	}
	public function BrowserMediaCanVideoElement() {
		return $this->response->browser->html5->video->canvideo;
	}
	public function BrowserType() {
		return $this->response->browser->type;
	}
	public function BrowserModel() {
		return $this->response->browser->model;
	}
	// device parameteres
	public function DeviceType() {
		return $this->response->device->type;
	}
	public function DeviceModel() {
		return $this->response->device->modelname;
	}
	public function DeviceVendor() {
		return $this->response->device->vendor;
	}
	public function DeviceOperatingSystem() {
		return $this->response->device->operatingsystem;
	}
	public function DeviceOperatingSystemVersion() {
		return $this->response->device->operatingsystemversion;
	}
	public function DeviceIsDesktop() {
		return ($this->DeviceType() === 'Desktop-Browser' || $this->DeviceType() === 'Bot');
	}
	public function DeviceIsTablet() {
		return ($this->DeviceType() === 'Tablet');
	}
	public function DeviceIsSmartphone() {
		return (($this->DeviceType() === 'Mobile Phone' || $this->DeviceType() === 'MediaPlayer') && $this->isDeviceMobilePhoneAreSmartphone());
	}
	public function DeviceIsFeaturephone() {
		return (($this->DeviceType() === 'Mobile Phone' || $this->DeviceType() === 'MediaPlayer') && !$this->isDeviceMobilePhoneAreSmartphone());
	}
	// hardware parameteres
	public function HardwareDisplay() {
		return $this->response->hardware->display;
	}
	public function HardwareDisplayDiagonalSize() {
		return $this->response->hardware->display->diagonalsize;
	}
	public function HardwareDisplayHeight() {
		return $this->response->hardware->display->height;
	}
	public function HardwareDisplayWidth() {
		return $this->response->hardware->display->width;
	}
	// image parameteres
	public function ImageMaxHeight() {
		return $this->response->image->maxheight;
	}
	public function ImageMaxWidth() {
		return $this->response->image->maxwidth;
	}
	// technology parameteres
	public function TechnologyGeneration() {
		return $this->response->device->profile->technologygeneration;
	}

	/**
	* Parameter API (future parameters commented out for now)
	*
	* @since	2.0.0
	*/
	public function setupAPI() {
		// normalize device type
		$devicetype = $this->response->device->type;
		if ($devicetype === 'Desktop-Browser' || $devicetype === 'Computer') {
			$devicetype = 'desktop';
		}
		if (($devicetype === 'Mobile Phone' || $devicetype === 'MediaPlayer') && $this->isSmartphone()) {
			$devicetype = 'smartphone';
		}
		if (($devicetype === 'Mobile Phone' || $devicetype === 'MediaPlayer') && !$this->isSmartphone()) {
			$devicetype = 'featurephone';
		}
		$this->browsertype = strtolower($this->response->browser->type);

		// Assign static parameters
		$this->vendor = strtolower($this->response->device->vendor);
		$this->model = strtolower($this->response->device->modelname);
		$this->os = strtolower($this->response->device->operatingsystem);
		$this->osversion = strtolower($this->response->device->operatingsystemversion);
		$this->browser = strtolower($this->response->browser->model);
		$this->browservendor = strtolower($this->response->browser->vendor);
		$this->devicetype = strtolower($devicetype);
		$this->bot = ($this->devicetype === 'bot' || $this->response->device->isbot);
		$this->desktop = $this->devicetype === 'desktop';
		$this->tablet = $this->devicetype === 'tablet';
		$this->smartphone = $this->devicetype === 'smartphone';
		$this->featurephone = $this->devicetype === 'featurephone';
		$this->phone = ($this->smartphone || $this->featurephone);
		$this->touch = $this->response->browser->cantouchapi;
		//$this->3dtouch
		$this->pointer = $this->response->browser->canpointerapi;
		$this->fontface = $this->response->browser->css->{'3fonts'}->canfontface;
		$this->localstorage = $this->response->browser->html5->storage->canlocalstorage;
		$this->geolocation = $this->response->browser->html5->location->cangeolocation;
		//$this->serviceworker
		//$this->pushnotification
		$this->positionfixed = $this->response->browser->css->canpositionfixed;
		//$this->positionsticky
		//$this->fullscreen
		//$this->flexbox
		//$this->flexboxlegacy
		//$this->flexboxtweener
		//$this->flexwrap
		$this->imagemaxwidth = $this->response->image->maxwidth; // Numeric
		$this->imagemaxheight = $this->response->image->maxheight; // Numeric
		//$this->pixelratio
		//$this->picture
		//$this->srcset
		//$this->webp
		$this->webm = $this->response->browser->html5->video->canwebm;
		$this->ogg = $this->response->browser->html5->video->cantheora;
		$this->h264 = $this->response->browser->html5->video->canh264;
		$this->svg = $this->response->browser->html5->parsing->cansvg;
		$this->svginline = $this->response->browser->css->cansvginline;
		$this->svgclippath = $this->response->browser->css->cansvgclippath;
		//$this->svgimg
		$this->csstransition = $this->response->browser->css->{'3transitions'}->cantransition;
		$this->csstransform = $this->response->browser->css->{'3transforms'}->cantransform;
		$this->csstransform3d = $this->response->browser->css->{'3transforms'}->cantransform3d;
		$this->csstransformorigin = $this->response->browser->css->{'3transforms'}->cantransformorigin;
		$this->cssperspective = $this->response->browser->css->{'3transforms'}->canperspective;
		$this->cssperspectiveorigin = $this->response->browser->css->{'3transforms'}->canperspectiveorigin;
		//$this->cssfilter
	}

	/**
	* If "Show Debug?" is checked in Admin, output dd object to page; triggered via wp_footer
	*
	* @since	2.0.0
	*/
	public function action_debug() {
		echo '<pre>';
		print_r($this);
		echo '</pre>';
	}
}

// Create dd instance
dd::get_instance();

// For older code
function dcs() {
	$dcs = dd::get_instance();
	$GLOBALS['DCS'] = $dcs;
	$GLOBALS['dd'] = $dcs;
	return $dcs;
}

// New API
function dd() {
	$dd = DCS::get_instance();
	$GLOBALS['dd'] = $dd;
	return $dd;
}