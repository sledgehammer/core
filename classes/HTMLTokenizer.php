<?php
/**
 * HTMLTokenizer
 */
namespace Sledgehammer;
/**
 * Een tokenizer voor htmlcode.
 * Met name geschikt voor html met fouten.
 * Met de uitvoer kun je de exacte (foute) html weer reconstrueren.
 *
 * Vaak is een oplossing mogelijk met DOMDocument of SimpleXML.
 * Gebruik in die gevallen niet deze tokenizer.
 *
 * Gebruik deze tokenizer als je een tag/attribuut wilt vervangen en de overige (mogelijk foutieve) html intact wilt laten.
 * Daarnaast is het triviaal om een syntax highlighter te maken met deze tokenizer.
 *
 * Tokens
 * -------------------
 *
 * <T_TAG T_ATTRIBUTE=T_VALUE>T_TEXT</T_CLOSE_TAG>
 * ^     ^ T_WHITESPACE      ^
 * ^ T_OPEN                  ^ T_CLOSE
 *
 *
 * T_TAG        Het type element 'div', 'a', 'br', enz. Kan een <a> of <br /> zijn.
 * T_WHITESPACE Een spatie of newline binnen een tag.
 * T_ATTRIBUTE  De naam van het attribuut bv 'href'
 * T_VALUE      De waarde van een attribuut
 * T_TEXT      De tekst voor of na een T_TAG of T_CLOSE_TAG
 * T_CLOSE_TAG  Het type sluit tag bevat 'a' van een '</a>'
 * T_LT         Een '<' die niet voor opmaak gebruikt wordt
 * T_DELIMITER  De " of ' die om de T_VALUE staan
 * T_COMMENT    De inhoud van een comment blok
 * T_SCRIPT     De inhoud van de een <script> blok
 *
 * T_CDATA
 * T_DTD_ENTITY
 * T_INVALID    Een character dat niet klopt. Deze zal de parsen moeten/kunnen negeren.
 *
 * @todo T_DTD_ATTRIBUTES opslitsen in meerdere T_DTD_ATTRIBUTE tokens
 *
 * Niet alle tokens hebben een type. Deze tokens bestaan dan uit de data-string i.p.v. een array(token-type, data-string)
 *
 * @link http://en.wikipedia.org/wiki/HTML_element#Syntax
 * @package Core
 */
class HTMLTokenizer extends Object implements \Iterator {

	/**
	 * Generated parser warnings. (The tokenizer doesn't report warnings it just stores them in this array)
	 * @var array
	 */
	public $warnings = array();
	/**
	 * The html code this tokenizer is parsing.
	 * @var string
	 */
	private $html;
	private $position;

	/**
	 * CONTENT, TAG_BODY, VALUE
	 * @var string
	 */
	private $state;

	/**
	 * T_TAG of T_CLOSE_TAG
	 * @var string
	 */
	private $tagType;
	private $currentTag;

	/**
	 * @var int
	 */
	private $dtdLevel;

	/**
	 * @var array
	 */
	private $tokenQueue = array();
	/**
	 * Number of characters in the html string.
	 * @var int
	 */
	private $htmlLength;
	/**
	 * Iterator::valid()
	 * @var bool
	 */
	private $valid;

	/**
	 * Iterator::key()
	 * @var int
	 */
	private $iteratorKey;

	/**
	 * Iterator::current()
	 * @var array|string
	 */
	private $iteratorCurrent;

	/**
	 * Array containing possible whitespace characters.
	 * @var array
	 */
	private $wsArray;

	/**
	 * String containing whitespace characters (for use in regular expressions)
	 * @var string
	 */
	private $wsPattern;

	function __construct($html) {
		$this->html = $html;
		$this->htmlLength = strlen($html);
		$this->wsArray = array(' ', "\n", "\r", "\t");
		$this->wsPattern = implode($this->wsArray);
		$this->rewind();
	}

