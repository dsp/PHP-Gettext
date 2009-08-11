<?php

include_once "Gettext.php";

$gn = new Gettext_Native("/Users/dsp/dev/python/mercurial/locale/", "hg", "de");
$ge = new Gettext_Extension("/Users/dsp/dev/python/mercurial/locale/", "hg", "de");
var_dump($gn->gettext("a bookmark of this name does not exist"));
var_dump($ge->gettext("a bookmark of this name does not exist"));
var_dump($gn->gettext("a bookmark of this name does not exist") == $ge->gettext("a bookmark of this name does not exist"));
