#!/usr/bin/perl

use PPI;
use PpiDumper;

my $fn = shift(@ARGV);

PpiDumper->new(PPI::Document->new($fn))->print;
