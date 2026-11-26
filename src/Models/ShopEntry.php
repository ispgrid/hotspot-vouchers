<?php

namespace Ispgrid\HotspotVouchers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of a shop's ledger: a charge when a batch is handed to the shop, or a payment staff record.
 *
 * @property int $id
 * @property int $shop_id
 * @property string $kind
 * @property string $amount
 * @property ?int $batch_id
 * @property Carbon $entry_date
 * @property ?string $note
 * @property ?int $user_id
 */
class ShopEntry extends Model
{
    public const CHARGE = 'charge';

    public const PAYMENT = 'payment';

    protected $connection = 'vouchers';

    protected $table = 'voucher_shop_entries';

    protected $fillable = ['shop_id', 'kind', 'amount', 'batch_id', 'entry_date', 'note', 'user_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['entry_date' => 'date', 'amount' => 'decimal:3'];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
