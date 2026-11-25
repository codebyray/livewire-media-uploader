<?php

namespace Codebyray\LivewireMediaUploader\Tests\Browser;

use Codebyray\LivewireMediaUploader\Tests\Fixtures\JpegOnlyPost;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class CreateForm extends Component
{
    public string $theme = 'tailwind';

    public bool $attached = false;

    public function save(): void
    {
        $post = JpegOnlyPost::create(['title' => 'Deferred gallery']);
        $this->dispatch('media:attach', model: JpegOnlyPost::class, id: $post->id, channel: 'create-gallery');
    }

    #[On('media-attached')]
    public function afterAttached(?string $channel = null): void
    {
        if ($channel === 'create-gallery') {
            $this->attached = true;
        }
    }

    public function render(): View
    {
        return view('uploader-browser::create');
    }
}
