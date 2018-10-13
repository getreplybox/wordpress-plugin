<?php
/**
 * Plugin Name: ReplyBox
 * Plugin URI: https://getreplybox.com/
 * Description: Comments
 * Version: 0.1
 * Author: ReplyBox
 * Author URI: https://getreplybox.com
 */

if (!defined('ABSPATH')) {
    exit;
}

final class ReplyBox
{
    /**
     * @var ReplyBox|null
     */
    private static $instance;

    /**
     * @var array
     */
    private $options = [];

    /**
     * Get ReplyBox instance.
     *
     * @return ReplyBox
     */
    public static function instance()
    {
        if (empty(static::$instance)) {
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
    private function init()
    {
        $this->options = $this->get_options();

        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_post_replybox_settings', [$this, 'save_form']);
        add_action('rest_api_init', [$this, 'register_api_endpoints']);

        if ($this->replace_comments()) {
            add_filter('comments_template', [$this, 'comments_template'], 100);
        }

        register_activation_hook(__FILE__, [$this, 'activate']);
    }

    /**
     * Get all options.
     *
     * @return array
     */
    private function get_options()
    {
        return get_option('replybox', []);
    }

    /**
     * Save the options.
     *
     * @return void
     */
    private function save_options()
    {
        update_option('replybox', $this->options);
    }

    /**
     * Get a single option.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private function get_option($key, $default = '')
    {
        if (isset($this->options[$key])) {
            return $this->options[$key];
        }

        return $default;
    }

    /**
     * Update a single option.
     *
     * @param string $key
     * @param string $value
     * @return $this
     */
    private function update_option($key, $value)
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * Should we overwrite the comments template?
     *
     * @return bool
     */
    private function replace_comments()
    {
        $return = !empty($this->get_option('site_id'));

        return apply_filters('replybox_replace_comments', $return);
    }

    /**
     * Generate a new secure token.
     *
     * @return string
     */
    private function generate_token()
    {
        $token = md5(uniqid(rand(), true));

        $this->update_option('secure_token', $token)->save_options();
    }

    /**
     * Register the admin page.
     */
    public function add_admin_menu()
    {
        add_submenu_page('options-general.php', __('ReplyBox', 'replybox'), __('ReplyBox', 'replybox'), 'manage_options', 'replybox', [$this, 'show_admin_page']);
    }

    /**
     * Render the admin page.
     */
    public function show_admin_page()
    {
        require_once plugin_dir_path(__FILE__) . 'views/admin-page.php';
    }

    /**
     * Save admin settings.
     *
     * @return void
     */
    public function save_form()
    {
        check_admin_referer('replybox_settings');

        $site_id = sanitize_text_field($_POST['site_id']);

        $this->update_option('site_id', $site_id)->save_options();

        if (!isset($_POST['_wp_http_referer'])) {
            $_POST['_wp_http_referer'] = wp_login_url();
        }

        $url = sanitize_text_field(wp_unslash($_POST['_wp_http_referer']));

        wp_safe_redirect(urldecode($url));
        exit;
    }

    /**
     * Register our API endpoints.
     *
     * @return void
     */
    public function register_api_endpoints()
    {
        register_rest_route('replybox/v1', '/comments', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_comments_endpoint'],
            'args'     => [
                'page'     => [
                    'default'           => 1,
                    'validate_callback' => function ($param, $request, $key) {
                        return is_numeric($param);
                    },
                ],
                'per_page' => [
                    'default'           => 100,
                    'validate_callback' => function ($param, $request, $key) {
                        return is_numeric($param);
                    },
                ],
                'token' => [
                    'required' => true,
                    'type'     => 'string'
                ],
            ],
        ]);

        register_rest_route('replybox/v1', '/comments', [
            'methods'  => 'POST',
            'callback' => [$this, 'post_comments_endpoint'],
            'args'     => [
                'token' => [
                    'required' => true,
                    'type'     => 'string'
                ],
            ],
        ]);
    }

    /**
     * GET comments API endpoint.
     *
     * @param WP_REST_Request $request
     * @return array
     */
    public function get_comments_endpoint($request)
    {
        if ($this->get_option('secure_token') !== $request['token']) {
            return new WP_Error('token_incorrect', __('Sorry, incorrect secure token.', 'replybox'), ['status' => 403]);
        }

        $query    = new WP_Comment_Query;
        $comments = $query->query([
            'orderby' => 'id',
            'order'   => 'asc',
            'number'  => $request['per_page'],
            'offset'  => $request['per_page'] * (absint($request['page']) - 1),
        ]);

        $query = new WP_Comment_Query;
        $count = $query->query([
            'count' => true,
        ]);
        $pages = ceil($count / $request['per_page']);

        return [
            'total'    => (int) $count,
            'pages'    => (int) $pages,
            'comments' => $this->prepare_comments($comments),
        ];
    }

    /**
     * POST comments API endpoint.
     *
     * @param WP_REST_Request $request
     * @return array
     */
    public function post_comments_endpoint($request)
    {
        if ($this->get_option('secure_token') !== $request['token']) {
            return new WP_Error('token_incorrect', __('Sorry, incorrect secure token.', 'replybox'), ['status' => 403]);
        }

        $user = get_user_by('email', $request['email']);

        $id = wp_new_comment([
            'comment_post_ID'      => (int) $request['post'],
            'user_id'              => $user ? $user->ID : 0,
            'comment_author'       => $user ? $user->display_name : $request['name'],
            'comment_author_email' => $request['email'],
            'comment_author_url'   => '',
            'comment_content'      => $request['content'],
            'comment_parent'       => (int) $request['parent'],
            'comment_agent'        => 'ReplyBox',
            'comment_type'         => '',
        ], true);

        return $id;
    }

    /**
     * Prepare comments for response.
     *
     * @param array $comments
     * @return array
     */
    private function prepare_comments($comments)
    {
        foreach ($comments as $key => $comment) {
            $comments[$key] = [
                'id'         => $comment->comment_ID,
                'post'       => $comment->comment_post_ID,
                'parent'     => $comment->comment_parent,
                'user_name'  => $comment->comment_author,
                'user_email' => $comment->comment_author_email,
                'content'    => $comment->comment_content,
                'approved'   => $comment->comment_approved,
                'date_gmt'   => $comment->comment_date_gmt,
            ];
        }

        return $comments;
    }

    /**
     * Get the URL of the embed script.
     *
     * @return string
     */
    private function get_embed_url()
    {
        return apply_filters('replybox_embed_url', 'https://cdn.getreplybox.com/js/embed.js');
    }

    /**
     * Replace the default WordPress comments.
     *
     * @return string
     */
    public function comments_template()
    {
        global $post;

        wp_enqueue_script('replybox-js', $this->get_embed_url(), [], null, true);
        wp_localize_script('replybox-js', 'replybox', [
            'site'       => $this->get_option('site_id'),
            'identifier' => $post->ID,
        ]);

        return plugin_dir_path(__FILE__) . 'views/comments.php';
    }

    /**
     * Plugin activated.
     *
     * @return void
     */
    public function activate()
    {
        if (empty($this->get_option('secure_token'))) {
            $this->generate_token();
        }
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
