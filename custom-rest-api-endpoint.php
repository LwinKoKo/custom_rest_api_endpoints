<?php
/*
Plugin Name: Custom REST API Endpoint
Plugin URI: https://www.lwinkoko.me
Description: A custom REST API endpoint with Basic authentication.
Author: Lwin Ko Ko
Version: 1.0.0
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Custom_Rest_API_Endpoints extends WP_REST_Controller {
	private $api_namespace;
	private $user_base;
	private $api_version;
	private $required_capability;
	
	public function __construct() {
		$this->api_namespace = 'api-custom/';
		$this->user_base = 'users';
		$this->api_version = 'v1';
		$this->required_capability = 'read';  // Minimum capability to use the endpoint
		$this->init();
	}
	
	public function register_routes() {
		$namespace = $this->api_namespace . $this->api_version;
		
		// User APIs
		register_rest_route( $namespace, '/' . $this->user_base, array(
			array( 
				'methods' => WP_REST_Server::READABLE, 
				'callback' => array( $this, 'get_users' ), 
				'permission_callback' => array( $this, 'permission_check' ),
			),
		) );
		register_rest_route( $namespace, '/' . $this->user_base . '/(?P<id>[\d]+)' , array(
			array( 
				'methods' => WP_REST_Server::READABLE, 
				'callback' => array( $this, 'get_user' ), 
				'permission_callback' => array( $this, 'permission_check' ),
			),
		) );
	}

	// Register our REST Server
	public function init(){
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}
	
	//Check User Is Authorized or Not
	public function permission_check( $request ){
		$headers = getallheaders();

		// Get username and password from the submitted headers.
		if ( array_key_exists( 'Authorization', $headers ) || array_key_exists( 'authorization', $headers ) ) {

			// Don't authenticate twice
			if ( ! empty( $user ) ) {
				return true;
			}

			// Check that we're trying to authenticate
			if ( !isset( $_SERVER['PHP_AUTH_USER'] ) ) {
				return true;
			}

			$username = $_SERVER['PHP_AUTH_USER'];
			$password = $_SERVER['PHP_AUTH_PW'];

			/**
			 * In multi-site, wp_authenticate_spam_check filter is run on authentication. This filter calls
			 * get_currentuserinfo which in turn calls the determine_current_user filter. This leads to infinite
			 * recursion and a stack overflow unless the current function is removed from the determine_current_user
			 * filter during authentication.
			 */
			remove_filter( 'determine_current_user', 'json_basic_auth_handler', 20 );

			$user = wp_authenticate( $username, $password );

			add_filter( 'determine_current_user', 'json_basic_auth_handler', 20 );

			if ( is_wp_error( $user ) ) {
				return new WP_Error( 'incorrect_password', 'The password you entered for the username ' . $username . ' is incorrect.' , array( 'status' => 401 /* Unauthorized */ ) );
			}

			return true;
		}
		else {
			return new WP_Error( 'invalid-method', 'You must specify a valid username and password.', array( 'status' => 400 /* Bad Request */ ) );
		}
	}
	
	public function get_users( WP_REST_Request $request ){
		// All real actions code here!
		global $wpdb;
		$user_ids = $wpdb->get_results("SELECT `ID` FROM `wp_users`");
		foreach( $user_ids as $user_key => $user_value ){
			$response[] = $this->prepare_user_response( $user_value->ID );
		}
		return $response;
	}
	
	public function get_user( WP_REST_Request $request ){
		// All real actions code here!
		global $wpdb;
		$user_id = $wpdb->get_var("SELECT `ID` FROM `wp_users` WHERE `ID` = '" . $request['id'] . "'");
		if( $user_id ){
			$response = $this->prepare_user_response( $user_id );
			return $response;
		}
		else{
			return new WP_Error( 'user_not_found', "There is no user with of " . $request['id'] , array( 'status' => 404 /* Not Found */ ) );
		}
	}
	
	public function prepare_user_response( $user_id ){
		global $wpdb;
		
		$user_data = $wpdb->get_results("SELECT * FROM `wp_users` WHERE `ID` = '" . $user_id . "'");
		$user_meta = $wpdb->get_results("SELECT `meta_key`, `meta_value` FROM `wp_usermeta` WHERE `user_id` = '" . $user_id . "'");
		
		if( $user_data ){
			$user_data = $user_data[0];
		}
			
		if( $user_meta ){
			foreach( $user_meta as $key => $value){
				$meta_data[ $value->meta_key ] = $value->meta_value;
			}
		}
		else{
			$meta_data = array();
		}
			
		$user_data->meta_data = $meta_data;
			
		$response = $user_data;
			
		return $response;
	}
}
 
$custom_rest_api_endpoints = new Custom_Rest_API_Endpoints();
