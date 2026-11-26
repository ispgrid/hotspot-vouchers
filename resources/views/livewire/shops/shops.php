<?php

use App\Livewire\Concerns\AuthorizesEveryRequest;
use App\Livewire\Concerns\ShowsToasts;
use Illuminate\Validation\Rule;
use Ispgrid\HotspotVouchers\Models\Shop;
use Ispgrid\HotspotVouchers\Support\Amount;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The shops that buy voucher batches, with what each was charged, paid and still owes.
 */
new #[Title('Voucher shops')] class extends Component
{
    use AuthorizesEveryRequest, ShowsToasts;

    public string $name = '';

    public string $phone = '';

    public string $note = '';

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    protected function authorizeAccess(): void
    {
        $this->authorize('generate-vouchers');
    }

    /**
     * @return list<array{id: int, name: string, phone: ?string, charged: string, paid: string, due: string, owes: bool}>
     */
    #[Computed]
    public function shops(): array
    {
        return Shop::query()->orderBy('name')->get()->map(function (Shop $shop): array {
            $balance = $shop->balance();

            return [
                'id' => $shop->id, 'name' => $shop->name, 'phone' => $shop->phone,
                'charged' => Amount::format($balance['charged']), 'paid' => Amount::format($balance['paid']),
                'due' => Amount::format($balance['due']), 'owes' => bccomp($balance['due'], '0', 3) > 0,
            ];
        })->all();
    }

    public function add(): void
    {
        $this->authorize('generate-vouchers');
        $this->name = trim($this->name);
        $this->phone = trim($this->phone);
        $this->note = trim($this->note);
        $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('vouchers.voucher_shops', 'name')],
            'phone' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        Shop::query()->create(['name' => $this->name, 'phone' => $this->phone === '' ? null : $this->phone, 'note' => $this->note === '' ? null : $this->note]);

        $this->reset('name', 'phone', 'note');
        unset($this->shops);
        $this->dispatch('modal-close', name: 'new-shop');
        $this->toast(__('Shop added.'));
    }
};
