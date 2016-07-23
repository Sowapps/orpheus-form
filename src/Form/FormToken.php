<?php
/**
 * FormToken
 */

namespace Orpheus\Form;

use Orpheus\Exception\UserException;
use Orpheus\Core\Route;
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
	
	/**
	 * The name
	 * 
	 * @var string
	 */
	protected $name;
	
	/**
	 * Max allowed token
	 * 
	 * @var int
	 */
	protected $maxToken;
	
	/**
	 * Max allowed usage of one token
	 * 
	 * @var int
	 */
	protected $maxUsage;
	
	/**
	 * Last token
	 * 
	 * @var string
	 */
	protected $lastToken;
	
	const SESSION_KEY			= 'FORM_TOKENS';
	const HTML_PREFIX			= 'token_';
	const ERROR_INVALIDTOKEN	= 'invalidFormToken';
	
	/**
	 * The default token length
	 * 
	 * @var integer
	 */
	public static $TOKEN_LENGTH	= 16;
	
	/**
	 * The default max token
	 * 
	 * @var integer
	 * 
	 * Can not be unlimited or refreshed pages will create a non limited amount of tokens
	 * We store the minimum amount of data to allow no control of expiration
	 */
	public static $DEFAULT_MAXTOKEN	= 10;

	/**
	 * Constructor
	 * 
	 * @param string $name
	 * @param int $maxToken
	 * @param int $maxUsage Number of max usage, default value is 1.
	 */
	public function __construct($name=NULL, $maxToken=null, $maxUsage=1) {
		$this->name		= $name ? $name : Route::getCurrentRouteName();
		$this->maxToken	= $maxToken ? $maxToken : static::$DEFAULT_MAXTOKEN;
		$this->maxUsage	= $maxUsage;
	}

	/**
	 * Generate a new token
	 * 
	 * @return string The token
	 */
	public function generateToken() {
		if( !isset($_SESSION[self::SESSION_KEY][$this->name]) ) {
			$_SESSION[self::SESSION_KEY][$this->name]	= array();
		}
		$TOKEN_SESSION = &$_SESSION[self::SESSION_KEY][$this->name];
		do {
			$token = generatePassword(static::$TOKEN_LENGTH);
		} while( isset($TOKEN_SESSION[$token]) );
		if( count($TOKEN_SESSION) >= $this->maxToken ) {
			array_shift($TOKEN_SESSION);
		}
		$TOKEN_SESSION[$token]	= 0;
		return $token;
	}
	
	/**
	 * Generate a new token and return HTML input tag
	 * 
	 * @param boolean $force
	 * @return string The HTML input tag
	 */
	public function generateTokenHTML($force=false) {
		if( $force ) {
			$token	= $this->generateToken();
		} else {
			if( !isset($this->lastToken) ) {
				$this->lastToken	= $this->generateToken();
			}
			$token	= $this->lastToken;
		}
		return '<input type="hidden" name="'.self::HTML_PREFIX.$this->name.'" value="'.$token.'" />';
	}
	
	/**
	 * Generate a new token and display HTML input tag
	 * 
	 * @param string $force
	 */
	public function _generateTokenHTML($force=false) {
		echo $this->generateTokenHTML($force);
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
	 * @param string $token
	 * @return boolean True if the token is valid 
	 */
	public function validate($token) {
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
	 * @param InputRequest $domain
	 * @param string $domain
	 * @throws UserException
	 */
	public function validateForm(InputRequest $request, $domain=null) {
		if( !$this->validateCurrent($request) ) {
			throw new UserException(self::ERROR_INVALIDTOKEN, $domain);
		}
	}
	
	/**
	 * Validate token in request
	 * 
	 * @param InputRequest $request
	 * @return boolean
	 */
	public function validateCurrent(InputRequest $request) {
		return $this->validate($request->getInputValue(self::HTML_PREFIX.$this->name));
// 		return $this->validate(POST(self::HTML_PREFIX.$this->name));
	}
}
