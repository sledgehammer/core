<?php
/**
 * HttpAuthentication
 */
namespace Sledgehammer;
/**
 * Basic HTTP autentication
 *
 * Usage:
 *   $auth = new HttpAuthentication('Realm');
 *   $credentials = $auth->authenticate();
 *
 * Add a validation callback to resend authentication headers when invalid credentials are given.
 *   $auth = new HttpAuthentication('Realm', array('MyClass','login'));
 *   $credentials = $auth->authenticate();
 *
 * @package Core
 */
class HttpAuthentication extends Object {

	/**
	 * WWW-Authenticate: realm=
	 * @var string
	 */
	private $realm;

	/**
	 * Callback for validating the given credentials
	 * @var callback
	 */
	private $validation;

	/**
	 * Constructor
	 *
	 * @param string $realm
	 * @param Closure|array $validation Callback for validating the given credentials
	 */
	function __construct($realm = null, $validation = null) {
		$this->realm = $realm;
		if ($validation === null) {
			$validation = function () {
						return true;
					};
		}
		$this->validation = $validation;
	}

	/**
	 * Return credentials or false and send login headers.
	 * @return array|false
	 */
	function authenticate() {
		if (isset($_SERVER['PHP_AUTH_USER'])) { // Is er een gebruikersnaam bekend?
			if (call_user_func($this->validation, $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
				return array('username' => $_SERVER['PHP_AUTH_USER'], 'password' => $_SERVER['PHP_AUTH_PW']);
			}
		}
		if (headers_sent()) {
			warning('Unable show login dialog, HTTP headers already sent');
			return false;
		}
		$this->logout();
		return false;
	}

	/**
	 * Send HTTP login headers.
	 */
	function logout() {
		header($_SERVER['SERVER_PROTOCOL'].' 401 Unauthorized');
		if ($this->realm === null) {
			header('WWW-Authenticate: Basic');
		} else {
			header('WWW-Authenticate: Basic realm="'.addslashes($this->realm).'"');
		}
	}

}

?>
