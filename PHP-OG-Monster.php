erl	Moved the Perl version of program and began PHP rework.	2 years ago
.gitignore	Added 'tags' script.	2 years ago
Converter.php	Update Converter.php	2 minutes ago
PpiDocument.php	Code cleanup for release.	a year ago
PpiDumper.pm	Moved php version to main directory.	2 years ago
PpiElement.php	Code cleanup for release.	a year ago
PpiNode.php	Code cleanup for release.	a year ago
PpiStatement.php	Code cleanup for release.	a year ago
PpiStructure.php	Code cleanup for release.	a year ago
PpiToken.php	Just output warning & skip instead of abort in case of parser panic.	a year ago
README.md	Bug in make_md program wasn't showing all tests.	a year ago
Troll-punch-php	Create Troll-punch-php	4 hours ago
dumplex.pl	Make able to run from any directory.	2 years ago
perltophp.php	Code cleanup for release.	a year ago
www	Create www	4 hours ago
 README.md
Information
THEY SAID IT COULDN'T BE DONE!
...and they were mostly right. That said, this program makes an attempt to convert Perl code to PHP code.

The roots of this program are in having about 200,000 lines of Perl code to convert to PHP. It tries to do a lot of the "grunt" work of converting code that easily translates over. See below for what it will do.

Things to keep in mind:

It does what I needed it to do, which means it won't convert everything you'll find in Perl. The code I had to convert mostly followed a style and used a relatively old subset of Perl 5.
The code is relatively well documented (greatly out of necessity), but it doesn't try to be the most elegantly structured code in the world. Many files have more than one class.
Be prepared that you'll have to go through and finish converting what it couldn't. It's meant to do 70-80% of the work. 90% if you're lucky.
You'll probably want to add your own conversions. I'll warn you that this code is fragile. Very fragile. Perl is a crazy mishmash of syntax, and making one thing work can break other things. The Test file is your only defense. Add tests as you go or you'll regret it.
There are a few conversions that are very specific to my conversions, particularly converting Perl block comments to PHPDOC comments. See below.
If you don't understand Perl or don't understand PHP and you were hired to do a conversion, this tool won't save you.
Method of Operation
The only thing that makes this possible is the Perl PPI package which creates what I'll too-generously call a syntax tree. I would also call it an insane, inconsistent barely-usable tool that parses Perl and creates some amount of syntax identification. Anyway, the tool runs a Perl program that creates that output and feeds that to the PHP program that does the conversion.

Note that there is a replacement for PpiDumper that fixes a bug in the comment processing that made things difficult.

There are PHP classes that correspond to the various PPI syntax classes that the PPI tool outputs.

There are four phases to the conversion:

Whitespace consolidation, where it eliminates whitespace tokens and records the whitespace in the nearby object. This simplified things.
Scan the syntax tree and attempt to figure out context, such as whether it's list content, hash context, scalar, etc.
Call converters for the token objects that figure out what should happen.
Dump out the converted token objects to text.
Usage
php perltophp.php [file.pm]
php perltophp.php -i [file.pm] -o [file.php]

You may need to up the ulimit for large files. I have a script file that looks like:

#!/bin/sh  
ulimit -s 102400  
php /path/to/file/perltophp.php $@  
Limitations
The limitations are numerous. Take a look at the test file to see what it will actually do. A few notes, however:

It looks at package names and converts that to a class name. In my case, I usually needed the code indented four spaces after that, but it was easier to just use Vim to push everything over than to try and do that automatically.
If the program isn't sure about something, it marks it with "/*check*/". Don't take that to mean that anything not marked is perfectly fine.
It's not currently converting expressions contained in parenthesis, things like "This is a $abc->{string_type}" to "This is a {$abc['string_type']}". This is an annoying limitation I might fix someday, but there haven't been enough of those cases to motivate me to fix it. It wouldn't be that hard.
It doesn't look for functions contained inside the module, and then add "$this->". This is definitely annoying and I have no excuse not to fix it, since it would be pretty easy to fix. This one might actually get there.
The above said, one goal I had was to make the output at lease parseable without errors. That means that some conversions I just marked as needing conversion and put in some sort of placeholder. This goal isn't achieved in all cases, but probably 95% of them for my code base.

