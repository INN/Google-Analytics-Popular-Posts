<?php

class AnalyticBridge {

	/**
	 * Refers to a single instance of this class.
	 */
	private static $instance = null;

	/**
	 * Refers to a single instance of this class.
	 */
	private $client;

	private $clientAuthenticated;

	/**
	 * Creates or returns an instance of this class.
	 *
	 * @return AnalyticBridge Object A single instance of this class.
	 */
	public static function get_instance() {

		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;

	} // end get_instance;

	/**
	 * Initializes the plugin by setting localization, filters, and administration functions.
	 */
	private function __construct() {

		$this->client = null;
		$this->clientAuthenticated = false;

	} // end constructor

	/**
	 * Attempts to authenticate with google's servers.
	 *
	 * Pulls the access_token and refresh_token provided by google from the
	 * database and authenticates a google client.
	 *
	 * On success, returns a google_client object that's pre authenticated and loaded
	 * with the right scopes to access analytic data and email/name of the authenticated.
	 *
	 * On failure, returns false.
	 *
	 * @since v0.1
	 *
	 * @param boolean $auth whether we should try to authenticate the client or just set it up
	 *		with the right scopes.
	 * @param array $e passed by reference. If provided, $e will contain error information
	 *		if authentication fails.
	 *
	 * @return Google_Client object on success, 'false' on failure.
	 */
	public function getClient( $auth = true, &$e = null ) {

		if( $auth && $this->client && $this->clientAuthenticated ) {
			return $this->client;
		}

		// We want to authenticate and there is no ( auth ticket and refresh token )
		// Both are needed, see https://developers.google.com/identity/protocols/OAuth2
		if ( $auth && ! ( get_option( 'analyticbridge_access_token' ) && get_option( 'analyticbridge_refresh_token' ) ) ) :

			// @todo we need better user-facing errors here if there is not a access token or a refresh token
			// including instructions on how to revoke permissions in their google account to get a new refresh token when they sign in again, because google only doles those out on the first sign-in
			if ( $e ) {
				error_log( var_export( "You need a reset token, probably", false ) );
				$e = array();
				$e['message'] = 'No access token. Get a system administrator to authenticate the Google Analytics Popular Posts plugin.';
			}

			return false;

		// We want to authenticate and there is no client id or client secret.
		// Client id and secret are needed to create the redirect button, to send us to Google oAuth page
		// See https://developers.google.com/identity/protocols/OAuth2
		elseif ( $auth && !( analyticbridge_client_id() && analyticbridge_client_secret() ) ) :

			if ( $e ) {
				$e = array();
				$e['message'] = 'No client id or client secret. Get a system administrator to authenticate the Google Analytics Popular Posts plugin.';
			}

			return false;

		// We have everything we need.
		else :

			// Create a Google Client.

			$config = new Google_Config();
			$config->setCacheClass("Google_Cache_Null");

			$client = new Google_Client($config);
			$client->setApplicationName("Analytic_Bridge");
			$client->setClientId( analyticbridge_client_id() );
			$client->setClientSecret( analyticbridge_client_secret() );
			$client->setRedirectUri(site_url("/wp-admin/options-general.php?page=analytic-bridge"));
			$client->setAccessType("offline");
			$client->setScopes(
				array(
					'https://www.googleapis.com/auth/analytics.readonly',
					'https://www.googleapis.com/auth/userinfo.email',
					'https://www.googleapis.com/auth/userinfo.profile'
				)
			);

			/*
			 * If there's an access token set, try to authenticate with it.
			 * Otherwise we just return without any authenticating.
			 */
			if( $auth ) :

				try {
					$client->setAccessToken( get_option('analyticbridge_access_token') );
					if( $client->isAccessTokenExpired() && get_option('analyticbridge_refresh_token') ) {
						$token = get_option('analyticbridge_refresh_token');
						$accesstoken = $client->refreshToken( $token );
						update_option('analyticbridge_access_token',$client->getAccessToken());
					}
					$this->clientAuthenticated = true;

				} catch(Google_Auth_Exception $error) {

					// return (by reference) error information.
					if ( $e ) {
						$e = $error;
					}

					$this->clientAuthenticated = false;
					return false;
				}

			endif;

			// Return our client.

			$this->client = $client;

			return $client;

		endif;
	}

}

/**
 * Attempts to authenticate with google's servers.
 *
 * @see AnalyticBridge->getClient() for full documentation.
 *
 * @since v0.1
 *
 * @param boolean $auth whether we should try to authenticate the client or just set it up
 *		with the right scopes.
 * @param array $e passed by reference. If provided, $e will contain error information
 *		if authentication fails.
 * @return Google_Client object on success, 'false' on failure.
 */
function analytic_bridge_google_client($auth = true,&$e = null) {
	$AnalyticBridge = AnalyticBridge::get_instance();
	return $AnalyticBridge->getClient($auth,$e);
}

/**
 * Used the first time a user is authenticating.
 *
 * Attempts to authenticate a new google client for the first time and
 * saves an access and refresh token to the database before returning the client.
 *
 * @since 0.1
 *
 * @param String $code
 */
function analytic_bridge_authenticate_google_client($code, &$e = null) {

	// get a new unauthenticated google client.
	$client = analytic_bridge_google_client(false,$e);

	// If we didn't get a client (for whatever reason) return false.
	if(!$client)
		return false;

	$client->authenticate($code);
	update_option('analyticbridge_access_token',$client->getAccessToken());
	update_option('analyticbridge_refresh_token',$client->getRefreshToken());
	update_option('analyticbridge_authenticated_user',get_current_user_id());
	update_option('analyticbridge_authenticated_date_gmt',current_time('mysql',true));

}

function analyticbridge_client_id() {
	if(get_site_option('analyticbridge_network_setting_api_client_id')) {
		$network = true;
		return get_site_option('analyticbridge_network_setting_api_client_id');
	} else {
		$network = false;
		return get_option('analyticbridge_setting_api_client_id');
	}
}

function analyticbridge_client_secret() {
	if(get_site_option('analyticbridge_network_setting_api_client_secret')) {
		$network = true;
		return get_site_option('analyticbridge_network_setting_api_client_secret');
	} else {
		$network = false;
		return get_option('analyticbridge_setting_api_client_secret');
	}
}


/**
 * If API tokens are defined for the network return true. Else, return false.
 *
 * @since v0.1
 *
 * @return boolean true if network api tokens are defined, false if otherwise.
 */
function analyticbridge_using_network_api_tokens() {
	if(get_site_option('analyticbridge_network_setting_api_client_secret_network') || get_site_option('analyticbridge_network_setting_api_client_id')) {
		return true;
	} else {
		return false;
	}
}
