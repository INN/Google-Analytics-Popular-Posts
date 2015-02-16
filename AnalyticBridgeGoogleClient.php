<?php

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
 *        with the right scopes.
 * @param array $e passed by reference. If provided, $e will contain error information 
 *        if authentication fails.
 * 
 * @return Google_Client object on success, 'false' on failure.
 */
function analytic_bridge_google_client($auth = true,&$e = null) {

	// We want to authenticate and there is no auth ticket or refresh token.
	if( $auth && !(get_option('analyticbridge_access_token') && get_option('analyticbridge_refresh_token')) ) :
		
		if( $e ) {
			$e = array();
			$e['message'] = 'No access token. Get a system administrator to authenticate the Analytic Bridge.';
		}

		return false;

	// We want to authenticate and there is no client id or client secret.
	elseif( $auth && !(analyticbridge_client_id() && analyticbridge_client_secret()) ) :

		if( $e ) {
			$e = array();
			$e['message'] = 'No access token. Get a system administrator to authenticate the Analytic Bridge.';
		}

		return false;

	// We have everything we need.
	else : 

		// Create a Google Client.
		$client = new Google_Client();
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
			
			} catch(Google_Auth_Exception $error) {
				
				// return (by reference) error information.
				if ( $e ) { 
					$e = $error; 
				}
				return false;
			}

		endif;

		// Return our client.
		return $client;

	endif;

}

/**
 * Used the first time a user is authenticating.
 * 
 * Attempts to authenticate a new google client for the first time and
 * saves an access and refresh token to the database before returning the client.
 * 
 * @param String $code 
 * 
 * @since 0.1
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
