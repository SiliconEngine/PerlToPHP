<?php
/**
 * Base call for all PPI classes.
 */

class PpiElement
{
    public $id;
    public $level;
    public $parent;
    public $children = [];
    public $next;
    public $prev;

    public $content = '';
    public $startContent = '';
    public $endContent = '';

    public $cancel = false;

    /**
     * Default code generator: just use the content string and recursively
     * call the children's converters.
     */
    public function genCode()
    {
        if ($this->cancel) {
            return '';
        }

        $s = $this->startContent . $this->content;
        foreach ($this->children as $child) {
            if (! $child->cancel) {
                $s .= $child->genCode();
            }
        }

        return $s . $this->endContent;
    }

    /**
     * Cancel this element from generating data
     */
    public function cancel()
    {
        $this->cancel = true;
    }

    /**
     * Cancel all objects from current until passed object found.
     */
    public function cancelUntil(
        $last)
    {
        $obj = $this;
        do {
            $obj->cancel();
            $obj = $obj->next;
        } while ($obj !== null && $obj !== $last);

        return;
    }

    /**
     * Peek ahead (n) tokens and return them.
     */
    public function peekAhead(
        $count)
    {
        $obj = $this;
        $list = [];
        while ($count--) {
            if ($obj !== null) {
                $obj = $obj->next;
            }
            $list[] = $obj;
        }
        return $list;
    }

    /**
     * Skip whitespace after this object and return object;
     */
    public function SkipWhitespace()
    {
        $obj = $this;
        while ($obj !== null && $obj instanceof PpiTokenWhitespace) {
            $obj = $obj->next;
        }
        return $obj;
    }

    /**
     * Get next token that isn't whitespace
     */
    public function getNextNonWs()
    {
        $obj = $this;
        do {
            $obj = $obj->next;
        } while ($obj !== null && $obj instanceof PpiTokenWhitespace);

        return $obj;
    }

    /**
     * Check if object is a new line
     */
    public function isNewline()
    {
        return ($this instanceof PpiTokenWhitespace &&
            strpos($this->content, "\n") !== null);
    }


    /**
     * Move until newline found.
     */
    public function findNewline()
    {
        $obj = $this;
        while ($obj !== null && (! ($obj instanceof PpiTokenWhitespace) ||
                        strpos($obj->content, "\n") === false)) {
            $obj = $obj->next;
        }

        return $obj;
    }

    /**
     * Convert underscored name to CamelCase.
     */
    public function cvtCamelCase(
        $name)
    {
        if (strpos($name, '_') !== false) {
            return str_replace(' ', '', ucwords(str_replace('_', ' ',
                strtolower($name))));
        }

        // If no underscores, just leave it alone.
        return $name;
    }

}
