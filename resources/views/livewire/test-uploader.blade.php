<div class="max-w-6xl mx-auto p-6 space-y-8">
    {{-- Header --}}
    <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">🚀 AF File Uploader - Test Suite</h1>
        <p class="text-gray-500">Comprehensive testing of all uploader features</p>
    </div>

    {{-- Section 1: Basic Uploads --}}
    <section class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-700 mb-4 flex items-center gap-2">
            <span class="w-8 h-8 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-sm">1</span>
            Basic File Uploads
        </h2>
        <div class="grid md:grid-cols-3 gap-6">
            {{-- Simple Image --}}
            <div class="space-y-2">
                <label class="text-sm font-medium text-gray-600">Image Upload</label>
                <x-af-uploader 
                    wire:model="simpleImage" 
                    accept="image/*"
                    label="Drop image here"
                />
                <p class="text-xs text-gray-400">
                    Status: @if($simpleImage) <span class="text-green-600">✓ Uploaded</span> @else <span class="text-gray-400">Empty</span> @endif
                </p>
            </div>

            {{-- Simple Video --}}
            <div class="space-y-2">
                <label class="text-sm font-medium text-gray-600">Video Upload</label>
                <x-af-uploader 
                    wire:model="simpleVideo" 
                    accept="video/*"
                    label="Drop video here"
                />
                <p class="text-xs text-gray-400">
                    Status: @if($simpleVideo) <span class="text-green-600">✓ Uploaded</span> @else <span class="text-gray-400">Empty</span> @endif
                </p>
            </div>

            {{-- Simple Document --}}
            <div class="space-y-2">
                <label class="text-sm font-medium text-gray-600">Document Upload</label>
                <x-af-uploader 
                    wire:model="simpleDocument" 
                    accept=".pdf,.doc,.docx,.txt"
                    label="Drop document here"
                />
                <p class="text-xs text-gray-400">
                    Status: @if($simpleDocument) <span class="text-green-600">✓ Uploaded</span> @else <span class="text-gray-400">Empty</span> @endif
                </p>
            </div>
        </div>
    </section>

    {{-- Section 2: Cropper Tests --}}
    <section class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-700 mb-4 flex items-center gap-2">
            <span class="w-8 h-8 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center text-sm">2</span>
            Image Cropper Tests
        </h2>
        <div class="grid md:grid-cols-3 gap-6">
            {{-- Square Crop --}}
            <div class="space-y-2">
                <label class="text-sm font-medium text-gray-600">Square Crop (1:1)</label>
                <x-af-uploader 
                    wire:model="cropperSquare" 
                    cropper="true"
                    ratio="1/1"
                    variant="squared"
                    label="Crop 1:1"
                />
                <p class="text-xs text-gray-400">
                    Status: @if($cropperSquare) <span class="text-green-600">✓ Cropped</span> @else <span class="text-gray-400">Empty</span> @endif
                </p>
            </div>

            {{-- Wide Crop --}}
            <div class="space-y-2">
                <label class="text-sm font-medium text-gray-600">Wide Crop (16:9)</label>
                <x-af-uploader 
                    wire:model="cropperWide" 
                    cropper="true"
                    ratio="16/9"
                    variant="rect"
                    label="Crop 16:9"
                />
                <p class="text-xs text-gray-400">
                    Status: @if($cropperWide) <span class="text-green-600">✓ Cropped</span> @else <span class="text-gray-400">Empty</span> @endif
                </p>
            </div>

            {{-- Circle Crop --}}
            <div class="space-y-2 flex flex-col items-center">
                <label class="text-sm font-medium text-gray-600">Circle Avatar</label>
                <x-af-uploader 
                    wire:model="cropperCircle" 
                    cropper="true"
                    ratio="1/1"
                    is-circle="true"
                    variant="circled"
                    label="Avatar"
                    width="150px"
                />
                <p class="text-xs text-gray-400">
                    Status: @if($cropperCircle) <span class="text-green-600">✓ Cropped</span> @else <span class="text-gray-400">Empty</span> @endif
                </p>
            </div>
        </div>
    </section>

    {{-- Section 3: Variant Tests --}}
    <section class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-700 mb-4 flex items-center gap-2">
            <span class="w-8 h-8 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-sm">3</span>
            Layout Variants
        </h2>
        <div class="grid md:grid-cols-5 gap-4">
            {{-- Plain --}}
            <div class="space-y-2">
                <label class="text-xs font-medium text-gray-600 text-center block">Plain</label>
                <x-af-uploader 
                    wire:model="variantPlain" 
                    variant="plain"
                    label="Plain"
                />
            </div>

            {{-- Squared --}}
            <div class="space-y-2">
                <label class="text-xs font-medium text-gray-600 text-center block">Squared</label>
                <x-af-uploader 
                    wire:model="variantSquared" 
                    variant="squared"
                    label="Square"
                />
            </div>

            {{-- Rect --}}
            <div class="space-y-2">
                <label class="text-xs font-medium text-gray-600 text-center block">Rectangle</label>
                <x-af-uploader 
                    wire:model="variantRect" 
                    variant="rect"
                    label="Rect"
                />
            </div>

            {{-- Circled --}}
            <div class="space-y-2 flex flex-col items-center">
                <label class="text-xs font-medium text-gray-600 text-center block">Circled</label>
                <x-af-uploader 
                    wire:model="variantCircled" 
                    variant="circled"
                    label="Circle"
                />
            </div>

            {{-- Inline --}}
            <div class="space-y-2">
                <label class="text-xs font-medium text-gray-600 text-center block">Inline</label>
                <x-af-uploader 
                    wire:model="variantInline" 
                    variant="inline"
                    label="Inline Upload"
                />
            </div>
        </div>
    </section>

    {{-- Section 4: Error Handling Test --}}
    <section class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-700 mb-4 flex items-center gap-2">
            <span class="w-8 h-8 bg-red-100 text-red-600 rounded-full flex items-center justify-center text-sm">4</span>
            Error Handling Test
        </h2>
        <div class="max-w-md">
            <p class="text-sm text-gray-500 mb-4">Upload a file larger than 1MB to test error handling and auto-reset:</p>
            <x-af-uploader 
                wire:model="errorTest" 
                max-size="1"
                label="Max 1MB - Test Error"
            />
        </div>
    </section>

    {{-- Section 5: Advanced Features --}}
    <section class="test-card bg-white dark:bg-neutral-800 rounded-xl shadow-lg p-6 border border-gray-100 dark:border-neutral-700">
        <h2 class="text-xl font-bold text-gray-700 dark:text-gray-200 mb-4 flex items-center gap-2">
            <span class="w-8 h-8 bg-cyan-100 dark:bg-cyan-900 text-cyan-600 dark:text-cyan-400 rounded-full flex items-center justify-center text-sm">5</span>
            Advanced Features
        </h2>
        <div class="grid md:grid-cols-3 gap-6">
            {{-- Target Size --}}
            <div class="space-y-2">
                <label class="text-sm font-medium text-gray-600 dark:text-gray-300">Target Size (250KB)</label>
                <x-af-uploader 
                    wire:model="targetSizeTest" 
                    cropper="true"
                    target-size="250"
                    label="Max 250KB output"
                />
                <p class="text-xs text-gray-400 dark:text-gray-500">
                    Status: @if($targetSizeTest) <span class="text-green-600">✓ Optimized</span> @else <span>Empty</span> @endif
                </p>
            </div>

            {{-- Convert to WebP --}}
            <div class="space-y-2">
                <label class="text-sm font-medium text-gray-600 dark:text-gray-300">Convert to WebP</label>
                <x-af-uploader 
                    wire:model="convertTest" 
                    cropper="true"
                    convert="webp"
                    label="Auto WebP"
                />
                <p class="text-xs text-gray-400 dark:text-gray-500">
                    Status: @if($convertTest) <span class="text-green-600">✓ Converted</span> @else <span>Empty</span> @endif
                </p>
            </div>

            {{-- Optimized --}}
            <div class="space-y-2">
                <label class="text-sm font-medium text-gray-600 dark:text-gray-300">Optimized Upload</label>
                <x-af-uploader 
                    wire:model="optimizedTest" 
                    cropper="true"
                    optimized="true"
                    quality="0.8"
                    label="Optimized"
                />
                <p class="text-xs text-gray-400 dark:text-gray-500">
                    Status: @if($optimizedTest) <span class="text-green-600">✓ Optimized</span> @else <span>Empty</span> @endif
                </p>
            </div>
        </div>
    </section>

    {{-- Section 6: Custom Sizing --}}
    <section class="test-card bg-white dark:bg-neutral-800 rounded-xl shadow-lg p-6 border border-gray-100 dark:border-neutral-700">
        <h2 class="text-xl font-bold text-gray-700 dark:text-gray-200 mb-4 flex items-center gap-2">
            <span class="w-8 h-8 bg-amber-100 dark:bg-amber-900 text-amber-600 dark:text-amber-400 rounded-full flex items-center justify-center text-sm">6</span>
            Custom Sizing (w-100, h-100px)
        </h2>
        <div class="grid md:grid-cols-2 gap-6">
            {{-- Custom Width --}}
            <div class="space-y-2">
                <label class="text-sm font-medium text-gray-600 dark:text-gray-300">Width: 100% (w-100)</label>
                <x-af-uploader 
                    wire:model="customWidth" 
                    width="100%"
                    height="80px"
                    label="Full width uploader"
                    variant="inline"
                />
            </div>

            {{-- Custom Height --}}
            <div class="space-y-2">
                <label class="text-sm font-medium text-gray-600 dark:text-gray-300">Fixed 150x150px</label>
                <x-af-uploader 
                    wire:model="customHeight" 
                    width="150px"
                    height="150px"
                    label="Fixed size"
                    variant="squared"
                />
            </div>
        </div>
    </section>

    {{-- Section 7: Debug Info --}}
    <section class="bg-gray-50 dark:bg-neutral-900 rounded-xl p-6 border border-gray-200 dark:border-neutral-700">
        <h2 class="text-lg font-bold text-gray-700 dark:text-gray-200 mb-4">📊 Debug Information</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs font-mono">
            <div class="bg-white dark:bg-neutral-800 p-3 rounded border border-gray-200 dark:border-neutral-700">
                <span class="text-gray-500 dark:text-gray-400">simpleImage:</span><br>
                <span class="text-gray-800 dark:text-gray-200">{{ $simpleImage ? 'Has File' : 'null' }}</span>
            </div>
            <div class="bg-white dark:bg-neutral-800 p-3 rounded border border-gray-200 dark:border-neutral-700">
                <span class="text-gray-500 dark:text-gray-400">cropperSquare:</span><br>
                <span class="text-gray-800 dark:text-gray-200">{{ $cropperSquare ? 'Has File' : 'null' }}</span>
            </div>
            <div class="bg-white dark:bg-neutral-800 p-3 rounded border border-gray-200 dark:border-neutral-700">
                <span class="text-gray-500 dark:text-gray-400">cropperCircle:</span><br>
                <span class="text-gray-800 dark:text-gray-200">{{ $cropperCircle ? 'Has File' : 'null' }}</span>
            </div>
            <div class="bg-white dark:bg-neutral-800 p-3 rounded border border-gray-200 dark:border-neutral-700">
                <span class="text-gray-500 dark:text-gray-400">errorTest:</span><br>
                <span class="text-gray-800 dark:text-gray-200">{{ $errorTest ? 'Has File' : 'null' }}</span>
            </div>
            <div class="bg-white dark:bg-neutral-800 p-3 rounded border border-gray-200 dark:border-neutral-700">
                <span class="text-gray-500 dark:text-gray-400">targetSizeTest:</span><br>
                <span class="text-gray-800 dark:text-gray-200">{{ $targetSizeTest ? 'Has File' : 'null' }}</span>
            </div>
            <div class="bg-white dark:bg-neutral-800 p-3 rounded border border-gray-200 dark:border-neutral-700">
                <span class="text-gray-500 dark:text-gray-400">convertTest:</span><br>
                <span class="text-gray-800 dark:text-gray-200">{{ $convertTest ? 'Has File' : 'null' }}</span>
            </div>
            <div class="bg-white dark:bg-neutral-800 p-3 rounded border border-gray-200 dark:border-neutral-700">
                <span class="text-gray-500 dark:text-gray-400">simpleVideo:</span><br>
                <span class="text-gray-800 dark:text-gray-200">{{ $simpleVideo ? 'Has File' : 'null' }}</span>
            </div>
            <div class="bg-white dark:bg-neutral-800 p-3 rounded border border-gray-200 dark:border-neutral-700">
                <span class="text-gray-500 dark:text-gray-400">simpleDocument:</span><br>
                <span class="text-gray-800 dark:text-gray-200">{{ $simpleDocument ? 'Has File' : 'null' }}</span>
            </div>
        </div>
    </section>
</div>
