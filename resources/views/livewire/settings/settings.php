<?php

use App\Livewire\Concerns\AuthorizesEveryRequest;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\AppSetting;
use Livewire\Component;

/**
 * The add-on's section on System, then Add-ons: the name printed on the cards and the Hotspot login address the QR
 * code opens.
 */
new class extends Component
{
    use AuthorizesEveryRequest, ShowsToasts;

    public string $card_title = '';

    public string $login_url = '';

    public function mount(): void
    {
        $this->authorizeAccess();
        $this->card_title = (string) AppSetting::valueOf('vouchers.card_title');
        $this->login_url = (string) AppSetting::valueOf('vouchers.login_url');
    }

    protected function authorizeAccess(): void
    {
        $this->authorize('generate-vouchers');
    }

    public function save(): void
    {
        $this->authorize('generate-vouchers');
        $this->card_title = trim($this->card_title);
        $this->login_url = trim($this->login_url);
        $this->validate([
            'card_title' => ['nullable', 'string', 'max:40'],
            'login_url' => ['nullable', 'string', 'max:200', 'url:http,https'],
        ]);

        AppSetting::put('vouchers.card_title', $this->card_title === '' ? null : $this->card_title);
        AppSetting::put('vouchers.login_url', $this->login_url === '' ? null : $this->login_url);
        $this->toast(__('Settings saved.'));
    }
};
