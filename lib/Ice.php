<?php 

class Ice
{
    const SORT_ASC = 0;
    const SORT_DESC = 1;
    const SORT_NONE = 2;
	
    protected $rootDir;
    protected $configDir;
    protected $pluginsDir;
    protected $themesDir;
    protected $locked = false;
    protected $plugins;
    protected $config;
    protected $requestUrl;
    protected $requestFile;
    protected $rawContent;
    protected $meta;
    protected $parsedown;
    protected $content;
    protected $pages;
    protected $currentPage;
    protected $previousPage;
    protected $nextPage;
    protected $twig;
    protected $twigVariables;

    public function __construct($rootDir, $configDir, $pluginsDir, $themesDir)
    {
        $this->rootDir = rtrim($rootDir, '/\\') . '/';
        $this->configDir = $this->getAbsolutePath($configDir);
        $this->pluginsDir = $this->getAbsolutePath($pluginsDir);
        $this->themesDir = $this->getAbsolutePath($themesDir);
    }

    public function getRootDir()
    {
        return $this->rootDir;
    }

    public function getConfigDir()
    {
        return $this->configDir;
    }

    public function getPluginsDir()
    {
        return $this->pluginsDir;
    }

    public function getThemesDir()
    {
        return $this->themesDir;
    }

    public function run()
    {
        $this->locked = true;

        $this->loadPlugins();
        $this->triggerEvent('onPluginsLoaded', array(&$this->plugins));

        $this->loadConfig();
        $this->triggerEvent('onConfigLoaded', array(&$this->config));

        if (!is_dir($this->getConfig('content_dir'))) {
            throw new RuntimeException('Invalid content directory "' . $this->getConfig('content_dir') . '"');
        }

        $this->evaluateRequestUrl();
        $this->triggerEvent('onRequestUrl', array(&$this->requestUrl));

        $this->discoverRequestFile();
        $this->triggerEvent('onRequestFile', array(&$this->requestFile));

        $this->triggerEvent('onContentLoading', array(&$this->requestFile));

        if (file_exists($this->requestFile)) {
            $this->rawContent = $this->loadFileContent($this->requestFile);
        } else {
            $this->triggerEvent('on404ContentLoading', array(&$this->requestFile));

            header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
            $this->rawContent = $this->load404Content($this->requestFile);

            $this->triggerEvent('on404ContentLoaded', array(&$this->rawContent));
        }

        $this->triggerEvent('onContentLoaded', array(&$this->rawContent));

        $headers = $this->getMetaHeaders();

        $this->triggerEvent('onMetaParsing', array(&$this->rawContent, &$headers));
        $this->meta = $this->parseFileMeta($this->rawContent, $headers);
        $this->triggerEvent('onMetaParsed', array(&$this->meta));

        $this->triggerEvent('onParsedownRegistration');
        $this->registerParsedown();

        $this->triggerEvent('onContentParsing', array(&$this->rawContent));

        $this->content = $this->prepareFileContent($this->rawContent, $this->meta);
        $this->triggerEvent('onContentPrepared', array(&$this->content));

        $this->content = $this->parseFileContent($this->content);
        $this->triggerEvent('onContentParsed', array(&$this->content));

        $this->triggerEvent('onPagesLoading');

        $this->readPages();
        $this->sortPages();
        $this->discoverCurrentPage();

        $this->triggerEvent('onPagesLoaded', array(
            &$this->pages,
            &$this->currentPage,
            &$this->previousPage,
            &$this->nextPage
        ));

        $this->triggerEvent('onTwigRegistration');
        $this->registerTwig();

        $this->twigVariables = $this->getTwigVariables();
        if (isset($this->meta['template']) && $this->meta['template']) {
            $templateName = $this->meta['template'];
        } else {
            $templateName = 'index';
        }
        if (file_exists($this->getThemesDir() . $this->getConfig('theme') . '/' . $templateName . '.twig')) {
            $templateName .= '.twig';
        } else {
            $templateName .= '.html';
        }

        $this->triggerEvent('onPageRendering', array(&$this->twig, &$this->twigVariables, &$templateName));

        $output = $this->twig->render($templateName, $this->twigVariables);
        $this->triggerEvent('onPageRendered', array(&$output));

        return $output;
    }

