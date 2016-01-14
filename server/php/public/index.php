<?php
    require_once('config.php');
    require(constant('HOCKEY_INCLUDE_DIR'));
    
    $router = Router::get(array(
    	'appDirectory'       => dirname(__FILE__).DIRECTORY_SEPARATOR,
    	'RCDirectories' => array(
	    		'releases'	=> 'Releases',
				'i01'		=> 'i01'
    	),
    	'SnapshotDirectories' => array(
	    		'nightly'	=> 'Nightly',
				'nightlyi01'=> 'Nightly i01'
    	)
    ));

    $page = new Renderer($router->app, $router);
    $page->setDevice(Device::currentDevice());
    
    echo $page;
?>