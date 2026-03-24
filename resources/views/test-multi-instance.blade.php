<!DOCTYPE html>
<html lang="en" class="af-dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AF Uploader - Multi-Instance Test</title>
    @livewireStyles
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/packages/artflow-studio/file-uploader/css/main.css">
</head>
<body class="bg-gray-50 dark:bg-gray-900 p-8">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold mb-8 text-gray-900 dark:text-white">
            File Uploader - Multiple Instance Test
        </h1>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Instance 1: Plain Image Upload -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
                    Instance 1: Plain Upload
                </h3>
                <livewire:uploader-test-1 />
            </div>

            <!-- Instance 2: With Cropper -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
                    Instance 2: With Cropper
                </h3>
                <livewire:uploader-test-2 />
            </div>

            <!-- Instance 3: Circular Avatar -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
                    Instance 3: Circular Avatar
                </h3>
                <livewire:uploader-test-3 />
            </div>

            <!-- Instance 4: Video Upload -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
                    Instance 4: Video Upload
                </h3>
                <livewire:uploader-test-4 />
            </div>

            <!-- Instance 5: Document Upload -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
                    Instance 5: Document Upload
                </h3>
                <livewire:uploader-test-5 />
            </div>

            <!-- Instance 6: Large File -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
                    Instance 6: Custom Size
                </h3>
                <livewire:uploader-test-6 />
            </div>
        </div>

        <!-- Test Info -->
        <div class="mt-8 bg-blue-50 dark:bg-blue-900/20 p-6 rounded-lg">
            <h2 class="text-xl font-bold mb-4 text-blue-900 dark:text-blue-100">
                ✅ Test Scenarios
            </h2>
            <ul class="space-y-2 text-blue-800 dark:text-blue-200">
                <li>✓ Upload files to different instances simultaneously</li>
                <li>✓ Verify no state leakage between instances</li>
                <li>✓ Check preview images display correctly</li>
                <li>✓ Test remove functionality per instance</li>
                <li>✓ Verify cropper only affects its own instance</li>
                <li>✓ Test Livewire refresh doesn't break UI</li>
                <li>✓ Verify "LOADED" status doesn't appear in other instances</li>
            </ul>
        </div>
    </div>

    @livewireScripts
    <script src="/packages/artflow-studio/file-uploader/js/index.js" type="module"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
