<?php
	class Commit extends AppModel {
		var $name = 'Commit';
		public $useDbConfig = 'git';
		
		var $belongsTo = array(
			'Repository' => array(
				'classname' => 'Repository'
			)
		);
	}
?>