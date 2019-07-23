# Sleeps 2 Santa

A basic php sleeps until christmas countdown.
With options for custom days and or timezones.

## Getting Started

To get started just clone this repository into your website, or if you're not bothered about this text just the following files will do:
.htaccess
index.php
error.php

### Prerequisites

Any webserver with php. If you're not using apache you'll have to convert the .htaccess file for your chosen webserver.


### Using this script

If you call the index.php without any variables it will output the amount of sleeps until Christmas
and it will assume you're in the UK

```
There are xx sleeps to Christmas.
```

### Custom date / timezone

There are 3 custom options:
* tz: TimeZone any php recognised [TimeZone](https://www.php.net/manual/en/timezones.php)
* td: Target day. In case you want a day other than christmas, containing at least day month and year [format](https://www.php.net/manual/en/function.date-parse.php)
* tdn: Target day name. So the script will know what to call your day

You can call any number of options or none

```
/index.php?tz=Europe/Amsterdam&tdn="Dutch Christmas"
```

## License

This project is licensed under [GNU GPL v3](/LICENSE.txt)

## Acknowledgments

* Parsing of the README.md by [Parsedown](https://github.com/erusev/parsedown)
