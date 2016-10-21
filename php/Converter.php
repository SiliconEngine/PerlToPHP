<?php

class Converter
{
    protected $root;
    protected $flatList;

    function readFile(
        $fn)
    {
        $pipe = popen("perl -e 'use PPI;' -e 'use PpiDumper;' -e 'PpiDumper->new(PPI::Document->new(\"$fn\"))->print;'", 'r');
        $n = 0;

        $this->flatList = [];
        $lastLevel = -1;
        $lastObj = null;
        $parentObj = null;
        $parentStack = [];
        while (($line = fgets($pipe)) !== false) {
            ++$n;
//print "Line $n: $line\n";

            if (preg_match('/^(\s*)(\S+)\s*(.*)/', rtrim($line), $matches)) {
                $spaces = $matches[1];
                $type = $matches[2];
                $content = $matches[3];
                $level = strlen($spaces) / 2;
//                print "$level: [$type] $content\n";

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
                    // Whitepsace token, translate escaped characters and
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

                $lastObj = $obj;

            } else {
                print "BAD: $line\n";
            }
        }
        pclose($pipe);
        print "$n lines read\n";
    }

    public function dumpStruct(
        $obj = null,
        $level = 0)
    {
        if ($obj === null) {
            $obj = $this->root;
        }
        $s = $obj->id . " " . $this->fmtObj($obj, $level) . "\n";
        foreach ($obj->children as $sub) {
            if ($sub->parent !== $obj) {
                print "bad parent!\n";
                exit(0);
            }
            $s .= $this->dumpStruct($sub, $level+1);
        }

        return $s;
    }

    protected function fmtObj(
        $obj,
        $level = 0)
    {
        $content = $obj->content;
        if ($obj->startContent !== '') {
            $content = "[ $obj->startContent ] $content";
        }
        if ($obj->endContent !== '') {
            $content = "$content [ $obj->endContent ]";
        }

        return sprintf('%-40s   %s',
            str_repeat(' ', $level*2) . get_class($obj), $content);
    }

    /**
     * Start the conversion process
     * @return string  Converted code.
     */
    public function convert()
    {
        print "Phase 1: Calling all converters\n";
        // Step 1: Call all converters
        foreach ($this->flatList as $obj) {
            $obj->genCode();
        }

        // Recursively generate code
        print "Phase 2: Writing source\n";
        return "<?php\n" . $this->root->getRecursiveContent();
    }


}