Conversions You May Not Want
There were various things I needed that you may not, including:

Certain comment blocks that fit our old styling are converted to PHPDOC.
Underscore variables and function names of the type var_name are converted to more modern camelCase, like varName.
Support
If you send me bug reports ("I did xyz, and it didn't work!"), I'll ignore them. Feature requests will be laughed at. Submissions will be considered, but really this is a low-priority project. I'm only releasing it because I figured it might help other people with this onerous task.

License
MIT License.

Conversions
The following was automatically generated from the test case file (see program make_md.php in Tests directory). [Side note: MD was a huge pain to make code in tables look right.]

PERL	PHP
@a = ( 'a', 'b' );	$a = [ 'a', 'b' ];
$a = [ 'a', 'b' ];
$a = [ ];
$a = [];	$a = [ 'a', 'b' ];
$a = [ ];
$a = [];
@a = (1 + 2, 3);	$a = [1 + 2, 3];
@a = (1, 2, (3 + 4));	$a = [1, 2, (3 + 4)];
$a = [ (1, 2, (3 + 4)) ];	$a = /*check*/array_merge( [1, 2, (3 + 4)] );
$a = (1, 2, 3);	$a = (1, 2, 3);
$a = [1, 2, (3 + 4) ];	$a = [1, 2, (3 + 4) ];
$a = [@a, @b];	$a = /*check*/array_merge($a, $b);
($a, $b, $c) = (1, 2, 3);	list($a, $b, $c) = [1, 2, 3];
sub func
{
    my ($a, $b, $c) = (1, 2, 3);
}	function func()
{
    list($a, $b, $c) = [1, 2, 3];
}
foreach $a (@b) {
    print $a;
}	foreach ($b as $a) {
    print $a;
}
$a = $b if ($a == 1);

if ($a == 1) {
    $a = $b;
}
    if ($a or $b) {
        print;
    }	    if ($a || $b) {
        print;
    }
if ($x =~ /\s+/) {
    print;
}	if (preg_match('/\s+/', $x)) {
    print;
}
$x =~ s/\s+/abc/;
$x =~ s/\s+/ abc /;
$x =~ s/ def / abc /;	$x = preg_replace('/\s+/', 'abc', $x);
$x = preg_replace('/\s+/', ' abc ', $x);
$x = preg_replace('/ def /', ' abc ', $x);
if ($x !~ /\s+/) {
    print;
}	if (! (preg_match('/\s+/', $x))) {
    print;
}
if ($name eq 'Test' and $agency->{Test} !~ /^\/LINK/) {
}
$z = $agency->{Test} !~ /^\/LINK/;
$z = $b + $agency->{Test} =~ /^\/LINK/;	if ($name === 'Test' && ! (preg_match('/^\/LINK/', $agency['Test']))) {
}
$z = ! (preg_match('/^\/LINK/', $agency['Test']));
$z = $b + preg_match('/^\/LINK/', $agency['Test']);
sub func
{
    my ($a, $b, $with_under, $camelCase) = @_;

    print;
}

sub func_new
{
    my $x = func(@_);
}

sub func_new_two
{
    my ($x, $y) = func_new(@_);
}	function func($a, $b, $withUnder, $camelCase)
{
    print;
}

function funcNew()
{
    $x = func($fake/*check:@*/);
}

function funcNewTwo()
{
    list($x, $y) = funcNew($fake/*check:@*/);
}

sub func
{
    my $a = shift;
    print;
}	function func($a)
{
    print;
}
$s = ' ' x $b;	$s = str_repeat(' ', $b);
$a = 'b'; # test	$a = 'b'; // test
local $a = 'b';	$a = 'b';
$a = qq|
test
    test|;

$a = <<<EOT

test
    test
