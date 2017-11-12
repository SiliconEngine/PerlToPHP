Information
===========

## **THEY SAID IT COULDN'T BE DONE!**

...and they were mostly right. That said, this program makes an attempt to
convert Perl code to PHP code.

The roots of this program are in having about 200,000 lines of Perl code
to convert to PHP. It tries to do a lot of the "grunt" work of converting
code that easily translates over. See below for what it will do.

Things to keep in mind:

* It does what I needed it to do, which means it won't convert everything
you'll find in Perl. The code I had to convert mostly followed a style
and used a relatively old subset of Perl 5.
* The code is relatively well documented (greatly out of necessity), but
it doesn't try to be the most elegantly structured code in the world. Many
files have more than one class.
* Be prepared that you'll have to go through and finish converting
what it couldn't. It's meant to do 70-80% of the work. 90% if you're lucky.
* You'll probably want to add your own conversions. I'll warn you that
this code is fragile. Very fragile. Perl is a crazy mishmash of syntax,
and making one thing work can break other things. The Test file is your
only defense. Add tests as you go or you'll regret it.
* There are a few conversions that are very specific to my conversions,
particularly converting Perl block comments to PHPDOC comments. See below.
* If you don't understand Perl or don't understand PHP and you were hired
to do a conversion, this tool won't save you.

Method of Operation
===================

The only thing that makes this possible is the Perl PPI package which creates
what I'll too-generously call a syntax tree. I would also call it an insane,
inconsistent barely-usable tool that parses Perl and creates some amount
of syntax identification. Anyway, the tool runs a Perl program that creates
that output and feeds that to the PHP program that does the conversion.

Note that there is a replacement for PpiDumper that fixes a bug in the
comment processing that made things difficult.

There are PHP classes that correspond to the various PPI syntax classes
that the PPI tool outputs.

There are four phases to the conversion:

1) Whitespace consolidation, where it eliminates whitespace tokens and records
the whitespace in the nearby object. This simplified things.
2) Scan the syntax tree and attempt to figure out context, such as whether
it's list content, hash context, scalar, etc.
3) Call converters for the token objects that figure out what should happen.
4) Dump out the converted token objects to text.

Usage
=====

php perltophp.php [file.pm]  
php perltophp.php -i [file.pm] -o [file.php]  

You may need to up the ulimit for large files. I have a script file that
looks like:


    #!/bin/sh  
    ulimit -s 102400  
    php /path/to/file/perltophp.php $@  


Limitations
===========

The limitations are numerous. Take a look at the test file to see what
it will actually do. A few notes, however:

1) It looks at package names and converts that to a class name. In my case,
I usually needed the code indented four spaces after that, but it was
easier to just use Vim to push everything over than to try and do that
automatically.
2) If the program isn't sure about something, it marks it with "/\*check\*/".
Don't take that to mean that anything not marked is perfectly fine.
3) It's not currently converting expressions contained in parenthesis,
things like "This is a $abc->{string_type}" to "This is a {$abc['string_type']}".
This is an annoying limitation I might fix someday, but there haven't been
enough of those cases to motivate me to fix it. It wouldn't be that hard.
4) It doesn't look for functions contained inside the module, and then add
"$this->". This is definitely annoying and I have no excuse not to fix it,
since it would be pretty easy to fix. This one might actually get there.

The above said, one goal I had was to make the output at lease parseable
without errors. That means that some conversions I just marked as needing
conversion and put in some sort of placeholder. This goal isn't achieved in
all cases, but probably 95% of them for my code base.


Conversions You May Not Want
============================

There were various things I needed that you may not, including:

1) Certain comment blocks that fit our old styling are converted to PHPDOC.
2) Underscore variables and function names of the type var_name are converted
to more modern camelCase, like varName.

Support
=======

If you send me bug reports ("I did xyz, and it didn't work!"), I'll ignore
them. Feature requests will be laughed at. Submissions will be considered,
but really this is a low-priority project. I'm only releasing it because I
figured it might help other people with this onerous task.

License
=======

MIT License.

Conversions
===========

The following was automatically generated from the test case file (see
program make_md.php in Tests directory). [Side note: MD was a huge pain to
make code in tables look right.]

