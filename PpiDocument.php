<?php
/**
 * Perl Document Nodes.
 *
 * @author          Tim Behrendsen <tim@siliconengine.com>
 * @created         2016-10-14
 */
class PpiDocument extends PpiNode
{
    public function anaContext()
    {
        $this->setContext('neutral');
    }
}

class PpiDocumentFragment extends PpiDocument { }
