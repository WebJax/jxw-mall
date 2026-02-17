# Tenant Self-Service Facebook Connection System

This document describes the tenant self-service system for connecting Facebook and Instagram business pages to the mall website.

## Overview

Mall administrators can generate secure, one-time links for each shop tenant. Tenants use these links to connect their Facebook business pages without needing WordPress login access. The system automatically imports posts and displays them on the website with engagement metrics.

## Features

### For Mall Administrators

- **Connection Management Dashboard**: View all shops and their connection status
- **Magic Link Generation**: Generate secure, one-time links for tenants
- **Email Integration**: Send connection invitations directly to shop email addresses
- **Disconnect Function**: Ability to disconnect shops if needed
- **Backward Compatibility**: Works alongside existing manual configuration

### For Tenants

- **No WordPress Login Required**: Tenants connect via magic link
- **Simple OAuth Flow**: Click link → Login to Facebook → Select page → Done
- **Auto-Selection**: If tenant only manages one page, it's automatically selected
- **Multi-Page Support**: Choose from multiple pages if managing several
- **Success Confirmation**: Clear success page with next steps

### For Website Visitors

- **Engagement Metrics**: See likes, comments, and shares on posts
- **Responsive Display**: Engagement counts display nicely on all devices

## How It Works

### 1. Admin Generates Link

Admin goes to **Settings → Facebook Feed → Butiksforbindelser** and clicks "Generer Link" for a shop.

The system:
- Generates a cryptographically secure 64-character token
- Sets 7-day expiration
- Displays copyable link in modal
- Optionally sends email to shop contact

### 2. Tenant Connects Page

Tenant clicks magic link and lands on branded connection page.

The system:
- Validates token (not expired, not already used)
- Displays shop name and mall name
- Provides Facebook OAuth button

When tenant clicks "Connect":
- Redirects to Facebook OAuth
- Requests permissions: `pages_show_list`, `pages_read_engagement`, `pages_read_user_content`
- Returns to callback URL

### 3. Token Exchange & Page Selection

After OAuth approval:
- Exchanges short-lived token for long-lived token (60 days)
- Fetches list of pages user manages
- If single page: auto-connects and shows success
- If multiple pages: displays selection form

### 4. Connection Saved

After page selection:
- Saves connection to database with page access token
- Marks magic token as used
- Sends notification email to admin
- Shows success page to tenant

### 5. Automatic Import

Daily cron job:
- Fetches posts from all connected pages
- Uses connection-specific tokens
- Extracts engagement metrics (likes, comments, shares)
- Updates last sync timestamp

## Database Schema

### wp_centershop_fb_connections

Stores active Facebook page connections for shops.

```sql
CREATE TABLE wp_centershop_fb_connections (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    shop_id bigint(20) UNSIGNED NOT NULL,
    fb_page_id varchar(100) NOT NULL,
    fb_page_name varchar(255) DEFAULT NULL,
    page_access_token text NOT NULL,
    token_expires datetime DEFAULT NULL,
    connected_date datetime DEFAULT CURRENT_TIMESTAMP,
    last_sync datetime DEFAULT NULL,
    is_active tinyint(1) DEFAULT 1,
    connection_type varchar(20) DEFAULT 'facebook',
    PRIMARY KEY (id),
    UNIQUE KEY shop_page (shop_id, fb_page_id)
);
```

### wp_centershop_fb_magic_tokens

Stores one-time magic tokens for tenant connections.

```sql
CREATE TABLE wp_centershop_fb_magic_tokens (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    shop_id bigint(20) UNSIGNED NOT NULL,
    token varchar(64) NOT NULL,
    created_date datetime DEFAULT CURRENT_TIMESTAMP,
    expires_date datetime NOT NULL,
    used tinyint(1) DEFAULT 0,
    used_date datetime DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY token (token)
);
```

### wp_centershop_fb_posts (Updated)

Added engagement columns:

```sql
ALTER TABLE wp_centershop_fb_posts 
ADD COLUMN likes_count int DEFAULT 0,
ADD COLUMN comments_count int DEFAULT 0,
ADD COLUMN shares_count int DEFAULT 0;
```

## API Methods

### Facebook Graph API Calls

**Get Page Posts** (includes engagement):
```
GET /{page-id}/posts
?fields=id,message,created_time,permalink_url,full_picture,
        attachments{media_type,media,url},
        likes.summary(true),
        comments.summary(true),
        shares
```

**Exchange Token**:
```
GET /oauth/access_token
?grant_type=fb_exchange_token
&client_id={app-id}
&client_secret={app-secret}
&fb_exchange_token={short-lived-token}
```

**Get User Pages**:
```
GET /me/accounts
?fields=id,name,access_token
```

## Security Considerations

### Token Security

- **Cryptographic Randomness**: Uses `random_bytes(32)` for 256-bit entropy
- **Single Use**: Tokens can only be used once
- **Expiration**: 7-day automatic expiration
- **Unique Constraints**: Database enforces uniqueness

### Input Validation

- All user input sanitized with WordPress functions
- Shop ID validated against post type
- Token format validated before use
- Nonce validation on all AJAX requests

### Authorization

- Only users with `manage_options` can generate links
- Token required for connection flow
- Shop ID must match token's shop ID
- Page tokens stored securely in database

### Data Protection

