#!/usr/bin/perl -w
# Fix:*Labels getting eaten
# Fix: @{$abc} for length / cast to array
# Fix: Process =~, !~
# Fix: Mark $1, $2, etc
# Fix: Eliminate '1;' at end
# Fix: Functions (e.g., 'CvtNull')
# Fix: Reverse foreach
# Fix? Functions without parentheses (uc $a) 
# Fix: my ($a, $b)
# Fix: if (defined($a = $b))


    use strict;
    use PPI;
    use PPI::Dumper;
    use Params::Util qw(_INSTANCE);
    use Data::Dumper;
    use Text::Tabs;

    my @LastList = ( undef, undef, undef, undef, undef, undef, undef, undef );

    my $fn = shift @ARGV;
#    $fn = './mod.pm' if ! defined($fn);

    my $EndBraceFlag = 0;
    my $BraceLevel = 0;
    my $InClass = 0;

    my $doc = PPI::Document->new($fn);

    my $outlog;
    open($outlog, ">/tmp/perltophp.log") || die "Could not open log file";
##    print $outlog "TEST\n";
##    close $outlog;
##    exit(0);

    if (! $doc->isa('PPI::Document')) {
	print STDERR "Structure was not a document: " . getType($doc) . "\n";
	exit(1);
    }

    my $phpObj = ProcessDocument($doc);

    my $newfn = $fn;
    if (! ($newfn =~ s/(.*)\..*/$1.php/)) {
        $newfn .= '.php';
    }
#    $newfn = "/tmp/$newfn";
    open(my $fh, ">$newfn");
print $outlog "START REF: " . ref($phpObj) . "\n";
    DumpOutput($fh, $phpObj, 0);
    print STDERR "New file written to $newfn\n";
    close $fh;

    close $outlog;
    print STDERR "Log written to /tmp/perltophp.log\n";

    exit(0);

sub DumpOutput
{
    my $fh = shift;
    my $phpObj = shift;
    my $level = shift;
    if (ref($phpObj) eq 'PhpToken') {
	print $fh $phpObj->content;
my $content = $phpObj->content;
$content =~ s/\n/\\n/;
$content =~ s/\t/\\t/;
$content =~ s/\r/\\r/;
print $outlog scalar(' ' x ($level*2)) . "PhpToken: '$content'\n";
    } else {
print $outlog scalar(' ' x ($level*2)) . ref($phpObj) . "\n";
	if (ref($phpObj) eq 'PhpStructure') {
	    print $fh $phpObj->{start};
	}

#print $outlog "REF: " . ref($phpObj) . "\n";
#print $outlog scalar(' ' x ($level*2)) . "children: ";
#foreach my $subobj (@{$phpObj->{children}}) {
#print $outlog scalar(' ' x ($level*2)) . "  " . ref($subobj);
#}
	foreach my $subobj (@{$phpObj->{children}}) {
	    DumpOutput($fh, $subobj, $level+1);
	}

	if (ref($phpObj) eq 'PhpStructure') {
	    print $fh $phpObj->{finish};
	}
    }
}


