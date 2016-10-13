<?php

class PpiStructure extends PpiNode { }
class PpiStructureBlock extends PpiStructure { }
class PpiStructureCondition extends PpiStructure { }
class PpiStructureFor extends PpiStructure { }
class PpiStructureGiven extends PpiStructure { }
class PpiStructureWhen extends PpiStructure { }
class PpiStructureUnknown extends PpiStructure { }



/**
 * Parentheses: functions, lists, etc.
 */
class PpiStructureList extends PpiStructure
{
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

            $type = get_class($prev);
            if (! in_array($type, [
                'PpiTokenWord',
                'PpiTokenSymbol' ])) {
                $this->startContent = '[';
                $this->endContent = ']';
            }
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
    public function genCode()
    {
        if (! $this->converted) {
            $this->startContent = '[';
            $this->endContent = ']';
        }

        return parent::genCode();
    }
}
