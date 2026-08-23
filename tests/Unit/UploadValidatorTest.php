<?php

namespace ArtflowStudio\FileUploader\Tests\Unit;

use ArtflowStudio\FileUploader\Exceptions\UploadRejected;
use ArtflowStudio\FileUploader\Support\UploadValidator;
use ArtflowStudio\FileUploader\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;

class UploadValidatorTest extends TestCase
{
    #[Test]
    public function a_wildcard_family_matches(): void
    {
        $file = UploadedFile::fake()->image('photo.png');

        $this->assertTrue(UploadValidator::matchesAccept($file, 'image/*'));
        $this->assertFalse(UploadValidator::matchesAccept($file, 'video/*'));
    }

    #[Test]
    public function an_extension_token_matches(): void
    {
        $file = UploadedFile::fake()->create('report.pdf', 10, 'application/pdf');

        $this->assertTrue(UploadValidator::matchesAccept($file, '.pdf'));
        $this->assertTrue(UploadValidator::matchesAccept($file, 'image/*,.pdf'));
        $this->assertFalse(UploadValidator::matchesAccept($file, '.docx'));
    }

    #[Test]
    public function an_empty_accept_expression_allows_everything(): void
    {
        $file = UploadedFile::fake()->create('notes.txt', 1, 'text/plain');

        $this->assertTrue(UploadValidator::matchesAccept($file, ''));
        $this->assertTrue(UploadValidator::matchesAccept($file, null));
    }

    #[Test]
    public function oversized_files_are_rejected(): void
    {
        $file = UploadedFile::fake()->create('big.png', 3072, 'image/png');

        $this->expectException(UploadRejected::class);
        $this->expectExceptionMessage('larger than the 2MB limit');

        UploadValidator::assertAllowed($file, 'image/*', 2);
    }

    #[Test]
    public function the_config_ceiling_overrides_a_larger_component_limit(): void
    {
        config()->set('af-uploader.validation.max_size', 1);

        $file = UploadedFile::fake()->create('big.png', 1536, 'image/png');

        $this->expectException(UploadRejected::class);

        UploadValidator::assertAllowed($file, 'image/*', 50);
    }

    #[Test]
    public function executable_extensions_are_rejected(): void
    {
        $file = UploadedFile::fake()->create('shell.php', 1, 'text/plain');

        $this->expectException(UploadRejected::class);
        $this->expectExceptionMessage('.php files are not allowed');

        UploadValidator::assertAllowed($file);
    }

    #[Test]
    public function a_double_extension_is_rejected(): void
    {
        // avatar.php.png still executes under a misconfigured server.
        $file = UploadedFile::fake()->image('avatar.php.png');

        $this->expectException(UploadRejected::class);

        UploadValidator::assertAllowed($file);
    }

    #[Test]
    public function svg_is_blocked_by_default(): void
    {
        $file = UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml');

        $this->expectException(UploadRejected::class);

        UploadValidator::assertAllowed($file);
    }

    #[Test]
    public function svg_can_be_allowed_explicitly(): void
    {
        config()->set('af-uploader.validation.allow_svg', true);

        $file = UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml');

        UploadValidator::assertAllowed($file);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function a_mismatched_type_is_rejected(): void
    {
        $file = UploadedFile::fake()->create('clip.mp4', 1, 'video/mp4');

        $this->expectException(UploadRejected::class);
        $this->expectExceptionMessage('does not match the accepted types');

        UploadValidator::assertAllowed($file, 'image/*');
    }

    #[Test]
    public function enforcement_can_be_switched_off(): void
    {
        config()->set('af-uploader.validation.enforce', false);

        UploadValidator::assertAllowed(
            UploadedFile::fake()->create('shell.php', 1),
            'image/*',
            0.001
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function an_acceptable_file_passes(): void
    {
        UploadValidator::assertAllowed(UploadedFile::fake()->image('ok.jpg'), 'image/*', 10);

        $this->expectNotToPerformAssertions();
    }
}
