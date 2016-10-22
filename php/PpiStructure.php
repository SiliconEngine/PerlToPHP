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
        // Two methods we can use:
        // 1) Context is set by sub-elements, which works most of the time.
        // Doesn't work with $a = (1, 2, 3), which is technically a scalar
        // expression, but comes out as array because of the comma. But
        // it's a rare case.
        //
        // 2) The other way to do it is to use the context of the parent.
        // Not sure if this has issues yet.
        $this->setContext($this->parent->context);
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
