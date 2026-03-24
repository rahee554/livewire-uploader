<div class="max-w-4xl mx-auto bg-white p-8 rounded-xl shadow-lg">
    <h1 class="text-2xl font-bold mb-2 text-center text-indigo-600">🔄 Tab Persistence Test</h1>
    <p class="text-center text-gray-500 mb-8">Upload a file, switch tabs, switch back - the file should persist!</p>

    {{-- Tab Navigation --}}
    <div class="flex gap-2 mb-6 border-b pb-2">
        <button 
            wire:click="switchTab('image')"
            class="px-4 py-2 rounded-t-lg font-semibold transition-colors {{ $activeTab === 'image' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-600 hover:bg-gray-300' }}">
            📷 Image Tab
        </button>
        <button 
            wire:click="switchTab('video')"
            class="px-4 py-2 rounded-t-lg font-semibold transition-colors {{ $activeTab === 'video' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-600 hover:bg-gray-300' }}">
            🎬 Video Tab
        </button>
        <button 
            wire:click="switchTab('document')"
            class="px-4 py-2 rounded-t-lg font-semibold transition-colors {{ $activeTab === 'document' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-600 hover:bg-gray-300' }}">
            📄 Document Tab
        </button>
    </div>

    {{-- Tab Content - All uploaders ALWAYS rendered to preserve state --}}
    <div class="min-h-[350px] p-4 border rounded-lg bg-gray-50">
        {{-- Image Tab --}}
        <div x-data="{ visible: true }" 
             x-init="$watch('$wire.activeTab', v => visible = v === 'image')"
             x-show="visible" 
             x-cloak
             style="display: none;"
             :style="visible ? 'display: block;' : 'display: none;'">
            <h3 class="text-lg font-bold mb-4">Image Upload</h3>
            <p class="text-sm text-gray-500 mb-4">Accepts: JPG, PNG, GIF, WebP</p>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs font-semibold text-gray-600 mb-2">Without Cropper:</p>
                    <div wire:ignore>
                        <x-af-uploader 
                            wire:model="imageFile" 
                            accept="image/*"
                            label="Drop image here or click"
                            variant="rect"
                        />
                    </div>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-600 mb-2">With Cropper:</p>
                    <div wire:ignore>
                        <x-af-uploader 
                            wire:model="cropperImageFile" 
                            accept="image/*"
                            cropper="true"
                            label="Drop image (cropper)"
                            variant="rect"
                        />
                    </div>
                </div>
            </div>

            <div class="mt-4 p-3 rounded bg-white border space-y-1">
                <p class="text-sm font-mono">
                    <span class="font-bold">$imageFile:</span> 
                    @if($imageFile)
                        <span class="text-green-600">✓ Has File ({{ is_object($imageFile) ? get_class($imageFile) : gettype($imageFile) }})</span>
                    @else
                        <span class="text-gray-400">Empty</span>
                    @endif
                </p>
                <p class="text-sm font-mono">
                    <span class="font-bold">$cropperImageFile:</span> 
                    @if($cropperImageFile)
                        <span class="text-green-600">✓ Has File ({{ is_object($cropperImageFile) ? get_class($cropperImageFile) : gettype($cropperImageFile) }})</span>
                    @else
                        <span class="text-gray-400">Empty</span>
                    @endif
                </p>
            </div>
        </div>

        {{-- Video Tab --}}
        <div x-data="{ visible: false }" 
             x-init="$watch('$wire.activeTab', v => visible = v === 'video')"
             x-show="visible" 
             x-cloak
             style="display: none;"
             :style="visible ? 'display: block;' : 'display: none;'">
            <h3 class="text-lg font-bold mb-4">Video Upload</h3>
            <p class="text-sm text-gray-500 mb-4">Accepts: MP4, WebM, MOV</p>
            
            <div wire:ignore>
                <x-af-uploader 
                    wire:model="videoFile" 
                    accept="video/*"
                    label="Drop video here or click"
                    variant="rect"
                />
            </div>

            <div class="mt-4 p-3 rounded bg-white border">
                <p class="text-sm font-mono">
                    <span class="font-bold">$videoFile:</span> 
                    @if($videoFile)
                        <span class="text-green-600">✓ Has File</span>
                    @else
                        <span class="text-gray-400">Empty</span>
                    @endif
                </p>
            </div>
        </div>

        {{-- Document Tab --}}
        <div x-data="{ visible: false }" 
             x-init="$watch('$wire.activeTab', v => visible = v === 'document')"
             x-show="visible" 
             x-cloak
             style="display: none;"
             :style="visible ? 'display: block;' : 'display: none;'">
            <h3 class="text-lg font-bold mb-4">Document Upload</h3>
            <p class="text-sm text-gray-500 mb-4">Accepts: PDF, DOC, TXT</p>
            
            <div wire:ignore>
                <x-af-uploader 
                    wire:model="documentFile" 
                    accept=".pdf,.doc,.docx,.txt"
                    label="Drop document here or click"
                    variant="rect"
                />
            </div>

            <div class="mt-4 p-3 rounded bg-white border">
                <p class="text-sm font-mono">
                    <span class="font-bold">$documentFile:</span> 
                    @if($documentFile)
                        <span class="text-green-600">✓ Has File</span>
                    @else
                        <span class="text-gray-400">Empty</span>
                    @endif
                </p>
            </div>
        </div>
    </div>

    {{-- Debug Panel --}}
    <div class="mt-6 p-4 bg-gray-800 text-white rounded-lg text-sm font-mono">
        <h4 class="font-bold mb-2 text-yellow-400">📊 State Debug</h4>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <span class="text-gray-400">activeTab:</span> 
                <span class="text-cyan-400">{{ $activeTab }}</span>
            </div>
            <div>
                <span class="text-gray-400">imageFile:</span> 
                <span class="{{ $imageFile ? 'text-green-400' : 'text-red-400' }}">{{ $imageFile ? 'SET' : 'NULL' }}</span>
            </div>
            <div>
                <span class="text-gray-400">cropperImageFile:</span> 
                <span class="{{ $cropperImageFile ? 'text-green-400' : 'text-red-400' }}">{{ $cropperImageFile ? 'SET' : 'NULL' }}</span>
            </div>
            <div>
                <span class="text-gray-400">videoFile:</span> 
                <span class="{{ $videoFile ? 'text-green-400' : 'text-red-400' }}">{{ $videoFile ? 'SET' : 'NULL' }}</span>
            </div>
        </div>
    </div>

    <div class="mt-4 text-center text-xs text-gray-400">
        ArtFlow File Uploader - Tab Persistence Test
    </div>
</div>
