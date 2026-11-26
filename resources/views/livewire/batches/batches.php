<?php

use App\Livewire\Concerns\AuthorizesEveryRequest;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\Account;
use App\Models\Plan;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Ispgrid\HotspotVouchers\Actions\GenerateBatch;
use Ispgrid\HotspotVouchers\Models\Batch;
use Ispgrid\HotspotVouchers\Models\Voucher;
use Ispgrid\HotspotVouchers\Support\Amount;
use Ispgrid\HotspotVouchers\Support\CardStatus;
use Ispgrid\HotspotVouchers\Support\Codes;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Voucher batches: the list, a new batch, and finding one card by its code.
 */
new #[Title('Vouchers')] class extends Component
{
    use AuthorizesEveryRequest, ShowsToasts, WithPagination;

    #[Url]
    public string $code = '';

    public string $plan_id = '';

    public string $count = '50';

    public string $card_price = '';

    public string $login_style = Batch::PIN;

    public string $code_length = '8';

    public string $prefix = '';

    public string $devices = '1';

    public string $note = '';

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->canAny(['generate-vouchers', 'print-vouchers']), 403);
    }

    /**
     * Hotspot plans whose validity starts at the first login: a card waits unused for as long as it takes to sell.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function plans(): array
    {
        return Plan::query()->where('hotspot_starts_at_first_login', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function loginStyles(): array
    {
        return [Batch::PIN => __('One PIN (username and password the same)'), Batch::PASSWORD => __('Username and password')];
    }

    /**
     * A new plan choice fills in its price.
     */
    public function updatedPlanId(): void
    {
        $plan = ctype_digit($this->plan_id) ? Plan::query()->find((int) $this->plan_id) : null;

        if ($plan !== null) {
            $this->card_price = Amount::plain((string) $plan->price);
        }
    }

    #[Computed]
    public function batches(): LengthAwarePaginator
    {
        return Batch::query()->with('shop')->withCount('vouchers')->withCount(['vouchers as sold_count' => fn ($query) => $query->whereNotNull('sold_at')])
            ->latest('id')->paginate(25);
    }

    /**
     * Used cards per batch on this page (their customer logged in once).
     *
     * @return array<int, int>
     */
    #[Computed]
    public function usedCounts(): array
    {
        $vouchers = Voucher::query()->whereIn('batch_id', collect($this->batches->items())->pluck('id'))->get(['batch_id', 'account_id']);
        $used = Account::withTrashed()->whereIn('id', $vouchers->pluck('account_id'))->whereNotNull('hotspot_first_login_at')->pluck('id')->flip();

        return $vouchers->filter(fn (Voucher $voucher): bool => $used->has($voucher->account_id))->countBy('batch_id')->all();
    }

    /**
     * The card whose code was typed, with its batch and status.
     *
     * @return ?array{code: string, batch: int, status: string, label: string, color: string, customer: ?int}
     */
    #[Computed]
    public function found(): ?array
    {
        $code = strtoupper(trim($this->code));

        if ($code === '') {
            return null;
        }

        $voucher = Voucher::query()->with('batch')->where('code', $code)->first();

        if ($voucher === null) {
            return null;
        }

        $account = Account::withTrashed()->find($voucher->account_id);
        $status = CardStatus::of($voucher, $voucher->batch, $account);

        return [
            'code' => $voucher->code, 'batch' => $voucher->batch_id, 'status' => $status, 'label' => CardStatus::label($status),
            'color' => CardStatus::color($status), 'customer' => $account !== null && auth()->user()?->can('view', $account) ? $account->id : null,
        ];
    }

    public function money(string $amount): string
    {
        return Amount::format($amount);
    }

    public function generate(GenerateBatch $generate): void
    {
        $this->authorize('generate-vouchers');
        $this->prefix = strtoupper(trim($this->prefix));
        $this->note = trim($this->note);
        $this->validate([
            'plan_id' => ['required', Rule::in(array_map('strval', array_keys($this->plans)))],
            'count' => ['required', 'integer', 'min:1', 'max:'.GenerateBatch::MAX_CARDS],
            'card_price' => ['required', 'regex:'.Amount::pattern()],
            'login_style' => ['required', Rule::in([Batch::PIN, Batch::PASSWORD])],
            'code_length' => ['required', 'integer', 'min:'.Codes::MIN_LENGTH, 'max:'.Codes::MAX_LENGTH],
            'prefix' => ['nullable', 'regex:/^['.Codes::ALPHABET.']{1,6}$/'],
            'devices' => ['required', 'integer', 'min:1', 'max:10'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'plan_id.required' => __('Choose a plan whose validity starts at the first login.'),
            'prefix.regex' => __('Up to 6 capital letters and digits, without 0, O, 1, I and L.'),
        ]);

        // Up to 200 customers in one request.
        set_time_limit(300);

        $batch = $generate->handle([
            'plan_id' => (int) $this->plan_id,
            'count' => (int) $this->count,
            'card_price' => $this->card_price,
            'login_style' => $this->login_style,
            'code_length' => (int) $this->code_length,
            'prefix' => $this->prefix === '' ? null : $this->prefix,
            'devices' => (int) $this->devices,
            'note' => $this->note === '' ? null : $this->note,
        ], auth()->id());

        session()->flash('toast', __('Batch made. Hand it out to make its cards work.'));
        $this->redirectRoute('vouchers.batches.show', ['batch' => $batch->id], navigate: true);
    }
};
