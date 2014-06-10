<?php defined('SYSPATH') OR die('No direct script access.');


class Kohana_Datable_Orm extends Datable {
	
	protected function _column_table_reference($column_name)
	{
		//if(isset($this->_columns[$column_name]['template'])) return NUll;
		
		return $column_name;
	}
	
	protected function _column_prop_name($column_name)
	{
		if(isset($this->_columns[$column_name]['template'])) return NUll;
		
		return $column_name;
	}
	
	protected function _column_label($column_name)
	{
		$object_labels = $this->_object->labels();
		
		if(isset($this->_columns[$column_name]['label'])){
			
			return __($this->_columns[$column_name]['label']);
			
		}elseif(isset($object_labels[$column_name])){
			
			return $object_labels[$column_name];
			
		}
		
		return $column_name;
	}
	
	protected function _count($object)
	{	
		$count = clone $object;
		
		return $count->count_all();
	}
	
	protected function _list($object)
	{			
		return $object->find_all();
	}
	
}