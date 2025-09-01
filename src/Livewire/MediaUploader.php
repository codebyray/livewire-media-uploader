<?php

namespace Codebyray\LivewireMediaUploader\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Validation\Rules\File as FileRule;

class MediaUploader extends Component
{
    use WithFileUploads;

    public array   $namespaces          = ['App\\Models'];
    public array   $aliases             = [];
    public ?string $collection          = 'images';
    public ?string $disk                = null;
    public bool    $multiple            = true;
    public ?string $accept              = null;
    public bool    $showList            = true;
    public int     $maxSizeKb           = 500;
    public array   $uploads             = [];
    public array   $selected            = [];
    public array   $items               = [];
    public string  $onNameConflict      = 'rename';
    public bool    $skipExactDuplicates = false;
    public ?string $preset              = null;
    public array   $allowedTypes        = [];
    public array   $allowedMimes        = [];
    public string  $attachedFilesTitle  = "Current gallery";
    public array   $editing             = [];
    public array   $pendingMeta         = [];
    public ?int    $confirmingDeleteId  = null;
    public string  $allowedLabel        = '';
    public ?string $theme               = null;
    public ?string $pendingModelClass   = null; // new
    public ?string $channel             = null;

    #[Locked]
    public ?string         $resolvedModelClass = null;
    #[Locked]
    public int|string|null $resolvedModelId    = null;

    public function mount(
        $for = null,
        ?string $model = null,
        int|string|null $id = null,
        ?string $collection = 'images',
        ?string $disk = null,
        bool $multiple = true,
        ?string $accept = null,
        bool $showList = true,
        int $maxSizeKb = 10240,
        array $namespaces = null,
        array $aliases = null,
        string $attachedFilesTitle = "Attached media",
        ?string $channel = null,
    ): void {
        if ($namespaces !== null) $this->namespaces = $namespaces;
        if ($aliases !== null)    $this->aliases    = $aliases;

        $this->channel            = $channel;
        $this->collection         = $collection ?: 'images';
        $this->disk               = $disk;
        $this->multiple           = $multiple;
        $this->accept             = $accept;
        $this->showList           = $showList;
        $this->maxSizeKb          = $maxSizeKb;
        $this->attachedFilesTitle = $attachedFilesTitle;

        $this->loadPresetFromConfig();

        if ($for instanceof Model) {
            if (! $for->exists) abort(422, 'Target model must be saved before attaching media.');
            if (! $for instanceof HasMedia) abort(422, class_basename($for) . ' must implement Spatie\\MediaLibrary\\HasMedia.');
            $this->resolvedModelClass = $for::class;
            $this->resolvedModelId    = (string) $for->getKey();
        } elseif ($model) {
            $fqcn = $this->resolveModelClass($model);
            if (! in_array(HasMedia::class, class_implements($fqcn), true)) {
                abort(422, class_basename($fqcn) . ' must implement Spatie\\MediaLibrary\\HasMedia.');
            }
            if ($id !== null) {
                $fqcn::findOrFail($id);
                $this->resolvedModelClass = $fqcn;
                $this->resolvedModelId    = (string) $id;
            } else {
                // PENDING: we only know the class for now
                $this->pendingModelClass = $fqcn;
            }
        } else {
            abort(422, 'Provide either :for="$model" or model="Class" (id optional).');
        }

        if ($this->showList && $this->hasTarget()) {
            $this->loadItems();
        }
    }

