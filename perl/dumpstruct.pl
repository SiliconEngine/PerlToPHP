
    use PPI;
    use PPI::Dumper;

    my $fn = "./perltophp.pl";
##    my $doc = PPI::Document->new($fn);
##    my $dumper = PPI::Dumper->new($doc);
##    $dumper->print;


    PPI::Dumper->new(PPI::Document->new($fn))->print;
