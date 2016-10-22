<?php
class PpiStatement extends PpiNode
{
// alternate method, but having child set parent worked better.
//    public function anaContext()
//    {
//        // Use context of parent
//        $this->setContext($this->parent->context);
//    }
}


class PpiStatementExpression extends PpiStatement { }
class PpiStatementPackage extends PpiStatement { }
class PpiStatementInclude extends PpiStatement { }
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

