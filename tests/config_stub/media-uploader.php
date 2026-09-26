<?php

return [
    'accept_from_config' => true,

    'watermark' => [
        'enabled' => false,
        'path' => null,
        'position' => 'bottom-right',
        'padding_x' => 0,
        'padding_y' => 0,
        'padding_unit' => 'pixel',
        'width' => 10,
        'width_unit' => 'pixel',
        'height' => 10,
        'height_unit' => 'pixel',
        'fit' => 'contain',
        'opacity' => 100,
    ],

    'collections' => [
        'avatars' => 'images',
        'images' => 'images',
        'attachments' => 'docs',
    ],

    'presets' => [
        'images' => [
            'types' => 'jpg,jpeg,png,webp,avif,gif',
            'mimes' => 'image/jpeg,image/png,image/webp,image/avif,image/gif',
            'max_kb' => 10240,
        ],
        'docs' => [
            'types' => 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt',
            'mimes' => 'application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/plain',
            'max_kb' => 20480,
        ],
        'default' => [
            'types' => 'jpg,jpeg,png,webp,avif,gif,pdf,doc,docx,xls,xlsx,ppt,pptx,txt',
            'mimes' => 'image/jpeg,image/png,image/webp,image/avif,image/gif,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/plain',
            'max_kb' => 10240,
        ],
    ],
];
