<?php

/**
 *
 * Licence: MIT License (MIT)
 * Copyright (c) 2019 Joseph Block
 *
 * This class is used to communicate and authenticate against rocks
 * @todo implement URL_REGISTRATION_ID and smurfalert
 */
class rocksOauth {
	//SCRIPT VERSION
	const VERSION = 1.0;

	//Data retrieval
	const URL_PROFILE = "api/1.0/profile";
	const URL_EVENTS = "/api/1.0/events";
	const URL_REGISTRATION_ID = "/api/1.0/registration_id/{ID}";
	const URL_RSVP = "/api/1.0/rsvp"; //use with event oAuth
	const URL_ANOMALY_RSVP = "/api/1.0/rsvp/{ANOMALY_ID}";
	const URL_SETTINGS = "/api/1.0/settings";
	const URL_SMURFALERT = "/api/1.0/smurfalert";
	const URL_SMURFALERT_GID = "/api/1.0/smurfalert/{GID}";
	const URL_TELEGRAM = "/api/1.0/telegram";
	const URL_USERINFO = "/api/1.0/userinfo";

	//Endpoints
	const ENDPOINT_AUTH = "oauth/authorize";
	const ENDPOINT_TOKEN = "oauth/token";

	//Scopes
	const SCOPE_GENERAL = "general";
	const SCOPE_PROFILE = "profile";
	const SCOPE_PROFILE_WRITE = "profile-write";
	const SCOPE_EVENT = "event";
	const SCOPE_TELEGRAM = "telegram";
	const SCOPE_USERINFO = "userinfo";

	//Testing Scope NOTE: DO NOT USE ON PRODUCTION
	const TESTING_SCOPE = array(
		rocksOauth::SCOPE_GENERAL,
		rocksOauth::SCOPE_PROFILE,
		rocksOauth::SCOPE_TELEGRAM,
		rocksOauth::SCOPE_USERINFO
	);

	//variables
	public $client;
	public $secret;
	public $redirect;
	public $ch;
	public $root = "https://enlightened.rocks/";
	public $token;
	public $refreshToken;
	public $code;
	public $expiresIn;
	protected $scopes = array();

	/**
	 * rocksOauth constructor.
	 */
	public function __construct() {

		$this->ch = curl_init();
		curl_setopt( $this->ch, CURLOPT_USERAGENT, 'rocksOauth Client v' . rocksOauth::VERSION );
		curl_setopt( $this->ch, CURLOPT_POST, true );
		curl_setopt( $this->ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $this->ch, CURLOPT_HEADER, false );
		curl_setopt( $this->ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $this->ch, CURLOPT_CONNECTTIMEOUT, 30 );
		curl_setopt( $this->ch, CURLOPT_TIMEOUT, 600 );

	}

	public function getExpiresIn() {
		return $this->expiresIn;
	}

	/**
	 * @param $state
	 *
	 * @return string
	 * @throws Exception
	 */
	public function getAuthURL( $state ) {
		if( ! $this->redirect ) {
			throw new Exception( 'You must provide a redirect URL' );
		}
		if( ! $this->scopes ) {
			throw new Exception( 'You must provide a OAuth scope' );
		}
		$scope = implode( $this->scopes, "%20" );


		$url = $this->root . rocksOauth::ENDPOINT_AUTH . "?client_id=" . $this->client . "&redirect_uri=" . $this->redirect . "&response_type=code&scope=" . $scope . "&state=" . md5( $state );

		return $url;
	}

	/**
	 * sets the redirect
	 *
	 * @param $redirect
	 */
	public function setRedirect( $redirect ) {
		$this->redirect = $redirect;
	}

	/**
	 * destroys the curl session
	 */
	public function __destruct() {
		curl_close( $this->ch );
	}

