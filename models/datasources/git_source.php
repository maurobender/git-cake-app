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
				'parenta' => array(
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
			)
		);
		
		protected $_model_table_map = array(
			'Repository' => 'repositories',
			'Commit' => 'commits'
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
			
			switch($model->name) {
				case 'Repository':
					$repositories = $git->getRepositories($queryData['conditions'], $queryData['limit']);
					
					foreach ($repositories as $repository => $repository_path) {
						$stats = $this->getStats($repository, 0, 0);
						$repo['Repository'] = array(
							'name'			=> $repository,
							'path'			=> $repository_path,
							'description'	=> file_get_contents("{$repository_path}description"),
							'owner'			=> $this->fileOwner($repository_path),
							'last_change'	=> $this->lastChange($repository_path),
							'today' => $stats['today'],
							'total' => $stats['total']
						);
						
						$results[] = $repo;
					}
					break;
				case 'Commit':
					$commits = $git->getCommits($queryData['conditions'], $queryData['limit']);
					
					foreach($commits as $commit) {
						$temp['Commit'] = $commit;
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
		
/*		function findAll() {
			list($repositories, $valid) = Git::loadRepositories($this->_config);

			$repos = array();
			foreach ($repositories as $repository) {
					$repo = array(
						'link'          => $this->link($repository),
						'description'   => file_get_contents("{$repository}{$this->_config['repo_suffix']}description"),
						'owner'         => $this->fileOwner($repository),
						'last_change'   => $this->lastChange($repository),
						'download'      => $this->link($repository, array('download' => true)),
					);
					$stats = $this->getStats($repository, 0, 0);
					$repo['today'] = $stats['today'];
					$repo['total'] = $stats['total'];
					$repos[] = $repo;
			}
			return $repos;
		}*/

		function link($repo, $options = array()) {
			$options = array_merge(array(
					'download' => false,
					'tag' => 'HEAD',
			), $options);

			$path = basename($repo);
			if ($options['download']) {
					return sprintf('<a href="/%s/downloads/%s">snapshot</a>', $path, $options['tag']);
			}
			return sprintf('<a href="/%s">%s</a>', $path, $path);
		}

		function fileOwner($repo) {
			$out = array();
			//$cmd = "GIT_DIR=" . escapeshellarg($repo) . " {$this->_config['git_binary']} rev-list --header --max-count=1 HEAD 2>&1 | grep -a committer | cut -d' ' -f2-3";
			$cmd = "GIT_DIR=" . escapeshellarg($repo) . " {$this->_config['git_binary']} rev-list --pretty=format:'commiter: %ce' --max-count=1 HEAD 2>&1 | grep commiter | cut -c11-";
			$own = exec($cmd, &$out);
			return $own;
		}

		function lastChange($repo) {
			$out = array();
			$cmd = "GIT_DIR=" . escapeshellarg($repo . $this->_config['repo_suffix']) . " {$this->_config['git_binary']} rev-list  --header --max-count=1 HEAD 2>&1 | grep -a committer | cut -d' ' -f5-6";
			$date = exec($cmd, &$out);
			return date('d-m-Y', (int) $date);
		}

		function repoPath($proj) {
			return Git::repoPath($proj);
		}

		function getTags($proj) {
			return Git::parse($this->_config, $proj, 'tags');
		}

		function getBranches($proj) {
			return Git::parse($this->_config, $proj, 'branches');
		}

		function getStats($repository, $inc = false, $fbasename = 'counters') {
			return Git::stats($repository, $inc, $fbasename);
		}

		function getOwner($proj) {
			$path = Git::repoPath($proj);
			return self::fileOwner($path);
		}

		function getLastChange($proj) {
			$path = Git::repoPath($proj);
			return self::lastChange($path);
		}

		function getShortlog($proj) {
			return Git::shortlogs($this->_config, $proj);
		}

		function getDiff($proj, $commit) {
			return Git::diff($this->_config, $proj, $commit);
		}

		function getTree($proj, $filepath = 'HEAD') {
			if ($filepath != 'HEAD') $filepath = "HEAD:{$filepath}";
			return Git::lsTree($this->_config, $proj, $filepath);
		}

		function getCommit($proj, $commit) {
			$commit = Git::commit($this->_config, $proj, $commit);
			if (is_array($commit) && count($commit) == 1) return current($commit);
			return $commit;
		}

		function getDescription($proj) {
			$path = Git::repoPath($proj);
			return file_get_contents("{$path}{$this->_config['repo_suffix']}/description");
		}
	}
?>