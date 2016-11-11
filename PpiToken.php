<?php

class PpiTokenWhitespace extends PpiToken { }
class PpiTokenPod extends PpiToken { }
class PpiTokenNumberBinary extends PpiTokenNumber { }
class PpiTokenNumberOctal extends PpiTokenNumber { }
class PpiTokenNumberHex extends PpiTokenNumber { }
class PpiTokenNumberFloat extends PpiTokenNumber { }
class PpiTokenNumberExp extends PpiTokenNumberFloat { }
class PpiTokenNumberVersion extends PpiTokenNumber { }
class PpiTokenDashedWord extends PpiToken { }
class PpiTokenArrayIndex extends PpiToken { }
class PpiTokenQuoteSingle extends PpiTokenQuote { }
class PpiTokenQuoteDouble extends PpiTokenQuote { }
class PpiTokenQuoteLiteral extends PpiTokenQuote { }
class PpiTokenQuoteLike extends PpiToken { }
class PpiTokenQuoteLikeBacktick extends PpiTokenQuoteLike { }
class PpiTokenQuoteLikeCommand extends PpiTokenQuoteLike { }
class PpiTokenQuoteLikeRegexp extends PpiTokenQuoteLike { }
class PpiTokenQuoteLikeReadline extends PpiTokenQuoteLike { }
class PpiTokenRegexpMatch extends PpiTokenRegexp { }
class PpiTokenRegexpSubstitute extends PpiTokenRegexp { }
class PpiTokenRegexpTransliterate extends PpiTokenRegexp { }
class PpiTokenHereDoc extends PpiToken { }
class PpiTokenStructure extends PpiToken { }
class PpiTokenSeparator extends PpiToken { }
class PpiTokenData extends PpiToken { }
class PpiTokenEnd extends PpiToken { }
class PpiTokenPrototype extends PpiToken { }
class PpiTokenAttribute extends PpiToken { }
class PpiTokenUnknown extends PpiToken { }

// Added token not in Perl
class PpiTokenNewline extends PpiTokenWhitespace { }

class PpiToken extends PpiElement
{
    public function anaContext()
    {
//        // Default to parent context
//        $this->setContext($this->parent->context);

        // Default to prior token's context, unless newline
        if ($this->prev->isNewline()) {
            $this->setContext('scalar');
        } else {
            $this->setContext($this->getPrevSiblingNonWs()->context);
        }
    }
}


/**
 * "Magic" token (special variables)
 */

class PpiTokenMagic extends PpiTokenSymbol
{
    function genCode()
    {
        if (! $this->converted) {
            $name = substr($this->content, 1);

            // Check for numbered variable (e.g., $1)
            if (ctype_digit($name)) {
                $this->content = "\$fake/*check:{$this->content}*/";

            } else switch ($this->content) {
                default:
                    $this->content = "\$fake/*check:{$this->content}*/";
                    break;
            }
        }

        return parent::genCode();
    }
}


/**
 * Label
 * Nothing to do except convert some unfortunate names that are reserved
 * words in PHP.
 */
class PpiTokenLabel extends PpiToken
{
    function genCode()
    {
        if (! $this->converted) {
            if ($this->content == 'EXIT:') {
                $this->content = 'EXIT_LABEL:';
            }
        }
        return parent::genCode();
    }
}


class PpiTokenQuote extends PpiToken
{
    public function anaContext()
    {
        $this->setContext('string');
    }
}

class PpiTokenNumber extends PpiToken
{
    public function anaContext()
    {
        $this->setContext('scalar');
    }

    function genCode()
    {
        if (! $this->converted) {
            // If we have the case of "1;" that needs to be at the end
            // of perl modules, remove it.

            if ($this->parent instanceof PpiStatement
                    && $this->parent->parent instanceof PpiDocument
                    && $this->prevSibling === null
                    && $this->next->isSemicolon() == ';'
                    && $this->next->next->isNewline()) {
                $this->cancel();
                $this->next->cancel();
                $this->next->next->cancel();
            }
        }
        return parent::genCode();
    }
}


