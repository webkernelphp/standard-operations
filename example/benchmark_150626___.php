<?php
require __DIR__ . '/third_party/autoload.php';
require __DIR__ . '/bootstrap/app.php';

use Composer\Factory;
use Composer\IO\NullIO;

$io        = new NullIO();
$composer  = Factory::create($io);
$config    = $composer->getConfig();
$vendorDir = $config->get('vendor-dir');
$ref       = new ReflectionProperty($config, 'baseDir');
$ref->setAccessible(true);
$baseDir   = $ref->getValue($config);
$jsonPath  = $baseDir . '/composer.json';

// ---------------------------------------------------------------------------
// Group: Project root
// ---------------------------------------------------------------------------
$benchBasePath = fn() => base_path();

$benchProjectRoot = fn() => project_root();

$benchWKRoot = fn() => \Webkernel\StdLoc\WebkernelComposer::root();

$benchInstalledVersions = fn() => dirname(
    \Composer\InstalledVersions::getInstallPath('filament/filament'), 5
);

$benchResolveFilename = function () {
    static $r = null;
    if ($r === null) {
        $f   = (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName();
        $dir = substr($f, 0, strrpos($f, DIRECTORY_SEPARATOR));
        $cfg = require $dir . '/installed.php';
        $r   = resolveFilename($cfg['root']['install_path'], iterations: 10000);
    }
    return $r;
};

// ---------------------------------------------------------------------------
// Group: Vendor dir
// ---------------------------------------------------------------------------
$benchGlobalVendorDir = function () use ($vendorDir) { return $vendorDir; };

$benchWKVendorDir = fn() => \Webkernel\StdLoc\WebkernelComposer::vendorDir();

$benchVendorDirPHP = function () {
    static $r = null;
    if ($r === null) {
        $v   = \Webkernel\StdLoc\WebkernelComposer::vendorDir();
        $ins = require $v . '/composer/installed.php';
        $r   = $ins['root']['install_path'];
    }
    return $r;
};

// ---------------------------------------------------------------------------
// Group: composer.json path
// ---------------------------------------------------------------------------
$benchGlobalJsonPath = function () use ($jsonPath) { return $jsonPath; };

$benchBaseDirConcat  = function () use ($baseDir) { return $baseDir . '/composer.json'; };

$benchWKRootConcat   = fn() => \Webkernel\StdLoc\WebkernelComposer::root() . '/composer.json';

// ---------------------------------------------------------------------------
// Run
// ---------------------------------------------------------------------------
webkernel_functions_benchmark([

    'Project root' => [
        'base_path()'       => $benchBasePath,
        'project_root()'    => $benchProjectRoot,
        'WK::root()'        => $benchWKRoot,
        'InstalledVersions' => $benchInstalledVersions,
        'resolveFilename()' => $benchResolveFilename,
    ],

    'Vendor dir' => [
        'global $vendorDir' => $benchGlobalVendorDir,
        'WK::vendorDir()'   => $benchWKVendorDir,
        'vendorDir()+php'   => $benchVendorDirPHP,
    ],

    'Composer.json path' => [
        'global $jsonPath'  => $benchGlobalJsonPath,
        'global $baseDir./' => $benchBaseDirConcat,
        'WK::root()./'      => $benchWKRootConcat,
    ],

], iterations: 10000);
