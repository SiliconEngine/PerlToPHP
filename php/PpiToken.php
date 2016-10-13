<?php

class PpiToken extends PpiElement { }
class PpiTokenWhitespace extends PpiToken { }
class PpiTokenPod extends PpiToken { }
class PpiTokenNumber extends PpiToken { }
class PpiTokenNumberBinary extends PpiTokenNumber { }
class PpiTokenNumberOctal extends PpiTokenNumber { }
class PpiTokenNumberHex extends PpiTokenNumber { }
class PpiTokenNumberFloat extends PpiTokenNumber { }
class PpiTokenNumberExp extends PpiTokenNumberFloat { }
class PpiTokenNumberVersion extends PpiTokenNumber { }
class PpiTokenDashedWord extends PpiToken { }
class PpiTokenMagic extends PpiTokenSymbol { }
class PpiTokenArrayIndex extends PpiToken { }
class PpiTokenQuote extends PpiToken { }
class PpiTokenQuoteSingle extends PpiTokenQuote { }
class PpiTokenQuoteDouble extends PpiTokenQuote { }
class PpiTokenQuoteLiteral extends PpiTokenQuote { }
class PpiTokenQuoteInterpolate extends PpiTokenQuote { }
class PpiTokenQuoteLike extends PpiToken { }
class PpiTokenQuoteLikeBacktick extends PpiTokenQuoteLike { }
class PpiTokenQuoteLikeCommand extends PpiTokenQuoteLike { }
class PpiTokenQuoteLikeRegexp extends PpiTokenQuoteLike { }
class PpiTokenQuoteLikeReadline extends PpiTokenQuoteLike { }
class PpiTokenRegexp extends PpiToken { }
class PpiTokenRegexpMatch extends PpiTokenRegexp { }
class PpiTokenRegexpSubstitute extends PpiTokenRegexp { }
class PpiTokenRegexpTransliterate extends PpiTokenRegexp { }
class PpiTokenHereDoc extends PpiToken { }
class PpiTokenCast extends PpiToken { }
class PpiTokenStructure extends PpiToken { }
class PpiTokenLabel extends PpiToken { }
class PpiTokenSeparator extends PpiToken { }
class PpiTokenData extends PpiToken { }
class PpiTokenEnd extends PpiToken { }
class PpiTokenPrototype extends PpiToken { }
class PpiTokenAttribute extends PpiToken { }
class PpiTokenUnknown extends PpiToken { }


class PpiTokenOperator extends PpiToken
{
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
            case '->':
                $next = $this->getNextNonWs();
                if (! ($next instanceof PpiTokenWord)) {
                    $this->content = '';
                }
                break;
            }
        }

        return parent::genCode();
    }
}




class PpiTokenComment extends PpiToken
{
    function genCode()
    {
        if (! $this->converted) {
            $this->content = preg_replace('/^#/', '//', $this->content);
        }

        return parent::genCode();
    }
}



/**
 * string like "qw(abc def)";
 */
class PpiTokenQuoteLikeWords extends PpiTokenQuoteLike
{
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

            if (substr($varName, 0, 1) == '$') {
                $this->content = $path . '$' .
                    lcfirst($this->cvtCamelCase(substr($varName, 1)));
            } else {
                $this->content = $path . lcfirst($this->cvtCamelCase($varName));
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
    function genCode()
    {
        if (! $this->converted) {
            $word = $this->content;

            if (strpos($word, '::') !== false) {
                return $this->tokenWordPackageName();
            }

            if ($this->parent instanceof PpiStatementExpression &&
                    $this->parent->parent instanceof PpiStructureSubscript) {
                // Might be a bareword hash index

                return $this->quoteBareWord();
            }

            if ($this->parent instanceof PpiStatementVariable) {
                switch($word) {
                case 'my':          $this->tokenWordMy();           break;
                default:
                    // Possibly a function name

                    if (! $this->isReservedWord($word)) {
                        $this->content = lcfirst($this->cvtCamelCase($word));
                    }
                }
            }

            if ($this->parent instanceof PpiStatementSub) {
                switch($word) {
                case 'sub':         $this->tokenWordSub();          break;
                }
            }

            if ($this->parent instanceof PpiStatementPackage) {
                switch($word) {
                case 'package':     $this->tokenWordPackage();      break;
                }
            }

            if ($this->parent instanceof PpiStatementInclude) {
                switch($word) {
                case 'require':     $this->content = 'use';         break;
                }
            }

            if ($this->parent instanceof PpiStatementCompound) {
                switch($word) {
                case 'elsif':     $this->content = 'elseif';         break;
                }
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
            $tokens = $this->peekAhead(4);
            $name = lcfirst($this->cvtCamelCase($tokens[1]->content));
            $tokens[0]->cancel();
            $tokens[1]->cancel();

            if ($tokens[3] instanceof PpiStructureBlock) {
                // Try to figure out an argument list
                // First check for the easy case of "my ($var, $var2) = @_;"

                $obj = $tokens[3];
                $obj = $obj->next;
                $firstObj = $obj;
                $obj = $obj->next->SkipWhitespace();

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
                            break;
                        }

                        if ($obj instanceof PpiTokenSymbol) {
                            $argList[] = $var = $obj->genCode();
                            continue;
                        }

                        if ($obj->content == ';') {
                            break;
                        }
                    }
                }

                if (! $found) {
                    $argList = [];

                    // Not found, try the more complicate version. Look
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
                                $obj5->content != ';') {

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
        $this->content .= "class " . $this->cvtCamelCase($className) . "\n{";

        // Put a closing brace at end of file
        $this->root->endContent = "}\n";

        // Cancel out until the semicolon
        $obj = $this;
        do {
            $obj = $obj->next;
            $obj->cancel();
        } while ($obj->content != ';');
    }

    private function killTokenAndWs()
    {
        $this->cancel();
        $obj = $this->next;
        while ($obj instanceof PpiTokenWhitespace) {
            $obj->cancel();
            $obj = $obj->next;
        }
    }

    private function tokenWordMy()
    {
        // If not within a subroutine, probably within class.
        // Change to 'private'.
        if (! $this->isWithinSub()) {
            $this->content = 'private';
            return;
        }

        // Otherwise, kill the 'my'
        $this->killTokenAndWs();

        // Scan ahead and see if there's an initializer. If so, just kill
        // the 'my' and whitespace. Otherwise add an initializer to the
        // variable.

        $peek = $this->peekAhead(3, [ 'skip_ws' => true]);
        if ($peek[1]->content == '=') {
            // Have initializer, we're done.

            return;
        }

        if ($peek[2]->content == ';') {
            $peek[2]->content = " = '';";
        }
        return;
    }


}