class PpiTokenRegexp extends PpiToken
{
    function genCode()
    {
        if (! $this->converted) {
            $regex = $this->content;

            // Need to escape single quotes in regex expression
            $regex = str_replace("'", "\\'", $regex);
            $this->content = "'$regex'";
        }
        return parent::genCode();
    }
}

class PpiTokenCast extends PpiToken
{
    public function anaContext()
    {
        switch ($this->content) {
        case '@':
            $this->setContextChain('array');
            break;
        case '%':
            $this->setContextChain('hash');
            break;
        case '\\':
            $this->setContextChain('neutral');
            break;
        default:
            print "unknown cast: {$this->content}, line {$this->lineNum}\n";
            exit(0);
        }
    }


    function genCode()
    {
        if (! $this->converted) {
            // Can't really convert casts like "@$abc" unless you know
            // the context, because it might need to be "count($abc)".
            // Better to check these manually.
            // Might do some unambiguous cases here later.
            // Now have context, so we try and do the right thing.

            $next = $this->next;
            if ($this->content == '@' && $next instanceof PpiStructureBlock) {
                $next->startContent = "/* {$next->startContent} */";
                $next->endContent = "/* {$next->endContent} */";
            }

            $this->content = "/*check:{$this->content}*/";
        }
        return parent::genCode();
    }
}

class PpiTokenOperator extends PpiToken
{
    public function anaContext()
    {
        switch ($this->content) {
        case ',':
            // Special logic, might be an array list or a scalar expression
            // separator.
            // See: PpiStructureList for notes.
            //
            // Always scalar, because it's marking off scalars even in lists
            $this->setContext('scalar');
            break;
        case '.':
            $this->setContextChain('string');
            break;

        case '=':
            // lvalue generally determines context
            $this->setContext($this->getPrevSiblingNonWs()->context);
            break;

        default:
            // Default to scalar
            $this->setContextChain('scalar');
        }
    }

    function genCode()
    {
        if (! $this->converted) {
            switch ($this->content) {
            case 'eq':
                $this->content = '===';
                break;
            case 'ne':
                $this->content = '!==';
                break;
            case 'lt':
                $this->content = '<';
                break;
            case 'gt':
                $this->content = '>';
                break;
            case 'not':
                $this->content = '!';
                break;
            case 'and':
                $this->content = '&&';
                break;
            case 'or':
                $this->content = '||';
                break;
            case '->':
                $next = $this->getNextNonWs();
                if (! ($next instanceof PpiTokenWord)) {
                    $this->cancel();
                }
                break;
            case '=~':
                return $this->cvtRegEx();
            case '!~':
                return $this->cvtRegEx(true);
            case 'x':
                return $this->cvtStrRepeat();
            case '-e':
                return $this->cvtFileExists();
            }
        }

        return parent::genCode();
    }

    /**
     * Convert -e (file exists) operator.
     */
    function cvtFileExists()
    {
        $obj = $this->next;
        $expr = trim($obj->getRecursiveContent());
        $this->content = "file_exists($expr)";
        $obj->cancelAll();
    }

