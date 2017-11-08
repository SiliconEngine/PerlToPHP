<?php
/**
 * Perl-to-PHP main converter class.
 *
 * @author          Tim Behrendsen <tim@siliconengine.com>
 * @created         2016-10-14
 */
require_once('PpiElement.php');
require_once('PpiNode.php');
require_once('PpiDocument.php');
require_once('PpiStatement.php');
require_once('PpiStructure.php');
require_once('PpiToken.php');

class Converter
{
    protected $root;
    protected $flatList;
    protected $quietOpt = false;
    protected $verboseOpt = false;

    public function setQuiet(
        $opt)
    {
        $this->quietOpt = $opt;
    }

    public function setVerbose(
        $opt)
    {
        $this->verboseOpt = $opt;
    }

    /**
     * Read perl file and build lexical structure using external perl
     * program, which uses PPI::Dumper.
     */
    public function readFile(
        $fn)
    {
        if (! file_exists($fn)) {
            print "File not found: $fn\n";
            exit(0);
        }

        $path = realpath(dirname(__FILE__));
        $pipe = popen("$path/dumplex.pl $fn", 'r');
        $n = 0;
        $lineNum = 1;

        $this->flatList = [];
        $lastLevel = -1;
        $lastObj = null;
        $parentObj = null;
        $parentStack = [];
        while (($line = fgets($pipe)) !== false) {
            ++$n;

            if (preg_match('/^(\s*)(\S+)\s*(.*)/', rtrim($line), $matches)) {
                $spaces = $matches[1];
                $type = $matches[2];
                $content = $matches[3];
                $level = strlen($spaces) / 2;

                // Convert to PPI class
                $className = str_replace('::', '', $type);
                $className = str_replace('PPI', 'Ppi', $className);
                $obj = new $className;
                $this->flatList[] = $obj;

                if ($this->root === null) {
                    $this->root = $obj;
                }

                $obj->root = $this->root;
                $obj->id = $n;
                $obj->level = $level;
                $obj->lineNum = $lineNum;

                if (strpos($content, '\n') !== false) {
                    ++$lineNum;
                }

                if ($obj instanceof PpiStructure) {
                    // Special content is start/end characters. Format is:
                    // "( ... )", etc.

                    if (strlen($content) != 7) {
                        print "Invalid structure content: $content\n";
                        exit(0);
                    }

                    $obj->startContent = substr($content, 0, 1);
                    $obj->endContent = substr($content, -1, 1);

                } elseif ($obj instanceof PpiTokenWhitespace) {
                    // Whitespace token, translate escaped characters and
                    // remove outer single quotes

                    $obj->content = str_replace('\t', "\t",
                        str_replace('\n', "\n",
                        substr($content, 1, -1)));

                } elseif ($obj instanceof PpiTokenComment) {
                    // Comment token, translate escaped characters and
                    // remove outer single quotes. Might have \\ escaped.

                    $content = str_replace('\\\\', "\001", $content);
                    $content = str_replace('\t', "\t",
                        str_replace('\n', "\n",
                        substr($content, 1, -1)));
                    $obj->content = str_replace("\001", '\\', $content);

                } elseif ($obj instanceof PpiTokenQuoteLikeWords) {
                    // qw() can have embedded newlines.
                    // Note that PPI has a bug with things like:
                    //      @a = qw( abc def \n
                    //          ghi jkl );
                    // It gives us: "qw( abc def \n\n    ghi jlk )".
                    // Might have \\ escaped, which we keep escaped.

                    $content = str_replace('\\\\', "\001", $content);
                    $content = str_replace('\t', "\t",
                        str_replace('\n', "\n",
                        substr($content, 1, -1)));
                    $obj->content = str_replace("\001", '\\\\', $content);

                } else {
                    // Regular content, just remove outer single quotes

                    $obj->content = substr($content, 1, -1);
                }

                if ($level > $lastLevel) {
                    // Children of prior object

                    if ($parentObj !== null) {
                        $parentStack[] = $parentObj;
                    }
                    $parentObj = $lastObj;
                    $lastLevel = $level;

                } elseif ($level < $lastLevel) {
                    // Going back up a level

                    while ($level < $lastLevel) {
                        $parentObj = array_pop($parentStack);
                        --$lastLevel;
                    }
                }

                $obj->parent = $parentObj;
                if ($parentObj === null) {
                    $parentObj = $obj;
                } else {
                    $prior = end($parentObj->children);
                    $parentObj->children[] = $obj;
                    if (! empty($prior)) {
                        $obj->prevSibling = $prior;
                        $prior->nextSibling = $obj;
                    }
                }

                if ($lastObj !== null) {
                    $lastObj->next = $obj;
                }
                $obj->prev = $lastObj;

                // Set expression precedence in object
                $obj->setPrecedence();

                $lastObj = $obj;

            } else {
                print "BAD: $line\n";
            }
        }
        pclose($pipe);
        if (! $this->quietOpt) {
            error_log("$n lines read");
        }
    }

    /**
     * Do whitespace consolidation to simplify conversion. It's a lot
     * easier if we don't have to deal with whether we have whitespace
     * tokens in our tree. Each token has a field with its own leading
     * spaces. This also makes it easier to change indent.
     *
     * Tasks:
     * 1) Eliminate whitespace tokens and put in the next token object.
     * 2) Convert newlines into special newline object
     */
    private function consolidateWhitespace()
    {
        // Recursively scan the tree, doing the processing. We'll
        // maintain a parent stack so we can scan the parents if we need to.

        $parentStack = [];
        $process = null;
        $process = function ($obj) use (&$process, &$parentStack) {
            if ($obj instanceof PpiTokenComment) {
                $this->chgComment($obj);

            } elseif ($obj instanceof PpiTokenWhitespace) {
                $this->chgWhitespace($obj);
            }

            if (count($obj->children)) {
                array_push($parentStack, $obj);
                foreach ($obj->children as $sub) {
                    $process($sub);
                }
                array_pop($parentStack);
            }
        };

        $process($this->root);
    }

