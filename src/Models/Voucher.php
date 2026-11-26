<?php

namespace Ispgrid\HotspotVouchers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One card. Its login is core's Hotspot customer account_id (username = code); whether it was used or expired is
 * read from that customer.
 *
 * @property int $id
 * @property int $batch_id
 * @property int $account_id
 * @property string $code
 * @property ?Carbon $sold_at
 */
class Voucher extends Model
{
    protected $connection = 'vouchers';

    protected $table = 'vouchers';

    protected $fillable = ['batch_id', 'account_id', 'code', 'sold_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sold_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