	function rewind() {
		if ($this->iteratorKey === 0) {
			return;
		}
		$this->iteratorKey = 0;
		$this->dtdLevel = 0;
		$this->state = 'CONTENT';

		$this->position = 0;
		$this->tokenQueue = array();
		$this->iteratorCurrent = null;
		$this->valid = ($this->htmlLength > 0);
		if ($this->valid) {
			$this->iteratorCurrent = $this->parseToken();
		}
	}

	/**
	 *
	 * @return array Token
	 */
	function parseToken() {
		try {
			$startPos = $this->position;
			switch ($this->state) {

				case 'CONTENT':
					$this->parseContent();
					break;

				case 'TAG':
					$this->parseTag();
					break;

				case 'TAG_BODY':
					$this->parseTagBody();
					break;

				case 'VALUE':
					$this->parseValue();
					break;

				case 'COMMENT':
					$this->parseHtmlComment();
					break;

				case 'DTD_ELEMENTS':
					$this->parseDTDElements();
					break;

				case 'SCRIPT':
					$this->parseScript();
					break;

				default:
					throw new \Exception('Unknown state: "'.$this->state.'"');
			}
		} catch (\Exception $exception) {
			if ($exception->getMessage() == 'HTML_TOKENIZER_EOF') {
				$this->warning('Unexpected end of stream, state: '.$this->state);
			} else {
				throw $exception;
			}
		}
		if ($startPos === $this->position) {
			throw new \Exception('No new tokens. State: '.$this->state.', position: '.$startPos);
		}
		return array_shift($this->tokenQueue);
	}

	/**
	 * Verzameld alle T_TEXT tokens
	 *
	 */
	function parseContent() {
		// @todo T_GT token '>' detectie .
		$ltPos = $this->strpos('<');
		if ($ltPos === false) { // Geen tags meer gevonden?
			$this->buildLastToken('T_TEXT');
			return;
		}
		$this->state = 'TAG';
		if ($ltPos !== $this->position) { // Begint de tag niet direct?
			$this->buildToken($ltPos - 1, 'T_TEXT');
			return;
		}
		$this->parseTag();
	}

	function parseTag() {
		if ($this->getChar() !== '<') {
			throw new \Exception('parseTag() saninty check failed');
		}
		$nextChar = $this->substr(1, 1);
		if (preg_match('/[>1-9'.$this->wsPattern.']/', $nextChar)) { // Is het geen begin van een tag?
			$this->warning('A "T_TEXT" block contains a "<" character');
			$this->buildToken($this->position, 'T_LT');
			$this->state = 'CONTENT';
			return;
		}
		if ($this->substr(0, 4) == '<!--') {
			$this->state = 'COMMENT';
			$this->buildToken($this->position + 3);
			return;
		}
		if ($nextChar == '?') {
			$this->parseParserTag();
			return;
		}
		if ($nextChar == '!') {
			$this->parseDTDTag();
			return;
		}
		if ($nextChar == '/') {
			$this->tagType = 'T_CLOSE_TAG';
			$this->buildToken($this->position + 1, 'T_OPEN');
		} else {
			$this->tagType = 'T_TAG';
			$this->buildToken($this->position, 'T_OPEN');
		}
		$pos = $this->firstOccurrance(array_merge(array('>'), $this->wsArray), $match);
		if ($pos === false) {
			$this->warning('Unclosed '.$this->tagType);
			$this->buildLastToken($this->tagType);
			return;
		}
		if ($pos == $this->position) { // Wordt er tag vermeld aan het begin van de tag?
			$this->warning('No tag defined');
		} else {
			$this->currentTag = ($this->tagType == 'T_TAG') ? $this->substr(0, $pos - $this->position) : null;
			$this->buildToken($pos - 1, $this->tagType); // Tag is bekend
		}
		$this->state = 'TAG_BODY'; // We gaan over tot het parsen van de attributen
	}

