<?php

use Livewire\Volt\Component;
use App\Models\Creditor;
use App\Models\Loan;

new class extends Component {
    public $creditors;
    public $totalDebt = 0;
    public $totalInterest = 0;
    public $loanCount = 0;
    public $monthlyPayment = 0;
    public $strategy = 'snowball';
    public $schedule = [];
    
    // Tab control
    public $activeTab = 'creditors';

    // Modal controls
    public $showAddLoanModal = false;
    public $showEditLoanModal = false;
    public $showDeleteConfirm = false;
    public $showEditCreditorModal = false;
    public $showDeleteCreditorConfirm = false;

    // Loan form fields
    public $editingLoanId = null;
    public $loanName;
    public $loanBalance;
    public $loanInterest;
    public $loanMinPayment;
    public $selectedCreditor;

    public $deleteLoanId = null;
    
    // Creditor form fields
    public $editingCreditorId = null;
    public $creditorName;
    public $creditorType;
    public $deleteCreditorId = null;

    public function mount()
    {
        $this->loadData();
        
        // Load saved preferences from user
        $user = auth()->user();
        $this->monthlyPayment = $user->monthly_payment ?? 0;
        $this->strategy = $user->debt_strategy ?? 'snowball';
        
        // Calculate with loaded values
        if ($this->monthlyPayment > 0) {
            $this->calculateStrategy();
        }
    }

    public function loadData()
    {
        $this->creditors = Creditor::with('loans')->where('user_id', auth()->id())->get();

        $this->totalDebt = $this->creditors->flatMap->loans->sum('balance');
        $this->totalInterest = $this->creditors->flatMap->loans->sum(fn($loan) => $loan->balance * ($loan->interest_rate / 100));
        $this->loanCount = $this->creditors->flatMap->loans->count();
    }

    public function updatedMonthlyPayment()
    {
        // Save to database
        auth()->user()->update(['monthly_payment' => $this->monthlyPayment]);
        
        $this->calculateStrategy();
    }

    public function updatedStrategy()
    {
        // Save to database
        auth()->user()->update(['debt_strategy' => $this->strategy]);
        
        $this->calculateStrategy();
    }

    public function calculateStrategy()
    {
        $loans = $this->creditors->flatMap->loans;

        if ($loans->isEmpty() || $this->monthlyPayment <= 0) {
            $this->schedule = [];
            return;
        }

        // Sort loans based on strategy
        $loans = match ($this->strategy) {
            'avalanche' => $loans->sortByDesc('interest_rate')->values(),
            'snowball' => $loans->sortBy('balance')->values(),
            'highest_balance' => $loans->sortByDesc('balance')->values(),
            'lowest_payment' => $loans->sortBy('minimum_payment')->values(),
            'highest_payment' => $loans->sortByDesc('minimum_payment')->values(),
            default => $loans->values(),
        };

        $schedule = [];
        $month = 1;

        // Create working array of loan balances
        $balances = $loans->map(fn($loan) => [
            'id' => $loan->id,
            'name' => $loan->name,
            'balance' => (float) $loan->balance,
            'rate' => (float) $loan->interest_rate,
            'min' => (float) $loan->minimum_payment,
        ])->toArray();

        // Check if monthly payment covers minimums
        $totalMinPayments = array_sum(array_column($balances, 'min'));
        if ($this->monthlyPayment < $totalMinPayments) {
            $this->schedule = [];
            return;
        }

        while (array_sum(array_column($balances, 'balance')) > 0.01 && $month <= 600) {
            $totalPaymentThisMonth = 0;
            $remainingBudget = $this->monthlyPayment;
            $loanBreakdown = [];

            // Step 1: Apply interest to all loans and track it
            foreach ($balances as $key => &$loan) {
                if ($loan['balance'] > 0) {
                    $monthlyInterest = ($loan['balance'] * ($loan['rate'] / 100)) / 12;
                    $loan['balance'] += $monthlyInterest;
                    
                    $loanBreakdown[$key] = [
                        'name' => $loan['name'],
                        'starting_balance' => $loan['balance'],
                        'interest_charged' => $monthlyInterest,
                        'payment' => 0,
                        'principal' => 0,
                        'interest' => 0,
                        'ending_balance' => 0,
                    ];
                }
            }
            unset($loan);

            // Step 2: Pay minimums on all loans
            foreach ($balances as $key => &$loan) {
                if ($loan['balance'] > 0) {
                    $payment = min($loan['min'], $loan['balance'], $remainingBudget);
                    $loan['balance'] -= $payment;
                    $totalPaymentThisMonth += $payment;
                    $remainingBudget -= $payment;
                    
                    // Calculate how much went to interest vs principal
                    $interestPortion = min($payment, $loanBreakdown[$key]['interest_charged']);
                    $principalPortion = $payment - $interestPortion;
                    
                    $loanBreakdown[$key]['payment'] += $payment;
                    $loanBreakdown[$key]['interest'] += $interestPortion;
                    $loanBreakdown[$key]['principal'] += $principalPortion;
                }
            }
            unset($loan);

            // Step 3: Apply extra payment to priority loan (first loan with balance)
            foreach ($balances as $key => &$loan) {
                if ($loan['balance'] > 0 && $remainingBudget > 0) {
                    $extraPayment = min($remainingBudget, $loan['balance']);
                    $loan['balance'] -= $extraPayment;
                    $totalPaymentThisMonth += $extraPayment;
                    $remainingBudget -= $extraPayment;
                    
                    // Extra payment goes entirely to principal
                    $loanBreakdown[$key]['payment'] += $extraPayment;
                    $loanBreakdown[$key]['principal'] += $extraPayment;
                    
                    break; // Only pay extra to the first priority loan
                }
            }
            unset($loan);

            // Update ending balances
            foreach ($loanBreakdown as $key => &$breakdown) {
                $breakdown['ending_balance'] = $balances[$key]['balance'];
            }
            unset($breakdown);

            // Record this month's data
            $schedule[] = [
                'month' => $month,
                'total_payment' => round($totalPaymentThisMonth, 2),
                'remaining_debt' => round(array_sum(array_column($balances, 'balance')), 2),
                'loans' => $loanBreakdown,
            ];

            $month++;

            // Stop if debt is paid off
            if (array_sum(array_column($balances, 'balance')) <= 0.01) {
                break;
            }
        }

        $this->schedule = $schedule;
    }

    public function openAddLoanModal($creditorId)
    {
        $this->resetLoanForm();
        $this->selectedCreditor = $creditorId;
        $this->showAddLoanModal = true;
    }

    public function openEditLoanModal($loanId)
    {
        $loan = Loan::findOrFail($loanId);
        $this->editingLoanId = $loan->id;
        $this->loanName = $loan->name;
        $this->loanBalance = $loan->balance;
        $this->loanInterest = $loan->interest_rate;
        $this->loanMinPayment = $loan->minimum_payment;
        $this->selectedCreditor = $loan->creditor_id;
        $this->showEditLoanModal = true;
    }

    public function saveLoan()
    {
        $this->validate([
            'loanName' => 'required|string|max:255',
            'loanBalance' => 'required|numeric|min:0',
            'loanInterest' => 'required|numeric|min:0',
            'loanMinPayment' => 'required|numeric|min:0',
        ]);

        if ($this->editingLoanId) {
            Loan::find($this->editingLoanId)->update([
                'name' => $this->loanName,
                'balance' => $this->loanBalance,
                'interest_rate' => $this->loanInterest,
                'minimum_payment' => $this->loanMinPayment,
            ]);
        } else {
            Loan::create([
                'creditor_id' => $this->selectedCreditor,
                'name' => $this->loanName,
                'balance' => $this->loanBalance,
                'interest_rate' => $this->loanInterest,
                'minimum_payment' => $this->loanMinPayment,
            ]);
        }

        $this->resetLoanForm();
        $this->showAddLoanModal = false;
        $this->showEditLoanModal = false;
        $this->loadData();
        $this->calculateStrategy();
    }

    public function confirmDeleteLoan($id)
    {
        $this->deleteLoanId = $id;
        $this->showDeleteConfirm = true;
    }

    public function deleteLoan()
    {
        Loan::findOrFail($this->deleteLoanId)->delete();
        $this->showDeleteConfirm = false;
        $this->loadData();
        $this->calculateStrategy();
    }

    public function resetLoanForm()
    {
        $this->editingLoanId = null;
        $this->loanName = '';
        $this->loanBalance = '';
        $this->loanInterest = '';
        $this->loanMinPayment = '';
        $this->selectedCreditor = null;
    }
    
    public function openEditCreditorModal($creditorId)
    {
        $creditor = Creditor::findOrFail($creditorId);
        $this->editingCreditorId = $creditor->id;
        $this->creditorName = $creditor->name;
        $this->creditorType = $creditor->type;
        $this->showEditCreditorModal = true;
    }
    
    public function saveCreditor()
    {
        $this->validate([
            'creditorName' => 'required|string|max:255',
            'creditorType' => 'required|string|max:255',
        ]);
        
        Creditor::find($this->editingCreditorId)->update([
            'name' => $this->creditorName,
            'type' => $this->creditorType,
        ]);
        
        $this->resetCreditorForm();
        $this->showEditCreditorModal = false;
        $this->loadData();
        $this->calculateStrategy();
    }
    
    public function confirmDeleteCreditor($id)
    {
        $this->deleteCreditorId = $id;
        $this->showDeleteCreditorConfirm = true;
    }
    
    public function deleteCreditor()
    {
        Creditor::findOrFail($this->deleteCreditorId)->delete();
        $this->showDeleteCreditorConfirm = false;
        $this->loadData();
        $this->calculateStrategy();
    }
    
    public function resetCreditorForm()
    {
        $this->editingCreditorId = null;
        $this->creditorName = '';
        $this->creditorType = '';
    }

};
?>

