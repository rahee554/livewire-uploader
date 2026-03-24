@props([
    'model' => null,
    'cropper' => 'false',
    'preview' => 'true',
    'ratio' => null,
    'isCircle' => 'false',
    'maxWidth' => 2000,
    'quality' => null,
    'variant' => 'plain',
    'label' => 'Drop file or click',
    'width' => null,
    'height' => null,
    'autoUpload' => 'true',
    'maxSize' => 10,
    'accept' => 'image/*',
    'targetSize' => null,
    'convert' => null,
    'lossless' => 'false',
    'optimized' => 'false',
    'multiple' => false,
])

@php
    $wireModel = $attributes->wire('model');
    $modelName = $wireModel->value() ?: 'default';
    
    // Create a stable hash for this instance - must be deterministic across Livewire re-renders
    // so we can restore state from cache
    $context = [
        $modelName,
        $attributes->get('label', ''),
        $attributes->get('variant', ''),
        $attributes->get('wire:key', ''),
        $attributes->get('name', ''),
        // Include loop index if in a loop
        isset($loop) ? $loop->index : '',
        // Include a path-based hash to differentiate same-named properties in different views
        md5(__FILE__),
        // Use request URI for additional uniqueness without random component
        // This keeps ID stable across Livewire morphs while being unique per page
        request()->getRequestUri(),
    ];
    $stableHash = substr(md5(serialize($context)), 0, 12);
    $instanceId = 'af-upl-' . ($attributes->get('id') ?: $stableHash);

    // Filter accept
    $acceptAttr = $accept;
    if ($cropper === 'true') {
        $acceptAttr = 'image/*';
    }

    $variantClass = match ($variant) {
        'squared' => 'af-dz-squared',
        'rect' => 'af-dz-rect',
        'circled' => 'af-dz-circled',
        'inline' => 'af-dz-inline',
        default => 'af-dz-plain',
    };

    // Parse width/height - support w-100, w-100px, h-100, etc.
    $parseSize = function($value) {
        if (!$value) return null;
        // If it's just a number or ends with common units, use as-is
        if (preg_match('/^\d+(%|px|em|rem|vh|vw)?$/', $value)) {
            return is_numeric($value) ? $value . 'px' : $value;
        }
        return $value;
    };
    
    $widthStyle = $parseSize($width);
    $heightStyle = $parseSize($height);
    
    $style = '';
    if ($widthStyle) {
        $style .= "width: {$widthStyle};";
    }
    if ($heightStyle) {
        $style .= "height: {$heightStyle};";
    }
    
    // Quality: default to 0.80 when outputting WebP (cropper always outputs WebP; convert="webp" also outputs WebP)
    $isLossless = $lossless === 'true' || $lossless === true;
    $isWebpOutput = $convert !== null || $cropper === 'true';
    $resolvedQuality = $quality !== null
        ? (float) $quality
        : ($isLossless ? 1.0 : ($isWebpOutput ? 0.80 : 0.92));

    // Determine if this is accepting images for file type icon logic
    $isImageOnly = str_contains($accept, 'image');
    $isVideoOnly = str_contains($accept, 'video');
@endphp

