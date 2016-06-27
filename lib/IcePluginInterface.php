<?php

interface IcePluginInterface
{
    public function __construct(Ice $Ice);

    public function handleEvent($eventName, array $params);

    public function setEnabled($enabled, $recursive = true, $auto = false);

    public function isEnabled();

    public function isStatusChanged();

    public function getDependencies();

    public function getDependants();

    public function getIce();
}
