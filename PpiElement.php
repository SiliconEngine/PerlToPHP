<?php
/**
 * Base call for all PPI classes.
 */

class PpiElement
{
    public $id;
    public $lineNum;
    public $level;
    public $root;
    public $parent;
    public $children = [];
    public $next;
    public $prev;
    public $nextSibling;
    public $prevSibling;

    public $preWs = '';                 // Whitespace preceding token
    public $endPreWs = '';              // Whitespace preceding end content
    public $content = '';
    public $startContent = '';
    public $endContent = '';

    /**
     * What context the node is in: neutral, array, hash, scalar, string
     */
    public $context = null;

    /**
     * Converter has been run
     */
    public $converted = false;

    /**
     * Whether this node's content should be canceled.
     */
    public $cancel = false;

    /**
     * Default code generator: just mark as converted, and return content.
     */
    public function genCode()
    {
        $this->converted = true;
        return $this->content;
    }

    /**
     * Analyze lexical tree and figure out context for each node
     */
    public function analyzeTreeContext()
    {
        // Try and get the context if we can already determine it
        if ($this->context === null) {
            $this->anaContext();
        }

        foreach ($this->children as $child) {
            if (! $child->cancel) {
                $child->analyzeTreeContext();
            }
        }

        // Try again to get the context if the children gave us a clue
        if ($this->context === null) {
            $this->anaContext();
        }
    }

    public function setContext(
        $context)
    {
        if ($context !== null && $this->context === null) {
            $this->context = $context;
// debug
//            $this->context = $context . '/' . $this->id;
        }
    }

    public function anaContext()
    {
        return;
    }

    /**
     * Set context for node and all unset parent nodes
     */
    public function setContextChain(
        $context)
    {
        $node = $this;
        while ($node !== null && $node->context === null) {
            $node->context = $context;
            $node = $node->parent;
        }
        return;
    }


    /**
     * Get content for node and all children.
     */
    public function getRecursiveContent()
    {
        if (! $this->converted) {
            $this->genCode();
        }

        $s = '';
        if (! $this->cancel) {
            $s = $this->preWs . $this->startContent . $this->content;
        }
        foreach ($this->children as $child) {
            $s .= $child->getRecursiveContent();
        }

        if (! $this->cancel) {
            $s .= $this->endPreWs . $this->endContent;
        }
        return $s;
    }


    /**
     * Cancel this element from generating data
     */
    public function cancel()
    {
        $this->cancel = true;
        $this->converted = true;
    }

    public function uncancel()
    {
        $this->cancel = false;
    }

    /**
     * Cancel all objects from current until passed object found.
     */
    public function cancelUntil(
        $last)
    {
        $obj = $this;
        for(;;) {
            $obj->cancel();
            if ($obj === $last) {
                break;
            }
            $obj = $obj->next;
        }

        return;
    }

    /**
     * cancelAll - Cancel token and all children
     */
    public function cancelAll()
    {
        $this->cancel();
        foreach ($this->children as $child) {
            $child->cancelAll();
        }
    }

    /**
     * Remove content and following white space
     */
    public function killContentAndWs()
    {
        $this->content = '';                // Keep indent whitespace
        for ($obj = $this; $obj = $obj->next; ++$obj) {
            if ($obj instanceof PpiToken || $obj instanceof PpiStructure) {
                $obj->preWs = '';
                break;
            }
        }
        return;
    }


    /**
     * getNextToken() - Return next token or structure
     */
    public function getNextToken()
    {
        $obj = $this;
        while (! ($obj instanceof PpiToken || $obj instanceof PpiStructure)) {
            $obj = $obj->next;
        }

        return $obj;
    }

