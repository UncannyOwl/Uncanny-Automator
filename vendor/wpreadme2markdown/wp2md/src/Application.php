<?php

/**
 * @author Christian Archer <sunchaser@sunchaser.info>
 * @copyright © 2019, Christian Archer
 * @license MIT
 */

declare(strict_types=1);

namespace WPReadme2Markdown\Cli;

use Composer\InstalledVersions;
use Symfony\Component\Console\Application as ConsoleApplication;

class Application extends ConsoleApplication
{
    private $libVersion;

    public function __construct(string $version)
    {
        if ($version === '@package_version@') {
            // if not a root package, get version from composer
            $version = InstalledVersions::getRootPackage()['name'] === 'wpreadme2markdown/wp2md' ?
                'UNKNOWN' :
                InstalledVersions::getPrettyVersion('wpreadme2markdown/wp2md');
        }

        parent::__construct('WP Readme to Markdown CLI', $version);

        $this->add(new Convert());
        $this->setDefaultCommand('wp2md', true);
        $this->libVersion = InstalledVersions::getPrettyVersion('wpreadme2markdown/wpreadme2markdown');
    }

    public function getLongVersion(): string
    {
        return parent::getLongVersion() . PHP_EOL .
            sprintf('WP Readme to Markdown Library <info>%s</info>', $this->libVersion);
    }
}