	/**
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function getToken() {
		if( ! $this->code ) {
			throw new Exception( "Required: use setCode before calling this function" );
		}

		$fields = array(
			'grant_type'    => 'authorization_code',
			'code'          => $this->code,
			'client_id'     => $this->client,
			'redirect_uri'  => $this->redirect
		);
		try {
			$result          = $this->callPost( rocksOauth::ENDPOINT_TOKEN, $fields,"",$this->client.":".$this->secret );
			$this->expiresIn = $result->{'expires_in'};
			$this->setToken( $result->{'access_token'} );
			$this->setRefreshToken( $result->{'refresh_token'} );

			return $this->token;
		} catch ( Exception $e ) {
			throw $e;
		}
	}

	/**
	 * @param mixed $token
	 */
	public function setToken( $token ) {
		$this->token = $token;
	}

	/**
	 * @param             $url
	 * @param string      $fields_string
	 *
	 * @param string      $contentType
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function callPostJson( $url, $fields_string = "", $contentType = "application/json" ) {
		if( ! $this->token ) {
			throw new Exception( "No token" );
		}
		$ch = $this->ch;

		curl_setopt( $ch, CURLOPT_URL, $this->root . $url );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: ' . $contentType,
			"Authorization: Bearer " . $this->token
		) );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $fields_string );
		$response_body = curl_exec( $ch );
		if( curl_error( $ch ) ) {
			throw new Exception( "API call to $url failed: " . curl_error( $ch ) );
		}
		$result = json_decode( $response_body );
		if( isset( $result->{'error'} ) ) {
			throw new Exception( "Error: " . $response_body );
		}
		if( $result === null ) {
			throw new Exception( 'We were unable to decode the JSON response from the API: ' . $response_body );
		}

		return $result;
	}

	/**
	 * @param             $url
	 * @param array       $parms
	 * @param string      $fields_string
	 *
	 * @param null        $basicAuth
	 * @param string      $contentType
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function callPost( $url, $parms, $fields_string = "",$basicAuth = null, $contentType = "application/x-www-form-urlencoded" ) {
		if( ! $this->token && !( $url == rocksOauth::ENDPOINT_AUTH || $url == rocksOauth::ENDPOINT_TOKEN ) ) {
			throw new Exception( "No token" );
		}
		$ch = $this->ch;
		foreach( $parms as $key => $value ) {
			$fields_string .= $key . '=' . $value . '&';
		}
		$headers = array('Content-Type: ' . $contentType);
		if(!($url == rocksOauth::ENDPOINT_AUTH || $url == rocksOauth::ENDPOINT_TOKEN)){
			$headers[]="Authorization: Bearer " . $this->token;
		}
		$fields_string = rtrim( $fields_string, '&' );
		curl_setopt( $ch, CURLOPT_URL, $this->root . $url );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_POST, count( $parms ) );
		if($basicAuth !=null){
			curl_setopt($ch, CURLOPT_USERPWD, $basicAuth);
		}
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $fields_string );
		$response_body = curl_exec( $ch );
		if( curl_error( $ch ) ) {
			throw new Exception( "API call to $url failed: " . curl_error( $ch ) );
		}
		var_dump($ch);
		echo $response_body;
		$result = json_decode( $response_body );
		if( isset( $result->{'error'} ) ) {
			throw new Exception( "Error: " . $result->{'error'} . " Message: " . $result->{'error_description'} );
		}
		if( $result === null ) {
			throw new Exception( 'We were unable to decode the JSON response from the API: ' . $response_body );
		}

		return $result;
	}

	/**
	 * Returns a Rocks_user object
	 *
	 * @param array $field
	 *
	 * @return ROCKS_USER
	 * @throws Exception
	 */
	public function getRocksUser() {
		if( ! $this->token ) {
			throw new Exception( "No token" );
		}
		try {
			$result = $this->callGet( rocksOauth::URL_USERINFO );
			if( $result->{'status'} === "unauthorized" ) {
				throw new Exception( $result->{'message'} );
			} else {
				return new ROCKS_USER( $result );
			}
		} catch ( Exception $e ) {
			throw $e;
		}
	}

	/**
	 * Telegram object
	 *
	 * @return TELEGRAM
	 * @throws Exception
	 */
	public function getTelegram() {
		if( ! $this->token ) {
			throw new Exception( "No token" );
		}
		try {
			$result = $this->callGet( rocksOauth::URL_TELEGRAM );
			if( $result->{'status'} === "unauthorized" ) {
				throw new Exception( $result->{'message'} );
			} else {
				return new TELEGRAM( $result );
			}
		} catch ( Exception $e ) {
			throw $e;
		}
	}

