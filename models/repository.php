<?php
	class Repository extends AppModel {
		var $name = 'Repository';
		public $useDbConfig = 'git';
		
		var $hasMany = array(
			'Commit' => array(
				'classname' => 'Commit',
				'limit' => 5
			)
		);
	}
?>