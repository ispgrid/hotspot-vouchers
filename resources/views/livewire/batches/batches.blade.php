<section class="grid gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <x-ui.heading level="1" size="xl">{{ __('Vouchers') }}</x-ui.heading>
            <x-ui.text muted class="mt-1">{{ __('Batches of prepaid Hotspot cards. A new batch does not work until it is handed out, to a shop or for counter sale.') }}</x-ui.text>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('generate-vouchers')
                <x-ui.button variant="secondary" href="{{ route('vouchers.shops.index') }}" wire:navigate>{{ __('Shops') }}</x-ui.button>
                <x-ui.button x-on:click="$dispatch('modal-open', { name: 'new-batch' })">{{ __('New batch') }}</x-ui.button>
            @endcan
        </div>
    </div>

    <div class="grid gap-3 sm:max-w-md">
        <x-ui.input wire:model.live.debounce.400ms="code" :label="__('Find a card')" :placeholder="__('Code')" autocomplete="off" autocapitalize="characters" spellcheck="false" />
        @if (trim($code) !== '')
            @if ($this->found)
                <x-ui.text>
                    <span class="font-mono font-medium">{{ $this->found['code'] }}</span>
                    <x-ui.badge :color="$this->found['color']">{{ $this->found['label'] }}</x-ui.badge>
                    <x-ui.link href="{{ route('vouchers.batches.show', $this->found['batch']) }}" wire:navigate>{{ __('Batch :batch', ['batch' => $this->found['batch']]) }}</x-ui.link>
                    @if ($this->found['customer'] !== null)
                        <x-ui.link href="{{ route('customers.show', $this->found['customer']) }}" wire:navigate>{{ __('Customer') }}</x-ui.link>
                    @endif
                </x-ui.text>
            @else
                <x-ui.text muted>{{ __('No card has this code.') }}</x-ui.text>
            @endif
        @endif
    </div>

    <x-ui.table>
        <x-slot:head>
            <x-ui.th>{{ __('Batch') }}</x-ui.th>
            <x-ui.th>{{ __('Made') }}</x-ui.th>
            <x-ui.th>{{ __('Plan') }}</x-ui.th>
            <x-ui.th>{{ __('Card price') }}</x-ui.th>
            <x-ui.th>{{ __('Cards') }}</x-ui.th>
            <x-ui.th>{{ __('Sold') }}</x-ui.th>
            <x-ui.th>{{ __('Used') }}</x-ui.th>
            <x-ui.th>{{ __('Sold through') }}</x-ui.th>
            <x-ui.th>{{ __('Status') }}</x-ui.th>
        </x-slot:head>

        @forelse ($this->batches as $batch)
            <tr wire:key="batch-{{ $batch->id }}">
                <x-ui.td><x-ui.link href="{{ route('vouchers.batches.show', $batch->id) }}" wire:navigate class="font-medium">#{{ $batch->id }}</x-ui.link></x-ui.td>
                <x-ui.td class="whitespace-nowrap tabular-nums">{{ $batch->created_at->format('Y-m-d H:i') }}</x-ui.td>
                <x-ui.td>{{ $batch->plan_name }}</x-ui.td>
                <x-ui.td class="tabular-nums">{{ $this->money((string) $batch->card_price) }}</x-ui.td>
                <x-ui.td class="tabular-nums">{{ $batch->vouchers_count }}</x-ui.td>
                <x-ui.td class="tabular-nums">{{ $batch->sold_count }}</x-ui.td>
                <x-ui.td class="tabular-nums">{{ $this->usedCounts[$batch->id] ?? 0 }}</x-ui.td>
                <x-ui.td>{{ $batch->soldThrough() }}</x-ui.td>
                <x-ui.td><x-ui.badge :color="$batch->stateColor()">{{ $batch->stateLabel() }}</x-ui.badge></x-ui.td>
            </tr>
        @empty
            <tr>
                <x-ui.td colspan="9" class="py-8 text-center text-zinc-500 dark:text-zinc-400">{{ __('No batches yet.') }}</x-ui.td>
            </tr>
        @endforelse
    </x-ui.table>

    {{ $this->batches->links() }}

    @can('generate-vouchers')
        <x-ui.modal name="new-batch" width="lg">
            <form wire:submit="generate" class="grid grid-cols-1 gap-5" novalidate>
                <x-ui.heading size="lg">{{ __('New batch') }}</x-ui.heading>
                @if ($this->plans === [])
                    <x-ui.alert variant="warning">{{ __('Vouchers need a Hotspot plan whose validity starts at the first login. Make one on Plans first.') }}</x-ui.alert>
                @endif
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-ui.select wire:model.live="plan_id" :label="__('Plan')" :options="$this->plans" :placeholder="__('Choose a plan')" :description="__('Validity and limits come from the plan.')" required />
                    <x-ui.input wire:model="count" type="number" min="1" max="200" :label="__('Cards')" :description="__('Up to 200 in one batch.')" required />
                    <x-ui.input wire:model="card_price" inputmode="decimal" :label="__('Card price')" :description="__('Printed on the card.')" required />
                    <x-ui.select wire:model="login_style" :label="__('Login')" :options="$this->loginStyles" required />
                    <x-ui.input wire:model="code_length" type="number" min="6" max="12" :label="__('Code length')" required />
                    <x-ui.input wire:model="prefix" :label="__(':label (optional)', ['label' => __('Prefix')])" maxlength="6" autocapitalize="characters" spellcheck="false" />
                    <x-ui.input wire:model="devices" type="number" min="1" max="10" :label="__('Devices at once')" required />
                    <x-ui.input wire:model="note" :label="__(':label (optional)', ['label' => __('Note')])" maxlength="255" />
                </div>
                <div class="flex justify-end gap-2">
                    <x-ui.button variant="secondary" x-on:click="$dispatch('modal-close', { name: 'new-batch' })">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button type="submit">{{ __('Make batch') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endcan
</section>
