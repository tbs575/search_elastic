<?php
if (!\defined('PHPUNIT_RUN')) {
	\define('PHPUNIT_RUN', 1);
}
require_once __DIR__.'/../../../../lib/base.php';
\OC::$composerAutoloader->addPsr4('Test\\', OC::$SERVERROOT . '/tests/lib/', true);
\OC::$composerAutoloader->addPsr4('Tests\\', OC::$SERVERROOT . '/tests/', true);
require_once __DIR__ . '/../../vendor/autoload.php';
\OC_Hook::clear();
\OC_App::loadApp('search_elastic');
