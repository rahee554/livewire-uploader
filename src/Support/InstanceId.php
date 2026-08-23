<?php

namespace ArtflowStudio\FileUploader\Support;

/**
 * Produces a DOM id for an uploader instance that is identical on the initial
 * page render and on every subsequent Livewire update.
 *
 * The previous implementation hashed request()->getRequestUri(), which is the
 * page URL on first render but "/livewire/update" on every update after it —
 * so the wire:key changed underneath Alpine on the first interaction. See
 * AUDIT.md, CRIT-02.
 *
 * The id is built from two deterministic parts:
 *
 *   scope    the id of the Livewire component currently rendering, so two
 *            components on one page that both bind wire:model="photo" cannot
 *            collide.
 *   ordinal  how many times this same key has been seen inside this scope
 *            during this render, so @foreach loops stay distinct.
 *
 * Both are stable because Livewire re-renders a component's view top to bottom
 * in the same order every time.
 */
final class InstanceId
{
    /** @var array<string, int> */
    private static array $counters = [];

    /**
     * @param  string  $key  Usually the bound wire:model expression.
     */
    public static function make(string $key, ?string $explicit = null): string
    {
        if ($explicit !== null && $explicit !== '') {
            return 'af-'.self::slug($explicit);
        }

        $scope = self::scope();
        $bucket = $scope.'|'.$key;

        $ordinal = self::$counters[$bucket] = (self::$counters[$bucket] ?? -1) + 1;

        return 'af-'.substr(hash('xxh128', $bucket.'#'.$ordinal), 0, 12);
    }

    /**
     * Drop the counters for one Livewire component.
     *
     * Called by {@see InstanceIdHook} at the start of every render, so the
     * ordinals a component hands out restart from zero and reproduce exactly
     * the ids of its previous render.
     */
    public static function resetScope(string $scope): void
    {
        $prefix = $scope.'|';

        foreach (array_keys(self::$counters) as $bucket) {
            if (str_starts_with($bucket, $prefix)) {
                unset(self::$counters[$bucket]);
            }
        }
    }

    /** Drop every counter. Intended for tests. */
    public static function reset(): void
    {
        self::$counters = [];
    }

    private static function scope(): string
    {
        return LivewireBridge::componentId() ?? 'global';
    }

    private static function slug(string $value): string
    {
        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '-', $value) ?? '';

        return trim($slug, '-') ?: 'instance';
    }
}
