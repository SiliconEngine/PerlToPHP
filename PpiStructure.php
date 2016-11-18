<?php

class PpiStructure extends PpiNode { }
class PpiStructureGiven extends PpiStructure { }
class PpiStructureWhen extends PpiStructure { }
class PpiStructureUnknown extends PpiStructure { }


/**
 * Block - note that this isn't only code blocks, can by array/hash context.
 */
class PpiStructureBlock extends PpiStructure { }

/**
 * Condition structure
 */
class PpiStructureCondition extends PpiStructure
{
    public function anaContext()
    {
        // Has to be a logical
        $this->setContext('scalar');
    }
}

class PpiStructureFor extends PpiStructure
{
    public function anaContext()
    {
        // For has kind of a list
        $this->setContext('array');
    }
}

/**
 * Parentheses: functions, lists, etc.
 */
class PpiStructureList extends PpiStructure
{
    public function anaContext()
    {
        // Methods we can use:
        //
        // 1) Context is set by sub-elements, which works most of the time.
        // Doesn't work with $a = (1, 2, 3), which is technically a scalar
        //     expression, but comes out as array because of the comma. But
        //     it's a rare case.
        // Works: @a = (1, 2, (3 + 4))
        // Doesn't Work: @a = (1 + 2, 3)
        //
        // 2) The other way to do it is to use the context of the parent.
        // Not sure if this has issues yet.
        // Works: @a = (1 + 2, 3)
        // Doesn't Work: @a = (1, 2, (3 + 4))
        //
        // Shaky: $a = [ (1, 1, (3 + 4)) ] -> $a = [ [1, 1, [3 + 4] ]
        //          This should prob become: $a = array_merge([1, 1, [3 + 4])
        //$this->setContext($this->parent->context);
        //return;
        //
        // 3) Hybrid: We use #2 and get the context of the expression. AND:
        //      a) We make it scalar if the parent is another PpiStructureList.
        //          The logic is that individual elements of a array list are
        //          usually scalars
        //      b) If we get a comma and the list is a scalar, we convert it
        //          it to an array (see comma logic)
        //      c) We can possibly go from this to using array_merge
        //      PROBLEM: Brackets become PpiStructureConstructor, need to
        //          become array context. Just look for PpiStucture?
        //          Has a generic PpiStatement in-between.
        //      PROBLEM: Thinking I may not need the comma forcing an array.
        //
        // *** None of the above worked well
        // New method:
        //
        // Types of brackets:
        //      $a = [ ]
        //          Brackets -> array context to scalar reference
        //      @a = [ ]
        //          Scalar reference -> cast to array (array_merge)
        //      $a = ( )
        //          Scalar expression, keep as parenthesis
        //      @a = ( )
        //          Array context, convert to brackets.
        //      $scalar = [ (1, 2, 3) ]
        //          1. Interior is array context because of brackets.
        //      $scalar = ( (1, 2, 3) )
        //          ...
        //      function(1, 2, 3)
        //          Array context, keep as parenthesis
        //
        // Rules:
        //      1) Brackets are:
        //          a) Always array context that generate a scalar reference.
        //      2) Parenthesis are:
        //          a) Scalar if left is scalar context
        //          b) Array if left is array context
        //      3) Elements within list are scalar
        //      4) Scan backward by sibling rather than up to parent for
        //          context.
        //      5) Special: 'return' is array if has a comma (chk in 'return')
        //      6) Special: foreach $a (@$b)
        //      7) Special: Empty parenthesis () is an empty array.
        //
        // TESTS:
        //    @a = (1 + 2, 3)
        //    @a = (1, 2, (3 + 4))
        //    $a = [ (1, 2, (3 + 4)) ] -> array_merge( [1, 2, (3 + 4)] )
        //    $a = (1, 2, 3)
        //    $a = [1, 2, (3 + 4) ]
        //    $a = [@a, @b] -> $a = array_merge($a, $b)
        //    ($a, $b, $c) = (1, 2, 3) -> list($a, $b, $c) = [1, 2, 3]
        //    my ($a, $b, $c) = (1, 2, 3) -> list($a, $b, $c) = [1, 2, 3]

        $node = $this;
        $context = null;
        for(;;) {
            if ($node->prevSibling === null) {
                $node = $node->parent;
            } else {
                $node = $node->getPrevSiblingNonWs();
            }

            if ($node instanceof PpiTokenSymbol) {
                $firstChild = $node->parent->children[0];
                if ($firstChild instanceof PpiTokenWord
                        && $firstChild->content == 'foreach') {
                    $context = 'array';
                    break;
                }
            }

            if ($node === null || $node->isNewline()) {
                // Hit front of line means it might be a list assignment
                // ($a, $b) = ... Check if list of variables.
                // Note need to skip down past PpiStatementExpression

                $foundNonSymbol = false;
                foreach ($this->next->children as $child) {
                    if (! ($child instanceof PpiTokenSymbol ||
                            $child->content == ',')) {
                        $foundNonSymbol = true;
                        break;
                    }
                }

                if ($foundNonSymbol) {
                    $context = 'scalar';
                } else {
                    $context = 'array';
                }
                break;
            }

            // Skip these until we get something else
            if ($node instanceof PpiStatement ||
                        $node instanceof PpiTokenWhitespace) {
                continue;
            }

            if ($node->context != 'neutral') {
                $context = $node->context;
                break;
            }
        }

        $this->setContext($context);
        return;
    }

