@props([
    'accept' => null,
    'autoUpload' => null,
    'compress' => null,
    'convert' => null,
    'cropper' => null,
    'editor' => null,
    'height' => null,
    'isCircle' => null,
    'label' => null,
    'lockRatio' => null,
    'lossless' => null,
    'maxFiles' => null,
    'maxHeight' => null,
    'maxSize' => null,
    'maxWidth' => null,
    'multiple' => false,
    'preview' => null,
    'quality' => null,
    'ratio' => null,
    'showSavings' => null,
    'targetSize' => null,
    'theme' => null,
    'variant' => null,
    'width' => null,
])

@php
    use ArtflowStudio\FileUploader\Support\InstanceId;
    use ArtflowStudio\FileUploader\Support\LivewireBridge;
    use ArtflowStudio\FileUploader\Support\Options;

    $wireModel = $attributes->wire('model');
    $model = $wireModel->value() ?: null;

    // Built by hand rather than with @entangle: that directive compiles against
    // $__livewire, which an anonymous Blade component never has in scope.
    $entangle = $model ? LivewireBridge::entangle($model, $wireModel->hasModifier('live')) : null;

    // Deterministic across the initial render and every Livewire update.
    // See Support/InstanceId and AUDIT.md, CRIT-02.
    $instanceId = InstanceId::make($model ?? 'file', $attributes->get('id'));

    $config = Options::build($instanceId, $model, [
        'accept' => $accept,
        'autoUpload' => $autoUpload,
        'compress' => $compress,
        'convert' => $convert,
        'cropper' => $cropper,
        'editor' => $editor,
        'isCircle' => $isCircle,
        'lockRatio' => $lockRatio,
        'lossless' => $lossless,
        'maxFiles' => $maxFiles,
        'maxHeight' => $maxHeight,
        'maxSize' => $maxSize,
        'maxWidth' => $maxWidth,
        'multiple' => $multiple,
        'preview' => $preview,
        'quality' => $quality,
        'ratio' => $ratio,
        'showSavings' => $showSavings,
        'targetSize' => $targetSize,
        'theme' => $theme,
    ]);

    $config['editor']['ratios'] = (array) config('af-uploader.editor.ratios', ['1', '4/3', '3/2', '16/9', 'free']);

    $variant = $variant ?: config('af-uploader.defaults.variant', 'plain');
    $label = $label ?: config('af-uploader.defaults.label', 'Drop file or click');

    $variantClass = match ($variant) {
        'squared' => 'af-dz-squared',
        'rect' => 'af-dz-rect',
        'circled' => 'af-dz-circled',
        'inline' => 'af-dz-inline',
        default => 'af-dz-plain',
    };

    // A bare number means pixels; anything already carrying a unit passes through.
    $dimension = fn ($value) => $value === null || $value === ''
        ? null
        : (is_numeric($value) ? $value.'px' : $value);

    $style = collect(['width' => $dimension($width), 'height' => $dimension($height)])
        ->filter()
        ->map(fn ($value, $property) => "{$property}: {$value};")
        ->implode(' ');

    $isCircleVariant = $variant === 'circled' || $config['editor']['circle'];
@endphp

<div
    {{ $attributes->only('class')->merge(['class' => 'af-uploader-wrapper']) }}
    wire:key="{{ $instanceId }}"
    wire:ignore
    x-data="afUploader({{ Js::from($config) }}{{ $entangle ? ', '.$entangle : '' }})"
    x-cloak
