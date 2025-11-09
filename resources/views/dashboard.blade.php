<x-layouts.app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <!-- Stats Grid -->
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <!-- Total Debt Card -->
            <div class="relative overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 p-6">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-neutral-500 dark:text-neutral-400">Total Debt</p>
                        <h3 class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-50">
                            N${{ number_format(auth()->user()->creditors->flatMap->loans->sum('balance'), 2) }}
                        </h3>
                        <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                            Across {{ auth()->user()->creditors->flatMap->loans->count() }} loan(s)
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-neutral-200 dark:border-neutral-700">
                    <div class="flex items-center gap-2 text-xs">
                        <span class="text-neutral-600 dark:text-neutral-400">Monthly minimum:</span>
                        <span class="font-semibold text-neutral-900 dark:text-neutral-50">N${{ number_format(auth()->user()->creditors->flatMap->loans->sum('minimum_payment'), 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Creditors Card -->
            <div class="relative overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 p-6">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-neutral-500 dark:text-neutral-400">Creditors</p>
                        <h3 class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-50">
                            {{ auth()->user()->creditors->count() }}
                        </h3>
                        <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                            Active institutions
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-neutral-200 dark:border-neutral-700">
                    <a href="{{ route('creditors.index') }}" class="text-xs text-blue-600 dark:text-blue-400 hover:underline font-medium">
                        Manage creditors →
                    </a>
                </div>
            </div>

            <!-- Strategy Card -->
            <div class="relative overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 p-6">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-neutral-500 dark:text-neutral-400">Active Strategy</p>
                        <h3 class="mt-2 text-2xl font-bold text-neutral-900 dark:text-neutral-50 capitalize">
                            {{ str_replace('_', ' ', auth()->user()->debt_strategy ?? 'Not Set') }}
                        </h3>
                        <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                            @if(auth()->user()->monthly_payment)
                                Monthly payment: N${{ number_format(auth()->user()->monthly_payment, 2) }}
                            @else
                                No payment set
                            @endif
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-neutral-200 dark:border-neutral-700">
                    <a href="{{ route('creditors.index') }}" class="text-xs text-green-600 dark:text-green-400 hover:underline font-medium">
                        View payment plan →
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="relative h-full flex-1 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800">
            <div class="p-6 h-full flex flex-col">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-neutral-900 dark:text-neutral-50">Quick Actions</h2>
                </div>
                
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <!-- Add Creditor -->
                    <a href="{{ route('creditors.index') }}" class="group relative overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900 p-6 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-all">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-neutral-900 dark:text-neutral-50 mb-1">Add Creditor</h3>
                                <p class="text-sm text-neutral-500 dark:text-neutral-400">Add a new creditor or institution</p>
                            </div>
                        </div>
                    </a>

                    <!-- Add Loan -->
                    <a href="{{ route('creditors.index') }}" class="group relative overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900 p-6 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-all">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                                <svg class="w-5 h-5 text-green-600 dark:text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-neutral-900 dark:text-neutral-50 mb-1">Add Loan</h3>
                                <p class="text-sm text-neutral-500 dark:text-neutral-400">Track a new debt or loan</p>
                            </div>
                        </div>
                    </a>

                    <!-- View Payment Schedule -->
                    <a href="{{ route('creditors.index') }}" class="group relative overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900 p-6 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-all">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                                <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-neutral-900 dark:text-neutral-50 mb-1">Payment Schedule</h3>
                                <p class="text-sm text-neutral-500 dark:text-neutral-400">View your amortization plan</p>
                            </div>
                        </div>
                    </a>

                    <!-- Update Strategy -->
                    <a href="{{ route('creditors.index') }}" class="group relative overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900 p-6 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-all">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-lg bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                                <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-neutral-900 dark:text-neutral-50 mb-1">Update Strategy</h3>
                                <p class="text-sm text-neutral-500 dark:text-neutral-400">Change your payoff method</p>
                            </div>
                        </div>
                    </a>

                    <!-- View Debt Chart -->
                    <a href="{{ route('creditors.index') }}" class="group relative overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900 p-6 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-all">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-lg bg-pink-100 dark:bg-pink-900/30 flex items-center justify-center">
                                <svg class="w-5 h-5 text-pink-600 dark:text-pink-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-neutral-900 dark:text-neutral-50 mb-1">Debt Chart</h3>
                                <p class="text-sm text-neutral-500 dark:text-neutral-400">Visualize your debt breakdown</p>
                            </div>
                        </div>
                    </a>

                    <!-- Export Data -->
                    <a href="{{ route('creditors.index') }}" class="group relative overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900 p-6 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-all">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-lg bg-teal-100 dark:bg-teal-900/30 flex items-center justify-center">
                                <svg class="w-5 h-5 text-teal-600 dark:text-teal-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-neutral-900 dark:text-neutral-50 mb-1">Export Data</h3>
                                <p class="text-sm text-neutral-500 dark:text-neutral-400">Download your debt records</p>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Empty State if no data -->
                @if(auth()->user()->creditors->isEmpty())
                    <div class="flex-1 flex items-center justify-center mt-8">
                        <div class="text-center max-w-md">
                            <div class="w-16 h-16 rounded-2xl bg-neutral-100 dark:bg-neutral-700 flex items-center justify-center mx-auto mb-4">
                                <svg class="w-8 h-8 text-neutral-400 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-50 mb-2">No creditors yet</h3>
                            <p class="text-sm text-neutral-500 dark:text-neutral-400 mb-6">
                                Get started by adding your first creditor and tracking your debts.
                            </p>
                            <a href="{{ route('creditors.index') }}" class="inline-flex items-center gap-2 px-6 py-3 bg-neutral-900 dark:bg-neutral-50 text-neutral-50 dark:text-neutral-900 rounded-xl hover:bg-neutral-800 dark:hover:bg-neutral-200 transition-all font-medium shadow-lg">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                Add First Creditor
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
