## Upgrade Notes

### `2.x` to `3.x`

Version 3 doesn't really attempt to be backwards compatible - I haven't been actively
using PHP for some years, so this release is a revived and modernized package for PHP 8,
with a smaller scope.

Earlier versions of this package came with a Dispatcher and a URL builder, both of which
have been removed from version `3.0.0` of this package:

- The Dispatcher had no real connection with the Router itself, and was more like a
  proof of concept than a full-blown implementation - if you wish to upgrade to `3.0.0`,
  you can copy the dispatcher from the
  [old version](https://github.com/mindplay-dk/timber/blob/6defdfaea55bb59171b54a9abcf388e836e18e66/src/Dispatcher.php)
  and use this as a basis for your own dispatcher.

- The URL builder also had no direct connection with the Router, as has been removed -
  you can find third-party packages on Packagist specifically for this purpose.

Major breaking changes:

- `Registry` is now called `PatternRegistry`, and `Symbol` is called `Pattern`.

Minor breaking changes:

- Static type-hints were added.

Other than that, what remains is more or less the same.
