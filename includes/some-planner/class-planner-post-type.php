<?php
/**
 * SoMe Planner - Custom Post Type
 * 
 * Registrerer centershop_post CPT til planlagte SoMe opslag
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_Planner_Post_Type {
    
    /**
     * Post type name
     */
    const POST_TYPE = 'centershop_post';
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomies'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_' . self::POST_TYPE, array($this, 'save_meta'), 10, 2);
        
        // REST API support
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    /**
     * Register post type
     */
    public function register_post_type() {
        $labels = array(
            'name'               => __('SoMe Opslag', 'centershop_txtdomain'),
            'singular_name'      => __('SoMe Opslag', 'centershop_txtdomain'),
            'menu_name'          => __('SoMe Opslag', 'centershop_txtdomain'),
            'add_new'            => __('Tilføj nyt', 'centershop_txtdomain'),
            'add_new_item'       => __('Tilføj nyt opslag', 'centershop_txtdomain'),
            'edit_item'          => __('Rediger opslag', 'centershop_txtdomain'),
            'new_item'           => __('Nyt opslag', 'centershop_txtdomain'),
            'view_item'          => __('Se opslag', 'centershop_txtdomain'),
            'search_items'       => __('Søg i opslag', 'centershop_txtdomain'),
            'not_found'          => __('Ingen opslag fundet', 'centershop_txtdomain'),
            'not_found_in_trash' => __('Ingen opslag i papirkurven', 'centershop_txtdomain'),
        );
        
        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false, // Vi viser i CenterShop menu
            'query_var'          => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => array('title', 'editor', 'thumbnail'),
            'show_in_rest'       => true,
            'rest_base'          => 'centershop-posts',
        );
        
        register_post_type(self::POST_TYPE, $args);
    }
    
    /**
     * Register taxonomies
     */
    public function register_taxonomies() {
        // Post format taxonomy
        register_taxonomy('centershop_post_format', self::POST_TYPE, array(
            'labels' => array(
                'name' => __('Opslagsformat', 'centershop_txtdomain'),
                'singular_name' => __('Format', 'centershop_txtdomain'),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => true,
            'hierarchical' => true,
        ));
        
        // Insert default formats
        if (!term_exists('butik_i_fokus', 'centershop_post_format')) {
            wp_insert_term(__('Butik i fokus', 'centershop_txtdomain'), 'centershop_post_format', array('slug' => 'butik_i_fokus'));
            wp_insert_term(__('Engagement', 'centershop_txtdomain'), 'centershop_post_format', array('slug' => 'engagement'));
            wp_insert_term(__('Konkurrence', 'centershop_txtdomain'), 'centershop_post_format', array('slug' => 'konkurrence'));
            wp_insert_term(__('Event', 'centershop_txtdomain'), 'centershop_post_format', array('slug' => 'event'));
            wp_insert_term(__('Information', 'centershop_txtdomain'), 'centershop_post_format', array('slug' => 'information'));
        }
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'centershop_post_details',
            __('Opslagsdetaljer', 'centershop_txtdomain'),
            array($this, 'render_details_meta_box'),
            self::POST_TYPE,
            'side',
            'high'
        );
        
        add_meta_box(
            'centershop_post_media',
            __('Medier', 'centershop_txtdomain'),
            array($this, 'render_media_meta_box'),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }
    
    /**
     * Render details meta box
     */
    public function render_details_meta_box($post) {
        wp_nonce_field('centershop_post_details', 'centershop_post_nonce');
        
        $scheduled_date = get_post_meta($post->ID, '_centershop_scheduled_date', true);
        $post_type = get_post_meta($post->ID, '_centershop_post_type', true) ?: 'post';
        $status = get_post_meta($post->ID, '_centershop_status', true) ?: 'draft';
        $shop_id = get_post_meta($post->ID, '_centershop_shop_id', true);
        
        // Get shops
        $shops = get_posts(array(
            'post_type' => 'butiksside',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        ?>
        <p>
            <label for="centershop_scheduled_date"><strong><?php _e('Planlagt dato', 'centershop_txtdomain'); ?></strong></label><br>
            <input type="date" id="centershop_scheduled_date" name="centershop_scheduled_date" 
                   value="<?php echo esc_attr($scheduled_date); ?>" class="widefat">
        </p>
        
        <p>
            <label for="centershop_post_type"><strong><?php _e('Type', 'centershop_txtdomain'); ?></strong></label><br>
            <select id="centershop_post_type" name="centershop_post_type" class="widefat">
                <option value="post" <?php selected($post_type, 'post'); ?>><?php _e('Opslag', 'centershop_txtdomain'); ?></option>
                <option value="reel" <?php selected($post_type, 'reel'); ?>><?php _e('Reel', 'centershop_txtdomain'); ?></option>
                <option value="story" <?php selected($post_type, 'story'); ?>><?php _e('Story', 'centershop_txtdomain'); ?></option>
            </select>
        </p>
        
        <p>
            <label for="centershop_status"><strong><?php _e('Status', 'centershop_txtdomain'); ?></strong></label><br>
            <select id="centershop_status" name="centershop_status" class="widefat">
                <option value="draft" <?php selected($status, 'draft'); ?>><?php _e('Kladde', 'centershop_txtdomain'); ?></option>
                <option value="awaiting_content" <?php selected($status, 'awaiting_content'); ?>><?php _e('Afventer indhold', 'centershop_txtdomain'); ?></option>
                <option value="ready" <?php selected($status, 'ready'); ?>><?php _e('Klar', 'centershop_txtdomain'); ?></option>
                <option value="published" <?php selected($status, 'published'); ?>><?php _e('Publiceret', 'centershop_txtdomain'); ?></option>
            </select>
        </p>
        
        <p>
            <label for="centershop_shop_id"><strong><?php _e('Butik', 'centershop_txtdomain'); ?></strong></label><br>
            <select id="centershop_shop_id" name="centershop_shop_id" class="widefat">
                <option value=""><?php _e('Ingen / Center generelt', 'centershop_txtdomain'); ?></option>
                <?php foreach ($shops as $shop): ?>
                    <option value="<?php echo $shop->ID; ?>" <?php selected($shop_id, $shop->ID); ?>>
                        <?php echo esc_html($shop->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }
    
    /**
     * Render media meta box
     */
    public function render_media_meta_box($post) {
        $media_ids = get_post_meta($post->ID, '_centershop_media_ids', true);
        $media_ids = is_array($media_ids) ? $media_ids : array();
        
        ?>
        <div class="centershop-media-gallery">
            <div class="centershop-media-items" id="centershop-media-items">
                <?php foreach ($media_ids as $media_id): ?>
                    <?php $this->render_media_item($media_id); ?>
                <?php endforeach; ?>
            </div>
            
            <p>
                <button type="button" class="button centershop-add-media" id="centershop-add-media">
                    <?php _e('Tilføj medie', 'centershop_txtdomain'); ?>
                </button>
            </p>
            
            <input type="hidden" name="centershop_media_ids" id="centershop-media-ids" 
                   value="<?php echo esc_attr(implode(',', $media_ids)); ?>">
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var mediaFrame;
            
            $('#centershop-add-media').on('click', function(e) {
                e.preventDefault();
                
                if (mediaFrame) {
                    mediaFrame.open();
                    return;
                }
                
                mediaFrame = wp.media({
                    title: '<?php _e('Vælg medier', 'centershop_txtdomain'); ?>',
                    button: { text: '<?php _e('Tilføj', 'centershop_txtdomain'); ?>' },
                    multiple: true
                });
                
                mediaFrame.on('select', function() {
                    var attachments = mediaFrame.state().get('selection').toJSON();
                    var ids = $('#centershop-media-ids').val();
                    ids = ids ? ids.split(',') : [];
                    
                    attachments.forEach(function(attachment) {
                        if (ids.indexOf(attachment.id.toString()) === -1) {
                            ids.push(attachment.id);
                            var html = '<div class="centershop-media-item" data-id="' + attachment.id + '">';
                            if (attachment.type === 'image') {
                                html += '<img src="' + (attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url) + '">';
                            } else {
                                html += '<video src="' + attachment.url + '"></video>';
                            }
                            html += '<button type="button" class="centershop-remove-media">&times;</button>';
                            html += '</div>';
                            $('#centershop-media-items').append(html);
                        }
                    });
                    
                    $('#centershop-media-ids').val(ids.join(','));
                });
                
                mediaFrame.open();
            });
            
            $(document).on('click', '.centershop-remove-media', function() {
                var $item = $(this).closest('.centershop-media-item');
                var removeId = $item.data('id').toString();
                var ids = $('#centershop-media-ids').val().split(',');
                ids = ids.filter(function(id) { return id !== removeId; });
                $('#centershop-media-ids').val(ids.join(','));
                $item.remove();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render single media item
     */
    private function render_media_item($media_id) {
        $attachment = get_post($media_id);
        if (!$attachment) return;
        
        $type = wp_attachment_is('video', $media_id) ? 'video' : 'image';
        $url = $type === 'image' 
            ? wp_get_attachment_image_url($media_id, 'thumbnail') 
            : wp_get_attachment_url($media_id);
        
        ?>
        <div class="centershop-media-item" data-id="<?php echo $media_id; ?>">
            <?php if ($type === 'image'): ?>
                <img src="<?php echo esc_url($url); ?>" alt="">
            <?php else: ?>
                <video src="<?php echo esc_url($url); ?>"></video>
            <?php endif; ?>
            <button type="button" class="centershop-remove-media">&times;</button>
        </div>
        <?php
    }
    
    /**
     * Save post meta
     */
    public function save_meta($post_id, $post) {
        if (!isset($_POST['centershop_post_nonce']) || 
            !wp_verify_nonce($_POST['centershop_post_nonce'], 'centershop_post_details')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save scheduled date
        if (isset($_POST['centershop_scheduled_date'])) {
            update_post_meta($post_id, '_centershop_scheduled_date', sanitize_text_field($_POST['centershop_scheduled_date']));
        }
        
        // Save post type
        if (isset($_POST['centershop_post_type'])) {
            update_post_meta($post_id, '_centershop_post_type', sanitize_text_field($_POST['centershop_post_type']));
        }
        
        // Save status
        if (isset($_POST['centershop_status'])) {
            update_post_meta($post_id, '_centershop_status', sanitize_text_field($_POST['centershop_status']));
        }
        
        // Save shop ID
        if (isset($_POST['centershop_shop_id'])) {
            update_post_meta($post_id, '_centershop_shop_id', intval($_POST['centershop_shop_id']));
        }
        
        // Save media IDs
        if (isset($_POST['centershop_media_ids'])) {
            $media_ids = array_filter(array_map('intval', explode(',', $_POST['centershop_media_ids'])));
            update_post_meta($post_id, '_centershop_media_ids', $media_ids);
        }
    }
    
    /**
     * Register REST routes
     */
    public function register_rest_routes() {
        register_rest_route('centershop/v1', '/posts', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_posts'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
        
        register_rest_route('centershop/v1', '/posts/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_post'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
        
        register_rest_route('centershop/v1', '/posts', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_create_post'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
        
        register_rest_route('centershop/v1', '/posts/(?P<id>\d+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'rest_update_post'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
    }
    
    /**
     * REST: Get posts
     */
    public function rest_get_posts($request) {
        $args = array(
            'post_type' => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'any'
        );
        
        // Filter by date range
        if ($request->get_param('start_date') && $request->get_param('end_date')) {
            $args['meta_query'] = array(
                array(
                    'key' => '_centershop_scheduled_date',
                    'value' => array($request->get_param('start_date'), $request->get_param('end_date')),
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                )
            );
        }
        
        // Filter by shop for shop_manager
        if (CenterShop_Shop_Roles::is_shop_manager()) {
            $shop_id = CenterShop_Shop_Roles::get_user_shop_id();
            $args['meta_query'][] = array(
                'key' => '_centershop_shop_id',
                'value' => $shop_id,
                'compare' => '='
            );
        }
        
        $posts = get_posts($args);
        $result = array();
        
        foreach ($posts as $post) {
            $result[] = $this->format_post_for_rest($post);
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * REST: Get single post
     */
    public function rest_get_post($request) {
        $post = get_post($request['id']);
        
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return new WP_Error('not_found', __('Opslag ikke fundet', 'centershop_txtdomain'), array('status' => 404));
        }
        
        return rest_ensure_response($this->format_post_for_rest($post));
    }
    
    /**
     * REST: Create post
     */
    public function rest_create_post($request) {
        $post_data = array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => sanitize_text_field($request->get_param('title')),
            'post_content' => wp_kses_post($request->get_param('caption'))
        );
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // Save meta
        $this->save_rest_meta($post_id, $request);
        
        return rest_ensure_response($this->format_post_for_rest(get_post($post_id)));
    }
    
    /**
     * REST: Update post
     */
    public function rest_update_post($request) {
        $post = get_post($request['id']);
        
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return new WP_Error('not_found', __('Opslag ikke fundet', 'centershop_txtdomain'), array('status' => 404));
        }
        
        $post_data = array(
            'ID' => $post->ID
        );
        
        if ($request->get_param('title')) {
            $post_data['post_title'] = sanitize_text_field($request->get_param('title'));
        }
        
        if ($request->get_param('caption')) {
            $post_data['post_content'] = wp_kses_post($request->get_param('caption'));
        }
        
        wp_update_post($post_data);
        
        // Save meta
        $this->save_rest_meta($post->ID, $request);
        
        return rest_ensure_response($this->format_post_for_rest(get_post($post->ID)));
    }
    
    /**
     * Save meta from REST request
     */
    private function save_rest_meta($post_id, $request) {
        if ($request->get_param('scheduled_date')) {
            update_post_meta($post_id, '_centershop_scheduled_date', sanitize_text_field($request->get_param('scheduled_date')));
        }
        
        if ($request->get_param('post_type')) {
            update_post_meta($post_id, '_centershop_post_type', sanitize_text_field($request->get_param('post_type')));
        }
        
        if ($request->get_param('status')) {
            update_post_meta($post_id, '_centershop_status', sanitize_text_field($request->get_param('status')));
        }
        
        if ($request->get_param('shop_id') !== null) {
            update_post_meta($post_id, '_centershop_shop_id', intval($request->get_param('shop_id')));
        }
        
        if ($request->get_param('media_ids')) {
            $media_ids = array_filter(array_map('intval', (array) $request->get_param('media_ids')));
            update_post_meta($post_id, '_centershop_media_ids', $media_ids);
        }
    }
    
    /**
     * Format post for REST response
     */
    private function format_post_for_rest($post) {
        $shop_id = get_post_meta($post->ID, '_centershop_shop_id', true);
        $shop = $shop_id ? get_post($shop_id) : null;
        
        $media_ids = get_post_meta($post->ID, '_centershop_media_ids', true);
        $media_ids = is_array($media_ids) ? $media_ids : array();
        
        $media = array();
        foreach ($media_ids as $media_id) {
            $type = wp_attachment_is('video', $media_id) ? 'video' : 'image';
            $media[] = array(
                'id' => $media_id,
                'type' => $type,
                'url' => wp_get_attachment_url($media_id),
                'thumbnail' => $type === 'image' ? wp_get_attachment_image_url($media_id, 'medium') : null
            );
        }
        
        return array(
            'id' => $post->ID,
            'title' => $post->post_title,
            'caption' => $post->post_content,
            'scheduled_date' => get_post_meta($post->ID, '_centershop_scheduled_date', true),
            'post_type' => get_post_meta($post->ID, '_centershop_post_type', true) ?: 'post',
            'status' => get_post_meta($post->ID, '_centershop_status', true) ?: 'draft',
            'shop_id' => $shop_id,
            'shop_name' => $shop ? $shop->post_title : null,
            'media' => $media,
            'created' => $post->post_date,
            'modified' => $post->post_modified
        );
    }
}
