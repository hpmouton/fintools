<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>FinTools - Smart Debt Management</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Styles -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <style>
            * {
                font-family: 'Inter', sans-serif;
            }
            
            @keyframes float {
                0%, 100% { transform: translateY(0px) rotate(0deg); }
                50% { transform: translateY(-20px) rotate(3deg); }
            }
            
            @keyframes gradient {
                0%, 100% { background-position: 0% 50%; }
                50% { background-position: 100% 50%; }
            }
            
            .animate-float {
                animation: float 6s ease-in-out infinite;
            }
            
            .animate-gradient {
                background-size: 200% 200%;
                animation: gradient 15s ease infinite;
            }
            
            .glass {
                background: rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
            }
            
            .glass-border {
                border: 1px solid rgba(255, 255, 255, 0.2);
            }
        </style>
    </head>
    <body class="min-h-screen bg-gradient-to-br from-zinc-900 via-zinc-800 to-zinc-900 overflow-hidden">
        <!-- Floating background elements -->
        <div class="fixed inset-0 overflow-hidden pointer-events-none">
            <div class="absolute top-20 left-20 w-72 h-72 bg-white/5 rounded-full blur-3xl animate-float"></div>
            <div class="absolute bottom-20 right-20 w-96 h-96 bg-zinc-700/10 rounded-full blur-3xl animate-float" style="animation-delay: 2s;"></div>
            <div class="absolute top-1/2 left-1/2 w-80 h-80 bg-zinc-600/5 rounded-full blur-3xl animate-float" style="animation-delay: 4s;"></div>
        </div>

        <div class="relative min-h-screen flex flex-col">
            <!-- Navigation -->
            @if (Route::has('login'))
                <nav class="w-full p-6 lg:p-8">
                    <div class="max-w-7xl mx-auto flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-xl flex items-center justify-center glass-border overflow-hidden">
                                <img src="{{ asset('images/logo.png') }}" alt="FinTools Logo" class="w-full h-full object-cover" />
                            </div>
                            <span class="text-white text-xl font-bold">FinTools</span>
                        </div>
                        <div class="flex items-center gap-3">
                            @auth
                                <a
                                    href="{{ url('/dashboard') }}"
                                    class="px-6 py-2.5 glass glass-border text-white rounded-xl hover:bg-white/20 transition-all font-medium"
                                >
                                    Dashboard
                                </a>
                            @else
                                <a
                                    href="{{ route('login') }}"
                                    class="px-6 py-2.5 text-white/80 hover:text-white hover:bg-white/10 rounded-xl transition-all font-medium"
                                >
                                    Log in
                                </a>

                                @if (Route::has('register'))
                                    <a
                                        href="{{ route('register') }}"
                                        class="px-6 py-2.5 bg-white text-zinc-900 rounded-xl hover:bg-zinc-100 transition-all font-medium shadow-lg"
                                    >
                                        Get Started
                                    </a>
                                @endif
                            @endauth
                        </div>
                    </div>
                </nav>
            @endif

            <!-- Hero Section -->
            <main class="flex-1 flex items-center justify-center p-6 lg:p-8">
                <div class="max-w-6xl mx-auto w-full">
                    <div class="grid lg:grid-cols-2 gap-12 items-center">
                        <!-- Left Content -->
                        <div class="text-center lg:text-left space-y-8">
                            <div class="inline-flex items-center gap-2 px-4 py-2 glass glass-border rounded-full text-white text-sm font-medium">
                                <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                                Smart Debt Management
                            </div>
                            
                            <h1 class="text-5xl lg:text-7xl font-bold text-white leading-tight">
                                Take Control of Your
                                <span class="bg-gradient-to-r from-zinc-200 to-zinc-400 bg-clip-text text-transparent">
                                    Financial Future
                                </span>
                            </h1>
                            
                            <p class="text-xl text-zinc-300 leading-relaxed">
                                Track your debts, create payment strategies, and visualize your journey to financial freedom with our powerful tools.
                            </p>


                        </div>

                        <!-- Right Content - Feature Cards -->
                        <div class="space-y-6">
                            <!-- Card 1 -->
                            <div class="glass glass-border rounded-3xl p-8 hover:bg-white/15 transition-all transform hover:-translate-y-1">
                                <div class="flex items-start gap-4">
                                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-zinc-600 to-zinc-700 flex items-center justify-center flex-shrink-0 shadow-lg">
                                        <svg class="w-6 h-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="text-xl font-semibold text-white mb-2">Multiple Strategies</h3>
                                        <p class="text-zinc-300 leading-relaxed">
                                            Choose from Snowball, Avalanche, and more to find the perfect payoff strategy for your situation.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Card 2 -->
                            <div class="glass glass-border rounded-3xl p-8 hover:bg-white/15 transition-all transform hover:-translate-y-1">
                                <div class="flex items-start gap-4">
                                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-zinc-600 to-zinc-700 flex items-center justify-center flex-shrink-0 shadow-lg">
                                        <svg class="w-6 h-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="text-xl font-semibold text-white mb-2">Payment Schedule</h3>
                                        <p class="text-zinc-300 leading-relaxed">
                                            See your complete amortization schedule and track your progress month by month.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Card 3 -->
                            <div class="glass glass-border rounded-3xl p-8 hover:bg-white/15 transition-all transform hover:-translate-y-1">
                                <div class="flex items-start gap-4">
                                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-zinc-600 to-zinc-700 flex items-center justify-center flex-shrink-0 shadow-lg">
                                        <svg class="w-6 h-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="text-xl font-semibold text-white mb-2">Visual Insights</h3>
                                        <p class="text-zinc-300 leading-relaxed">
                                            Beautiful charts and graphs help you understand your debt journey at a glance.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>


        </div>
    </body>
</html>
  