| PERL | PHP |
| ---- | --- |
| `@a = ( 'a', 'b' );` | `$a = [ 'a', 'b' ];` |
| `$a = [ 'a', 'b' ];`<br>`$a = [ ];`<br>`$a = [];` | `$a = [ 'a', 'b' ];`<br>`$a = [ ];`<br>`$a = [];` |
| `@a = (1 + 2, 3);` | `$a = [1 + 2, 3];` |
| `@a = (1, 2, (3 + 4));` | `$a = [1, 2, (3 + 4)];` |
| `$a = [ (1, 2, (3 + 4)) ];` | `$a = /*check*/array_merge( [1, 2, (3 + 4)] );` |
| `$a = (1, 2, 3);` | `$a = (1, 2, 3);` |
| `$a = [1, 2, (3 + 4) ];` | `$a = [1, 2, (3 + 4) ];` |
| `$a = [@a, @b];` | `$a = /*check*/array_merge($a, $b);` |
| `($a, $b, $c) = (1, 2, 3);` | `list($a, $b, $c) = [1, 2, 3];` |
| `sub func`<br>`{`<br>&nbsp;&nbsp;&nbsp;&nbsp;`my ($a, $b, $c) = (1, 2, 3);`<br>`}` | `function func()`<br>`{`<br>&nbsp;&nbsp;&nbsp;&nbsp;`list($a, $b, $c) = [1, 2, 3];`<br>`}` || `sub func()`<br>`{`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` | `function func()`<br>`{`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` || `$a = sub {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`};` | `$a = function () {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`};` |
| `foreach $a (@b) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print $a;`<br>`}` | `foreach ($b as $a) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print $a;`<br>`}` || `foreach $a (@$b) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print $a;`<br>`}` | `foreach ($b as $a) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print $a;`<br>`}` || `foreach my $a (@$b) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print $a;`<br>`}` | `foreach ($b as $a) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print $a;`<br>`}` || `foreach my $phone (`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$addresses->getElementsByTagName('Phone')) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` | `foreach ($addresses->getElementsByTagName('Phone') as $phone) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}`<br>` ` || `foreach my $var (1, 2, 3) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}`<br>`foreach my $var (@$b) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}`<br>`foreach my $var ([1,2],[3,4]) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}`<br>`foreach my $var ([1,2],[3,4]) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}`<br>`foreach my $var (&func(1)) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}`<br>`foreach $a (@{$a->{test}}) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}`<br>`foreach $a (qw(1 2 3 4 test)) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` | `foreach ([1, 2, 3] as $var) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}`<br>`foreach ($b as $var) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}`<br>`foreach ([[1,2],[3,4]] as $var) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}`<br>`foreach ([[1,2],[3,4]] as $var) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}`<br>`foreach (/*check*/func(1) as $var) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}`<br>`foreach (($a['test']) as $a) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}`<br>`foreach ([ 1, 2, 3, 4, 'test' ] as $a) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` |
| `$a = $b if ($a == 1);`<br>` `<br>` ` | `if ($a == 1) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = $b;`<br>`}` || `EXIT:`<br>`if (@{$hash{Test}} == 0) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` | `EXIT_LABEL:`<br>`if (count($hash['Test']) == 0) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` |
| &nbsp;&nbsp;&nbsp;&nbsp;`if ($a or $b) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`}` | &nbsp;&nbsp;&nbsp;&nbsp;`if ($a \|\| $b) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`}` |
| `if ($x =~ /\s+/) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` | `if (preg_match('/\s+/', $x)) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` || `if (($x . $y) =~ /\s+/) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` | `if (preg_match('/\s+/', ($x . $y))) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` |
| `$x =~ s/\s+/abc/;`<br>`$x =~ s/\s+/  abc  /;`<br>`$x =~ s/  def  /  abc  /;` | `$x = preg_replace('/\s+/', 'abc', $x);`<br>`$x = preg_replace('/\s+/', '  abc  ', $x);`<br>`$x = preg_replace('/  def  /', '  abc  ', $x);` || `$x =~ s/\s+/abc/g;` | `$x = preg_replace('/\s+/', 'abc', $x);` || `($x = $q->{home_tel}) =~ s/[\(\) -]/xyz/g;` | `$x = preg_replace('/[\(\) -]/', 'xyz', ($x = $q['home_tel']));` |
| `if ($x !~ /\s+/) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` | `if (! (preg_match('/\s+/', $x))) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` |
| `if ($name eq 'Test' and $agency->{Test} !~ /^\/LINK/) {`<br>`}`<br>`$z = $agency->{Test} !~ /^\/LINK/;`<br>`$z = $b + $agency->{Test} =~ /^\/LINK/;` | `if ($name === 'Test' && ! (preg_match('/^\/LINK/', $agency['Test']))) {`<br>`}`<br>`$z = ! (preg_match('/^\/LINK/', $agency['Test']));`<br>`$z = $b + preg_match('/^\/LINK/', $agency['Test']);` |
| `sub func`<br>`{`<br>&nbsp;&nbsp;&nbsp;&nbsp;`my ($a, $b, $with_under, $camelCase) = @_;`<br>` `<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}`<br>` `<br>`sub func_new`<br>`{`<br>&nbsp;&nbsp;&nbsp;&nbsp;`my $x = func(@_);`<br>`}`<br>` `<br>`sub func_new_two`<br>`{`<br>&nbsp;&nbsp;&nbsp;&nbsp;`my ($x, $y) = func_new(@_);`<br>`}` | `function func($a, $b, $withUnder, $camelCase)`<br>`{`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}`<br>` `<br>`function funcNew()`<br>`{`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$x = func($fake/*check:@*/);`<br>`}`<br>` `<br>`function funcNewTwo()`<br>`{`<br>&nbsp;&nbsp;&nbsp;&nbsp;`list($x, $y) = funcNew($fake/*check:@*/);`<br>`}`<br>` `<br>` ` |
| `sub func`<br>`{`<br>&nbsp;&nbsp;&nbsp;&nbsp;`my $a = shift;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` | `function func($a)`<br>`{`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}`<br>` ` || `sub func`<br>`{`<br>&nbsp;&nbsp;&nbsp;&nbsp;`my $a = shift;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`my $b = shift;`<br>` `<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` | `function func($a, $b)`<br>`{`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}`<br>` `<br>` `<br>` ` |
| `$s = ' ' x $b;` | `$s = str_repeat(' ', $b);` || `$s = ' ' x (5 * $x);` | `$s = str_repeat(' ', (5 * $x));` |
| `$a = 'b';           # test` | `$a = 'b';           // test` || `###[...etc...]###`<br>`#								  #`<br>`#   new_function - this is a test				  #`<br>`#								  #`<br>`###[...etc...]###` | `/**`<br>&nbsp;`*   this is a test`<br>&nbsp;`*/`<br>` `<br>` ` || &nbsp;&nbsp;&nbsp;&nbsp;`###[...etc...]###`<br>&nbsp;&nbsp;&nbsp;&nbsp;`#								      #`<br>&nbsp;&nbsp;&nbsp;&nbsp;`#   new_function - this is a test				      #`<br>&nbsp;&nbsp;&nbsp;&nbsp;`#								      #`<br>&nbsp;&nbsp;&nbsp;&nbsp;`###[...etc...]###` | &nbsp;&nbsp;&nbsp;&nbsp;`/**`<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;`*   this is a test`<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;`*/`<br>` `<br>` ` |
| `local $a = 'b';` | `$a = 'b';` |
| `$a = qq\|`<br>`test`<br>&nbsp;&nbsp;&nbsp;&nbsp;`test\|;`<br>` `<br>` ` | `$a = <<<EOT`<br>` `<br>`test`<br>&nbsp;&nbsp;&nbsp;&nbsp;`test`<br>`EOT;` |
| `$a = qw(a def c);`<br>`$a = qw( a def c );`<br>`$a = qw/ a def c /;`<br>`$a = qw( a def c`<br>&nbsp;&nbsp;&nbsp;&nbsp;`d e f );` | `$a = [ 'a', 'def', 'c' ];`<br>`$a = [ 'a', 'def', 'c' ];`<br>`$a = [ 'a', 'def', 'c' ];`<br>`$a = [ 'a', 'def', 'c', 'd', 'e', 'f' ];`<br>` ` |
| &nbsp;&nbsp;&nbsp;&nbsp;`$a = lc $b;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = lc @$b;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = lc($b);` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = strtolower($b);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = strtolower($b);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = strtolower($b);` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[lc $c];` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[strtolower($c)];` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = lc $var{stuff1}{stuff2};`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = lc $var[10][20];` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = strtolower($var['stuff1']['stuff2']);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = strtolower($var[10][20]);` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = lc $b;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = lc @$b;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = lc($b);` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = strtolower($b);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = strtolower($b);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = strtolower($b);` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[lc $c];` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[strtolower($c)];` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = lc $var{stuff1}{stuff2};`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = lc $var[10][20];` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = strtolower($var['stuff1']['stuff2']);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = strtolower($var[10][20]);` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = shift $b;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = shift @$b;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = shift($b);` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = array_shift($b);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = array_shift($b);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = array_shift($b);` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[shift $c];` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[array_shift($c)];` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = shift $var{stuff1}{stuff2};`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = shift $var[10][20];` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = array_shift($var['stuff1']['stuff2']);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = array_shift($var[10][20]);` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = pop $b;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = pop @$b;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = pop($b);` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = array_pop($b);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = array_pop($b);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = array_pop($b);` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[pop $c];` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[array_pop($c)];` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = pop $var{stuff1}{stuff2};`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = pop $var[10][20];` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = array_pop($var['stuff1']['stuff2']);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = array_pop($var[10][20]);` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = uc $b;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = uc @$b;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = uc($b);` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = strtoupper($b);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = strtoupper($b);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = strtoupper($b);` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[uc $c];` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[strtoupper($c)];` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = uc $var{stuff1}{stuff2};`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = uc $var[10][20];` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = strtoupper($var['stuff1']['stuff2']);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = strtoupper($var[10][20]);` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = lc $b;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = lc @$b;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = lc($b);` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = strtolower($b);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = strtolower($b);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = strtolower($b);` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[lc $c];` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[strtolower($c)];` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = lc $var{stuff1}{stuff2};`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = lc $var[10][20];` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = strtolower($var['stuff1']['stuff2']);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = strtolower($var[10][20]);` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = delete $b;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = delete @$b;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = delete($b);` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = unset($b);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = unset($b);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = unset($b);` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[delete $c];` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[unset($c)];` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = delete $var{stuff1}{stuff2};`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = delete $var[10][20];` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = unset($var['stuff1']['stuff2']);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = unset($var[10][20]);` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = keys $b;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = keys @$b;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = keys($b);` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = array_keys($b);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = array_keys($b);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = array_keys($b);` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[keys $c];` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[array_keys($c)];` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = keys $var{stuff1}{stuff2};`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = keys $var[10][20];` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = array_keys($var['stuff1']['stuff2']);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = array_keys($var[10][20]);` |
| &nbsp;&nbsp;&nbsp;&nbsp;`$a = defined $test_var;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[defined $c];`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = defined $var{stuff1}{stuff2};`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = defined $var[10][20];` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = /*check*/isset($testVar);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[/*check*/isset($c)];`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = /*check*/isset($var['stuff1']['stuff2']);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = /*check*/isset($var[10][20]);` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = uc $test_var;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[uc $c];`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = uc $var{stuff1}{stuff2};`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = uc $var[10][20];` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = strtoupper($testVar);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[strtoupper($c)];`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = strtoupper($var['stuff1']['stuff2']);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = strtoupper($var[10][20]);` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = lc $test_var;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[lc $c];`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = lc $var{stuff1}{stuff2};`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = lc $var[10][20];` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = strtolower($testVar);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[strtolower($c)];`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = strtolower($var['stuff1']['stuff2']);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = strtolower($var[10][20]);` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = delete $test_var;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[delete $c];`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = delete $var{stuff1}{stuff2};`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = delete $var[10][20];` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = unset($testVar);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[unset($c)];`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = unset($var['stuff1']['stuff2']);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = unset($var[10][20]);` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = int $test_var;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[int $c];`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = int $var{stuff1}{stuff2};`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = int $var[10][20];` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = floor($testVar);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[floor($c)];`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = floor($var['stuff1']['stuff2']);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = floor($var[10][20]);` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = shift $test_var;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[shift $c];`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = shift $var{stuff1}{stuff2};`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = shift $var[10][20];` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = array_shift($testVar);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[array_shift($c)];`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = array_shift($var['stuff1']['stuff2']);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = array_shift($var[10][20]);` || &nbsp;&nbsp;&nbsp;&nbsp;`$a = pop $test_var;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[pop $c];`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = pop $var{stuff1}{stuff2};`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = pop $var[10][20];` | &nbsp;&nbsp;&nbsp;&nbsp;`$a = array_pop($testVar);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = $b[array_pop($c)];`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = array_pop($var['stuff1']['stuff2']);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = array_pop($var[10][20]);` |
| `@a = sort @b;`<br>`@a = sort(@b);`<br>`@a = sort @a, @b;` | `$a = $fake/*check:sort($b)*/;`<br>`$a = $fake/*check:sort($b)*/;`<br>`$a = $fake/*check:sort($a, $b)*/;` |
| `$a = func(shift);`<br>`$b = shift;` | `$a = func($fake/*check:shift*/);`<br>`$b = $fake/*check:shift*/;` |
| `if ($a < $b) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`} elsif ($c < $d) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` | `if ($a < $b) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`} elseif ($c < $d) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` |
| `@x = split(':', $b . $c);` | `$x = explode(':', $b . $c);` || `@x = split(/[a-z]/, $b . $c);` | `$x = preg_split('/[a-z]/', $b . $c);` |
| `use Foo::Bar;` | `use Foo\Bar;` || `require Foo::Bar;` | `use Foo\Bar;` || `use Foo::Bar qw(a b c);` | `use Foo\Bar /*qw(a b c)*/;` || `sub abc`<br>`{`<br>&nbsp;&nbsp;&nbsp;&nbsp;`use Foo::Bar qw(a b c);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`require Foo::Bar qw(a b c);`<br>`}` | `function abc()`<br>`{`<br>&nbsp;&nbsp;&nbsp;&nbsp;`/*check:use Foo::Bar qw(a b c)*/;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`/*check:require Foo::Bar qw(a b c)*/;`<br>`}` |
| `goto EXIT;`<br>`print;`<br>`EXIT:`<br>`print;` | `goto EXIT_LABEL;`<br>`print;`<br>`EXIT_LABEL:`<br>`print;` |
| `if (-e ($a . $b . '.def')) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` | `if (file_exists(($a . $b . '.def'))) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` |
| `return 1;`<br>`return (1);`<br>`return (1, 2);` | `return 1;`<br>`return (1);`<br>`return [1, 2];` |
| `unless ($a = $b) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` | `if (! ($a = $b)) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` || `print unless ($a = $b);`<br>` `<br>` ` | `if (! ($a = $b)) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` || `$a = $b unless ($c = func());`<br>`$a = $b unless $c = func();`<br>`$a = $b unless ($c) = func();`<br>` `<br>` `<br>` `<br>` `<br>` `<br>` ` | `if (! ($c = func())) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = $b;`<br>`}`<br>`if (! ($c = func())) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = $b;`<br>`}`<br>`if (! (list($c) = func())) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = $b;`<br>`}` |
| `sub func`<br>`{`<br>&nbsp;&nbsp;&nbsp;&nbsp;`my $a;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`if ($a == $b) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`}`<br>` `<br>&nbsp;&nbsp;&nbsp;&nbsp;`my (@c, @d);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`my @list;`<br>`}`<br>` ` | `function func()`<br>`{`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$a = null;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`if ($a == $b) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`}`<br>` `<br>&nbsp;&nbsp;&nbsp;&nbsp;`$c = [];`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$d = [];`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$list = [];`<br>`}` |
| `$a = Package::Stuff::new('abc');` | `$a = new Package\Stuff('abc');` |
| `print;`<br>`1;` | `print;`<br>` ` |
| `$var = @$list;`<br>`$var = @$with_underscore;`<br>`$var = @{$list};`<br>`$var = $#list;`<br>`$var = $#{$list};`<br>`$var = @list_var;`<br>`$var = func((@list_var + 1) / 2);`<br>`if (@$var) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` | `$var = count($list);`<br>`$var = count($withUnderscore);`<br>`$var = count($list);`<br>`$var = (count($list)-1);`<br>`$var = (count($list)-1);`<br>`$var = count($listVar);`<br>`$var = func((count($listVar) + 1) / 2);`<br>`if (count($var)) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` |
| `@ISA = [ 'Exporter' ];`<br>`@EXPORT = [ 'TestFile' ];` | `//@ISA = [ 'Exporter' ];`<br>`//@EXPORT = [ 'TestFile' ];` |
| `@a = @$b;`<br>`@a = @{$b};`<br>`@a = @{['a', 'b', 'c']};` | `$a = $b;`<br>`$a = $b;`<br>`$a = (['a', 'b', 'c']);` |
| `func(\@b, 2);`<br>`$a = \@b;`<br>`$a = \%b;` | `func(/*check:\*/$b, 2);`<br>`$a = /*check:\*/$b;`<br>`$a = /*check:\*/$b;` |
| `no warnings qw(uninitialized);`<br>`use warnings qw(uninitialized);` | `//no warnings qw(uninitialized);`<br>`//use warnings qw(uninitialized);` |
| `$a = $b{hash};`<br>`$a = $b{'hash'};`<br>`$a = $b->{hash};`<br>`$a = $b->{'hash'};`<br>`$a = ($b->{'hash'} + $b{hash});` | `$a = $b['hash'];`<br>`$a = $b['hash'];`<br>`$a = $b['hash'];`<br>`$a = $b['hash'];`<br>`$a = ($b['hash'] + $b['hash']);` |
| `$a = $b[ $c->function ];` | `$a = $b[ $c->function ];` |
| &nbsp;&nbsp;&nbsp;&nbsp;`$var = uc func($a[$rq->{test}]);`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$var = uc func($a[$rq->{test}])[10]{'abc'};` | &nbsp;&nbsp;&nbsp;&nbsp;`$var = strtoupper(func($a[$rq['test']]));`<br>&nbsp;&nbsp;&nbsp;&nbsp;`$var = strtoupper(func($a[$rq['test']])[10]{'abc'});` |
| `for ($i = 0; $i < 1; ++$i) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`next;`<br>`}`<br>`for ($i = 0; $i < 1; ++$i) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`next LABEL;`<br>`}`<br>`for ($i = 0; $i < 1; ++$i) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`next if ($a == $b);`<br>`}`<br>` `<br>` ` | `for ($i = 0; $i < 1; ++$i) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`continue;`<br>`}`<br>`for ($i = 0; $i < 1; ++$i) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`continue /*check:LABEL*/;`<br>`}`<br>`for ($i = 0; $i < 1; ++$i) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`if ($a == $b) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;`continue;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`}`<br>`}` || `for ($i = 0; $i < 1; ++$i) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`last;`<br>`}`<br>`for ($i = 0; $i < 1; ++$i) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`last LABEL;`<br>`}`<br>`for ($i = 0; $i < 1; ++$i) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`last if ($a == $b);`<br>`}`<br>` `<br>` ` | `for ($i = 0; $i < 1; ++$i) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`break;`<br>`}`<br>`for ($i = 0; $i < 1; ++$i) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`break /*check:LABEL*/;`<br>`}`<br>`for ($i = 0; $i < 1; ++$i) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`if ($a == $b) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;`break;`<br>&nbsp;&nbsp;&nbsp;&nbsp;`}`<br>`}` |
| `chop $z;`<br>`chop($z);`<br>`chop($b = $z);`<br>`chop($b = $z + 3 * 10);`<br>`chomp $z;`<br>`chomp($z);`<br>`chomp($b = $z);`<br>`chomp($b = $z + 3 * 10);` | `$z = /*check:chop*/substr($z, 0, -1);`<br>`$z = /*check:chop*/substr($z, 0, -1);`<br>`$b = /*check:chop*/substr($b = $z, 0, -1);`<br>`$b = /*check:chop*/substr($b = $z + 3 * 10, 0, -1);`<br>`$z = /*check:chomp*/preg_replace('/\n$/', '', $z);`<br>`$z = /*check:chomp*/preg_replace('/\n$/', '', $z);`<br>`$b = /*check:chomp*/preg_replace('/\n$/', '', $b = $z);`<br>`$b = /*check:chomp*/preg_replace('/\n$/', '', $b = $z + 3 * 10);` |
| `return ();`<br>`return ( );` | `return [];`<br>`return [ ];` |
| `func(1, 2, 3, );` | `func(1, 2, 3 );` |
| `%o = %{$match->{key}};`<br>`%o = %$match;` | `$o = /*check:%*/$match['key'];`<br>`$o = /*check:%*/$match;` |
| `@a = grep { @_ ne '' } @list;`<br>`@a = grep { @_ ne ''; } @list;`<br>`$a = join(' ', grep { @_ ne '' } @list);`<br>`$a = join(' ', grep { @_ ne ''; } @{$abc->{def}});` | `$a = array_filter($list, function ($fake) { $fake/*check:@*/ !== ''; });`<br>`$a = array_filter($list, function ($fake) { $fake/*check:@*/ !== ''; });`<br>`$a = join(' ', array_filter($list, function ($fake) { $fake/*check:@*/ !== ''; }));`<br>`$a = join(' ', array_filter($abc['def'], function ($fake) { $fake/*check:@*/ !== ''; }));` |
| `$a = 10 .. 30;`<br>`$a = 4 + 5 .. 6 + 7;`<br>`foreach my $rule_type (100..105) {`<br>`}` | `$a = range(10, 30);`<br>`$a = range(4 + 5, 6 + 7);`<br>`foreach (/*check*/range(100, 105) as $ruleType) {`<br>`}` |
| `$a = func($a, $b, ($c, $d));`<br>`$a = func($a, $b, ($c+ $d));`<br>`@a = ($a, $b, ($c, $d));` | `$a = func($a, $b, $c, $d);`<br>`$a = func($a, $b, ($c+ $d));`<br>`$a = [$a, $b, $c, $d];` |
| `@a = (@b, $c);`<br>`foreach $b (@b, $c) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` | `$a = array_merge($b, $c);`<br>`foreach (array_merge($b, $c) as $b) {`<br>&nbsp;&nbsp;&nbsp;&nbsp;`print;`<br>`}` || `@a = (`<br>&nbsp;&nbsp;&nbsp;&nbsp;`[ 1, 2 ],`<br>&nbsp;&nbsp;&nbsp;&nbsp;`[ 3, 4 ],`<br>`);`<br>`@std_rules = (`<br>&nbsp;&nbsp;&nbsp;&nbsp;`{	field => 'field1',`<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;`sub => 'test1',`<br>&nbsp;&nbsp;&nbsp;&nbsp;`},`<br>&nbsp;&nbsp;&nbsp;&nbsp;`{	field => 'field2',`<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;`sub => 'test2',`<br>&nbsp;&nbsp;&nbsp;&nbsp;`},`<br>`);` | `$a = [`<br>&nbsp;&nbsp;&nbsp;&nbsp;`[ 1, 2 ],`<br>&nbsp;&nbsp;&nbsp;&nbsp;`[ 3, 4 ],`<br>`];`<br>`$stdRules = [`<br>&nbsp;&nbsp;&nbsp;&nbsp;`[	'field' => 'field1',`<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;`'sub' => 'test1',`<br>&nbsp;&nbsp;&nbsp;&nbsp;`],`<br>&nbsp;&nbsp;&nbsp;&nbsp;`[	'field' => 'field2',`<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;`'sub' => 'test2',`<br>&nbsp;&nbsp;&nbsp;&nbsp;`],`<br>`];` |
| `$ABC = 10;`<br>`$abc_def = 10;`<br>`$a = func_name(10);`<br>`$a = FuncName(10);`<br>`$a = FUNCNAME(10);`<br>`$a = _def(10);`<br>`$a = $_def;` | `$ABC = 10;`<br>`$abcDef = 10;`<br>`$a = funcName(10);`<br>`$a = funcName(10);`<br>`$a = FUNCNAME(10);`<br>`$a = _def(10);`<br>`$a = $_def;` |
| `&$a(1, 2);`<br>`&{$b->{func}}(1, 2);` | `/*check:&*/$a(1, 2);`<br>`/*check:&*/$b['func'](1, 2);` |
| `$a = [ @b ];`<br>`$a = [ @b, @c ];`<br>`$a = [ @b, 2 ];`<br>`$a = shift @b;`<br>`$a = func(shift(@b));`<br>`$a = func(shift @b);`<br>`$a = @a;`<br>`$a = 0 + @a;`<br>` `<br>`# This is wrong, but hard to fix. Make sure it's marked`<br>`func(@a, 2, 4);` | `$a = /*check*/array_merge( $b );`<br>`$a = /*check*/array_merge( $b, $c );`<br>`$a = /*check*/array_merge( $b, 2 );`<br>`$a = array_shift($b);`<br>`$a = func(array_shift($b));`<br>`$a = func(array_shift($b));`<br>`$a = count($a);`<br>`$a = 0 + count($a);`<br>` `<br>`// This is wrong, but hard to fix. Make sure it's marked`<br>`func(/*check*/count($a), 2, 4);` |
