## A CSV Content-Type Exporter for Bolt 3.4+

This is a lightweight CSV exporter that by default adds a new CSV export menu with a link to export
each available content-type in your installation.

### Install

Via the marketplace search for csv export and install, or via the CLI: 
`cd extensions; composer require rossriley/bolt-csv-export ^1.0`

### Configuration

After you have installed the extension you will have a `csvexport.yml` file created for you in your
`app/config/extensions` directory. You can add contenttypes to the `disabled` list to prevent them
being exportable.

Additionally you can setup field name mappings, this allows you to override the default field names 
to adjust what is shown in the header row of your CSV export.

If you want to omit a specific field from the export then set the mapping to `false` and the column
will be skipped.

Finally you can adjust the permission of Bolt users that are allowed to use the export functionality
it defaults to `contentaction` but you can change this or add your own permission to Bolt specifically
for this extension.
