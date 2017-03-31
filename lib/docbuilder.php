<?php
/*
 *
 *  Class to manage Documentation
 *  Depends on MarkDown Class
 */
class DocBuilder {

  function __construct($params) {
    $this->json_path = NULL;
    $this->root = NULL;
    $this->json_content = NULL;
    $this->branch = NULL;
    $this->mdList = NULL;
    $this->parser = NULL; // Markdown instance
    if (is_array($params)) {
        if (array_key_exists('json_path', $params)) {
            $this->json_path = $params["json_path"];
            $this->json_content = json_decode(
              file_get_contents($this->json_path), true);
        }
        if (array_key_exists('root', $params)) $this->root = $params["root"];
        if (array_key_exists('parser', $params)) $this->parser = $params["parser"];
    }
    if ($this->json_content && $this->root) {
      $this->branch = $this->findCurrentBranch($this->json_content, $this->root);
    }
    foreach($this->json_content as $category => $listDocs) {
      foreach($listDocs as $singleDoc) {
        $singleDoc['category'] = $category;
        $singleDoc['root'] = dirname($singleDoc['url']);
        $singleDoc['filename'] = $this->parser->generate_anchor_from(str_replace(".md", "", basename($singleDoc['url'])));
        $singleDoc['subfolders'] = $this->generate_subfolders($singleDoc['url']);
        $singleDoc['get_param'] = $this->generate_subfolders_path($singleDoc['subfolders'], $singleDoc['filename']);
        $this->mdList[$singleDoc['get_param']] = $singleDoc;
      }
    }
  }

  function generate_subfolders($url) {
    $filename = basename($url);
    $subfolder = [];
    $local_path = substr($url, strlen($this->root."/".$this->branch."/"), (-1) * strlen($filename));
    $local_path_array = explode("/", $local_path);
    foreach ($local_path_array as $current_path) {
      if ($current_path) array_push($subfolder,$this->parser->generate_anchor_from($current_path));
    }
    return $subfolder;
  }

  function generate_subfolders_path($subfolders, $filename) {
    return implode("-", $subfolders) . "-" . $filename;
  }

  function findCurrentBranch($docList, $projectRoot){
    $firstDocURL = substr(array_values($docList)[0][0]['url'], strlen($projectRoot));
    $pieces = explode("/",$firstDocURL);
    return $pieces[1];
  }

  function get_content($doc) {
    $this->currentDoc = $doc;
    $resultat = NULL;
    if (array_key_exists($doc, $this->mdList)) {
      $gitURL = $this->mdList[$doc]['url'];
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $gitURL);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      $resultat = curl_exec ($ch);
      curl_close($ch);
    }
    else {
      $currentCat = "";
      foreach($this->mdList as $index => $currentdoc) {
        if ($currentdoc['category'] != $currentCat) {
          $currentCat = $currentdoc['category'];
          $resultat .= "## " . $currentCat . "\n\n";
        }
        $resultat .= " * [".$currentdoc['title']."](./index.php?doc=".$currentdoc['get_param'].")\n\n";
      }
    }

    //Expected Links
    // ![...](images/image.ext)           => ![...](https://raw.githubusercontent.com/SambaEdu/se3-docs/images/image.ext)
    // ![...](https://)                   => do nothing
    // [...](#anchor)                     => [...](#filtered_anchor)
    // [...](https://)                    => do nothing
    // [...](subfolder/filename.md)       => [...](./index.php?doc=filtered_subfolder-filtered_filename)
    // [...](subfolder/filename.ext)       => [...](https://raw.githubusercontent.com/SambaEdu/se3-docs/subfolder/filename.ext)
    // [...](subfolder/filename.md#anchor)=> [...](./index.php?doc=filtered_subfolder-filtered_filename#filtered_anchor)
    // [...](../../subfolder/filename.md)

    $pattern = "/\!\[(.*)\]\(images\//";
    $replacement = '![$1](' . $this->mdList[$doc]['root'] . "/images/";
    $resultat = preg_replace($pattern, $replacement, $resultat);

    // fix anchors according to Markdown Engine specifications
    $pattern = "/\[(.*)\]\(\#(.*)\)/";
    $resultat = preg_replace_callback($pattern, function($matches){
      if ($this->parser) {
        $match2 = $this->parser->generate_anchor_from($matches[2]);
      }
      return "[" . $matches[1] . "](#" . $match2 . ")";
    }, $resultat);

    // fix link to subfolders for md targets
    $pattern = "/\[(.*)\]\((?!https?|\#|\.\/index\.php)(.*)\)/";
    $resultat = preg_replace_callback($pattern, function($matches){
      $aux = explode("#", $matches[2]);
      if (substr($aux[0], -3) != ".md") {
        $entities = explode("/", $aux[0]);
        $finalEntities = $this->mdList[$this->currentDoc]['subfolders'];
        foreach ($entities as $entity) {
          if ($entity == '..') {
            array_pop($finalEntities);
          }
          else {
            if ($this->parser) {
              $entity = $this->parser->generate_anchor_from($entity);
            }
            array_push($finalEntities, $entity);
          }
        }
        $strFinalEntities = implode("/", $finalEntities);
        if (array_key_exists(1, $aux)) {
          if ($aux[1]) $strFinalEntities = $strFinalEntities . "#" . $this->parser->generate_anchor_from($aux[1]);
        }
        return "[" . $matches[1] . "](" . $this->root . "/" . $this->branch . "/" . $strFinalEntities . ")";
      }
      else {
        $entities = explode("/", str_replace(".md", "", $aux[0]));
        $finalEntities = $this->mdList[$this->currentDoc]['subfolders'];
        foreach ($entities as $entity) {
          if ($entity == '..') {
            array_pop($finalEntities);
          }
          else {
            if ($this->parser) {
              $entity = $this->parser->generate_anchor_from($entity);
            }
            array_push($finalEntities, $entity);
          }
        }

        $strFinalEntities = implode("-", $finalEntities);
        if (array_key_exists(1, $aux)) {
          if ($aux[1]) $strFinalEntities = $strFinalEntities . "#" . $this->parser->generate_anchor_from($aux[1]);
        }
        if (array_key_exists(implode("-", $finalEntities), $this->mdList)) {
            return "[" . $matches[1] . "](./index.php?doc=" . $strFinalEntities . ")";
        }
        else {
          return "[~~~" . $matches[1] . "~~~](./index.php?doc=" . $strFinalEntities . ")";
        }
      }


    }, $resultat);

    // fix code included
    $pattern = "/```((?s).*?)```/";
    $replacement = '<pre>$1</pre>';
    $resultat = preg_replace($pattern, $replacement, $resultat);
    return $resultat;
  }
}