sub ProcessDocument
{
    my ($node) = @_;

##    my $handle = ListInit($node->{children});

    my $phpObj = new PhpStatement($node, undef);
    $phpObj->outToken(undef, "<?php\n");

    my $handle = ListInit($node->{children});
    while (my $child = ListGetNext($handle)) {
	my $type = ref $child;

	if ($type eq 'PPI::Statement') {
	    ProcessStatement($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Statement::Include') {
	    ProcessStatementInclude($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Statement::Package') {
	    ProcessStatementPackage($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Statement::Sub') {
	    ProcessStatementSub($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Statement::Variable') {
	    ProcessStatementVariable($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Statement::Break') {
	    ProcessStatementBreak($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Statement::Compound') {
	    ProcessStatementCompound($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Comment') {
	    my $content = $child->content;
	    next if ($content =~ /^#!/);

	    $phpObj->outToken($node, $content);

	} elsif ($type eq 'PPI::Token::Whitespace') {
	    $phpObj->outToken($node, $child->content);

	} else {
	    print STDERR "PPI::Document unhandled type: $type\n";
	}
    }

    if ($EndBraceFlag) {
	$phpObj->outToken(undef, "}\n");
    }

    return $phpObj;
}

sub ProcessStatement
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = new PhpStatement($node, $phpParentObj);
    $phpParentObj->addChild($phpObj);

    # Scan for if statement in the children to see if we have an if modifier
    # like "$a += 10 if $b == 0;";
    my $ifModifier = 0;
    my $hadOther = 0;
    my $lastToken;
    foreach my $child (@{$node->{children}}) {
	my $type = ref $child;
	next if ($type eq 'PPI::Token::Whitespace');
	if ($type eq 'PPI::Token::Word' && $child->content eq 'if') {
	    if ($hadOther) {
		$ifModifier = 1;
	    }
	} else {
	    $hadOther = 1;
	    $lastToken = $child->content if defined($child->content);
	}
    }

    my $ifIdx;

    my $handle = ListInit($node->{children});
    while (my $child = ListGetNext($handle)) {
	my $type = ref $child;

	if ($type eq 'PPI::Structure::Block') {
	    ProcessStructureBlock($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Structure::Condition') {
	    ProcessStructureCondition($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Structure::Constructor') {
	    ProcessStructureConstructor($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Structure::List') {
	    ProcessStructureList($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Structure::Subscript') {
	    ProcessStructureSubscript($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Cast') {
	    ProcessTokenCast($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Magic') {
	    ProcessTokenMagic($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Number') {
	    ProcessTokenNumber($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Operator') {
	    ProcessTokenOperator($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Quote::Double') {
	    ProcessTokenQuoteDouble($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Quote::Single') {
	    ProcessTokenQuoteSingle($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::QuoteLike::Words') {
	    ProcessTokenQuoteLikeWords($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Regexp::Match') {
	    ProcessTokenRegexpMatch($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Regexp::Substitute') {
	    ProcessTokenRegexpSubstitute($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Structure') {
	    ProcessTokenStructure($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Structure::For') {
	    ProcessStructureFor($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Label') {
	    ProcessTokenLabel($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Symbol') {
	    ProcessTokenSymbol($phpObj, $child, $node, $handle);

	    # If declaring a local variable without an initializer,
	    # then we need to add an initializer.

	    my $subnode = ListGetLastNonWhite();
	    if (defined($subnode) && $subnode->content eq 'my') {
		$subnode = ListLookaheadNonWhite($handle);
		if (defined($subnode) && $subnode->content eq ';') {
		    $phpObj->outToken($node, ' = null');
		}
	    }

	} elsif ($type eq 'PPI::Token::Whitespace') {
	    ProcessTokenWhitespace($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Word') {
	    my $content = $child->content;

	    if ($content eq 'my') {
		ListGetNext($handle);	    # Skip whitespace
		next;
	    }

	    ProcessTokenWord($phpObj, $child, $node, $handle);

	    if ($content eq 'if') {
		# Save position of 'if', if we need it

		$ifIdx = $#{$phpObj->{children}};
	    }

	} else {
	    print STDERR "PPI::Statement unhandled type: $type\n";
	}
    }

    # Check if had an if modifier. Note that we also need to check if
    # the last token is a semicolon, because we want this to be done by
    # the outermost PPI:Statement. Some inner ones may actually contain the
    # 'if', but we don't want to process this there.

    my $startIdx = 0;
print $outlog "SCANNING: startIdx = $startIdx\n";
print $outlog "    ifModifier = $ifModifier\n";
print $outlog "    lastToken = $lastToken\n";
    if ($ifModifier && $lastToken eq ';') {
	# We have an if modifier expression. Reverse the sense and rewrite
	# the code.

print $outlog "    ifIdx = $ifIdx\n";
	my $children = $phpObj->{children};
	my $endIdx = $#$children;
print $outlog "    endIdx = $endIdx\n";

	my $ifPhpObj = $children->[$ifIdx];
	my @exprTokens = (@$children)[$startIdx .. $ifIdx - 1];
	my @ifTokens = (@$children)[$ifIdx+1 .. $endIdx-1];
	trimPhpList(\@exprTokens);
	trimPhpList(\@ifTokens);

	if ($children->[$endIdx]->content ne ';') {
	    my $s = $children->[$endIdx]->content;
	    print STDERR "Bad modifier: end token was $s\n";
	    exit(0);
	}

	# Get whitespace before statement

	my $scanPhp = $children->[$startIdx];
print $outlog "Scanning back from " . dspPhpObj($scanPhp) . "\n";
	my $ws = '';
	for(;;) {
	    $scanPhp = $scanPhp->getPrevToken();
	    my $s = $scanPhp->content;
print $outlog "CHECK WS: s = '$s'\n";

	    last if ($s !~ /^\s+$/);
	    if ($s =~ /\n/) {
		$s =~ s/.*\n//;
		$ws .= $s;
		last;
	    }
	    $ws .= $s;
	}

##	my $ws = '';
##	for (my $idx = $startIdx; --$idx >= 0; ) {
##	    my $s = $children->[$idx]->content;
##print $outlog "CHECK WS: s = '$s'\n";
##	    last if ($s !~ /^\s+$/);
##	    if ($s =~ /\n/) {
##		$s =~ s/.*\n//;
##		$ws .= $s;
##		last;
##	    }
##	    $ws .= $s;
##	}

print $outlog "ws: '$ws'\n";
print $outlog "expr: " . join(' : ', @exprTokens) . "\n";
print $outlog "if: " . join(' : ', @ifTokens) . "\n";

	my (@p1, @p2);
	if ($ifTokens[0]->content ne '(') {
	    @p1 = ( PhpToken->new($node, '(') );
	    @p2 = ( PhpToken->new($node, ')') );
	}

	# Set new list of children
	$phpObj->{children} = [ $ifPhpObj, PhpToken->new(undef, ' '), @p1,
	    @ifTokens, @p2,
	    PhpToken->new(undef, ' '),
	    PhpToken->new(undef, '{'),
	    PhpToken->new(undef, "\n"),
	    PhpToken->new(undef, unexpand($ws. '    ')),
	    @exprTokens,
	    PhpToken->new(undef, ';'),
	    PhpToken->new(undef, "\n"),
	    PhpToken->new(undef, $ws),
	    PhpToken->new(undef, '}'),
	    PhpToken->new(undef, "\n") ];
    }

    return;
}

sub ProcessTokenWord
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = $phpParentObj;

    my $content = $node->content;
    if ($content eq 'my') {
	# Don't need 'my' statements

    } elsif ($content eq 'caller') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq 'chop') {
	$phpObj->outToken($node, "/*check:chop*/");

    } elsif ($content eq 'defined') {
	$phpObj->outToken($node, 'isset');

    } elsif ($content eq 'else') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq 'elsif') {
	$phpObj->outToken($node, 'elseif');

    } elsif ($content eq 'for') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq 'foreach') {
	$phpObj->outToken($node, "/*check*/$content");

    } elsif ($content eq 'goto') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq 'if') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq 'join') {
	$phpObj->outToken($node, 'implode');

    } elsif ($content eq 'last') {
	$phpObj->outToken($node, 'break');

    } elsif ($content eq 'next') {
	$phpObj->outToken($node, 'continue');

    } elsif ($content eq 'length') {
	$phpObj->outToken($node, 'strlen');

    } elsif ($content eq 'local') {
	;

    } elsif ($content eq 'package') {
	$phpObj->outToken($node, 'class');
	$InClass = 1;

    } elsif ($content eq 'print') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq 'push') {
	$phpObj->outToken($node, 'array_push');

    } elsif ($content eq 'require') {
	;

    } elsif ($content eq 'return') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq 'shift') {
	$phpObj->outToken($node, 'array_shift');

    } elsif ($content eq 'split') {
	$phpObj->outToken($node, 'explode');

    } elsif ($content eq 'strict') {
	;

    } elsif ($content eq 'sub') {
	# In this context, sub is a anonymous function
	$phpObj->outToken($node, 'function');
	$phpObj->outToken($node, ' ');
	$phpObj->outToken($node, '(');
	$phpObj->outToken($node, ')');

    } elsif ($content eq 'substr') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq 'uc') {
	$phpObj->outToken($node, 'strtoupper');

    } elsif ($content eq 'unpack') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq 'use') {
	;

    } elsif ($content eq 'while') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq 'STDERR') {
	;	    # Just ignore STDERR

    } elsif ($content eq 'EXIT') {
	my $subnode = ListGetLastNonWhite();
	if (defined($subnode) && $subnode->content eq 'goto') {
	    # Using reserved word in goto

	    $content = 'EXITER';
	}
	$phpObj->outToken($node, $content);

    } elsif ((ref $parent) eq 'PPI::Statement::Expression') {
	# Bareword within hash index or '=>' operator

	$phpObj->outToken($node, "'$content'");

    } else {
	print STDERR "ProcessTokenWord: Unknown content $content\n";
	$phpObj->outToken($node, $content);
    }

    return;
}

sub ProcessTokenComment
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = $phpParentObj;

    $phpObj->outToken($node, $node->content);

    return;
}

sub ProcessTokenQuoteSingle
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = $phpParentObj;

    $phpObj->outToken($node, $node->content);

    return;
}

sub ProcessTokenLabel
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = $phpParentObj;

    my $content = $node->content;
    if ($content eq 'EXIT:') {
	# Reserved word

	$content = 'EXITER:';
    }
    $phpObj->outToken($node, $content);

    return;
}

sub ProcessTokenMagic
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = $phpParentObj;

    my $content = $node->content;
    if ($content eq '$_') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq '@_') {
	$phpObj->outToken($node, 'func_get_args()/*check*/');

    } elsif ($content =~ /\$[0-9]+$/) {
	$phpObj->outToken($node, "$content/*check*/");

    } else {
	print STDERR "ProcessTokenMagic Unknown content $content\n";
	$phpObj->outToken($node, $content);
    }



    return;
}

sub ProcessTokenStructure
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = $phpParentObj;

    my $content = $node->content;
    if ($content eq ';') {
	$phpObj->outToken($node, $content);
    } else {
	print STDERR "ProcessTokenStructure: Unknown content $content\n";
	$phpObj->outToken($node, $content);
    }

    return;
}

sub ProcessTokenSymbol
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = $phpParentObj;

    my $content = $node->content;
    my $isVar = 0;

    if ($content =~ /\$\w+/) {
	# Variable name, pass it through

	$phpObj->outToken($node, $content);
	$isVar = 1;

    } elsif ($content =~ /\@\w+/) {
	# Array variable

	$phpObj->outToken($node, '$' . substr($content, 1));
	$isVar = 1;

    } elsif ($content =~ /\%\w+/) {
	# Map variable

	$phpObj->outToken($node, '$' . substr($content, 1));
	$isVar = 1;

    } elsif ($content =~ /&\w+/) {
	# Function

	my $func = substr($content, 1);
	if ($func =~ s/::/\\/g) {
	    $func = "\\$func";
	}
	$phpObj->outToken($node, $func);

    } else {
	print STDERR "ProcessTokenSymbol: Unknown content $content\n";
	$phpObj->outToken($node, $content);
    }

    if ($isVar && $BraceLevel == 0 && $InClass) {
       my $subnode = ListLookaheadNonWhite($parentHandle);
       if (defined($subnode)) {
	   if ($subnode->content eq '=') {
	       # Add a 'protected' in front

	       $phpObj->insertToken($node, 'protected ', -1);
	    }
	}
    }

    return;
}
sub ProcessTokenQuoteDouble
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = $phpParentObj;

    $phpObj->outToken($node, $node->content);

    return;
}

sub ProcessStatementExpression
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = new PhpStatement($node, $phpParentObj);
    $phpParentObj->addChild($phpObj);

    my $handle = ListInit($node->{children});
    while (my $child = ListGetNext($handle)) {
	my $type = ref $child;

	if ($type eq 'PPI::Structure::Block') {
	    ProcessStructureBlock($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Structure::Constructor') {
	    ProcessStructureConstructor($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Structure::List') {
	    ProcessStructureList($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Structure::Subscript') {
	    ProcessStructureSubscript($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Cast') {
	    ProcessTokenCast($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Magic') {
	    ProcessTokenMagic($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Number') {
	    ProcessTokenNumber($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Operator') {
	    ProcessTokenOperator($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Quote::Double') {
	    ProcessTokenQuoteDouble($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Quote::Single') {
	    ProcessTokenQuoteSingle($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Regexp::Match') {
	    ProcessTokenRegexpMatch($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Symbol') {
	    ProcessTokenSymbol($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Whitespace') {
	    ProcessTokenWhitespace($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Word') {
	    ProcessTokenWord($phpObj, $child, $node, $handle);

	} else {
	    print STDERR "PPI::Statement::Expression unhandled type: $type\n";
	}
    }

    return;
}

sub ProcessTokenOperator
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = $phpParentObj;

    my $content = $node->content;

    if ($content eq '<=') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq '<') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq '=~') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq '==') {
	$phpObj->outToken($node, "/*check*/$content");

    } elsif ($content eq '=>') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq '=') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq '>=') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq '>') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq '->') {
	;

    } elsif ($content eq '-') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq ',') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq ':') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq '!~') {
	$phpObj->outToken($node, "/*check*/$content");

    } elsif ($content eq '!=') {
	$phpObj->outToken($node, "/*check*/$content");

    } elsif ($content eq '!') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq '?') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq '.=') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq '.') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq '*') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq '+=') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq '+') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq '++') {
	$phpObj->outToken($node, $content);

    } elsif ($content eq 'and') {
	$phpObj->outToken($node, '&&');

    } elsif ($content eq 'eq') {
	$phpObj->outToken($node, '/*check*/==');

    } elsif ($content eq 'ne') {
	$phpObj->outToken($node, '/*check*/!=');

    } elsif ($content eq 'or') {
	$phpObj->outToken($node, '||');

    } elsif ($content eq 'x') {
	$phpObj->outToken($node, "/*check:str_repeat*/.");

    } else {
	print STDERR "ProcessTokenOperator Unknown content $content\n";
	$phpObj->outToken($node, $content);
    }


    return;
}

