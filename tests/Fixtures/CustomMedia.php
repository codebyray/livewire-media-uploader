<?php

namespace Codebyray\LivewireMediaUploader\Tests\Fixtures;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CustomMedia extends Media
{
    protected $table = 'custom_media';
}