    public function genCode()
    {
        if (! $this->converted) {
            $prev = $this->getPrevNonWs();
            $nextSib = $this->getNextSiblingUpTree();
            if ($nextSib !== null && $nextSib->content == '=') {
                // list l-value

                $this->startContent = 'list(';
                return parent::genCode();
            }

            $peek = $this->peekBehind(2, [ 'skip_ws' => true ]);
            if ($peek[0]->content == '->' &&
                    $peek[1] instanceof PpiTokenSymbol) {
                // Looks like anonymous function call: "$var->( )"

                return parent::genCode();
            }

            // If a regular function call, don't change (ex: word('a', 'b') ).
            // Exception: return ('a', 'b')
            if ($this->prev instanceof PpiTokenWord &&
                            $this->prev->content != 'return') {

                // In perl, you can have a comma at the end, but no in PHP.
                // Kill any stray commas.

                if ($this->nextSibling !== null) {
                    $obj = $this->nextSibling->prev;
                    while ($obj !== null && $obj->isWs()) {
                        $obj = $obj->prev;
                    }
                    if ($obj !== null && $obj->content == ',') {
                        $obj->cancel();
                    }
                }

                return parent::genCode();
            }

            // Empty parentheses should be converted to brackets
            if (count($this->children) == 0) {
                $this->startContent = '[';
                $this->endContent = ']';
                return parent::genCode();
            }

            // If array context, convert to brackets
            if (in_array($this->context, ['array', 'hash'])) {
                $this->startContent = '[';
                $this->endContent = ']';
            }
        }

        return parent::genCode();
    }
}

class PpiStructureConstructor extends PpiStructure
{
    public function anaContext()
    {
        $this->setContextChain('array');
    }

    public function genCode()
    {
        if (! $this->converted) {
            $useMerge = false;
            if ($this->startContent == '[') {
                // If first element is an array, then use array_merge
                for ($node = $this->next; $node != null; $node = $node->next) {
                    if (! ($node instanceof PpiStatement || 
                            $node instanceof PpiTokenWhitespace)) {

                        if ($node->context == 'array') {
                            $useMerge = true;
                        }
                        break;
                    }
                }
            }

            if ($useMerge) {
                $this->startContent = '/*check*/array_merge(';
                $this->endContent = ')';
            } else {
                // Might be brackets or braces, make them always brackets
                $this->startContent = '[';
                $this->endContent = ']';
            }
        }

        return parent::genCode();
    }
}

/**
 * Array subscript
 */

class PpiStructureSubscript extends PpiStructure
{
    public function anaContext()
    {
        if ($this->prev instanceof PpiTokenSymbol
                        || $this->prev->content == '->') {
            // Hash subscript
            $this->setContextChain('scalar');
        } else {
            $this->setContextChain('array');
        }
    }

    public function genCode()
    {
        if (! $this->converted) {
            $this->startContent = '[';
            $this->endContent = ']';
        }

        return parent::genCode();
    }
}
