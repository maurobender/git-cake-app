<?php
	class Repository extends AppModel {
		var $name = 'Repository';
		var $primaryKey = 'name';
		public $useDbConfig = 'git';
		
		var $hasMany = array(
			'Commit' => array(
				'className' => 'Commit',
				'foreignKey' => 'repository',
				'limit' => 5
			),
			'GitFile' => array(
				'className' => 'GitFile',
				'foreignKey' => 'repository'
			),
			'GitTag' => array(
				'className' => 'GitTag',
				'foreignKey' => 'repository',
				'limit' => 5
			)
		);
	}
?>