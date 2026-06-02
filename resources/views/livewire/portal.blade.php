<div class="min-h-screen flex items-center justify-center p-6 bg-gray-50 dark:bg-gray-900">
    <div class="max-w-6xl w-full">
        <div class="text-center mb-12">
            <img src="{{ asset('logo/logo.png') }}" alt="{{ __('Logo') }}" class="mx-auto mb-4 h-40 md:h-52 w-auto" onerror="this.style.display='none'">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-2">منبع التميز</h1>
            <p class="text-gray-600 dark:text-gray-400 text-lg">{{ __('Training Center Management System') }}</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <a href="{{ url('/admin') }}" class="group bg-white dark:bg-gray-800 rounded-2xl shadow-md hover:shadow-xl border border-gray-200 dark:border-gray-700 p-8 transition-all">
                <div class="text-center">
                    <div class="mx-auto mb-5 w-16 h-16 rounded-full bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center text-blue-600 group-hover:scale-110 transition-transform">
                        <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-semibold mb-2 text-gray-900 dark:text-white">{{ __('Employees') }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">{{ __('Employee login portal') }}</p>
                    <span class="inline-flex px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium">{{ __('Login') }}</span>
                </div>
            </a>

            <a href="{{ route('trainer.login') }}" wire:navigate class="group bg-white dark:bg-gray-800 rounded-2xl shadow-md hover:shadow-xl border border-gray-200 dark:border-gray-700 p-8 transition-all">
                <div class="text-center">
                    <div class="mx-auto mb-5 w-16 h-16 rounded-full bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center text-emerald-600 group-hover:scale-110 transition-transform">
                        <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-semibold mb-2 text-gray-900 dark:text-white">{{ __('Trainers') }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">{{ __('Trainer login portal') }}</p>
                    <span class="inline-flex px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-medium">{{ __('Login') }}</span>
                </div>
            </a>

            <a href="{{ route('student.login') }}" wire:navigate class="group bg-white dark:bg-gray-800 rounded-2xl shadow-md hover:shadow-xl border border-gray-200 dark:border-gray-700 p-8 transition-all">
                <div class="text-center">
                    <div class="mx-auto mb-5 w-16 h-16 rounded-full bg-purple-100 dark:bg-purple-900/40 flex items-center justify-center text-purple-600 group-hover:scale-110 transition-transform">
                        <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-semibold mb-2 text-gray-900 dark:text-white">{{ __('Students') }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">{{ __('Student login portal') }}</p>
                    <span class="inline-flex px-5 py-2.5 rounded-lg bg-purple-600 hover:bg-purple-700 text-white font-medium">{{ __('Login') }}</span>
                </div>
            </a>
        </div>
    </div>
</div>
