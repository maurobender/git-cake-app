<?php
	App::import('Lib', 'Git');
	
	/**
	* @brief Git DataSource. (Read-Only Datasource)
	*/
	class GitSource extends DataSource {
		protected $_schema = array(
			'repositories' => array(
				'name' => array(
					'type' => 'string',
					'null' => false,
					'key' => 'primary',
					'length' => 100,
				),
				'path' => array(
					'type' => 'string',
					'null' => true,
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
				'last_change' => array(
					'type' => 'datetime',
					'null' => true
				)
			),
			'commits' => array(
				'hash' => array(
					'type' => 'string',
					'null' => false,
					'key' => 'primary',
					'length' => 100,
				),
				'parents' => array(
					'type' => 'array(string)',
					'null' => true
				),
				'subject' => array(
					'type' => 'string',
					'null' => true,
					'length' => 500
				),
				'author' => array(
					'type' => 'string',
					'null' => true,
					'length' => 200,
				),
				'email' => array(
					'type' => 'string',
					'null' => true,
					'length' => 200
				),
				'timestamp' => array(
					'type' => 'datetime',
					'null' => true
				),
				'repository' => array(
					'type' => 'string'
				)
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
				'content' => array(
					'type' => 'text'
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
					'null' => false,
					'key' => 'primary',
					'length' => 100,
				),
				'name' => array(
					'type' => 'string',
					'null' => true,
					'length' => 100,
				),
				'repository' => array(
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
		
		public function read(&$model, $queryData = array(), $recursive = null) {
			$git = new Git($this->_config);
			$results = array();
			$linkedModels = array(); 
			
			if (!is_null($recursive)) { 
				$_recursive = $model->recursive; 
				$model->recursive = $recursive; 
			} 
			
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
			
			// ================================ 
			// = Searching for Related Models = 
			// ================================ 
			if ($model->recursive > 0) {
				foreach ($model->__associations as $type) {
					foreach ($model->{$type} as $assoc => $assocData) {
						$linkModel =& $model->{$assoc};
						//debug($linkModel);
						
						if (!in_array($type . '/' . $assoc, $linkedModels)) {
							if ($model->useDbConfig == $linkModel->useDbConfig) {
								$db =& $this; 
							} else {
								$db =& ConnectionManager::getDataSource($linkModel->useDbConfig); 
							} 
						} elseif ($model->recursive > 1 && ($type == 'belongsTo' || $type == 'hasOne')) { 
							$db =& $this; 
						} 
						
						if (isset($db)) { 
							$stack = array($assoc); 
							$db->queryAssociation($model, $linkModel, $type, $assoc, $assocData, $array, true, $results, $model->recursive - 1, $stack); 
							unset($db); 
						} 
					}
				}
			}
			
			if (!is_null($recursive)) { 
				$model->recursive = $_recursive; 
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
		
		// Asociated Models functions
		/** 
		* GenerateAssociationQuery 
		*/     
		function generateAssociationQuery(& $model, & $linkModel, $type, $association = null, $assocData = array (), & $queryData, $external = false, & $resultSet) {
			switch ($type) {
					case 'hasOne':
						return null;
						break;
					case 'belongsTo':
						$id = $resultSet[$model->name][$assocData['foreignKey']];
						$queryData['conditions'] = array(trim($linkModel->primaryKey) => trim($id));
						$queryData['order'] = array();
						$queryData['fields'] = '';
						$queryData['limit'] = 1;
						
						return $queryData;
						break;
					case 'hasMany':
						$id = $resultSet[$model->name][$model->primaryKey];
						$queryData['conditions'] = array(trim($assocData['foreignKey']) => trim($id));
						
						if(isset($assocData['conditions']) && is_array($assocData['conditions'])) {
							foreach($assocData['conditions'] as $cond_k => $cond_v) {
								$cond_k = str_replace("{$linkModel->name}.", '', $cond_k);
								
								if(strpos($cond_v, "{$model->name}.") !== false) {
									$cond_field = str_replace("{$model->name}.", '', $cond_v);
									if(in_array($cond_field, array_keys($resultSet[$model->name]))) {
										$cond_v = $resultSet[$model->name][$cond_field];
									}
								}
								
								$condition = array($cond_k => $cond_v);
								$queryData['conditions'] += $condition;
							}
						}
						
						$queryData['order'] = array();
						$queryData['fields'] = '';
						$queryData['limit'] = $assocData['limit'];
						
						return $queryData;  
						break;
					case 'hasAndBelongsToMany' :  
						return null;  
			}
			
			return null;  
		}
		
		/** 
		* QueryAssociation 
		*  
		*/   
		function queryAssociation(& $model, & $linkModel, $type, $association, $assocData, & $queryData, $external = false, & $resultSet, $recursive, $stack) {
			foreach($resultSet as $projIndex => $row) { 
				$queryData = $this->generateAssociationQuery($model, $linkModel, $type, $association, $assocData, $queryData, $external, $row); 
			
				$associatedData = $this->readAssociated($linkModel, $queryData, $recursive); 
					
				foreach($associatedData as $assocIndex => $relatedModel) { 
					$modelName = key($relatedModel); 
					$resultSet[$projIndex][$modelName][$assocIndex] = $relatedModel[$modelName]; 
				}
			}
		}
		
		/**  
		* readAssociated 
		* very similar to read but for related data 
		* unlike read does not make a reference to the passed model 
		*  
		* @param Model $model  
		* @param array $queryData  
		* @param integer $recursive Number of levels of association  
		* @return unknown  
		*/
		function readAssociated($linkedModel, $queryData = array (), $recursive = null) {
			$model =& $linkedModel;
			$git = new Git($this->_config);
			$results = array();
			$linkedModels = array(); 
			
			if (!is_null($recursive)) { 
				$_recursive = $model->recursive; 
				$model->recursive = $recursive; 
			} 
			
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
			
			// ================================ 
			// = Searching for Related Models = 
			// ================================ 
			if ($model->recursive > 0) {
				foreach ($model->__associations as $type) {
					foreach ($model->{$type} as $assoc => $assocData) {
						$linkModel =& $model->{$assoc};
						debug($linkModel);
						
						if (!in_array($type . '/' . $assoc, $linkedModels)) {
							if ($model->useDbConfig == $linkModel->useDbConfig) {
								$db =& $this; 
							} else {
								$db =& ConnectionManager::getDataSource($linkModel->useDbConfig); 
							} 
						} elseif ($model->recursive > 1 && ($type == 'belongsTo' || $type == 'hasOne')) { 
							$db =& $this; 
						} 
						
						if (isset($db)) { 
							$stack = array($assoc); 
							$db->queryAssociation($model, $linkModel, $type, $assoc, $assocData, $array, true, $results, $model->recursive - 1, $stack); 
							unset($db); 
						} 
					}
				}
			}
			
			if (!is_null($recursive)) { 
				$model->recursive = $_recursive; 
			} 
			
			return $results;
		}
	}
?>