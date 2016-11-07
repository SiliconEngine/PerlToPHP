<?php
require_once('AbstractConverterTester.php');

class ConverterTest extends \AbstractConverterTester
{
    public function setUp()
    {
        chdir('..');
        return;
    }

    protected function doConvertTest(
        $perl,
        $php)
    {
        // Add newlines to end if not already there. This is more convenient
        // so we don't have to have blank lines in every test.
        if (substr($perl, 0, -1) != "\n") {
            $perl .= "\n";
        }

        if (substr($php, 0, -1) != "\n") {
            $php .= "\n";
        }

        // Do first conversion test
        $cvtPhp = $this->convertPerl($perl);
        $this->assertCodeEquals($php, $cvtPhp);

        // Also auto-test enclosed in function, if not already
        if (strpos($php, 'function') === false) {
            $perl = "sub func {\n" . $perl . "\n}";
            $php = "function func() {\n" . $php . "\n}";
            $cvtPhp = $this->convertPerl($perl);
            $this->assertCodeEquals($php, $cvtPhp);
        }
    }

    public function testConvert1()
    {
        $perl = <<<'PERL'
    @a = ( 'a', 'b' );
PERL;

        $php = <<<'PHP'
    $a = [ 'a', 'b' ];
PHP;
        $this->doConvertTest($perl, $php);
    }


    public function testConvert2()
    {
        $perl = <<<'PERL'
    $a = [ 'a', 'b' ];
PERL;

        $php = <<<'PHP'
    $a = [ 'a', 'b' ];
PHP;
        $this->doConvertTest($perl, $php);
    }


    public function testConvert3()
    {
        $perl = <<<'PERL'
    @a = (1 + 2, 3);
PERL;

        $php = <<<'PHP'
    $a = [1 + 2, 3];
PHP;
        $this->doConvertTest($perl, $php);
    }


    public function testConvert4()
    {
        $perl = <<<'PERL'
    @a = (1, 2, (3 + 4));
PERL;

        $php = <<<'PHP'
    $a = [1, 2, (3 + 4)];
PHP;
        $this->doConvertTest($perl, $php);
    }


    public function testConvert5()
    {
        $perl = <<<'PERL'
    $a = [ (1, 2, (3 + 4)) ];
PERL;

        $php = <<<'PHP'
    $a = /*check*/array_merge( [1, 2, (3 + 4)] );
PHP;
        $this->doConvertTest($perl, $php);
    }


    public function testConvert6()
    {
        $perl = <<<'PERL'
    $a = (1, 2, 3);
PERL;

        $php = <<<'PHP'
    $a = (1, 2, 3);
PHP;
        $this->doConvertTest($perl, $php);
    }


    public function testConvert7()
    {
        $perl = <<<'PERL'
    $a = [1, 2, (3 + 4) ];
PERL;

        $php = <<<'PHP'
    $a = [1, 2, (3 + 4) ];
PHP;
        $this->doConvertTest($perl, $php);
    }


    public function testConvert8()
    {
        $perl = <<<'PERL'
    $a = [@a, @b];
PERL;

        $php = <<<'PHP'
    $a = /*check*/array_merge($a, $b);
PHP;
        $this->doConvertTest($perl, $php);
    }


    public function testConvert9()
    {
        $perl = <<<'PERL'
    ($a, $b, $c) = (1, 2, 3);
PERL;

        $php = <<<'PHP'
    list($a, $b, $c) = [1, 2, 3];
PHP;
        $this->doConvertTest($perl, $php);
    }


    public function testConvert10()
    {
        $perl = <<<'PERL'
    sub func
    {
        my ($a, $b, $c) = (1, 2, 3);
    }
PERL;

        $php = <<<'PHP'
    function func()
    {
        list($a, $b, $c) = [1, 2, 3];
    }
PHP;
        $this->doConvertTest($perl, $php);
    }

    public function testForeach1()
    {
        $perl = <<<'PERL'
    foreach $a (@b) {
        print $a;
    }        
PERL;

        $php = <<<'PHP'
    foreach ($b as $a) {
        print $a;
    }        
PHP;
        $this->doConvertTest($perl, $php);
    }

    public function testForeach2()
    {
        $perl = <<<'PERL'
    foreach $a (@$b) {
        print $a;
    }        
PERL;

        $php = <<<'PHP'
    foreach (/*check:@*/$b as $a) {
        print $a;
    }        
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Test flipping if around
     */
    public function testIfFlip1()
    {
        $perl = <<<'PERL'
    $a = $b if ($a == 1);        
PERL;

        $php = <<<'PHP'
    if ($a == 1) {
        $a = $b;
    }        
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Test operator translation
     */
    public function testOperators()
    {
        $list = [
            [ 'eq',     '===' ],
            [ 'ne',     '!==' ],
            [ 'lt',     '<' ],
            [ 'gt',     '>' ],
            [ 'and',    '&&' ],
            [ 'or',     '||' ]
        ];

        foreach ($list as $chk) {
            $perlOp = $chk[0];
            $phpOp = $chk[1];

            $perl = <<<"PERL"
    if (\$a $perlOp \$b) {
        print;
    }
PERL;

            $php = <<<"PHP"
    if (\$a $phpOp \$b) {
        print;
    }
PHP;
        }

        $this->doConvertTest($perl, $php);
    }

    /**
     * Test regular expression operator
     */
    public function testRegEx()
    {
        // Test simple
        $perl = <<<'PERL'
            if ($x =~ /\s+/) {
                print;
            }
PERL;

        $php = <<<'PHP'
            if (preg_match('/\s+/', $x)) {
                print;
            }
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Test negative regular expression operator
     */
    public function testNegRegEx()
    {
        $perl = <<<'PERL'
            if ($x !~ /\s+/) {
                print;
            }
PERL;

        $php = <<<'PHP'
            if (! (preg_match('/\s+/', $x))) {
                print;
            }
PHP;
        $this->doConvertTest($perl, $php);
    }

//    /**
//     * Test of str_repeat
//     */
//    public function testStrRepeat()
//    {
//        // Test simple expression
//        $perl = <<<'PERL'
//            $s = ' ' x $b;
//PERL;
//
//        $php = <<<'PHP'
//            $s = str_repeat(' ', $b);
//PHP;
//        $this->doConvertTest($perl, $php);
//
//        // Test with right hand expression
//        $perl = <<<'PERL'
//            $s = ' ' x (5 * $x);
//PERL;
//
//        $php = <<<'PHP'
//            $s = str_repeat(' ', (5 * $x));
//PHP;
//        $this->doConvertTest($perl, $php);
//    }

    /**
     * Template for new tests
     */
    public function name()
    {
        $perl = <<<'PERL'
PERL;

        $php = <<<'PHP'
PHP;
        $this->doConvertTest($perl, $php);
    }


}


