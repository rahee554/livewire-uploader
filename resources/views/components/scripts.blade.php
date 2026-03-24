@once
<script>
    // Global cache to preserve blob URLs across Livewire re-renders
    window._afUploaderCache = window._afUploaderCache || {};
    // Track initialized croppers for wire:navigate support
    window._afCropperInitialized = window._afCropperInitialized || false;
    
    // Wire:navigate and Livewire hook for re-initialization
    document.addEventListener('livewire:navigated', () => {
        // Re-scan for cropper elements after navigation
        if (window.AF_Cropper && typeof window.AF_Cropper.scan === 'function') {
            setTimeout(() => window.AF_Cropper.scan(), 50);
        }
    });
    
    // Also handle Livewire component updates
    document.addEventListener('livewire:update', () => {
        if (window.AF_Cropper && typeof window.AF_Cropper.scan === 'function') {
            setTimeout(() => window.AF_Cropper.scan(), 100);
        }
    });
    
    window.afUploader = function(config) {
        return {
            progress: 0,
            isUploading: false,
            statusText: '',
            statusType: '',
            hasFile: false,
            filePreview: null,
            fileName: '',
            fileSize: '',
            accept: config.accept || '',
            wireModel: config.wireModel,
            modelValue: config.modelValue,
            id: config.id,
            activeFile: null,
            dragActive: false,
            pendingFile: null,
            maxSize: config.maxSize,
            cropper: config.cropper || false,
            autoUpload: config.autoUpload !== false,
            multiple: config.multiple || false,
            selectedFiles: [],
            fileCount: 0,
            isResetting: false, // Prevent UI flicker during reset
            
            isImageFile(url) {
                if (!url) return false;
                if (typeof url !== 'string') return false;
                
                if (url.startsWith('data:image/')) return true;
                if (url.startsWith('blob:')) return true;
                
                // If context is strictly image, assume it is an image
                if (this.accept && (this.accept === 'image/*' || this.accept.startsWith('image/')) && !this.accept.includes('video') && !this.accept.includes('pdf')) {
                    return true;
                }

                if (url.match(/\.(jpeg|jpg|gif|png|webp|svg|bmp|tiff)($|\?)/i)) return true;

                if (url.includes('/livewire/preview-file/')) {
                    // If video is allowed, assume it's NOT an image (avoid broken image icon)
                    if (this.accept && (this.accept.includes('video') || this.accept.includes('pdf'))) {
                        return false;
                    }
                    return true;
                }
                
                return false;
            },
            
            getFileType(fileName) {
                if (!fileName) return 'file';
                const ext = fileName.split('.').pop()?.toLowerCase() || '';
                if (['pdf'].includes(ext)) return 'pdf';
                if (['mp4', 'webm', 'mov', 'avi', 'mkv', 'flv', 'wmv'].includes(ext)) return 'video';
                if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff', 'avif'].includes(ext)) return 'image';
                if (['doc', 'docx', 'txt', 'rtf', 'odt'].includes(ext)) return 'doc';
                return 'file';
            },
            
            getFileTypeClass(fileName) {
                const type = this.getFileType(fileName);
                return 'af-type-' + type;
            },

            async init() {
                // Restore cached state if component was re-initialized (Livewire morph)
                // Use wireModel as key since instance ID might change across re-renders
                const cacheKey = this.wireModel || this.id;
                const cache = window._afUploaderCache[cacheKey];
                if (cache && cache.hasFile) {
                    this.filePreview = cache.filePreview;
                    this.fileName = cache.fileName;
                    this.hasFile = cache.hasFile;
                }
                
                this.$nextTick(() => {
                    this.reinit();
                });

                // Sync with entangled model
                this.$watch('modelValue', (value) => {
                    this.syncWithLivewire(value);
                });

                if (this.modelValue) {
                    this.syncWithLivewire(this.modelValue);
                }
            },
            
            initCropper() {
                // Reinitialize the cropper module on every component init
                // This handles wire:navigate scenarios where the script may have run
                // but the DOM was replaced
                if (window.AF_Cropper && typeof window.AF_Cropper.scan === 'function') {
                    window.AF_Cropper.scan();
                }
            },

            async fetchFileFromUrl(url) {
               try {
                   const response = await fetch(url);
                   const blob = await response.blob();
                   const filename = url.split('/').pop() || 'file';
                   return new File([blob], filename, { type: blob.type });
               } catch (e) {
                   console.error('Error hydrating file from URL:', e);
                   return null;
               }
            },

            reinit() {
                const fileInput = this.$refs['fileInput_' + this.id];
                // Only if external cropper lib is present and needed
                if (window.AF_Cropper && fileInput && this.cropper) {
                    if (fileInput._af_cleanup) fileInput._af_cleanup();
                    window.AF_Cropper.setupDropzone(fileInput);
                }
            },

            async syncWithLivewire(val) {
                if (this.isUploading) {
                    return;
                }

                if (val === undefined || val === null || val === '' || (Array.isArray(val) && val.length === 0)) {
                    if (this.hasFile && !this.isUploading && !this.pendingFile) {
                        this.clearState();
                    }
                    return;
                }

                let valueToPreview = Array.isArray(val) ? val[0] : val;
                
                // If we already have a blob preview and filename from current upload, preserve them
                if (this.filePreview && this.filePreview.startsWith('blob:')) {
                    this.hasFile = true;
                    // Keep existing filename which should be the original file name
                    return;
                }
                
                if (typeof valueToPreview === 'string') {
                    // Handle Livewire temporary file reference
                    if (valueToPreview.indexOf('livewire-file:') === 0) {
                        // For temporary files, we can't get a preview URL without server call
                        // Just show file is present - keep existing filename if we have one
                        this.hasFile = true;
                        if (!this.fileName) {
                            this.fileName = 'Uploaded file';
                        }
                        // Don't try to set preview - it will 404
                        return;
                    }

                    // Check if this is a Livewire temp filename (hash-like pattern with no slashes)
                    // These can't be loaded as image URLs - keep existing blob preview if we have it
                    // Matches: hash filenames like "abc123...xyz.webp" or cropped files like "cropped_1234567890.webp"
                    const isLivewireHashFilename = !valueToPreview.includes('/') && 
                        (/^[a-zA-Z0-9]{20,}\.[\w]+$/.test(valueToPreview) || 
                         /^cropped_\d+\.[\w]+$/.test(valueToPreview));
                    
                    if (isLivewireHashFilename) {
                        // This is a Livewire temp filename, can't use as URL
                        this.hasFile = true;
                        // Keep existing fileName and filePreview (blob URL) if present
                        return;
                    }

                    // For regular URLs (persisted files), update preview
                    if (valueToPreview !== this.filePreview) {
                        this.hasFile = true;
                        this.filePreview = valueToPreview;
                        // Only update filename if we don't already have one
                        if (!this.fileName) {
                            const parts = valueToPreview.split('/');
                            this.fileName = parts[parts.length - 1] || 'File';
                        }
                        
                        // Hydrate file object from URL if not already present
                        // This ensures that if we switch tabs, the "file" is "there" even if just a preview
                        // Note: For actual form submission, Livewire uses the wire:model property.
                        // We primarily need this for visual consistency and potentially for any client-side ops.
                        
                        // Optional: if we want to truly emulate the file being selected in the input (not possible for security)
                        // But we can set pendingFile so other logic works.
                        // if (!this.pendingFile) {
                        //    this.pendingFile = await this.fetchFileFromUrl(valueToPreview);
                        // }
                    }
                } else if (valueToPreview && typeof valueToPreview === 'object') {
                    this.hasFile = true;
                    // Handle TemporaryUploadedFile serialization
                    const path = valueToPreview.preview_url || valueToPreview.temporary_url || valueToPreview.url || valueToPreview.path || null;
                    
                    if (path) {
                        this.filePreview = path;
                        
                        // Special check for Livewire temporaryUrl
                        if (valueToPreview.temporary_url) {
                             this.filePreview = valueToPreview.temporary_url;
                        }
                    } else {
                         // Fallback relative path handling if needed
                    }
                    
                    this.fileName = valueToPreview.name || valueToPreview.client_name || 'File';
                }
            },

            clearState() {
                this.isResetting = true; // Prevent UI flicker
                this.hasFile = false;
                this.filePreview = null;
                this.fileName = '';
                this.pendingFile = null;
                this.activeFile = null;
                this.selectedFiles = [];
                this.fileCount = 0;
                this.progress = 0;
                this.isUploading = false;
                this.statusText = '';
                this.statusType = '';
                const input = this.$refs['fileInput_' + this.id];
                if (input) input.value = '';
                // Clear cache using wireModel as key
                const cacheKey = this.wireModel || this.id;
                delete window._afUploaderCache[cacheKey];
                // Allow UI to settle before enabling transitions again
                this.$nextTick(() => {
                    setTimeout(() => {
                        this.isResetting = false;
                    }, 50);
                });
            },

            formatFileSize(bytes) {
                if (!bytes) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
            },

            onStatusUpdate(e) {
                const detail = e.detail;
                if (detail.id !== this.id) return;
        
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
                    const input = this.$refs['fileInput_' + this.id];
                    if (input) input.value = ''; // Clear input to allow re-selection
                    setTimeout(() => { if (this.statusText === detail.text) this.statusText = ''; }, 2500);
                }
                if (this.statusType === 'danger' || this.statusType === 'error') {
                    this.handleError(detail.text);
                }
            },

            onExternalFileSelected(e) {
                const detail = e.detail;
                if (detail.id !== this.id) return;
                // Validate file type for drag-and-drop where browser accept is bypassed
                const fileInput = this.$refs['fileInput_' + this.id];
                const acceptStr = fileInput?.accept || '';
                if (!this.isTypeAccepted(detail.file, acceptStr)) {
                    const ext = detail.file.name.split('.').pop()?.toUpperCase() || detail.file.type || 'unknown';
                    this.handleError('Unsupported file type: ' + ext);
                    return;
                }
                this.handleFileSelection(detail.file);
            },

            onImageCropped(e) {
                const detail = e.detail;
                if (detail.id !== this.id) return;
                this.handleFileSelection(detail.file);
            },

            onInputFileChange(e) {
                const files = e.target.files;
                if (!files || files.length === 0) return;

                // Client-Side Max Size Validation (MB)
                for (let i = 0; i < files.length; i++) {
                    if (this.maxSize > 0 && files[i].size > (this.maxSize * 1024 * 1024)) {
                        this.handleError('File too large (max ' + this.maxSize + 'MB)' + (files.length > 1 ? ': ' + files[i].name : ''));
                        e.target.value = '';
                        return;
                    }
                }

                // Client-Side File Type Validation against the accept attribute
                const acceptStr = e.target.accept || '';
                for (let i = 0; i < files.length; i++) {
                    if (!this.isTypeAccepted(files[i], acceptStr)) {
                        const ext = files[i].name.split('.').pop()?.toUpperCase() || files[i].type || 'unknown';
                        this.handleError('Unsupported file type: ' + ext);
                        e.target.value = '';
                        return;
                    }
                }

                // Multiple file mode
                if (this.multiple) {
                    this.handleMultipleFiles(Array.from(files));
                    return;
                }

                const file = files[0];

                // Delegate to Cropper if cropper is enabled, AF_Cropper module is loaded, and it's an image
                const isCropper = this.cropper;
                const hasCropperModule = window.AF_Cropper && typeof window.AF_Cropper.handleFile === 'function';
                
                if (isCropper && hasCropperModule && file.type.startsWith('image/')) {
                     // Let AF_Cropper handle it - it will dispatch af-image-cropped event when done
                     // The cropper module will open the modal and handle the cropping
                     return;
                }

                this.handleFileSelection(file);
            },

            async handleFileSelection(file) {
                if (!this.wireModel) return;

                // Convert to WebP if requested (non-cropped images; cropped images are already WebP)
                const fileInput = this.$refs['fileInput_' + this.id];
                if (fileInput && file.type.startsWith('image/') && file.type !== 'image/webp') {
                    const convertFormat = fileInput.dataset?.afConvert;
                    if (convertFormat === 'webp') {
                        const isLossless = fileInput.dataset?.afLossless === 'true';
                        const quality = isLossless ? 1.0 : parseFloat(fileInput.dataset?.afQuality || 0.80);
                        file = await this.convertToWebp(file, quality);
                    }
                }

                this.pendingFile = file;
                this.fileName = file.name || 'File';
                this.fileSize = this.formatFileSize(file.size);
                this.hasFile = true;
                this.statusText = '';
                
                // Local preview generation
                if (file.type && file.type.startsWith('image/')) {
                    this.filePreview = URL.createObjectURL(file);
                } else if (file instanceof Blob && file.type.startsWith('image/')) {
                    this.filePreview = URL.createObjectURL(file);
                } else {
                    this.filePreview = null;
                }
                
                // Cache state for Livewire re-render recovery using wireModel as key
                const cacheKey = this.wireModel || this.id;
                window._afUploaderCache[cacheKey] = {
                    filePreview: this.filePreview,
                    fileName: this.fileName,
                    hasFile: this.hasFile
                };
                
                if (this.autoUpload) {
                    setTimeout(() => {
                        this.uploadFile(file);
                    }, 100);
                } else {
                    this.statusText = 'Ready to upload';
                    this.statusType = 'info';
                }
            },

            uploadFile(file) {
                if (this.isUploading) return;
                
                // Validate that we have an actual File or Blob, not a JSON object
                if (!(file instanceof File) && !(file instanceof Blob)) {
                    console.error('AF Uploader: Invalid file type - expected File or Blob, got:', typeof file);
                    this.handleError('Invalid file type');
                    return;
                }
                
                // Additional check: ensure it's not a JSON file masquerading as something else
                if (file.type === 'application/json' || (file.name && file.name.endsWith('.json'))) {
                    console.error('AF Uploader: JSON files are not supported for direct upload');
                    this.handleError('Invalid file format');
                    return;
                }

                this.activeFile = file;
                this.isUploading = true;
                this.progress = 0;
                this.statusText = 'Uploading...';
                this.statusType = 'info';
        
                this.$wire.upload(this.wireModel, file, 
                    async (uploadedName) => {
                        // Success
                        this.isUploading = false;
                        this.progress = 100;
                        this.statusText = 'Success';
                        this.statusType = 'success';
                        this.hasFile = true;
                        // Keep the blob preview if we have one - don't overwrite with server path
                        // uploadedName is a hash filename that can't be loaded as image URL
                        // this.filePreview should already have the blob URL from handleFileSelection
                        this.pendingFile = null;
                        
                        // Clear input so if user removes and re-uploads same file, change event fires
                        const input = this.$refs['fileInput_' + this.id];
                        if (input) input.value = ''; 

                        this.$dispatch('af-upload-finished', { property: this.wireModel, response: uploadedName, id: this.id });

                        if (typeof this.$wire.$commit === 'function') {
                            try { await this.$wire.$commit(); } catch (e) {}
                        }

                        setTimeout(() => { if (this.statusText === 'Success') this.statusText = ''; }, 2000);
                    },
                    (err) => {
                        // Error - auto reset handled by handleError
                        this.handleError('Upload Failed');
                        console.error('AF Uploader Error:', err);
                        this.$dispatch('af-upload-error', { property: this.wireModel, error: err, id: this.id });
                    },
                    (event) => {
                        this.progress = Math.round(event.detail.progress);
                    }
                );
            },

            handleMultipleFiles(files) {
                if (!this.wireModel) return;

                this.selectedFiles = files;
                this.fileCount = files.length;
                this.hasFile = true;
                this.fileName = files.length + ' file' + (files.length > 1 ? 's' : '') + ' selected';
                this.statusText = '';

                // Preview first image if available
                const firstImage = files.find(f => f.type && f.type.startsWith('image/'));
                this.filePreview = firstImage ? URL.createObjectURL(firstImage) : null;

                // Cache state
                const cacheKey = this.wireModel || this.id;
                window._afUploaderCache[cacheKey] = {
                    filePreview: this.filePreview,
                    fileName: this.fileName,
                    hasFile: this.hasFile
                };

                if (this.autoUpload) {
                    setTimeout(() => this.uploadMultipleFiles(files), 100);
                } else {
                    this.statusText = 'Ready to upload ' + files.length + ' files';
                    this.statusType = 'info';
                }
            },

            uploadMultipleFiles(files) {
                if (this.isUploading) return;

                this.isUploading = true;
                this.progress = 0;
                this.statusText = 'Uploading ' + files.length + ' files...';
                this.statusType = 'info';

                this.$wire.uploadMultiple(this.wireModel, files,
                    async (uploadedFilenames) => {
                        this.isUploading = false;
                        this.progress = 100;
                        this.statusText = 'Success';
                        this.statusType = 'success';
                        this.hasFile = true;
                        this.selectedFiles = [];

                        const input = this.$refs['fileInput_' + this.id];
                        if (input) input.value = '';

                        this.$dispatch('af-upload-finished', {
                            property: this.wireModel,
                            response: uploadedFilenames,
                            id: this.id,
                            multiple: true
                        });

                        if (typeof this.$wire.$commit === 'function') {
                            try { await this.$wire.$commit(); } catch (e) {}
                        }

                        setTimeout(() => { if (this.statusText === 'Success') this.statusText = ''; }, 2000);
                    },
                    (err) => {
                        this.handleError('Upload Failed');
                        console.error('AF Uploader Multiple Error:', err);
                    },
                    (event) => {
                        this.progress = Math.round(event.detail.progress);
                    }
                );
            },

            handleError(msg) {
                this.isUploading = false;
                this.statusText = msg;
                this.statusType = 'danger';
                this.pendingFile = null;
                this.activeFile = null;
                const input = this.$refs['fileInput_' + this.id];
                if (input) input.value = ''; // CRITICAL: Reset input to unlock interactions
                
                // Auto-reset after showing error message
                setTimeout(() => { 
                    if (this.statusText === msg && this.statusType === 'danger') {
                        this.clearState();
                    }
                }, 3000);
            },

            async remove() {
                if (!this.wireModel || this.isResetting) return;
                
                // Set resetting flag early to prevent click events from firing
                this.isResetting = true;
                
                // Clear UI state
                this.clearState();
                
                // Use the entangled modelValue to clear - this is the most reliable way
                // since entangle creates a proper two-way binding that survives wire:ignore
                this.modelValue = this.multiple ? [] : null;
            },

            cancelUpload() {
                // Cancel ongoing upload
                this.isUploading = false;
                this.progress = 0;
                this.statusText = 'Upload cancelled';
                this.statusType = 'info';
                this.pendingFile = null;
                this.activeFile = null;
                
                const input = this.$refs['fileInput_' + this.id];
                if (input) input.value = '';
                
                // Clear the status message after a delay
                setTimeout(() => {
                    if (this.statusText === 'Upload cancelled') {
                        this.clearState();
                    }
                }, 2000);
            },

            isImageFile(path) {
                if (!path) return false;
                if (path instanceof File || path instanceof Blob) return path.type.startsWith('image/');
                const str = path.toString();
                return str.match(/\.(jpg|jpeg|png|gif|webp|svg|avif)/i) !== null || 
                       str.startsWith('blob:') || 
                       str.startsWith('data:image/') || 
                       str.includes('livewire-file-preview') ||
                       str.includes('storage/') || 
                       str.includes('temp');
            },

            /**
             * Check whether a File matches the given accept attribute string.
             * Handles MIME types (image/webp), wildcards (image/*), and extensions (.jpg).
             */
            isTypeAccepted(file, acceptStr) {
                if (!acceptStr || acceptStr.trim() === '' || acceptStr.trim() === '*' || acceptStr.trim() === '*/*') {
                    return true;
                }
                const accepted = acceptStr.split(',').map(t => t.trim().toLowerCase());
                const fileType = (file.type || '').toLowerCase();
                const fileExt = '.' + (file.name || '').split('.').pop().toLowerCase();
                return accepted.some(type => {
                    if (!type || type === '*' || type === '*/*') return true;
                    if (type.endsWith('/*')) return fileType.startsWith(type.replace('/*', '/'));
                    if (type.startsWith('.')) return fileExt === type;
                    return fileType === type;
                });
            },

            /**
             * Convert an image File to WebP format using an off-screen Canvas.
             * Falls back to the original file if conversion fails.
             */
            async convertToWebp(file, quality = 0.80) {
                return new Promise(resolve => {
                    const img = new Image();
                    const url = URL.createObjectURL(file);
                    img.onload = () => {
                        URL.revokeObjectURL(url);
                        const canvas = document.createElement('canvas');
                        canvas.width = img.naturalWidth;
                        canvas.height = img.naturalHeight;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0);
                        canvas.toBlob(blob => {
                            if (!blob) { resolve(file); return; }
                            const name = (file.name || 'image').replace(/\.[^.]+$/, '') + '.webp';
                            resolve(new File([blob], name, { type: 'image/webp' }));
                        }, 'image/webp', quality);
                    };
                    img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
                    img.src = url;
                });
            }
        }
    }
</script>
@endonce
