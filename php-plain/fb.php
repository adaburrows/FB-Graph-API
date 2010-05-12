<?php
/* Fb
 * An easy to use, basic implementation of Facebook's Graph API for PHP
 *==============================================================================
 * -- Version alpha 0.1 --
 * The source code is fairly well documented, except for the base class it
 * derives from.
 * This code is being released under an MIT style license:
 *
 * Copyright (c) 2010 Jillian Ada Burrows
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *------------------------------------------------------------------------------
 * Original Author:		Jillian Ada Burrows
 * Email:			jill@adaburrows.com
 * Website:			<http://www.adaburrows.com>
 * Facebook:			<http://www.facebook.com/jillian.burrows>
 * Twitter:			@jburrows
 *------------------------------------------------------------------------------
 * Use at your own peril! J/K
 * 
 */

require('http_request.php');
class Fb extends HttpRequest {
	//Facebook API domain
	var $fb_d = 'graph.facebook.com';
	//Facebook oAuth endpoint
	var $fb_oauth = '/oauth/';
	//Facebook variables to initalize later
	var $fb_id;
	var $fb_key;
	var $fb_secret;

	//redirect URI (on your server)
	var $redirect_uri;
	//store the token here
	var $token;

	function FbComponent($app_id, $app_key, $app_secret) {$this->__construct($app_id, $app_key, $app_secret);}

	function __construct($app_id, $app_key, $app_secret) {
		//put the FB API keys here:
		$this->fb_id		= $app_id;
		$this->fb_key		= $app_key;
		$this->fb_secret	= $app_secret;

		//call parent constructor
		parent::__construct();

		//set up the specifics for connecting to facebook's api
		$this->request_params['port']		= 443;
		$this->request_params['scheme']		= 'tls://';
		$this->request_params['host']		= $this->fb_d;		
	}

	/* set_redirect_uri
	 * ----------------
	 * Sets the URI that face will redirect the user to after authentication
	 */
	function set_redirect_uri($uri) {
		$this->redirect_uri = $uri;
	}

	/* auth_redirect
	 * -------------
	 * Redirects the user to the autication page if necessary.
	 * You can set the permissions you would like by passing in an array of permissions:
	 *     auth_redirect( array( 'publish_stream', 'create_event', 'rsvp_event' ) );
	 * See <http://developers.facebook.com/docs/authentication/permissions>
	 * for a full list of permissions.
	 */
	function auth_redirect($permissions=null) {
		if (!isset($_GET['code'])) {
			$redirect_url  = "https://{$this->fb_d}{$this->fb_oauth}authorize?";
			$redirect_url .= "client_id={$this->fb_id}";
			$redirect_url .= "&redirect_uri={$this->redirect_uri}";
			$redirect_url .= $permissions!=null ? '&'.implode(',', $permissions) : '';

			http_redirect($redirect_url);

		}
	}

	/* get_token
	 * ---------
	 * After authentication is requested this function will return true if 
	 * the access token was retreived successfully. The access token is stored
	 * in $this->token.
	 */
	function get_token() {
		$this->request_params['path']		= $this->fb_oauth.'access_token';
		$this->request_params['query_params']	= array(
				'client_id'	=> $this->fb_id,
				'redirect_uri'	=> $this->redirect_uri,
				'client_secret'	=> $this->fb_secret,
				'code'		=> $_GET['code']
			);

		$data = $this->do_request() ? $this->explode_query($this->get_data()) : null;
		$access_token = isset($data['access_token']) ? $data['access_token'] : null;
		$this->token = $access_token;
		return $access_token!=null;
	}

	/* get_object
	 * ----------
	 * Requests an object's basic info from the FB social graph. Returns data
	 * as a hash with keys that match the expected values as specified at:
	 * <http://developers.facebook.com/docs/api>
	 */
	function get_object($object) {
		$this->request_params['path']		= $object;
		$this->request_params['query_params']	= array(
				'access_token' => $this->token
			);
		$object = $this->do_request() ? $this->get_data() : null;
		if ($object != null) {
			$object = json_decode($object, true);
		}
		return $object;	
	}

	/* get_connection_types
	 * --------------------
	 * Requests an object's connection types to the FB social graph. Returns
	 * a hash of connection types as keys and links to the respective api
	 * calls.
	 * <http://developers.facebook.com/docs/api>
	 */
	function get_relationships($object) {
		$this->request_params['path']		= $object;
		$this->request_params['query_params']	= array(
				'metadata'	=> 1,
				'access_token'	=> $this->token
			);
		$object = $this->do_request() ? $this->get_data() : null;
		if ($object != null) {
			$object = json_decode($object, true);
		}
		return $object['metadata']['connections'];	
	}

	/* get_connections
	 * ---------------
	 * Requests an object's connections to other objects on the FB graph.
	 * $relation can be: any of: (friends, home, feed (Wall), likes, 
	 * movies, books, notes, photos, videos, events, groups).
	 * However, call get_connection_types to get a real list of connection
	 * an object supports.
	 * <http://developers.facebook.com/docs/api>
	 */
	function get_connections($object, $relation) {
		$this->request_params['path']		= "$object/$relation";
		$this->request_params['query_params']	= array(
				'access_token' => $this->token
			);
		$object = $this->do_request() ? $this->get_data() : null;
		if ($object != null) {
			$object = json_decode($object, true);
		}
		return isset($object['data']) ? $object['data'] : null;
	}

	function post_feed($object, $message_params) {
		$this->request_params['method']		= 'POST';
		$this->request_params['path']		= "$object/feed";
		$this->request_params['query_params']	= array_merge(array(
				'access_token' => $this->token
			), $message_params);
		$this->request_params['body']		= $this->build_query($message_params);
		$object = $this->do_request() ? $this->get_data() : null;
		if ($object != null) {
			$object = json_decode($object, true);
		}
		return $object;
	}
}
