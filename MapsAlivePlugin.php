<?php

class MapsAlivePlugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array(
        'config',
        'config_form',
        'define_routes',
        'initialize'
    );

    protected $_filters = array(
    );

    public function hookConfig()
    {
        MapsAliveConfig::saveConfiguration();
    }

    public function hookConfigForm()
    {
        require dirname(__FILE__) . '/config_form.php';
    }

    public function hookDefineRoutes($args)
    {
        $args['router']->addConfig(new Zend_Config_Ini(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'routes.ini', 'routes'));
    }

    public function hookInitialize()
    {
        add_translation_source(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'languages');
    }
}
