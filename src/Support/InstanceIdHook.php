<?php

namespace ArtflowStudio\FileUploader\Support;

use Livewire\ComponentHook;

/**
 * Clears a component's uploader-id counters at the start of every render.
 *
 * InstanceId hands out ordinals so several uploaders bound to the same
 * property (a @foreach, say) get distinct ids. Those ordinals must restart from
 * zero on each render, or the second render of a component produces
 * af-<hash-of-1> where the first produced af-<hash-of-0>, the wire:key changes,
 * and Alpine's state is thrown away — the same class of failure as the request
 * URI hash this replaced (AUDIT.md, CRIT-02).
 *
 * Resetting per request would be enough for PHP-FPM, where each request is a
 * fresh process. It is not enough under Octane, FrankenPHP or any long-running
 * worker, where a single process serves thousands of renders.
 */
class InstanceIdHook extends ComponentHook
{
    public function render($view, $data): void
    {
        InstanceId::resetScope($this->component->getId());
    }
}
