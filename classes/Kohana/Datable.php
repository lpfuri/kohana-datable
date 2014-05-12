<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Datable class
 * 
 * Module for jQuery DataTables plugin
 * 
 * @author      Francisco Urioste <lpfuri@gmail.com>
 * @copyright	(c) 2013-2014 Francisco Urioste
 * @license		MIT
 */

abstract class Kohana_Datable {
	
	/**
	 * Datatable configuration
	 * @var array
	 */
	protected $_config;
	
	/**
	* Stores source object
	* @var mixed Database_Query_Builder_Select|ORM
	*/
	protected $_object;
	
	/**
	 * Datatable columns info
	 * @var array
	 */
	protected $_columns = array();
	
	/**
	 * Datatable Javascript settings
	 * @var array
	 */
	protected $_datatable_settings = array();
	
	/**
	 * Table html attributes
	 * @var array
	 */
	protected $_table_attributes = array();
	
	/**
	 * Tells if there will be needed the column filter plugin
	 * @var bool
	 */
	protected $_column_filters_enabled = FALSE;
	
	/**
	 * Stores column filter settings
	 * @var array
	 */
	protected $_column_filters_settings = array(); 
	
	/**
	 * Stores plugins needed for the datatable with its settings
	 * @var array
	 */
	protected $_plugins = array();
	
	
	/**
	* Selects the correct driver and creates an instance of it.
	*
	* @param   mixed  $object                     Database_Query_Builder_Select|ORM object
    * @param   array  $datatable_settings         Settings of datatables plugin
	* @param   array  $columns                    Table columns settings	
	* @return  mixed  Datable_Db|Datable_Orm
	*/
	public static function factory($object, array $datatable_settings = array(), array $columns = array())
	{
		if(get_class($object) == 'Database_Query_Builder_Select'){
			
			$object_type = 'Db';
			
		}elseif(in_array('ORM', class_parents($object))){
			
			$object_type = 'Orm';
			
		}else{
			
			throw new Kohana_Exception('Wrong object');
			
		}
		
		$driver_object = 'Datable_'.$object_type;
		
		return new $driver_object($object, $columns, $datatable_settings);
	}
	
	/**
	 * Load class vars and add columns
	 */
	public function __construct($object, array $columns = array(), array $datatable_settings = array())
	{	
		// Class vars
		$this->_config             = Kohana::$config->load('datable');
		$this->_object             = $object;
		$this->_columns            = $columns;
		$this->_datatable_settings = Arr::merge($this->_config['default_datatable_settings'], $datatable_settings);
		
		// Add columns
		foreach($this->_columns as $column_name => $settings) $this->add_column($column_name, $settings);
	}
	
	/**
	 * Return rendered datatable
	 * 
	 * @return  string  Table HTML and Javascript
	 */
	public function __toString()
	{
		return $this->render();
	}
	
	/**
	 * Set table HTML attributes.
	 * 
	 * @param   array  $attributes
	 * @return  mixed  Datable_Db|Datable_Orm
	 */
	public function set_table_attributes(array $attributes)
	{
		$this->_table_attributes = $attributes;
		
		return $this;
	}
	
	/**
	 * Adds a new column
	 * 
	 * @param   string  $name
	 * @param   array   $settings
	 * @return  mixed   Datable_Db|Datable_Orm
	 */
	public function add_column($name, array $settings = array())
	{
		// Automatic turn off searching and sorting for columns with templates if necessary
		if(isset($settings['template'])){
				
			if(!isset($settings['sort']) OR !isset($settings['filter']['method'])){
					
				if(!isset($settings['aoColumns'])) $settings['aoColumns'] = array();
				if(!isset($settings['aoColumns']['bSortable']) AND !isset($settings['sort'])) $settings['aoColumns']['bSortable'] = FALSE;
				if(!isset($settings['aoColumns']['bSearchable']) AND !isset($settings['filter']['method'])) $settings['aoColumns']['bSearchable'] = FALSE;	

			}
			
		}
		
		$settings['table_reference'] = $this->_column_table_reference($name);
		
		$settings['prop_name']       = $this->_column_prop_name($name);
		
		$settings['label']           = $this->_column_label($name);
		
		$this->_columns[$name]       = $settings;
		
		return $this;
	}
	
