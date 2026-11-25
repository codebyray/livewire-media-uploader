<?php

namespace Codebyray\LivewireMediaUploader\Tests\Fixtures;

use Spatie\MediaLibrary\Support\FileNamer\DefaultFileNamer;

class PrefixedFileNamer extends DefaultFileNamer
{
    public function originalFileName(string $fileName): string
    {
        return 'asset-'.parent::originalFileName($fileName);
    }
}
