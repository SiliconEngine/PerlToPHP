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
        // Just return
        //return

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
        // Works: @a = (1 + 2, 3)
        // Works: @a = (1, 2, (3 + 4))
        // Shaky: $a = [ (1, 2, (3 + 4)) ] -> [ [1, 2, (3 + 4)] ]
        //          ^^ should be array_merge (TRUE)
        // Shaky: $a = (1, 2, 3)
        // Shaky: $a = [1, 2, (3 + 4) ]
        //      STOP: Brackets become PpiStructureConstructor, need to become
        //          array context. Just look for PpiStucture?
        //          Has a generic PpiStatement in-between.
        //      STOP: Thinking I may not need the comma forcing an array.
        //
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
        //
        // Rules:
        //      1) Brackets are:
        //          a) Always array context that generate a scalar reference.
        //      2) Parenthesis are:
        //          a) Scalar if outside scalar context
        //          b) Array if outside array context


        if ($this->parent instanceof PpiStructureList ||
                    ($this->parent instanceof PpiStatementExpression &&
                    $this->parent->parent instanceof PpiStructureList)) {
            $this->setContext('scalar');
        } else {
            $this->setContext($this->parent->context);
        }
        return;
    }

    public function genCode()
    {
        if (! $this->converted) {
            $prev = $this->getPrevNonWs();
            $nextSib = $this->getNextSiblingNonWs();
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

            // If array context, convert to brackets
            if ($this->context == 'array') {
                $this->startContent = '[';
                $this->endContent = ']';
            }

// old method, not very sophisticated and didn't work well
//            $type = get_class($prev);
//            if (! in_array($type, [
//                'PpiTokenWord',
//                'PpiTokenSymbol' ])) {
//                $this->startContent = '[';
//                $this->endContent = ']';
//            }
        }

        return parent::genCode();
    }
}

class PpiStructureConstructor extends PpiStructure
{
    public function genCode()
    {
        if (! $this->converted) {
            $this->startContent = '[';
            $this->endContent = ']';
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
        $this->setContextChain('array');
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
