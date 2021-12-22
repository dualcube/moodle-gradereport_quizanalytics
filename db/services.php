<?php

$services = array(
  'moodle-gradereport_quizanalytics' => array(                      //the name of the web service
      'functions' => array ('moodle-gradereport_quizanalytics_graded_users_selector'), //web service functions of this service
      'requiredcapability' => '',                //if set, the web service user need this capability to access 
                                                 //any function of this service. For example: 'some/capability:specified'                 
      'restrictedusers' =>0,                      //if enabled, the Moodle administrator must link some user to this service
                                                  //into the administration
      'enabled'=> 1,                               //if enabled, the service can be reachable on a default installation
      'shortname'=>'gradereport_quizanalytics' //the short name used to refer to this service from elsewhere including when fetching a token
   )
);

$functions = array(
    'moodle-gradereport_quizanalytics_graded_users_selector' => array(
        'classname' => 'moodle-gradereport_quizanalytics_external',
        'methodname' => 'quizanalytics_graded_users_selector',
        'classpath' => 'grade/report/quizanalytics/externallib.php',
        'description' => 'Rendering data',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ),
);