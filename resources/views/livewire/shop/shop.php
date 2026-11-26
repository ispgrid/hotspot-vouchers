<?php

use App\Livewire\Concerns\AuthorizesEveryRequest;
use App\Livewire\Concerns\ShowsToasts;
use Illuminate\Validation\Rule;
use Ispgrid\HotspotVouchers\Models\Batch;
use Ispgrid\HotspotVouchers\Models\Shop;
use Ispgrid\HotspotVouchers\Models\ShopEntry;
use Ispgrid\HotspotVouchers\Support\Amount;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * One shop: its details, its ledger (charges for batches, payments) and the batches it bought.
 */
new #[Title('Voucher shop')] class extends Component
{
    use AuthorizesEveryRequest, ShowsToasts;

    #[Locked]
    public int $shopId;

    public string $name = '';

    public string $phone = '';

    public string $note = '';

    public string $amount = '';

    public string $paid_on = '';

    public string $payment_note = '';

    public function mount(int $shop): void
    {
        $this->authorizeAccess();
        $model = Shop::query()->findOrFail($shop);
        $this->shopId = $model->id;
        $this->name = $model->name;
        $this->phone = (string) $model->phone;
        $this->note = (string) $model->note;
        $this->paid_on = now()->toDateString();
    }

    protected function authorizeAccess(): void
    {
        $this->authorize('generate-vouchers');
    }

    #[Computed]
    public function shop(): Shop
    {
        return Shop::query()->findOrFail($this->shopId);
    }

    /**
     * @return array{charged: string, paid: string, due: string}
     */
    #[Computed]
    public function balance(): array
    {
        return array_map(Amount::format(...), $this->shop->balance());
    }

    /**
     * @return list<array{date: string, kind: string, amount: string, batch: ?int, note: ?string}>
     */
    #[Computed]
    public function entries(): array
    {
        return ShopEntry::query()->where('shop_id', $this->shopId)->orderByDesc('entry_date')->orderByDesc('id')->get()
            ->map(fn (ShopEntry $entry): array => [
                'date' => $entry->entry_date->format('Y-m-d'),
                'kind' => $entry->kind === ShopEntry::CHARGE ? __('Batch charged') : __('Payment'),
                'amount' => Amount::format((string) $entry->amount),
                'batch' => $entry->batch_id,
                'note' => $entry->note,
            ])->all();
    }

    /**
     * @return list<array{id: int, plan: string, cards: int, handed_out: ?string}>
     */
    #[Computed]
    public function batches(): array
    {
        return Batch::query()->where('shop_id', $this->shopId)->withCount('vouchers')->latest('id')->get()
            ->map(fn (Batch $batch): array => ['id' => $batch->id, 'plan' => $batch->plan_name, 'cards' => $batch->vouchers_count, 'handed_out' => $batch->handed_out_at?->format('Y-m-d')])
            ->all();
    }

    public function save(): void
    {
        $this->authorize('generate-vouchers');
        $this->name = trim($this->name);
        $this->phone = trim($this->phone);
        $this->note = trim($this->note);
        $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('vouchers.voucher_shops', 'name')->ignore($this->shopId)],
            'phone' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->shop->update(['name' => $this->name, 'phone' => $this->phone === '' ? null : $this->phone, 'note' => $this->note === '' ? null : $this->note]);
        unset($this->shop);
        $this->toast(__('Shop saved.'));
    }

    public function recordPayment(): void
    {
        $this->authorize('generate-vouchers');
        $this->payment_note = trim($this->payment_note);
        $this->validate([
            'amount' => ['required', 'regex:'.Amount::pattern(), 'not_regex:/^0+(\.0+)?$/'],
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'payment_note' => ['nullable', 'string', 'max:255'],
        ], ['amount.not_regex' => __('Enter an amount above zero.')]);

        ShopEntry::query()->create([
            'shop_id' => $this->shopId, 'kind' => ShopEntry::PAYMENT, 'amount' => $this->amount,
            'entry_date' => $this->paid_on, 'note' => $this->payment_note === '' ? null : $this->payment_note, 'user_id' => auth()->id(),
        ]);

        activity()->event('voucher-shop-payment')
            ->withProperties(['shop' => $this->shop->name, 'amount' => $this->amount, 'date' => $this->paid_on])
            ->log('voucher-shop-payment');

        $this->reset('amount', 'payment_note');
        unset($this->balance, $this->entries);
        $this->dispatch('modal-close', name: 'payment');
        $this->toast(__('Payment recorded.'));
    }
};
