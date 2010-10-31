<?php
	class RepositoriesController extends AppController {
		var $name = 'Repositories';
		
		function index() {
			$this->set('repositories', $this->Repository->find('first'));
			$commits = $this->Repository->Commit->find('first', array('conditions' => array('repository' => 'project2')));
			debug($commits);
			$this->set('commits', $commits);
		}
	}
?>