sub DoRegEx
{
    my $phpObj = shift;
    my $node = shift;
    my $pattern = shift;

    my $delim = substr($pattern, 1, 1);
    $pattern =~ /s(${delim}.*?${delim})(.*)${delim}(.*)/;
    my $pat1 = $1;
    my $pat2 = $2;
    my $modifiers = $3;
    my $glob = 0;
    if ($modifiers =~ s/g//) {
	$glob = 1;
    }

    my $phpChildren = $phpObj->{children};

    my $opr = pop(@{$phpObj->{children}})->content;
    if ($opr =~ /^\s+$/) {
	$opr = pop(@{$phpObj->{children}})->content;
    }

    my $var = pop(@{$phpObj->{children}})->content;
    if ($var =~ /^\s+$/) {
	$var = pop(@{$phpObj->{children}})->content;
    }

    my $limit = '';
    if (! $glob) {
	$limit = ", 1";
    }

    if ($opr eq '=~') {
	$phpObj->outToken($node, "$var = preg_replace('$pat1$modifiers', '$pat2', $var$limit);/*check*/");
    }
print $outlog "opr = $opr\n";
print $outlog "var = $var\n";
print $outlog "pat1 = $pat1\n";
print $outlog "pat2 = $pat2\n";

    return;
}

sub ProcessTokenRegexpMatch
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = $phpParentObj;

