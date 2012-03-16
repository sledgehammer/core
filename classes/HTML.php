<?php
/**
 * HTML, an view compatible class for rendering raw html.
 * Contains static helper functions for generating tags, etc
 *
 * @package Core
 */
namespace SledgeHammer;
class HTML extends Object {

	/**
	 * @var string $html
	 */
	private $html;

	/**
	 * @var array
	 */
	private $headers;

	/**
	 *
	 * @param string $html
	 * @param array $headers
	 */
	function __construct($html, $headers = array()) {
		$this->html = $html;
		$this->headers = $headers;
	}

	function render() {
		echo $this->html;
	}

	function __toString() {
		return $this->html;
	}

	function getHeaders() {
		return $this->headers;
	}

	/**
	 * Generate a HTML element
	 *
	 * @param string $name  Name of the element. Example "div", "img", "script", etc
	 * @param array $attributes
	 * @param bool|string|array $contents  true: Only generate the opentag, string html, array with sub elements
	 * @return HTML
	 */
	static function element($name, $attributes, $contents = '') {
		$name = strtolower($name);
		$element = new HTML('<'.$name);
		foreach ($attributes as $key => $value) {
			$element->html .= ' '.strtolower($key).'="'.self::escape($value).'"';
		}
		if ($contents === true) { // Only generate the open tag?
			$element->html .= '>';
			return $element;
		}
		if ($contents === '') { // Close the tag?
			if (in_array($name, array('img', 'meta', 'link', 'param', 'input', 'br', 'hr', 'div'))) {
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
	 * Build an icon tag <img class="icon" /> or <i class="icon-*"></i>
	 *
	 * @link http://twitter.github.com/bootstrap/base-css.html#icons
	 * @param string Name or URL of the icon
	 * @return HTML
	 */
	static function icon($icon) {
		if (preg_match('/^[a-z-]+$/', $icon)) {
			return self::element('i', array('class' => 'icon-'.$icon));
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