<div {{ $attributes->only(['class'])->merge(['class' => 'af-uploader-wrapper']) }} x-cloak
    wire:ignore
    wire:key="{{ $instanceId }}"
    @af-status-update-{{ $instanceId }}.window="onStatusUpdate($event)" 
    @af-external-file-selected-{{ $instanceId }}.window="onExternalFileSelected($event)"
    @af-image-cropped-{{ $instanceId }}.window="onImageCropped($event)" 
    x-data="window.afUploader({
        wireModel: '{{ $wireModel->value() }}',
        modelValue: @entangle($wireModel->value()),
        id: '{{ $instanceId }}',
        autoUpload: {{ $autoUpload ? 'true' : 'false' }},
        maxSize: {{ $maxSize }},
        cropper: {{ $cropper === 'true' ? 'true' : 'false' }},
        accept: '{{ $acceptAttr }}',
        multiple: {{ ($multiple === true || $multiple === 'true') ? 'true' : 'false' }}
    })">
    {{-- Layer 3 (Top): Close button - positioned OUTSIDE dropzone at wrapper level, always accessible --}}
    <button class="af-clear-btn" 
        @click.stop="remove()" 
        x-show="(hasFile || statusText) && !isUploading && !isResetting" 
        x-transition:enter="af-btn-enter"
        x-transition:leave="af-btn-leave"
        type="button" 
        aria-label="Remove file">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
    </button>

    <div class="af-dropzone {{ $variantClass }}" id="dz-{{ $instanceId }}"
        x-ref="dz_{{ $instanceId }}"
        :class="{ 'is-uploading': isUploading, 'has-error': statusType === 'danger', 'has-file': hasFile && !isUploading, 'drag-active': dragActive, 'is-resetting': isResetting }" 
        style="{{ $style }}"
        @click="if (!cropper && !isUploading && !isResetting && (!hasFile || multiple)) { $refs['fileInput_' + id].click(); }"
        @dragover.prevent="dragActive = true"
        @dragleave.prevent="dragActive = false"
        @drop.prevent="dragActive = false; if ($event.dataTransfer.files.length) { const dt = new DataTransfer(); const dropped = $event.dataTransfer.files; if (multiple) { for (let i = 0; i < dropped.length; i++) { dt.items.add(dropped[i]); } } else { dt.items.add(dropped[0]); } $refs['fileInput_' + id].files = dt.files; $refs['fileInput_' + id].dispatchEvent(new Event('change')); }"
    >
        {{-- Layer 1 (Bottom): Default upload prompt --}}
        <div class="af-content-default" x-show="!isUploading && !statusText && !hasFile && !isResetting" x-transition.opacity.duration.150ms>
            <svg class="af-upload-icon-small" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 15V3m0 0l4 4m-4-4L8 7m-4 11v3h16v-3" />
            </svg>
            <span class="af-label">{{ $label }}</span>
        </div>

        {{-- Layer 2 (Middle): File preview - contained within dropzone --}}
        <div class="af-file-preview-card" x-show="hasFile && !isResetting" x-transition.opacity.duration.200ms>
            <div class="af-preview-content">
                <template x-if="filePreview && isImageFile(filePreview)">
                    <div class="af-preview-image {{ $isCircle === 'true' || $variant === 'circled' ? 'af-circle-mask' : '' }}">
                        <img :src="filePreview" :alt="fileName" class="af-preview-thumb">
                    </div>
                </template>
                <template x-if="!filePreview || !isImageFile(filePreview)">
                    <div class="af-preview-icon" :class="getFileTypeClass(fileName)">
                        <template x-if="getFileType(fileName) === 'pdf'">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 2l5 5h-5V4zM8.5 17.5v-4h1.25c1.1 0 2 .67 2 1.5s-.9 1.5-2 1.5H9.5v1.5h-1zm1-2.5h.25c.55 0 1-.22 1-.5s-.45-.5-1-.5H9.5v1zm3.5 2.5v-4h1.5c1.38 0 2.5.9 2.5 2s-1.12 2-2.5 2H13zm1-3v2h.5c.83 0 1.5-.45 1.5-1s-.67-1-1.5-1H14zm4 3v-4h3v1h-2v.5h1.5v1H19v1.5h-1z"/></svg>
                        </template>
                        <template x-if="getFileType(fileName) === 'video'">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17 10.5V7a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h12a1 1 0 001-1v-3.5l4 4v-11l-4 4zM15 16H5V8h10v8z"/></svg>
                        </template>
                        <template x-if="getFileType(fileName) === 'image'">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                        </template>
                        <template x-if="getFileType(fileName) === 'doc'">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm4 18H6V4h7v5h5v11zM9.5 11.5v6h1v-2h1c.83 0 1.5-.67 1.5-1.5v-1c0-.83-.67-1.5-1.5-1.5h-2zm1 1h1c.28 0 .5.22.5.5v1c0 .28-.22.5-.5.5h-1v-2z"/></svg>
                        </template>
                        <template x-if="!['pdf', 'video', 'image', 'doc'].includes(getFileType(fileName))">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm4 18H6V4h7v5h5v11z"/></svg>
                        </template>
                    </div>
                </template>
                <div class="af-preview-info">
                    <p class="af-preview-name" x-text="fileName || 'Loaded'"></p>
                </div>
            </div>
        </div>

        {{-- Status overlay with spinner --}}
        <div class="af-status-overlay" :class="{ 'active': statusText || isUploading }" x-transition>
            <template x-if="isUploading">
                <div class="af-upload-spinner-card">
                    {{-- Circular spinner with percentage --}}
                    <div class="af-spinner-container">
                        <svg class="af-circular-spinner" viewBox="0 0 50 50">
                            <circle class="af-spinner-track" cx="25" cy="25" r="20" fill="none" stroke-width="4"></circle>
                            <circle class="af-spinner-progress" cx="25" cy="25" r="20" fill="none" stroke-width="4"
                                :style="'stroke-dasharray: ' + (progress * 1.256) + ', 125.6'"></circle>
                        </svg>
                        <div class="af-spinner-percent" x-text="progress + '%'"></div>
                    </div>
                    <span class="af-upload-status-text">Uploading...</span>
                    <button type="button" class="af-cancel-upload" @click.stop="cancelUpload()" title="Cancel Upload">
                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="3">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            </template>

            <template x-if="!isUploading && statusText">
                <div class="af-status-card" :class="'af-state-' + statusType">
                    <div class="af-status-icon">
                        <template x-if="statusType === 'success'">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M20 6L9 17L4 12" />
                            </svg>
                        </template>
                        <template x-if="statusType === 'danger' || statusType === 'error'">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M18 6L6 18M6 6l12 12" />
                            </svg>
                        </template>
                        <template x-if="statusType === 'info'">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" y1="16" x2="12" y2="12" />
                                <line x1="12" y1="8" x2="12.01" y2="8" />
                            </svg>
                        </template>
                    </div>
                    <span class="af-status-message" x-text="statusText"></span>
                </div>
            </template>
        </div>

        <input type="file" x-ref="fileInput_{{ $instanceId }}"
            {{ $attributes->whereDoesntStartWith('wire:model')->whereDoesntStartWith('class')->whereDoesntStartWith('wire:key')->whereDoesntStartWith('id') }}
            id="{{ $instanceId }}"
            accept="{{ $accept }}"
            {{ ($multiple === true || $multiple === 'true') ? 'multiple' : '' }}
            @change="onInputFileChange"
            @if ($wireModel->value()) data-af-wire-model="{{ $wireModel->value() }}" @endif
            data-af-id="{{ $instanceId }}"
            af-cropper="{{ $cropper }}" data-af-preview="{{ $preview }}"
            data-af-ratio="{{ $ratio }}" data-af-is-circle="{{ $isCircle }}"
            data-af-max-width="{{ $maxWidth }}" data-af-quality="{{ $resolvedQuality }}"
            @if($targetSize) data-af-target-size="{{ $targetSize }}" @endif
            @if($convert) data-af-convert="{{ $convert }}" @endif
            data-af-lossless="{{ $isLossless ? 'true' : 'false' }}"
            data-af-optimized="{{ $optimized }}"
            hidden>
    </div>
