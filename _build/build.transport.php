<?php

$tstart = explode(' ', microtime());
$tstart = $tstart[1] + $tstart[0];
set_time_limit(0);
 
/* задаем имя пакета */
define('PKG_NAME','ResizeImageOnUpload');
define('PKG_NAME_LOWER','resizeimageonupload');
define('PKG_VERSION','1.0');
define('PKG_RELEASE','rc1');
 
/* задаем пути для упаковщика */
$root = dirname(dirname(__FILE__)).'/';
$sources = array(
    'root' => $root,
    'build' => $root.'_build/',
    'data' => $root.'_build/data/',
    'resolvers' => $root.'_build/resolvers/',
    // 'chunks' => $root.'core/components/'.PKG_NAME_LOWER.'/chunks/',
    'lexicon' => $root.'core/components/'.PKG_NAME_LOWER.'/lexicon/',
    'docs' => $root.'core/components/'.PKG_NAME_LOWER.'/docs/',
    'elements' => $root.'core/components/'.PKG_NAME_LOWER.'/elements/',
    // 'source_assets' => $root.'assets/components/'.PKG_NAME_LOWER,
    'source_core' => $root.'core/components/'.PKG_NAME_LOWER,
);
unset($root);
 
/* override with your own defines here (see build.config.sample.php) */
require_once $sources['build'].'build.config.php';
require_once MODX_CORE_PATH.'model/modx/modx.class.php';
 
$modx= new modX();
$modx->initialize('mgr');
echo '<pre>'; /* used for nice formatting of log messages */
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');
 
$modx->loadClass('transport.modPackageBuilder','',false, true);
$builder = new modPackageBuilder($modx);
$builder->createPackage(PKG_NAME_LOWER,PKG_VERSION,PKG_RELEASE);
$builder->registerNamespace(PKG_NAME_LOWER,false,true,'{core_path}components/'.PKG_NAME_LOWER.'/');


/*------------== Создаем категорию ==-----------------*/
// чтобы запихать плагин в категорию и дать красивый вид имени
// в общем списке пакетов при установке
$modx->log(modX::LOG_LEVEL_INFO,'Create category...');flush();
$category= $modx->newObject('modCategory');
$category->set('id',1);
$category->set('category',PKG_NAME);


/*------------== Добавляем плагин ==-----------------*/
$modx->log(modX::LOG_LEVEL_INFO,'Packaging in plugins...');flush();
$plugin = include $sources['data'].'transport.plugins.php';
if (empty($plugin)) $modx->log(modX::LOG_LEVEL_ERROR,'Could not package in plugins.');flush();
// $category->addMany($plugin);
/*------------== Добавляем события плагина ==-----------------*/
$events = include $sources['data'].'transport.plugins.events.php';
if (is_array($events) && !empty($events)) {
    $plugin->addMany($events);
	/* add plugin to category */
	$category->addMany($plugin);
    $modx->log(xPDO::LOG_LEVEL_INFO,'Packaged in '.count($events).' Plugin Events.'); flush();
} else {
    $modx->log(xPDO::LOG_LEVEL_ERROR,'Could not find plugin events!'); flush();
}
unset($events);
unset($plugin);

$attributes = array(
    xPDOTransport::UNIQUE_KEY => 'category',
    xPDOTransport::PRESERVE_KEYS => false,
    xPDOTransport::UPDATE_OBJECT => true,
    xPDOTransport::RELATED_OBJECTS => true,
    xPDOTransport::RELATED_OBJECT_ATTRIBUTES => array (
        'Snippets' => array(
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::UNIQUE_KEY => 'name',
        ),
        'Chunks' => array(
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::UNIQUE_KEY => 'name',
        ),
        'Plugins' => array(
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::UNIQUE_KEY => 'name',
            xPDOTransport::RELATED_OBJECTS => true,
            xPDOTransport::RELATED_OBJECT_ATTRIBUTES => array (
                'PluginEvents' => array(
                    xPDOTransport::PRESERVE_KEYS => true,
                    xPDOTransport::UPDATE_OBJECT => false,
                    xPDOTransport::UNIQUE_KEY => array('pluginid','event'),
                ),
            ),
        ),
    )
);
// $vehicle = $builder->createVehicle($plugin, $attributes);
$vehicle = $builder->createVehicle($category, $attributes);
$builder->putVehicle($vehicle);

/*------------== Добавляем информацию к пакету ==-----------------*/
$modx->log(modX::LOG_LEVEL_INFO,'Adding package attributes and setup options...');flush();
$builder->setPackageAttributes(array(
    'package_name' => PKG_NAME,
    'name' => PKG_NAME,
    'license' => file_get_contents($sources['docs'].'license.txt'),
    'readme' => file_get_contents($sources['docs'].'readme.txt'),
    'changelog' => file_get_contents($sources['docs'].'changelog.txt'),
	// яркий пример QIP
	// https://github.com/splittingred/Quip/blob/develop/_build/build.transport.php
    // 'setup-options' => array(
        // 'source' => $sources['build'].'setup.options.php',
    // ),
));

/*------------== Добавляем пространство имен ==-----------------*/
// нет нужды так как уже зарегистрированно в строке $builder->registerNamespace
// $namespace = $modx->newObject('modNamespace');
// $namespace->set('name',PKG_NAME_LOWER);
// $namespace->set('path','{core_path}components/'.PKG_NAME_LOWER.'/');
// $vehicle = $builder->createVehicle($namespace,array(
    // xPDOTransport::UNIQUE_KEY => 'name',
    // xPDOTransport::PRESERVE_KEYS => true,
    // xPDOTransport::UPDATE_OBJECT => true,
// ));
// $builder->putVehicle($vehicle);
// $modx->log(modX::LOG_LEVEL_INFO,'Packaged in '.PKG_NAME_LOWER.' namespace.');flush();
// unset($vehicle,$namespace);

/*------------== Добавление файловых резольверов (Resolvers) ==-----------------*/
$modx->log(modX::LOG_LEVEL_INFO,'Adding file resolvers to category...');flush();
// $vehicle->resolve('file',array(
    // 'source' => $sources['source_assets'],
    // 'target' => "return MODX_ASSETS_PATH.'components/';",
// ));
// $vehicle->resolve('file',array(
    // 'source' => $sources['source_core'],
    // 'target' => "return MODX_CORE_PATH.'components/';",
// ));

 
/*------------== Упаковываем ==-----------------*/
/* zip up package */
$modx->log(modX::LOG_LEVEL_INFO,'Packing up transport package zip...');flush();
$builder->pack();
 
$tend= explode(" ", microtime());
$tend= $tend[1] + $tend[0];
$totalTime= sprintf("%2.4f s",($tend - $tstart));
$modx->log(modX::LOG_LEVEL_INFO,"\n<br />Package Built.<br />\nExecution time: {$totalTime}\n");flush();
exit ();