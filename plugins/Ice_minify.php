<?php
class Ice_Minify extends AbstractIcePlugin
{
    protected $enabled = true;
	protected $minify = true;
    protected $compress_css = true;
    protected $compress_js = true;
    protected $remove_comments = true;
    public function onPageRendered(&$output)
    {

        if ($this->minify) {

            $output = $this->minifyHTML($output);

        }

    }

    public function onConfigLoaded(array &$settings)
    {

        $this->minify = isset($settings['Ice_minify']['minify']) ? $settings['Ice_minify']['minify'] : $this->isEnabled();

        $this->compress_css = isset($settings['Ice_minify']['compress_css']) ? $settings['Ice_minify']['compress_css'] : true;

        $this->compress_js = isset($settings['Ice_minify']['compress_js']) ? $settings['Ice_minify']['compress_js'] : true;

        $this->remove_comments = isset($settings['Ice_minify']['remove_comments']) ? $settings['Ice_minify']['remove_comments'] : true;

    }

    public function minifyHTML($html) {
        $pattern = '/<(?<script>script).*?<\/script\s*>|<(?<style>style).*?<\/style\s*>|<!(?<comment>--).*?-->|<(?<tag>[\/\w.:-]*)(?:".*?"|\'.*?\'|[^\'">]+)*>|(?<text>((<[^!\/\w.:-])?[^<]*)+)|/si';
        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);
        $overriding = false;
        $raw_tag = false;
        $html = '';
		
        foreach ($matches as $token) {
            $strip = true;
            $tag = (isset($token['tag'])) ? strtolower($token['tag']) : null;
            $content = $token[0];
            if (is_null($tag)) {
                if (!empty($token['script'])) {
                    $strip = $this->compress_js;
                } else if (!empty($token['style'])) {
                    $strip = $this->compress_css;
                } else if ($this->remove_comments) {
                    if (!$overriding && $raw_tag != 'textarea') {
                        $content = preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $content);
                    }
                }
            } else {
                if ($tag == 'pre' || $tag == 'textarea') {
                    $raw_tag = $tag;
                } else if ($tag == '/pre' || $tag == '/textarea') {
                    $raw_tag = false;
                } else {
                    if ($raw_tag || $overriding) {
                        $strip = false;
                    } else {
                        $strip = true;
                        $content = preg_replace('/(\s+)(\w++(?<!\baction|\balt|\bcontent|\bsrc)="")/', '$1', $content);
                        $content = str_replace(' />', '/>', $content);
                    }
               }
           }
            if ($strip) {
                $content = $this->removeWhiteSpace($content);
            }
            $html .= $content;
        }
        return $html;
    }

    protected function removeWhiteSpace($str) {
        $str = str_replace("\t", ' ', $str);
        $str = str_replace("\n",  '', $str);
        $str = str_replace("\r",  '', $str);
        while (stristr($str, '  ')) {
            $str = str_replace('  ', '', $str);
        }
        return $str;
    }
}