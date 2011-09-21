<?php
/**
 * Text, a string class for handeling (multibyte) strings with OOP syntax
 * Modelled after the C# String class.
 * @link http://msdn.microsoft.com/en-us/library/system.string.aspx
 * 
 * @package Core
 */
namespace SledgeHammer;
class Text extends Object implements \ArrayAccess {

	/**
	 * @var int  The number of characters
	 */
	public $length;
	
	/**
	 * @var string  The string in UTF-8 
	 */
	private $text;

	/**
	 * Construct the Text object and convert $text to UTF-8
	 * @param string $text  The text
	 * @param string $charset  The charset of $text, null will auto-detect
	 */
	function __construct($text, $charset = null) {
		if ($text instanceof Text) {
			$this->text = $text->text;
		}
		if ($charset === null) {
			$charset = mb_detect_encoding($text, array('ASCII', 'UTF-8', 'ISO-8859-15'), true);
			if ($charset === false) {
				notice('Unable to detect charset');
				$charset = 'UTF-8';
			}
		}
		$this->text = mb_convert_encoding($text, 'UTF-8', $charset);
		unset($this->length);
	}

	function __toString() {
		return $this->text;
	}
	
	/**
	 * Virtual propeties like "length"
	 * @param string $property
	 * @return mixed 
	 */
	function __get($property) {
		if ($property == 'length') {
			return mb_strlen($this->text, 'UTF-8');
		}
		parent::__get($property);
	}
	
	// Mutations
	
	function toUpper() {
		return new Text(mb_strtoupper($this->text, 'UTF-8'), 'UTF-8');
	}
	
	function toLower() {
		return new Text(mb_strtolower($this->text, 'UTF-8'), 'UTF-8');
	}
	function trim($charlist = null) {
		return new Text(trim($this->text, $charlist), 'UTF-8');
	}
	function trimStart($charlist = null) {
		return new Text(ltrim($this->text, $charlist), 'UTF-8');
	}
	function trimEnd($charlist = null) {
		return new Text(rtrim($this->text, $charlist), 'UTF-8');
	}
	
	function substring($offset, $length = null) {
		if ($length === null) {
			if (mb_internal_encoding() == 'UTF-8') {
				return new Text(mb_substr($this->text, $offset), 'UTF-8');
			}
			if ($offset < 0) {
				$length = $offset * -1;
			} else {
				$length = $this->length - $offset;
			}
		}
		return new Text(mb_substr($this->text, $offset, $length, 'UTF-8'), 'UTF-8');
	}

	function reverse() {
		$result = '';
		for($i = $this->length - 1; $i >= 0; $i--) {
			$result .= mb_substr($this->text, $i, 1, 'UTF-8');
		}
		return new Text($result, 'UTF-8'); 
	}

	function replace($search, $replace) {
		return new Text(str_replace($search, $replace, $this->text), 'UTF-8');
	}
	
	function split($separator, $limit = null) {
		$strings = explode($separator, $this->text, $limit);
		$texts = array();
		foreach ($strings as $text) {
			$texts[] = new Text($text, 'UTF-8');
		}
		return $texts;	
	}
	
	function ucfirst() {
		return new Text($this[0]->toUpper().$this->substring(1), 'UTF-8');
	}
	function capitalize() {
		return new Text($this[0]->toUpper().$this->substring(1)->toLower(), 'UTF-8');
	}

	// Info 
	
	/**
	 * A.k.a strpos
	 * @param type $search
	 * @param type $offset 
	 */
	function indexOf($text, $offset = 0, $ignoreCase = false) {
		if ($ignoreCase) {
			return mb_stripos($this->text, $text, $offset, 'UTF-8');
		} else {
			return mb_strpos($this->text, $text, $offset, 'UTF-8');
		}
	}

	function startsWith($text) {
		$text = new Text($text);
		if ($this->substring(0, $text->length) == $text) {
			return true;
		}
		return false;
	}
	
	function endsWith($text) {
		$text = new Text($text);
		if ($this->substring(0 - $text->length) == $text) {
			return true;
		}
		return false;
	}
	
	function equals($text, $ignoreCase = false) {
		$text = new Text($text);
		if ($ignoreCase) {
			return ($text->toLower()->text === $text->toLower()->text);
		} else {
			return ($text->text === $text->text);
		}
	}

		//
	
	// ArrayAccess
	public function offsetExists($offset) {
		return ($offset < mb_strlen($this->text, 'UTF-8'));
	}
	public function offsetGet($offset) {
		return $this->substring($offset, 1);
	}
	public function offsetSet($offset, $value) {
		throw new \Exception('Not implemented');
	}
	public function offsetUnset($offset) {
		throw new \Exception('Not implemented');
	}	
}
?>
