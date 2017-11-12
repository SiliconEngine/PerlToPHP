<?php
/**
 * Generate MD-formatted text version of tests for documentation.
 *
 * Trying to make code look good inside of tables was a complete pain.
 * MD is an abomination.
 *
 * @author          Tim Behrendsen <tim@siliconengine.com>
 * @created         2017-11-12
 */
class AbstractConverterTester {};

require_once('./ConverterTest.php');

class wrapper extends ConverterTest
{
    public $docText;

    protected function doConvertTest(
        $perl,
        $php,
        $options = [])
    {
        $lines1 = explode("\n", trim($perl, "\n"));
        $lines2 = explode("\n", trim($php, "\n"));
        $num = max(count($lines1), count($lines2));

        $fmtLine = function($s) {
            $s = rtrim($s);
            if ($s === '') {
                $s = ' ';
            }
            $s = preg_replace('/^\s{12}/', '', $s);
            $s = preg_replace('/^(\s*)(.+)/', '\1`\2`', $s);
            $s = str_replace('|', '\\|', $s);
            // Change leading spaces to &nbsp;
            $s = preg_replace('/(?:^|\G)\s/', '&nbsp;', $s);

            // Special, make long test look better
            $s = preg_replace('/######+/', '###[...etc...]###', $s);
            return $s;
        };

        $lines1 = array_merge($lines1, array_fill(0, $num-count($lines1), ''));
        $lines2 = array_merge($lines2, array_fill(0, $num-count($lines2), ''));
        $lines1 = array_map($fmtLine, $lines1);
        $lines2 = array_map($fmtLine, $lines2);

        $s1 = implode('<br>', $lines1);
        $s2 = implode('<br>', $lines2);
        $this->docText .= "| $s1 | $s2 |";
    }
}

print "| PERL | PHP |\n";
print "| ---- | --- |\n";
$methods = get_class_methods('wrapper');
$wrap = new wrapper;

foreach ($methods as $method) {
    if (substr($method, 0, 4) == 'test') {
        $wrap->docText = '';
        $wrap->$method();
        print $wrap->docText . "\n";
    }
}