    protected function loadPlugins()
    {
        $this->plugins = array();
        $pluginFiles = $this->getFiles($this->getPluginsDir(), '.php');
        foreach ($pluginFiles as $pluginFile) {
            require_once($pluginFile);

            $className = preg_replace('/^[0-9]+-/', '', basename($pluginFile, '.php'));
            if (class_exists($className)) {
                $plugin = new $className($this);
                $className = get_class($plugin);

                $this->plugins[$className] = $plugin;
            } else {

            }
        }
    }

    public function getPlugin($pluginName)
    {
        if (isset($this->plugins[$pluginName])) {
            return $this->plugins[$pluginName];
        }

        throw new RuntimeException("Missing plugin '" . $pluginName . "'");
    }

    public function getPlugins()
    {
        return $this->plugins;
    }

    protected function loadConfig()
    {
        $config = null;
        if (file_exists($this->getConfigDir() . 'config.php')) {
            require($this->getConfigDir() . 'config.php');
        }

        $defaultConfig = array(
            'site_title' => 'Ice',
            'base_url' => '',
            'rewrite_url' => null,
            'theme' => 'default',
            'date_format' => '%D %T',
            'twig_config' => array('cache' => false, 'autoescape' => false, 'debug' => false),
            'pages_order_by' => 'alpha',
            'pages_order' => 'asc',
            'content_dir' => null,
            'content_ext' => '.md',
            'timezone' => ''
        );

        $this->config = is_array($this->config) ? $this->config : array();
        $this->config += is_array($config) ? $config + $defaultConfig : $defaultConfig;

        if (empty($this->config['base_url'])) {
            $this->config['base_url'] = $this->getBaseUrl();
        } else {
            $this->config['base_url'] = rtrim($this->config['base_url'], '/') . '/';
        }

        if ($this->config['rewrite_url'] === null) {
            $this->config['rewrite_url'] = $this->isUrlRewritingEnabled();
        }

        if (empty($this->config['content_dir'])) {
            if (is_dir($this->getRootDir() . 'content')) {
                $this->config['content_dir'] = $this->getRootDir() . 'content/';
            } else {
                $this->config['content_dir'] = $this->getRootDir() . 'content-sample/';
            }
        } else {
            $this->config['content_dir'] = $this->getAbsolutePath($this->config['content_dir']);
        }

        if (empty($this->config['timezone'])) {
            $this->config['timezone'] = @date_default_timezone_get();
        }
        date_default_timezone_set($this->config['timezone']);
    }

    public function setConfig(array $config)
    {
        if ($this->locked) {
            throw new LogicException("You cannot modify Ice's config after processing has started");
        }

        $this->config = $config;
    }

    public function getConfig($configName = null)
    {
        if ($configName !== null) {
            return isset($this->config[$configName]) ? $this->config[$configName] : null;
        } else {
            return $this->config;
        }
    }

    protected function evaluateRequestUrl()
    {
        $pathComponent = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
        if (($pathComponentLength = strpos($pathComponent, '&')) !== false) {
            $pathComponent = substr($pathComponent, 0, $pathComponentLength);
        }
        $this->requestUrl = (strpos($pathComponent, '=') === false) ? rawurldecode($pathComponent) : '';
        $this->requestUrl = trim($this->requestUrl, '/');
    }

    public function getRequestUrl()
    {
        return $this->requestUrl;
    }

