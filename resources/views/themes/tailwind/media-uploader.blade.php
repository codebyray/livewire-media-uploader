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
    class="flex flex-col gap-3"
>
    @if (session('media_uploader_notice'))
        <div
            x-data="{ open: true }"
            x-cloak
            x-show="open"
            x-transition.opacity.duration.150ms
            wire:key="flash-notice"
            role="alert"
            class="relative rounded-md border px-3 py-2 text-sm
                   border-emerald-200 bg-emerald-50 text-emerald-900
                   dark:border-emerald-800/40 dark:bg-emerald-900/20 dark:text-emerald-200"
        >
            <div class="pr-8">
                {{ session('media_uploader_notice') }}
            </div>

            <!-- Dismiss -->
            <button
                type="button"
                @click="open = false"
                class="absolute right-1.5 top-1.5 inline-flex h-7 w-7 items-center justify-center
                       rounded hover:bg-emerald-100/50 dark:hover:bg-emerald-900/30 cursor-pointer
                       focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600/50"
                aria-label="Dismiss notification"
            >
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/>
                </svg>
                <span class="sr-only">Dismiss</span>
            </button>
        </div>
    @endif

    <div class="text-base -mb-1 font-medium text-neutral-900 dark:text-neutral-100">Manage gallery</div>

    <!-- Dropzone -->
    <div
        class="rounded-xl border-2 p-0 shadow-none bg-white dark:bg-neutral-800"
        :class="isDropping ? 'border-emerald-500' : 'border-neutral-200 dark:border-neutral-700'"
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
        <div class="flex items-center justify-between gap-3 p-4">
            <div class="flex items-center gap-2">
                <svg class="text-neutral-400 dark:text-neutral-500" style="width:24px;height:24px" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M7 16a4 4 0 118 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M12 12V4m0 0l-3 3m3-3l3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <rect x="3" y="12" width="18" height="8" rx="2" stroke="currentColor" stroke-width="2"/>
                </svg>
                <div class="text-sm">
                    <div class="font-semibold text-neutral-900 dark:text-neutral-100">Drag &amp; drop files here</div>
                    <div class="text-neutral-500 dark:text-neutral-400">
                        or click to choose from your computer
                        @if(!empty($allowedLabel))
                            <span class="text-neutral-500 dark:text-neutral-400 text-xs mt-1"> - Allowed file types: {{ $allowedLabel }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <label class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium cursor-pointer bg-neutral-900 text-white hover:bg-neutral-800 dark:text-white dark:bg-sky-600 dark:hover:bg-sky-500">
                <span>Choose file<span @class(['', 's' => $multiple])>s</span></span>
                <input
                    x-ref="input"
                    type="file"
                    class="hidden"
                    wire:model="uploads"
                    @if($multiple) multiple @endif
                    @if($accept)   accept="{{ $accept }}" @endif
                />
            </label>
        </div>

        <!-- Progress -->
        <template x-if="progress > 0">
            <div class="px-4 pb-4">
                <div class="w-full rounded-full h-2 bg-neutral-100 dark:bg-neutral-800">
                    <div class="h-2 rounded-full transition-all bg-neutral-900 dark:bg-neutral-100" :style="`width:${progress}%;`"></div>
                </div>
                <div class="mt-1 text-xs text-neutral-500 dark:text-neutral-400" x-text="progress + '%'"></div>
            </div>
        </template>

        <!-- Selected queue -->
        @if(!empty($selected) && count($selected) > 0)
            <div class="px-4 pb-4">
                <div class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Ready to upload:</div>
                <div class="mt-2 space-y-2">
                    @foreach ($selected as $sel)
                        @php
                            $temp = $uploads[$sel['queue_key']] ?? null;
                            $canPreview = ($sel['is_image'] ?? false) && $temp && method_exists($temp, 'temporaryUrl');
                        @endphp

                        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700 dark:bg-neutral-900 p-3">
                            <!-- top row: name + size -->
                            <div class="flex items-center justify-between gap-3">
                                <span class="truncate text-sm text-neutral-900 dark:text-neutral-100">{{ $sel['name'] }}</span>
                                <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ number_format(($sel['size'] ?? 0)/1024, 1) }} KB</span>
                            </div>

                            <!-- form row -->
                            <div class="mt-3 items-start">
                                <div class="flex flex-col gap-3 md:grid md:grid-cols-12">
                                    <!-- thumb -->
                                    <div class="md:col-span-2">
                                        @if($canPreview)
                                            <img src="{{ $temp->temporaryUrl() }}" alt="" class="w-20 h-20 object-cover rounded-md border border-neutral-200 dark:border-neutral-700">
                                        @else
                                            <div class="w-20 h-20 grid place-items-center rounded-md border border-neutral-200 dark:border-neutral-700 text-neutral-400 dark:text-neutral-500">
                                                <svg viewBox="0 0 24 24" class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.5">
                                                    <path d="M7 3h6l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/>
                                                    <path d="M13 3v5h5"/>
                                                </svg>
                                            </div>
                                        @endif
                                    </div>

                                    <!-- caption -->
                                    <div class="md:col-span-3">
                                        <label class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">Caption</label>
                                        <input
                                            type="text"
                                            wire:model.lazy="pendingMeta.{{ $sel['queue_key'] }}.caption"
                                            class="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm
                                                   text-neutral-900 placeholder-neutral-400
                                                   focus:outline-none focus:ring-2 focus:ring-sky-500
                                                   dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100 dark:placeholder-neutral-500"
                                        />
                                        @error('pendingMeta.'.$sel['queue_key'].'.caption')
                                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- description -->
                                    <div class="md:col-span-5">
                                        <label class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">Description</label>
                                        <input
                                            type="text"
                                            wire:model.lazy="pendingMeta.{{ $sel['queue_key'] }}.description"
                                            class="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm
                                                   text-neutral-900 placeholder-neutral-400
                                                   focus:outline-none focus:ring-2 focus:ring-sky-500
                                                   dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100 dark:placeholder-neutral-500"
                                        />
                                        @error('pendingMeta.'.$sel['queue_key'].'.description')
                                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- order -->
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">Order</label>
                                        <input
                                            type="number" min="1" step="1"
                                            wire:model.lazy="pendingMeta.{{ $sel['queue_key'] }}.order"
                                            placeholder="e.g. 1"
                                            class="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm
                                                   text-neutral-900 placeholder-neutral-400
                                                   focus:outline-none focus:ring-2 focus:ring-sky-500
                                                   dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100 dark:placeholder-neutral-500"
                                        />
                                        @error('pendingMeta.'.$sel['queue_key'].'.order')
                                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- remove -->
                                    <div class="md:col-span-12 flex justify-end">
                                        <button
                                            type="button"
                                            wire:click="removeFromQueue({{ $sel['queue_key'] }})"
                                            wire:loading.attr="disabled"
                                            class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium
                                                   border border-red-500 text-red-600 hover:bg-red-50
                                                   dark:border-red-600 dark:text-red-400 dark:hover:bg-red-900/20"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-3 flex gap-2">
                    <button
                        type="button"
                        wire:click="uploadFiles"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium
                               bg-sky-600 text-white hover:bg-sky-500
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-600/50
                               disabled:opacity-60"
                    >
                        Upload
                    </button>

                    <button
                        type="button"
                        wire:click="clearQueue"
                        class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium
                               bg-neutral-100 text-neutral-800 hover:bg-neutral-200
                               dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700"
                    >
                        Clear
                    </button>
                </div>
            </div>
        @endif
    </div>

    @if($showList)
        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700 shadow-none overflow-hidden bg-white dark:bg-neutral-900">
            <div class="flex items-center justify-between px-4 py-3 border-b border-neutral-200 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ $attachedFilesTitle ?? 'Current gallery' }}</div>
                <button
                    type="button"
                    wire:click="loadItems"
                    class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium
                           hover:bg-neutral-100 text-neutral-700
                           dark:text-neutral-200 dark:hover:bg-neutral-800"
                >
                    Refresh
                </button>
            </div>

            @if (count($items) === 0)
                <div class="p-4">
                    <p class="text-sm text-neutral-500 dark:text-neutral-400 mb-0">No gallery images yet.</p>
                </div>
            @else
                @if (!empty($listAll) && $listAll && !empty($groups))
                    {{-- GROUPED BY COLLECTION --}}
                    @foreach ($groups as $collectionName => $collectionItems)
                        {{-- Group header --}}
                        <div class="px-4 py-2 bg-neutral-50 dark:bg-neutral-800/50 border-b border-neutral-200 dark:border-neutral-800">
                            <div class="text-[11px] font-semibold tracking-wide uppercase text-neutral-500 dark:text-neutral-400">
                                {{ $collectionName }}
                            </div>
                        </div>

                        <ul class="divide-y divide-neutral-200 dark:divide-neutral-800">
                            @foreach ($collectionItems as $m)
                                @php
                                    $id = (int) $m['id'];
                                    $isEditing = isset($editing[$id]);
                                    $linkText = $m['caption'] ?: ($m['name'] ?: ($m['original_name'] ?? $m['file_name']));
                                    $isImage = \Illuminate\Support\Str::startsWith($m['mime'] ?? '', 'image/');
                                @endphp

                                <li class="p-3 flex content-center gap-3">
                                    {{-- Thumbnail / icon --}}
                                    @if(!empty($m['thumb']))
                                        <img src="{{ $m['thumb'] }}" alt="" class="rounded-md object-cover w-16 h-16 border border-neutral-200 dark:border-neutral-700">
                                    @else
                                        <div class="rounded-md w-16 h-16 grid place-items-center border border-neutral-200 dark:border-neutral-700 text-neutral-400 dark:text-neutral-500">
                                            <svg viewBox="0 0 24 24" class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.5">
                                                <path d="M7 3h6l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/>
                                                <path d="M13 3v5h5"/>
                                            </svg>
                                        </div>
                                    @endif

                                    {{-- Content --}}
                                    <div class="flex-1 min-w-0 max-w-full">
                                        @if (! $isEditing)
                                            <div class="font-semibold truncate">
                                                @if ($isImage)
                                                    <button
                                                        type="button"
                                                        @click="openPreview(@js($m['url']), @js($linkText))"
                                                        class="cursor-pointer text-emerald-700 hover:text-emerald-800 dark:text-emerald-300 dark:hover:text-emerald-200"
                                                    >
                                                        {{ $linkText }}
                                                    </button>
                                                @else
                                                    <a
                                                        href="{{ $m['url'] }}"
                                                        target="_blank"
                                                        class="cursor-pointer text-emerald-700 hover:text-emerald-800 dark:text-emerald-300 dark:hover:text-emerald-200"
                                                    >
                                                        {{ $linkText }}
                                                    </a>
                                                @endif
                                            </div>

                                            {{-- Optional description --}}
                                            @if(!empty($m['description']))
                                                <div class="text-sm text-neutral-500 dark:text-neutral-400 truncate">{{ $m['description'] }}</div>
                                            @endif

                                            {{-- Size + collection name meta --}}
                                            <div class="text-xs text-neutral-500 dark:text-neutral-400">
                                                {{ number_format(($m['size'] ?? 0)/1024, 1) }} KB
                                                @if(!empty($m['collection']))
                                                    <span class="mx-2">•</span>
                                                    <span class="uppercase">{{ $m['collection'] }}</span>
                                                @endif
                                            </div>
                                        @else
                                            <div class="grid grid-cols-1 md:[grid-template-columns:1fr_2fr_auto] gap-2 max-w-full">
                                                <div>
                                                    <div class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">Caption</div>
                                                    <input
                                                        type="text"
                                                        wire:model.defer="editing.{{ $id }}.caption"
                                                        placeholder="Optional caption"
                                                        class="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm
                                                   text-neutral-900 placeholder-neutral-400
                                                   focus:outline-none focus:ring-2 focus:ring-sky-500
                                                   dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100 dark:placeholder-neutral-500"
                                                    />
                                                    @error('editing.'.$id.'.caption')
                                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                                    @enderror
                                                </div>

                                                <div>
                                                    <div class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">Description</div>
                                                    <input
                                                        type="text"
                                                        wire:model.defer="editing.{{ $id }}.description"
                                                        placeholder="Optional description"
                                                        class="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm
                                                   text-neutral-900 placeholder-neutral-400
                                                   focus:outline-none focus:ring-2 focus:ring-sky-500
                                                   dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100 dark:placeholder-neutral-500"
                                                    />
                                                    @error('editing.'.$id.'.description')
                                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                                    @enderror
                                                </div>

                                                <div>
                                                    <div class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">Order</div>
                                                    <div class="w-20">
                                                        <input
                                                            type="number" min="1" step="1"
                                                            wire:model.defer="editing.{{ $id }}.order"
                                                            class="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm text-center
                                                       text-neutral-900 placeholder-neutral-400
                                                       focus:outline-none focus:ring-2 focus:ring-sky-500
                                                       dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100 dark:placeholder-neutral-500"
                                                        />
                                                    </div>
                                                    @error('editing.'.$id.'.order')
                                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="flex gap-2 self-stretch md:items-center">
                                        @if (! $isEditing)
                                            <button
                                                type="button"
                                                wire:click="startEdit({{ $id }})"
                                                class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium
                                           hover:bg-neutral-100 text-neutral-700
                                           dark:text-neutral-200 dark:hover:bg-neutral-800"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="confirmDelete({{ $id }})"
                                                wire:loading.attr="disabled"
                                                class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium
                                           border border-red-500 text-red-600 hover:bg-red-50
                                           dark:border-red-600 dark:text-red-400 dark:hover:bg-red-900/20"
                                            >
                                                Delete
                                            </button>
                                        @else
                                            <div class="flex items-center gap-2 self-stretch mt-4">
                                                <button
                                                    type="button"
                                                    wire:click="saveEdit({{ $id }})"
                                                    wire:loading.attr="disabled"
                                                    class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium
                                               border border-sky-500 text-sky-600 hover:bg-sky-50
                                               dark:border-sky-600 dark:text-sky-400 dark:hover:bg-sky-900/20"
                                                >
                                                    Save
                                                </button>
                                                <button
                                                    type="button"
                                                    wire:click="cancelEdit({{ $id }})"
                                                    class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium
                                               hover:bg-neutral-100 text-neutral-700
                                               dark:text-neutral-200 dark:hover:bg-neutral-800"
                                                >
                                                    Cancel
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endforeach
                @else
                    {{-- SINGLE COLLECTION (original rendering) --}}
                    <ul class="divide-y divide-neutral-200 dark:divide-neutral-800">
                        @foreach ($items as $m)
                            @php
                                $id = (int) $m['id'];
                                $isEditing = isset($editing[$id]);
                                $linkText = $m['caption'] ?: ($m['name'] ?: ($m['original_name'] ?? $m['file_name']));
                                $isImage = \Illuminate\Support\Str::startsWith($m['mime'] ?? '', 'image/');
                            @endphp

                            <li class="p-3 flex content-center gap-3">
                                {{-- Thumbnail / icon --}}
                                @if(!empty($m['thumb']))
                                    <img src="{{ $m['thumb'] }}" alt="" class="rounded-md object-cover w-16 h-16 border border-neutral-200 dark:border-neutral-700">
                                @else
                                    <div class="rounded-md w-16 h-16 grid place-items-center border border-neutral-200 dark:border-neutral-700 text-neutral-400 dark:text-neutral-500">
                                        <svg viewBox="0 0 24 24" class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path d="M7 3h6l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/>
                                            <path d="M13 3v5h5"/>
                                        </svg>
                                    </div>
                                @endif

                                {{-- Content --}}
                                <div class="flex-1 min-w-0 max-w-full">
                                    @if (! $isEditing)
                                        <div class="font-semibold truncate">
                                            @if ($isImage)
                                                <button
                                                    type="button"
                                                    @click="openPreview(@js($m['url']), @js($linkText))"
                                                    class="cursor-pointer text-emerald-700 hover:text-emerald-800 dark:text-emerald-300 dark:hover:text-emerald-200"
                                                >
                                                    {{ $linkText }}
                                                </button>
                                            @else
                                                <a
                                                    href="{{ $m['url'] }}"
                                                    target="_blank"
                                                    class="cursor-pointer text-emerald-700 hover:text-emerald-800 dark:text-emerald-300 dark:hover:text-emerald-200"
                                                >
                                                    {{ $linkText }}
                                                </a>
                                            @endif
                                        </div>
                                        @if(!empty($m['description']))
                                            <div class="text-sm text-neutral-500 dark:text-neutral-400 truncate">{{ $m['description'] }}</div>
                                        @endif
                                        <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ number_format(($m['size'] ?? 0)/1024, 1) }} KB</div>
                                    @else
                                        <div class="grid grid-cols-1 md:[grid-template-columns:1fr_2fr_auto] gap-2 max-w-full">
                                            <div>
                                                <div class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">Caption</div>
                                                <input
                                                    type="text"
                                                    wire:model.defer="editing.{{ $id }}.caption"
                                                    placeholder="Optional caption"
                                                    class="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm
                                               text-neutral-900 placeholder-neutral-400
                                               focus:outline-none focus:ring-2 focus:ring-sky-500
                                               dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100 dark:placeholder-neutral-500"
                                                />
                                                @error('editing.'.$id.'.caption')
                                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div>
                                                <div class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">Description</div>
                                                <input
                                                    type="text"
                                                    wire:model.defer="editing.{{ $id }}.description"
                                                    placeholder="Optional description"
                                                    class="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm
                                               text-neutral-900 placeholder-neutral-400
                                               focus:outline-none focus:ring-2 focus:ring-sky-500
                                               dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100 dark:placeholder-neutral-500"
                                                />
                                                @error('editing.'.$id.'.description')
                                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div>
                                                <div class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">Order</div>
                                                <div class="w-20">
                                                    <input
                                                        type="number" min="1" step="1"
                                                        wire:model.defer="editing.{{ $id }}.order"
                                                        class="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm text-center
                                                   text-neutral-900 placeholder-neutral-400
                                                   focus:outline-none focus:ring-2 focus:ring-sky-500
                                                   dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100 dark:placeholder-neutral-500"
                                                    />
                                                </div>
                                                @error('editing.'.$id.'.order')
                                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div class="flex gap-2 self-stretch md:items-center">
                                    @if (! $isEditing)
                                        <button
                                            type="button"
                                            wire:click="startEdit({{ $id }})"
                                            class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium
                                            hover:bg-neutral-100 text-neutral-700
                                            dark:text-neutral-200 dark:hover:bg-neutral-800"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="confirmDelete({{ $id }})"
                                            wire:loading.attr="disabled"
                                            class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium
                                            border border-red-500 text-red-600 hover:bg-red-50
                                            dark:border-red-600 dark:text-red-400 dark:hover:bg-red-900/20"
                                        >
                                            Delete
                                        </button>
                                    @else
                                        <div class="flex items-center gap-2 self-stretch mt-4">
                                            <button
                                                type="button"
                                                wire:click="saveEdit({{ $id }})"
                                                wire:loading.attr="disabled"
                                                class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium
                                               border border-sky-500 text-sky-600 hover:bg-sky-50
                                               dark:border-sky-600 dark:text-sky-400 dark:hover:bg-sky-900/20"
                                            >
                                                Save
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="cancelEdit({{ $id }})"
                                                class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium
                                               hover:bg-neutral-100 text-neutral-700
                                               dark:text-neutral-200 dark:hover:bg-neutral-800"
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

                <!-- Image Preview Overlay (kept as-is) -->
                <div
                    x-cloak
                    x-show="preview.open"
                    x-transition.opacity
                    @keydown.window.escape="closePreview()"
                    class="fixed inset-0 z-[60]"
                    aria-modal="true" role="dialog" aria-label="Image preview"
                >
                    <div class="absolute inset-0 bg-black/80" @click="closePreview()"></div>
                    <button
                        type="button"
                        @click="closePreview()"
                        class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-lg
                       bg-white/90 text-neutral-700 shadow hover:bg-white
                       dark:bg-neutral-800/90 dark:text-neutral-200 dark:hover:bg-neutral-800"
                        aria-label="Close preview"
                    >
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/>
                        </svg>
                    </button>
                    <div class="relative mx-auto h-full w-full max-w-5xl">
                        <div class="flex h-full items-center justify-center p-6">
                            <img
                                :src="preview.url"
                                :alt="preview.alt"
                                class="max-h-[85vh] max-w-[90vw] rounded-xl border border-neutral-200 bg-white object-contain shadow-lg
                                dark:border-neutral-700 dark:bg-neutral-900"
                                @click.stop
                                loading="eager"
                                decoding="async"
                            >
                        </div>
                    </div>
                </div>

                <div
                    x-cloak
                    x-data
                    x-show="$wire.confirmingDeleteId !== null"
                    x-transition.opacity
                    @keydown.window.escape="$wire.cancelDelete()"
                    class="fixed inset-0 z-50"
                    aria-modal="true" role="dialog" aria-labelledby="delete-modal-title"
                >
                    <div class="absolute inset-0 bg-black/80" @click="$wire.cancelDelete()"></div>
                    <div class="relative mx-auto mt-24 w-full max-w-md rounded-xl bg-white p-4 shadow-lg dark:bg-neutral-900 border dark:border-neutral-700">
                        @php
                            $toDelete = null;
                            if (!is_null($confirmingDeleteId)) {
                                $toDelete = collect($items)->firstWhere('id', $confirmingDeleteId);
                            }
                        @endphp
                        <div class="flex items-start gap-3">
                            <div>
                                @if($toDelete && !empty($toDelete['thumb']))
                                    <img src="{{ $toDelete['thumb'] }}" alt="" class="w-16 h-16 rounded-md object-cover border border-neutral-200 dark:border-neutral-700">
                                @else
                                    <div class="w-16 h-16 grid place-items-center rounded-md border border-neutral-200 dark:border-neutral-700 text-neutral-400 dark:text-neutral-500">
                                        <svg viewBox="0 0 24 24" class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path d="M7 3h6l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/>
                                            <path d="M13 3v5h5"/>
                                        </svg>
                                    </div>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 id="delete-modal-title" class="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                                    Delete this file?
                                </h3>
                                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-300 truncate">
                                    {{ $toDelete['file_name'] ?? 'This file' }}
                                </p>
                                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                    This action cannot be undone.
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 flex justify-end gap-2">
                            <button
                                type="button"
                                wire:click="cancelDelete"
                                class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium
                               bg-neutral-100 text-neutral-800 hover:bg-neutral-200
                               dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                wire:click="deleteConfirmed()"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium
                               bg-red-600 text-white hover:bg-red-500
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600/50
                               disabled:opacity-60"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
