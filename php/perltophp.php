<?php

    require_once('PpiElement.php');
    require_once('PpiNode.php');
    require_once('PpiDocument.php');
    require_once('PpiStatement.php');
    require_once('PpiStructure.php');
    require_once('PpiToken.php');
    require_once('Converter.php');

    $options = getopt('i:o:');
    $fn = $options['i'];
    $outFn = get_in($options, 'o');

    $cvt = new Converter;
    $cvt->readFile($fn);
    $s = $cvt->dumpStruct();
    file_put_contents('/tmp/ppi.txt', $s);
    $newDoc = $cvt->convert();

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
