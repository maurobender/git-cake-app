<?php
	class GitFile extends AppModel {
		var $name = 'GitFile';
		public $useDbConfig = 'git';
		
		var $belongsTo = array(
			'Repository' => array(
				'classname' => 'Repository'
			),
			'Commit'
		);
	}
?>