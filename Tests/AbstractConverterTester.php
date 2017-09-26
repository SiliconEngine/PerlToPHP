<?php
require_once('../Converter.php');

class AbstractConverterTester extends PHPUnit\Framework\TestCase
{
    protected $tmpPerlFn = '/tmp/test_perl.pl';
    protected $tmpPhpFn = '/tmp/test_php.php';

    /**
     * Do conversion by calling program.
     *
     * Current directory must be location of perltophp.php.
     */
    protected function execCvtCommand(
        $perlCode)
    {
        file_put_contents($this->tmpPerlFn, $perlCode);
        $cmd = "php perltophp.php -q -i {$this->tmpPerlFn} -o {$this->tmpPhpFn}";
        system($cmd);

        $result = file_get_contents($this->tmpPhpFn);

        chdir($cwd);
        return $result;
    }

    /**
     * Do conversion with class directly.
     *
     * Current directory must be location of perltophp.php.
     */
    protected function convertPerl(
        $perlCode)
    {
        $cvt = new Converter;
        $cvt->setQuiet(true);
        file_put_contents('/tmp/test_perl.pl', $perlCode);
        $cvt->readFile('/tmp/test_perl.pl');
        return $cvt->convert();
    }

    /**
     * Assert equals, but add '<?php' to front.
     */
    protected function assertCodeEquals(
        $expected,
        $actual)
    {
        $this->assertEquals("<?php\n" . $expected, $actual);
    }

}