	/**
	 *
	 */
	function parseTagBody() {
		$this->collectWhitespace();
		// Detect ending
		$pos = $this->firstOccurrance(array('>', '/>'), $match);
		if ($pos === $this->position) { // Einde tagBody
			$this->buildToken($this->position + strlen($match) - 1, 'T_CLOSE');
			$this->state = 'CONTENT';
			if ($this->currentTag == 'script') {
				if ($match == '>') {
					$this->state = 'SCRIPT';
				} else {
					$this->warning('Self-closing "<script />" tags aren\'t supported by all browsers');
				}
			}
			return;
		}

		// Attribute naam
		$type = ($this->tagType == 'T_TAG') ? 'T_ATTRIBUTE' : 'T_INVALID';
		$char = $this->getChar();
		if (in_array($char, array('/', '!', '?'))) {
			$this->warning('Invalid character: "'.$char.'"');
			$this->buildToken($this->position, 'T_INVALID');
			return;
		}
		$pos = $this->firstOccurrance(array_merge($this->wsArray, array('=', '>')), $match);
		if ($pos === false) {
			$this->warning('Unclosed '.$this->tagType);
			$this->buildLastToken($type);
			return;
		}
		if ($this->tagType == 'T_CLOSE_TAG') {
			$this->warning('Attributes are not allowed in close tags');
		}
		$this->buildToken($pos - 1, $type);
		$this->collectWhitespace('T_INVALID');
		if ($this->getChar() !== '=') { // Heeft dit attribuut geen waarde?
			return;
		}
		$this->buildToken($this->position); // assignment operator
		$this->collectWhitespace();
		$this->state = 'VALUE';
	}

	function parseValue() {
		$invalid = ($this->tagType === 'T_CLOSE_TAG'); // T_CLOSE_TAG mogen geen attributen bevatten.
		$t_value = ($invalid ? 'T_INVALID' : 'T_VALUE');
		$t_delimiter = ($invalid ? 'T_INVALID' : 'T_DELIMITER');

		$delimiter = $this->getChar();
		if (in_array($delimiter, array('"', "'"))) { // Heeft de waarde delimiters?
			$this->buildToken($this->position, $t_delimiter);
			$pos = $this->strpos($delimiter);
			if ($pos === false) {
				$this->warning('Undelimited VALUE');
				$this->buildLastToken($t_value);
				return;
			}
			if ($pos !== $this->position) { // *Geen* Lege(alt="") waarde?
				$this->buildToken($pos - 1, $t_value);
			}
			$this->buildToken($this->position, $t_delimiter);
			$this->state = 'TAG_BODY';
			return;
		}
		$pos = $this->firstOccurrance(array_merge($this->wsArray, array('>')));
		if ($pos === false) {
			$this->warning('Undelimited VALUE');
			$this->buildLastToken($t_value);
			return;
		}
		$this->buildToken($pos - 1, $t_value);
		$this->state = 'TAG_BODY';
	}

	function parseHtmlComment() {
		$pos = $this->strpos('-->');
		if ($pos === false) {
			$this->warning('Unterminated T_COMMENT. Use "-->" ');
			$this->buildLastToken('T_COMMENT');
			return;
		}
		if ($this->position === $pos) {
			$this->warning('Empty T_COMMENT block');
		} else {
			$this->buildToken($pos - 1, 'T_COMMENT');
		}
		$this->buildToken($this->position + 2);
		$this->state = 'CONTENT';
	}

	/**
	 * <?xml ?> en <?php ?> tags
	 */
	function parseParserTag() {
		$endPos = $this->firstOccurrance(array('?>', '>'), $endToken);
		if ($endPos === false) {
			$this->warning('parserTag not terminated (use "?>")');
			$this->buildLastToken(null);
			return;
		}
		$this->buildToken($endPos + strlen($endToken) - 1, 'T_PARSER_TAG');
		$this->state = 'CONTENT';
	}

