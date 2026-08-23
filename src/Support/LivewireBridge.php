<?php

namespace ArtflowStudio\FileUploader\Support;

use Livewire\Mechanisms\ExtendBlade\ExtendBlade;

/**
 * Resolves the Livewire component that is currently rendering.
 *
 * Anonymous Blade components get an isolated view scope, so `$__livewire` —
 * which Livewire's own @entangle directive compiles against — is simply not
 * defined inside <x-af-uploader>. Reaching the component through ExtendBlade
 * instead lets the uploader build the same expression by hand and, just as
 * importantly, degrade to a plain input when it is rendered outside Livewire
 * rather than raising "Undefined variable $__livewire".
 */
final class LivewireBridge
{
    public static function componentId(): ?string
    {
        if (! class_exists(ExtendBlade::class) || ! method_exists(ExtendBlade::class, 'currentRendering')) {
            return null;
        }

        // Returns false, not null, when nothing is rendering.
        $component = ExtendBlade::currentRendering();

        if (! is_object($component) || ! method_exists($component, 'getId')) {
            return null;
        }

        return (string) $component->getId();
    }

    /**
     * The JavaScript expression Livewire's @entangle directive would emit.
     *
     * Returns null when there is no component to bind to, so the caller can
     * omit the argument entirely.
     */
    public static function entangle(string $property, bool $live = false): ?string
    {
        $id = self::componentId();

        if ($id === null || $property === '') {
            return null;
        }

        return sprintf(
            "window.Livewire.find('%s').entangle('%s')%s",
            $id,
            $property,
            $live ? '.live' : '',
        );
    }
}
