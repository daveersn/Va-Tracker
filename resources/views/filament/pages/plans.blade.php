<x-filament-panels::page>
    @php
        $activePlans = $plans->where('status', 'active');
        $closedPlans = $plans->where('status', 'closed');
    @endphp


    @if ($planPreview !== [])
        <x-filament::section>
            <x-slot name="heading">{{ $configurationPlanId === null ? 'Riepilogo di apertura' : 'Riepilogo della modifica' }}</x-slot>
            <p class="mb-4">{{ $previewInstrument?->ticker }} · {{ $previewInstrument?->name }} — da {{ substr($planPreview['start_period'], 5, 2) }}/{{ substr($planPreview['start_period'], 0, 4) }}, incremento mensile {{ \App\Support\Decimal::formatMoney($planPreview['monthly_increment']) }} €.</p>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>Obiettivo di apertura <strong class="block">{{ \App\Support\Decimal::formatMoney($planPreview['opening_target_amount']) }} €</strong></div>
                <div>Primo periodo registrabile <strong class="block">{{ substr($planPreview['first_period'], 5, 2) }}/{{ substr($planPreview['first_period'], 0, 4) }}</strong></div>
                <div>Obiettivo del primo periodo <strong class="block">{{ \App\Support\Decimal::formatMoney($planPreview['first_target_amount']) }} €</strong></div>
                <div>Quote di apertura <strong class="block">{{ $planPreview['opening_quantity'] }}</strong></div>
                <div>Capitale di apertura <strong class="block">{{ \App\Support\Decimal::formatMoney($planPreview['opening_invested_amount']) }} €</strong></div>
                <div>Liquidità virtuale di apertura <strong class="block">{{ \App\Support\Decimal::formatMoney($planPreview['opening_virtual_liquidity']) }} €</strong></div>
            </div>
            <p class="my-4 text-sm text-gray-500 dark:text-gray-400">Il riepilogo non ha salvato dati. Confermando non verranno create rilevazioni né modificate quotazioni.</p>
            {{ $this->confirmPlanAction }}
        </x-filament::section>
    @endif

    @foreach (['PAC attivi' => $activePlans, 'PAC conclusi' => $closedPlans] as $heading => $group)
        @if ($group->isNotEmpty())
            <section class="rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                <h2 class="px-5 py-4 text-xl font-semibold sm:px-6">{{ $heading }}</h2>
                <ul class="divide-y divide-gray-200 border-t border-gray-200 dark:divide-white/10 dark:border-white/10">
                    @foreach ($group as $plan)
                        <li wire:key="plan-{{ $plan->id }}" class="flex flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-6">
                            <div class="min-w-0">
                                <h3 class="break-words text-base font-semibold">{{ $plan->instrument->ticker }} <span class="font-normal text-gray-600 dark:text-gray-300">{{ $plan->instrument->name }}</span></h3>
                                <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">
                                    Obiettivo +{{ \App\Support\Decimal::formatEuro((int) $plan->getRawOriginal('monthly_increment')) }} al mese
                                    · Da {{ $plan->start_period->format('m/Y') }}
                                    @if ($plan->closed_period)
                                        a {{ $plan->closed_period->format('m/Y') }}
                                    @endif
                                </p>
                                @if ($plan->previousPlan || $plan->successor)
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                        @if ($plan->previousPlan)
                                            <x-filament::link :href="\App\Filament\Pages\PlanDetail::getUrl(['plan' => $plan->previousPlan->id])">Continua il PAC precedente</x-filament::link>
                                        @endif
                                        @if ($plan->successor)
                                            <x-filament::link :href="\App\Filament\Pages\PlanDetail::getUrl(['plan' => $plan->successor->id])">Vedi il PAC che lo continua</x-filament::link>
                                        @endif
                                    </p>
                                @endif
                            </div>
                            <x-filament::button color="gray" tag="a" :href="\App\Filament\Pages\PlanDetail::getUrl(['plan' => $plan->id])">
                                {{ $plan->status === 'active' ? 'Apri PAC' : 'Apri storico' }}
                            </x-filament::button>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endforeach

    @if ($activePlans->isEmpty())
        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $closedPlans->isEmpty() ? 'Aggiungi il tuo primo PAC per iniziare a registrare i mesi.' : 'Non ci sono PAC attivi. Puoi aprire un nuovo piano o consultare lo storico.' }}</p>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
