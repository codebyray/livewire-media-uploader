<div>
    <livewire:media-uploader
        model="Codebyray\LivewireMediaUploader\Tests\Fixtures\JpegOnlyPost"
        :theme="$theme"
        authorizeAbility="manage-media"
        channel="create-gallery"
    />
    <button type="button" wire:click="save">Save form</button>
    @if($attached)
        <p>Deferred attachment complete</p>
    @endif
</div>
