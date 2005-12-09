<?php

require_once 'PEAR/PackageFileManager.php';

$version = '0.2.0';
$notes = <<<EOT
- do not fix case in listUsers()
- unified case fixing in the list*() methods
- split index and constraint handling
- quote identifiers where possible inside the manager methods depending on
  the new 'quote_identifier' option (defaults to off)
- refactored get*Declaration() methods to use getTypeDeclaration()
- setting in_transaction to false on disconnect
- added new Function modules to handle difference in SQL functions
- force rollback() with open transactions on disconnect
- fixed table renaming
- escape floats to make sure they do not contain evil characters (bug #5608)
- split off manipulation queries into exec() method from the query() method *BC BREAK*
- added is_manip parameter to prepare() method which needs to be used for DML statements *BC BREAK*
- use lastInsertID() method in nextID()

open todo items:
- this driver needs a serious update as it's currently unmaintained/untested
EOT;

$package = new PEAR_PackageFileManager();

$result = $package->setOptions(
    array(
        'packagefile'       => 'package_fbsql.xml',
        'package'           => 'MDB2_Driver_fbsql',
        'summary'           => 'fbsql MDB2 driver',
        'description'       => 'This is the Frontbase SQL MDB2 driver.',
        'version'           => $version,
        'state'             => 'alpha',
        'license'           => 'BSD License',
        'filelistgenerator' => 'cvs',
        'include'           => array('*fbsql*'),
        'ignore'            => array('package_fbsql.php'),
        'notes'             => $notes,
        'changelogoldtonew' => false,
        'simpleoutput'      => true,
        'baseinstalldir'    => '/',
        'packagedirectory'  => './',
        'dir_roles'         => array(
            'docs' => 'doc',
             'examples' => 'doc',
             'tests' => 'test',
             'tests/templates' => 'test',
        ),
    )
);

if (PEAR::isError($result)) {
    echo $result->getMessage();
    die();
}

$package->addMaintainer('fmk', 'lead', 'Frank M. Kromann', 'frank@kromann.info');
$package->addMaintainer('lsmith', 'lead', 'Lukas Kahwe Smith', 'smith@pooteeweet.org');

$package->addDependency('php', '4.3.0', 'ge', 'php', false);
$package->addDependency('PEAR', '1.0b1', 'ge', 'pkg', false);
$package->addDependency('MDB2', '2.0.0beta7', 'ge', 'pkg', false);
$package->addDependency('fbsql', null, 'has', 'ext', false);

$package->addglobalreplacement('package-info', '@package_version@', 'version');

if (array_key_exists('make', $_GET) || (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] == 'make')) {
    $result = $package->writePackageFile();
} else {
    $result = $package->debugPackageFile();
}

if (PEAR::isError($result)) {
    echo $result->getMessage();
    die();
}