    function cvtRegEx($neg = false)
    {
        $left = $this->prevSibling;
        $right = $this->next;
        $right->preWs = '';

        // Get left side variable to match
        $var = trim($left->getRecursiveContent());

        // Get right side regex expression, which might come back with
        // single quotes.
        $regex = $right->getRecursiveContent();

        if (substr($regex, 0, 2) == "'s") {
            // Substitute, need to split into regex and substitute

            // Strip the quotes and the 's' first
            $regex = substr($regex, 2, -1);

            $regex = preg_replace('/\\w*$/', '', $regex);
            $delim = substr($regex, 0, 1);
            if ($delim == '/') {
                $delim = '\\' . $delim;
            }

            // Split into two strings. Have to check for escapes. Note the
            // look-behind assertion for a backslash.
            if (preg_match("/($delim.*(?<!\\\\)$delim)(.*)$delim/", $regex,
                    $matches)) {

                $pattern = trim($matches[1]);
                $replace = trim($matches[2]);
                $leftVar = $var;

                // Check for special case that looks like this:
                // ($x = $y) =~ s/pattern/subst/;
                if (preg_match('/\(\s*(\$\w+)\s*=/', $leftVar, $matches)) {
                    $leftVar = $matches[1];
                }

                $this->content = "$leftVar = preg_replace('$pattern', " .
                    "'$replace', $var)";
                $this->preWs = $left->preWs;
            } else {
                // Can't convert

                return parent::genCode();
            }

        } else {
            // Match

            $regex = trim($regex);
            $var = trim($var);
            $this->preWs = '';
            $this->content = "preg_match($regex, $var)";
            if ($neg) {
                $this->content = "! ({$this->content})";
            }
        }

        $left->cancelAll();
        $right->cancelAll();

        return parent::genCode();
    }

    /**
     * Convert 'x' (string repeat) operator.
     */
    private function cvtStrRepeat()
    {
        $left = $this->prev;
        $right = $this->next;
        $left->preWs = '';
        $right->preWs = '';

        $leftText = $left->getRecursiveContent();
        $rightText = $right->getRecursiveContent();

        $this->content = "str_repeat($leftText, $rightText)";
        $left->cancelAll();
        $right->cancelAll();
        return parent::genCode();
    }

}


class PpiTokenComment extends PpiToken
{
    public function anaContext()
    {
        $this->setContext('neutral');
    }

    function genCode()
    {
        if (! $this->converted) {
            $comment = preg_replace('/^#/', '//', $this->content);
            $this->content = $comment;

            // If block comment, convert style to PHPDOC
            if (preg_match('/^\/\/####/', $comment, $matches)) {
                $this->content = "/**";

                $obj = $this->next;
                $first = true;
                $open = true;
                while ($obj !== null && ($obj instanceof PpiTokenComment
                            || $obj instanceof PpiTokenNewline)) {
                    $s = $obj->content;

                    /* #      # */

                    if ($obj instanceof PpiTokenNewline) {
                        // Pass through newlines

                    } elseif (preg_match('/^#\s*#$/', $s, $matches)) {
                        // Remove blanks if directly following top
                        if (! $first) {
                            $obj->content = " *";
                            $obj->converted = true;
                        } else {
                            $obj->cancel();
                            $obj->next->cancel();       // Newline
                        }

                    /* #   text   # */
                    } elseif (preg_match('/^#(\s+)(.*)#\s*$/', $s, $matches)) {
                        $first = false;
                        $obj->content = " *{$matches[1]}" . rtrim($matches[2]);
                        $obj->converted = true;

                        // Remove "SubName - ", which was old comment convention
                        $obj->content = preg_replace('/^(\s+\*\s+)\w+ - /',
                            '\1', $obj->content);

                    /* ####### */
                    } elseif (preg_match('/^#+\s*$/', $s, $matches)) {
                        $first = false;
                        $obj->content = " */";
                        $obj->converted = true;

                        // If previous was blank, delete it
                        $last = $obj->prev->prev;
                        if (preg_match('/^\s*\*\s*$/', $last->content)) {
                            $last->cancel();
                            $last->next->cancel();      // Newline
                        }
                        $open = false;
                        break;
                    }

                    $obj = $obj->next;
                }

                // If was unclosed, go ahead and close it.
                if ($open) {
                    $obj->content .= "\n */\n";
                }
            }
        }

        return parent::genCode();
    }
}



/**
 * string like "qw(abc def)";
 */
class PpiTokenQuoteLikeWords extends PpiTokenQuoteLike
{
    public function anaContext()
    {
        $this->setContextChain('string');
    }