EOT;
$a = qw(a def c);
$a = qw( a def c );
$a = qw/ a def c /;
$a = qw( a def c
    d e f );	$a = [ 'a', 'def', 'c' ];
$a = [ 'a', 'def', 'c' ];
$a = [ 'a', 'def', 'c' ];
$a = [ 'a', 'def', 'c', 'd', 'e', 'f' ];
    $a = lc $b;
    $a = lc @$b;
    $a = lc($b);	    $a = strtolower($b);
    $a = strtolower($b);
    $a = strtolower($b);
    $a = defined $test_var;
    $a = $b[defined $c];
    $a = defined $var{stuff1}{stuff2};
    $a = defined $var[10][20];	    $a = /*check*/isset($testVar);
    $a = $b[/*check*/isset($c)];
    $a = /*check*/isset($var['stuff1']['stuff2']);
    $a = /*check*/isset($var[10][20]);
@a = sort @b;
@a = sort(@b);
@a = sort @a, @b;	$a = $fake/*check:sort($b)*/;
$a = $fake/*check:sort($b)*/;
$a = $fake/*check:sort($a, $b)*/;
$a = func(shift);
$b = shift;	$a = func($fake/*check:shift*/);
$b = $fake/*check:shift*/;
if ($a < $b) {
    print;
} elsif ($c < $d) {
    print;
}	if ($a < $b) {
    print;
} elseif ($c < $d) {
    print;
}
@x = split(':', $b . $c);	$x = explode(':', $b . $c);
use Foo::Bar;	use Foo\Bar;
goto EXIT;
print;
EXIT:
print;	goto EXIT_LABEL;
print;
EXIT_LABEL:
print;
if (-e ($a . $b . '.def')) {
    print;
}	if (file_exists(($a . $b . '.def'))) {
    print;
}
return 1;
return (1);
return (1, 2);	return 1;
return (1);
return [1, 2];
unless ($a = $b) {
    print;
}	if (! ($a = $b)) {
    print;
}
sub func
{
    my $a;
    if ($a == $b) {
        print;
    }

    my (@c, @d);
    my @list;
}
function func()
{
    $a = null;
    if ($a == $b) {
        print;
    }

    $c = [];
    $d = [];
    $list = [];
}
$a = Package::Stuff::new('abc');	$a = new Package\Stuff('abc');
print;
1;	print;
$var = @$list;
$var = @$with_underscore;
$var = @{$list};
$var = $#list;
$var = $#{$list};
$var = @list_var;
$var = func((@list_var + 1) / 2);
if (@$var) {
    print;
}	$var = count($list);
$var = count($withUnderscore);
$var = count($list);
$var = (count($list)-1);
$var = (count($list)-1);
$var = count($listVar);
$var = func((count($listVar) + 1) / 2);
if (count($var)) {
    print;
}
@ISA = [ 'Exporter' ];
@EXPORT = [ 'TestFile' ];	//@ISA = [ 'Exporter' ];
//@EXPORT = [ 'TestFile' ];
@a = @$b;
@a = @{$b};
@a = @{['a', 'b', 'c']};	$a = $b;
$a = $b;
$a = (['a', 'b', 'c']);
func(\@b, 2);
$a = \@b;
$a = \%b;	func(/*check:\*/$b, 2);
$a = /*check:\*/$b;
$a = /*check:\*/$b;
no warnings qw(uninitialized);
use warnings qw(uninitialized);	//no warnings qw(uninitialized);
//use warnings qw(uninitialized);
$a = $b{hash};
$a = $b{'hash'};
$a = $b->{hash};
$a = $b->{'hash'};
$a = ($b->{'hash'} + $b{hash});	$a = $b['hash'];
$a = $b['hash'];
$a = $b['hash'];
$a = $b['hash'];
$a = ($b['hash'] + $b['hash']);
$a = $b[ $c->function ];	$a = $b[ $c->function ];
    $var = uc func($a[$rq->{test}]);
    $var = uc func($a[$rq->{test}])[10]{'abc'};	    $var = strtoupper(func($a[$rq['test']]));
    $var = strtoupper(func($a[$rq['test']])[10]{'abc'});
