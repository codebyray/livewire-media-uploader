<?php

namespace Codebyray\LivewireMediaUploader\Tests\Fixtures;

use Codebyray\LivewireMediaUploader\Livewire\MediaUploader;

/**
 * Test-only subclass to make duplicate detection deterministic:
 * forces the same hash for every uploaded file.
 */
class TestableMediaUploader extends MediaUploader
{
    public function prepareUploadedFileForTest(mixed $file, string $originalName): string
    {
        return $this->prepareUploadedFile($file, $originalName);
    }

    protected function fileSha256(mixed $file): ?string
    {
        return 'TEST-HASH-0001';
    }
}