>
    {{-- Sits outside the dropzone so clicking it never reaches the picker. --}}
    <button
        type="button"
        class="af-clear-btn"
        x-show="hasFiles || message"
        x-cloak
        @click.stop="clear()"
        aria-label="Remove file"
    >
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
    </button>

    <div
        class="af-dropzone {{ $variantClass }}"
        style="{{ $style }}"
        role="button"
        tabindex="0"
        :aria-busy="uploading || busy"
        :class="{
            'is-uploading': uploading,
            'is-busy': busy,
            'has-error': messageTone === 'error',
            'has-file': hasFiles && !uploading,
            'drag-active': dragging,
        }"
        @click="openPicker()"
        @keydown.enter.prevent="openPicker()"
        @keydown.space.prevent="openPicker()"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="onDrop($event)"
    >
        {{-- Empty state --}}
        <div class="af-content-default" x-show="!hasFiles && !uploading && !busy && !message" x-transition.opacity.duration.150ms>
            <svg class="af-upload-icon-small" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 15V3m0 0l4 4m-4-4L8 7m-4 11v3h16v-3" />
            </svg>
            <span class="af-label">{{ $label }}</span>
        </div>

        {{--
            Several files: a scrollable thumbnail grid, each tile removable on
            its own. A single shared preview plus a "3 files" caption told you
            nothing about what you had actually picked.
        --}}
        <div class="af-file-grid" x-show="multiple && hasFiles" x-cloak x-transition.opacity.duration.200ms>
            <div class="af-file-grid-scroll">
                <template x-for="(file, index) in files" :key="file.name + ':' + index">
                    <div class="af-file-tile" :class="`af-kind-${file.kind}`">
                        <template x-if="file.preview">
                            <img :src="file.preview" :alt="file.name" class="af-tile-thumb">
                        </template>

                        <template x-if="!file.preview">
                            <span class="af-tile-ext" x-text="file.name.split('.').pop()?.toUpperCase() || 'FILE'"></span>
                        </template>

                        <button
                            type="button"
                            class="af-tile-remove"
                            @click.stop="removeAt(index)"
                            :aria-label="`Remove ${file.name}`"
                            :title="file.name"
                        >
                            <svg viewBox="0 0 24 24" width="10" height="10" fill="none" stroke="currentColor" stroke-width="3.5">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                    </div>
                </template>
            </div>

            <p class="af-file-grid-caption">
                <span x-text="summary"></span>
                <span class="af-grid-total" x-text="totalSize"></span>
            </p>
        </div>

        {{-- One file --}}
        <div class="af-file-preview-card" x-show="!multiple && hasFiles" x-transition.opacity.duration.200ms>
            <div class="af-preview-content">
                <template x-if="files[0]?.preview">
                    <div class="af-preview-image {{ $isCircleVariant ? 'af-circle-mask' : '' }}">
                        <img :src="files[0].preview" :alt="files[0].name" class="af-preview-thumb">
                    </div>
                </template>

                <template x-if="!files[0]?.preview">
                    <div class="af-preview-icon" :class="`af-kind-${files[0]?.kind || 'file'}`">
                        @include('af-uploader::components.file-icons')
                    </div>
                </template>

                <div class="af-preview-info">
                    <p class="af-preview-name" x-text="summary"></p>

                    {{-- Plain size, unless there is a saving worth reporting --}}
                    <p
                        class="af-preview-meta"
                        x-show="files.length === 1 && files[0]?.size && !files[0]?.savings"
                        x-text="files[0]?.size"
                    ></p>

                    {{--
                        Only rendered when the caller passed show-savings and the
                        browser actually shrank the file. See savingsFor().
                    --}}
                    <p
                        class="af-preview-savings"
                        x-show="files.length === 1 && files[0]?.savings"
                        x-cloak
                    >
                        <span class="af-savings-before" x-text="files[0]?.savings?.before"></span>
                        <span class="af-savings-arrow" aria-hidden="true">&rarr;</span>
                        <span class="af-savings-after" x-text="files[0]?.savings?.after"></span>
                        <span
                            class="af-savings-badge"
                            :class="{ 'af-savings-missed': files[0]?.savings?.met === false }"
                            :title="files[0]?.savings?.met === false
                                ? `Could not reach ${files[0]?.savings?.budget}`
                                : null"
                            x-text="`−${files[0]?.savings?.percent}%`"
                        ></span>
                    </p>
                </div>
            </div>
        </div>

        {{-- Resting hint: sits above the preview instead of covering it --}}
        <div class="af-ready-pill" x-show="messageTone === 'ready' && !uploading && !busy" x-cloak x-text="message"></div>

        {{--
            Progress along the bottom edge. It is the only progress indicator a
            short or inline dropzone has room for, and it is unobtrusive enough
            to leave running everywhere.
        --}}
        <div class="af-progress-rail" x-show="uploading" x-cloak>
            <div class="af-progress-fill" :style="`width: ${progress}%`"></div>
        </div>

        {{-- Progress and messages --}}
        <div class="af-status-overlay" :class="{ 'active': showOverlay, 'is-compact': compact }" x-transition>
            <template x-if="uploading">
                <div class="af-upload-spinner-card">
                    {{-- The ring needs ~120px of height; below that the rail carries it. --}}
                    <div class="af-spinner-container" x-show="!compact">
                        <svg class="af-circular-spinner" viewBox="0 0 50 50">
                            <circle class="af-spinner-track" cx="25" cy="25" r="20" fill="none" stroke-width="4" />
                            <circle
                                class="af-spinner-progress" cx="25" cy="25" r="20" fill="none" stroke-width="4"
                                :style="`stroke-dasharray: ${progress * 1.256}, 125.6`"
                            />
                        </svg>
                        <div class="af-spinner-percent" x-text="`${progress}%`"></div>
                    </div>

                    <span class="af-upload-status-text">
                        <span x-show="!compact">Uploading&hellip;</span>
                        <span x-show="compact" x-text="`Uploading ${progress}%`"></span>
                    </span>

                    <button type="button" class="af-cancel-upload" @click.stop="cancel()" title="Cancel upload">
                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="3">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                </div>
            </template>

            <template x-if="busy && !uploading">
                <div class="af-status-card af-state-info">
                    <span class="af-status-message">Processing&hellip;</span>
                </div>
            </template>

            <template x-if="message && !uploading && !busy">
                <div class="af-status-card" :class="`af-state-${messageTone}`" role="status">
                    <span class="af-status-message" x-text="message"></span>
                </div>
            </template>
        </div>

        {{-- Manual upload trigger; only reachable when auto-upload is off --}}
        <button
            type="button"
            class="af-btn af-btn-primary af-upload-btn"
            x-show="awaitingUpload"
            x-cloak
            @click.stop="upload()"
        >
            Upload
        </button>

        <input
            type="file"
            x-ref="input"
            id="{{ $instanceId }}"
            accept="{{ $config['accept'] }}"
            @if ($config['multiple']) multiple @endif
            @change="onPick($event)"
            {{--
                openPicker() calls input.click(), and that synthetic click
                bubbles back up to the dropzone's own @click — which would open
                a second file dialog. Stopping it here is what keeps one click
                to one dialog (AUDIT.md, HIGH-02).
            --}}
            @click.stop
            {{ $attributes->except(['class', 'id', 'style', 'accept', 'multiple'])->whereDoesntStartWith('wire:') }}
            hidden
        >
    </div>
</div>

{{--
    $errors is shared onto views by the session error middleware. An anonymous
    Blade component rendered outside a web request never receives it, so guard
    rather than assume.
--}}
@if ($model && isset($errors) && $errors->has($wireModel->name()))
    <small class="af-error-text">{{ $errors->first($wireModel->name()) }}</small>
@endif