	/**
	 * Gets events available to user
	 *
	 * @return mixed
	 * @throws Exception
	 * @todo convert to object
	 *
	 */
	public function getEvents() {
		if( ! $this->token ) {
			throw new Exception( "No token" );
		}
		try {
			$result = $this->callGet( rocksOauth::URL_EVENTS );
			if( $result->{'status'} === "unauthorized" ) {
				throw new Exception( $result->{'message'} );
			} else {
				return $result;
			}
		} catch ( Exception $e ) {
			throw $e;
		}
	}

	/**
	 * Returns a Rocks_user object
	 *
	 * @return ROCKS_PROFILE
	 * @throws Exception
	 */
	public function getRocksProfile() {
		if( ! $this->token ) {
			throw new Exception( "No token" );
		}
		try {
			$result = $this->callGet( rocksOauth::URL_PROFILE );
			if( $result->{'status'} === "unauthorized" ) {
				throw new Exception( $result->{'message'} );
			} else {
				return new ROCKS_PROFILE( $result );
			}
		} catch ( Exception $e ) {
			throw $e;
		}
	}

	/**
	 * @param ROCKS_PROFILE $profile
	 *
	 * @return ROCKS_PROFILE
	 * @throws Exception
	 */
	public function setRocksProfile( $profile ) {
		if( ! $this->token ) {
			throw new Exception( "No token" );
		}

		try {
			$result = $this->callPostJson( rocksOauth::URL_PROFILE, json_encode( $profile ) );
			if( $result->{'status'} === "unauthorized" ) {
				throw new Exception( $result->{'message'} );
			} else {
				return new ROCKS_PROFILE( $result );
			}
		} catch ( Exception $e ) {
			throw $e;
		}
	}

	public function getRSVP() {
		if( ! $this->token ) {
			throw new Exception( "No token" );
		}

		try {
			$result = $this->callGet( rocksOauth::URL_RSVP );
			if( $result->{'status'} === "unauthorized" ) {
				throw new Exception( $result->{'message'} );
			} else {
				return $result;
			}
		} catch ( Exception $e ) {
			throw $e;
		}
	}

	public function getRSVP_event( $event ) {
		if( ! $this->token ) {
			throw new Exception( "No token" );
		}
		$url = str_replace( "{ANOMALY_ID}", $event, rocksOauth::URL_ANOMALY_RSVP );
		try {
			$result = $this->callGet( $url );
			if( $result->{'status'} === "unauthorized" ) {
				throw new Exception( $result->{'message'} );
			} else {
				return $result;
			}
		} catch ( Exception $e ) {
			throw $e;
		}
	}

	public function getSettings() {
		if( ! $this->token ) {
			throw new Exception( "No token" );
		}
		try {
			$result = $this->callGet( rocksOauth::URL_SETTINGS );
			if( $result->{'status'} === "unauthorized" ) {
				throw new Exception( $result->{'message'} );
			} else {
				return $result;
			}
		} catch ( Exception $e ) {
			throw $e;
		}
	}