	/**
	 * Enables Jovan's Popovic datatables column filter plugin.
	 * 
	 * @param   array  $settings                   Plugin initialization parameters
	 * @return  mixed  Datable_Db|Datable_Orm
	 */
	public function enable_column_filters(array $settings = array())
	{		
		$this->_column_filters_enabled = TRUE;
		
		$this->_column_filters_settings =  Arr::merge($this->_config['default_filters_settings'], $settings);
		
		return $this;
	}
	
	/**
	 * Set other plugins and settings
	 * 
	 * @param   string  $plugin_name
	 * @param   array   $settings
	 * @return  mixed   Datable_Db|Datable_Orm
	 */
	public function plugin($plugin_name, $settings = array())
	{
		$this->_plugins[$plugin_name] = $settings;
		
		return $this;
	}
	
	/**
	 * Process datatable request and returns the data
	 * 
	 * @param   array  $request
	 * @return  array
	 */
	public function get_rows_data(array $request)
	{
		$data = array();
		
		$raw_list = clone $this->_object;
		
		$column_names = array_keys($this->_columns);
		
		// Search
		if(isset($request['sSearch']) AND $request['sSearch'] != ''){
			
			$raw_list->where_open();
			
			for($i = 0; $i < $request['iColumns']; $i++){
				
				if($request['bSearchable_'.$i] == 'true'){
					
					$column_settings = $this->_columns[$column_names[$i]];
					
					if(isset($column_settings['filter']['method']) AND is_callable($column_settings['filter']['method'])){
						
						// Custom search filter
						call_user_func($column_settings['filter']['method'], $raw_list, $request['sSearch'], 'SEARCH');
						
					}else{
						
						// Default search		
						$raw_list->or_where($this->_columns[$column_names[$i]]['table_reference'], 'LIKE', $request['sSearch'].'%');
						
					}
					
				}
				
			}
			
			$raw_list->where_close();
			
		}
		
		// Column filters
		if($this->_column_filters_enabled){
			
			// First collect columns
			$filter_columns = array();
			for($i = 0; $i < $request['iColumns']; $i++){
				
				if($request['sSearch_'.$i] != '') $filter_columns[$column_names[$i]] = $request['sSearch_'.$i];
				
			}
			
			// Then filter
			if(count($filter_columns)){
				
				foreach($filter_columns as $column_name => $sSearch){
					
					$column_settings = $this->_columns[$column_name];
					
					$filter_settings = $column_settings['filter'];
					
					if(isset($filter_settings['method']) AND is_callable($filter_settings['method'])){
						
						// Custom filter
						call_user_func($filter_settings['method'], $raw_list, $sSearch, 'FILTER');
						
					}else{
						
						// Standard filter
						$type = isset($filter_settings['input']['type']) ? $filter_settings['input']['type'] : 'text';
						
						switch($type){
							case 'text':
									
								$raw_list->and_where($this->_columns[$column_name]['table_reference'], 'LIKE', $sSearch.'%');
							
							break;
							case 'select':
								
								$options = $column_settings['filter']['values'];
								
								if(in_array($sSearch, $options)){
									
									$raw_list->and_where($this->_columns[$column_name]['table_reference'], '=', array_search($sSearch, $options));
									
								}else{
									
									$raw_list->and_where($this->_columns[$column_name]['table_reference'], '=', '');
									
								}
																
							break;
							default:
								
								$raw_list->and_where($this->_columns[$column_name]['table_reference'], 'LIKE', $sSearch.'%');
							
							break;						
						}
						
					}			
					
				}
				
			}
					
		}
		
		// Rows total
		$data['iTotalRecords'] = $this->_count($raw_list);
				
		// Rows displayed
		$data['iTotalDisplayRecords'] = $data['iTotalRecords'];
		
		// Offset and limit for paging	
		if(isset($request['iDisplayLength']) AND $request['iDisplayLength'] > -1){
		
			$raw_list->offset($request['iDisplayStart']);
			$raw_list->limit($request['iDisplayLength']);
			
		}
		
		// Sorting
		if(isset($request['iSortingCols'])){
			
			for($i = 0; $i < $request['iSortingCols']; $i++){
				
				$col = $request['iSortCol_'.$i];
				$dir = $request['sSortDir_'.$i];
				
				if(isset($this->_columns[$column_names[$col]]['sort'])){
					
					/*
					 * 
					 * Multiple sorting in one field
					 * 
					 * 'name' => array(
					 * 		'template' => '{lastname}, {name}',
					 * 		'sort' => array('lastname', 'name'),
					 * ),
					 * 
					 */
					foreach($this->_columns[$column_names[$col]]['sort'] as $field) $raw_list->order_by($field, $dir);
				}else{
					// Default sorting
					$raw_list->order_by($this->_columns[$column_names[$col]]['table_reference'], $dir);
				}
				
			}
		
		}
		
		// Get requiered fields
		$list = array();
		
		foreach($this->_list($raw_list) as $item){
			
			$row_data = array();
			
			foreach($this->_columns as $column_name => $settings){
				
				// Method type template
				if(isset($settings['template']) AND is_callable(($settings['template']))){
					
					$value = call_user_func($settings['template'], $item);
				
				// String type template
				}else if(isset($settings['template'])){
					
					$value = $this->_apply_template($item, $settings['template']);
				
				// Just the property
				}else{
										
					$value = $item->{$this->_columns[$column_name]['prop_name']};
					
				}
				
				$row_data[$column_name] = $value;
				
			}
			
			$list[] = $row_data;
		}
		
		if(isset($request['sEcho'])) $data['sEcho'] = $request['sEcho'];
		$data['aaData'] = $list;
		
		return $data;
	}

