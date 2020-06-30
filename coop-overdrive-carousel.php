<?php defined('ABSPATH') || die(-1);

/**
*	@package: OverDrive	
*	@comment: Driver for OAuth connection and API searching
*	@author: Erik Stainsby / Roaring Sky Software
*	@copyright: BC Libraries Coop, 2015
**/

/**
 * Plugin Name: OverDrive carousel widget
 * Description: Carousel of new titles on OverDrive. NETWORK ACTIVATE.
 * Author: Erik Stainsby, Roaring Sky Software
 * Version: 0.1.5
 **/


if ( ! class_exists( 'Overdrive_Carousel' )) :
	
class Overdrive_Carousel {

	public function __construct() {
	
		add_action( 'init', array( &$this, '_init' ));
	
	}
	
	public function _init() {
	
		add_shortcode( 'overdrive_carousel', array( &$this, 'coop_od_shortcode' ) );
		wp_register_sidebar_widget('carousel-overdrive','OverDrive Carousel',array(&$this,'coop_od_widget'));
		wp_register_widget_control('carousel-overdrive','OverDrive Carousel',array(&$this,'coop_od_widget_control'));
	
		require_once( 'inc/ODauth.php' );	
		
		if( !is_admin()) {		
			wp_register_style( 'coop-overdrive', 	plugins_url( '/css/overdrive.css', __FILE__ ), false );
			add_action( 'wp_enqueue_scripts', array( &$this, 'frontside_enqueue_styles_scripts' ));
			wp_register_script( 'coop-overdrive-js', 	plugins_url( '/js/overdrive.js',__FILE__), array('jquery'));
		}
		else 
		{	
			add_action( 'admin_enqueue_scripts', array( &$this, 'admin_enqueue_styles_scripts' ));
		}	
	}
	
	public function frontside_enqueue_styles_scripts() {
		wp_enqueue_style( 'coop-overdrive' );
		wp_enqueue_script( 'coop-overdrive-js' );
	}
	
	public function admin_enqueue_styles_scripts($hook) {
	
		if( 'widgets.php' !== $hook ) {
			return;
		}
	
		wp_register_style('coop-overdrive-admin',plugins_url('/css/overdrive-admin.css',__FILE__), false );
		wp_register_script('coop-overdrive-admin-js', plugins_url( '/js/overdrive-admin.js',__FILE__), array('jquery'), false);
		wp_enqueue_style('coop-overdrive-admin');
		wp_enqueue_script('coop-overdrive-admin-js');
		
	}
	
	
	public function coop_od_shortcode ( $atts ) {
	
		extract( shortcode_atts( array(), $atts ));
								
		global $odauth;
		if( ! isset($odauth)) {
			die('no OD auth library found');
		}
		
		$cover_count = get_option('coop-od-covers');
		if( empty($cover_count)) {
			$cover_count = 20;
		}
		
		
		$out = array();

		$dwell = 800;
		$transition = 400;

		//error_log("Province was: ". $odauth->province);

		/*Start making OverDrive API calls:
		 1. Generate token
		 2. Use token to get_product_link
		 3. Use both to grab covers and data
		 */

		//If the transient does not exist or is expired, refresh the data
		if ( false === ( $newest_data = get_transient( 'coop_overdrive_daily_results' . $odauth->province ) ) ) {
			$token = $odauth->get_token();

			$link = $odauth->get_product_link( $token );

			$newest_data = $odauth->get_newest_n( $token, $link, $cover_count );

    	set_transient( 'coop_overdrive_daily_results' . $odauth->province, $newest_data, WEEK_IN_SECONDS );
    	$msg = "Transient OD DATA EXPIRED for {$odauth->province} and we made an API call.";
		}

		else { //Otherwise refresh from transient data and make no calls.
			$newest_data = get_transient( 'coop_overdrive_daily_results' . $odauth->province );
			$msg = "Currently using CACHED OD DATA for {$odauth->province}";
		}

		$out[] = $newest_data;

		$out[] = '<script type="text/javascript">';
		$out[] = 'jQuery().ready(function($) { ';
		$out[] = '   $(".carousel-container").tinycarousel({ ';
		$out[] = '       display: 1, ';
		$out[] = '       controls: true, ';
		$out[] = '       interval: true, ';
		$out[] = '       intervalTime: '.$dwell.', ';
		$out[] = '       duration:     '.$transition.' ';
		$out[] = '	}) ';
		$out[] = '}); ';
    if (! empty($msg) )$out[]= "console.log('$msg')";
		$out[] = '</script>';

		return implode( "\n", $out );
	}


