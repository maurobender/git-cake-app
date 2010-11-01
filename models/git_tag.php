<?php
	class GitTag extends AppModel {
		var $name = 'GitTag';
		var $primaryKey = 'hash';
		public $useDbConfig = 'git';
		
		var $belongsTo = array(
			'Repository' => array(
				'className' => 'Repository',
				'foreignKey' => 'repository'
			)
		);
	}
?>