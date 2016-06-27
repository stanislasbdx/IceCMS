<?php

class IceTwigExtension extends Twig_Extension
{
    private $Ice;

    public function __construct(Ice $Ice)
    {
        $this->Ice = $Ice;
    }

    public function getIce()
    {
        return $this->Ice;
    }

    public function getName()
    {
        return 'IceTwigExtension';
    }

    public function getFilters()
    {
        return array(
            'markdown' => new Twig_SimpleFilter('markdown', array($this, 'markdownFilter')),
            'map' => new Twig_SimpleFilter('map', array($this, 'mapFilter')),
            'sort_by' => new Twig_SimpleFilter('sort_by', array($this, 'sortByFilter')),
        );
    }

    public function markdownFilter($markdown)
    {
        if ($this->getIce()->getParsedown() === null) {
            throw new LogicException(
                'Unable to apply Twig "markdown" filter: '
                . 'Parsedown instance wasn\'t registered yet'
            );
        }

    }

    public function mapFilter($var, $mapKeyPath)
    {
        if (!is_array($var) && (!is_object($var) || !is_a($var, 'Traversable'))) {
            throw new Twig_Error_Runtime(sprintf(
                'The map filter only works with arrays or "Traversable", got "%s"',
                is_object($var) ? get_class($var) : gettype($var)
            ));
        }

        $result = array();
        foreach ($var as $key => $value) {
            $mapValue = $this->getKeyOfVar($value, $mapKeyPath);
            $result[$key] = ($mapValue !== null) ? $mapValue : $value;
        }
        return $result;
    }

    public function sortByFilter($var, $sortKeyPath, $fallback = 'bottom')
    {
        if (is_object($var) && is_a($var, 'Traversable')) {
            $var = iterator_to_array($var, true);
        } elseif (!is_array($var)) {
            throw new Twig_Error_Runtime(sprintf(
                'The sort_by filter only works with arrays or "Traversable", got "%s"',
                is_object($var) ? get_class($var) : gettype($var)
            ));
        }
        if (($fallback !== 'top') && ($fallback !== 'bottom') && ($fallback !== 'keep')) {
            throw new Twig_Error_Runtime('The sort_by filter only supports the "top", "bottom" and "keep" fallbacks');
        }

        $twigExtension = $this;
        $varKeys = array_keys($var);
        uksort($var, function ($a, $b) use ($twigExtension, $var, $varKeys, $sortKeyPath, $fallback, &$removeItems) {
            $aSortValue = $twigExtension->getKeyOfVar($var[$a], $sortKeyPath);
            $aSortValueNull = ($aSortValue === null);

            $bSortValue = $twigExtension->getKeyOfVar($var[$b], $sortKeyPath);
            $bSortValueNull = ($bSortValue === null);

            if ($aSortValueNull xor $bSortValueNull) {
                if ($fallback === 'top') {
                } elseif ($fallback === 'bottom') {
                    return ($aSortValueNull - $bSortValueNull);
                }
            } elseif (!$aSortValueNull && !$bSortValueNull) {
                if ($aSortValue != $bSortValue) {
                    return ($aSortValue > $bSortValue) ? 1 : -1;
                }
            }

            $aIndex = array_search($a, $varKeys);
            $bIndex = array_search($b, $varKeys);
            return ($aIndex > $bIndex) ? 1 : -1;
        });

        return $var;
    }

    public static function getKeyOfVar($var, $keyPath)
    {
        if (empty($keyPath)) {
            return null;
        } elseif (!is_array($keyPath)) {
            $keyPath = array($keyPath);
        }

        foreach ($keyPath as $key) {
            if (is_object($var)) {
                if (is_a($var, 'ArrayAccess')) {
                } elseif (is_a($var, 'Traversable')) {
                    $var = iterator_to_array($var);
                } elseif (isset($var->{$key})) {
                    $var = $var->{$key};
                    continue;
                } elseif (is_callable(array($var, 'get' . ucfirst($key)))) {
                    try {
                        $var = call_user_func(array($var, 'get' . ucfirst($key)));
                        continue;
                    } catch (BadMethodCallException $e) {
                        return null;
                    }
                } else {
                    return null;
                }
            } elseif (!is_array($var)) {
                return null;
            }

            if (isset($var[$key])) {
                $var = $var[$key];
                continue;
            }

            return null;
        }

        return $var;
    }
}
