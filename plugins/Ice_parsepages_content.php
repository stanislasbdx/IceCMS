<?php
class IceParsePagesContent extends AbstractIcePlugin
{
    protected $enabled = false;

    public function onSinglePageLoaded(array &$pageData)
    {
        if (!isset($pageData['content'])) {
            $pageData['content'] = $this->prepareFileContent($pageData['raw_content'], $pageData['meta']);
            $pageData['content'] = $this->parseFileContent($pageData['content']);
        }
    }
}