    /**
     * Peek ahead (n) tokens and return them.
     */
    public function peekAhead(
        $count,
        $options = null)
    {
        $skipWs = isset($options['skip_ws']);
        if ($count == 0) {
            return [];
        }

        $obj = $this;
        $list = [];
        for(;;) {
repeat:
            $obj = $obj->next;
            if ($obj === null) {
                break;
            }

            if ($skipWs && $obj instanceof PpiTokenWhitespace) {
                goto repeat;
            }

            $list[] = $obj;
        } while (--$count);

        return $list;
    }

    /**
     * Peek behind (n) tokens and return them.
     */
    public function peekBehind(
        $count,
        $options = null)
    {
        $skipWs = isset($options['skip_ws']);
        if ($count == 0) {
            return [];
        }

        $obj = $this;
        $list = [];
        for(;;) {
repeat:
            $obj = $obj->prev;
            if ($obj === null) {
                break;
            }

            if ($skipWs && $obj instanceof PpiTokenWhitespace) {
                goto repeat;
            }

            $list[] = $obj;
        } while (--$count);

        return $list;
    }

    /**
     * Quick test for white space or comment
     */
    public function isWs()
    {
        // Note we treat root node as ws, makes things easier
        return ($this instanceof PpiTokenWhitespace
            || $this instanceof PpiTokenComment
            || $this instanceof PpiDocument);
    }

    /**
     * Skip whitespace after this object and return object;
     */
    public function skipWhitespace()
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
     * Get previous token that isn't whitespace
     */
    public function getPrevNonWs()
    {
        $obj = $this;
        do {
            $obj = $obj->prev;
        } while ($obj !== null && $obj instanceof PpiTokenWhitespace);

        return $obj;
    }

    /**
     * Get next sibling that isn't whitespace
     */
    public function getNextSiblingNonWs()
    {
        $obj = $this;
        do {
            if ($obj->nextSibling === null) {
                $obj = $obj->parent;
            } else {
                $obj = $obj->nextSibling;
            }
        } while ($obj !== null && $obj instanceof PpiTokenWhitespace);

        return $obj;
    }

    /**
     * Get previous token that isn't whitespace. If hits front, goes
     * up to parent to continue;
     */
    public function getPrevSiblingNonWs()
    {
        $obj = $this;
        do {
            if ($obj->prevSibling === null) {
                $obj = $obj->parent;
            } else {
                $obj = $obj->prevSibling;
            }
        } while ($obj !== null && $obj instanceof PpiTokenWhitespace);

        return $obj;
    }

    /**
     * Find lowest end leaf of a node's children
     */
    public function getLastLeaf()
    {
        $obj = $this;
        while (count($obj->children)) {
            $obj = end($obj->children);
        }
        return $obj;
    }

    /**
     * Check if object is a new line
     */
    public function isNewline()
    {
        return $this instanceof PpiTokenNewline;
    }

    /**
     * Move until newline found.
     */
    public function findNewline()
    {
        $obj = $this;
        while ($obj !== null && ! ($obj instanceof PpiTokenNewline)) {
            $obj = $obj->next;
        }

        return $obj;
    }


    /**
     * Extract entire line of elements.
     */
    public function isolateLineElements()
    {
        $leftList = [];
        $rightList = [];

        $obj = $this;
        do {
            $obj = $obj->prev;
            $leftList[] = $obj;
        } while (! $obj->isNewline());

        $obj = $this;
        do {
            $obj = $obj->next;
            $rightList[] = $obj;
        } while (! $obj->isNewline());

        $ret = new \stdClass;
        $ret->leftList = array_reverse($leftList);
        $ret->rightList = $rightList;
        return $ret;
    }

    /**
     * Check if node is within a subroutine
     */
    public function isWithinSub()
    {
        $obj = $this;
        while ($obj !== null) {
            if ($obj instanceof PpiStatementSub) {
                return true;
            }
            $obj = $obj->parent;
        }
        return false;
    }

    /**
     * Figure out the indent for the line of the current token.
     */
    public function getIndent()
    {
        // Scan backward for newline
        $obj = $this;
        while ($obj->prev !== null && ! $obj->isNewline()) {
            $obj = $obj->prev;
        }

        // Scan forward and find first token/structure
        do {
            $obj = $obj->next;
        } while (! ($obj instanceof PpiToken || $obj instanceof PpiStructure));

        return strlen($this->tabExpand($obj->preWs));
    }