print $outlog "..........................MATCH!\n";
    my $content = $node->content;
print $outlog "    content = $content\n";
    $phpObj->outToken($node, "'$content'");

    # Look back and see if we translated to explode, which should be a
    # preg_split when we have a pattern.

    my $idx = $#{$phpObj->{children}};
    while (--$idx >= 0 && $phpObj->{children}->[$idx]->content =~ /^\s+/) {
	;
    }
    if ($idx >= 0) {
	my $token1 = $phpObj->{children}->[$idx]->content;
print $outlog "    token1 = $token1\n";
	while (--$idx >= 0 && $phpObj->{children}->[$idx]->content =~ /^\s+/) {
	    ;
	}
	if ($idx >= 0) {
	    my $token2 = $phpObj->{children}->[$idx]->content;
print $outlog "    token2 = $token1\n";
	    if ($token1 eq '(' && $token2 eq 'explode') {
		$phpObj->{children}->[$idx] =
		    new PhpToken($node, 'preg_replace', $phpObj);
	    }
	}
    }

    return;
}

sub ProcessStatementSub
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = new PhpStatement($node, $phpParentObj);
    $phpParentObj->addChild($phpObj);

    my $handle = ListInit($node->{children});
    while (my $child = ListGetNext($handle)) {
	my $type = ref $child;

	if ($type eq 'PPI::Token::Word') {
	    my $content = $child->content;
	    next if $content eq 'sub';

	    $phpObj->outToken($node, "function ${content}()\n");
	    ListZapWS($handle);	# Suck up whitespace

	} elsif ($type eq 'PPI::Structure::Block') {
	    ProcessStructureBlock($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Whitespace') {
	    ;

	} else {
	    print STDERR "PPI::Statement::Sub unhandled type: $type\n";
	}
    }

    return;
}

