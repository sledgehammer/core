<?php
namespace Sledgehammer;
/**
 * PearInstaller, installs PEAR packages into your project folder.
 *
 * @link http://pear.php.net/manual/en/core.rest.php
 * @link http://pear.php.net/manual/en/guide.developers.package2.tags.php
 *
 * @package Core
 */
class PearInstaller extends Observable {

	/**
	 * @var array The events/listeners
	 */
	protected $events = array(
		'installing' => array(),
		'installed' => array(),
		'channelAdded' => array(),
	);

	/**
	 * @var array The target directories per role. array("php" => PATH.'pear/classes')
	 */
	private $targets;
	/**
	 * @var array domain => restUrl;
	 */
	private $channels = array();

	/**
	 * @var array package => url
	 */
	private $packages = array();

	/**
	 * @param array $targets The target directories per role. array("php" => PATH.'pear/classes', 'doc' => ...)
	 */
	function __construct($targets) {
		$this->targets = $targets;
	}

	/**
	 * Discovers all packages inside the given domain/channel
	 *
	 * @param string $domain  Channel/Domain. Example: 'pear.php.net', 'pear.phpunit.de', 'pear.doctrine-project.org'
	 */
	function addChannel($domain) {
		if (isset($this->channels[$domain])) {
			return false;
		}
		$data = simplexml_load_file('http://'.$domain.'/channel.xml');
		$baseurl = (string) $data->servers->primary->rest->baseurl[0];
		$this->channels[$domain] = array(
			'baseurl' => $baseurl,
			'categories' => array(),
			'packages' => array(),
		);
		$pear = $this;

		$xml = simplexml_load_file($baseurl.'c/categories.xml');
		foreach ($xml->c as $category) {
			$url = $category->attributes('http://www.w3.org/1999/xlink');
			$category = (string) $category;
			$this->channels[$domain]['categories'][] = $category;
			cURL::get('http://'.$domain.dirname($url['href']).'/packages.xml', array(), function ($data) use ($pear, $domain, $category) {
						$pear->registerCategory($domain, $category, simplexml_load_string($data)->p);
					});
		}
		cURL::synchronize();
		$this->trigger('channelAdded', $this, $domain, $this->channels[$domain]);
	}

	/**
	 * Registers the packages from the associated channel & category
	 *
	 * @param string $channel
	 * @param string $category
	 * @param \SimpleXMLElement $packages
	 */
	function registerCategory($channel, $category, $packages) {
		foreach ($packages as $package) {
			$url = $package->attributes('http://www.w3.org/1999/xlink');
			$package = (string) $package;
			$this->packages[$package] = $channel;
			$this->channels[$channel]['packages'][$package] = array(
				'channel' => $channel,
				'category' => $category,
				'path' => (string) $url['href']
			);
		}
	}

	/**
	 * Get a list of names for all detected packages.
	 *
	 * @return array
	 */
	function getPackages() {
		return array_keys($this->packages);
	}

