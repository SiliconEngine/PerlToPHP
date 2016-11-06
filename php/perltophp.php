<?php
/**
 * Program to convert Perl code to PHP.
 * @author          Tim Behrendsen <tim@behrendsen.com>
 * @created         2016-10-14
 *
 * TODO:
 * -) Flag conversions of @var to $var (might be a count expression). Try
 *      and determine if it's part of a math expression?
 * -) Maybe implment generate test if math expression.
 * -) Remove casts and just flag. That's the usual case.
 * -) Line 2516: Bad conversion of parenthesis.
 * *) Switch around foreach
 * -) ' ' x (1 + 1)
 * -) Mark 'chop' statement with 'check'
 * -) $_[0] !~ /pattern/ -- Need to scan backward for ws or last sibling
 * -) Convert expressions in strings
 * -) keys
 */


    require_once('Converter.php');

    ini_set('memory_limit', '1G');

    $options = getopt('i:o:q');
    $fn = $options['i'];
    $outFn = get_in($options, 'o');
    $quietOpt = isset($options['q']);

    $cvt = new Converter;
    $cvt->setQuiet($quietOpt);
    $cvt->readFile($fn);

    $ppiFn = ! empty($outFn) ? "$outFn.ppi" : "$fn.ppi";
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
