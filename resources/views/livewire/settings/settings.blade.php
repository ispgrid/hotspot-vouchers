<form wire:submit="save" class="grid grid-cols-1 gap-5">
    <div class="grid grid-cols-1 items-start gap-5 sm:grid-cols-2">
        <x-ui.input wire:model="card_title" :label="__('Name on the cards')" :placeholder="config('app.name')" maxlength="40" />
        <x-ui.input wire:model="login_url" type="url" :label="__('Hotspot login address')" placeholder="http://hotspot.example/login" :description="__('The QR code on each card opens it and logs the card in. Empty: no QR code.')" />
    </div>

    <div class="grid grid-cols-1 content-start gap-3">
        <x-ui.heading>{{ __('Logo on the cards') }}</x-ui.heading>
        @if ($this->logoPreview)
            <div class="flex items-center gap-4">
                <img src="{{ $this->logoPreview }}" alt="{{ __('Logo') }}" class="max-h-16 rounded border border-zinc-200 bg-white p-1 dark:border-zinc-700">
                <x-ui.button variant="secondary" size="sm" icon="trash" wire:click="removeLogo">{{ __('Remove logo') }}</x-ui.button>
            </div>
        @endif
        <x-ui.file-upload wire:model="logo" accept="image/png,image/jpeg" :label="__('Upload a logo')" :description="__('PNG or JPG, 1 MB at most. A new logo replaces the old one.')" />
    </div>

    <div class="grid grid-cols-1 items-start gap-5 sm:grid-cols-2">
        <x-ui.input wire:model="footer_1" :label="__('Footer line 1')" :placeholder="__('Help: 98765 43210')" maxlength="40" />
        <x-ui.input wire:model="footer_2" :label="__('Footer line 2')" :placeholder="__('Ask the shop for help')" maxlength="40" />
        <x-ui.input wire:model="band_colour" :label="__('Band colour')" placeholder="#166534" maxlength="7" :description="__('A colour for the top of each card, like #166534. Empty: no band. Thermal paper prints no band.')" />
        <x-ui.select wire:model="layout" :label="__('Default layout')" :options="$this->layouts" :description="__('Print cards lists every layout; this one comes first.')" />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-ui.switch wire:model="show_price" :label="__('Show the price')" />
        <x-ui.switch wire:model="show_validity" :label="__('Show the validity')" />
        <x-ui.switch wire:model="show_plan" :label="__('Show the plan name')" />
        <x-ui.switch wire:model="show_qr" :label="__('Show the QR code')" :description="__('Needs the Hotspot login address.')" />
    </div>

    <div>
        <x-ui.button type="submit">{{ __('Save') }}</x-ui.button>
    </div>
</form>
