# Facebook og Instagram Feed Funktionalitet

Dette plugin tilbyder nu fuld support for både Facebook og Instagram feeds med shortcodes og Gutenberg blocks.

## Funktioner

### Facebook Feed
- ✅ Automatisk import af Facebook posts fra butikkers sider
- ✅ Gutenberg block til visning af Facebook feed
- ✅ Shortcodes til fleksibel visning
- ✅ Daglig automatisk opdatering (kl. 03:00)
- ✅ Manuel import funktion

### Instagram Feed
- ✅ Automatisk import af Instagram posts fra butikkers profiler
- ✅ Gutenberg block til visning af Instagram feed
- ✅ Shortcodes til fleksibel visning
- ✅ Daglig automatisk opdatering (kl. 03:30)
- ✅ Manuel import funktion
- ✅ Support for billeder og videoer

## Opsætning

### 1. Facebook Feed Opsætning

#### Global Konfiguration
1. Gå til **CenterShop → Facebook Feed** i WordPress admin
2. Angiv dit Facebook Access Token
3. Konfigurer antal posts pr. import (standard: 10)

#### Butiks Konfiguration
For hver butik skal du:
1. Redigere butikken i WordPress
2. Angive følgende custom fields:
   - Facebook Side ID (eksisterende felt)
   - Facebook Page Token (hvis butikken har sin egen token)

### 2. Instagram Feed Opsætning

#### Global Konfiguration
1. Gå til **CenterShop → Instagram Feed** i WordPress admin
2. Angiv dit Instagram Access Token (fra Instagram Basic Display API eller Instagram Graph API)
3. Konfigurer antal posts pr. import (standard: 10)

#### Butiks Konfiguration
For hver butik skal du:
1. Redigere butikken i WordPress
2. Angive følgende custom fields:
   - `butik_payed_ig_user_id` - Instagram bruger-ID
   - `butik_payed_ig_token` - Instagram access token for butikken

