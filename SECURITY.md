# Security policy

## Supported versions

The latest release on the default branch is the only supported version.

## Reporting a vulnerability

Please report privately rather than opening a public issue.

Use GitHub's [private vulnerability reporting][advisory] on the repository, or
email the maintainer through the address on the
[GitHub profile](https://github.com/rahee554).

[advisory]: https://github.com/rahee554/livewire-uploader/security/advisories/new

Useful things to include:

- What an attacker can do, and what they need in order to do it.
- A reproduction — the request, the file, or the component setup.
- The package version, and the PHP and Laravel versions.

You should get an acknowledgement within a few days. Please give a reasonable
window for a fix before disclosing publicly.

## What counts

This package accepts files from untrusted people and writes them to disk, so its
security-sensitive surface is small and well defined:

- **`Support/UploadValidator`** — anything that gets an executable extension, a
  double extension, an oversized file or a disallowed MIME type past it.
- **`WithUploader::deleteStoredUpload()`** and **`discardUpload()`** — both are
  callable from the browser and take caller-supplied paths. Any traversal
  outside the configured disk is a vulnerability.
- **Stored filenames** — anything that lets a filename become script when
  rendered back into a page.
- **The published assets** — anything in `resources/js` that would execute
  content from an uploaded file.

## What does not

- The browser-side `accept` and `max-size` checks. They are a convenience and
  are trivially bypassed by posting straight at Livewire's upload endpoint —
  which is exactly why the server-side validator exists. Bypassing them is not a
  vulnerability in itself; getting past `UploadValidator` is.
- The testbed route. It is restricted to `local` and `testing` by default. If
  you have deliberately enabled it in production, that is a configuration
  choice, not a package defect.
- Anything requiring the attacker to already have code execution on the server.
