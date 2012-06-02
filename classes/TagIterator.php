<?php
/**
 * TagIterator
 */
namespace Sledgehammer;
/**
 * Een iterator die de tags uit htmlcode haalt.
 * Met name geschikt voor html met fouten, zoals bijvoorbeeld een "header.php" waarbij een aantal tags niet worden gesloten.
 * Met de uitvoer kun je de exacte (foute) html weer reconstrueren.
 *
 * Vaak is een oplossing mogelijk met DOMDocument of SimpleXML.
 * Gebruik in die gevallen niet deze iterator.
 *
 * @package Core
 */
class TagIterator extends Object implements \Iterator {

	/**
	 * Generated parser warnings. (The tokenizer doesn't report warnings, it just stores them in this array)
	 * @var array
	 */
	public $warnings;

	/**
	 * The tokenizer which proceesses the HTML.
	 * @var HTMLTokenizer
	 */
	private $tokenizer;

	/**
	 * Convert tagnames to lowercase.
	 * @var bool
	 */
	private $toLowercase;

	/**
	 * Iterator::key()
	 * @var int
	 */
	private $key;

	/**
	 * Iterator::current()
	 * @var array
	 */
	private $tag;

	/**
	 * Iterator::valid()
	 * @var bool
	 */
	private $valid;

	/**
	 * Constructor
	 * @param string $html
	 * @param bool $toLowercase  Bij true worden alle tags en attributen omgezet naar lowercase. '<ImG SrC="TeSt">' wordt array('<img', array('src' => 'TeSt'),'>', 'html' => <ImG SrC='TeSt'>)
	 */
	function __construct($html, $toLowercase = true) {
		$this->tokenizer = new HTMLTokenizer($html);
		$this->toLowercase = $toLowercase;
		$this->warnings = & $this->tokenizer->warnings;
	}

	/**
	 * Iterator::rewind()
	 * @return void
	 */
	function rewind() {
		$this->tokenizer->rewind();
		$this->valid = true;
		$this->next();
		$this->key = 0;
	}

	/**
	 * Iterator::valid()
	 * @return bool
	 */
	function valid() {
		return $this->valid;
	}

	/**
	 * Iterator::current()
	 * @return array
	 */
	function current() {
		return $this->tag;
	}

	/**
	 * Iterator::key()
	 * @return int
	 */
	function key() {
		return $this->key;
	}

	/**
	 * Iterator::next()
	 * @return void
	 */
	function next() {
		if ($this->tokenizer->valid() == false) { // Ende html bereikt?
			$this->valid = false;
		}
		$content = '';
		while ($this->tokenizer->valid()) {
			$token = $this->tokenizer->current();

			if ($token[0] == 'T_OPEN' || $token == '<!' || $token == '<!--') {
				if ($content === '') {
					if ($token[0] == 'T_OPEN') {
						$this->tag = $this->extractTag();
					} elseif ($token == '<!') {
						$this->tag = $this->extractEntity();
					} else {
						$this->tokenizer->next();
						$token = $this->tokenizer->current();
						$comment = $token;
						$this->tokenizer->next();
						$token = $this->tokenizer->current();
						$this->tokenizer->next();
						if ($token !== '-->') {
							throw new \Exception('Unterminated T_COMMENT');
						}
						$this->tag = array(
							0 => '<!--',
							1 => array($comment[1]),
							2 => $token,
							'html' => '<!--'.$comment[1].$token,
						);
					}
				} else {
					$this->tag = $content;
				}
				$this->key++;
				return;
			}
			$content .= is_array($token) ? $token[1] : $token;
			$this->tokenizer->next();
		}
		$this->tag = $content;
		$this->key++;
	}

	/**
	 * Extract an open or closing tag.
	 * @return array token
	 */
	private function extractTag() {
		$tag = array(
			0 => '', // tag '<a' of '</a'
			1 => array(), // parameters
			2 => '', // > of />
			'html' => '',
		);
		$token = $this->tokenizer->current();
		if ($token[0] !== 'T_OPEN') { // Sanity check
			throw new \Exception('Sanity check failed. Expected a "T_OPEN" token');
		}
		$state = 'NAME';
		$attribute = null;
		while ($this->tokenizer->valid()) {
			$token = $this->tokenizer->current();
			$this->tokenizer->next();
			$tag['html'] .= is_array($token) ? $token[1] : $token;
			if ($token[0] === 'T_CLOSE') { // Einde van de tag?
				$tag[2] = $token[1];
				return $tag;
			}
			if ($token[0] !== 'T_WHITESPACE') {
				switch ($state) {

					case 'NAME';
						if ($token[0] == 'T_OPEN') {
							$tag[0] = $token[1];
							break;
						}
						if (!in_array($token[0], array('T_TAG', 'T_CLOSE_TAG'))) {
							$this->warnings[] = 'TagIterator: Unexpected token: "'.(is_array($token) ? '['.$token[0].'] '.$token[1] : $token).'"';
							return $tag['html'];
						}
						$tag[0] .= ($this->toLowercase ? strtolower($token[1]) : $token[1]);
						$state = 'ATTRIBUTES';
						break;

					case 'ATTRIBUTES':
						if ($token[0] == 'T_ATTRIBUTE') {
							$attribute = ($this->toLowercase ? strtolower($token[1]) : $token[1]);
							$tag[1][$attribute] = null;
						}
						if ($token[0] == 'T_VALUE') {
							if ($attribute === null) {
								$this->warnings[] = 'TagIterator: T_VALUE without T_ATTRIBUTE';
							} else {
								$tag[1][$attribute] = $token[1];
								$attribute = null;
							}
						}
						break;
				}
			}
		}
	}

	/**
	 * Extract an entity like "<!DOCTYPE html>"
	 * @return array|string
	 */
	private function extractEntity() {
		$entity = array(
			0 => '', // entity '<!DOCTYPE'
			1 => array(), // parameters
			2 => '', // > of />
			'html' => '',
		);
		$token = $this->tokenizer->current();
		if ($token !== '<!') { // Sanity check
			throw new \Exception('Sanity check failed. Expected a "<!" token');
		}
		while ($this->tokenizer->valid()) {
			$token = $this->tokenizer->current();
			$this->tokenizer->next();
			$entity['html'] .= is_array($token) ? $token[1] : $token;

			if ($token[0] == 'T_DTD_ENTITY' || $token == '<!') {
				$entity[0] .= is_array($token) ? $token[1] : $token;
			} elseif ($token[0] == 'T_DTD_ATTRIBUTES') {
				$entity[1][] = $token[1];
			} elseif ($token == '>') {
				$entity[2] = $token;
				return $entity;
			} else {
				$this->warnings[] = 'TagIterator: Unexpected token in entity: "'.(is_array($token) ? '['.$token[0].'] '.$token[1] : $token).'"';
				return $entity['html'];
			}
		}
	}

}

?>