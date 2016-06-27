<?php

abstract class AbstractIcePlugin implements IcePluginInterface
{
    private $Ice;

    protected $enabled = true;

    protected $statusChanged = false;

    protected $dependsOn = array();

    private $dependants;

    public function __construct(Ice $Ice)
    {
        $this->Ice = $Ice;
    }

    public function handleEvent($eventName, array $params)
    {
        if ($eventName === 'onConfigLoaded') {
            $pluginEnabled = $this->getConfig(get_called_class() . '.enabled');
            if ($pluginEnabled !== null) {
                $this->setEnabled($pluginEnabled);
            } else {
                $pluginConfig = $this->getConfig(get_called_class());
                if (is_array($pluginConfig) && isset($pluginConfig['enabled'])) {
                    $this->setEnabled($pluginConfig['enabled']);
                }
            }
        }

        if ($this->isEnabled() || ($eventName === 'onPluginsLoaded')) {
            if (method_exists($this, $eventName)) {
                call_user_func_array(array($this, $eventName), $params);
            }
        }
    }

    public function setEnabled($enabled, $recursive = true, $auto = false)
    {
        $this->statusChanged = (!$this->statusChanged) ? !$auto : true;
        $this->enabled = (bool) $enabled;

        if ($enabled) {
            $this->checkDependencies($recursive);
        } else {
            $this->checkDependants($recursive);
        }
    }

    public function isEnabled()
    {
        return $this->enabled;
    }

    public function isStatusChanged()
    {
        return $this->statusChanged;
    }

    public function getIce()
    {
        return $this->Ice;
    }

    public function __call($methodName, array $params)
    {
        if (method_exists($this->getIce(), $methodName)) {
            return call_user_func_array(array($this->getIce(), $methodName), $params);
        }

        throw new BadMethodCallException(
            'Call to undefined method ' . get_class($this->getIce()) . '::' . $methodName . '() '
            . 'through ' . get_called_class() . '::__call()'
        );
    }

    protected function checkDependencies($recursive)
    {
        foreach ($this->getDependencies() as $pluginName) {
            try {
                $plugin = $this->getPlugin($pluginName);
            } catch (RuntimeException $e) {
                throw new RuntimeException(
                    "Unable to enable plugin '" . get_called_class() . "':"
                    . "Required plugin '" . $pluginName . "' not found"
                );
            }

            if (is_a($plugin, 'IcePluginInterface') && !$plugin->isEnabled()) {
                if ($recursive) {
                    if (!$plugin->isStatusChanged()) {
                        $plugin->setEnabled(true, true, true);
                    } else {
                        throw new RuntimeException(
                            "Unable to enable plugin '" . get_called_class() . "':"
                            . "Required plugin '" . $pluginName . "' was disabled manually"
                        );
                    }
                } else {
                    throw new RuntimeException(
                        "Unable to enable plugin '" . get_called_class() . "':"
                        . "Required plugin '" . $pluginName . "' is disabled"
                    );
                }
            }
        }
    }

    public function getDependencies()
    {
        return (array) $this->dependsOn;
    }

    protected function checkDependants($recursive)
    {
        $dependants = $this->getDependants();
        if (!empty($dependants)) {
            if ($recursive) {
                foreach ($this->getDependants() as $pluginName => $plugin) {
                    if ($plugin->isEnabled()) {
                        if (!$plugin->isStatusChanged()) {
                            $plugin->setEnabled(false, true, true);
                        } else {
                            throw new RuntimeException(
                                "Unable to disable plugin '" . get_called_class() . "': "
                                . "Required by manually enabled plugin '" . $pluginName . "'"
                            );
                        }
                    }
                }
            } else {
                $dependantsList = 'plugin' . ((count($dependants) > 1) ? 's' : '') . ' ';
                $dependantsList .= "'" . implode("', '", array_keys($dependants)) . "'";
                throw new RuntimeException(
                    "Unable to disable plugin '" . get_called_class() . "': "
                    . "Required by " . $dependantsList
                );
            }
        }
    }

    public function getDependants()
    {
        if ($this->dependants === null) {
            $this->dependants = array();
            foreach ($this->getPlugins() as $pluginName => $plugin) {
                if (is_a($plugin, 'IcePluginInterface')) {
                    $dependencies = $plugin->getDependencies();
                    if (in_array(get_called_class(), $dependencies)) {
                        $this->dependants[$pluginName] = $plugin;
                    }
                }
            }
        }

        return $this->dependants;
    }
}