    function genCode()
    {
        if (! $this->converted) {
            if (preg_match('/qw\s*\((.*)\)/', $this->content, $matches)) {
                $list = explode(' ', $matches[1]);
                $this->content = '[ ' . implode(', ', array_map(function ($s) {
                    return "'$s'";
                }, $list)) . ' ]';
            }
        }

        return parent::genCode();
    }
}

/**
 * Usually a variable name
 */
class PpiTokenSymbol extends PpiToken
{
    public function anaContext()
    {
        switch (substr($this->content, 0, 1)) {
        default:
        case '$':
            // Scalar variable, may or may not be scalar expression
            $this->setContextChain('scalar');
            break;

        case '@':
            $this->setContextChain('array');
            break;

        case '%':
            $this->setContextChain('hash');
            break;
        }
    }

    function genCode()
    {
        if (! $this->converted) {
            $varName = $this->content;

            switch (substr($varName, 0, 1)) {
            case '$':
                // Normal variable
                break;

            case '@':
                if ($varName == '@ISA') {
                    // Special case of @ISA and just comment out

                    $varName = '//@ISA';
                } else {
                    // Array, change to normal variable

                    $varName = '$' . substr($varName, 1);
                }
                break;

            case '%':
                // Hash, change to normal variable
                $varName = '$' . substr($varName, 1);
                break;

            case '&':
                // Function, just strip off
                $varName = substr($varName, 1);
                break;

            default:
                // Other is most likely function
                break;
            }

            $path = '';
            if (strpos($varName, '::') !== false) {
                // Word has a path, convert it

                $save = '';
                if (substr($varName, 0, 1) == '$') {
                    $varName = substr($varName, 1);
                    $save = '$';
                }

                if (preg_match('/(.*)::(.*)/', $varName, $matches)) {
                    $path = '\\' . $this->cvtPackageName($matches[1]) . '::';
                    $varName = $save . $matches[2];
                }
            }

            // Convert variable names to camel case
            if (substr($varName, 0, 1) == '$') {
                $this->content = $path . '$' .
                    $this->cvtCamelCase(substr($varName, 1));
            } else {
                $this->content = $path . $this->cvtCamelCase($varName);
            }

            // Translate special object reference name
            if ($this->content == '$self') {
                $this->content = '$this';
            }
        }

        return parent::genCode();
    }

}




/**
 * Process general token word, this is where a lot of the action takes
 * place.
 */
class PpiTokenWord extends PpiToken
{
    public function anaContext()
    {
        switch ($this->content) {
        case 'shift':
        case 'unshift':
        case 'pop':
        case 'push':
        case 'split':
        case 'delete':
        case 'keys':
            $this->setContextChain('array');
            break;

        case 'join':
        case 'uc':
        case 'lc':
        case 'ucwords':
            $this->setContextChain('string');
            break;

        case 'defined':
        case 'length':
            $this->setContextChain('scalar');
            break;

        case 'my':
            // If next token is parenthesis, then use array context
            $node = $this->getNextNonWs();
            if ($node->startContent == '(') {
                $this->setContext('array');
            } else {
                $this->setContext('scalar');
            }
            break;

        case 'return':
            // Check for the "return (1, 2)" case.
            // Scalar: return (1)
            // Array:  return (1, 2)
            $context = 'scalar';
            $obj = $this->next;
            if ($obj->startContent == '(') {
                foreach ($obj->next->children as $child) {
                    if ($child->content == ',') {
                        $context = 'array';
                        break;
                    }
                }
            }
            $this->setContext($context);
            break;

        default:
            // Some sort of function, most likely
            $this->setContext('scalar');
            break;
        }
    }

