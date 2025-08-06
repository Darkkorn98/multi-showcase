<?php
return [
    'image_folder'    => __DIR__ . '/images/',             // Folder with main images (make sure to name artist__example.png using 2 underscores not just 1)
    'fallback_folder' => __DIR__ . '/fallback_images/',    // Folder for fallback images (optional, use fallback_image path accordingly)
    'fallback_image'  => __DIR__ . '/fallback_images/pexels__fallback.jpg', // Full path fallback image(in pexels put artistname if you wish to credit)
    'font_path'       => __DIR__ . '/fonts/OpenSans-Regular.ttf',  // Path to TTF font file (you can choose any ttf font you like just name it here accordingly)
    'output_width'    => 300,     // Output image width in pixels
    'output_height'   => 300,     // Output image height in pixels
    'font_size'       => 12,      // Font size for artist credit
    'text_padding'    => 10,      // Padding from edges for text
    'text_angle'      => 0,       // Angle of text (0 for horizontal)
    'text_position'   => 'bottom-left', // Text position: bottom-left, bottom-right, top-left, top-right, center
    'font_color'      => [255, 255, 255],   // Text color as RGB array (white)
    'shadow_color'    => [0, 0, 0],         // Shadow color as RGB array (black)
    'cache_ttl'       => 900,     // Cache duration in seconds (15 minutes)
    'fallback_credit' => '',      // Optional manual fallback credit, leave empty to extract from filename
    'delimiter'       => '__'     // Delimiter used in filenames to separate artist credit (2 underscores)
];
