<?php
/**
 * Twitter Tweet plugin allows you to quick import a Twitter
 * status message into any post/page without having to copy the information.
 * 
 * Formats the output just like Twitter.
 * 
 * Note: PHP5 Only!
 * 
 * @author Shane Froebel <shane@bugssite.org>
 * @version 1.0.0
 * @copyright 2009
 * @package twitter-tweet
 */

 /**
 * Define the Twitter Tweet Plugin
 * @version 1.0.0
 * @return none
 */
DEFINE ('TWITTERTWEET_VERSION', '1.0.0');		// MAKE SURE THIS MATCHES THE VERSION ABOVE AND BELOW!!!!

require_once(ABSPATH . WPINC . '/class-IXR.php');
require_once(ABSPATH . WPINC . '/class-json.php');

/*
**************************************************************************
Plugin Name:  Twitter Tweet
Plugin URI:   http://bugssite.org/projects/wordpress-plugins/twitter-tweet/
Version:      1.0.0
Description:  Shows a Twitter Status quickly in WordPress without copying the information.
Author:       Shane Froebel <http://bugssite.org> and Jonathan Dingman <http://wpvibe.com>
**************************************************************************/

/**
 * The shortcode
 * 
 * Note: PHP5 must be installed.
 * 
 * @version 1.0.0
 * @return none
 */
if ( version_compare(PHP_VERSION, '5.0.0', '>=') )	// we need to be using at least version PHP 5.0.0
	add_shortcode('tweeted', 'twittertweet_getstatus');

/**
 * Get the status information first before we query the Twitter API
 * @since 1.0.0
 * @param string $status_url
 * @return none
 */
function twittertweet_getstatus($atts, $content) {

	$data = explode("/", $content); // Get the status url - Only need the numbers after the last '/'

	if ( $data[4] != "status" && $data[4] != "statuses" )		//	 user enter wrong url. must be in the form of 'http://twitter.com/[USER]/status/[NUMBER}' 
		return;
		
	if ( !is_numeric($data[5]) )	//	the last part of the string is not a number at all!
		return;
		
	return twittertweet_showstatus($data[5]);
}

/**
 * Output the Status!
 * @since 1.0.0
 * @param int $status_id
 * @return none
 */
function twittertweet_showstatus($status_id = '') {
	global $post;
	
	/** Check to see if it's already stored in the post/page data. **/
	$twittertweet_tweet_meta_values = get_post_meta($post->ID, '_twittertweet_' . $status_id, true);
	// If we have data already from this post, we should be showing it. No need to re-query Twitter.
	if ( is_array($twittertweet_tweet_meta_values) ) {
		return twittertweet_showtweet($twittertweet_tweet_meta_values);
	}
	
	/**
	 * Query Twitter API
	 **/
	$twitter_ixr = new IXR_Twitter('http://twitter.com/statuses/show/'.$status_id.'.json');
	$twitter_ixr->debug = false;	//	only set this to true when debuging Twitter Connection!
	if ( !$twitter_ixr->query('GET'))
	    die('Something Went Wrong: '.$twitter_ixr->getErrorCode().' : '.$twitter_ixr->getErrorMessage());
	/** Store the data. **/	
	$twitter_ixr_data = $twitter_ixr->message;
	/** Close the IXR connection **/
	unset($twitter_ixr);
	
	/**
	 * Start JSON Service 
	 **/
	$twitter_json = new Services_JSON();
	/** Decode JSON Data **/
	$twitter_json_data = $twitter_json->decode($twitter_ixr_data->message);
	/** Close the JSON Connection **/
	unset($twitter_json);
	
	/** Store Data **/
	$tweet_array = array(
		'id'		=> $twitter_json_data->user->id,
		'realname'	=> $twitter_json_data->user->name,
		'user'		=> $twitter_json_data->user->screen_name,
		'gravatar'	=> $twitter_json_data->user->profile_image_url,
		'text'		=> $twitter_json_data->text,
		'timedate'	=> $twitter_json_data->created_at
	);
	add_post_meta($post->ID, '_twittertweet_' . $status_id, $tweet_array, true);
	unset($twitter_json_data);
	
	return twittertweet_showtweet($tweet_array);
	
}

/**
 * Show the tweet.
 * @since 1.0.0
 * @param $content
 * @return none
 */
function twittertweet_showtweet($content) {
	
	// Apply Filters
	$text_generate = apply_filters('twittertweet_text', $content['text']);
	$user_generate = apply_filters('twittertweet_user', sprintf( __('<a href="http://twitter.com/%s" title="Visit %s Twitter Page">%s</a>'), $content['user'], $content['user'], $content['user']), $content['user']);
	$timedate_generate = apply_filters('twittertweet_timedate', $content['timedate']);

	$tweet_output .= '<div class="tweeted">';
	$tweet_output .= '<div>'.$text_generate.'</div>';
	$tweet_output .= '<div style="padding-bottom: 10px;">';
	$tweet_output .= '<div class="tweeted_user"><img alt="" src="'.$content['gravatar'].'" class="tweeted_img" border="0" width="73" height="73" />'.$user_generate.'/'.apply_filters('twittertweet_realname', $content['realname']).'</div>';
	$tweet_output .= '<div class="tweeted_date">'.$timedate_generate.'</div>';
	$tweet_output .= '</div>';
	$tweet_output .= '</div>';

	//echo $tweet_output;
	
	return $tweet_output;
}