    function genCode()
    {
        if (! $this->converted) {
            $word = $this->content;

            if (strpos($word, '::') !== false) {
                return $this->tokenWordPackageName();
            }

            if ($this->parent instanceof PpiStatementExpression) {

                // Check for bareword hash index. Sibling should be null,
                // which means word is by itself.
                if ($this->parent->parent instanceof PpiStructureSubscript
                        && $this->nextSibling === null) {
                    return $this->quoteBareWord();
                }

                // Check for a bareword key: abc => 'def'
                if ($this->next->content == '=>') {
                    return $this->quoteBareWord();
                }
            }

            if (! $this->isReservedWord($word)) {
                // Looks like some sort of function name or variable

                $this->content = $this->cvtCamelCase($word);
                return parent::genCode();
            }

            if ($this->parent instanceof PpiStatement) {
                switch($word) {
                case 'my':          $this->tokenWordMy();           break;
                case 'split':       $this->tokenWordSplit();        break;
                case 'shift':
                    $this->convertWordWithArg('array_shift');
                    break;
                case 'pop':
                    $this->convertWordWithArg('array_pop');
                    break;
                case 'uc':
                    $this->convertWordWithArg('strtoupper');
                    break;
                case 'lc':
                    $this->convertWordWithArg('strtolower');
                    break;
                case 'delete':
                    $this->convertWordWithArg('unset');
                    break;
                case 'keys':
                    $this->convertWordWithArg('array_keys');
                    break;
                case 'close':
                    $this->convertWordWithArg('close');
                    break;
                case 'unshift':
                    $this->content = 'array_unshift';
                    break;
                case 'push':
                    $this->content = 'array_push';
                    break;
                case 'length':
                    $this->content = 'strlen';
                    break;
                case 'defined':
                    $this->convertWordWithArg('/*check*/isset');
                    break;
                case 'sub':         $this->tokenWordSub();          break;
                case 'package':     $this->tokenWordPackage();      break;
                case 'require':     $this->tokenWordUse();          break;
                case 'use':         $this->tokenWordUse();          break;
                case 'elsif':       $this->content = 'elseif';      break;
                case 'foreach':     $this->tokenWordForeach();      break;
                case 'if':
                    $this->tokenWordConditionals();
                    break;
                case 'unless':
                    $this->tokenWordConditionals();
                    break;
                case 'STDERR':
                case 'local':
                    $this->killContentAndWs();
                    break;
                case 'goto':
                    $this->tokenWordGoto(true, 'if');
                    break;
                }
            } else {
                print "Not statement: {$this->content}\n";
            }
        }

        return parent::genCode();
    }

    private function quoteBareWord()
    {
        $word = $this->content;

        // Put quotes around bareweord
        $c = substr($word, 0, 1);
        if ($c != '"' && $c != "'") {
            $this->content = "'$word'";
        }
        return parent::genCode();
    }


