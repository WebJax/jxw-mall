<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('Forbind din Facebook side', 'centershop_txtdomain'); ?> - <?php bloginfo('name'); ?></title>
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
        .centershop-tenant-connect {
            background: #fff;
            max-width: 600px;
            width: 100%;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .centershop-tenant-connect h1 {
            color: #333;
            margin-top: 0;
            margin-bottom: 20px;
        }
        .centershop-tenant-connect p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .fb-connect-btn {
            background: #1877f2;
            color: #fff;
            border: none;
            padding: 15px 30px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s ease;
        }
        .fb-connect-btn:hover {
            background: #166fe5;
        }
        .fb-connect-btn:before {
            content: "f";
            font-family: "Facebook", sans-serif;
            margin-right: 8px;
        }
        .info-text {
            font-size: 14px;
            color: #999;
        }
        .shop-name {
            font-weight: 600;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="centershop-tenant-connect">
        <h1><?php _e('Forbind din Facebook side', 'centershop_txtdomain'); ?></h1>
        
        <p><?php 
            printf(
                __('Hej <span class="shop-name">%s</span>! Klik nedenfor for at forbinde din Facebook Business side til %s hjemmeside.', 'centershop_txtdomain'), 
                esc_html($shop->post_title), 
                esc_html($mall_name)
            ); 
        ?></p>
        
        <p><?php _e('Dine opslag vil automatisk blive vist på hjemmesiden sammen med andre butikkers opslag.', 'centershop_txtdomain'); ?></p>
        
        <p>
            <a href="<?php echo esc_url($oauth_url); ?>" class="fb-connect-btn">
                <?php _e('Forbind Facebook side', 'centershop_txtdomain'); ?>
            </a>
        </p>
        
        <p class="info-text">
            <?php _e('Du kan afbryde forbindelsen når som helst fra dine Facebook indstillinger.', 'centershop_txtdomain'); ?>
        </p>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>
