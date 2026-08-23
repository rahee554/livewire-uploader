<?php

namespace ArtflowStudio\FileUploader\Tests\Unit;

use ArtflowStudio\FileUploader\Support\InstanceId;
use ArtflowStudio\FileUploader\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class InstanceIdTest extends TestCase
{
    #[Test]
    public function repeating_the_same_key_yields_distinct_ids(): void
    {
        $first = InstanceId::make('photo');
        $second = InstanceId::make('photo');

        $this->assertNotSame($first, $second, 'Two uploaders in a loop must not share an id.');
    }

    #[Test]
    public function the_sequence_repeats_identically_on_the_next_render(): void
    {
        // This is the property the old request-URI hash did not have: the id
        // must be the same on the initial render and on every Livewire update
        // (AUDIT.md, CRIT-02).
        $first = [InstanceId::make('photo'), InstanceId::make('photo'), InstanceId::make('avatar')];

        InstanceId::reset();

        $second = [InstanceId::make('photo'), InstanceId::make('photo'), InstanceId::make('avatar')];

        $this->assertSame($first, $second);
    }

    #[Test]
    public function different_keys_do_not_collide(): void
    {
        $this->assertNotSame(InstanceId::make('photo'), InstanceId::make('avatar'));
    }

    #[Test]
    public function an_explicit_id_wins_and_is_stable(): void
    {
        $this->assertSame('af-hero', InstanceId::make('photo', 'hero'));
        $this->assertSame('af-hero', InstanceId::make('other', 'hero'));
    }

    #[Test]
    public function an_explicit_id_is_reduced_to_safe_characters(): void
    {
        $this->assertSame('af-my-id', InstanceId::make('photo', 'my id'));
        $this->assertSame('af-a-b', InstanceId::make('photo', 'a.b'));
    }

    #[Test]
    public function generated_ids_are_valid_html_identifiers(): void
    {
        $id = InstanceId::make('photo');

        $this->assertMatchesRegularExpression('/^af-[a-z0-9]+$/', $id);
    }
}
