# Installation

Before you start, make sure [Symfony UX StimulusBundle is configured in your
app](https://symfony.com/bundles/StimulusBundle/current/index.html).

Install the bundle using Composer (Symfony Flex will auto-enable it):

```terminal
composer require pentiminax/ux-datatables
```

## Assets

This bundle ships DataTables assets through AssetMapper when it is available
in your Symfony version. If you're using AssetMapper, no extra work is needed
after installation.

If you're still on Webpack Encore, install the JavaScript dependencies and
rebuild your assets so the DataTables packages are available:

```terminal
npm install --force
npm run watch

# or use yarn
yarn install --force
yarn watch
```

Once the assets are built, the `@pentiminax/ux-datatables/datatable`
Stimulus controller becomes available automatically.
