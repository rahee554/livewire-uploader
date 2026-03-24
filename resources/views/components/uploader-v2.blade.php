@props([
    'model' => null,
    'cropper' => 'false',
    'preview' => 'true',
    'ratio' => null,
    'isCircle' => 'false',
    'maxWidth' => 2000,
    'quality' => 0.92,
    'variant' => 'plain',
    'label' => 'Drop file or click',
    'width' => null,
    'height' => '200px',
])

@php
    // Generate unique ID per instance for perfect isolation
    $uniqueId = 'af-uploader-' . uniqid() . '-' . str_replace('.', '', microtime(true));
    $wireModel = $attributes->wire('model');
    
    $variantClass = match ($variant) {
        'squared' => 'af-dz-squared',
        'rect' => 'af-dz-rect',
        'circled' => 'af-dz-circled',
        'inline' => 'af-dz-inline',
        default => 'af-dz-plain',
    };

    $style = '';
    if ($width) {
        $style .= "width: {$width};";
    }
    if ($height) {
        $style .= "height: {$height};";
    }
@endphp

<div 
    {{ $attributes->only(['class'])->merge(['class' => 'af-uploader-wrapper']) }} 
    x-cloak
    wire:ignore
    wire:key="{{ $uniqueId }}"
    x-data="afUploaderInstance_{{ $uniqueId }}()"
    @af-status-update-{{ $uniqueId }}.window="onStatusUpdate($event)" 
    @af-file-selected-{{ $uniqueId }}.window="onFileSelected($event)"
    @af-image-cropped-{{ $uniqueId }}.window="onImageCropped($event)"
>
    <div 
        class="af-dropzone {{ $variantClass }}" 
        id="dz-{{ $uniqueId }}" 
        wire:ignore
        :class="{ 
            'is-uploading': isUploading, 
            'has-error': statusType === 'danger',
            'has-file': hasFile && !isUploading && !statusText
        }" 
        style="{{ $style }}"
        @dragover.prevent="dragActive = true"
        @dragleave.prevent="dragActive = false"
        @drop.prevent="handleDrop($event)"
        :class="{ 'drag-active': dragActive }"
    >
        <!-- Close/Remove Button -->
        <button 
            class="af-clear-btn" 
            @click.stop="remove()" 
            x-show="(hasFile || statusText) && !isUploading"
            type="button"
            aria-label="Remove file"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>

        <!-- Default Upload Area -->
        <div 
            class="af-content-default" 
            x-show="!isUploading && !statusText && !hasFile" 
            @click="$refs.fileInput_{{ $uniqueId }}.click()"
        >
            <svg class="af-upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12" />
            </svg>
            <span class="af-label">{{ $label }}</span>
        </div>

        <!-- File Preview Card (FilePond-style) -->
        <div class="af-file-preview-card" x-show="hasFile && !isUploading && !statusText" x-transition:enter="af-fade-in" x-transition:leave="af-fade-out">
            <div class="af-preview-content">
                <!-- Image Preview -->
                <template x-if="filePreview && isImageFile(filePreview)">
                    <div class="af-preview-image">
                        <img :src="filePreview" :alt="fileName" class="af-preview-thumb">
                    </div>
                </template>
                
                <!-- File Icon (non-images) -->
                <template x-if="!filePreview || !isImageFile(filePreview)">
                    <div class="af-preview-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                        </svg>
                    </div>
                </template>
                
                <!-- File Info -->
                <div class="af-preview-info">
                    <p class="af-preview-name" x-text="fileName || 'File'"></p>
                    <p class="af-preview-size" x-text="fileSize" x-show="fileSize"></p>
                </div>
            </div>
        </div>

        <!-- Upload Progress & Status Overlay -->
        <div class="af-status-overlay" :class="{ 'active': statusText || isUploading }" x-transition>
            <!-- Uploading State -->
            <template x-if="isUploading">
                <div class="af-upload-card">
                    <div class="af-card-header">
                        <span class="af-file-tag" x-text="getFileExtension()"></span>
                        <div class="af-loading-dots">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                    
                    <!-- Progress Ring -->
                    <div class="af-progress-ring-container">
                        <svg class="af-progress-ring" viewBox="0 0 36 36">
                            <path 
                                class="af-ring-bg"
                                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" 
                            />
                            <path 
                                class="af-ring-progress" 
                                :style="`stroke-dasharray: ${progress}, 100`"
                                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" 
                            />
                        </svg>
                        <span class="af-ring-text" x-text="progress + '%'"></span>
                    </div>
                    
                    <span class="af-status-label" x-text="statusText"></span>
                </div>
            </template>

            <!-- Success/Error State -->
            <template x-if="!isUploading && statusText">
                <div class="af-status-card" :class="`af-state-${statusType}`">
                    <div class="af-status-icon">
                        <!-- Success Icon -->
                        <template x-if="statusType === 'success'">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M20 6L9 17L4 12" />
                            </svg>
                        </template>
                        
                        <!-- Error Icon -->
                        <template x-if="statusType === 'danger' || statusType === 'error'">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M18 6L6 18M6 6l12 12" />
                            </svg>
                        </template>
                        
                        <!-- Info Icon -->
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

        <!-- Hidden File Input -->
        <input 
            type="file" 
            x-ref="fileInput_{{ $uniqueId }}"
            {{ $attributes->whereDoesntStartWith('wire:model')->whereDoesntStartWith('class')->whereDoesntStartWith('wire:key') }}
            id="{{ $uniqueId }}"
            @change="handleFileSelect($event)"
            @if ($wireModel->value()) data-af-wire-model="{{ $wireModel->value() }}" @endif
            data-af-unique-id="{{ $uniqueId }}"
            af-cropper="{{ $cropper }}" 
            data-af-preview="{{ $preview }}"
            data-af-ratio="{{ $ratio }}" 
            data-af-is-circle="{{ $isCircle }}"
            data-af-max-width="{{ $maxWidth }}" 
            data-af-quality="{{ $quality }}" 
            hidden
        >
    </div>
