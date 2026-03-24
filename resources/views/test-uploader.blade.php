<!DOCTYPE html>
<html lang="en" x-data="{ darkMode: false }" :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AF Uploader Test</title>
    @livewireStyles
    @afUploaderAssets
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class'
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        /* Test page specific styles - not part of the package */
        .test-card { transition: all 0.3s ease; }
        .test-card:hover { transform: translateY(-2px); box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .dark .test-card { background: #262626; border-color: #404040; }
        .dark body { background: #171717; }
    </style>
</head>
<body class="bg-gray-100 dark:bg-neutral-900 p-4 md:p-10 font-sans text-gray-900 dark:text-gray-100 min-h-screen transition-colors duration-300">
    {{-- Dark Mode Toggle --}}
    <div class="fixed top-4 right-4 z-50">
        <button @click="darkMode = !darkMode" 
            class="p-3 rounded-full bg-white dark:bg-neutral-800 shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-200 dark:border-neutral-700">
            <template x-if="!darkMode">
                <svg class="w-5 h-5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"/>
                </svg>
            </template>
            <template x-if="darkMode">
                <svg class="w-5 h-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/>
                </svg>
            </template>
        </button>
    </div>

    <div class="max-w-5xl mx-auto bg-white dark:bg-neutral-800 p-6 md:p-8 rounded-2xl shadow-xl border border-gray-200 dark:border-neutral-700 transition-colors duration-300">
        <h1 class="text-3xl font-extrabold mb-2 text-center bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">Uploader Test Suite</h1>
        <p class="text-center text-gray-500 dark:text-gray-400 mb-8">AF File Uploader v4.0 - All Features Demo</p>
        
        {{ $slot }}
    </div>

    @livewireScripts
</body>
</html>
