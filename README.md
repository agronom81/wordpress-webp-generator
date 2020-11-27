# wordpress-webp-generator

Thanks Alena Lyakhova - https://www.linkedin.com/in/alena-lyakhova-169100109/

This small script will allow you to create webp images uploaded via the media uploader on the fly.

## How to use

Copy wds_webp_generate.php into your theme and reference it from your functions.php file e.g. `require_once('wds_webp_generate.php');`, then you're good to go.

You can then use it in your theme as such:

```
wds_picture_generate( $alt, $file_id, $file_url, $img_size )
```
generate image with picture tag

or

```
wds_webp_generate( $alt, $file_id, $file_url, $img_size )
```
generate simple img tag with srcset and sizes

$alt - alt text (required)

$file_id - picture id

$file_url - picture url (if you do not have $file_id, then $file_id = 0)

$image_size - standard wordpress image size (default full)