	/**
	 * Render the datatable
	 * 
	 * @param   bool    $javascript
	 * @return  string
	 */
	public function render($javascript = TRUE)
	{	
		$table_attributes = $this->_table_attributes;
		
		if(!isset($table_attributes['id'])) $table_attributes['id'] = 'datatable';
		
		// Automatically add "display" class to the table
		if(isset($table_attributes['class'])){
			$table_attributes['class'] .= ' display';
		}else{
			$table_attributes['class'] = 'display';	
		}
		
		$attributes = '';
		foreach($table_attributes as $key => $value) $attributes .= ' '.$key .'="' . $value .'"';

		// Columns
		$fields_row = '';
		$filters_row = '';
		foreach(array_keys($this->_columns) as $column_name) {
			
			$label = $this->_column_label($column_name);
					
			$fields_row .= '<th>'.$label.'</th>';
			$filters_row .= isset($this->_columns[$column_name]['filter']['input']) ?  '<th>'.$label.'</th>' : '<th></th>';
			
		}
			
		// Make the table
		$html = '<table '.$attributes.'>';
		
		if($this->_column_filters_enabled){
			
			if(strstr($this->_column_filters_settings['sPlaceHolder'], 'head')){

				$html .= '<thead><tr>'.$filters_row.'</tr><tr>'.$fields_row.'</tr></thead>';
				$html .= '<tbody></tbody>';
			
			}else{
				
				$html .= '<thead><tr>'.$fields_row.'</tr></thead>';
				$html .= '<tbody></tbody>';
				$html .= '<tfoot><tr>'.$filters_row.'</tr></tfoot>';
				
			}
			
		}else{
			
			$html .= '<thead><tr>'.$fields_row.'</tr></thead>';
			$html .= '<tbody></tbody>';
			
		}
		
		$html .= '</table>';
		
		return $javascript ? $this->_render_javascript().$html : $html;
	}
	
	
	/**
	 * Render javascript code for the datatable
	 * 
	 * @return  string
	 */
	protected function _render_javascript()
	{		
		$aoColumns = array();
		
		foreach($this->_columns as $column_name => $settings){
			$column_info = array('mData' => $column_name);
			
			if(isset($settings['aoColumns'])) $column_info = Arr::merge($column_info, $settings['aoColumns']);
			
			$aoColumns[] = $column_info;
		}
			
		$datatable_settings = Arr::merge(
			$this->_datatable_settings,
			array('aoColumns' => $aoColumns)
		);
		
		$html = '<script type="text/javascript">';
		$html .= '$(document).ready(function(){';
		
			$html .= 'datatable_'.str_replace('-', '_', $this->_table_attributes['id']).' = $("#'.$this->_table_attributes['id'].'").dataTable({';

				// Adding settings
				foreach($datatable_settings as $name => $value) $html .= $this->_format_setting($name, $value);
				
				// Set fnServerData to make column filter plugin work
				if(!isset($datatable_settings['fnServerData']) AND $datatable_settings['bServerSide'] AND $this->_column_filters_enabled)
					$html .= $this->_format_setting('fnServerData', $this->_config['default_fnServerData']);
				
				// Internationalisation
				$langs_folder = $this->_config['langs_folder'];
				if(isset($langs_folder) AND !isset($settings['oLanguage']['sUrl']))
					$html .= $this->_format_setting('oLanguage', array('sUrl' => URL::base().$langs_folder.substr(I18n::$lang, 0, 2).'.txt'));
				
				// Remove last coma
				if(substr($html, -1) == ',') $html = substr_replace($html ,'', -1);
			
			$html .= '});';
			
			if($this->_column_filters_enabled){
				
				$html = substr_replace($html ,'', -1);
				
				$html .= '.columnFilter({';
					
					foreach($this->_column_filters_settings as $name => $value) $html .= $this->_format_setting($name, $value);
					
					// Sorting column settings
					$aoColumns = array();
					foreach($this->_columns as $column_settings) $aoColumns[] = isset($column_settings['filter']['input']) ? $column_settings['filter']['input'] : null;
					
					$html .= $this->_format_setting('aoColumns', $aoColumns);

					// Remove last coma
					if(substr($html, -1) == ',') $html = substr_replace($html ,'', -1);
					
				$html .= '});';
				
			}
			
			// Apply plugins
			foreach($this->_plugins as $plugin_name => $settings){
				
				$html = substr_replace($html ,'', -1);
				
				$html .= '.'.$plugin_name.'(';
				
				if(is_array($settings)){
					
					$html .= '{';
					
						foreach($settings as $name => $value) $html .= $this->_format_setting($name, $value);
						
						// Remove last coma
						if(count($settings)) $html = substr_replace($html ,'', -1);
						
					$html .= '}';
				
				}elseif(is_string($settings)){
					
					$html .= "'".$settings."'";
					
				}else{
					
					$html .= json_encode($settings);
					
				}
				
				$html .= ');';
				
			}
			
		$html .= '});';
		$html .= '</script>';
		
		return $html;
	}
	