	/**
	 * CDATA & Inline DTD tags
	 *
	 * @link http://nl.wikipedia.org/wiki/Document_Type_Definition#Voorbeeld_2
	 */
	function parseDTDTag() {
		if ($this->substr(0, 2) !== '<!') {
			throw new \Exception('parseDTDTag() saninty check failed');
		}
		// @link http://en.wikipedia.org/wiki/CDATA#CDATA_sections_in_XML
		if ($this->substr(0, 9) == '<![CDATA[') {
			$this->buildToken($this->position + 8);
			$pos = $this->strpos(']]>');
			if ($pos === false) {
				$this->warning('CDATA not terminated');
				$this->buildLastToken('T_CDATA');
				return;
			}
			if ($this->position == $pos) {
				warning('Empty CDATA'); // Required?
			} else {
				$this->buildToken($pos - 1, 'T_CDATA');
			}
			$this->buildToken($this->position + 2);
			$this->state = 'CONTENT';
			return;
		}
		$this->buildToken($this->position + 1);
		$pos = $this->firstOccurrance(array_merge(array('[', '>'), $this->wsArray));
		if ($pos === false) {
			$this->warning('Inline DTD tag not terminated');
			$this->buildLastToken('T_DTD_ENTITY');
			return;
		}
		if ($pos == $this->position) {
			$this->warning(' No DTD Entity defined');
		} else {
			$this->buildToken($pos - 1, 'T_DTD_ENTITY');
		}
		$this->parseDTDAttributes('OPEN');
	}

	function parseDTDAttributes($state) {
		$pos = $this->firstOccurrance(array('[', '>'), $match);
		if ($pos === false) {
			$this->warning('Inline DTD tag not terminated');
			$this->buildLastToken('T_DTD_ATTRIBUTES');
			return;
		}
		if ($pos === $this->position) {
			if ($state == 'OPEN') {
				// $this->warning(' No DTD attributes'); // Niet verplicht?
			}
		} else {
			$this->buildToken($pos - 1, 'T_DTD_ATTRIBUTES');
		}
		$this->buildToken($this->position);
		if ($match == '[') { // DTD met elementen
			$this->state = 'DTD_ELEMENTS';
			$this->dtdLevel++;
		} else { // Einde DTD tag
			if ($this->dtdLevel == 0) {
				$this->state = 'CONTENT';
			}
		}
	}

	function parseDTDElements() {
		$this->collectWhitespace();
		$pos = $this->firstOccurrance(array('<!', ']', '>'), $match);
		if ($pos === false) {
			$this->warning('Invalid DTD elements');
			$this->buildLastToken('T_DTD_ATTRIBUTES');
			return;
		}
		if ($match == '>') {
			$this->warning('Invalid DTD elements');
			$this->dtdLevel = 0;
			if ($this->position != $pos) {
				$this->buildToken($pos - 1, 'T_INVALID');
			}
			$this->buildToken($this->position);
			$this->state = 'CONTENT'; // @todo verify of het dan weer om content gaat
			return;
		}
		if ($match == ']') { // Einde collectie?
			$this->dtdLevel--;
			$this->buildToken($this->position);
			$this->parseDTDAttributes('CLOSE');
			return;
		}
		if ($this->position != $pos) {
			$this->buildToken($pos - 1, 'T_INVALID');
		}
		$this->parseDTDTag();
	}

	function parseScript() {
		$pos = $this->stripos('</script');
		if ($pos === false) {
			$this->warning('Unterminated <script> tag');
			$this->buildLastToken('T_SCRIPT');
			return;
		}
		$this->state = 'TAG';
		if ($this->position == $pos) { // No script body?
			$this->parseTag();
			return;
		}
		$this->buildToken($pos - 1, 'T_SCRIPT');
	}

	function collectWhitespace($tokenType = 'T_WHITESPACE') {
		$whitespace = false;
		for ($i = $this->position; $i < $this->htmlLength; $i++) {
			if (in_array($this->html{$i}, $this->wsArray)) {
				$whitespace = true;
			} else {
				break;
			}
		}
		if ($whitespace) {
			$this->buildToken($i - 1, $tokenType);
			return;
		}
	}

	/**
	 * Een token instellen o.b.v. van de eind positie van de token.
	 * Zal de $this->position bijwerken
	 */
	function buildToken($endPosition, $tokenType = null) {
		$length = $endPosition + 1 - $this->position;
		if ($length <= 0) {
			if ($this->position >= $this->htmlLength) {
				throw new \Exception('EOF reached, parsing '.$tokenType.' token failed');
			}
			throw new \Exception('Invalid lenght for token: '.$tokenType.', start: '.$this->position.' state: '.$this->state);
		}
		$value = substr($this->html, $this->position, $length);
		$this->position = $endPosition + 1;
		if ($tokenType === null) {
			$this->tokenQueue[] = $value;
		} else {
			$this->tokenQueue[] = array($tokenType, $value);
		}
	}