    protected function discoverRequestFile()
    {
        if (empty($this->requestUrl)) {
            $this->requestFile = $this->getConfig('content_dir') . 'index' . $this->getConfig('content_ext');
        } else {
            $requestUrl = str_replace('\\', '/', $this->requestUrl);
            $requestUrlParts = explode('/', $requestUrl);

            $requestFileParts = array();
            foreach ($requestUrlParts as $requestUrlPart) {
                if (($requestUrlPart === '') || ($requestUrlPart === '.')) {
                    continue;
                } elseif ($requestUrlPart === '..') {
                    array_pop($requestFileParts);
                    continue;
                }

                $requestFileParts[] = $requestUrlPart;
            }

            if (empty($requestFileParts)) {
                $this->requestFile = $this->getConfig('content_dir') . 'index' . $this->getConfig('content_ext');
                return;
            }

            $this->requestFile = $this->getConfig('content_dir') . implode('/', $requestFileParts);
            if (is_dir($this->requestFile)) {
                $indexFile = $this->requestFile . '/index' . $this->getConfig('content_ext');
                if (file_exists($indexFile) || !file_exists($this->requestFile . $this->getConfig('content_ext'))) {
                    $this->requestFile = $indexFile;
                    return;
                }
            }
            $this->requestFile .= $this->getConfig('content_ext');
        }
    }

    public function getRequestFile()
    {
        return $this->requestFile;
    }

    public function loadFileContent($file)
    {
        return file_get_contents($file);
    }

    public function load404Content($file)
    {
        $errorFileDir = substr($file, strlen($this->getConfig('content_dir')));
        do {
            $errorFileDir = dirname($errorFileDir);
            $errorFile = $errorFileDir . '/404' . $this->getConfig('content_ext');
        } while (!file_exists($this->getConfig('content_dir') . $errorFile) && ($errorFileDir !== '.'));

        if (!file_exists($this->getConfig('content_dir') . $errorFile)) {
            $errorFile = ($errorFileDir === '.') ? '404' . $this->getConfig('content_ext') : $errorFile;
            throw new RuntimeException('Required "' . $this->getConfig('content_dir') . $errorFile . '" not found');
        }

        return $this->loadFileContent($this->getConfig('content_dir') . $errorFile);
    }

    public function getRawContent()
    {
        return $this->rawContent;
    }

    public function getMetaHeaders()
    {
        $headers = array(
            'title' => 'Title',
            'description' => 'Description',
            'author' => 'Author',
            'date' => 'Date',
            'robots' => 'Robots',
            'template' => 'Template'
        );

        $this->triggerEvent('onMetaHeaders', array(&$headers));
        return $headers;
    }

    public function parseFileMeta($rawContent, array $headers)
    {
        $meta = array();
        $pattern = "/^(\/(\*)|---)[[:blank:]]*(?:\r)?\n"
            . "(?:(.*?)(?:\r)?\n)?(?(2)\*\/|---)[[:blank:]]*(?:(?:\r)?\n|$)/s";
        if (preg_match($pattern, $rawContent, $rawMetaMatches) && isset($rawMetaMatches[3])) {
            $yamlParser = new \Symfony\Component\Yaml\Parser();
            $meta = $yamlParser->parse($rawMetaMatches[3]);
            $meta = ($meta !== null) ? array_change_key_case($meta, CASE_LOWER) : array();

            foreach ($headers as $fieldId => $fieldName) {
                $fieldName = strtolower($fieldName);
                if (isset($meta[$fieldName])) {
                    if ($fieldId != $fieldName) {
                        $meta[$fieldId] = $meta[$fieldName];
                        unset($meta[$fieldName]);
                    }
                } elseif (!isset($meta[$fieldId])) {
                    $meta[$fieldId] = '';
                }
            }

            if (!empty($meta['date'])) {
                $meta['time'] = strtotime($meta['date']);
                $meta['date_formatted'] = utf8_encode(strftime($this->getConfig('date_format'), $meta['time']));
            } else {
                $meta['time'] = $meta['date_formatted'] = '';
            }
        } else {
            $meta = array_fill_keys(array_keys($headers), '');
            $meta['time'] = $meta['date_formatted'] = '';
        }

        return $meta;
    }

    public function getFileMeta()
    {
        return $this->meta;
    }

