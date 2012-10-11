<?php
/**
 * Vergelijkt 2 environments, en geeft aan constantes/database settings verschillen/ontbreken.
 *
 */
namespace Sledgehammer;
class CompareEnvironments extends Util {

	function __construct() {
		$this->title = 'Compare environment settings';
	}

	/**
	 * Constroleerd op verschillende opties
	 *
	 * @param array $data Dit array wordt gevult met de verschillen
	 * @param string $module Naam van de betreffende module
	 * @param string $file Type bestand (constants.ini / database.ini)
	 * @param array $source Array met opties van de bron environment
	 * @param array $target Array met opties van de doel environment
	 */
	function formatted_diff($source, $target) {
		$data = array();
		$missing_in_source = array_diff_key($target, $source);
		if (count($missing_in_source) > 0) {
			foreach ($missing_in_source as $key => $null) {
				$data[] = array(
						'setting' => $key,
						'source' => '<span style="color:red">Missing</span>',
						'target' => htmlentities($target[$key]),
						);
			}
		}
		foreach ($source as $key => $value) {
			if (!isset($target[$key])) {
				$data[] = array(
						'setting' => $key,
						'source' => htmlentities($value),
						'target' => '<span style="color:red">Missing</span>',
						);
			} elseif (!equals($source[$key], $target[$key])) {
				$data[] = array(
						'setting' => $key,
						'source' => htmlentities($value),
						'target' => htmlentities($target[$key]),
						);
			}
		}
		return $data;
	}

	function execute() {
		// Genereer een formulier waarmee je 2 environments kan kiezen.
		$environments = array('development', 'staging', 'production');
		$Form = new Form(array('method' => 'get'), array(
			new Fieldset('Compare environments', array(
				'environments' => new FieldLabel('Environments', new Fields(array(
					'source' => new SelectBox('source', $environments, array(), new NotEmptyValidator()),
					'target' => new SelectBox('target', $environments, array(), new NotEmptyValidator()),
					new Input('submit', NULL, array('value' => 'Compare')),
				))),
			)),
		));
		$values = $Form->import($errors);
		if (!$values) { // Zijn er geen environments gekozen?
			return $Form;
		}
		$source = $values[0]['environments']['source'];
		$target = $values[0]['environments']['target'];
		$modules = Sledgehammer::getModules($this->paths['modules']);
		$constants_diff = array();
		/*
		// Loop door alle modules en vergelijk de constantes
		foreach($modules as $module) {
			$constants_ini = DEVHOOK_PATH.$module['folder'].'settings/constants.ini';
			if (!file_exists($constants_ini)) {
				continue;
			}
			$constants = parse_ini_file($constants_ini, true);
			$source_constants = isset($constants[$source]) ? $constants[$source] : array();
			$target_constants = isset($constants[$target]) ? $constants[$target] : array();
			$data = formatted_diff($source_constants, $target_constants);
			if (count($data) > 0) {
				foreach($data as $row) {
					$row['Module'] = $module['name'];
					$constants_diff[] = $row;
				}
			}
		}*/
		// Vergelijk database instellingen
		$database_diff = array();
		$db_links_compared = array();
		$database_ini = $this->paths['project'].'app/database.ini';
		if (file_exists($database_ini)) {
			$database_settings = parse_ini_file($database_ini, true);
			foreach ($database_settings as $env_and_link => $settings) {
				$exploded_env_and_link = explode('.', $env_and_link);
				$environment = $exploded_env_and_link[0];
				$link = $exploded_env_and_link[1];
				if (in_array($link, $db_links_compared)) { // Is deze link al gecontroleerd?
					continue; // door met de volgende link
				}
				$data = false;
				if ($environment == $source) { // Is deze database setting voor de bron environment?
					if (isset($database_settings[$target.'.'.$link])) { // Is deze link ook voor de target environment geconfigureerd?
						$target_settings = $database_settings[$target.'.'.$link];
					} else {
						$target_settings = array(); // Er zijn geen target settings
					}
					$data = formatted_diff($settings, $target_settings);
				} elseif ($environment == $target) {
					if (isset($database_settings[$source.'.'.$link])) { // Is deze link ook voor de target environment geconfigureerd?
						$source_settings = $database_settings[$source.'.'.$link];
					} else {
						$source_settings = array(); // Er zijn geen target settings
					}
					$data = formatted_diff($source_settings, $settings);
				}
				if ($data) {
					foreach ($data as $row) {
						$row['Link'] = '['.$link.']';
						$database_diff[] = $row;
					}
					$db_links_compared[] = $link;
				}
			}
		}
		$output = '';
		if (count($constants_diff) > 0) {
			$ConstantsDiff = new InteractiveTable(array('Module', 'setting', 'source', 'target'), '#');
			$ConstantsDiff->headers = array(
					'setting' => 'Constant',
					'source' => '['.$source.']',
					'target' => '['.$target.']',
					);
			$ConstantsDiff->Iterator = $constants_diff;
			$output .= '<h2>Constants.ini\'s</h2>'.view_to_string($ConstantsDiff);
		}

		if (count($database_diff) > 0) {
			$DatabaseDiff = new InteractiveTable(array('Link', 'setting', 'source', 'target'), '#');
			$DatabaseDiff->headers = array(
					'setting' => 'Setting',
					'source' => '['.$source.']',
					'target' => '['.$target.']',
					);
			$DatabaseDiff->Iterator = $database_diff;
			$output .= '<h2>Database.ini</h2>'.view_to_string($DatabaseDiff);
		}

		if ($output == '') {
			$output .= view_to_string(Alert::info('<h3>No differences found</h3>The environments are identical'));
		}
		return new HTML(view_to_string($Form).'<br />'.$output);
	}
}
?>
