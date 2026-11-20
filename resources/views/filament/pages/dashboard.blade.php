<x-filament-panels::page>
    @php
        $singlePlan = count($rows) === 1 ? $rows[0] : null;
    @endphp

    @if ($rows === [])
        <section aria-labelledby="dashboard-empty" class="grid justify-items-start gap-4 rounded-xl border border-gray-200 bg-white p-6 sm:p-8 dark:border-white/10 dark:bg-gray-900">
            <div>
                <h2 id="dashboard-empty" class="text-xl font-semibold">
                    {{ $hasClosedPlans ? 'Nessun PAC attivo' : 'Il tuo primo PAC parte da qui' }}
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Crea un piano da zero oppure continua da un saldo iniziale.</p>
            </div>
            <x-filament::button tag="a" :href="\App\Filament\Pages\Plans::getUrl(['create' => 1])">Crea un PAC</x-filament::button>
            @if ($hasClosedPlans)
                <x-filament::link :href="\App\Filament\Pages\Plans::getUrl()">Consulta lo storico dei PAC</x-filament::link>
            @endif
        </section>
    @else
        <section aria-labelledby="dashboard-month" class="rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
            <div class="px-5 py-4 sm:px-6">
                <h2 id="dashboard-month" class="text-xl font-semibold tracking-tight">Rilevazioni di {{ $currentPeriod }}</h2>
            </div>

            <ul class="divide-y divide-gray-200 border-t border-gray-200 dark:divide-white/10 dark:border-white/10">
                @foreach ($rows as $row)
                    @php($plan = $row['plan'])
                    <li wire:key="plan-{{ $plan->id }}" class="grid items-start gap-4 px-5 py-5 sm:px-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] xl:items-center">
                        <div class="min-w-0">
                            <a href="{{ \App\Filament\Pages\PlanDetail::getUrl(['plan' => $plan->id]) }}" class="inline-flex max-w-full items-center gap-1.5 rounded-sm text-base font-semibold underline decoration-gray-300 underline-offset-4 hover:decoration-current focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary-500 dark:decoration-gray-500">
                                <span class="break-all">{{ $plan->instrument->ticker }}</span>
                                <x-filament::icon icon="heroicon-m-chevron-right" class="size-4 shrink-0" />
                            </a>
                            <p class="mt-1 break-words text-sm text-gray-600 dark:text-gray-300">{{ $plan->instrument->name }}</p>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Obiettivo +{{ \App\Support\Decimal::formatEuro((int) $plan->getRawOriginal('monthly_increment')) }} al mese · da {{ $plan->start_period->locale('it')->translatedFormat('F Y') }}</p>
                        </div>

                        <div>
                            <p @class(['text-sm font-medium', 'text-amber-800 dark:text-amber-300' => $row['eligible']])>{{ $row['status'] }}</p>
                            @if ($row['last'] !== null && $row['status'] !== 'Rilevazione salvata')
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Ultima rilevazione a {{ $row['last']->locale('it')->translatedFormat('F Y') }}</p>
                            @elseif ($row['last'] === null && $plan->opening_period !== null)
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                    @if ($plan->previous_plan_id !== null)
                                        <a class="underline underline-offset-4" href="{{ \App\Filament\Pages\PlanDetail::getUrl(['plan' => $plan->previous_plan_id]) }}">Saldo dal PAC precedente</a>
                                    @else
                                        Saldo di apertura
                                    @endif
                                    a {{ $plan->opening_period->locale('it')->translatedFormat('F Y') }}
                                </p>
                            @endif
                        </div>

                        @if ($row['eligible'])
                            <div class="justify-self-start xl:justify-self-end">
                                <x-filament::button size="lg" tag="a" :href="\App\Filament\Pages\PlanDetail::getUrl(['plan' => $plan->id, 'record' => 1])" :aria-label="'Registra rilevazione per '.$plan->instrument->ticker">
                                    Registra rilevazione
                                </x-filament::button>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>

        <section aria-labelledby="dashboard-portfolio" class="rounded-xl border border-gray-200 bg-white px-5 py-5 sm:px-6 dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2">
                <h2 id="dashboard-portfolio" class="text-xl font-semibold tracking-tight">Portafoglio</h2>
                <x-filament::link :href="\App\Filament\Pages\Plans::getUrl()">{{ $hasClosedPlans ? 'Tutti i PAC e storico' : 'Tutti i PAC' }}</x-filament::link>
            </div>

            <dl @class(['mt-5 grid grid-cols-2 gap-x-4 gap-y-5', 'xl:grid-cols-4' => $singlePlan !== null, 'xl:grid-cols-3' => $singlePlan === null])>
                <div class="col-span-2 xl:col-span-1">
                    <dt class="text-sm text-gray-600 dark:text-gray-300">{{ $missingPrices ? 'Valore parziale dei titoli' : 'Valore dei titoli' }}</dt>
                    <dd class="mt-1 break-words text-2xl font-semibold tabular-nums">{{ $missingPrices === count($rows) ? 'Non disponibile' : \App\Support\Decimal::formatEuro($totalValue) }}</dd>
                    @if ($singlePlan !== null && $singlePlan['plan']->instrument->latest_price !== null)
                        @php($instrument = $singlePlan['plan']->instrument)
                        <dd class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            {{ $singlePlan['quantity'] }} quote a {{ str_replace('.', ',', $instrument->latest_price) }} €<br>
                            Prezzo del {{ $instrument->latest_price_date->format('d/m/Y') }}
                        </dd>
                    @endif
                </div>
                @if ($singlePlan !== null)
                    <div>
                        <dt class="text-sm text-gray-600 dark:text-gray-300">Obiettivo di {{ $currentPeriod }}</dt>
                        <dd class="mt-1 break-words text-xl font-semibold tabular-nums">{{ $singlePlan['target'] === null ? 'Non ancora maturato' : \App\Support\Decimal::formatEuro($singlePlan['target']) }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-sm text-gray-600 dark:text-gray-300">Capitale investito</dt>
                    <dd class="mt-1 break-words text-xl font-semibold tabular-nums">{{ \App\Support\Decimal::formatEuro($totalInvested) }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-600 dark:text-gray-300">Liquidità virtuale</dt>
                    <dd class="mt-1 break-words text-xl font-semibold tabular-nums">{{ \App\Support\Decimal::formatEuro($totalVirtual) }}</dd>
                    <dd class="mt-1 text-sm text-gray-600 dark:text-gray-300">Saldo simulato, non spendibile</dd>
                </div>
            </dl>

            @if ($missingPrices)
                <p role="status" class="mt-5 text-sm text-amber-800 dark:text-amber-300">
                    {{ $missingPrices === 1 ? 'Un PAC con quote è senza quotazione.' : $missingPrices.' PAC con quote sono senza quotazione.' }}
                    <x-filament::link :href="\App\Filament\Pages\Instruments::getUrl()">Aggiorna le quotazioni</x-filament::link>
                </p>
            @endif

            @if ($singlePlan === null)
                <ul class="mt-5 divide-y divide-gray-200 border-t border-gray-200 dark:divide-white/10 dark:border-white/10">
                    @foreach ($rows as $row)
                        @php($plan = $row['plan'])
                        <li class="py-4">
                            <h3 class="text-sm font-semibold">{{ $plan->instrument->ticker }} · da {{ $plan->start_period->format('m/Y') }} · Obiettivo +{{ \App\Support\Decimal::formatEuro((int) $plan->getRawOriginal('monthly_increment')) }} al mese</h3>
                            <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
                                <div>
                                    <dt class="text-gray-600 dark:text-gray-300">Valore dei titoli</dt>
                                    <dd class="font-medium tabular-nums">{{ $row['valuation'] === null ? 'Non disponibile' : \App\Support\Decimal::formatEuro($row['valuation']) }}</dd>
                                    <dd class="text-gray-600 dark:text-gray-300">{{ $row['quantity'] }} quote
                                        @if ($plan->instrument->latest_price !== null)
                                            a {{ str_replace('.', ',', $plan->instrument->latest_price) }} € del {{ $plan->instrument->latest_price_date->format('d/m/Y') }}
                                        @endif</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-600 dark:text-gray-300">Obiettivo di {{ $currentPeriod }}</dt>
                                    <dd class="font-medium tabular-nums">{{ $row['target'] === null ? 'Non ancora maturato' : \App\Support\Decimal::formatEuro($row['target']) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-600 dark:text-gray-300">Capitale investito</dt>
                                    <dd class="font-medium tabular-nums">{{ \App\Support\Decimal::formatEuro($row['capital']) }}</dd>
                                </div>
                            </dl>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
</x-filament-panels::page>
