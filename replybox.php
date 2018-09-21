<?php
/**
 * Plugin Name: ReplyBox
 * Plugin URI: https://getreplybox.com/
 * Description: Comments
 * Version: 0.1
 * Author: ReplyBox
 * Author URI: https://getreplybox.com
 */

if ( ! defined('ABSPATH') ) {
    exit;
}

final class ReplyBox
{
    /**
     * @var ReplyBox|null
     */
    private static $instance;

    /**
     * Get ReplyBox instance.
     *
     * @return ReplyBox
     */
    public static function instance()
    {
        if ( empty( static::$instance ) ) {
            static::$instance = new self();
            static::$instance->init();
        }

        return static::$instance;
    }

    /**
     * Init ReplyBox class.
     *
     * @return void
     */
    private function init() {
        add_action( 'rest_api_init', array( $this, 'register_api_endpoints' ) );
    }

    /**
     * Register our API endpoints.
     *
     * @return void
     */
    public function register_api_endpoints()
    {
        register_rest_route( 'replybox/v1', '/comments', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'comments_endpoint' ),
            'args'     => array(
            	'page'     => array(
            		'default'           => 1,
            		'validate_callback' => function( $param, $request, $key ) {
          				return is_numeric( $param );
        			},
            	),
            	'per_page' => array(
            		'default'           => 100,
            		'validate_callback' => function( $param, $request, $key ) {
          				return is_numeric( $param );
        			},
            	),
            ),
        ) );
    }

    /**
     * Prepare comments for response.
     *
     * @param WP_REST_Request $request
     * @return array
     */
    public function comments_endpoint( $request )
    {
    	$query    = new WP_Comment_Query;
		$comments = $query->query( array(
    		'orderby' => 'id',
    		'order'   => 'asc',
    		'number'  => $request['per_page'],
    		'offset'  => $request['per_page'] * ( absint( $request['page'] ) - 1 ),
    	) );

		$query = new WP_Comment_Query;
		$count = $query->query( array(
			'count' => true,
		) );
		$pages = ceil( $count / $request['per_page'] );

		return array(
			'total'    => (int) $count,
			'pages'    => (int) $pages,
			'comments' => $this->prepare_comments( $comments ),
		);
    }

    /**
     * Prepare comments for response.
     *
     * @param array $comments
     * @return array
     */
    private function prepare_comments($comments) {
    	foreach ( $comments as $key => $comment ) {
    		$comments[ $key ] = array(
    			'id'         => $comment->comment_ID,
    			'post'       => $comment->comment_post_ID,
    			'parent'     => $comment->comment_parent,
    			'user_name'  => $comment->comment_author,
    			'user_email' => $comment->comment_author_email,
    			'content'    => $comment->comment_content,
    			'date_gmt'   => $comment->comment_date_gmt, 
    		);
    	}

    	return $comments;
    }

    /**
     * Protected constructor to prevent creating a new instance of the
     * class via the `new` operator from outside of this class.
     */
    private function __construct()
    {
        //
    }

    /**
     * As this class is a singleton it should not be clone-ablel
     */
    private function __clone()
    {
        //
    }

    /**
     * As this class is a singleton it should not be able to be unserializedl
     */
    private function __wakeup()
    {
        //
    }
}

/**
 * Return the ReplyBox instance.
 *
 * @return ReplyBox
 */
function getreplybox()
{
    return ReplyBox::instance();
}

// Let's go!
getreplybox();