    private function tokenWordSub()
    {
        $parent = $this->parent;
        // Need to look at context

        if ($parent instanceof PpiStatementVariable) {
            // Anonymous function

            $this->content = 'function ()';
        } elseif ($parent instanceof PpiStatementSub) {
            // Function context

            // Get the name of the function
            $tokens = $this->peekAhead(2, [ 'skip_ws' => true ]);
            $name = $this->cvtCamelCase($tokens[0]->content);
            $tokens[0]->cancel();
            $argList = [];

            if ($tokens[1] instanceof PpiStructureBlock) {
                // Try to figure out an argument list
                // First check for the easy case of "my ($var, $var2) = @_;"

                $obj = $tokens[1];
                $obj = $obj->next;
                $firstObj = $obj;
                $obj = $obj->next;

                $argList = [];
                $found = false;
                $saveObj = $obj;
                if ($obj instanceof PpiStatementVariable) {
                    $max = 200;
                    while (($obj = $obj->next) !== null && --$max > 0) {
                        if ($obj->content == '@_') {
                            $found = true;
                            // Cancel semicolon and newline
                            $obj = $obj->findNewline();

                            // Skip blank lines
                            while ($obj->next->isNewline()) {
                                $obj = $obj->next;
                            }
                            // Keep last newline
                            $obj = $obj->prev;
                            break;
                        }

                        if ($obj instanceof PpiTokenSymbol) {
                            $argList[] = $var = $obj->genCode();
                            continue;
                        }

                        if ($obj->isSemicolon()) {
                            break;
                        }
                    }
                }

                if (! $found) {
                    $argList = [];

                    // Not found, try the more complicated version. Look
                    // for lines like: "my $var = shift;"
                    $obj = $saveObj;
                    while ($obj instanceof PpiStatementVariable) {
                        $obj1 = $obj->getNextNonWs();
                        $obj2 = $obj1->getNextNonWs();
                        $obj3 = $obj2->getNextNonWs();
                        $obj4 = $obj3->getNextNonWs();
                        $obj5 = $obj4->getNextNonWs();
                        $obj = $obj5->getNextNonWs();
                        if ($obj1->content != 'my' ||
                                ! ($obj2 instanceof PpiTokenSymbol) ||
                                $obj3->content != '=' ||
                                $obj4->content != 'shift' ||
                                ! $obj5->isSemicolon()) {

                            break;
                        }

                        $argList[] = $obj2->genCode();
                    }

                    if (count($argList)) {
                        $obj = $obj5;
                        while (! $obj->isNewline()) {
                            $obj = $obj->next;
                        }
                    }
                }
            }

            // Cancel out the lines with the argument list
            if (count($argList)) {
                $firstObj->cancelUntil($obj);

                // If first argument is '$self', remove it
                if ($argList[0] == '$self' || $argList[0] == '$this') {
                    array_shift($argList);
                }
            }


            $this->content = "function $name(" .
                implode(', ', $argList) . ")";
        } else {
            throw new \Exception("Bad context " . get_class($parent) .
                ", Could not convert $word\n");
        }

        return;
    }

    private function tokenWordPackageName()
    {
        // Convert package name to class name

        $name = $this->content;
        $this->content = $this->cvtPackageName($this->content);

        // If ends in 'new' and this is an assignment, convert to
        // "= new class"
        if (strtolower(substr($this->content, -4, 4)) == '\new' &&
                substr($this->prev->content, -1, 1) == '=') {
            $this->content = "new " . substr($this->content, 0, -4);
        }

        return parent::genCode();
    }

    private function tokenWordPackage()
    {
        // Convert package statement to class statement

        $this->content = 'class';
        $obj = $this->getNextNonWs();
        if (! preg_match('/(.*)::(.*)/', $obj->content, $matches)) {
            $ns = '';
            $className = $obj->content;
        } else {
            $ns = $this->cvtPackageName($matches[1]);
            $className = $matches[2];
        }

        $this->content = '';
        if (! empty($ns)) {
            $this->content .= "namespace $ns;\n\n";
        }
        $this->content .= "class " . ucfirst($this->cvtCamelCase($className)) .
            "\n{";

        // Put a closing brace at end of file
        $this->root->endContent = "}\n";

        // Cancel out until the semicolon
        $obj = $this;
        do {
            $obj = $obj->next;
            $obj->cancel();
        } while (! $obj->isSemicolon());
    }