</div>

@if ($wireModel->name() && isset($errors))
    @error($wireModel->name())
        <small class="text-danger mt-1 d-block text-red-500 text-xs">{{ $message }}</small>
    @enderror
@endif


@once
    <style>
        #af-modal {
            display: none;
        }

        #af-modal.active {
            display: flex !important;
        }
    </style>

    <div id="af-modal">
        <div class="af-modal-header">
            <span style="font-weight: 600; letter-spacing: 0.5px; font-size: 13px; color: var(--af-primary);">IMAGE
                EDITOR</span>
            <button type="button" class="af-btn" id="af-cancel"
                style="padding: 5px 10px; font-size: 20px; color: var(--af-secondary);">&times;</button>
        </div>

        <div class="af-canvas-wrapper">
            <canvas id="af-canvas"></canvas>
            <div id="af-rot-val">0&deg;</div>
        </div>

        <div class="af-controls">
            <div class="af-ratio-group">
                <button type="button" class="af-btn active" data-ratio="1">1:1</button>
                <div class="af-circle-toggle" id="af-ratio-circle" title="Toggle Circle Mask">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                        <path
                            d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" />
                    </svg>
                </div>
                <button type="button" class="af-btn" data-ratio="4/3">4:3</button>
                <button type="button" class="af-btn" data-ratio="3/2">3:2</button>
                <button type="button" class="af-btn" data-ratio="16/9">16:9</button>
                <button type="button" class="af-btn af-icon-btn" data-ratio="0" title="Free Form">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                        <path
                            d="M13 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-8h-2v8H5V5h8V3zm2 0v2h3.59L8.34 15.25l1.41 1.41L20 6.41V10h2V3h-7z" />
                    </svg>
                </button>
            </div>

            <div class="af-btn-group">
                <button type="button" class="af-btn af-icon-btn" id="af-rot-left" title="Rotate Counter-Clockwise">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                        <path
                            d="M12.25 2c-5.52 0-10 4.48-10 10s4.48 10 10 10 10-4.48 10-10h-2c0 4.42-3.58 8-8 8s-8-3.58-8-8 3.58-8 8-8v4l5-5-5-5v4z" />
                    </svg>
                </button>
                <button type="button" class="af-btn af-icon-btn" id="af-zoom-out" title="Zoom Out">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor">
                        <path d="M19 13H5v-2h14v2z" />
                    </svg>
                </button>
                <button type="button" class="af-btn af-icon-btn" id="af-fit-view" title="Auto-Fit Image to Mask">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <polyline points="9 21 3 21 3 15"></polyline>
                        <line x1="21" y1="3" x2="14" y2="10"></line>
                        <line x1="3" y1="21" x2="10" y2="14"></line>
                        <polyline points="21 15 21 21 15 21"></polyline>
                        <polyline points="3 9 3 3 9 3"></polyline>
                        <line x1="21" y1="21" x2="14" y2="14"></line>
                        <line x1="3" y1="3" x2="10" y2="10"></line>
                    </svg>
                </button>
                <button type="button" class="af-btn af-icon-btn" id="af-zoom-in" title="Zoom In">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor">
                        <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z" />
                    </svg>
                </button>
                <button type="button" class="af-btn af-icon-btn" id="af-rot-right" title="Rotate Clockwise">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                        <path
                            d="M11.75 2c5.52 0 10 4.48 10 10s-4.48 10-10 10-10-4.48-10-10h2c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8v4l-5-5 5-5v4z" />
                    </svg>
                </button>
            </div>

            <button type="button" class="af-btn af-btn-primary" id="af-confirm"
                style="width: 100%; margin-top: 10px; padding: 12px; font-weight: 600; background: var(--af-accent); color: #fff; border: none; border-radius: 10px; cursor: pointer;">
                Confirm Crop & Save
            </button>
        </div>
    </div>
@endonce

@include('af-uploader::components.scripts')
