<div
    x-data="{
        isDropping: false,
        progress: 0,
        preview: { open: false, url: '', alt: '' },
        openPreview(url, alt='') { this.preview = { open: true, url, alt } },
        closePreview() { this.preview.open = false; this.preview.url=''; this.preview.alt='' }
    }"
    x-on:livewire-upload-start="progress = 0"
    x-on:livewire-upload-progress="progress = $event.detail.progress"
    x-on:livewire-upload-error="progress = 0"
    x-on:livewire-upload-finish="progress = 100; setTimeout(()=>progress=0, 900)"
    class="d-flex flex-column gap-3"
>
    @if (session('media_uploader_notice'))
        <div
            x-data="{ open: true }"
            x-cloak
            x-show="open"
            x-transition.opacity.duration.150ms
            wire:key="flash-notice"
            role="alert"
            class="alert alert-success alert-dismissible fade show mb-0 position-relative"
        >
            <div class="pe-4">
                {{ session('media_uploader_notice') }}
            </div>

            <!-- Dismiss -->
            <button
                type="button"
                @click="open = false"
                class="btn-close position-absolute top-0 end-0 m-2"
                aria-label="Dismiss notification"
            ></button>
        </div>
    @endif

    <div class="fw-medium text-body mb-0">Manage gallery</div>

    <!-- Dropzone -->
    <div
        class="rounded-3 border p-0 bg-white"
        :class="isDropping ? 'border-success' : 'border-secondary'"
        style="border-style: dashed;"
        @dragover.prevent="isDropping = true"
        @dragleave.prevent="isDropping = false"
        @drop.prevent="
            isDropping = false;
            const list = ($event.dataTransfer && $event.dataTransfer.files) || null;
            if (!list || !list.length) return;

            let dropped = Array.from(list).filter(f => f && f instanceof File && typeof f.name === 'string');
            @if (! $multiple)
                dropped = dropped.slice(0, 1);
            @endif

            @if (! empty($accept))
                const rules = @js(collect(explode(',', $accept))->map(fn($s) => trim($s))->filter()->values());
                const matchesRule = (file, rule) => {
                    if (!rule) return true;
                    if (rule.endsWith('/*')) {
                        const prefix = rule.slice(0, -1);
                        return typeof file.type === 'string' && file.type.startsWith(prefix);
                    }
                    if (rule.includes('/')) return file.type === rule;
                    const ext = '.' + (file.name.split('.').pop() || '').toLowerCase();
                    const normalized = rule.startsWith('.') ? rule.toLowerCase() : ('.' + rule.toLowerCase());
                    return ext === normalized;
                };
                dropped = dropped.filter(f => rules.some(r => matchesRule(f, r)));
                if (!dropped.length) return;
            @endif

            try {
                const dt = new DataTransfer();
                dropped.forEach(f => dt.items.add(f));
                $refs.input.files = dt.files;
                $refs.input.dispatchEvent(new Event('change', { bubbles: true }));
            } catch (e) {
                console.error('File assign failed:', e);
            }
        "
    >
        <!-- Card body -->
        <div class="d-flex align-items-center justify-content-between gap-3 p-3">
            <div class="d-flex align-items-center gap-2">
                <svg class="text-secondary" style="width:24px;height:24px" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M7 16a4 4 0 118 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M12 12V4m0 0l-3 3m3-3l3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <rect x="3" y="12" width="18" height="8" rx="2" stroke="currentColor" stroke-width="2"/>
                </svg>
                <div class="small">
                    <div class="fw-semibold text-body">Drag &amp; drop files here</div>
                    <div class="text-muted">
                        or click to choose from your computer
                        @if(!empty($allowedLabel))
                            <span class="text-muted small mt-1"> - Allowed file types: {{ $allowedLabel }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <label class="btn btn-dark mb-0 d-inline-flex align-items-center gap-2">
                <span>Choose file<span @class(['', 's' => $multiple])>s</span></span>
                <input
                    x-ref="input"
                    type="file"
                    class="d-none"
                    wire:model="uploads"
                    @if($multiple) multiple @endif
                    @if($accept)   accept="{{ $accept }}" @endif
                />
            </label>
        </div>

        <!-- Progress -->
        <template x-if="progress > 0">
            <div class="px-3 pb-3">
                <div class="progress" role="progressbar" aria-label="Upload progress" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="progress">
                    <div class="progress-bar" :style="`width:${progress}%`"></div>
                </div>
                <div class="mt-1 small text-muted" x-text="progress + '%'"></div>
            </div>
        </template>

        <!-- Selected queue -->
        @if(!empty($selected) && count($selected) > 0)
            <div class="px-3 pb-3">
                <div class="small fw-semibold text-body">Ready to upload:</div>
                <div class="mt-2 d-flex flex-column gap-2">
                    @foreach ($selected as $sel)
                        @php
                            $temp = $uploads[$sel['queue_key']] ?? null;
                            $canPreview = ($sel['is_image'] ?? false) && $temp && method_exists($temp, 'temporaryUrl');
                        @endphp

                        <div class="rounded-3 border p-3 bg-light">
                            <!-- top row: name + size -->
                            <div class="d-flex align-items-center justify-content-between gap-3">
                                <span class="text-truncate small text-body">{{ $sel['name'] }}</span>
                                <span class="small text-muted">{{ number_format(($sel['size'] ?? 0)/1024, 1) }} KB</span>
                            </div>

                            <!-- form row -->
                            <div class="mt-3">
                                <div class="row g-3 align-items-start">
                                    <!-- thumb -->
                                    <div class="col-md-2">
                                        @if($canPreview)
                                            <img src="{{ $temp->temporaryUrl() }}" alt="" class="img-thumbnail" style="width:80px;height:80px;object-fit:cover;">
                                        @else
                                            <div class="d-grid border rounded text-muted align-items-center justify-content-center" style="width:80px;height:80px;place-items:center;">
                                                <svg viewBox="0 0 24 24" style="width:28px;height:28px" fill="none" stroke="currentColor" stroke-width="1.5">
                                                    <path d="M7 3h6l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/>
                                                    <path d="M13 3v5h5"/>
                                                </svg>
                                            </div>
                                        @endif
                                    </div>

                                    <!-- caption -->
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Caption</label>
                                        <input
                                            type="text"
                                            wire:model.lazy="pendingMeta.{{ $sel['queue_key'] }}.caption"
                                            class="form-control form-control-sm"
                                        />
                                        @error('pendingMeta.'.$sel['queue_key'].'.caption')
                                        <div class="form-text text-danger">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- description -->
                                    <div class="col-md-5">
                                        <label class="form-label small mb-1">Description</label>
                                        <input
                                            type="text"
                                            wire:model.lazy="pendingMeta.{{ $sel['queue_key'] }}.description"
                                            class="form-control form-control-sm"
                                        />
                                        @error('pendingMeta.'.$sel['queue_key'].'.description')
                                        <div class="form-text text-danger">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- order -->
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1">Order</label>
                                        <input
                                            type="number" min="1" step="1"
                                            wire:model.lazy="pendingMeta.{{ $sel['queue_key'] }}.order"
                                            placeholder="e.g. 1"
                                            class="form-control form-control-sm"
                                        />
                                        @error('pendingMeta.'.$sel['queue_key'].'.order')
                                        <div class="form-text text-danger">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- remove -->
                                    <div class="col-12 d-flex justify-content-end">
                                        <button
                                            type="button"
                                            wire:click="removeFromQueue({{ $sel['queue_key'] }})"
                                            wire:loading.attr="disabled"
                                            class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-2"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-3 d-flex gap-2">
                    <button
                        type="button"
                        wire:click="uploadFiles"
                        wire:loading.attr="disabled"
                        class="btn btn-primary d-inline-flex align-items-center gap-2"
                    >
                        Upload
                    </button>

                    <button
                        type="button"
                        wire:click="clearQueue"
                        class="btn btn-light d-inline-flex align-items-center gap-2"
                    >
                        Clear
                    </button>
                </div>
            </div>
        @endif
    </div>

    @if($showList)
        <div class="rounded-3 border overflow-hidden bg-white">
            <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                <div class="small fw-medium text-body">{{ $attachedFilesTitle ?? 'Current gallery' }}</div>
                <button
                    type="button"
                    wire:click="loadItems"
                    class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2"
                >
                    Refresh
                </button>
            </div>

            @if (count($items) === 0)
                <div class="p-3">
                    <p class="small text-muted mb-0">No gallery images yet.</p>
                </div>
            @else
                @if (count($items) === 0)
                    <div class="p-3">
                        <p class="small text-muted mb-0">No gallery images yet.</p>
                    </div>
                @else
                    @if ($listAll)
                        {{-- Grouped by collection --}}
                        @foreach ($groups as $collectionName => $collectionItems)
                            <div class="px-3 py-2 bg-light border-bottom">
                                <div class="small fw-semibold text-body text-uppercase">{{ $collectionName }}</div>
                            </div>

                            <ul class="list-group list-group-flush">
                                @foreach ($collectionItems as $m)
                                    @php
                                        $id = (int) $m['id'];
                                        $isEditing = isset($editing[$id]);
                                        $linkText = $m['caption'] ?: ($m['name'] ?: ($m['original_name'] ?? $m['file_name']));
                                    @endphp

                                    {{-- Paste your existing <li>...</li> markup here, unchanged, using $m --}}
                                    <li class="list-group-item p-3 d-flex gap-3 align-items-start">
                                        {{-- Thumbnail / icon --}}
                                        @if(!empty($m['thumb']))
                                            <img src="{{ $m['thumb'] }}" alt="" class="rounded border" style="width:64px;height:64px;object-fit:cover;">
                                        @else
                                            <div class="rounded border d-grid text-muted align-items-center justify-content-center" style="width:64px;height:64px;place-items:center;">
                                                <svg viewBox="0 0 24 24" style="width:28px;height:28px" fill="none" stroke="currentColor" stroke-width="1.5">
                                                    <path d="M7 3h6l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/>
                                                    <path d="M13 3v5h5"/>
                                                </svg>
                                            </div>
                                        @endif

                                        {{-- Content (unchanged) --}}
                                        <div class="flex-grow-1 min-w-0">
                                            @php $isImage = \Illuminate\Support\Str::startsWith($m['mime'] ?? '', 'image/'); @endphp
                                            @if (! $isEditing)
                                                <div class="fw-semibold text-truncate">
                                                    @if ($isImage)
                                                        <button type="button" @click="openPreview(@js($m['url']), @js($linkText))" class="btn btn-link p-0 align-baseline">
                                                            {{ $linkText }}
                                                        </button>
                                                    @else
                                                        <a href="{{ $m['url'] }}" target="_blank" class="link-primary text-decoration-none">
                                                            {{ $linkText }}
                                                        </a>
                                                    @endif
                                                </div>
                                                @if(!empty($m['description']))
                                                    <div class="small text-muted text-truncate">{{ $m['description'] }}</div>
                                                @endif
                                                <div class="small text-muted">
                                                    {{ number_format(($m['size'] ?? 0)/1024, 1) }} KB
                                                    <span class="mx-2">•</span>
                                                    <span class="text-uppercase">{{ $m['collection'] }}</span>
                                                </div>
                                            @else
                                                {{-- your existing edit form for caption/description/order --}}
                                                <div class="row g-2">
                                                    <div class="col-12 col-md-6">
                                                        <div class="form-label small mb-1">Caption</div>
                                                        <input type="text" wire:model.defer="editing.{{ $id }}.caption" class="form-control form-control-sm" />
                                                        @error('editing.'.$id.'.caption') <div class="form-text text-danger">{{ $message }}</div> @enderror
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <div class="form-label small mb-1">Description</div>
                                                        <input type="text" wire:model.defer="editing.{{ $id }}.description" class="form-control form-control-sm" />
                                                        @error('editing.'.$id.'.description') <div class="form-text text-danger">{{ $message }}</div> @enderror
                                                    </div>
                                                    <div class="col-12 col-md-3">
                                                        <div class="form-label small mb-1">Order</div>
                                                        <div style="max-width: 80px;">
                                                            <input type="number" min="1" step="1" wire:model.defer="editing.{{ $id }}.order" class="form-control form-control-sm text-center" />
                                                        </div>
                                                        @error('editing.'.$id.'.order') <div class="form-text text-danger">{{ $message }}</div> @enderror
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="d-flex gap-2 align-self-stretch align-items-center">
                                            @if (! $isEditing)
                                                <button type="button" wire:click="startEdit({{ $id }})" class="btn btn-outline-secondary btn-sm">Edit</button>
                                                <button type="button" wire:click="confirmDelete({{ $id }})" class="btn btn-outline-danger btn-sm">Delete</button>
                                            @else
                                                <div class="d-flex gap-2 mt-2 mt-md-0">
                                                    <button type="button" wire:click="saveEdit({{ $id }})" class="btn btn-outline-primary btn-sm">Save</button>
                                                    <button type="button" wire:click="cancelEdit({{ $id }})" class="btn btn-outline-secondary btn-sm">Cancel</button>
                                                </div>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endforeach
                    @else
                        <ul class="list-group list-group-flush">
                        @foreach ($items as $m)
                            @php
                                $id = (int) $m['id'];
                                $isEditing = isset($editing[$id]);
                                $linkText = $m['caption'] ?: ($m['name'] ?: ($m['original_name'] ?? $m['file_name']));
                            @endphp

                            <li class="list-group-item p-3 d-flex gap-3 align-items-start">
                                {{-- Thumbnail / icon --}}
                                @if(!empty($m['thumb']))
                                    <img src="{{ $m['thumb'] }}" alt="" class="rounded border" style="width:64px;height:64px;object-fit:cover;">
                                @else
                                    <div class="rounded border d-grid text-muted align-items-center justify-content-center" style="width:64px;height:64px;place-items:center;">
                                        <svg viewBox="0 0 24 24" style="width:28px;height:28px" fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path d="M7 3h6l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/>
                                            <path d="M13 3v5h5"/>
                                        </svg>
                                    </div>
                                @endif

                                {{-- Content --}}
                                <div class="flex-grow-1 min-w-0">
                                    @if (! $isEditing)
                                        @php
                                            $isImage = \Illuminate\Support\Str::startsWith($m['mime'] ?? '', 'image/');
                                        @endphp

                                        <div class="fw-semibold text-truncate">
                                            @if ($isImage)
                                                <button
                                                    type="button"
                                                    @click="openPreview(@js($m['url']), @js($linkText))"
                                                    class="btn btn-link p-0 align-baseline"
                                                >
                                                    {{ $linkText }}
                                                </button>
                                            @else
                                                <a
                                                    href="{{ $m['url'] }}"
                                                    target="_blank"
                                                    class="link-primary text-decoration-none"
                                                >
                                                    {{ $linkText }}
                                                </a>
                                            @endif
                                        </div>
                                        @if(!empty($m['description']))
                                            <div class="small text-muted text-truncate">{{ $m['description'] }}</div>
                                        @endif
                                        <div class="small text-muted">{{ number_format(($m['size'] ?? 0)/1024, 1) }} KB</div>
                                    @else
                                        <div class="row g-2">
                                            <div class="col-12 col-md-6">
                                                <div class="form-label small mb-1">Caption</div>
                                                <input
                                                    type="text"
                                                    wire:model.defer="editing.{{ $id }}.caption"
                                                    placeholder="Optional caption"
                                                    class="form-control form-control-sm"
                                                />
                                                @error('editing.'.$id.'.caption')
                                                <div class="form-text text-danger">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="col-12 col-md-6">
                                                <div class="form-label small mb-1">Description</div>
                                                <input
                                                    type="text"
                                                    wire:model.defer="editing.{{ $id }}.description"
                                                    placeholder="Optional description"
                                                    class="form-control form-control-sm"
                                                />
                                                @error('editing.'.$id.'.description')
                                                <div class="form-text text-danger">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="col-12 col-md-3">
                                                <div class="form-label small mb-1">Order</div>
                                                <div style="max-width: 80px;">
                                                    <input
                                                        type="number" min="1" step="1"
                                                        wire:model.defer="editing.{{ $id }}.order"
                                                        class="form-control form-control-sm text-center"
                                                    />
                                                </div>
                                                @error('editing.'.$id.'.order')
                                                <div class="form-text text-danger">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div class="d-flex gap-2 align-self-stretch align-items-center">
                                    @if (! $isEditing)
                                        <button
                                            type="button"
                                            wire:click="startEdit({{ $id }})"
                                            class="btn btn-outline-secondary btn-sm"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="confirmDelete({{ $id }})"
                                            wire:loading.attr="disabled"
                                            class="btn btn-outline-danger btn-sm"
                                        >
                                            Delete
                                        </button>
                                    @else
                                        <div class="d-flex gap-2 mt-2 mt-md-0">
                                            <button
                                                type="button"
                                                wire:click="saveEdit({{ $id }})"
                                                wire:loading.attr="disabled"
                                                class="btn btn-outline-primary btn-sm"
                                            >
                                                Save
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="cancelEdit({{ $id }})"
                                                class="btn btn-outline-secondary btn-sm"
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </li>
                            @endforeach
                            </ul>
                    @endif
                @endif

                <div
                    x-cloak
                    x-show="preview.open"
                    x-transition.opacity
                    @keydown.window.escape="closePreview()"
                    class="position-fixed top-0 start-0 w-100 h-100"
                    style="z-index: 1060;"
                    aria-modal="true" role="dialog" aria-label="Image preview"
                >
                    <!-- Backdrop -->
                    <div class="position-absolute top-0 start-0 w-100 h-100 bg-dark bg-opacity-75" @click="closePreview()"></div>

                    <button
                        type="button"
                        @click="closePreview()"
                        class="btn btn-light position-absolute end-0 top-0 m-3 d-inline-flex align-items-center justify-content-center"
                        aria-label="Close preview"
                    >
                        &times;
                    </button>

                    <!-- Panel -->
                    <div class="position-relative mx-auto h-100 w-100" style="max-width: 960px;">
                        <div class="d-flex h-100 align-items-center justify-content-center p-3">
                            <img
                                :src="preview.url"
                                :alt="preview.alt"
                                class="img-fluid rounded shadow border bg-white"
                                style="max-height: 85vh; object-fit: contain;"
                                @click.stop
                                loading="eager"
                                decoding="async"
                            >
                        </div>
                    </div>
                </div>

                {{-- Delete confirmation modal --}}
                <div
                    x-cloak
                    x-data
                    x-show="$wire.confirmingDeleteId !== null"
                    x-transition.opacity
                    @keydown.window.escape="$wire.cancelDelete()"
                    class="position-fixed top-0 start-0 w-100 h-100"
                    style="z-index: 1055;"
                    aria-modal="true" role="dialog" aria-labelledby="delete-modal-title"
                >
                    <div class="position-absolute top-0 start-0 w-100 h-100 bg-dark bg-opacity-75" @click="$wire.cancelDelete()"></div>

                    <div class="position-relative mx-auto mt-5" style="max-width: 520px;">
                        <div class="bg-white border rounded-3 shadow p-4">
                            @php
                                $toDelete = null;
                                if (!is_null($confirmingDeleteId)) {
                                    $toDelete = collect($items)->firstWhere('id', $confirmingDeleteId);
                                }
                            @endphp

                            <div class="d-flex align-items-start gap-3">
                                <div>
                                    @if($toDelete && !empty($toDelete['thumb']))
                                        <img src="{{ $toDelete['thumb'] }}" alt="" class="rounded border" style="width:64px;height:64px;object-fit:cover;">
                                    @else
                                        <div class="rounded border d-grid text-muted align-items-center justify-content-center" style="width:64px;height:64px;place-items:center;">
                                            <svg viewBox="0 0 24 24" style="width:28px;height:28px" fill="none" stroke="currentColor" stroke-width="1.5">
                                                <path d="M7 3h6l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/>
                                                <path d="M13 3v5h5"/>
                                            </svg>
                                        </div>
                                    @endif
                                </div>

                                <div class="flex-grow-1 min-w-0">
                                    <h3 id="delete-modal-title" class="h6 fw-semibold text-body mb-1">
                                        Delete this file?
                                    </h3>
                                    <p class="text-muted small mb-1 text-truncate">
                                        {{ $toDelete['file_name'] ?? 'This file' }}
                                    </p>
                                    <p class="text-muted small mb-0">
                                        This action cannot be undone.
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 d-flex justify-content-end gap-2">
                                <button
                                    type="button"
                                    wire:click="cancelDelete"
                                    class="btn btn-light btn-sm"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    wire:click="deleteConfirmed()"
                                    wire:loading.attr="disabled"
                                    class="btn btn-danger btn-sm"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
