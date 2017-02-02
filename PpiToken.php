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
class PpiTokenQuoteSingle extends PpiTokenQuote { }
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
        // Default to prior token's context, unless newline
        if ($this->prev->isNewline()) {
            $this->setContext('scalar');
        } else {
            $this->setContext($this->getPrevSiblingUpTree()->context);
        }
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
        case '&':
        case '$':
            // Scalar variable, may or may not be scalar expression
            $this->setContextChain('scalar');
            break;

        case '@':
            // Hard to create a general rule for array or scalar here
            if ($this->prevSibling !== null &&
                    $this->prevSibling->content == 'my') { 
                // my @var

                $this->setContextChain('array');

            } elseif ($this->parent instanceof PpiStatementExpression) {
                // Use context of parent. Not sure if this is reliable.

                $this->setContextChain($this->parent->context);
            } elseif ($this->prevSibling !== null &&
                    $this->prevSibling->content == '=') { 

                // An '=' sign implies scalar
                $this->setContextChain('scalar');

            } else {
                $this->setContextChain('array');
            }
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
                if ($varName == '@ISA' || $varName == '@EXPORT') {
                    // Special case, just comment out

                    $varName = "//{$varName}";
                } else {
                    // Array, change to normal variable if array context
                    // otherwise convert to count.

                    if ($this->context == 'scalar') {
                        $varName = 'count($' .
                            $this->cvtCamelCase(substr($varName, 1)) . ')';
                    } else {
                        $varName = '$' . substr($varName, 1);
                    }
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

            // Translate special object reference name, unless it's
            // an assignment
            if ($this->content == '$self' && $this->next->content != '=') {
                $this->content = '$this';
            }
        }

        return parent::genCode();
    }

}


class PpiTokenQuoteDouble extends PpiTokenQuote
{
    function genCode()
    {
        if (! $this->converted) {
            // If not within a subroutine, probably within class. Check if there
            // are replacements in string, which we can't do in an initializer.
            if (! $this->isWithinSub()) {
                $this->content = str_replace('$', '$/*check*/', $this->content);
            }
        }

        return parent::genCode();
    }

}

/**
 * Special count syntax like "$#var"
 */
