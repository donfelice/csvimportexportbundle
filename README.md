# CSVImportExportBundle

The CSVImportExportBundle is an eZ Platform v2 bundle providing a basic import and export capabilities for the admin interface.

## Installation

### Use Composer

Run the following from your website root folder to install Analytics Bundle:

```
$ composer require donfelice/csvimportexportbundle
```

### Activate the bundle

Activate the bundle in app/AppKernel.php file by adding it to the $bundles array in registerBundles method, together with other required bundles:

```javascript
public function registerBundles()
{
    ...
    $bundles[] = new Donfelice\AnalyticsBundle\DonfeliceCSVImportExportBundle();

    return $bundles;
}
```

### Assetic configuration

You need to add it to Assetic configuration in app/config/config.yml, together with EzPlatformAdminUiBundle and all other bundles already configured there:

```
assetic:
    bundles: [EzPlatformAdminUiBundle, DonfeliceCSVImportExportBundle]
```

## Limitations

Yes.

- As for now only ezstring and ezemail field types are supported. More coming.
- Will not do imports of unlimited objects very well..
- Exported files don't look to good in Excel on macOS. Appears to be a well known and long story about BOM and UTF and several other monsters..