- Prepared SQL statements prevent injection
- Escaped output prevents XSS
- No sensitive data in URLs (except one-time tokens)
- Admin email notifications for connections

## Backward Compatibility

The system maintains full backward compatibility with the existing manual configuration:

**Old Method** (still works):
- Admin manually configures Facebook App ID/Secret
- Admin logs in with Facebook to get tokens
- Pages configured in textarea format: `page_id:shop_id`

**New Method** (tenant self-service):
- Uses connection-based tokens
- Stored in dedicated tables
- Fully integrated with import system

**Import Logic**:
1. First checks for connections in new system
2. Falls back to old configuration if no connections
3. Can use both simultaneously

## Usage Examples

### Admin: Generate Link for Shop

```php
// Via AJAX
wp_ajax_centershop_fb_generate_magic_link

// Returns
{
    "success": true,
    "data": {
        "link": "https://site.com/connect-facebook?shop=123&token=abc...",
        "token": "abc123...",
        "expires": "24. januar 2024 15:30"
    }
}
```

### Admin: Send Email to Shop

```php
// Via AJAX
wp_ajax_centershop_fb_send_connection_email

// Parameters
{
    "shop_id": 123,
    "magic_link": "https://..."
}

// Gets email from post meta
$shop_email = get_post_meta($shop_id, 'butik_payed_mail', true);
```

### Admin: Disconnect Shop

```php
// Via AJAX
wp_ajax_centershop_fb_disconnect_shop

// Parameters
{
    "connection_id": 456
}

// Sets is_active = 0
```

## Troubleshooting

### Token Expired

**Error**: "Token er udløbet. Kontakt center admin for et nyt link."

**Solution**: Admin generates new magic link.

### Token Already Used

**Error**: "Token er allerede brugt"

**Solution**: Admin generates new magic link.

### No Pages Found

**Error**: "Ingen Facebook sider fundet. Du skal være administrator af en Facebook Business side."

**Cause**: User doesn't manage any Facebook pages.

**Solution**: Tenant must be added as admin/editor on their Facebook Business Page.

### Email Not Sending

**Cause**: Shop has no email address in custom field.

**Solution**: Add email to shop's "Kontakt information" → "Email" field.

### Import Not Working

**Checks**:
1. Connection shows as "Forbundet" in admin
2. "Sidst synkroniseret" shows recent date
3. Facebook App is in Live mode (not Development)
4. Page access token hasn't expired

## Facebook App Configuration

### Required Permissions

- `pages_show_list` - List pages user manages
- `pages_read_engagement` - Read engagement metrics
- `pages_read_user_content` - Read posts from pages

### App Review

If app is in Development mode, only admins/testers can connect. For production:

1. Submit app for review
2. Request above permissions
3. Explain use case: "Import Facebook posts to website"
4. Wait for approval (usually 3-5 days)

### Valid OAuth Redirect URIs

Add to Facebook App settings:
```
https://yourdomain.com/connect-facebook/callback
```

## Maintenance

### Cleanup Expired Tokens

Run periodically (add to cron if needed):

```php
$connections = CenterShop_FB_Connections::get_instance();
$deleted = $connections->cleanup_expired_tokens();
```

### Refresh Expiring Tokens

Page tokens from user accounts are long-lived (60 days) but do expire. The system should be enhanced to:

1. Check for tokens expiring soon
2. Use refresh endpoint to renew
3. Email admin if refresh fails

## Future Enhancements

### Instagram Support

The architecture supports Instagram with minimal changes:

1. Update OAuth scope to include Instagram permissions
2. Add Instagram Graph API calls
3. Set `connection_type = 'instagram'` when saving
4. Use Instagram-specific field names

### Token Auto-Refresh

Add cron job to automatically refresh expiring tokens:

```php
add_action('centershop_fb_token_refresh', function() {
    // Check for tokens expiring in 7 days
    // Refresh using Facebook API
    // Email admin if refresh fails
});
```

### Multi-Admin Support

Allow shop owners to have multiple admins who can disconnect:

- Add auth tokens to shop meta
- Allow shop owners to manage own connections
- Send disconnect notifications to both admin and shop

## Code Files

### New Files

- `includes/facebook-feed/class-fb-connections.php` - Connection management
- `includes/facebook-feed/class-fb-tenant-auth.php` - OAuth handler
- `templates/tenant-connect.php` - Landing page
- `templates/tenant-success.php` - Success page
- `templates/tenant-error.php` - Error page
- `templates/tenant-page-selection.php` - Multi-page selection

### Modified Files

- `jxw-mall.php` - Load new classes, create tables
- `includes/facebook-feed/class-fb-database.php` - Engagement columns
- `includes/facebook-feed/class-fb-api-handler.php` - Token exchange, engagement fields
- `includes/facebook-feed/class-fb-importer.php` - Use connections
- `includes/facebook-feed/class-fb-shortcodes.php` - Display engagement
- `includes/facebook-feed/class-fb-settings.php` - Admin UI
- `css/centershop-fb-feed.css` - Engagement styling

## Support

For issues or questions:

1. Check Import Log in admin
2. Verify Facebook App configuration
3. Test with single shop first
4. Review error messages in templates
5. Check browser console for JavaScript errors

## Changelog

### Version 2.1.0

- Added tenant self-service connection system
- Added magic link generation
- Added engagement metrics display
- Added connection management UI
- Enhanced security with token validation
- Improved backward compatibility
