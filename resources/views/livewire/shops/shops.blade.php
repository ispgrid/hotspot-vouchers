<section class="grid gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <x-ui.link href="{{ route('vouchers.index') }}" wire:navigate class="text-sm">{{ __('Vouchers') }}</x-ui.link>
            <x-ui.heading level="1" size="xl" class="mt-1">{{ __('Shops') }}</x-ui.heading>
            <x-ui.text muted class="mt-1">{{ __('Shops buy batches. Handing a batch to a shop charges it; record its payments on the shop.') }}</x-ui.text>
        </div>
        <x-ui.button x-on:click="$dispatch('modal-open', { name: 'new-shop' })">{{ __('Add shop') }}</x-ui.button>
    </div>

    <x-ui.table>
        <x-slot:head>
            <x-ui.th>{{ __('Shop') }}</x-ui.th>
            <x-ui.th>{{ __('Phone') }}</x-ui.th>
            <x-ui.th>{{ __('Charged') }}</x-ui.th>
            <x-ui.th>{{ __('Paid') }}</x-ui.th>
            <x-ui.th>{{ __('Due') }}</x-ui.th>
        </x-slot:head>

        @forelse ($this->shops as $shop)
            <tr wire:key="shop-{{ $shop['id'] }}">
                <x-ui.td><x-ui.link href="{{ route('vouchers.shops.show', $shop['id']) }}" wire:navigate class="font-medium">{{ $shop['name'] }}</x-ui.link></x-ui.td>
                <x-ui.td>{{ $shop['phone'] }}</x-ui.td>
                <x-ui.td class="tabular-nums">{{ $shop['charged'] }}</x-ui.td>
                <x-ui.td class="tabular-nums">{{ $shop['paid'] }}</x-ui.td>
                <x-ui.td @class(['tabular-nums', 'font-medium text-red-700 dark:text-red-300' => $shop['owes']])>{{ $shop['due'] }}</x-ui.td>
            </tr>
        @empty
            <tr>
                <x-ui.td colspan="5" class="py-8 text-center text-zinc-500 dark:text-zinc-400">{{ __('No shops yet.') }}</x-ui.td>
            </tr>
        @endforelse
    </x-ui.table>

    <x-ui.modal name="new-shop">
        <form wire:submit="add" class="grid grid-cols-1 gap-5" novalidate>
            <x-ui.heading size="lg">{{ __('Add shop') }}</x-ui.heading>
            <x-ui.input wire:model="name" :label="__('Name')" maxlength="100" required />
            <x-ui.input wire:model="phone" type="tel" :label="__(':label (optional)', ['label' => __('Phone')])" maxlength="20" />
            <x-ui.input wire:model="note" :label="__(':label (optional)', ['label' => __('Note')])" maxlength="255" />
            <div class="flex justify-end gap-2">
                <x-ui.button variant="secondary" x-on:click="$dispatch('modal-close', { name: 'new-shop' })">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit">{{ __('Add shop') }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</section>
