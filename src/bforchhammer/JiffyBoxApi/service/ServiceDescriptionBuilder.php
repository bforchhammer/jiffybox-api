<?php

namespace bforchhammer\JiffyBoxApi\service;

use Guzzle\Service\Description\Operation;
use Guzzle\Service\Description\Parameter;
use Guzzle\Service\Description\ServiceDescription;

class ServiceDescriptionBuilder
{
    private $serviceDescription = null;
    private $operations = array();
    private $outdated = false;

    /**
     * @return ServiceDescription
     */
    public function getServiceDescription()
    {
        if (empty($this->serviceDescription) || $this->outdated) {
            $this->initialiseServiceDescription();
        }

        return $this->serviceDescription;
    }

    public function addOperation(Operation $operation)
    {
        $operationName = $operation->getName();
        if (!isset($this->operations[$operationName])) {
            $this->operations[$operationName] = array();
        }
        $this->operations[$operationName][] = $operation;

        $this->outdated = true;
    }

    protected function initialiseServiceDescription()
    {
        $this->serviceDescription = ServiceDescription::factory(array(
            'name' => 'JiffyBox',
            'apiVersion' => 'v1.0',
            'baseUrl' => 'https://api.jiffybox.de/{token}/{version}',
            'description' => 'JiffyBox WebService API',
        ));

        foreach ($this->operations as $operationName => $operationSet) {
            $duplicateNames = (count($operationSet) > 1);

            foreach ($operationSet as $operation) {
                // If we have multiple operations with the same name, try to improve their names by appending the names
                // of required parameters, e.g., "ById".
                if ($duplicateNames) {
                    $this->increaseOperationNameSpecificity($operation);
                }

                // If the operation name has already been registered, then rename it by appending a number until a
                // unique name has been found. This is extremely unlikely given the naming scheme and suffix above.
                $newOperationName = $operationName;
                $i = 2;
                while ($this->serviceDescription->hasOperation($newOperationName)) {
                    $newOperationName = $operationName . $i;
                    $i++;
                }
                if ($operationName != $newOperationName) {
                    $operation->setName($newOperationName);
                }

                $this->serviceDescription->addOperation($operation);
            }
        }
    }

    public function parseOperation($module, $path, $method, $details)
    {
        static $validHttpMethods = array('GET', 'POST', 'PUT', 'DELETE');

        if (!in_array($method, $validHttpMethods)) {
            throw new \InvalidArgumentException('Received invalid HTTP method ' . $method . '. Expected one of: ' . implode(', ', $validHttpMethods));
        }

        $operation = new Operation(array(
            'httpMethod' => $method,
            'uri' => $this->getOperationUri($module, $path),
            'summary' => isset($details['description']) ? $details['description'] : '',
        ));

        // Extract all parameters from URI.
        preg_match_all("/<([\w]+)>/", $path, $matches);
        foreach ($matches[1] as $name) {
            $operation->addParam(new Parameter(array(
                'name' => $name,
                'location' => 'uri',
                'description' => '',
                'required' => TRUE,
            )));
        }

        /*foreach ($commandDetails['parameters']['must'] as $key => $value) {
        }
        foreach ($commandDetails['parameters']['may'] as $key => $value) {
        }*/

        $this->setOperationName($operation, $module, $path);

        $this->addOperation($operation);

        return $operation;
    }

    private static function getOperationUri($module, $path)
    {
        $uri = str_replace(array('<', '>'), array('{', '}'), $path);
        return $module . $uri;
    }

    private static function setOperationName(Operation $operation, $module, $path)
    {
        $parameters = $operation->getParams();
        $requiredParamCount = 0;
        foreach ($parameters as $param) {
            if ($param->getRequired()) $requiredParamCount++;
        }

        $prefix = strtolower($operation->getHttpMethod());
        switch ($operation->getHttpMethod()) {
            case 'GET':
                if ($requiredParamCount == 0) $prefix = 'list';
                else $prefix = 'get';
                break;
            case 'POST':
                $prefix = 'create';
                break;
            case 'PUT':
                $prefix = 'update';
                break;
            case 'DELETE':
                $prefix = 'delete';
        }

        $suffix = str_replace('/', '', strip_tags($path));
        $operationName = $prefix . ucfirst($module) . ucfirst($suffix);
        $operationName = trim($operationName, '-');

        $operation->setName($operationName);
    }

    /**
     * Increase specificity of the given operation's name by appending strings like "ById".
     *
     * Only required parameters of the operation are appended this way.
     * Multiple required parameters are separated by 'And'.
     */
    protected static function increaseOperationNameSpecificity(Operation $operation)
    {
        $suffix = '';
        $parameters = $operation->getParams();
        foreach ($parameters as $param) {
            if ($param->getRequired()) {
                $suffix .= empty($suffix) ? 'By' : 'And';
                $suffix .= ucfirst($param->getName());
            }
        }
        $operation->setName($operation->getName() . $suffix);
    }

}