	/**
	 * Download and install a PEAR package.
	 *
	 * @throws Exceptions on failure
	 * @param string $package
	 * @param string $version
	 * @param array $options array(
	 *   'version' = Install a specific version
	 *   'target' => alternative target directory
	 *   'channel' => specifiy the channel
	 * )
	 */
	function install($package, $options = array()) {
		$version = array_value($options, 'version') ? : 'stable';
		if (isset($options['channel'])) {
			$channel = $options['channel'];
			$this->addChannel($channel);
			if (empty($this->channels[$channel]['packages'][$package])) {
				if (isset($this->channels[$channel]['packages'])) {
					foreach ($this->channels[$channel]['packages'] as $name => $info) {
						if (strcasecmp($name, $package) === 0) {
							return $this->install($name, $options);
						}
					}
				}
				throw new InfoException('Package "'.$package.'" not found in channel: '.$channel, quoted_human_implode(' and ', array_keys($this->channels[$channel]['packages'])));
			}
			$packageLocation = &$this->channels[$channel]['packages'][$package];
		} else {
			if (count($this->channels) === 0) {
				$this->addChannel('pear.php.net');
			}
			if (empty($this->packages[$package])) {
				foreach ($this->packages as $name => $channel) {
					if (strcasecmp($name, $package) === 0) {
						return $this->install($name, $options);
					}
				}
				throw new InfoException('Package "'.$package.'" not found in channels: '.quoted_human_implode(' and ', array_keys($this->channels)), 'Available packages: '.quoted_human_implode(' and ', array_keys($this->packages)));
			}
			$packageLocation = &$this->channels[$this->packages[$package]]['packages'][$package];
		}
		$release = $this->findRelease($packageLocation, $version);
		if (array_value($packageLocation, 'installed') === $version) {
			return;

		}
		$this->trigger('installing', $this, $package, $version);
		$tmpFolder = TMP_DIR.'PearInstaller/';
		$folderName = $package.'-'.$version;
		$tarFile = $tmpFolder.$folderName.'/package.tar';
		mkdirs(dirname($tarFile));
		if (file_exists($tarFile) === false) { // Is this package already in the tmp folder
			cURL::download($release->g.'.tar', $tarFile);
		}
		chdir(dirname($tarFile));
		system('tar xf '.escapeshellarg($tarFile), $exit);
		if ($exit !== 0) {
			throw new \Exception('Unable to untar "'.$tarFile.'"');
		}
		if (file_exists(dirname($tarFile).'/package2.xml')) {
			$info = simplexml_load_file(dirname($tarFile).'/package2.xml');
		} else {
			$info = simplexml_load_file(dirname($tarFile).'/package.xml');
		}
		// Install dependencies first
		foreach ($info->dependencies->required->package as $dependancy) {
			if ($dependancy->conflicts) {
//				notice('Dependancy "'.$dependancy->name.'" for "'.$package.'" <conflicts />');
				continue;
			}
			$this->install((string) $dependancy->name, array(
				'channel' => (string) $dependancy->channel,
			));
		}
		$renames = array();
		foreach ($info->phprelease as $release) {
			if ($release->count() > 0) {
				foreach ($release->filelist->install as $move) {
					$renames[(string) $move['name']] = (string) $move['as'];
				}
			}
		}
		$files = $this->extractFiles($info->contents->dir, '', '/', $renames);
		foreach ($files as $file) {
			if (isset($this->targets[$file['role']])) {
				$dir = $this->targets[$file['role']];
				if (in_array($file['role'], array('doc', 'www'))) {
					if (text($file['to'])->startsWith($package) == false) {
						$dir = $this->makePath($dir, $package);
					}
				}
				$target = $this->makePath($dir, $file['to']);
				if (mkdirs(dirname($target)) == false || is_writable(dirname($target)) == false) {
					throw new \Exception('Target "'.$target.'" is not writable');
				}
				$source = $this->makePath($tmpFolder.$folderName.'/'.$folderName, $file['from']);
				if (isset($file['tasks'])) {
					$contents = file_get_contents($source);
					foreach ($file['tasks'] as $task) {
						$value = null;
						if ($task['type'] === 'package-info') {
							if ($task['to'] == 'version') {
								$value = $version;
							} elseif ($task['to'] == 'state') {
								$value = (string) $info->stability->release;
							}
						} elseif ($task['type'] == 'pear-config') {
							if (substr($task['to'], -4) === '_dir') {
								$role = substr($task['to'], 0, -4);
								if (isset($this->targets[$role])) {
									$value = $this->targets[$role];
									// @todo calculate relative paths
									notice('Harcoding path "'.$value.'" into "'.$file['to'].'"', $file);
								}
							} elseif ($task['to'] == 'php_bin') {
								$value = trim(`which php`);
								notice('Harcoding path "'.$value.'" into "'.$file['to'].'"', $file);
							}
						}
						if ($task['task'] === 'replace') {
							if ($value != '') {
								$contents = str_replace($task['from'], $value, $contents);
							} else {
								notice($task['type'].' "'.$task['to'].'" not yet supported');
							}
						} else {
							notice('task "'.$task['task'].'" not implemented');
						}
					}
					file_put_contents($target, $contents);
				} else {
					copy($source, $target);
				}
			}
		}
		rmdir_recursive($tmpFolder.$folderName.'/'.$folderName);
		$packageLocation['installed'] = $version;
		$this->trigger('installed', $this, $package, $version);
	}

	/**
	 * Fetch the release info (download link) of a package
	 * If the version is "stable", "beta" or "latest" the $version will be set the versionnumber
	 *
	 * @param array $package
	 * @param string $version
	 * @return \SimpleXMLElement
	 * @throws \Exception
	 */
	function findRelease($package, &$version) {
		$info = simplexml_load_file('http://'.$package['channel'].$package['path'].'/info.xml')->r->attributes('http://www.w3.org/1999/xlink');
		$url = 'http://'.$package['channel'].$info['href'].'/';
		if (in_array($version, array('stable', 'beta', 'latest'))) {
			$version = file_get_contents($url.$version.'.txt');
			if (preg_match('/^[0-9]+/', $version) == false) {
				throw new \Exception('Invalid version number: "'.$version.'"');
			}
		}
		return simplexml_load_file($url.$version.'.xml');
	}

	/**
	 * Extract files from the <dir> in the package(2).xml
	 *
	 * @return array
	 */
	private function extractFiles($dir, $from = '', $to ='', $renames = array()) {
		$from .= (string) $dir['name'];
		if ($dir['baseinstalldir'] !== null) {
			$to = (string) $dir['baseinstalldir'];
		}
		$files = array();
		foreach ($dir->dir as $subdir) {
			$files += $this->extractFiles($subdir, $from, $to, $renames);
		}
		foreach ($dir->file as $data) {
			$target = (string) $data['name'];
			if (isset($renames[$target])) {
				$target = $renames[$target];
			}
			$file = array(
				'role' => (string)$data['role'],
				'from' => $this->makePath($from, $data['name']),
				'to' => $this->makePath($to, $target),
			);
			if ($data['md5sum']) {
				$file['md5'] = (string)$data['md5sum'];
			}
			$tasks = $data->children('http://pear.php.net/dtd/tasks-1.0');
			if ($tasks->count() > 0) {
				foreach ($tasks as $task) {
					if ($task->getName() == 'replace') {
						$replace = $task->attributes();
						$file['tasks'][] = array(
							'task' => 'replace',
							'type' => (string)$replace['type'],
							'from' => (string)$replace['from'],
							'to'   => (string)$replace['to']
						);
					}
				}
			}
			if ($data['baseinstalldir']) {
				$file['to'] = $this->makePath($data['baseinstalldir'], $target);
			}
			$files[] = $file;
		}
		return $files;
	}

	private function makePath($folder, $filename) {
		if (substr($folder, -1) === '/') {
			if (substr($filename, 0, 1) === '/') {
				return $folder.substr($filename, 1);
			}
			return $folder.$filename;
		}
		if (substr($filename, 0, 1) === '/') {
			return $folder.$filename;
		}
		return $folder.'/'.$filename;
	}
}

?>