    /**
     * Expand tab characters in string.
     */
    function tabExpand($s)
    {
        $tabStop = 8;
        while (strpos($s, "\t") !== false) {
            $s = preg_replace_callback('/(.*?)(\t+)/',
                function ($matches) use ($tabStop) {
                    return $matches[1] . str_repeat(' ', strlen($matches[2]) * 
                    $tabStop - strlen($matches[1]) % $tabStop);
                }, $s);
        }

        return $s;
    }

    /**
     * Convert underscored name to CamelCase.
     */
    public function cvtCamelCase(
        $name)
    {
        // Just keep single underscores
        if ($name == '_') {
            return $name;
        }

        if (strpos($name, '_') !== false) {
            return str_replace(' ', '', ucwords(str_replace('_', ' ',
                strtolower($name))));
        }

        // If no underscores, just leave it alone.
        return $name;
    }

    public function cvtPackageName(
        $name)
    {
        $name = str_replace('::', ' ', $name);
        return str_replace(' ', '\\', ucwords($name));
    }


    private $reservedWords = [ 'length', 'setpgrp', 'endgrent', 
        'link', 'setpriority', 'endhostent', 'listen', 'setprotoent', 
        'endnetent', 'local', 'setpwent', 'endprotoent', 'localtime', 
        'setservent', 'endpwent', 'log', 'setsockopt', 'endservent', 
        'lstat', 'shift', 'eof', 'map', 'shmctl', 'eval', 'mkdir', 'shmget', 
        'exec', 'msgctl', 'shmread', 'exists', 'msgget', 'shmwrite', 
        'exit', 'msgrcv', 'shutdown', 'fcntl', 'msgsnd', 'sin', 'fileno', 
        'my', 'sleep', 'flock', 'next', 'socket', 'fork', 'not', 'socketpair', 
        'format', 'oct', 'sort', 'formline', 'open', 'splice', 'getc', 
        'opendir', 'split', 'getgrent', 'ord', 'sprintf', 'getgrgid', 
        'our', 'sqrt', 'getgrnam', 'pack', 'srand', 'gethostbyaddr', 
        'pipe', 'stat', 'gethostbyname', 'pop', 'state', 'gethostent', 
        'pos', 'study', 'getlogin', 'print', 'substr', 'getnetbyaddr', 
        'printf', 'symlink', 'abs', 'getnetbyname', 'prototype', 'syscall', 
        'accept', 'getnetent', 'push', 'sysopen', 'alarm', 'getpeername', 
        'quotemeta', 'sysread', 'atan2', 'getpgrp', 'rand', 'sysseek', 
        'AUTOLOAD', 'getppid', 'read', 'system', 'BEGIN', 'getpriority', 
        'readdir', 'syswrite', 'bind', 'getprotobyname', 'readline', 
        'tell', 'binmode', 'getprotobynumber', 'readlink', 'telldir', 
        'bless', 'getprotoent', 'readpipe', 'tie', 'break', 'getpwent', 
        'recv', 'tied', 'caller', 'getpwnam', 'redo', 'time', 'chdir', 
        'getpwuid', 'ref', 'times', 'CHECK', 'getservbyname', 'rename', 
        'truncate', 'chmod', 'getservbyport', 'require', 'uc', 'chomp', 
        'getservent', 'reset', 'ucfirst', 'chop', 'getsockname', 'return', 
        'umask', 'chown', 'getsockopt', 'reverse', 'undef', 'chr', 'glob', 
        'rewinddir', 'UNITCHECK', 'chroot', 'gmtime', 'rindex', 'unlink', 
        'close', 'goto', 'rmdir', 'unpack', 'closedir', 'grep', 'say', 
        'unshift', 'connect', 'hex', 'scalar', 'untie', 'cos', 'index', 
        'seek', 'use', 'crypt', 'INIT', 'seekdir', 'utime', 'dbmclose', 
        'int', 'select', 'values', 'dbmopen', 'ioctl', 'semctl', 'vec', 
        'defined', 'join', 'semget', 'wait', 'delete', 'keys', 'semop', 
        'waitpid', 'DESTROY', 'kill', 'send', 'wantarray', 'die', 'last', 
        'setgrent', 'warn', 'dump', 'lc', 'sethostent', 'write', 'each',
        'lcfirst', 'setnetent',
        '__DATA__', 'else', 'lock', 'qw', '__END__', 'elsif', 'lt', 'qx',
        '__FILE__', 'eq', 'm', 's', '__LINE__', 'exp', 'ne', 'sub',
        '__PACKAGE__', 'for', 'no', 'tr', 'and', 'foreach', 'or', 'unless',
        'cmp', 'ge', 'package', 'until', 'continue', 'gt', 'q', 'while',
        'CORE', 'if', 'qq', 'xor', 'do', 'le', 'qr', 'y', 'STDERR'
    ];


