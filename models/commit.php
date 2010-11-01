<?php
	class Commit extends AppModel {
		var $name = 'Commit';
		var $primaryKey = 'hash';
		public $useDbConfig = 'git';
		
		var $belongsTo = array(
			'Repository' => array(
				'className' => 'Repository',
				'foreignKey' => 'repository'
			)
		);
		
		var $hasMany = array(
			'GitFile' => array(
				'className' => 'GitFile',
				'foreignKey' => 'commit',
				'conditions' => array('GitFile.repository' => 'Commit.repository')
			)
		);
	}
?>