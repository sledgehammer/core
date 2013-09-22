<?php
/**
 * Html
 */
namespace Sledgehammer;
/**
 * Html, a view compatible class for rendering raw html.
 * Contains static helper functions for generating tags, etc.
 *
 * @package Core
 */
class Html extends Object {

	/**
	 * The output string.
	 * @var string
	 */
	private $html;

	/**
	 * The View headers.
	 * @var array
	 */
	private $headers;

	/**
	 * Constructor
	 * @param string $html
	 * @param array $headers
	 */
	function __construct($html, $headers = array()) {
		$this->html = $html;
		$this->headers = $headers;
	}

	/**
	 * Output the html.
	 */
	function render() {
		echo $this->html;
	}

	/**
	 * Returns the html.
	 * Allows `echo $html;`
	 *
	 * @return string
	 */
	function __toString() {
		return $this->html;
	}

	/**
	 * Return View headers.
	 * @return array
	 */
	function getHeaders() {
		return $this->headers;
	}

	/**
	 * Generate a HTML element.
	 *
	 * @param string $name  Name of the element. Example "div", "img", "script", etc
	 * @param array $attributes array('type'=> 'checkbox', 'checked' => true, 'disabled')
	 * @param bool|string|array $contents  true: Only generate the opentag, string html, array with sub elements
	 * @return Html
	 */
	static function element($name, $attributes, $contents = '') {
		$name = strtolower($name);
		$element = new Html('<'.$name);
		foreach ($attributes as $key => $value) {
			if (is_int($key)) {
				if (preg_match('/^[a-z\\-_:]+$/i', $value)) {
					$element->html .= ' '.strtolower($value);
				} else {
					notice('Invalid attribute: '.$key, $value);
				}
			} elseif (is_bool($value)) {
				if ($value) {
					$element->html .= ' '.strtolower($key);
				}
			} else {
				$element->html .= ' '.strtolower($key).'="'.self::escape($value).'"';
			}

		}
		if ($contents === true) { // Only generate the open tag?
			$element->html .= '>';
			return $element;
		}
		if ($contents === '') { // Close the tag?
			if (in_array($name, array('area', 'base', 'br', 'hr', 'input', 'img', 'link', 'meta'))) {
				$element->html .= ' />';
			} else {
				$element->html .= '></'.$name.'>';
			}
			return $element;
		}

		$element->html .= '>';
		if (is_array($contents)) {
			foreach ($contents as $sub_element) {
				$element->html .= $sub_element;
			}
		} else {
			$element->html .= $contents;
		}
		$element->html .= '</'.$name.'>';
		return $element;
	}

	/**
	 * Build an icon tag <img class="icon" /> or <span class="glyphicon glyphicon-*"></span>
	 *
	 * @link http://getbootstrap.com/components/#glyphicons
	 * @param string Name or URL of the icon
	 * @return Html
	 */
	static function icon($icon) {
		if (preg_match('/^[a-z-]+$/', $icon)) {
			return self::element('span', array('class' => 'glyphicon glyphicon-'.$icon));
		}
		if (preg_match('/^http[s]|^\/|^[.]{1,2}\//', $icon) === 0) { // relative url?
			$icon = WEBROOT.$icon;
		}
		return self::element('img', array('src' => $icon, 'class' => 'icon', 'alt' => ''));
	}

	/**
	 * htmlentities() using the current charset.
	 *
	 * @param string $text
	 * @param int $flags ENT_COMPAT, ENT_QUOTES, ENT_NOQUOTES, ENT_IGNORE , ENT_SUBSTITUTE, ENT_DISALLOWD, ENT_HTML401, ENT_XML1, ENT_XHTML, ENT_HTML5
	 * @return string
	 */
	static function escape($text, $flags = ENT_COMPAT) {
		return htmlentities($text, ENT_COMPAT, Framework::$charset);
	}

}

?>
