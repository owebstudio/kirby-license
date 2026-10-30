# oweb studio license for Kirby

The license check of the paid [oweb studio](https://owebstudio.com) plugins for [Kirby](https://getkirby.com). It validates [Lemon Squeezy](https://www.lemonsqueezy.com) license keys and shows the license in the Panel.

You don't need to install this package yourself. Every paid oweb studio plugin comes with its own copy.

## For site owners

- **Local and staging sites need no key.** `localhost`, `*.test`, `*.local`, `*.ddev.site`, loopback IPs and the `staging.`, `dev.` and `test.` subdomains are free to use.
- **Live sites need a key.** Open the Panel's System view (Kirby 5 and newer) and click the license status of the plugin, or use the button in the plugin's notice. Enter the key from your purchase email. Only admins can activate and deactivate licenses.
- **One activation per site.** The key is activated for the domain of the site. `www.`, `staging.`, `dev.` and `test.` subdomains count as the same site. Keys of the former oweb studio bundle unlock every paid plugin with a single activation.
- **Your website keeps working.** Without a key the plugin locks its main Panel features and shows a notice. The frontend of your site never breaks.

### Other staging hosts

Add hosts that should not need a key to your `site/config/config.php`. `*` matches any subdomains:

```php
return [
    'owebstudio.license.staging' => [
        'preview.example.com',
        '*.example.dev'
    ]
];
```

### What is stored and sent

The activation is stored in `site/config/.owebstudio-licenses`. The file contains your license key, so keep it out of public repositories. If you deploy `site/config` with Git, either activate the license on the live site or deploy the file with it.

To activate the key and to validate it about once a week, the plugin sends the license key, the domain of the site and the activation ID to the Lemon Squeezy License API (`api.lemonsqueezy.com`). The validation only runs during Panel requests, never while visitors browse your site. If the API can't be reached, the last known state is kept and the check is retried a day later. Nothing else about your site or its visitors is sent.

Your server must be able to reach `api.lemonsqueezy.com` over HTTPS to activate a key.

## For plugin developers

The package is meant for the paid oweb studio plugins, but the code is open, so you can see exactly what the check does.

### Requirements

- Kirby 4, 5 or 6
- PHP 8.1+ (Kirby 6 needs PHP 8.3+)

### Distribution

Plugins can be installed with Composer, as a ZIP file or as a Git submodule, and two plugins may ship different versions of this package. To make class conflicts impossible, each plugin gets its own copy under its own namespace. The copy lives outside `vendor/`, because Composer installs of Kirby plugins remove the plugin's `vendor/` folder.

```sh
composer require --dev owebstudio/kirby-license
php vendor/bin/scope "Oweb\InstagramFeed\License" lib/license
```

Commit `lib/license` and run the command again to update. It only replaces a folder it generated itself. The plugins share the license file only. Its format only gets new fields, and unknown fields are kept, so all versions can read and write it.

### Usage

```php
<?php

use Kirby\Cms\App as Kirby;
use Oweb\InstagramFeed\License\License;

require_once __DIR__ . '/lib/license/autoload.php';

$license = new License(
    plugin: 'owebstudio/instagram-feed',
    products: [123456],
    name: 'Instagram Feed',
    url: 'https://owebstudio.com/kirby/plugins/instagram-feed'
);

Kirby::plugin(
    'owebstudio/instagram-feed',
    $license->extend([
        // the plugin's extensions
    ]),
    ...$license->args()
);
```

- `products`: the plugin's own Lemon Squeezy product IDs. The legacy bundle is accepted automatically.
- `extend()` adds the license dialog to the Panel's system area.
- `args()` adds the license to the System view on Kirby 5 and newer. Kirby 4 has no plugin licenses, so it adds nothing there. Pass it as the last argument, after the positional ones.

### API

| Method | Description |
| --- | --- |
| `$license->isLicensed()` | `true` with a valid key or on a local or staging host |
| `$license->isLocked()` | The opposite: lock the plugin's main features |
| `$license->status()` | `Status::Active`, `Exempt`, `Invalid` or `Missing` |
| `$license->message()` | Translated notice for the Panel, `null` if not locked |
| `$license->panel()` | State for Panel components (see below) |
| `$license->dialog()` | Panel path of the license dialog |
| `License::for('owebstudio/instagram-feed')` | The license object of a plugin |

`isLicensed()`, `isLocked()`, `status()` and `message()` never send a request and never throw, so they are safe to use in templates, hooks and the frontend.

`panel()` validates the key again when it is due (Panel requests only) and returns:

```php
[
    'button'  => 'Activate',  // label for the dialog button
    'dialog'  => 'oweb-license/owebstudio/instagram-feed', // null for non-admins
    'link'    => 'https://owebstudio.com/…',
    'locked'  => true,
    'message' => 'Instagram Feed is not activated on example.com.',
    'status'  => 'missing',   // active, exempt, invalid, missing
    'theme'   => 'notice',    // for <k-box>
]
```

Pass it to your field or view and show it with core components, which work in the Vue 2 Panel (Kirby 4 and 5) and the Vue 3 Panel (Kirby 6):

```html
<k-box v-if="license.locked" :theme="license.theme">
  <k-text>{{ license.message }}</k-text>
  <k-button v-if="license.dialog" :dialog="license.dialog" icon="key" variant="filled" size="sm">
    {{ license.button }}
  </k-button>
</k-box>
```

Check `isLocked()` in your API routes and dialogs too. The Panel lock alone is only a hint.

### Rules for locking

- Never break the frontend of a site. Snippets and templates keep rendering.
- Lock the main action in the Panel (saving in an editor, a paid API call, editing the field), not the whole Panel.
- Never block a customer's checkout.

## Development

```sh
composer install
vendor/bin/phpunit
```

The tests never send requests: the License API is replaced by a fake client. To test another Kirby version, install the dev dependencies without `getkirby/cms` and set `KIRBY_ROOT` to a `kirby` folder.

## License

MIT. The paid plugins that use this package are licensed under the [oweb studio Commercial License](https://owebstudio.com/license).