    protected function registerParsedown()
    {
        $this->parsedown = new ParsedownExtra();
    }

    public function getParsedown()
    {
        return $this->parsedown;
    }

    public function prepareFileContent($rawContent, array $meta)
    {
        $metaHeaderPattern = "/^(\/(\*)|---)[[:blank:]]*(?:\r)?\n"
            . "(?:(.*?)(?:\r)?\n)?(?(2)\*\/|---)[[:blank:]]*(?:(?:\r)?\n|$)/s";
        $content = preg_replace($metaHeaderPattern, '', $rawContent, 1);

        $content = str_replace('%site_title%', $this->getConfig('site_title'), $content);

        if ($this->isUrlRewritingEnabled()) {
            $content = str_replace('%base_url%?', $this->getBaseUrl(), $content);
        } else {
            $content = str_replace('%base_url%?', $this->getBaseUrl() . '?', $content);
        }
        $content = str_replace('%base_url%', rtrim($this->getBaseUrl(), '/'), $content);

        $themeUrl = $this->getBaseUrl() . basename($this->getThemesDir()) . '/' . $this->getConfig('theme');
        $content = str_replace('%theme_url%', $themeUrl, $content);

        if (!empty($meta)) {
            $metaKeys = $metaValues = array();
            foreach ($meta as $metaKey => $metaValue) {
                if (is_scalar($metaValue) || ($metaValue === null)) {
                    $metaKeys[] = '%meta.' . $metaKey . '%';
                    $metaValues[] = strval($metaValue);
                }
            }
            $content = str_replace($metaKeys, $metaValues, $content);
        }

        return $content;
    }

    public function parseFileContent($content)
    {
        if ($this->parsedown === null) {
            throw new LogicException("Unable to parse file contents: Parsedown instance wasn't registered yet");
        }

        return $this->parsedown->text($content);
    }

    public function getFileContent()
    {
        return $this->content;
    }

    protected function readPages()
    {
        $this->pages = array();
        $files = $this->getFiles($this->getConfig('content_dir'), $this->getConfig('content_ext'), Ice::SORT_NONE);
        foreach ($files as $i => $file) {
            if (basename($file) === '404' . $this->getConfig('content_ext')) {
                unset($files[$i]);
                continue;
            }

            $id = substr($file, strlen($this->getConfig('content_dir')), -strlen($this->getConfig('content_ext')));

            $conflictFile = $this->getConfig('content_dir') . $id . '/index' . $this->getConfig('content_ext');
            if (in_array($conflictFile, $files, true)) {
                continue;
            }

            $url = $this->getPageUrl($id);
            if ($file != $this->requestFile) {
                $rawContent = file_get_contents($file);

                $headers = $this->getMetaHeaders();
                try {
                    $meta = $this->parseFileMeta($rawContent, $headers);
                } catch (\Symfony\Component\Yaml\Exception\ParseException $e) {
                    $meta = $this->parseFileMeta('', $headers);
                    $meta['YAML_ParseError'] = $e->getMessage();
                }
            } else {
                $rawContent = &$this->rawContent;
                $meta = &$this->meta;
            }

            $page = array(
                'id' => $id,
                'url' => $url,
                'title' => &$meta['title'],
                'description' => &$meta['description'],
                'author' => &$meta['author'],
                'time' => &$meta['time'],
                'date' => &$meta['date'],
                'date_formatted' => &$meta['date_formatted'],
                'raw_content' => &$rawContent,
                'meta' => &$meta
            );

            if ($file === $this->requestFile) {
                $page['content'] = &$this->content;
            }

            unset($rawContent, $meta);

            $this->triggerEvent('onSinglePageLoaded', array(&$page));

            $this->pages[$id] = $page;
        }
    }

