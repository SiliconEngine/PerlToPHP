<?php
/**
 * Program to convert Perl code to PHP.
 *
 * @author          Tim Behrendsen <tim@siliconengine.com>
 * @created         2016-10-14
 */
    require_once('Converter.php');

    ini_set('memory_limit', '1G');

    $options = [];
    $fileList = [];
    $newList = $argv;
    foreach (array_splice($argv, 1) as $arg) {
        if (substr($arg, 0, 1) == '-') {
            break;
        }
        $fileList[] = $arg;
    }

    if (count($fileList) > 0) {
        // In quick parameter mode

        $fn = $fileList[0];
        $outFn = get_in($fileList, 1);
    } else {
        $options = getopt('i:o:qv');
        $fn = get_in($options, 'i');
        $outFn = get_in($options, 'o');
    }

    $quietOpt = isset($options['q']);
    $verboseOpt = isset($options['v']);

    $cvt = new Converter;
    $cvt->setQuiet($quietOpt);
    $cvt->setVerbose($verboseOpt);
    $cvt->readFile($fn);

    $ppiFn = '/tmp/' . basename(empty($outFn) ? $fn : $outFn) . '.ppi';
    $newDoc = $cvt->convert($ppiFn);

    if (! empty($outFn)) {
        file_put_contents($outFn, $newDoc);
    } else {
        print $newDoc;
    }



function get_in(
    $array,			// Array to index
    $keys,			// List of keys, or scalar index
    $default = null)		// Value to return if not defined
{
    if ($array === null)
        return $default;

    if (is_array($keys)) {
        $current = $array;
        foreach ($keys as $key) {
            if (! array_key_exists($key, $current)) {
                return $default;
            }

            $current = $current[$key];
        }

        return $current;
    }

    if (! is_array($array)) {
        throw new \Exception("Bad array argument");
    }
    return array_key_exists($keys, $array) ? $array[$keys] : $default;
}
