# icons/

Line icons from **Lucide** (<https://lucide.dev>), the icon set named in the design system (wireframe p6).
The files are unmodified copies from the `lucide-static` package, version **1.52.0**, saved in the project so
the site needs no internet connection or build step to show them.

Use them through the helper in `includes/helpers.php`:

```php
<?= icon('droplet') ?>
```

To add an icon, find its name on <https://lucide.dev/icons>, download
`https://unpkg.com/lucide-static@1.52.0/icons/<name>.svg` into this folder and add it to `docs/credits.md`.

Licence: ISC (some icons MIT, from the Feather project). The full text is in [`LICENSE`](LICENSE) and must stay
with the icon files.
