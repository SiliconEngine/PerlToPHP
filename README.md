Information
===========

## **THEY SAID IT COULDN'T BE DONE!**

...and they were mostly right. That said, this program makes an attempt to
convert Perl code to PHP code.

The roots of this program are in having about 200,000 lines of Perl code
to convert to PHP. It tries to do a lot of the "grunt" work of converting
code that easily translates over.

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
particularly converting Perl block comments to PHPDOC comments.
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

Support
=======

If you send me bug reports ("I did xyz, and it didn't work!"), I'll ignore
them. Feature requests will be laughed at. Submissions will be considered,
but really this is a low-priority project. I'm only releasing it because I
figured it might help other people with this onerous task.

License
=======

MIT License.
