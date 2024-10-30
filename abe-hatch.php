<?php
/**
 * Plugin name: Hatch
 * Author: A Big Egg
 * Version: 1.1.0
 * Description: Provides helper functions for working with Timber and ACF. Requires Timber (timber-library) and works well with Advanced Custom Fields Pro and Gravity Forms.
 */

require_once('inc/image.php');


class Hatch
{
    public static $context = [];
    
    public static $post_transformers = [];
    public static $taxonomy_transformers = [];
    public static $main_transformer = false;
    
    public function __construct()
    {
        // ensure that post objects from ACF are passed through our transformation function
        add_filter('acf/format_value/type=post_object', [ $this, 'construct_post' ], 50);
        add_filter('acf/format_value/type=relationship', [ $this, 'construct_posts' ], 50);
        add_filter('acf/format_value/type=image', [ $this, 'construct_image' ], 50);
        
        // check environment is suitable
        add_filter('plugins_loaded', [ $this, 'check_prerequisites']);
    }

    public function check_prerequisites()
    {
        if (! class_exists('Timber') && ! defined('WP_CLI')) {
            throw new Exception('The Timber plugin must be installed for Hatch to work.');
        }
    }

    /**
     * Add an ACF field to the Timber array, passing an optional preparation function to prepare the value
     *
     * Here's an example - we can pass in the names of ACF fields we want added to the context:
     *
     * ```php
     * Hatch::add_acf_context( [
     *    'title',
     *    'favourite_colour'
     * ]);
     * ```
     *
     * Another example, but this time the 'title' field will be passed through our a preparation function to transform it before it's added to the context:
     *
     * ```php
     * Hatch::add_acf_context( [
     *    'title' => function( $value ) {
     *        return strtoupper( $value );
     *     },
     *     'favourite_colour'
     * ]);
     * ```
     *
     * (In any case, you need to call `Hatch::render` to render a template with your context set)
     *
     * @param  mixed $keys A string for a single ACF field name, or an array of ACF field names
     * @return void
     */
    public static function add_acf_context($key, $func = false)
    {
        if (is_string($key)) {
            $val = get_field($key);

            if (is_callable($func)) {
                $val = call_user_func($func, $val);
            }

            static::$context[$key] = $val;
            return;
        }

        if (is_array($key)) {
            $keys = $key;
        }

        foreach ($keys as $key => $value) {
            if (is_integer($key) && is_string($value)) {
                // we've been passed the name of an ACF field - add it to the context
                $name = $value; // the ACF name is in the value of the array
                static::$context[$name] = get_field($name);
                continue;
            }

            if (is_string($key)) {
                // we've been passed the name of an ACF field AND a preparation function
                $func = $value;
                static::$context[$key] = call_user_func($func, get_field($key));
            }
        }
    }
    
    /**
     * Add a key/value to the context without touching ACF
     *
     * @param  mixed $key The key to add to the context array OR an array of keys
     * @param  mixed $value The function which, when called, will return the context to add
     * @return void
     */
    public static function add_context($key, $callback = false)
    {
        if (is_array($key)) {
            foreach ($key as $key => $value) {
                self::add_context($key, $value);
            }
            return;
        }

        if (is_callable($callback)) {
            self::$context[$key] = call_user_func_array($callback, []);
        }
    }
    
    /**
     * Add a Gravity Form to the context
     *
     * @param  mixed $key The key to add to the context array
     * @param  mixed $callback A function which returns the form ID that should be used
     * @param  mixed $args The args
     * @return void
     */
    public static function add_gform_context($key, $callback, $args = [])
    {
        $args = wp_parse_args($args, [
            'display_title'       => true,
            'display_description' => true,
            'display_inactive'    => false,
            'field_values'        => null,
            'ajax'                => false,
            'tabindex'            => 0,
        ]);
        
        if (! is_callable($callback)) {
            throw new Exception('Callback must be of type callable');
            return;
        }

        if (! function_exists('gravity_form')) {
            throw new Exception('Gravity Forms is not installed/active, or gravity_form function unavailable');
            return;
        }

        $form_id = call_user_func_array($callback, []);

        $rendered_form = gravity_form($form_id, $args['display_title'], $args['display_description'], $args['display_inactive'], $args['field_values'], $args['ajax'], $args['tabindex'], false);
    
        self::add_context($key, $rendered_form);
    }
    
    /**
     * Render a template with Timber using our prepared context
     *
     * @param  mixed $view The Timber template to render
     * @param  mixed $additional_context An optional array of additional context to pass through - this will override the context currently set
     * @return void
     */
    public static function render($view, $additional_context = [])
    {
        $context = self::get_final_context($additional_context);

        Timber::render($view, $context);

        static::$context = [];
    }
    
    /**
     * get_final_context
     *
     * @param  mixed $context
     * @return void
     */
    private static function get_final_context($context = [])
    {
        $base_context = Timber::get_context();
        $base_context['post'] = self::get_post();

        if (self::has_transformer_for_main()) {
            $base_context = self::transform_main($base_context);
        }
        
        return array_merge($base_context, static::$context, $context);
    }
    
    /**
     * Get posts
     *
     * @param  mixed $args
     * @return array
     */
    public static function get_posts($args)
    {
        return self::construct_posts(Timber::get_posts($args));
    }

