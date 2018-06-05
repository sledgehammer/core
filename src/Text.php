<?php

namespace Sledgehammer\Core;

use ArrayAccess;
use Exception;

/**
 * Text, a string class for handeling (multibyte) strings with OOP syntax
 * Modelled after the C# String class.
 *
 * @link http://msdn.microsoft.com/en-us/library/system.string.aspx
 *
 * @property-read int $length  The number of characters
 */
class Text extends Base implements ArrayAccess
{
    /**
     * The string in UTF-8.
     *
     * @var string
     */
    private $text;

    /**
     * Construct the Text object and convert $text to UTF-8.
     *
     * @param string       $text    The text
     * @param string|array $charset string: The charset of $text; array: Autodetect encoding, example: array('ASCII', 'UTF-8', 'ISO-8859-15'); null: defaults to Framework::$charset
     */
    public function __construct($text, $charset = null)
    {
        if ($text instanceof self) {
            $this->text = $text->text;
            if ($charset !== null && $charset !== 'UTF-8') {
                \Sledgehammer\notice('Invalid charset given, an Text object will alway be UTF-8 encoded');
            }

            return;
        }
        if ($charset === null) {
            $charset = Framework::$charset;
        } elseif (is_array($charset)) {
            $charset = mb_detect_encoding($text, $charset, true);
            if ($charset === false) {
                \Sledgehammer\notice('Unable to detect charset');
                $this->text = mb_convert_encoding($text, 'UTF-8');

                return;
            }
        }
        $this->text = mb_convert_encoding($text, 'UTF-8', $charset);
    }

    /**
     * Allow Text objects to be used as php strings.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->text;
    }

    /**
     * Virtual properties like "length".
     *
     * @param string $property
     *
     * @return mixed
     */
    public function __get($property)
    {
        if ($property == 'length') {
            return mb_strlen($this->text, 'UTF-8');
        }
        $properties = \Sledgehammer\reflect_properties($this);
        $properties['public']['length'] = -1;
        \Sledgehammer\warning('Property: "'.$property.'" doesn\'t exist in a "'.get_class($this).'" object.', \Sledgehammer\build_properties_hint($properties));
    }

    // Mutations

    /**
     * Returns a copy of this text converted to uppercase.
     *
     * @return Text
     */
    public function toUpper()
    {
        return new self(mb_strtoupper($this->text, 'UTF-8'), 'UTF-8');
    }

    /**
     * Returns a copy of this text converted to lowercase.
     *
     * @return Text
     */
    public function toLower()
    {
        return new self(mb_strtolower($this->text, 'UTF-8'), 'UTF-8');
    }

    /**
     * Removes all leading and trailing white-space characters from the current text.
     *
     * @link http://php.net/manual/en/function.trim.php
     *
     * @param $charlist  (optional) The stripped characters can also be specified. list the characters that you want to be stripped.
     *
     * @return Text
     */
    public function trim($charlist = null)
    {
        return new self(trim($this->text, $charlist), 'UTF-8');
    }

    /**
     * Removes all leading white-space characters from the current text.
     *
     * @link http://php.net/manual/en/function.ltrim.php
     *
     * @param array $charlist (optional) The stripped characters can also be specified. list the characters that you want to be stripped.
     *
     * @return Text
     */
    public function trimStart($charlist = null)
    {
        if ($charlist === null) {
            return new self(ltrim($this->text), 'UTF-8');
        }

        return new self(ltrim($this->text, $charlist), 'UTF-8');
    }

    /**
     * Removes all trailing occurrences white-space characters from the current text.
     *
     * @link http://php.net/manual/en/function.rtrim.php
     *
     * @param array $charlist (optional) The stripped characters can also be specified. list the characters that you want to be stripped.
     *
     * @return Text
     */
    public function trimEnd($charlist = null)
    {
        if ($charlist === null) {
            return new self(rtrim($this->text), 'UTF-8');
        }

        return new self(rtrim($this->text, $charlist), 'UTF-8');
    }

