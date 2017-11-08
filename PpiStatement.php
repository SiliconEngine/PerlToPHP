<?php
/**
 * PPI statement nodes.
 *
 * @author          Tim Behrendsen <tim@siliconengine.com>
 * @created         2016-10-14
 */
class PpiStatement extends PpiNode
{
    public function anaContext()
    {
        // Use context of parent
        $this->setContext($this->parent->context);
    }
}


class PpiStatementExpression extends PpiStatement { }
class PpiStatementPackage extends PpiStatement { }
class PpiStatementSub extends PpiStatement { }
class PpiStatementScheduled extends PpiStatementSub { }
class PpiStatementCompound extends PpiStatement { }
class PpiStatementBreak extends PpiStatement { }
class PpiStatementGiven extends PpiStatement { }
class PpiStatementWhen extends PpiStatement { }
class PpiStatementData extends PpiStatement { }
class PpiStatementEnd extends PpiStatement { }
class PpiStatementVariable extends PpiStatementExpression { }
class PpiStatementNull extends PpiStatement { }
class PpiStatementUnmatchedBrace extends PpiStatement { }
class PpiStatementUnknown extends PpiStatement { }

/**
 * Label
 * Nothing to do except convert some unfortunate names that are reserved
 * words in PHP.
 */
class PpiStatementInclude extends PpiStatement
{
    function genCode()
    {
        if (! $this->converted) {
            // Comment out warning directives and don't convert it.
            $peek = $this->peekAhead(2);
            if (in_array($peek[0]->content, ['no', 'use'])
                    && $peek[1]->content == 'warnings') {
                $peek[0]->content = "//{$peek[0]->content}";
                foreach ($this->children as $child) {
                    $child->converted = true;
                }
            }
        }

        return parent::genCode();
    }
}