</div>

@if ($wireModel->name() && isset($errors))
    @error($wireModel->name())
        <small class="text-danger mt-1 d-block text-red-500 text-xs">{{ $message }}</small>
    @enderror
@endif

@once
<script>
// Alpine.js Component Factory - Instance Isolated
function afUploaderInstance_{{ $uniqueId }}() {
    return {
        // State
        progress: 0,
        isUploading: false,
        statusText: '',
        statusType: '',
        hasFile: false,
        filePreview: null,
        fileName: '',
        fileSize: '',
        wireModel: '{{ $wireModel->value() }}',
        uniqueId: '{{ $uniqueId }}',
        activeFile: null,
        dragActive: false,
        
        // Initialization
        init() {
            this.$nextTick(() => {
                // Setup cropper if enabled
                if (window.AF_Cropper && this.$refs['fileInput_{{ $uniqueId }}']) {
                    const input = this.$refs['fileInput_{{ $uniqueId }}'];
                    if (input._af_cleanup) input._af_cleanup();
                    window.AF_Cropper.setupDropzone(input);
                }
                
                // Sync initial state from Livewire
                this.syncInitialState();
            });
            
            // Cleanup on destroy
            this.$watch('$el', () => {
                if (!document.contains(this.$el)) {
                    this.cleanup();
                }
            });
        },
        
        // Sync initial file from Livewire model
        async syncInitialState() {
            if (!this.wireModel) return;
            
            try {
                const val = await this.$wire.get(this.wireModel);
                if (val) {
                    this.hasFile = true;
                    const pathParts = val.toString().split('/');
                    this.fileName = pathParts[pathParts.length - 1];
                    this.filePreview = val.toString();
                    
                    // Calculate file size if possible
                    if (this.activeFile) {
                        this.fileSize = this.formatFileSize(this.activeFile.size);
                    }
                }
            } catch (e) {
                console.warn('AF Uploader: Could not sync initial state', e);
            }
        },
        
        // Handle file selection from input
        handleFileSelect(event) {
            const files = event.target.files;
            if (!files || files.length === 0) return;
            
            const file = files[0];
            this.activeFile = file;
            
            // If cropper is enabled, let AF_Cropper handle it
            if (event.target.getAttribute('af-cropper') === 'true') {
                // AF_Cropper will dispatch af-file-selected-{{ $uniqueId }} or af-image-cropped-{{ $uniqueId }}
                return;
            }
            
            // Otherwise, handle upload directly
            this.startUpload(file);
        },
        
        // Handle drag & drop
        handleDrop(event) {
            this.dragActive = false;
            const files = event.dataTransfer.files;
            if (!files || files.length === 0) return;
            
            const input = this.$refs['fileInput_{{ $uniqueId }}'];
            if (input) {
                // Create new DataTransfer and assign to input
                const dt = new DataTransfer();
                dt.items.add(files[0]);
                input.files = dt.files;
                
                // Trigger change event
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },
        
        // Start upload process
        startUpload(file) {
            if (!this.wireModel || !file) return;
            
            this.isUploading = true;
            this.progress = 0;
            this.statusText = 'Preparing...';
            this.statusType = 'info';
            this.hasFile = false;
            
            const input = this.$refs['fileInput_{{ $uniqueId }}'];
            const isMultiple = input && input.hasAttribute('multiple');
            const uploadTarget = isMultiple ? (Array.from(input.files) || [file]) : file;
            
            // Dispatch upload started event
            this.$dispatch('af-upload-started', { property: this.wireModel, uniqueId: this.uniqueId });
            
            // Livewire upload
            this.$wire.upload(
                this.wireModel, 
                uploadTarget,
                // Success callback
                async (uploadedName) => {
                    this.isUploading = false;
                    this.progress = 100;
                    this.statusText = 'Upload complete!';
                    this.statusType = 'success';
                    this.hasFile = true;
                    this.fileName = file.name || 'File';
                    this.fileSize = this.formatFileSize(file.size);
                    this.filePreview = uploadedName;
                    
                    this.$dispatch('af-upload-finished', { 
                        property: this.wireModel, 
                        response: uploadedName,
                        uniqueId: this.uniqueId
                    });
                    
                    // Commit to Livewire
                    if (isMultiple) {
                        try { await this.$wire.$refresh(); } catch (e) {}
                    } else {
                        if (typeof this.$wire.$commit === 'function') {
                            try { await this.$wire.$commit(); } catch (e) {}
                        }
                    }
                    
                    // Clear success message after delay
                    setTimeout(() => {
                        if (this.statusText === 'Upload complete!') {
                            this.statusText = '';
                        }
                    }, 2500);
                },
                // Error callback
                (err) => {
                    this.isUploading = false;
                    this.statusText = 'Upload failed';
                    this.statusType = 'danger';
                    console.error('AF Uploader Error:', err);
                    
                    this.$dispatch('af-upload-error', { 
                        property: this.wireModel, 
                        error: err,
                        uniqueId: this.uniqueId
                    });
                    
                    // Clear error message after delay
                    setTimeout(() => {
                        if (this.statusText === 'Upload failed') {
                            this.statusText = '';
                        }
                    }, 3000);
                },
                // Progress callback
                (event) => {
                    this.progress = Math.round(event.detail.progress);
                    this.statusText = `Uploading ${this.progress}%`;
                }
            );
        },
        
        // Event handlers from AF_Cropper
        onStatusUpdate(e) {
            const detail = e.detail;
            if (detail.uniqueId && detail.uniqueId !== this.uniqueId) return;
            if (detail.input && detail.input !== this.$refs['fileInput_{{ $uniqueId }}']) return;
            
            this.statusText = detail.text;
            this.statusType = detail.type;
            
            if (detail.progress !== undefined) {
                this.progress = detail.progress;
                this.isUploading = detail.progress < 100 && detail.progress > 0;
            }
            
            if (this.statusType === 'success') {
                this.isUploading = false;
                this.progress = 100;
                this.hasFile = true;
                setTimeout(() => { 
                    if (this.statusText === detail.text) this.statusText = ''; 
                }, 2500);
            }
            
            if (this.statusType === 'danger') {
                this.isUploading = false;
                setTimeout(() => { 
                    if (this.statusText === detail.text) this.statusText = ''; 
                }, 3000);
            }
        },
        
        onImageCropped(e) {
            const detail = e.detail;
            if (detail.uniqueId && detail.uniqueId !== this.uniqueId) return;
            if (detail.input === this.$refs['fileInput_{{ $uniqueId }}']) {
                this.handleSelection(e);
            }
        },
        
        onFileSelected(e) {
            const detail = e.detail;
            if (detail.uniqueId && detail.uniqueId !== this.uniqueId) return;
            if (detail.input === this.$refs['fileInput_{{ $uniqueId }}']) {
                this.handleSelection(e);
            }
        },
        
        handleSelection(e) {
            const detail = e.detail;
            if (detail.input && detail.input !== this.$refs['fileInput_{{ $uniqueId }}']) return;
            
            const { input, file, files, blob } = detail;
            if (!this.wireModel) return;
            
            // Prevent duplicate uploads
            if (this.isUploading && this.activeFile === file) return;
            
            this.activeFile = file;
            this.startUpload(file);
        },
        
        // Remove file
        async remove() {
            if (!this.wireModel) return;
            
            this.statusText = '';
            this.statusType = '';
            this.progress = 0;
            this.isUploading = false;
            this.activeFile = null;
            this.hasFile = false;
            this.fileName = '';
            this.fileSize = '';
            this.filePreview = null;
            
            // Clear cropper if present
            const input = this.$refs['fileInput_{{ $uniqueId }}'];
            if (window.AF_Cropper && input) {
                window.AF_Cropper.clear(input);
            }
            
            // Clear file input
            if (input) {
                input.value = '';
            }
            
            // Revert Livewire upload
            await this.$wire.revertUpload(this.wireModel, '');
            if (typeof this.$wire.$commit === 'function') {
                await this.$wire.$commit();
            }
            
            this.$dispatch('af-upload-reverted', { 
                property: this.wireModel,
                uniqueId: this.uniqueId
            });
        },
        
        // Cleanup
        cleanup() {
            const input = this.$refs['fileInput_{{ $uniqueId }}'];
            if (window.AF_Cropper && input && input._af_cleanup) {
                input._af_cleanup();
            }
        },
        
        // Utility functions
        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        },
        
        isImageFile(path) {
            if (!path) return false;
            return path.match(/\.(jpg|jpeg|png|gif|webp|svg)$/i) !== null;
        },
        
        getFileExtension() {
            if (!this.activeFile) return 'FILE';
            const name = this.activeFile.name || '';
            const parts = name.split('.');
            return parts.length > 1 ? parts[parts.length - 1].toUpperCase() : 'FILE';
        }
    };
}
</script>
@endonce
