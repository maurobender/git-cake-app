<?php
	/**
	* Git DataSource
	*/
	App::import('Lib', 'Git');
	class GitSource extends DataSource {
		protected $_schema = array(
			'repositories' => array(
				'name' => array(
					'type' => 'string',
					'null' => true,
					'key' => 'primary',
					'length' => 100,
				),
				'id' => array(
					'type' => 'string',
					'null' => true,
					'key' => 'primary',
					'length' => 100,
				),
				'path' => array(
					'type' => 'string',
					'null' => true,
					'key' => 'primary',
					'length' => 250
				),
				'owner' => array(
					'type' => 'string',
					'null' => true,
					'length' => 100,
				),
				'description' => array(
					'type' => 'string',
					'null' => true,
					'length' => 140
				),
				'created' => array(
					'type' => 'datetime',
					'null' => true
				)
			),
			'commits' => array(
				'hash' => array(
					'type' => 'string',
					'null' => true,
					'key' => 'primary',
					'length' => 100,
				),
				'parent' => array(
					'type' => 'array(string)',
					'null' => true
				),
				'message' => array(
					'type' => 'string',
					'null' => true,
					'key' => 'primary',
					'length' => 500
				),
				'author_email' => array(
					'type' => 'string',
					'null' => true,
					'length' => 200,
				),
				'commiter_email' => array(
					'type' => 'string',
					'null' => true,
					'length' => 200
				),
				'created' => array(
					'type' => 'datetime',
					'null' => true
				),
			),
			'git_files' => array(
				'hash' => array(
					'type' => 'string',
					'null' => true,
					'key' => 'primary',
					'length' => 100,
				),
				'perm' => array(
					'type' => 'string',
					'null' => true,
					'length' => 10,
				),
				'type' => array(
					'type' => 'string',
					'null' => true,
					'length' => 10,
				),
				'path' => array(
					'type' => 'string',
					'null' => true,
					'length' => 500
				),
				'name' => array(
					'type' => 'string',
					'null' => true,
					'length' => 200,
				),
				'commit' => array(
					'type' => 'string',
					'null' => true,
					'length' => 200
				),
				'repository' => array(
					'type' => 'string',
					'null' => true,
					'length' => 200
				)
			),
			'git_tags' => array(
				'hash' => array(
					'type' => 'string',
					'null' => true,
					'key' => 'primary',
					'length' => 100,
				),
				'name' => array(
					'type' => 'string',
					'null' => true,
					'length' => 100,
				),
			)
		);
		
		protected $_model_table_map = array(
			'Repository' => 'repositories',
			'Commit' => 'commits',
			'GitFile' => 'git_files',
			'GitTag' => 'git_tags'
		);
		
		public function __construct($config) {
			$this->_config = $config;
			
			parent::__construct($config);
		}
		
		public function listSources() {
			return array_keys($this->_schema);
		}
		
		public function read($model, $queryData = array()) {
			$git = new Git($this->_config);
			$results = array();
			
			switch($model->name) {
				case 'Repository':
					$repositories = $git->getRepositories($queryData['conditions'], $queryData['limit']);
					
					foreach($repositories as $repository) {
						$temp['Repository'] = $repository;
						$results[] = $temp;
					}
					break;
				case 'Commit':
					$commits = $git->getCommits($queryData['conditions'], $queryData['limit']);
					
					foreach($commits as $commit) {
						$temp['Commit'] = $commit;
						$results[] = $temp;
					}
					break;
				case 'GitFile':
					$files = $git->getFiles($queryData['conditions']);
					
					foreach($files as $file) {
						$temp['GitFile'] = $file;
						$results[] = $temp;
					}
					break;
				case 'GitTag':
					$tags = $git->getTags($queryData['conditions']);
					
					foreach($tags as $tag) {
						$temp['GitTag'] = $tag;
						$results[] = $temp;
					}
					break;
				case 'Default':
					debug('No se encuentra la tabla para el modelo "' . $model->name . '".');
					break;
			}
			
			return $results;
		}
		
		public function describe($model) {
			$result = array();
			
			if(in_array($model->name, array_keys($this->_model_table_map))) {
				$result = $this->_schema[$this->_model_table_map[$model->name]];
			} else {
				debug('No se encuentra la tabla para el modelo "' . $model->name . '".');
			}
			
			return $result;
		}
	}
?>