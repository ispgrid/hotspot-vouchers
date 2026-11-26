<section class="grid gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <x-ui.link href="{{ route('vouchers.index') }}" wire:navigate class="text-sm">{{ __('Vouchers') }}</x-ui.link>
            <x-ui.heading level="1" size="xl" class="mt-1 flex items-center gap-2">
                {{ __('Batch :batch', ['batch' => $this->batch->id]) }}
                <x-ui.badge :color="$this->batch->stateColor()">{{ $this->batch->stateLabel() }}</x-ui.badge>
            </x-ui.heading>
            <x-ui.text muted class="mt-1">
                {{ $this->batch->plan_name }}, {{ $this->validity }}. {{ __('Card price') }}: {{ $this->money((string) $this->batch->card_price) }}.
                @if ($this->batch->soldThrough())
                    {{ __('Sold through') }}: {{ $this->batch->soldThrough() }}.
                @endif
                @if ($this->batch->note)
                    {{ $this->batch->note }}
                @endif
            </x-ui.text>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('print-vouchers')
                <x-ui.button variant="secondary" href="{{ route('vouchers.batches.print', $this->batch->id) }}" target="_blank" rel="noopener">{{ __('Print cards') }}</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('vouchers.batches.csv', $this->batch->id) }}">{{ __('Export CSV') }}</x-ui.button>
            @endcan
            @can('generate-vouchers')
                @if ($this->batch->state() === 'new')
                    <x-ui.button x-on:click="$dispatch('modal-open', { name: 'hand-out' })">{{ __('Hand out') }}</x-ui.button>
                @elseif ($this->batch->state() === 'on')
                    <x-ui.button variant="secondary" wire:click="switchOff" wire:confirm="{{ __('Switch the batch off? Its cards stop logging in, and online ones are disconnected.') }}">{{ __('Switch off') }}</x-ui.button>
                @else
                    <x-ui.button wire:click="switchOn">{{ __('Switch on') }}</x-ui.button>
                @endif
            @endcan
            @if ($this->canDelete)
                <x-ui.button variant="danger" wire:click="delete" wire:confirm="{{ __('Delete this batch and all its cards?') }}">{{ __('Delete batch') }}</x-ui.button>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <x-ui.stat :label="__('Cards')" :value="count($this->allCards)" />
        <x-ui.stat :label="__('Sold')" :value="($this->counts['sold'] ?? 0) + ($this->counts['used'] ?? 0) + ($this->counts['expired'] ?? 0)" />
        <x-ui.stat :label="__('Used')" :value="($this->counts['used'] ?? 0) + ($this->counts['expired'] ?? 0)" />
        <x-ui.stat :label="__('Expired')" :value="$this->counts['expired'] ?? 0" />
        <x-ui.stat :label="__('Takings')" :value="$this->takings" compact />
    </div>

    <div class="sm:max-w-xs">
        <x-ui.select wire:model.live="status" :label="__('Status')" :options="$this->statuses" />
    </div>

    <x-ui.table>
        <x-slot:head>
            <x-ui.th>{{ $this->batch->login_style === 'pin' ? __('PIN') : __('Username') }}</x-ui.th>
            @if ($this->batch->login_style !== 'pin')
                <x-ui.th>{{ __('Password') }}</x-ui.th>
            @endif
            <x-ui.th>{{ __('Status') }}</x-ui.th>
            <x-ui.th>{{ __('Sold') }}</x-ui.th>
            <x-ui.th>{{ __('First login') }}</x-ui.th>
            <x-ui.th>{{ __('Expiry') }}</x-ui.th>
            <x-ui.th></x-ui.th>
        </x-slot:head>

        @forelse ($this->cards as $card)
            <tr wire:key="card-{{ $card['id'] }}">
                <x-ui.td class="font-mono font-medium">{{ $card['code'] }}</x-ui.td>
                @if ($this->batch->login_style !== 'pin')
                    <x-ui.td class="font-mono">{{ $card['password'] }}</x-ui.td>
                @endif
                <x-ui.td><x-ui.badge :color="$this->statusColor($card['status'])">{{ $this->statusLabel($card['status']) }}</x-ui.badge></x-ui.td>
                <x-ui.td class="whitespace-nowrap tabular-nums">{{ $card['sold_at'] }}</x-ui.td>
                <x-ui.td class="whitespace-nowrap tabular-nums">{{ $card['first_login'] }}</x-ui.td>
                <x-ui.td class="whitespace-nowrap tabular-nums">{{ $card['expiry'] }}</x-ui.td>
                <x-ui.td class="text-end">
                    @can('generate-vouchers')
                        @if ($this->batch->shop_id === null && $card['status'] === 'unused')
                            <x-ui.button size="sm" variant="secondary" wire:click="markSold({{ $card['id'] }})">{{ __('Mark sold') }}</x-ui.button>
                        @elseif ($this->batch->shop_id === null && $card['status'] === 'sold')
                            <x-ui.button size="sm" variant="ghost" wire:click="markSold({{ $card['id'] }}, false)">{{ __('Not sold') }}</x-ui.button>
                        @endif
                    @endcan
                </x-ui.td>
            </tr>
        @empty
            <tr>
                <x-ui.td colspan="7" class="py-8 text-center text-zinc-500 dark:text-zinc-400">{{ __('No card has this status.') }}</x-ui.td>
            </tr>
        @endforelse
    </x-ui.table>

    @can('generate-vouchers')
        <x-ui.modal name="hand-out">
            <form wire:submit="handOut" class="grid grid-cols-1 gap-5" novalidate>
                <x-ui.heading size="lg">{{ __('Hand out') }}</x-ui.heading>
                <x-ui.text muted>{{ __('The cards work from now on. Handed to a shop, every card counts as sold and the shop is charged its price per card.') }}</x-ui.text>
                <x-ui.select wire:model.live="shop_id" :label="__('To')" :options="$this->shops" />
                @if ($shop_id !== '')
                    <x-ui.input wire:model="shop_price" inputmode="decimal" :label="__('Shop price per card')" required />
                @endif
                <div class="flex justify-end gap-2">
                    <x-ui.button variant="secondary" x-on:click="$dispatch('modal-close', { name: 'hand-out' })">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button type="submit">{{ __('Hand out') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endcan
</section>
