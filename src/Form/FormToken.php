<?php
/**
 * FormToken
 */

namespace Orpheus\Form;

use Orpheus\Exception\UserException;
use Orpheus\InputController\HttpController\HttpRoute;
use Orpheus\InputController\InputRequest;

/**
 * The Form Token class
 *
 * This class is limit the use of form data to only one shot.
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class FormToken {
	
	const SESSION_KEY = 'FORM_TOKENS';
	
	const HTML_PREFIX = 'token_';
	
	const ERROR_INVALID_TOKEN = 'invalidFormToken';
	
	/**
	 * The default token length
	 *
	 * @var integer
	 */
	public static int $TOKEN_LENGTH = 16;
	
	/**
	 * The default max token
	 *
	 * @var integer
	 *
	 * Can not be unlimited or refreshed pages will create a non-limited amount of tokens
	 * We store the minimum amount of data to allow no control of expiration
	 */
	public static int $DEFAULT_TOKEN_LIMIT = 10;
	
	/**
	 * The name
	 *
	 * @var string
	 */
	protected string $name;
	
	/**
	 * Max allowed token
	 *
	 * @var int
	 */
	protected int $tokenLimit;
	
	/**
	 * Max allowed usage of one token
	 *
	 * @var int
	 */
	protected int $maxUsage;
	
	/**
	 * Last token
	 *
	 * @var string
	 */
	protected string $lastToken;
	
	/**
	 * Constructor
	 *
	 * @param int $maxUsage Number of max usage, default value is 1.
	 */
	public function __construct(?string $name = null, ?int $maxToken = null, int $maxUsage = 1) {
		$this->name = $name ?: HttpRoute::getCurrentRouteName();
		$this->tokenLimit = $maxToken ?: static::$DEFAULT_TOKEN_LIMIT;
		$this->maxUsage = $maxUsage;
	}
	
	/**
	 * Generate a new token
	 *
	 * @return string The token
	 */
	public function generateToken(): string {
		if( !isset($_SESSION[self::SESSION_KEY][$this->name]) ) {
			$_SESSION[self::SESSION_KEY][$this->name] = [];
		}
		$TOKEN_SESSION = &$_SESSION[self::SESSION_KEY][$this->name];
		do {
			$token = generateRandomString(static::$TOKEN_LENGTH);
		} while( isset($TOKEN_SESSION[$token]) );
		if( count($TOKEN_SESSION) >= $this->tokenLimit ) {
			array_shift($TOKEN_SESSION);
		}
		$TOKEN_SESSION[$token] = 0;
		
		return $token;
	}
	
	/**
	 * Generate a new token and return HTML input tag
	 *
	 * @return string The HTML input tag
	 */
	public function generateTokenHTML(bool $force = false): string {
		if( $force ) {
			$token = $this->generateToken();
		} else {
			if( !isset($this->lastToken) ) {
				$this->lastToken = $this->generateToken();
			}
			$token = $this->lastToken;
		}
		
		return '<input type="hidden" name="' . self::HTML_PREFIX . $this->name . '" value="' . $token . '" />';
	}
	
	/**
	 * Get HTML input tag as string
	 *
	 * @return string
	 */
	public function __toString() {
		return $this->generateTokenHTML();
	}
	
	/**
	 * Validate the given token
	 *
	 * @return boolean True if the token is valid
	 */
	public function validate(string $token): bool {
		if( !isset($_SESSION[self::SESSION_KEY][$this->name]) ) {
			return false;
		}
		$TOKEN_SESSION = &$_SESSION[self::SESSION_KEY][$this->name];
		if( empty($token) || empty($TOKEN_SESSION) || !isset($TOKEN_SESSION[$token]) ) {
			return false;
		}
		$TOKEN_SESSION[$token]++;
		if( $TOKEN_SESSION[$token] >= $this->maxUsage ) {
			unset($TOKEN_SESSION[$token]);
		}
		
		return true;
	}
	
	/**
	 * Validate the given token from form or throw an UserException
	 *
	 * @throws UserException
	 */
	public function validateForm(InputRequest $request, ?string $domain = null): void {
		if( !$this->validateCurrent($request) ) {
			throw new UserException(self::ERROR_INVALID_TOKEN, $domain);
		}
	}
	
	/**
	 * Validate token in request
	 */
	public function validateCurrent(InputRequest $request): bool {
		return $this->validate($request->getInputValue(self::HTML_PREFIX . $this->name));
	}
	
}
