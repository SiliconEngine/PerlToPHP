<?php

    require_once('PpiElement.php');
    require_once('PpiNode.php');
    require_once('PpiDocument.php');
    require_once('PpiStatement.php');
    require_once('PpiStructure.php');
    require_once('PpiToken.php');
    require_once('Converter.php');


    $fn = '/tmp/test.pl';

    $cvt = new Converter;
    $cvt->readFile($fn);
    $s = $cvt->dumpStruct();
    file_put_contents('/tmp/ppi.txt', $s);
    $newDoc = $cvt->convert();
    file_put_contents('/tmp/new.php', $newDoc);

