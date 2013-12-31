<?php
namespace bforchhammer\JiffyBoxApi;

use Guzzle\Common\Collection;
use Guzzle\Service\Client;
use Guzzle\Service\Description\ServiceDescription;

class JiffyBoxClient extends Client {

    public static function factory($config = array())
    {
        // Provide a hash of default client configuration options
        $serviceDescription = ServiceDescription::factory('jiffybox.json');
        $default = array(
            'service_description' => $serviceDescription,
            'version' => $serviceDescription->getApiVersion(),
        );

        // The following values are required when creating the client
        $required = array(
            'service_description',
            'token',
            'version',
        );

        // Merge in default settings and validate the config
        $config = Collection::fromConfig($config, $default, $required);

        // Create a new JiffyBox client
        $client = new self('', $config);

        // Properly attach service description
        $client->setDescription($config->get('service_description'));

        return $client;
    }

    public function listModules() {
        $response = $this->getCommand('doc')->execute();
        return $response["result"];
    }

    public function getDocumentation($module) {
        $response = $this->getCommand('doc-module', array('module' => $module))->execute();
        return $response["result"];
    }

}