    protected function sortPages()
    {
        $order = $this->getConfig('pages_order');
        $alphaSortClosure = function ($a, $b) use ($order) {
            $aSortKey = (basename($a['id']) === 'index') ? dirname($a['id']) : $a['id'];
            $bSortKey = (basename($b['id']) === 'index') ? dirname($b['id']) : $b['id'];

            $cmp = strcmp($aSortKey, $bSortKey);
            return $cmp * (($order === 'desc') ? -1 : 1);
        };

        if ($this->getConfig('pages_order_by') === 'date') {
            uasort($this->pages, function ($a, $b) use ($alphaSortClosure, $order) {
                if (empty($a['time']) || empty($b['time'])) {
                    $cmp = (empty($a['time']) - empty($b['time']));
                } else {
                    $cmp = ($b['time'] - $a['time']);
                }

                if ($cmp === 0) {
                    return $alphaSortClosure($a, $b);
                }

                return $cmp * (($order === 'desc') ? 1 : -1);
            });
        } else {
            uasort($this->pages, $alphaSortClosure);
        }
    }

    public function getPages()
    {
        return $this->pages;
    }

    protected function discoverCurrentPage()
    {
        $pageIds = array_keys($this->pages);

        $contentDir = $this->getConfig('content_dir');
        $contentExt = $this->getConfig('content_ext');
        $currentPageId = substr($this->requestFile, strlen($contentDir), -strlen($contentExt));
        $currentPageIndex = array_search($currentPageId, $pageIds);
        if ($currentPageIndex !== false) {
            $this->currentPage = &$this->pages[$currentPageId];

            if (($this->getConfig('order_by') === 'date') && ($this->getConfig('order') === 'desc')) {
                $previousPageOffset = 1;
                $nextPageOffset = -1;
            } else {
                $previousPageOffset = -1;
                $nextPageOffset = 1;
            }

            if (isset($pageIds[$currentPageIndex + $previousPageOffset])) {
                $previousPageId = $pageIds[$currentPageIndex + $previousPageOffset];
                $this->previousPage = &$this->pages[$previousPageId];
            }

            if (isset($pageIds[$currentPageIndex + $nextPageOffset])) {
                $nextPageId = $pageIds[$currentPageIndex + $nextPageOffset];
                $this->nextPage = &$this->pages[$nextPageId];
            }
        }
    }

    public function getCurrentPage()
    {
        return $this->currentPage;
    }

    public function getPreviousPage()
    {
        return $this->previousPage;
    }

    public function getNextPage()
    {
        return $this->nextPage;
    }

    protected function registerTwig()
    {
        $twigLoader = new Twig_Loader_Filesystem($this->getThemesDir() . $this->getConfig('theme'));
        $this->twig = new Twig_Environment($twigLoader, $this->getConfig('twig_config'));
        $this->twig->addExtension(new Twig_Extension_Debug());
        $this->twig->addExtension(new IceTwigExtension($this));

        $this->twig->addFilter(new Twig_SimpleFilter('link', array($this, 'getPageUrl')));

        $Ice = $this;
        $pages = &$this->pages;
        $this->twig->addFilter(new Twig_SimpleFilter('content', function ($page) use ($Ice, &$pages) {
            if (isset($pages[$page])) {
                $pageData = &$pages[$page];
                if (!isset($pageData['content'])) {
                    $pageData['content'] = $Ice->prepareFileContent($pageData['raw_content'], $pageData['meta']);
                    $pageData['content'] = $Ice->parseFileContent($pageData['content']);
                }
                return $pageData['content'];
            }
            return null;
        }));
    }

    public function getTwig()
    {
        return $this->twig;
    }

    protected function getTwigVariables()
    {
        $frontPage = $this->getConfig('content_dir') . 'index' . $this->getConfig('content_ext');
        return array(
            'config' => $this->getConfig(),
            'base_dir' => rtrim($this->getRootDir(), '/'),
            'base_url' => rtrim($this->getBaseUrl(), '/'),
            'theme_dir' => $this->getThemesDir() . $this->getConfig('theme'),
            'theme_url' => $this->getBaseUrl() . basename($this->getThemesDir()) . '/' . $this->getConfig('theme'),
            'rewrite_url' => $this->isUrlRewritingEnabled(),
            'site_title' => $this->getConfig('site_title'),
            'meta' => $this->meta,
            'content' => $this->content,
            'pages' => $this->pages,
            'prev_page' => $this->previousPage,
            'current_page' => $this->currentPage,
            'next_page' => $this->nextPage,
            'is_front_page' => ($this->requestFile === $frontPage),
        );
    }

