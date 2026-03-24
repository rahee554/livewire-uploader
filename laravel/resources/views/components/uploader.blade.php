@props([
    'id' => 'af-uploader-' . uniqid(),
    'cropper' => 'true',
    'ratio' => '1',
    'isCircle' => 'false',
    'maxWidth' => '2000',
    'quality' => '0.92',
    'format' => 'image/webp',
    'preview' => 'true',
    'downloadable' => 'false',
    'label' => 'Click or Drop File',
    'variant' => 'squared', // squared, rect, circled, inline
])

<div class="af-dropzone af-dz-{{ $variant }} {{ $attributes->get('class') }}" 
     x-data="{ uploading: false, progress: 0 }"
     x-on:livewire-upload-start="uploading = true; progress = 0"
     x-on:livewire-upload-finish="uploading = false; progress = 100"
     x-on:livewire-upload-error="uploading = false"
     x-on:livewire-upload-progress="progress = $event.detail.progress">
    
    <span class="af-label">{{ $label }}</span>
    
    <input type="file" 
           id="{{ $id }}"
           {{ $attributes->whereStartsWith('wire:model') }}
           af-cropper="true"
           data-af-cropper="{{ $cropper }}"
           data-af-ratio="{{ $ratio }}"
           data-af-is-circle="{{ $isCircle }}"
           data-af-max-width="{{ $maxWidth }}"
           data-af-quality="{{ $quality }}"
           data-af-format="{{ $format }}"
           data-af-preview="{{ $preview }}"
           data-af-downloadable="{{ $downloadable }}"
           hidden>

    {{-- Progress bar for Livewire --}}
    <div class="af-progress-wrap" :class="{ 'active': uploading }">
        <div class="af-progress-bar" :style="'width: ' + progress + '%'"></div>
    </div>
</div>
