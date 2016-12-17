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
        $php,
        $options = [])
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
        if (! isset($options['no_func'])
                    && strpos($php, 'function') === false) {
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
            $a = [ ];
            $a = [];
PERL;

        $php = <<<'PHP'
            $a = [ 'a', 'b' ];
            $a = [ ];
            $a = [];
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


    /**
     * Test function conversions.
     */
    public function testSub()
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

        // Test function with prototype
        $perl = <<<'PERL'
            sub func()
            {
                print;
            }
PERL;

        $php = <<<'PHP'
            function func()
            {
                print;
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

        // Test with cast
        $perl = <<<'PERL'
            foreach $a (@$b) {
                print $a;
            }        
PERL;

        $php = <<<'PHP'
            foreach ($b as $a) {
                print $a;
            }        
PHP;
        $this->doConvertTest($perl, $php);

        // Test with 'my'
        $perl = <<<'PERL'
            foreach my $a (@$b) {
                print $a;
            }        
PERL;

        $php = <<<'PHP'
            foreach ($b as $a) {
                print $a;
            }        
PHP;
        $this->doConvertTest($perl, $php);

        // Test with line break
        $perl = <<<'PERL'
            foreach my $phone (
                $addresses->getElementsByTagName('Phone')) {
                print;
            }
PERL;

        $php = <<<'PHP'
            foreach ($addresses->getElementsByTagName('Phone') as $phone) {
                print;
            }
PHP;
        $this->doConvertTest($perl, $php);

        // Test with argument list as a list
        $perl = <<<'PERL'
            foreach my $var (1, 2, 3) {
                print;
            }
            foreach my $var (@$b) {
                print;
            }
            foreach my $var ([1,2],[3,4]) {
                print;
            }
            foreach my $var ([1,2],[3,4]) {
                print;
            }
            foreach my $var (&func(1)) {
                print;
            }
            foreach $a (@{$a->{test}}) {
                print;
            }
            foreach $a (qw(1 2 3 4 test)) {
                print;
            }
PERL;

        $php = <<<'PHP'
            foreach ([1, 2, 3] as $var) {
                print;
            }
            foreach ($b as $var) {
                print;
            }
            foreach ([[1,2],[3,4]] as $var) {
                print;
            }
            foreach ([[1,2],[3,4]] as $var) {
                print;
            }
            foreach (/*check*/func(1) as $var) {
                print;
            }
            foreach (($a['test']) as $a) {
                print;
            }
            foreach ([ 1, 2, 3, 4, 'test' ] as $a) {
                print;
            }
PHP;
        $this->doConvertTest($perl, $php);


    }

    /**
     * Test reversing if, things like: "$a = $b if $c == $d;
     */
    public function testIfReverse1()
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

        // Test if has label before (bug case: should NOT reverse)
        $perl = <<<'PERL'
EXIT:
            if (@{$hash{Test}} == 0) {
                print;
            }
PERL;

        $php = <<<'PHP'
EXIT_LABEL:
            if (count($hash['Test']) == 0) {
                print;
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

        // Test special case: ($var1 = $var2) =~ s/pattern/sub/g;
        $perl = <<<'PERL'
            ($x = $q->{home_tel}) =~ s/[\(\) -]/xyz/g;
PERL;

        $php = <<<'PHP'
            $x = preg_replace('/[\(\) -]/', 'xyz', ($x = $q['home_tel']));
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
     * Test expression precedence detection
     */
    public function testExprPrec()
    {
        $perl = <<<'PERL'
            if ($name eq 'Test' and $agency->{Test} !~ /^\/LINK/) {
            }
            $z = $agency->{Test} !~ /^\/LINK/;
            $z = $b + $agency->{Test} =~ /^\/LINK/;
PERL;

        $php = <<<'PHP'
            if ($name === 'Test' && ! (preg_match('/^\/LINK/', $agency['Test']))) {
            }
            $z = ! (preg_match('/^\/LINK/', $agency['Test']));
            $z = $b + preg_match('/^\/LINK/', $agency['Test']);
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
                my ($a, $b, $with_under, $camelCase) = @_;

                print;
            }
PERL;

        $php = <<<'PHP'
            function func($a, $b, $withUnder, $camelCase)
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
                print;
            }
PERL;

        $php = <<<'PHP'
            function func($a)
            {
                print;
            }
PHP;
        $this->doConvertTest($perl, $php);

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
            $a = qw( a def c );
            $a = qw/ a def c /;
            $a = qw( a def c
                d e f );
PERL;

        $php = <<<'PHP'
            $a = [ 'a', 'def', 'c' ];
            $a = [ 'a', 'def', 'c' ];
            $a = [ 'a', 'def', 'c' ];
            $a = [ 'a', 'def', 'c', 'd', 'e', 'f' ];
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Test monadic array functions that can be used without parenthesis
     * (Example: $a = pop $b)
     */
    public function testMonadicArray()
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
        ];

        foreach ($list as $func) {

            // Test straightforward
            $perl = <<<"PERL"
                \$a = {$func['perl']} \$b;
                \$a = {$func['perl']} @\$b;
                \$a = {$func['perl']}(\$b);
PERL;

            $php = <<<"PHP"
                \$a = {$func['php']}(\$b);
                \$a = {$func['php']}(\$b);
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
     * Test monadic scalar functions that can be used without parenthesis
     * (Example: $a = pop $b)
     */
    public function testMonadicScalar()
    {
        $list = [
            [ 'perl' => 'defined', 'php' => '/*check*/isset' ],
        ];

        foreach ($list as $func) {

            // Test straightforward
            // Test as an index, like $a = $b[pop $c];
            // Test multiple subscripts, like pop $var{stuff1}{stuff2}
            $perl = <<<"PERL"
                \$a = {$func['perl']} \$b;
                \$a = \$b[{$func['perl']} \$c];
                \$a = {$func['perl']} \$var{stuff1}{stuff2};
                \$a = {$func['perl']} \$var[10][20];
PERL;

            $php = <<<"PHP"
                \$a = {$func['php']}(\$b);
                \$a = \$b[{$func['php']}(\$c)];
                \$a = {$func['php']}(\$var['stuff1']['stuff2']);
                \$a = {$func['php']}(\$var[10][20]);
PHP;
            $this->doConvertTest($perl, $php);
        }
    }

    /**
     * Test sort function
     */
    public function testSort()
    {
        $perl = <<<"PERL"
            @a = sort @b;
            @a = sort(@b);
            @a = sort @a, @b;
PERL;

        $php = <<<"PHP"
            \$a = \$fake/*check:sort(\$b)*/;
            \$a = \$fake/*check:sort(\$b)*/;
            \$a = \$fake/*check:sort(\$a, \$b)*/;
PHP;
        $this->doConvertTest($perl, $php);
    }


    /**
     * Test empty shift
     */
    public function testEmptyShift()
    {
        $perl = <<<'PERL'
            $a = func(shift);
            $b = shift;
PERL;

        $php = <<<'PHP'
            $a = func($fake/*check:shift*/);
            $b = $fake/*check:shift*/;
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Check function call
     */
    public function checkFuncCall()
    {
        $perl = <<<'PERL'
            $a = func();
            $b = &func();
PERL;

        $php = <<<'PHP'
            $a = func();
            $b = func();
PHP;
        $this->doConvertTest($perl, $php);
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
        $this->doConvertTest($perl, $php, [ 'no_func' => true ]);

        // Simple require
        $perl = <<<'PERL'
            require Foo::Bar;
PERL;

        $php = <<<'PHP'
            use Foo\Bar;
PHP;
        $this->doConvertTest($perl, $php, [ 'no_func' => true ]);

        // Use with stuff after it, just comment it out.
        $perl = <<<'PERL'
            use Foo::Bar qw(a b c);
PERL;

        $php = <<<'PHP'
            use Foo\Bar /*qw(a b c)*/;
PHP;
        $this->doConvertTest($perl, $php, [ 'no_func' => true ]);

        // Within a subroutine is invalid, so comment out
        $perl = <<<'PERL'
            sub abc
            {
                use Foo::Bar qw(a b c);
                require Foo::Bar qw(a b c);
            }
PERL;

        $php = <<<'PHP'
            function abc()
            {
                /*check:use Foo::Bar qw(a b c)*/;
                /*check:require Foo::Bar qw(a b c)*/;
            }
PHP;
        $this->doConvertTest($perl, $php, [ 'no_func' => true ]);

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
     * Return statement with scalar or array
     */
    public function testReturn()
    {
        $perl = <<<'PERL'
            return 1;
            return (1);
            return (1, 2);
PERL;

        $php = <<<'PHP'
            return 1;
            return (1);
            return [1, 2];
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Test 'unless'
     */
    public function testUnless()
    {
        // Do straight case
        $perl = <<<'PERL'
            unless ($a = $b) {
                print;
            }
PERL;

        $php = <<<'PHP'
            if (! ($a = $b)) {
                print;
            }
PHP;
        $this->doConvertTest($perl, $php);

        // Do 'switcharound' reversal case.
        $perl = <<<'PERL'
            print unless ($a = $b);
PERL;

        $php = <<<'PHP'
            if (! ($a = $b)) {
                print;
            }
PHP;
        $this->doConvertTest($perl, $php);

        // Reversal case where we need parentheses
        $perl = <<<'PERL'
            $a = $b unless ($c = func());
            $a = $b unless $c = func();
            $a = $b unless ($c) = func();
PERL;

        $php = <<<'PHP'
            if (! ($c = func())) {
                $a = $b;
            }
            if (! ($c = func())) {
                $a = $b;
            }
            if (! (list($c) = func())) {
                $a = $b;
            }
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Template for new tests
     */
    public function testInitialize()
    {
        // 1) Test if 'my' variable by itself gets an initializer.
        // 2) Also check if the semicolon token getting modified to ' = null;'
        //    affects the 'if' reverse check (unusual bug case).
        $perl = <<<'PERL'
            sub func
            {
                my $a;
                if ($a == $b) {
                    print;
                }
            }
PERL;

        $php = <<<'PHP'
            function func()
            {
                $a = null;
                if ($a == $b) {
                    print;
                }
            }
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Convert '= Package::new()' to '= new Package()'
     */
    public function testConvertNew()
    {
        $perl = <<<'PERL'
            $a = Package::Stuff::new('abc');
PERL;

        $php = <<<'PHP'
            $a = new Package\Stuff('abc');
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Test end of module "1;"
     */
    public function testEndModule()
    {
        $perl = <<<'PERL'
            print;
1;
PERL;

        $php = <<<'PHP'
            print;
PHP;
        $this->doConvertTest($perl, $php, [ 'no_func' => true ]);
    }

    /**
     * Check scalar count expressions (@$, $#)
     */
    public function test()
    {
        $perl = <<<'PERL'
            $var = @$list;
            $var = @$with_underscore;
            $var = @{$list};
            $var = $#list;
            $var = $#{$list};
            $var = @list_var;
            $var = func((@list_var + 1) / 2);
            if (@$var) {
                print;
            }
PERL;

        $php = <<<'PHP'
            $var = count($list);
            $var = count($withUnderscore);
            $var = count($list);
            $var = (count($list)-1);
            $var = (count($list)-1);
            $var = count($listVar);
            $var = func((count($listVar) + 1) / 2);
            if (count($var)) {
                print;
            }
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Comment out special variable assignments
     */
    public function testSpecialVarComment()
    {
        $perl = <<<'PERL'
            @ISA = [ 'Exporter' ];
            @EXPORT = [ 'TestFile' ];
PERL;

        $php = <<<'PHP'
            //@ISA = [ 'Exporter' ];
            //@EXPORT = [ 'TestFile' ];
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Check for replacements in strings that are in initializers
     */
    public function checkInitStringReplace()
    {
        $perl = <<<'PERL'
            namespace stuff;
            my $var = "test $replace test";
PERL;

        $php = <<<'PHP'
            class stuff {
            private $var = "test $/*check*/replace test";
            }
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Test '@' casting
     */
    public function testArrayCast()
    {
        $perl = <<<'PERL'
            @a = @$b;
            @a = @{$b};
            @a = @{['a', 'b', 'c']};
PERL;

        $php = <<<'PHP'
            $a = $b;
            $a = $b;
            $a = (['a', 'b', 'c']);
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Testing commenting out warning directives
     */
    public function testWarningDirectives()
    {
        $perl = <<<'PERL'
            no warnings qw(uninitialized);
            use warnings qw(uninitialized);
PERL;

        $php = <<<'PHP'
            //no warnings qw(uninitialized);
            //use warnings qw(uninitialized);
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Test hash subscripts
     */
    public function testHashSubscript()
    {
        $perl = <<<'PERL'
            $a = $b{hash};
            $a = $b{'hash'};
            $a = $b->{hash};
            $a = $b->{'hash'};
            $a = ($b->{'hash'} + $b{hash});
PERL;

        $php = <<<'PHP'
            $a = $b['hash'];
            $a = $b['hash'];
            $a = $b['hash'];
            $a = $b['hash'];
            $a = ($b['hash'] + $b['hash']);
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Empty function method bareword inside subscript
     */
    public function testFunctionMethodInSubscript()
    {
        $perl = <<<'PERL'
            $a = $b[ $c->function ];
PERL;

        $php = <<<'PHP'
            $a = $b[ $c->function ];
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Test multiple subscripts with function and no parentheses
     */
    public function testComplexSubscript()
    {
        $perl = <<<'PERL'
                $var = uc func($a[$rq->{test}]);
                $var = uc func($a[$rq->{test}])[10]{'abc'};
PERL;

        $php = <<<'PHP'
                $var = strtoupper(func($a[$rq['test']]));
                $var = strtoupper(func($a[$rq['test']])[10]{'abc'});
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Test next/last
     */
    public function testNextLast()
    {
        $perl = <<<'PERL'
            for ($i = 0; $i < 1; ++$i) {
                next;
            }
            for ($i = 0; $i < 1; ++$i) {
                next LABEL;
            }
            for ($i = 0; $i < 1; ++$i) {
                next if ($a == $b);
            }
PERL;

        $php = <<<'PHP'
            for ($i = 0; $i < 1; ++$i) {
                continue;
            }
            for ($i = 0; $i < 1; ++$i) {
                continue /*check:LABEL*/;
            }
            for ($i = 0; $i < 1; ++$i) {
                if ($a == $b) {
                    continue;
                }
            }
PHP;
        $this->doConvertTest($perl, $php);

        $perl = <<<'PERL'
            for ($i = 0; $i < 1; ++$i) {
                last;
            }
            for ($i = 0; $i < 1; ++$i) {
                last LABEL;
            }
            for ($i = 0; $i < 1; ++$i) {
                last if ($a == $b);
            }
PERL;

        $php = <<<'PHP'
            for ($i = 0; $i < 1; ++$i) {
                break;
            }
            for ($i = 0; $i < 1; ++$i) {
                break /*check:LABEL*/;
            }
            for ($i = 0; $i < 1; ++$i) {
                if ($a == $b) {
                    break;
                }
            }
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Test chop/chomp
     */
    public function testChopChomp()
    {
        $perl = <<<'PERL'
            chop $z;
            chop($z);
            chop($b = $z);
            chop($b = $z + 3 * 10);
            chomp $z;
            chomp($z);
            chomp($b = $z);
            chomp($b = $z + 3 * 10);
PERL;

        $php = <<<'PHP'
            $z = /*check:chop*/substr($z, 0, -1);
            $z = /*check:chop*/substr($z, 0, -1);
            $b = /*check:chop*/substr($b = $z, 0, -1);
            $b = /*check:chop*/substr($b = $z + 3 * 10, 0, -1);
            $z = /*check:chomp*/preg_replace('/\n$/', '', $z);
            $z = /*check:chomp*/preg_replace('/\n$/', '', $z);
            $b = /*check:chomp*/preg_replace('/\n$/', '', $b = $z);
            $b = /*check:chomp*/preg_replace('/\n$/', '', $b = $z + 3 * 10);
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Test empty parentheses into array
     */
    public function testEmptyParens()
    {
        $perl = <<<'PERL'
            return ();
            return ( );
PERL;

        $php = <<<'PHP'
            return [];
            return [ ];
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Test removing extra commas from function parameter lists
     */
    public function testExtraCommaFuncParam()
    {
        $perl = <<<'PERL'
            func(1, 2, 3, );
PERL;

        $php = <<<'PHP'
            func(1, 2, 3 );
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Test hash cast
     */
    public function testHashCast()
    {
        $perl = <<<'PERL'
            %o = %{$match->{key}};
            %o = %$match;
PERL;

        $php = <<<'PHP'
            $o = /*check:%*/$match['key'];
            $o = /*check:%*/$match;
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Test grep
     */
    public function testGrep()
    {
        $perl = <<<'PERL'
            @a = grep { @_ ne '' } @list;
            @a = grep { @_ ne ''; } @list;
            $a = join(' ', grep { @_ ne '' } @list);
            $a = join(' ', grep { @_ ne ''; } @{$abc->{def}});
PERL;

        $php = <<<'PHP'
            $a = array_filter($list, function ($fake) { $fake/*check:@*/ !== ''; });
            $a = array_filter($list, function ($fake) { $fake/*check:@*/ !== ''; });
            $a = join(' ', array_filter($list, function ($fake) { $fake/*check:@*/ !== ''; }));
            $a = join(' ', array_filter($abc['def'], function ($fake) { $fake/*check:@*/ !== ''; }));
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Range operator
     */
    public function testRangeOp()
    {
        $perl = <<<'PERL'
            $a = 10 .. 30;
            $a = 4 + 5 .. 6 + 7;
            foreach my $rule_type (100..105) {
            }
PERL;

        $php = <<<'PHP'
            $a = range(10, 30);
            $a = range(4 + 5, 6 + 7);
            foreach (/*check*/range(100, 105) as $ruleType) {
            }
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Check combining  ists
     * func(1, 2, (3, 4)) => func(1, 2, 3, 4)
     */
    public function testListCombine()
    {
        $perl = <<<'PERL'
            $a = func($a, $b, ($c, $d));
            $a = func($a, $b, ($c+ $d));
            @a = ($a, $b, ($c, $d));
PERL;

        $php = <<<'PHP'
            $a = func($a, $b, $c, $d);
            $a = func($a, $b, ($c+ $d));
            $a = [$a, $b, $c, $d];
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Test merging of arrays in a list
     */
    public function testArrayMerge()
    {
        $perl = <<<'PERL'
            @a = (@b, $c);
            foreach $b (@b, $c) {
                print;
            }
PERL;

        $php = <<<'PHP'
            $a = array_merge($b, $c);
            foreach (array_merge($b, $c) as $b) {
                print;
            }
PHP;
        $this->doConvertTest($perl, $php);

        // Should NOT convert
        $perl = <<<'PERL'
            @a = (
                [ 1, 2 ],
                [ 3, 4 ],
            );
            @std_rules = (
                {	field => 'field1',
                        sub => 'test1',
                },
                {	field => 'field2',
                        sub => 'test2',
                },
            );
PERL;

        $php = <<<'PHP'
            $a = [
                [ 1, 2 ],
                [ 3, 4 ],
            ];
            $stdRules = [
                [	'field' => 'field1',
                        'sub' => 'test1',
                ],
                [	'field' => 'field2',
                        'sub' => 'test2',
                ],
            ];
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Convert names to camel case
     */
    public function testNameConversion()
    {
        $perl = <<<'PERL'
            $ABC = 10;
            $abc_def = 10;
            $a = func_name(10);
            $a = FuncName(10);
            $a = FUNCNAME(10);
            $a = _def(10);
            $a = $_def;
PERL;

        $php = <<<'PHP'
            $ABC = 10;
            $abcDef = 10;
            $a = funcName(10);
            $a = funcName(10);
            $a = FUNCNAME(10);
            $a = _def(10);
            $a = $_def;
PHP;
        $this->doConvertTest($perl, $php);
    }

    /**
     * Test function casts
     */
    public function testFunctionCast()
    {
        $perl = <<<'PERL'
            &$a(1, 2);
            &{$b->{func}}(1, 2);
PERL;

        $php = <<<'PHP'
            /*check:&*/$a(1, 2);
            /*check:&*/$b['func'](1, 2);
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


