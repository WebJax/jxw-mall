<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('Forbindelse gennemført!', 'centershop_txtdomain'); ?> - <?php bloginfo('name'); ?></title>
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
        .centershop-tenant-success {
            background: #fff;
            max-width: 600px;
            width: 100%;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            text-align: center;
        }
        .success-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .centershop-tenant-success h1 {
            color: #333;
            margin-top: 0;
            margin-bottom: 20px;
        }
        .centershop-tenant-success p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .page-name {
            font-weight: 600;
            color: #1877f2;
        }
        .btn-primary {
            background: #667eea;
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
            background: #5568d3;
        }
        .info-box {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
            text-align: left;
        }
        .info-box ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .info-box li {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="centershop-tenant-success">
        <div class="success-icon">✓</div>
        
        <h1><?php _e('Forbundet!', 'centershop_txtdomain'); ?></h1>
        
        <p><?php 
            printf(
                __('Din Facebook side <span class="page-name">"%s"</span> er nu forbundet til %s.', 'centershop_txtdomain'), 
                esc_html($page_name), 
                esc_html($mall_name)
            ); 
        ?></p>
        
        <div class="info-box">
            <strong><?php _e('Hvad sker der nu?', 'centershop_txtdomain'); ?></strong>
            <ul>
                <li><?php _e('Dine opslag importeres automatisk dagligt', 'centershop_txtdomain'); ?></li>
                <li><?php _e('Opslag vises på hjemmesiden sammen med andre butikkers opslag', 'centershop_txtdomain'); ?></li>
                <li><?php _e('Likes, kommentarer og delinger vises med på hjemmesiden', 'centershop_txtdomain'); ?></li>
            </ul>
        </div>
        
        <p>
            <a href="<?php echo home_url(); ?>" class="btn-primary">
                <?php _e('Gå til hjemmesiden', 'centershop_txtdomain'); ?>
            </a>
        </p>
        
        <p style="font-size: 14px; color: #999; margin-top: 30px;">
            <?php _e('Hvis du har spørgsmål, kontakt venligst center administratoren.', 'centershop_txtdomain'); ?>
        </p>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>
