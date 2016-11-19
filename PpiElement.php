<?php
/**
 * Base call for all PPI classes.
 */

class PpiElement
{
    public $id;
    public $lineNum;
    public $level;
    public $precedence;                 // Token expression precedence
    public $root;
    public $parent;
    public $children = [];
    public $next;
    public $prev;
    public $nextSibling;
    public $prevSibling;
    public $converter;                  // Main converter object

    public $preWs = '';                 // Whitespace preceding token
    public $endPreWs = '';              // Whitespace preceding end content
    public $content = '';
    public $startContent = '';
    public $endContent = '';

    private $precList = [
        1 => [ '[', '(', '{' ],
        2 => [ '->' ],
        3 => [ '++', '--' ],
        4 => [ '**' ],
        5 => [ '!', '~', '\\', 'and', 'unary', '+', 'and', '-' ],
        6 => [ '=~', '!~' ],
        7 => [ '*', '/', '%', 'x' ],
        8 => [ '+', '-', '.' ],
        9 => [ '<<', '>>' ],
        10 => [ 'named_unary_operators' ],
        11 => [ '<', '>', '<=', '>=', 'lt', 'gt', 'le', 'ge' ],
        12 => [ '==', '!=', '<=>', 'eq', 'ne', 'cmp', '~~' ],
        13 => [ '&' ],
        14 => [ '|', '^' ],
        15 => [ '&&' ],
        16 => [ '||', '//' ],
        17 => [ '..', ' ...' ],
        18 => [ '?:' ],
        19 => [ '=', '+=', '-=', '*=', 'etc.', 'goto', 'last', 'next', 'redo', 'dump' ],
        20 => [ ',', '=>' ],
        21 => [ 'list_operators' ],
        22 => [ 'not' ],
        23 => [ 'and' ],
        24 => [ 'or', 'xor' ],
        25 => [ 'if', 'unless' ],
        30 => [ ';' ]
    ];

    private $phpReservedWords = [
        'abstract', 'and', 'array', 'as', 'break', 'callable', 'case',
        'catch', 'class', 'clone', 'const', 'continue', 'declare',
        'default', 'die', 'do', 'echo', 'else', 'elseif', 'empty',
        'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch',
        'endwhile', 'eval', 'exit', 'extends', 'final', 'finally',
        'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements',
        'include', 'include_once', 'instanceof', 'insteadof', 'interface',
        'isset', 'list', 'namespace', 'new', 'or', 'print', 'private',
        'protected', 'public', 'require', 'require_once', 'return',
        'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use',
        'var', 'while', 'xor', 'yield'
    ];

    private $perlReservedWords = [ 'length', 'setpgrp', 'endgrent', 
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
        // Call all the converters first, otherwise we might grab content
        // before it can be modified by a converter.
        $this->callRecursiveConverters();

        // Get the content recursively.
        return $this->recurseContent();
    }

    private function recurseContent()
    {
        if (! $this->converted) {
            $this->genCode();
        }

        $s = '';
        if (! $this->cancel) {
            $s = $this->preWs . $this->startContent . $this->content;
        }
        foreach ($this->children as $child) {
            $s .= $child->recurseContent();
        }

        if (! $this->cancel) {
            $s .= $this->endPreWs . $this->endContent;
        }
        return $s;
    }