class PpiTokenArrayIndex extends PpiTokenSymbol
{
    function genCode()
    {
        if (! $this->converted) {
            if (substr($this->content, 0, 2) != '$#') {
                print "Unknown array index syntax: {$this->content}\n";
                exit(1);
            }

            $var = '$' . substr($this->content, 2);
            $this->content = "(count($var)-1)";
        }
        return parent::genCode();
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
            // Might be scalar or array
            $this->setContext($this->prev->context);
            break;
        case '%':
            $this->setContextChain('hash');
            break;
        case '\\':
            $this->setContextChain('neutral');
            break;
        case '$#':
            $this->setContextChain('scalar');
            break;
        case '&':
            $this->setContextChain('scalar');
            break;
        default:
            // Default to scalar
            $this->setContextChain('scalar');
            break;
        }
    }


    function genCode()
    {
        if (! $this->converted) {
            switch ($this->content) {
            case '$#':          // count() - 1
            case '@':
                $needMinus = $this->content == '$#';

                // Need to check context to see if this is a cast-to-array or
                // it's a count of an array.
                $next = $this->next;
                if ($this->context == 'scalar') {
                    // Convert to count expression

                    $text = '';
                    if ($next instanceof PpiStructureBlock) {
                        // Something like @{expression}

                        $text = $this->next->getRecursiveContent();
                        $this->next->cancelAll();
                        $this->content = "count(" . substr($text, 1, -1) . ")";
                        $this->converted = true;

                    } elseif ($next instanceof PpiTokenSymbol) {
                        // Something like @$a

                        $this->content = "count({$next->genCode()})";
                        $next->cancel();
                    } else {
                        print "Unknown cast following type: " .
                            get_class($next) . "\n";
                        exit(1);
                    }

                    if ($needMinus) {
                        $this->content = "({$this->genCode()}-1)";
                    }

                } else {
                    // Array context, just remove the cast

                    $this->content = '';
                    if ($next instanceof PpiStructureBlock) {
                        // Change to parentheses, unless it's just a single
                        // word in the interior.

                        $text = $next->getRecursiveContent();
                        $next->converted = true;
                        if (preg_match('/\{\$\w+\}/', $text)) {
                            $next->cancel();
                        } else {
                            $next->startContent = '(';
                            $next->endContent = ')';
                            $next->converted = true;
                        }
                    }
                }
                break;

            case '%':
                // Comment out hash cast and remove braces if any
                $this->content = "/*check:{$this->content}*/";
                if ($this->next->startContent == '{') {
                    $this->next->startContent = '';
                    $this->next->endContent = '';
                }

                break;

            case '&':
                // Function cast, just comment out.
                $this->content = '/*check:&*/';

                // Change braces to parentheses
                if ($this->next instanceof PpiStructureBlock) {
                    $this->next->startContent = '';
                    $this->next->endContent = '';
                    $this->next->converted = true;
                }
                break;

            default:
                $this->content = "/*check:{$this->content}*/";
                break;
            }
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
            $this->setContext($this->getPrevSiblingUpTree()->context);
            break;

        case '..':
            $this->setContext('array');
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
            case '..':
                return $this->cvtRange();
            }
        }

        return parent::genCode();
    }

    /**
     * Convert -e (file exists) operator.
     */
    function cvtFileExists()
    {
        list($expr, $right) = $this->getRightArg();
        $this->content = "file_exists($expr)";
    }

    function cvtRegEx($neg = false)
    {
        $left = $this->prevSibling;
        $right = $this->next;
        $right->preWs = '';

        list($var, $left) = $this->getLeftArg();

        // Get right side regex expression, which might come back with
        // single quotes.
        list($regex, $last) = $this->getRightArg();

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
                $replace = $matches[2];
                $leftVar = $var = trim($var);

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
            $this->preWs = $left->preWs;
            $this->content = "preg_match($regex, $var)";
            if ($neg) {
                $this->content = "! ({$this->content})";
            }
        }

        return parent::genCode();
    }

    /**
     * Range operatior '..'
     */
    function cvtRange()
    {
        list($leftText, $left) = $this->getLeftArg();
        list($rightText, $right) = $this->getRightArg();
        $this->content = "range($leftText, $rightText)";
        return parent::genCode();
    }

    /**
     * Convert 'x' (string repeat) operator.
     */
    private function cvtStrRepeat()
    {
        list($leftText, $left) = $this->getLeftArg();
        list($rightText, $right) = $this->getRightArg();

        $this->content = "str_repeat($leftText, $rightText)";
        $this->preWs = $left->preWs;
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
        $this->setContextChain('array');
    }

    function genCode()
    {
        if (! $this->converted) {
            $d = trim(substr($this->content, 3, -1));
            $list = preg_split('/\s+/', $d);
            $this->content = '[ ' . implode(', ', array_map(function ($s) {
                return (is_numeric($s)) ? $s : "'$s'";
            }, $list)) . ' ]';
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
        case 'sort':
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
            // If next token is parenthesis or start of array, then use
            // array context
            $node = $this->getNextNonWs();
            if ($node->startContent == '(' ||
                            substr($node->content, 0, 1) == '@') {
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
                        && $this->prevSibling === null
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
                    $this->tokenWordWithArg('array_shift(%s)');
                    break;
                case 'pop':
                    $this->tokenWordWithArg('array_pop(%s)');
                    break;
                case 'uc':
                    $this->tokenWordWithArg('strtoupper(%s)');
                    break;
                case 'lc':
                    $this->tokenWordWithArg('strtolower(%s)');
                    break;
                case 'delete':
                    $this->tokenWordWithArg('unset(%s)');
                    break;
                case 'keys':
                    $this->tokenWordWithArg('array_keys(%s)');
                    break;
                case 'close':
                    $this->tokenWordWithArg('close(%s)');
                    break;
                case 'sort':
                    $this->tokenWordWithArg('$fake/*check:sort(%s)*/');
                    break;
                case 'bless':
                    $this->tokenWordCommentOut();
                    break;
                case 'grep':
                    $this->tokenWordGrep();
                    break;
                case 'unshift':
                    $this->content = 'array_unshift';
                    break;
                case 'push':
                    $this->content = 'array_push';
                    break;
                case 'length':
                    $this->tokenWordWithArg('strlen(%s)');
                    break;
                case 'int':
                    $this->tokenWordWithArg('floor(%s)');
                    break;
                case 'defined':
                    $this->tokenWordWithArg('/*check*/isset(%s)');
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
                    $this->tokenWordGoto();
                    break;
                case 'last':
                    $this->tokenWordLast();
                    break;
                case 'next':
                    $this->tokenWordNext();
                    break;
                case 'chop':
                    $this->tokenLValueWithArg('/*check:chop*/substr(%s, 0, -1)');
                    break;
                case 'chomp':
                    $this->tokenLValueWithArg("/*check:chomp*/preg_replace('/\\n$/', '', %s)");
                    break;
                case 'eval':
                    $this->tokenWordEval();
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

            $argList = [];
            $obj = $this->getNextNonWs();

            // Get the name of the function
            $name = $this->cvtCamelCase($obj->content);
            if ($this->isPhpReservedWord($name)) {
                $name = 'x' . ucfirst($name);
            }

            $obj->cancel();
            $obj = $obj->getNextNonWs();

            // If parentheses follow the function (prototype), just kill it
            if ($obj instanceof PpiTokenPrototype) {
                $obj->cancelAll();
                $obj = $obj->getNextNonWs();
            }

            if ($obj instanceof PpiStructureBlock) {
                // Try to figure out an argument list
                // First check for the easy case of "my ($var, $var2) = @_;"

                $obj = $obj->getNextNonWs();
                $firstObj = $obj;

                $argList = [];
                $found = false;
                $saveObj = $obj;
                if ($obj instanceof PpiStatementVariable) {
                    $max = 200;
                    $child = $obj->children[0];
                    $lastChild = end($obj->children);
                    while (! $child->isSemicolon() && --$max > 0) {
                        if ($child->content == '@_') {
                            $found = true;

                            // Cancel object and all children
                            $obj->cancelAll();

                            // Suck up extra newlines, but keep one
                            $obj = $obj->nextSibling;
                            if ($obj->isNewline()) {
                                while ($obj->next->isNewline()) {
                                    $obj->cancel();
                                    $obj = $obj->next;
                                }
                            }
                            break;
                        }

                        if ($child instanceof PpiTokenSymbol) {
                            $argList[] = $child->genCode();
                        }

                        $child = $child->next;
                        if ($child === $lastChild) {
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
                        $list = $obj->peekAhead(6, [ 'skip_ws' => true ]);
                        if ($list[0]->content != 'my'
                                || ! $list[1] instanceof PpiTokenSymbol
                                || $list[2]->content != '='
                                || $list[3]->content != 'shift'
                                || ! $list[4]->isSemicolon()) {

                            break;
                        }

                        $argList[] = $list[1]->genCode();
                        $obj = $list[5];
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

    /**
     * Comment out whole thing
     */
    private function tokenWordCommentOut()
    {
        $this->content = "check:{$this->content}";
        $obj = $this;

        // The next word is the argument and then just comment out
        // anything that follows.
        $newObj = $obj->insertLeftText('/*');
        $newObj->preWs = $obj->preWs;
        $obj->preWs = '';

        while (! $obj->isSemicolon()) {
            // Set to just leave contents alone
            $obj->converted = true;
            $obj = $obj->next;
        }
        $obj->insertLeftText('*/');
        return;
    }


    /**
     * 'eval' keyword
     */
    private function tokenWordEval()
    {
        // eval { code };
        // Just remove the eval and braces.

        $this->content = "/*check:eval*/";
        $obj = $this->next;
        if ($obj->startContent == '{') {
            $obj->startContent = '';
            $obj->endContent = '';
            $obj->preWs = '';
            $obj->endPreWs = '';
        }

        return parent::genCode();
    }

    private function tokenWordPackageName()
    {
        // Convert package name to class name

        $name = $this->content;

        // If ends in 'new' and this is an assignment, convert to
        // "= new class"
        $new = '';
        if (strtolower(substr($name, -5, 5)) == '::new' &&
                substr($this->prev->content, -1, 1) == '=') {
            $new = "new ";
            $name = substr($name, 0, -5);
        }

        $this->content = $new . $this->cvtPackageName($name);

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
                        $type = $child->context;
                        $var = $child->genCode();
                        $varList[] = (object)['name' => $var, 'type' => $type];
                    }
                    $child->cancel();
                }

                $indent = str_repeat(' ', $this->getIndent());
                $s = '';
                foreach ($varList as $var) {
                    if ($s !== '') {
                        $s .= "\n$indent";
                    }
                    $s .= "{$var->name} = " .
                        ($var->type == 'array' ? '[]' : 'null') . ";";
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
            $peek[1]->content = " = " .
                ($this->context == 'array' ? '[]' : 'null') . ";";
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
     * Check for things like "pop @x"
     */
    private function tokenWordWithArg($pattern)
    {
        // Check for case like "$a = func(shift);". Just mark it.
        // Or case like = shift;
        if ($this->nextSibling === null || $this->next->isSemicolon()) {
            $this->content = "\$fake/*check:{$this->content}*/";
            return;
        }

        // If has parentheses, just get interior content
        if ($this->next instanceof PpiStructureList) {
            $code = $this->next->getRecursiveContent();
            $this->next->cancelAll();
        } else {
            list($code, $right) = $this->getRightArg();
        }

        // Strip possible parentheses
        if (substr($code, 0, 1) == '(') {
            $code = substr($code, 1, -1);
        }

        $this->content = sprintf($pattern, trim($code));
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

        // Check in front to see if we need a reverse of "expr if (cond);"
        $obj = $this->getPrevSiblingNonWs();
        if ($obj === null) {
            // No code before the if/unless statement. If 'unless', then we
            // need to reverse the conditional.
            if ($this->content == 'unless') {
                // The condition must be in parenthesis, which helps things.
                if (! ($this->next instanceof PpiStructureCondition)) {
                    print "Missing PpiStructureCondition:\n" .
                        "{$this->next->fmtObj()}\n";
                    exit(1);
                }

                $this->content = 'if';
                $this->next->startContent = "(! (";
                $this->next->endContent = "))";
            }

            return;
        }

        // parent should be a statement object
        if (! ($this->parent instanceof PpiStatement)) {
            return;
        }

        $indentAmt = $this->getIndent();

        list($leftText, $left) = $this->getLeftArg();
        list($rightText, $right) = $this->getRightArg();

        // Copy any spaces at front of expression to new expression
        $this->preWs = $left->preWs;

        $rightText = trim(preg_replace('/;\s*$/', '', $rightText));

        // If expression not surrounded by parentheses, then add them.
        // Note we need to check if it's the entire expression for cases like:
        //     $a = $b if ($c) = func();
        // expression, then rremove the parentheses.
        if (substr($rightText, 0, 1) != '(' || $right !== $this->nextSibling) {
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

        // If token after right expression is a semicolon, kill it.
        $obj = $right->getNextSiblingUpTree();
        if ($obj->isSemicolon()) {
            $obj->cancel();
        }

        return;
    }

    /**
     * Convert functions like 'chop' which take an l-value
     */
    private function tokenLValueWithArg(
        $format)                    // sprintf-format
    {
        list($code, $right) = $this->getRightArg();

        // Remove parenthesis, if any.
        if (substr($code, 0, 1) == '(') {
            $code = substr($code, 1, -1);
        }
        $code = trim($code);
        $var = $code;

        // See if it's something like "chop($a = $b)"
        if (preg_match('/(\$\w+)\s*=/', $var, $matches)) {
            $var = $matches[1];
        }

        $format = "%s = $format";
        $this->content = sprintf($format, $var, $code);
        return;
    }

    /**
     * Process foreach statement, which requires syntax mod.
     */
    private function tokenWordForeach()
    {
        // Skip possible 'my' of the variable
        $obj = $this->next;
        if ($obj->content == 'my') {
            $obj->cancel();
            $obj = $obj->getNextNonWs();
        }

        // Next token should be the variable
        if (! ($obj instanceof PpiTokenSymbol)) {
            print "Foreach invalid variable token: " . get_class($obj) .
                ", content: '{$obj->content}'\n";
            exit(1);
        }
        $var = $obj->genCode();
        $obj->cancel();

        // Next is expression in parenthesis
        $obj = $obj->getNextNonWs();
        if (! ($obj instanceof PpiStructureList)) {
            print "Foreach invalid expression token: " . get_class($obj) .
                "content: {$obj->content}\n";
            exit(1);
        }
        $expr = trim($obj->getRecursiveContent());

        // Check if interior is list of scalars or an array, such as:
        // Scalars: [1, 2, 3]
        // Scalars: [[1,2], [3,4]]
        // Array: [@b]

        $first = $obj->children[0]->getNextNonWs();
        if ($first->context == 'scalar' || $first->startContent == '[') {
            // Keep the outer brackets.
            // Exception: if first argument is a function, strip the brackets
            // and add a '/*check*/'. Ex: foreach $a (func(1)):

            if (preg_match('/^\[\s*\w+\(/', $expr)) {
                $expr = $this->stripParensOrBrackets($expr);
                $expr = "/*check*/$expr";
            }

        } else {
            // Remove brackets or parentheses, if any

            $expr = $this->stripParensOrBrackets($expr);
        }

        $obj->cancelAll();
        $this->content = "foreach ($expr as $var)";
        return;
    }

    /**
     * Use / Require
     */
    private function tokenWordUse()
    {
        // If used within a function, not allowed in PHP. Comment out
        // whole thing.
        if ($this->isWithinSub()) {
            $this->content = "check:{$this->content}";
            $obj = $this;
        } else {
            // If require, convert to 'use'
            $this->content = 'use';
            $obj = $this->next->next;
        }

        // The next word is the argument and then just comment out
        // anything that follows.
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
        if ($label->content == 'EXIT') {
            $label->content = 'EXIT_LABEL';
        }
    }

    /**
     * 'last'
     */
    private function tokenWordLast()
    {
        $this->content = 'break';
        $label = $this->next;
        if ($label instanceof PpiTokenWord) {
            // If it's of form 'last if (condition)', let it convert later.
            if (! in_array($label->content, [ 'if', 'unless' ])) {
                $label->content = "/*check:{$label->content}*/";
            }
        }
    }

    /**
     * 'next'
     */
    private function tokenWordNext()
    {
        $this->content = 'continue';
        $label = $this->next;
        if ($label instanceof PpiTokenWord) {
            // If it's of form 'next if (condition)', let it convert later.
            if (! in_array($label->content, [ 'if', 'unless' ])) {
                $label->content = "/*check:{$label->content}*/";
            }
        }
    }

    /**
     * 'grep'
     */
    private function tokenWordGrep()
    {
        $obj = $this->getNextNonWs();
        if ($obj instanceof PpiStructureBlock) {
            $block = trim($obj->getRecursiveContent());
            $obj->cancelAll();

            // Make sure there's a semicolon at end of function text.
            if (! preg_match('/;\s*\}$/', $block)) {
                $block = preg_replace('/(\s*\}$)/', ';\1', $block);
            }

            list($var, $right) = $obj->getRightArg();
            $var = $this->stripParensOrBrackets($var);
            $this->content = "array_filter($var, function (\$fake) $block)";
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
