<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('Vælg din Facebook side', 'centershop_txtdomain'); ?> - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .centershop-tenant-page-selection {
            background: #fff;
            max-width: 600px;
            width: 100%;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .centershop-tenant-page-selection h1 {
            color: #333;
            margin-top: 0;
            margin-bottom: 20px;
        }
        .centershop-tenant-page-selection p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .page-list {
            border: 1px solid #ddd;
            border-radius: 8px;
            margin: 20px 0;
        }
        .page-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .page-item:last-child {
            border-bottom: none;
        }
        .page-item:hover {
            background: #f5f5f5;
        }
        .page-item input[type="radio"] {
            margin-right: 10px;
        }
        .page-name {
            font-weight: 600;
            color: #333;
        }
        .page-id {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        .btn-primary {
            background: #1877f2;
            color: #fff;
            border: none;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s ease;
        }
        .btn-primary:hover {
            background: #166fe5;
        }
        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="centershop-tenant-page-selection">
        <h1><?php _e('Vælg din Facebook side', 'centershop_txtdomain'); ?></h1>
        
        <p><?php _e('Vi fandt flere Facebook sider du administrerer. Vælg den side du vil forbinde:', 'centershop_txtdomain'); ?></p>
        
        <form method="post" action="" id="page-selection-form">
            <?php wp_nonce_field('centershop_fb_page_selection_' . $token); ?>
            <input type="hidden" name="shop_id" value="<?php echo esc_attr($shop_id); ?>">
            <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
            <input type="hidden" name="transient_key" value="<?php echo esc_attr($transient_key); ?>">
            
            <div class="page-list">
                <?php foreach ($pages as $index => $page): ?>
                    <label class="page-item">
                        <input type="radio" name="selected_page" value="<?php echo esc_attr($index); ?>" required>
                        <div>
                            <div class="page-name"><?php echo esc_html($page['name']); ?></div>
                            <div class="page-id">ID: <?php echo esc_html($page['id']); ?></div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
            
            <p>
                <button type="submit" class="btn-primary">
                    <?php _e('Forbind valgte side', 'centershop_txtdomain'); ?>
                </button>
            </p>
        </form>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>
