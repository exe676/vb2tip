<?php
/**
 * vBulletin MD5 script v0.3.1
 *
 * Author: NewEraCracker
 * License: Public Domain
 *
 * Dedicated to ForumScriptz
 */

// ==========
// Functions
// ==========

// Receive an array with vB Hashes and returns it the right way
function vbHashArray($arr)
{
    // ==========
    // Grab
    // ==========
    $content = print_r( $arr, true );

    // ==========
    // Parse
    // ==========

    // Trim and add ';' in the end
    $content = trim($content).';';

    // Be sure of the line endings
    $content = str_replace( "\n", "\r\n", str_replace( "\r", '', $content) );

    // Replace 4 spaces with tab
    $content = str_replace( '    ', "\t", $content );

    // Replace the '[' and ']' with single quote
    $content = str_replace(array("[","]"), "'", $content);

    // Replace the 'Array [new line] [tabs] (' with 'array('
    $content = preg_replace("/Array\r\n(.*)\(/","array(",$content);

    // Quote the md5 hashes
    $content = preg_replace("/=> [A-Za-z0-9_]{32}/", "FIX '$0',", $content);
    $content = str_replace("FIX '=> ","=> '",$content);

    // Fix the ')' with a new line after it
    $content = str_replace(")\r\n", '),', $content);

    // ==========    
    // Return
    // ==========
    return $content;
}

// Calculate vBMd5 of a file
function vbMd5Calc($file)
{
    return md5( str_replace( "\r\n", "\n", @file_get_contents($file) ) );
}

// ==========
// Main
// ==========

// Start
$crlf = "\r\n";
include('./includes/md5_sums_vbulletin.php');

// Generate the new md5s
foreach($md5_sums as $path => $file)
{
    foreach($file as $filename => $hash)
    {
        $md5_sums[$path][$filename] = vbMd5Calc( "./{$path}/{$filename}" );
    }
}

// ==========
// Buffer the output
// ==========
ob_start();

echo '<?php'.$crlf;
echo '// '.$md5_sum_softwareid.' '.$md5_sum_versions[$md5_sum_softwareid].$crlf;
echo '$md5_sums = '.vbHashArray($md5_sums).$crlf;
echo '$md5_sum_softwareid = \''.$md5_sum_softwareid."';".$crlf;
echo '$md5_sum_versions[\''.$md5_sum_softwareid."'] = '".$md5_sum_versions[$md5_sum_softwareid]."';".$crlf;
echo '?>';

$content = str_replace( $crlf, "\n", ob_get_contents() );
ob_end_clean();

// ==========
// Download the new md5_sums_vbulletin.php
// ==========
header('Content-Description: File Transfer');
header('Content-Type: application/force-download');
header('Content-Length: '.strlen($content));
header('Content-Disposition: attachment; filename=md5_sums_vbulletin.php');
echo $content;
exit();
?> 