sub ProcessTokenNumber
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = $phpParentObj;

    $phpObj->outToken($node, $node->content);

    return;
}

sub ProcessStructureBlock
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = new PhpStructure($node, $phpParentObj);
    $phpParentObj->addChild($phpObj);

    my $start = $node->start ? $node->start->content : '???';
    my $finish = $node->finish ? $node->finish->content : '???';

    my $last = ListGetLastNonWhite();
    if (defined($last)) {
	if ($last->content eq '@') {
	    # Cast to list, don't need

	    $start = '';
	    $finish = '';
	}
    }

    $phpObj->setContents($start, $finish);

    ++$BraceLevel;

    my $handle = ListInit($node->{children});
    while (my $child = ListGetNext($handle)) {
	my $type = ref $child;

	if ($type eq 'PPI::Statement') {
	    ProcessStatement($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Statement::Break') {
	    ProcessStatementBreak($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Statement::Compound') {
	    ProcessStatementCompound($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Statement::Include') {
	    ProcessStatementInclude($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Statement::Variable') {
	    ProcessStatementVariable($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Comment') {
	    $phpObj->outToken($node, $child->content);

	} elsif ($type eq 'PPI::Token::Whitespace') {
	    $phpObj->outToken($node, $child->content);

	} else {
	    print STDERR "PPI::Structure::Block unhandled type: $type\n";
	}
    }

    --$BraceLevel;
    return;
}

sub ProcessStatementVariable
{
    ProcessStatement(@_);

##    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
##
##    foreach my $child (@{$node->{children}}) {
##	my $type = ref $child;
##
##	if ($type eq 'PPI::Structure::Block') {
##	    ProcessStructureBlock($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Structure::Constructor') {
##	    ProcessStructureConstructor($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Structure::List') {
##	    ProcessStructureList($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Structure::Subscript') {
##	    ProcessStructureSubscript($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Token::Cast') {
##	    ProcessTokenCast($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Token::Magic') {
##	    ProcessTokenMagic($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Token::Number') {
##	    ProcessTokenNumber($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Token::Operator') {
##	    ProcessTokenOperator($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Token::Quote::Double') {
##	    ProcessTokenQuoteDouble($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Token::Quote::Single') {
##	    ProcessTokenQuoteSingle($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Token::Structure') {
##	    ProcessTokenStructure($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Token::Symbol') {
##	    ProcessTokenSymbol($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Token::Whitespace') {
##	    ProcessTokenWhitespace($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Token::Word') {
##	    ProcessTokenWord($phpObj, $child, $node, $handle);
##
##	} else {
##	    print STDERR "PPI::Statement::Variable unhandled type: $type\n";
##	}
##    }
##
##    return;
}

sub ProcessStatementCompound
{
    ProcessStatement(@_);

##    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
##
##    my $handle = ListInit($node->{children});
##    while (my $child = ListGetNext($handle)) {
##	my $type = ref $child;
##
##	if ($type eq 'PPI::Structure::Block') {
##	    ProcessStructureBlock($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Structure::Condition') {
##	    ProcessStructureCondition($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Structure::For') {
##	    ProcessStructureFor($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Structure::List') {
##	    ProcessStructureList($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Token::Label') {
##	    ProcessTokenLabel($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Token::Symbol') {
##	    ProcessTokenSymbol($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Token::Whitespace') {
##	    ProcessTokenWhitespace($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Token::Word') {
##	    ProcessTokenWord($phpObj, $child, $node, $handle);
##
##	} else {
##	    print STDERR "PPI::Statement::Compound unhandled type: $type\n";
##	}
##    }

    return;
}

sub ProcessTokenRegexpSubstitute
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = $phpParentObj;

    DoRegEx($phpObj, $node, $node->content);

    return;
}

sub ProcessTokenCast
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = $phpParentObj;

    my $content = $node->content;
    if ($content eq '@') {
	# Cast to array or get length of list. Very difficult to
	# differentiate.

	$phpObj->outToken($node, '/*check:cast@*/');
    } else {
	print STDERR "PPI::Token::Cast unhandled type: $content\n";
    }

    return;
}

sub ProcessStatementPackage
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = new PhpStatement($node, $phpParentObj);
    $phpParentObj->addChild($phpObj);

    my $handle = ListInit($node->{children});
    while (my $child = ListGetNext($handle)) {
	my $type = ref $child;

	if ($type eq 'PPI::Token::Structure') {

	} elsif ($type eq 'PPI::Token::Whitespace') {

	} elsif ($type eq 'PPI::Token::Word') {
	    my $content = $child->content;

	    if ($content eq 'package') {
		my $space1 = ListGetNext($handle)->content;
		my $name = ListGetNext($handle)->content;
		my $semi = ListGetNext($handle)->content;
		if ((ref $semi) eq 'PPI::Token::Whitespace') {
		    $semi = ListGetNext($handle)->content;
		}

		if ($name =~ /::/) {
		    $name =~ /(.*)::(.*)/;
		    my $ns = $1;
		    $name = $2;
		    $ns =~ s/::/\\/g;
		    $phpObj->outToken($node, "namespace $ns;");
		    $phpObj->outToken($node, "\n");
		    $phpObj->outToken($node, "\n");
		}

		$phpObj->outToken($node, "class $name\n{");
		$EndBraceFlag = 1;
		$InClass = 1;
		next;
	    }

	} else {
	    print STDERR "PPI::Statement::Package unhandled type: $type\n";
	}
    }

    return;
}

sub ProcessStructureFor
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = new PhpStructure($node, $phpParentObj);
    $phpParentObj->addChild($phpObj);

    my $start = $node->start ? $node->start->content : '???';
    my $finish = $node->finish ? $node->finish->content : '???';

    $phpObj->setContents($start, $finish);

    my $handle = ListInit($node->{children});
    while (my $child = ListGetNext($handle)) {
	my $type = ref $child;

	if ($type eq 'PPI::Statement') {
	    ProcessStatement($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Statement::Variable') {
	    ProcessStatementVariable($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Statement::Null') {
	    $phpObj->outToken($node, $child->content);

	} elsif ($type eq 'PPI::Token::Whitespace') {
	    ProcessTokenWhitespace($phpObj, $child, $node, $handle);

	} else {
	    print STDERR "PPI::Structure::For unhandled type: $type\n";
	}
    }

    return;
}

sub ProcessTokenWhitespace
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = $phpParentObj;

    $phpObj->outToken($node, $node->content);

    return;
}

sub ProcessStatementInclude
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = new PhpStatement($node, $phpParentObj);
    $phpParentObj->addChild($phpObj);

    # Throw away entire line
    ListZapNL($parentHandle);
    return;

    my $handle = ListInit($node->{children});
    while (my $child = ListGetNext($handle)) {
	my $type = ref $child;

	if ($type eq 'PPI::Token::Structure') {
	    ProcessTokenStructure($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Whitespace') {
	    ProcessTokenWhitespace($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Word') {
	    ProcessTokenWord($phpObj, $child, $node, $handle);

	} else {
	    print STDERR "PPI::Statement::Include unhandled type: $type\n";
	}
    }

    return;
}

sub ProcessStatementBreak
{
    ProcessStatement(@_);

##    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
##
##    my $handle = ListInit($node->{children});
##    while (my $child = ListGetNext($handle)) {
##	my $type = ref $child;
##
##	if ($type eq 'PPI::Structure::Condition') {
##	    ProcessStructureCondition($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Structure::List') {
##	    ProcessStructureList($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Structure::Subscript') {
##	    ProcessStructureSubscript($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Token::Magic') {
##	    ProcessTokenMagic($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Token::Number') {
##	    ProcessTokenNumber($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Token::Operator') {
##	    ProcessTokenOperator($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Token::Quote::Double') {
##	    ProcessTokenQuoteDouble($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Token::Quote::Single') {
##	    ProcessTokenQuoteSingle($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Token::Regexp::Match') {
##	    ProcessTokenRegexpMatch($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Token::Structure') {
##	    ProcessTokenStructure($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Token::Symbol') {
##	    ProcessTokenSymbol($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Token::Whitespace') {
##	    ProcessTokenWhitespace($phpObj, $child, $node, $handle);
##
##	} elsif ($type eq 'PPI::Token::Word') {
##	    ProcessTokenWord($phpObj, $child, $node, $handle);
##
##	} else {
##	    print STDERR "PPI::Statement::Break unhandled type: $type\n";
##	}
##    }
##    return;
}

sub ProcessStructureConstructor
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = new PhpStructure($node, $phpParentObj);
    $phpParentObj->addChild($phpObj);

    my $start = $node->start ? $node->start->content : '???';
    my $finish = $node->finish ? $node->finish->content : '???';

    # Array definition
    $start = '[';
    $finish = ']';

    $phpObj->setContents($start, $finish);

    my $handle = ListInit($node->{children});
    while (my $child = ListGetNext($handle)) {
	my $type = ref $child;

	if ($type eq 'PPI::Statement') {
	    ProcessStatement($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Statement::Expression') {
	    ProcessStatementExpression($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Whitespace') {
	    ProcessTokenWhitespace($phpObj, $child, $node, $handle);

	} else {
	    print STDERR "PPI::Structure::Constructor unhandled type: $type\n";
	}
    }

    return;
}

sub ProcessStructureCondition
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = new PhpStructure($node, $phpParentObj);
    $phpParentObj->addChild($phpObj);

    my $start = $node->start ? $node->start->content : '???';
    my $finish = $node->finish ? $node->finish->content : '???';

    $phpObj->setContents($start, $finish);

    my $handle = ListInit($node->{children});
    while (my $child = ListGetNext($handle)) {
	my $type = ref $child;

	if ($type eq 'PPI::Statement::Expression') {
	    ProcessStatementExpression($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Statement') {
	    ProcessStatement($phpObj, $child, $node, $handle);

	} else {
	    print STDERR "PPI::Structure::Condition unhandled type: $type\n";
	}
    }

    return;
}

sub ProcessTokenQuoteLikeWords
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = $phpParentObj;

    my $content = $node->content;
    $content =~ s/qw\(\s*//;
    $content =~ s/\s*\)$//;

    my @words = map { s/'/\\'/g; $_; } split(/\s+/, $content);
    if (@words) {
	$phpObj->outToken($node, "[ '" . join("', '", @words) . "' ]");
    }

    return;
}

sub ProcessStructureList
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = new PhpStructure($node, $phpParentObj);
    $phpParentObj->addChild($phpObj);

    my $start = $node->start ? $node->start->content : '???';
    my $finish = $node->finish ? $node->finish->content : '???';

    # Check if this is a "list() = ..." type expression

    my $ahead1 = ListLookahead($parentHandle, 0);
    my $ahead2 = ListLookahead($parentHandle, 1);
    my $endIdx = $#{$phpObj->{children}};
    my $phpBehind1 = $endIdx >= 0 ? $phpObj->{children}->[$endIdx]->content : '';
    my $phpBehind2 = $endIdx >= 1 ? $phpObj->{children}->[$endIdx-1]->content : '';

    if (($phpBehind1 =~ /^[\w\\]+$/) || ($phpBehind1 =~ /^[\w\\]+$/)) {
	# Word before -- looks like function call

	$start = '(';
	$finish = ')';

    } elsif ((defined($ahead1) && $ahead1->content eq '=') ||
		(defined($ahead2) && $ahead2->content eq '=')) {
	$start = 'list(';
	$finish = ')';

    } elsif ((ref $parent) eq 'PPI::Statement::Variable') {
	# Might be array definition, see if variable was an array

	my $subnode1 = ListGetLastNonWhite(1);
	my $subnode2 = ListGetLastNonWhite(2);
	my $c2 = defined($subnode2) ? substr($subnode2->content, 0, 1) : '';
	if (defined($subnode1) && defined($subnode2) &&
		    $subnode1->content eq '=' && ($c2 eq '@' || $c2 eq '%')) {
	    # Looks like an array definition

	    $start = '[';
	    $finish = ']';
	}
    }

    $phpObj->setContents($start, $finish);

    my $handle = ListInit($node->{children});
    while (my $child = ListGetNext($handle)) {
	my $type = ref $child;

	if ($type eq 'PPI::Statement') {
	    ProcessStatement($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Statement::Expression') {
	    ProcessStatementExpression($phpObj, $child, $node, $handle);

	} elsif ($type eq 'PPI::Token::Whitespace') {
	    ProcessTokenWhitespace($phpObj, $child, $node, $handle);

	} else {
	    print STDERR "PPI::Structure::List unhandled type: $type\n";
	}
    }

    return;
}

sub ProcessStructureSubscript
{
    my ($phpParentObj, $node, $parent, $parentHandle) = @_;
    my $phpObj = new PhpStructure($node, $phpParentObj);
    $phpParentObj->addChild($phpObj);

    my $start = $node->start ? $node->start->content : '???';
    my $finish = $node->finish ? $node->finish->content : '???';

    $start = '[';
    $finish = ']';

    $phpObj->setContents($start, $finish);

    my $handle = ListInit($node->{children});
    while (my $child = ListGetNext($handle)) {
	my $type = ref $child;

	if ($type eq 'PPI::Statement::Expression') {
	    ProcessStatementExpression($phpObj, $child, $node, $handle);

	} else {
	    print STDERR "PPI::Structure::Subscript unhandled type: $type\n";
	}
    }

    return;
}

##my $CurList;
##
##sub my $handle = ListInit
##{
##    my $list = shift @_;
##    $CurList = $list;
##}
##
##sub ListGetNext
##{
##    my $next = shift @$CurList;
##print STDERR "Returning $next\n";
##    return $next;
##}

my $Handle = 0;
my %CurList;
#use vars qw(@LastList);

sub ListInit
{
    ++$Handle;
    $CurList{$Handle} = shift @_;
    return $Handle;
}

sub ListGetNext
{
    use vars qw(@LastList);
    my $handle = shift;
    my $next = shift @{$CurList{$handle}};
    if (defined($next)) {
	unshift(@LastList, $next);
	pop(@LastList);
    }
if (defined($next)) {
print $outlog "NEXT: " . getType($next) . "\n";
}
    return $next;
}

sub ListPutBack
{
    my ($handle, $node) = @_;
    unshift(@{$CurList{$handle}}, $node);
}

sub ListLookahead
{
    my ($handle, $idx) = @_;
    return $CurList{$handle}->[$idx];
}

sub ListLookaheadNonWhite
{
    my ($handle) = @_;
    foreach my $node (@{$CurList{$handle}}) {
	if (ref($node) ne 'PPI::Token::Whitespace') {
	    return $node;
	}
    }

    return undef;
}

# Remove whitespace if next token in list, unless it's a newline

sub ListZapWS
{
    my ($handle) = @_;
    my $next = $CurList{$handle}->[0];
    if (defined($next) && (ref $next) eq 'PPI::Token::Whitespace') {
	my $content = $next->content;
	if ($content !~ /\n/) {
	    shift @{$CurList{$handle}};
	}
    }
}

# Remove whitespace if next token in list is a newline

sub ListZapNL
{
    my ($handle) = @_;
    my $next = $CurList{$handle}->[0];
    if (defined($next) && (ref $next) eq 'PPI::Token::Whitespace') {
	my $content = $next->content;
	if ($content =~ /\n/) {
	    shift @{$CurList{$handle}};
	}
    }
}

sub ListGetLast
{
    return $LastList[1];
}

sub ListGetLastNonWhite
{
    my $count = shift;
    $count = 1 if ! defined($count);
    use vars qw(@LastList);

    for (my $i = 1; $i < @LastList; ++$i) {
	if (defined($LastList[$i]) &&
		    (ref $LastList[$i]) ne 'PPI::Token::Whitespace') {
	    if (--$count <= 0) {
		return $LastList[$i];
	    }
	}
    }

    return undef;
}

sub getType
{
    my $node = shift;
    my $string = ref $node;

    if ($node->isa('PPI::Token')) {
	# Add the content

	my $content = $node->content;
	$content =~ s/\n/\\n/g;
	$content =~ s/\t/\\t/g;
	$string .= "  \t'$content'";
    } elsif ($node->isa('PPI::Structure')) {
	# Add the content

	my $start = $node->start ? $node->start->content : '???';
	my $finish = $node->finish ? $node->finish->content : '???';
	$string .= "  \t$start ... $finish";
    }

    return $string;
}

sub trimPhpList
{
    my $list = shift;
    for(;;) {
	my $php = shift @$list;
	last if ! defined($php);
	if ($php->content !~ /^\s+$/) {
	    unshift(@$list, $php);
	    last;
	}
    }

    for(;;) {
	my $php = pop @$list;
	last if ! defined($php);
	if ($php->content !~ /^\s+$/) {
	    push(@$list, $php);
	    last;
	}
    }

    return;
}

sub dspPhpObj
{
    my $obj = shift;
    return "PhpObj " . ref($obj) . (ref($obj) eq 'PhpToken' ?
	(", Content is '" . $obj->content . "'") : '');
}

package PhpToken;
    sub new
    {
	use Data::Dumper;

	my ($class, $node, $content, $phpParent) = @_;
	my $self = {
	    #'ref_obj' => $node,
	    'ref_type' => defined($node) ? ref($node) : '',
	    'parent' => $phpParent,
	    'content' => defined($content) ? $content : '',
	};

print $outlog "CREATE TOKEN: content = '$content'\n";
	bless $self, $class;
	return $self;
    }

    sub content
    {
	return $_[0]->{'content'};
    }

    sub getLastTokenLeaf
    {
	my ($self, $phpObj) = @_;

print $outlog "    getLastTokenLeaf\n";
	my $children = $phpObj->{children};
	return undef if (@$children == 0);

	# Scan backward looking for last token child, if we hit an aggregate,
	# recursively scan that one.
	for (my $idx = $#{$children}; $idx >= 0; --$idx) {
	    my $last = $children->[$idx];
print $outlog "    getLastTokenLeaf: last = " . ref($last) . "\n";
	    if (ref($last) eq 'PhpToken') {
		return $last;
	    }

	    $last = $self->getLastTokenLeaf($last);
	    return $last if defined($last);

	    # Try back one in the last
	}

	return undef;		    # Ran out of children
    }

    sub getPrevToken
    {
	my $self = shift;

	my $scanObj = $self;
	while ($scanObj) {
	    my $parent = $scanObj->{parent};
	    my $children = $parent->{children};
print $outlog "getPrevToken: parent = " . ref($parent) . "($parent)\n";

	    my ($idx, $foundIdx);
	    for ($idx = 0; $idx < @$children; ++$idx) {
print $outlog "    scan: child = $children->[$idx]\n";
		if ($children->[$idx] == $scanObj) {
		    $foundIdx = $idx;
		    last;
		}
	    }

print $outlog "    foundIdx = $foundIdx\n";
	    if (! defined($foundIdx)) {
		print STDERR "ERROR: token not found, self = $self\n";
		print STDERR "Parent: $parent\n";
		print STDERR "Children:";
		foreach my $child (@$children) {
		    print STDERR " $child";
		}
		print $outlog "\n";
		exit(1);
	    }

	    $idx = $foundIdx;
	    while (--$idx >= 0) {
		my $php = $children->[$idx];
print $outlog "    Backing up, idx = $idx, type = " . ref($php) . (ref($php) eq 'PhpToken' ? ("Contents: '" . $php->content . "'") : '') . "\n";
		return $php if (ref($php) eq 'PhpToken');

print $outlog "    Calling getLastTokenLeaf\n";
		my $leaf = $self->getLastTokenLeaf($php);
		return $leaf if (defined($leaf));
	    }

	    # Need to go up a level

print $outlog "    Going up a level...\n";
#	    $parent = $parent->{parent};
#	    $children = defined($parent) ? $parent->{children} : undef;
#	    $idx = defined($children) ? @$children : 0;
	    $scanObj = $parent->{parent};
	}

	return undef;
    }

package PhpAggregateBase;
    sub new
    {
	my ($class, $node, $phpParent) = @_;
print $outlog "CREATE AGGREG $class\n";
	my $self = {
	    #'ref_obj' => $node,
	    'ref_type' => ref $node,
	    'parent' => $phpParent,
	    'children' => [],
	};

	bless $self, $class;
	return $self;
    }

    sub outToken
    {
	my ($self, $node, $content) = @_;
	my $token = new PhpToken($node, $content, $self);

	push(@{$self->{children}}, $token);
    }

    sub insertToken
    {
	my ($self, $node, $content, $offset) = @_;
	my $token = new PhpToken($node, $content, $self);
	my $idx = @{$self->{children}} + $offset;
	splice(@{$self->{children}}, $idx, 0, $token);
    }

    sub setContents
    {
	my $self = shift;
	$self->{start} = shift;
	$self->{finish} = shift;
    }

    sub addChild
    {
	use Data::Dumper;

	my $self = shift;
	push(@{$self->{children}}, shift);
#print $outlog "addChild: object $self is now " . Dumper(\$self);
    }

    sub content
    {
	return '';
    }

package PhpStructure;
    use parent -norequire, qw( PhpAggregateBase );

    sub new
    {
	my $self = PhpAggregateBase::new(@_);
	$self->{start} = '';
	$self->{finish} = '';
	return $self;
    }

package PhpStatement;
    use parent -norequire, qw( PhpAggregateBase );
