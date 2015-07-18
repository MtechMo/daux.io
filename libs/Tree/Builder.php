<?php namespace Todaymade\Daux\Tree;

use Todaymade\Daux\Config;
use Todaymade\Daux\Daux;
use Todaymade\Daux\DauxHelper;

class Builder
{
    /**
     * Build the initial tree
     *
     * @param Directory $node
     * @param array $ignore
     * @param Config $params
     */
    public static function build($node, $ignore, Config $params)
    {
        if (!$dh = opendir($node->getPath())) {
            return;
        }

        if ($node instanceof Root) {
            // Ignore config.json in the root directory
            $ignore['files'][] = 'config.json';
        }

        while (($file = readdir($dh)) !== false) {
            if ($file == '.' || $file == '..') {
                continue;
            }

            $path = $node->getPath() . DS . $file;

            if (is_dir($path) && in_array($file, $ignore['folders'])) {
                continue;
            }
            if (!is_dir($path) && in_array($file, $ignore['files'])) {
                continue;
            }

            $entry = null;
            if (is_dir($path)) {
                $new = new Directory($node, static::getUriFromFilename(static::getFilename($path)), $path);
                $new->setName(DauxHelper::pathinfo($path)['filename']);
                $new->setTitle(static::getTitleFromFilename($new->getName()));
                static::build($new, $ignore, $params);
            } else {
                static::createContent($node, $path, $params);
            }
        }

        $node->sort();
        if (isset($node->getEntries()[$params['index_key']])) {
            $node->getEntries()[$params['index_key']]->setFirstPage($node->getFirstPage());
            $node->setIndexPage($node->getEntries()[$params['index_key']]);
        } else {
            $node->setIndexPage(false);
        }
    }

    /**
     * @param Directory $parent
     * @param string $path
     * @param Config $params
     * @return Content|Raw
     */
    public static function createContent(Directory $parent, $path, Config $params)
    {
        $name = DauxHelper::pathinfo($path)['filename'];

        if (in_array(pathinfo($path, PATHINFO_EXTENSION), Daux::$VALID_MARKDOWN_EXTENSIONS)) {
            $uri = static::getUriFromFilename($name);
            if ($params['mode'] === Daux::STATIC_MODE) {
                $uri .= '.html';
            }

            $entry = new Content($parent, $uri, $path, filemtime($path));
        } else {
            $entry = new Raw($parent, static::getUriFromFilename(static::getFilename($path)), $path, filemtime($path));
        }

        if ($entry->getUri() == $params['index_key']) {
            if ($parent instanceof Root) {
                $entry->setTitle($params['title']);
            } else {
                $entry->setTitle($parent->getTitle());
            }
        } else {
            $entry->setTitle(static::getTitleFromFilename($name));
        }

        $entry->setIndexPage(false);
        $entry->setName($name);

        return $entry;
    }

    /**
     * @param string $file
     * @return string
     */
    protected static function getFilename($file)
    {
        $parts = explode('/', $file);
        return end($parts);
    }

    /**
     * @param string $filename
     * @return string
     */
    protected static function getTitleFromFilename($filename)
    {
        $filename = explode('_', $filename);
        if ($filename[0] == '' || is_numeric($filename[0])) {
            unset($filename[0]);
        } else {
            $t = $filename[0];
            if ($t[0] == '-') {
                $filename[0] = substr($t, 1);
            }
        }
        $filename = implode(' ', $filename);
        return $filename;
    }

    /**
     * @param string $filename
     * @return string
     */
    protected static function getUriFromFilename($filename)
    {
        $filename = explode('_', $filename);
        if ($filename[0] == '' || is_numeric($filename[0])) {
            unset($filename[0]);
        } else {
            $t = $filename[0];
            if ($t[0] == '-') {
                $filename[0] = substr($t, 1);
            }
        }
        $filename = implode('_', $filename);
        return $filename;
    }

    /**
     * @param Directory $parent
     * @param String $title
     * @return Directory
     */
    public static function getOrCreateDir(Directory $parent, $title)
    {
        $slug = DauxHelper::slug($title);

        if (array_key_exists($slug, $parent->getEntries())) {
            return $parent->getEntries()[$slug];
        }

        $dir = new Directory($parent, $slug);
        $dir->setTitle($title);

        return $dir;
    }

    /**
     * @param Directory $parent
     * @param string $title
     * @return Content
     */
    public static function getOrCreatePage(Directory $parent, $title)
    {
        $slug = DauxHelper::slug($title);
        $uri = $slug . ".html";

        if (array_key_exists($uri, $parent->getEntries())) {
            return $parent->getEntries()[$uri];
        }

        $page = new Content($parent, $uri);
        $page->setContent("-"); //set an almost empty content to avoid problems

        if ($title == 'index') {
            $page->setName('_index');
            $page->setTitle($parent->getTitle());
            $parent->setIndexPage($page);
        } else {
            $page->setName($slug);
            $page->setTitle($title);
        }

        return $page;
    }
}
