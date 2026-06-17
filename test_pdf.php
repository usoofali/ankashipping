<?php

use Smalot\PdfParser\Parser;

require 'vendor/autoload.php';

$parser = new Parser;
$pdf = $parser->parseFile('BL.pdf');
$page = $pdf->getPages()[0];
echo $page->getText();
