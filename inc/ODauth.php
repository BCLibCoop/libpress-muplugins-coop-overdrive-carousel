<?php defined('ABSPATH') || die(-1);

/**
*	@package: OverDrive	
*	@comment: open auth connection library
*	@author: Erik Stainsby / Roaring Sky Software
*	@copyright: BC Libraries Coop, 2015
**/

if ( ! class_exists( 'ODAuth' )) :
	
class ODauth {

	var $libID;
	var $clientkey;
	var $clientsecret;
	public $province; //access this from outside for transient

	//don't set these until init where a check is made with get_site_url
	var $auth_uri = 'https://oauth.overdrive.com/token';
	var $account_uri = 'http://api.overdrive.com/v1/libraries';
	
	public function __construct() {
		add_action( 'init', array( &$this, '_init' ));
		
		$shortcode = get_blog_option( get_current_blog_id(), '_coop_sitka_lib_shortname');

		preg_match('%(^[A-Z]{1})%', $shortcode, $matches);
		$provsub = $matches[1];


		if ($provsub === 'M' || $provsub === 'S') {
			$this->province = 'mb';
			$this->libID = '1326';
			$this->clientkey = '***REMOVED***';
			$this->clientsecret = '***REMOVED***';
		}

		else { //BC or not applicable
			
			$this->province = 'bc';
			$this->libID = '1228';
			$this->clientkey = '***REMOVED***';
			$this->clientsecret = '***REMOVED***';
		}
	
	}
	
	public function _init() {

		//error_log( __FUNCTION__ );
	}
	
	
	public function get_token() {

		$hash = base64_encode($this->clientkey.':'.$this->clientsecret);
		$authheader = array('Authorization: Basic '.$hash,
							'Content-Type: application/x-www-form-urlencoded;charset=UTF-8' );
		$bodydata = 'grant_type=client_credentials';
				
		$ch = curl_init( $this->auth_uri ); 
		
		curl_setopt($ch, CURLOPT_POST, 1);	
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
		curl_setopt($ch, CURLOPT_HTTPHEADER, $authheader );
		curl_setopt($ch, CURLOPT_POSTFIELDS, $bodydata);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 

		$json = curl_exec($ch);
		curl_close($ch);
	
	//	error_log( $json );
		
		$data = json_decode( $json );
		$token = $data->access_token;
		
	//	error_log( $token );
		
		return $token;
	 
	}
	
	public function get_product_link( $token ) {
		
		$ch = curl_init( $this->account_uri .'/'. $this->libID );
		
		$userip = $_SERVER['REMOTE_ADDR'];
		
		curl_setopt($ch, CURLOPT_HTTPGET, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer '.$token, 'X-Forwarded-For: '.$userip) );
		curl_setopt($ch, CURLOPT_USERAGENT, 'BC Libraries Coop Carousel v2' );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
		
		$json = curl_exec($ch);
		curl_close($ch);
		
		$account = json_decode( $json );
		
		$url = $account->links->products->href;
		$type = $account->links->products->type; 
		
		return array( 'url'=>$url, 'type'=>$type );
		
	}
	
	
	public function get_newest_n( $token, $link, $n ) {

			$ch = curl_init( $link['url'] .'/?limit='.$n.'&offset=0&sort=dateadded:desc' );
			
			$userip = $_SERVER['REMOTE_ADDR'];
			
			curl_setopt($ch, CURLOPT_HTTPGET, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer '.$token, 'X-Forwarded-For: '.$userip) );
			curl_setopt($ch, CURLOPT_USERAGENT, 'BC Libraries Coop Carousel v2' );
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
			
			$json = curl_exec($ch);
			curl_close($ch);

			$r = json_decode( $json );

		$out = array();
			
		$out[] = '<div class="carousel-container">';
	  $out[] = '<div class="carousel-viewport">';
		$out[] = '<ul class="carousel-tray">';

		foreach( $r->products as $p ) {
			
			$out[] = '<li class="carousel-item">';
			$out[] = sprintf('<a href="http://%s">',$p->contentDetails[0]->href);
			$out[] = sprintf('<img src="%s">',$p->images->cover150Wide->href);
			$out[] = '<div class="carousel-item-assoc">';
			$out[] = sprintf('<span class="carousel-item-title">%s</span><br/><span class="carousel-item-author">%s</span></a>',$p->title, $p->primaryCreator->name);
			$out[] = '</div><!-- .carousel-item-assoc -->';
			$out[] = '</li>';
		}

		$out[] = '</ul><!-- .carousel-tray -->';
		$out[] = '</div><!-- .carousel-viewport -->';
		$out[] = '<div class="carousel-button-box">';
		$out[] = '<a class="carousel-buttons prev" href="#">left</a>';
		$out[] = '<a class="carousel-buttons next" href="#">right</a>';
		$out[] = '</div><!-- .carousel-button-box -->';
		$out[] = '</div><!-- .carousel-container -->';

		return implode("\n",$out);
	}
	
}
	
	
if ( ! isset( $odauth ) ) {
	global $odauth; 
	$odauth = new ODauth();
}
	
endif; /* ! class_exists */
