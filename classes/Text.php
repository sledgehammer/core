<?php
namespace SledgeHammer;
/**
 * Text, a string class for handeling (multibyte) strings with OOP syntax
 * Modelled after the C# String class.
 * @link http://msdn.microsoft.com/en-us/library/system.string.aspx
 *
 * @package Core
 *
 * @property-read int $length  The number of characters
 */
class Text extends Object implements \ArrayAccess {

	/**
	 * The string in UTF-8
	 * @var string
	 */
	private $text;

	/**
	 * Construct the Text object and convert $text to UTF-8
	 * @param string $text  The text
	 * @param string $charset  The charset of $text, null will auto-detect
	 */
	function __construct($text, $charset = null) {
		if ($text instanceof Text) {
			$this->text = $text;
			if ($charset !== null && $charset !== 'UTF-8') {
				notice('Invalid charset given, an Text object will alway be UTF-8 encoded');
			}
			return;
		}
		if ($charset === null) {
			$charset = mb_detect_encoding($text, array('ASCII', 'UTF-8', 'ISO-8859-15'), true);
			if ($charset === false) {
				notice('Unable to detect charset');
				$charset = 'UTF-8';
			}
		}
		$this->text = mb_convert_encoding($text, 'UTF-8', $charset);
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

	/**
	 * Returns a copy of this text converted to uppercase.
	 *
	 * @return Text
	 */
	function toUpper() {
		return new Text(mb_strtoupper($this->text, 'UTF-8'), 'UTF-8');
	}

	/**
	 * Returns a copy of this text converted to lowercase.
	 *
	 * @return Text
	 */
	function toLower() {
		return new Text(mb_strtolower($this->text, 'UTF-8'), 'UTF-8');
	}

	/**
	 * Removes all leading and trailing white-space characters from the current text.
	 * @link http://php.net/manual/en/function.trim.php
	 *
	 * @param $charlist  (optional) The stripped characters can also be specified. list the characters that you want to be stripped.
	 * @return Text
	 */
	function trim($charlist = null) {
		return new Text(trim($this->text, $charlist), 'UTF-8');
	}

	/**
	 * Removes all leading white-space characters from the current text.
	 * @link http://php.net/manual/en/function.ltrim.php
	 *
	 * @param $charlist  (optional) The stripped characters can also be specified. list the characters that you want to be stripped.
	 * @return Text
	 */
	function trimStart($charlist = null) {
		return new Text(ltrim($this->text, $charlist), 'UTF-8');
	}

	/**
	 * Removes all trailing occurrences white-space characters from the current text.
	 * @link http://php.net/manual/en/function.rtrim.php
	 *
	 * @param $charlist  (optional) The stripped characters can also be specified. list the characters that you want to be stripped.
	 * @return Text
	 */
	function trimEnd($charlist = null) {
		return new Text(rtrim($this->text, $charlist), 'UTF-8');
	}

	/**
	 * Returns a truncated copy of this text.
	 * Only appends the given suffix when the text was trucated.
	 *
	 * @param int $maxLenght
	 * @param string $suffix [optional] Defaults to  the "..." character
	 * @return Text
	 */
	function truncate($maxLenght, $suffix = null) {
		if ($this->length < $maxLenght) {
			return new Text($this->text, 'UTF-8');
		}
		if ($suffix === null) {
			$suffix = html_entity_decode('&hellip;', ENT_NOQUOTES, 'UTF-8');
		} else {
			$suffix = new Text($suffix);
		}
		$pos = strrpos($this->substring(0, $maxLenght), ' ');
		return $this->substring(0, $pos).$suffix;
	}

	/**
	 * Returns a substring from this text.
	 * Similar to substr()
	 *
	 * @param int $offset
	 * @param int $length  (optional)
	 * @return Text
	 */
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

	/**
	 * Returns a copy of this text written backwards.
	 *
	 * @return Text
	 */
	function reverse() {
		$result = '';
		for ($i = $this->length - 1; $i >= 0; $i--) {
			$result .= mb_substr($this->text, $i, 1, 'UTF-8');
		}
		return new Text($result, 'UTF-8');
	}

	/**
	 * Returns a copy of this text in which all occurrences of $search  are replaced with $replace.
	 *
	 * @param string $search
	 * @param string $replace
	 * @return Text
	 */
	function replace($search, $replace) {
		return new Text(str_replace($search, $replace, $this->text), 'UTF-8');
	}

	/**
	 * Split a string by the $separator
	 * Similar to explode()
	 *
	 * @param string $separator
	 * @param int|null $limit  (optional)
	 * @return array
	 */
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
	 * Returns the index of the first occurrence of the specified $text
	 * Similar to strpos()
	 *
	 * @param string $search
	 * @param int $offset
	 * @return int|false
	 */
	function indexOf($text, $offset = 0, $ignoreCase = false) {
		if ($ignoreCase) {
			return mb_stripos($this->text, $text, $offset, 'UTF-8');
		} else {
			return mb_strpos($this->text, $text, $offset, 'UTF-8');
		}
	}

	/**
	 * Determines whether the beginning of this text matches the specified $text.
	 *
	 * @param string $text
	 * @return bool
	 */
	function startsWith($text) {
		$text = new Text($text);
		if ($this->substring(0, $text->length) == $text) {
			return true;
		}
		return false;
	}

	/**
	 * Determines whether the end of this text matches the specified $text.
	 *
	 * @param string $text
	 * @return bool
	 */
	function endsWith($text) {
		$text = new Text($text);
		if ($this->substring(0 - $text->length) == $text) {
			return true;
		}
		return false;
	}

	/**
	 * Check if this text has same value as $text.
	 *
	 * @param Text $text
	 * @param bool $ignoreCase
	 * @return bool
	 */
	function equals($text, $ignoreCase = false) {
		$text = new Text($text);
		if ($ignoreCase) {
			return ($text->toLower()->text === $text->toLower()->text);
		} else {
			return ($text->text === $text->text);
		}
	}

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
