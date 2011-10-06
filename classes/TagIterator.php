<?php
/**
 * Een iterator die de tags uit htmlcode haalt.
 * Met name geschikt voor html met fouten.
 * Met de uitvoer kun je de exacte (foute) html weer reconstrueren.
 *
 * Vaak is een oplossing mogelijk met DOMDocument of SimpleXML.
 * Gebruik in die gevallen niet deze iterator.

 * @package Core
 */
namespace SledgeHammer;
class TagIterator extends Object implements \Iterator {

	public
		$warnings;

	private
		$tokenizer,
		$toLowerCase,
		$key,
		$tag,
		$valid;

	/**
	 *
	 * @param string $html
	 * @param bool $toLowerCase  Bij true worden alle tags en attributen omgezet naar lowercase. '<ImG SrC="TeSt">' wordt array('<img', array('src' => 'TeSt'),'>', 'html' => <ImG SrC='TeSt'>)
	 */
	function __construct($html, $toLowerCase = true) {
		$this->tokenizer = new HTMLTokenizer($html);
		$this->toLowerCase = $toLowerCase;
		$this->warnings = & $this->tokenizer->warnings;
	}

	/**
	 *
	 */
	function rewind() {
		$this->tokenizer->rewind();
		$this->valid = true;
		$this->next();
		$this->key = 0;
	}

	/**
	 *
	 *
	 * @return void
	 */
	function next() {
		if ($this->tokenizer->valid() == false) { // Ende html bereikt?
			$this->valid = false;
		}
		$content = '';
		while ($this->tokenizer->valid()) {
			$token = $this->tokenizer->current();

			if ($token[0] == 'T_OPEN' || $token == '<!') {
				if ($content === '') {
					if ($token == '<!') {
						$this->tag = $this->extractEntity();
					} else {
						$this->tag = $this->extractTag();
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
						$tag[0] .= ($this->toLowerCase ? strtolower($token[1]) : $token[1]);
						$state = 'ATTRIBUTES';
						break;

					case 'ATTRIBUTES':
						if ($token[0] == 'T_ATTRIBUTE') {
							$attribute = ($this->toLowerCase ? strtolower($token[1]) : $token[1]);
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

	function extractEntity() {
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

	/**
	 * @return bool
	 */
	function valid() {
		return $this->valid;
	}

	/**
	 * @return array
	 */
	function current() {
		return $this->tag;
	}

	/**
	 * @return int
	 */
	function key() {
		return $this->key;
	}
}
?>