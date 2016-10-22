<?php

class PpiDocument extends PpiNode
{
    public function anaContext()
    {
        $this->setContext('neutral');
    }
}

class PpiDocumentFragment extends PpiDocument { }