	public function coop_od_widget($args) {

		// error_log(__FUNCTION__);

		global $odauth;
		if( ! isset($odauth)) {
			die('no OD auth library found');
		}
		
		$heading = get_option('coop-od-title');
		$cover_count = get_option('coop-od-covers');
		$dwell = get_option('coop-od-dwell');
		$transition = get_option('coop-od-transition');
		
		if( empty($heading)) {
			$heading = 'Fresh eBooks/Audio';
		}
		if( empty($cover_count)) {
			$cover_count = 20;
		}
		if( empty($dwell)) {
			$dwell = 800;
		}
		if( empty($transition)) {
			$transition = 400;
		}
		
		/*Start making OverDrive API calls:
		 1. Generate token
		 2. Use token to get_product_link
		 3. Use both to grab covers and data
		 */

		//If the transient does not exist or is expired, refresh the data
		if ( false === ( $newest_data = get_transient( 'coop_overdrive_daily_results' . $odauth->province ) ) ) {

			$token = $odauth->get_token();
			$link = $odauth->get_product_link( $token );
			$newest_data = $odauth->get_newest_n( $token, $link, $cover_count );

			set_transient( 'coop_overdrive_daily_results' . $odauth->province, $newest_data, 60 * HOUR_IN_SECONDS );

		}

		else { //Otherwise refresh from transient data and make no calls.
			$newest_data = get_transient( 'coop_overdrive_daily_results' . $odauth->province );
		}

		$out = array();
		
		extract($args);
		/*	widget-declaration:
			id
			name
			before_widget
			after_widget
			before_title
			after_title
		*/
		
		$out[] = $before_widget;
		
		$out[] = $before_title;
		$out[] = '<a href="http://downloads.bclibrary.ca/">';
		$out[] = $heading;
		$out[] = '</a>';
		$out[] = $after_title;
		
		// returning HTML currently 
		$out[] = $newest_data;
		
		$out[] = $after_widget;
		
		$out[] = '<script type="text/javascript">';
		$out[] = 'jQuery().ready(function($) { ';
		$out[] = '   $(".carousel-container").tinycarousel({ ';
		$out[] = '       display: 1, ';
		$out[] = '       controls: true, ';
		$out[] = '       interval: true, ';
		$out[] = '       intervalTime: '.$dwell.', ';
		$out[] = '       duration:     '.$transition.' ';
		$out[] = '	}) ';
		$out[] = '}); ';
		$out[] = '</script>';
				
		echo implode( "\n", $out );
	}
	
	
	public function coop_od_widget_control() {
		
	//	error_log(__FUNCTION__);
		
		if(!get_option('coop-od-title'))
		{
			add_option('coop-od-title','Fresh eBooks & audioBooks');
		}
		$coop_od_title = $coop_od_title_new = get_option('coop-od-title');
		if( array_key_exists('coop-od-title',$_POST))
		{
			$coop_od_title_new = sanitize_text_field($_POST['coop-od-title']);
		}
		if( $coop_od_title != $coop_od_title_new ) {
			$coop_od_title = $coop_od_title_new;
			update_option('coop-od-title',$coop_od_title);
		}
		
		if(!get_option('coop-od-covers'))
		{
			add_option('coop-od-covers', 20);
		}
		$coop_od_covers = $coop_od_covers_new = get_option('coop-od-covers');
		if(array_key_exists('coop-od-covers',$_POST))
		{
			$coop_od_covers_new = sanitize_text_field($_POST['coop-od-covers']);
		}
		if( $coop_od_covers != $coop_od_covers_new ) {
			$coop_od_covers = $coop_od_covers_new;
			update_option('coop-od-covers',$coop_od_covers);
		}
		
		
		if(!get_option('coop-od-dwell'))
		{
			add_option('coop-od-dwell', 800 );
		}
		$coop_od_dwell = $coop_od_dwell_new = get_option('coop-od-dwell');
		if(array_key_exists('coop-od-dwell',$_POST))
		{
			$coop_od_dwell_new = sanitize_text_field($_POST['coop-od-dwell']);
		}
		if( $coop_od_dwell != $coop_od_dwell_new ) {
			$coop_od_dwell = $coop_od_dwell_new;
			update_option('coop-od-dwell',$coop_od_dwell);
		}
		
		
		if(!get_option('coop-od-transition'))
		{
			add_option('coop-od-transition', 400 );
		}
		$coop_od_transition = $coop_od_transition_new = get_option('coop-od-transition');
		if(array_key_exists('coop-od-transition',$_POST))
		{
			$coop_od_transition_new = sanitize_text_field($_POST['coop-od-transition']);
		}
		if( $coop_od_transition != $coop_od_transition_new ) {
			$coop_od_transition = $coop_od_transition_new;
			update_option('coop-od-transition',$coop_od_transition);
		}
	
		$out = array();
				
		$out[] = '<p>';
		$out[] = '<label for="coop-od-title">Heading:</label>';
		$out[] = '<input id="coop-od-title" type="text" value="'.$coop_od_title.'" name="coop-od-title">';
		$out[] = '</p>';

		$out[] = '<p>';
		$out[] = '<label for="coop-od-covers">Number of covers:</label>';
		$out[] = '<input id="coop-od-covers" type="text" value="'.$coop_od_covers.'" name="coop-od-covers">';
		$out[] = '</p>';
	
		$out[] = '<p>';
		$out[] = '<label for="coop-od-dwell">Dwell time (ms):</label>';
		$out[] = '<input id="coop-od-dwell" type="text" value="'.$coop_od_dwell.'" name="coop-od-dwell">';
		$out[] = '</p>';
		
		$out[] = '<p>';
		$out[] = '<label for="coop-od-transition">Transition time (ms):</label>';
		$out[] = '<input id="coop-od-transition" type="text" value="'.$coop_od_transition.'" name="coop-od-transition">';
		$out[] = '</p>';
		
	
		echo implode("\n",$out);
	}
	
}
	
if ( ! isset( $overdrive_carousel ) ) {
	global $overdrive_carousel; 
	$overdrive_carousel = new Overdrive_Carousel();
}
	
endif; /* ! class_exists */