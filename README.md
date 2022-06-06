## Editing an article

# Saving an article

## Use your own fonts

You need to collect a CSS file with certain comments and fonts sewn into base64, and then they will automatically appear in the Layout.
default url /vms_fonts.css

At the top of the CSS file you need to specify the default font in the comments, which will be set when creating a new text object.
```
/* default_font_family: 'formular'; */
/* default_font_weight: 400; */
/* default_font_size: 16px; */
/* default_line_height: 24px; */
```

Further, for each `@ font-face` it is necessary to register comments with the name of the font and its style.
```
   /* font_name: 'Formular'; */
   /* font_style_name: 'Light'; */
```

Final CSS file:
```
/* default_font_family: 'formular'; */
/* default_font_weight: 400; */
/* default_font_size: 16px; */
/* default_line_height: 24px; */

@ font-face {
   /* font_name: 'Formular'; */
   /* font_style_name: 'Light'; */
    font-family: 'formular';
    src: url (data: application / font-woff2; charset = utf-8; base64, KJHGKJHGJHG) format ('woff2'),
         url (data: application / font-woff; charset = utf-8; base64, KJHGKJHGJHG) format ('woff');
    font-weight: 300;
    font-style: normal;
}

@ font-face {
   /* font_name: 'Formular'; */
   /* font_style_name: 'Regular; */
    font-family: 'formular';
    src: url (data: application / font-woff2; charset = utf-8; base64, KJHGKJHGJHG) format ('woff2'),
         url (data: application / font-woff; charset = utf-8; base64, KJHGKJHGJHG) format ('woff');
    font-weight: 400;
    font-style: normal;
}
```

## Displaying Articles
The HTML code of the article should be accompanied by the connection of the script:

```
<script type = "text / javascript">
    window.onVMSAPIReady = function (api) {
        api.Article.enable ({
            display_mode: 'default'
        });
    };
</script>
<script src="//go.verstka.org/api.js" async type="text/javascript"></script>
```
