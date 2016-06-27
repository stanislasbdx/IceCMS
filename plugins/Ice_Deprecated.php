<?php 

class IceDeprecated extends AbstractIcePlugin
{
    protected $enabled = false;
    protected $requestFile;

    public function onPluginsLoaded(array &$plugins)
    {
        if (!empty($plugins)) {
            foreach ($plugins as $plugin) {
                if (!is_a($plugin, 'IcePluginInterface')) {
                    if (!$this->isStatusChanged()) {
                        $this->setEnabled(true, true, true);
                    }
                    break;
                }
            }
        } else {
            if (!$this->isStatusChanged()) {
                $this->setEnabled(true, true, true);
            }
        }

        if ($this->isEnabled()) {
            $this->triggerEvent('plugins_loaded');
        }
    }

    public function onConfigLoaded(array &$config)
    {
        $this->defineConstants();
        $this->loadRootDirConfig($config);
        $this->enablePlugins();
        $GLOBALS['config'] = &$config;

        $this->triggerEvent('config_loaded', array(&$config));
    }

    protected function defineConstants()
    {
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', $this->getRootDir());
        }
        if (!defined('CONFIG_DIR')) {
            define('CONFIG_DIR', $this->getConfigDir());
        }
        if (!defined('LIB_DIR')) {
            $IceReflector = new ReflectionClass('Ice');
            define('LIB_DIR', dirname($IceReflector->getFileName()) . '/');
        }
        if (!defined('PLUGINS_DIR')) {
            define('PLUGINS_DIR', $this->getPluginsDir());
        }
        if (!defined('THEMES_DIR')) {
            define('THEMES_DIR', $this->getThemesDir());
        }
        if (!defined('CONTENT_DIR')) {
            define('CONTENT_DIR', $this->getConfig('content_dir'));
        }
        if (!defined('CONTENT_EXT')) {
            define('CONTENT_EXT', $this->getConfig('content_ext'));
        }
    }

    protected function loadRootDirConfig(array &$realConfig)
    {
        if (file_exists($this->getRootDir() . 'config.php')) {
            $config = null;
            require($this->getRootDir() . 'config.php');

            if (is_array($config)) {
                if (isset($config['base_url'])) {
                    $config['base_url'] = rtrim($config['base_url'], '/') . '/';
                }
                if (isset($config['content_dir'])) {
                    $config['content_dir'] = rtrim($config['content_dir'], '/\\') . '/';
                }

                $realConfig = $config + $realConfig;
            }
        }
    }

    protected function enablePlugins()
    {
        $plugins = $this->getPlugins();
        if (isset($plugins['IceParsePagesContent'])) {
            if (!$plugins['IceParsePagesContent']->isStatusChanged()) {
                $plugins['IceParsePagesContent']->setEnabled(true, true, true);
            }
        }
        if (isset($plugins['IceExcerpt'])) {
            if (!$plugins['IceExcerpt']->isStatusChanged()) {
                $plugins['IceExcerpt']->setEnabled(true, true, true);
            }
        }
    }

    public function onRequestUrl(&$url)
    {
        $this->triggerEvent('request_url', array(&$url));
    }

    public function onRequestFile(&$file)
    {
        $this->requestFile = &$file;
    }

    public function onContentLoading(&$file)
    {
        $this->triggerEvent('before_load_content', array(&$file));
    }

    public function onContentLoaded(&$rawContent)
    {
        $this->triggerEvent('after_load_content', array(&$this->requestFile, &$rawContent));
    }

    public function on404ContentLoading(&$file)
    {
        $this->triggerEvent('before_404_load_content', array(&$file));
    }

    public function on404ContentLoaded(&$rawContent)
    {
        $this->triggerEvent('after_404_load_content', array(&$this->requestFile, &$rawContent));
    }

    public function onMetaHeaders(array &$headers)
    {
        $this->triggerEvent('before_read_file_meta', array(&$headers));
    }

    public function onMetaParsed(array &$meta)
    {
        $this->triggerEvent('file_meta', array(&$meta));
    }

    public function onContentParsing(&$rawContent)
    {
        $this->triggerEvent('before_parse_content', array(&$rawContent));
    }

    public function onContentParsed(&$content)
    {
        $this->triggerEvent('after_parse_content', array(&$content));

        $this->triggerEvent('content_parsed', array(&$content));
    }

    public function onSinglePageLoaded(array &$pageData)
    {
        $this->triggerEvent('get_page_data', array(&$pageData, $pageData['meta']));
    }

    public function onPagesLoaded(
        array &$pages,
        array &$currentPage = null,
        array &$previousPage = null,
        array &$nextPage = null
    ) {
        $plainPages = array();
        foreach ($pages as &$pageData) {
            $plainPages[] = &$pageData;
        }
        unset($pageData);

        $this->triggerEvent('get_pages', array(&$plainPages, &$currentPage, &$previousPage, &$nextPage));

        $pages = array();
        foreach ($plainPages as &$pageData) {
            if (!isset($pageData['id'])) {
                $urlPrefixLength = strlen($this->getBaseUrl()) + intval(!$this->isUrlRewritingEnabled());
                $pageData['id'] = substr($pageData['url'], $urlPrefixLength);
            }

            $id = $pageData['id'];
            for ($i = 1; isset($pages[$id]); $i++) {
                $id = $pageData['id'] . '~dup' . $i;
            }

            $pages[$id] = &$pageData;
        }
    }

    public function onTwigRegistration()
    {
        $this->triggerEvent('before_twig_register');
    }

    public function onPageRendering(Twig_Environment &$twig, array &$twigVariables, &$templateName)
    {
        $fileExtension = '';
        if (($fileExtensionPos = strrpos($templateName, '.')) !== false) {
            $fileExtension = substr($templateName, $fileExtensionPos);
            $templateName = substr($templateName, 0, $fileExtensionPos);
        }

        $this->triggerEvent('before_render', array(&$twigVariables, &$twig, &$templateName));

        $templateName = $templateName . $fileExtension;
    }

    public function onPageRendered(&$output)
    {
        $this->triggerEvent('after_render', array(&$output));
    }

    protected function triggerEvent($eventName, array $params = array())
    {
        foreach ($this->getPlugins() as $plugin) {
            if (method_exists($plugin, $eventName)) {
                call_user_func_array(array($plugin, $eventName), $params);
            }
        }
    }
}