<?php

class IceEditor extends AbstractIcePlugin
{
    protected $enabled = false;
    private $is_admin;
    private $is_logout;
    private $plugin_path;
    private $contentDir;
    private $contentExt;
    private $password;
    private $urlRewriting;
    private $adminUrl;

    public function onConfigLoaded(&$config)
    {
        $this->is_admin = false;
        $this->is_logout = false;
        $this->plugin_path = dirname(__FILE__);
        $this->contentDir = $config['content_dir'];
        $this->contentExt = $config['content_ext'];
        if (isset($config['rewrite_url']) &&
        !empty($config['rewrite_url']) &&
        $config['rewrite_url'] == true) {
            $this->urlRewriting = '/';
        } else {
            $this->urlRewriting = '/?';
        }
        if (isset($config['IceEditor']['password']) &&
        !empty($config['IceEditor']['password'])) {
            $this->password = $config['IceEditor']['password'];
        }
        if (isset($config['IceEditor']['url']) &&
        !empty($config['IceEditor']['url'])) {
            $this->adminUrl = $config['IceEditor']['url'];
        }
        if (!isset($_SESSION)) {
            session_start();
        }
    }

    public function onRequestUrl(&$url)
    {
        if ($url == $this->adminUrl) {
            $this->is_admin = true;
        }
        if ($url == 'admin/new') {
            $this->doNew();
        }
        if ($url == 'admin/open') {
            $this->doOpen();
        }
        if ($url == 'admin/save') {
            $this->doSave();
        }
        if ($url == 'admin/delete') {
            $this->doDelete();
        }
        if ($url == 'admin/logout') {
            $this->is_logout = true;
        }
    }

    public function onPageRendering(&$twig, &$twigVariables, &$templateName)
    {
        if ($this->is_logout) {
            session_destroy();
            header('Location: '.$twigVariables['base_url']. $this->urlRewriting .$this->adminUrl);
            exit;
        }

        if ($this->is_admin) {
            header($_SERVER['SERVER_PROTOCOL'].' 200 OK');

            $loader = new Twig_Loader_Filesystem($this->plugin_path);
            $twig->setLoader($loader);

            $twigVariables['editor_url'] = $this->adminUrl;

            if (!$this->password) {
                $twigVariables['login_error'] = 'No password set!';
                echo $twig->render('views/login.twig', $twigVariables);
                exit;
            }
            if (!isset($_SESSION['Ice_logged_in']) || !$_SESSION['Ice_logged_in']) {
                if (isset($_POST['password'])) {
                    if (hash('sha512', $_POST['password']) == $this->password) {
                        $_SESSION['Ice_logged_in'] = true;
                    } else {
                        $twigVariables['login_error'] = 'Invalid password.';
                        echo $twig->render('views/login.twig', $twigVariables);
                        exit;
                    }
                } else {
                    echo $twig->render('views/login.twig', $twigVariables);
                    exit;
                }
            }
            echo $twig->render('views/editor.twig', $twigVariables);
            exit;
        }
    }

    private function doCheckLogin()
    {
        if (!isset($_SESSION['Ice_logged_in']) ||
        !$_SESSION['Ice_logged_in']) {
            die(json_encode(array('error' => 'Error: Unathorized')));
        }
    }

    private function doNew()
    {

        $this->doCheckLogin();
        $title = isset($_POST['title']) && $_POST['title'] ? strip_tags($_POST['title']) : '';
        $file = $this->slugify(basename($title));
        if (!$file) {
            die(json_encode(array('error' => 'Error: Invalid file name')));
        }
        $error = '';
        $file .= $this->contentExt;
        $content = '/*
Title: '.$title.'
Description:
Author: 
Date: '.date('Y/m/d').'
Icon: 
Robots: noindex,nofollow
Template:
*/';
        if (file_exists($this->contentDir.$file)) {
            $error = 'Error: A post already exists with this title';
        } else {
            file_put_contents($this->contentDir.$file, $content);
        }
        die(json_encode(array(
            'title' => $title,
            'content' => $content,
            'file' => basename(str_replace($this->contentExt, '', $file)),
            'error' => $error,
        )));
    }

    private function doOpen()
    {
        $this->doCheckLogin();
        $file_url = isset($_POST['file']) && $_POST['file'] ? $_POST['file'] : '';
        $file = urldecode(basename($file_url));
        if (!$file) {
            die('Open Error: Invalid file '.$file.' at the URL: '.$file_url);
        }
        $file .= $this->contentExt;
        if (file_exists($this->contentDir.$file)) {
            die(file_get_contents($this->contentDir.$file));
        } else {
            die('Open Error: Invalid file '.$file.' at the URL: '.$file_url);
        }
    }

    private function doSave()
    {

        $this->doCheckLogin();
        $file_url = isset($_POST['file']) && $_POST['file'] ? $_POST['file'] : '';
        $file = urldecode(basename($file_url));
        if (!$file) {
            die('Save Error: Invalid file');
        }
        $content = isset($_POST['content']) && $_POST['content'] ? $_POST['content'] : '';
        if (!$content) {
            die('Save Error: Invalid content');
        }
        $file .= $this->contentExt;
        file_put_contents($this->contentDir.$file, $content);
        die($content);
    }

    private function doDelete()
    {

        $this->doCheckLogin();
        $file_url = isset($_POST['file']) && $_POST['file'] ? $_POST['file'] : '';
        $file = urldecode(basename($file_url));
        if (!$file) {
            die('Delete Error: Invalid file');
        }
        $file .= $this->contentExt;
        if (file_exists($this->contentDir.$file)) {
            die(unlink($this->contentDir.$file));
        }
    }

    private function slugify($text)
    {
        $text = preg_replace('~[^\\pL\d]+~u', '-', $text);
        $text = trim($text, '-');
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = strtolower($text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        if (empty($text)) {
            return 'n-a';
        }
        return $text;
    }
}