<?php defined('SYSPATH') OR die('No direct access allowed.');

return array
(
	'default_datatable_settings' => array(
		'bJQueryUI' => TRUE,
		'sPaginationType' => 'full_numbers',
		'sDom' => '<""p><"clear"><"H"lfr>t<"F"i>',
		'bProcessing' => FALSE,
        'bServerSide' => TRUE,
		'sServerMethod' => 'POST',
	),
	'default_filters_settings' => array(
		'sPlaceHolder'    => 'foot',
		'sRangeSeparator' => '~',
		'iFilteringDelay' => '500',
		'sRangeFormat'    => 'From {from} to {to}',
		'sDateFromToken'  => 'from',
		'sDateToToken'    => 'to'
	),
	// For column filter plugin
	'default_fnServerData' => 'function(sUrl, aoData, fnCallback) {
						            $.ajax({
						                url: sUrl,
						                success: fnCallback,
						                data: aoData,
						                type: "POST", 
						                dataType: "json",
						                cache: false
						            });
						        }',
	'langs_folder' => NULL,
);