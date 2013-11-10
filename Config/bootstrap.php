<?php

	Configure::write('predictionIO', array(
		'appkey' => 'your-key',
		'userModel' => 'User',
		'engine' => ''
	));

	$files = array(
	    APP . 'Vendor' . DS . 'autoload.php',
	    App::pluginPath('PredictionIO') . 'vendor' . DS . 'autoload.php'
	);

	foreach ($files as $file) {
	    if (file_exists($file)) {
	        require_once $file;
	        break;
	    }
	}
