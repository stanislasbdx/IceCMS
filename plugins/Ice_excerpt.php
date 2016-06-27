<?php

class IceExcerpt extends AbstractIcePlugin
{
    protected $enabled = false;
    protected $dependsOn = array('IceParsePagesContent');

    public function onConfigLoaded(array &$config)
    {
        if (!isset($config['excerpt_length'])) {
            $config['excerpt_length'] = 50;
        }
    }

    public function onSinglePageLoaded(array &$pageData)
    {
        if (!isset($pageData['excerpt'])) {
            $pageData['excerpt'] = $this->createExcerpt(
                strip_tags($pageData['content']),
                $this->getConfig('excerpt_length')
            );
        }
    }

    protected function createExcerpt($string, $wordLimit)
    {
        $words = explode(' ', $string);
        if (count($words) > $wordLimit) {
            return trim(implode(' ', array_slice($words, 0, $wordLimit))) . '&hellip;';
        }
        return $string;
    }
}