    /**
     * get_taxonomy_filter_options
     *
     * @param  mixed $args
     * @return void
     */
    public static function get_taxonomy_filter_options($args)
    {
        $args = wp_parse_args($args, [
            'taxonomy'   => false,
            'label'      => 'Select an option',
            'filter_key' => 'filter_' . $args['taxonomy'],
            'page'       => false
        ]);
        
        $filter_key   = $args['filter_key'];
        $label        = $args['label'];
        $listing_page = $args['listing_page'];
        $active_val   = intval($_GET[$filter_key]);
        $output = [];

        $terms = self::get_terms([
            'taxonomy' => $args['taxonomy']
        ]);

        array_unshift($output, [
            'title'  => $label,
            'active' => empty($active_val),
            'link'   => add_query_arg($filter_key, 0, $listing_page)
        ]);

        foreach ($terms as &$term) {
            $output[] = [
                'title'  => $term->name,
                'value'  => $term->id,
                'active' => $active_val == $term->id,
                'link'   => add_query_arg($filter_key, $term->id, $listing_page)
            ];
        }

        return $output;
    }

    /**
     * Get terms
     *
     * @param  mixed $args
     * @return array
     */
    public static function get_terms($args)
    {
        return self::construct_terms(Timber::get_terms($args));
    }
    
    /**
     * get_post
     *
     * @return object
     */
    public static function get_post($post = false)
    {
        return self::construct_post($post);
    }
    
    /**
     * construct_post
     *
     * @param  mixed $post_id
     * @return mixed
     */
    public static function construct_post($post_id = 0)
    {
        if (! empty($post_id)) {

            // we have a TimberPost or WP_Post
            if (is_object($post_id)) {
                $post_id = $post_id->ID;
            }
        }

        $post = new Timber\Post($post_id);

        $thumb_id = get_post_thumbnail_id($post->ID);

        if ($thumb_id) {
            $post->thumbnail = new HatchImage($thumb_id);
        }

        if (self::has_transformer_for_post_type($post->post_type)) {
            $post = self::transform_post($post);
        }

        return $post;
    }
    
        
    /**
     * construct term
     *
     * @param  mixed $term_id
     * @return void
     */
    public static function construct_term($term_id = 0)
    {
        if (! $term_id) {
            return $term_id;
        }

        $term = new Timber\Term($term_id);

        if (self::has_transformer_for_taxonomy($term->taxonomy)) {
            $term = self::transform_term($term);
        }
    
        return $term;
    }
    
    
    /**
     * construct_image
     *
     * @param  mixed $image
     * @return void
     */
    public static function construct_image($image)
    {
        if (is_array($image)) {
            $image = $image['id'];
        }
        
        return new HatchImage($image);
    }
    
    /**
     * has_transformer_for_post_type
     *
     * @param  mixed $post_type
     * @return void
     */
    public static function has_transformer_for_post_type($post_type)
    {
        return isset(self::$post_transformers[$post_type]);
    }
    
    /**
     * has_transformer_for_term_type
     *
     * @param  mixed $taxonomy
     * @return void
     */
    public static function has_transformer_for_taxonomy($taxonomy)
    {
        return isset(self::$taxonomy_transformers[$taxonomy]);
    }
    
    /**
     * has_transformer_for_main
     *
     * @return void
     */
    public static function has_transformer_for_main()
    {
        return !! self::$main_transformer;
    }
    

    /**
     * transform_post
     *
     * @param  mixed $post
     * @return void
     */
    public static function transform_post($post)
    {
        $transformer = self::$post_transformers[$post->post_type];

        if (! self::has_transformer_for_post_type($post->post_type)) {
            return $post;
        }

        return call_user_func($transformer, $post);
    }
    
    /**
     * transform_term
     *
     * @param  mixed $post
     * @return void
     */
    public static function transform_term($term)
    {
        $transformer = self::$taxonomy_transformers[$term->taxonomy];

        if (! self::has_transformer_for_taxonomy($term->taxonomy)) {
            return $term;
        }

        return call_user_func($transformer, $term);
    }
    
    /**
     * transform_main
     *
     * @param  mixed $context
     * @return void
     */
    public static function transform_main($context)
    {
        if (! self::has_transformer_for_main()) {
            return $context;
        }

        $transformer = self::$main_transformer;

        return call_user_func($transformer, $context);
    }

    /**
     * construct_post
     *
     * @param  mixed $post_id
     * @return void
     */
    public static function construct_posts($array_of_posts)
    {
        if (! is_array($array_of_posts)) {
            return [];
        }

        return array_map('self::construct_post', $array_of_posts);
    }

    
    /**
     * construct_terms
     *
     * @param  mixed $post_id
     * @return array
     */
    public static function construct_terms($array_of_terms)
    {
        if (! is_array($array_of_terms)) {
            return [];
        }

        return array_map('self::construct_term', $array_of_terms);
    }

    
    
    /**
     * Register a transformer for a post. A transformer lets you manipulate the Timber post objects
     * that are returned by Hatch::get_posts or Hatch::get_post
     *
     * @param  mixed $post_type
     * @param  mixed $callback
     * @return void
     */
    public static function register_transformer_for_post_type($post_type, $callback)
    {
        self::$post_transformers[$post_type] = $callback;
    }
    
    /**
     * Register a transformer for a taxonomy
     *
     * @param  mixed $taxonomy
     * @param  mixed $callback
     * @return void
     */
    public static function register_transformer_for_taxonomy($taxonomy, $callback)
    {
        self::$taxonomy_transformers[$taxonomy] = $callback;
    }
    
    /**
     * Register a transformer for the main context
     *
     * @param  mixed $callback
     * @return void
     */
    public static function register_transformer_for_main($callback)
    {
        self::$main_transformer = $callback;
    }

    /**
     * Debug function - call this to dump the current context to inspect it
     *
     * @return void
     */
    public static function dd()
    {
        $context = self::get_final_context();

        if (function_exists('dd')) {
            dd($context);
        } else {
            var_dump($context);
            die();
        }
    }
}

$h_instance = new Hatch();
