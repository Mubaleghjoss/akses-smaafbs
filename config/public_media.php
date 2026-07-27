<?php

return [
    'enabled' => env('PUBLIC_IMAGE_OPTIMIZATION_ENABLED', true),
    'webp_quality' => (int) env('PUBLIC_IMAGE_WEBP_QUALITY', 78),
    'profiles' => [
        'material' => [
            'width' => 1280,
            'height' => 1280,
            'thumbnail_width' => 640,
            'thumbnail_height' => 360,
        ],
        'question' => [
            'width' => 1280,
            'height' => 1280,
        ],
        'structure' => [
            'width' => 960,
            'height' => 1200,
            'thumbnail_width' => 240,
            'thumbnail_height' => 300,
        ],
        'content' => [
            'width' => 1280,
            'height' => 1280,
        ],
        'logo' => [
            'width' => 256,
            'height' => 256,
        ],
    ],
];