	/**
	 * Format javascript setting for rendering
	 * 
	 * @param   string  $name
	 * @param   string  $value
	 * @return  string
	 */
	protected function _format_setting($name, $value)
	{	
		// Arrays and boolean
		if(is_array($value) OR is_bool($value)){
			
			$setting = $name.':'.json_encode($value);
		
		// Callbacks
		}elseif(substr($name, 0, 2) == 'fn'){
			
			$setting = $name.':'.$value;
			
		// Strings
		}else{
			
			$setting = $name.":'".$value."'";
			
		}
		
		return $setting.','; 
	}
	
	
	/**
	 * Apply column string type template
	 * 
	 * @param   mixed   $item
	 * @param   string  $template
	 * @return  string
	 */
	protected function _apply_template($item, $template)
	{					
		foreach($this->_parse_template($template) as $replace){
			$prop_name = $replace;
			
			$prop_name = str_replace('{', '', $prop_name);
			$prop_name = str_replace('}', '', $prop_name);
			
			// Replace with object value 
			$template = str_replace($replace, $item->{$prop_name}, $template);
		}
		
		return $template;
	}
	
	/**
	 * Parse template and find replacements
	 * 
	 * @param  string  $template
	 */
	protected function _parse_template($template)
	{
		// Search for proerties between {}
		preg_match_all('#\{[a-z_]+\}#', $template, $replacements);
		
		return array_unique($replacements[0]);
	}
	
	protected function _column_table_reference($column_name){}
	
	protected function _column_prop_name($column_name){}
	
	protected function _column_label($column_name){}
	
	protected function _count($object){}
	
 	protected function _list($object){}
	
}

