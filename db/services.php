<?php

$services = array(
    'moodle_gradereport_quizanalytics' => array(  // the name of the web service
        'functions' => array('moodle_quizanalytics_analytic'), // web service functions of this service
        'requiredcapability' => '', // if set, the web service user need this capability to access // any function of this service. For example: 'some/capability:specified'                 
        'restrictedusers' => 0, // if enabled, the Moodle administrator must link some user to this service// into the administration
        'enabled' => 1, // if enabled, the service can be reachable on a default installation
    )
);

$functions = array(
    'moodle_quizanalytics_analytic' => array(
        'classname' => 'moodle_gradereport_quizanalytics_external',
        'methodname' => 'quizanalytics_analytic',
        'classpath' => 'grade/report/quizanalytics/externallib.php',
        'description' => 'Get Analytics data',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    )
);
