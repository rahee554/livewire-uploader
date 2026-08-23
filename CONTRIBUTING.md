# Contributing

Thanks for taking an interest. Bug reports, questions and pull requests are all
welcome, and you do not need permission to open any of them.

Repository: <https://github.com/rahee554/livewire-uploader>

---

## Reporting a bug

Open an issue using the **Bug report** template. The two things that make a
report actionable are:

1. **A minimal reproduction.** The `<x-af-uploader>` tag as you wrote it, plus
   the relevant properties on the Livewire component. Ten lines beats a
   description of an entire form.
2. **What you expected versus what happened.** "The preview does not appear" and
   "the file uploads but the preview does not appear" are different bugs.

Also useful: PHP, Laravel and Livewire versions, the browser, and anything in
the browser console.

If you can reproduce it on the testbed page (`/af-uploader/testbed` in a local
app), say which panel — that removes most of the guesswork.

### Security issues

Do **not** open a public issue for anything exploitable — path traversal, a
bypass of the upload validation, stored XSS through a filename. Email the
maintainer instead. See [SECURITY.md](SECURITY.md).

---

## Suggesting a feature

Open an issue with the **Feature request** template and describe the problem
before the solution. "I need square avatars under 100KB" tells us more than
"add a `squareCompress` prop", and often the feature already exists under a
different name.

The package deliberately stays close to one job — putting a file on the server
with as little ceremony as possible. Features that belong to the *application*
(image galleries, media libraries, S3 lifecycle rules) are usually better built
on top of it than inside it.

---

## Working on the code

### Getting set up

```bash
git clone https://github.com/rahee554/livewire-uploader.git
cd livewire-uploader
composer install
composer test
```

The suite runs on [Orchestra Testbench](https://packages.tools/testbench) — no
host application required.

### Testing your change inside a real app

Point a Laravel app's `composer.json` at your clone:

```json
"repositories": [
    { "type": "path", "url": "../livewire-uploader" }
]
```

```bash
composer require artflow-studio/uploader:*
php artisan af-uploader:install
```

Composer symlinks the package, so edits are live. After changing anything under
`resources/css` or `resources/js`, re-publish:

```bash
php artisan af-uploader:publish --force
```

Then open `/af-uploader/testbed` — every configuration the component supports on
one page. Add a panel there for whatever you are working on; it is the fastest
way to check a change against the other variants.

### Before opening a pull request

```bash
composer lint    # Pint, the same style Laravel uses
composer test
```

Both run in CI, so running them locally saves a round trip.

---

## What we look for in a pull request

**Tests.** A bug fix should come with a test that fails without it. A feature
should have coverage for the interesting cases, not only the happy path. Look at
`tests/Unit/OptionsTest.php` for the shape.

**One thing at a time.** A focused diff gets reviewed quickly. A pull request
that fixes a bug *and* reformats a file *and* renames a method usually does not.

**Comments that explain why.** The codebase leans on comments to record the
reasoning behind non-obvious decisions — why the instance id avoids the request
URI, why `@entangle` cannot be used in an anonymous component. If your change
involves a constraint the next reader will not guess, write it down. Please do
not add comments that restate the code.

**Public API changes need a note.** New props go in the README table and
`config/uploader.php`. Anything breaking goes in `docs/CHANGELOG.md` under
Breaking, with the upgrade step spelled out.

### Things worth knowing before you dig in

- The browser checks are a convenience. `Support/UploadValidator` is the
  enforcement boundary, and anything reachable from the browser must assume its
  arguments are hostile.
- `resources/js` is plain ES modules with no build step. Keep it that way — the
  package should stay installable with `composer require` and nothing else.
- The uploader is one Alpine component that owns its input for its whole
  lifetime. Adding a second thing that listens on the same element is how the
  previous version accumulated most of its bugs; `AUDIT.md` has the details.
- Every class is namespaced `af-*`, and colours come from `--af-*` custom
  properties. A rule that styles a bare element selector will leak into the host
  application.

---

## Code of conduct

Be decent to each other. Assume the person on the other end is doing their best
with incomplete information, because they usually are. Anything else —
harassment, condescension, bad faith — is not welcome here, and maintainers will
act on it.

---

## Licence

Contributions are made under the [MIT licence](LICENSE) that covers the project.
