# WP Readme to Markdown

[![Packagist](https://img.shields.io/packagist/v/wpreadme2markdown/wp2md.svg?maxAge=2592000)](https://packagist.org/packages/wpreadme2markdown/wp2md)
[![Code Climate](https://img.shields.io/codeclimate/maintainability/wpreadme2markdown/wp2md.svg?maxAge=2592000)](https://codeclimate.com/github/wpreadme2markdown/wp2md)

Convert WordPress Plugin Readme Files to GitHub Flavored Markdown.
The tool is built on the [WP Readme to Markdown Library](https://github.com/wpreadme2markdown/wp-readme-to-markdown)

## Features

* Converts headings
* Formats contributors, donate link, etc.
* Inserts screenshots

## Usage

    # with files as params
    wp2md -i readme.txt -o README.md
    # or with unix pipes
    wp2md < readme.txt > README.md

## Installation

### Composer (recommended)

Add a composer dependency to your project:

    "require-dev": {
        "wpreadme2markdown/wp2md": "*"
    }

The binary will be `vendor/bin/wp2md`

### Download binary

You may install WP2MD binary globally

    sudo wget https://github.com/wpreadme2markdown/wp2md/releases/latest/download/wp2md.phar -O /usr/local/bin/wp2md
    sudo chmod a+x /usr/local/bin/wp2md

## PHAR compilation

    # install dependencies
    composer install
    # run pake build script
    composer pake phar

Executable PHAR archive will be created as `build/wp2md.phar`

* This assumes composer is installed as a package in your operating system.
  If not, replace `composer` with php command and your composer.phar location
  (i.e. `php ../phars/composer.phar`)

## Web Version

 Visit [this GitHub page](https://github.com/wpreadme2markdown/web) for the web version and a link to its running instance
