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
            $perl = "sub func {\n" . $perl . "\n}\n";
            $php = "function func() {\n" . $php . "\n}\n";
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
     * Test regular expression match operator
     */
    public function testRegExMatch()
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


        // Test with expression
        $perl = <<<'PERL'
            if (($x . $y) =~ /\s+/) {
                print;
            }
PERL;

        $php = <<<'PHP'
            if (preg_match('/\s+/', ($x . $y))) {
                print;
            }
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Test regular expression substitute operator
     */
    public function testRegExReplace()
    {
        // Test simple
        $perl = <<<'PERL'
            $x =~ s/\s+/abc/;
PERL;

        $php = <<<'PHP'
            $x = preg_replace('/\s+/', 'abc', $x);
PHP;
        $this->doConvertTest($perl, $php);

        // Test with 'g'
        $perl = <<<'PERL'
            $x =~ s/\s+/abc/g;
PERL;

        $php = <<<'PHP'
            $x = preg_replace('/\s+/', 'abc', $x);
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

    /**
     * Test function argument conversion ('= @_' method)
     */
    public function testFuncArg1()
    {
        $perl = <<<'PERL'
            sub func
            {
                my ($a, $b) = @_;

                print;
            }
PERL;

        $php = <<<'PHP'
            function func($a, $b)
            {
                print;
            }
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Test function argument conversion (shift method)
     */
    public function testFuncArg2()
    {
        $perl = <<<'PERL'
            sub func
            {
                my $a = shift;
                my $b = shift;

                print;
            }
PERL;

        $php = <<<'PHP'
            function func($a, $b)
            {
                print;
            }
PHP;
        $this->doConvertTest($perl, $php);
    }
    /**
     * Test of str_repeat
     */
    public function testStrRepeat()
    {
        // Test simple expression
        $perl = <<<'PERL'
            $s = ' ' x $b;
PERL;

        $php = <<<'PHP'
            $s = str_repeat(' ', $b);
PHP;
        $this->doConvertTest($perl, $php);

        // Test with right hand expression
        $perl = <<<'PERL'
            $s = ' ' x (5 * $x);
PERL;

        $php = <<<'PHP'
            $s = str_repeat(' ', (5 * $x));
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Test comment block conversion
     */
    public function testCommentBlockStyle()
    {
        // Test changing regular comment from # to //
        $perl = <<<'PERL'
            $a = 'b';           # test
PERL;

        $php = <<<'PHP'
            $a = 'b';           // test
PHP;
        $this->doConvertTest($perl, $php);

        // Test changing block comment
        $perl = <<<'PERL'
###################################################################
#								  #
#   new_function - this is a test				  #
#								  #
###################################################################
PERL;

        $php = <<<'PHP'
/**
 *   this is a test
 */
PHP;
        $this->doConvertTest($perl, $php);

        // Test changing indented block comment
        $perl = <<<'PERL'
    ###################################################################
    #								      #
    #   new_function - this is a test				      #
    #								      #
    ###################################################################
PERL;

        $php = <<<'PHP'
    /**
     *   this is a test
     */
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * 'local' keyword.
     */
    public function testLocalKeyword()
    {
        $perl = <<<'PERL'
            local $a = 'b';
PERL;

        $php = <<<'PHP'
            $a = 'b';
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Test 'qq' style quoting
     */
    public function testQqQuoting()
    {
        $perl = <<<'PERL'
            $a = qq|
test
    test|;
PERL;

        $php = <<<'PHP'
            $a = <<<EOT

test
    test
EOT;
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Check 'qw'
     */
    public function testQwQuoting()
    {
        $perl = <<<'PERL'
            $a = qw(a def c);
PERL;

        $php = <<<'PHP'
            $a = [ 'a', 'def', 'c' ];
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Test monadic function that can be used without parenthesis
     * (Example: $a = pop $b)
     */
    public function testFuncConvert()
    {
        $list = [
            [ 'perl' => 'lc', 'php' => 'strtolower' ],
            [ 'perl' => 'lc', 'php' => 'strtolower' ],
            [ 'perl' => 'shift', 'php' => 'array_shift' ],
            [ 'perl' => 'pop', 'php' => 'array_pop' ],
            [ 'perl' => 'uc', 'php' => 'strtoupper' ],
            [ 'perl' => 'lc', 'php' => 'strtolower' ],
            [ 'perl' => 'delete', 'php' => 'unset' ],
            [ 'perl' => 'keys', 'php' => 'array_keys' ],
            [ 'perl' => 'defined', 'php' => '/*check*/isset' ],
        ];

        foreach ($list as $func) {

            // Test straightforward
            $perl = <<<"PERL"
                \$a = {$func['perl']} \$b;
PERL;

            $php = <<<"PHP"
                \$a = {$func['php']}(\$b);
PHP;
            $this->doConvertTest($perl, $php);

            // Test as an index, like $a = $b[pop $c];
            $perl = <<<"PERL"
                \$a = \$b[{$func['perl']} \$c];
PERL;

            $php = <<<"PHP"
                \$a = \$b[{$func['php']}(\$c)];
PHP;
            $this->doConvertTest($perl, $php);

            // Test multiple subscripts, like pop $var{stuff1}{stuff2}
            $perl = <<<"PERL"
                \$a = {$func['perl']} \$var{stuff1}{stuff2};
                \$a = {$func['perl']} \$var[10][20];
PERL;

            $php = <<<"PHP"
                \$a = {$func['php']}(\$var['stuff1']['stuff2']);
                \$a = {$func['php']}(\$var[10][20]);
PHP;
            $this->doConvertTest($perl, $php);
        }
    }

    /**
     * Check 'elsif'
     */
    public function testElsif()
    {
        $perl = <<<'PERL'
            if ($a < $b) {
                print;
            } elsif ($c < $d) {
                print;
            }
PERL;

        $php = <<<'PHP'
            if ($a < $b) {
                print;
            } elseif ($c < $d) {
                print;
            }
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * split
     */
    public function testSplit()
    {
        // Split without pattern
        $perl = <<<'PERL'
            @x = split(':', $b . $c);
PERL;

        $php = <<<'PHP'
            $x = explode(':', $b . $c);
PHP;
        $this->doConvertTest($perl, $php);


        // Split with pattern
        $perl = <<<'PERL'
            @x = split(/[a-z]/, $b . $c);
PERL;

        $php = <<<'PHP'
            $x = preg_split('/[a-z]/', $b . $c);
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * use/require statement
     */
    public function testUseRequire()
    {
        // Simple use
        $perl = <<<'PERL'
            use Foo::Bar;
PERL;

        $php = <<<'PHP'
            use Foo\Bar;
PHP;
        $this->doConvertTest($perl, $php);

        // Simple require
        $perl = <<<'PERL'
            require Foo::Bar;
PERL;

        $php = <<<'PHP'
            use Foo\Bar;
PHP;
        $this->doConvertTest($perl, $php);

        // Use with stuff after it, just comment it out.
        $perl = <<<'PERL'
            use Foo::Bar qw(a b c);
PERL;

        $php = <<<'PHP'
            use Foo\Bar /*qw(a b c)*/;
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * goto - check for conversion of labels as reserved words
     */
    public function testGoto()
    {
        $perl = <<<'PERL'
            goto EXIT;
            print;
EXIT:
            print;
PERL;

        $php = <<<'PHP'
            goto EXIT_LABEL;
            print;
EXIT_LABEL:
            print;
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Test -e file exists operator
     */
    public function testFileExists()
    {
        $perl = <<<'PERL'
            if (-e ($a . $b . '.def')) {
                print;
            }
PERL;

        $php = <<<'PHP'
            if (file_exists(($a . $b . '.def'))) {
                print;
            }
PHP;
        $this->doConvertTest($perl, $php);
    }




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


