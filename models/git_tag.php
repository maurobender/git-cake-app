<?php
	class GitTag extends AppModel {
		var $name = 'GitTag';
		public $useDbConfig = 'git';
		
		var $belongsTo = array(
			'Repository' => array(
				'classname' => 'Repository'
			)
		);
	}
?>