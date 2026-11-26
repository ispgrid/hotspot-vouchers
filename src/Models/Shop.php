<?php

namespace Ispgrid\HotspotVouchers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shop that buys voucher batches. Staff keep the list; shops never log in.
 *
 * @property int $id
 * @property string $name
 * @property ?string $phone
 * @property ?string $note
 */
class Shop extends Model
{
    protected $connection = 'vouchers';

    protected $table = 'voucher_shops';

    protected $fillable = ['name', 'phone', 'note'];

    /**
     * @return HasMany<ShopEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(ShopEntry::class);
    }

    /**
     * @return HasMany<Batch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    /**
     * Charged, paid and still due, as strings with three decimals.
     *
     * @return array{charged: string, paid: string, due: string}
     */
    public function balance(): array
    {
        $sums = $this->entries()->selectRaw("COALESCE(SUM(CASE WHEN kind = 'charge' THEN amount END), 0) AS charged, COALESCE(SUM(CASE WHEN kind = 'payment' THEN amount END), 0) AS paid")->toBase()->first();
        $charged = is_numeric($sums->charged ?? null) ? (string) $sums->charged : '0';
        $paid = is_numeric($sums->paid ?? null) ? (string) $sums->paid : '0';

        return ['charged' => $charged, 'paid' => $paid, 'due' => bcsub($charged, $paid, 3)];
    }
}