    public function getBaseUrl()
    {
        $baseUrl = $this->getConfig('base_url');
        if (!empty($baseUrl)) {
            return $baseUrl;
        }

        $protocol = 'http';
        if (!empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] !== 'off')) {
            $protocol = 'https';
        } elseif ($_SERVER['SERVER_PORT'] == 443) {
            $protocol = 'https';
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
            $protocol = 'https';
        }

        $this->config['base_url'] =
            $protocol . "://" . $_SERVER['HTTP_HOST']
            . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

        return $this->getConfig('base_url');
    }

    public function isUrlRewritingEnabled()
    {
        $urlRewritingEnabled = $this->getConfig('rewrite_url');
        if ($urlRewritingEnabled !== null) {
            return $urlRewritingEnabled;
        }

        $this->config['rewrite_url'] = (isset($_SERVER['Ice_URL_REWRITING']) && $_SERVER['Ice_URL_REWRITING']);
        return $this->getConfig('rewrite_url');
    }

    public function getPageUrl($page, $queryData = null)
    {
        if (is_array($queryData)) {
            $queryData = http_build_query($queryData, '', '&');
        } elseif (($queryData !== null) && !is_string($queryData)) {
            throw new InvalidArgumentException(
                'Argument 2 passed to ' . get_called_class() . '::getPageUrl() must be of the type array or string, '
                . (is_object($queryData) ? get_class($queryData) : gettype($queryData)) . ' given'
            );
        }
        if (!empty($queryData)) {
            $page = !empty($page) ? $page : 'index';
            $queryData = $this->isUrlRewritingEnabled() ? '?' . $queryData : '&' . $queryData;
        }

        if (empty($page)) {
            return $this->getBaseUrl() . $queryData;
        } elseif (!$this->isUrlRewritingEnabled()) {
            return $this->getBaseUrl() . '?' . rawurlencode($page) . $queryData;
        } else {
            return $this->getBaseUrl() . implode('/', array_map('rawurlencode', explode('/', $page))) . $queryData;
        }
    }

    protected function getFiles($directory, $fileExtension = '', $order = self::SORT_ASC)
    {
        $directory = rtrim($directory, '/');
        $result = array();

        $files = scandir($directory, $order);
        $fileExtensionLength = strlen($fileExtension);
        if ($files !== false) {
            foreach ($files as $file) {
                if ((substr($file, 0, 1) === '.') || in_array(substr($file, -1), array('~', '#'))) {
                    continue;
                }

                if (is_dir($directory . '/' . $file)) {
                    $result = array_merge($result, $this->getFiles($directory . '/' . $file, $fileExtension, $order));
                } elseif (empty($fileExtension) || (substr($file, -$fileExtensionLength) === $fileExtension)) {
                    $result[] = $directory . '/' . $file;
                }
            }
        }

        return $result;
    }

    public function getAbsolutePath($path)
    {
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            if (preg_match('/^([a-zA-Z]:\\\\|\\\\\\\\)/', $path) !== 1) {
                $path = $this->getRootDir() . $path;
            }
        } else {
            if (substr($path, 0, 1) !== '/') {
                $path = $this->getRootDir() . $path;
            }
        }
        return rtrim($path, '/\\') . '/';
    }

    protected function triggerEvent($eventName, array $params = array())
    {
        if (!empty($this->plugins)) {
            foreach ($this->plugins as $plugin) {
                if (is_a($plugin, 'IcePluginInterface')) {
                    $plugin->handleEvent($eventName, $params);
                }
            }
        }
    }
}