<?php
/**
 * Facebook Feed - Posts List Page
 * 
 * Admin side til at vise importerede Facebook posts fra database
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_FB_Posts_List {
    
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
        add_action('centershop_render_facebook_posts', array($this, 'render_posts_page'));
        add_action('wp_ajax_centershop_fb_delete_post', array($this, 'ajax_delete_post'));
    }
    
    /**
     * Render posts list page
     */
    public function render_posts_page() {
        $db = CenterShop_FB_Database::get_instance();
        
        // Handle pagination
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 25;
        $offset = ($paged - 1) * $per_page;
        
        // Handle filters
        $shop_id = isset($_GET['shop_id']) && $_GET['shop_id'] !== '' ? intval($_GET['shop_id']) : null;
        
        // Get posts
        $posts = $db->get_all_posts($per_page, $offset, $shop_id);
        $total = $db->get_total_count($shop_id);
        $total_pages = ceil($total / $per_page);
        
        // Get all shops for filter
        $shops = get_posts(array(
            'post_type' => 'butiksside',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Facebook Opslag', 'centershop_txtdomain'); ?></h1>
            
            <!-- Filters -->
            <div class="tablenav top">
                <form method="get" action="">
                    <input type="hidden" name="page" value="centershop-facebook-posts">
                    
                    <select name="shop_id" id="shop-filter">
                        <option value=""><?php _e('Alle butikker', 'centershop_txtdomain'); ?></option>
                        <?php foreach ($shops as $shop): ?>
                            <option value="<?php echo esc_attr($shop->ID); ?>" <?php selected($shop_id, $shop->ID); ?>>
                                <?php echo esc_html($shop->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" class="button"><?php _e('Filtrer', 'centershop_txtdomain'); ?></button>
                    <?php if ($shop_id): ?>
                        <a href="<?php echo admin_url('admin.php?page=centershop-facebook-posts'); ?>" class="button">
                            <?php _e('Ryd filter', 'centershop_txtdomain'); ?>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Stats -->
            <div style="padding:15px;background:#fff;border:1px solid #ccc;margin:15px 0;">
                <strong><?php _e('Total:', 'centershop_txtdomain'); ?></strong> <?php echo $total; ?> <?php _e('opslag', 'centershop_txtdomain'); ?>
                <?php if ($shop_id): ?>
                    <span style="margin-left:20px;color:#666;">
                        (<?php _e('Filtreret', 'centershop_txtdomain'); ?>)
                    </span>
                <?php endif; ?>
            </div>
            
            <!-- Posts table -->
            <?php if (empty($posts)): ?>
                <div class="notice notice-info">
                    <p><?php _e('Ingen Facebook opslag fundet.', 'centershop_txtdomain'); ?></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:60px;"><?php _e('Billede', 'centershop_txtdomain'); ?></th>
                            <th><?php _e('Besked', 'centershop_txtdomain'); ?></th>
                            <th style="width:150px;"><?php _e('Butik', 'centershop_txtdomain'); ?></th>
                            <th style="width:120px;"><?php _e('Oprettet', 'centershop_txtdomain'); ?></th>
                            <th style="width:120px;"><?php _e('Importeret', 'centershop_txtdomain'); ?></th>
                            <th style="width:100px;"><?php _e('Handlinger', 'centershop_txtdomain'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post): ?>
                            <tr>
                                <td>
                                    <?php if ($post->full_picture): ?>
                                        <img src="<?php echo esc_url($post->full_picture); ?>" 
                                             style="width:50px;height:50px;object-fit:cover;border-radius:3px;">
                                    <?php else: ?>
                                        <span class="dashicons dashicons-format-image" style="font-size:40px;color:#ddd;"></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong>
                                        <?php if ($post->permalink_url): ?>
                                            <a href="<?php echo esc_url($post->permalink_url); ?>" target="_blank">
                                                <?php echo esc_html(wp_trim_words($post->message, 10)); ?>
                                            </a>
                                        <?php else: ?>
                                            <?php echo esc_html(wp_trim_words($post->message, 10)); ?>
                                        <?php endif; ?>
                                    </strong>
                                    <br>
                                    <small style="color:#666;">
                                        <?php echo esc_html(wp_trim_words($post->message, 20)); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($post->shop_id): ?>
                                        <?php 
                                        $shop = get_post($post->shop_id);
                                        if ($shop) {
                                            echo '<strong>' . esc_html($shop->post_title) . '</strong>';
                                        } else {
                                            echo '<em style="color:#999;">' . __('Slettet butik', 'centershop_txtdomain') . '</em>';
                                        }
                                        ?>
                                    <?php else: ?>
                                        <em style="color:#999;"><?php _e('Ingen kobling', 'centershop_txtdomain'); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($post->created_time): ?>
                                        <?php echo esc_html(date_i18n('j. M Y', strtotime($post->created_time))); ?><br>
                                        <small style="color:#666;"><?php echo esc_html(date_i18n('H:i', strtotime($post->created_time))); ?></small>
                                    <?php else: ?>
                                        <em style="color:#999;">-</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo esc_html(date_i18n('j. M Y', strtotime($post->imported_time))); ?><br>
                                    <small style="color:#666;"><?php echo esc_html(date_i18n('H:i', strtotime($post->imported_time))); ?></small>
                                </td>
                                <td>
                                    <?php if ($post->permalink_url): ?>
                                        <a href="<?php echo esc_url($post->permalink_url); ?>" 
                                           target="_blank" class="button button-small" 
                                           title="<?php _e('Se på Facebook', 'centershop_txtdomain'); ?>">
                                            <span class="dashicons dashicons-facebook" style="margin-top:3px;"></span>
                                        </a>
                                    <?php endif; ?>
                                    <button type="button" 
                                            class="button button-small centershop-delete-post" 
                                            data-post-id="<?php echo esc_attr($post->id); ?>"
                                            title="<?php _e('Slet', 'centershop_txtdomain'); ?>">
                                        <span class="dashicons dashicons-trash" style="margin-top:3px;"></span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <span class="displaying-num">
                                <?php printf(__('%s poster', 'centershop_txtdomain'), number_format_i18n($total)); ?>
                            </span>
                            <?php
                            $page_links = paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $paged
                            ));
                            
                            if ($page_links) {
                                echo '<span class="pagination-links">' . $page_links . '</span>';
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.centershop-delete-post').on('click', function() {
                if (!confirm('<?php _e('Er du sikker på at du vil slette dette opslag?', 'centershop_txtdomain'); ?>')) {
                    return;
                }
                
                var $btn = $(this);
                var postId = $btn.data('post-id');
                
                $btn.prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'centershop_fb_delete_post',
                    nonce: '<?php echo wp_create_nonce('centershop_fb_delete_post'); ?>',
                    post_id: postId
                }, function(response) {
                    if (response.success) {
                        $btn.closest('tr').fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data);
                        $btn.prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Delete post
     */
    public function ajax_delete_post() {
        check_ajax_referer('centershop_fb_delete_post', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Ingen adgang', 'centershop_txtdomain'));
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(__('Ugyldigt post ID', 'centershop_txtdomain'));
        }
        
        $db = CenterShop_FB_Database::get_instance();
        $result = $db->delete_post($post_id);
        
        if ($result) {
            wp_send_json_success(__('Opslag slettet', 'centershop_txtdomain'));
        } else {
            wp_send_json_error(__('Kunne ikke slette opslag', 'centershop_txtdomain'));
        }
    }
}