    private function tokenWordMy()
    {
        // If not within a subroutine, probably within class.
        // Change to 'private'.
        if (! $this->isWithinSub()) {
            $this->content = 'private';
            return;
        }

        // Scan ahead and see if we have an initializer somewhere
        $scan = $this;
        while (! $scan->isSemicolon() && $scan->content != '=') {
            $scan = $scan->next;
        }
        $hasInit = $scan->content == '=';

        // See if we have a list of variables in parenthesis.
        $obj = $this->getNextNonWs();
        if ($obj instanceof PpiStructureList) {
            if ($hasInit) {
                // If an initializer, this is handled elsewhere. Remove
                // the 'my' and the following whitespace

                // Otherwise, kill the 'my' and following whitespace
                $this->killContentAndWs();
                return;
            }

            // No initializer, must be a declaration. Take it apart into
            // separate assignments.
            $obj = $obj->next;
            if ($obj instanceof PpiStatementExpression) {
                $varList = [];
                foreach ($obj->children as $child) {
                    if ($child instanceof PpiTokenSymbol) {
                        $var = $child->genCode();
                        $varList[] = $var;
                    }
                    $child->cancel();
                }

                $indent = str_repeat(' ', $this->getIndent());
                $s = '';
                foreach ($varList as $var) {
                    if ($s !== '') {
                        $s .= "\n$indent";
                    }
                    $s .= "$var = null;";
                }
                $this->content = $s;
                $this->next->cancelUntil($scan);
            }
            return;
        }

        // Otherwise, kill the 'my' and following whitespace
        $this->killContentAndWs();

        // Scan ahead and see if there's an initializer. If so, just kill
        // the 'my' and whitespace. Otherwise add an initializer to the
        // variable.
        $peek = $this->peekAhead(2, [ 'skip_ws' => true]);
        if ($peek[0]->content == '=') {
            // Have initializer, we're done.

            return;
        }

        if ($peek[1]->isSemicolon()) {
            $peek[1]->content = " = null;";
        }
        return;
    }

    private function tokenWordSplit()
    {
        $this->content = 'preg_split';
        $obj = $this->next;
        if ($obj instanceof PpiStructureList) {
            $obj = $obj->next;
        }
        if ($obj instanceof PpiStatementExpression) {
            $obj = $obj->next;
        }

        // Test if we can use faster explode
        if ($obj instanceof PpiTokenQuote) {
            $pattern = $obj->content;
            if (! preg_match('/[\\().*]/', $pattern)) {
                // Doesn't look like a pattern, use explode

                $this->content = 'explode';
            }
        }
    }

    /**
     * Check for things like "= pop @x"
     */
    private function convertWordWithArg($newWord)
    {
        // Check for case like "$a = func(shift);". Just mark it.
        if ($this->nextSibling === null) {
            $this->content = "\$fake/*check:{$this->content}*/";
            return;
        }

        $this->content = $newWord;
        $obj = $this->next;
        // Check for parentheses
        if (! ($obj instanceof PpiStructureList)) {
            $obj->preWs = '';
            $code = $obj->getRecursiveContent();
            $obj->cancelAll();

            // See if we have a subscript following a word
            if ($obj instanceof PpiTokenSymbol) {
                for(;;) {
                    $obj = $obj->nextSibling;
                    if ($obj === null ||
                            ! $obj instanceof PpiStructureSubscript) {
                        break;
                    }

                    $code .= $obj->getRecursiveContent();
                    $obj->cancelAll();
                }
            }

            $this->content = "$newWord($code)";
        }

        return;
    }

    /**
     * Process conditional statements, such as 'if', 'until', etc
     * Particularly deals with things like "$a = 10 if $b == 20";
     */
    private function tokenWordConditionals()
    {
        $neg = $this->content == 'unless';
        $needSwitch = false;

        // Scan ahead to see if we need a reverse of "expr if (cond);"
        $obj = $this;
        while (($obj = $obj->prev) !== null) {
            if ($obj->isWs()) {
                continue;
            }

            if ($obj instanceof PpiStructureBlock) {
                break;
            }

            if ($obj->isSemicolon()) {
                break;
            }

            if (! empty($obj->content)) {
                // Some sort of code before

                $needSwitch = true;
                break;
            }
        }

        if (! $needSwitch) {
            // No code before the 'if' statement. If 'unless', then we need
            // to reverse the conditional.
            if ($this->content == 'unless') {
                $condition = trim($this->next->getRecursiveContent());
                $this->next->cancelAll();
                $this->content = "if (! $condition)";
            }

            return;
        }

        // parent should be a statement object
        if (! ($this->parent instanceof PpiStatement)) {
            return;
        }

        $indentAmt = $this->getIndent();

        $leftText = '';
        $rightText = '';
        $foundObj = false;
        $parent = $this->parent;
        foreach ($parent->children as $obj) {
            if ($obj === $this) {
                $foundObj = true;
                continue;
            }

            if (! $foundObj) {
                $leftText .= $obj->getRecursiveContent();
            } else {
                $rightText .= $obj->getRecursiveContent();
            }
        }
        $parent->cancelAll();
        $this->uncancel();

        // Copy any spaces at front of expression to new expression
        $this->preWs = $parent->getNextToken()->preWs;

        $rightText = trim(preg_replace('/;\s*$/', '', $rightText));
        if (substr($rightText, 0, 1) != '(') {
            $rightText = "($rightText)";
        }
        if ($neg) {
            $rightText = "(! $rightText)";
        }
        $leftText = trim($leftText);

        $rightText = str_replace("\n", ' ', $rightText);
        $leftText = str_replace("\n", ' ', $leftText);
        $rightText = preg_replace('/\s+/', ' ', $rightText);
        $leftText = preg_replace('/\s+/', ' ', $leftText);

        $indent = str_repeat(' ', $indentAmt);
        $this->content = "if $rightText {\n" .
            "$indent    $leftText;\n" .
            "$indent}";
        return;
    }

