<?php

require __DIR__ . '/vendor/autoload.php';
include('lib/mymarkdown.php');
include('lib/docbuilder.php');


$parser = new MyMarkdown();
$se3Doc = new DocBuilder([
  'json_path' => 'toc.json',
  'root' => 'https://raw.githubusercontent.com/SambaEdu/se3-docs',
  'parser' => $parser
]);

$doc_id = isset($_GET['doc'])?$_GET['doc']:-1;
$mainContentMD = $se3Doc->get_content($doc_id);
$mainContentHTML = $parser->parse($mainContentMD);
$summaryMD = $parser->build_md_summary();
$summaryHTML = $parser->parse($summaryMD);

// $mainContentHTML : main document in HTML
// $summaryHTML : Summary in HTML
include('template.html');