    private function chgComment(
        $obj)
    {
        if (! preg_match('/^([ \t]*)(.*)(\n*)(.*)/', $obj->content, $matches)) {
            print "BAD CONTENT: {$obj->content}\n";
            exit(1);
        }

        $spaces = $matches[1];
        $content = $matches[2];
        $nl = $matches[3];
        $rest = $matches[4];
        if ($rest !== '') {
            print "ERROR: comment had spaces after newline: '$obj->content'\n";
            exit(1);
        }

        // Note might have spaces from prior WS token
        $obj->preWs .= $spaces;
        $obj->content = $content;

        if ($nl !== '') {
            // Add a whitespace token after this one.

            $newObj = self::copyNewClass($obj, 'PpiTokenNewline');
            $newObj->preWs = '';
            $newObj->content = "\n";
            $obj->insertRightSibling($newObj);
        }

        return;
    }

    private function chgWhitespace(
        $obj)
    {
        if (! preg_match('/^([ \t]*)(.*)(\n*)(.*)/', $obj->content, $matches)) {
            print "BAD CONTENT: {$obj->content}\n";
            exit(1);
        }

        $spaces = $matches[1];
        $content = $matches[2];
        $nl = $matches[3];
        $rest = $matches[4];
        $isNew = false;

        if ($nl !== '') {
            // Translate object to a PpiTokenNewline

            $newObj = self::copyNewClass($obj, 'PpiTokenNewline');
            $obj->insertRightSibling($newObj);
            $obj->delete();

            $newObj->preWs = $spaces;
            $newObj->content = "\n";

            // If no spaces after newline, we're done
            if ($rest === '') {
                return;
            }

            // Add the spaces to following token
            $obj = $newObj;
            $spaces = $rest;
            $isNew = true;
        }

        // Move whitespace to following PpiToken or PpiStructure. We'll
        // stop at the end of our current list of children. If we hit the
        // end, then the spaces belong to the end of a PpiStructure.

        $last = $obj->parent;
        while (count($last->children)) {
            $last = end($last->children);
        }

        // Determine:
        // 1) Are we already on the last node
        // 2) Is there a PpiToken somewhere through the last node.
        $node = $obj;
        $foundToken = false;
        if ($node !== $last) {
            do {
                $node = $node->next;
                if ($node instanceof PpiToken
                                || $node instanceof PpiStructure) {
                    $node->preWs .= $spaces;
                    if (! $isNew) {
                        $obj->delete();
                    }
                    $foundToken = true;
                    break;
                }
            } while ($node !== $last);
        }

        if (! $foundToken) {
            // If we ran out of tokens, then spaces belong at end of 
            // parent PpiStructure, just before endContent

            for ($node = $obj->parent; $node !== null; $node = $node->parent) {
                if ($node instanceof PpiStructure) {
                    $node->endPreWs .= $spaces;
                    $obj->delete();
                    break;
                }
            }
        }
    }

    /**
     * Create new node with different class and duplicate content.
     */
    static function copyNewClass(
        $obj,
        $newClass,
        $noContent = false)
    {
        $newObj = new $newClass;
	$newObj->id = $obj->id;
	$newObj->lineNum = $obj->lineNum;
	$newObj->level = $obj->level;
	$newObj->root = $obj->root;
        if (! $noContent) {
            $newObj->children = $obj->children;
            $newObj->preWs = $obj->preWs;
            $newObj->endPreWs = $obj->endPreWs;
            $newObj->content = $obj->content;
            $newObj->startContent = $obj->startContent;
            $newObj->endContent = $obj->endContent;
        }
        return $newObj;
    }

    /**
     * Dump out lexical structure for debug purposes.
     */
    static function dumpStruct(
        $obj,
        $level = 0)
    {
        $s = $obj->fmtObj($level) . "\n";
        foreach ($obj->children as $sub) {
            if ($sub->parent !== $obj) {
                print "bad parent!\n";
                exit(0);
            }
            $s .= self::dumpStruct($sub, $level+1);
        }

        return $s;
    }

    /**
     * Start the conversion process
     * @return string  Converted code.
     */
    public function convert(
        $ppiFn = null)
    {
        if (! $this->quietOpt) {
            error_log("Phase 1: Do whitespace consolidation");
        }
        $this->consolidateWhitespace();

        if (! $this->quietOpt) {
            error_log("Phase 2: Analyze lexical structure");
        }
        $this->root->analyzeTreeContext();

        if (! empty($ppiFn)) {
            file_put_contents($ppiFn, self::dumpStruct($this->root));
        }

        if (! $this->quietOpt) {
            error_log("Phase 3: Calling all converters");
        }

        // Step 1: Call all converters
        foreach ($this->flatList as $obj) {
            if ($this->verboseOpt) {
                print "Convert: {$obj->id}\n";
            }
            $obj->genCode();
        }

        if (! empty($ppiFn) && strpos($ppiFn, 'ppi') !== false) {
            $newFn = str_replace('ppi', 'pp2', $ppiFn);
            file_put_contents($newFn, self::dumpStruct($this->root));
        }

        // Recursively generate code
        if (! $this->quietOpt) {
            error_log("Phase 4: Writing source");
        }
        return "<?php\n" . $this->root->getRecursiveContent();
    }


}
