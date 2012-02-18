<?php
namespace SledgeHammer;
/**
 * PearInstaller
 *
 * @package Core
 */
class PearInstaller extends Observable {

	protected $events = array(
		'installing' => array(),
		'installed' => array(),
		'channelAdded' => array(),
	);

	/**
	 * @var array domain => restUrl;
	 */
	private $channels = array();

	/**
	 * @var array package => url
	 */
	private $packages = array();


	/**
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
		$pm = $this;

		$xml = simplexml_load_file($baseurl.'c/categories.xml');
		foreach ($xml->c as $category) {
			$url = $category->attributes('http://www.w3.org/1999/xlink');
			$category = (string) $category;
			$this->channels[$domain]['categories'][] = $category;
			cURL::get('http://'.$domain.dirname($url['href']).'/packages.xml', array(), function ($data) use ($pm, $domain, $category) {
						$pm->registerCategory($domain, $category, simplexml_load_string($data)->p);
					});
		}
		cURL::synchronize();
		$this->trigger('channelAdded', $this, $domain, $this->channels[$domain]);
	}

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

	function getPackages() {
		return array_keys($this->packages);
	}

	/**
	 *
	 * @param type $package
	 * @param type $version
	 * @param array $options array(
	 *   'version' = Install a specific version
	 *   'target' => alternative target directory
	 *   'channel' => specifiy the channel
	 * )
	 * @throws Exceptions on failure
	 */
	function install($package, $options = array()) {
		$targetFolder = array_value($options, 'target') ?: APPLICATION_DIR.'pear';
		$version = array_value($options, 'version') ?: 'stable';
		if (mkdirs($targetFolder) == false || is_writable($targetFolder) == false) {
			throw new \Exception('Target "'.$targetFolder.'" not writable');
		}
		if (isset($options['channel'])) {
			$channel = $options['channel'];
			$this->addChannel($channel);
			if (empty($this->channels[$channel]['packages'][$package])) {
				throw new InfoException('Package "'.$package.'" not found in channel: '.$channel, quoted_human_implode(' and ', array_keys($this->channels[$channel]['packages'])));
			}
			$release = $this->findRelease($this->channels[$channel]['packages'][$package], $version);
		} else {
			if (empty($this->packages[$package])) {
				throw new InfoException('Package "'.$package.'" not found in channels: '.quoted_human_implode(' and ', array_keys($this->channels)), 'Available packages: '.quoted_human_implode(' and ', array_keys($this->packages)));
			}
			$release = $this->findRelease($this->channels[$this->packages[$package]]['packages'][$package], $version);
		}
		$this->trigger('installing', $this, $package, $version);
		$tmpFolder = TMP_DIR.'PearInstaller/';
		$folderName = $package.'-'.$version;
		$tarFile = $tmpFolder.$folderName.'/package.tar';
		mkdirs(dirname($tarFile));
		if (file_exists($tarFile) === false) { // Is this package already in the tmp folder
			cURL::download($release->g.'.tar', $tarFile);
			chdir(dirname($tarFile));
			system('tar xf '.escapeshellarg($tarFile), $exit);
			if ($exit !== 0) {
				throw new \Exception('Unable to untar "'.$tarFile.'"');
			}
		}
		$info = simplexml_load_file(dirname($tarFile).'/package.xml');
		// Install dependencies first
		foreach ($info->dependencies->required->package as $dependancy) {
//			if (empty($this->packages[(string) $dependancy->name])) {
//				throw new InfoException('Dependancy "'.$dependancy->name.'" not found (Requires channel: "'.$dependancy->channel.'")', 'Current channels: '.quoted_human_implode(' and ', array_keys($this->channels)));
//			}
			$this->install((string) $dependancy->name, array(
				'target' => $targetFolder,
				'channel' => (string) $dependancy->channel,
			));
		}
		$renames = array();
//		$x = new \SimpleXMLElement();
		foreach ($info->phprelease as $release) {
			if ($release->count() > 0) {
				foreach ($release->filelist->install as $move) {
					$renames[(string)$move['name']] = (string)$move['as'];
				}
			}
		}
		foreach ($info->contents->dir as $dir) {
			foreach ($dir->file as $file) {
				if ($file['role'] == 'php') {
					$target = (string)$file['name'];
					if (isset($renames[$target])) {
						$target = $renames[$target];
					}
					$target = $targetFolder.'/'.$target;
					mkdirs(dirname($target));
					copy($tmpFolder.$folderName.'/'.$folderName.'/'.$file['name'], $target);
				}
			}
		}
		$this->trigger('installed', $this, $package, $version);
	}

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

	private function callback($method) {
		$pm = $this;
		return function ($curl) use ($pm, $method) {
					$pm->$method($curl);
				};
	}

}

?>
