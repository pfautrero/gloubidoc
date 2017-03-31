<?php

class MyMarkdown extends \cebe\markdown\Markdown {
  function __construct() {
    $this->MyTitles = [];
  }
    protected function consumeHeadLine($lines, $current)
    {
      $block = [
       'HeadLine',
       'content' => "",
      ];
      $block['content'] = $lines[$current];
      return [$block, $current];
    }

    protected function renderHeadLine($block)
    {
      $title = $block['content'];
      $level = min(substr_count($title, '#'),6);
      $pattern = "/#{1,6}(.*)/";
      $replacement = '${1}';
      $title = trim(preg_replace($pattern, $replacement, $title));

      $anchor = $this->generate_anchor_from($title);

      $pattern = "/`(.*)`/";
      $replacement = '<code class="code-title">${1}</code>';
      $title = preg_replace($pattern, $replacement, $title);

      array_push($this->MyTitles, [
        'title' => $title,
        'anchor' => $anchor,
        'level' => $level
      ]);
      return "<a style='padding-top:30px;' name='".$anchor."'></a><h" . $level . ">" . $title . "</h" . $level . ">";
    }

    function generate_anchor_from($text) {
      return strtolower(preg_replace("/[^A-Za-z0-9\-\. ]/", '', str_replace(" ", "-", $text)));
    }

    function build_md_summary(){
      $summary = "";
      $space = "";
      $level = 2;
      foreach($this->MyTitles as $title) {
        if ($level == 0) {
          $level = $title['level'];
        }
        else if ($title['level'] > $level) {
          $space .= "    ";
        }
        if ($title['level'] < $level) {
          $space = substr($space, strlen($space) - 4);
        }
        $summary .= $space . "* [". $title['title'] ."]" . "(#". $title['anchor'] .")\n\n";
        $level = $title['level'];

      }
      return $summary;
    }
}
