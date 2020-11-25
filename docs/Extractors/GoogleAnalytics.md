# Extract statistics from multiple properties in GoogleAnalytics.

```php
use \Wizaplace\Etl\Etl;
use \Wizaplace\Etl\Extractors\GoogleAnalytics;

$pipeline = new Etl();
$config = '/path/to/credentials.json';
$extractor = new GoogleAnalytics($config);
$input = ['example.com', 'example.org'];
$options = [
    'startDate' => '2020-11-11',
    'dimensions' => ['ga:date'],
    'metrics' => [
        ['name' => 'ga:pageviews', 'type' => 'INTEGER'],
        ['name' => 'ga:uniquePageviews', 'type' => 'INTEGER'],
        ['name' => 'ga:searchResultViews', 'type' => 'INTEGER'],
        ['name' => 'ga:users', 'type' => 'INTEGER'],
        ['name' => 'ga:newUsers', 'type' => 'INTEGER'],
        ['name' => 'ga:sessions', 'type' => 'INTEGER'],
        ['name' => 'ga:avgPageLoadTime', 'type' => 'FLOAT'],
        ['name' => 'ga:avgRedirectionTime', 'type' => 'FLOAT'],
        ['name' => 'ga:avgServerResponseTime', 'type' => 'FLOAT'],
        ['name' => 'ga:avgSessionDuration', 'type' => 'TIME'],
    ],
    'views' => [],
];
$iterator = $pipeline->extract($extractor, $input, $options)->toIterator();
```

Set $input to an empty array to extract all sites associated with the
profile in $config.

Set $options['views'] to a non-empty array of profile summary names to
extract only the specified views of the data.

### Input

The input source.

By default, the input is all the web properties the Google client has access to.
However, an array of property names may be specified to limit the extraction to
a smaller set of properties. (In the example below, property names correspond to
the web site name, but that is not necessarily true for all GA users.)

| Type     | Default value |
|----------|---------------|
| string[] | `null`        |

```php
$input = ['www.example.com', 'demo.example.com'];
```

### Options

#### Dimensions (required)

The dimension or dimensions used to group analytics data (frequently "ga:date").

| Type     | Default value |
|----------|---------------|
| string[] | `null`        |

```php
$options = ['dimensions' => ['ga:date']];
```
     
#### Metrics (required)

The metrics to be extracted.

Each element of the metrics array consists of a name and a type:

| Type     | Default value |
|----------|---------------|
| string[] | `null`        |

```php
$options = [
'metrics' => [
   ['name' => 'ga:pageviews', 'type' => 'INTEGER'],
   ['name' => 'ga:avgPageLoadTime', 'type' => 'FLOAT'],
   ['name' => 'ga:avgSessionDuration', 'type' => 'TIME'],
]
];
```

#### StartDate (required)

The first date to be extracted.

| Type   | Default value |
|--------|---------------|
| string | `null`        |

```php
$options = ['startDate' => '2020-11-11'];
```

#### EndDate (optional)

| Type   | Default value                       |
|--------|-------------------------------------|
| string | date_create('now')->format('Y-m-d') |

The last date to be extracted. Uses yesterday if none is explicitly provided.

```php
$options = ['startDate' => '2020-11-11'];
```

#### Views (optional)

| Type     | Default value |
|----------|---------------|
| string[] | `null`        |

If specified, views will select specific profile summaries for extraction.

```php
$options = ['views' => ['All data']];
```
