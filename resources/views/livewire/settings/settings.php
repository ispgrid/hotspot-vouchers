<?php

use App\Livewire\Concerns\AuthorizesEveryRequest;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ispgrid\HotspotVouchers\Support\CardLayouts;
use Ispgrid\HotspotVouchers\Support\CardSettings;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The add-on's section on System, then Add-ons: the card design (name, logo, footer, band colour, which fields
 * show, default layout) and the Hotspot login address the QR code opens. It applies to every batch.
 */
new class extends Component
{
    use AuthorizesEveryRequest, ShowsToasts, WithFileUploads;

    public string $card_title = '';

    public string $login_url = '';

    /** @var TemporaryUploadedFile|null */
    public $logo = null;

    public string $footer_1 = '';

    public string $footer_2 = '';

    public string $band_colour = '';

    public bool $show_price = true;

    public bool $show_validity = true;

    public bool $show_plan = true;

    public bool $show_qr = true;

    public string $layout = CardLayouts::DEFAULT;

    public function mount(): void
    {
        $this->authorizeAccess();
        $card = CardSettings::current();
        $this->card_title = (string) AppSetting::valueOf('vouchers.card_title');
        $this->login_url = (string) AppSetting::valueOf('vouchers.login_url');
        $this->footer_1 = (string) AppSetting::valueOf('vouchers.footer_1');
        $this->footer_2 = (string) AppSetting::valueOf('vouchers.footer_2');
        $this->band_colour = (string) $card->band;
        $this->show_price = $card->showPrice;
        $this->show_validity = $card->showValidity;
        $this->show_plan = $card->showPlan;
        $this->show_qr = $card->showQr;
        $this->layout = app(CardLayouts::class)->has($card->layout) ? $card->layout : CardLayouts::DEFAULT;
    }

    protected function authorizeAccess(): void
    {
        $this->authorize('generate-vouchers');
    }

    public function save(): void
    {
        $this->authorize('generate-vouchers');

        foreach (['card_title', 'login_url', 'footer_1', 'footer_2', 'band_colour'] as $field) {
            $this->{$field} = trim($this->{$field});
        }

        $this->validate([
            'card_title' => ['nullable', 'string', 'max:40'],
            'login_url' => ['nullable', 'string', 'max:200', 'url:http,https'],
            'logo' => ['nullable', 'file', 'mimetypes:image/png,image/jpeg', 'max:1024', function (string $attribute, mixed $value, Closure $fail): void {
                // mimetypes: trusts the file name under tests; this reads the bytes.
                if ($value instanceof TemporaryUploadedFile && ! in_array(self::typeOf((string) $value->get()), ['image/png', 'image/jpeg'], true)) {
                    $fail(__('The logo must be a PNG or JPG image.'));
                }
            }],
            'footer_1' => ['nullable', 'string', 'max:40'],
            'footer_2' => ['nullable', 'string', 'max:40'],
            'band_colour' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'layout' => ['required', 'string', 'in:'.implode(',', array_keys(app(CardLayouts::class)->labels()))],
        ], [
            'logo.mimetypes' => __('The logo must be a PNG or JPG image.'),
            'logo.max' => __('The logo must be 1 MB or smaller.'),
            'band_colour.regex' => __('Enter a colour like #166534, or leave it empty.'),
        ]);

        $old = AppSetting::valueOf('vouchers.logo');
        $logo = null;

        if ($this->logo instanceof TemporaryUploadedFile) {
            $extension = self::typeOf((string) $this->logo->get()) === 'image/png' ? 'png' : 'jpg';
            $logo = $this->logo->storeAs('vouchers', Str::random(40).'.'.$extension, 'addons');
        }

        AppSetting::put('vouchers.card_title', $this->card_title === '' ? null : $this->card_title);
        AppSetting::put('vouchers.login_url', $this->login_url === '' ? null : $this->login_url);
        AppSetting::put('vouchers.footer_1', $this->footer_1 === '' ? null : $this->footer_1);
        AppSetting::put('vouchers.footer_2', $this->footer_2 === '' ? null : $this->footer_2);
        AppSetting::put('vouchers.band_colour', $this->band_colour === '' ? null : strtolower($this->band_colour));
        AppSetting::put('vouchers.show_price', $this->show_price ? '1' : '0');
        AppSetting::put('vouchers.show_validity', $this->show_validity ? '1' : '0');
        AppSetting::put('vouchers.show_plan', $this->show_plan ? '1' : '0');
        AppSetting::put('vouchers.show_qr', $this->show_qr ? '1' : '0');
        AppSetting::put('vouchers.layout', $this->layout);

        if (is_string($logo)) {
            AppSetting::put('vouchers.logo', $logo);

            if ($old !== null && $old !== $logo) {
                Storage::disk('addons')->delete($old);
            }
        }

        if ($this->logo instanceof TemporaryUploadedFile) {
            $this->logo->delete();
        }

        $this->reset('logo');
        unset($this->logoPreview);
        $this->toast(__('Settings saved.'));
    }

    /**
     * Cards print without a logo from now on; the file is deleted.
     */
    public function removeLogo(): void
    {
        $this->authorize('generate-vouchers');
        $path = AppSetting::valueOf('vouchers.logo');
        AppSetting::put('vouchers.logo', null);

        if ($path !== null) {
            Storage::disk('addons')->delete($path);
        }

        unset($this->logoPreview);
        $this->toast(__('Logo removed.'));
    }

    #[Computed]
    public function logoPreview(): ?string
    {
        return CardSettings::current()->logoDataUri();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function layouts(): array
    {
        return app(CardLayouts::class)->labels();
    }

    private static function typeOf(string $bytes): string
    {
        return (string) (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    }
};
