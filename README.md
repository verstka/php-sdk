# Verstka Editor PHP-SDK

First, You need to require package by composer with composer require verstka/php-sdk

## Initialization via config

```php
$verstkaBuilder = new \Verstka\Builder\VerstkaConfigBuilder(
                            'API_KEY_FRsGhsgFGSG45d34',
                            'SECRET_KEY_32ff2f23f32f',
                            'https://aws-host.toexternal_storage.com', // Optional, image storage host
                            'https://verstka.org', // Optional, host api Verstka
                            true, // Optional, Debug mode
                          );
                          
$verstkaEditor  = $verstkaBuilder->build();

```

## Initialization with ENV

```php
$verstkaBuilder = new \Verstka\Builder\VerstkaEnvBuilder();
                          
$verstkaEditor  = $verstkaBuilder->build();

```

set ```.env``` file in the root of your project with settings listed below:

```
verstka_apikey = "..."
verstka_secret = "..."
verstka_host = "https://verstka.org"
```

if you don't use vlucas/phpdotenv or yiithings/yii2-dotenv or something like that just set environment before new object
of Verstka create:

```
putenv('verstka_apikey=...');
putenv('verstka_secret=...');
putenv('verstka_host=https://verstka.org');
```

additional parameters:

```
images_host - in case if you use relative images
and storage host different from callback url host
```

## Editing an article

```php
$sql = 'SELECT * FROM t_materials WHERE name = :name';
$article = static::getDatabase()->fetchOne($sql, ['name' => $material_id]);

$body = $is_mobile ? $article['mobile_html'] : $article['desktop_html'];

$custom_fileds = [
    'auth_user' => 'test',                                      //if you have http authorization on callback url
    'auth_pw' => 'test',                                        //if you have http authorization on callback url
    'fonts.css' => 'https://mydomain.com/static/vms_fonts.css', //if you use custom fonts set
    'mobile' => true                                            //if you open mobile version of the post,
    'user_id' => 123                                            //if you want to know the user who opened the editor when saving 
];
```

for example and then just:

```php
/// ....
$verstkaEditor  = $verstkaBuilder->build();
$verstka_url = $verstkaEditor->open($material_id, $body, $is_mobile, 'https://mydomain.com/verstka/save', $custom_fileds);
```

## Saving an article

```php
///  ....
$verstkaEditor  = $verstkaBuilder->build();
return $verstkaEditor->save($client_callback_function, $data);
```

where

```php
function clientCallback(array $data): bool
{
/*
  $data will contain array:
  [
      'article_body' =>  ... //html of article to save
      'custom_fields' => ... //json with additional staff
      'is_mobile' =>     ... //is mobile version of article
      'material_id' =>   ... //article id
      'user_id' =>       ... //user id
      'images' =>        ... //array of used images
  ]
*/

    //file_put_contents('/tmp/client_callback.log', print_r($data, true));

    $is_fail = false;
    $article_body = $data['article_body'];
    $article_static_dir_rel = sprintf('/upload/verstka/%s%s', $data['is_mobile'] ? 'm_':'', $data['material_id']);
    $article_static_dir_abs = sprintf('%s%s%s%s', WEBROOT, DIRECTORY_SEPARATOR, '/public/', $article_static_dir_rel);
    @mkdir($article_static_dir_abs,  0777, true);
    foreach ($data['images'] as $image_name => $image_file) {
        $is_renamed = rename($image_file, sprintf('%s/%s', $article_static_dir_abs, $image_name));
        $is_fail = $is_fail || !$is_renamed;
        $html_image_name_old = sprintf('/vms_images/%s', $image_name);
        $html_image_name_new = sprintf('%s/%s', $article_static_dir_rel, $image_name);
        if ($is_renamed) {
            $article_body = str_replace($html_image_name_old, $html_image_name_new, $article_body);
        }
    }
    
    if ($is_fail) {
        return false; //tell editor that save goes wrong
    }
    
    if ($data['is_mobile']) {
        $sql = 'update t_materials set mobile_html =  :article_body where name = :name;';
    } else {
        $sql = 'update t_materials set desktop_html = :article_body where name = :name;';
    }

    $db = Plugin::getDatabase();
    $saved = (bool)$db->fetchAffected($sql, ['article_body' => $article_body, 'name' => $data['material_id']]);
    $is_fail = $is_fail || !$saved;

    return !$is_fail;
}
```

## Use your own fonts

You need to collect a CSS file with certain comments and fonts sewn into base64, and then they will automatically appear
in the Layout.
default url /vms_fonts.css

At the top of the CSS file you need to specify the default font in the comments, which will be set when creating a new
text object.

```css
/* default_font_family: 'formular'; */
/* default_font_weight: 400; */
/* default_font_size: 16px; */
/* default_line_height: 24px; */
```

Further, for each `@ font-face` it is necessary to register comments with the name of the font and its style.

```css
/* font_name: 'Formular'; */
/* font_style_name: 'Light'; */
```

Final CSS file:

```css
/* default_font_family: 'formular'; */
/* default_font_weight: 400; */
/* default_font_size: 16px; */
/* default_line_height: 24px; */

@font-face {
    /* font_name: 'Formular'; */
    /* font_style_name: 'Light'; */  
    font-family: 'formular';
    src: url (data: application / font-woff2; charset = utf-8; base64, KJHGKJHG...) format ('woff2'),
         url (data: application / font-woff; charset = utf-8; base64, KJHGKJHGJ...) format ('woff');
    font-weight: 300;
    font-style: normal;
}

@font-face {
    /* font_name: 'Formular'; */
    /* font_style_name: 'Regular; */
    font-family: 'formular';
    src: url (data: application / font-woff2; charset = utf-8; base64, AAFEWDDWEDD...) format ('woff2'),
         url (data: application / font-woff; charset = utf-8; base64, AAFEWDDWEDD...) format ('woff');
    font-weight: 400;
    font-style: normal;
}
```

## Displaying Articles

The HTML code of the article should be accompanied by the connection of the script:

```html

<link href="//go.verstka.org/critical.css" rel="stylesheet">

<script type="text / javascript">
    window.onVMSAPIReady = function (api) {
        api.Article.enable ({
            display_mode: 'default'
        });
    };

</script>
<script src="//go.verstka.org/api.js" async type="text/javascript"></script>
```
