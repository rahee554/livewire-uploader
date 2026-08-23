## What this changes

<!-- One or two sentences. Link the issue if there is one: Fixes #12 -->

## Why

<!-- The problem it solves. If the reasoning is non-obvious, it probably also
     belongs in a comment next to the code. -->

## How to check it

<!-- The steps a reviewer should take. If you added a testbed panel, say which. -->

## Checklist

- [ ] `composer test` passes
- [ ] `composer lint` passes
- [ ] A test covers this — one that fails without the change, for a bug fix
- [ ] New props are in the README table and `config/uploader.php`
- [ ] Anything breaking is in `docs/CHANGELOG.md` with the upgrade step
