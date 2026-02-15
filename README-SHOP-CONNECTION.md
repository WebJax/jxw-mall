# Butik-Nyhed Forbindelse (Shop-News Connection)

## Oversigt
Dette modul gør det muligt at forbinde WordPress posts (nyheder) med butikker. Når en nyhed er forbundet med en eller flere butikker, vises de som klikbare pills ved siden af post meta informationen.

## Features

### 1. **Meta Box i Post Editor**
- Når du redigerer en nyhed, kan du vælge en eller flere butikker
- Vises som en checkbox-liste i sidebar
- Gemmes automatisk når posten opdateres

### 2. **Automatisk Visning af Pills**
- Pills vises automatisk i toppen af indholdet på single post pages
- Klikbare links til butikssiderne
- Pæn gradient styling med hover-effekter
- Emoji ikon for bedre visuel identifikation

### 3. **Tilknyttede Nyheder på Butikssider**
- Viser alle nyheder forbundet til en specifik butik
- Bruges med shortcode eller template function

## Brug

### I WordPress Admin
1. Gå til **Indlæg → Tilføj nyt** eller rediger et eksisterende indlæg
2. I sidebaren finder du "Tilknyttede Butikker" meta box
3. Vælg en eller flere butikker fra listen
4. Gem indlægget

### Automatisk Visning
Pills vises automatisk øverst i nyhedens indhold på single post pages.

### Manuel Placering med Shortcode

#### Vis forbundne butikker:
```php
[connected_shops]
```

#### Vis forbundne butikker for en specifik post:
```php
[connected_shops post_id="123"]
```

#### Vis relaterede nyheder på en butikside:
```php
[shop_related_posts]
```

#### Vis relaterede nyheder for en specifik butik:
```php
[shop_related_posts shop_id="456"]
```

### Template Functions

#### Vis forbundne butikker i et tema template:
```php
<?php
if (function_exists('jxw_display_connected_shops')) {
    echo jxw_display_connected_shops();
}
?>
```

#### Vis relaterede nyheder på en butikside:
```php
<?php
if (function_exists('jxw_display_shop_related_posts')) {
    echo jxw_display_shop_related_posts();
}
?>
```

#### Hent posts for en specifik butik (avanceret):
```php
<?php
if (function_exists('jxw_get_posts_for_shop')) {
    $posts = jxw_get_posts_for_shop($shop_id);
    foreach ($posts as $post) {
        // Dit custom loop her
    }
}
?>
```

## Integration i Tema

### Tilføj til Post Meta Area
Rediger `template-parts/content-single.php`:

```php
<p class="udgivet-den">Udgivet den <?php the_date(); ?></p>
<?php 
// Vis forbundne butikker som pills
if (function_exists('jxw_display_connected_shops')) {
    echo jxw_display_connected_shops();
}
?>
```

### Inline Display ved Post Meta
For at vise pills inline med udgivelsesinformationen:

```php
<p class="udgivet-den">
    Udgivet den <?php the_date(); ?>
    <?php 
    if (function_exists('jxw_display_connected_shops')) {
        $shops = jxw_display_connected_shops();
        if ($shops) {
            echo str_replace('class="jxw-connected-shops"', 'class="jxw-connected-shops inline"', $shops);
        }
    }
    ?>
</p>
```

### På Butikssiden
Tilføj til `templates/single-butiksside.php` eller brug shortcode i WordPress editor:

```php
<?php
if (function_exists('jxw_display_shop_related_posts')) {
    echo jxw_display_shop_related_posts();
}
?>
```

## Styling

### Standard Styling
Pills har gradient baggrund (lilla/blå) med hover-effekter:
- Smooth transitions
- Box shadow
- Transform på hover

### Custom Farver
Du kan tilføje klasser for forskellige farver:

```php
// I din template, modificer output:
.jxw-shop-pill.blue   // Blå gradient
.jxw-shop-pill.green  // Grøn gradient
.jxw-shop-pill.orange // Orange gradient
.jxw-shop-pill.pink   // Pink gradient
```

### Custom CSS
Tilpas styling i dit tema ved at override i `style.css`:

```css
.jxw-shop-pill {
    /* Din custom styling */
    background: #your-color;
    border-radius: 10px;
}
```

## Deaktivere Automatisk Visning

Hvis du vil have fuld kontrol over placeringen:

```php
// Tilføj til dit temas functions.php
remove_filter('the_content', 'jxw_add_shops_to_content', 10);
```

Derefter kan du bruge `jxw_display_connected_shops()` præcis hvor du vil have det.

## Database

Forbindelser gemmes som post meta:
- **Meta key:** `_jxw_connected_shops`
- **Meta value:** Array af shop IDs
- **Post type:** post (nyheder)

## API Functions

### `jxw_display_connected_shops($post_id = null)`
Returnerer HTML markup med shop pills.

### `jxw_get_posts_for_shop($shop_id)`
Returnerer array af WP_Post objekter forbundet til butikken.

### `jxw_display_shop_related_posts($shop_id = null)`
Returnerer HTML markup med liste af relaterede nyheder.

## Eksempler

### Eksempel 1: Custom Loop i Tema
```php
<?php
$connected_shops = get_post_meta(get_the_ID(), '_jxw_connected_shops', true);
if (!empty($connected_shops)) {
    echo '<div class="my-custom-shops">';
    foreach ($connected_shops as $shop_id) {
        $shop = get_post($shop_id);
        echo '<a href="' . get_permalink($shop_id) . '">';
        echo get_the_post_thumbnail($shop_id, 'thumbnail');
        echo $shop->post_title;
        echo '</a>';
    }
    echo '</div>';
}
?>
```

### Eksempel 2: Tilføj Butik Count i Archive
```php
<?php
// I din archive template
if (function_exists('jxw_get_posts_for_shop')) {
    $post_count = count(jxw_get_posts_for_shop(get_the_ID()));
    echo '<span class="post-count">' . $post_count . ' nyheder</span>';
}
?>
```

## Support & Udvidelser

### Tilføj Meta til REST API
Hvis du bruger Gutenberg eller REST API:

```php
// Tilføj til dit temas functions.php
add_action('rest_api_init', function() {
    register_rest_field('post', 'connected_shops', array(
        'get_callback' => function($post) {
            return get_post_meta($post['id'], '_jxw_connected_shops', true);
        },
        'schema' => array(
            'type' => 'array',
            'items' => array('type' => 'integer')
        )
    ));
});
```

## Filstruktur
```
jxw-mall/
├── includes/
│   └── functions-post-shop-connection.php  # Main functionality
├── css/
│   └── shop-pills.css                      # Styling
└── README-SHOP-CONNECTION.md               # Denne fil
```

## Changelog

### Version 1.0.0
- Initial release
- Meta box i post editor
- Automatisk visning af pills
- Shortcodes
- Template functions
- Responsive styling
