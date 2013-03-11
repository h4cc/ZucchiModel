<?php
return array(
    'service_manager' => array(
        'factories' => array(
            'zucchimodel.modelmanager' => function ($sm) {
                $manager = new ZucchiModel\ModelManager();
                return $manager;
            },
            'Zend\Db\Adapter\Adapter' => 'Zend\Db\Adapter\AdapterServiceFactory',
        ),
        'aliases' => array(
            'modelmanager' => 'zucchimodel.modelmanager',
        )
    ),
);