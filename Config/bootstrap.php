<?php

    Configure::write('predictionIO', array(
        'appkey' => 'your-key',
        'userModel' => 'User',
        'engine' => ''
    ));

    require_once App::pluginPath('PredictionIO') . 'vendor' . DS . 'autoload.php';