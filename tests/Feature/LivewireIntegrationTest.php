<?php

namespace ArtflowStudio\FileUploader\Tests\Feature;

use ArtflowStudio\FileUploader\Concerns\WithUploader;
use ArtflowStudio\FileUploader\Livewire\UploaderTestbed;
use ArtflowStudio\FileUploader\Tests\TestCase;
use Livewire\Component;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;

/**
 * Rendering inside a real Livewire component, which is the only place the
 * entangle bridge can be exercised.
 */
class LivewireIntegrationTest extends TestCase
{
    #[Test]
    public function it_binds_to_the_component_property(): void
    {
        $html = html_entity_decode(Livewire::test(SingleUploader::class)->html());

        $this->assertStringContainsString('window.Livewire.find(', $html);
        $this->assertStringContainsString(".entangle('photo')", $html);
    }

    #[Test]
    public function the_live_modifier_is_carried_through(): void
    {
        // Quotes arrive HTML-escaped inside the x-data attribute.
        $html = html_entity_decode(Livewire::test(LiveUploader::class)->html());

        $this->assertStringContainsString(".entangle('photo').live", $html);
    }

    #[Test]
    public function ids_are_identical_on_re_render(): void
    {
        // The defect this guards against: the id used to include the request
        // URI, which is the page URL on first render and /livewire/update on
        // every update, so the wire:key changed under Alpine on the first
        // interaction (AUDIT.md, CRIT-02).
        $component = Livewire::test(SingleUploader::class);

        $before = $this->ids($component->html());

        $component->set('counter', 1);

        $this->assertSame($before, $this->ids($component->html()));
    }

    /**
     * The testbed renders three uploaders inside a @foreach.
     *
     * It is used rather than an inline render() string because Livewire 4's
     * wire-key precompiler cannot handle a @foreach in an inline component
     * string at all — a bare loop with no package markup fails the same way.
     */
    #[Test]
    public function uploaders_in_a_loop_all_get_distinct_ids(): void
    {
        $ids = $this->ids(Livewire::test(UploaderTestbed::class)->html());

        $this->assertGreaterThanOrEqual(9, count($ids));
        $this->assertSame($ids, array_unique($ids), 'Every uploader on the page needs its own id.');
    }

    #[Test]
    public function loop_ids_are_stable_across_re_renders(): void
    {
        $component = Livewire::test(UploaderTestbed::class);

        $before = $this->ids($component->html());

        $component->call('rerender');

        $this->assertSame($before, $this->ids($component->html()));
    }

    #[Test]
    public function two_components_binding_the_same_name_do_not_collide(): void
    {
        // Both components use wire:model="photo". The old build keyed its
        // preview cache on that name alone, so they overwrote each other
        // (AUDIT.md, CRIT-03). Ids must be scoped per component.
        $first = $this->ids(Livewire::test(SingleUploader::class)->html());
        $second = $this->ids(Livewire::test(OtherUploader::class)->html());

        $this->assertNotSame($first, $second);
    }

    /** @return list<string> */
    private function ids(string $html): array
    {
        preg_match_all('/wire:key="(af-[a-z0-9-]+)"/', $html, $matches);

        return $matches[1];
    }
}

class SingleUploader extends Component
{
    use WithUploader;

    public $photo;

    public int $counter = 0;

    public function render(): string
    {
        return '<div><span>{{ $counter }}</span><x-af-uploader wire:model="photo" /></div>';
    }
}

class OtherUploader extends Component
{
    use WithUploader;

    public $photo;

    public function render(): string
    {
        return '<div><x-af-uploader wire:model="photo" /></div>';
    }
}

class LiveUploader extends Component
{
    use WithUploader;

    public $photo;

    public function render(): string
    {
        return '<div><x-af-uploader wire:model.live="photo" /></div>';
    }
}

class LoopUploader extends Component
{
    use WithUploader;

    public array $slots = [null, null, null];

    public int $counter = 0;

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <span>{{ $counter }}</span>
                @foreach ($slots as $index => $slot)
                    <x-af-uploader wire:model="slots.{{ $index }}" />
                @endforeach
            </div>
        BLADE;
    }
}