    /**
     * Returns a truncated copy of this text.
     * Only appends the given suffix when the text was trucated.
     *
     * @param int    $maxLenght
     * @param string $suffix    [optional] Defaults to  the "..." character
     *
     * @return Text
     */
    public function truncate($maxLenght, $suffix = null)
    {
        if ($this->length < $maxLenght) {
            return new self($this->text, 'UTF-8');
        }
        if ($suffix === null) {
            $suffix = html_entity_decode('&hellip;', ENT_NOQUOTES, 'UTF-8');
        } else {
            $suffix = new self($suffix);
        }
        $pos = strrpos($this->substring(0, $maxLenght), ' ');

        return $this->substring(0, $pos).$suffix;
    }

    /**
     * Returns a substring from this text.
     * Similar to substr().
     *
     * @param int $offset
     * @param int $length (optional)
     *
     * @return Text
     */
    public function substring($offset, $length = null)
    {
        if ($length === null) {
            if (mb_internal_encoding() == 'UTF-8') {
                return new self(mb_substr($this->text, $offset), 'UTF-8');
            }
            if ($offset < 0) {
                $length = $offset * -1;
            } else {
                $length = $this->length - $offset;
            }
        }

        return new self(mb_substr($this->text, $offset, $length, 'UTF-8'), 'UTF-8');
    }

    /**
     * Returns a copy of this text written backwards.
     *
     * @return Text
     */
    public function reverse()
    {
        $result = '';
        for ($i = $this->length - 1; $i >= 0; --$i) {
            $result .= mb_substr($this->text, $i, 1, 'UTF-8');
        }

        return new self($result, 'UTF-8');
    }

    /**
     * Returns a copy of this text in which all occurrences of $search  are replaced with $replace.
     *
     * @param string $search
     * @param string $replace
     *
     * @return Text
     */
    public function replace($search, $replace)
    {
        return new self(str_replace($search, $replace, $this->text), 'UTF-8');
    }

    /**
     * Split a string by the $separator
     * Similar to explode().
     *
     * @param string   $separator
     * @param int|null $limit     (optional)
     *
     * @return array
     */
    public function split($separator, $limit = null)
    {
        $strings = explode($separator, $this->text, $limit);
        $texts = [];
        foreach ($strings as $text) {
            $texts[] = new self($text, 'UTF-8');
        }

        return $texts;
    }

    /**
     * Convert the first character to uppercase.
     *
     * @return \Sledgehammer\Text
     */
    public function ucfirst()
    {
        return new self($this[0]->toUpper().$this->substring(1), 'UTF-8');
    }

    /**
     * Convert the first character to uppercase and the rest to lowercase.
     *
     * @return \Sledgehammer\Text
     */
    public function capitalize()
    {
        return new self($this[0]->toUpper().$this->substring(1)->toLower(), 'UTF-8');
    }

    // Info

    /**
     * Returns the index of the first occurrence of the specified $text.
     *
     * @link http://nl.php.net/manual/en/function.strpos.php
     *
     * @param string $text       Needle/Search
     * @param int    $offset     Skip the first X characters
     * @param bool   $ignoreCase Use true for a case-insensitive search.
     *
     * @return int|false
     */
    public function indexOf($text, $offset = 0, $ignoreCase = false)
    {
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
     *
     * @return bool
     */
    public function startsWith($text)
    {
        $text = new self($text);
        if ($this->substring(0, $text->length) == $text) {
            return true;
        }

        return false;
    }

    /**
     * Determines whether the end of this text matches the specified $text.
     *
     * @param string $text
     *
     * @return bool
     */
    public function endsWith($text)
    {
        $text = new self($text);
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
     *
     * @return bool
     */
    public function equals($text, $ignoreCase = false)
    {
        $text = new self($text);
        if ($ignoreCase) {
            return $text->toLower()->text === $text->toLower()->text;
        } else {
            return $text->text === $text->text;
        }
    }

    /**
     * Whether a offset exists.
     *
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     *
     * @param int|string $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $offset < mb_strlen($this->text, 'UTF-8');
    }

    /**
     * Offset to retrieve.
     *
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     *
     * @param int|string $offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->substring($offset, 1);
    }

    /**
     * Offset to set.
     *
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     *
     * @param int|string $offset
     * @param mixed      $value
     */
    public function offsetSet($offset, $value)
    {
        throw new Exception('Not (yet) implemented');
    }

    /**
     * Offset to unset.
     *
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     *
     * @param int string $offset
     */
    public function offsetUnset($offset)
    {
        throw new Exception('Not (yet) implemented');
    }
}
