<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('Fejl', 'centershop_txtdomain'); ?> - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .centershop-tenant-error {
            background: #fff;
            max-width: 600px;
            width: 100%;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            text-align: center;
        }
        .error-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .centershop-tenant-error h1 {
            color: #d63638;
            margin-top: 0;
            margin-bottom: 20px;
        }
        .error-message {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            text-align: left;
            color: #856404;
        }
        .centershop-tenant-error p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .btn {
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
            margin: 5px;
            transition: background 0.3s ease;
        }
        .btn:hover {
            background: #5568d3;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="centershop-tenant-error">
        <div class="error-icon">⚠️</div>
        
        <h1><?php _e('Der opstod en fejl', 'centershop_txtdomain'); ?></h1>
        
        <div class="error-message">
            <?php echo esc_html($error_message); ?>
        </div>
        
        <p><?php _e('Kontakt venligst center administratoren hvis problemet fortsætter.', 'centershop_txtdomain'); ?></p>
        
        <p>
            <?php if ($show_retry): ?>
                <a href="<?php echo home_url('/connect-facebook'); ?>" class="btn">
                    <?php _e('Prøv igen', 'centershop_txtdomain'); ?>
                </a>
            <?php endif; ?>
            <a href="<?php echo home_url(); ?>" class="btn btn-secondary">
                <?php _e('Gå til hjemmesiden', 'centershop_txtdomain'); ?>
            </a>
        </p>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>
