<?php

namespace Codebyray\LivewireMediaUploader\Tests\Fixtures;

class SingleFilePost extends TestPost
{
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')->singleFile();
    }
}