    public function isReservedWord(
        $word)
    {
        return in_array($word, $this->reservedWords);
    }

    public function fmtObj(
        $level = 0)
    {
        $content = $this->content;
        if ($this->startContent !== '') {
            $content = "[ $this->startContent ] $content";
        }
        if ($this->endContent !== '') {
            if ($this->endPreWs !== '') {
                $s = $this->endPreWs;
                $s = str_replace("\t", '\t', $s);
                $s = str_replace("\n", '\n', $s);
                $content = "$content '$s':";
            }
            $content = "$content [ $this->endContent ]";
        }
        $content = str_replace("\t", '\t', $content);
        $content = str_replace("\n", '\n', $content);

        if ($this->preWs !== '') {
            $s = $this->preWs;
            $s = str_replace("\t", '\t', $s);
            $s = str_replace("\n", '\n', $s);
            $content = "'$s': $content";
        }

        return sprintf('%-12s %-40s   %s', $this->context ?: 'null',
            str_repeat(' ', $level*2) . get_class($this), $content);
    }

    /**
     * Add passed object as our sibling on the right.
     */
    function insertRightSibling(
        $newObj)
    {
        $curNext = $this->next;
        $this->next = $newObj;
        $newObj->prev = $this;
        if ($curNext !== null) {
            $curNext->prev = $newObj;
            $newObj->next = $curNext;
        }

        $curNext = $this->nextSibling;
        $this->nextSibling = $newObj;
        $newObj->prevSibling = $this;
        if ($curNext !== null) {
            $curNext->prevSibling = $newObj;
            $newObj->nextSibling = $curNext;
        }

        // Insert into parent's child list
        $parent = $this->parent;
        for ($i = 0; $i < count($parent->children); ++$i) {
            if ($parent->children[$i] === $this) {
                array_splice($parent->children, $i+1, 0, [$newObj]);
                break;
            }
        }
        $newObj->parent = $parent;

        return;
    }

    /**
     * Delete current object from the lexical tree
     */
    function delete()
    {
        $curNext = $this->next;
        $curPrev = $this->prev;
        if ($curNext !== null) {
            $curNext->prev = $curPrev;
        }
        if ($curPrev !== null) {
            $curPrev->next = $curNext;
        }

        $curNext = $this->nextSibling;
        $curPrev = $this->prevSibling;
        if ($curNext !== null) {
            $curNext->prevSibling = $curPrev;
        }
        if ($curPrev !== null) {
            $curPrev->nextSibling = $curNext;
        }

        // Delete from parent's child list
        $parent = $this->parent;
        for ($i = 0; $i < count($parent->children); ++$i) {
            if ($parent->children[$i] === $this) {
                array_splice($parent->children, $i, 1);
                break;
            }
        }

        return;
    }

}
