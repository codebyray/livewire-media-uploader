<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Model Namespaces
    |--------------------------------------------------------------------------
    | Define the namespaces where your Eloquent models are located.
    |
    | When passing a class name (e.g., model="Product") to the Livewire component,
    | the package will iterate through these namespaces to resolve the
    | Fully Qualified Class Name (FQCN).
    |
    | Useful if your models are organized across different directories,
    | such as "App\Models" or "App\Domain\Models".
    |
    | Example:
    |   'model_namespaces' => ['App\\Models', 'App\\Domain\\Models'],
    */
    'model_namespaces' => [
        'App\\Models',
    ],

    /*
    |--------------------------------------------------------------------------
    | Active UI Theme
    |--------------------------------------------------------------------------
    | Controls which Blade view is used to render the uploader UI.
    | Accepts a key from the "themes" map below.
    |
    | Set globally via .env:
    |   MEDIA_UPLOADER_THEME=tailwind
    |
    | You can override per-instance in your Livewire component usage:
    |   <livewire:media-uploader
    |       :for="$post"
    |       collection="images"
    |       theme="custom"
    |   />
    |
    | Default: 'tailwind'
    */
    'theme' => env('MEDIA_UPLOADER_THEME', 'tailwind'),

    /*
    |--------------------------------------------------------------------------
    | Theme View Map
    |--------------------------------------------------------------------------
    | A list of available uploader themes. Keys are theme identifiers, values
    | are the fully qualified Blade view names to render the component.
    |
    | Custom themes:
    | - Create a new folder under the resources/views/vendor/media-uploader/themes directory, e.g. "custom".
    | - Copy one of the supplied theme files (media-uploader.blade.php) from "tailwind" or "bootstrap"
    |   into the new "custom" folder. Keep the file name unchanged
    |   (e.g. media-uploader.blade.php).
    | - Edit the copied file as needed.
    | - Register it here using the folder name as the key and the view path as the value:
    |   'custom' => 'media-uploader::themes.custom.media-uploader',
    | - Make sure to clear your view and config cache after implementing the new theme
    |
    | Usage:
    | - Globally via .env (see MEDIA_UPLOADER_THEME above), or
    | - Per instance:
    |   <livewire:media-uploader
    |       :for="$post"
    |       collection="images"
    |       theme="custom"
    |   />
    */
    'themes' => [
        'tailwind' => 'media-uploader::themes.tailwind.media-uploader',
        'bootstrap' => 'media-uploader::themes.bootstrap.media-uploader',
        // 'custom' => 'media-uploader::themes.custom.media-uploader',
    ],

    /*
    |--------------------------------------------------------------------------
    | Accept Attribute Source
    |--------------------------------------------------------------------------
    | When true, the uploader's HTML "accept" attribute is computed from the
    | selected preset's "types" or "mimes" defined below. When false, the
    | component won't auto-generate the "accept" attribute from config.
    */
    'accept_from_config' => true,

    /*
    |--------------------------------------------------------------------------
    | Collection → Preset Mapping
    |--------------------------------------------------------------------------
    | Define logical collections (used by your forms/models) and map each one
    | to a preset name from the "presets" section below.
    | Example: uploading to the "avatars" collection will apply the "images"
    | preset's validation constraints.
    */
    'collections' => [
        'avatars' => 'images',
        'images' => 'images',
        'attachments' => 'docs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Presets
    |--------------------------------------------------------------------------
    | Each preset defines:
    |  - types: Comma-separated file extensions (used for UI accept lists).
    |  - mimes: Comma-separated MIME types (useful for strict validation).
    |  - max_kb: Maximum file size in kilobytes.
    |
    | All values can be overridden via env variables for environment-specific
    | behavior. If an env var is missing, the default value is used.
    */
    'presets' => [
        /*
        |----------------------------------------------------------------------
        | Images Preset
        |----------------------------------------------------------------------
        | Env:
        |  - MEDIA_TYPES_IMAGES   (e.g. "jpg,jpeg,png,webp,avif,gif")
        |  - MEDIA_MIMES_IMAGES   (e.g. "image/jpeg,image/png,...")
        |  - MEDIA_MAXKB_IMAGES   (integer KB, e.g. 10240 for 10 MB)
        */
        'images' => [
            'types' => env('MEDIA_TYPES_IMAGES', 'jpg,jpeg,png,webp,avif,gif'),
            'mimes' => env('MEDIA_MIMES_IMAGES', 'image/jpeg,image/png,image/webp,image/avif,image/gif'),
            'max_kb' => (int) env('MEDIA_MAXKB_IMAGES', 10240),
        ],

        // ... existing code ...
        /*
        |----------------------------------------------------------------------
        | Documents Preset
        |----------------------------------------------------------------------
        | Env:
        |  - MEDIA_TYPES_DOCS     (e.g. "pdf,doc,docx,xls,xlsx,ppt,pptx,txt")
        |  - MEDIA_MIMES_DOCS     (e.g. "application/pdf,application/msword,...")
        |  - MEDIA_MAXKB_DOCS     (integer KB, e.g. 20480 for 20 MB)
        */
        'docs' => [
            'types' => env('MEDIA_TYPES_DOCS', 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt'),
            'mimes' => env('MEDIA_MIMES_DOCS', 'application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/plain'),
            'max_kb' => (int) env('MEDIA_MAXKB_DOCS', 20480),
        ],

        /*
        |----------------------------------------------------------------------
        | Videos Preset
        |----------------------------------------------------------------------
        | Env:
        |  - MEDIA_TYPES_VIDEOS   (e.g. "mp4,mov,webm")
        |  - MEDIA_MIMES_VIDEOS   (e.g. "video/mp4,video/quicktime,video/webm")
        |  - MEDIA_MAXKB_VIDEOS   (integer KB, e.g. 102400 for 100 MB)
        */
        'videos' => [
            'types' => env('MEDIA_TYPES_VIDEOS', 'mp4,mov,webm'),
            'mimes' => env('MEDIA_MIMES_VIDEOS', 'video/mp4,video/quicktime,video/webm'),
            'max_kb' => (int) env('MEDIA_MAXKB_VIDEOS', 102400),
        ],

        /*
        |----------------------------------------------------------------------
        | Default Preset
        |----------------------------------------------------------------------
        | A catch-all preset combining common image and document formats.
        | Env:
        |  - MEDIA_TYPES_DEFAULT
        |  - MEDIA_MIMES_DEFAULT
        |  - MEDIA_MAXKB_DEFAULT
        */
        'default' => [
            'types' => env('MEDIA_TYPES_DEFAULT', 'jpg,jpeg,png,webp,avif,gif,pdf,doc,docx,xls,xlsx,ppt,pptx,txt'),
            'mimes' => env('MEDIA_MIMES_DEFAULT', 'image/jpeg,image/png,image/webp,image/avif,image/gif,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/plain'),
            'max_kb' => (int) env('MEDIA_MAXKB_DEFAULT', 10240),
        ],
    ],
];