/**
 * Add Twitter Style
 * @since 1.0.0
 * @param $styles
 * @return none
 */
function twittertweet_default_styles(&$styles) {
	$styles->add( 'twitter-tweet', WP_PLUGIN_URL . '/include-tweets-from-twitter/twitter-tweet.css' , '', '20100112' );
}

/**
 * Only output CSS code if needed.
 * @since 1.0.0
 * @param $posts
 * @return array
 */
function twittertweet_showcss($posts) {
	if (empty($posts)) 
		return $posts;
 
	$shortcode_found = false;
	foreach ($posts as $post) {
		if (stripos($post->post_content, '[tweeted]')) {
			$shortcode_found = true;
			break;
		}
	}
 
	if (($shortcode_found) && (!get_option('twitter_tweet_useowncss')))
		wp_enqueue_style('twitter-tweet');
 
	return $posts;
}

/**
 * Empty Field
 * @since 1.0.0
 * @return none
 */
function twittertweet_settings() {
	// None!
}

/**
 * Show Twitter Tweet Option!
 * @since 1.0.0
 * @return none
 */
function twittertweet_settings_option() {
	echo '<label for="twitter_tweet_useowncss"><input name="twitter_tweet_useowncss" id="twitter_tweet_useowncss" value="1"'.checked('1', get_option('twitter_tweet_useowncss'), false).' type="checkbox"> ' . __("Use own CSS for showing Tweets") . ' </label>';
}

/**
 * Process Twitter Admin
 * @since 1.0.0
 * @return none 
 */
function twittertweet_admin_init() {
	add_settings_section('twittertweet_main', 'Twitter Tweet Settings', 'twittertweet_settings', 'misc');
	add_settings_field('misc', 'Twitter Tweet CSS Usage', 'twittertweet_settings_option', 'misc', 'twittertweet_main');
}

/**
 * White List Options
 * @since 1.0.0
 * @param $whitelist_options
 * @return array
 */
function twittertwee_whitelist_update($whitelist_options) {
	
	/** Temp Save **/
	$tmp_mist = $whitelist_options['misc'];
	$our_options = array('twitter_tweet_useowncss');
	
	/** Create and Save **/
	$update_misc_options = array_merge($tmp_mist, $our_options);
	$whitelist_options['misc'] = $update_misc_options;
	
	/** Send Options Back! **/
	return $whitelist_options;
	
}

/**
 * Extends IXR_Client to use it for Twitter API Calls.
 * @author Shane A. Froebel
 * @since 1.0.0
 */
class IXR_Twitter extends IXR_Client {
	
	/**
	 * This is the hard-coded User Agent String
	 * @var string
	 */
	var $useragent = 'Twitter Tweet WordPress Plugin';
	
    function query() {
        $args = func_get_args();
        $method = array_shift($args);
        $request = new IXR_Request($method, $args);
        $length = $request->getLength();
        $xml = $request->getXml();
        
        $r = "\r\n";
        $request = "GET {$this->path} HTTP/1.0$r";

		$this->headers['Host']				= $this->server;
		$this->headers['Content-Type']		= 'text/xml';
		$this->headers['User-Agent']		= $this->useragent;
		$this->headers['Content-Length']	= $length;
		
		if ($this->debug) {
			echo '<pre class="ixr_request">'.htmlspecialchars($request)."\n</pre>\n\n";
		}
		
		foreach( $this->headers as $header => $value ) {
			$request .= "{$header}: {$value}{$r}";
		}
		$request .= $r;
        $request .= $xml;
        if ($this->timeout) {
            $fp = @fsockopen($this->server, $this->port, $errno, $errstr, $this->timeout);
        } else {
            $fp = @fsockopen($this->server, $this->port, $errno, $errstr);
        }
        if (!$fp) {
            $this->error = new IXR_Error('twitter-tweet-transport', "Transport Error - Could not open socket: $errno $errstr");
            return false;
        }
        fputs($fp, $request);
        $contents = '';
        $debug_contents = '';
        $gotFirstLine = false;
        $gettingHeaders = true;
        while (!feof($fp)) {
            $line = fgets($fp, 4096);
            if (!$gotFirstLine) {
                if (strstr($line, '200') === false) {
                    $this->error = new IXR_Error('twitter-tweet-transport-http', 'Transport Error - HTTP status code was not 200. - ' . $line);
                    return false;
                }
                $gotFirstLine = true;
            }
            if (trim($line) == '') {
                $gettingHeaders = false;
            }
            if (!$gettingHeaders) {
                $contents .= trim($line);
            }
            if ($this->debug) {
            	$debug_contents .= $line;
            }
        }
        if ($this->debug) {
        	echo '<pre class="ixr_response">'.htmlspecialchars($debug_contents)."\n</pre>\n\n";
        }
        if (empty($contents)) {
        	$this->error = new IXR_Error('twitter-tweet-nocontent', 'No content.');
        	return false;
        }
        $this->message = new IXR_Message($contents);
        if ($this->debug) {
        	echo print_r($this->message);
        }
        return true;
    }
    
}

/**
 * Actions
 */
add_action( 'admin_init', 'twittertweet_admin_init' );
add_action( 'wp_default_styles', 'twittertweet_default_styles' );
add_filter( 'the_posts', 'twittertweet_showcss' ); 
add_filter( 'whitelist_options', 'twittertwee_whitelist_update' );
?>