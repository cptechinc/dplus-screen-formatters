<?php
    namespace Dplus\ScreenFormatters;

    use Processwire\WireInput;
    use Dplus\ProcessWire\DplusWire;
    use Purl\Url as Purl;

	/**
	 * Abstract file to build Formatter classes from and to provide properties and methods
	 */
	abstract class ScreenMakerFormatter extends ScreenMaker {
		
		/**
		 * Formatter
		 * @var string
		 */
		protected $formatter = false; // WILL BE JSON DECODED ARRAY
		
		/**
		 * Array with the structure of Screen
		 * @var array
		 */
		protected $tableblueprint = false; // WILL BE ARRAY
		
		/**
		 * Where is formatted derived from 
         * ex. input | database | default
		 * @var string
		 */
		protected $source;
		
		/**
		 * Date Types for Formatter Selection
		 * @var string
		 */
        protected $datetypes = array(
            'm/d/y' => 'MM/DD/YY', 
            'm/d/Y' => 'MM/DD/YYYY', 
            'm/d' => 'MM/DD', 
            'm/Y' => 'MM/YYYY'
        );
		
		/**
		 * File Directory for default formatters
		 * @var string /path/to/dir
		 */
		public static $defaultformatterdirectory = false;
		
		/* =============================================================
		   CONSTRUCTOR AND SETTER FUNCTIONS
	   ============================================================ */
	   /**
		* Constructor
		* @param string $sessionID Session Identifier
		*/
		public function __construct($sessionID) {
			parent::__construct($sessionID);
			$this->load_fields();
			$this->load_formatter();
			$this->generate_tableblueprint();
		}
		
		/* =============================================================
			SETTER FUNCTIONS
	   ============================================================ */
		/**
		 * Turns Debug On or Off
		 * @param bool $debug Turn on Debug?
		 */
		public function set_debug($debug) {
			$this->debug = $debug;
			$this->get_filepath();
		}
		
		/**
		 * Sets the User ID for the Formatter
		 * @param string $userID User ID
		 */
		public function set_userid($userID) {
			$this->userID = $userID;
		}
		
		/* =============================================================
			GETTER FUNCTIONS
       ============================================================ */
       /**
		 * Returns blueprint and loads it if need be
		 * @return array blueprint array
		 */
		public function get_tableblueprint() {
			if (!$this->tableblueprint) {
				$this->generate_tableblueprint();
			}
			return $this->tableblueprint;
        }
        
	   /**
		* Returns the formatter property
		* @return array Formatter
		*/
		public function get_formatter() {
			if (!$this->formatter) {
				$this->load_formatter();
			}
			return $this->formatter;
		}
		
		/**
		 * Returns the title for the document screen
		 * @return string Document Title
		 */
		public function get_doctitle() {
			return $this->title;
		}
		
		/**
		 * Returns an array with default formatting
		 * @return array an array with default formatting
		 */
		public function get_defaultformattercolumn() {
			return array(
				"line"           => 0,
				"column"         => 0,
				"type"           => "C",
				"col-length"     => 0,
				"label"          => "Label",
				"before-decimal" => false,
				"after-decimal"  => false,
				"date-format"    => false,
				"percent"        => false,
				"input"          => false,
				"data-justify"   => "left",
				"label-justify"  => "right"
			);
		}
		
		/* =============================================================
			PUBLIC FUNCTIONS
		============================================================ */
		/**
		 * Generates and Sets tableblueprint property based on 
		 * the input
		 * @param  WireInput $input Input with field definitions
		 * @return void
		 */
		public function generate_formatterfrominput(WireInput $input) {
			$this->formatter = false;
			$postarray = $table = array('colcount' => 0);
			$tablesections = array_keys($this->fields);
			
			if ($input->post->user) {
				$userID = $input->post->text('user');
				$this->set_userid($userID);
			} else {
				$this->set_userid(DplusWire::wire('user')->loginid);
			}
			
			
			foreach ($tablesections as $tablesection) {
				$postarray[$tablesection] = array('rows' => 0, 'colcount' => 0, 'columns' => array());
				$table[$tablesection] = array('rowcount' => 0, 'rows' => array());
				
				foreach (array_keys($this->fields[$tablesection]) as $column) {
					$postcolumn = str_replace(' ', '', $column);
					$linenumber = $input->post->int($postcolumn.'-line');
					$length = $input->post->int($postcolumn.'-length');
					$colnumber = $input->post->int($postcolumn.'-column');
					$label = $input->post->text($postcolumn.'-label');
					$dateformat = $beforedecimal = $afterdecimal = false;
					$justify_data = $input->post->text($postcolumn.'-justify-data');
					$justify_label = $input->post->text($postcolumn.'-justify-label');
					$is_input = $input->post->text($postcolumn.'-is-input') == 'Y' ? true : false;
					$is_percent = $input->post->text($postcolumn.'-is-percent') == 'Y' ? true : false;
					
					if ($this->fields[$tablesection][$column]['type'] == 'D') {
						$dateformat = $input->post->text($postcolumn.'-date-format');
					} elseif ($this->fields[$tablesection][$column]['type'] == 'N') {
						$beforedecimal = $input->post->int($postcolumn.'-before-decimal');
						$afterdecimal = $input->post->int($postcolumn.'-after-decimal');
					}
					
					$postarray[$tablesection]['columns'][$column] = array(
						'column'         => $colnumber,
						'line'           => $linenumber,
						'col-length'     => $length,
						'label'          => $label,
						'before-decimal' => $beforedecimal,
						'after-decimal'  => $afterdecimal,
						'date-format'    => $dateformat,
						'percent'        => $is_percent,
						'input'          => $is_input,
						'data-justify'   => $justify_data,
						'label-justify'  => $justify_label
					);
				}
				
				foreach ($postarray[$tablesection]['columns'] as $column) {
					if ($column['line'] > $postarray[$tablesection]['rows']) {
						$postarray[$tablesection]['rows'] = $column['line'];
					}
				}
				
				for ($i = 1; $i < ($postarray[$tablesection]['rows'] + 1); $i++) {
					$table[$tablesection]['rows'][$i] = array('columns' => array());
					
					foreach ($postarray[$tablesection]['columns'] as $column) {
						if ($column['line'] == $i) {
							$table[$tablesection]['rows'][$i]['columns'][$column['column']] = $column;
						}
					}
				}

				foreach ($table[$tablesection]['rows'] as $row) {
					$columncount = 0;
					$maxcolumn = 0;
					foreach ($row['columns'] as $column) {
						$columncount += $column['col-length'];
						$maxcolumn = $column['column'] > $maxcolumn ? $column['column'] : $maxcolumn;
					}
					$columncount = ($maxcolumn > $columncount) ? $maxcolumn : $columncount;
					$postarray[$tablesection]['colcount'] = $columncount;
					$postarray['colcount'] = ($columncount > $postarray['colcount']) ? $columncount : $postarray['colcount'];
				}
			}
			
			$this->formatter = $postarray;
			$this->source = 'input';
			$this->generate_tableblueprint();
		}
		/**
		 * Saves the tableblue print with field definitions to the database
		 * @param  bool   $debug Run in debug? if so return SQL Query
		 * @return array         Response array
		 */
		public function save($debug = false) {
			if ($this->userID != 'preview') {
				$userpermission = DplusWire::wire('pages')->get('/config/print/')->allow_userprintformatter;
				$userpermission = (!empty($userpermission)) ? $userpermission : DplusWire::wire('users')->get("name=$this->userID")->hasPermission('setup-print-formatter');
			}
			
			if ($this->does_printformatterexist() ) {
				return $this->update($debug);
			} else {
				return $this->create($debug);
			}
		}
		
		/**
		 * Calls this::save() then returns and response array for JSON purposes
		 * @return array Response array
		 */
		public function save_andrespond() {
			$response = $this->save();
			
			if ($response['success']) {
				$msg = $this->userID == DplusWire::wire('user')->loginid ? "Your table ($this->code) configuration has been saved" : "The configuration for $this->userID has been saved";
				$json = array (
					'response' => array (
						'error'      => false,
						'notifytype' => 'success',
						'action'     => $response['querytype'],
						'message'    => $msg,
						'icon'       => 'fa fa-floppy-o',
					)
				);
			} else {
				$msg = $this->userID == DplusWire::wire('user')->loginid ? "Your configuration ($this->code) was not able to be saved, you may have not made any discernable changes." : "The configuration for $this->userID was not able to be saved, you may have not made any discernable changes.";
				$json = array (
					'response' => array (
						'error'      => true,
						'notifytype' => 'danger',
						'action'     => $response['querytype'],
						'message'    => $msg,
						'icon'       => 'fa fa-exclamation-triangle',
					)
				);
			}
			return $json;
		}
		
		/**
		 * Returns if User Can edit this formatter
		 * @param  string $userID User can be provided or will use current user
		 * @return bool           Is the User allowed to edit this formatter?
		 */
		public function can_edit($userID = '') {
			$allowed = false;
			$userID = !empty($userID) ? $userID : DplusWire::wire('user')->loginid;
			
			if (DplusWire::wire('users')->find("name=$userID")->count) {
			   $allowed = DplusWire::wire('users')->get("name=".DplusWire::wire('user')->loginid)->hasPermission('setup-formatter');
			}
			return $allowed;
		}
			
		/* =============================================================
			CLASS FUNCTIONS
		============================================================ */
		public function generate_previewurl() {
			$url = new Purl(DplusWire::wire('pages')->get("template=document-formatted-page,name=$this->code")->url);
			$url->query->set('preview', 'preview');
			$url->query->set('debug', 'debug');
			return $url->getUrl();
		}
		
		/**
		 * Parses through the formatter array and sets the tableblueprint
		 * @return void
		 */
		protected function generate_tableblueprint() {
			$tablesections = array_keys($this->fields);
			$table = array('colcount' => $this->formatter['colcount']);
			
			foreach ($tablesections as $section) {
				$columns = array_keys($this->formatter[$section]['columns']);
				
				$table[$section] = array(
					'rowcount' => $this->formatter[$section]['rowcount'],
					'colcount' => 0,
					'rows' => array()
				);
			
				for ($i = 1; $i < $this->formatter[$section]['rows'] + 1; $i++) {
					$table[$section]['rows'][$i] = array('columns' => array());
					
					foreach ($columns as $column) {
						if ($this->formatter[$section]['columns'][$column]['line'] == $i) {
							$col = array(
								'id'             => $column, 
								'label'          => $this->formatter[$section]['columns'][$column]['label'],
								'column'         => $this->formatter[$section]['columns'][$column]['column'],
								'type'           => $this->fields[$section][$column],
								'col-length'     => $this->formatter[$section]['columns'][$column]['col-length'],
								'before-decimal' => $this->formatter[$section]['columns'][$column]['before-decimal'],
								'after-decimal'  => $this->formatter[$section]['columns'][$column]['after-decimal'],
								'date-format'    => $this->formatter[$section]['columns'][$column]['date-format'],
								'percent'        => $this->formatter[$section]['columns'][$column]['percent'],
								'input'          => $this->formatter[$section]['columns'][$column]['input'],
								'data-justify'   => $this->formatter[$section]['columns'][$column]['data-justify'],
								'label-justify'  => $this->formatter[$section]['columns'][$column]['label-justify'],
							 );
							$table[$section]['rows'][$i]['columns'][$this->formatter[$section]['columns'][$column]['column']] = $col;
							$table[$section]['colcount'] = $col['column'] > $table[$section]['colcount'] ? $col['column'] : $table[$section]['colcount'];
						}
					}
				}
			}
			$this->tableblueprint = $table;
		}
		
		/**
		 * Set the fields array with the values from the json file
		 * @return void
		 */
		protected function load_fields() {
			$this->fields = json_decode(file_get_contents(self::$fieldfiledir."$this->code.json"), true);
		}
		
		/* =============================================================
			CRUD FUNCTIONS
		========================================================== */
		/**
		 * Sets the formatter array with the field definitions
		 * 1. Checks if user has a formatter
		 * 2. Checks if there's a saved default formatter
		 * 3. Get default formatter
		 * @return void
		 */
		protected function load_formatter() {
			if ($this->does_printformatterexist('default')) {
				$this->formatter = get_screenformatter($this->code, 'default');
				$this->source = 'database';
			} else {
				$this->formatter = file_get_contents($this::$defaultformatterdirectory."$this->code.json");
				$this->source = 'default';
			}
			$this->formatter = json_decode($this->formatter, true);
		}
		
		/**
		 * Returns if user has a formatter for this type saved
		 * @return bool             Does User have a formatter
		 */
		protected function does_printformatterexist() {
			return does_screenformatterexist($this->code, $this->userID);
		}
		
		
		/**
		 * Updates the formatter property to the database for this User
		 * @param  bool    $debug  Run in debug? If so, return SQL Query
		 * @return array         Response
		 */
		protected function update( $debug = false) {
			return update_screenformatter($this->code, $this->userID, json_encode($this->formatter), $this->userID, $debug);
		}
		
		/**
		 * Saves the formatter property to the database for this User
		 * @param  bool   $debug  Run in debug? If so, return SQL Query
		 * @return array          Response
		 */
		protected function create($debug = false) {
			return create_screenformatter($this->code, $this->userID, json_encode($this->formatter), $debug);
		}
		
		/* =============================================================
			STATIC FUNCTIONS
		========================================================== */
		/**
		 * Defines default formatters Directory
		 * @param string $dir path/to/dir
		 */
		public static function set_defaultformatterfiledirectory($dir) {
			self::$defaultformatterdirectory = $dir;
		}
}
