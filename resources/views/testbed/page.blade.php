<div>
    <h1>AF Uploader testbed</h1>
    <p class="lede">
        Every configuration the component supports, on one page.
        Render count: <span data-testid="renders">{{ $renders }}</span>
    </p>

    {{--
        Each button applies a different host convention to <html>. The uploader
        should follow every one of them without being told, and fall back to the
        OS setting when "None" clears them all.
    --}}
    <div class="theme-bar" wire:ignore>
        <strong>Host theme:</strong>
        <button type="button" class="af-btn" data-host="none">None (follow OS)</button>
        <button type="button" class="af-btn" data-host="tailwind-dark">Tailwind <code>.dark</code></button>
        <button type="button" class="af-btn" data-host="tailwind-light">Tailwind light</button>
        <button type="button" class="af-btn" data-host="bs-dark">Bootstrap <code>data-bs-theme="dark"</code></button>
        <button type="button" class="af-btn" data-host="bs-light">Bootstrap light</button>
        <button type="button" class="af-btn" data-host="data-dark"><code>data-theme="dark"</code></button>
        <span>&rarr; resolved: <code data-testid="resolved-theme">…</code></span>
    </div>

    <script>
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-host]');
            if (!button) return;

            const root = document.documentElement;
            root.classList.remove('dark', 'light');
            root.removeAttribute('data-bs-theme');
            root.removeAttribute('data-theme');

            const apply = {
                'tailwind-dark': () => root.classList.add('dark'),
                'tailwind-light': () => root.classList.add('light'),
                'bs-dark': () => root.setAttribute('data-bs-theme', 'dark'),
                'bs-light': () => root.setAttribute('data-bs-theme', 'light'),
                'data-dark': () => root.setAttribute('data-theme', 'dark'),
            }[button.dataset.host];

            apply?.();
        });

        // Mirror whatever the uploader resolved, so the effect is visible.
        new MutationObserver(() => {
            const readout = document.querySelector('[data-testid="resolved-theme"]');
            if (readout) readout.textContent = document.documentElement.getAttribute('data-af-theme') ?? '—';
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-af-theme'] });
    </script>

    <div class="toolbar">
        <button type="button" wire:click="rerender" data-testid="rerender">Force re-render</button>
        <button type="button" wire:click="save" data-testid="save">Store uploads</button>
        <button type="button" wire:click="reset_all" data-testid="reset">Reset all</button>
    </div>

    <div class="grid">
        <section class="panel" data-panel="simple">
            <h2>Simple image</h2>
            <x-af-uploader wire:model="simple" id="simple" height="170" />
            <p class="note">Auto-uploads. Accepts images only.</p>
            <div class="state" data-testid="state-simple">{{ $this->state['simple'] }}</div>
        </section>

        <section class="panel" data-panel="manual">
            <h2>Manual upload</h2>
            <x-af-uploader wire:model="manual" id="manual" auto-upload="false" height="170" />
            <p class="note">auto-upload="false" — an Upload button appears instead.</p>
            <div class="state" data-testid="state-manual">{{ $this->state['manual'] }}</div>
        </section>

        <section class="panel" data-panel="cropped">
            <h2>Editor, 16:9</h2>
            <x-af-uploader wire:model="cropped" id="cropped" editor ratio="16/9" convert="webp" height="170" />
            <p class="note">Opens the crop editor, exports WebP.</p>
            <div class="state" data-testid="state-cropped">{{ $this->state['cropped'] }}</div>
        </section>

        <section class="panel" data-panel="avatar">
            <h2>Circular avatar</h2>
            <x-af-uploader
                wire:model="avatar"
                id="avatar"
                editor
                is-circle
                variant="circled"
                target-size="200KB"
                height="170"
            />
            <p class="note">Circular mask, ratio pinned to 1:1, compressed under 200KB.</p>
            <div class="state" data-testid="state-avatar">{{ $this->state['avatar'] }}</div>
        </section>

        <section class="panel" data-panel="document">
            <h2>Document</h2>
            <x-af-uploader wire:model="document" id="document" accept=".pdf,.txt,.csv" max-size="5" height="170" />
            <p class="note">Non-image types get a file icon rather than a preview.</p>
            <div class="state" data-testid="state-document">{{ $this->state['document'] }}</div>
        </section>

        <section class="panel" data-panel="gallery">
            <h2>Multiple</h2>
            <x-af-uploader wire:model="gallery" id="gallery" multiple max-files="4" accept="image/*" height="170" />
            <p class="note">Up to four images at once.</p>
            <div class="state" data-testid="state-gallery">{{ $this->state['gallery'] }} file(s)</div>
        </section>
    </div>

    <h2 style="margin-top:32px">What each uploader accepts</h2>
    <div class="grid">
        <section class="panel" data-panel="imagesOnly">
            <h2>Images only</h2>
            <x-af-uploader wire:model="imagesOnly" id="imagesOnly" accept="image/*" multiple height="170" />
            <p class="note">accept="image/*" — a video or PDF is refused before it uploads.</p>
            <div class="state" data-testid="state-imagesOnly">{{ $this->state['imagesOnly'] }} file(s)</div>
        </section>

        <section class="panel" data-panel="videoOnly">
            <h2>Video only</h2>
            <x-af-uploader wire:model="videoOnly" id="videoOnly" accept="video/*" max-size="50" height="170" />
            <p class="note">accept="video/*" with a 50MB ceiling.</p>
            <div class="state" data-testid="state-videoOnly">{{ $this->state['videoOnly'] }}</div>
        </section>

        <section class="panel" data-panel="sized">
            <h2>Size-limited</h2>
            <x-af-uploader wire:model="sized" id="sized" accept="image/*,.pdf" max-size="0.05" height="170" />
            <p class="note">max-size="0.05" (50KB) — larger files are named and refused.</p>
            <div class="state" data-testid="state-sized">{{ $this->state['sized'] }}</div>
        </section>

        <section class="panel" data-panel="percent">
            <h2>Compress to 40%</h2>
            <x-af-uploader
                wire:model="percent"
                id="percent"
                convert="webp"
                compress="40%"
                show-savings
                height="170"
            />
            <p class="note">compress="40%" — output kept under 40% of whatever was picked.</p>
            <div class="state" data-testid="state-percent">{{ $this->state['percent'] }}</div>
        </section>

        <section class="panel" data-panel="both">
            <h2>Both ceilings</h2>
            <x-af-uploader
                wire:model="both"
                id="both"
                convert="webp"
                compress="50%"
                target-size="120KB"
                show-savings
                height="170"
            />
            <p class="note">compress="50%" plus target-size="120KB" — the tighter of the two applies.</p>
            <div class="state" data-testid="state-both">{{ $this->state['both'] }}</div>
        </section>

        <section class="panel" data-panel="manyFiles">
            <h2>Many files</h2>
            <x-af-uploader wire:model="manyFiles" id="manyFiles" multiple accept="image/*,.pdf,.csv" height="200" />
            <p class="note">Each tile removes on its own. Scrolls when it overflows.</p>
            <div class="state" data-testid="state-manyFiles">{{ $this->state['manyFiles'] }} file(s)</div>
        </section>
    </div>

    <h2 style="margin-top:32px">Variants</h2>
    <div class="grid">
        <section class="panel" data-panel="inline">
            <h2>Inline</h2>
            <x-af-uploader wire:model="inline" id="inline" variant="inline" accept="image/*,.pdf" />
            <p class="note">A single row — fits inside a form next to other fields.</p>
            <div class="state" data-testid="state-inline">{{ $this->state['inline'] }}</div>
        </section>

        <section class="panel" data-panel="squared">
            <h2>Squared</h2>
            <x-af-uploader wire:model="squared" id="squared" variant="squared" />
            <p class="note">1:1 box, sized by its container.</p>
            <div class="state" data-testid="state-squared">{{ $this->state['squared'] }}</div>
        </section>

        <section class="panel" data-panel="rect">
            <h2>Rect (16:9)</h2>
            <x-af-uploader wire:model="rect" id="rect" variant="rect" />
            <p class="note">16:9 box for banners and covers.</p>
            <div class="state" data-testid="state-rect">{{ $this->state['rect'] }}</div>
        </section>

        <section class="panel" data-panel="shrink">
            <h2>Compression report</h2>
            <x-af-uploader
                wire:model="shrink"
                id="shrink"
                convert="webp"
                quality="0.7"
                max-width="1000"
                show-savings
                height="170"
            />
            <p class="note">show-savings — reports before → after and the percentage saved.</p>
            <div class="state" data-testid="state-shrink">{{ $this->state['shrink'] }}</div>
        </section>

        <section class="panel" data-panel="tiny">
            <h2>Rejects big files</h2>
            <x-af-uploader wire:model="tiny" id="tiny" max-size="0.01" accept="image/*" height="170" />
            <p class="note">max-size="0.01" (10KB) — anything larger is refused client-side.</p>
            <div class="state" data-testid="state-tiny">{{ $this->state['tiny'] }}</div>
        </section>
    </div>

    <h2 style="margin-top:32px">Loop — ids must stay distinct and stable</h2>
    <div class="slots">
        @foreach ($loopFiles as $index => $loopFile)
            <x-af-uploader wire:model="loopFiles.{{ $index }}" height="130" label="Slot {{ $index + 1 }}" />
        @endforeach
    </div>

    <h2 style="margin-top:32px">Server results</h2>
    <div class="state" data-testid="saved">{{ $saved ? implode("\n", $saved) : 'nothing stored' }}</div>
    <div class="state" data-testid="errors">{{ $errors ? implode("\n", $errors) : 'no errors' }}</div>
</div>