for ($i = 0; $i < 1; ++$i) {
    next;
}
for ($i = 0; $i < 1; ++$i) {
    next LABEL;
}
for ($i = 0; $i < 1; ++$i) {
    next if ($a == $b);
}

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
chop $z;
chop($z);
chop($b = $z);
chop($b = $z + 3 * 10);
chomp $z;
chomp($z);
chomp($b = $z);
chomp($b = $z + 3 * 10);	$z = /*check:chop*/substr($z, 0, -1);
$z = /*check:chop*/substr($z, 0, -1);
$b = /*check:chop*/substr($b = $z, 0, -1);
$b = /*check:chop*/substr($b = $z + 3 * 10, 0, -1);
$z = /*check:chomp*/preg_replace('/\n$/', '', $z);
$z = /*check:chomp*/preg_replace('/\n$/', '', $z);
$b = /*check:chomp*/preg_replace('/\n$/', '', $b = $z);
$b = /*check:chomp*/preg_replace('/\n$/', '', $b = $z + 3 * 10);
return ();
return ( );	return [];
return [ ];
func(1, 2, 3, );	func(1, 2, 3 );
%o = %{$match->{key}};
%o = %$match;	$o = /*check:%*/$match['key'];
$o = /*check:%*/$match;
@a = grep { @_ ne '' } @list;
@a = grep { @_ ne ''; } @list;
$a = join(' ', grep { @_ ne '' } @list);
$a = join(' ', grep { @_ ne ''; } @{$abc->{def}});	$a = array_filter($list, function ($fake) { $fake/*check:@*/ !== ''; });
$a = array_filter($list, function ($fake) { $fake/*check:@*/ !== ''; });
$a = join(' ', array_filter($list, function ($fake) { $fake/*check:@*/ !== ''; }));
$a = join(' ', array_filter($abc['def'], function ($fake) { $fake/*check:@*/ !== ''; }));
$a = 10 .. 30;
$a = 4 + 5 .. 6 + 7;
foreach my $rule_type (100..105) {
}	$a = range(10, 30);
$a = range(4 + 5, 6 + 7);
foreach (/*check*/range(100, 105) as $ruleType) {
}
$a = func($a, $b, ($c, $d));
$a = func($a, $b, ($c+ $d));
@a = ($a, $b, ($c, $d));	$a = func($a, $b, $c, $d);
$a = func($a, $b, ($c+ $d));
$a = [$a, $b, $c, $d];
@a = (@b, $c);
foreach $b (@b, $c) {
    print;
}	$a = array_merge($b, $c);
foreach (array_merge($b, $c) as $b) {
    print;
}
$ABC = 10;
$abc_def = 10;
$a = func_name(10);
$a = FuncName(10);
$a = FUNCNAME(10);
$a = _def(10);
$a = $_def;	$ABC = 10;
$abcDef = 10;
$a = funcName(10);
$a = funcName(10);
$a = FUNCNAME(10);
$a = _def(10);
$a = $_def;
&$a(1, 2);
&{$b->{func}}(1, 2);	/*check:&*/$a(1, 2);
/*check:&*/$b['func'](1, 2);
$a = [ @b ];
$a = [ @b, @c ];
$a = [ @b, 2 ];
$a = shift @b;
$a = func(shift(@b));
$a = func(shift @b);
$a = @a;
$a = 0 + @a;

# This is wrong, but hard to fix. Make sure it's marked
func(@a, 2, 4);	$a = /*check*/array_merge( $b );
$a = /*check*/array_merge( $b, $c );
$a = /*check*/array_merge( $b, 2 );
$a = array_shift($b);
$a = func(array_shift($b));
$a = func(array_shift($b));
$a = count($a);
$a = 0 + count($a);

// This is wrong, but hard to fix. Make sure it's marked
func(/*check*/count($a), 2, 4);
© 2019 GitHub, Inc.
Terms
Privacy
Security
Status
Help
Contact GitHub
Pricing
API
Training
Blog
About
