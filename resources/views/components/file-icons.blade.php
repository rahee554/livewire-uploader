{{--
    Icon for the current file's kind, chosen from files[0].kind.

    Kept in its own partial so the dropzone markup stays readable. Every branch
    is an Alpine <template>, so exactly one renders.
--}}

<template x-if="files[0]?.kind === 'pdf'">
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 2l5 5h-5V4zM8.5 17.5v-4h1.25c1.1 0 2 .67 2 1.5s-.9 1.5-2 1.5H9.5v1.5h-1zm1-2.5h.25c.55 0 1-.22 1-.5s-.45-.5-1-.5H9.5v1zm3.5 2.5v-4h1.5c1.38 0 2.5.9 2.5 2s-1.12 2-2.5 2H13zm1-3v2h.5c.83 0 1.5-.45 1.5-1s-.67-1-1.5-1H14z"/></svg>
</template>

<template x-if="files[0]?.kind === 'video'">
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17 10.5V7a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h12a1 1 0 001-1v-3.5l4 4v-11l-4 4z"/></svg>
</template>

<template x-if="files[0]?.kind === 'audio'">
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3v10.55A4 4 0 1014 17V7h4V3h-6z"/></svg>
</template>

<template x-if="files[0]?.kind === 'image'">
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
</template>

<template x-if="files[0]?.kind === 'sheet'">
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm4 18H6V4h7v5h5v11zM8 12h3v2H8v-2zm5 0h3v2h-3v-2zm-5 4h3v2H8v-2zm5 0h3v2h-3v-2z"/></svg>
</template>

<template x-if="files[0]?.kind === 'archive'">
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20 6h-8l-2-2H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V8a2 2 0 00-2-2zm-9 4h2v2h-2v-2zm0 3h2v2h-2v-2zm0 3h2v2h-2v-2z"/></svg>
</template>

<template x-if="['doc', 'slide'].includes(files[0]?.kind)">
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm4 18H6V4h7v5h5v11zM8 12h8v2H8v-2zm0 4h8v2H8v-2z"/></svg>
</template>

<template x-if="!['pdf', 'video', 'audio', 'image', 'sheet', 'archive', 'doc', 'slide'].includes(files[0]?.kind)">
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm4 18H6V4h7v5h5v11z"/></svg>
</template>
