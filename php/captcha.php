<?php
require(dirname(__FILE__) . '/securimage/securimage.php');

// Sorry for non-Chinese speakers.
$wortschatz = '
的一是了我不人在他有这个上们来到时大地为子中你说生
国年着就那和要她出也得里后自以会家可下而过天去能对
小多然于心学么之都好看起发当没成只如事把还用第样道
想作种开美总从无情己面最女但现前些所同日手又行意动
';

$fontname = dirname(__FILE__) . '/securimage/font.ttf';

$image = new Securimage();

$image->ttf_file        = $fontname;
if(rand(0,100) < 10)    // great possibility is a good reason for refreshing
    $image->captcha_type = Securimage::SI_CAPTCHA_MATHEMATIC;

//$image->case_sensitive  = true;                            // true to use case sensitve codes - not recommended
$image->image_height    = 70;                                // width in pixels of the image
$image->image_width     = $image->image_height * M_E;          // a good formula for image size
$image->perturbation    = 0.8;                               // 1.0 = high distortion, higher numbers = more distortion
$image->image_bg_color  = new Securimage_Color("#CCCCCC");   // image background color
$image->text_color      = new Securimage_Color("#000000");   // captcha text color
$image->num_lines       = 8;                                 // how many lines to draw over the image
$image->line_color      = new Securimage_Color("#000000");   // color of lines over the image

$image->charset = str_split(
    str_replace(array("\n", "\r", " "), '', $wortschatz),
    6
);

$image->code_length	= 2;
#$image->image_type      = SI_IMAGE_JPEG;                     // render as a jpeg image
$image->image_signature = 'NPBS Entrance';
$image->signature_font = $fontname;
$image->signature_color = new Securimage_Color(rand(0, 64),
                                             rand(64, 128),
                                             rand(128, 255));  // random signature color

$image->show();
