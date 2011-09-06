# Fuel LDAP package

The LDAP package allows to connect, bind and run queries against an LDAP server (OpenLdap, Microsoft AD, etc.) to retrieve information. It includes an Auth driver to validate login credentials against the directory as well.

## About

* Version: 0.9
* Author: Axel Pardemann

## Development Team

* Axel Pardemann - Lead Developer ([http://axelitus.mx] (http://axelitus.mx))


## Installation

This package follows standard installation rules, which can be found within the [FuelPHP Documentation for Packages] (http://fuelphp.com/docs/general/packages.html)

First you need to add axelitus' github location to the package configuration file (package.php) located in fuel/core/config/ folder (you are encourage to copy this file to your app/config folder and edit that file instead):

```
// config/package.php
return array(
    'sources' => array(
        'github.com/fuel-packages',
        'github.com/axelitus', // ADD THIS LINE
    ),
);
```

Then just run the following command from the terminal while in your applications' base directory

```
oil package install ldap
```

## Usage

Coming soon...

### Configuration

Coming soon...

### Basic usage

Coming soon...

#### Connect

Coming soon...

#### Bind

Coming soon...

#### Unbind

Coming soon...

#### Disconnect

Coming soon...

### Intermediate usage

Coming soon...

#### Bind Credentials

Coming soon...

#### Queries

Coming soon...

##### Query Builder

Coming soon...

#### Results

Coming soon...

##### Result Formatter

Coming soon...

### Advanced usage

Coming soon...

#### Auth Driver

Coming soon...

##### Configuration

Coming soon...

##### Usage

Coming soon...
