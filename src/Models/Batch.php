<?php

namespace Ispgrid\HotspotVouchers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A batch of voucher cards on one first-login Hotspot plan. New cards are Inactive; handing the batch out (to a
 * shop, or for counter sale) activates them; it can be switched off and on again.
 *
 * @property int $id
 * @property int $plan_id
 * @property string $plan_name
 * @property string $card_price
 * @property string $login_style
 * @property int $code_length
 * @property ?string $prefix
 * @property int $devices
 * @property ?string $note
 * @property ?int $shop_id
 * @property ?string $shop_price
 * @property ?Carbon $handed_out_at
 * @property ?Carbon $switched_off_at
 * @property ?int $created_by
 * @property Carbon $created_at
 * @property-read Shop $shop
 */
class Batch extends Model
{
    /**
     * The card's username is also its password (one code to type).
     */
    public const PIN = 'pin';

    /**
     * A username and a separate password.
     */
    public const PASSWORD = 'password';

    protected $connection = 'vouchers';

    protected $table = 'voucher_batches';

    protected $fillable = ['plan_id', 'plan_name', 'card_price', 'login_style', 'code_length', 'prefix', 'devices', 'note',
        'shop_id', 'shop_price', 'handed_out_at', 'switched_off_at', 'created_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['handed_out_at' => 'datetime', 'switched_off_at' => 'datetime', 'card_price' => 'decimal:3', 'shop_price' => 'decimal:3'];
    }

    /**
     * @return HasMany<Voucher, $this>
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * new (cards do not work yet), on, or off.
     */
    public function state(): string
    {
        return match (true) {
            $this->handed_out_at === null => 'new',
            $this->switched_off_at !== null => 'off',
            default => 'on',
        };
    }

    public function stateLabel(): string
    {
        return match ($this->state()) {
            'new' => __('Not handed out'),
            'off' => __('Switched off'),
            default => __('On'),
        };
    }

    public function stateColor(): string
    {
        return match ($this->state()) {
            'new' => 'zinc',
            'off' => 'red',
            default => 'green',
        };
    }

    /**
     * Where the cards went: the shop's name, "Counter sale", or nothing while not handed out.
     */
    public function soldThrough(): ?string
    {
        if ($this->handed_out_at === null) {
            return null;
        }

        return $this->shop_id === null ? __('Counter sale') : $this->shop->name;
    }
}
