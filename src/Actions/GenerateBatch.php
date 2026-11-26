<?php

namespace Ispgrid\HotspotVouchers\Actions;

use App\Actions\Customers\CreateAccount;
use App\Actions\Customers\DeleteAccount;
use App\Enums\ServiceType;
use App\Models\Plan;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;
use Ispgrid\HotspotVouchers\Models\Batch;
use Ispgrid\HotspotVouchers\Models\Voucher;
use Ispgrid\HotspotVouchers\Support\Codes;
use RuntimeException;
use Throwable;

/**
 * Makes a batch: one Hotspot customer per card, Inactive and without a plan (core's CreateAccount, source
 * "hotspot-vouchers", billing Free so a card never gets an invoice), and its row in the vouchers database. The
 * cards start working when the batch is handed out.
 */
final class GenerateBatch
{
    public const SOURCE = 'hotspot-vouchers';

    public const MAX_CARDS = 200;

    /**
     * Tries for a code no customer has before giving up (a short code with most codes taken).
     */
    private const TRIES = 20;

    public function __construct(private CreateAccount $createAccount, private DeleteAccount $deleteAccount) {}

    /**
     * @param  array{plan_id: int, count: int, card_price: string, login_style: string, code_length: int, prefix: ?string, devices: int, note: ?string}  $input
     *
     * @throws InvalidArgumentException when the plan does not start at the first login
     */
    public function handle(array $input, ?int $userId): Batch
    {
        $plan = Plan::query()->findOrFail($input['plan_id']);

        if (! $plan->hotspot_starts_at_first_login) {
            throw new InvalidArgumentException('A voucher plan starts at the first login.');
        }

        $batch = Batch::query()->create([
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'card_price' => $input['card_price'],
            'login_style' => $input['login_style'],
            'code_length' => $input['code_length'],
            'prefix' => $input['prefix'],
            'devices' => $input['devices'],
            'note' => $input['note'],
            'created_by' => $userId,
        ]);

        for ($i = 0; $i < $input['count']; $i++) {
            $this->card($batch);
        }

        activity()->event('voucher-batch-created')
            ->withProperties(['batch' => $batch->id, 'cards' => $input['count'], 'plan' => $plan->name])
            ->log('voucher-batch-created');

        return $batch;
    }

    private function card(Batch $batch): void
    {
        for ($try = 0; $try < self::TRIES; $try++) {
            $code = ($batch->prefix ?? '').Codes::make($batch->code_length);

            if (Voucher::query()->where('code', $code)->exists()) {
                continue;
            }

            try {
                $account = $this->createAccount->handle([
                    'username' => $code,
                    'password' => $batch->login_style === Batch::PIN ? $code : Codes::make($batch->code_length),
                    'fullname' => 'Voucher '.$code,
                    'service_type' => ServiceType::Hotspot,
                    'mac_type' => $batch->devices,
                    'billing_free' => true,
                    'notifications_off' => true,
                    'remarks' => 'Voucher batch '.$batch->id,
                ], self::SOURCE);
            } catch (UniqueConstraintViolationException) {
                continue;
            }

            try {
                Voucher::query()->create(['batch_id' => $batch->id, 'account_id' => $account->id, 'code' => $code]);
            } catch (Throwable $exception) {
                // The card row failed after core made the customer: remove the customer, so no login exists
                // without its card.
                $this->deleteAccount->handle($account->id);

                throw $exception;
            }

            return;
        }

        throw new RuntimeException('No free voucher code found. Use longer codes.');
    }
}