    /**
     * Process foreach statement, which requires syntax mod.
     */
    private function tokenWordForeach()
    {
        $needSwitch = false;
        $obj = $this;

        // First find the variable
        $var = null;
        while (($obj = $obj->next) !== null) {
            if ($obj instanceof PpiTokenSymbol) {
                $var = $obj->genCode();
                break;
            }

            if ($obj instanceof PpiStructureList ||
                    $obj instanceof PpiStructureBlock || $obj->isSemicolon()) {
                break;              // Something went wrong
            }
        }

        if ($var === null) {
            return;
        }

        // Look for the expression in parenthesis
        $exprObj = null;
        while (($obj = $obj->next) !== null) {
            if ($obj instanceof PpiStructureList) {
                $exprObj = $obj;
                break;
            }

            if ($obj instanceof PpiStructureBlock || $obj->isSemicolon()) {
                break;
            }
        }

        if ($exprObj === null) {
            return;
        }

        // Accumulate text of children, which is the expression
        foreach ($exprObj->children as $obj) {
            $obj->genCode();
        }

        $exprText = '';
        foreach ($exprObj->children as $obj) {
            $exprText .= $obj->getRecursiveContent();
        }
        $exprText = trim($exprText);

        $this->next->cancelUntil($exprObj->getLastLeaf());

        $this->content = "foreach ($exprText as $var)";
        return;
    }

    /**
     * Use / Require
     */
    private function tokenWordUse()
    {
        $this->content = 'use';

        // The next word is the argument and then just comment out
        // anything that follows.
        $obj = $this->next->next;
        if (! $obj->isSemicolon()) {
            $newObj = $obj->insertLeftText('/*');
            $newObj->preWs = $obj->preWs;
            $obj->preWs = '';

            while (! $obj->isSemicolon()) {
                // Set to just leave contents alone
                $obj->converted = true;
                $obj = $obj->next;
            }
            $obj->insertLeftText('*/');
        }

        return;
    }

    /**
     * goto
     * Don't need to do much to this, except there are some unfortunate
     * cases where goto labels are reserved words in PHP.
     */
    private function tokenWordGoto()
    {
        $label = $this->next;
        if ($label->content = 'EXIT') {
            $label->content = 'EXIT_LABEL';
        }
    }

}

/**
 * Things like "qq|  stuff |;";
 */
class PpiTokenQuoteInterpolate extends PpiTokenQuote
{
    function genCode()
    {
        if (! $this->converted) {
            // Convert these to "<<<EOT" style
            // They may have literal \n and \t, which we convert here.

            $content = "<<<EOT\n" .
                substr($this->content, 3, -1) .
                "\nEOT";
            $content = str_replace('\n', "\n", $content);
            $content = str_replace('\t', "\t", $content);
            $this->content = $content;
            return parent::genCode();
        }
    }


}
