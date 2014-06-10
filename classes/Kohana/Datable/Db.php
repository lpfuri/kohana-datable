<?php defined('SYSPATH') OR die('No direct script access.');


class Kohana_Datable_Db extends Datable {
	
	protected $_extra_selected = array();
		
	protected function _column_table_reference($column_name)
	{
		//if(isset($this->_columns[$column_name]['template'])) return NUll;
		
		return isset($this->_columns[$column_name]['table_column']) ? $this->_columns[$column_name]['table_column'][0] : $column_name;
	}
	
	protected function _column_prop_name($column_name)
	{
		if(isset($this->_columns[$column_name]['template'])) return NUll;
		
		return isset($this->_columns[$column_name]['table_column']) ? $this->_columns[$column_name]['table_column'][1] : $column_name;
	}
	
	protected function _column_label($column_name)
	{
		if(isset($this->_columns[$column_name]['label'])) return __($this->_columns[$column_name]['label']);
		
		return $column_name;
	}
	
	protected function _count($object)
	{	
		$count = clone $object;
		
		return $count->select(array(DB::expr('COUNT(*)'),'records_found'))->execute()->get('records_found');
	}
	
	protected function _list($object)
	{
		foreach($this->_columns as $column_name => $settings){
			
			if(isset($settings['template'])) continue;
			
			if(isset($settings['table_column'])){
				
			 	$object->select($settings['table_column']);
				
			}else{
				
				$object->select($column_name);
				
			}
		}
		
		foreach($this->_extra_selected as $column_name) $object->select($column_name);
		
		return $object->execute(null, TRUE);
	}
	
	public function select($columns)
	{
		$columns = func_get_args();

		$this->_extra_selected = array_merge($this->_extra_selected, $columns);
		
		return $this;
	}
	
}