<div class="min-h-screen p-6">
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-bold text-zinc-900 dark:text-zinc-50">Debt Overview</h1>
        </div>

        <!-- Payment Strategy Section -->
        <div class="bg-white/40 dark:bg-zinc-800/40 backdrop-blur-2xl rounded-3xl p-6 border border-white/50 dark:border-zinc-700/50 shadow-xl">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-2">
                    <svg class="w-6 h-6 text-zinc-700 dark:text-zinc-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" />
                    </svg>
                    <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-50">Payment Strategy</h2>
                </div>
                <div wire:loading.delay wire:target="monthlyPayment,strategy" class="flex items-center gap-2 text-xs text-green-600 dark:text-green-400">
                    <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    Saving...
                </div>
                <div wire:loading.remove wire:target="monthlyPayment,strategy" class="flex items-center gap-2 text-xs text-green-600 dark:text-green-400">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    Auto-saved
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Monthly Payment (N$)</label>
                    <input 
                        type="number" 
                        wire:model.live="monthlyPayment" 
                        class="w-full px-4 py-3 bg-white/60 dark:bg-zinc-700/60 border border-zinc-200 dark:border-zinc-600 rounded-xl text-zinc-900 dark:text-zinc-50 placeholder-zinc-400 dark:placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-400 dark:focus:ring-zinc-500 transition-all backdrop-blur-xl" 
                        placeholder="Enter amount"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Strategy</label>
                    <select 
                        wire:model.live="strategy" 
                        class="w-full px-4 py-3 bg-white/60 dark:bg-zinc-700/60 border border-zinc-200 dark:border-zinc-600 rounded-xl text-zinc-900 dark:text-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-400 dark:focus:ring-zinc-500 transition-all backdrop-blur-xl"
                    >
                        <optgroup label="Popular Strategies">
                            <option value="snowball">💎 Snowball - Smallest balance first (Quick wins!)</option>
                            <option value="avalanche">🏔️ Avalanche - Highest interest first (Save money!)</option>
                        </optgroup>
                        <optgroup label="Alternative Strategies">
                            <option value="highest_balance">🎯 Highest Balance - Largest debt first</option>
                            <option value="lowest_payment">⚡ Lowest Payment - Smallest minimum payment first</option>
                            <option value="highest_payment">💪 Highest Payment - Largest minimum payment first</option>
                        </optgroup>
                    </select>
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                        @if($strategy === 'snowball')
                            Focus on quick wins by paying off smallest debts first - great for motivation!
                        @elseif($strategy === 'avalanche')
                            Mathematically optimal - save the most money by tackling high-interest debt first.
                        @elseif($strategy === 'highest_balance')
                            Eliminate your largest debts first to reduce total debt faster.
                        @elseif($strategy === 'lowest_payment')
                            Free up monthly cash flow by eliminating loans with smallest minimum payments.
                        @elseif($strategy === 'highest_payment')
                            Reduce monthly payment obligations by eliminating largest minimum payments.
                        @endif
                    </p>
                </div>
            </div>

            @php
                $totalMinPayments = $creditors->flatMap->loans->sum('minimum_payment');
                $snowballAmount = $monthlyPayment > 0 ? max(0, $monthlyPayment - $totalMinPayments) : 0;
                $hasSnowball = $snowballAmount > 0;
            @endphp

            @if ($monthlyPayment > 0)
                <div class="mt-6 p-4 rounded-2xl {{ $hasSnowball ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800' }}">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0">
                            @if ($hasSnowball)
                                <div class="w-12 h-12 rounded-xl bg-green-500 dark:bg-green-600 flex items-center justify-center">
                                    <svg class="w-6 h-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                                    </svg>
                                </div>
                            @else
                                <div class="w-12 h-12 rounded-xl bg-red-500 dark:bg-red-600 flex items-center justify-center">
                                    <svg class="w-6 h-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                    </svg>
                                </div>
                            @endif
                        </div>

                        <div class="flex-1">
                            @if ($hasSnowball)
                                <h3 class="text-lg font-semibold text-green-900 dark:text-green-100 mb-2">Snowball Active! 🎉</h3>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between items-center">
                                        <span class="text-green-700 dark:text-green-300">Monthly Payment:</span>
                                        <span class="font-bold text-green-900 dark:text-green-100">N${{ number_format($monthlyPayment, 2) }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-green-700 dark:text-green-300">Combined Minimums:</span>
                                        <span class="font-medium text-green-800 dark:text-green-200">N${{ number_format($totalMinPayments, 2) }}</span>
                                    </div>
                                    <div class="pt-2 border-t border-green-300 dark:border-green-700 flex justify-between items-center">
                                        <span class="text-green-800 dark:text-green-200 font-semibold">Extra Payment (Snowball):</span>
                                        <span class="text-xl font-bold text-green-600 dark:text-green-400">N${{ number_format($snowballAmount, 2) }}</span>
                                    </div>
                                </div>
                                <p class="text-xs text-green-700 dark:text-green-300 mt-3">
                                    This extra N${{ number_format($snowballAmount, 2) }} will accelerate your {{ $strategy }} payoff strategy!
                                </p>
                            @else
                                <h3 class="text-lg font-semibold text-red-900 dark:text-red-100 mb-2">Payment Too Low</h3>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between items-center">
                                        <span class="text-red-700 dark:text-red-300">Your Monthly Payment:</span>
                                        <span class="font-bold text-red-900 dark:text-red-100">N${{ number_format($monthlyPayment, 2) }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-red-700 dark:text-red-300">Required Minimums:</span>
                                        <span class="font-bold text-red-900 dark:text-red-100">N${{ number_format($totalMinPayments, 2) }}</span>
                                    </div>
                                    <div class="pt-2 border-t border-red-300 dark:border-red-700 flex justify-between items-center">
                                        <span class="text-red-800 dark:text-red-200 font-semibold">Shortfall:</span>
                                        <span class="text-xl font-bold text-red-600 dark:text-red-400">-N${{ number_format(abs($snowballAmount), 2) }}</span>
                                    </div>
                                </div>
                                <p class="text-xs text-red-700 dark:text-red-300 mt-3">
                                    Increase your payment by at least N${{ number_format($totalMinPayments - $monthlyPayment, 2) }} to cover minimum payments.
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Tabbed Section: Creditors & Loans / Payment Schedule -->
        <div class="bg-white/40 dark:bg-zinc-800/40 backdrop-blur-2xl rounded-3xl p-6 border border-white/50 dark:border-zinc-700/50 shadow-xl">
            <!-- Tabs Header -->
            <div class="flex items-center gap-2 mb-6 border-b border-zinc-200 dark:border-zinc-700">
                <button 
                    wire:click="$set('activeTab', 'creditors')"
                    class="flex items-center gap-2 px-4 py-3 border-b-2 transition-all {{ $activeTab === 'creditors' ? 'border-zinc-900 dark:border-zinc-50 text-zinc-900 dark:text-zinc-50 font-semibold' : 'border-transparent text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
                >
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                    </svg>
                    <span>Creditors & Loans</span>
                    @if ($loanCount > 0)
                        <span class="px-2 py-0.5 bg-zinc-900 dark:bg-zinc-50 text-zinc-50 dark:text-zinc-900 rounded-full text-xs font-bold">{{ $loanCount }}</span>
                    @endif
                </button>
                
                <button 
                    wire:click="$set('activeTab', 'chart')"
                    class="flex items-center gap-2 px-4 py-3 border-b-2 transition-all {{ $activeTab === 'chart' ? 'border-zinc-900 dark:border-zinc-50 text-zinc-900 dark:text-zinc-50 font-semibold' : 'border-transparent text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
                >
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                    </svg>
                    <span>Debt Chart</span>
                </button>
                
                <button 
                    wire:click="$set('activeTab', 'schedule')"
                    class="flex items-center gap-2 px-4 py-3 border-b-2 transition-all {{ $activeTab === 'schedule' ? 'border-zinc-900 dark:border-zinc-50 text-zinc-900 dark:text-zinc-50 font-semibold' : 'border-transparent text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
                >
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                    </svg>
                    <span>Payment Schedule</span>
                    @if (!empty($schedule))
                        <span class="px-2 py-0.5 bg-zinc-900 dark:bg-zinc-50 text-zinc-50 dark:text-zinc-900 rounded-full text-xs font-bold">{{ count($schedule) }} mo</span>
                    @endif
                </button>
            </div>

            <!-- Tab Content: Creditors & Loans -->
            @if ($activeTab === 'creditors')
                @if ($creditors->isEmpty())
                    <div class="text-center py-12">
                        <svg class="w-16 h-16 mx-auto text-zinc-300 dark:text-zinc-600 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                        </svg>
                        <p class="text-zinc-500 dark:text-zinc-400 text-lg">No creditors added yet</p>
                        <p class="text-zinc-400 dark:text-zinc-500 text-sm mt-1">Add a creditor from the dashboard to get started</p>
                    </div>
                @else
                    <div class="space-y-4">
                    @foreach ($creditors as $creditor)
                        <div class="bg-white/30 dark:bg-zinc-700/30 backdrop-blur-xl rounded-2xl p-5 border border-white/40 dark:border-zinc-600/40">
                            <div class="flex justify-between items-center mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-zinc-200 dark:bg-zinc-600 flex items-center justify-center">
                                        <svg class="w-6 h-6 text-zinc-600 dark:text-zinc-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-lg text-zinc-900 dark:text-zinc-50">{{ $creditor->name }}</h3>
                                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $creditor->type }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button 
                                        wire:click="openEditCreditorModal({{ $creditor->id }})" 
                                        class="inline-flex items-center gap-1 px-3 py-1.5 bg-white/40 dark:bg-zinc-600/40 text-zinc-700 dark:text-zinc-300 rounded-lg hover:bg-white/60 dark:hover:bg-zinc-600/60 transition-all text-xs font-medium border border-white/50 dark:border-zinc-500/50"
                                        title="Edit Creditor"
                                    >
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                        </svg>
                                    </button>
                                    <button 
                                        wire:click="confirmDeleteCreditor({{ $creditor->id }})" 
                                        class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-500/10 dark:bg-red-500/20 text-red-600 dark:text-red-400 rounded-lg hover:bg-red-500/20 dark:hover:bg-red-500/30 transition-all text-xs font-medium border border-red-500/30 dark:border-red-500/40"
                                        title="Delete Creditor"
                                    >
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                    <button 
                                        wire:click="openAddLoanModal({{ $creditor->id }})" 
                                        class="flex items-center gap-2 px-4 py-2 bg-zinc-900 dark:bg-zinc-50 text-zinc-50 dark:text-zinc-900 rounded-xl hover:bg-zinc-800 dark:hover:bg-zinc-200 transition-colors text-sm font-medium shadow-md"
                                    >
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                        </svg>
                                        Add Loan
                                    </button>
                                </div>
                            </div>

                            @if ($creditor->loans->count())
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="text-left text-zinc-600 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-600">
                                                <th class="pb-3 font-medium">Loan</th>
                                                <th class="pb-3 font-medium">Balance</th>
                                                <th class="pb-3 font-medium">Interest</th>
                                                <th class="pb-3 font-medium">Min Payment</th>
                                                <th class="pb-3 font-medium text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($creditor->loans as $loan)
                                                <tr class="border-b border-zinc-100 dark:border-zinc-700 last:border-0">
                                                    <td class="py-3 font-medium text-zinc-900 dark:text-zinc-50">{{ $loan->name }}</td>
                                                    <td class="py-3 text-zinc-700 dark:text-zinc-300">N${{ number_format($loan->balance, 2) }}</td>
                                                    <td class="py-3 text-zinc-700 dark:text-zinc-300">{{ $loan->interest_rate }}%</td>
                                                    <td class="py-3 text-zinc-700 dark:text-zinc-300">N${{ number_format($loan->minimum_payment, 2) }}</td>
                                                    <td class="py-3 text-right space-x-2">
                                                        <button 
                                                            wire:click="openEditLoanModal({{ $loan->id }})" 
                                                            class="inline-flex items-center gap-1 px-3 py-1 bg-white/40 dark:bg-zinc-600/40 text-zinc-700 dark:text-zinc-300 rounded-lg hover:bg-white/60 dark:hover:bg-zinc-600/60 transition-all text-xs font-medium border border-white/50 dark:border-zinc-500/50"
                                                        >
                                                            <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                            </svg>
                                                            Edit
                                                        </button>
                                                        <button 
                                                            wire:click="confirmDeleteLoan({{ $loan->id }})" 
                                                            class="inline-flex items-center gap-1 px-3 py-1 bg-red-500/10 dark:bg-red-500/20 text-red-600 dark:text-red-400 rounded-lg hover:bg-red-500/20 dark:hover:bg-red-500/30 transition-all text-xs font-medium border border-red-500/30 dark:border-red-500/40"
                                                        >
                                                            <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                            </svg>
                                                            Delete
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-center py-8">
                                    <svg class="w-12 h-12 mx-auto text-zinc-300 dark:text-zinc-600 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                    </svg>
                                    <p class="text-zinc-500 dark:text-zinc-400 text-sm">No loans for this creditor</p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                    </div>
                @endif
            
            <!-- Tab Content: Debt Chart -->
            @elseif ($activeTab === 'chart')
                @if (!empty($schedule))
                    <div class="space-y-6">
                        <!-- Summary Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-white/30 dark:bg-zinc-700/30 backdrop-blur-xl rounded-2xl p-4 border border-white/40 dark:border-zinc-600/40">
                                <div class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400 text-sm mb-2">
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <span class="font-medium">Starting Debt</span>
                                </div>
                                <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-50">
                                    N${{ number_format($totalDebt, 2) }}
                                </div>
                            </div>

                            <div class="bg-white/30 dark:bg-zinc-700/30 backdrop-blur-xl rounded-2xl p-4 border border-white/40 dark:border-zinc-600/40">
                                <div class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400 text-sm mb-2">
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                    </svg>
                                    <span class="font-medium">Months to Freedom</span>
                                </div>
                                <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-50">
                                    {{ count($schedule) }} months
                                </div>
                            </div>

                            <div class="bg-white/30 dark:bg-zinc-700/30 backdrop-blur-xl rounded-2xl p-4 border border-white/40 dark:border-zinc-600/40">
                                <div class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400 text-sm mb-2">
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                                    </svg>
                                    <span class="font-medium">Total Paid</span>
                                </div>
                                <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-50">
                                    N${{ number_format(collect($schedule)->sum('total_payment'), 2) }}
                                </div>
                            </div>
                        </div>

                        <!-- Chart Container -->
                        <div class="bg-white/30 dark:bg-zinc-700/30 backdrop-blur-xl rounded-2xl p-6 border border-white/40 dark:border-zinc-600/40">
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50 mb-4">Debt Reduction Over Time</h3>
                            
                            <!-- Simple CSS-based Chart -->
                            <div class="space-y-2">
                                @php
                                    $maxDebt = $totalDebt;
                                    $chartData = collect($schedule)->filter(function($month, $index) {
                                        return $index % max(1, floor(count($this->schedule) / 20)) == 0; // Show max 20 data points
                                    });
                                @endphp
                                
                                @foreach ($chartData as $month)
                                    @php
                                        $percentage = ($month['remaining_debt'] / $maxDebt) * 100;
                                        $paidPercentage = 100 - $percentage;
                                    @endphp
                                    <div class="flex items-center gap-3">
                                        <div class="w-16 text-xs text-zinc-600 dark:text-zinc-400 font-medium">
                                            Mo {{ $month['month'] }}
                                        </div>
                                        <div class="flex-1 h-8 bg-zinc-200 dark:bg-zinc-700 rounded-lg overflow-hidden relative">
                                            <div class="h-full bg-gradient-to-r from-green-500 to-green-400 dark:from-green-600 dark:to-green-500 transition-all duration-300" style="width: {{ $paidPercentage }}%"></div>
                                            <div class="absolute inset-0 flex items-center justify-center text-xs font-semibold text-zinc-900 dark:text-zinc-50">
                                                N${{ number_format($month['remaining_debt'], 0) }}
                                            </div>
                                        </div>
                                        <div class="w-20 text-xs text-zinc-600 dark:text-zinc-400 text-right">
                                            {{ number_format($paidPercentage, 1) }}% paid
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @else
                    <div class="text-center py-12">
                        <svg class="w-16 h-16 mx-auto text-zinc-300 dark:text-zinc-600 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                        </svg>
                        <p class="text-zinc-500 dark:text-zinc-400 text-lg mb-2">No chart data yet</p>
                        <p class="text-zinc-400 dark:text-zinc-500 text-sm">Enter a monthly payment amount to generate the debt reduction chart</p>
                    </div>
                @endif
            
            <!-- Tab Content: Payment Schedule -->
            @elseif ($activeTab === 'schedule')
                @if (!empty($schedule))
                    <div class="mb-4 flex items-center justify-between">
                        <div class="flex items-center gap-2 text-zinc-600 dark:text-zinc-400 text-sm">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <span>Using <span class="font-semibold text-zinc-900 dark:text-zinc-50">{{ ucfirst($strategy) }}</span> method</span>
                        </div>
                        <div class="text-sm text-zinc-600 dark:text-zinc-400">
                            Payoff in <span class="font-bold text-zinc-900 dark:text-zinc-50">{{ count($schedule) }}</span> months
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                        <thead>
                            <tr class="text-left bg-gradient-to-r from-zinc-50 to-zinc-100 dark:from-zinc-800 dark:to-zinc-700 border-b-2 border-zinc-300 dark:border-zinc-600">
                                <th class="py-4 px-4 font-bold text-zinc-900 dark:text-zinc-50 text-sm">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-zinc-500 dark:text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                        </svg>
                                        Month
                                    </div>
                                </th>
                                <th class="py-4 px-4 font-bold text-zinc-900 dark:text-zinc-50 text-sm">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-zinc-500 dark:text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                                        </svg>
                                        Loan
                                    </div>
                                </th>
                                <th class="py-4 px-4 font-bold text-zinc-900 dark:text-zinc-50 text-sm text-right">Starting Balance</th>
                                <th class="py-4 px-4 font-bold text-zinc-900 dark:text-zinc-50 text-sm text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <svg class="w-4 h-4 text-red-500 dark:text-red-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                        Interest
                                    </div>
                                </th>
                                <th class="py-4 px-4 font-bold text-zinc-900 dark:text-zinc-50 text-sm text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <svg class="w-4 h-4 text-blue-500 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                                        </svg>
                                        Payment
                                    </div>
                                </th>
                                <th class="py-4 px-4 font-bold text-zinc-900 dark:text-zinc-50 text-sm text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <svg class="w-4 h-4 text-green-500 dark:text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                                        </svg>
                                        Principal
                                    </div>
                                </th>
                                <th class="py-4 px-4 font-bold text-zinc-900 dark:text-zinc-50 text-sm text-right">Ending Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                            @foreach ($schedule as $row)
                                @php
                                    $loanCount = count($row['loans']);
                                    $isFirstLoan = true;
                                @endphp
                                
                                @foreach ($row['loans'] as $loanDetail)
                                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors {{ $loanDetail['payment'] > $loanDetail['interest'] + 0.01 ? 'bg-green-50/30 dark:bg-green-900/10' : '' }}">
                                        @if ($isFirstLoan)
                                            <td class="py-4 px-4 align-top" rowspan="{{ $loanCount }}">
                                                <div class="flex flex-col gap-2">
                                                    <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-br from-blue-100 to-indigo-100 dark:from-blue-900/40 dark:to-indigo-900/40 text-blue-700 dark:text-blue-300 font-bold text-lg shadow-sm">
                                                        {{ $row['month'] }}
                                                    </span>
                                                    <div class="mt-2 space-y-1">
                                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                            <span class="font-medium">Total:</span>
                                                            <span class="font-semibold text-zinc-700 dark:text-zinc-300">N${{ number_format($row['total_payment'], 2) }}</span>
                                                        </div>
                                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                            <span class="font-medium">Debt:</span>
                                                            <span class="font-semibold text-zinc-700 dark:text-zinc-300">N${{ number_format($row['remaining_debt'], 2) }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            @php $isFirstLoan = false; @endphp
                                        @endif
                                        
                                        <td class="py-4 px-4">
                                            <div class="flex items-center gap-2">
                                                <div class="w-8 h-8 rounded-lg bg-zinc-100 dark:bg-zinc-700 flex items-center justify-center flex-shrink-0">
                                                    <svg class="w-4 h-4 text-zinc-600 dark:text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                    </svg>
                                                </div>
                                                <span class="font-medium text-zinc-900 dark:text-zinc-50 text-sm">{{ $loanDetail['name'] }}</span>
                                            </div>
                                        </td>
                                        
                                        <td class="py-4 px-4 text-right">
                                            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                                N${{ number_format($loanDetail['starting_balance'], 2) }}
                                            </span>
                                        </td>
                                        
                                        <td class="py-4 px-4 text-right">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-lg text-sm font-semibold">
                                                <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                                </svg>
                                                N${{ number_format($loanDetail['interest_charged'], 2) }}
                                            </span>
                                        </td>
                                        
                                        <td class="py-4 px-4 text-right">
                                            @if ($loanDetail['payment'] > 0)
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 rounded-lg text-sm font-bold">
                                                    N${{ number_format($loanDetail['payment'], 2) }}
                                                </span>
                                            @else
                                                <span class="text-zinc-400 dark:text-zinc-600 text-sm">-</span>
                                            @endif
                                        </td>
                                        
                                        <td class="py-4 px-4 text-right">
                                            @if ($loanDetail['principal'] > 0)
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 rounded-lg text-sm font-semibold">
                                                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" />
                                                    </svg>
                                                    N${{ number_format($loanDetail['principal'], 2) }}
                                                </span>
                                            @else
                                                <span class="text-zinc-400 dark:text-zinc-600 text-sm">-</span>
                                            @endif
                                        </td>
                                        
                                        <td class="py-4 px-4 text-right">
                                            @if ($loanDetail['ending_balance'] <= 0.01)
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r from-green-100 to-emerald-100 dark:from-green-900/30 dark:to-emerald-900/30 text-green-700 dark:text-green-400 rounded-lg text-xs font-bold border border-green-200 dark:border-green-800">
                                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                    </svg>
                                                    PAID OFF
                                                </span>
                                            @else
                                                <span class="text-sm font-bold text-zinc-900 dark:text-zinc-50">
                                                    N${{ number_format($loanDetail['ending_balance'], 2) }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                
                                <!-- Subtotal Row -->
                                <tr class="bg-gradient-to-r from-zinc-100 to-zinc-50 dark:from-zinc-800 dark:to-zinc-700 border-t-2 border-b-2 border-zinc-300 dark:border-zinc-600">
                                    <td colspan="4" class="py-3 px-4 text-right font-bold text-zinc-700 dark:text-zinc-300 text-sm">
                                        <span class="inline-flex items-center gap-2">
                                            <svg class="w-4 h-4 text-zinc-500 dark:text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0 1 16.5 7.605" />
                                            </svg>
                                            Month {{ $row['month'] }} Total:
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <span class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 rounded-lg text-sm font-bold">
                                            N${{ number_format($row['total_payment'], 2) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <span class="inline-flex items-center gap-1 px-3 py-1.5 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 rounded-lg text-sm font-bold">
                                            N${{ number_format(collect($row['loans'])->sum('principal'), 2) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <span class="text-sm font-bold text-zinc-900 dark:text-zinc-50">
                                            N${{ number_format($row['remaining_debt'], 2) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-gradient-to-r from-zinc-200 to-zinc-100 dark:from-zinc-700 dark:to-zinc-600 border-t-4 border-zinc-400 dark:border-zinc-500">
                                <td colspan="4" class="py-5 px-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <svg class="w-5 h-5 text-zinc-600 dark:text-zinc-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
                                        </svg>
                                        <span class="font-bold text-zinc-900 dark:text-zinc-50 text-base">Grand Total:</span>
                                    </div>
                                </td>
                                <td class="py-5 px-4 text-right">
                                    <span class="inline-flex items-center gap-1.5 px-4 py-2 bg-gradient-to-r from-blue-100 to-indigo-100 dark:from-blue-900/40 dark:to-indigo-900/40 text-blue-800 dark:text-blue-300 rounded-lg text-base font-bold border border-blue-200 dark:border-blue-800">
                                        N${{ number_format(collect($schedule)->sum('total_payment'), 2) }}
                                    </span>
                                </td>
                                <td class="py-5 px-4 text-right">
                                    <span class="inline-flex items-center gap-1.5 px-4 py-2 bg-gradient-to-r from-green-100 to-emerald-100 dark:from-green-900/40 dark:to-emerald-900/40 text-green-800 dark:text-green-300 rounded-lg text-base font-bold border border-green-200 dark:border-green-800">
                                    N${{ number_format(collect($schedule)->sum(fn($m) => collect($m['loans'])->sum('principal')), 2) }}
                                </td>
                                <td class="py-4 text-right font-bold text-zinc-900 dark:text-zinc-50">N$0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                    </div>
                    
                    <!-- Legend -->
                    <div class="mt-4 flex items-center gap-6 text-xs text-zinc-600 dark:text-zinc-400 flex-wrap">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded bg-green-50 dark:bg-green-900/10 border border-green-200 dark:border-green-800"></div>
                            <span>= Extra payment applied (priority loan)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-red-600 dark:text-red-400 font-medium">Red</span>
                            <span>= Interest charged</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-green-600 dark:text-green-400 font-medium">Green</span>
                            <span>= Principal reduction</span>
                        </div>
                    </div>
                @else
                    <div class="text-center py-12">
                        <svg class="w-16 h-16 mx-auto text-zinc-300 dark:text-zinc-600 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg>
                        <p class="text-zinc-500 dark:text-zinc-400 text-lg mb-2">No payment schedule yet</p>
                        <p class="text-zinc-400 dark:text-zinc-500 text-sm">Enter a monthly payment amount above to see your payment schedule</p>
                    </div>
                @endif
            @endif
        </div>

    <!-- Add/Edit Loan Modal -->
    <x-modal wire:model="showAddLoanModal" wire:model.live="showEditLoanModal">
        <div class="p-6 space-y-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-zinc-100 dark:bg-zinc-700 flex items-center justify-center">
                    <svg class="w-6 h-6 text-zinc-600 dark:text-zinc-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ $editingLoanId ? 'Edit Loan' : 'Add Loan' }}</h2>
            </div>

            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Loan Name</label>
                <input 
                    type="text" 
                    wire:model="loanName" 
                    class="w-full px-4 py-3 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-600 rounded-xl text-zinc-900 dark:text-zinc-50 placeholder-zinc-400 dark:placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-400 dark:focus:ring-zinc-500 transition-all"
                    placeholder="Enter loan name"
                >
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Balance (N$)</label>
                    <input 
                        type="number" 
                        wire:model="loanBalance" 
                        class="w-full px-4 py-3 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-600 rounded-xl text-zinc-900 dark:text-zinc-50 placeholder-zinc-400 dark:placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-400 dark:focus:ring-zinc-500 transition-all"
                        placeholder="0.00"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Interest Rate (%)</label>
                    <input 
                        type="number" 
                        wire:model="loanInterest" 
                        class="w-full px-4 py-3 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-600 rounded-xl text-zinc-900 dark:text-zinc-50 placeholder-zinc-400 dark:placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-400 dark:focus:ring-zinc-500 transition-all"
                        placeholder="0.00"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Min Payment (N$)</label>
                    <input 
                        type="number" 
                        wire:model="loanMinPayment" 
                        class="w-full px-4 py-3 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-600 rounded-xl text-zinc-900 dark:text-zinc-50 placeholder-zinc-400 dark:placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-400 dark:focus:ring-zinc-500 transition-all"
                        placeholder="0.00"
                    >
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <button 
                    wire:click="$set('showAddLoanModal', false); $set('showEditLoanModal', false)" 
                    class="px-6 py-2.5 bg-zinc-100 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300 rounded-xl hover:bg-zinc-200 dark:hover:bg-zinc-600 transition-all font-medium"
                >
                    Cancel
                </button>
                <button 
                    wire:click="saveLoan" 
                    class="px-6 py-2.5 bg-zinc-900 dark:bg-zinc-50 text-zinc-50 dark:text-zinc-900 rounded-xl hover:bg-zinc-800 dark:hover:bg-zinc-200 transition-all font-medium shadow-lg"
                >
                    Save Loan
                </button>
            </div>
        </div>
    </x-modal>

    <!-- Delete Confirmation Modal -->
    <x-modal wire:model="showDeleteConfirm">
        <div class="p-6 text-center space-y-5">
            <div class="w-16 h-16 rounded-2xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto">
                <svg class="w-8 h-8 text-red-600 dark:text-red-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            
            <div>
                <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-50 mb-2">Delete Loan</h2>
                <p class="text-zinc-600 dark:text-zinc-400">Are you sure you want to delete this loan? This action cannot be undone.</p>
            </div>

            <div class="flex justify-center gap-3 pt-2">
                <button 
                    wire:click="$set('showDeleteConfirm', false)" 
                    class="px-6 py-2.5 bg-zinc-100 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300 rounded-xl hover:bg-zinc-200 dark:hover:bg-zinc-600 transition-all font-medium"
                >
                    Cancel
                </button>
                <button 
                    wire:click="deleteLoan" 
                    class="px-6 py-2.5 bg-red-600 dark:bg-red-500 text-white rounded-xl hover:bg-red-700 dark:hover:bg-red-600 transition-all font-medium shadow-lg"
                >
                    Delete Loan
                </button>
            </div>
        </div>
    </x-modal>
    
    <!-- Edit Creditor Modal -->
    <x-modal wire:model="showEditCreditorModal">
        <div class="p-6 space-y-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-zinc-100 dark:bg-zinc-700 flex items-center justify-center">
                    <svg class="w-6 h-6 text-zinc-600 dark:text-zinc-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-50">Edit Creditor</h2>
            </div>

            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Creditor Name</label>
                <input 
                    type="text" 
                    wire:model="creditorName" 
                    class="w-full px-4 py-3 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-600 rounded-xl text-zinc-900 dark:text-zinc-50 placeholder-zinc-400 dark:placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-400 dark:focus:ring-zinc-500 transition-all"
                    placeholder="Enter creditor name"
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Type</label>
                <input 
                    type="text" 
                    wire:model="creditorType" 
                    class="w-full px-4 py-3 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-600 rounded-xl text-zinc-900 dark:text-zinc-50 placeholder-zinc-400 dark:placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-400 dark:focus:ring-zinc-500 transition-all"
                    placeholder="e.g., Bank, Credit Card, Personal"
                >
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <button 
                    wire:click="$set('showEditCreditorModal', false)" 
                    class="px-6 py-2.5 bg-zinc-100 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300 rounded-xl hover:bg-zinc-200 dark:hover:bg-zinc-600 transition-all font-medium"
                >
                    Cancel
                </button>
                <button 
                    wire:click="saveCreditor" 
                    class="px-6 py-2.5 bg-zinc-900 dark:bg-zinc-50 text-zinc-50 dark:text-zinc-900 rounded-xl hover:bg-zinc-800 dark:hover:bg-zinc-200 transition-all font-medium shadow-lg"
                >
                    Save Changes
                </button>
            </div>
        </div>
    </x-modal>
    
    <!-- Delete Creditor Confirmation Modal -->
    <x-modal wire:model="showDeleteCreditorConfirm">
        <div class="p-6 text-center space-y-5">
            <div class="w-16 h-16 rounded-2xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto">
                <svg class="w-8 h-8 text-red-600 dark:text-red-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            
            <div>
                <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-50 mb-2">Delete Creditor</h2>
                <p class="text-zinc-600 dark:text-zinc-400">Are you sure you want to delete this creditor? This will also delete all associated loans. This action cannot be undone.</p>
            </div>

            <div class="flex justify-center gap-3 pt-2">
                <button 
                    wire:click="$set('showDeleteCreditorConfirm', false)" 
                    class="px-6 py-2.5 bg-zinc-100 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300 rounded-xl hover:bg-zinc-200 dark:hover:bg-zinc-600 transition-all font-medium"
                >
                    Cancel
                </button>
                <button 
                    wire:click="deleteCreditor" 
                    class="px-6 py-2.5 bg-red-600 dark:bg-red-500 text-white rounded-xl hover:bg-red-700 dark:hover:bg-red-600 transition-all font-medium shadow-lg"
                >
                    Delete Creditor
                </button>
            </div>
        </div>
    </x-modal>
</div>
