<?php

namespace ArtflowStudio\FileUploader\Tests\Unit;

use ArtflowStudio\FileUploader\Support\Options;
use ArtflowStudio\FileUploader\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class OptionsTest extends TestCase
{
    #[Test]
    public function the_string_false_is_false(): void
    {
        // The whole reason auto-upload never worked: Blade hands props over as
        // strings, and 'false' is truthy in PHP (AUDIT.md, CRIT-01).
        $this->assertFalse(Options::bool('false', true));
        $this->assertFalse(Options::bool('0', true));
        $this->assertFalse(Options::bool('off', true));
        $this->assertFalse(Options::bool(false, true));
    }

    #[Test]
    public function a_bare_attribute_is_true(): void
    {
        // <x-af-uploader multiple /> gives Blade the string "multiple".
        $this->assertTrue(Options::bool('multiple', false));
        $this->assertTrue(Options::bool('true', false));
        $this->assertTrue(Options::bool(true, false));
    }

    #[Test]
    public function an_absent_value_falls_back_to_the_default(): void
    {
        $this->assertTrue(Options::bool(null, true));
        $this->assertFalse(Options::bool(null, false));
        $this->assertTrue(Options::bool('', true));
    }

    #[Test]
    public function auto_upload_false_survives_the_full_pipeline(): void
    {
        $config = Options::build('af-1', 'photo', ['autoUpload' => 'false']);

        $this->assertFalse($config['autoUpload']);
    }

    #[Test]
    #[DataProvider('ratios')]
    public function ratios_are_normalised(mixed $input, ?float $expected): void
    {
        $actual = Options::ratio($input);

        if ($expected === null) {
            $this->assertNull($actual);

            return;
        }

        $this->assertEqualsWithDelta($expected, $actual, 0.0001);
    }

    public static function ratios(): array
    {
        return [
            'slash' => ['16/9', 16 / 9],
            'colon' => ['4:3', 4 / 3],
            'square string' => ['1', 1.0],
            'decimal' => [1.5, 1.5],
            'free keyword' => ['free', 0.0],
            'zero' => ['0', 0.0],
            'null' => [null, null],
            'nonsense' => ['wide', null],
            'zero denominator' => ['16/0', null],
        ];
    }

    #[Test]
    #[DataProvider('sizes')]
    public function human_sizes_become_bytes(mixed $input, ?int $expected): void
    {
        $this->assertSame($expected, Options::bytes($input));
    }

    public static function sizes(): array
    {
        return [
            'kilobytes' => ['500KB', 512000],
            'lowercase' => ['500kb', 512000],
            'spaced' => ['1.5 mb', 1572864],
            'megabytes' => ['2MB', 2097152],
            'bare number is bytes' => ['4096', 4096],
            'null' => [null, null],
            'empty' => ['', null],
            'nonsense' => ['big', null],
            'zero' => ['0KB', null],
        ];
    }

    #[Test]
    public function target_size_reaches_the_image_options(): void
    {
        // It used to be emitted as data-af-target-size and read as
        // data-af-max-size, so it never arrived (AUDIT.md, HIGH-05).
        $config = Options::build('af-1', 'photo', ['targetSize' => '500KB']);

        $this->assertSame(512000, $config['image']['targetSize']);
    }

    #[Test]
    public function convert_is_honoured_and_drives_the_editor_format(): void
    {
        $config = Options::build('af-1', 'photo', ['convert' => 'jpg', 'cropper' => true]);

        $this->assertSame('jpeg', $config['image']['convert']);
        $this->assertSame('image/jpeg', $config['editor']['format']);
    }

    #[Test]
    public function an_unsupported_convert_target_is_dropped(): void
    {
        $config = Options::build('af-1', 'photo', ['convert' => 'tiff']);

        $this->assertNull($config['image']['convert']);
    }

    #[Test]
    public function lossless_pins_quality_to_one(): void
    {
        $config = Options::build('af-1', 'photo', ['lossless' => true, 'quality' => 0.3]);

        $this->assertSame(1.0, $config['image']['quality']);
    }

    #[Test]
    public function quality_accepts_percentages(): void
    {
        // Anything above 1 is read as a percentage, so both spellings work.
        $this->assertSame(0.8, Options::build('af-1', null, ['quality' => 80])['image']['quality']);
        $this->assertSame(0.8, Options::build('af-1', null, ['quality' => 0.8])['image']['quality']);
    }

    #[Test]
    public function quality_is_clamped_to_a_usable_range(): void
    {
        $this->assertSame(1.0, Options::build('af-1', null, ['quality' => 150])['image']['quality']);
        $this->assertSame(0.05, Options::build('af-1', null, ['quality' => 0])['image']['quality']);
    }

    #[Test]
    public function a_circular_mask_forces_a_square_ratio(): void
    {
        $config = Options::build('af-1', 'photo', ['isCircle' => true, 'ratio' => '16/9']);

        $this->assertSame(1.0, $config['editor']['ratio']);
        $this->assertTrue($config['editor']['lockRatio']);
    }

    #[Test]
    #[DataProvider('percentages')]
    public function compression_percentages_are_normalised(mixed $input, ?int $expected): void
    {
        $this->assertSame($expected, Options::percent($input));
    }

    public static function percentages(): array
    {
        return [
            'with sign' => ['40%', 40],
            'without sign' => ['40', 40],
            'spaced' => [' 60 % ', 60],
            'fraction' => [0.6, 60],
            'integer' => [75, 75],
            'rounds' => ['33.4%', 33],
            'null' => [null, null],
            'empty' => ['', null],
            'nonsense' => ['half', null],
            'no compression at 100' => ['100%', null],
            'over 100' => ['150%', null],
            'zero' => ['0%', null],
        ];
    }

    #[Test]
    public function both_compression_ceilings_survive_the_pipeline(): void
    {
        // Absolute and relative budgets are independent; the browser applies
        // whichever turns out tighter for the file actually picked.
        $config = Options::build('af-1', 'photo', [
            'targetSize' => '120KB',
            'compress' => '50%',
        ]);

        $this->assertSame(122880, $config['image']['targetSize']);
        $this->assertSame(50, $config['image']['compress']);
    }

    #[Test]
    public function either_compression_ceiling_works_alone(): void
    {
        $absolute = Options::build('af-1', 'photo', ['targetSize' => '500KB'])['image'];
        $this->assertSame(512000, $absolute['targetSize']);
        $this->assertNull($absolute['compress']);

        $relative = Options::build('af-1', 'photo', ['compress' => '40%'])['image'];
        $this->assertNull($relative['targetSize']);
        $this->assertSame(40, $relative['compress']);
    }

    #[Test]
    public function the_compression_report_is_opt_in(): void
    {
        $this->assertFalse(Options::build('af-1', 'photo', [])['showSavings']);
        $this->assertTrue(Options::build('af-1', 'photo', ['showSavings' => true])['showSavings']);

        // <x-af-uploader show-savings /> arrives as the bare attribute name.
        $this->assertTrue(Options::build('af-1', 'photo', ['showSavings' => 'show-savings'])['showSavings']);
    }

    #[Test]
    public function the_compression_report_can_be_switched_on_globally(): void
    {
        config()->set('af-uploader.defaults.show_savings', true);

        $this->assertTrue(Options::build('af-1', 'photo', [])['showSavings']);
        $this->assertFalse(Options::build('af-1', 'photo', ['showSavings' => 'false'])['showSavings']);
    }

    #[Test]
    public function the_theme_mode_defaults_to_following_the_host(): void
    {
        $this->assertSame('auto', Options::build('af-1', 'photo', [])['theme']);
    }

    #[Test]
    public function the_theme_mode_is_configurable_and_overridable(): void
    {
        config()->set('af-uploader.theme', 'dark');
        $this->assertSame('dark', Options::build('af-1', 'photo', [])['theme']);

        $this->assertSame('light', Options::build('af-1', 'photo', ['theme' => 'light'])['theme']);
    }

    #[Test]
    public function an_unrecognised_theme_mode_falls_back_to_auto(): void
    {
        $this->assertSame('auto', Options::theme('midnight'));
        $this->assertSame('auto', Options::theme(null));
        $this->assertSame('system', Options::theme('SYSTEM'));
    }

    #[Test]
    public function props_override_config_defaults(): void
    {
        config()->set('af-uploader.defaults.max_size', 25);

        $this->assertSame(25.0, Options::build('af-1', null, [])['maxSize']);
        $this->assertSame(4.0, Options::build('af-1', null, ['maxSize' => 4])['maxSize']);
    }
}
