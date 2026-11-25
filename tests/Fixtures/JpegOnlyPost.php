<?php

namespace Codebyray\LivewireMediaUploader\Tests\Fixtures;

class JpegOnlyPost extends TestPost
{
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')->acceptsMimeTypes(['image/jpeg']);
    }
}