	/**
	 * @param        $url
	 * @param string $contentType
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function callGet( $url, $contentType = "application/x-www-form-urlencoded" ) {
		if( ! $this->token ) {
			throw new Exception( "No token" );
		}
		$ch = $this->ch;
		curl_setopt( $ch, CURLOPT_URL, $this->root . $url );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-Type: ' . $contentType, "Authorization: Bearer " . $this->token ) );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "GET" );
		$response_body = curl_exec( $ch );
		if( curl_error( $ch ) ) {
			throw new Exception( "API call to $url failed: " . curl_error( $ch ) );
		}
		$result = json_decode( $response_body );
		if( isset( $result->{'error'} ) ) {
			throw new Exception( "Error: " . $result->{'error'} . " Message: " . $result->{'error_description'} );
		}
		if( $result === null ) {
			throw new Exception( 'We were unable to decode the JSON response from the API: ' . $response_body );
		}

		return $result;
	}

	/**
	 * @param       $refreshToken
	 *
	 * @param       $state
	 *
	 * @param array $field
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public
	function getNewToken(
		$refreshToken, $state, $field = array()
	) {
		$fields = array(
			'grant_type'    => 'refresh_token',
			'refresh_token' => $refreshToken,
			'client_id'     => $this->client,
			'client_secret' => $this->secret,
			"state"         => md5( $state )
		);
		$fields = array_merge( $fields, $field );
		$result = $this->callPost( rocksOauth::ENDPOINT_TOKEN, $fields );
		$this->setToken( $result->{'access_token'} );
		if( isset( $result->{'refresh_token'} ) ) {
			$this->setRefreshToken( $result->{'refresh_token'} );
		}

		return $this->token;
	}

	/**
	 * @param $scopes
	 */
	public
	function addScope(
		$scopes
	) {
		if( is_string( $scopes ) && ! in_array( $scopes, $this->scopes ) ) {
			$this->scopes[] = $scopes;
		} elseif( is_array( $scopes ) ) {
			foreach( $scopes as $scope ) {
				$this->addScope( trim( $scope ) );
			}
		}
	}

	/**
	 * @return mixed
	 */
	public
	function getRefreshToken() {
		return $this->refreshToken;
	}

	/**
	 * @param mixed $refreshToken
	 */
	public
	function setRefreshToken(
		$refreshToken
	) {
		$this->refreshToken = $refreshToken;
	}

	/**
	 * @param mixed $code
	 */
	public
	function setCode(
		$code
	) {
		$this->code = $code;
	}

	/**
	 * @param mixed $client
	 */
	public
	function setClient(
		$client
	) {
		$this->client = $client;
	}

	/**
	 * @param mixed $secret
	 */
	public
	function setSecret(
		$secret
	) {
		$this->secret = $secret;
	}
}

class ROCKS_USER {
	public $agentid;
	public $email;
	public $id;
	public $link;
	public $name;
	public $picture;
	public $verified;

	public function __construct( $json ) {
		$this->agentid  = $json->{'agentid'};
		$this->email    = $json->{'email'};
		$this->id       = $json->{'id'};
		$this->link     = $json->{'link'};
		$this->name     = $json->{'name'};
		$this->picture  = $json->{'picture'};
		$this->verified = boolval( $json->{'verified'} );
	}
}

class ROCKS_PROFILE {
	public $agentid;
	public $level;
	public $location;
	public $country;
	public $locale;
	public $language;
	public $community;
	public $postalcode;

	public function __construct( $json ) {
		$this->agentid    = $json->{'agentid'};
		$this->level      = intval( $json->{'level'} );
		$this->location   = $json->{'location'};
		$this->country    = $json->{'country'};
		$this->locale     = $json->{'locale'};
		$this->language   = $json->{'language'};
		$this->community  = $json->{'community'};
		$this->postalcode = $json->{'postalcode'};
	}

	/**
	 * @param mixed $agentid
	 */
	public function setAgentid( $agentid ) {
		$this->agentid = $agentid;
	}

	/**
	 * @param int $level
	 */
	public function setLevel( $level ) {
		$this->level = intval( $level );
	}

	/**
	 * @param mixed $location
	 */
	public function setLocation( $location ) {
		$this->location = $location;
	}

	/**
	 * @param mixed $country
	 */
	public function setCountry( $country ) {
		$this->country = $country;
	}

	/**
	 * @param mixed $locale
	 */
	public function setLocale( $locale ) {
		$this->locale = $locale;
	}

	/**
	 * @param mixed $language
	 */
	public function setLanguage( $language ) {
		$this->language = $language;
	}

	/**
	 * @param mixed $community
	 */
	public function setCommunity( $community ) {
		$this->community = $community;
	}

	/**
	 * @param mixed $postalcode
	 */
	public function setPostalcode( $postalcode ) {
		$this->postalcode = $postalcode;
	}

}

class TELEGRAM {
	public $name;
	public $tgid;

	public function __construct( $json ) {
		$this->name = $json->{'name'};
		$this->tgid = $json->{'tgid'};
	}
}