	function buildLastToken($type) {
		if ($this->htmlLength !== $this->position) {
			$this->buildToken($this->htmlLength - 1, $type);
		}
	}

	/**
	 * Het karakter op huidige positie
	 * @return char
	 */
	function getChar() {
		if ($this->position == $this->htmlLength) {
			throw new \Exception('HTML_TOKENIZER_EOF');
		}
		return $this->html{$this->position};
	}

	/**
	 * Een substr op de $html
	 * relatief vanaf de huidige $position
	 */
	function substr($start, $length = null) {
		if ($start < 0) {
			throw new \Exception('substr() doesnt accept a negative $start');
		}
		if ($length === null) {
			return substr($this->html, $this->position + $start);
		}
		return substr($this->html, $this->position + $start, $length);
	}

	/**
	 * Een strpos op de $html
	 * relatief vanaf de huidige $position
	 */
	function strpos($needle) {
		return strpos($this->html, $needle, $this->position);
	}

	/**
	 * Een strpos op de $html
	 * relatief vanaf de huidige $position
	 */
	function stripos($needle) {
		return stripos($this->html, $needle, $this->position);
	}

	/**
	 * @return int de positie van de needle die het eerste voorkomt in de string
	 */
	function firstOccurrance($needles, &$match = null) {
		$pos = false;
		foreach ($needles as $needle) {
			$needlePos = $this->strpos($needle);
			if ($needlePos === false) {
				continue;
			}
			if ($pos === false) {
				$pos = $needlePos;
				$match = $needle;
			} elseif ($pos > $needlePos) {
				$pos = $needlePos;
				$match = $needle;
			}
		}
		return $pos;
	}

	function warning($message) {
		$this->warnings[] = $message.' on position '.$this->position.' parsing '.$this->state;
	}

	function key() {
		return $this->iteratorKey;
	}

	function current() {
		return $this->iteratorCurrent;
	}

	function valid() {
		return $this->valid;
	}

	function next() {
		$this->iteratorKey++;
		if (count($this->tokenQueue)) { // Zijn er nog tokens ge queue d?
			$this->iteratorCurrent = array_shift($this->tokenQueue);
			return;
		}
		if ($this->position == $this->htmlLength) {
			$this->iteratorKey--;
			$this->valid = false;
			return;
		}
		$this->iteratorCurrent = $this->parseToken();
	}

	/**
	 * Geef een syntax highlighted versie van de html. (Het title attribute wordt ingesteld met het tag type)
	 *
	 * @param $charset
	 * @return void
	 */
	function dump($charset = 'UTF-8') {
		$errorColor = 'white:background:red';
		$colors = array(
			'T_TAG' => 'purple',
			'T_CLOSE_TAG' => 'purple',
			'T_OPEN' => 'green',
			'T_CLOSE' => 'green',
			'T_ATTRIBUTE' => 'brown',
			'T_VALUE' => 'darkblue',
			'T_COMMENT' => 'gray',
			'T_DTD_ENTITY' => 'orange',
			'T_DTD_ATTRIBUTES' => 'brown',
			'T_TEXT' => 'black',
			'T_CDATA' => 'black',
			'T_WHITESPACE' => 'red',
			'T_INVALID' => $errorColor,
			'T_LT' => $errorColor,
			'T_GT' => $errorColor,
			'T_PARSER_TAG' => 'Aquamarine',
			'T_DELIMITER' => 'orange',
		);
		echo '<pre style="background:white;overflow:auto;padding:10px;color:red">';
		foreach ($this as $index => $token) {
			if (is_array($token)) {
				echo '<span title="', $token[0], '" style="color:', $colors[$token[0]], '">', htmlentities($token[1], ENT_COMPAT, $charset), '</span>';
			} else {
				echo '<span style="color:green">', htmlentities($token, ENT_COMPAT, $charset), '</span>';
			}
		}
		echo "</pre>";
	}

}

?>