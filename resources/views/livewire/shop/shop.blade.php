<section class="grid gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <x-ui.link href="{{ route('vouchers.shops.index') }}" wire:navigate class="text-sm">{{ __('Shops') }}</x-ui.link>
            <x-ui.heading level="1" size="xl" class="mt-1">{{ $this->shop->name }}</x-ui.heading>
        </div>
        <x-ui.button x-on:click="$dispatch('modal-open', { name: 'payment' })">{{ __('Record payment') }}</x-ui.button>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-ui.stat :label="__('Charged')" :value="$this->balance['charged']" compact />
        <x-ui.stat :label="__('Paid')" :value="$this->balance['paid']" compact />
        <x-ui.stat :label="__('Due')" :value="$this->balance['due']" compact />
    </div>

    <x-ui.card>
        <form wire:submit="save" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-ui.input wire:model="name" :label="__('Name')" maxlength="100" required />
            <x-ui.input wire:model="phone" type="tel" :label="__(':label (optional)', ['label' => __('Phone')])" maxlength="20" />
            <x-ui.input wire:model="note" :label="__(':label (optional)', ['label' => __('Note')])" maxlength="255" />
            <div>
                <x-ui.button type="submit">{{ __('Save') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <div class="grid gap-3">
        <x-ui.heading>{{ __('Ledger') }}</x-ui.heading>
        <x-ui.table>
            <x-slot:head>
                <x-ui.th>{{ __('Date') }}</x-ui.th>
                <x-ui.th>{{ __('Entry') }}</x-ui.th>
                <x-ui.th>{{ __('Amount') }}</x-ui.th>
                <x-ui.th>{{ __('Batch') }}</x-ui.th>
                <x-ui.th>{{ __('Note') }}</x-ui.th>
            </x-slot:head>
            @forelse ($this->entries as $entry)
                <tr wire:key="entry-{{ $loop->index }}">
                    <x-ui.td class="whitespace-nowrap tabular-nums">{{ $entry['date'] }}</x-ui.td>
                    <x-ui.td>{{ $entry['kind'] }}</x-ui.td>
                    <x-ui.td class="tabular-nums">{{ $entry['amount'] }}</x-ui.td>
                    <x-ui.td>
                        @if ($entry['batch'] !== null)
                            <x-ui.link href="{{ route('vouchers.batches.show', $entry['batch']) }}" wire:navigate>#{{ $entry['batch'] }}</x-ui.link>
                        @endif
                    </x-ui.td>
                    <x-ui.td>{{ $entry['note'] }}</x-ui.td>
                </tr>
            @empty
                <tr>
                    <x-ui.td colspan="5" class="py-8 text-center text-zinc-500 dark:text-zinc-400">{{ __('Nothing charged or paid yet.') }}</x-ui.td>
                </tr>
            @endforelse
        </x-ui.table>
    </div>

    <div class="grid gap-3">
        <x-ui.heading>{{ __('Batches') }}</x-ui.heading>
        <x-ui.table>
            <x-slot:head>
                <x-ui.th>{{ __('Batch') }}</x-ui.th>
                <x-ui.th>{{ __('Plan') }}</x-ui.th>
                <x-ui.th>{{ __('Cards') }}</x-ui.th>
                <x-ui.th>{{ __('Handed out') }}</x-ui.th>
            </x-slot:head>
            @forelse ($this->batches as $batch)
                <tr wire:key="batch-{{ $batch['id'] }}">
                    <x-ui.td><x-ui.link href="{{ route('vouchers.batches.show', $batch['id']) }}" wire:navigate>#{{ $batch['id'] }}</x-ui.link></x-ui.td>
                    <x-ui.td>{{ $batch['plan'] }}</x-ui.td>
                    <x-ui.td class="tabular-nums">{{ $batch['cards'] }}</x-ui.td>
                    <x-ui.td class="tabular-nums">{{ $batch['handed_out'] }}</x-ui.td>
                </tr>
            @empty
                <tr>
                    <x-ui.td colspan="4" class="py-8 text-center text-zinc-500 dark:text-zinc-400">{{ __('No batches handed to this shop yet.') }}</x-ui.td>
                </tr>
            @endforelse
        </x-ui.table>
    </div>

    <x-ui.modal name="payment">
        <form wire:submit="recordPayment" class="grid grid-cols-1 gap-5" novalidate>
            <x-ui.heading size="lg">{{ __('Record payment') }}</x-ui.heading>
            <x-ui.input wire:model="amount" inputmode="decimal" :label="__('Amount')" required />
            <x-ui.input wire:model="paid_on" type="date" :label="__('Date')" required />
            <x-ui.input wire:model="payment_note" :label="__(':label (optional)', ['label' => __('Note')])" maxlength="255" />
            <div class="flex justify-end gap-2">
                <x-ui.button variant="secondary" x-on:click="$dispatch('modal-close', { name: 'payment' })">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit">{{ __('Record payment') }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</section>
