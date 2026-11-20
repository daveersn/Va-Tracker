<x-filament-panels::page>
    @php
        $maxChartValue = max(1, (int) collect($points)->flatMap(fn ($point) => array_filter([$point['target'], $point['actual']]))->max());
        $targetPath = collect($points)->values()->map(function ($point, $index) use ($points, $maxChartValue) {
            $x = count($points) < 2 ? 450 : 56 + ($index / (count($points) - 1) * 790);
            $y = 202 - ($point['target'] / $maxChartValue * 170);
            return ($index === 0 ? 'M' : 'L').number_format($x, 1, '.', '').' '.number_format($y, 1, '.', '');
        })->implode(' ');
    @endphp

    <div class="space-y-6">
        @php
            $isActive = $plan->status === 'active';
            $currentMonth = now()->startOfMonth();
            $lastRecorded = $latest?->period;
            $nextPeriod = \Illuminate\Support\Carbon::parse($plan->firstRecordablePeriod());
            $monthStatus = match (true) {
                ! $isActive => 'Concluso a '.$plan->closed_period->locale('it')->translatedFormat('F Y'),
                $lastRecorded?->isSameMonth($currentMonth) => 'Rilevazione salvata',
                $nextPeriod->lte($currentMonth) => 'Da rilevare',
                $plan->start_period->gt($currentMonth) => 'Il piano non è ancora iniziato',
                default => 'Periodo già coperto',
            };
        @endphp

        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <x-filament::link :href="\App\Filament\Pages\Plans::getUrl()">Tutti i PAC</x-filament::link>
            <div class="flex flex-wrap gap-x-4 gap-y-2">
                @if ($plan->previousPlan)
                    <x-filament::link :href="\App\Filament\Pages\PlanDetail::getUrl(['plan' => $plan->previousPlan->id])">PAC precedente</x-filament::link>
                @endif
                @if ($plan->successor)
                    <x-filament::link :href="\App\Filament\Pages\PlanDetail::getUrl(['plan' => $plan->successor->id])">PAC che lo continua</x-filament::link>
                @endif
            </div>
        </div>

        @if ($this->investmentSnapshot === [] && $this->successorPreview === [])
            <section aria-labelledby="pac-overview" class="rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 sm:px-6">
                    <div>
                        <h2 id="pac-overview" class="text-xl font-semibold tracking-tight">
                            {{ $isActive ? 'Rilevazione di '.$currentMonth->locale('it')->translatedFormat('F Y') : 'Saldo alla chiusura' }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            Obiettivo +{{ \App\Support\Decimal::formatEuro((int) $plan->getRawOriginal('monthly_increment')) }} al mese
                            · Avvio {{ $plan->start_period->format('m/Y') }}
                        </p>
                    </div>
                    <span @class([
                        'rounded-full px-2.5 py-1 text-sm font-medium',
                        'bg-primary-50 text-primary-800 dark:bg-primary-950 dark:text-primary-200' => $monthStatus === 'Da rilevare',
                        'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200' => $monthStatus !== 'Da rilevare',
                    ])>{{ $monthStatus }}</span>
                </div>

                <div class="grid gap-5 border-t border-gray-200 p-5 sm:px-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.7fr)] lg:gap-8 dark:border-white/10">
                    <div>
                        <h3 class="text-sm text-gray-600 dark:text-gray-300">{{ $isActive ? 'Valore delle quote' : 'Valore all’ultima rilevazione' }}</h3>
                        <p class="mt-1 text-3xl font-semibold tracking-tight tabular-nums">
                            {{ $portfolioValue === null ? 'Non disponibile' : \App\Support\Decimal::formatEuro($portfolioValue) }}
                        </p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            {{ number_format($plan->quantity(), 0, ',', '.') }} {{ $plan->quantity() === 1 ? 'quota' : 'quote' }}
                            @if ($displayPrice !== null)
                                · {{ str_replace('.', ',', $displayPrice) }} € per quota
                                @if ($displayPriceDate)
                                    al {{ $displayPriceDate->format('d/m/Y') }}
                                @endif
                            @endif
                        </p>
                    </div>
                    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        <div>
                            <dt class="text-sm text-gray-600 dark:text-gray-300">Capitale investito</dt>
                            <dd class="mt-1 text-lg font-semibold tabular-nums">{{ \App\Support\Decimal::formatEuro($plan->investedAmount()) }}</dd>
                        </div>
                        @if ($calendarTarget !== null)
                            <div>
                                <dt class="text-sm text-gray-600 dark:text-gray-300">{{ $isActive ? 'Obiettivo del mese' : 'Obiettivo alla chiusura' }}</dt>
                                <dd class="mt-1 text-lg font-semibold tabular-nums">{{ \App\Support\Decimal::formatEuro($calendarTarget) }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-sm text-gray-600 dark:text-gray-300">Liquidità virtuale</dt>
                            <dd class="mt-1 text-lg font-semibold tabular-nums">{{ \App\Support\Decimal::formatEuro($plan->virtualLiquidity()) }}</dd>
                            <dd class="mt-1 text-sm text-gray-600 dark:text-gray-300">Saldo simulato, non spendibile</dd>
                        </div>
                    </dl>
                </div>
            </section>
        @endif

        @if ($this->investmentSnapshot !== [])
            <section class="rounded-xl border border-gray-200 bg-white p-5 sm:p-6 dark:border-white/10 dark:bg-gray-900">
                <h2 class="text-xl font-semibold">Anteprima {{ $this->editingInvestmentId ? 'della correzione' : 'della rilevazione' }} · {{ \Illuminate\Support\Carbon::parse($this->investmentInput['period'])->locale('it')->translatedFormat('F Y') }}</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Prezzo di {{ str_replace('.', ',', $this->investmentInput['unit_price']) }} € del {{ \Illuminate\Support\Carbon::parse($this->investmentInput['price_date'])->format('d/m/Y') }}</p>

                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    @if ($this->investmentSnapshot['proposed_quantity'] !== $this->investmentSnapshot['purchased_quantity'])
                        <div>
                            <dt class="text-sm text-gray-600 dark:text-gray-300">Acquisto proposto</dt>
                            <dd class="mt-1 text-lg font-semibold">{{ $this->investmentSnapshot['proposed_quantity'] }} quote · {{ \App\Support\Decimal::formatEuro($this->investmentSnapshot['proposed_amount']) }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-sm text-gray-600 dark:text-gray-300">Acquisto da registrare</dt>
                        <dd class="mt-1 text-lg font-semibold">{{ $this->investmentSnapshot['purchased_quantity'] }} quote · {{ \App\Support\Decimal::formatEuro($this->investmentSnapshot['purchased_amount']) }}</dd>
                    </div>
                </dl>
                <div class="mt-4 text-sm text-gray-700 dark:text-gray-300">
                    @if (($this->investmentSnapshot['range_status'] ?? '') === 'virtual_liquidity')
                        L’importo teorico è negativo: non vengono proposte vendite e la differenza incrementa la liquidità virtuale.
                    @elseif (($this->investmentSnapshot['range_status'] ?? '') === 'above_increment')
                        L’importo teorico supera l’incremento mensile. L’acquisto resta consentito.
                    @else
                        Importo nel range dell’incremento mensile.
                    @endif
                    @if ($this->investmentSnapshot['rounding_exceeds_increment'] ?? false)
                        <span class="font-medium">Il controvalore proposto supera l’incremento per effetto dell’arrotondamento.</span>
                    @endif
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    {{ $this->saveInvestmentAction }}
                    {{ $this->adjustInvestmentAction }}
                    <x-filament::link tag="button" color="gray" wire:click="clearInvestmentPreview">Annulla anteprima</x-filament::link>
                </div>
                <details class="mt-5 border-t border-gray-200 pt-4 dark:border-white/10">
                    <summary class="cursor-pointer text-sm font-medium">Vedi calcolo e saldi dopo l’acquisto</summary>
                    <dl class="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                        @foreach ([
                            'portfolio_value_before' => 'Valore prima dell’acquisto',
                            'target_amount' => 'Obiettivo del mese',
                            'theoretical_investment' => 'Importo teorico',
                            'invested_amount_after' => 'Capitale investito dopo',
                            'virtual_liquidity_increase' => 'Liquidità virtuale del mese',
                            'virtual_liquidity_after' => 'Liquidità virtuale totale',
                        ] as $field => $label)
                            <div>
                                <dt class="text-gray-600 dark:text-gray-300">{{ $label }}</dt>
                                <dd class="font-medium">{{ \App\Support\Decimal::formatEuro($this->investmentSnapshot[$field]) }}</dd>
                            </div>
                        @endforeach
                        <div><dt class="text-gray-600 dark:text-gray-300">Quote prima</dt><dd>{{ $this->investmentSnapshot['quantity_before'] }}</dd></div>
                        <div><dt class="text-gray-600 dark:text-gray-300">Quote dopo</dt><dd>{{ $this->investmentSnapshot['quantity_after'] }}</dd></div>
                    </dl>
                </details>
            </section>
        @endif

        @if ($this->successorPreview !== [])
            <section class="rounded-2xl border border-violet-200 bg-violet-50/70 p-5 dark:border-violet-900 dark:bg-violet-950/30 sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-950 dark:text-white">Il nuovo PAC partirà da {{ \Illuminate\Support\Carbon::parse($this->successorPreview['start_period'])->format('m/Y') }}</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Il PAC attuale si chiuderà al {{ \Illuminate\Support\Carbon::parse($this->successorPreview['closed_period'])->format('m/Y') }}. La conferma renderà non modificabile anche il suo storico.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        {{ ($this->adjustSuccessorPreviewAction) }}
                        {{ ($this->createSuccessorAction) }}
                    </div>
                </div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="rounded-xl bg-white p-4 dark:bg-gray-900"><p class="text-xs text-gray-500 dark:text-gray-400">Nuovo incremento</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney((int) $this->successorPreview['monthly_increment']) }}</p></div>
                    <div class="rounded-xl bg-white p-4 dark:bg-gray-900"><p class="text-xs text-gray-500 dark:text-gray-400">Quote trasferite</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">{{ number_format((int) $this->successorPreview['opening_quantity'], 0, ',', '.') }}</p></div>
                    <div class="rounded-xl bg-white p-4 dark:bg-gray-900"><p class="text-xs text-gray-500 dark:text-gray-400">Capitale investito</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney((int) $this->successorPreview['opening_invested_amount']) }}</p></div>
                    <div class="rounded-xl bg-white p-4 dark:bg-gray-900"><p class="text-xs text-gray-500 dark:text-gray-400">Obiettivo ereditato</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney((int) $this->successorPreview['opening_target_amount']) }}</p></div>
                    <div class="rounded-xl bg-white p-4 dark:bg-gray-900"><p class="text-xs text-gray-500 dark:text-gray-400">Liquidità virtuale</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney((int) $this->successorPreview['opening_virtual_liquidity']) }}</p></div>
                </div>
                <p class="mt-4 rounded-xl bg-white px-4 py-3 text-sm text-gray-700 dark:bg-gray-900 dark:text-gray-300">Primo obiettivo del successore: <strong>€ {{ \App\Support\Decimal::formatMoney((int) $this->successorPreview['first_target_amount']) }}</strong>. I mesi senza rilevazione fanno avanzare l’obiettivo per calendario, senza acquisti o liquidità virtuale fittizi.</p>
            </section>
        @endif

        <section aria-labelledby="pac-history" class="rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-wrap items-baseline justify-between gap-2 px-5 py-4 sm:px-6">
                <h2 id="pac-history" class="text-xl font-semibold tracking-tight">Storico delle rilevazioni</h2>
                @if ($investments->isNotEmpty())
                    <span class="text-sm text-gray-600 dark:text-gray-300">{{ $investments->count() }} {{ $investments->count() === 1 ? 'rilevazione' : 'rilevazioni' }}</span>
                @endif
            </div>
            @if ($investments->isEmpty())
                <p class="border-t border-gray-200 px-5 py-4 text-sm text-gray-600 sm:px-6 dark:border-white/10 dark:text-gray-300">
                    @if ($plan->previousPlan)
                        Le rilevazioni precedenti sono nello <x-filament::link :href="\App\Filament\Pages\PlanDetail::getUrl(['plan' => $plan->previousPlan->id])">storico del PAC precedente</x-filament::link>.
                    @else
                        Nessuna rilevazione salvata per questo PAC.
                    @endif
                </p>
            @else
                <div class="grid gap-px border-t border-gray-200 dark:border-white/10">
                    @foreach ($investments as $investment)
                        <details wire:key="history-{{ $investment->id }}" class="group border-b border-gray-200 last:border-b-0 dark:border-white/10">
                            <summary class="grid cursor-pointer list-none grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1 px-5 py-3 hover:bg-gray-50 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto] sm:px-6 dark:hover:bg-white/5">
                                <span class="font-medium">{{ ucfirst($investment->period->locale('it')->translatedFormat('F Y')) }}</span>
                                <span class="col-start-1 text-sm text-gray-600 sm:col-start-2 sm:row-start-1 dark:text-gray-300">
                                    {{ match ($investment->purchased_quantity) {
                                        0 => 'Nessun acquisto',
                                        1 => '1 quota acquistata',
                                        default => number_format($investment->purchased_quantity, 0, ',', '.').' quote acquistate',
                                    } }}
                                </span>
                                <span class="col-start-1 text-sm font-medium tabular-nums sm:col-start-3 sm:row-start-1">{{ \App\Support\Decimal::formatEuro((int) $investment->getRawOriginal('purchased_amount')) }}</span>
                                <x-filament::icon icon="heroicon-o-chevron-down" class="col-start-2 row-start-1 size-5 text-gray-600 group-open:rotate-180 sm:col-start-4 dark:text-gray-300" />
                            </summary>
                            <div class="border-t border-gray-100 px-5 py-4 sm:px-6 dark:border-white/10">
                                <dl class="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
                                    <div>
                                        <dt class="text-gray-600 dark:text-gray-300">Prezzo unitario</dt>
                                        <dd>{{ str_replace('.', ',', $investment->unit_price) }} € del {{ $investment->price_date->format('d/m/Y') }}</dd>
                                    </div>
                                    @foreach ([
                                        'target_amount' => 'Obiettivo del mese',
                                        'portfolio_value_before' => 'Valore prima dell’acquisto',
                                        'theoretical_investment' => 'Importo teorico',
                                        'proposed_amount' => 'Importo proposto',
                                        'invested_amount_after' => 'Capitale investito dopo',
                                        'virtual_liquidity_increase' => 'Liquidità virtuale del mese',
                                        'virtual_liquidity_after' => 'Liquidità virtuale totale',
                                    ] as $field => $label)
                                        <div>
                                            <dt class="text-gray-600 dark:text-gray-300">{{ $label }}</dt>
                                            <dd>{{ \App\Support\Decimal::formatEuro((int) $investment->getRawOriginal($field)) }}</dd>
                                        </div>
                                    @endforeach
                                    <div><dt class="text-gray-600 dark:text-gray-300">Quote prima</dt><dd>{{ $investment->quantity_before }}</dd></div>
                                    <div><dt class="text-gray-600 dark:text-gray-300">Quote proposte</dt><dd>{{ $investment->proposed_quantity }}</dd></div>
                                    <div><dt class="text-gray-600 dark:text-gray-300">Quote dopo</dt><dd>{{ $investment->quantity_after }}</dd></div>
                                    <div><dt class="text-gray-600 dark:text-gray-300">Valore dopo l’acquisto</dt><dd>{{ \App\Support\Decimal::formatEuro(\App\Support\Decimal::value($investment->quantity_after, \App\Support\Decimal::price($investment->unit_price))) }}</dd></div>
                                </dl>
                                <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                                    {{ match ($investment->range_status) {
                                        'virtual_liquidity' => 'Obiettivo già superato.',
                                        'above_increment' => 'Importo teorico sopra l’incremento mensile.',
                                        default => 'Importo teorico entro l’incremento mensile.',
                                    } }}
                                    @if ($investment->rounding_exceeds_increment)
                                        Il controvalore proposto supera l’incremento per arrotondamento.
                                    @endif
                                </p>
                                @if ($isActive && $latest?->is($investment))
                                    <div class="mt-4 flex flex-wrap gap-3">
                                        <x-filament::button color="gray" wire:click="$wire.mountAction('editLatestInvestment', { investment: {{ $investment->id }} })">Correggi rilevazione</x-filament::button>
                                        <x-filament::button color="danger" wire:click="$wire.mountAction('deleteLatestInvestment', { investment: {{ $investment->id }} })">Elimina rilevazione</x-filament::button>
                                    </div>
                                @endif
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif
        </section>

        <details class="group rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-gray-900 sm:p-6">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-base font-semibold">Andamento per calendario <x-filament::icon icon="heroicon-o-chevron-down" class="size-5 shrink-0 group-open:rotate-180" /></summary>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h3 class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">Obiettivo e valore prima dell’acquisto</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">La linea mostra l’obiettivo mensile. I punti indicano il valore delle quote prima di ciascun acquisto registrato. Il grafico copre fino a 180 mesi.</p>
                </div>
                <div class="flex flex-wrap gap-4 text-xs font-medium text-gray-600 dark:text-gray-400">
                    <span class="inline-flex items-center gap-2"><span class="h-0.5 w-5 bg-primary-600"></span>Obiettivo</span>
                    <span class="inline-flex items-center gap-2"><span class="size-2.5 rounded-full border-2 border-violet-600 bg-white dark:bg-gray-900"></span>Prima dell’acquisto</span>
                </div>
            </div>
            <div class="mt-5 overflow-x-auto">
                <svg viewBox="0 0 900 245" role="img" aria-label="Grafico dell’obiettivo per calendario e del valore prima degli acquisti" class="h-64 min-w-[720px] w-full">
                    <line x1="56" y1="32" x2="846" y2="32" class="stroke-gray-100 dark:stroke-gray-800" />
                    <line x1="56" y1="117" x2="846" y2="117" class="stroke-gray-100 dark:stroke-gray-800" />
                    <line x1="56" y1="202" x2="846" y2="202" class="stroke-gray-200 dark:stroke-gray-700" />
                    <text x="8" y="36" class="fill-gray-400 text-[10px]">{{ \App\Support\Decimal::formatEuro($maxChartValue) }}</text>
                    <text x="8" y="206" class="fill-gray-400 text-[10px]">€ 0</text>
                    <path d="{{ $targetPath }}" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="text-primary-600 dark:text-primary-400" />
                    @foreach ($points as $index => $point)
                        @php
                            $x = count($points) < 2 ? 450 : 56 + ($index / (count($points) - 1) * 790);
                            $y = $point['actual'] === null ? null : 202 - ($point['actual'] / $maxChartValue * 170);
                        @endphp
                        @if ($point['actual'] !== null)
                            <circle cx="{{ number_format($x, 1, '.', '') }}" cy="{{ number_format($y, 1, '.', '') }}" r="5" fill="white" stroke="#7c3aed" stroke-width="3">
                                <title>{{ $point['period'] }} · prima dell’acquisto {{ \App\Support\Decimal::formatEuro($point['actual']) }}</title>
                            </circle>
                        @endif
                    @endforeach
                    @foreach (collect($points)->values()->filter(fn ($point, $index) => $index === 0 || $index === count($points) - 1) as $point)
                        @php $index = array_search($point, $points, true); $x = count($points) < 2 ? 450 : 56 + ($index / (count($points) - 1) * 790); @endphp
                        <text x="{{ number_format($x, 1, '.', '') }}" y="230" text-anchor="middle" class="fill-gray-500 text-[10px]">{{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $point['period'])->format('m/Y') }}</text>
                    @endforeach
                </svg>
            </div>
        </details>

        <details class="group rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 sm:p-6">
                <span class="text-base font-semibold">Configurazione e saldo iniziale</span>
                <x-filament::icon icon="heroicon-o-chevron-down" class="size-5 text-gray-500 transition-transform group-open:rotate-180" />
            </summary>
            <div class="grid gap-3 border-t border-gray-100 p-5 dark:border-gray-800 sm:grid-cols-2 lg:grid-cols-4 sm:p-6">
                <div><p class="text-sm text-gray-600 dark:text-gray-300">Modalità</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $plan->opening_period ? 'Con saldo iniziale' : 'Da zero' }}</p></div>
                <div><p class="text-sm text-gray-600 dark:text-gray-300">Inizio originario</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $plan->start_period->format('m/Y') }}</p></div>
                <div><p class="text-sm text-gray-600 dark:text-gray-300">Mese coperto all’apertura</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $plan->opening_period?->format('m/Y') ?? 'Nessuno' }}</p></div>
                @if ($isActive)
                    <div><p class="text-sm text-gray-600 dark:text-gray-300">Primo mese registrabile</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $nextPeriod->format('m/Y') }}</p></div>
                @endif
                <div><p class="text-sm text-gray-600 dark:text-gray-300">Quote di apertura</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">{{ number_format((int) $plan->opening_quantity, 0, ',', '.') }}</p></div>
                <div><p class="text-sm text-gray-600 dark:text-gray-300">Capitale iniziale</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney((int) $plan->getRawOriginal('opening_invested_amount')) }}</p></div>
                <div><p class="text-sm text-gray-600 dark:text-gray-300">Obiettivo di apertura</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney((int) $plan->getRawOriginal('opening_target_amount')) }}</p></div>
                <div><p class="text-sm text-gray-600 dark:text-gray-300">Liquidità virtuale iniziale</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney((int) $plan->getRawOriginal('opening_virtual_liquidity')) }}</p></div>
            </div>
            @if ($isActive && $plan->successor === null && ($firstSuccessorPeriod = $this->firstCompatibleSuccessorPeriod($plan)) > now()->format('Y-m'))
                <p class="px-5 pb-5 text-sm text-gray-600 sm:px-6 dark:text-gray-300">Puoi passare a un nuovo incremento da {{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $firstSuccessorPeriod)->locale('it')->translatedFormat('F Y') }}.</p>
            @endif
        </details>

    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