    protected function metaRules(int $mediaId): array
    {
        return [
            "editing.$mediaId.caption"     => ['nullable', 'string', 'max:255'],
            "editing.$mediaId.description" => ['nullable', 'string', 'max:2000'],
            "editing.$mediaId.order"       => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function queueMetaRules(): array
    {
        return [
            'pendingMeta.*.caption'     => ['nullable', 'string', 'max:255'],
            'pendingMeta.*.description' => ['nullable', 'string', 'max:2000'],
            'pendingMeta.*.order'       => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function nextOrder(): int
    {
        $model = $this->target();
        $collection = $this->collection ?? 'default';

        return (int) ($model->media()
                ->where('collection_name', $collection)
                ->max('order_column') ?? 0) + 1;
    }

    protected function csvToArray(?string $csv): array
    {
        return collect(explode(',', (string) $csv))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->values()
            ->all();
    }

    protected function buildAccept(array $mimes, array $exts): ?string
    {
        $a = [];
        foreach ($mimes as $m) $a[] = $m;
        foreach ($exts as $e)  $a[] = '.' . ltrim($e, '.');
        return $a ? implode(',', array_unique($a)) : null;
    }

    protected function loadPresetFromConfig(): void
    {
        $presetKey = $this->preset
            ?? config('media-uploader.collections.' . ($this->collection ?? 'default'))
            ?? 'default';

        $cfg = (array) config("media-uploader.presets.$presetKey", []);
        $this->allowedTypes = $this->csvToArray($cfg['types'] ?? '');
        $this->allowedMimes = $this->csvToArray($cfg['mimes'] ?? '');

        if (empty($this->maxSizeKb) && isset($cfg['max_kb'])) {
            $this->maxSizeKb = (int) $cfg['max_kb'];
        }

        if (empty($this->accept) && config('media-uploader.accept_from_config')) {
            $accept = $this->buildAccept($this->allowedMimes, $this->allowedTypes);
            if ($accept) $this->accept = $accept;
        }

        $this->refreshAllowedLabel();
    }

    protected function resolveModelClass(string $value): string
    {
        $value = trim($value);

        if (isset($this->aliases[$value]) && class_exists($this->aliases[$value])) {
            return $this->aliases[$value];
        }

        if (class_exists($value)) return $value;

        if ($morphed = Relation::getMorphedModel($value)) {
            return $morphed;
        }

        $raw   = trim($value, " \t\n\r\0\x0B\\/.");
        $parts = preg_split('/[.\/\\\\]+/', $raw) ?: [];
        $parts = array_map(fn ($p) => Str::studly($p), array_filter($parts));

        if (empty($parts)) abort(422, "Unknown model class/alias [{$value}].");

        $candidates = [];
        foreach ($this->namespaces as $ns) {
            $candidates[] = rtrim($ns, '\\') . '\\' . implode('\\', $parts);
            if (count($parts) >= 1) {
                $alt = $parts;
                $alt[count($alt)-1] = Str::studly(Str::singular($alt[count($alt)-1]));
                $candidates[] = rtrim($ns, '\\') . '\\' . implode('\\', $alt);
            }
            if (count($parts) === 1) {
                $candidates[] = rtrim($ns, '\\') . '\\' . $parts[0];
                $candidates[] = rtrim($ns, '\\') . '\\' . Str::studly(Str::singular($parts[0]));
            }
        }

        foreach (array_unique($candidates) as $fqcn) {
            if (class_exists($fqcn)) return $fqcn;
        }

        abort(422, "Unknown model class/alias [{$value}].");
    }

    protected function hasTarget(): bool
    {
        return !empty($this->resolvedModelClass)
            && $this->resolvedModelId !== null
            && $this->resolvedModelId !== '';
    }

    protected function target(): ?Model
    {
        if (! $this->hasTarget()) return null;
        $cls = $this->resolvedModelClass;
        return $cls::findOrFail($this->resolvedModelId);
    }

    public function updatedUploads(): void
    {
        $list = is_array($this->uploads) ? $this->uploads : [];
        $baseOrder = $this->nextOrder();

        $this->selected = collect($list)->filter()->map(function ($f, $i) use ($baseOrder)  {
            $name = method_exists($f, 'getClientOriginalName')
                ? $f->getClientOriginalName()
                : (property_exists($f, 'name') ? $f->name : 'file');

            $size = method_exists($f, 'getSize')
                ? $f->getSize()
                : (property_exists($f, 'size') ? $f->size : 0);

            $this->pendingMeta[$i] = $this->pendingMeta[$i] ?? [
                'caption'     => null,
                'description' => null,
                'order'       => $baseOrder + $i,
            ];

            return [
                'queue_key' => $i,
                'name'      => (string) $name,
                'size'      => (int) $size,
                'is_image'  => $this->isImageLike($f),
            ];
        })->values()->all();
    }

    public function clearQueue(): void
    {
        $this->reset(['uploads', 'selected']);
        $this->pendingMeta = [];
    }

    public function uploadFiles(): void
    {
        $fileRule = FileRule::defaults();
        if (($this->preset ?? null) === 'images') $fileRule = $fileRule->image();
        if (!empty($this->allowedTypes)) $fileRule = $fileRule->types($this->allowedTypes);
        if (!empty($this->maxSizeKb))    $fileRule = $fileRule->max($this->maxSizeKb);

        $perFileRules = ['required', $fileRule];
        if (!empty($this->allowedMimes)) $perFileRules[] = 'mimetypes:' . implode(',', $this->allowedMimes);

        $this->validate([
                            'uploads'   => ['required', 'array'],
                            'uploads.*' => $perFileRules,
                        ] + $this->queueMetaRules());

        if (! $this->hasTarget()) {
            session()->flash('media_uploader_notice', 'Files queued. They will be attached after you save.');
            return;
        }

        $model      = $this->target();
        $collection = $this->collection ?? 'default';
        $added      = $replaced = $skipped = $renamed = 0;

        foreach ($this->uploads as $i => $file) {
            $originalName = method_exists($file, 'getClientOriginalName')
                ? $file->getClientOriginalName()
                : (property_exists($file, 'name') ? $file->name : 'file');

            $hash = $this->skipExactDuplicates ? $this->fileSha256($file) : null;
            if ($this->skipExactDuplicates && $hash) {
                $existsSameHash = $model->media()
                    ->where('collection_name', $collection)
                    ->where('custom_properties->sha256', $hash)
                    ->first();

                if ($existsSameHash) { $skipped++; continue; }
            }

            $targetName = $originalName;
            if ($this->onNameConflict !== 'allow') {
                if ($conflict = $this->existingByName($model, $collection, $targetName)) {
                    switch ($this->onNameConflict) {
                        case 'replace': $conflict->delete(); $replaced++; break;
                        case 'skip':    $skipped++; continue 2;
                        case 'rename':  $targetName = $this->uniqueFileName($model, $collection, $originalName); $renamed++; break;
                    }
                }
            }

            $adder = $model->addMedia($file)->usingFileName($targetName);
            if ($hash) $adder->withCustomProperties(['sha256' => $hash]);

            $media = $this->disk
                ? $adder->toMediaCollection($collection, $this->disk)
                : $adder->toMediaCollection($collection);

            $meta = $this->pendingMeta[$i] ?? ['caption' => null, 'description' => null, 'order' => null];

            $media->setCustomProperty('caption', $meta['caption'] ?: null);
            $media->setCustomProperty('description', $meta['description'] ?: null);

            if (!empty($meta['order'])) {
                $media->order_column = (int) $meta['order'];
            }
            $media->save();

            $added++;
        }

        $this->clearQueue();
        $this->pendingMeta = [];
        if ($this->showList) $this->loadItems();

        $parts = [];
        if ($added)    $parts[] = "{$added} added";
        if ($renamed)  $parts[] = "{$renamed} renamed";
        if ($replaced) $parts[] = "{$replaced} replaced";
        if ($skipped)  $parts[] = "{$skipped} skipped";
        $msg = $parts ? ('Upload complete: ' . implode(', ', $parts) . '.') : 'Nothing uploaded.';

        $this->dispatch('media-uploaded');
        session()->flash('media_uploader_notice', $msg);
    }

    #[On('media:attach')]
    public function attachTo(
        string $model,
        int|string $id,
        ?string $collection = null,
        ?string $disk = null,
        ?string $channel = null // optional scoping
    ): void {
        // If you use channels, ignore events for other uploaders
        if ($this->channel && $this->channel !== $channel) return;

        $fqcn = $this->resolveModelClass($model);
        if (! in_array(HasMedia::class, class_implements($fqcn), true)) {
            abort(422, class_basename($fqcn) . ' must implement Spatie\\MediaLibrary\\HasMedia.');
        }
        $fqcn::findOrFail($id);

        $this->resolvedModelClass = $fqcn;
        $this->resolvedModelId    = (string) $id;

        $originalCollection = $this->collection;
        $originalDisk       = $this->disk;
        if ($collection) $this->collection = $collection;
        if ($disk)       $this->disk       = $disk;

        if (empty($this->uploads)) {
            // Nothing queued; just let the parent know we’re ready
            $this->dispatch('media-attached', model: $fqcn, id: (string) $id);
            $this->collection = $originalCollection;
            $this->disk       = $originalDisk;
            return;
        }

        $this->uploadFiles(); // will now succeed because target is set

        $this->collection = $originalCollection;
        $this->disk       = $originalDisk;

        $this->dispatch('media-attached', model: $fqcn, id: (string) $id);
    }

    public function remove(int $mediaId): void
    {
        $media = Media::findOrFail($mediaId);

        $belongs = $media->model_type === $this->resolvedModelClass
            && (string) $media->model_id === (string) $this->resolvedModelId;

        abort_unless($belongs, 403, 'This media does not belong to the specified model.');

        $media->delete();

        if ($this->showList) $this->loadItems();

        $this->dispatch('media-deleted', id: $mediaId);
    }

    public function removeFromQueue(int $queueKey): void
    {
        unset($this->uploads[$queueKey], $this->pendingMeta[$queueKey]);
        $this->uploads     = array_values($this->uploads);
        $this->pendingMeta = array_values($this->pendingMeta);
        $this->updatedUploads();
    }

    protected function isImageLike(mixed $file): bool
    {
        if ($file instanceof TemporaryUploadedFile) {
            $mime = $file->getClientMimeType() ?: $file->getMimeType();
            if (is_string($mime) && str_starts_with($mime, 'image/')) return true;

            $ext = strtolower($file->getClientOriginalExtension()
                                  ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
            return in_array($ext, ['jpg','jpeg','png','gif','webp','bmp','svg','tif','tiff','avif'], true);
        }
        return false;
    }

    public function loadItems(): void
    {
        if (! $this->hasTarget()) {
            $this->items = [];
            return;
        }

        $model = $this->target();
        $collection = $this->collection ?? 'default';

        $this->items = $model->media()
            ->where('collection_name', $collection)
            ->orderBy('order_column')
            ->get()
            ->map(function (Media $m) {
                $thumb = $m->hasGeneratedConversion('thumb') ? $m->getUrl('thumb') : $m->getUrl();
                return [
                    'id'          => $m->id,
                    'file_name'   => $m->file_name,
                    'name'        => $m->name,
                    'url'         => $m->getUrl(),
                    'thumb'       => $thumb,
                    'size'        => $m->size,
                    'mime'        => $m->mime_type,
                    'created'     => $m->created_at?->toDateTimeString(),
                    'caption'     => $m->getCustomProperty('caption'),
                    'description' => $m->getCustomProperty('description'),
                    'order'       => (int) $m->order_column,
                ];
            })->toArray();
    }

    public function startEdit(int $mediaId): void
    {
        $item = collect($this->items)->firstWhere('id', $mediaId);
        if (! $item) return;

        $this->editing[$mediaId] = [
            'caption'     => $item['caption'] ?? null,
            'description' => $item['description'] ?? null,
            'order'       => $item['order'] ?? null,
        ];
    }

    public function saveEdit(int $mediaId): void
    {
        $this->validate($this->metaRules($mediaId));

        $media = Media::findOrFail($mediaId);
        $belongs = $media->model_type === $this->resolvedModelClass
            && (string) $media->model_id === (string) $this->resolvedModelId;
        abort_unless($belongs, 403, 'This media does not belong to the specified model.');

        $meta = $this->editing[$mediaId] ?? ['caption' => null, 'description' => null, 'order' => null];

        $media->setCustomProperty('caption', $meta['caption'] ?: null);
        $media->setCustomProperty('description', $meta['description'] ?: null);

        if (!empty($meta['order'])) {
            $media->order_column = (int) $meta['order'];
        }
        $media->save();

        unset($this->editing[$mediaId]);
        if ($this->showList) $this->loadItems();

        $this->dispatch('media-meta-updated', id: $mediaId);
        session()->flash('media_uploader_notice', 'Media details updated.');
    }

    public function cancelEdit(int $mediaId): void
    {
        unset($this->editing[$mediaId]);
    }

    protected function fileSha256(mixed $file): ?string
    {
        $path = method_exists($file, 'getRealPath') ? $file->getRealPath() : null;
        if (! $path || ! is_file($path)) return null;

        return hash_file('sha256', $path);
    }

    protected function existingByName(Model $model, string $collection, string $fileName): ?Media
    {
        return $model->media()
            ->where('collection_name', $collection)
            ->where('file_name', $fileName)
            ->first();
    }

    protected function uniqueFileName(Model $model, string $collection, string $original): string
    {
        $base      = pathinfo($original, PATHINFO_FILENAME);
        $ext       = pathinfo($original, PATHINFO_EXTENSION);
        $suffix    = 0;
        $candidate = $original;

        while (
        $model->media()
            ->where('collection_name', $collection)
            ->where('file_name', $candidate)
            ->exists()
        ) {
            $suffix++;
            $candidate = $base . ' (' . $suffix . ')' . ($ext ? ".{$ext}" : '');
        }

        return $candidate;
    }

    public function confirmDelete(int $mediaId): void
    {
        $this->confirmingDeleteId = $mediaId;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function deleteConfirmed(): void
    {
        $id = $this->confirmingDeleteId;
        $this->confirmingDeleteId = null;

        if ($id) $this->remove($id);
    }

    protected function refreshAllowedLabel(): void
    {
        $types = is_array($this->allowedTypes)
            ? $this->allowedTypes
            : explode(',', (string) $this->allowedTypes);

        $exts = array_values(array_unique(array_filter(array_map(function ($t) {
            $t = strtolower(trim($t));
            if ($t === '') return null;
            if (str_starts_with($t, '.')) $t = substr($t, 1);
            if (!preg_match('/^[a-z0-9]+$/', $t)) return null;
            return strtolower($t);
        }, $types))));

        $this->allowedLabel = $exts ? implode(', ', $exts) : '';
    }

    public function render(): View
    {
        return view('media-uploader::livewire.media-uploader');
    }
}