    /**
     * Call converter for node and all children.
     */
    public function callRecursiveConverters()
    {
        $this->genCode();
        foreach ($this->children as $child) {
            $child->callRecursiveConverters();
        }
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
        do {
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
        do {
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
    public function skipWs()
    {
        $obj = $this;
        while ($obj !== null && $obj instanceof PpiTokenWhitespace) {
            $obj = $obj->next;
        }
        return $obj;
    }
    public function skipWhitespace()
    {
        return $this->skipWs();
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
     * Get next sibling that isn't whitespace. If hits end, goes up to
     * parent to continue.
     */
    public function getNextSiblingUpTree()
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
     * Get next sibling that isn't whitespace. Returns null if hit end.
     */
    public function getNextSiblingNonWs()
    {
        $obj = $this;
        do {
            $obj = $obj->nextSibling;
        } while ($obj !== null && $obj instanceof PpiTokenWhitespace);

        return $obj;
    }

    /**
     * Get previous sibling that isn't whitespace. If hits front, goes
     * up to parent to continue.
     */
    public function getPrevSiblingUpTree()
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
     * Get previous sibling that isn't whitespace. If hits front, returns null.
     */
    public function getPrevSiblingNonWs()
    {
        $obj = $this;
        do {
            $obj = $obj->prevSibling;
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
     * Check if node has a semicolon at the end. Note that nodes might
     * have text before the semicolon if they had modifications.
     */
    public function isSemicolon()
    {
        return substr($this->content, -1, 1) == ';';
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
    public function tabExpand($s)
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
     * Convert underscored name to CamelCase. First character is set to lower
     * case. If all uppercase, then is preserved that way.
     */
    public function cvtCamelCase(
        $name)
    {
        // Just keep single underscores
        if ($name == '_') {
            return $name;
        }

        // If all uppercase, leave it alone
        if (strtoupper($name) == $name) {
            return $name;
        }

        if (strpos($name, '_') !== false) {
            return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ',
                strtolower($name)))));
        }

        // If no underscores, just leave it alone.
        return $name;
    }

    public function cvtPackageName(
        $name)
    {
        $words = explode('::', $name);
        $words = array_map(function ($w) {
            if (in_array(strtolower($w), $this->phpReservedWords)) {
                return 'X' . strtolower($w);
            }
            return ucfirst($w);
        }, $words);

        return implode('\\', $words);
    }

    public function isReservedWord(
        $word)
    {
        return in_array($word, $this->perlReservedWords);
    }

    public function isPhpReservedWord(
        $word)
    {
        return in_array($word, $this->phpReservedWords);
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

        return sprintf('%3d%s%-12s %-40s   %s', $this->id,
            $this->cancel ? '-' : ' ', $this->context ?: 'null',
            str_repeat(' ', $level*2) . get_class($this), $content);
    }

    /**
     * Add passed object as our sibling on the right.
     */
    public function insertRightSibling(
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
     * Add passed object as our sibling on the left.
     */
    public function insertLeftSibling(
        $newObj)
    {
        $curPrev = $this->prev;
        $this->prev = $newObj;
        $newObj->next = $this;
        if ($curPrev !== null) {
            $curPrev->next = $newObj;
            $newObj->prev = $curPrev;
        }

        $curPrev = $this->prevSibling;
        $this->prevSibling = $newObj;
        $newObj->nextSibling = $this;
        if ($curPrev !== null) {
            $curPrev->nextSibling = $newObj;
            $newObj->prevSibling = $curPrev;
        }

        // Insert into parent's child list
        $parent = $this->parent;
        for ($i = 0; $i < count($parent->children); ++$i) {
            if ($parent->children[$i] === $this) {
                array_splice($parent->children, $i, 0, [$newObj]);
                break;
            }
        }
        $newObj->parent = $parent;

        return;
    }

    /**
     * Insert text to the left of the token.
     */
    public function insertLeftText(
        $text)
    {
        $obj = Converter::copyNewClass($this, 'PpiToken', true);
        $obj->content = $text;
        $this->insertLeftSibling($obj);
        return $obj;
    }

    /**
     * Insert text to the right of the token.
     */
    public function insertRightText(
        $text)
    {
        $obj = Converter::copyNewClass($this, 'PpiToken', true);
        $obj->content = $text;
        $this->insertRightSibling($obj);
        return $obj;
    }

    /**
     * Delete current object from the lexical tree
     */
    public function delete()
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

    /**
     * Set expression precedence for token into internal variable.
     */
    public function setPrecedence()
    {
        $this->precedence = $this->getPrecedence();
    }

    /**
     * Get expression precedence for token
     */
    public function getPrecedence()
    {
        static $oprPrec = null;
        $listOperators = [ 'sort' ];

        if ($oprPrec === null) {
            $oprPrec = [];
            foreach ($this->precList as $level => $keywords) {
                foreach ($keywords as $word) {
                    $oprPrec[$word] = $level;
                }
            }
        }

        $token = $this->startContent ?: $this->content;
        if ($this instanceof PpiTokenRegexp) {
            $token = '//';
        } elseif (in_array($token, $listOperators)) {
            $token = 'list_operators';
        } elseif (! isset($oprPrec[$token])
                    && preg_match('/^-{0,1}\w+$/', $this->content)) {
            // Bareword, possible function

            $token = 'named_unary_operators';
        }
        return isset($oprPrec[$token]) ? $oprPrec[$token] : 0;
    }

    /**
     * Figure out left argument of this operator by examining the
     * the precedence.
     */
    function getLeftArg(
        $options = [])
    {
//print "({$this->content}) LEFT THIS: {$this->fmtObj()}\n";
//print Converter::dumpStruct($this->root);
        $prec = $this->precedence;
//print "({$this->content}) LEFT PREC: $prec\n";
        $noCancel = ! empty($options['no_cancel']);
        $noTrim = ! empty($options['no_trim']);

        // Scan backward and look for token with higher precedence so we
        // can isolate the argument.
        $scan = $this->prevSibling;
        if ($scan === null) {
            return [ 'start' => null, 'content' => '' ];
        }

        while ($scan->prevSibling !== null
                        && $scan->prevSibling->precedence <= $prec) {
//print "({$this->content}) LEFT SCAN {$scan->prevSibling->precedence}: {$scan->prevSibling->fmtObj()}\n";
            $scan = $scan->prevSibling;
        }
//if ($scan->prevSibling !== null) {
//print "({$this->content}) LEFT STOP {$scan->prevSibling->precedence}: {$scan->prevSibling->fmtObj()}\n";
//} else {
//print "({$this->content}) LEFT STOP NULL\n";
//}
        // Might've scanned back to a newline, skip past it
        while ($scan->isWs()) {
            $scan = $scan->nextSibling;
        }
        $start = $scan;

        // Call all the converters before we gather the content
        for ($scan = $start; $scan !== $this; $scan = $scan->nextSibling) {
            $scan->callRecursiveConverters();
        }

        // Gather the content
        $content = '';
        for ($scan = $start; $scan !== $this; $scan = $scan->nextSibling) {
            $content .= $scan->getRecursiveContent();
            if (! $noCancel) {
                $scan->cancelAll();
            }
        }

        if (! $noTrim) {
            $content = trim($content);
        }

        return [ $content, $start ];
    }

    /**
     * Figure out right argument of this operator by examining the
     * the precedence.
     */
    function getRightArg(
        $options = [])
    {
//print "({$this->content}) RIGHT THIS: {$this->fmtObj()}\n";
//print Converter::dumpStruct($this->root);
        $prec = $this->precedence;
        $noCancel = ! empty($options['no_cancel']);
        $noTrim = ! empty($options['no_trim']);

        // Scan forward and look for token with higher precedence so we
        // can isolate the argument.
        $content = '';
        if ($this->nextSibling === null) {
            return [ 'end' => null, 'content' => '' ];
        }

//print "({$this->content}) RIGHT PREC: $prec\n";
        $scan = $this;
        do {
            $scan = $scan->nextSibling;
//if ($scan->nextSibling !== null) {
//$scanPrec = $scan->nextSibling->precedence;
//print "({$this->content}) RIGHT SCAN $scanPrec: {$scan->fmtObj()}\n";
//}
        } while ($scan->nextSibling !== null
            && $scan->nextSibling->precedence <= $prec);
        $last = $scan;
//print "({$this->content}) RIGHT LAST: {$last->fmtObj()}\n";

//print "({$this->content}) RIGHT CALL CONVERTERS\n";
        // Call all the converters before we gather the text
        $scan = $this;
        do {
            $scan = $scan->nextSibling;
            $scan->callRecursiveConverters();
        } while ($scan !== $last);

//print "({$this->content}) RIGHT GATHER TEXT\n";
        // Gather the text
        $scan = $this;
        do {
            $scan = $scan->nextSibling;
            $content .= $scan->getRecursiveContent();
        } while ($scan !== $last);

//print "({$this->content}) RIGHT CANCEL\n";
        if (! $noCancel) {
            $scan = $this;
            do {
                $scan = $scan->nextSibling;
                $scan->cancelAll();
            } while ($scan !== $last);
        }

        if (! $noTrim) {
            $content = trim($content);
        }

        return [ $content, $last ];
    }

    /**
     * Utility function to remove a set of parentheses or brackets on ends
     */
    public function stripParensOrBrackets(
        $s)
    {
        // Test if there are matching parentheses or brackets around
        // expression.
        return trim(preg_replace('/[\[\(]+(.*)[\]\)]+/s', '\1', trim($s)));
    }
}
