<form wire:submit="save" class="grid grid-cols-1 gap-5">
    <div class="grid grid-cols-1 items-start gap-5 sm:grid-cols-2">
        <x-ui.input wire:model="card_title" :label="__('Name on the cards')" :placeholder="config('app.name')" maxlength="40" />
        <x-ui.input wire:model="login_url" type="url" :label="__('Hotspot login address')" placeholder="http://hotspot.example/login" :description="__('The QR code on each card opens it and logs the card in. Empty: no QR code.')" />
    </div>
    <div>
        <x-ui.button type="submit">{{ __('Save') }}</x-ui.button>
    </div>
</form>
