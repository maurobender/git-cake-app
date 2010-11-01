<?php
	class RepositoriesController extends AppController {
		var $name = 'Repositories';
		
		function index() {
			$this->set('repositories', $this->Repository->find('all'));
			
			//$this->set('commits', $this->Repository->Commit->find('all', array('conditions' => array('repository' => 'repository_name'), 'limit' => 10)));
			//$this->set('files', $this->Repository->GitFile->find('all', array('conditions' => array('repository' => 'repository_name'))));
			//$this->set('tags', $this->Repository->GitTag->find('all', array('conditions' => array('repository' => 'repository_name'), 'limit' => 5)));*/
		}
	}
?>