# JXW Mall

WordPress-plugin til administration og visning af butikker i et shoppingcenter, inkl. butikssider, åbningstider, grundplan og Gutenberg-blokke.

## Funktioner

- Custom post type: `butiksside` (butikker)
- Butiksdata via metafelter (adresse, telefon, mail, web, sociale medier, logo)
- CenterShop admin-menu med oversigt, butikker, kategorier, åbningstider, eksport og indstillinger
- Åbningstider med ugedage, helligdage og ekstra tekst
- Eksport af butiksmails fra admin
- Grundplansmodul med upload af billede/SVG, klikbare områder og butikskobling
- Frontend-søgning i butikker til grundplan
- Template-override for enkeltbutik og butiksliste-side

## Gutenberg-blokke

Plugin’et indeholder bl.a. følgende blokke:

- `centershop/shop-list` – butiksliste
- `centershop/single-shop` – enkelt butik
- `centershop/shop-logo-carousel` – logo-ticker
- `centershop/opening-hours` – centerets åbningstider
- `centershop/floorplan` – interaktiv grundplan
- `centershop/facebook-feed` – Facebook feed
- `centershop/instagram-feed` – Instagram feed
- `centershop/read-also` – "Læs også"-kort med søgning på artikler, begivenheder og sider

## Shortcodes

- `[centershop_floorplan]` – viser centerets grundplan
- `[dc_show_all]` – viser brancheoversigt
- `[diana-erhvervsliste diana_branche="<term_id>"]` – viser butikker i en kategori

## Installation

1. Kopiér plugin-mappen til `wp-content/plugins/jxw-mall`.
2. Aktivér **JXW Mall** i WordPress admin.
3. Opret/vedligehold butikker under **CenterShop → Alle butikker**.
4. Konfigurér åbningstider under **CenterShop → Åbningstider**.
5. Konfigurér grundplan under **CenterShop → Grundplan**.

## Vigtige filer

- `jxw-mall.php` – plugin bootstrap og registrering af hooks, post type, blokke og templates
- `includes/class-admin-menu.php` – centralt admin-menu system
- `includes/class-settings.php` – plugin-indstillinger
- `includes/class-floorplan.php` – grundplansfunktionalitet
- `includes/functions-cpt-butikker.php` – CPT og butik-metaboxes
- `includes/functions-shopping-hours.php` – centerets åbningstider
- `includes/functions-opening-hours-v2.php` – åbningstider på butiksniveau

## Krav

- WordPress med støtte for Gutenberg-blokke
- PHP-version kompatibel med WordPress-installationen

## Licens

GPL-2.0 (jf. plugin-header i `jxw-mall.php`).
