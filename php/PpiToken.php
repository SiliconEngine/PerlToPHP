<?php

class PpiToken extends PpiElement { }
class PpiTokenWhitespace extends PpiToken { }
class PpiTokenComment extends PpiToken { }
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
class PpiTokenOperator extends PpiToken { }
class PpiTokenQuote extends PpiToken { }
class PpiTokenQuoteSingle extends PpiTokenQuote { }
class PpiTokenQuoteDouble extends PpiTokenQuote { }
class PpiTokenQuoteLiteral extends PpiTokenQuote { }
class PpiTokenQuoteInterpolate extends PpiTokenQuote { }
class PpiTokenQuoteLike extends PpiToken { }
class PpiTokenQuoteLikeBacktick extends PpiTokenQuoteLike { }
class PpiTokenQuoteLikeCommand extends PpiTokenQuoteLike { }
class PpiTokenQuoteLikeRegexp extends PpiTokenQuoteLike { }
class PpiTokenQuoteLikeWords extends PpiTokenQuoteLike { }
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


/**
 * Usually a variable name
 */
class PpiTokenSymbol extends PpiToken
{

}




/**
 * Process general token word, this is where a lot of the action takes
 * place.
 */
class PpiTokenWord extends PpiToken
{
    function genCode()
    {
        $word = $this->content;
        $parent = $this->parent;
        switch($word) {
        case 'sub':
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
                }

                $this->content = "function $name(" .
                    implode(', ', $argList) . ")";

            } else {
                throw new \Exception("Bad context " . get_class($parent) .
                    ", Could not convert $word\n");
            }
            break;
        }

        return parent::genCode();
    }
}
