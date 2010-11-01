<?php
	class GitFile extends AppModel {
		var $name = 'GitFile';
		var $primaryKey = 'hash';
		public $useDbConfig = 'git';
		
		var $belongsTo = array(
			'Repository' => array(
				'className' => 'Repository',
				'foreignKey' => 'repository'
			),
			'Commit' => array(
				'className' => 'Commit',
				'foreignKey' => 'commit'
			)
		);
	}
?>