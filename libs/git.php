<?php
	class Git {
		protected $repos;
		protected $config;
		
		/**
		* @brief The constructor.
		* @param array <b>$condig</b> The configuration needed for the class:
		*	<ul>
		*		<li><i>repo_directory: </i> The repositories root folder path.</li>
		*		<li><i>repo_suffix: </i> The repositories suffix, commonly '.git'.</li>
		*		<li><i>git_binary: </i> The path to the git binary excutable.</li>
		*	</ul>
		*/
		public function __construct($config) {
			if(!in_array('repo_directory', array_keys($config))
				|| !in_array('repo_suffix', array_keys($config))
				|| !in_array('git_binary', array_keys($config))) {
				echo 'Git Error: No a valid configuration was specified. The class couldn\'t be constructed.';
				return;
			}
			
			$this->config = $config;
		}
		
		/**
		* @brief Return the repositories wich are in the repositories root directory.
		* @param array <b>$conditions</b> Filters for the repositories:
		*	<ul>
		*		<li><i>repository: </i> Repository name.</li>
		*	</ul>
		* @param integer <b>$limit</b> The count of repositories to return.
		* @return array An array of repositories.
		*
		* TODO The <b>$limits</b> actually doesn't do anything. Make it work.
		* TODO The <b>$conditions</b> array actually doesn't do anything. Make it work.
		*/
		public function getRepositories($conditions = array(), $limit = 0) {
			if (!isset($this->config['repo_directory'])) return array();
			
			$repoDir = $this->config['repo_directory'];
			if (!file_exists($repoDir)) return array();
			if (!is_dir($repoDir)) return array();
			
			$repos = array();
			// Open the repositories directory.
			if ($handle = opendir($repoDir)) {
				// Walk trought the repositories.
				while (false !== ($file = readdir($handle))) {
					
					$repo_path = $repoDir . $file;
					$repo = str_replace($this->config['repo_suffix'], '', $file);
					
					if ($file[0] != '.' && is_dir($repo_path)) {
						if (is_dir($repo_path)) {
							$headFile = "HEAD";
							if (substr($repo_path, -1) != "/") {
								$headFile = "/{$headFile}";
							}
							if (file_exists($repo_path . $headFile)
							&& $this->getOwner($repo_path) != NULL) {
								$repos[trim($repo)] = trim("{$repo_path}/");
							}
						}
					}
				}
				
				closedir($handle);
			}
			
			foreach ($repos as $repository => $repository_path) {
				$repo = array(
					'name'			=> $repository,
					'path'			=> $repository_path,
					'description'	=> file_get_contents("{$repository_path}description"),
					'owner'			=> $this->fileOwner($repository_path),
					'last_change'	=> $this->lastChange($repository_path),
				);
				
				$results[] = $repo;
			}
			
			return $results;
		}
		
		
		/**
		* @brief Return the commits for all the projects or for one particular project.
		* @param array <b>$conditions</b> Filters for the commits:
		*	<ul>
		*		<li><i>repository: </i> Repository that the commit(s) belongs.</li>
		*		<li><i>hash: </i> Hash of the commit.</li>
		*	</ul>
		* @param integer <b>$limit</b> The count of commits to return.
		* @return array An array of commits.
		*/
		public function getCommits($conditions = array(), $limit = 0) {
			$results = array();
			$options = array();
			
			if($limit > 0)
				$options['count'] = $limit;
			
			if(isset($conditions['hash'])) {
				$options['since'] = $conditions['hash'];
				$options['count'] = 1;
			}
			
			if(isset($conditions['repository'])) {
				$repo_path = $this->config['repo_directory'] . $conditions['repository'] . $this->config['repo_suffix'];
				$results = $this->getLastNCommits($repo_path, $options);
				
				foreach($results as $result_k => $result_v)
					$results[$result_k] += array('repository' => $conditions['repository']);
			} else {
				$repos = $this->getRepositories();
				
				foreach($repos as $repo) {
					$temp = $this->getLastNCommits($repo, $options);
					
					foreach($temp as $temp_k => $temp_v)
						$temp[$temp_k] += array('repository' => $repo);
						
					$results += $temp;
				}
			}
			
			return $results;
		}
		
		/**
		* @brief Return the files of an repository.
		* @param array <b>$conditions</b> Filters for the files:
		*	<ul>
		*		<li><i>commit: </i> Commit that the file(s) belongs.</li>
		*		<li><i>repository: </i> Repository that the file(s) belongs.</li>
		*		<li><i>path: </i> The path of the folder that the(s) file belongs.</li>
		*		<li><i>name: </i> Name of the file.</li>
		*	</ul>
		* @param integer <b>$limit</b> The count of files to return.
		* @return array An array of files.
		*/
		public function getFiles($conditions = array(), $limit = 0) {
			$files = array();
			
			$commit = (isset($conditions['commit']) ? $conditions['commit'] : 'HEAD');
			$repository = '';
			if(isset($conditions['repository']))
				$repository = $this->config['repo_directory'] . $conditions['repository'] . $this->config['repo_suffix'];
			$path = (isset($conditions['path']) ? $conditions['path'] : '');
			$name = (isset($conditions['name']) ? $conditions['name'] : '');
			$recursive = $name ==  '';
			
			if($repository == '') {
			} else {
				$files = $this->lsTree($repository, $commit . ':' . $path, $name, $recursive);
				
				foreach($files as $file_k => $file_v)
					$files[$file_k]['repository'] = $conditions['repository'];
			}
			
			foreach($files as $file_k => $file_v) {
				$file_v['path'] = ($path != '' ? $path . '/' . $file_v['path'] : $file_v['path']);
				
				if($file_v['type'] == 'blob')
					$files[$file_k]['content'] = $this->getFileContents($repository, $commit . ':' . $file_v['path']);
				else
					$files[$file_k]['content'] = '';
				
				$files[$file_k]['file'] = basename($file_v['path']);
				$files[$file_k]['path'] = (dirname($file_v['path']) == '.' ? '' : dirname($file_v['path']));
				$files[$file_k]['commit'] = $commit;
			}
			
			return $files;
		}
		
		/**
		* @brief Return the repository tags.
		*/
		public function getTags($conditions = array(), $limit = 0) {
			if(!isset($conditions['repository']))
				return array();
			
			$name = (isset($conditions['name']) ? $conditions['name'] : '');
			$repo = $this->config['repo_directory'] . $conditions['repository'] . $this->config['repo_suffix'];
			$tags = $this->parse($repo, 'tags', $name);
			
			foreach($tags as $tag_k => $tag_v) {
				$tags[$tag_k]['repository'] = $conditions['repository'];
			}
			
			return $tags;
		}

    public function getOwner($path) {
        $out = array();
        $cmd = "GIT_DIR=" . escapeshellarg($path) . " {$this->config['git_binary']} rev-list  --header --max-count=1 HEAD 2>&1 | grep -a committer | cut -d' ' -f2-3";
        $own = exec($cmd, &$out);
        return $own;
    }

	private function parse($repo, $what, $pattern = '' ) {
		$cmd1 = "GIT_DIR=" . escapeshellarg($repo) . " {$this->config['git_binary']} rev-parse  --symbolic --" . escapeshellarg($what) . "=*" . $pattern . "*  2>&1";
		$out1 = array();
		
		$results = array();
		exec($cmd1, &$out1);
		for($i = 0; $i < count($out1); $i++) {
				$temp = array('hash' => '', 'name' => '');
				
				$cmd2="GIT_DIR=" . escapeshellarg($repo) . " {$this->config['git_binary']} rev-list --max-count=1 " . escapeshellarg($out1[$i]) . " 2>&1";
				$out2 = array();
				exec($cmd2, &$out2);
				
				$temp['hash'] = $out2[0];
				$temp['name'] = $out1[$i];
				
				$results[] = $temp;
		}
		
		return $results;
	}
	
	public function stats($repo, $inc = false, $fbasename = 'counters') {
		$rtoday = 0;
		$rtotal = 0;
		$now = floor(time()/24/60/60); // number of days since 1970

		if (!is_dir(CACHE)) {
			mkdir(CACHE);
			chmod(CACHE, 0777);
		}

		$fname = CACHE . basename($repo);

		if (!is_dir($fname)) {
			mkdir($fname);
			chmod($fname, 0777);
		}

		$fname = CACHE . basename($repo) . "/" . $fbasename . "-" . basename($repo, ".git");
		$fd = 0;

		//$fp1 = sem_get(fileinode($fname), 1);
		//sem_acquire($fp1);

		if (file_exists($fname)) {
			$file = fopen($fname, "r+"); // open the counter file
		} else {
			$file = FALSE;
		}
		if ($file != FALSE) {
			fseek($file, 0); // rewind the file to beginning
			// read out the counter value
			fscanf($file, "%d %d %d", $fd, $rtoday, $rtotal);
			if($fd != $now) {
					$rtoday = 0;
					$fd = $now;
			}
			if ($inc) {
					$rtoday++;
					$rtotal++;
			}
			fclose($file);
		}
		// uncomment the next lines to erase the counters
		//$rtoday = 0;
		//$rtotal = 0;
		$file = fopen($fname, "w+"); // open or create the counter file
		// write the counter value
		fseek($file, 0); // rewind the file to beginning
		fwrite($file, "$fd $rtoday $rtotal\n");
		fclose($file);
		chmod($fname, 0666);
		return array('today' => $rtoday, 'total' => $rtotal);
	}
	
	public static function diff($config, $proj, $commit) {
		$out = array();
		$cmd = "GIT_DIR=" . self::$repos[$proj] . $config['repo_suffix'] . " {$config['git_binary']} show {$commit} --format=\"%b\" 2>&1";
		exec($cmd, &$out);

		$diff = false;
		$summary = array();
		$file = array();
		$results = array();
		foreach ($out as $line) {
			if (empty($line)) continue;
			if ($diff) {
					if (substr($line, 0, 4) === 'diff') {
						$results[] = array(
							'file' => implode("\n", $file),
							'summary' => implode("\n", $summary),
						);
						$file       = array();
						$summary    = array();
						$summary[]  = $line;
						$diff       = false;
					} else {
						$file[]     = $line;
					}
			} else {
					if (substr($line, 0, 3) === '@@ ') {
						$diff       = true;
						$file[]     = $line;
					} else {
						$summary[]  = $line;
					}
			}
		}
		$results[] = array(
			'file' => implode("\n", $file),
			'summary' => implode("\n", $summary),
		);
		return $results;
	}
	
	// Utility Funcions
	//==========================================================================
	
	private function getLastNCommits($repo, $options = array()) {
		$options = array_merge(array(
			'since' => 'HEAD',
			'until' => 'HEAD',
			'dry'   => false,
			'params' => array()
		), $options);
		
		if (isset($options['count']) && $options['count'] == 1) {
			$query = $options['since'];
		} else {
			$query = implode('..', array($options['since'], $options['until']));
			if (in_array($query, array('..', 'HEAD..HEAD'))) $query = '--all';
		}
		
		// --full-history --topo-order --skip=0
		
		$params     = array();
		if(isset($options['count']))
			$params[]   = "max-count={$options['count']}";
		foreach ($options['params'] as $param) {
			$params[]= $param;
		}
		$params     = implode(' --', $params);
		if (!empty($params)) $params = "--{$params}";
		
		$format     = array();
		$format[]   = 'parents %P';
		$format[]   = 'tree %T';
		$format[]   = 'author %aN';
		$format[]   = 'email %aE';
		$format[]   = 'timestamp %at';
		$format[]   = 'subject %s';
		$format[]   = 'endrecord%n';
		$format     = implode('%n', $format);
		
		$cmd = "GIT_DIR=" . $repo . " {$this->config['git_binary']} rev-list {$query} {$params} --pretty=format:\"{$format}\"";
		if ($options['dry']) return $cmd;
		$out = array();
		exec($cmd, &$out);
		
		$commit = array();
		$results = array();
		foreach ($out as $line) {
			$line = trim($line);
			
			if (empty($line)) {
					$results[] = array_merge(array(
						'parents' => array()
					), $commit);
					
					$commit = array();
					continue;
			}
			if ($line == 'endrecord') {
					// Commit exists, we can generate extra data here
					continue;
			}

			$descriptor = strstr($line, ' ', true);
			$info = trim(strstr($line, ' '));
			if ($descriptor == 'commit') {
					$commit['hash'] = $info;
			} else if ($descriptor == 'parents') {
					$commit['parents'] = explode(' ', $info);
			} else if ($descriptor == 'tree') {
					$commit['tree'] = $info;
			} else if ($descriptor == 'author') {
					$commit['author'] = $info;
			} else if ($descriptor == 'email') {
					$commit['email'] = $info;
			} else if ($descriptor == 'timestamp') {
					$commit['timestamp'] = $info;
			} else if ($descriptor == 'subject') {
					$commit['subject'] = $info;
			}
		}
		
		return $results;
	}
	
	private function fileOwner($repo) {
		$out = array();
		$cmd = "GIT_DIR=" . escapeshellarg($repo) . " {$this->config['git_binary']} rev-list --pretty=format:'commiter: %ce' --max-count=1 HEAD 2>&1 | grep commiter | cut -c11-";
		$own = exec($cmd, &$out);
		return $own;
	}

	private function lastChange($repo) {
		$out = array();
		$cmd = "GIT_DIR=" . escapeshellarg($repo) . " {$this->config['git_binary']} rev-list --pretty=format:'date: %at' --header HEAD --max-count=1 | grep date | cut -d' ' -f2-3";
		$date = exec($cmd, &$out);
		return date('d-m-Y', (int) $date);
	}
	
	private function lsTree($repo, $tree, $file = '', $recursive = false) {
		$out = array();
		$cmd = "GIT_DIR=" .$repo . " {$this->config['git_binary']} ls-tree " . $tree . " 2>&1";
		
		if($recursive)
			$cmd .= ' -r -t';
		
		if($file != '')
			$cmd .= " | grep {$file}";
		
		//Have to strip the \t between hash and file
		$cmd .= " | sed -e 's/\t/ /g'";

		exec($cmd, &$out);

		$results = array();
		foreach ($out as $line) {
				$results[] = array_combine(
					array('perm', 'type', 'hash', 'path'),
					explode(" ", $line, 4)
				);
		}
		
		return $results;
	}
	
	private function getFileContents($repo, $tree) {
		$out = array();
		$cmd = "GIT_DIR=" . escapeshellarg($repo) . " {$this->config['git_binary']} show " . $tree;
		exec($cmd, &$out);
		$content = implode($out, "\n");
		return $content;
	}
}