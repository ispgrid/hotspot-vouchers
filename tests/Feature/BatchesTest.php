<?php

use App\Enums\AccountStatus;
use App\Enums\ServiceType;
use App\Models\Account;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Ispgrid\HotspotVouchers\Actions\GenerateBatch;
use Ispgrid\HotspotVouchers\Models\Batch;
use Ispgrid\HotspotVouchers\Models\Voucher;
use Ispgrid\HotspotVouchers\Support\Codes;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * A batch made the way the New batch form makes it.
 *
 * @param  array<string, mixed>  $input
 */
function voucherBatch(int $count = 3, array $input = []): Batch
{
    $plan = Plan::factory()->hours(3)->firstLogin()->create(['name' => 'Hotspot 3h', 'price' => 20]);

    return app(GenerateBatch::class)->handle([
        'plan_id' => $plan->id, 'count' => $count, 'card_price' => '20', 'login_style' => Batch::PIN,
        'code_length' => 8, 'prefix' => null, 'devices' => 1, 'note' => null, ...$input,
    ], auth()->id());
}

function staffWithVoucherBoxes(string ...$boxes): User
{
    $role = Role::create(['name' => 'Desk '.count($boxes).random_int(1, 99999), 'guard_name' => 'web']);
    $role->givePermissionTo($boxes);

    return User::factory()->role($role->name)->create();
}

it('makes a batch of Inactive Hotspot cards with codes nobody misreads', function () {
    $plan = Plan::factory()->hours(3)->firstLogin()->create(['name' => 'Hotspot 3h', 'price' => 20]);

    Livewire::test('vouchers::batches')
        ->set('plan_id', (string) $plan->id)
        ->assertSet('card_price', '20.00')
        ->set('count', '5')
        ->set('code_length', '6')
        ->set('prefix', 'ab')
        ->set('devices', '2')
        ->call('generate')
        ->assertHasNoErrors()
        ->assertRedirect(route('vouchers.batches.show', Batch::query()->sole()->id));

    $vouchers = Voucher::query()->get();
    $accounts = Account::query()->whereIn('id', $vouchers->pluck('account_id'))->get();

    expect($vouchers)->toHaveCount(5)
        ->and($vouchers->pluck('code')->unique())->toHaveCount(5)
        ->and($vouchers->pluck('code')->every(fn (string $code): bool => preg_match('/^AB['.Codes::ALPHABET.']{6}$/', $code) === 1))->toBeTrue()
        ->and($accounts)->toHaveCount(5)
        ->and($accounts->every(fn (Account $account): bool => $account->status_id === AccountStatus::Inactive && $account->service_type === ServiceType::Hotspot
            && $account->billing_free && $account->source === 'hotspot-vouchers' && $account->mac_type === 2 && $account->password === $account->username))->toBeTrue()
        ->and(Activity::query()->where('event', 'voucher-batch-created')->sole()->properties['cards'])->toBe(5);
});

it('gives username and password cards a separate password', function () {
    $batch = voucherBatch(2, ['login_style' => Batch::PASSWORD]);

    foreach (Voucher::query()->where('batch_id', $batch->id)->get() as $voucher) {
        $account = Account::query()->findOrFail($voucher->account_id);
        expect($account->username)->toBe($voucher->code)->and($account->password)->not->toBe($voucher->code)->toHaveLength(8);
    }
});

it('offers only plans whose validity starts at the first login', function () {
    $monthly = Plan::factory()->create(['name' => 'Home 30 days']);
    Plan::factory()->firstLogin()->create(['name' => 'Hotspot day']);

    Livewire::test('vouchers::batches')
        ->assertSee('Hotspot day')
        ->assertDontSee('Home 30 days')
        ->set('plan_id', (string) $monthly->id)
        ->call('generate')
        ->assertHasErrors(['plan_id']);

    expect(Batch::query()->count())->toBe(0);
});

it('refuses a batch it cannot make', function (string $field, string $value) {
    $plan = Plan::factory()->firstLogin()->create();

    Livewire::test('vouchers::batches')->set('plan_id', (string) $plan->id)->set($field, $value)->call('generate')->assertHasErrors([$field]);
})->with([
    'too many cards' => ['count', '201'],
    'no cards' => ['count', '0'],
    'short codes' => ['code_length', '5'],
    'a prefix with O' => ['prefix', 'BOX'],
    'a long prefix' => ['prefix', 'ABCDEFG'],
    'a price with letters' => ['card_price', '2O'],
]);

it('keeps vouchers out of the customers list unless picked', function () {
    voucherBatch(2);
    $code = Voucher::query()->first()->code;

    Livewire::test('pages::customers.index')->assertDontSee($code);
    Livewire::test('pages::customers.index')->set('source', 'hotspot-vouchers')->assertSee($code);
});

it('finds a card by its code', function () {
    $batch = voucherBatch(1);
    $code = Voucher::query()->sole()->code;

    Livewire::test('vouchers::batches')->set('code', strtolower($code))->assertSee($code)->assertSee(__('Batch :batch', ['batch' => $batch->id]));
    Livewire::test('vouchers::batches')->set('code', 'NOSUCH')->assertSee(__('No card has this code.'));
});

it('lets print-only staff see batches but not make them', function () {
    voucherBatch(1);
    $this->actingAs(staffWithVoucherBoxes('print-vouchers'));

    Livewire::test('vouchers::batches')->assertOk()->assertDontSee(__('New batch'))->call('generate')->assertForbidden();
    $this->get(route('vouchers.shops.index'))->assertForbidden();
});

it('is closed to staff without a voucher box, also once taken away', function () {
    $this->actingAs(staffWithVoucherBoxes('view-customers'));
    $this->get(route('vouchers.index'))->assertForbidden();

    $role = Role::create(['name' => 'Voucher desk', 'guard_name' => 'web']);
    $role->givePermissionTo('generate-vouchers');
    $staff = User::factory()->role('Voucher desk')->create();
    $this->actingAs($staff);
    $page = Livewire::test('vouchers::batches')->assertOk();

    $role->revokePermissionTo('generate-vouchers');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $staff->unsetRelation('roles')->unsetRelation('permissions');

    $page->set('code', 'X')->assertForbidden();
});

it('removes the customer when its card row cannot be saved', function () {
    $plan = Plan::factory()->firstLogin()->create();
    DB::connection('vouchers')->statement('ALTER TABLE vouchers ADD CONSTRAINT vouchers_fail CHECK (batch_id < 0)');

    try {
        expect(fn () => app(GenerateBatch::class)->handle([
            'plan_id' => $plan->id, 'count' => 1, 'card_price' => '20', 'login_style' => Batch::PIN,
            'code_length' => 8, 'prefix' => null, 'devices' => 1, 'note' => null,
        ], null))->toThrow(Exception::class);
    } finally {
        DB::connection('vouchers')->statement('ALTER TABLE vouchers DROP CONSTRAINT vouchers_fail');
    }

    expect(Account::query()->where('source', 'hotspot-vouchers')->count())->toBe(0);
});
