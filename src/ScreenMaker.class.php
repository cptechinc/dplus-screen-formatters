<?php
    namespace Dplus\ScreenFormatters;

    use Dplus\ProcessWire\DplusWire;
    use Dplus\Content\HTMLWriter;
    
	/**
	 * Abstract file to build Screen classes from and to provide properties and methods
	 */
	abstract class ScreenMaker {
		use \Dplus\Base\ThrowErrorTrait;
		use \Dplus\Base\MagicMethodTraits;

		/**
		 * Session ID
		 * Used for getting the Dplus Generated File
		 * @var string
		 */
		protected $sessionID;
		
		/**
		 * User ID
		 * Used for getting the Dplus Generated File
		 * @var string
		 */
		protected $userID;

		/**
		 * Run in Debug
		 * @var bool
		 */
        protected $debug = false;
        
        /**
         * Is this formatter for a print page?
         * @var bool
         */
        protected $print;

		/**
		 * Screen Type
		 * @var string table | grid
		 */
        protected $screentype = 'table';

		/**
		 * Formatter Code
		 * @var string
		 */
		protected $code = '';

		/**
		 * Title of Screen
		 * @var string
		 */
		protected $title = '';

		/**
		 * File Name
		 * @var string
		 */
		protected $datafilename = '';

		/**
		 * Used for Test Files
		 * @var string
		 */
		protected $testprefix = '';

		/**
		 * JSON
		 * @var array
		 */
		protected $json = false; // WILL BE JSON DECODED ARRAY

		/**
		 * Array of the fields that will be used by the JSON array
		 * @var array
		 */
		protected $fields = false; // WILL BE JSON DECODED ARRAY

		/**
		 * Key Value array of Sections that exist I.E. header => Header, detail => Detail
		 * @var string
		 */
		protected $datasections = array();

		/**
		 * File Directory
		 * @var string /path/to/dir
		 */
		public static $filedir = false;
		
		/**
		 * File Directory for Test Files
		 * @var string /path/to/dir
		 */
		public static $testfiledir = false;

		/**
		 * File Directory for field Definitions
		 * @var string /path/to/dir
		 */
		public static $fieldfiledir = false;

		/**
		 * Columns Aliases that signifiy Tracking Number
		 * Used for generating Cell Data
		 * @var array
		 */
		protected static $trackingcolumns = array('Tracking Number');

		/**
		 * Columns Aliases that signifiy Phone Number
		 * Used for generating Cell Data
		 * @var array
		 */
		protected static $phonecolumns = array('phone', 'fax');

		/* =============================================================
			CONSTRUCTOR AND SETTER FUNCTIONS
		============================================================ */
		/**
		 * Constructor
		 * @param string $sessionID Session ID
		 */
		public function __construct($sessionID) {
			$this->sessionID = $sessionID;
		}

		/**
		 * Turn debug on or Off
		 * @param bool $debug
		 */
		public function set_debug($debug) {
			$this->debug = $debug;
		}
		
		/**
		 * Turn debug on or Off
		 * @param string $userID
		 */
		public function set_user($userID = 'default') {
			$this->userID = $userID;
		}
		
		/* =============================================================
			GETTER FUNCTIONS
		============================================================ */
		/**
		 * Looks and returns property value, also looks through
		 * @param  string $property Property name to get value from
		 * @return mixed           Value of Property
		 */
		public function __get($property) {
			if (property_exists($this, $property) !== true) {
				$this->error("This property ($property) does not exist");
				return false;
			}
			$method = "get_{$property}";
			if (method_exists($this, $method)) {
				return $this->$method();
			} else {
				return $this->$property;
			}
		}
		/**
		 * Returns the Fields Definition and loads them if not already defined
		 * @return array fields
		 */
		public function get_fields() {
			if (!$this->fields) {
				$this->load_fields();
			}
			return $this->fields;
		}

		/* =============================================================
			CLASS FUNCTIONS
        ============================================================ */
        /**
         * Returns the file path
         *
         * @return string
         */
        public function get_filepath() {
           return ($this->debug) ? self::$testfiledir.$this->datafilename.".json" : self::$filedir.$this->sessionID."-".$this->datafilename.".json";
        }

		/**
		 * Converts json into array, then if errors, then we make an array for Errors
		 * @return array
		 */
		public function process_json() {
            $json = json_decode(file_get_contents($this->get_filepath()), true);

            if (!empty($json)) {
                $this->json = $json;
            } else {
                $this->json = array(
                    'error' => true,
                    'errormsg' => "The $this->title JSON contains errors. JSON ERROR: ".json_last_error()
                );
            }
		}

		/**
		 * Returns the screen made
		 * @return string html of screen
		 */
		public function generate_screen() {
			return '';
		}

		/**
		 * Processes JSON then generates the screen
		 * USED BY Preview Screen formatter
		 * @param  bool $generatejavascript whether or not to use javascript
		 * @return string                    HTML for screen
		 */
		public function process_andgeneratescreen($generatejavascript = false) {
            $bootstrap = new HTMLWriter();
            
			if (file_exists($this->get_filepath())) {
				// JSON file will be false if an error occurred during file_get_contents or json_decode
				$this->process_json();

				if ($this->json['error']) {
					return $bootstrap->createalert('warning', $this->json['errormsg']);
				} else {
					return $generatejavascript ? $this->generate_screen() . $this->generate_javascript() : $this->generate_screen();
				}
			} else {
				return $bootstrap->createalert('warning', 'Information Not Available');
			}
		}

		/**
		 * Returns the javascript for this screen
		 * @return string Javascript
		 */
		public function generate_javascript() {
			return '';
		}

		/**
		 * Returns the show notes input
		 * @return string  HTML select
		 */
		public function generate_shownotesselect() {
			$bootstrap = new HTMLWriter();
			$array = array();
			foreach (DplusWire::wire('config')->yesnoarray as $key => $value) {
				$array[$value] = $key;
			}
			return $bootstrap->select('class=form-control input-sm|id=shownotes', $array);
		}

		/* =============================================================
			STATIC FORMATTING FUNCTIONS
		============================================================ */
		/**
		 * Generates the celldata based of the column, column type and the json array it's in, looks at if the data is numeric
		 * @param string $parent the array in which the data is contained
		 * @param string $column the key in which we use to look up the value, may contain the type
         * @param bool   $print  Format for print?
		 */
		public static function generate_formattedcelldata($parent, $column, $print = false) {
			$bootstrap = new HTMLWriter();
			$celldata = '';
            $qtyregex = "/(quantity)/i";
            
            // Format data
			if ($type == 'D') {
				$celldata = (strlen($parent[$column['id']]) > 0) ? date($column['date-format'], strtotime($parent[$column['id']])) : $parent[$column['id']];
			} elseif ($type == 'N') {
				if (is_string($parent[$column['id']])) {
					$celldata = number_format(floatval($parent[$column['id']]), $column['after-decimal']);
				} else {
					$celldata = number_format($parent[$column['id']], $column['after-decimal']);
				}
				if (!empty($column['percent'])) {
					$celldata .= "%";
				}
			} else {
				$celldata = $parent[$column['id']];
            }

            // Format data HTML formatting
            if (in_array($column['id'], self::$trackingcolumns)) {
				$href = self::generate_trackingurl($parent['Service Type'], $parent[$column['id']]);
				return $href ? $bootstrap->a("href=$href|target=_blank", $celldata) : $celldata;
			} elseif (in_array($column['id'], self::$phonecolumns)) {
				$href = self::generate_phoneurl($parent[$column['id']]);
				return $bootstrap->a("href=tel:$href", $celldata);
			} elseif (preg_match($qtyregex , $column['id'])) {
				if (DplusWire::wire('modules')->isInstalled('QtyPerCase')) {
					$qtypercase = DplusWire::wire('modules')->get('QtyPerCase');
					$itemID = isset($parent["Item ID"]) ? $parent["Item ID"] : DplusWire::wire('session')->itemid;
					$description = $qtypercase->generate_casebottleqtydesc($itemID, $celldata);
					return $bootstrap->span("class=has-hover|data-toggle=tooltip|title=$description", $celldata);
				} else {
					return $celldata;
				}
			} elseif (!empty($column['input'])) {
				return $bootstrap->input("class=form-control input-sm underlined|value=$celldata");
			} else {
				return $celldata;
			}
        }
        
        /**
		 * Returns the data formatted into appropriate formats
		 * Ex. Tracking Columns will have a tracking link
		 * @param  array  $parent array of values keyed by column names
		 * @param  string $column Name of Column
		 * @return string         Value or HTML content
		 */
		public static function generate_celldata($parent, $column) {
			$bootstrap = new HTMLWriter();
			if (in_array($column, self::$trackingcolumns)) {
				$href = self::generate_trackingurl($parent['Service Type'], $parent[$column]);
				return $href ? $bootstrap->a("href=$href|target=_blank", $parent[$column]) : $parent[$column];
			} elseif(in_array($column, self::$phonecolumns)) {
				$href = self::generate_phoneurl($parent[$column]);
				return $bootstrap->a("href=tel:$href", $parent[$column]);
			} else {
				return $parent[$column];
			}
        }
        
        /**
		 * Returns HTML Tracking Link
		 * @param  string $servicetype Ex. Fedex, UPS, USPS
		 * @param  string $tracknbr    Tracking Number
		 * @return string             URL
		 */
		public static function generate_trackingurl($servicetype, $tracknbr) {
			$href = false;
			if (strpos(strtolower($servicetype), 'fed') !== false) {
				$href = "https://www.fedex.com/apps/fedextrack/?action=track&trackingnumber=$tracknbr&cntry_code=us";
			} elseif (strpos(strtolower($servicetype), 'ups') !== false) {
				$href = "http://wwwapps.ups.com/WebTracking/track?track=yes&trackNums=$tracknbr&loc=en_us";
			} elseif (strpos(strtolower($servicetype), 'gro') !== false) {
				$href = "http://wwwapps.ups.com/WebTracking/track?track=yes&trackNums=$tracknbr&loc=en_us";
			} elseif (strpos(strtolower($servicetype), 'usps') !== false) {
				$href = "https://tools.usps.com/go/TrackConfirmAction?tLabels=$tracknbr";
			} elseif (strpos(strtolower($servicetype), 'spee') !== false) {
				$href = "http://packages.speedeedelivery.com/index.php?barcodes=$tracknbr";
			}
			return $href;
        }
        
        /**
		 * Returns phone string for URL
		 * @param  string $phone Phone Number
		 * @return string        reformatted phone value
		 */
		public static function generate_phoneurl($phone) {
			return str_replace('-', '', $phone);
		}

		/* =============================================================
			CONFIG FUNCTIONS
		============================================================ */
		/**
		 * Defines Default File Directory
		 * @param string $dir path/to/dir
		 */
		public static function set_filedirectory($dir) {
			self::$filedir = $dir;
		}

		/**
		 * Defines test data Directory
		 * @param string $dir path/to/dir
		 */
		public static function set_testdatafiledirectory($dir) {
			self::$testfiledir = $dir;
		}

		/**
		 * Defines field definiton file Directory
		 * @param string $dir path/to/dir
		 */
		public static function set_fieldfiledirectory($dir) {
			self::$fieldfiledir = $dir;
		}
	}
