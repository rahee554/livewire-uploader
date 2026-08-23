<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Defaults used by WithUploader::storeUpload() when the caller does not
    | pass an explicit disk or directory.
    |
    */

    'disk' => env('AF_UPLOADER_DISK', 'public'),

    'directory' => env('AF_UPLOADER_DIRECTORY', 'uploads'),

    /*
    |--------------------------------------------------------------------------
    | Theme
    |--------------------------------------------------------------------------
    |
    | auto    Follow the host application. Bootstrap's data-bs-theme,
    |         Tailwind's .dark class, data-theme, data-color-mode and the other
    |         common conventions are all recognised on <html> and <body>. When
    |         the application expresses no preference, the operating system
    |         setting decides. An in-page theme toggle is picked up live.
    |
    | system  Ignore the host and follow the OS setting only.
    | light   Always light.
    | dark    Always dark.
    |
    | Applications can also drive it at runtime:
    |
    |     window.AFUploaderTheme.set('dark')
    |
    */

    'theme' => env('AF_UPLOADER_THEME', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Component defaults
    |--------------------------------------------------------------------------
    |
    | Every <x-af-uploader> starts from these values. Any prop on the tag
    | overrides the matching key here.
    |
    */

    'defaults' => [
        'accept' => 'image/*',
        'max_size' => 10,          // megabytes; 0 disables the check
        'max_files' => null,       // only meaningful with multiple
        'auto_upload' => true,
        'preview' => true,
        'variant' => 'plain',      // plain | squared | rect | circled | inline
        'label' => 'Drop file or click',

        // Show "1.4 MB -> 220 KB (-84%)" under the filename when the browser
        // shrank the image before uploading. Off by default; switch it on per
        // uploader with show-savings, or globally here.
        'show_savings' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Client-side image processing
    |--------------------------------------------------------------------------
    |
    | Applied in the browser before the bytes ever reach Livewire, so the
    | upload is smaller and the server never sees the original.
    |
    | convert     null keeps the source format; otherwise webp | jpeg | png
    | quality     0..1. Ignored when lossless is true.
    |
    | Two independent ceilings on the encoded output:
    |
    | target_size an absolute budget, e.g. '500KB' or '1.5MB'
    | compress    a share of the original, e.g. '40%'
    |
    | Set either or both; when both are set the tighter one applies. The
    | encoder trades quality first and resolution second to reach the budget.
    |
    */

    'image' => [
        'convert' => null,
        'quality' => 0.82,
        'lossless' => false,
        'max_width' => 2000,
        'max_height' => null,
        'target_size' => null,

        // Percentage ceiling: keep at most this share of the original size.
        // Combine with target_size and the tighter of the two applies.
        'compress' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Editor (crop / rotate / zoom)
    |--------------------------------------------------------------------------
    |
    | ratio accepts '16/9', '4:3', '1', or null for free-form.
    | When lock_ratio is true the ratio buttons are hidden from the editor.
    |
    */

    'editor' => [
        'enabled' => false,
        'ratio' => null,
        'circle' => false,
        'lock_ratio' => false,
        'format' => 'image/webp',
        'ratios' => ['1', '4/3', '3/2', '16/9', 'free'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Testbed page
    |--------------------------------------------------------------------------
    |
    | A page exercising every uploader configuration, used by the package's
    | browser tests and handy for manual checks.
    |
    | enabled: null means "local and testing only". Set it to false to switch
    | the route off everywhere, or true to expose it deliberately.
    |
    */

    'testbed' => [
        'enabled' => env('AF_UPLOADER_TESTBED', null),
        'path' => 'af-uploader/testbed',
    ],

    /*
    |--------------------------------------------------------------------------
    | Server-side validation
    |--------------------------------------------------------------------------
    |
    | The browser checks are a convenience only — they are trivially bypassed
    | by posting straight at Livewire's upload endpoint. These rules are the
    | actual enforcement boundary and run inside storeUpload().
    |
    | blocked_extensions is checked against the client filename *and* against
    | the extension guessed from the file's real MIME type.
    |
    */

    'validation' => [
        'enforce' => true,

        'blocked_extensions' => [
            'php', 'phar', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8',
            'exe', 'com', 'bat', 'cmd', 'sh', 'bash', 'cgi', 'pl',
            'jsp', 'asp', 'aspx', 'htaccess',
        ],

        // SVG can carry script. Allow it only if you sanitise it yourself.
        'allow_svg' => false,

        // Hard ceiling in megabytes, independent of any per-component max-size.
        // Null falls back to the component value.
        'max_size' => null,
    ],

];
