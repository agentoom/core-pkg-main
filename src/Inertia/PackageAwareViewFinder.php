<?php

namespace Agentoom\Core\Inertia;

use Composer\InstalledVersions;
use Illuminate\View\FileViewFinder;
use InvalidArgumentException;

/**
 * Resolves Inertia page components that live inside optional packages.
 *
 * Core pages keep the plain `Admin/Page` name and resolve through the
 * configured page paths. Package pages use a `package::Path/To/Page` name and
 * resolve inside that package's own `resources/js/Pages` directory, so a
 * package can be installed or uninstalled without any core configuration
 * change.
 */
class PackageAwareViewFinder extends FileViewFinder
{
    /**
     * @return string
     */
    public function find($name)
    {
        if (str_contains($name, '::')) {
            return $this->findPackagePage($name);
        }

        return parent::find($name);
    }

    /**
     * Resolve "package::Path/To/Page" against the package's own page directory.
     */
    protected function findPackagePage(string $name): string
    {
        [$package, $page] = explode('::', $name, 2);

        $packageName = InstalledVersions::isInstalled("agentoom/{$package}")
            ? "agentoom/{$package}"
            : (InstalledVersions::isInstalled("agentoom/core-{$package}") ? "agentoom/core-{$package}" : null);

        $installPath = $packageName !== null
            ? InstalledVersions::getInstallPath($packageName)
            : null;

        if ($installPath !== null) {
            foreach ($this->extensions as $extension) {
                $path = "{$installPath}/resources/js/Pages/{$page}.{$extension}";

                if ($this->files->exists($path)) {
                    return $path;
                }
            }
        }

        throw new InvalidArgumentException("View [{$name}] not found.");
    }
}