#### Få Instagram Access Token
1. Gå til [Facebook Developers](https://developers.facebook.com/)
2. Opret en app og aktiver Instagram Basic Display API
3. Følg [denne vejledning](https://developers.facebook.com/docs/instagram-basic-display-api/getting-started)
4. Kopier access token til plugin indstillingerne

## Brug af Shortcodes

### Facebook Feed Shortcode

```php
[centershop_facebook_feed count="10" shop="123" layout="grid" columns="3" show_date="yes" show_shop="yes" excerpt_length="150"]
```

**Parametre:**
- `count` - Antal posts at vise (standard: 10)
- `shop` - Butiks ID (valgfri - viser alle butikker hvis tom)
- `layout` - Layout type: `grid` eller `list` (standard: grid)
- `columns` - Antal kolonner i grid (1-6, standard: 3)
- `show_date` - Vis dato: `yes` eller `no` (standard: yes)
- `show_shop` - Vis butiksnavn: `yes` eller `no` (standard: yes)
- `excerpt_length` - Længde af tekst i tegn (standard: 150)

**Alias:** Du kan også bruge `[mall_facebook_feed]`

### Instagram Feed Shortcode

```php
[centershop_instagram_feed count="10" shop="123" layout="grid" columns="3" show_date="yes" show_shop="yes" excerpt_length="150"]
```

**Parametre:**
- `count` - Antal posts at vise (standard: 10)
- `shop` - Butiks ID (valgfri - viser alle butikker hvis tom)
- `layout` - Layout type: `grid` eller `list` (standard: grid)
- `columns` - Antal kolonner i grid (1-6, standard: 3)
- `show_date` - Vis dato: `yes` eller `no` (standard: yes)
- `show_shop` - Vis butiksnavn: `yes` eller `no` (standard: yes)
- `excerpt_length` - Længde af tekst i tegn (standard: 150)

**Alias:** Du kan også bruge `[mall_instagram_feed]`

## Brug af Gutenberg Blocks

### Facebook Feed Block

1. Åbn siden/indlægget i Gutenberg editor
2. Klik på "+" for at tilføje en ny blok
3. Søg efter "Facebook Feed"
4. Konfigurer blokken i højre sidebar:
   - Vælg butik (eller alle)
   - Indstil antal posts
   - Vælg layout (grid/liste)
   - Indstil antal kolonner
   - Til/fra for dato og butiksnavn
   - Indstil tekst længde

### Instagram Feed Block

1. Åbn siden/indlægget i Gutenberg editor
2. Klik på "+" for at tilføje en ny blok
3. Søg efter "Instagram Feed"
4. Konfigurer blokken i højre sidebar (samme muligheder som Facebook)

## Automatisk Import

Begge feeds importerer automatisk nye posts dagligt:
- **Facebook:** Kl. 03:00 hver dag
- **Instagram:** Kl. 03:30 hver dag

Du kan også manuelt importere posts:
1. Gå til **CenterShop → Facebook Feed** eller **Instagram Feed**
2. Klik på "Importer nu" knappen

## Se Importerede Posts

### Facebook Posts
Gå til **CenterShop → Facebook Posts** for at se alle importerede Facebook posts

### Instagram Posts
Gå til **CenterShop → Instagram Posts** for at se alle importerede Instagram posts

## Styling

Feeds bruger følgende CSS klasser som du kan tilpasse i dit tema:

### Facebook Feed
- `.centershop-fb-feed` - Feed container
- `.centershop-fb-post` - Enkelt post
- `.centershop-fb-post-image` - Post billede
- `.centershop-fb-post-content` - Post indhold
- `.centershop-fb-post-shop` - Butiksnavn
- `.centershop-fb-post-text` - Post tekst
- `.centershop-fb-post-meta` - Meta information

### Instagram Feed
- `.centershop-ig-feed` - Feed container
- `.centershop-ig-post` - Enkelt post
- `.centershop-ig-post-image` - Post billede
- `.centershop-ig-post-content` - Post indhold
- `.centershop-ig-post-shop` - Butiksnavn
- `.centershop-ig-post-text` - Post tekst
- `.centershop-ig-post-meta` - Meta information
- `.centershop-ig-video-icon` - Video play ikon

## Eksempler

### Vis seneste 6 Facebook posts i grid med 2 kolonner
```php
[centershop_facebook_feed count="6" columns="2"]
```

### Vis Instagram posts fra en specifik butik
```php
[centershop_instagram_feed shop="123" count="12" columns="4"]
```

### Vis Facebook posts som liste
```php
[centershop_facebook_feed layout="list" count="5"]
```

### Vis Instagram posts uden butiksnavn
```php
[centershop_instagram_feed show_shop="no" columns="3"]
```

## Fejlfinding

### Ingen posts bliver importeret

1. **Tjek access tokens:**
   - Gå til indstillingssiden
   - Verificer at access tokens er korrekte
   - For Instagram: tokens udløber - forny dem jævnligt

2. **Tjek butiks konfiguration:**
   - Sikr at hver butik har korrekt Facebook Side ID / Instagram Bruger ID
   - Verificer at tokens er konfigureret for butikkerne

3. **Tjek cron jobs:**
   - Sikr at WordPress cron kører korrekt
   - Du kan manuelt teste import via admin siden

4. **Tjek fejllog:**
   - Aktiver WordPress debug mode
   - Se efter fejlmeddelelser i wp-content/debug.log

### Posts vises ikke på frontend

1. Tjek at der er posts i admin (CenterShop → Facebook/Instagram Posts)
2. Verificer shortcode parametre
3. Tjek for JavaScript fejl i browseren
4. Sikr at CSS filerne bliver indlæst

## API Begrænsninger

### Facebook
- Facebook Graph API har rate limits
- Tokens skal fornyes jævnligt
- Kræver godkendt Facebook app for produktion

### Instagram
- Instagram Basic Display API tokens udløber efter 60 dage
- Brug token refresh funktionalitet
- Instagram Graph API anbefales for business accounts

## Support

For hjælp kontakt jaxweb.dk
