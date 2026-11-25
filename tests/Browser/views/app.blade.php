<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Uploader browser tests</title>
    <link rel="stylesheet" href="/assets/{{ $theme }}.css">
    <style>[x-cloak] { display: none !important; } body { padding: 24px; } section { margin-bottom: 32px; }</style>
    @livewireStyles
</head>
<body>
    <section data-testid="left">
        <livewire:media-uploader :for="$left" :theme="$theme" channel="left" attachedFilesTitle="Left gallery" />
    </section>
    <section data-testid="right">
        <livewire:media-uploader :for="$right" :theme="$theme" channel="right" attachedFilesTitle="Right gallery" />
    </section>
    <section data-testid="create">
        <livewire:browser-create :theme="$theme" />
    </section>
    @livewireScripts
</body>
</html>
