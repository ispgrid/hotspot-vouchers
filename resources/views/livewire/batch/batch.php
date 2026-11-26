<?php

use App\Livewire\Concerns\AuthorizesEveryRequest;
use App\Livewire\Concerns\ShowsToasts;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;
use Ispgrid\HotspotVouchers\Actions\DeleteBatch;
use Ispgrid\HotspotVouchers\Actions\HandOutBatch;
use Ispgrid\HotspotVouchers\Actions\SwitchBatch;
use Ispgrid\HotspotVouchers\Models\Batch;
use Ispgrid\HotspotVouchers\Models\Shop;
use Ispgrid\HotspotVouchers\Models\Voucher;
use Ispgrid\HotspotVouchers\Support\Amount;
use Ispgrid\HotspotVouchers\Support\CannotChangeBatch;
use Ispgrid\HotspotVouchers\Support\Cards;
use Ispgrid\HotspotVouchers\Support\CardStatus;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * One batch: its cards and their status, and what staff do with it (hand out, switch off and on, mark cards sold,
 * print, export, delete).
 */
new #[Title('Voucher batch')] class extends Component
{
    use AuthorizesEveryRequest, ShowsToasts;

    #[Locked]
    public int $batchId;

    #[Url]
    public string $status = '';

    /**
     * '' for counter sale, or a shop's id.
     */
    public string $shop_id = '';

    public string $shop_price = '';

    public function mount(int $batch): void
    {
        $this->authorizeAccess();
        $this->batchId = Batch::query()->findOrFail($batch)->id;
        $this->shop_price = Amount::plain((string) $this->batch->card_price);
    }

    protected function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->canAny(['generate-vouchers', 'print-vouchers']), 403);
    }

    #[Computed]
    public function batch(): Batch
    {
        return Batch::query()->with('shop')->findOrFail($this->batchId);
    }

    /**
     * @return list<array{id: int, code: string, password: ?string, status: string, account_id: int, sold_at: ?string, first_login: ?string, expiry: ?string}>
     */
    #[Computed]
    public function allCards(): array
    {
        return Cards::of($this->batch);
    }

    /**
     * The cards with the status filter applied.
     *
     * @return list<array{id: int, code: string, password: ?string, status: string, account_id: int, sold_at: ?string, first_login: ?string, expiry: ?string}>
     */
    #[Computed]
    public function cards(): array
    {
        return array_values(array_filter($this->allCards, fn (array $card): bool => $this->status === '' || $card['status'] === $this->status));
    }

    /**
     * Cards per status, for the summary.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        return array_count_values(array_column($this->allCards, 'status'));
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function statuses(): array
    {
        $choices = ['' => __('All cards')];

        foreach (CardStatus::KEYS as $key) {
            $choices[$key] = CardStatus::label($key);
        }

        return $choices;
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function shops(): array
    {
        return ['' => __('Counter sale')] + Shop::query()->orderBy('name')->pluck('name', 'id')->mapWithKeys(fn (string $name, int $id): array => [(string) $id => $name])->all();
    }

    /**
     * What the batch brought in: the shop's charge, or the card price of each card sold over the counter.
     */
    #[Computed]
    public function takings(): string
    {
        $batch = $this->batch;
        $sold = count(array_filter($this->allCards, fn (array $card): bool => $card['sold_at'] !== null));

        return $batch->shop_id !== null
            ? Amount::format(bcmul((string) $batch->shop_price, (string) count($this->allCards), 3))
            : Amount::format(bcmul((string) $batch->card_price, (string) $sold, 3));
    }

    #[Computed]
    public function validity(): string
    {
        return Cards::validity($this->batch);
    }

    /**
     * Only a batch none of whose cards was used can go.
     */
    #[Computed]
    public function canDelete(): bool
    {
        return auth()->user()?->can('delete-voucher-batches') === true && ($this->counts['used'] ?? 0) === 0 && ($this->counts['expired'] ?? 0) === 0;
    }

    public function money(string $amount): string
    {
        return Amount::format($amount);
    }

    public function statusLabel(string $status): string
    {
        return CardStatus::label($status);
    }

    public function statusColor(string $status): string
    {
        return CardStatus::color($status);
    }

    public function handOut(HandOutBatch $handOut): void
    {
        $this->authorize('generate-vouchers');
        $this->validate([
            'shop_id' => ['nullable', Rule::in(array_keys($this->shops))],
            ...($this->shop_id === '' ? [] : ['shop_price' => ['required', 'regex:'.Amount::pattern()]]),
        ]);
        set_time_limit(300);

        try {
            $handOut->handle($this->batchId, $this->shop_id === '' ? null : (int) $this->shop_id, $this->shop_id === '' ? null : $this->shop_price, auth()->id());
        } catch (CannotChangeBatch $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->refresh();
        $this->dispatch('modal-close', name: 'hand-out');
        $this->toast(__('Batch handed out. Its cards work now.'));
    }

    public function switchOff(SwitchBatch $switch): void
    {
        $this->switchTo($switch, false, __('Batch switched off. Its cards no longer log in.'));
    }

    public function switchOn(SwitchBatch $switch): void
    {
        $this->switchTo($switch, true, __('Batch switched on. Its cards log in again.'));
    }

    private function switchTo(SwitchBatch $switch, bool $on, string $done): void
    {
        $this->authorize('generate-vouchers');
        set_time_limit(300);

        try {
            $switch->handle($this->batchId, $on);
        } catch (CannotChangeBatch $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->refresh();
        $this->toast($done);
    }

    /**
     * A card of a counter-sale batch was sold (or the mark was wrong).
     */
    public function markSold(int $voucherId, bool $sold = true): void
    {
        $this->authorize('generate-vouchers');
        $batch = $this->batch;

        if ($batch->shop_id !== null || $batch->handed_out_at === null) {
            return;
        }

        Voucher::query()->where('batch_id', $batch->id)->whereKey($voucherId)->update(['sold_at' => $sold ? now() : null]);
        $this->refresh();
    }

    public function delete(DeleteBatch $delete): void
    {
        $this->authorize('delete-voucher-batches');
        set_time_limit(300);

        try {
            $delete->handle($this->batchId);
        } catch (CannotChangeBatch $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        } catch (ModelNotFoundException) {
            // Deleted meanwhile: the list is where staff go next anyway.
        }

        session()->flash('toast', __('Batch deleted, with its cards.'));
        $this->redirectRoute('vouchers.index', navigate: true);
    }

    private function refresh(): void
    {
        unset($this->batch, $this->allCards, $this->cards, $this->counts, $this->takings, $this->canDelete);
    }
};
