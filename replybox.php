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
        add_filter( 'comments_template', array( $this, 'comments_template' ) );
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
            'callback' => array( $this, 'get_comments_endpoint' ),
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

        register_rest_route( 'replybox/v1', '/comments', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'post_comments_endpoint' ),
        ) );
    }

    /**
     * GET comments API endpoint.
     *
     * @param WP_REST_Request $request
     * @return array
     */
    public function get_comments_endpoint( $request )
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
     * POST comments API endpoint.
     *
     * @param WP_REST_Request $request
     * @return array
     */
    public function post_comments_endpoint( $request ) {
    	$user = get_user_by( 'email', $request['email'] );

    	$id = wp_new_comment( array(
    		'comment_post_ID'      => (int) $request['post'],
    		'user_id'              => $user ? $user->ID : 0,
    		'comment_author'       => $user ? $user->display_name : $request['name'],
    		'comment_author_email' => $request['email'],
    		'comment_author_url'   => '',
    		'comment_content'      => $request['content'],
    		'comment_parent'       => (int) $request['parent'],
    		'comment_agent'        => 'ReplyBox',
    		'comment_type'         => '',
    	), true );

    	return $id;
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
     * Replace the default WordPress comments.
     *
     * @return string
     */
    public function comments_template() {
    	global $post;

    	wp_enqueue_script( 'replybox-js', 'https://getreplybox.test/js/embed.js', array(), null, true );
    	wp_localize_script( 'replybox-js', 'replybox', array(
    		'site'       => 'Won6bm0qx7',
    		'identifier' => $post->ID,
 		) );
    	
    	return plugin_dir_path( __FILE__ ) . 'views/comments.php';
    }

    /**
     * Protected constructor to prevent creating a new instance of the
     * class via the `new` operator from outside of this class.
     */
    private function __construct() {}

    /**
     * As this class is a singleton it should not be clone-ablel
     */
    private function __clone() {}

    /**
     * As this class is a singleton it should not be able to be unserializedl
     */
    private function __wakeup() {}
}

/**
 * Return the ReplyBox instance.
 *
 * @return ReplyBox
 */
function getreplybox() {
    return ReplyBox::instance();
}

// Let's go!
getreplybox();
